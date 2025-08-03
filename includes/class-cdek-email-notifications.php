<?php
/**
 * Класс для управления email уведомлениями СДЭК доставки
 * 
 * @package CdekDelivery
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CdekEmailNotifications {
    
    /**
     * Конструктор класса
     */
    public function __construct() {
        // Хуки для отправки email уведомлений
        add_action('woocommerce_checkout_order_processed', array($this, 'send_delivery_notifications'), 20, 3);
        add_action('woocommerce_order_status_changed', array($this, 'send_status_change_notifications'), 10, 4);
        
        // Добавляем настройки email в админку
        add_action('admin_init', array($this, 'register_email_settings'));
    }
    
    /**
     * Отправка уведомлений о выбранном способе доставки при создании заказа
     */
    public function send_delivery_notifications($order_id, $posted_data, $order) {
        if (!$order || !is_object($order)) {
            return;
        }
        
        // Проверяем, включены ли email уведомления
        if (!get_option('cdek_email_notifications_enabled', 1)) {
            return;
        }
        
        // Получаем данные о доставке
        $delivery_type = get_post_meta($order_id, '_cdek_delivery_type', true);
        $point_code = get_post_meta($order_id, '_cdek_point_code', true);
        $point_data = get_post_meta($order_id, '_cdek_point_data', true);
        
        // Проверяем, что это заказ с доставкой СДЭК
        $shipping_methods = $order->get_shipping_methods();
        $is_cdek_order = false;
        
        foreach ($shipping_methods as $item_id => $item) {
            if (strpos($item->get_method_id(), 'cdek_delivery') !== false) {
                $is_cdek_order = true;
                break;
            }
        }
        
        if (!$is_cdek_order) {
            return;
        }
        
        // Определяем тип доставки для отправки соответствующего уведомления
        switch ($delivery_type) {
            case 'pickup':
                $this->send_pickup_notification($order, $order_id);
                break;
                
            case 'manager':
                $this->send_manager_notification($order, $order_id);
                break;
                
            case 'cdek':
            default:
                if ($point_code && $point_data) {
                    $this->send_cdek_delivery_notification($order, $order_id, $point_data);
                }
                break;
        }
        
        error_log('СДЭК Email: Отправлены уведомления для заказа #' . $order_id . ' (тип: ' . $delivery_type . ')');
    }
    
    /**
     * Отправка уведомления о самовывозе
     */
    private function send_pickup_notification($order, $order_id) {
        $customer_email = $order->get_billing_email();
        $admin_email = get_option('cdek_admin_notification_email', get_option('admin_email'));
        $site_name = get_option('cdek_email_from_name', get_bloginfo('name'));
        
        // Данные для шаблона
        $template_data = array(
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_email' => $customer_email,
            'order_total' => $order->get_formatted_order_total(),
            'pickup_address' => 'г.Саратов, ул. Осипова, д. 18а',
            'site_name' => $site_name,
            'order_date' => $order->get_date_created()->format('d.m.Y H:i')
        );
        
        // Отправляем клиенту
        $this->send_pickup_email_to_customer($customer_email, $template_data);
        
        // Отправляем администратору
        $this->send_pickup_email_to_admin($admin_email, $template_data);
    }
    
    /**
     * Отправка уведомления об обсуждении с менеджером
     */
    private function send_manager_notification($order, $order_id) {
        $customer_email = $order->get_billing_email();
        $admin_email = get_option('cdek_admin_notification_email', get_option('admin_email'));
        $site_name = get_option('cdek_email_from_name', get_bloginfo('name'));
        
        // Данные для шаблона
        $template_data = array(
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_email' => $customer_email,
            'order_total' => $order->get_formatted_order_total(),
            'site_name' => $site_name,
            'order_date' => $order->get_date_created()->format('d.m.Y H:i'),
            'delivery_address' => $order->get_shipping_city() . ', ' . $order->get_shipping_address_1()
        );
        
        // Отправляем клиенту
        $this->send_manager_email_to_customer($customer_email, $template_data);
        
        // Отправляем администратору
        $this->send_manager_email_to_admin($admin_email, $template_data);
    }
    
    /**
     * Отправка уведомления о доставке СДЭК
     */
    private function send_cdek_delivery_notification($order, $order_id, $point_data) {
        $customer_email = $order->get_billing_email();
        $admin_email = get_option('cdek_admin_notification_email', get_option('admin_email'));
        $site_name = get_option('cdek_email_from_name', get_bloginfo('name'));
        
        // Форматируем информацию о пункте выдачи
        $point_info = $this->format_point_info($point_data);
        
        // Данные для шаблона
        $template_data = array(
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_email' => $customer_email,
            'order_total' => $order->get_formatted_order_total(),
            'site_name' => $site_name,
            'order_date' => $order->get_date_created()->format('d.m.Y H:i'),
            'point_name' => $point_data['name'] ?? 'Пункт выдачи СДЭК',
            'point_code' => get_post_meta($order_id, '_cdek_point_code', true),
            'point_address' => $point_data['location']['address_full'] ?? '',
            'point_info' => $point_info
        );
        
        // Отправляем клиенту
        $this->send_cdek_email_to_customer($customer_email, $template_data);
        
        // Отправляем администратору
        $this->send_cdek_email_to_admin($admin_email, $template_data);
    }
    
    /**
     * Отправка email клиенту о самовывозе
     */
    private function send_pickup_email_to_customer($email, $data) {
        $subject = sprintf('[%s] Заказ #%s - Самовывоз', $data['site_name'], $data['order_number']);
        
        $message = $this->get_pickup_customer_template($data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $data['site_name'] . ' <' . get_option('admin_email') . '>'
        );
        
        wp_mail($email, $subject, $message, $headers);
        
        error_log('СДЭК Email: Отправлено уведомление о самовывозе клиенту ' . $email);
    }
    
    /**
     * Отправка email администратору о самовывозе
     */
    private function send_pickup_email_to_admin($email, $data) {
        $subject = sprintf('[%s] Новый заказ #%s - Самовывоз', $data['site_name'], $data['order_number']);
        
        $message = $this->get_pickup_admin_template($data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $data['site_name'] . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );
        
        wp_mail($email, $subject, $message, $headers);
        
        error_log('СДЭК Email: Отправлено уведомление о самовывозе администратору ' . $email);
    }
    
    /**
     * Отправка email клиенту об обсуждении с менеджером
     */
    private function send_manager_email_to_customer($email, $data) {
        $subject = sprintf('[%s] Заказ #%s - Обсуждение доставки с менеджером', $data['site_name'], $data['order_number']);
        
        $message = $this->get_manager_customer_template($data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $data['site_name'] . ' <' . get_option('admin_email') . '>'
        );
        
        wp_mail($email, $subject, $message, $headers);
        
        error_log('СДЭК Email: Отправлено уведомление об обсуждении с менеджером клиенту ' . $email);
    }
    
    /**
     * Отправка email администратору об обсуждении с менеджером
     */
    private function send_manager_email_to_admin($email, $data) {
        $subject = sprintf('[%s] Новый заказ #%s - Требуется обсуждение доставки', $data['site_name'], $data['order_number']);
        
        $message = $this->get_manager_admin_template($data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $data['site_name'] . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );
        
        wp_mail($email, $subject, $message, $headers);
        
        error_log('СДЭК Email: Отправлено уведомление об обсуждении с менеджером администратору ' . $email);
    }
    
    /**
     * Отправка email клиенту о доставке СДЭК
     */
    private function send_cdek_email_to_customer($email, $data) {
        $subject = sprintf('[%s] Заказ #%s - Доставка СДЭК', $data['site_name'], $data['order_number']);
        
        $message = $this->get_cdek_customer_template($data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $data['site_name'] . ' <' . get_option('admin_email') . '>'
        );
        
        wp_mail($email, $subject, $message, $headers);
        
        error_log('СДЭК Email: Отправлено уведомление о доставке СДЭК клиенту ' . $email);
    }
    
    /**
     * Отправка email администратору о доставке СДЭК
     */
    private function send_cdek_email_to_admin($email, $data) {
        $subject = sprintf('[%s] Новый заказ #%s - Доставка СДЭК', $data['site_name'], $data['order_number']);
        
        $message = $this->get_cdek_admin_template($data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $data['site_name'] . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );
        
        wp_mail($email, $subject, $message, $headers);
        
        error_log('СДЭК Email: Отправлено уведомление о доставке СДЭК администратору ' . $email);
    }
    
    /**
     * Шаблон email для клиента - самовывоз
     */
    private function get_pickup_customer_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Заказ #<?php echo $data['order_number']; ?> - Самовывоз</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .pickup-info { background: #d4edda; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .highlight { background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📍 Заказ оформлен на самовывоз</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <p>Здравствуйте, <strong><?php echo $data['customer_name']; ?></strong>!</p>
                    
                    <p>Ваш заказ #<?php echo $data['order_number']; ?> успешно оформлен на <strong>самовывоз</strong>.</p>
                    
                    <div class="pickup-info">
                        <h3>📍 Адрес для самовывоза:</h3>
                        <p><strong><?php echo $data['pickup_address']; ?></strong></p>
                        <p><strong>Стоимость:</strong> Бесплатно</p>
                    </div>
                    
                    <div class="highlight">
                        <p><strong>⏰ Важно:</strong> Мы свяжемся с вами в ближайшее время для уточнения удобного времени получения заказа.</p>
                    </div>
                    
                    <h3>📋 Детали заказа:</h3>
                    <ul>
                        <li><strong>Номер заказа:</strong> #<?php echo $data['order_number']; ?></li>
                        <li><strong>Дата заказа:</strong> <?php echo $data['order_date']; ?></li>
                        <li><strong>Общая сумма:</strong> <?php echo $data['order_total']; ?></li>
                        <li><strong>Телефон:</strong> <?php echo $data['customer_phone']; ?></li>
                    </ul>
                    
                    <p>Если у вас есть вопросы, свяжитесь с нами по телефону или email.</p>
                    
                    <p>Спасибо за ваш заказ!</p>
                </div>
                
                <div class="footer">
                    <p><?php echo $data['site_name']; ?><br>
                    Это автоматическое уведомление, не отвечайте на это письмо.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Шаблон email для администратора - самовывоз
     */
    private function get_pickup_admin_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Новый заказ #<?php echo $data['order_number']; ?> - Самовывоз</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .customer-info { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .pickup-info { background: #d4edda; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .action-required { background: #fff3cd; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #ffc107; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📍 Новый заказ на самовывоз</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <div class="action-required">
                        <h3>⚠️ Требуется действие</h3>
                        <p><strong>Необходимо связаться с клиентом для уточнения времени самовывоза!</strong></p>
                    </div>
                    
                    <div class="customer-info">
                        <h3>👤 Информация о клиенте:</h3>
                        <ul>
                            <li><strong>Имя:</strong> <?php echo $data['customer_name']; ?></li>
                            <li><strong>Телефон:</strong> <?php echo $data['customer_phone']; ?></li>
                            <li><strong>Email:</strong> <?php echo $data['customer_email']; ?></li>
                        </ul>
                    </div>
                    
                    <div class="pickup-info">
                        <h3>📍 Самовывоз:</h3>
                        <p><strong>Адрес:</strong> <?php echo $data['pickup_address']; ?></p>
                        <p><strong>Стоимость доставки:</strong> Бесплатно</p>
                    </div>
                    
                    <h3>📋 Детали заказа:</h3>
                    <ul>
                        <li><strong>Номер заказа:</strong> #<?php echo $data['order_number']; ?></li>
                        <li><strong>Дата заказа:</strong> <?php echo $data['order_date']; ?></li>
                        <li><strong>Общая сумма:</strong> <?php echo $data['order_total']; ?></li>
                    </ul>
                    
                    <p><strong>Следующие шаги:</strong></p>
                    <ol>
                        <li>Связаться с клиентом по телефону <?php echo $data['customer_phone']; ?></li>
                        <li>Уточнить удобное время для самовывоза</li>
                        <li>Подготовить заказ к выдаче</li>
                        <li>Уведомить клиента о готовности заказа</li>
                    </ol>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Шаблон email для клиента - обсуждение с менеджером
     */
    private function get_manager_customer_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Заказ #<?php echo $data['order_number']; ?> - Обсуждение доставки</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #17a2b8; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .manager-info { background: #d1ecf1; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .highlight { background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📞 Обсуждение доставки с менеджером</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <p>Здравствуйте, <strong><?php echo $data['customer_name']; ?></strong>!</p>
                    
                    <p>Ваш заказ #<?php echo $data['order_number']; ?> успешно оформлен. Вы выбрали <strong>обсуждение доставки с менеджером</strong>.</p>
                    
                    <div class="manager-info">
                        <h3>📞 Что происходит дальше:</h3>
                        <p><strong>Наш менеджер свяжется с вами в ближайшее время</strong> для обсуждения:</p>
                        <ul>
                            <li>Удобного способа доставки</li>
                            <li>Времени и места доставки</li>
                            <li>Стоимости доставки (если применимо)</li>
                            <li>Других деталей по вашему заказу</li>
                        </ul>
                        <p><strong>Стоимость консультации:</strong> Бесплатно</p>
                    </div>
                    
                    <div class="highlight">
                        <p><strong>📱 Убедитесь, что ваш телефон доступен:</strong> <?php echo $data['customer_phone']; ?></p>
                    </div>
                    
                    <h3>📋 Детали заказа:</h3>
                    <ul>
                        <li><strong>Номер заказа:</strong> #<?php echo $data['order_number']; ?></li>
                        <li><strong>Дата заказа:</strong> <?php echo $data['order_date']; ?></li>
                        <li><strong>Общая сумма:</strong> <?php echo $data['order_total']; ?></li>
                        <li><strong>Адрес доставки:</strong> <?php echo $data['delivery_address']; ?></li>
                        <li><strong>Телефон:</strong> <?php echo $data['customer_phone']; ?></li>
                    </ul>
                    
                    <p>Если у вас есть срочные вопросы, свяжитесь с нами по телефону или email.</p>
                    
                    <p>Спасибо за ваш заказ!</p>
                </div>
                
                <div class="footer">
                    <p><?php echo $data['site_name']; ?><br>
                    Это автоматическое уведомление, не отвечайте на это письмо.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Шаблон email для администратора - обсуждение с менеджером
     */
    private function get_manager_admin_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Новый заказ #<?php echo $data['order_number']; ?> - Требуется обсуждение доставки</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #17a2b8; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .customer-info { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .action-required { background: #fff3cd; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #ffc107; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📞 Требуется обсуждение доставки</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <div class="action-required">
                        <h3>⚠️ Требуется действие менеджера</h3>
                        <p><strong>Клиент выбрал обсуждение доставки с менеджером!</strong></p>
                        <p>Необходимо связаться с клиентом для обсуждения деталей доставки.</p>
                    </div>
                    
                    <div class="customer-info">
                        <h3>👤 Информация о клиенте:</h3>
                        <ul>
                            <li><strong>Имя:</strong> <?php echo $data['customer_name']; ?></li>
                            <li><strong>Телефон:</strong> <?php echo $data['customer_phone']; ?></li>
                            <li><strong>Email:</strong> <?php echo $data['customer_email']; ?></li>
                            <li><strong>Адрес доставки:</strong> <?php echo $data['delivery_address']; ?></li>
                        </ul>
                    </div>
                    
                    <h3>📋 Детали заказа:</h3>
                    <ul>
                        <li><strong>Номер заказа:</strong> #<?php echo $data['order_number']; ?></li>
                        <li><strong>Дата заказа:</strong> <?php echo $data['order_date']; ?></li>
                        <li><strong>Общая сумма:</strong> <?php echo $data['order_total']; ?></li>
                    </ul>
                    
                    <h3>📞 Что нужно обсудить с клиентом:</h3>
                    <ol>
                        <li>Предпочтительный способ доставки</li>
                        <li>Удобное время доставки</li>
                        <li>Точный адрес доставки</li>
                        <li>Стоимость доставки</li>
                        <li>Особые требования к доставке</li>
                    </ol>
                    
                    <p><strong>Следующие шаги:</strong></p>
                    <ol>
                        <li>Связаться с клиентом по телефону <?php echo $data['customer_phone']; ?></li>
                        <li>Обсудить все детали доставки</li>
                        <li>Зафиксировать договоренности в комментариях к заказу</li>
                        <li>При необходимости скорректировать стоимость заказа</li>
                    </ol>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Шаблон email для клиента - доставка СДЭК
     */
    private function get_cdek_customer_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Заказ #<?php echo $data['order_number']; ?> - Доставка СДЭК</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .cdek-info { background: #e3f2fd; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .highlight { background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚚 Доставка СДЭК</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <p>Здравствуйте, <strong><?php echo $data['customer_name']; ?></strong>!</p>
                    
                    <p>Ваш заказ #<?php echo $data['order_number']; ?> успешно оформлен с доставкой через <strong>СДЭК</strong>.</p>
                    
                    <div class="cdek-info">
                        <h3>🚚 Информация о доставке:</h3>
                        <p><strong>Пункт выдачи:</strong> <?php echo $data['point_name']; ?></p>
                        <p><strong>Код пункта:</strong> <?php echo $data['point_code']; ?></p>
                        <p><strong>Адрес:</strong> <?php echo $data['point_address']; ?></p>
                        <?php echo $data['point_info']; ?>
                    </div>
                    
                    <div class="highlight">
                        <p><strong>📱 SMS-уведомление:</strong> Вы получите SMS-сообщение, когда заказ прибудет в пункт выдачи.</p>
                        <p><strong>🆔 Для получения заказа потребуется:</strong> Документ, удостоверяющий личность, и номер заказа.</p>
                    </div>
                    
                    <h3>📋 Детали заказа:</h3>
                    <ul>
                        <li><strong>Номер заказа:</strong> #<?php echo $data['order_number']; ?></li>
                        <li><strong>Дата заказа:</strong> <?php echo $data['order_date']; ?></li>
                        <li><strong>Общая сумма:</strong> <?php echo $data['order_total']; ?></li>
                        <li><strong>Телефон:</strong> <?php echo $data['customer_phone']; ?></li>
                    </ul>
                    
                    <p><strong>Примерные сроки доставки:</strong> 2-5 рабочих дней</p>
                    
                    <p>Отследить статус заказа можно на сайте СДЭК по коду отправления (будет предоставлен после передачи заказа в СДЭК).</p>
                    
                    <p>Если у вас есть вопросы, свяжитесь с нами по телефону или email.</p>
                    
                    <p>Спасибо за ваш заказ!</p>
                </div>
                
                <div class="footer">
                    <p><?php echo $data['site_name']; ?><br>
                    Это автоматическое уведомление, не отвечайте на это письмо.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Шаблон email для администратора - доставка СДЭК
     */
    private function get_cdek_admin_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Новый заказ #<?php echo $data['order_number']; ?> - Доставка СДЭК</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .customer-info { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .cdek-info { background: #e3f2fd; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .action-required { background: #d4edda; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #28a745; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚚 Новый заказ СДЭК</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <div class="action-required">
                        <h3>✅ Заказ готов к обработке</h3>
                        <p>Заказ оформлен с доставкой СДЭК. Пункт выдачи выбран клиентом.</p>
                    </div>
                    
                    <div class="customer-info">
                        <h3>👤 Информация о клиенте:</h3>
                        <ul>
                            <li><strong>Имя:</strong> <?php echo $data['customer_name']; ?></li>
                            <li><strong>Телефон:</strong> <?php echo $data['customer_phone']; ?></li>
                            <li><strong>Email:</strong> <?php echo $data['customer_email']; ?></li>
                        </ul>
                    </div>
                    
                    <div class="cdek-info">
                        <h3>🚚 Информация о доставке СДЭК:</h3>
                        <p><strong>Пункт выдачи:</strong> <?php echo $data['point_name']; ?></p>
                        <p><strong>Код пункта:</strong> <?php echo $data['point_code']; ?></p>
                        <p><strong>Адрес пункта:</strong> <?php echo $data['point_address']; ?></p>
                        <?php echo $data['point_info']; ?>
                    </div>
                    
                    <h3>📋 Детали заказа:</h3>
                    <ul>
                        <li><strong>Номер заказа:</strong> #<?php echo $data['order_number']; ?></li>
                        <li><strong>Дата заказа:</strong> <?php echo $data['order_date']; ?></li>
                        <li><strong>Общая сумма:</strong> <?php echo $data['order_total']; ?></li>
                    </ul>
                    
                    <p><strong>Следующие шаги:</strong></p>
                    <ol>
                        <li>Подготовить заказ к отправке</li>
                        <li>Создать заказ в системе СДЭК (автоматически при смене статуса на "Обработка")</li>
                        <li>Передать посылку в СДЭК</li>
                        <li>Отследить доставку до пункта выдачи</li>
                    </ol>
                    
                    <p><strong>Примерные сроки доставки:</strong> 2-5 рабочих дней</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Форматирование информации о пункте выдачи
     */
    private function format_point_info($point_data) {
        $info = '';
        
        // Режим работы
        if (isset($point_data['work_time_list']) && is_array($point_data['work_time_list'])) {
            $info .= '<p><strong>Режим работы:</strong><br>';
            $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            foreach ($point_data['work_time_list'] as $work_time) {
                if (isset($work_time['day']) && isset($work_time['time'])) {
                    $day_index = intval($work_time['day']) - 1;
                    if ($day_index >= 0 && $day_index < 7) {
                        $info .= $days[$day_index] . ': ' . esc_html($work_time['time']) . '<br>';
                    }
                }
            }
            $info .= '</p>';
        }
        
        // Телефоны
        if (isset($point_data['phones']) && is_array($point_data['phones']) && !empty($point_data['phones'])) {
            $phone_numbers = array();
            foreach ($point_data['phones'] as $phone) {
                if (is_array($phone) && isset($phone['number'])) {
                    $phone_numbers[] = $phone['number'];
                } else {
                    $phone_numbers[] = $phone;
                }
            }
            if (!empty($phone_numbers)) {
                $info .= '<p><strong>Телефон:</strong> ' . esc_html(implode(', ', $phone_numbers)) . '</p>';
            }
        }
        
        return $info;
    }
    
    /**
     * Отправка уведомлений при изменении статуса заказа
     */
    public function send_status_change_notifications($order_id, $old_status, $new_status, $order) {
        // Проверяем, что это заказ с доставкой СДЭК
        $delivery_type = get_post_meta($order_id, '_cdek_delivery_type', true);
        
        if (!$delivery_type) {
            return;
        }
        
        // Отправляем уведомления при определенных изменениях статуса
        if ($new_status === 'processing') {
            $this->send_processing_notification($order, $order_id, $delivery_type);
        } elseif ($new_status === 'completed') {
            $this->send_completed_notification($order, $order_id, $delivery_type);
        }
    }
    
    /**
     * Уведомление о переводе заказа в обработку
     */
    private function send_processing_notification($order, $order_id, $delivery_type) {
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Заказ #%s принят в обработку', $site_name, $order->get_order_number());
        
        $message = $this->get_processing_notification_template($order, $delivery_type);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
        );
        
        wp_mail($customer_email, $subject, $message, $headers);
        
        error_log('СДЭК Email: Отправлено уведомление о принятии в обработку для заказа #' . $order_id);
    }
    
    /**
     * Шаблон уведомления о принятии заказа в обработку
     */
    private function get_processing_notification_template($order, $delivery_type) {
        $delivery_text = '';
        
        switch ($delivery_type) {
            case 'pickup':
                $delivery_text = 'Заказ будет готов к самовывозу. Мы уведомим вас, когда можно будет забрать заказ.';
                break;
            case 'manager':
                $delivery_text = 'Наш менеджер свяжется с вами для уточнения деталей доставки.';
                break;
            case 'cdek':
            default:
                $delivery_text = 'Заказ будет передан в СДЭК для доставки в выбранный пункт выдачи.';
                break;
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Заказ принят в обработку</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .status-info { background: #d4edda; padding: 15px; border-radius: 6px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✅ Заказ принят в обработку</h1>
                    <p>Заказ #<?php echo $order->get_order_number(); ?></p>
                </div>
                
                <div class="content">
                    <p>Здравствуйте, <strong><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></strong>!</p>
                    
                    <div class="status-info">
                        <h3>🔄 Статус заказа изменен</h3>
                        <p>Ваш заказ #<?php echo $order->get_order_number(); ?> принят в обработку.</p>
                        <p><?php echo $delivery_text; ?></p>
                    </div>
                    
                    <p>Спасибо за ваш заказ!</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Уведомление о завершении заказа
     */
    private function send_completed_notification($order, $order_id, $delivery_type) {
        $customer_email = $order->get_billing_email();
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Заказ #%s выполнен', $site_name, $order->get_order_number());
        
        $message = $this->get_completed_notification_template($order, $delivery_type);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
        );
        
        wp_mail($customer_email, $subject, $message, $headers);
        
        error_log('СДЭК Email: Отправлено уведомление о завершении заказа #' . $order_id);
    }
    
    /**
     * Шаблон уведомления о завершении заказа
     */
    private function get_completed_notification_template($order, $delivery_type) {
        $delivery_text = '';
        
        switch ($delivery_type) {
            case 'pickup':
                $delivery_text = 'Заказ получен при самовывозе.';
                break;
            case 'manager':
                $delivery_text = 'Заказ доставлен согласно договоренности с менеджером.';
                break;
            case 'cdek':
            default:
                $delivery_text = 'Заказ получен в пункте выдачи СДЭК.';
                break;
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Заказ выполнен</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .status-info { background: #d4edda; padding: 15px; border-radius: 6px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Заказ выполнен</h1>
                    <p>Заказ #<?php echo $order->get_order_number(); ?></p>
                </div>
                
                <div class="content">
                    <p>Здравствуйте, <strong><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></strong>!</p>
                    
                    <div class="status-info">
                        <h3>✅ Заказ завершен</h3>
                        <p>Ваш заказ #<?php echo $order->get_order_number(); ?> успешно выполнен.</p>
                        <p><?php echo $delivery_text; ?></p>
                    </div>
                    
                    <p>Благодарим вас за покупку! Будем рады видеть вас снова.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Регистрация настроек email уведомлений
     */
    public function register_email_settings() {
        // Настройки будут добавлены в admin-page.php
        register_setting('cdek_delivery_settings', 'cdek_email_notifications_enabled');
        register_setting('cdek_delivery_settings', 'cdek_admin_notification_email');
        register_setting('cdek_delivery_settings', 'cdek_email_from_name');
    }
}