<?php
/**
 * Plugin Name: Dynamic Price Optimizer
 * Plugin URI: https://example.com/dynamic-price-optimizer
 * Description: Optimize product pricing dynamically based on market trends, competitor prices, and customer behavior.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: dynamic-price-optimizer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DPO_VERSION', '1.0.0');
define('DPO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DPO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'DynamicPriceOptimizer\\';
    $base_dir = DPO_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

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
                <p><?php _e('Dynamic Price Optimizer requires WooCommerce to be installed and active.', 'dynamic-price-optimizer'); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Initialize main plugin class
    require_once DPO_PLUGIN_DIR . 'includes/class-dynamic-price-optimizer.php';
    $plugin = new DynamicPriceOptimizer\DynamicPriceOptimizer();
    $plugin->init();
}
add_action('plugins_loaded', 'dpo_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once DPO_PLUGIN_DIR . 'includes/class-dynamic-price-optimizer-activator.php';
    DynamicPriceOptimizer\DynamicPriceOptimizerActivator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    require_once DPO_PLUGIN_DIR . 'includes/class-dynamic-price-optimizer-deactivator.php';
    DynamicPriceOptimizer\DynamicPriceOptimizerDeactivator::deactivate();
}); 