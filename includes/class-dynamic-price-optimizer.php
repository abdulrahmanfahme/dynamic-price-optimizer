<?php
declare(strict_types=1);

namespace DynamicPriceOptimizer;

class DynamicPriceOptimizer {
    /**
     * @var DataCollector
     */
    private $data_collector;

    /**
     * @var PriceOptimizer
     */
    private $price_optimizer;

    /**
     * @var AdminInterface
     */
    private $admin_interface;

    /**
     * Initialize the plugin
     */
    public function init(): void {
        // Load dependencies
        $this->load_dependencies();

        // Initialize components
        $this->data_collector = new DataCollector();
        $this->price_optimizer = new PriceOptimizer();
        $this->admin_interface = new AdminInterface();

        // Set up hooks
        $this->setup_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {
        require_once DPO_PLUGIN_DIR . 'includes/class-data-collector.php';
        require_once DPO_PLUGIN_DIR . 'includes/class-price-optimizer.php';
        require_once DPO_PLUGIN_DIR . 'includes/class-admin-interface.php';
    }

    /**
     * Set up WordPress hooks
     */
    private function setup_hooks(): void {
        // Schedule price optimization
        add_action('dpo_schedule_price_optimization', [$this, 'run_price_optimization']);

        // Product hooks
        add_action('woocommerce_before_product_object_save', [$this, 'optimize_product_price'], 10, 1);
        add_action('woocommerce_product_set_stock', [$this, 'handle_stock_change'], 10, 1);
        add_action('woocommerce_product_set_stock_status', [$this, 'handle_stock_status_change'], 10, 2);

        // Admin hooks
        add_action('admin_menu', [$this->admin_interface, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this->admin_interface, 'enqueue_admin_assets']);
        add_action('wp_ajax_dpo_update_price', [$this, 'handle_ajax_price_update']);
        add_action('wp_ajax_dpo_get_optimization_data', [$this, 'handle_ajax_get_data']);

        // Initialize scheduled tasks
        if (!wp_next_scheduled('dpo_schedule_price_optimization')) {
            wp_schedule_event(time(), 'hourly', 'dpo_schedule_price_optimization');
        }
    }

    /**
     * Run price optimization for all products
     */
    public function run_price_optimization(): void {
        $products = wc_get_products([
            'limit' => -1,
            'status' => 'publish',
            'return' => 'ids'
        ]);

        foreach ($products as $product_id) {
            $this->optimize_product_price($product_id);
        }
    }

    /**
     * Optimize price for a specific product
     */
    public function optimize_product_price(int $product_id): void {
        try {
            $product = wc_get_product($product_id);
            if (!$product) {
                return;
            }

            // Collect market data
            $market_data = $this->data_collector->collect_market_data($product);

            // Get current price and cost
            $current_price = $product->get_price();
            $cost = $this->data_collector->get_product_cost($product);

            // Calculate optimal price
            $optimal_price = $this->price_optimizer->calculate_optimal_price(
                $current_price,
                $cost,
                $market_data
            );

            // Update price if within allowed range
            if ($this->price_optimizer->is_price_update_allowed($optimal_price, $current_price)) {
                $product->set_price($optimal_price);
                $product->save();
            }

            // Log optimization results
            $this->log_optimization_results($product_id, $current_price, $optimal_price, $market_data);

        } catch (\Exception $e) {
            error_log(sprintf(
                'Dynamic Price Optimizer Error: %s for product ID %d',
                $e->getMessage(),
                $product_id
            ));
        }
    }

    /**
     * Handle stock level changes
     */
    public function handle_stock_change(\WC_Product $product): void {
        if ($product->get_stock_quantity() <= 5) {
            $this->optimize_product_price($product->get_id());
        }
    }

    /**
     * Handle stock status changes
     */
    public function handle_stock_status_change(\WC_Product $product, string $status): void {
        if ($status === 'instock') {
            $this->optimize_product_price($product->get_id());
        }
    }

    /**
     * Handle AJAX price update request
     */
    public function handle_ajax_price_update(): void {
        check_ajax_referer('dpo_price_update', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        $this->optimize_product_price($product_id);
        wp_send_json_success('Price updated successfully');
    }

    /**
     * Handle AJAX data request
     */
    public function handle_ajax_get_data(): void {
        check_ajax_referer('dpo_get_data', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }

        $market_data = $this->data_collector->collect_market_data($product);
        wp_send_json_success($market_data);
    }

    /**
     * Log optimization results
     */
    private function log_optimization_results(
        int $product_id,
        float $current_price,
        float $optimal_price,
        array $market_data
    ): void {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'product_id' => $product_id,
            'current_price' => $current_price,
            'optimal_price' => $optimal_price,
            'price_change' => $optimal_price - $current_price,
            'market_data' => $market_data
        ];

        $logs = get_option('dpo_optimization_logs', []);
        array_unshift($logs, $log_entry);
        
        // Keep only last 100 logs
        $logs = array_slice($logs, 0, 100);
        
        update_option('dpo_optimization_logs', $logs);
    }
} 