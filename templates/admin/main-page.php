<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="dpo-filters">
        <select id="dpo-product-filter">
            <option value=""><?php _e('All Products', 'dynamic-price-optimizer'); ?></option>
            <?php foreach ($products as $product_id): ?>
                <?php $product = wc_get_product($product_id); ?>
                <option value="<?php echo esc_attr($product_id); ?>">
                    <?php echo esc_html($product->get_name()); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="button button-primary" id="dpo-refresh-data">
            <?php _e('Refresh Data', 'dynamic-price-optimizer'); ?>
        </button>
    </div>

    <div class="dpo-products-grid">
        <?php foreach ($products as $product_id): ?>
            <?php
            $product = wc_get_product($product_id);
            $optimization_data = $this->get_product_optimization_data($product_id);
            ?>
            <div class="dpo-product-card" data-product-id="<?php echo esc_attr($product_id); ?>">
                <div class="dpo-product-header">
                    <h3><?php echo esc_html($product->get_name()); ?></h3>
                    <span class="dpo-product-sku"><?php echo esc_html($product->get_sku()); ?></span>
                </div>

                <div class="dpo-price-info">
                    <div class="dpo-current-price">
                        <span class="dpo-label"><?php _e('Current Price:', 'dynamic-price-optimizer'); ?></span>
                        <span class="dpo-value"><?php echo wc_price($optimization_data['current_price']); ?></span>
                    </div>

                    <div class="dpo-optimal-price">
                        <span class="dpo-label"><?php _e('Optimal Price:', 'dynamic-price-optimizer'); ?></span>
                        <span class="dpo-value"><?php echo wc_price($optimization_data['optimal_price']); ?></span>
                    </div>

                    <div class="dpo-price-change <?php echo $optimization_data['price_change'] >= 0 ? 'positive' : 'negative'; ?>">
                        <span class="dpo-label"><?php _e('Change:', 'dynamic-price-optimizer'); ?></span>
                        <span class="dpo-value">
                            <?php echo sprintf(
                                '%s (%s%%)',
                                wc_price($optimization_data['price_change']),
                                number_format($optimization_data['price_change_percentage'], 2)
                            ); ?>
                        </span>
                    </div>
                </div>

                <div class="dpo-market-data">
                    <h4><?php _e('Market Data', 'dynamic-price-optimizer'); ?></h4>
                    
                    <div class="dpo-competitor-prices">
                        <h5><?php _e('Competitor Prices', 'dynamic-price-optimizer'); ?></h5>
                        <?php if (!empty($optimization_data['market_data']['competitor_prices'])): ?>
                            <ul>
                                <?php foreach ($optimization_data['market_data']['competitor_prices'] as $price): ?>
                                    <li>
                                        <?php echo esc_html($price['source']); ?>: 
                                        <?php echo wc_price($price['price']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="dpo-no-data"><?php _e('No competitor data available', 'dynamic-price-optimizer'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="dpo-inventory-levels">
                        <h5><?php _e('Inventory', 'dynamic-price-optimizer'); ?></h5>
                        <p>
                            <?php _e('Current Stock:', 'dynamic-price-optimizer'); ?> 
                            <?php echo esc_html($optimization_data['market_data']['inventory_levels']['current_stock']); ?>
                        </p>
                        <p>
                            <?php _e('Status:', 'dynamic-price-optimizer'); ?> 
                            <?php echo esc_html($optimization_data['market_data']['inventory_levels']['stock_status']); ?>
                        </p>
                    </div>
                </div>

                <div class="dpo-risks">
                    <h4><?php _e('Risk Analysis', 'dynamic-price-optimizer'); ?></h4>
                    <?php if (!empty($optimization_data['risks'])): ?>
                        <ul>
                            <?php foreach ($optimization_data['risks'] as $risk): ?>
                                <li class="dpo-risk-level-<?php echo esc_attr($risk['level']); ?>">
                                    <?php echo esc_html($risk['message']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="dpo-no-data"><?php _e('No risks identified', 'dynamic-price-optimizer'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="dpo-actions">
                    <?php if ($optimization_data['can_update']): ?>
                        <button type="button" class="button button-primary dpo-update-price" 
                                data-product-id="<?php echo esc_attr($product_id); ?>">
                            <?php _e('Update Price', 'dynamic-price-optimizer'); ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="button button-secondary" disabled>
                            <?php _e('No Update Needed', 'dynamic-price-optimizer'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script type="text/template" id="dpo-product-card-template">
    <div class="dpo-product-card" data-product-id="{{product_id}}">
        <div class="dpo-product-header">
            <h3>{{product_name}}</h3>
            <span class="dpo-product-sku">{{sku}}</span>
        </div>

        <div class="dpo-price-info">
            <div class="dpo-current-price">
                <span class="dpo-label"><?php _e('Current Price:', 'dynamic-price-optimizer'); ?></span>
                <span class="dpo-value">{{current_price}}</span>
            </div>

            <div class="dpo-optimal-price">
                <span class="dpo-label"><?php _e('Optimal Price:', 'dynamic-price-optimizer'); ?></span>
                <span class="dpo-value">{{optimal_price}}</span>
            </div>

            <div class="dpo-price-change {{price_change_class}}">
                <span class="dpo-label"><?php _e('Change:', 'dynamic-price-optimizer'); ?></span>
                <span class="dpo-value">{{price_change}}</span>
            </div>
        </div>

        <div class="dpo-market-data">
            <h4><?php _e('Market Data', 'dynamic-price-optimizer'); ?></h4>
            
            <div class="dpo-competitor-prices">
                <h5><?php _e('Competitor Prices', 'dynamic-price-optimizer'); ?></h5>
                {{competitor_prices}}
            </div>

            <div class="dpo-inventory-levels">
                <h5><?php _e('Inventory', 'dynamic-price-optimizer'); ?></h5>
                <p>
                    <?php _e('Current Stock:', 'dynamic-price-optimizer'); ?> 
                    {{current_stock}}
                </p>
                <p>
                    <?php _e('Status:', 'dynamic-price-optimizer'); ?> 
                    {{stock_status}}
                </p>
            </div>
        </div>

        <div class="dpo-risks">
            <h4><?php _e('Risk Analysis', 'dynamic-price-optimizer'); ?></h4>
            {{risks}}
        </div>

        <div class="dpo-actions">
            {{actions}}
        </div>
    </div>
</script> 