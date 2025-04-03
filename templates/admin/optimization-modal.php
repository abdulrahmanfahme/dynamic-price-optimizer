<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="dpo-optimization-modal" class="dpo-modal">
    <div class="dpo-modal-content">
        <div class="dpo-modal-header">
            <h3 class="dpo-modal-title"><?php esc_html_e('Price Optimization Analysis', 'dynamic-price-optimizer'); ?></h3>
            <button type="button" class="dpo-modal-close">&times;</button>
        </div>

        <div class="dpo-modal-body">
            <!-- Market Analysis Section -->
            <div class="dpo-analysis-section">
                <h4><?php esc_html_e('Market Analysis', 'dynamic-price-optimizer'); ?></h4>
                <div id="dpo-market-analysis">
                    <p class="dpo-loading"><?php esc_html_e('Loading market analysis...', 'dynamic-price-optimizer'); ?></p>
                </div>
            </div>

            <!-- Competitor Analysis Section -->
            <div class="dpo-analysis-section">
                <h4><?php esc_html_e('Competitor Analysis', 'dynamic-price-optimizer'); ?></h4>
                <div id="dpo-competitor-analysis">
                    <p class="dpo-loading"><?php esc_html_e('Loading competitor analysis...', 'dynamic-price-optimizer'); ?></p>
                </div>
            </div>

            <!-- Customer Behavior Section -->
            <div class="dpo-analysis-section">
                <h4><?php esc_html_e('Customer Behavior', 'dynamic-price-optimizer'); ?></h4>
                <div id="dpo-customer-analysis">
                    <p class="dpo-loading"><?php esc_html_e('Loading customer behavior analysis...', 'dynamic-price-optimizer'); ?></p>
                </div>
            </div>

            <!-- Risk Analysis Section -->
            <div class="dpo-analysis-section">
                <h4><?php esc_html_e('Risk Analysis', 'dynamic-price-optimizer'); ?></h4>
                <div id="dpo-risk-analysis">
                    <p class="dpo-loading"><?php esc_html_e('Loading risk analysis...', 'dynamic-price-optimizer'); ?></p>
                </div>
            </div>

            <!-- Price Recommendation Section -->
            <div class="dpo-analysis-section">
                <h4><?php esc_html_e('Price Recommendation', 'dynamic-price-optimizer'); ?></h4>
                <div id="dpo-price-recommendation">
                    <p class="dpo-loading"><?php esc_html_e('Calculating optimal price...', 'dynamic-price-optimizer'); ?></p>
                </div>
            </div>
        </div>

        <div class="dpo-modal-footer">
            <button type="button" class="button button-secondary dpo-modal-close">
                <?php esc_html_e('Close', 'dynamic-price-optimizer'); ?>
            </button>
            <button type="button" class="button button-primary" id="dpo-apply-optimization">
                <?php esc_html_e('Apply Optimization', 'dynamic-price-optimizer'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.dpo-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999999;
}

.dpo-modal-content {
    position: relative;
    background: #fff;
    width: 90%;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-height: 90vh;
    overflow-y: auto;
}

.dpo-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f1;
}

.dpo-modal-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.dpo-modal-close {
    background: none;
    border: none;
    color: #646970;
    cursor: pointer;
    font-size: 20px;
    padding: 0;
}

.dpo-modal-body {
    margin-bottom: 20px;
}

.dpo-analysis-section {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.dpo-analysis-section:last-child {
    margin-bottom: 0;
}

.dpo-analysis-section h4 {
    margin: 0 0 10px 0;
    color: #1d2327;
    font-size: 14px;
    font-weight: 600;
}

.dpo-analysis-section p {
    margin: 0 0 5px 0;
    color: #646970;
}

.dpo-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #f0f0f1;
}

/* Risk Analysis Styles */
.risk-high {
    color: #d63638;
}

.risk-medium {
    color: #dba617;
}

.risk-low {
    color: #00a32a;
}

/* Loading State */
.dpo-loading {
    color: #646970;
    font-style: italic;
}

/* Responsive Styles */
@media screen and (max-width: 782px) {
    .dpo-modal-content {
        width: 95%;
        margin: 20px auto;
    }

    .dpo-modal-footer {
        flex-direction: column;
    }

    .dpo-modal-footer button {
        width: 100%;
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Close modal on button click
    $('.dpo-modal-close').on('click', function() {
        $('#dpo-optimization-modal').hide();
    });

    // Close modal on outside click
    $(window).on('click', function(event) {
        if (event.target === $('#dpo-optimization-modal')[0]) {
            $('#dpo-optimization-modal').hide();
        }
    });

    // Handle apply optimization
    $('#dpo-apply-optimization').on('click', function() {
        var button = $(this);
        var productId = button.data('product-id');
        var variationId = button.data('variation-id');
        var loop = button.data('loop');
        
        button.prop('disabled', true).text(dpoAdmin.i18n.applying);
        
        $.ajax({
            url: dpoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dpo_update_product_price',
                nonce: dpoAdmin.nonce,
                product_id: productId,
                variation_id: variationId,
                loop: loop
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(dpoAdmin.i18n.error);
                }
            },
            error: function() {
                alert(dpoAdmin.i18n.error);
            },
            complete: function() {
                button.prop('disabled', false).text(dpoAdmin.i18n.apply);
            }
        });
    });
});
</script> 