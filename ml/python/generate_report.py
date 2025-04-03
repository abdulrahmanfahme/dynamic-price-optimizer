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
import matplotlib.pyplot as plt
import seaborn as sns
from jinja2 import Environment, FileSystemLoader

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

def collect_report_data(connection, product_id, start_date, end_date):
    """
    Collect data for report generation
    """
    try:
        # Get sales data
        sales_query = """
            SELECT date, sales_count, revenue
            FROM wp_dpo_sales_data
            WHERE product_id = %s
            AND date BETWEEN %s AND %s
            ORDER BY date
        """
        sales_df = pd.read_sql(sales_query, connection, params=[product_id, start_date, end_date])
        
        # Get competitor price data
        competitor_query = """
            SELECT date, price, competitor_price
            FROM wp_dpo_competitor_prices
            WHERE product_id = %s
            AND date BETWEEN %s AND %s
            ORDER BY date
        """
        competitor_df = pd.read_sql(competitor_query, connection, params=[product_id, start_date, end_date])
        
        # Get customer behavior data
        customer_query = """
            SELECT date, views, add_to_cart, purchases
            FROM wp_dpo_customer_data
            WHERE product_id = %s
            AND date BETWEEN %s AND %s
            ORDER BY date
        """
        customer_df = pd.read_sql(customer_query, connection, params=[product_id, start_date, end_date])
        
        # Get risk analysis data
        risk_query = """
            SELECT date, overall_risk, financial_risk, operational_risk, market_risk
            FROM wp_dpo_risk_analysis
            WHERE product_id = %s
            AND date BETWEEN %s AND %s
            ORDER BY date
        """
        risk_df = pd.read_sql(risk_query, connection, params=[product_id, start_date, end_date])
        
        return {
            'sales': sales_df,
            'competitor': competitor_df,
            'customer': customer_df,
            'risk': risk_df
        }
    except Error as e:
        print(f"Error collecting report data: {str(e)}")
        sys.exit(1)

def generate_charts(data, output_dir):
    """
    Generate charts for the report
    """
    try:
        # Create output directory if it doesn't exist
        os.makedirs(output_dir, exist_ok=True)
        
        # Set style
        plt.style.use('seaborn')
        
        # Sales and Revenue Chart
        plt.figure(figsize=(12, 6))
        plt.plot(data['sales']['date'], data['sales']['sales_count'], label='Sales')
        plt.plot(data['sales']['date'], data['sales']['revenue'], label='Revenue')
        plt.title('Sales and Revenue Over Time')
        plt.xlabel('Date')
        plt.ylabel('Count/Amount')
        plt.legend()
        plt.xticks(rotation=45)
        plt.tight_layout()
        plt.savefig(os.path.join(output_dir, 'sales_revenue.png'))
        plt.close()
        
        # Price Comparison Chart
        plt.figure(figsize=(12, 6))
        plt.plot(data['competitor']['date'], data['competitor']['price'], label='Our Price')
        plt.plot(data['competitor']['date'], data['competitor']['competitor_price'], label='Competitor Price')
        plt.title('Price Comparison Over Time')
        plt.xlabel('Date')
        plt.ylabel('Price')
        plt.legend()
        plt.xticks(rotation=45)
        plt.tight_layout()
        plt.savefig(os.path.join(output_dir, 'price_comparison.png'))
        plt.close()
        
        # Customer Behavior Chart
        plt.figure(figsize=(12, 6))
        plt.plot(data['customer']['date'], data['customer']['views'], label='Views')
        plt.plot(data['customer']['date'], data['customer']['add_to_cart'], label='Add to Cart')
        plt.plot(data['customer']['date'], data['customer']['purchases'], label='Purchases')
        plt.title('Customer Behavior Over Time')
        plt.xlabel('Date')
        plt.ylabel('Count')
        plt.legend()
        plt.xticks(rotation=45)
        plt.tight_layout()
        plt.savefig(os.path.join(output_dir, 'customer_behavior.png'))
        plt.close()
        
        # Risk Analysis Chart
        plt.figure(figsize=(12, 6))
        plt.plot(data['risk']['date'], data['risk']['overall_risk'], label='Overall Risk')
        plt.plot(data['risk']['date'], data['risk']['financial_risk'], label='Financial Risk')
        plt.plot(data['risk']['date'], data['risk']['operational_risk'], label='Operational Risk')
        plt.plot(data['risk']['date'], data['risk']['market_risk'], label='Market Risk')
        plt.title('Risk Analysis Over Time')
        plt.xlabel('Date')
        plt.ylabel('Risk Score')
        plt.legend()
        plt.xticks(rotation=45)
        plt.tight_layout()
        plt.savefig(os.path.join(output_dir, 'risk_analysis.png'))
        plt.close()
        
        return {
            'sales_revenue': 'sales_revenue.png',
            'price_comparison': 'price_comparison.png',
            'customer_behavior': 'customer_behavior.png',
            'risk_analysis': 'risk_analysis.png'
        }
    except Exception as e:
        print(f"Error generating charts: {str(e)}")
        sys.exit(1)

def generate_html_report(data, output_dir):
    """
    Generate HTML report
    """
    try:
        # Create output directory if it doesn't exist
        os.makedirs(output_dir, exist_ok=True)
        
        # Calculate summary statistics
        summary = {
            'total_orders': data['sales']['sales_count'].sum(),
            'total_revenue': data['sales']['revenue'].sum(),
            'avg_order_value': data['sales']['revenue'].sum() / max(1, data['sales']['sales_count'].sum()),
            'conversion_rate': data['customer']['purchases'].sum() / max(1, data['customer']['views'].sum()),
            'price_difference': data['competitor']['price'].mean() - data['competitor']['competitor_price'].mean(),
            'current_risk_level': 'high' if data['risk']['overall_risk'].iloc[-1] > 0.7 else 'medium' if data['risk']['overall_risk'].iloc[-1] > 0.3 else 'low',
            'current_overall_risk': data['risk']['overall_risk'].iloc[-1]
        }
        
        # Generate charts
        charts = generate_charts(data, output_dir)
        
        # Load template
        template_dir = os.path.join(os.path.dirname(__file__), 'templates')
        env = Environment(loader=FileSystemLoader(template_dir))
        template = env.get_template('report.html')
        
        # Render template
        html_content = template.render(
            summary=summary,
            charts=charts,
            now=datetime.now
        )
        
        # Save report
        report_path = os.path.join(output_dir, 'report.html')
        with open(report_path, 'w') as f:
            f.write(html_content)
            
        return report_path
    except Exception as e:
        print(f"Error generating HTML report: {str(e)}")
        sys.exit(1)

def main():
    """
    Main function to generate report
    """
    try:
        if len(sys.argv) != 4:
            print("Usage: python generate_report.py <product_id> <start_date> <end_date>")
            sys.exit(1)
            
        # Get command line arguments
        product_id = sys.argv[1]
        start_date = sys.argv[2]
        end_date = sys.argv[3]
        
        # Validate dates
        try:
            datetime.strptime(start_date, '%Y-%m-%d')
            datetime.strptime(end_date, '%Y-%m-%d')
        except ValueError:
            print("Error: Dates must be in YYYY-MM-DD format")
            sys.exit(1)
            
        # Create output directory
        output_dir = os.path.join(os.path.dirname(__file__), '..', 'reports', f'product_{product_id}')
        os.makedirs(output_dir, exist_ok=True)
        
        # Connect to database
        connection = get_db_connection()
        
        # Collect data
        data = collect_report_data(connection, product_id, start_date, end_date)
        
        # Generate report
        report_path = generate_html_report(data, output_dir)
        
        print(f"Report generated successfully: {report_path}")
        
    except Exception as e:
        print(f"Error in main: {str(e)}")
        sys.exit(1)
    finally:
        if 'connection' in locals():
            connection.close()

if __name__ == "__main__":
    main() 