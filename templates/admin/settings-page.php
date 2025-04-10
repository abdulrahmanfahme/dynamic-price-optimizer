<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('dpo_settings_nonce'); ?>

        <div class="dpo-settings-grid">
            <div class="dpo-settings-section">
                <h2><?php _e('Price Optimization Settings', 'dynamic-price-optimizer'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dpo_min_margin">
                                <?php _e('Minimum Margin', 'dynamic-price-optimizer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dpo_min_margin" 
                                   name="dpo_min_margin" 
                                   value="<?php echo esc_attr($settings['min_margin']); ?>"
                                   step="0.01"
                                   min="0"
                                   max="1"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Minimum profit margin (0-1)', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="dpo_max_margin">
                                <?php _e('Maximum Margin', 'dynamic-price-optimizer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dpo_max_margin" 
                                   name="dpo_max_margin" 
                                   value="<?php echo esc_attr($settings['max_margin']); ?>"
                                   step="0.01"
                                   min="0"
                                   max="1"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Maximum profit margin (0-1)', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="dpo_max_price_change">
                                <?php _e('Maximum Price Change', 'dynamic-price-optimizer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dpo_max_price_change" 
                                   name="dpo_max_price_change" 
                                   value="<?php echo esc_attr($settings['max_price_change']); ?>"
                                   step="0.01"
                                   min="0"
                                   max="1"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Maximum allowed price change (0-1)', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="dpo-settings-section">
                <h2><?php _e('Weight Settings', 'dynamic-price-optimizer'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dpo_competitor_weight">
                                <?php _e('Competitor Weight', 'dynamic-price-optimizer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dpo_competitor_weight" 
                                   name="dpo_competitor_weight" 
                                   value="<?php echo esc_attr($settings['competitor_weight']); ?>"
                                   step="0.01"
                                   min="0"
                                   max="1"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Weight for competitor price factor (0-1)', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="dpo_historical_weight">
                                <?php _e('Historical Weight', 'dynamic-price-optimizer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dpo_historical_weight" 
                                   name="dpo_historical_weight" 
                                   value="<?php echo esc_attr($settings['historical_weight']); ?>"
                                   step="0.01"
                                   min="0"
                                   max="1"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Weight for historical data factor (0-1)', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="dpo_seasonal_weight">
                                <?php _e('Seasonal Weight', 'dynamic-price-optimizer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dpo_seasonal_weight" 
                                   name="dpo_seasonal_weight" 
                                   value="<?php echo esc_attr($settings['seasonal_weight']); ?>"
                                   step="0.01"
                                   min="0"
                                   max="1"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Weight for seasonal factor (0-1)', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="dpo_inventory_weight">
                                <?php _e('Inventory Weight', 'dynamic-price-optimizer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dpo_inventory_weight" 
                                   name="dpo_inventory_weight" 
                                   value="<?php echo esc_attr($settings['inventory_weight']); ?>"
                                   step="0.01"
                                   min="0"
                                   max="1"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Weight for inventory factor (0-1)', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="dpo_demand_weight">
                                <?php _e('Demand Weight', 'dynamic-price-optimizer'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dpo_demand_weight" 
                                   name="dpo_demand_weight" 
                                   value="<?php echo esc_attr($settings['demand_weight']); ?>"
                                   step="0.01"
                                   min="0"
                                   max="1"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Weight for demand factor (0-1)', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="dpo-settings-section">
                <h2><?php _e('Competitor API Settings', 'dynamic-price-optimizer'); ?></h2>

                <div id="dpo-competitor-apis">
                    <?php foreach ($settings['competitor_apis'] as $index => $api): ?>
                        <div class="dpo-api-entry">
                            <p>
                                <label>
                                    <?php _e('API Name:', 'dynamic-price-optimizer'); ?>
                                    <input type="text" 
                                           name="dpo_competitor_apis[<?php echo $index; ?>][name]" 
                                           value="<?php echo esc_attr($api['name']); ?>"
                                           class="regular-text">
                                </label>
                            </p>
                            <p>
                                <label>
                                    <?php _e('API URL:', 'dynamic-price-optimizer'); ?>
                                    <input type="url" 
                                           name="dpo_competitor_apis[<?php echo $index; ?>][url]" 
                                           value="<?php echo esc_attr($api['url']); ?>"
                                           class="regular-text">
                                </label>
                            </p>
                            <p>
                                <label>
                                    <?php _e('API Key:', 'dynamic-price-optimizer'); ?>
                                    <input type="password" 
                                           name="dpo_competitor_apis[<?php echo $index; ?>][api_key]" 
                                           value="<?php echo esc_attr($api['api_key']); ?>"
                                           class="regular-text">
                                </label>
                            </p>
                            <button type="button" class="button button-secondary dpo-remove-api">
                                <?php _e('Remove API', 'dynamic-price-optimizer'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="button button-secondary" id="dpo-add-api">
                    <?php _e('Add API', 'dynamic-price-optimizer'); ?>
                </button>
            </div>

            <div class="dpo-settings-section">
                <h2><?php _e('Holiday Settings', 'dynamic-price-optimizer'); ?></h2>

                <div id="dpo-holidays">
                    <?php foreach ($settings['holidays'] as $index => $holiday): ?>
                        <div class="dpo-holiday-entry">
                            <input type="date" 
                                   name="dpo_holidays[]" 
                                   value="<?php echo esc_attr($holiday); ?>"
                                   class="regular-text">
                            <button type="button" class="button button-secondary dpo-remove-holiday">
                                <?php _e('Remove', 'dynamic-price-optimizer'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="button button-secondary" id="dpo-add-holiday">
                    <?php _e('Add Holiday', 'dynamic-price-optimizer'); ?>
                </button>
            </div>

            <div class="dpo-settings-section">
                <h2><?php _e('Automation Settings', 'dynamic-price-optimizer'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Automatic Updates', 'dynamic-price-optimizer'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="dpo_auto_update" 
                                       value="1" 
                                       <?php checked($settings['auto_update']); ?>>
                                <?php _e('Enable automatic price updates', 'dynamic-price-optimizer'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Automatically update prices based on optimization rules', 'dynamic-price-optimizer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <input type="submit" 
                   name="dpo_save_settings" 
                   class="button button-primary" 
                   value="<?php esc_attr_e('Save Settings', 'dynamic-price-optimizer'); ?>">
        </p>
    </form>
</div>

<script type="text/template" id="dpo-api-template">
    <div class="dpo-api-entry">
        <p>
            <label>
                <?php _e('API Name:', 'dynamic-price-optimizer'); ?>
                <input type="text" 
                       name="dpo_competitor_apis[{{index}}][name]" 
                       class="regular-text">
            </label>
        </p>
        <p>
            <label>
                <?php _e('API URL:', 'dynamic-price-optimizer'); ?>
                <input type="url" 
                       name="dpo_competitor_apis[{{index}}][url]" 
                       class="regular-text">
            </label>
        </p>
        <p>
            <label>
                <?php _e('API Key:', 'dynamic-price-optimizer'); ?>
                <input type="password" 
                       name="dpo_competitor_apis[{{index}}][api_key]" 
                       class="regular-text">
            </label>
        </p>
        <button type="button" class="button button-secondary dpo-remove-api">
            <?php _e('Remove API', 'dynamic-price-optimizer'); ?>
        </button>
    </div>
</script>

<script type="text/template" id="dpo-holiday-template">
    <div class="dpo-holiday-entry">
        <input type="date" 
               name="dpo_holidays[]" 
               class="regular-text">
        <button type="button" class="button button-secondary dpo-remove-holiday">
            <?php _e('Remove', 'dynamic-price-optimizer'); ?>
        </button>
    </div>
</script> 