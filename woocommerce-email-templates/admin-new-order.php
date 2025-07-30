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
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails\HTML
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer billing full name */ ?>
<p><?php printf( esc_html__( 'You've received the following order from %s:', 'woocommerce' ), $order->get_formatted_billing_full_name() ); ?></p>

<?php
// СДЭК: Добавляем информацию о доставке в начале письма
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
    
    <!-- СДЭК: Блок информации о доставке -->
    <div style="background: #e8f5e8; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
        <h2 style="color: #28a745; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px; text-align: center;">
            📦 ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК
        </h2>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; font-weight: bold; color: #155724; width: 30%;">
                    Пункт выдачи:
                </td>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; color: #155724;">
                    <?php echo esc_html($point_name); ?>
                </td>
            </tr>
            
            <?php if ($cdek_delivery_cost): ?>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; font-weight: bold; color: #155724;">
                    Стоимость доставки:
                </td>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; color: #28a745; font-weight: bold; font-size: 16px;">
                    <?php echo esc_html($cdek_delivery_cost); ?> руб.
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if ($address): ?>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; font-weight: bold; color: #155724;">
                    Адрес:
                </td>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; color: #155724;">
                    <?php echo esc_html($address); ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; font-weight: bold; color: #155724;">
                    Код пункта:
                </td>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; color: #155724;">
                    <code style="background: #f8f9fa; padding: 4px 8px; border: 1px solid #28a745; border-radius: 4px; font-weight: bold;">
                        <?php echo esc_html($cdek_point_code); ?>
                    </code>
                </td>
            </tr>
            
            <?php if ($phone): ?>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; font-weight: bold; color: #155724;">
                    Телефон пункта:
                </td>
                <td style="padding: 8px 0; border-bottom: 1px solid #d4edda; color: #155724;">
                    <a href="tel:<?php echo esc_attr($phone); ?>" style="color: #007cba; text-decoration: none;">
                        <?php echo esc_html($phone); ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if ($work_time): ?>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #155724;">
                    Режим работы:
                </td>
                <td style="padding: 8px 0; color: #155724;">
                    <?php echo esc_html($work_time); ?>
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