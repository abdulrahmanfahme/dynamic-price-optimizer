#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import json
import pandas as pd
import numpy as np
import requests
import time
from bs4 import BeautifulSoup
from datetime import datetime
import mysql.connector
from mysql.connector import Error
from urllib.parse import urlparse
import re

def get_db_connection():
    """
    Create database connection using WordPress configuration
    """
    try:
        # Get WordPress root directory from environment variable or use default
        wp_root = os.getenv('WP_ROOT', os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(__file__)))))
        config_file = os.path.join(wp_root, 'wp-config.php')
        
        if not os.path.exists(config_file):
            raise FileNotFoundError(f"WordPress config file not found at: {config_file}")
            
        # Read WordPress configuration
        with open(config_file) as f:
            content = f.read()
            
        # Extract database credentials
        db_name = None
        db_user = None
        db_password = None
        db_host = None
        
        for line in content.split('\n'):
            if "define('DB_NAME'" in line:
                db_name = line.split("'")[3]
            elif "define('DB_USER'" in line:
                db_user = line.split("'")[3]
            elif "define('DB_PASSWORD'" in line:
                db_password = line.split("'")[3]
            elif "define('DB_HOST'" in line:
                db_host = line.split("'")[3]
                
        if not all([db_name, db_user, db_password, db_host]):
            raise ValueError("Missing database credentials in WordPress config")
            
        # Connect to database
        connection = mysql.connector.connect(
            host=db_host,
            user=db_user,
            password=db_password,
            database=db_name
        )
        
        return connection
    except Error as e:
        print(f"Error connecting to database: {str(e)}")
        sys.exit(1)

def validate_url(url):
    """Validate and normalize URL"""
    try:
        parsed = urlparse(url)
        if not all([parsed.scheme, parsed.netloc]):
            return False
        return url
    except:
        return False

def get_competitor_urls(connection):
    """
    Get competitor URLs from database
    """
    try:
        query = """
            SELECT product_id, competitor_url 
            FROM wp_dpo_competitor_urls 
            WHERE is_active = 1
        """
        cursor = connection.cursor(dictionary=True)
        cursor.execute(query)
        urls = cursor.fetchall()
        cursor.close()
        
        # Validate URLs
        valid_urls = []
        for url_data in urls:
            if validate_url(url_data['competitor_url']):
                valid_urls.append(url_data)
            else:
                print(f"Warning: Invalid URL found for product {url_data['product_id']}")
                
        return valid_urls
    except Error as e:
        print(f"Error getting competitor URLs: {str(e)}")
        return []

def scrape_price(url, headers=None):
    """
    Scrape price from competitor website with rate limiting and error handling
    """
    if headers is None:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
    
    try:
        # Add delay to respect rate limiting
        time.sleep(2)
        
        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        
        soup = BeautifulSoup(response.text, 'html.parser')
        
        # Common price selectors
        price_selectors = [
            'span.price',
            'div.price',
            'p.price',
            'span[class*="price"]',
            'div[class*="price"]',
            'p[class*="price"]',
            'span[itemprop="price"]',
            'meta[itemprop="price"]',
            'span[class*="amount"]',
            'div[class*="amount"]'
        ]
        
        # Try each selector
        for selector in price_selectors:
            price_element = soup.select_one(selector)
            if price_element:
                # Extract price text
                price_text = price_element.get_text().strip()
                
                # Remove currency symbols and non-numeric characters
                price_text = re.sub(r'[^\d.,]', '', price_text)
                
                # Handle different decimal separators
                price_text = price_text.replace(',', '.')
                
                try:
                    price = float(price_text)
                    return price
                except ValueError:
                    continue
        
        # If no price found with selectors, try to find any number that looks like a price
        price_pattern = r'\d+[.,]\d{2}'
        price_matches = re.findall(price_pattern, response.text)
        if price_matches:
            try:
                price = float(price_matches[0].replace(',', '.'))
                return price
            except ValueError:
                pass
        
        print(f"Warning: No price found at {url}")
        return None
    except requests.RequestException as e:
        print(f"Error fetching {url}: {str(e)}")
        return None
    except Exception as e:
        print(f"Unexpected error scraping {url}: {str(e)}")
        return None

def update_competitor_prices(connection, prices_df):
    """
    Update competitor prices in database
    """
    try:
        cursor = connection.cursor()
        
        # Prepare data for bulk insert
        values = []
        for _, row in prices_df.iterrows():
            values.append((
                row['product_id'],
                row['competitor_url'],
                row['price'],
                datetime.now()
            ))
        
        # Use REPLACE INTO to handle duplicates
        query = """
            REPLACE INTO wp_dpo_competitor_prices 
            (product_id, competitor_url, price, last_updated)
            VALUES (%s, %s, %s, %s)
        """
        
        cursor.executemany(query, values)
        connection.commit()
        cursor.close()
        
        return True
    except Error as e:
        print(f"Error updating competitor prices: {str(e)}")
        connection.rollback()
        return False

def analyze_competitor_prices(connection):
    """
    Analyze competitor prices and update database
    """
    try:
        # Get competitor URLs
        urls = get_competitor_urls(connection)
        if not urls:
            print("No valid competitor URLs found")
            return False
        
        # Scrape prices
        prices = []
        for url_data in urls:
            price = scrape_price(url_data['competitor_url'])
            if price is not None:
                prices.append({
                    'product_id': url_data['product_id'],
                    'competitor_url': url_data['competitor_url'],
                    'price': price
                })
        
        if not prices:
            print("No prices were successfully scraped")
            return False
        
        # Convert to DataFrame
        prices_df = pd.DataFrame(prices)
        
        # Update database
        success = update_competitor_prices(connection, prices_df)
        if success:
            print(f"Successfully updated {len(prices)} competitor prices")
            return True
        else:
            print("Failed to update competitor prices")
            return False
    except Error as e:
        print(f"Error in competitor price analysis: {str(e)}")
        return False

def main():
    """
    Main function to analyze competitor prices
    """
    # Connect to database
    print("Connecting to database...")
    connection = get_db_connection()
    
    try:
        # Analyze competitor prices
        success = analyze_competitor_prices(connection)
        if not success:
            sys.exit(1)
    except Exception as e:
        print(f"Error during competitor analysis: {str(e)}")
        sys.exit(1)
    finally:
        connection.close()

if __name__ == "__main__":
    main() 