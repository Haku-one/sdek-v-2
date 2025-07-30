<?php
/**
 * Plugin Name: СДЭК Доставка для WooCommerce
 * Plugin URI: https://yoursite.com
 * Description: Плагин для интеграции доставки СДЭК с упрощенной формой адреса и картой пунктов выдачи
 * Version: 1.0.0
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
define('CDEK_DELIVERY_VERSION', '1.0.0');

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
        
        // AJAX обработчики
        add_action('wp_ajax_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_nopriv_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_nopriv_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_get_address_suggestions', array($this, 'ajax_get_address_suggestions'));
        add_action('wp_ajax_nopriv_get_address_suggestions', array($this, 'ajax_get_address_suggestions'));
        
        // Регистрация настроек плагина
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Сохранение данных о выбранном пункте выдачи
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_cdek_point_data'));
        
        // Отображение информации о пункте выдачи в админке
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_cdek_point_in_admin'));
        
        // AJAX для проверки подключения
        add_action('wp_ajax_test_cdek_connection', array($this, 'ajax_test_cdek_connection'));
        
        // Вывод габаритов товаров в оформлении заказа
        add_action('woocommerce_checkout_after_order_review', array($this, 'display_product_dimensions_checkout'), 5);
        
        // Скрытие ненужных полей через CSS
        add_action('wp_head', array($this, 'hide_checkout_fields_css'));
        
        // Активация плагина
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        
        // Поддержка новых блоков WooCommerce
        add_action('plugins_loaded', array($this, 'load_blocks_integration'));
        
        // Добавляем габариты в описание товара в корзине
        add_filter('woocommerce_get_item_data', array($this, 'add_dimensions_to_cart_item'), 10, 2);
        
        // Создание таблиц для кэширования при активации
        add_action('after_setup_theme', array($this, 'create_cache_tables'));
        
        // Предзагрузка популярных данных
        add_action('wp_loaded', array($this, 'preload_popular_data'));
        
        // Подключение модуля отправки данных о заказах
        add_action('init', array($this, 'load_order_sender'));
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
        // Меняем метку для поля адреса
        $fields['shipping']['shipping_address_1']['label'] = 'Город доставки';
        $fields['shipping']['shipping_address_1']['placeholder'] = 'Например: Москва';
        $fields['shipping']['shipping_address_1']['required'] = true;
        
        return $fields;
    }
    
    public function customize_address_fields($fields) {
        // Настраиваем поле адреса
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
        
        // Добавляем отладочную информацию
        error_log('СДЭК AJAX: Запрос пунктов для адреса: ' . $address);
        
        $cdek_api = new CdekAPI();
        $points = $cdek_api->get_delivery_points($address);
        
        // Логируем результат
        error_log('СДЭК AJAX: Получено пунктов: ' . count($points));
        if (!empty($points)) {
            error_log('СДЭК AJAX: Первый пункт: ' . print_r($points[0], true));
        }
        
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
        
        error_log('СДЭК расчет: Данные для расчета - Код пункта: ' . $point_code . ', Вес: ' . $cart_weight . ', Стоимость: ' . $cart_value);
        error_log('СДЭК расчет: Размеры: ' . print_r($cart_dimensions, true));
        error_log('СДЭК расчет: Реальные габариты: ' . ($has_real_dimensions ? 'Да' : 'Нет'));
        
        // Проверяем, что у нас есть все необходимые данные
        if (empty($point_code)) {
            error_log('СДЭК расчет: Не указан код пункта выдачи');
            wp_send_json_error('Не указан код пункта выдачи');
            return;
        }
        
        if (empty($cart_dimensions) || !isset($cart_dimensions['length']) || !isset($cart_dimensions['width']) || !isset($cart_dimensions['height'])) {
            error_log('СДЭК расчет: Некорректные габариты товара');
            wp_send_json_error('Некорректные габариты товара');
            return;
        }
        
        $cdek_api = new CdekAPI();
        $cost_data = $cdek_api->calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions);
        
        if ($cost_data && isset($cost_data['delivery_sum']) && $cost_data['delivery_sum'] > 0) {
            error_log('СДЭК расчет: ✅ Успешно рассчитана стоимость через НАСТОЯЩИЙ API: ' . $cost_data['delivery_sum']);
            
            // Убедимся что передаем флаг успешного API расчета
            $cost_data['api_success'] = true;
            $cost_data['fallback'] = false;
            
            wp_send_json_success($cost_data);
        } else {
            error_log('СДЭК расчет: ❌ API не вернул корректную стоимость.');
            error_log('СДЭК расчет: Детали ответа API: ' . print_r($cost_data, true));
            error_log('СДЭК расчет: ❌ ОТКАЗЫВАЕМСЯ ОТ РАСЧЕТА - НЕТ FALLBACK');
            
            // НЕТ РЕЗЕРВНОГО РАСЧЕТА! Возвращаем ошибку
            wp_send_json_error(array(
                'message' => 'API СДЭК недоступен, расчет стоимости невозможен',
                'api_response' => $cost_data,
                'debug_info' => array(
                    'point_code' => $point_code,
                    'cart_weight' => $cart_weight,
                    'cart_value' => $cart_value,
                    'cart_dimensions' => $cart_dimensions
                )
            ));
        }
    }
    
    public function ajax_get_address_suggestions() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $search = sanitize_text_field($_POST['search']);
        
        // Генерируем предложения адресов
        $suggestions = $this->generate_address_suggestions($search);
        
        wp_send_json_success($suggestions);
    }
    
    private function generate_address_suggestions($search) {
        $suggestions = array();
        $search_lower = mb_strtolower($search);
        
        // Список российских городов
        $cities = array(
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
            'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
            'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул'
        );
        
        foreach ($cities as $city) {
            if (mb_strpos(mb_strtolower($city), $search_lower) !== false) {
                $suggestions[] = array(
                    'value' => $city,
                    'text' => $city,
                    'city' => $city,
                    'street' => ''
                );
            }
        }
        
        return array_slice($suggestions, 0, 10);
    }
    
    private function calculate_fallback_cost($weight, $value, $dimensions, $has_real_dimensions) {
        $base_cost = 300; // Базовая стоимость
        
        // Дополнительная стоимость за вес свыше 500г
        if ($weight > 500) {
            $extra_weight = ceil(($weight - 500) / 500);
            $base_cost += $extra_weight * 35;
        }
        
        // Дополнительная стоимость за габариты
        if ($has_real_dimensions && $dimensions) {
            $volume = $dimensions['length'] * $dimensions['width'] * $dimensions['height'];
            if ($volume > 12000) {
                $extra_volume = ceil(($volume - 12000) / 6000);
                $base_cost += $extra_volume * 50;
            }
        }
        
        // Страховка за высокую стоимость
        if ($value > 3000) {
            $base_cost += ceil(($value - 3000) / 1000) * 20;
        }
        
        return $base_cost;
    }
    
    public function display_product_dimensions_checkout() {
        // Получаем товары из корзины
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
            
            // Получаем габариты товара
            $length = $product->get_length();
            $width = $product->get_width(); 
            $height = $product->get_height();
            $weight = $product->get_weight();
            
            // Если хотя бы один из размеров указан, выводим товар
            if ($length || $width || $height || $weight) {
                $has_dimensions = true;
                
                echo '<div class="product-dimensions" style="margin-bottom: 10px; padding: 8px; background: white; border: 1px solid #e0e0e0; border-radius: 3px;">';
                echo '<strong>' . $product->get_name() . '</strong>';
                if ($quantity > 1) {
                    echo ' <span style="color: #666;">(×' . $quantity . ')</span>';
                }
                echo '<br>';
                echo '<span style="color: #666; font-size: 14px;">';
                
                // Выводим габариты если они есть
                if ($length && $width && $height) {
                    echo '📏 Габариты: ' . $length . '×' . $width . '×' . $height . ' см';
                } else {
                    // Выводим те размеры что есть
                    $dimensions = array();
                    if ($length) $dimensions[] = 'Д: ' . $length . 'см';
                    if ($width) $dimensions[] = 'Ш: ' . $width . 'см';
                    if ($height) $dimensions[] = 'В: ' . $height . 'см';
                    if (!empty($dimensions)) {
                        echo '📏 ' . implode(' | ', $dimensions);
                    }
                }
                
                // Выводим вес если он есть
                if ($weight) {
                    if ($length || $width || $height) {
                        echo ' | ';
                    }
                    echo '⚖️ Вес: ' . $weight;
                    // Определяем единицы измерения
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
        
        // Если ни у одного товара нет габаритов, показываем сообщение
        if (!$has_dimensions) {
            echo '<div style="padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px; color: #856404;">';
            echo '⚠️ <strong>Внимание:</strong> У товаров в корзине не указаны габариты и вес.<br>';
            echo 'Стоимость доставки будет рассчитана приблизительно.';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Добавляем скрытые поля с данными для JavaScript
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
        
        // Получаем габариты товара
        $length = $product->get_length();
        $width = $product->get_width(); 
        $height = $product->get_height();
        
        // Если есть габариты, добавляем их в метаданные
        if ($length && $width && $height) {
            $item_data[] = array(
                'name' => 'Габариты (Д×Ш×В)',
                'value' => $length . '×' . $width . '×' . $height . ' см'
            );
        }
        
        return $item_data;
    }
    
    public function hide_checkout_fields_css() {
        if (is_checkout()) {
            echo '<style></style>';
        }
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
            echo 'Адрес: ' . esc_html($point_data['location']['address_full']) . '<br>';
            if (isset($point_data['phone'])) {
                echo 'Телефон: ' . esc_html($point_data['phone']) . '<br>';
            }
            echo '</div>';
        }
    }
    
    public function ajax_test_cdek_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_cdek_connection')) {
            wp_die('Security check failed');
        }
        
        $cdek_api = new CdekAPI();
        $token = $cdek_api->get_auth_token();
        
        if ($token) {
            wp_send_json_success('Подключение к API СДЭК успешно установлено');
        } else {
            wp_send_json_error('Не удалось подключиться к API СДЭК. Проверьте учетные данные.');
        }
    }
    
    public function activate_plugin() {
        // Создание таблиц или начальных настроек при активации плагина
        if (!get_option('cdek_plugin_version')) {
            add_option('cdek_plugin_version', CDEK_DELIVERY_VERSION);
            add_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
            add_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
            add_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
            add_option('cdek_sender_city', '51'); // Саратов - код 51
        }
    }
    
    public function load_blocks_integration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-blocks-integration.php';
        }
    }
    
    /**
     * Подключение модуля отправки данных о заказах СДЭК
     */
    public function load_order_sender() {
        include_once plugin_dir_path(__FILE__) . 'cdek-order-sender.php';
    }
    
    /**
     * Создание таблиц для продвинутого кэширования
     */
    public function create_cache_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdek_cache';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiry_time datetime NOT NULL,
            created_time datetime DEFAULT CURRENT_TIMESTAMP,
            hit_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY expiry_time (expiry_time),
            KEY hit_count (hit_count)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Создание индексов для быстрого поиска
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_cache_key_expiry ON $table_name (cache_key, expiry_time);");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_expiry_hit ON $table_name (expiry_time, hit_count);");
    }
    
    /**
     * Предзагрузка популярных данных
     */
    public function preload_popular_data() {
        if (wp_doing_ajax() || wp_doing_cron() || is_admin()) {
            return;
        }
        
        // Предзагружаем данные для популярных городов в фоновом режиме
        wp_schedule_single_event(time() + 10, 'cdek_preload_popular_cities');
        add_action('cdek_preload_popular_cities', array($this, 'preload_popular_cities_data'));
    }
    
    /**
     * Предзагрузка данных для популярных городов
     */
    public function preload_popular_cities_data() {
        $popular_cities = array('Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань');
        $cdek_api = new CdekAPI();
        
        foreach ($popular_cities as $city) {
            // Предзагружаем пункты выдачи для популярных городов
            $cdek_api->get_delivery_points($city);
            
            // Небольшая задержка чтобы не перегружать API
            usleep(500000); // 0.5 секунды
        }
    }
}

// Инициализация плагина
new CdekDeliveryPlugin();

// Класс для работы с СДЭК API
class CdekAPI {
    
    private $account;
    private $password;
    private $test_mode;
    private $base_url;
    private $cache_manager;
    
    public function __construct() {
        $this->account = get_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
        $this->password = get_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
        
        // ПРИНУДИТЕЛЬНО ОТКЛЮЧАЕМ ТЕСТОВЫЙ РЕЖИМ - он не работает с данными учетными данными
        $this->test_mode = 0;
        update_option('cdek_test_mode', 0);
        $this->base_url = 'https://api.cdek.ru/v2'; // Всегда используем продакшн API
        
        // Устанавливаем город отправителя как Саратов (правильный код 428)
        update_option('cdek_sender_city', '428');
        
        // Инициализируем менеджер кэша
        $this->cache_manager = new CdekCacheManager();
        
        // Логируем настройки подключения для отладки
        error_log('🔧 СДЭК API CONFIG: Режим - ПРОДАКШН (принудительно)');
        error_log('🔧 СДЭК API CONFIG: URL - ' . $this->base_url);
        error_log('🔧 СДЭК API CONFIG: Account ID - ' . substr($this->account, 0, 8) . '...');
        error_log('🔧 СДЭК API CONFIG: Password length - ' . strlen($this->password) . ' символов');
    }
    
    public function get_auth_token() {
        $cache_key = 'cdek_auth_token_v2';
        
        // Проверяем кэш с увеличенным TTL
        $cached_token = $this->cache_manager->get($cache_key);
        if ($cached_token !== false) {
            error_log('🔑 СДЭК AUTH: ✅ Используем кэшированный токен');
            return $cached_token;
        }
        
        error_log('🔑 СДЭК AUTH: Получаем новый токен авторизации');
        error_log('🔑 СДЭК AUTH: URL: ' . $this->base_url . '/oauth/token');
        error_log('🔑 СДЭК AUTH: Client ID: ' . $this->account);
        error_log('🔑 СДЭК AUTH: Client Secret: ' . substr($this->password, 0, 8) . '...');
        
        $auth_data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->account,
            'client_secret' => $this->password
        );
        
        $response = wp_remote_post($this->base_url . '/oauth/token', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'WordPress/CDEK-Plugin-Optimized',
                'Accept' => 'application/json',
                'Connection' => 'keep-alive'
            ),
            'body' => $auth_data,
            'timeout' => 15, // Уменьшаем таймаут для быстрого отказа
            'sslverify' => true,
            'blocking' => true,
            'compress' => true
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            error_log('🔑 СДЭК AUTH: HTTP код: ' . $response_code);
            
            $parsed_body = json_decode($body, true);
            if (isset($parsed_body['access_token'])) {
                $token = $parsed_body['access_token'];
                $expires_in = isset($parsed_body['expires_in']) ? intval($parsed_body['expires_in']) : 3600;
                
                // Кэшируем токен с запасом времени
                $this->cache_manager->set($cache_key, $token, $expires_in - 300);
                error_log('🔑 СДЭК AUTH: ✅ Токен получен и кэширован на ' . ($expires_in - 300) . ' сек');
                return $token;
            } else {
                error_log('🔑 СДЭК AUTH: ❌ Не удалось получить токен. Ответ: ' . print_r($parsed_body, true));
            }
        } else {
            error_log('🔑 СДЭК AUTH: ❌ Ошибка HTTP запроса: ' . $response->get_error_message());
        }
        
        return false;
    }
    
    public function get_delivery_points($address) {
        $city = $this->extract_city_from_address($address);
        $cache_key = 'cdek_points_' . md5($city ? $city : 'all_russia') . '_v3';
        
        // Проверяем кэш с длительным TTL для пунктов выдачи
        $cached_points = $this->cache_manager->get($cache_key);
        if ($cached_points !== false) {
            error_log('📦 СДЭК POINTS: ✅ Используем кэшированные пункты для ' . ($city ? $city : 'России') . ' (' . count($cached_points) . ' шт.)');
            return $cached_points;
        }
        
        $token = $this->get_auth_token();
        if (!$token) {
            error_log('СДЭК API: Не удалось получить токен авторизации');
            return array();
        }
        
        error_log('СДЭК API: Ищем пункты для города: ' . ($city ? $city : 'все города России'));
        
        // Оптимизированные параметры запроса
        $params = array(
            'country_code' => 'RU',
            'size' => 2000, // Увеличиваем лимит
            'page' => 0
        );
        
        if (!empty($city)) {
            $params['city'] = $city;
        }
        
        $url = add_query_arg($params, $this->base_url . '/deliverypoints');
        
        error_log('СДЭК API: 🚀 Запрос к API: ' . $url);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/CDEK-Plugin-Optimized',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive'
            ),
            'timeout' => 20, // Увеличиваем таймаут для больших запросов
            'compress' => true,
            'blocking' => true
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            error_log('СДЭК API: Код ответа: ' . $response_code);
            
            if ($response_code === 200 && $body) {
                $points = array();
                
                // Проверяем различные форматы ответа СДЭК API
                if (isset($body['entity']) && is_array($body['entity'])) {
                    $points = $body['entity'];
                } elseif (is_array($body) && !empty($body)) {
                    $points = $body;
                }
                
                if (!empty($points)) {
                    // Кэшируем пункты выдачи на длительный срок (24 часа)
                    $this->cache_manager->set($cache_key, $points, 86400);
                    error_log('СДЭК API: ✅ Найдено и кэширано пунктов: ' . count($points));
                    return $points;
                } else {
                    error_log('СДЭК API: ⚠️ Пустой ответ от API');
                }
            } else {
                error_log('СДЭК API: ❌ Ошибка API, код: ' . $response_code);
                if (isset($body['errors'])) {
                    error_log('СДЭК API: Ошибки: ' . print_r($body['errors'], true));
                }
            }
        } else {
            error_log('СДЭК API: ❌ Ошибка HTTP запроса: ' . $response->get_error_message());
        }
        
        return array();
    }
    
    public function calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions) {
        // Создаем уникальный ключ кэша для расчета
        $cache_key = 'cdek_cost_' . md5($point_code . '_' . $cart_weight . '_' . json_encode($cart_dimensions) . '_' . $cart_value . '_' . $has_real_dimensions) . '_v2';
        
        // Проверяем кэш расчетов (TTL 30 минут)
        $cached_cost = $this->cache_manager->get($cache_key);
        if ($cached_cost !== false) {
            error_log('💰 СДЭК COST: ✅ Используем кэшированную стоимость: ' . $cached_cost['delivery_sum'] . ' руб.');
            return $cached_cost;
        }
        
        error_log('🎯 СДЭК РАСЧЕТ: Начинаем расчет для пункта ' . $point_code);
        
        $token = $this->get_auth_token();
        if (!$token) {
            error_log('❌ СДЭК расчет: Не удалось получить токен авторизации');
            return false;
        }
        
        error_log('✅ СДЭК РАСЧЕТ: Токен авторизации получен');
        
        // Подготавливаем данные для расчета  
        $from_location = array(
            'code' => get_option('cdek_sender_city', '428') // Саратов
        );
        
        // Определяем локацию назначения более эффективно
        $to_location = $this->determine_destination_location($point_code, $point_data);
        
        if (!$to_location) {
            error_log('СДЭК расчет: Не удалось определить локацию назначения');
            return false;
        }
        
        // Подготавливаем данные о посылках
        $packages = array(
            array(
                'weight' => max(100, intval($cart_weight)),
                'length' => max(10, intval($cart_dimensions['length'])),
                'width' => max(10, intval($cart_dimensions['width'])),
                'height' => max(5, intval($cart_dimensions['height']))
            )
        );
        
        // Определяем тариф
        $tariff_code = 136; // Посылка склад-пункт выдачи
        
        // Формируем запрос
        $data = array(
            'date' => date('Y-m-d\TH:i:sO'),
            'type' => 1,
            'currency' => 1,
            'lang' => 'rus',
            'tariff_code' => $tariff_code,
            'from_location' => $from_location,
            'to_location' => $to_location,
            'packages' => $packages
        );
        
        // Добавляем страхование если нужно
        if ($cart_value > 3000) {
            $data['services'] = array(
                array(
                    'code' => 'INSURANCE',
                    'parameter' => strval(intval($cart_value))
                )
            );
        }
        
        error_log('🚀 СДЭК API: Отправляем запрос расчета стоимости');
        
        $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/CDEK-Plugin-Optimized',
                'Connection' => 'keep-alive'
            ),
            'body' => json_encode($data),
            'timeout' => 15, // Быстрый таймаут для расчетов
            'compress' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('СДЭК расчет: Ошибка HTTP запроса: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $parsed_body = json_decode($body, true);
        
        error_log('📥 СДЭК API: HTTP код ответа: ' . $response_code);
        
        if ($response_code === 200 && $parsed_body) {
            if (isset($parsed_body['delivery_sum']) && $parsed_body['delivery_sum'] > 0) {
                $result = array(
                    'delivery_sum' => intval($parsed_body['delivery_sum']),
                    'period_min' => isset($parsed_body['period_min']) ? $parsed_body['period_min'] : null,
                    'period_max' => isset($parsed_body['period_max']) ? $parsed_body['period_max'] : null,
                    'api_success' => true
                );
                
                // Кэшируем результат расчета на 30 минут
                $this->cache_manager->set($cache_key, $result, 1800);
                
                error_log('🎉 СДЭК API: Успешно получена и кэширована стоимость: ' . $result['delivery_sum'] . ' руб.');
                return $result;
            } elseif (isset($parsed_body['errors']) && !empty($parsed_body['errors'])) {
                error_log('❌ СДЭК API: Ошибки: ' . print_r($parsed_body['errors'], true));
                return $this->try_alternative_calculation($data, $token, $cache_key);
            }
        }
        
        error_log('❌ СДЭК API: Некорректный ответ. HTTP код: ' . $response_code);
        return false;
    }
    
    private function determine_destination_location($point_code, $point_data) {
        // Оптимизированное определение локации назначения
        $location = array();
        
        // Способ 1: city_code (самый быстрый)
        if (isset($point_data['location']['city_code']) && !empty($point_data['location']['city_code'])) {
            $location['code'] = intval($point_data['location']['city_code']);
            return $location;
        }
        
        // Способ 2: postal_code
        if (isset($point_data['location']['postal_code']) && !empty($point_data['location']['postal_code'])) {
            $location['postal_code'] = $point_data['location']['postal_code'];
            return $location;
        }
        
        // Способ 3: по коду пункта (быстрый lookup)
        $city_codes = $this->get_city_codes_map();
        foreach ($city_codes as $prefix => $city_info) {
            if (stripos($point_code, $prefix) === 0) {
                $location['code'] = $city_info['code'];
                return $location;
            }
        }
        
        // Способ 4: city name
        if (isset($point_data['location']['city']) && !empty($point_data['location']['city'])) {
            $location['city'] = trim($point_data['location']['city']);
            return $location;
        }
        
        return false;
    }
    
    private function get_city_codes_map() {
        // Кэшированная карта кодов городов
        static $city_codes = null;
        
        if ($city_codes === null) {
            $city_codes = array(
                'MSK' => array('code' => 44, 'name' => 'Москва'),
                'SPB' => array('code' => 137, 'name' => 'Санкт-Петербург'),
                'NSK' => array('code' => 270, 'name' => 'Новосибирск'),
                'EKB' => array('code' => 51, 'name' => 'Екатеринбург'),
                'KZN' => array('code' => 172, 'name' => 'Казань'),
                'NN' => array('code' => 276, 'name' => 'Нижний Новгород'),
                'CHE' => array('code' => 56, 'name' => 'Челябинск'),
                'SAM' => array('code' => 350, 'name' => 'Самара'),
                'UFA' => array('code' => 414, 'name' => 'Уфа'),
                'ROV' => array('code' => 335, 'name' => 'Ростов-на-Дону'),
                'KRD' => array('code' => 93, 'name' => 'Краснодар'),
                'PERM' => array('code' => 296, 'name' => 'Пермь'),
                'VRN' => array('code' => 432, 'name' => 'Воронеж'),
                'VGG' => array('code' => 438, 'name' => 'Волгоград'),
                'KRS' => array('code' => 207, 'name' => 'Красноярск'),
                'SRT' => array('code' => 354, 'name' => 'Саратов'),
                'TYU' => array('code' => 409, 'name' => 'Тюмень')
            );
        }
        
        return $city_codes;
    }
    
    private function try_alternative_calculation($original_data, $token, $cache_key) {
        error_log('СДЭК расчет: Пробуем альтернативные тарифы');
        
        $alternative_tariffs = [136, 138, 233, 234];
        
        foreach ($alternative_tariffs as $tariff) {
            $data = $original_data;
            $data['tariff_code'] = $tariff;
            
            // Упрощаем локацию
            if (!isset($data['to_location']['code'])) {
                $data['to_location'] = array('code' => 44); // Москва по умолчанию
            }
            
            $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => 10
            ));
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code === 200) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body['delivery_sum']) && $body['delivery_sum'] > 0) {
                        $result = array(
                            'delivery_sum' => intval($body['delivery_sum']),
                            'period_min' => isset($body['period_min']) ? $body['period_min'] : null,
                            'period_max' => isset($body['period_max']) ? $body['period_max'] : null,
                            'api_success' => true,
                            'alternative_tariff' => $tariff
                        );
                        
                        // Кэшируем альтернативный результат
                        $this->cache_manager->set($cache_key, $result, 1800);
                        
                        error_log('СДЭК расчет: ✅ Альтернативный расчет успешен с тарифом ' . $tariff . ': ' . $result['delivery_sum']);
                        return $result;
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
        
        // Очищаем от префиксов
        $city = preg_replace('/^(г\.?\s*|город\s+)/ui', '', $address);
        
        // Берем первую часть до запятой
        $parts = explode(',', $city);
        $city = trim($parts[0]);
        
        return $city;
    }
}

/**
 * Менеджер кэша для СДЭК API
 */
class CdekCacheManager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cdek_cache';
    }
    
    /**
     * Получить значение из кэша
     */
    public function get($key) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT cache_value, expiry_time FROM {$this->table_name} WHERE cache_key = %s AND expiry_time > NOW()",
            $key
        ));
        
        if ($result) {
            // Увеличиваем счетчик попаданий
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name} SET hit_count = hit_count + 1 WHERE cache_key = %s",
                $key
            ));
            
            $value = maybe_unserialize($result->cache_value);
            return $value;
        }
        
        return false;
    }
    
    /**
     * Сохранить значение в кэш
     */
    public function set($key, $value, $ttl = 3600) {
        global $wpdb;
        
        $serialized_value = maybe_serialize($value);
        $expiry_time = date('Y-m-d H:i:s', time() + $ttl);
        
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table_name} (cache_key, cache_value, expiry_time) 
             VALUES (%s, %s, %s) 
             ON DUPLICATE KEY UPDATE 
             cache_value = VALUES(cache_value), 
             expiry_time = VALUES(expiry_time),
             hit_count = 0",
            $key,
            $serialized_value,
            $expiry_time
        ));
        
        // Периодически очищаем устаревшие записи
        if (rand(1, 100) === 1) {
            $this->cleanup_expired();
        }
        
        return $result !== false;
    }
    
    /**
     * Удалить устаревшие записи
     */
    public function cleanup_expired() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$this->table_name} WHERE expiry_time < NOW()");
        
        // Удаляем старые неиспользуемые записи
        $wpdb->query("DELETE FROM {$this->table_name} WHERE created_time < DATE_SUB(NOW(), INTERVAL 7 DAY) AND hit_count = 0");
    }
    
    /**
     * Очистить весь кэш
     */
    public function flush() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
}
