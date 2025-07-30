<?php
/**
 * Интеграция СДЭК доставки с WooCommerce Blocks
 * 
 * Этот файл обеспечивает правильную работу кастомных полей
 * с новым checkout блоками WooCommerce
 * 
 * @package CDEK_Delivery_Blocks
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс для интеграции с WooCommerce Blocks
 */
class CDEK_WooCommerce_Blocks_Integration {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Инициализация хуков
     */
    private function init_hooks() {
        // Хуки для WooCommerce Blocks
        add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'save_blocks_delivery_data'));
        add_action('woocommerce_blocks_checkout_order_processed', array($this, 'process_blocks_order'), 10, 1);
        
        // Добавляем поддержку кастомных полей в Store API
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'handle_store_api_order'), 10, 1);
        
        // Регистрируем дополнительные поля для blocks
        add_action('init', array($this, 'register_blocks_fields'));
    }
    
    /**
     * Регистрация дополнительных полей для blocks
     */
    public function register_blocks_fields() {
        if (function_exists('woocommerce_store_api_register_endpoint_data')) {
            woocommerce_store_api_register_endpoint_data(array(
                'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
                'namespace'       => 'cdek-delivery',
                'data_callback'   => array($this, 'get_blocks_data'),
                'schema_callback' => array($this, 'get_blocks_schema'),
                'schema_type'     => ARRAY_A,
            ));
        }
    }
    
    /**
     * Получение данных для blocks
     */
    public function get_blocks_data() {
        return array(
            'discuss_delivery_selected' => '',
            'cdek_delivery_cost' => '',
            'cdek_selected_point_code' => '',
            'cdek_selected_point_data' => '',
        );
    }
    
    /**
     * Схема данных для blocks
     */
    public function get_blocks_schema() {
        return array(
            'discuss_delivery_selected' => array(
                'description' => 'Выбор обсуждения доставки с менеджером',
                'type'        => 'string',
            ),
            'cdek_delivery_cost' => array(
                'description' => 'Стоимость доставки СДЭК',
                'type'        => 'string',
            ),
            'cdek_selected_point_code' => array(
                'description' => 'Код выбранного пункта СДЭК',
                'type'        => 'string',
            ),
            'cdek_selected_point_data' => array(
                'description' => 'Данные выбранного пункта СДЭК',
                'type'        => 'string',
            ),
        );
    }
    
    /**
     * Сохранение данных из WooCommerce Blocks
     */
    public function save_blocks_delivery_data($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        error_log('СДЭК Blocks: Обработка заказа #' . $order->get_id());
        
        // Получаем данные из request
        $request_data = $this->get_request_data();
        
        if (empty($request_data)) {
            error_log('СДЭК Blocks: Данные запроса пустые');
            return;
        }
        
        $this->process_delivery_data($order, $request_data);
    }
    
    /**
     * Обработка заказа из blocks
     */
    public function process_blocks_order($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        error_log('СДЭК Blocks: Финальная обработка заказа #' . $order->get_id());
        $this->save_blocks_delivery_data($order);
    }
    
    /**
     * Обработка через Store API
     */
    public function handle_store_api_order($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        error_log('СДЭК Store API: Обработка заказа #' . $order->get_id());
        $this->save_blocks_delivery_data($order);
    }
    
    /**
     * Получение данных из запроса
     */
    private function get_request_data() {
        $data = array();
        
        // Проверяем различные источники данных
        
        // 1. JSON данные из body
        $json_input = file_get_contents('php://input');
        if ($json_input) {
            $json_data = json_decode($json_input, true);
            if (is_array($json_data)) {
                // Ищем наши поля в разных местах JSON
                $data = array_merge($data, $this->extract_delivery_fields($json_data));
            }
        }
        
        // 2. $_POST данные (fallback)
        if (!empty($_POST)) {
            $data = array_merge($data, $this->extract_delivery_fields($_POST));
        }
        
        // 3. $_REQUEST данные (еще один fallback)
        if (!empty($_REQUEST)) {
            $data = array_merge($data, $this->extract_delivery_fields($_REQUEST));
        }
        
        error_log('СДЭК Blocks: Извлеченные данные: ' . print_r($data, true));
        
        return $data;
    }
    
    /**
     * Извлечение полей доставки из массива данных
     */
    private function extract_delivery_fields($source_data) {
        $fields = array();
        $search_fields = array(
            'discuss_delivery_selected',
            'cdek_delivery_cost',
            'cdek_selected_point_code', 
            'cdek_selected_point_data'
        );
        
        // Прямой поиск
        foreach ($search_fields as $field) {
            if (isset($source_data[$field])) {
                $fields[$field] = $source_data[$field];
            }
        }
        
        // Поиск в вложенных массивах (для API запросов)
        if (isset($source_data['extensions'])) {
            foreach ($search_fields as $field) {
                if (isset($source_data['extensions'][$field])) {
                    $fields[$field] = $source_data['extensions'][$field];
                }
                if (isset($source_data['extensions']['cdek-delivery'][$field])) {
                    $fields[$field] = $source_data['extensions']['cdek-delivery'][$field];
                }
            }
        }
        
        // Поиск в дополнительных полях
        if (isset($source_data['additional_fields'])) {
            foreach ($search_fields as $field) {
                if (isset($source_data['additional_fields'][$field])) {
                    $fields[$field] = $source_data['additional_fields'][$field];
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Обработка данных доставки
     */
    private function process_delivery_data($order, $data) {
        $order_id = $order->get_id();
        
        // Обработка "Обсудить доставку с менеджером"
        if (isset($data['discuss_delivery_selected']) && $data['discuss_delivery_selected'] == '1') {
            error_log('СДЭК Blocks: Сохраняем выбор обсуждения доставки для заказа #' . $order_id);
            
            // Сохраняем в старом формате для совместимости
            update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
            
            // Добавляем кастомные поля
            $order->update_meta_data('Тип доставки', 'Обсудить с менеджером');
            $order->update_meta_data('Статус доставки', 'Требуется обсуждение');
            $order->update_meta_data('Действие менеджера', 'Связаться с клиентом для обсуждения доставки');
            $order->add_order_note('Клиент выбрал "Обсудить доставку с менеджером"');
            $order->save();
        }
        
        // Обработка данных СДЭК
        if (isset($data['cdek_selected_point_code']) && !empty($data['cdek_selected_point_code'])) {
            $point_code = sanitize_text_field($data['cdek_selected_point_code']);
            error_log('СДЭК Blocks: Сохраняем данные СДЭК для заказа #' . $order_id . ', код: ' . $point_code);
            
            // Сохраняем в старом формате для совместимости
            update_post_meta($order_id, '_cdek_point_code', $point_code);
            
            // Обрабатываем данные пункта выдачи
            if (isset($data['cdek_selected_point_data']) && !empty($data['cdek_selected_point_data'])) {
                $point_data = json_decode(stripslashes($data['cdek_selected_point_data']), true);
                if ($point_data && is_array($point_data)) {
                    update_post_meta($order_id, '_cdek_point_data', $point_data);
                    
                    // Добавляем кастомные поля для СДЭК
                    $point_name = isset($point_data['name']) ? $point_data['name'] : 'Пункт выдачи';
                    
                    $order->update_meta_data('Тип доставки', 'СДЭК');
                    $order->update_meta_data('Пункт выдачи СДЭК', $point_name);
                    $order->update_meta_data('Код пункта СДЭК', $point_code);
                    
                    if (isset($point_data['location']['address_full'])) {
                        $order->update_meta_data('Адрес пункта выдачи', $point_data['location']['address_full']);
                    }
                    
                    if (isset($point_data['work_time'])) {
                        $order->update_meta_data('Время работы ПВЗ', $point_data['work_time']);
                    }
                    
                    if (isset($point_data['phone'])) {
                        $order->update_meta_data('Телефон ПВЗ', $point_data['phone']);
                    }
                    
                    $order->save();
                }
            }
        }
        
        // Обработка стоимости доставки
        if (isset($data['cdek_delivery_cost']) && !empty($data['cdek_delivery_cost'])) {
            $delivery_cost = sanitize_text_field($data['cdek_delivery_cost']);
            error_log('СДЭК Blocks: Сохраняем стоимость доставки для заказа #' . $order_id . ': ' . $delivery_cost);
            
            update_post_meta($order_id, '_cdek_delivery_cost', $delivery_cost);
            $order->update_meta_data('Стоимость доставки СДЭК', $delivery_cost . ' руб.');
            $order->save();
        }
    }
}

// Инициализируем интеграцию только если WooCommerce активен
if (class_exists('WooCommerce')) {
    new CDEK_WooCommerce_Blocks_Integration();
}
?>