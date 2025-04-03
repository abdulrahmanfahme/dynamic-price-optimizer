#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import json
import pandas as pd
from datetime import datetime, timedelta
import mysql.connector
from mysql.connector import Error

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
    except Exception as e:
        print(f"Error connecting to database: {str(e)}")
        sys.exit(1)

def collect_sales_data(connection, start_date, end_date):
    """
    Collect sales data from WooCommerce orders
    """
    query = """
    SELECT 
        p.ID as product_id,
        DATE(o.post_date) as date,
        COUNT(DISTINCT o.ID) as sales,
        SUM(oi.meta_value) as revenue,
        AVG(oi.meta_value) as avg_order_value
    FROM wp_posts p
    JOIN wp_postmeta pm ON p.ID = pm.post_id
    JOIN wp_woocommerce_order_items oi ON pm.meta_value = oi.order_id
    JOIN wp_posts o ON oi.order_id = o.ID
    WHERE p.post_type = 'product'
    AND o.post_type = 'shop_order'
    AND o.post_status = 'wc-completed'
    AND o.post_date BETWEEN %s AND %s
    GROUP BY p.ID, DATE(o.post_date)
    """
    
    try:
        df = pd.read_sql(query, connection, params=[start_date, end_date])
        return df
    except Error as e:
        print(f"Error collecting sales data: {str(e)}")
        return None

def collect_competitor_data(connection, start_date, end_date):
    """
    Collect competitor price data
    """
    query = """
    SELECT 
        p.ID as product_id,
        DATE(cp.date) as date,
        AVG(cp.price) as competitor_price,
        MIN(cp.price) as min_competitor_price,
        MAX(cp.price) as max_competitor_price,
        STD(cp.price) as competitor_price_std
    FROM wp_posts p
    JOIN wp_dpo_competitor_prices cp ON p.ID = cp.product_id
    WHERE p.post_type = 'product'
    AND cp.date BETWEEN %s AND %s
    GROUP BY p.ID, DATE(cp.date)
    """
    
    try:
        df = pd.read_sql(query, connection, params=[start_date, end_date])
        return df
    except Error as e:
        print(f"Error collecting competitor data: {str(e)}")
        return None

def collect_customer_data(connection, start_date, end_date):
    """
    Collect customer behavior data
    """
    query = """
    SELECT 
        p.ID as product_id,
        DATE(v.date) as date,
        COUNT(DISTINCT v.session_id) as views,
        COUNT(DISTINCT CASE WHEN v.event_type = 'add_to_cart' THEN v.session_id END) as add_to_cart,
        COUNT(DISTINCT CASE WHEN v.event_type = 'purchase' THEN v.session_id END) as purchases
    FROM wp_posts p
    JOIN wp_dpo_product_views v ON p.ID = v.product_id
    WHERE p.post_type = 'product'
    AND v.date BETWEEN %s AND %s
    GROUP BY p.ID, DATE(v.date)
    """
    
    try:
        df = pd.read_sql(query, connection, params=[start_date, end_date])
        return df
    except Error as e:
        print(f"Error collecting customer data: {str(e)}")
        return None

def collect_product_data(connection):
    """
    Collect product information
    """
    query = """
    SELECT 
        p.ID as product_id,
        pm_price.meta_value as price,
        pm_stock.meta_value as stock_level,
        pm_manage_stock.meta_value as manage_stock,
        pm_stock_status.meta_value as stock_status
    FROM wp_posts p
    LEFT JOIN wp_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
    LEFT JOIN wp_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
    LEFT JOIN wp_postmeta pm_manage_stock ON p.ID = pm_manage_stock.post_id AND pm_manage_stock.meta_key = '_manage_stock'
    LEFT JOIN wp_postmeta pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
    WHERE p.post_type = 'product'
    """
    
    try:
        df = pd.read_sql(query, connection)
        return df
    except Error as e:
        print(f"Error collecting product data: {str(e)}")
        return None

def main():
    """
    Main function to collect training data
    """
    try:
        if len(sys.argv) != 3:
            print("Usage: python collect_data.py <start_date> <end_date>")
            sys.exit(1)
            
        start_date = sys.argv[1]
        end_date = sys.argv[2]
        
        # Validate dates
        try:
            datetime.strptime(start_date, '%Y-%m-%d')
            datetime.strptime(end_date, '%Y-%m-%d')
        except ValueError:
            print("Error: Dates must be in YYYY-MM-DD format")
            sys.exit(1)
            
        # Create data directory if it doesn't exist
        data_dir = os.path.join(os.path.dirname(__file__), '..', 'data')
        os.makedirs(data_dir, exist_ok=True)
        
        # Connect to database
        connection = get_db_connection()
        
        # Collect data
        sales_data = collect_sales_data(connection, start_date, end_date)
        competitor_data = collect_competitor_data(connection, start_date, end_date)
        customer_data = collect_customer_data(connection, start_date, end_date)
        product_data = collect_product_data(connection)
        
        # Save data to CSV files
        sales_data.to_csv(os.path.join(data_dir, 'sales_data.csv'), index=False)
        competitor_data.to_csv(os.path.join(data_dir, 'competitor_data.csv'), index=False)
        customer_data.to_csv(os.path.join(data_dir, 'customer_data.csv'), index=False)
        product_data.to_csv(os.path.join(data_dir, 'product_data.csv'), index=False)
        
        print(f"Data collection completed successfully. Results saved to {data_dir}")
        
    except Exception as e:
        print(f"Error in data collection: {str(e)}")
        sys.exit(1)
    finally:
        if 'connection' in locals():
            connection.close()

if __name__ == "__main__":
    main() 