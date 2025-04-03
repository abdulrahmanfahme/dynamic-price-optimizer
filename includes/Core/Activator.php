<?php
namespace DynamicPriceOptimizer\Core;

/**
 * Handles plugin activation tasks
 */
class Activator {
    /**
     * Create necessary database tables
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Competitor prices table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}dpo_competitor_prices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            competitor_url varchar(255) NOT NULL,
            price decimal(10,2) NOT NULL,
            last_updated datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id)
        ) $charset_collate;";

        // Market analysis table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}dpo_market_analysis (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            date date NOT NULL,
            market_price decimal(10,2) NOT NULL,
            market_share decimal(5,2) NOT NULL,
            price_competitiveness decimal(5,2) NOT NULL,
            trend_indicator decimal(5,2) NOT NULL,
            market_volatility decimal(5,2) NOT NULL,
            market_opportunity decimal(5,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY date (date)
        ) $charset_collate;";

        // Risk analysis table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}dpo_risk_analysis (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            date date NOT NULL,
            profit_margin decimal(5,2) NOT NULL,
            price_volatility decimal(5,2) NOT NULL,
            stock_risk decimal(5,2) NOT NULL,
            order_cancellation_rate decimal(5,2) NOT NULL,
            overall_risk decimal(5,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY date (date)
        ) $charset_collate;";

        // Sales data table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}dpo_sales_data (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            date date NOT NULL,
            orders_count int(11) NOT NULL,
            revenue decimal(10,2) NOT NULL,
            average_order_value decimal(10,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY date (date)
        ) $charset_collate;";

        // Customer data table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}dpo_customer_data (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            date date NOT NULL,
            views int(11) NOT NULL,
            add_to_cart int(11) NOT NULL,
            purchases int(11) NOT NULL,
            conversion_rate decimal(5,2) NOT NULL,
            cart_abandonment_rate decimal(5,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY date (date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add default options
        add_option('dpo_settings', array(
            'min_markup' => 10,
            'max_markup' => 200,
            'update_frequency' => 'daily',
            'competitor_update_frequency' => 'weekly',
            'risk_threshold' => 70,
            'ml_model_path' => '',
            'enable_auto_updates' => true,
            'enable_competitor_tracking' => true,
            'enable_risk_analysis' => true,
            'enable_market_analysis' => true
        ));

        add_option('dpo_version', DPO_VERSION);

        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $dpo_dir = $upload_dir['basedir'] . '/dpo-logs';
        if (!file_exists($dpo_dir)) {
            wp_mkdir_p($dpo_dir);
        }

        // Create .htaccess to protect logs
        $htaccess_file = $dpo_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "deny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Schedule cron jobs
        if (!wp_next_scheduled('dpo_daily_price_update')) {
            wp_schedule_event(time(), 'daily', 'dpo_daily_price_update');
        }
        if (!wp_next_scheduled('dpo_weekly_competitor_update')) {
            wp_schedule_event(time(), 'weekly', 'dpo_weekly_competitor_update');
        }
    }
} 