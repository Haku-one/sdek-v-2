<?php
/**
 * Шаблон письма администратору о новом заказе
 * Переопределяет стандартный шаблон WooCommerce для отображения информации о доставке СДЭК
 * 
 * Этот файл должен быть размещен в: hello-elementor/woocommerce/emails/admin-new-order.php
 * 
 * @see https://docs.woocommerce.com/document/template-structure/
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
<p><?php printf( esc_html__( 'Вы получили заказ от %s. Заказ выглядит следующим образом:', 'woocommerce' ), $order->get_formatted_billing_full_name() ); ?></p>

<?php
// Получаем информацию о доставке СДЭК
$delivery_type = get_post_meta($order->get_id(), '_cdek_delivery_type', true);
$point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
$point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);

// Проверяем, что это заказ с доставкой СДЭК
$shipping_methods = $order->get_shipping_methods();
$is_cdek_order = false;

foreach ($shipping_methods as $item_id => $item) {
    if (strpos($item->get_method_id(), 'cdek_delivery') !== false) {
        $is_cdek_order = true;
        break;
    }
}

// Отображаем информацию о доставке СДЭК в начале письма, если это СДЭК заказ
if ($is_cdek_order && $delivery_type) {
    ?>
    <div style="background: #f8f9fa; border: 2px solid #007cba; border-radius: 8px; padding: 20px; margin: 20px 0;">
        <h2 style="color: #007cba; margin-top: 0; text-align: center;">🚚 ИНФОРМАЦИЯ О ДОСТАВКЕ</h2>
        
        <?php switch ($delivery_type) {
            case 'pickup': ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 6px;">
                    <h3 style="color: #155724; margin-top: 0;">📍 САМОВЫВОЗ</h3>
                    <p><strong>Адрес:</strong> г.Саратов, ул. Осипова, д. 18а</p>
                    <p><strong>Стоимость:</strong> Бесплатно</p>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-top: 10px;">
                        <strong>⚠️ ВНИМАНИЕ:</strong> Клиент выбрал самовывоз. Необходимо связаться с ним для уточнения времени получения заказа.
                    </div>
                </div>
                <?php break;
                
            case 'manager': ?>
                <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 6px;">
                    <h3 style="color: #0c5460; margin-top: 0;">📞 ДОСТАВКА ПО ДОГОВОРЕННОСТИ</h3>
                    <p>Клиент выбрал доставку по договоренности с менеджером.</p>
                    <p><strong>Стоимость:</strong> Бесплатно</p>
                    <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                        <strong>📋 ЗАДАЧА:</strong> Необходимо связаться с клиентом для уточнения деталей доставки.
                    </div>
                </div>
                <?php break;
                
            case 'cdek':
            default:
                if ($point_code && $point_data) { ?>
                    <div style="background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; border-radius: 6px;">
                        <h3 style="color: #1565c0; margin-top: 0;">🏪 ПУНКТ ВЫДАЧИ СДЭК</h3>
                        <div style="background: white; padding: 15px; border-radius: 4px; border: 1px solid #e1e5e9;">
                            <p><strong>Название:</strong> <?php echo esc_html($point_data['name']); ?></p>
                            <p><strong>Код пункта:</strong> <?php echo esc_html($point_code); ?></p>
                            
                            <?php if (isset($point_data['location']['address_full'])) { ?>
                                <p><strong>Адрес:</strong> <?php echo esc_html($point_data['location']['address_full']); ?></p>
                            <?php } ?>
                            
                            <?php 
                            // Режим работы
                            if (isset($point_data['work_time_list']) && is_array($point_data['work_time_list'])) { ?>
                                <p><strong>Режим работы:</strong></p>
                                <ul style="margin: 5px 0 5px 20px;">
                                    <?php 
                                    $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                                    foreach ($point_data['work_time_list'] as $work_time) {
                                        if (isset($work_time['day']) && isset($work_time['time'])) {
                                            $day_index = intval($work_time['day']) - 1;
                                            if ($day_index >= 0 && $day_index < 7) {
                                                echo '<li>' . $days[$day_index] . ': ' . esc_html($work_time['time']) . '</li>';
                                            }
                                        }
                                    } ?>
                                </ul>
                            <?php } ?>
                            
                            <?php 
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
                                if (!empty($phone_numbers)) { ?>
                                    <p><strong>Телефоны:</strong> <?php echo esc_html(implode(', ', $phone_numbers)); ?></p>
                                <?php }
                            } ?>
                        </div>
                    </div>
                <?php } else { ?>
                    <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 6px;">
                        <h3 style="color: #721c24; margin-top: 0;">⚠️ ВНИМАНИЕ</h3>
                        <p>Пункт выдачи СДЭК не выбран или данные не сохранены. Необходимо связаться с клиентом.</p>
                    </div>
                <?php }
                break;
        } ?>
    </div>
    <?php
}

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