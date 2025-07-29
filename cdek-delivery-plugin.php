<?php
/**
 * Plugin Name: СДЭК Доставка для WooCommerce - БЫСТРАЯ ВЕРСИЯ
 * Plugin URI: https://yoursite.com
 * Description: Максимально быстрый плагин для интеграции доставки СДЭК без кэширования данных, но с оптимизированными API запросами
 * Version: 2.0.0
 * Author: Your Name
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * Text Domain: cdek-delivery
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Проверяем, активен ли WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

define('CDEK_DELIVERY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CDEK_DELIVERY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CDEK_DELIVERY_VERSION', '2.0.0');

// Основной класс плагина
class CdekDeliveryPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Хуки для настройки полей адреса
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_filter('woocommerce_default_address_fields', array($this, 'customize_address_fields'));
        
        // Хуки для СДЭК
        add_action('woocommerce_shipping_init', array($this, 'init_cdek_shipping'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_cdek_shipping_method'));
        
        // AJAX обработчики - быстрые версии
        add_action('wp_ajax_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_nopriv_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_nopriv_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        
        // Регистрация настроек плагина
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Сохранение данных о выбранном пункте выдачи
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_cdek_point_data'));
        
        // Отображение информации о пункте выдачи в админке
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_cdek_point_in_admin'));
        
        // Вывод габаритов товаров в оформлении заказа
        add_action('woocommerce_checkout_after_order_review', array($this, 'display_product_dimensions_checkout'), 5);
        
        // Активация плагина
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        
        // Поддержка новых блоков WooCommerce
        add_action('plugins_loaded', array($this, 'load_blocks_integration'));
        
        // Добавляем габариты в описание товара в корзине
        add_filter('woocommerce_get_item_data', array($this, 'add_dimensions_to_cart_item'), 10, 2);
    }
    
    public function init() {
        load_plugin_textdomain('cdek-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=4020b4d5-1d96-476c-a10e-8ab18f0f3702&lang=ru_RU', array(), null, true);
            
            wp_enqueue_script('cdek-delivery-js', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/cdek-delivery.js', array('jquery', 'yandex-maps'), CDEK_DELIVERY_VERSION, true);
            wp_enqueue_style('cdek-delivery-css', CDEK_DELIVERY_PLUGIN_URL . 'assets/css/cdek-delivery.css', array(), CDEK_DELIVERY_VERSION);
            
            wp_localize_script('cdek-delivery-js', 'cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdek_nonce')
            ));
        }
    }
    
    public function customize_checkout_fields($fields) {
        $fields['shipping']['shipping_address_1']['label'] = 'Город доставки';
        $fields['shipping']['shipping_address_1']['placeholder'] = 'Например: Москва';
        $fields['shipping']['shipping_address_1']['required'] = true;
        return $fields;
    }
    
    public function customize_address_fields($fields) {
        $fields['address_1']['label'] = 'Город доставки';
        $fields['address_1']['placeholder'] = 'Например: Москва';
        $fields['address_1']['required'] = true;
        return $fields;
    }
    
    public function init_cdek_shipping() {
        if (!class_exists('WC_Cdek_Shipping_Method')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-cdek-shipping-method.php';
        }
    }
    
    public function add_cdek_shipping_method($methods) {
        $methods['cdek_delivery'] = 'WC_Cdek_Shipping_Method';
        return $methods;
    }
    
    public function ajax_get_cdek_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $address = sanitize_text_field($_POST['address']);
        
        $cdek_api = new FastCdekAPI();
        $points = $cdek_api->get_delivery_points($address);
        
        wp_send_json_success($points);
    }
    
    public function ajax_calculate_delivery_cost() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $point_code = sanitize_text_field($_POST['point_code']);
        $point_data = json_decode(stripslashes($_POST['point_data']), true);
        $cart_weight = floatval($_POST['cart_weight']);
        $cart_dimensions = json_decode(stripslashes($_POST['cart_dimensions']), true);
        $cart_value = floatval($_POST['cart_value']);
        $has_real_dimensions = intval($_POST['has_real_dimensions']);
        
        // Быстрая проверка обязательных данных
        if (empty($point_code) || empty($cart_dimensions)) {
            wp_send_json_error('Некорректные данные для расчета');
            return;
        }
        
        $cdek_api = new FastCdekAPI();
        $cost_data = $cdek_api->calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions);
        
        if ($cost_data && isset($cost_data['delivery_sum']) && $cost_data['delivery_sum'] > 0) {
            $cost_data['api_success'] = true;
            $cost_data['fallback'] = false;
            wp_send_json_success($cost_data);
        } else {
            // Быстрый fallback расчет
            $fallback_cost = $this->calculate_fast_fallback_cost($cart_weight, $cart_value, $cart_dimensions, $has_real_dimensions);
            wp_send_json_success(array(
                'delivery_sum' => $fallback_cost,
                'fallback' => true,
                'api_success' => false,
                'message' => 'Использован быстрый расчет'
            ));
        }
    }
    
    private function calculate_fast_fallback_cost($weight, $value, $dimensions, $has_real_dimensions) {
        $base_cost = 350;
        
        // Быстрые надбавки
        if ($weight > 500) {
            $base_cost += ceil(($weight - 500) / 500) * 40;
        }
        
        if ($has_real_dimensions && $dimensions) {
            $volume = $dimensions['length'] * $dimensions['width'] * $dimensions['height'];
            if ($volume > 12000) {
                $base_cost += ceil(($volume - 12000) / 6000) * 60;
            }
        }
        
        if ($value > 3000) {
            $base_cost += ceil(($value - 3000) / 1000) * 25;
        }
        
        return $base_cost;
    }
    
    public function display_product_dimensions_checkout() {
        $cart_items = WC()->cart->get_cart();
        
        if (empty($cart_items)) {
            return;
        }
        
        echo '<div id="product-dimensions-info" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: block !important;">';
        echo '<h4>📦 Габариты товаров в заказе:</h4>';
        echo '<div class="dimensions-list">';
        
        $has_dimensions = false;
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            $length = $product->get_length();
            $width = $product->get_width(); 
            $height = $product->get_height();
            $weight = $product->get_weight();
            
            if ($length || $width || $height || $weight) {
                $has_dimensions = true;
                
                echo '<div class="product-dimensions" style="margin-bottom: 10px; padding: 8px; background: white; border: 1px solid #e0e0e0; border-radius: 3px;">';
                echo '<strong>' . $product->get_name() . '</strong>';
                if ($quantity > 1) {
                    echo ' <span style="color: #666;">(×' . $quantity . ')</span>';
                }
                echo '<br>';
                echo '<span style="color: #666; font-size: 14px;">';
                
                if ($length && $width && $height) {
                    echo '📏 Габариты: ' . $length . '×' . $width . '×' . $height . ' см';
                }
                
                if ($weight) {
                    if ($length || $width || $height) {
                        echo ' | ';
                    }
                    echo '⚖️ Вес: ' . $weight;
                    if (get_option('woocommerce_weight_unit') === 'kg') {
                        echo ' кг';
                    } else {
                        echo ' г';
                    }
                }
                
                echo '</span>';
                echo '</div>';
            }
        }
        
        if (!$has_dimensions) {
            echo '<div style="padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px; color: #856404;">';
            echo '⚠️ <strong>Внимание:</strong> У товаров в корзине не указаны габариты и вес.<br>';
            echo 'Стоимость доставки будет рассчитана приблизительно.';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Скрытые поля с данными для JavaScript
        echo '<div id="wc-cart-data" style="display: none;">';
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            echo '<div class="cart-item-data" ';
            echo 'data-product-id="' . $product->get_id() . '" ';
            echo 'data-quantity="' . $quantity . '" ';
            echo 'data-length="' . ($product->get_length() ?: 0) . '" ';
            echo 'data-width="' . ($product->get_width() ?: 0) . '" ';
            echo 'data-height="' . ($product->get_height() ?: 0) . '" ';
            echo 'data-weight="' . ($product->get_weight() ?: 0) . '" ';
            echo 'data-price="' . $product->get_price() . '"';
            echo '></div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    public function add_dimensions_to_cart_item($item_data, $cart_item) {
        $product = $cart_item['data'];
        
        $length = $product->get_length();
        $width = $product->get_width(); 
        $height = $product->get_height();
        
        if ($length && $width && $height) {
            $item_data[] = array(
                'name' => 'Габариты (Д×Ш×В)',
                'value' => $length . '×' . $width . '×' . $height . ' см'
            );
        }
        
        return $item_data;
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Настройки СДЭК',
            'СДЭК Доставка',
            'manage_options',
            'cdek-delivery-settings',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        include_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
    }
    
    public function save_cdek_point_data($order_id) {
        if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
            update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($_POST['cdek_selected_point_code']));
        }
        
        if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
            $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
            if ($point_data) {
                update_post_meta($order_id, '_cdek_point_data', $point_data);
            }
        }
    }
    
    public function display_cdek_point_in_admin($order) {
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        
        if ($point_code && $point_data) {
            echo '<div class="cdek-point-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
            echo '<h4>Пункт выдачи СДЭК:</h4>';
            echo '<strong>' . esc_html($point_data['name']) . '</strong><br>';
            echo 'Код: ' . esc_html($point_code) . '<br>';
            if (isset($point_data['location']['address_full'])) {
                echo 'Адрес: ' . esc_html($point_data['location']['address_full']) . '<br>';
            }
            if (isset($point_data['phone'])) {
                echo 'Телефон: ' . esc_html($point_data['phone']) . '<br>';
            }
            echo '</div>';
        }
    }
    
    public function activate_plugin() {
        if (!get_option('cdek_plugin_version')) {
            add_option('cdek_plugin_version', CDEK_DELIVERY_VERSION);
            add_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
            add_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
            add_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
            add_option('cdek_sender_city', '428'); // Саратов
        }
    }
    
    public function load_blocks_integration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-blocks-integration.php';
        }
    }
}

// Инициализация плагина
new CdekDeliveryPlugin();

// Быстрый класс для работы с СДЭК API
class FastCdekAPI {
    
    private $account;
    private $password;
    private $base_url;
    
    public function __construct() {
        $this->account = get_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
        $this->password = get_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
        $this->base_url = 'https://api.cdek.ru/v2'; // Всегда продакшн
    }
    
    public function get_auth_token() {
        $cache_key = 'cdek_auth_token_fast';
        $token = get_transient($cache_key);
        
        if (!$token) {
            $auth_data = array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->account,
                'client_secret' => $this->password
            );
            
            $response = wp_remote_post($this->base_url . '/oauth/token', array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent' => 'WordPress/CDEK-Fast-Plugin'
                ),
                'body' => $auth_data,
                'timeout' => 15, // Быстрый таймаут
                'sslverify' => true
            ));
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['access_token'])) {
                    $token = $body['access_token'];
                    $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) : 3600;
                    set_transient($cache_key, $token, $expires_in - 60);
                }
            }
        }
        
        return $token;
    }
    
    public function get_delivery_points($address) {
        $token = $this->get_auth_token();
        if (!$token) {
            return array();
        }
        
        $city = $this->extract_city_from_address($address);
        
        $params = array(
            'country_code' => 'RU',
            'size' => 1000, // Ограничиваем для быстроты
            'page' => 0
        );
        
        if (!empty($city)) {
            $params['city'] = $city;
        }
        
        $url = add_query_arg($params, $this->base_url . '/deliverypoints');
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15 // Быстрый таймаут
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                
                if (isset($body['entity']) && is_array($body['entity'])) {
                    return $body['entity'];
                } elseif (is_array($body) && !empty($body)) {
                    return $body;
                }
            }
        }
        
        return array();
    }
    
    public function calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions) {
        $token = $this->get_auth_token();
        if (!$token) {
            return false;
        }
        
        // Быстрое определение локации назначения
        $to_location = $this->get_fast_location($point_code, $point_data);
        if (!$to_location) {
            return false;
        }
        
        // Подготовка данных для API
        $data = array(
            'date' => date('Y-m-d\TH:i:sO'),
            'type' => 1,
            'currency' => 1,
            'lang' => 'rus',
            'tariff_code' => 136, // ПВЗ
            'from_location' => array('code' => 428), // Саратов
            'to_location' => $to_location,
            'packages' => array(
                array(
                    'weight' => max(100, intval($cart_weight)),
                    'length' => max(10, intval($cart_dimensions['length'])),
                    'width' => max(10, intval($cart_dimensions['width'])),
                    'height' => max(5, intval($cart_dimensions['height']))
                )
            )
        );
        
        // Добавляем страхование для дорогих заказов
        if ($cart_value > 3000) {
            $data['services'] = array(
                array(
                    'code' => 'INSURANCE',
                    'parameter' => strval(intval($cart_value))
                )
            );
        }
        
        $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 15 // Быстрый таймаут
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                
                if (isset($body['delivery_sum']) && $body['delivery_sum'] > 0) {
                    return array(
                        'delivery_sum' => intval($body['delivery_sum']),
                        'period_min' => isset($body['period_min']) ? $body['period_min'] : null,
                        'period_max' => isset($body['period_max']) ? $body['period_max'] : null,
                        'api_success' => true
                    );
                }
                
                // Попробуем альтернативные тарифы
                return $this->try_alternative_tariffs($data, $token);
            }
        }
        
        return false;
    }
    
    private function get_fast_location($point_code, $point_data) {
        // Быстрое определение локации по приоритету
        if (isset($point_data['location']['city_code']) && !empty($point_data['location']['city_code'])) {
            return array('code' => intval($point_data['location']['city_code']));
        }
        
        if (isset($point_data['location']['postal_code']) && !empty($point_data['location']['postal_code'])) {
            return array('postal_code' => $point_data['location']['postal_code']);
        }
        
        if (isset($point_data['location']['city']) && !empty($point_data['location']['city'])) {
            return array('city' => trim($point_data['location']['city']));
        }
        
        // Быстрое определение по коду пункта
        $city_codes = array(
            'MSK' => 44, 'SPB' => 137, 'NSK' => 270, 'EKB' => 51, 'KZN' => 172,
            'NN' => 276, 'CHE' => 56, 'SAM' => 350, 'UFA' => 414, 'ROV' => 335,
            'KRD' => 93, 'PERM' => 296, 'VRN' => 432, 'VGG' => 438, 'KRS' => 207,
            'SRT' => 354, 'TYU' => 409, 'MKHCH' => 470
        );
        
        foreach ($city_codes as $prefix => $code) {
            if (stripos($point_code, $prefix) === 0) {
                return array('code' => $code);
            }
        }
        
        return false;
    }
    
    private function try_alternative_tariffs($original_data, $token) {
        $alternative_tariffs = [138, 233, 234]; // Постамат, Эконом, Стандарт
        
        foreach ($alternative_tariffs as $tariff) {
            $data = $original_data;
            $data['tariff_code'] = $tariff;
            
            // Упрощаем локацию до Москвы если не удается определить
            if (!isset($data['to_location']['code'])) {
                $data['to_location'] = array('code' => 44); // Москва
            }
            
            $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => 10
            ));
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code === 200) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body['delivery_sum']) && $body['delivery_sum'] > 0) {
                        return array(
                            'delivery_sum' => intval($body['delivery_sum']),
                            'period_min' => isset($body['period_min']) ? $body['period_min'] : null,
                            'period_max' => isset($body['period_max']) ? $body['period_max'] : null,
                            'api_success' => true,
                            'alternative_tariff' => $tariff
                        );
                    }
                }
            }
        }
        
        return false;
    }
    
    private function extract_city_from_address($address) {
        $address = trim($address);
        
        if (strtolower($address) === 'россия' || strtolower($address) === 'russia') {
            return '';
        }
        
        $city = preg_replace('/^(г\.?\s*|город\s+)/ui', '', $address);
        $parts = explode(',', $city);
        $city = trim($parts[0]);
        
        return $city;
    }
}
