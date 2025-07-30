<?php
/**
 * Admin new order email (Simplified version with custom fields)
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
<p><?php printf( esc_html__( 'You have received the following order from %s:', 'woocommerce' ), $order->get_formatted_billing_full_name() ); ?></p>

<?php
// Проверяем тип доставки из кастомных полей для отображения специального уведомления
$delivery_type = $order->get_meta('Тип доставки');
$delivery_status = $order->get_meta('Статус доставки');

if ($delivery_type === 'Обсудить с менеджером' || $delivery_status === 'Требуется обсуждение') {
    ?>
    <!-- Важное уведомление об обсуждении доставки -->
    <div style="background: #ffeb3b; border: 2px solid #ff9800; padding: 15px; margin: 20px 0; border-radius: 8px; text-align: center; font-family: Arial, sans-serif;">
        <h3 style="color: #e65100; margin: 0 0 10px 0; font-size: 18px;">
            🚨 ТРЕБУЕТСЯ ОБСУЖДЕНИЕ ДОСТАВКИ
        </h3>
        <p style="margin: 0; color: #e65100; font-size: 16px; font-weight: bold;">
            ⚠️ Свяжитесь с клиентом для уточнения деталей доставки
        </p>
        <p style="margin: 5px 0 0 0; color: #e65100; font-size: 14px;">
            Телефон: <strong><?php echo esc_html($order->get_billing_phone()); ?></strong>
        </p>
    </div>
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

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );