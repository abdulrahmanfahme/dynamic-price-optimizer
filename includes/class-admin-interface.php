<?php
declare(strict_types=1);

namespace DynamicPriceOptimizer;

class AdminInterface {
    /**
     * @var string
     */
    private $plugin_slug = 'dynamic-price-optimizer';

    /**
     * Add admin menu items
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('Dynamic Price Optimizer', 'dynamic-price-optimizer'),
            __('Price Optimizer', 'dynamic-price-optimizer'),
            'manage_woocommerce',
            $this->plugin_slug,
            [$this, 'render_main_page'],
            'dashicons-chart-line',
            56
        );

        add_submenu_page(
            $this->plugin_slug,
            __('Settings', 'dynamic-price-optimizer'),
            __('Settings', 'dynamic-price-optimizer'),
            'manage_woocommerce',
            $this->plugin_slug . '-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, $this->plugin_slug) === false) {
            return;
        }

        wp_enqueue_style(
            'dpo-admin-style',
            DPO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            DPO_VERSION
        );

        wp_enqueue_script(
            'dpo-admin-script',
            DPO_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            DPO_VERSION,
            true
        );

        wp_localize_script('dpo-admin-script', 'dpoAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpo_admin_nonce'),
            'i18n' => [
                'confirmPriceUpdate' => __('Are you sure you want to update the price?', 'dynamic-price-optimizer'),
                'priceUpdated' => __('Price updated successfully', 'dynamic-price-optimizer'),
                'error' => __('An error occurred', 'dynamic-price-optimizer')
            ]
        ]);
    }

    /**
     * Render main admin page
     */
    public function render_main_page(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'dynamic-price-optimizer'));
        }

        $products = wc_get_products([
            'limit' => -1,
            'status' => 'publish',
            'return' => 'ids'
        ]);

        include DPO_PLUGIN_DIR . 'templates/admin/main-page.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'dynamic-price-optimizer'));
        }

        if (isset($_POST['dpo_save_settings'])) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . 
                __('Settings saved successfully.', 'dynamic-price-optimizer') . 
                '</p></div>';
        }

        $settings = $this->get_settings();
        include DPO_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }

    /**
     * Save plugin settings
     */
    private function save_settings(): void {
        check_admin_referer('dpo_settings_nonce');

        $settings = [
            'min_margin' => floatval($_POST['dpo_min_margin'] ?? 0.15),
            'max_margin' => floatval($_POST['dpo_max_margin'] ?? 0.40),
            'max_price_change' => floatval($_POST['dpo_max_price_change'] ?? 0.20),
            'competitor_weight' => floatval($_POST['dpo_competitor_weight'] ?? 0.30),
            'historical_weight' => floatval($_POST['dpo_historical_weight'] ?? 0.25),
            'seasonal_weight' => floatval($_POST['dpo_seasonal_weight'] ?? 0.15),
            'inventory_weight' => floatval($_POST['dpo_inventory_weight'] ?? 0.15),
            'demand_weight' => floatval($_POST['dpo_demand_weight'] ?? 0.15),
            'auto_update' => isset($_POST['dpo_auto_update']),
            'competitor_apis' => $this->sanitize_competitor_apis($_POST['dpo_competitor_apis'] ?? []),
            'holidays' => $this->sanitize_holidays($_POST['dpo_holidays'] ?? [])
        ];

        update_option('dpo_settings', $settings);
    }

    /**
     * Get plugin settings
     */
    private function get_settings(): array {
        $defaults = [
            'min_margin' => 0.15,
            'max_margin' => 0.40,
            'max_price_change' => 0.20,
            'competitor_weight' => 0.30,
            'historical_weight' => 0.25,
            'seasonal_weight' => 0.15,
            'inventory_weight' => 0.15,
            'demand_weight' => 0.15,
            'auto_update' => false,
            'competitor_apis' => [],
            'holidays' => []
        ];

        return wp_parse_args(get_option('dpo_settings', []), $defaults);
    }

    /**
     * Sanitize competitor API settings
     */
    private function sanitize_competitor_apis(array $apis): array {
        $sanitized = [];
        foreach ($apis as $api) {
            if (empty($api['name']) || empty($api['url'])) {
                continue;
            }

            $sanitized[] = [
                'name' => sanitize_text_field($api['name']),
                'url' => esc_url_raw($api['url']),
                'api_key' => sanitize_text_field($api['api_key'] ?? '')
            ];
        }
        return $sanitized;
    }

    /**
     * Sanitize holiday dates
     */
    private function sanitize_holidays(array $holidays): array {
        return array_map(function($date) {
            return sanitize_text_field($date);
        }, array_filter($holidays));
    }

    /**
     * Get product optimization data
     */
    public function get_product_optimization_data(int $product_id): array {
        $product = wc_get_product($product_id);
        if (!$product) {
            return [];
        }

        $data_collector = new DataCollector();
        $price_optimizer = new PriceOptimizer();

        $market_data = $data_collector->collect_market_data($product);
        $current_price = $product->get_price();
        $cost = $data_collector->get_product_cost($product);

        $optimal_price = $price_optimizer->calculate_optimal_price(
            $current_price,
            $cost,
            $market_data
        );

        $risks = $price_optimizer->get_risk_analysis(
            $current_price,
            $optimal_price,
            $market_data
        );

        return [
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'current_price' => $current_price,
            'optimal_price' => $optimal_price,
            'price_change' => $optimal_price - $current_price,
            'price_change_percentage' => (($optimal_price - $current_price) / $current_price) * 100,
            'market_data' => $market_data,
            'risks' => $risks,
            'can_update' => $price_optimizer->is_price_update_allowed($optimal_price, $current_price)
        ];
    }

    /**
     * Handle AJAX request for product data
     */
    public function handle_ajax_get_product_data(): void {
        check_ajax_referer('dpo_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        $data = $this->get_product_optimization_data($product_id);
        wp_send_json_success($data);
    }
} 