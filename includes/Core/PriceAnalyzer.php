<?php
namespace DynamicPriceOptimizer\Core;

use DynamicPriceOptimizer\Core\MLEngine;

/**
 * Handles price analysis and optimization
 */
class PriceAnalyzer {
    /**
     * ML Engine instance
     *
     * @var MLEngine
     */
    private $ml_engine;

    /**
     * Constructor
     */
    public function __construct() {
        $this->ml_engine = new MLEngine();
    }

    /**
     * Analyze product and get price recommendations
     *
     * @param \WC_Product $product
     * @return array
     */
    public function analyze_product($product) {
        $product_id = $product->get_id();
        $current_price = $product->get_price();
        $optimization_settings = get_post_meta($product_id, '_dpo_optimization_settings', true);
        $min_markup = isset($optimization_settings['min_markup']) ? $optimization_settings['min_markup'] : 10;
        $max_markup = isset($optimization_settings['max_markup']) ? $optimization_settings['max_markup'] : 200;

        // Get market analysis
        $market_analysis = $this->get_market_analysis($product_id);
        
        // Get risk analysis
        $risk_analysis = $this->get_risk_analysis($product_id);
        
        // Get customer behavior
        $customer_behavior = $this->get_customer_behavior($product_id);
        
        // Get competitor prices
        $competitor_prices = $this->get_competitor_prices($product_id);

        // Calculate optimal price using ML model
        $optimal_price = $this->ml_engine->predict_price(array(
            'product_id' => $product_id,
            'current_price' => $current_price,
            'market_analysis' => $market_analysis,
            'risk_analysis' => $risk_analysis,
            'customer_behavior' => $customer_behavior,
            'competitor_prices' => $competitor_prices
        ));

        // Apply markup constraints
        $cost = $this->calculate_product_cost($product);
        $min_price = $cost * (1 + ($min_markup / 100));
        $max_price = $cost * (1 + ($max_markup / 100));
        
        $optimal_price = max($min_price, min($max_price, $optimal_price));

        return array(
            'current_price' => $current_price,
            'optimal_price' => $optimal_price,
            'price_difference' => $optimal_price - $current_price,
            'price_change_percentage' => (($optimal_price - $current_price) / $current_price) * 100,
            'market_analysis' => $market_analysis,
            'risk_analysis' => $risk_analysis,
            'customer_behavior' => $customer_behavior,
            'competitor_prices' => $competitor_prices,
            'cost' => $cost,
            'min_price' => $min_price,
            'max_price' => $max_price
        );
    }

    /**
     * Update product price
     *
     * @param \WC_Product $product
     * @return bool
     */
    public function update_product_price($product) {
        $analysis = $this->analyze_product($product);
        
        if ($analysis['price_difference'] != 0) {
            $product->set_price($analysis['optimal_price']);
            $product->save();
            
            // Log price update
            $this->log_price_update($product->get_id(), $analysis);
            
            return true;
        }
        
        return false;
    }

    /**
     * Get market analysis for a product
     *
     * @param int $product_id
     * @return array
     */
    private function get_market_analysis($product_id) {
        global $wpdb;
        
        $analysis = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dpo_market_analysis 
            WHERE product_id = %d 
            ORDER BY date DESC 
            LIMIT 1",
            $product_id
        ));
        
        return $analysis ? (array) $analysis : array();
    }

    /**
     * Get risk analysis for a product
     *
     * @param int $product_id
     * @return array
     */
    private function get_risk_analysis($product_id) {
        global $wpdb;
        
        $analysis = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dpo_risk_analysis 
            WHERE product_id = %d 
            ORDER BY date DESC 
            LIMIT 1",
            $product_id
        ));
        
        return $analysis ? (array) $analysis : array();
    }

    /**
     * Get customer behavior for a product
     *
     * @param int $product_id
     * @return array
     */
    private function get_customer_behavior($product_id) {
        global $wpdb;
        
        $behavior = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dpo_customer_data 
            WHERE product_id = %d 
            ORDER BY date DESC 
            LIMIT 1",
            $product_id
        ));
        
        return $behavior ? (array) $behavior : array();
    }

    /**
     * Get competitor prices for a product
     *
     * @param int $product_id
     * @return array
     */
    private function get_competitor_prices($product_id) {
        global $wpdb;
        
        $prices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dpo_competitor_prices 
            WHERE product_id = %d 
            ORDER BY last_updated DESC",
            $product_id
        ));
        
        return $prices ? array_map(function($price) {
            return (array) $price;
        }, $prices) : array();
    }

    /**
     * Calculate product cost
     *
     * @param \WC_Product $product
     * @return float
     */
    private function calculate_product_cost($product) {
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            $costs = array();
            
            foreach ($variations as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                if ($variation_obj) {
                    $costs[] = $this->get_variation_cost($variation_obj);
                }
            }
            
            return !empty($costs) ? min($costs) : 0;
        } else {
            return $this->get_variation_cost($product);
        }
    }

    /**
     * Get variation cost
     *
     * @param \WC_Product $product
     * @return float
     */
    private function get_variation_cost($product) {
        $cost = get_post_meta($product->get_id(), '_cost', true);
        return $cost ? floatval($cost) : 0;
    }

    /**
     * Log price update
     *
     * @param int $product_id
     * @param array $analysis
     */
    private function log_price_update($product_id, $analysis) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/dpo-logs/price-updates.log';
        
        $log_entry = sprintf(
            "[%s] Product ID: %d, Old Price: %.2f, New Price: %.2f, Change: %.2f%%\n",
            current_time('mysql'),
            $product_id,
            $analysis['current_price'],
            $analysis['optimal_price'],
            $analysis['price_change_percentage']
        );
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Update product metrics
     *
     * @param \WC_Product $product
     * @param string $event_type
     */
    public function update_product_metrics($product, $event_type) {
        $product_id = $product->get_id();
        $date = current_time('Y-m-d');
        
        // Update sales data
        $this->update_sales_data($product_id, $date, $event_type);
        
        // Update customer behavior
        $this->update_customer_behavior($product_id, $date, $event_type);
        
        // Update market analysis
        $this->update_market_analysis($product_id, $date);
        
        // Update risk analysis
        $this->update_risk_analysis($product_id, $date);
    }

    /**
     * Update sales data
     *
     * @param int $product_id
     * @param string $date
     * @param string $event_type
     */
    private function update_sales_data($product_id, $date, $event_type) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dpo_sales_data';
        
        // Get existing data
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND date = %s",
            $product_id,
            $date
        ));
        
        if ($existing) {
            // Update existing data
            $wpdb->update(
                $table,
                array(
                    'orders_count' => $existing->orders_count + ($event_type === 'purchase' ? 1 : 0),
                    'revenue' => $existing->revenue + ($event_type === 'purchase' ? $existing->average_order_value : 0),
                    'average_order_value' => ($existing->revenue + ($event_type === 'purchase' ? $existing->average_order_value : 0)) / ($existing->orders_count + ($event_type === 'purchase' ? 1 : 0))
                ),
                array(
                    'product_id' => $product_id,
                    'date' => $date
                )
            );
        } else {
            // Insert new data
            $wpdb->insert(
                $table,
                array(
                    'product_id' => $product_id,
                    'date' => $date,
                    'orders_count' => $event_type === 'purchase' ? 1 : 0,
                    'revenue' => $event_type === 'purchase' ? get_post_meta($product_id, '_price', true) : 0,
                    'average_order_value' => $event_type === 'purchase' ? get_post_meta($product_id, '_price', true) : 0
                )
            );
        }
    }

    /**
     * Update customer behavior
     *
     * @param int $product_id
     * @param string $date
     * @param string $event_type
     */
    private function update_customer_behavior($product_id, $date, $event_type) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dpo_customer_data';
        
        // Get existing data
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND date = %s",
            $product_id,
            $date
        ));
        
        if ($existing) {
            // Update existing data
            $wpdb->update(
                $table,
                array(
                    'views' => $existing->views + 1,
                    'add_to_cart' => $existing->add_to_cart + ($event_type === 'add_to_cart' ? 1 : 0),
                    'purchases' => $existing->purchases + ($event_type === 'purchase' ? 1 : 0),
                    'conversion_rate' => (($existing->purchases + ($event_type === 'purchase' ? 1 : 0)) / ($existing->views + 1)) * 100,
                    'cart_abandonment_rate' => (($existing->add_to_cart - ($existing->purchases + ($event_type === 'purchase' ? 1 : 0))) / $existing->add_to_cart) * 100
                ),
                array(
                    'product_id' => $product_id,
                    'date' => $date
                )
            );
        } else {
            // Insert new data
            $wpdb->insert(
                $table,
                array(
                    'product_id' => $product_id,
                    'date' => $date,
                    'views' => 1,
                    'add_to_cart' => $event_type === 'add_to_cart' ? 1 : 0,
                    'purchases' => $event_type === 'purchase' ? 1 : 0,
                    'conversion_rate' => $event_type === 'purchase' ? 100 : 0,
                    'cart_abandonment_rate' => $event_type === 'add_to_cart' ? 100 : 0
                )
            );
        }
    }

    /**
     * Update market analysis
     *
     * @param int $product_id
     * @param string $date
     */
    private function update_market_analysis($product_id, $date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dpo_market_analysis';
        
        // Get competitor prices
        $competitor_prices = $this->get_competitor_prices($product_id);
        $market_price = 0;
        if (!empty($competitor_prices)) {
            $prices = array_column($competitor_prices, 'price');
            $market_price = array_sum($prices) / count($prices);
        }
        
        // Get sales data
        $sales_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dpo_sales_data WHERE product_id = %d AND date = %s",
            $product_id,
            $date
        ));
        
        // Calculate market share
        $total_sales = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(revenue) FROM {$wpdb->prefix}dpo_sales_data WHERE date = %s",
            $date
        ));
        
        $market_share = $sales_data && $total_sales ? ($sales_data->revenue / $total_sales) * 100 : 0;
        
        // Calculate price competitiveness
        $current_price = get_post_meta($product_id, '_price', true);
        $price_competitiveness = $market_price ? (($market_price - $current_price) / $market_price) * 100 : 0;
        
        // Calculate trend indicator
        $trend_indicator = $this->calculate_trend_indicator($product_id);
        
        // Calculate market volatility
        $market_volatility = $this->calculate_market_volatility($product_id);
        
        // Calculate market opportunity
        $market_opportunity = $this->calculate_market_opportunity($product_id);
        
        // Update or insert market analysis
        $wpdb->replace(
            $table,
            array(
                'product_id' => $product_id,
                'date' => $date,
                'market_price' => $market_price,
                'market_share' => $market_share,
                'price_competitiveness' => $price_competitiveness,
                'trend_indicator' => $trend_indicator,
                'market_volatility' => $market_volatility,
                'market_opportunity' => $market_opportunity
            )
        );
    }

    /**
     * Update risk analysis
     *
     * @param int $product_id
     * @param string $date
     */
    private function update_risk_analysis($product_id, $date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'dpo_risk_analysis';
        
        // Calculate profit margin
        $cost = $this->calculate_product_cost(wc_get_product($product_id));
        $price = get_post_meta($product_id, '_price', true);
        $profit_margin = $price ? (($price - $cost) / $price) * 100 : 0;
        
        // Calculate price volatility
        $price_volatility = $this->calculate_price_volatility($product_id);
        
        // Calculate stock risk
        $stock_risk = $this->calculate_stock_risk($product_id);
        
        // Calculate order cancellation rate
        $order_cancellation_rate = $this->calculate_order_cancellation_rate($product_id);
        
        // Calculate overall risk
        $overall_risk = ($price_volatility + $stock_risk + $order_cancellation_rate) / 3;
        
        // Update or insert risk analysis
        $wpdb->replace(
            $table,
            array(
                'product_id' => $product_id,
                'date' => $date,
                'profit_margin' => $profit_margin,
                'price_volatility' => $price_volatility,
                'stock_risk' => $stock_risk,
                'order_cancellation_rate' => $order_cancellation_rate,
                'overall_risk' => $overall_risk
            )
        );
    }

    /**
     * Calculate trend indicator
     *
     * @param int $product_id
     * @return float
     */
    private function calculate_trend_indicator($product_id) {
        global $wpdb;
        
        // Get sales data for the last 7 days
        $sales_data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dpo_sales_data 
            WHERE product_id = %d 
            AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY date ASC",
            $product_id
        ));
        
        if (count($sales_data) < 2) {
            return 0;
        }
        
        // Calculate linear regression
        $x = array();
        $y = array();
        foreach ($sales_data as $data) {
            $x[] = strtotime($data->date);
            $y[] = $data->revenue;
        }
        
        $n = count($x);
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_xx = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_xx += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x * $sum_x);
        
        // Normalize slope to percentage
        $trend_indicator = ($slope / $y[0]) * 100;
        
        return $trend_indicator;
    }

    /**
     * Calculate market volatility
     *
     * @param int $product_id
     * @return float
     */
    private function calculate_market_volatility($product_id) {
        global $wpdb;
        
        // Get competitor prices for the last 7 days
        $competitor_prices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dpo_competitor_prices 
            WHERE product_id = %d 
            AND last_updated >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            $product_id
        ));
        
        if (empty($competitor_prices)) {
            return 0;
        }
        
        // Calculate standard deviation
        $prices = array_column($competitor_prices, 'price');
        $mean = array_sum($prices) / count($prices);
        
        $squared_diff_sum = 0;
        foreach ($prices as $price) {
            $squared_diff_sum += pow($price - $mean, 2);
        }
        
        $std_dev = sqrt($squared_diff_sum / count($prices));
        
        // Normalize to percentage
        $volatility = ($std_dev / $mean) * 100;
        
        return $volatility;
    }

    /**
     * Calculate market opportunity
     *
     * @param int $product_id
     * @return float
     */
    private function calculate_market_opportunity($product_id) {
        global $wpdb;
        
        // Get market analysis
        $market_analysis = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dpo_market_analysis 
            WHERE product_id = %d 
            ORDER BY date DESC 
            LIMIT 1",
            $product_id
        ));
        
        if (!$market_analysis) {
            return 0;
        }
        
        // Calculate opportunity score based on multiple factors
        $opportunity_score = 0;
        
        // Market share factor (inverse relationship)
        $opportunity_score += (100 - $market_analysis->market_share) * 0.3;
        
        // Price competitiveness factor
        $opportunity_score += max(0, $market_analysis->price_competitiveness) * 0.3;
        
        // Trend factor
        $opportunity_score += max(0, $market_analysis->trend_indicator) * 0.2;
        
        // Market volatility factor (inverse relationship)
        $opportunity_score += (100 - $market_analysis->market_volatility) * 0.2;
        
        return $opportunity_score;
    }

    /**
     * Calculate price volatility
     *
     * @param int $product_id
     * @return float
     */
    private function calculate_price_volatility($product_id) {
        global $wpdb;
        
        // Get price history for the last 7 days
        $price_history = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_value as price 
            FROM {$wpdb->postmeta} 
            WHERE post_id = %d 
            AND meta_key = '_price' 
            AND meta_value != '' 
            ORDER BY meta_id DESC 
            LIMIT 7",
            $product_id
        ));
        
        if (count($price_history) < 2) {
            return 0;
        }
        
        // Calculate standard deviation
        $prices = array_column($price_history, 'price');
        $mean = array_sum($prices) / count($prices);
        
        $squared_diff_sum = 0;
        foreach ($prices as $price) {
            $squared_diff_sum += pow($price - $mean, 2);
        }
        
        $std_dev = sqrt($squared_diff_sum / count($prices));
        
        // Normalize to percentage
        $volatility = ($std_dev / $mean) * 100;
        
        return $volatility;
    }

    /**
     * Calculate stock risk
     *
     * @param int $product_id
     * @return float
     */
    private function calculate_stock_risk($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }
        
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            $stock_risks = array();
            
            foreach ($variations as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                if ($variation_obj) {
                    $stock_risks[] = $this->calculate_variation_stock_risk($variation_obj);
                }
            }
            
            return !empty($stock_risks) ? max($stock_risks) : 0;
        } else {
            return $this->calculate_variation_stock_risk($product);
        }
    }

    /**
     * Calculate variation stock risk
     *
     * @param \WC_Product $product
     * @return float
     */
    private function calculate_variation_stock_risk($product) {
        $stock_quantity = $product->get_stock_quantity();
        $stock_status = $product->get_stock_status();
        
        if ($stock_status === 'outofstock') {
            return 100;
        }
        
        if ($stock_quantity === null) {
            return 0;
        }
        
        // Calculate risk based on stock level
        $risk = 0;
        
        if ($stock_quantity <= 0) {
            $risk = 100;
        } elseif ($stock_quantity <= 5) {
            $risk = 80;
        } elseif ($stock_quantity <= 10) {
            $risk = 60;
        } elseif ($stock_quantity <= 20) {
            $risk = 40;
        } elseif ($stock_quantity <= 50) {
            $risk = 20;
        }
        
        return $risk;
    }

    /**
     * Calculate order cancellation rate
     *
     * @param int $product_id
     * @return float
     */
    private function calculate_order_cancellation_rate($product_id) {
        global $wpdb;
        
        // Get orders for the last 30 days
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_status 
            FROM {$wpdb->posts} p 
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id 
            WHERE oi.order_item_type = 'line_item' 
            AND oi.order_id IN (
                SELECT order_id 
                FROM {$wpdb->prefix}woocommerce_order_items 
                WHERE order_item_type = 'line_item' 
                AND order_id IN (
                    SELECT ID 
                    FROM {$wpdb->posts} 
                    WHERE post_type = 'shop_order' 
                    AND post_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                )
            )",
            $product_id
        ));
        
        if (empty($orders)) {
            return 0;
        }
        
        $total_orders = count($orders);
        $cancelled_orders = 0;
        
        foreach ($orders as $order) {
            if ($order->post_status === 'wc-cancelled') {
                $cancelled_orders++;
            }
        }
        
        return ($cancelled_orders / $total_orders) * 100;
    }
} 