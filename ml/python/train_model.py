#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import json
import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_squared_error, r2_score
from imblearn.over_sampling import SMOTE
from joblib import dump
import logging

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('training.log'),
        logging.StreamHandler()
    ]
)

def load_training_data(data_path):
    """
    Load and prepare training data from CSV files
    """
    try:
        # Load sales data
        sales_df = pd.read_csv(os.path.join(data_path, 'sales_data.csv'))
        
        # Load competitor data
        competitor_df = pd.read_csv(os.path.join(data_path, 'competitor_data.csv'))
        
        # Load customer behavior data
        customer_df = pd.read_csv(os.path.join(data_path, 'customer_data.csv'))
        
        # Merge all data
        df = pd.merge(sales_df, competitor_df, on=['product_id', 'date'], how='left')
        df = pd.merge(df, customer_df, on=['product_id', 'date'], how='left')
        
        # Handle missing values
        df = df.fillna({
            'competitor_price': df['price'].mean(),
            'views': 0,
            'add_to_cart': 0,
            'purchases': 0
        })
        
        return df
    except Exception as e:
        logging.error(f"Error loading training data: {str(e)}")
        raise

def prepare_features(df):
    """
    Prepare features for training
    """
    try:
        # Convert date to datetime
        df['date'] = pd.to_datetime(df['date'])
        
        # Extract time-based features
        features = pd.DataFrame()
        features['day_of_week'] = df['date'].dt.dayofweek
        features['month'] = df['date'].dt.month
        features['day_of_month'] = df['date'].dt.day
        features['is_weekend'] = (df['date'].dt.dayofweek >= 5).astype(int)
        
        # Add price-based features
        features['price'] = df['price']
        features['competitor_price'] = df['competitor_price']
        features['price_difference'] = df['price'] - df['competitor_price']
        features['price_ratio'] = df['price'] / df['competitor_price']
        
        # Add demand-based features
        features['sales'] = df['sales']
        features['views'] = df['views']
        features['conversion_rate'] = df['sales'] / df['views'].replace(0, 1)
        
        # Calculate rolling statistics
        for window in [7, 14, 30]:
            features[f'sales_ma_{window}'] = df.groupby('product_id')['sales'].transform(
                lambda x: x.rolling(window=window, min_periods=1).mean()
            )
            features[f'price_ma_{window}'] = df.groupby('product_id')['price'].transform(
                lambda x: x.rolling(window=window, min_periods=1).mean()
            )
        
        return features
        
    except Exception as e:
        logging.error(f"Error preparing features: {str(e)}")
        raise

def train_model(X, y, model_path):
    """
    Train the machine learning model
    """
    try:
        # Split data into training and validation sets
        X_train, X_val, y_train, y_val = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
        
        # Scale features
        scaler = StandardScaler()
        X_train_scaled = scaler.fit_transform(X_train)
        X_val_scaled = scaler.transform(X_val)
        
        # Train model
        model = RandomForestRegressor(
            n_estimators=100,
            max_depth=10,
            min_samples_split=5,
            min_samples_leaf=2,
            random_state=42
        )
        
        model.fit(X_train_scaled, y_train)
        
        # Evaluate model
        y_pred = model.predict(X_val_scaled)
        mse = mean_squared_error(y_val, y_pred)
        r2 = r2_score(y_val, y_pred)
        
        print(f"Model Performance:")
        print(f"MSE: {mse:.4f}")
        print(f"R2 Score: {r2:.4f}")
        
        # Save model and scaler
        joblib.dump(model, os.path.join(model_path, 'model.joblib'))
        joblib.dump(scaler, os.path.join(model_path, 'scaler.joblib'))
        
        return True
    except Exception as e:
        print(f"Error training model: {str(e)}")
        return False

def main():
    """
    Main function to train the model
    """
    # Get command line arguments
    if len(sys.argv) != 3:
        print("Usage: python train_model.py <data_path> <model_path>")
        sys.exit(1)
    
    data_path = sys.argv[1]
    model_path = sys.argv[2]
    
    # Create model directory if it doesn't exist
    os.makedirs(model_path, exist_ok=True)
    
    # Load and prepare data
    print("Loading training data...")
    df = load_training_data(data_path)
    
    # Prepare features
    print("Preparing features...")
    X, y = prepare_features(df)
    
    # Train model
    print("Training model...")
    success = train_model(X, y, model_path)
    
    if success:
        print("Model training completed successfully!")
    else:
        print("Model training failed!")
        sys.exit(1)

if __name__ == "__main__":
    main() 