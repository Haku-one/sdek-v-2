<?php
/**
 * WooCommerce Blocks Integration for CDEK Delivery
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class WC_Cdek_Blocks_Integration implements IntegrationInterface {

    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name() {
        return 'cdek-delivery';
    }

    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize() {
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles() {
        return array('cdek-delivery-blocks-frontend');
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles() {
        return array('cdek-delivery-blocks-editor');
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data() {
        return array(
            'cdek_ajax_url' => admin_url('admin-ajax.php'),
            'cdek_nonce' => wp_create_nonce('cdek_nonce'),
            'yandex_api_key' => get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702'),
        );
    }

    /**
     * Register frontend scripts
     */
    private function register_block_frontend_scripts() {
        wp_register_script(
            'cdek-delivery-blocks-frontend',
            CDEK_DELIVERY_PLUGIN_URL . 'assets/js/cdek-delivery.js',
            array('jquery'),
            CDEK_DELIVERY_VERSION,
            true
        );
    }

    /**
     * Register editor scripts
     */
    private function register_block_editor_scripts() {
        wp_register_script(
            'cdek-delivery-blocks-editor',
            CDEK_DELIVERY_PLUGIN_URL . 'assets/js/cdek-delivery.js',
            array('jquery'),
            CDEK_DELIVERY_VERSION,
            true
        );
    }
}

/**
 * Register the integration
 */
function cdek_delivery_register_blocks_integration() {
    if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry')) {
        add_action(
            'woocommerce_blocks_checkout_block_registration',
            function($integration_registry) {
                $integration_registry->register(new WC_Cdek_Blocks_Integration());
            }
        );
    }
}
add_action('init', 'cdek_delivery_register_blocks_integration');