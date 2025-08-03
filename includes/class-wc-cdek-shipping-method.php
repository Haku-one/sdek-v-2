<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Cdek_Shipping_Method extends WC_Shipping_Method {
    
    public function __construct($instance_id = 0) {
        $this->id = 'cdek_delivery';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('СДЭК — Пункт выдачи', 'cdek-delivery');
        $this->method_description = __('Доставка через сеть пунктов выдачи СДЭК', 'cdek-delivery');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        
        $this->init();
    }
    
    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Включить/Выключить', 'cdek-delivery'),
                'type' => 'checkbox',
                'description' => __('Включить доставку СДЭК', 'cdek-delivery'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Название метода', 'cdek-delivery'),
                'type' => 'text',
                'description' => __('Название, которое увидит покупатель при выборе способа доставки', 'cdek-delivery'),
                'default' => __('СДЭК — Пункт выдачи', 'cdek-delivery'),
                'desc_tip' => true,
            ),
            'base_cost' => array(
                'title' => __('Базовая стоимость', 'cdek-delivery'),
                'type' => 'number',
                'description' => __('Базовая стоимость доставки (будет заменена расчетом API)', 'cdek-delivery'),
                'default' => '300',
                'desc_tip' => true,
            ),
        );
    }
    
    public function calculate_shipping($package = array()) {
        // Получаем стоимость доставки из сессии (если выбран пункт выдачи)
        $cdek_cost = 0;
        $label = 'Выберите пункт выдачи';
        
        if (WC()->session) {
            $session_cost = WC()->session->get('cdek_delivery_cost');
            $session_point = WC()->session->get('cdek_selected_point_code');
            
            if (!empty($session_cost) && $session_cost > 0) {
                $cdek_cost = floatval($session_cost);
                $label = 'СДЭК доставка';
                error_log('СДЭК: Используем стоимость из сессии: ' . $cdek_cost);
            }
        }
        
        // Если нет стоимости в сессии, проверяем POST данные
        if ($cdek_cost == 0 && isset($_POST['cdek_delivery_cost']) && !empty($_POST['cdek_delivery_cost'])) {
            $cdek_cost = floatval($_POST['cdek_delivery_cost']);
            $label = 'СДЭК доставка';
            error_log('СДЭК: Используем стоимость из POST: ' . $cdek_cost);
        }
        
        // Добавляем метод доставки
        $this->add_rate(array(
            'id' => $this->id,
            'label' => $label,
            'cost' => $cdek_cost,
            'calc_tax' => 'per_item'
        ));
        
        error_log('СДЭК: Добавлен метод доставки с стоимостью: ' . $cdek_cost);
    }
    
    public function is_available($package) {
        $is_available = true;
        
        // Проверяем включена ли доставка
        if ('yes' !== $this->enabled) {
            $is_available = false;
        }
        
        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this);
    }
}