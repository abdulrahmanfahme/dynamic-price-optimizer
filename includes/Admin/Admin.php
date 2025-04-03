<?php
namespace DynamicPriceOptimizer\Admin;

use DynamicPriceOptimizer\Core\PriceAnalyzer;
use DynamicPriceOptimizer\Core\CompetitorTracker;

/**
 * Handles admin interface and settings
 */
class Admin {
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
     * Constructor
     *
     * @param PriceAnalyzer $price_analyzer
     * @param CompetitorTracker $competitor_tracker
     */
    public function __construct(PriceAnalyzer $price_analyzer, CompetitorTracker $competitor_tracker) {
        $this->price_analyzer = $price_analyzer;
        $this->competitor_tracker = $competitor_tracker;

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add product meta boxes
        add_action('add_meta_boxes', array($this, 'add_product_meta_boxes'));
        
        // Save product meta
        add_action('save_post_product', array($this, 'save_product_meta'));
        
        // Add AJAX handlers
        add_action('wp_ajax_dpo_analyze_product', array($this, 'handle_analyze_product'));
        add_action('wp_ajax_dpo_update_product_price', array($this, 'handle_update_product_price'));
        add_action('wp_ajax_dpo_bulk_optimize', array($this, 'handle_bulk_optimize'));
        add_action('wp_ajax_dpo_save_settings', array($this, 'handle_save_settings'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Dynamic Price Optimizer', 'dynamic-price-optimizer'),
            __('Price Optimizer', 'dynamic-price-optimizer'),
            'manage_woocommerce',
            'dynamic-price-optimizer',
            array($this, 'render_main_page'),
            'dashicons-chart-line',
            56
        );

        add_submenu_page(
            'dynamic-price-optimizer',
            __('Settings', 'dynamic-price-optimizer'),
            __('Settings', 'dynamic-price-optimizer'),
            'manage_woocommerce',
            'dynamic-price-optimizer-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'dynamic-price-optimizer') === false) {
            return;
        }

        wp_enqueue_style(
            'dpo-admin',
            DPO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DPO_VERSION
        );

        wp_enqueue_script(
            'dpo-admin',
            DPO_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            DPO_VERSION,
            true
        );

        wp_localize_script('dpo-admin', 'dpoAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpo_nonce'),
            'i18n' => array(
                'analyzing' => __('Analyzing...', 'dynamic-price-optimizer'),
                'updating' => __('Updating...', 'dynamic-price-optimizer'),
                'success' => __('Success!', 'dynamic-price-optimizer'),
                'error' => __('Error occurred.', 'dynamic-price-optimizer')
            )
        ));
    }

    /**
     * Add product meta boxes
     */
    public function add_product_meta_boxes() {
        add_meta_box(
            'dpo_price_optimization',
            __('Price Optimization', 'dynamic-price-optimizer'),
            array($this, 'render_price_optimization_meta_box'),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render price optimization meta box
     */
    public function render_price_optimization_meta_box($post) {
        $product = wc_get_product($post->ID);
        $optimization_settings = get_post_meta($post->ID, '_dpo_optimization_settings', true);
        $optimization_enabled = isset($optimization_settings['enabled']) ? $optimization_settings['enabled'] : false;
        $min_markup = isset($optimization_settings['min_markup']) ? $optimization_settings['min_markup'] : 10;
        $max_markup = isset($optimization_settings['max_markup']) ? $optimization_settings['max_markup'] : 200;

        include DPO_PLUGIN_DIR . 'templates/admin/price-optimization-meta-box.php';
    }

    /**
     * Save product meta
     */
    public function save_product_meta($post_id) {
        if (!isset($_POST['dpo_optimization_settings'])) {
            return;
        }

        $optimization_settings = array(
            'enabled' => isset($_POST['dpo_optimization_settings']['enabled']),
            'min_markup' => floatval($_POST['dpo_optimization_settings']['min_markup']),
            'max_markup' => floatval($_POST['dpo_optimization_settings']['max_markup'])
        );

        update_post_meta($post_id, '_dpo_optimization_settings', $optimization_settings);
    }

    /**
     * Render main admin page
     */
    public function render_main_page() {
        include DPO_PLUGIN_DIR . 'templates/admin/main-page.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include DPO_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }

    /**
     * Handle analyze product AJAX request
     */
    public function handle_analyze_product() {
        check_ajax_referer('dpo_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'dynamic-price-optimizer'));
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID.', 'dynamic-price-optimizer'));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Product not found.', 'dynamic-price-optimizer'));
        }

        try {
            $analysis = $this->price_analyzer->analyze_product($product);
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
            wp_send_json_error(__('Permission denied.', 'dynamic-price-optimizer'));
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID.', 'dynamic-price-optimizer'));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Product not found.', 'dynamic-price-optimizer'));
        }

        try {
            $this->price_analyzer->update_product_price($product);
            wp_send_json_success(__('Price updated successfully.', 'dynamic-price-optimizer'));
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
            wp_send_json_error(__('Permission denied.', 'dynamic-price-optimizer'));
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();
        if (empty($product_ids)) {
            wp_send_json_error(__('No products selected.', 'dynamic-price-optimizer'));
        }

        $results = array();
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                try {
                    $this->price_analyzer->update_product_price($product);
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
            wp_send_json_error(__('Permission denied.', 'dynamic-price-optimizer'));
        }

        $settings = array(
            'min_markup' => floatval($_POST['min_markup']),
            'max_markup' => floatval($_POST['max_markup']),
            'update_frequency' => sanitize_text_field($_POST['update_frequency']),
            'competitor_update_frequency' => sanitize_text_field($_POST['competitor_update_frequency']),
            'risk_threshold' => floatval($_POST['risk_threshold']),
            'enable_auto_updates' => isset($_POST['enable_auto_updates']),
            'enable_competitor_tracking' => isset($_POST['enable_competitor_tracking']),
            'enable_risk_analysis' => isset($_POST['enable_risk_analysis']),
            'enable_market_analysis' => isset($_POST['enable_market_analysis'])
        );

        update_option('dpo_settings', $settings);
        wp_send_json_success(__('Settings saved successfully.', 'dynamic-price-optimizer'));
    }
} 