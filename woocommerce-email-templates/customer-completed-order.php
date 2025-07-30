<?php
/**
 * Customer completed order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-completed-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p><?php esc_html_e( 'Your order has been marked complete. Your order details are shown below for your reference:', 'woocommerce' ); ?></p>

<?php
// СДЭК: Проверяем выбор "Обсудить доставку с менеджером"
$discuss_delivery = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);

if ($discuss_delivery == 'Да') {
    ?>
    <!-- СДЭК: Блок информации об обсуждении доставки для клиента -->
    <div style="background: #e3f2fd; border: 2px solid #1976d2; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
        <h2 style="color: #1976d2; margin-top: 0; border-bottom: 2px solid #1976d2; padding-bottom: 10px; text-align: center;">
            🗣️ Обсуждение условий доставки
        </h2>
        <div style="background: #bbdefb; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
            <p style="margin: 0; color: #0d47a1; font-size: 16px; font-weight: bold;">
                📞 Наш менеджер свяжется с вами для финального обсуждения доставки
            </p>
        </div>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tr>
                <td style="padding: 12px; border: 1px solid #64b5f6; background: #e1f5fe; color: #0d47a1; font-weight: bold; width: 40%;">
                    📋 Статус:
                </td>
                <td style="padding: 12px; border: 1px solid #64b5f6; background: #ffffff; color: #1565c0;">
                    Заказ выполнен, готов к доставке
                </td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #64b5f6; background: #e1f5fe; color: #0d47a1; font-weight: bold;">
                    🕐 Ожидаемый звонок:
                </td>
                <td style="padding: 12px; border: 1px solid #64b5f6; background: #ffffff; color: #1565c0;">
                    В ближайшее рабочее время
                </td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #64b5f6; background: #e1f5fe; color: #0d47a1; font-weight: bold;">
                    📞 Телефон для связи:
                </td>
                <td style="padding: 12px; border: 1px solid #64b5f6; background: #ffffff; color: #1565c0; font-weight: bold;">
                    <?php echo esc_html($order->get_billing_phone()); ?>
                </td>
            </tr>
        </table>
        <div style="margin-top: 20px; padding: 15px; background: #c8e6c9; border: 1px solid #a5d6a7; border-radius: 6px;">
            <h3 style="margin: 0 0 10px 0; color: #2e7d32; font-size: 16px;">📱 Убедитесь, что ваш телефон доступен</h3>
            <p style="margin: 0; color: #2e7d32; line-height: 1.5;">
                Наш менеджер согласует с вами финальные детали доставки: время, место и способ получения заказа.
                Если номер телефона изменился, пожалуйста, сообщите нам по email или через поддержку на сайте.
            </p>
        </div>
    </div>
    <!-- Конец блока обсуждения доставки -->
    <?php
} else {
    // Показываем стандартный блок СДЭК только если не выбрано обсуждение доставки
    
    // СДЭК: Добавляем информацию о доставке для покупателя
    $cdek_point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
    $cdek_point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
    $cdek_delivery_cost = get_post_meta($order->get_id(), '_cdek_delivery_cost', true);

    if ($cdek_point_code && $cdek_point_data) {
        // Декодируем данные пункта выдачи
        if (is_string($cdek_point_data)) {
            $cdek_point_data = json_decode($cdek_point_data, true);
        }
        
        // Получаем данные пункта
        $point_name = '';
        $point_address = '';
        $point_phone = '';
        $point_work_time = '';
        
        if (isset($cdek_point_data['name'])) {
            $point_name = $cdek_point_data['name'];
        }
        
        if (isset($cdek_point_data['location'])) {
            $location = $cdek_point_data['location'];
            $address_parts = array();
            
            if (isset($location['postal_code'])) {
                $address_parts[] = $location['postal_code'];
            }
            if (isset($location['country'])) {
                $address_parts[] = $location['country'];
            }
            if (isset($location['region'])) {
                $address_parts[] = $location['region'];
            }
            if (isset($location['city'])) {
                $address_parts[] = $location['city'];
            }
            if (isset($location['address'])) {
                $address_parts[] = $location['address'];
            }
            
            $point_address = implode(', ', $address_parts);
        }
        
        if (isset($cdek_point_data['phones']) && is_array($cdek_point_data['phones']) && !empty($cdek_point_data['phones'])) {
            $phone_obj = $cdek_point_data['phones'][0];
            if (isset($phone_obj['number'])) {
                $point_phone = $phone_obj['number'];
            }
        }
        
        if (isset($cdek_point_data['work_time']) && !empty($cdek_point_data['work_time'])) {
            $point_work_time = $cdek_point_data['work_time'];
        }
        
        // Форматируем стоимость
        $formatted_cost = '';
        if ($cdek_delivery_cost) {
            $formatted_cost = number_format((float)$cdek_delivery_cost, 0, '.', ' ') . ' руб.';
        }
        
        ?>
        <!-- СДЭК: Блок информации о доставке для покупателя -->
        <div style="background: #f0f8ff; border: 2px solid #007cba; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
            <h2 style="color: #007cba; margin-top: 0; border-bottom: 2px solid #007cba; padding-bottom: 10px; text-align: center;">
                📦 Ваш заказ готов к получению в СДЭК!
            </h2>
            <div style="background: #e7f3ff; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
                <p style="margin: 0; color: #0056b3; font-size: 16px; font-weight: bold;">
                    🎉 Поздравляем! Ваш заказ прибыл в пункт выдачи СДЭК
                </p>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <tr>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #cce7ff; color: #004085; font-weight: bold; width: 30%;">
                        🏪 Пункт выдачи:
                    </td>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #ffffff; color: #004085;">
                        <?php echo esc_html($point_name); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #cce7ff; color: #004085; font-weight: bold;">
                        📍 Адрес:
                    </td>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #ffffff; color: #004085;">
                        <?php echo esc_html($point_address); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #cce7ff; color: #004085; font-weight: bold;">
                        🔢 Код пункта:
                    </td>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #ffffff; color: #004085; font-weight: bold; font-size: 16px;">
                        <?php echo esc_html($cdek_point_code); ?>
                    </td>
                </tr>
                <?php if ($point_phone): ?>
                <tr>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #cce7ff; color: #004085; font-weight: bold;">
                        📞 Телефон:
                    </td>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #ffffff; color: #004085;">
                        <?php echo esc_html($point_phone); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($point_work_time): ?>
                <tr>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #cce7ff; color: #004085; font-weight: bold;">
                        🕐 Режим работы:
                    </td>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #ffffff; color: #004085;">
                        <?php echo esc_html($point_work_time); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($formatted_cost): ?>
                <tr>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #cce7ff; color: #004085; font-weight: bold;">
                        💰 Стоимость доставки:
                    </td>
                    <td style="padding: 10px; border: 1px solid #007cba; background: #ffffff; color: #004085; font-weight: bold;">
                        <?php echo esc_html($formatted_cost); ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <div style="margin-top: 20px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px;">
                <h3 style="margin: 0 0 10px 0; color: #155724; font-size: 16px;">📋 Что нужно для получения заказа:</h3>
                <ul style="margin: 0; padding-left: 20px; color: #155724; line-height: 1.6;">
                    <li><strong>Код пункта выдачи:</strong> <?php echo esc_html($cdek_point_code); ?></li>
                    <li><strong>Документ, удостоверяющий личность</strong> (паспорт, права)</li>
                    <li><strong>Номер заказа:</strong> <?php echo esc_html($order->get_order_number()); ?></li>
                </ul>
            </div>
            <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; text-align: center;">
                <p style="margin: 0; color: #856404; font-weight: bold;">
                    ⏰ Заказ будет храниться в пункте выдачи 7 дней
                </p>
            </div>
        </div>
        <!-- Конец блока СДЭК -->
        <?php
    }
}
?>

<?php

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );