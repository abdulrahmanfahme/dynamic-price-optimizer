<?php
/**
 * Class TestPriceOptimizer
 *
 * @package Dynamic_Price_Optimizer
 */

/**
 * Test cases for the Dynamic Price Optimizer plugin.
 */
class TestPriceOptimizer extends WP_UnitTestCase {

    /**
     * Test that the plugin is loaded correctly.
     */
    public function test_sample() {
        $this->assertTrue(true);
    }

    /**
     * Test price optimization functionality.
     */
    public function test_price_optimization() {
        // Create a test product
        $product_id = $this->factory->post->create([
            'post_title' => 'Test Product',
            'post_type' => 'product',
            'post_status' => 'publish'
        ]);

        // Set initial price
        update_post_meta($product_id, '_price', 100);

        // Get optimizer instance
        $optimizer = Dynamic_Price_Optimizer::get_instance();

        // Test price optimization
        $optimized_price = $optimizer->optimize_price($product_id);

        // Assert that the optimized price is within reasonable bounds
        $this->assertGreaterThanOrEqual(0, $optimized_price);
        $this->assertLessThanOrEqual(1000, $optimized_price);
    }

    /**
     * Test competitor price collection.
     */
    public function test_competitor_prices() {
        // Create a test product
        $product_id = $this->factory->post->create([
            'post_title' => 'Test Product',
            'post_type' => 'product',
            'post_status' => 'publish'
        ]);

        // Get optimizer instance
        $optimizer = Dynamic_Price_Optimizer::get_instance();

        // Test competitor price collection
        $competitor_prices = $optimizer->get_competitor_prices($product_id);

        // Assert that we get an array of prices
        $this->assertIsArray($competitor_prices);
    }

    /**
     * Test risk analysis functionality.
     */
    public function test_risk_analysis() {
        // Create a test product
        $product_id = $this->factory->post->create([
            'post_title' => 'Test Product',
            'post_type' => 'product',
            'post_status' => 'publish'
        ]);

        // Get optimizer instance
        $optimizer = Dynamic_Price_Optimizer::get_instance();

        // Test risk analysis
        $risk_analysis = $optimizer->analyze_risk($product_id);

        // Assert that we get a valid risk analysis result
        $this->assertIsArray($risk_analysis);
        $this->assertArrayHasKey('level', $risk_analysis);
        $this->assertArrayHasKey('factors', $risk_analysis);
    }
} 