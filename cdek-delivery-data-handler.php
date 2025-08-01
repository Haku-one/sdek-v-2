<?php
/**
 * СДЭК Delivery Data Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle delivery data processing
 */
class CDEK_Delivery_Data_Handler {
    
    public function __construct() {
        // Handle delivery data sync
        add_action('woocommerce_checkout_update_order_meta', array($this, 'sync_delivery_data'));
        
        // Handle extension data for blocks
        add_filter('woocommerce_store_api_checkout_update_order_from_request', array($this, 'update_order_from_request'), 10, 2);
    }
    
    /**
     * Sync delivery data to order
     */
    public function sync_delivery_data($order_id) {
        if (!$order_id) {
            return;
        }
        
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if we have CDEK delivery data in the request
        if (isset($_POST['cdek_point_code'])) {
            $order->update_meta_data('_cdek_point_code', sanitize_text_field($_POST['cdek_point_code']));
        }
        
        if (isset($_POST['cdek_point_address'])) {
            $order->update_meta_data('_cdek_point_address', sanitize_text_field($_POST['cdek_point_address']));
        }
        
        if (isset($_POST['cdek_delivery_cost'])) {
            $order->update_meta_data('_cdek_delivery_cost', sanitize_text_field($_POST['cdek_delivery_cost']));
        }
        
        $order->save();
    }
    
    /**
     * Update order from request for blocks
     */
    public function update_order_from_request($order, $request) {
        if (!$order || !$request) {
            return $order;
        }
        
        // Handle extension data
        $extensions = $request->get_param('extensions');
        
        if (isset($extensions['cdek-delivery'])) {
            $cdek_data = $extensions['cdek-delivery'];
            
            if (isset($cdek_data['point_code'])) {
                $order->update_meta_data('_cdek_point_code', sanitize_text_field($cdek_data['point_code']));
            }
            
            if (isset($cdek_data['point_address'])) {
                $order->update_meta_data('_cdek_point_address', sanitize_text_field($cdek_data['point_address']));
            }
            
            if (isset($cdek_data['delivery_cost'])) {
                $order->update_meta_data('_cdek_delivery_cost', sanitize_text_field($cdek_data['delivery_cost']));
            }
        }
        
        return $order;
    }
}

// Initialize the data handler
new CDEK_Delivery_Data_Handler();