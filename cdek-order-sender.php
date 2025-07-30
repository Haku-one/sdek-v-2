<?php
/**
 * СДЭК Order Sender - Отправка данных о доставке
 * Интегрируется с WooCommerce для отправки информации о выбранном ПВЗ
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс для отправки данных о СДЭК доставке
 */
class CdekOrderSender {
    
    public function __construct() {
        // Хуки для обработки заказов
        add_action('woocommerce_checkout_order_processed', array($this, 'process_cdek_order_data'), 20, 3);
        add_action('woocommerce_order_status_changed', array($this, 'send_cdek_notification_on_status_change'), 20, 4);
        
        // Хуки для отображения в админке
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_cdek_info_in_admin'), 10, 1);
        add_action('add_meta_boxes', array($this, 'add_cdek_meta_box'));
        
        // Хуки для email уведомлений
        add_action('woocommerce_email_after_order_table', array($this, 'add_cdek_info_to_emails'), 20, 4);
        add_filter('woocommerce_email_styles', array($this, 'add_cdek_email_styles'));
        
        // Хуки для отображения в аккаунте клиента
        add_action('woocommerce_view_order', array($this, 'display_cdek_info_in_account'), 20);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_cdek_tracking_info'), 20);
        
        // REST API хуки для внешних интеграций
        add_action('rest_api_init', array($this, 'register_cdek_api_endpoints'));
        
        // Webhook хуки для уведомления внешних систем
        add_action('woocommerce_order_status_completed', array($this, 'send_cdek_webhook'), 10, 1);
        
        // Интеграция с популярными плагинами
        add_action('plugins_loaded', array($this, 'init_third_party_integrations'));
    }
    
    /**
     * Обработка данных заказа при оформлении
     */
    public function process_cdek_order_data($order_id, $posted_data, $order) {
        // Получаем данные о выбранном ПВЗ
        $cdek_point_code = sanitize_text_field($_POST['cdek_selected_point_code'] ?? '');
        $cdek_point_data = $_POST['cdek_selected_point_data'] ?? '';
        
        if (!empty($cdek_point_code) && !empty($cdek_point_data)) {
            // Декодируем данные ПВЗ
            $point_data = json_decode(stripslashes($cdek_point_data), true);
            
            if ($point_data) {
                // Сохраняем основные данные
                update_post_meta($order_id, '_cdek_point_code', $cdek_point_code);
                update_post_meta($order_id, '_cdek_point_data', $point_data);
                
                // Сохраняем форматированную информацию для удобства
                $formatted_info = $this->format_cdek_info($point_data, $cdek_point_code);
                update_post_meta($order_id, '_cdek_formatted_info', $formatted_info);
                
                // Сохраняем данные о стоимости доставки
                $shipping_cost = $this->extract_shipping_cost($order);
                if ($shipping_cost > 0) {
                    update_post_meta($order_id, '_cdek_shipping_cost', $shipping_cost);
                }
                
                // Создаем заметку для админа
                $note = sprintf(
                    'Выбрана доставка СДЭК в пункт выдачи: %s (код: %s). Адрес: %s. Стоимость доставки: %s руб.',
                    $point_data['name'] ?? 'Не указано',
                    $cdek_point_code,
                    $point_data['location']['address_full'] ?? 'Не указан',
                    $shipping_cost
                );
                
                $order->add_order_note($note);
                
                // Отправляем уведомления
                $this->send_cdek_notifications($order_id, $formatted_info, $shipping_cost);
                
                // Логируем для отладки
                error_log("СДЭК Order Sender: Обработан заказ #{$order_id} с ПВЗ {$cdek_point_code}");
            }
        }
    }
    
    /**
     * Форматирование информации о ПВЗ
     */
    private function format_cdek_info($point_data, $point_code) {
        $info = array(
            'point_name' => $point_data['name'] ?? 'Пункт выдачи СДЭК',
            'point_code' => $point_code,
            'address' => $point_data['location']['address_full'] ?? $point_data['location']['address'] ?? 'Адрес не указан',
            'city' => $point_data['location']['city'] ?? '',
            'phone' => $this->extract_phone($point_data),
            'work_time' => $this->format_work_time($point_data),
            'coordinates' => array(
                'latitude' => $point_data['location']['latitude'] ?? null,
                'longitude' => $point_data['location']['longitude'] ?? null
            )
        );
        
        return $info;
    }
    
    /**
     * Извлечение телефона из данных ПВЗ
     */
    private function extract_phone($point_data) {
        if (isset($point_data['phones']) && is_array($point_data['phones']) && !empty($point_data['phones'])) {
            $phone = $point_data['phones'][0];
            return is_array($phone) ? ($phone['number'] ?? '') : $phone;
        }
        
        return $point_data['phone'] ?? '';
    }
    
    /**
     * Форматирование времени работы
     */
    private function format_work_time($point_data) {
        if (isset($point_data['work_time_list']) && is_array($point_data['work_time_list'])) {
            $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            $schedule = array();
            
            foreach ($point_data['work_time_list'] as $time_slot) {
                if (isset($time_slot['day']) && isset($time_slot['time'])) {
                    $day_name = $days[$time_slot['day'] - 1] ?? '';
                    $schedule[] = $day_name . ': ' . $time_slot['time'];
                }
            }
            
            return implode(', ', $schedule);
        }
        
        return $point_data['work_time'] ?? 'Не указано';
    }
    
    /**
     * Извлечение стоимости доставки из заказа
     */
    private function extract_shipping_cost($order) {
        $shipping_total = 0;
        
        foreach ($order->get_shipping_methods() as $shipping_method) {
            if (strpos($shipping_method->get_method_id(), 'cdek') !== false) {
                $shipping_total += floatval($shipping_method->get_total());
            }
        }
        
        return $shipping_total;
    }
    
    /**
     * Отправка уведомлений
     */
    private function send_cdek_notifications($order_id, $formatted_info, $shipping_cost) {
        // Отправляем email администратору
        $this->send_admin_notification($order_id, $formatted_info, $shipping_cost);
        
        // Отправляем в CRM/ERP системы если настроены
        $this->send_to_external_systems($order_id, $formatted_info, $shipping_cost);
        
        // Отправляем SMS уведомление если настроено
        $this->send_sms_notification($order_id, $formatted_info);
    }
    
    /**
     * Email уведомление администратору
     */
    private function send_admin_notification($order_id, $formatted_info, $shipping_cost) {
        $admin_email = get_option('admin_email');
        $order = wc_get_order($order_id);
        
        $subject = sprintf('Новый заказ СДЭК #%s - %s', $order_id, $formatted_info['point_name']);
        
        $message = $this->get_admin_email_template($order, $formatted_info, $shipping_cost);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>'
        );
        
        wp_mail($admin_email, $subject, $message, $headers);
        
        // Отправляем дополнительным получателям если настроено
        $additional_emails = get_option('cdek_notification_emails', '');
        if (!empty($additional_emails)) {
            $emails = array_map('trim', explode(',', $additional_emails));
            foreach ($emails as $email) {
                if (is_email($email)) {
                    wp_mail($email, $subject, $message, $headers);
                }
            }
        }
    }
    
    /**
     * Шаблон email для администратора
     */
    private function get_admin_email_template($order, $formatted_info, $shipping_cost) {
        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h2 style="color: #333; margin: 0 0 10px 0;">🚚 Новый заказ с доставкой СДЭК</h2>
                <p style="margin: 0; color: #666;">Заказ #<?php echo $order->get_id(); ?> от <?php echo $order->get_date_created()->format('d.m.Y H:i'); ?></p>
            </div>
            
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #333; margin: 0 0 15px 0;">📦 Информация о пункте выдачи</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold; width: 30%;">Название:</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($formatted_info['point_name']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Код ПВЗ:</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($formatted_info['point_code']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Адрес:</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($formatted_info['address']); ?></td>
                    </tr>
                    <?php if (!empty($formatted_info['phone'])): ?>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Телефон:</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($formatted_info['phone']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Режим работы:</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($formatted_info['work_time']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold;">Стоимость доставки:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #007cba;"><?php echo number_format($shipping_cost, 0, '.', ' '); ?> руб.</td>
                    </tr>
                </table>
            </div>
            
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #333; margin: 0 0 15px 0;">👤 Информация о заказчике</h3>
                <p><strong>Имя:</strong> <?php echo esc_html($order->get_formatted_billing_full_name()); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($order->get_billing_email()); ?></p>
                <p><strong>Телефон:</strong> <?php echo esc_html($order->get_billing_phone()); ?></p>
                <p><strong>Сумма заказа:</strong> <?php echo $order->get_formatted_order_total(); ?></p>
            </div>
            
            <div style="text-align: center; padding: 20px;">
                <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" 
                   style="display: inline-block; background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">
                    Просмотреть заказ в админке
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Отображение информации в админке заказа
     */
    public function display_cdek_info_in_admin($order) {
        $cdek_info = get_post_meta($order->get_id(), '_cdek_formatted_info', true);
        $shipping_cost = get_post_meta($order->get_id(), '_cdek_shipping_cost', true);
        
        if ($cdek_info) {
            echo '<div class="cdek-admin-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
            echo '<h3 style="margin: 0 0 15px 0; color: #333;">🚚 Доставка СДЭК</h3>';
            
            echo '<table class="widefat" style="background: white;">';
            echo '<tr><td><strong>Пункт выдачи:</strong></td><td>' . esc_html($cdek_info['point_name']) . '</td></tr>';
            echo '<tr><td><strong>Код ПВЗ:</strong></td><td>' . esc_html($cdek_info['point_code']) . '</td></tr>';
            echo '<tr><td><strong>Адрес:</strong></td><td>' . esc_html($cdek_info['address']) . '</td></tr>';
            
            if (!empty($cdek_info['phone'])) {
                echo '<tr><td><strong>Телефон:</strong></td><td>' . esc_html($cdek_info['phone']) . '</td></tr>';
            }
            
            echo '<tr><td><strong>Режим работы:</strong></td><td>' . esc_html($cdek_info['work_time']) . '</td></tr>';
            
            if ($shipping_cost > 0) {
                echo '<tr><td><strong>Стоимость доставки:</strong></td><td><span style="font-weight: bold; color: #007cba;">' . number_format($shipping_cost, 0, '.', ' ') . ' руб.</span></td></tr>';
            }
            
            echo '</table>';
            
            // Добавляем кнопки действий
            echo '<div style="margin-top: 15px;">';
            echo '<button type="button" class="button" onclick="cdekCopyPointInfo()">📋 Копировать адрес</button> ';
            echo '<button type="button" class="button" onclick="cdekOpenMap()">🗺️ Показать на карте</button>';
            echo '</div>';
            
            echo '</div>';
            
            // JavaScript для функций
            ?>
            <script>
            function cdekCopyPointInfo() {
                const text = '<?php echo esc_js($cdek_info['address']); ?>';
                navigator.clipboard.writeText(text).then(() => {
                    alert('Адрес скопирован в буфер обмена');
                });
            }
            
            function cdekOpenMap() {
                <?php if (!empty($cdek_info['coordinates']['latitude']) && !empty($cdek_info['coordinates']['longitude'])): ?>
                const lat = <?php echo $cdek_info['coordinates']['latitude']; ?>;
                const lng = <?php echo $cdek_info['coordinates']['longitude']; ?>;
                const url = `https://yandex.ru/maps/?pt=${lng},${lat}&z=16&l=map`;
                window.open(url, '_blank');
                <?php else: ?>
                const address = encodeURIComponent('<?php echo esc_js($cdek_info['address']); ?>');
                const url = `https://yandex.ru/maps/?text=${address}`;
                window.open(url, '_blank');
                <?php endif; ?>
            }
            </script>
            <?php
        }
    }
    
    /**
     * Добавление мета-бокса в админку заказа
     */
    public function add_cdek_meta_box() {
        add_meta_box(
            'cdek_delivery_info',
            '🚚 Информация о доставке СДЭК',
            array($this, 'render_cdek_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }
    
    /**
     * Рендер мета-бокса
     */
    public function render_cdek_meta_box($post) {
        $cdek_info = get_post_meta($post->ID, '_cdek_formatted_info', true);
        $shipping_cost = get_post_meta($post->ID, '_cdek_shipping_cost', true);
        
        if ($cdek_info) {
            echo '<div style="padding: 10px;">';
            echo '<p><strong>' . esc_html($cdek_info['point_name']) . '</strong></p>';
            echo '<p><small>' . esc_html($cdek_info['address']) . '</small></p>';
            
            if ($shipping_cost > 0) {
                echo '<p><strong>Стоимость: ' . number_format($shipping_cost, 0, '.', ' ') . ' руб.</strong></p>';
            }
            
            echo '<p><a href="#" onclick="cdekOpenMap(); return false;" class="button-secondary">Показать на карте</a></p>';
            echo '</div>';
        } else {
            echo '<p>Информация о доставке СДЭК не найдена.</p>';
        }
    }
    
    /**
     * Добавление информации в email уведомления
     */
    public function add_cdek_info_to_emails($order, $sent_to_admin, $plain_text, $email) {
        $cdek_info = get_post_meta($order->get_id(), '_cdek_formatted_info', true);
        $shipping_cost = get_post_meta($order->get_id(), '_cdek_shipping_cost', true);
        
        if ($cdek_info) {
            if ($plain_text) {
                echo "\n" . "=== ДОСТАВКА СДЭК ===" . "\n";
                echo "Пункт выдачи: " . $cdek_info['point_name'] . "\n";
                echo "Адрес: " . $cdek_info['address'] . "\n";
                if (!empty($cdek_info['phone'])) {
                    echo "Телефон: " . $cdek_info['phone'] . "\n";
                }
                echo "Режим работы: " . $cdek_info['work_time'] . "\n";
                if ($shipping_cost > 0) {
                    echo "Стоимость доставки: " . number_format($shipping_cost, 0, '.', ' ') . " руб.\n";
                }
                echo "\n";
            } else {
                ?>
                <div class="cdek-email-info" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
                    <h3 style="margin: 0 0 15px 0; color: #333;">🚚 Информация о доставке СДЭК</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 5px 0; font-weight: bold;">Пункт выдачи:</td>
                            <td style="padding: 5px 0;"><?php echo esc_html($cdek_info['point_name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; font-weight: bold;">Адрес:</td>
                            <td style="padding: 5px 0;"><?php echo esc_html($cdek_info['address']); ?></td>
                        </tr>
                        <?php if (!empty($cdek_info['phone'])): ?>
                        <tr>
                            <td style="padding: 5px 0; font-weight: bold;">Телефон:</td>
                            <td style="padding: 5px 0;"><?php echo esc_html($cdek_info['phone']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="padding: 5px 0; font-weight: bold;">Режим работы:</td>
                            <td style="padding: 5px 0;"><?php echo esc_html($cdek_info['work_time']); ?></td>
                        </tr>
                        <?php if ($shipping_cost > 0): ?>
                        <tr>
                            <td style="padding: 5px 0; font-weight: bold;">Стоимость доставки:</td>
                            <td style="padding: 5px 0; font-weight: bold; color: #007cba;"><?php echo number_format($shipping_cost, 0, '.', ' '); ?> руб.</td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Стили для email
     */
    public function add_cdek_email_styles($styles) {
        $styles .= '
        .cdek-email-info {
            margin: 20px 0 !important;
            padding: 20px !important;
            background: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 8px !important;
        }
        .cdek-email-info h3 {
            margin: 0 0 15px 0 !important;
            color: #333 !important;
        }
        .cdek-email-info table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        .cdek-email-info td {
            padding: 5px 0 !important;
        }
        ';
        
        return $styles;
    }
    
    /**
     * Отображение в аккаунте клиента
     */
    public function display_cdek_info_in_account($order_id) {
        $cdek_info = get_post_meta($order_id, '_cdek_formatted_info', true);
        $shipping_cost = get_post_meta($order_id, '_cdek_shipping_cost', true);
        
        if ($cdek_info) {
            ?>
            <div class="cdek-customer-info" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
                <h3>🚚 Информация о доставке СДЭК</h3>
                <p><strong>Пункт выдачи:</strong> <?php echo esc_html($cdek_info['point_name']); ?></p>
                <p><strong>Адрес:</strong> <?php echo esc_html($cdek_info['address']); ?></p>
                <?php if (!empty($cdek_info['phone'])): ?>
                <p><strong>Телефон:</strong> <?php echo esc_html($cdek_info['phone']); ?></p>
                <?php endif; ?>
                <p><strong>Режим работы:</strong> <?php echo esc_html($cdek_info['work_time']); ?></p>
                <?php if ($shipping_cost > 0): ?>
                <p><strong>Стоимость доставки:</strong> <span style="font-weight: bold; color: #007cba;"><?php echo number_format($shipping_cost, 0, '.', ' '); ?> руб.</span></p>
                <?php endif; ?>
                
                <p>
                    <a href="#" onclick="cdekOpenCustomerMap(); return false;" class="button">🗺️ Показать на карте</a>
                </p>
            </div>
            
            <script>
            function cdekOpenCustomerMap() {
                <?php if (!empty($cdek_info['coordinates']['latitude']) && !empty($cdek_info['coordinates']['longitude'])): ?>
                const lat = <?php echo $cdek_info['coordinates']['latitude']; ?>;
                const lng = <?php echo $cdek_info['coordinates']['longitude']; ?>;
                const url = `https://yandex.ru/maps/?pt=${lng},${lat}&z=16&l=map`;
                window.open(url, '_blank');
                <?php else: ?>
                const address = encodeURIComponent('<?php echo esc_js($cdek_info['address']); ?>');
                const url = `https://yandex.ru/maps/?text=${address}`;
                window.open(url, '_blank');
                <?php endif; ?>
            }
            </script>
            <?php
        }
    }
    
    /**
     * Отправка в external системы
     */
    private function send_to_external_systems($order_id, $formatted_info, $shipping_cost) {
        // Отправка в CRM
        $this->send_to_crm($order_id, $formatted_info, $shipping_cost);
        
        // Отправка в учетную систему
        $this->send_to_accounting_system($order_id, $formatted_info, $shipping_cost);
        
        // Отправка в аналитику
        $this->send_to_analytics($order_id, $formatted_info, $shipping_cost);
    }
    
    /**
     * Отправка в CRM
     */
    private function send_to_crm($order_id, $formatted_info, $shipping_cost) {
        $crm_webhook_url = get_option('cdek_crm_webhook_url', '');
        
        if (!empty($crm_webhook_url)) {
            $order = wc_get_order($order_id);
            
            $payload = array(
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'customer_name' => $order->get_formatted_billing_full_name(),
                'order_total' => $order->get_total(),
                'delivery_info' => $formatted_info,
                'shipping_cost' => $shipping_cost,
                'timestamp' => current_time('c')
            );
            
            wp_remote_post($crm_webhook_url, array(
                'body' => json_encode($payload),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'WooCommerce-CDEK-Plugin'
                ),
                'timeout' => 30
            ));
        }
    }
    
    /**
     * Отправка в учетную систему
     */
    private function send_to_accounting_system($order_id, $formatted_info, $shipping_cost) {
        // Интеграция с популярными учетными системами
        
        // 1С:Предприятие
        $this->send_to_1c($order_id, $formatted_info, $shipping_cost);
        
        // МойСклад
        $this->send_to_moysklad($order_id, $formatted_info, $shipping_cost);
    }
    
    /**
     * Отправка в 1С
     */
    private function send_to_1c($order_id, $formatted_info, $shipping_cost) {
        $one_c_endpoint = get_option('cdek_1c_endpoint', '');
        $one_c_login = get_option('cdek_1c_login', '');
        $one_c_password = get_option('cdek_1c_password', '');
        
        if (!empty($one_c_endpoint) && !empty($one_c_login)) {
            $order = wc_get_order($order_id);
            
            $xml_data = $this->generate_1c_xml($order, $formatted_info, $shipping_cost);
            
            wp_remote_post($one_c_endpoint, array(
                'body' => $xml_data,
                'headers' => array(
                    'Content-Type' => 'application/xml',
                    'Authorization' => 'Basic ' . base64_encode($one_c_login . ':' . $one_c_password)
                ),
                'timeout' => 60
            ));
        }
    }
    
    /**
     * Генерация XML для 1С
     */
    private function generate_1c_xml($order, $formatted_info, $shipping_cost) {
        ob_start();
        ?>
        <?xml version="1.0" encoding="UTF-8"?>
        <Документ>
            <ЗаказКлиента>
                <Номер><?php echo esc_xml($order->get_order_number()); ?></Номер>
                <Дата><?php echo esc_xml($order->get_date_created()->format('Y-m-d\TH:i:s')); ?></Дата>
                <Клиент>
                    <Наименование><?php echo esc_xml($order->get_formatted_billing_full_name()); ?></Наименование>
                    <Email><?php echo esc_xml($order->get_billing_email()); ?></Email>
                    <Телефон><?php echo esc_xml($order->get_billing_phone()); ?></Телефон>
                </Клиент>
                <СуммаДокумента><?php echo esc_xml($order->get_total()); ?></СуммаДокумента>
                <Доставка>
                    <Способ>СДЭК - Пункт выдачи</Способ>
                    <ПунктВыдачи><?php echo esc_xml($formatted_info['point_name']); ?></ПунктВыдачи>
                    <КодПВЗ><?php echo esc_xml($formatted_info['point_code']); ?></КодПВЗ>
                    <Адрес><?php echo esc_xml($formatted_info['address']); ?></Адрес>
                    <Стоимость><?php echo esc_xml($shipping_cost); ?></Стоимость>
                </Доставка>
                <СоставЗаказа>
                    <?php foreach ($order->get_items() as $item): ?>
                    <ПозицияЗаказа>
                        <Наименование><?php echo esc_xml($item->get_name()); ?></Наименование>
                        <Количество><?php echo esc_xml($item->get_quantity()); ?></Количество>
                        <Цена><?php echo esc_xml($item->get_total() / $item->get_quantity()); ?></Цена>
                        <Сумма><?php echo esc_xml($item->get_total()); ?></Сумма>
                    </ПозицияЗаказа>
                    <?php endforeach; ?>
                </СоставЗаказа>
            </ЗаказКлиента>
        </Документ>
        <?php
        return ob_get_clean();
    }
    
    /**
     * REST API endpoints
     */
    public function register_cdek_api_endpoints() {
        register_rest_route('cdek/v1', '/orders/(?P<id>\d+)/delivery', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_delivery_info'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));
        
        register_rest_route('cdek/v1', '/orders/(?P<id>\d+)/tracking', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_tracking_info'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));
    }
    
    /**
     * Получение информации о доставке через API
     */
    public function get_order_delivery_info($request) {
        $order_id = $request['id'];
        $cdek_info = get_post_meta($order_id, '_cdek_formatted_info', true);
        $shipping_cost = get_post_meta($order_id, '_cdek_shipping_cost', true);
        
        if ($cdek_info) {
            return new WP_REST_Response(array(
                'order_id' => $order_id,
                'delivery_info' => $cdek_info,
                'shipping_cost' => $shipping_cost,
                'status' => 'success'
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'message' => 'CDEK delivery info not found',
                'status' => 'error'
            ), 404);
        }
    }
    
    /**
     * Проверка прав API
     */
    public function check_api_permissions() {
        return current_user_can('manage_woocommerce');
    }
    
    /**
     * Инициализация интеграций с третьими плагинами
     */
    public function init_third_party_integrations() {
        // WooCommerce PDF Invoices & Packing Slips
        if (class_exists('WPO_WCPDF')) {
            add_action('wpo_wcpdf_after_order_details', array($this, 'add_cdek_to_pdf'), 10, 2);
        }
        
        // WooCommerce Email Customizer
        if (class_exists('WC_Email_Customizer')) {
            add_filter('wc_email_customizer_template_variables', array($this, 'add_cdek_email_variables'));
        }
        
        // Yoast SEO (structured data)
        if (class_exists('WPSEO_Frontend')) {
            add_filter('wpseo_schema_graph_pieces', array($this, 'add_cdek_structured_data'), 10, 2);
        }
    }
    
    /**
     * Добавление в PDF документы
     */
    public function add_cdek_to_pdf($template_type, $order) {
        $cdek_info = get_post_meta($order->get_id(), '_cdek_formatted_info', true);
        
        if ($cdek_info) {
            echo '<div class="cdek-pdf-info">';
            echo '<h3>Доставка СДЭК</h3>';
            echo '<p><strong>Пункт выдачи:</strong> ' . esc_html($cdek_info['point_name']) . '</p>';
            echo '<p><strong>Адрес:</strong> ' . esc_html($cdek_info['address']) . '</p>';
            echo '</div>';
        }
    }
}

// Инициализация класса
new CdekOrderSender();