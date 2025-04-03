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

def collect_risk_data(connection, start_date, end_date):
    """
    Collect risk-related data
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
        STD(cp.price) as competitor_price_std,
        pm_stock.meta_value as stock_level,
        pm_cost.meta_value as cost,
        pm_markup.meta_value as markup_percentage
    FROM wp_posts p
    JOIN wp_postmeta pm ON p.ID = pm.post_id
    JOIN wp_woocommerce_order_items oi ON pm.meta_value = oi.order_id
    JOIN wp_posts o ON oi.order_id = o.ID
    LEFT JOIN wp_dpo_competitor_prices cp ON p.ID = cp.product_id AND DATE(cp.date) = DATE(o.post_date)
    LEFT JOIN wp_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
    LEFT JOIN wp_postmeta pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_cost'
    LEFT JOIN wp_postmeta pm_markup ON p.ID = pm_markup.post_id AND pm_markup.meta_key = '_dpo_markup_percentage'
    WHERE p.post_type = 'product'
    AND o.post_type = 'shop_order'
    AND o.post_date BETWEEN %s AND %s
    GROUP BY p.ID, p.post_title, DATE(o.post_date)
    """
    
    try:
        df = pd.read_sql(query, connection, params=[start_date, end_date])
        return df
    except Error as e:
        print(f"Error collecting risk data: {str(e)}")
        return None

def analyze_risk_factors(df):
    """
    Analyze risk factors
    """
    # Calculate financial risk
    df['profit_margin'] = (df['avg_order_value'] - df['cost']) / df['avg_order_value']
    df['price_volatility'] = df.groupby('product_id')['avg_order_value'].transform(
        lambda x: x.rolling(window=30, min_periods=1).std()
    )
    df['competitor_price_volatility'] = df.groupby('product_id')['competitor_price'].transform(
        lambda x: x.rolling(window=30, min_periods=1).std()
    )
    
    # Calculate operational risk
    df['stock_risk'] = 1 - (df['stock_level'] / df.groupby('product_id')['stock_level'].transform('max'))
    df['order_cancellation_rate'] = df['cancelled_orders'] / df['orders']
    df['order_refund_rate'] = df['refunded_orders'] / df['orders']
    
    # Calculate market risk
    df['price_competitiveness'] = df['avg_order_value'] / df['competitor_price']
    df['market_share'] = df['orders'] / df.groupby('date')['orders'].transform('sum')
    df['revenue_share'] = df['revenue'] / df.groupby('date')['revenue'].transform('sum')
    
    # Calculate demand risk
    df['demand_volatility'] = df.groupby('product_id')['orders'].transform(
        lambda x: x.rolling(window=30, min_periods=1).std()
    )
    df['seasonality'] = df.groupby('product_id')['orders'].transform(
        lambda x: x.rolling(window=90, min_periods=1).std() / x.rolling(window=90, min_periods=1).mean()
    )
    
    # Calculate overall risk score
    df['financial_risk'] = (
        (1 - df['profit_margin']) * 0.3 +
        df['price_volatility'] * 0.2 +
        df['competitor_price_volatility'] * 0.2 +
        df['order_cancellation_rate'] * 0.15 +
        df['order_refund_rate'] * 0.15
    )
    
    df['operational_risk'] = (
        df['stock_risk'] * 0.4 +
        df['order_cancellation_rate'] * 0.3 +
        df['order_refund_rate'] * 0.3
    )
    
    df['market_risk'] = (
        (1 - df['price_competitiveness']) * 0.3 +
        (1 - df['market_share']) * 0.3 +
        (1 - df['revenue_share']) * 0.4
    )
    
    df['demand_risk'] = (
        df['demand_volatility'] * 0.5 +
        df['seasonality'] * 0.5
    )
    
    df['overall_risk'] = (
        df['financial_risk'] * 0.3 +
        df['operational_risk'] * 0.2 +
        df['market_risk'] * 0.3 +
        df['demand_risk'] * 0.2
    )
    
    # Calculate risk level
    df['risk_level'] = pd.cut(
        df['overall_risk'],
        bins=[0, 0.3, 0.6, 1.0],
        labels=['low', 'medium', 'high']
    )
    
    return df

def update_risk_analysis(connection, analysis_df):
    """
    Update risk analysis in database
    """
    query = """
    INSERT INTO wp_dpo_risk_analysis 
    (product_id, date, profit_margin, price_volatility, competitor_price_volatility,
     stock_risk, order_cancellation_rate, order_refund_rate,
     price_competitiveness, market_share, revenue_share,
     demand_volatility, seasonality,
     financial_risk, operational_risk, market_risk, demand_risk,
     overall_risk, risk_level) 
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    
    try:
        cursor = connection.cursor()
        for _, row in analysis_df.iterrows():
            cursor.execute(query, (
                row['product_id'],
                row['date'],
                row['profit_margin'],
                row['price_volatility'],
                row['competitor_price_volatility'],
                row['stock_risk'],
                row['order_cancellation_rate'],
                row['order_refund_rate'],
                row['price_competitiveness'],
                row['market_share'],
                row['revenue_share'],
                row['demand_volatility'],
                row['seasonality'],
                row['financial_risk'],
                row['operational_risk'],
                row['market_risk'],
                row['demand_risk'],
                row['overall_risk'],
                row['risk_level']
            ))
        connection.commit()
        cursor.close()
        return True
    except Error as e:
        print(f"Error updating risk analysis: {str(e)}")
        return False

def main():
    """
    Main function to analyze risk factors
    """
    try:
        if len(sys.argv) != 3:
            print("Usage: python analyze_risk.py <start_date> <end_date>")
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
        risk_data = collect_risk_data(connection, start_date, end_date)
        analysis_df = analyze_risk_factors(risk_data)
        
        # Save results
        output_file = os.path.join(data_dir, 'risk_analysis.csv')
        analysis_df.to_csv(output_file, index=False)
        
        # Update database
        update_risk_analysis(connection, analysis_df)
        
        print(f"Risk analysis completed successfully. Results saved to {output_file}")
        
    except Exception as e:
        print(f"Error in risk analysis: {str(e)}")
        sys.exit(1)
    finally:
        if 'connection' in locals():
            connection.close()

if __name__ == "__main__":
    main() 