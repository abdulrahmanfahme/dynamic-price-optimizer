<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('dpo_settings', array());
$default_settings = array(
    'enable_auto_optimization' => 0,
    'optimization_frequency' => 'daily',
    'min_price_change' => 1,
    'max_price_change' => 15,
    'risk_threshold' => 70,
    'competitor_weight' => 30,
    'demand_weight' => 40,
    'margin_weight' => 30,
    'python_path' => '',
    'enable_logging' => 0,
    'notification_email' => get_option('admin_email'),
    'price_rounding' => 2
);
$settings = wp_parse_args($settings, $default_settings);
?>

<div class="wrap">
    <h1><?php esc_html_e('Dynamic Price Optimizer Settings', 'dynamic-price-optimizer'); ?></h1>

    <form method="post" action="options.php" class="dpo-settings-form">
        <?php settings_fields('dpo_settings'); ?>
        
        <div class="dpo-settings-section">
            <h2><?php esc_html_e('General Settings', 'dynamic-price-optimizer'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dpo_settings[enable_auto_optimization]">
                            <?php esc_html_e('Enable Auto Optimization', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="dpo_settings[enable_auto_optimization]" 
                               name="dpo_settings[enable_auto_optimization]" 
                               value="1" 
                               <?php checked(1, $settings['enable_auto_optimization']); ?>>
                        <p class="description">
                            <?php esc_html_e('Automatically optimize prices based on the schedule below.', 'dynamic-price-optimizer'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dpo_settings[optimization_frequency]">
                            <?php esc_html_e('Optimization Frequency', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="dpo_settings[optimization_frequency]" 
                                name="dpo_settings[optimization_frequency]">
                            <option value="hourly" <?php selected('hourly', $settings['optimization_frequency']); ?>>
                                <?php esc_html_e('Hourly', 'dynamic-price-optimizer'); ?>
                            </option>
                            <option value="daily" <?php selected('daily', $settings['optimization_frequency']); ?>>
                                <?php esc_html_e('Daily', 'dynamic-price-optimizer'); ?>
                            </option>
                            <option value="weekly" <?php selected('weekly', $settings['optimization_frequency']); ?>>
                                <?php esc_html_e('Weekly', 'dynamic-price-optimizer'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dpo-settings-section">
            <h2><?php esc_html_e('Price Optimization Settings', 'dynamic-price-optimizer'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dpo_settings[min_price_change]">
                            <?php esc_html_e('Minimum Price Change (%)', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="dpo_settings[min_price_change]" 
                               name="dpo_settings[min_price_change]" 
                               value="<?php echo esc_attr($settings['min_price_change']); ?>"
                               min="0" 
                               max="100" 
                               step="0.1">
                        <p class="description">
                            <?php esc_html_e('Minimum percentage change required to update a price.', 'dynamic-price-optimizer'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dpo_settings[max_price_change]">
                            <?php esc_html_e('Maximum Price Change (%)', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="dpo_settings[max_price_change]" 
                               name="dpo_settings[max_price_change]" 
                               value="<?php echo esc_attr($settings['max_price_change']); ?>"
                               min="0" 
                               max="100" 
                               step="0.1">
                        <p class="description">
                            <?php esc_html_e('Maximum percentage change allowed for price updates.', 'dynamic-price-optimizer'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dpo_settings[risk_threshold]">
                            <?php esc_html_e('Risk Threshold (%)', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="dpo_settings[risk_threshold]" 
                               name="dpo_settings[risk_threshold]" 
                               value="<?php echo esc_attr($settings['risk_threshold']); ?>"
                               min="0" 
                               max="100" 
                               step="1">
                        <p class="description">
                            <?php esc_html_e('Risk level threshold for highlighting high-risk products.', 'dynamic-price-optimizer'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dpo-settings-section">
            <h2><?php esc_html_e('Optimization Weights', 'dynamic-price-optimizer'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dpo_settings[competitor_weight]">
                            <?php esc_html_e('Competitor Price Weight (%)', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="dpo_settings[competitor_weight]" 
                               name="dpo_settings[competitor_weight]" 
                               value="<?php echo esc_attr($settings['competitor_weight']); ?>"
                               min="0" 
                               max="100" 
                               step="1"
                               class="dpo-weight-input">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dpo_settings[demand_weight]">
                            <?php esc_html_e('Demand Weight (%)', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="dpo_settings[demand_weight]" 
                               name="dpo_settings[demand_weight]" 
                               value="<?php echo esc_attr($settings['demand_weight']); ?>"
                               min="0" 
                               max="100" 
                               step="1"
                               class="dpo-weight-input">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dpo_settings[margin_weight]">
                            <?php esc_html_e('Profit Margin Weight (%)', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="dpo_settings[margin_weight]" 
                               name="dpo_settings[margin_weight]" 
                               value="<?php echo esc_attr($settings['margin_weight']); ?>"
                               min="0" 
                               max="100" 
                               step="1"
                               class="dpo-weight-input">
                    </td>
                </tr>
            </table>

            <p class="description dpo-weights-total">
                <?php esc_html_e('Total weights must equal 100%', 'dynamic-price-optimizer'); ?>
                <span id="dpo-weights-sum"></span>
            </p>
        </div>

        <div class="dpo-settings-section">
            <h2><?php esc_html_e('Advanced Settings', 'dynamic-price-optimizer'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dpo_settings[python_path]">
                            <?php esc_html_e('Python Executable Path', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="dpo_settings[python_path]" 
                               name="dpo_settings[python_path]" 
                               value="<?php echo esc_attr($settings['python_path']); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Full path to Python executable (e.g., /usr/bin/python3). Leave empty for auto-detection.', 'dynamic-price-optimizer'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dpo_settings[price_rounding]">
                            <?php esc_html_e('Price Rounding (Decimals)', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="dpo_settings[price_rounding]" 
                               name="dpo_settings[price_rounding]" 
                               value="<?php echo esc_attr($settings['price_rounding']); ?>"
                               min="0" 
                               max="4" 
                               step="1">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dpo_settings[enable_logging]">
                            <?php esc_html_e('Enable Logging', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="dpo_settings[enable_logging]" 
                               name="dpo_settings[enable_logging]" 
                               value="1" 
                               <?php checked(1, $settings['enable_logging']); ?>>
                        <p class="description">
                            <?php esc_html_e('Log optimization activities for debugging purposes.', 'dynamic-price-optimizer'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dpo_settings[notification_email]">
                            <?php esc_html_e('Notification Email', 'dynamic-price-optimizer'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="email" 
                               id="dpo_settings[notification_email]" 
                               name="dpo_settings[notification_email]" 
                               value="<?php echo esc_attr($settings['notification_email']); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Email address for notifications about price changes and high-risk products.', 'dynamic-price-optimizer'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    function updateWeightsSum() {
        var sum = 0;
        $('.dpo-weight-input').each(function() {
            sum += parseInt($(this).val()) || 0;
        });
        $('#dpo-weights-sum').text(sum + '%');
        
        if (sum !== 100) {
            $('#dpo-weights-sum').addClass('error');
        } else {
            $('#dpo-weights-sum').removeClass('error');
        }
    }

    $('.dpo-weight-input').on('input', updateWeightsSum);
    updateWeightsSum();
});
</script> 