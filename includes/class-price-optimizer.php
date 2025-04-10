<?php
declare(strict_types=1);

namespace DynamicPriceOptimizer;

class PriceOptimizer {
    /**
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load optimization settings
     */
    private function load_settings(): void {
        $this->settings = [
            'min_margin' => get_option('dpo_min_margin', 0.15),
            'max_margin' => get_option('dpo_max_margin', 0.40),
            'max_price_change' => get_option('dpo_max_price_change', 0.20),
            'competitor_weight' => get_option('dpo_competitor_weight', 0.30),
            'historical_weight' => get_option('dpo_historical_weight', 0.25),
            'seasonal_weight' => get_option('dpo_seasonal_weight', 0.15),
            'inventory_weight' => get_option('dpo_inventory_weight', 0.15),
            'demand_weight' => get_option('dpo_demand_weight', 0.15)
        ];
    }

    /**
     * Calculate optimal price based on market data
     */
    public function calculate_optimal_price(
        float $current_price,
        float $cost,
        array $market_data
    ): float {
        // Calculate base price from cost and target margin
        $base_price = $this->calculate_base_price($cost);

        // Calculate price adjustments from various factors
        $competitor_adjustment = $this->calculate_competitor_adjustment(
            $current_price,
            $market_data['competitor_prices']
        );

        $historical_adjustment = $this->calculate_historical_adjustment(
            $market_data['historical_data']
        );

        $seasonal_adjustment = $this->calculate_seasonal_adjustment(
            $market_data['seasonal_factors']
        );

        $inventory_adjustment = $this->calculate_inventory_adjustment(
            $market_data['inventory_levels']
        );

        $demand_adjustment = $this->calculate_demand_adjustment(
            $market_data['demand_trends']
        );

        // Calculate weighted average of adjustments
        $total_adjustment = (
            $competitor_adjustment * $this->settings['competitor_weight'] +
            $historical_adjustment * $this->settings['historical_weight'] +
            $seasonal_adjustment * $this->settings['seasonal_weight'] +
            $inventory_adjustment * $this->settings['inventory_weight'] +
            $demand_adjustment * $this->settings['demand_weight']
        );

        // Apply adjustments to base price
        $optimal_price = $base_price * (1 + $total_adjustment);

        // Ensure price is within allowed range
        $optimal_price = $this->constrain_price($optimal_price, $current_price);

        return round($optimal_price, 2);
    }

    /**
     * Calculate base price from cost and target margin
     */
    private function calculate_base_price(float $cost): float {
        $target_margin = ($this->settings['min_margin'] + $this->settings['max_margin']) / 2;
        return $cost / (1 - $target_margin);
    }

    /**
     * Calculate price adjustment based on competitor prices
     */
    private function calculate_competitor_adjustment(
        float $current_price,
        array $competitor_prices
    ): float {
        if (empty($competitor_prices)) {
            return 0.0;
        }

        $avg_competitor_price = array_sum(array_column($competitor_prices, 'price')) / count($competitor_prices);
        return ($avg_competitor_price - $current_price) / $current_price;
    }

    /**
     * Calculate price adjustment based on historical data
     */
    private function calculate_historical_adjustment(array $historical_data): float {
        if (empty($historical_data)) {
            return 0.0;
        }

        // Calculate average daily revenue
        $total_revenue = array_sum(array_column($historical_data, 'total_revenue'));
        $total_sales = array_sum(array_column($historical_data, 'sales_count'));
        
        if ($total_sales === 0) {
            return 0.0;
        }

        $avg_revenue_per_sale = $total_revenue / $total_sales;
        
        // Compare with current price and calculate adjustment
        return ($avg_revenue_per_sale - $current_price) / $current_price;
    }

    /**
     * Calculate price adjustment based on seasonal factors
     */
    private function calculate_seasonal_adjustment(array $seasonal_factors): float {
        $adjustment = 0.0;

        // Weekend adjustment
        if ($seasonal_factors['is_weekend']) {
            $adjustment += 0.05; // 5% increase on weekends
        }

        // Holiday adjustment
        if ($seasonal_factors['is_holiday']) {
            $adjustment += 0.10; // 10% increase on holidays
        }

        // Season-based adjustment
        switch ($seasonal_factors['season']) {
            case 'summer':
                $adjustment += 0.05;
                break;
            case 'winter':
                $adjustment -= 0.05;
                break;
        }

        // Time of day adjustment
        $hour = $seasonal_factors['time_of_day'];
        if ($hour >= 9 && $hour <= 17) {
            $adjustment += 0.03; // 3% increase during business hours
        }

        return $adjustment;
    }

    /**
     * Calculate price adjustment based on inventory levels
     */
    private function calculate_inventory_adjustment(array $inventory_levels): float {
        $adjustment = 0.0;
        $current_stock = $inventory_levels['current_stock'];
        $low_stock_threshold = $inventory_levels['low_stock_threshold'];

        if ($current_stock <= $low_stock_threshold) {
            $adjustment += 0.10; // 10% increase when stock is low
        }

        if ($inventory_levels['stock_status'] === 'outofstock') {
            $adjustment += 0.15; // 15% increase when out of stock
        }

        return $adjustment;
    }

    /**
     * Calculate price adjustment based on demand trends
     */
    private function calculate_demand_adjustment(array $demand_trends): float {
        if (empty($demand_trends)) {
            return 0.0;
        }

        // Calculate average daily views
        $total_views = array_sum(array_column($demand_trends, 'views'));
        $total_unique_views = array_sum(array_column($demand_trends, 'unique_views'));
        
        if ($total_views === 0) {
            return 0.0;
        }

        // Calculate view-to-sale conversion rate
        $conversion_rate = $total_unique_views / $total_views;

        // Adjust price based on conversion rate
        if ($conversion_rate > 0.05) { // High demand
            return 0.05; // 5% increase
        } elseif ($conversion_rate < 0.02) { // Low demand
            return -0.05; // 5% decrease
        }

        return 0.0;
    }

    /**
     * Constrain price within allowed range
     */
    private function constrain_price(float $optimal_price, float $current_price): float {
        $max_change = $this->settings['max_price_change'];
        $min_price = $current_price * (1 - $max_change);
        $max_price = $current_price * (1 + $max_change);

        return max($min_price, min($max_price, $optimal_price));
    }

    /**
     * Check if price update is allowed
     */
    public function is_price_update_allowed(float $optimal_price, float $current_price): bool {
        $price_change = abs($optimal_price - $current_price) / $current_price;
        return $price_change >= 0.01; // Allow updates if change is at least 1%
    }

    /**
     * Get risk analysis for price change
     */
    public function get_risk_analysis(
        float $current_price,
        float $optimal_price,
        array $market_data
    ): array {
        $price_change = ($optimal_price - $current_price) / $current_price;
        $risks = [];

        if ($price_change > 0.10) {
            $risks[] = [
                'level' => 'high',
                'message' => 'Large price increase might reduce conversions'
            ];
        } elseif ($price_change < -0.10) {
            $risks[] = [
                'level' => 'high',
                'message' => 'Large price decrease might impact profit margins'
            ];
        }

        if ($market_data['inventory_levels']['current_stock'] <= 5) {
            $risks[] = [
                'level' => 'medium',
                'message' => 'Low inventory might affect customer satisfaction'
            ];
        }

        if (empty($market_data['competitor_prices'])) {
            $risks[] = [
                'level' => 'medium',
                'message' => 'No competitor data available for price comparison'
            ];
        }

        return $risks;
    }
} 