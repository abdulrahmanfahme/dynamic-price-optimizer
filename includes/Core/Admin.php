<?php
namespace DynamicPriceOptimizer\Core;

class Admin {
    private $price_analyzer;
    private $competitor_tracker;

    public function __construct() {
        $this->price_analyzer = new PriceAnalyzer();
        $this->competitor_tracker = new CompetitorTracker();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        // Add menu items
        add_action('admin_menu', [$this, 'add_menu_items']);
        
        // Add settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add AJAX handlers
        add_action('wp_ajax_dpo_analyze_product', [$this, 'handle_analyze_product']);
        add_action('wp_ajax_dpo_update_product_price', [$this, 'handle_update_product_price']);
        
        // Add product meta box
        add_action('add_meta_boxes', [$this, 'add_product_meta_box']);
        add_action('save_post_product', [$this, 'save_product_meta']);
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_menu_items() {
        add_menu_page(
            __('Dynamic Price Optimizer', 'dynamic-price-optimizer'),
            __('Price Optimizer', 'dynamic-price-optimizer'),
            'manage_woocommerce',
            'dynamic-price-optimizer',
            [$this, 'render_main_page'],
            'dashicons-chart-line',
            56
        );

        add_submenu_page(
            'dynamic-price-optimizer',
            __('Settings', 'dynamic-price-optimizer'),
            __('Settings', 'dynamic-price-optimizer'),
            'manage_woocommerce',
            'dynamic-price-optimizer-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('dpo_settings', 'dpo_settings');

        add_settings_section(
            'dpo_general_section',
            __('General Settings', 'dynamic-price-optimizer'),
            [$this, 'render_general_section'],
            'dynamic-price-optimizer-settings'
        );

        add_settings_field(
            'dpo_min_markup',
            __('Minimum Markup (%)', 'dynamic-price-optimizer'),
            [$this, 'render_min_markup_field'],
            'dynamic-price-optimizer-settings',
            'dpo_general_section'
        );

        add_settings_field(
            'dpo_max_markup',
            __('Maximum Markup (%)', 'dynamic-price-optimizer'),
            [$this, 'render_max_markup_field'],
            'dynamic-price-optimizer-settings',
            'dpo_general_section'
        );

        add_settings_field(
            'dpo_auto_update',
            __('Auto Update Prices', 'dynamic-price-optimizer'),
            [$this, 'render_auto_update_field'],
            'dynamic-price-optimizer-settings',
            'dpo_general_section'
        );
    }

    public function render_main_page() {
        include DPO_PLUGIN_DIR . 'templates/admin/main-page.php';
    }

    public function render_settings_page() {
        include DPO_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }

    public function render_general_section() {
        echo '<p>' . esc_html__('Configure general settings for the Dynamic Price Optimizer.', 'dynamic-price-optimizer') . '</p>';
    }

    public function render_min_markup_field() {
        $options = get_option('dpo_settings');
        $value = isset($options['min_markup']) ? $options['min_markup'] : 10;
        ?>
        <input type="number" name="dpo_settings[min_markup]" value="<?php echo esc_attr($value); ?>" min="0" max="100" step="0.1">
        <p class="description"><?php esc_html_e('Minimum markup percentage above cost', 'dynamic-price-optimizer'); ?></p>
        <?php
    }

    public function render_max_markup_field() {
        $options = get_option('dpo_settings');
        $value = isset($options['max_markup']) ? $options['max_markup'] : 200;
        ?>
        <input type="number" name="dpo_settings[max_markup]" value="<?php echo esc_attr($value); ?>" min="0" max="1000" step="0.1">
        <p class="description"><?php esc_html_e('Maximum markup percentage above cost', 'dynamic-price-optimizer'); ?></p>
        <?php
    }

    public function render_auto_update_field() {
        $options = get_option('dpo_settings');
        $value = isset($options['auto_update']) ? $options['auto_update'] : false;
        ?>
        <label>
            <input type="checkbox" name="dpo_settings[auto_update]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Automatically update prices based on optimization', 'dynamic-price-optimizer'); ?>
        </label>
        <?php
    }

    public function add_product_meta_box() {
        add_meta_box(
            'dpo_price_optimization',
            __('Price Optimization', 'dynamic-price-optimizer'),
            [$this, 'render_price_optimization_meta_box'],
            'product',
            'side',
            'high'
        );
    }

    public function render_price_optimization_meta_box($post) {
        wp_nonce_field('dpo_price_optimization', 'dpo_price_optimization_nonce');
        
        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }

        $current_price = $product->get_price();
        $cost = $product->get_meta('_cost');
        $markup = $cost > 0 ? (($current_price - $cost) / $cost) * 100 : 0;

        include DPO_PLUGIN_DIR . 'templates/admin/product-meta-box.php';
    }

    public function save_product_meta($post_id) {
        if (!isset($_POST['dpo_price_optimization_nonce']) || 
            !wp_verify_nonce($_POST['dpo_price_optimization_nonce'], 'dpo_price_optimization')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save optimization settings
        if (isset($_POST['dpo_optimization_settings'])) {
            update_post_meta($post_id, '_dpo_optimization_settings', sanitize_text_field($_POST['dpo_optimization_settings']));
        }
    }

    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php', 'toplevel_page_dynamic-price-optimizer'])) {
            return;
        }

        wp_enqueue_style(
            'dpo-admin',
            DPO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            DPO_VERSION
        );

        wp_enqueue_script(
            'dpo-admin',
            DPO_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            DPO_VERSION,
            true
        );

        wp_localize_script('dpo-admin', 'dpoAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpo_admin_nonce'),
            'i18n' => [
                'analyzing' => __('Analyzing...', 'dynamic-price-optimizer'),
                'updating' => __('Updating...', 'dynamic-price-optimizer'),
                'success' => __('Success!', 'dynamic-price-optimizer'),
                'error' => __('Error!', 'dynamic-price-optimizer')
            ]
        ]);
    }

    public function handle_analyze_product() {
        check_ajax_referer('dpo_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        $analysis = $this->price_analyzer->analyze_product($product_id);
        if (!$analysis) {
            wp_send_json_error('Analysis failed');
        }

        wp_send_json_success($analysis);
    }

    public function handle_update_product_price() {
        check_ajax_referer('dpo_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $new_price = isset($_POST['new_price']) ? floatval($_POST['new_price']) : 0;

        if (!$product_id || $new_price <= 0) {
            wp_send_json_error('Invalid data');
        }

        $result = $this->price_analyzer->update_product_price($product_id, $new_price);
        if (!$result) {
            wp_send_json_error('Failed to update price');
        }

        wp_send_json_success([
            'message' => __('Price updated successfully', 'dynamic-price-optimizer'),
            'new_price' => $new_price
        ]);
    }
} 