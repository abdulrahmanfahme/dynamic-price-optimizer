<?php
declare(strict_types=1);

namespace DynamicPriceOptimizer;

class DataCollector {
    /**
     * @var array
     */
    private $competitor_apis = [];

    /**
     * @var array
     */
    private $cache = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_competitor_apis();
    }

    /**
     * Initialize competitor API connections
     */
    private function initialize_competitor_apis(): void {
        // Initialize competitor API connections from settings
        $this->competitor_apis = get_option('dpo_competitor_apis', []);
    }

    /**
     * Collect market data for a product
     */
    public function collect_market_data(\WC_Product $product): array {
        $product_id = $product->get_id();
        $cache_key = "market_data_{$product_id}";

        // Check cache first
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $market_data = [
            'competitor_prices' => $this->get_competitor_prices($product),
            'historical_data' => $this->get_historical_data($product),
            'seasonal_factors' => $this->get_seasonal_factors($product),
            'inventory_levels' => $this->get_inventory_levels($product),
            'demand_trends' => $this->get_demand_trends($product),
            'cost_data' => $this->get_cost_data($product)
        ];

        // Cache the results
        $this->cache[$cache_key] = $market_data;

        return $market_data;
    }

    /**
     * Get competitor prices for a product
     */
    private function get_competitor_prices(\WC_Product $product): array {
        $competitor_prices = [];
        
        foreach ($this->competitor_apis as $api) {
            try {
                $price = $this->fetch_competitor_price($product, $api);
                if ($price) {
                    $competitor_prices[] = [
                        'source' => $api['name'],
                        'price' => $price,
                        'timestamp' => current_time('mysql')
                    ];
                }
            } catch (\Exception $e) {
                error_log(sprintf(
                    'Error fetching competitor price from %s: %s',
                    $api['name'],
                    $e->getMessage()
                ));
            }
        }

        return $competitor_prices;
    }

    /**
     * Fetch price from a specific competitor API
     */
    private function fetch_competitor_price(\WC_Product $product, array $api): ?float {
        // Implementation will depend on the specific API
        // This is a placeholder for demonstration
        return null;
    }

    /**
     * Get historical sales data for a product
     */
    private function get_historical_data(\WC_Product $product): array {
        global $wpdb;

        $product_id = $product->get_id();
        $days = 30; // Get last 30 days of data

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(meta_value) as date,
                COUNT(*) as sales_count,
                SUM(meta_value2) as total_revenue
            FROM {$wpdb->prefix}wc_order_items oi
            JOIN {$wpdb->prefix}wc_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE oi.order_id IN (
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'shop_order'
                AND post_status = 'wc-completed'
                AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            )
            AND oi.order_item_type = 'line_item'
            AND oim.meta_key = '_product_id'
            AND oim.meta_value = %d
            GROUP BY DATE(meta_value)
            ORDER BY date DESC",
            $days,
            $product_id
        ));

        return array_map(function($row) {
            return [
                'date' => $row->date,
                'sales_count' => (int)$row->sales_count,
                'total_revenue' => (float)$row->total_revenue
            ];
        }, $results);
    }

    /**
     * Get seasonal factors affecting the product
     */
    private function get_seasonal_factors(\WC_Product $product): array {
        $current_date = new \DateTime();
        $seasonal_factors = [
            'is_weekend' => in_array($current_date->format('N'), ['6', '7']),
            'is_holiday' => $this->is_holiday($current_date),
            'season' => $this->get_current_season($current_date),
            'time_of_day' => (int)$current_date->format('H')
        ];

        return $seasonal_factors;
    }

    /**
     * Check if current date is a holiday
     */
    private function is_holiday(\DateTime $date): bool {
        $holidays = get_option('dpo_holidays', []);
        return in_array($date->format('Y-m-d'), $holidays);
    }

    /**
     * Get current season
     */
    private function get_current_season(\DateTime $date): string {
        $month = (int)$date->format('n');
        
        if ($month >= 3 && $month <= 5) {
            return 'spring';
        } elseif ($month >= 6 && $month <= 8) {
            return 'summer';
        } elseif ($month >= 9 && $month <= 11) {
            return 'fall';
        } else {
            return 'winter';
        }
    }

    /**
     * Get inventory levels and related data
     */
    private function get_inventory_levels(\WC_Product $product): array {
        return [
            'current_stock' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'backorders_allowed' => $product->backorders_allowed(),
            'low_stock_threshold' => $product->get_low_stock_amount()
        ];
    }

    /**
     * Get demand trends for the product
     */
    private function get_demand_trends(\WC_Product $product): array {
        global $wpdb;

        $product_id = $product->get_id();
        $days = 7; // Get last 7 days of data

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(meta_value) as date,
                COUNT(*) as views,
                COUNT(DISTINCT meta_value2) as unique_views
            FROM {$wpdb->prefix}wc_product_views
            WHERE product_id = %d
            AND meta_value >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(meta_value)
            ORDER BY date DESC",
            $product_id,
            $days
        ));

        return array_map(function($row) {
            return [
                'date' => $row->date,
                'views' => (int)$row->views,
                'unique_views' => (int)$row->unique_views
            ];
        }, $results);
    }

    /**
     * Get cost data for the product
     */
    private function get_cost_data(\WC_Product $product): array {
        return [
            'cost' => $this->get_product_cost($product),
            'shipping_cost' => $this->get_shipping_cost($product),
            'tax_rate' => $this->get_tax_rate($product)
        ];
    }

    /**
     * Get product cost
     */
    public function get_product_cost(\WC_Product $product): float {
        return (float)$product->get_meta('_product_cost', true);
    }

    /**
     * Get shipping cost for the product
     */
    private function get_shipping_cost(\WC_Product $product): float {
        // Implementation will depend on your shipping setup
        return 0.0;
    }

    /**
     * Get tax rate for the product
     */
    private function get_tax_rate(\WC_Product $product): float {
        // Implementation will depend on your tax setup
        return 0.0;
    }

    /**
     * Clear cache for a specific product
     */
    public function clear_cache(int $product_id): void {
        unset($this->cache["market_data_{$product_id}"]);
    }

    /**
     * Clear all cache
     */
    public function clear_all_cache(): void {
        $this->cache = [];
    }
} 