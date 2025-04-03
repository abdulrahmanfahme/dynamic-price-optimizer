#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import json
import pandas as pd
import numpy as np
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

def collect_market_data(connection, start_date, end_date):
    """
    Collect market data
    """
    query = """
    SELECT 
        p.ID as product_id,
        p.post_title as product_name,
        DATE(o.post_date) as date,
        COUNT(DISTINCT o.ID) as orders,
        SUM(oi.meta_value) as revenue,
        AVG(oi.meta_value) as avg_order_value,
        COUNT(DISTINCT CASE WHEN o.post_status = 'wc-completed' THEN o.ID END) as completed_orders,
        COUNT(DISTINCT CASE WHEN o.post_status = 'wc-cancelled' THEN o.ID END) as cancelled_orders,
        COUNT(DISTINCT CASE WHEN o.post_status = 'wc-refunded' THEN o.ID END) as refunded_orders,
        AVG(cp.price) as competitor_price,
        MIN(cp.price) as min_competitor_price,
        MAX(cp.price) as max_competitor_price,
        STD(cp.price) as competitor_price_std
    FROM wp_posts p
    JOIN wp_postmeta pm ON p.ID = pm.post_id
    JOIN wp_woocommerce_order_items oi ON pm.meta_value = oi.order_id
    JOIN wp_posts o ON oi.order_id = o.ID
    LEFT JOIN wp_dpo_competitor_prices cp ON p.ID = cp.product_id AND DATE(cp.date) = DATE(o.post_date)
    WHERE p.post_type = 'product'
    AND o.post_type = 'shop_order'
    AND o.post_date BETWEEN %s AND %s
    GROUP BY p.ID, p.post_title, DATE(o.post_date)
    """
    
    try:
        df = pd.read_sql(query, connection, params=[start_date, end_date])
        return df
    except Error as e:
        print(f"Error collecting market data: {str(e)}")
        return None

def analyze_market_trends(df):
    """
    Analyze market trends
    """
    # Calculate daily metrics
    df['daily_orders'] = df.groupby(['product_id', 'date'])['orders'].transform('sum')
    df['daily_revenue'] = df.groupby(['product_id', 'date'])['revenue'].transform('sum')
    df['daily_avg_order_value'] = df.groupby(['product_id', 'date'])['avg_order_value'].transform('mean')
    
    # Calculate price competitiveness
    df['price_competitiveness'] = df['competitor_price'] / df['avg_order_value']
    df['price_advantage'] = (df['max_competitor_price'] - df['avg_order_value']) / df['max_competitor_price']
    
    # Calculate market share indicators
    df['order_share'] = df['orders'] / df.groupby('date')['orders'].transform('sum')
    df['revenue_share'] = df['revenue'] / df.groupby('date')['revenue'].transform('sum')
    
    # Calculate trend indicators
    for window in [7, 14, 30]:
        df[f'orders_ma_{window}'] = df.groupby('product_id')['orders'].transform(
            lambda x: x.rolling(window=window, min_periods=1).mean()
        )
        df[f'revenue_ma_{window}'] = df.groupby('product_id')['revenue'].transform(
            lambda x: x.rolling(window=window, min_periods=1).mean()
        )
        df[f'price_ma_{window}'] = df.groupby('product_id')['avg_order_value'].transform(
            lambda x: x.rolling(window=window, min_periods=1).mean()
        )
    
    # Calculate market volatility
    df['price_volatility'] = df.groupby('product_id')['avg_order_value'].transform(
        lambda x: x.rolling(window=30, min_periods=1).std()
    )
    df['demand_volatility'] = df.groupby('product_id')['orders'].transform(
        lambda x: x.rolling(window=30, min_periods=1).std()
    )
    
    # Calculate market opportunity score
    df['market_opportunity'] = (
        df['price_advantage'] * 0.3 +
        df['order_share'] * 0.3 +
        (1 - df['price_volatility']) * 0.2 +
        (1 - df['demand_volatility']) * 0.2
    )
    
    return df

def update_market_analysis(connection, analysis_df):
    """
    Update market analysis in database
    """
    query = """
    INSERT INTO wp_dpo_market_analysis 
    (product_id, date, daily_orders, daily_revenue, daily_avg_order_value,
     price_competitiveness, price_advantage, order_share, revenue_share,
     orders_ma_7, orders_ma_14, orders_ma_30,
     revenue_ma_7, revenue_ma_14, revenue_ma_30,
     price_ma_7, price_ma_14, price_ma_30,
     price_volatility, demand_volatility, market_opportunity) 
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    
    try:
        cursor = connection.cursor()
        for _, row in analysis_df.iterrows():
            cursor.execute(query, (
                row['product_id'],
                row['date'],
                row['daily_orders'],
                row['daily_revenue'],
                row['daily_avg_order_value'],
                row['price_competitiveness'],
                row['price_advantage'],
                row['order_share'],
                row['revenue_share'],
                row['orders_ma_7'],
                row['orders_ma_14'],
                row['orders_ma_30'],
                row['revenue_ma_7'],
                row['revenue_ma_14'],
                row['revenue_ma_30'],
                row['price_ma_7'],
                row['price_ma_14'],
                row['price_ma_30'],
                row['price_volatility'],
                row['demand_volatility'],
                row['market_opportunity']
            ))
        connection.commit()
        cursor.close()
        return True
    except Error as e:
        print(f"Error updating market analysis: {str(e)}")
        return False

def main():
    """
    Main function to analyze market trends
    """
    try:
        if len(sys.argv) != 3:
            print("Usage: python analyze_market.py <start_date> <end_date>")
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
        
        # Collect and analyze data
        market_data = collect_market_data(connection, start_date, end_date)
        analysis_df = analyze_market_trends(market_data)
        
        # Save results
        output_file = os.path.join(data_dir, 'market_analysis.csv')
        analysis_df.to_csv(output_file, index=False)
        
        # Update database
        update_market_analysis(connection, analysis_df)
        
        print(f"Market analysis completed successfully. Results saved to {output_file}")
        
    except Exception as e:
        print(f"Error in market analysis: {str(e)}")
        sys.exit(1)
    finally:
        if 'connection' in locals():
            connection.close()

if __name__ == "__main__":
    main() 