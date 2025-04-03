<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="dpo-variation-price-optimization">
    <h4><?php esc_html_e('Price Optimization', 'dynamic-price-optimizer'); ?></h4>
    
    <div class="dpo-form-row">
        <label>
            <input type="checkbox" 
                   name="dpo_optimization_settings[<?php echo esc_attr($loop); ?>][enabled]" 
                   value="1" 
                   <?php checked($optimization_enabled, true); ?>>
            <?php esc_html_e('Enable Price Optimization', 'dynamic-price-optimizer'); ?>
        </label>
    </div>

    <div class="dpo-form-row">
        <label for="dpo_min_markup_<?php echo esc_attr($loop); ?>">
            <?php esc_html_e('Minimum Markup (%):', 'dynamic-price-optimizer'); ?>
        </label>
        <input type="number" 
               id="dpo_min_markup_<?php echo esc_attr($loop); ?>" 
               name="dpo_optimization_settings[<?php echo esc_attr($loop); ?>][min_markup]" 
               value="<?php echo esc_attr($min_markup); ?>" 
               min="0" 
               max="100" 
               step="0.1">
    </div>

    <div class="dpo-form-row">
        <label for="dpo_max_markup_<?php echo esc_attr($loop); ?>">
            <?php esc_html_e('Maximum Markup (%):', 'dynamic-price-optimizer'); ?>
        </label>
        <input type="number" 
               id="dpo_max_markup_<?php echo esc_attr($loop); ?>" 
               name="dpo_optimization_settings[<?php echo esc_attr($loop); ?>][max_markup]" 
               value="<?php echo esc_attr($max_markup); ?>" 
               min="0" 
               max="1000" 
               step="0.1">
    </div>

    <div class="dpo-form-row">
        <button type="button" 
                class="button dpo-optimize-variation-button" 
                data-variation-id="<?php echo esc_attr($variation_id); ?>"
                data-loop="<?php echo esc_attr($loop); ?>">
            <?php esc_html_e('Analyze Price', 'dynamic-price-optimizer'); ?>
        </button>
    </div>

    <div class="dpo-optimization-status">
        <?php
        $last_optimization = get_post_meta($variation_id, '_dpo_last_optimization', true);
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

<style>
.dpo-variation-price-optimization {
    padding: 10px;
    margin: 10px 0;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
}

.dpo-variation-price-optimization h4 {
    margin: 0 0 10px 0;
    padding: 0;
    font-size: 14px;
    font-weight: 600;
}

.dpo-form-row {
    margin-bottom: 10px;
}

.dpo-form-row:last-child {
    margin-bottom: 0;
}

.dpo-form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.dpo-form-row input[type="number"] {
    width: 80px;
}

.dpo-optimization-status {
    margin-top: 10px;
    color: #666;
    font-style: italic;
    font-size: 12px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.dpo-optimize-variation-button').on('click', function() {
        var button = $(this);
        var variationId = button.data('variation-id');
        var loop = button.data('loop');
        
        button.prop('disabled', true).text(dpoAdmin.i18n.analyzing);
        
        $.ajax({
            url: dpoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dpo_analyze_product',
                nonce: dpoAdmin.nonce,
                product_id: variationId,
                is_variation: true
            },
            success: function(response) {
                if (response.success) {
                    showOptimizationModal(response.data, variationId, loop);
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

    function showOptimizationModal(data, variationId, loop) {
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

        // Update apply button
        $('#dpo-apply-optimization')
            .data('variation-id', variationId)
            .data('loop', loop)
            .show();

        // Show modal
        modal.show();
    }
});
</script> 