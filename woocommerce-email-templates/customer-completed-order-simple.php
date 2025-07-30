<?php
/**
 * Customer completed order email (Simplified version with custom fields)
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
 * @version 3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), $order->get_billing_first_name() ); ?></p>
<p><?php esc_html_e( 'Your order has been marked complete and you should have received your items. If you have any questions about this order please get in touch.', 'woocommerce' ); ?></p>

<?php
// Проверяем тип доставки из кастомных полей для отображения специальной информации
$delivery_type = $order->get_meta('Тип доставки');

if ($delivery_type === 'СДЭК') {
    $pvz_name = $order->get_meta('Пункт выдачи СДЭК');
    $pvz_address = $order->get_meta('Адрес пункта выдачи');
    $pvz_code = $order->get_meta('Код пункта СДЭК');
    
    if ($pvz_name || $pvz_code) {
        ?>
        <!-- Информация о доставке СДЭК -->
        <div style="background: #e8f5e8; border: 2px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
            <h3 style="color: #28a745; margin: 0 0 10px 0; text-align: center;">
                📦 Ваш заказ доставлен в пункт выдачи СДЭК
            </h3>
            <?php if ($pvz_name): ?>
                <p style="margin: 5px 0; color: #155724;">
                    <strong>Пункт выдачи:</strong> <?php echo esc_html($pvz_name); ?>
                </p>
            <?php endif; ?>
            <?php if ($pvz_address): ?>
                <p style="margin: 5px 0; color: #155724;">
                    <strong>Адрес:</strong> <?php echo esc_html($pvz_address); ?>
                </p>
            <?php endif; ?>
            <?php if ($pvz_code): ?>
                <p style="margin: 5px 0; color: #155724; text-align: center; background: #d4edda; padding: 10px; border-radius: 4px;">
                    <strong>Код для получения:</strong> <span style="font-size: 18px; font-weight: bold;"><?php echo esc_html($pvz_code); ?></span>
                </p>
            <?php endif; ?>
        </div>
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

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );