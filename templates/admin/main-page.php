<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('dpo_settings', array());
?>
<div class="wrap">
    <h1><?php esc_html_e('Dynamic Price Optimizer', 'dynamic-price-optimizer'); ?></h1>

    <div class="dpo-dashboard">
        <div class="dpo-stats">
            <div class="dpo-stat-card">
                <h3><?php esc_html_e('Products Optimized', 'dynamic-price-optimizer'); ?></h3>
                <div class="dpo-stat-value">
                    <?php
                    global $wpdb;
                    $optimized_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
                        WHERE meta_key = %s AND meta_value = %s",
                        '_dpo_optimization_settings',
                        'enabled'
                    ));
                    echo esc_html($optimized_count);
                    ?>
                </div>
            </div>

            <div class="dpo-stat-card">
                <h3><?php esc_html_e('Average Price Change', 'dynamic-price-optimizer'); ?></h3>
                <div class="dpo-stat-value">
                    <?php
                    $price_changes = $wpdb->get_var($wpdb->prepare(
                        "SELECT AVG(meta_value) FROM {$wpdb->postmeta} 
                        WHERE meta_key = %s",
                        '_dpo_price_change'
                    ));
                    echo esc_html(number_format($price_changes, 2)) . '%';
                    ?>
                </div>
            </div>

            <div class="dpo-stat-card">
                <h3><?php esc_html_e('Revenue Impact', 'dynamic-price-optimizer'); ?></h3>
                <div class="dpo-stat-value">
                    <?php
                    $revenue_impact = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(meta_value) FROM {$wpdb->postmeta} 
                        WHERE meta_key = %s",
                        '_dpo_revenue_impact'
                    ));
                    echo esc_html(wc_price($revenue_impact));
                    ?>
                </div>
            </div>
        </div>

        <div class="dpo-actions">
            <button class="button button-primary" id="dpo-bulk-optimize">
                <?php esc_html_e('Bulk Optimize Prices', 'dynamic-price-optimizer'); ?>
            </button>
            <button class="button" id="dpo-update-competitors">
                <?php esc_html_e('Update Competitor Prices', 'dynamic-price-optimizer'); ?>
            </button>
            <button class="button" id="dpo-train-model">
                <?php esc_html_e('Train ML Model', 'dynamic-price-optimizer'); ?>
            </button>
        </div>

        <div class="dpo-tables">
            <div class="dpo-table-section">
                <h2><?php esc_html_e('Recent Price Updates', 'dynamic-price-optimizer'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'dynamic-price-optimizer'); ?></th>
                            <th><?php esc_html_e('Old Price', 'dynamic-price-optimizer'); ?></th>
                            <th><?php esc_html_e('New Price', 'dynamic-price-optimizer'); ?></th>
                            <th><?php esc_html_e('Change', 'dynamic-price-optimizer'); ?></th>
                            <th><?php esc_html_e('Date', 'dynamic-price-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $price_updates = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}dpo_price_updates 
                            ORDER BY date DESC 
                            LIMIT 10"
                        ));

                        if ($price_updates) {
                            foreach ($price_updates as $update) {
                                $product = wc_get_product($update->product_id);
                                if ($product) {
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($update->product_id)); ?>">
                                                <?php echo esc_html($product->get_name()); ?>
                                            </a>
                                        </td>
                                        <td><?php echo wc_price($update->old_price); ?></td>
                                        <td><?php echo wc_price($update->new_price); ?></td>
                                        <td>
                                            <?php
                                            $change = (($update->new_price - $update->old_price) / $update->old_price) * 100;
                                            $class = $change >= 0 ? 'positive' : 'negative';
                                            echo '<span class="' . esc_attr($class) . '">' . esc_html(number_format($change, 2)) . '%</span>';
                                            ?>
                                        </td>
                                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($update->date))); ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e('No price updates yet.', 'dynamic-price-optimizer'); ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="dpo-table-section">
                <h2><?php esc_html_e('High Risk Products', 'dynamic-price-optimizer'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'dynamic-price-optimizer'); ?></th>
                            <th><?php esc_html_e('Risk Level', 'dynamic-price-optimizer'); ?></th>
                            <th><?php esc_html_e('Risk Factors', 'dynamic-price-optimizer'); ?></th>
                            <th><?php esc_html_e('Actions', 'dynamic-price-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $risk_threshold = isset($settings['risk_threshold']) ? $settings['risk_threshold'] : 70;
                        $high_risk_products = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}dpo_risk_analysis 
                            WHERE overall_risk >= %d 
                            ORDER BY overall_risk DESC 
                            LIMIT 10",
                            $risk_threshold
                        ));

                        if ($high_risk_products) {
                            foreach ($high_risk_products as $risk) {
                                $product = wc_get_product($risk->product_id);
                                if ($product) {
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($risk->product_id)); ?>">
                                                <?php echo esc_html($product->get_name()); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="dpo-risk-level <?php echo esc_attr($risk->overall_risk >= 90 ? 'high' : 'medium'); ?>">
                                                <?php echo esc_html(number_format($risk->overall_risk, 2)) . '%'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $risk_factors = array();
                                            if ($risk->profit_margin < 10) {
                                                $risk_factors[] = __('Low Profit Margin', 'dynamic-price-optimizer');
                                            }
                                            if ($risk->price_volatility > 50) {
                                                $risk_factors[] = __('High Price Volatility', 'dynamic-price-optimizer');
                                            }
                                            if ($risk->stock_risk > 70) {
                                                $risk_factors[] = __('Stock Risk', 'dynamic-price-optimizer');
                                            }
                                            if ($risk->order_cancellation_rate > 30) {
                                                $risk_factors[] = __('High Cancellation Rate', 'dynamic-price-optimizer');
                                            }
                                            echo esc_html(implode(', ', $risk_factors));
                                            ?>
                                        </td>
                                        <td>
                                            <button class="button dpo-analyze-product" data-product-id="<?php echo esc_attr($risk->product_id); ?>">
                                                <?php esc_html_e('Analyze', 'dynamic-price-optimizer'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="4"><?php esc_html_e('No high risk products found.', 'dynamic-price-optimizer'); ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Optimization Modal -->
<div id="dpo-optimization-modal" class="dpo-modal">
    <div class="dpo-modal-content">
        <span class="dpo-modal-close">&times;</span>
        <h2><?php esc_html_e('Price Optimization', 'dynamic-price-optimizer'); ?></h2>
        
        <div class="dpo-analysis-results">
            <div class="dpo-analysis-section">
                <h3><?php esc_html_e('Market Analysis', 'dynamic-price-optimizer'); ?></h3>
                <div id="dpo-market-analysis"></div>
            </div>
            
            <div class="dpo-analysis-section">
                <h3><?php esc_html_e('Competitor Analysis', 'dynamic-price-optimizer'); ?></h3>
                <div id="dpo-competitor-analysis"></div>
            </div>
            
            <div class="dpo-analysis-section">
                <h3><?php esc_html_e('Customer Behavior', 'dynamic-price-optimizer'); ?></h3>
                <div id="dpo-customer-analysis"></div>
            </div>
            
            <div class="dpo-analysis-section">
                <h3><?php esc_html_e('Risk Analysis', 'dynamic-price-optimizer'); ?></h3>
                <div id="dpo-risk-analysis"></div>
            </div>
        </div>
        
        <div class="dpo-optimization-actions">
            <button type="button" class="button button-primary" id="dpo-apply-optimization">
                <?php esc_html_e('Apply Optimization', 'dynamic-price-optimizer'); ?>
            </button>
            <button type="button" class="button" id="dpo-cancel-optimization">
                <?php esc_html_e('Cancel', 'dynamic-price-optimizer'); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize filters
    $('#dpo-category-filter, #dpo-status-filter').on('change', function() {
        refreshProductsList();
    });

    $('#dpo-search').on('input', function() {
        refreshProductsList();
    });

    // Initialize chart
    var ctx = document.getElementById('dpo-price-chart').getContext('2d');
    var priceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($this->get_chart_labels()); ?>,
            datasets: [{
                label: '<?php esc_html_e('Average Price', 'dynamic-price-optimizer'); ?>',
                data: <?php echo json_encode($this->get_chart_data()); ?>,
                borderColor: '#2271b1',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    });

    // Handle optimization modal
    $('.dpo-optimize-button').on('click', function() {
        var productId = $(this).data('product-id');
        showOptimizationModal(productId);
    });

    $('.dpo-modal-close, #dpo-cancel-optimization').on('click', function() {
        $('#dpo-optimization-modal').hide();
    });

    // Handle optimization application
    $('#dpo-apply-optimization').on('click', function() {
        var productId = $(this).data('product-id');
        var newPrice = $('#dpo-optimal-price').val();
        
        applyOptimization(productId, newPrice);
    });

    function refreshProductsList() {
        var category = $('#dpo-category-filter').val();
        var status = $('#dpo-status-filter').val();
        var search = $('#dpo-search').val();
        
        $.ajax({
            url: dpoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dpo_get_products_list',
                nonce: dpoAdmin.nonce,
                category: category,
                status: status,
                search: search
            },
            success: function(response) {
                if (response.success) {
                    $('#dpo-products-list').html(response.data);
                }
            }
        });
    }

    function showOptimizationModal(productId) {
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
                    displayAnalysisResults(response.data);
                    $('#dpo-optimization-modal').show();
                    $('#dpo-apply-optimization').data('product-id', productId);
                }
            }
        });
    }

    function displayAnalysisResults(data) {
        // Display market analysis
        $('#dpo-market-analysis').html(`
            <p>${data.market_analysis.price_trend > 0 ? 'Increasing' : 'Decreasing'} price trend</p>
            <p>Category trend: ${data.market_analysis.category_trend}</p>
        `);

        // Display competitor analysis
        $('#dpo-competitor-analysis').html(`
            <p>Average competitor price: ${data.competitor_prices.average_price}</p>
            <p>Price position: ${data.competitor_prices.price_position.percentile}th percentile</p>
        `);

        // Display customer behavior
        $('#dpo-customer-analysis').html(`
            <p>Purchase frequency: ${data.customer_behavior.purchase_frequency}</p>
            <p>View to purchase ratio: ${data.customer_behavior.view_to_purchase_ratio}</p>
        `);

        // Display risk analysis
        var riskHtml = '';
        data.risk_analysis.forEach(function(risk) {
            riskHtml += `<p class="risk-${risk.level}">${risk.message}</p>`;
        });
        $('#dpo-risk-analysis').html(riskHtml);
    }

    function applyOptimization(productId, newPrice) {
        $.ajax({
            url: dpoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dpo_update_product_price',
                nonce: dpoAdmin.nonce,
                product_id: productId,
                new_price: newPrice
            },
            success: function(response) {
                if (response.success) {
                    $('#dpo-optimization-modal').hide();
                    refreshProductsList();
                    alert(dpoAdmin.i18n.success);
                } else {
                    alert(dpoAdmin.i18n.error);
                }
            }
        });
    }
});
</script> 