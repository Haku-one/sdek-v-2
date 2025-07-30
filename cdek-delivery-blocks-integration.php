<?php
/**
 * СДЭК Доставка - Интеграция с WooCommerce Blocks
 * Улучшенная версия для корректной работы с новым checkout
 * 
 * @package CDEK_Delivery
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс для интеграции СДЭК доставки с WooCommerce Blocks
 */
class CDEK_Delivery_Blocks_Integration {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Инициализация хуков
     */
    private function init_hooks() {
        // Хуки для WooCommerce Blocks
        add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
        add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'save_blocks_checkout_data'));
        
        // Хуки для классического checkout
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_classic_checkout_data'), 10, 1);
        
        // Универсальные хуки для всех типов checkout
        add_action('woocommerce_checkout_order_processed', array($this, 'process_order_data'), 10, 3);
        
        // Хуки для отображения в email и админке
        add_action('woocommerce_email_order_details', array($this, 'add_delivery_info_to_email'), 25, 4);
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_delivery_info_in_admin'), 20);
        
        // REST API хуки для перехвата данных
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('woocommerce_rest_checkout_process_payment_with_context', array($this, 'process_rest_checkout_data'), 10, 2);
        
        // AJAX хуки для дополнительной обработки
        add_action('wp_ajax_cdek_save_delivery_choice', array($this, 'ajax_save_delivery_choice'));
        add_action('wp_ajax_nopriv_cdek_save_delivery_choice', array($this, 'ajax_save_delivery_choice'));
        
        // JavaScript для перехвата данных
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));
    }
    
    /**
     * Регистрация интеграции с WooCommerce Blocks
     */
    public function register_blocks_integration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry')) {
            add_action(
                'woocommerce_blocks_checkout_block_registration',
                array($this, 'register_checkout_block_integration')
            );
        }
    }
    
    /**
     * Подключение скриптов для checkout
     */
    public function enqueue_checkout_scripts() {
        if (is_checkout()) {
            wp_enqueue_script(
                'cdek-blocks-integration',
                plugins_url('cdek-blocks-integration.js', __FILE__),
                array('jquery', 'wp-hooks', 'wp-data'),
                '2.0.0',
                true
            );
            
            wp_localize_script('cdek-blocks-integration', 'cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdek_delivery_nonce')
            ));
        }
    }
    
    /**
     * Сохранение данных из WooCommerce Blocks checkout
     */
    public function save_blocks_checkout_data($order) {
        error_log('СДЭК Blocks: Обработка данных заказа #' . $order->get_id());
        
        // Получаем данные из различных источников
        $delivery_data = $this->extract_delivery_data_from_request();
        
        if (!empty($delivery_data)) {
            $this->save_delivery_data_to_order($order->get_id(), $delivery_data);
            error_log('СДЭК Blocks: Данные сохранены для заказа #' . $order->get_id() . ': ' . print_r($delivery_data, true));
        }
    }
    
    /**
     * Сохранение данных из классического checkout
     */
    public function save_classic_checkout_data($order_id) {
        error_log('СДЭК Classic: Обработка данных заказа #' . $order_id);
        
        $delivery_data = $this->extract_delivery_data_from_request();
        
        if (!empty($delivery_data)) {
            $this->save_delivery_data_to_order($order_id, $delivery_data);
            error_log('СДЭК Classic: Данные сохранены для заказа #' . $order_id . ': ' . print_r($delivery_data, true));
        }
    }
    
    /**
     * Обработка данных заказа (универсальный метод)
     */
    public function process_order_data($order_id, $posted_data, $order) {
        error_log('СДЭК Process: Финальная обработка заказа #' . $order_id);
        
        // Дополнительная проверка и обработка данных
        $this->ensure_delivery_data_saved($order_id);
    }
    
    /**
     * Извлечение данных доставки из запроса
     */
    private function extract_delivery_data_from_request() {
        $delivery_data = array();
        
        // 1. Проверяем $_POST
        if (isset($_POST['discuss_delivery_selected']) && $_POST['discuss_delivery_selected'] == '1') {
            $delivery_data['discuss_delivery'] = true;
            error_log('СДЭК: Найдено поле discuss_delivery_selected в $_POST');
        }
        
        // 2. Проверяем $_REQUEST (для совместимости)
        if (empty($delivery_data) && isset($_REQUEST['discuss_delivery_selected']) && $_REQUEST['discuss_delivery_selected'] == '1') {
            $delivery_data['discuss_delivery'] = true;
            error_log('СДЭК: Найдено поле discuss_delivery_selected в $_REQUEST');
        }
        
        // 3. Проверяем JSON данные (для WooCommerce Blocks)
        $input = file_get_contents('php://input');
        if ($input && !empty($input)) {
            $json_data = json_decode($input, true);
            if (is_array($json_data)) {
                // Проверяем различные структуры данных
                if (isset($json_data['discuss_delivery_selected'])) {
                    $delivery_data['discuss_delivery'] = ($json_data['discuss_delivery_selected'] == '1');
                    error_log('СДЭК: Найдено поле discuss_delivery_selected в JSON');
                }
                
                // Проверяем extensions для WooCommerce Blocks
                if (isset($json_data['extensions']['cdek-delivery']['discuss_delivery_selected'])) {
                    $delivery_data['discuss_delivery'] = ($json_data['extensions']['cdek-delivery']['discuss_delivery_selected'] == '1');
                    error_log('СДЭК: Найдено поле в extensions.cdek-delivery');
                }
                
                // Проверяем другие возможные структуры
                if (isset($json_data['extensionData']['cdek-delivery']['discuss_delivery_selected'])) {
                    $delivery_data['discuss_delivery'] = ($json_data['extensionData']['cdek-delivery']['discuss_delivery_selected'] == '1');
                    error_log('СДЭК: Найдено поле в extensionData.cdek-delivery');
                }
            }
        }
        
        // 4. Проверяем глобальные переменные JavaScript (через AJAX)
        if (empty($delivery_data) && isset($_POST['cdek_delivery_data'])) {
            $cdek_data = json_decode(stripslashes($_POST['cdek_delivery_data']), true);
            if (isset($cdek_data['discuss_delivery_selected'])) {
                $delivery_data['discuss_delivery'] = ($cdek_data['discuss_delivery_selected'] == '1');
                error_log('СДЭК: Найдено поле в cdek_delivery_data');
            }
        }
        
        // 5. Проверяем данные СДЭК пункта выдачи
        if (isset($_POST['cdek_point_code'])) {
            $delivery_data['cdek_point_code'] = sanitize_text_field($_POST['cdek_point_code']);
        }
        
        if (isset($_POST['cdek_point_data'])) {
            $delivery_data['cdek_point_data'] = $_POST['cdek_point_data'];
        }
        
        return $delivery_data;
    }
    
    /**
     * Сохранение данных доставки в заказе
     */
    private function save_delivery_data_to_order($order_id, $delivery_data) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Сохраняем выбор "Обсудить доставку с менеджером"
        if (isset($delivery_data['discuss_delivery']) && $delivery_data['discuss_delivery']) {
            update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
            
            // Добавляем как кастомные поля для отображения в email
            $order->update_meta_data('Тип доставки', 'Обсудить с менеджером');
            $order->update_meta_data('Статус доставки', 'Требуется обсуждение');
            $order->add_order_note('Клиент выбрал "Обсудить доставку с менеджером"');
            
            error_log('СДЭК: Сохранен выбор "Обсудить доставку" для заказа #' . $order_id);
        }
        
        // Сохраняем данные СДЭК пункта
        if (isset($delivery_data['cdek_point_code'])) {
            update_post_meta($order_id, '_cdek_point_code', $delivery_data['cdek_point_code']);
        }
        
        if (isset($delivery_data['cdek_point_data'])) {
            update_post_meta($order_id, '_cdek_point_data', $delivery_data['cdek_point_data']);
        }
        
        $order->save();
    }
    
    /**
     * Убеждаемся, что данные доставки сохранены
     */
    private function ensure_delivery_data_saved($order_id) {
        // Проверяем, есть ли уже сохраненные данные
        $discuss_delivery = get_post_meta($order_id, '_discuss_delivery_selected', true);
        
        if (!$discuss_delivery) {
            // Пытаемся извлечь данные еще раз
            $delivery_data = $this->extract_delivery_data_from_request();
            if (!empty($delivery_data)) {
                $this->save_delivery_data_to_order($order_id, $delivery_data);
                error_log('СДЭК: Повторное сохранение данных для заказа #' . $order_id);
            }
        }
    }
    
    /**
     * AJAX обработчик для сохранения выбора доставки
     */
    public function ajax_save_delivery_choice() {
        check_ajax_referer('cdek_delivery_nonce', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $discuss_delivery = ($_POST['discuss_delivery'] == '1');
        
        if ($order_id && $discuss_delivery) {
            $delivery_data = array('discuss_delivery' => true);
            $this->save_delivery_data_to_order($order_id, $delivery_data);
            
            wp_send_json_success('Данные сохранены');
        } else {
            wp_send_json_error('Неверные данные');
        }
    }
    
    /**
     * Добавление информации о доставке в email
     */
    public function add_delivery_info_to_email($order, $sent_to_admin, $plain_text, $email) {
        $order_id = $order->get_id();
        $discuss_delivery = get_post_meta($order_id, '_discuss_delivery_selected', true);
        
        if ($discuss_delivery == 'Да') {
            if ($plain_text) {
                echo "\n" . str_repeat('=', 50) . "\n";
                echo "ВАЖНО: ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ\n";
                echo str_repeat('=', 50) . "\n";
                echo "Клиент выбрал опцию 'Обсудить доставку с менеджером'.\n";
                echo "Необходимо связаться с клиентом для уточнения деталей доставки.\n\n";
            } else {
                ?>
                <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 8px;">
                    <h3 style="color: #856404; margin-top: 0;">⚠️ ВАЖНО: ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ</h3>
                    <p style="color: #856404; margin-bottom: 0;">
                        Клиент выбрал опцию <strong>"Обсудить доставку с менеджером"</strong>.<br>
                        Необходимо связаться с клиентом для уточнения деталей доставки.
                    </p>
                </div>
                <?php
            }
        }
        
        // Добавляем информацию о СДЭК пункте, если есть
        $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
        $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
        
        if ($cdek_point_code && $cdek_point_data) {
            if (is_string($cdek_point_data)) {
                $cdek_point_data = json_decode($cdek_point_data, true);
            }
            
            if ($plain_text) {
                echo "\n" . str_repeat('=', 50) . "\n";
                echo "ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК\n";
                echo str_repeat('=', 50) . "\n";
                echo "Пункт выдачи: " . ($cdek_point_data['name'] ?? 'Не указан') . "\n";
                echo "Код пункта: " . $cdek_point_code . "\n";
                if (isset($cdek_point_data['location']['address_full'])) {
                    echo "Адрес: " . $cdek_point_data['location']['address_full'] . "\n";
                }
                echo "\n";
            } else {
                ?>
                <div style="background: #e8f5e8; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px;">
                    <h3 style="color: #28a745; margin-top: 0;">📦 ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК</h3>
                    <p><strong>Пункт выдачи:</strong> <?php echo esc_html($cdek_point_data['name'] ?? 'Не указан'); ?></p>
                    <p><strong>Код пункта:</strong> <?php echo esc_html($cdek_point_code); ?></p>
                    <?php if (isset($cdek_point_data['location']['address_full'])): ?>
                        <p><strong>Адрес:</strong> <?php echo esc_html($cdek_point_data['location']['address_full']); ?></p>
                    <?php endif; ?>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Отображение информации о доставке в админке
     */
    public function display_delivery_info_in_admin($order) {
        $order_id = $order->get_id();
        $discuss_delivery = get_post_meta($order_id, '_discuss_delivery_selected', true);
        
        if ($discuss_delivery == 'Да') {
            ?>
            <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 8px;">
                <h4 style="color: #856404; margin: 0; font-size: 16px;">
                    ⚠️ ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ
                </h4>
                <p style="color: #856404; margin: 10px 0 0 0;">
                    Клиент выбрал опцию "Обсудить доставку с менеджером". 
                    Необходимо связаться с клиентом для уточнения деталей доставки.
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Регистрация REST маршрутов
     */
    public function register_rest_routes() {
        register_rest_route('cdek/v1', '/save-delivery-choice', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_save_delivery_choice'),
            'permission_callback' => '__return_true',
            'args' => array(
                'order_id' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'discuss_delivery' => array(
                    'required' => true,
                    'type' => 'boolean'
                )
            )
        ));
    }
    
    /**
     * REST API обработчик для сохранения выбора доставки
     */
    public function rest_save_delivery_choice($request) {
        $order_id = $request->get_param('order_id');
        $discuss_delivery = $request->get_param('discuss_delivery');
        
        if ($discuss_delivery) {
            $delivery_data = array('discuss_delivery' => true);
            $this->save_delivery_data_to_order($order_id, $delivery_data);
            
            return new WP_REST_Response('Данные сохранены', 200);
        }
        
        return new WP_REST_Response('Неверные данные', 400);
    }
    
    /**
     * Обработка данных из REST checkout
     */
    public function process_rest_checkout_data($result, $server) {
        error_log('СДЭК REST: Обработка REST checkout данных');
        
        // Получаем данные из запроса
        $request = $server->get_request();
        $body = $request->get_body();
        
        if ($body) {
            $data = json_decode($body, true);
            if (isset($data['extensions']['cdek-delivery']['discuss_delivery_selected'])) {
                // Сохраняем в глобальной переменной для последующего использования
                $GLOBALS['cdek_discuss_delivery'] = ($data['extensions']['cdek-delivery']['discuss_delivery_selected'] == '1');
                error_log('СДЭК REST: Найдены данные в extensions');
            }
        }
        
        return $result;
    }
}

// Инициализация интеграции
new CDEK_Delivery_Blocks_Integration();