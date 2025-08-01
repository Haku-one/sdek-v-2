<?php
/**
 * СДЭК Shipping Method Class
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Cdek_Shipping_Method Class
 */
class WC_Cdek_Shipping_Method extends WC_Shipping_Method {

    public function __construct($instance_id = 0) {
        $this->id                 = 'cdek_delivery';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('СДЭК Доставка', 'cdek-delivery');
        $this->method_description = __('Доставка через службу СДЭК с выбором пункта выдачи', 'cdek-delivery');
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
        );

        $this->init();
    }

    /**
     * Initialize shipping method
     */
    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');

        // Save settings in admin
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Включить/Выключить', 'cdek-delivery'),
                'type'    => 'checkbox',
                'label'   => __('Включить доставку СДЭК', 'cdek-delivery'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Название метода', 'cdek-delivery'),
                'type'        => 'text',
                'description' => __('Название, которое увидит покупатель при оформлении заказа', 'cdek-delivery'),
                'default'     => __('Доставка СДЭК', 'cdek-delivery'),
                'desc_tip'    => true,
            ),
            'api_login' => array(
                'title'       => __('API Логин', 'cdek-delivery'),
                'type'        => 'text',
                'description' => __('Логин для доступа к API СДЭК', 'cdek-delivery'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_password' => array(
                'title'       => __('API Пароль', 'cdek-delivery'),
                'type'        => 'password',
                'description' => __('Пароль для доступа к API СДЭК', 'cdek-delivery'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_mode' => array(
                'title'   => __('Тестовый режим', 'cdek-delivery'),
                'type'    => 'checkbox',
                'label'   => __('Включить тестовый режим', 'cdek-delivery'),
                'default' => 'yes'
            ),
        );
    }

    /**
     * Calculate shipping cost
     */
    public function calculate_shipping($package = array()) {
        $cost = 0;

        // Basic calculation - can be enhanced with CDEK API
        if (!empty($package['contents'])) {
            $cost = 300; // Default cost
        }

        $rate = array(
            'id'      => $this->id,
            'label'   => $this->title,
            'cost'    => $cost,
            'package' => $package,
        );

        $this->add_rate($rate);
    }

    /**
     * Check if shipping method is available
     */
    public function is_available($package) {
        return $this->enabled === 'yes';
    }
}