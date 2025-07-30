<?php
/**
 * СДЭК Доставка - Обработчик данных доставки
 * Файл для передачи данных доставки в email, заказ и админку
 * 
 * @package CDEK_Delivery
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс для обработки данных доставки СДЭК
 */
class CDEK_Delivery_Data_Handler {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Инициализация хуков WordPress
     */
    private function init_hooks() {
        // Этот файл теперь используется только как резерв
        // Основной функционал перенесен в тему через стандартные шаблоны WooCommerce
        
        // Хуки для сохранения данных в заказе (основной функционал)
        add_action('woocommerce_checkout_order_processed', array($this, 'save_delivery_data_to_order'), 10, 3);
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_delivery_meta_data'), 10, 1);
        
        // Дополнительные хуки для совместимости
        add_action('woocommerce_order_status_changed', array($this, 'log_delivery_data_change'), 10, 3);
        
        // Резервный хук для email (если тема не содержит шаблонов)
        add_action('init', array($this, 'maybe_add_fallback_email_hook'));
    }
    
    /**
     * Проверка необходимости добавления резервных хуков
     */
    public function maybe_add_fallback_email_hook() {
        // Проверяем, есть ли кастомные шаблоны в теме
        $theme_has_templates = $this->theme_has_cdek_templates();
        
        if (!$theme_has_templates) {
            // Если в теме нет шаблонов, добавляем резервные хуки
            add_action('woocommerce_email_order_details', array($this, 'add_delivery_info_to_email'), 20, 4);
            add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_delivery_info_in_admin'), 15);
            
            error_log('СДЭК Data Handler: Кастомные шаблоны в теме не найдены, используется резервный функционал');
        } else {
            error_log('СДЭК Data Handler: Найдены кастомные шаблоны в теме, резервный функционал отключен');
        }
    }
    
    /**
     * Проверка наличия СДЭК шаблонов в теме
     */
    private function theme_has_cdek_templates() {
        $theme_dir = get_template_directory();
        
        // Проверяем наличие кастомных email шаблонов
        $admin_template = $theme_dir . '/woocommerce/emails/admin-new-order.php';
        $customer_template = $theme_dir . '/woocommerce/emails/customer-completed-order.php';
        
        // Проверяем наличие СДЭК функций в functions.php темы
        $functions_file = $theme_dir . '/functions.php';
        $has_functions = false;
        
        if (file_exists($functions_file)) {
            $functions_content = file_get_contents($functions_file);
            $has_functions = strpos($functions_content, 'cdek_theme_init') !== false;
        }
        
        return (file_exists($admin_template) || file_exists($customer_template) || $has_functions);
    }
    
    /**
     * Добавление информации о доставке в email уведомления
     *
     * @param WC_Order $order Объект заказа
     * @param bool $sent_to_admin Отправляется ли администратору
     * @param bool $plain_text Текстовый формат
     * @param WC_Email $email Объект email
     */
    public function add_delivery_info_to_email($order, $sent_to_admin, $plain_text, $email) {
        $order_id = $order->get_id();
        $delivery_data = $this->get_delivery_data_from_order($order_id);
        
        if (!$delivery_data) {
            return;
        }
        
        if ($plain_text) {
            $this->render_text_email_template($delivery_data);
        } else {
            $this->render_html_email_template($delivery_data);
        }
    }
    
    /**
     * Сохранение данных доставки при оформлении заказа
     *
     * @param int $order_id ID заказа
     * @param array $posted_data Данные формы
     * @param WC_Order $order Объект заказа
     */
    public function save_delivery_data_to_order($order_id, $posted_data, $order) {
        $this->save_delivery_meta_data($order_id);
    }
    
    /**
     * Сохранение метаданных доставки
     *
     * @param int $order_id ID заказа
     */
    public function save_delivery_meta_data($order_id) {
        // Сохраняем стоимость доставки СДЭК
        if (isset($_POST['cdek_delivery_cost']) && !empty($_POST['cdek_delivery_cost'])) {
            $delivery_cost = sanitize_text_field($_POST['cdek_delivery_cost']);
            update_post_meta($order_id, '_cdek_delivery_cost', $delivery_cost);
            error_log('СДЭК Data Handler: Сохранена стоимость доставки для заказа ' . $order_id . ': ' . $delivery_cost . ' руб.');
        }
        
        // Сохраняем код пункта выдачи
        if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
            $point_code = sanitize_text_field($_POST['cdek_selected_point_code']);
            update_post_meta($order_id, '_cdek_point_code', $point_code);
            error_log('СДЭК Data Handler: Сохранен код пункта выдачи для заказа ' . $order_id . ': ' . $point_code);
        }
        
        // Сохраняем данные пункта выдачи
        if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
            $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
            if ($point_data && is_array($point_data)) {
                update_post_meta($order_id, '_cdek_point_data', $point_data);
                $point_name = isset($point_data['name']) ? $point_data['name'] : 'Пункт выдачи';
                error_log('СДЭК Data Handler: Сохранены данные пункта выдачи для заказа ' . $order_id . ': ' . $point_name);
                
                // Дополнительно сохраняем структурированные данные
                $this->save_structured_delivery_data($order_id, $point_data);
            }
        }
    }
    
    /**
     * Сохранение структурированных данных доставки
     *
     * @param int $order_id ID заказа
     * @param array $point_data Данные пункта выдачи
     */
    private function save_structured_delivery_data($order_id, $point_data) {
        // Сохраняем название пункта для удобного доступа
        if (isset($point_data['name'])) {
            $point_name = $point_data['name'];
            if (isset($point_data['location']['city'])) {
                $city = $point_data['location']['city'];
                $point_name = $city . ', ' . str_replace($city, '', $point_name);
                $point_name = trim($point_name, ', ');
            }
            update_post_meta($order_id, '_cdek_point_display_name', $point_name);
        }
        
        // Сохраняем полный адрес
        if (isset($point_data['location']['address_full'])) {
            update_post_meta($order_id, '_cdek_point_address', $point_data['location']['address_full']);
        }
        
        // Сохраняем телефон
        if (isset($point_data['phones']) && is_array($point_data['phones']) && !empty($point_data['phones'])) {
            $phone = $point_data['phones'][0]['number'] ?? $point_data['phones'][0];
            update_post_meta($order_id, '_cdek_point_phone', $phone);
        }
        
        // Сохраняем режим работы
        if (isset($point_data['work_time'])) {
            update_post_meta($order_id, '_cdek_point_work_time', $point_data['work_time']);
        }
        
        // Сохраняем город отдельно
        if (isset($point_data['location']['city'])) {
            update_post_meta($order_id, '_cdek_point_city', $point_data['location']['city']);
        }
    }
    
    /**
     * Отображение информации о доставке в админке заказа
     *
     * @param WC_Order $order Объект заказа
     */
    public function display_delivery_info_in_admin($order) {
        $order_id = $order->get_id();
        $delivery_data = $this->get_delivery_data_from_order($order_id);
        
        if (!$delivery_data) {
            return;
        }
        
        $this->render_admin_template($delivery_data);
    }
    
    /**
     * Получение данных доставки из заказа
     *
     * @param int $order_id ID заказа
     * @return array|false Данные доставки или false
     */
    private function get_delivery_data_from_order($order_id) {
        $point_code = get_post_meta($order_id, '_cdek_point_code', true);
        $point_data = get_post_meta($order_id, '_cdek_point_data', true);
        $delivery_cost = get_post_meta($order_id, '_cdek_delivery_cost', true);
        
        if (!$point_code || !$point_data) {
            return false;
        }
        
        // Получаем стоимость доставки из метаданных или из самого заказа
        if (!$delivery_cost) {
            $order = wc_get_order($order_id);
            $shipping_methods = $order->get_shipping_methods();
            
            foreach ($shipping_methods as $shipping_method) {
                if (strpos($shipping_method->get_method_id(), 'cdek') !== false) {
                    $delivery_cost = $shipping_method->get_total();
                    break;
                }
            }
        }
        
        return array(
            'point_code' => $point_code,
            'point_data' => $point_data,
            'delivery_cost' => $delivery_cost,
            'point_display_name' => get_post_meta($order_id, '_cdek_point_display_name', true),
            'point_address' => get_post_meta($order_id, '_cdek_point_address', true),
            'point_phone' => get_post_meta($order_id, '_cdek_point_phone', true),
            'point_work_time' => get_post_meta($order_id, '_cdek_point_work_time', true),
            'point_city' => get_post_meta($order_id, '_cdek_point_city', true),
        );
    }
    
    /**
     * Рендеринг HTML шаблона для email
     *
     * @param array $delivery_data Данные доставки
     */
    private function render_html_email_template($delivery_data) {
        $point_data = $delivery_data['point_data'];
        $point_name = $delivery_data['point_display_name'] ?: $point_data['name'];
        $delivery_cost = $delivery_data['delivery_cost'];
        $point_address = $delivery_data['point_address'] ?: $point_data['location']['address_full'];
        
        echo '<div style="background: #f8f9fa; border: 1px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">';
        echo '<h3 style="color: #28a745; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px;">📦 Информация о доставке СДЭК</h3>';
        
        // Название пункта выдачи
        echo '<p style="margin: 10px 0;"><strong>Пункт выдачи:</strong> ' . esc_html($point_name) . '</p>';
        
        // Стоимость доставки
        if ($delivery_cost) {
            echo '<p style="margin: 10px 0;"><strong>Стоимость доставки:</strong> <span style="color: #28a745; font-weight: bold;">' . esc_html($delivery_cost) . ' руб.</span></p>';
        }
        
        // Полный адрес
        if ($point_address) {
            echo '<p style="margin: 10px 0;"><strong>Адрес:</strong> ' . esc_html($point_address) . '</p>';
        }
        
        // Код пункта
        echo '<p style="margin: 10px 0;"><strong>Код пункта:</strong> <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px;">' . esc_html($delivery_data['point_code']) . '</code></p>';
        
        // Телефон если есть
        if ($delivery_data['point_phone']) {
            echo '<p style="margin: 10px 0;"><strong>Телефон пункта:</strong> <a href="tel:' . esc_attr($delivery_data['point_phone']) . '" style="color: #007cba; text-decoration: none;">' . esc_html($delivery_data['point_phone']) . '</a></p>';
        }
        
        // Режим работы
        if ($delivery_data['point_work_time']) {
            echo '<p style="margin: 10px 0;"><strong>Режим работы:</strong> ' . esc_html($delivery_data['point_work_time']) . '</p>';
        }
        
        echo '<div style="margin-top: 15px; padding: 10px; background: #e8f5e8; border-radius: 4px; font-size: 14px;">';
        echo '<strong>💡 Важно:</strong> Сохраните эту информацию для получения заказа в пункте выдачи СДЭК.';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Рендеринг текстового шаблона для email
     *
     * @param array $delivery_data Данные доставки
     */
    private function render_text_email_template($delivery_data) {
        $point_data = $delivery_data['point_data'];
        $point_name = $delivery_data['point_display_name'] ?: $point_data['name'];
        $delivery_cost = $delivery_data['delivery_cost'];
        $point_address = $delivery_data['point_address'] ?: $point_data['location']['address_full'];
        
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК\n";
        echo str_repeat('=', 50) . "\n";
        
        // Название пункта выдачи
        echo "Пункт выдачи: " . $point_name . "\n";
        
        // Стоимость доставки
        if ($delivery_cost) {
            echo "Стоимость доставки: " . $delivery_cost . " руб.\n";
        }
        
        // Полный адрес
        if ($point_address) {
            echo "Адрес: " . $point_address . "\n";
        }
        
        // Код пункта
        echo "Код пункта: " . $delivery_data['point_code'] . "\n";
        
        // Телефон если есть
        if ($delivery_data['point_phone']) {
            echo "Телефон пункта: " . $delivery_data['point_phone'] . "\n";
        }
        
        // Режим работы
        if ($delivery_data['point_work_time']) {
            echo "Режим работы: " . $delivery_data['point_work_time'] . "\n";
        }
        
        echo "\nВажно: Сохраните эту информацию для получения заказа в пункте выдачи СДЭК.\n";
        echo str_repeat('=', 50) . "\n\n";
    }
    
    /**
     * Рендеринг шаблона для админки
     *
     * @param array $delivery_data Данные доставки
     */
    private function render_admin_template($delivery_data) {
        $point_data = $delivery_data['point_data'];
        $point_name = $delivery_data['point_display_name'] ?: $point_data['name'];
        $delivery_cost = $delivery_data['delivery_cost'];
        $point_address = $delivery_data['point_address'] ?: $point_data['location']['address_full'];
        
        echo '<div class="cdek-delivery-info-admin" style="margin-top: 20px; padding: 15px; background: #e8f5e8; border: 1px solid #4caf50; border-radius: 4px;">';
        echo '<h4 style="color: #2e7d32; margin-top: 0;">📦 Информация о доставке СДЭК:</h4>';
        
        // Основная информация
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">';
        
        echo '<div>';
        echo '<div style="margin-bottom: 8px;"><strong>Пункт выдачи:</strong><br>' . esc_html($point_name) . '</div>';
        if ($delivery_cost) {
            echo '<div style="margin-bottom: 8px;"><strong>Стоимость доставки:</strong><br><span style="color: #2e7d32; font-weight: bold;">' . esc_html($delivery_cost) . ' руб.</span></div>';
        }
        echo '<div style="margin-bottom: 8px;"><strong>Код пункта:</strong><br><code style="background: #fff; padding: 4px 8px; border: 1px solid #ddd; border-radius: 3px;">' . esc_html($delivery_data['point_code']) . '</code></div>';
        echo '</div>';
        
        echo '<div>';
        if ($point_address) {
            echo '<div style="margin-bottom: 8px;"><strong>Адрес:</strong><br>' . esc_html($point_address) . '</div>';
        }
        if ($delivery_data['point_phone']) {
            echo '<div style="margin-bottom: 8px;"><strong>Телефон:</strong><br><a href="tel:' . esc_attr($delivery_data['point_phone']) . '" style="color: #007cba;">' . esc_html($delivery_data['point_phone']) . '</a></div>';
        }
        if ($delivery_data['point_work_time']) {
            echo '<div style="margin-bottom: 8px;"><strong>Режим работы:</strong><br>' . esc_html($delivery_data['point_work_time']) . '</div>';
        }
        echo '</div>';
        
        echo '</div>';
        
        // Кнопки действий
        echo '<div style="border-top: 1px solid #4caf50; padding-top: 10px;">';
        echo '<button type="button" class="button button-secondary" onclick="cdekCopyDeliveryInfo()" title="Скопировать информацию о доставке">📋 Копировать информацию</button>';
        echo ' <button type="button" class="button button-secondary" onclick="cdekPrintDeliveryInfo()" title="Распечатать информацию о доставке">🖨️ Печать</button>';
        echo '</div>';
        
        echo '</div>';
        
        // JavaScript для кнопок
        echo '<script>
        function cdekCopyDeliveryInfo() {
            var text = "Пункт выдачи СДЭК: ' . esc_js($point_name) . '\\n";
            text += "Стоимость: ' . esc_js($delivery_cost) . ' руб.\\n";
            text += "Адрес: ' . esc_js($point_address) . '\\n";
            text += "Код: ' . esc_js($delivery_data['point_code']) . '";
            
            navigator.clipboard.writeText(text).then(function() {
                alert("Информация о доставке скопирована в буфер обмена!");
            });
        }
        
        function cdekPrintDeliveryInfo() {
            var printWindow = window.open("", "_blank");
            printWindow.document.write("<html><head><title>Информация о доставке СДЭК</title></head><body>");
            printWindow.document.write("<h2>Информация о доставке СДЭК</h2>");
            printWindow.document.write("<p><strong>Пункт выдачи:</strong> ' . esc_js($point_name) . '</p>");
            printWindow.document.write("<p><strong>Стоимость:</strong> ' . esc_js($delivery_cost) . ' руб.</p>");
            printWindow.document.write("<p><strong>Адрес:</strong> ' . esc_js($point_address) . '</p>");
            printWindow.document.write("<p><strong>Код пункта:</strong> ' . esc_js($delivery_data['point_code']) . '</p>');
            printWindow.document.write("</body></html>");
            printWindow.document.close();
            printWindow.print();
        }
        </script>';
    }
    
    /**
     * Логирование изменений данных доставки
     *
     * @param int $order_id ID заказа
     * @param string $old_status Старый статус
     * @param string $new_status Новый статус
     */
    public function log_delivery_data_change($order_id, $old_status, $new_status) {
        $delivery_data = $this->get_delivery_data_from_order($order_id);
        
        if ($delivery_data) {
            error_log('СДЭК Data Handler: Заказ ' . $order_id . ' изменил статус с "' . $old_status . '" на "' . $new_status . '". Пункт выдачи: ' . $delivery_data['point_code']);
        }
    }
    
    /**
     * Получение форматированной информации о доставке для внешнего использования
     *
     * @param int $order_id ID заказа
     * @return array Форматированные данные
     */
    public function get_formatted_delivery_info($order_id) {
        $delivery_data = $this->get_delivery_data_from_order($order_id);
        
        if (!$delivery_data) {
            return array();
        }
        
        return array(
            'display_name' => $delivery_data['point_display_name'] ?: $delivery_data['point_data']['name'],
            'cost' => $delivery_data['delivery_cost'] . ' руб.',
            'address' => $delivery_data['point_address'] ?: $delivery_data['point_data']['location']['address_full'],
            'code' => $delivery_data['point_code'],
            'phone' => $delivery_data['point_phone'],
            'work_time' => $delivery_data['point_work_time'],
            'city' => $delivery_data['point_city']
        );
    }
}

// Инициализация класса
new CDEK_Delivery_Data_Handler();