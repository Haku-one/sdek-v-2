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
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails\HTML
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p><?php esc_html_e( 'Your order has been completed. Your order details are shown below for your reference:', 'woocommerce' ); ?></p>

<?php
// СДЭК: Добавляем информацию о доставке для покупателя
$cdek_point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
$cdek_point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
$cdek_delivery_cost = get_post_meta($order->get_id(), '_cdek_delivery_cost', true);

if ($cdek_point_code && $cdek_point_data) {
    // Получаем стоимость доставки
    if (!$cdek_delivery_cost) {
        $shipping_methods = $order->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method) {
            if (strpos($shipping_method->get_method_id(), 'cdek') !== false) {
                $cdek_delivery_cost = $shipping_method->get_total();
                break;
            }
        }
    }
    
    // Формируем название пункта выдачи
    $point_name = $cdek_point_data['name'];
    if (isset($cdek_point_data['location']['city'])) {
        $city = $cdek_point_data['location']['city'];
        $point_name = $city . ', ' . str_replace($city, '', $point_name);
        $point_name = trim($point_name, ', ');
    }
    
    // Получаем адрес
    $address = '';
    if (isset($cdek_point_data['location']['address_full'])) {
        $address = $cdek_point_data['location']['address_full'];
    } elseif (isset($cdek_point_data['location']['address'])) {
        $address = $cdek_point_data['location']['address'];
    }
    
    // Получаем телефон
    $phone = '';
    if (isset($cdek_point_data['phones']) && is_array($cdek_point_data['phones']) && !empty($cdek_point_data['phones'])) {
        $phone = $cdek_point_data['phones'][0]['number'] ?? $cdek_point_data['phones'][0];
    }
    
    // Получаем режим работы
    $work_time = isset($cdek_point_data['work_time']) ? $cdek_point_data['work_time'] : '';
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
            <p style="margin: 5px 0 0 0; color: #0056b3; font-size: 14px;">
                Приходите за покупкой в удобное для вас время
            </p>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #bee5eb; font-weight: bold; color: #0056b3; width: 30%;">
                    📍 Пункт выдачи:
                </td>
                <td style="padding: 10px 0; border-bottom: 1px solid #bee5eb; color: #0056b3; font-size: 16px; font-weight: bold;">
                    <?php echo esc_html($point_name); ?>
                </td>
            </tr>
            
            <?php if ($address): ?>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #bee5eb; font-weight: bold; color: #0056b3;">
                    🏠 Адрес:
                </td>
                <td style="padding: 10px 0; border-bottom: 1px solid #bee5eb; color: #0056b3;">
                    <?php echo esc_html($address); ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <tr style="background: #fff3cd;">
                <td style="padding: 10px; border-bottom: 1px solid #bee5eb; font-weight: bold; color: #856404;">
                    🔢 Код для получения:
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #bee5eb;">
                    <span style="background: #007cba; color: white; padding: 8px 16px; border-radius: 6px; font-weight: bold; font-size: 18px; display: inline-block;">
                        <?php echo esc_html($cdek_point_code); ?>
                    </span>
                </td>
            </tr>
            
            <?php if ($phone): ?>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #bee5eb; font-weight: bold; color: #0056b3;">
                    📞 Телефон пункта:
                </td>
                <td style="padding: 10px 0; border-bottom: 1px solid #bee5eb; color: #0056b3;">
                    <a href="tel:<?php echo esc_attr($phone); ?>" style="color: #007cba; text-decoration: none; font-weight: bold;">
                        <?php echo esc_html($phone); ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if ($work_time): ?>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #bee5eb; font-weight: bold; color: #0056b3;">
                    🕒 Режим работы:
                </td>
                <td style="padding: 10px 0; border-bottom: 1px solid #bee5eb; color: #0056b3;">
                    <?php echo esc_html($work_time); ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if ($cdek_delivery_cost): ?>
            <tr>
                <td style="padding: 10px 0; font-weight: bold; color: #0056b3;">
                    💰 Стоимость доставки:
                </td>
                <td style="padding: 10px 0; color: #28a745; font-weight: bold; font-size: 16px;">
                    <?php echo esc_html($cdek_delivery_cost); ?> руб.
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
            <p style="margin: 5px 0 0 0; color: #856404; font-size: 14px;">
                Не забудьте получить свою покупку вовремя!
            </p>
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