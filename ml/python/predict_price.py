#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import json
import pandas as pd
import numpy as np
from joblib import load
from datetime import datetime

def validate_input_data(product_data):
    """Validate input data structure and values"""
    required_fields = [
        'date',
        'price',
        'competitor_price',
        'sales',
        'views',
        'stock_level',
        'max_stock'
    ]
    
    # Check required fields
    for field in required_fields:
        if field not in product_data:
            raise ValueError(f"Missing required field: {field}")
    
    # Validate data types and ranges
    try:
        # Date validation
        datetime.strptime(product_data['date'], '%Y-%m-%d')
        
        # Price validation
        price = float(product_data['price'])
        if price <= 0:
            raise ValueError("Price must be greater than 0")
            
        # Competitor price validation
        competitor_price = float(product_data['competitor_price'])
        if competitor_price <= 0:
            raise ValueError("Competitor price must be greater than 0")
            
        # Sales validation
        sales = int(product_data['sales'])
        if sales < 0:
            raise ValueError("Sales count cannot be negative")
            
        # Views validation
        views = int(product_data['views'])
        if views < 0:
            raise ValueError("Views count cannot be negative")
            
        # Stock validation
        stock_level = int(product_data['stock_level'])
        max_stock = int(product_data['max_stock'])
        if stock_level < 0 or max_stock < 0:
            raise ValueError("Stock levels cannot be negative")
        if stock_level > max_stock:
            raise ValueError("Current stock level cannot exceed maximum stock")
            
        return True
        
    except ValueError as e:
        raise ValueError(f"Invalid data format: {str(e)}")
    except Exception as e:
        raise ValueError(f"Unexpected error validating data: {str(e)}")

def load_model(model_path):
    """Load trained model and scaler"""
    try:
        if not os.path.exists(model_path):
            raise FileNotFoundError(f"Model file not found: {model_path}")
            
        model = load(model_path)
        scaler = load(os.path.join(os.path.dirname(model_path), 'scaler.joblib'))
        
        return model, scaler
        
    except Exception as e:
        print(f"Error loading model: {str(e)}")
        sys.exit(1)

def prepare_features(product_data):
    """Prepare features for prediction"""
    try:
        # Validate input data
        validate_input_data(product_data)
        
        # Extract time-based features
        date = datetime.strptime(product_data['date'], '%Y-%m-%d')
        features = {
            'day_of_week': date.weekday(),
            'month': date.month,
            'day_of_month': date.day,
            'is_weekend': int(date.weekday() >= 5)
        }
        
        # Add price-based features
        features.update({
            'price': float(product_data['price']),
            'competitor_price': float(product_data['competitor_price']),
            'price_difference': float(product_data['price']) - float(product_data['competitor_price']),
            'price_ratio': float(product_data['price']) / float(product_data['competitor_price'])
        })
        
        # Add demand-based features
        features.update({
            'sales': int(product_data['sales']),
            'views': int(product_data['views']),
            'conversion_rate': int(product_data['sales']) / max(1, int(product_data['views']))
        })
        
        # Add stock-based features
        features.update({
            'stock_level': int(product_data['stock_level']),
            'max_stock': int(product_data['max_stock']),
            'stock_ratio': int(product_data['stock_level']) / max(1, int(product_data['max_stock']))
        })
        
        # Convert to DataFrame
        X = pd.DataFrame([features])
        
        return X
        
    except Exception as e:
        print(f"Error preparing features: {str(e)}")
        sys.exit(1)

def predict_price(model, scaler, X):
    """Make price prediction"""
    try:
        # Scale features
        X_scaled = scaler.transform(X)
        
        # Make prediction
        prediction = model.predict(X_scaled)[0]
        
        # Get feature importance
        importance = dict(zip(X.columns, model.feature_importances_))
        
        return {
            'predicted_price': float(prediction),
            'feature_importance': importance
        }
        
    except Exception as e:
        print(f"Error making prediction: {str(e)}")
        sys.exit(1)

def main():
    try:
        if len(sys.argv) != 3:
            print("Usage: python predict_price.py <model_path> <product_data_json>")
            sys.exit(1)
            
        # Get command line arguments
        model_path = sys.argv[1]
        product_data = json.loads(sys.argv[2])
        
        # Load model and scaler
        model, scaler = load_model(model_path)
        
        # Prepare features
        X = prepare_features(product_data)
        
        # Make prediction
        result = predict_price(model, scaler, X)
        
        # Output results
        print(json.dumps(result, indent=2))
        
    except Exception as e:
        print(f"Error in main: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main() 