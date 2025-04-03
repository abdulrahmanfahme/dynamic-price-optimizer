<?php
if (!defined('ABSPATH')) {
    exit;
}

$product_id = $product->get_id();
$current_price = $product->get_price();
$cost = $product->get_meta('_cost');
$markup = $cost > 0 ? (($current_price - $cost) / $cost) * 100 : 0;

// Get optimization settings
$optimization_settings = $product->get_meta('_dpo_optimization_settings');
$optimization_enabled = isset($optimization_settings['enabled']) ? $optimization_settings['enabled'] : false;
$min_markup = isset($optimization_settings['min_markup']) ? $optimization_settings['min_markup'] : 10;
$max_markup = isset($optimization_settings['max_markup']) ? $optimization_settings['max_markup'] : 200;
?>

<div class="dpo-product-meta-box">
    <!-- Enable/Disable Optimization -->
    <div class="dpo-section">
        <label>
            <input type="checkbox" name="dpo_optimization_settings[enabled]" 
                   value="1" <?php checked($optimization_enabled, true); ?>>
            <?php esc_html_e('Enable Price Optimization', 'dynamic-price-optimizer'); ?>
        </label>
    </div>

    <!-- Current Price Information -->
    <div class="dpo-section">
        <h4><?php esc_html_e('Current Price Information', 'dynamic-price-optimizer'); ?></h4>
        <table class="dpo-info-table">
            <tr>
                <td><?php esc_html_e('Current Price:', 'dynamic-price-optimizer'); ?></td>
                <td><?php echo wc_price($current_price); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Cost:', 'dynamic-price-optimizer'); ?></td>
                <td><?php echo wc_price($cost); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Current Markup:', 'dynamic-price-optimizer'); ?></td>
                <td><?php echo number_format($markup, 1); ?>%</td>
            </tr>
        </table>
    </div>

    <!-- Optimization Settings -->
    <div class="dpo-section">
        <h4><?php esc_html_e('Optimization Settings', 'dynamic-price-optimizer'); ?></h4>
        <table class="dpo-settings-table">
            <tr>
                <td>
                    <label for="dpo_min_markup_<?php echo esc_attr($product_id); ?>">
                        <?php esc_html_e('Minimum Markup (%):', 'dynamic-price-optimizer'); ?>
                    </label>
                </td>
                <td>
                    <input type="number" id="dpo_min_markup_<?php echo esc_attr($product_id); ?>" 
                           name="dpo_optimization_settings[min_markup]" 
                           value="<?php echo esc_attr($min_markup); ?>" 
                           min="0" max="100" step="0.1">
                </td>
            </tr>
            <tr>
                <td>
                    <label for="dpo_max_markup_<?php echo esc_attr($product_id); ?>">
                        <?php esc_html_e('Maximum Markup (%):', 'dynamic-price-optimizer'); ?>
                    </label>
                </td>
                <td>
                    <input type="number" id="dpo_max_markup_<?php echo esc_attr($product_id); ?>" 
                           name="dpo_optimization_settings[max_markup]" 
                           value="<?php echo esc_attr($max_markup); ?>" 
                           min="0" max="1000" step="0.1">
                </td>
            </tr>
        </table>
    </div>

    <!-- Quick Actions -->
    <div class="dpo-section">
        <h4><?php esc_html_e('Quick Actions', 'dynamic-price-optimizer'); ?></h4>
        <div class="dpo-actions">
            <button type="button" class="button dpo-optimize-button" 
                    data-product-id="<?php echo esc_attr($product_id); ?>">
                <?php esc_html_e('Analyze Price', 'dynamic-price-optimizer'); ?>
            </button>
            <button type="button" class="button dpo-view-history-button" 
                    data-product-id="<?php echo esc_attr($product_id); ?>">
                <?php esc_html_e('View History', 'dynamic-price-optimizer'); ?>
            </button>
        </div>
    </div>

    <!-- Optimization Status -->
    <div class="dpo-section">
        <h4><?php esc_html_e('Optimization Status', 'dynamic-price-optimizer'); ?></h4>
        <div class="dpo-status">
            <?php
            $last_optimization = $product->get_meta('_dpo_last_optimization');
            if ($last_optimization) {
                printf(
                    esc_html__('Last optimized: %s', 'dynamic-price-optimizer'),
                    esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_optimization)))
                );
            } else {
                esc_html_e('Not optimized yet', 'dynamic-price-optimizer');
            }
            ?>
        </div>
    </div>
</div>

<style>
.dpo-product-meta-box {
    padding: 10px;
}

.dpo-section {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.dpo-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.dpo-section h4 {
    margin: 0 0 10px 0;
    padding: 0;
}

.dpo-info-table,
.dpo-settings-table {
    width: 100%;
    border-collapse: collapse;
}

.dpo-info-table td,
.dpo-settings-table td {
    padding: 5px 0;
}

.dpo-info-table td:first-child,
.dpo-settings-table td:first-child {
    width: 40%;
    color: #666;
}

.dpo-actions {
    display: flex;
    gap: 10px;
}

.dpo-status {
    color: #666;
    font-style: italic;
}

input[type="number"] {
    width: 80px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle optimize button click
    $('.dpo-optimize-button').on('click', function() {
        var productId = $(this).data('product-id');
        var button = $(this);
        
        button.prop('disabled', true).text(dpoAdmin.i18n.analyzing);
        
        $.ajax({
            url: dpoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dpo_analyze_product',
                nonce: dpoAdmin.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    showOptimizationModal(response.data);
                } else {
                    alert(dpoAdmin.i18n.error);
                }
            },
            error: function() {
                alert(dpoAdmin.i18n.error);
            },
            complete: function() {
                button.prop('disabled', false).text(dpoAdmin.i18n.analyze);
            }
        });
    });

    // Handle view history button click
    $('.dpo-view-history-button').on('click', function() {
        var productId = $(this).data('product-id');
        window.location.href = dpoAdmin.adminUrl + '?page=dynamic-price-optimizer&product_id=' + productId;
    });

    function showOptimizationModal(data) {
        var modal = $('#dpo-optimization-modal');
        
        // Update modal content
        $('#dpo-market-analysis').html(`
            <p>${data.market_analysis.price_trend > 0 ? 'Increasing' : 'Decreasing'} price trend</p>
            <p>Category trend: ${data.market_analysis.category_trend}</p>
        `);

        $('#dpo-competitor-analysis').html(`
            <p>Average competitor price: ${data.competitor_prices.average_price}</p>
            <p>Price position: ${data.competitor_prices.price_position.percentile}th percentile</p>
        `);

        $('#dpo-customer-analysis').html(`
            <p>Purchase frequency: ${data.customer_behavior.purchase_frequency}</p>
            <p>View to purchase ratio: ${data.customer_behavior.view_to_purchase_ratio}</p>
        `);

        var riskHtml = '';
        data.risk_analysis.forEach(function(risk) {
            riskHtml += `<p class="risk-${risk.level}">${risk.message}</p>`;
        });
        $('#dpo-risk-analysis').html(riskHtml);

        // Show modal
        modal.show();
    }
});
</script> 