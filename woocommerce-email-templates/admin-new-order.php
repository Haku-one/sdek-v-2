<?php
/**
 * Admin new order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/admin-new-order.php.
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

<?php /* translators: %s: Customer billing full name */ ?>
<p><?php printf( esc_html__( 'You've received the following order from %s:', 'woocommerce' ), $order->get_formatted_billing_full_name() ); ?></p>

<?php
// СДЭК: Проверяем выбор "Обсудить доставку с менеджером"
$discuss_delivery = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);

if ($discuss_delivery == 'Да') {
    ?>
    <!-- СДЭК: Блок информации об обсуждении доставки -->
    <div style="background: #ffeb3b; border: 2px solid #ff9800; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
        <h2 style="color: #e65100; margin-top: 0; border-bottom: 2px solid #ff9800; padding-bottom: 10px; text-align: center;">
            🗣️ ТРЕБУЕТСЯ ОБСУЖДЕНИЕ ДОСТАВКИ
        </h2>
        <div style="background: #fff3e0; padding: 15px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
            <p style="margin: 0; color: #e65100; font-size: 16px; font-weight: bold;">
                ⚠️ ПРИОРИТЕТ: Связаться с клиентом в течение рабочего дня
            </p>
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 10px; border: 1px solid #ffcc02; background: #fffde7; color: #e65100; font-weight: bold; width: 35%;">
                    📞 Действие:
                </td>
                <td style="padding: 10px; border: 1px solid #ffcc02; background: #ffffff; color: #e65100;">
                    Связаться с клиентом для обсуждения доставки
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ffcc02; background: #fffde7; color: #e65100; font-weight: bold;">
                    📋 Обсудить:
                </td>
                <td style="padding: 10px; border: 1px solid #ffcc02; background: #ffffff; color: #e65100;">
                    Адрес, время, стоимость и способ доставки
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ffcc02; background: #fffde7; color: #e65100; font-weight: bold;">
                    📞 Телефон клиента:
                </td>
                <td style="padding: 10px; border: 1px solid #ffcc02; background: #ffffff; color: #e65100; font-weight: bold;">
                    <?php echo esc_html($order->get_billing_phone()); ?>
                </td>
            </tr>
        </table>
        <div style="margin-top: 15px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; text-align: center;">
            <strong style="color: #155724;">💡 После обсуждения:</strong><br>
            <span style="color: #155724; font-size: 14px;">
                Обновите информацию о доставке в заказе и добавьте примечание с деталями
            </span>
        </div>
    </div>
    <!-- Конец блока обсуждения доставки -->
    <?php
}

// СДЭК: Добавляем информацию о доставке в начале письма
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
    <!-- СДЭК: Блок информации о доставке -->
    <div style="background: #e8f5e8; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
        <h2 style="color: #28a745; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px; text-align: center;">
            📦 ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК
        </h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tr>
                <td style="padding: 10px; border: 1px solid #28a745; background: #d4edda; color: #155724; font-weight: bold; width: 30%;">
                    🏪 Пункт выдачи:
                </td>
                <td style="padding: 10px; border: 1px solid #28a745; background: #ffffff; color: #155724;">
                    <?php echo esc_html($point_name); ?>
                </td>
            </tr>
            <?php if ($formatted_cost): ?>
            <tr>
                <td style="padding: 10px; border: 1px solid #28a745; background: #d4edda; color: #155724; font-weight: bold;">
                    💰 Стоимость доставки:
                </td>
                <td style="padding: 10px; border: 1px solid #28a745; background: #ffffff; color: #155724; font-weight: bold; font-size: 16px;">
                    <?php echo esc_html($formatted_cost); ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 10px; border: 1px solid #28a745; background: #d4edda; color: #155724; font-weight: bold;">
                    📍 Адрес:
                </td>
                <td style="padding: 10px; border: 1px solid #28a745; background: #ffffff; color: #155724;">
                    <?php echo esc_html($point_address); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #28a745; background: #d4edda; color: #155724; font-weight: bold;">
                    🔢 Код пункта:
                </td>
                <td style="padding: 10px; border: 1px solid #28a745; background: #ffffff; color: #155724; font-weight: bold;">
                    <?php echo esc_html($cdek_point_code); ?>
                </td>
            </tr>
            <?php if ($point_phone): ?>
            <tr>
                <td style="padding: 10px; border: 1px solid #28a745; background: #d4edda; color: #155724; font-weight: bold;">
                    📞 Телефон:
                </td>
                <td style="padding: 10px; border: 1px solid #28a745; background: #ffffff; color: #155724;">
                    <?php echo esc_html($point_phone); ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($point_work_time): ?>
            <tr>
                <td style="padding: 10px; border: 1px solid #28a745; background: #d4edda; color: #155724; font-weight: bold;">
                    🕐 Режим работы:
                </td>
                <td style="padding: 10px; border: 1px solid #28a745; background: #ffffff; color: #155724;">
                    <?php echo esc_html($point_work_time); ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <div style="margin-top: 20px; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; text-align: center;">
            <strong style="color: #0c5460;">💡 Важно для выдачи заказа:</strong><br>
            <span style="color: #0c5460; font-size: 14px;">
                Покупатель должен предъявить код пункта выдачи: <strong><?php echo esc_html($cdek_point_code); ?></strong>
            </span>
        </div>
    </div>
    <!-- Конец блока СДЭК -->
    <?php
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