<?php
/**
 * Plugin Name: Dynamic Price Optimizer
 * Plugin URI: https://example.com/dynamic-price-optimizer
 * Description: A powerful WooCommerce plugin that uses machine learning and market analysis to optimize product prices.
 * Version: 1.0.0
 * Author: Your Company Name
 * Author URI: https://example.com
 * Text Domain: dynamic-price-optimizer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package DynamicPriceOptimizer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DPO_VERSION', '1.0.0');
define('DPO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DPO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DPO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'DynamicPriceOptimizer\\';
    $base_dir = DPO_PLUGIN_DIR . 'includes/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function dpo_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Dynamic Price Optimizer requires WooCommerce to be installed and active.', 'dynamic-price-optimizer'); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Dynamic Price Optimizer requires PHP 7.4 or higher.', 'dynamic-price-optimizer'); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Check Python version
    try {
        $ml_engine = new DynamicPriceOptimizer\Core\MLEngine();
    } catch (\Exception $e) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Dynamic Price Optimizer requires Python to be installed.', 'dynamic-price-optimizer'); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Load text domain
    load_plugin_textdomain('dynamic-price-optimizer', false, dirname(DPO_PLUGIN_BASENAME) . '/languages');

    // Initialize core components
    $plugin = new DynamicPriceOptimizer\Core\Plugin();
    $plugin->init();
}
add_action('plugins_loaded', 'dpo_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Run activation tasks
    DynamicPriceOptimizer\Core\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('dpo_daily_price_update');
    wp_clear_scheduled_hook('dpo_weekly_competitor_update');
});

// Uninstall hook
register_uninstall_hook(__FILE__, function() {
    // Delete plugin options
    delete_option('dpo_settings');
    delete_option('dpo_version');

    // Delete product meta
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_dpo_%'");

    // Delete plugin tables
    $tables = array(
        'dpo_competitor_prices',
        'dpo_market_analysis',
        'dpo_risk_analysis',
        'dpo_sales_data',
        'dpo_customer_data'
    );

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
    }

    // Delete logs directory
    $upload_dir = wp_upload_dir();
    $dpo_dir = $upload_dir['basedir'] . '/dpo-logs';
    if (file_exists($dpo_dir)) {
        array_map('unlink', glob("$dpo_dir/*.*"));
        rmdir($dpo_dir);
    }

    // Delete data directory
    $data_dir = $upload_dir['basedir'] . '/dpo-data';
    if (file_exists($data_dir)) {
        array_map('unlink', glob("$data_dir/*.*"));
        rmdir($data_dir);
    }

    // Delete models directory
    $models_dir = $upload_dir['basedir'] . '/dpo-models';
    if (file_exists($models_dir)) {
        array_map('unlink', glob("$models_dir/*.*"));
        rmdir($models_dir);
    }
}); 