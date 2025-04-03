<?php
namespace DynamicPriceOptimizer\Core;

/**
 * Main plugin class
 */
class Plugin {
    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Admin instance
     *
     * @var Admin
     */
    private $admin;

    /**
     * Price Analyzer instance
     *
     * @var PriceAnalyzer
     */
    private $price_analyzer;

    /**
     * Competitor Tracker instance
     *
     * @var CompetitorTracker
     */
    private $competitor_tracker;

    /**
     * ML Engine instance
     *
     * @var MLEngine
     */
    private $ml_engine;

    /**
     * Constructor
     */
    private function __construct() {
        $this->version = DPO_VERSION;
    }

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize components
        $this->init_components();

        // Set up hooks
        $this->setup_hooks();
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize admin
        $this->admin = new Admin(
            $this->get_price_analyzer(),
            $this->get_competitor_tracker()
        );

        // Initialize ML engine
        $this->ml_engine = new MLEngine();
    }

    /**
     * Set up plugin hooks
     */
    private function setup_hooks() {
        // Add cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));

        // Add price update hooks
        add_action('dpo_daily_price_update', array($this, 'run_daily_price_update'));
        add_action('dpo_weekly_competitor_update', array($this, 'run_weekly_competitor_update'));

        // Add product hooks
        add_action('woocommerce_product_after_variable_attributes', array($this, 'add_variation_price_optimization'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_price_optimization'), 10, 2);

        // Add order hooks
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completion'));
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_order_cancellation'));

        // Add AJAX handlers
        add_action('wp_ajax_dpo_analyze_product', array($this, 'handle_analyze_product'));
        add_action('wp_ajax_dpo_update_product_price', array($this, 'handle_update_product_price'));
        add_action('wp_ajax_dpo_bulk_optimize', array($this, 'handle_bulk_optimize'));
        add_action('wp_ajax_dpo_save_settings', array($this, 'handle_save_settings'));
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Cron schedules
     * @return array
     */
    public function add_cron_schedules($schedules) {
        $schedules['dpo_daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display' => __('Daily', 'dynamic-price-optimizer')
        );

        $schedules['dpo_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Weekly', 'dynamic-price-optimizer')
        );

        return $schedules;
    }

    /**
     * Run daily price update
     */
    public function run_daily_price_update() {
        try {
            $products = wc_get_products(array(
                'status' => 'publish',
                'limit' => -1
            ));

            foreach ($products as $product) {
                if ($this->should_update_price($product)) {
                    $this->get_price_analyzer()->analyze_and_update_price($product);
                }
            }
        } catch (\Exception $e) {
            $this->log_error('Daily price update failed: ' . $e->getMessage());
        }
    }

    /**
     * Run weekly competitor update
     */
    public function run_weekly_competitor_update() {
        try {
            $this->get_competitor_tracker()->update_all_competitor_prices();
        } catch (\Exception $e) {
            $this->log_error('Weekly competitor update failed: ' . $e->getMessage());
        }
    }

    /**
     * Add variation price optimization fields
     *
     * @param int $loop Variation loop index
     * @param array $variation_data Variation data
     * @param \WP_Post $variation Variation post
     */
    public function add_variation_price_optimization($loop, $variation_data, $variation) {
        $variation_id = $variation->ID;
        $optimization_settings = get_post_meta($variation_id, '_dpo_optimization_settings', true);
        $optimization_enabled = isset($optimization_settings['enabled']) ? $optimization_settings['enabled'] : false;
        $min_markup = isset($optimization_settings['min_markup']) ? $optimization_settings['min_markup'] : 10;
        $max_markup = isset($optimization_settings['max_markup']) ? $optimization_settings['max_markup'] : 200;

        include DPO_PLUGIN_DIR . 'templates/admin/variation-price-optimization.php';
    }

    /**
     * Save variation price optimization settings
     *
     * @param int $variation_id Variation ID
     * @param int $loop Variation loop index
     */
    public function save_variation_price_optimization($variation_id, $loop) {
        $optimization_settings = array(
            'enabled' => isset($_POST['dpo_optimization_settings'][$loop]['enabled']),
            'min_markup' => floatval($_POST['dpo_optimization_settings'][$loop]['min_markup']),
            'max_markup' => floatval($_POST['dpo_optimization_settings'][$loop]['max_markup'])
        );

        update_post_meta($variation_id, '_dpo_optimization_settings', $optimization_settings);
    }

    /**
     * Handle order completion
     *
     * @param int $order_id Order ID
     */
    public function handle_order_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            if ($product) {
                $this->get_price_analyzer()->update_product_metrics($product, 'purchase');
            }
        }
    }

    /**
     * Handle order cancellation
     *
     * @param int $order_id Order ID
     */
    public function handle_order_cancellation($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            if ($product) {
                $this->get_price_analyzer()->update_product_metrics($product, 'cancellation');
            }
        }
    }

    /**
     * Handle analyze product AJAX request
     */
    public function handle_analyze_product() {
        check_ajax_referer('dpo_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }

        try {
            $analysis = $this->get_price_analyzer()->analyze_product($product);
            wp_send_json_success($analysis);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle update product price AJAX request
     */
    public function handle_update_product_price() {
        check_ajax_referer('dpo_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }

        try {
            $this->get_price_analyzer()->analyze_and_update_price($product);
            wp_send_json_success();
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle bulk optimize AJAX request
     */
    public function handle_bulk_optimize() {
        check_ajax_referer('dpo_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();
        if (empty($product_ids)) {
            wp_send_json_error('No products selected');
        }

        $results = array();
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                try {
                    $this->get_price_analyzer()->analyze_and_update_price($product);
                    $results[$product_id] = true;
                } catch (\Exception $e) {
                    $results[$product_id] = $e->getMessage();
                }
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Handle save settings AJAX request
     */
    public function handle_save_settings() {
        check_ajax_referer('dpo_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        if (empty($settings)) {
            wp_send_json_error('No settings provided');
        }

        try {
            update_option('dpo_settings', $settings);
            wp_send_json_success();
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get price analyzer instance
     *
     * @return PriceAnalyzer
     */
    private function get_price_analyzer() {
        if (!$this->price_analyzer) {
            $this->price_analyzer = new PriceAnalyzer(
                $this->get_competitor_tracker(),
                $this->get_ml_engine()
            );
        }
        return $this->price_analyzer;
    }

    /**
     * Get competitor tracker instance
     *
     * @return CompetitorTracker
     */
    private function get_competitor_tracker() {
        if (!$this->competitor_tracker) {
            $this->competitor_tracker = new CompetitorTracker();
        }
        return $this->competitor_tracker;
    }

    /**
     * Get ML engine instance
     *
     * @return MLEngine
     */
    private function get_ml_engine() {
        if (!$this->ml_engine) {
            $this->ml_engine = new MLEngine();
        }
        return $this->ml_engine;
    }

    /**
     * Check if product price should be updated
     *
     * @param \WC_Product $product Product object
     * @return bool
     */
    private function should_update_price($product) {
        $optimization_settings = $product->get_meta('_dpo_optimization_settings');
        if (!$optimization_settings || !isset($optimization_settings['enabled']) || !$optimization_settings['enabled']) {
            return false;
        }

        $last_update = $product->get_meta('_dpo_last_price_update');
        if (!$last_update) {
            return true;
        }

        $update_interval = get_option('dpo_settings')['update_interval'] ?? 'daily';
        $interval_seconds = $update_interval === 'daily' ? DAY_IN_SECONDS : WEEK_IN_SECONDS;

        return (time() - strtotime($last_update)) >= $interval_seconds;
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/dpo-logs/error.log';
        $timestamp = current_time('mysql');
        $log_message = "[$timestamp] $message\n";
        error_log($log_message, 3, $log_file);
    }
} 