jQuery(document).ready(function($) {
    'use strict';

    // Initialize tooltips
    function initTooltips() {
        $('.dpo-tooltip').each(function() {
            $(this).attr('aria-label', $(this).data('tooltip'));
        });
    }

    // Initialize tabs
    function initTabs() {
        $('.dpo-tab').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            
            $('.dpo-tab').removeClass('active');
            $(this).addClass('active');
            
            $('.dpo-tab-content').removeClass('active');
            $(target).addClass('active');
        });
    }

    // Bulk optimize prices
    $('#dpo-bulk-optimize').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        
        if ($button.hasClass('dpo-loading')) {
            return;
        }

        if (!confirm(dpo_vars.confirm_bulk_optimize)) {
            return;
        }

        $button.addClass('dpo-loading');
        
        $.ajax({
            url: dpo_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'dpo_bulk_optimize',
                nonce: dpo_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', dpo_vars.error_message);
            },
            complete: function() {
                $button.removeClass('dpo-loading');
            }
        });
    });

    // Update competitor prices
    $('#dpo-update-competitors').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        
        if ($button.hasClass('dpo-loading')) {
            return;
        }

        $button.addClass('dpo-loading');
        
        $.ajax({
            url: dpo_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'dpo_update_competitors',
                nonce: dpo_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', dpo_vars.error_message);
            },
            complete: function() {
                $button.removeClass('dpo-loading');
            }
        });
    });

    // Train ML model
    $('#dpo-train-model').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        
        if ($button.hasClass('dpo-loading')) {
            return;
        }

        if (!confirm(dpo_vars.confirm_train_model)) {
            return;
        }

        $button.addClass('dpo-loading');
        
        $.ajax({
            url: dpo_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'dpo_train_model',
                nonce: dpo_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', dpo_vars.error_message);
            },
            complete: function() {
                $button.removeClass('dpo-loading');
            }
        });
    });

    // Analyze individual product
    $('.dpo-analyze-product').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const productId = $button.data('product-id');
        
        if ($button.hasClass('dpo-loading')) {
            return;
        }

        $button.addClass('dpo-loading');
        
        $.ajax({
            url: dpo_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'dpo_analyze_product',
                product_id: productId,
                nonce: dpo_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    showAnalysisModal(response.data);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', dpo_vars.error_message);
            },
            complete: function() {
                $button.removeClass('dpo-loading');
            }
        });
    });

    // Settings form validation
    function validateSettings() {
        let isValid = true;
        const weightsSum = parseInt($('#dpo_settings\\[competitor_weight\\]').val()) +
                          parseInt($('#dpo_settings\\[demand_weight\\]').val()) +
                          parseInt($('#dpo_settings\\[margin_weight\\]').val());

        if (weightsSum !== 100) {
            showNotice('error', dpo_vars.weights_error);
            isValid = false;
        }

        const minPrice = parseFloat($('#dpo_settings\\[min_price_change\\]').val());
        const maxPrice = parseFloat($('#dpo_settings\\[max_price_change\\]').val());

        if (minPrice >= maxPrice) {
            showNotice('error', dpo_vars.price_range_error);
            isValid = false;
        }

        return isValid;
    }

    // Settings form submission
    $('.dpo-settings-form').on('submit', function(e) {
        if (!validateSettings()) {
            e.preventDefault();
        }
    });

    // Show analysis modal
    function showAnalysisModal(data) {
        const modal = $(`
            <div class="dpo-modal">
                <div class="dpo-modal-content">
                    <span class="dpo-modal-close">&times;</span>
                    <h2>${data.product_name}</h2>
                    
                    <div class="dpo-analysis-grid">
                        <div class="dpo-analysis-card">
                            <h3>${dpo_vars.price_analysis}</h3>
                            <div class="dpo-analysis-value">${data.price_analysis.current_price}</div>
                            <div class="dpo-analysis-trend ${data.price_analysis.trend > 0 ? 'dpo-trend-up' : 'dpo-trend-down'}">
                                ${data.price_analysis.trend}% ${dpo_vars.last_30_days}
                            </div>
                        </div>
                        
                        <div class="dpo-analysis-card">
                            <h3>${dpo_vars.competitor_analysis}</h3>
                            <div class="dpo-analysis-value">${data.competitor_analysis.average_price}</div>
                            <div class="dpo-analysis-trend">
                                ${data.competitor_analysis.position} ${dpo_vars.market_position}
                            </div>
                        </div>
                        
                        <div class="dpo-analysis-card">
                            <h3>${dpo_vars.demand_analysis}</h3>
                            <div class="dpo-analysis-value">${data.demand_analysis.monthly_sales}</div>
                            <div class="dpo-analysis-trend ${data.demand_analysis.trend > 0 ? 'dpo-trend-up' : 'dpo-trend-down'}">
                                ${data.demand_analysis.trend}% ${dpo_vars.last_30_days}
                            </div>
                        </div>
                    </div>

                    <div class="dpo-chart-container">
                        <canvas id="dpo-price-history"></canvas>
                    </div>

                    <div class="dpo-recommendations">
                        <h3>${dpo_vars.recommendations}</h3>
                        <ul>
                            ${data.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);
        modal.fadeIn();

        // Initialize price history chart
        const ctx = document.getElementById('dpo-price-history').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.price_history.dates,
                datasets: [
                    {
                        label: dpo_vars.product_price,
                        data: data.price_history.prices,
                        borderColor: '#2271b1',
                        tension: 0.1
                    },
                    {
                        label: dpo_vars.competitor_avg,
                        data: data.price_history.competitor_prices,
                        borderColor: '#d63638',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });

        // Close modal
        modal.find('.dpo-modal-close').on('click', function() {
            modal.fadeOut(function() {
                modal.remove();
            });
        });

        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                modal.fadeOut(function() {
                    modal.remove();
                });
            }
        });
    }

    // Show notice
    function showNotice(type, message) {
        const notice = $(`
            <div class="dpo-notice ${type}">
                ${message}
            </div>
        `);

        $('.wrap > h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
    }

    // Initialize everything
    initTooltips();
    initTabs();
}); 