<?php
namespace DynamicPriceOptimizer\Core;

/**
 * Handles competitor price tracking
 */
class CompetitorTracker {
    private $db;
    private $api_client;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->api_client = new API\Client();
    }

    public function get_competitor_prices($product) {
        // Get competitor URLs from product meta
        $competitor_urls = $this->get_competitor_urls($product);
        
        // Get cached prices first
        $cached_prices = $this->get_cached_competitor_prices($product->get_id());
        
        // If cache is expired or empty, fetch fresh prices
        if ($this->should_refresh_competitor_prices($cached_prices)) {
            $fresh_prices = $this->fetch_competitor_prices($product, $competitor_urls);
            $this->cache_competitor_prices($product->get_id(), $fresh_prices);
            return $fresh_prices;
        }
        
        return $cached_prices;
    }

    private function get_competitor_urls($product) {
        $urls = $product->get_meta('_competitor_urls');
        if (empty($urls)) {
            // Try to find competitor URLs based on product name and category
            $urls = $this->find_competitor_urls($product);
        }
        return $urls;
    }

    private function find_competitor_urls($product) {
        $urls = [];
        
        // Get product name and category
        $product_name = $product->get_name();
        $categories = wc_get_product_category_list($product->get_id());
        
        // Search for competitor URLs using product name and category
        $search_terms = $this->generate_search_terms($product_name, $categories);
        
        // Use API to find competitor URLs
        foreach ($search_terms as $term) {
            $found_urls = $this->api_client->search_competitor_urls($term);
            $urls = array_merge($urls, $found_urls);
        }
        
        // Remove duplicates and invalid URLs
        $urls = array_unique(array_filter($urls, 'filter_var'));
        
        // Save found URLs to product meta
        if (!empty($urls)) {
            $product->update_meta_data('_competitor_urls', $urls);
            $product->save();
        }
        
        return $urls;
    }

    private function generate_search_terms($product_name, $categories) {
        $terms = [];
        
        // Add product name variations
        $terms[] = $product_name;
        $terms[] = str_replace(' ', '+', $product_name);
        
        // Add category-based terms
        if (!empty($categories)) {
            foreach (explode(',', $categories) as $category) {
                $terms[] = trim($category) . ' ' . $product_name;
            }
        }
        
        return array_unique($terms);
    }

    private function fetch_competitor_prices($product, $competitor_urls) {
        $prices = [];
        
        foreach ($competitor_urls as $url) {
            try {
                $price_data = $this->api_client->fetch_price_from_url($url);
                if ($price_data) {
                    $prices[] = [
                        'url' => $url,
                        'price' => $price_data['price'],
                        'currency' => $price_data['currency'],
                        'timestamp' => current_time('mysql'),
                        'status' => 'success'
                    ];
                }
            } catch (\Exception $e) {
                $prices[] = [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'timestamp' => current_time('mysql'),
                    'status' => 'error'
                ];
            }
        }
        
        return $prices;
    }

    private function get_cached_competitor_prices($product_id) {
        $cache_key = 'dpo_competitor_prices_' . $product_id;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data === false) {
            return [];
        }
        
        return $cached_data;
    }

    private function cache_competitor_prices($product_id, $prices) {
        $cache_key = 'dpo_competitor_prices_' . $product_id;
        set_transient($cache_key, $prices, HOUR_IN_SECONDS);
    }

    private function should_refresh_competitor_prices($cached_prices) {
        if (empty($cached_prices)) {
            return true;
        }
        
        // Check if any price is older than 1 hour
        foreach ($cached_prices as $price_data) {
            if (isset($price_data['timestamp'])) {
                $timestamp = strtotime($price_data['timestamp']);
                if (time() - $timestamp > HOUR_IN_SECONDS) {
                    return true;
                }
            }
        }
        
        return false;
    }

    public function analyze_competitor_pricing($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $competitor_prices = $this->get_competitor_prices($product);
        $current_price = $product->get_price();
        
        // Filter out failed price fetches
        $valid_prices = array_filter($competitor_prices, function($price_data) {
            return $price_data['status'] === 'success';
        });
        
        if (empty($valid_prices)) {
            return [
                'status' => 'error',
                'message' => 'No valid competitor prices found'
            ];
        }
        
        // Calculate price statistics
        $prices = array_column($valid_prices, 'price');
        $avg_price = array_sum($prices) / count($prices);
        $min_price = min($prices);
        $max_price = max($prices);
        
        // Calculate price position
        $price_position = $this->calculate_price_position($current_price, $prices);
        
        return [
            'status' => 'success',
            'current_price' => $current_price,
            'competitor_count' => count($valid_prices),
            'average_price' => $avg_price,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'price_position' => $price_position,
            'price_difference_percentage' => (($current_price - $avg_price) / $avg_price) * 100,
            'competitor_prices' => $valid_prices
        ];
    }

    private function calculate_price_position($current_price, $competitor_prices) {
        sort($competitor_prices);
        $position = 0;
        
        foreach ($competitor_prices as $price) {
            if ($current_price <= $price) {
                break;
            }
            $position++;
        }
        
        return [
            'position' => $position,
            'total' => count($competitor_prices),
            'percentile' => ($position / count($competitor_prices)) * 100
        ];
    }

    public function get_price_recommendations($product_id) {
        $analysis = $this->analyze_competitor_pricing($product_id);
        
        if ($analysis['status'] === 'error') {
            return $analysis;
        }
        
        $recommendations = [];
        
        // Price too high
        if ($analysis['price_difference_percentage'] > 10) {
            $recommendations[] = [
                'type' => 'high_price',
                'message' => 'Price is significantly higher than competitors',
                'suggestion' => 'Consider reducing price to stay competitive'
            ];
        }
        
        // Price too low
        if ($analysis['price_difference_percentage'] < -10) {
            $recommendations[] = [
                'type' => 'low_price',
                'message' => 'Price is significantly lower than competitors',
                'suggestion' => 'Consider increasing price to improve margins'
            ];
        }
        
        // Price position analysis
        if ($analysis['price_position']['percentile'] > 80) {
            $recommendations[] = [
                'type' => 'price_position',
                'message' => 'Price is in the top 20% of competitors',
                'suggestion' => 'Review pricing strategy to ensure value proposition'
            ];
        }
        
        return [
            'status' => 'success',
            'analysis' => $analysis,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Update all competitor prices
     */
    public function update_all_competitor_prices() {
        global $wpdb;
        
        // Get all products with competitor URLs
        $products = $wpdb->get_results(
            "SELECT DISTINCT product_id 
            FROM {$wpdb->prefix}dpo_competitor_prices"
        );
        
        foreach ($products as $product) {
            $this->update_competitor_prices($product->product_id);
        }
    }

    /**
     * Update competitor prices for a product
     *
     * @param int $product_id
     */
    public function update_competitor_prices($product_id) {
        global $wpdb;
        
        // Get competitor URLs for the product
        $competitors = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT competitor_url 
            FROM {$wpdb->prefix}dpo_competitor_prices 
            WHERE product_id = %d",
            $product_id
        ));
        
        foreach ($competitors as $competitor) {
            try {
                $price = $this->scrape_price($competitor->competitor_url);
                if ($price > 0) {
                    $this->save_competitor_price($product_id, $competitor->competitor_url, $price);
                }
            } catch (\Exception $e) {
                $this->log_error($e->getMessage());
            }
        }
    }

    /**
     * Add competitor URL for a product
     *
     * @param int $product_id
     * @param string $competitor_url
     * @return bool
     */
    public function add_competitor_url($product_id, $competitor_url) {
        global $wpdb;
        
        try {
            $price = $this->scrape_price($competitor_url);
            if ($price > 0) {
                return $this->save_competitor_price($product_id, $competitor_url, $price);
            }
        } catch (\Exception $e) {
            $this->log_error($e->getMessage());
        }
        
        return false;
    }

    /**
     * Remove competitor URL for a product
     *
     * @param int $product_id
     * @param string $competitor_url
     * @return bool
     */
    public function remove_competitor_url($product_id, $competitor_url) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'dpo_competitor_prices',
            array(
                'product_id' => $product_id,
                'competitor_url' => $competitor_url
            )
        );
    }

    /**
     * Scrape price from competitor URL
     *
     * @param string $url
     * @return float
     */
    private function scrape_price($url) {
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        // Execute cURL request
        $response = curl_exec($ch);
        
        // Check for errors
        if (curl_errno($ch)) {
            throw new \Exception('cURL Error: ' . curl_error($ch));
        }
        
        // Close cURL
        curl_close($ch);
        
        // Parse price using common selectors
        $price = 0;
        $selectors = array(
            'span.price',
            'div.price',
            'p.price',
            'span.amount',
            'div.amount',
            'p.amount'
        );
        
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($response);
        $xpath = new \DOMXPath($dom);
        
        foreach ($selectors as $selector) {
            $elements = $xpath->query("//*[contains(@class, '" . str_replace('.', '', $selector) . "')]");
            if ($elements->length > 0) {
                $price_text = $elements->item(0)->textContent;
                $price = $this->extract_price($price_text);
                if ($price > 0) {
                    break;
                }
            }
        }
} 