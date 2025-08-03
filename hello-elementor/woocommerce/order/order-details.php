<?php
/**
 * Шаблон деталей заказа
 * Переопределяет стандартный шаблон WooCommerce для улучшенного отображения информации о доставке СДЭК
 * 
 * Этот файл должен быть размещен в: hello-elementor/woocommerce/order/order-details.php
 * 
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.6.0
 */

defined( 'ABSPATH' ) || exit;

$order = wc_get_order( $order_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( ! $order ) {
	return;
}

$order_items           = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$show_purchase_note    = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
$show_customer_details = is_user_logged_in() && $order->get_user_id() === get_current_user_id();
$downloads             = $order->get_downloadable_items();
$show_downloads        = $order->has_downloadable_item() && $order->is_download_permitted();

if ( $show_downloads ) {
	wc_get_template(
		'order/order-downloads.php',
		array(
			'downloads'  => $downloads,
			'show_title' => true,
		)
	);
}

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

// Отображаем информацию о доставке СДЭК в начале, если это СДЭК заказ
if ($is_cdek_order && $delivery_type) {
    ?>
    <section class="woocommerce-cdek-delivery-info">
        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #007cba; border-radius: 12px; padding: 25px; margin: 25px 0; box-shadow: 0 4px 6px rgba(0, 123, 186, 0.1);">
            <h2 style="color: #007cba; margin-top: 0; text-align: center; font-size: 24px; margin-bottom: 20px;">🚚 Информация о получении заказа</h2>
            
            <?php switch ($delivery_type) {
                case 'pickup': ?>
                    <div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border: 1px solid #28a745; padding: 20px; border-radius: 8px; position: relative;">
                        <div style="position: absolute; top: -10px; left: 20px; background: #28a745; color: white; padding: 5px 15px; border-radius: 15px; font-size: 12px; font-weight: bold;">САМОВЫВОЗ</div>
                        <h3 style="color: #155724; margin-top: 10px; font-size: 20px;">📍 Самовывоз из нашего офиса</h3>
                        <div style="background: white; padding: 15px; border-radius: 6px; margin: 15px 0;">
                            <p style="margin: 5px 0; font-size: 16px;"><strong>📍 Адрес:</strong> г.Саратов, ул. Осипова, д. 18а</p>
                            <p style="margin: 5px 0; font-size: 16px;"><strong>💰 Стоимость:</strong> <span style="color: #28a745; font-weight: bold;">Бесплатно</span></p>
                        </div>
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin-top: 15px;">
                            <p style="margin: 0; color: #856404; font-size: 14px;"><strong>📞 Важно:</strong> Пожалуйста, свяжитесь с нами для уточнения времени получения заказа. Мы работаем в удобное для вас время!</p>
                        </div>
                    </div>
                    <?php break;
                    
                case 'manager': ?>
                    <div style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border: 1px solid #17a2b8; padding: 20px; border-radius: 8px; position: relative;">
                        <div style="position: absolute; top: -10px; left: 20px; background: #17a2b8; color: white; padding: 5px 15px; border-radius: 15px; font-size: 12px; font-weight: bold;">МЕНЕДЖЕРСКАЯ ДОСТАВКА</div>
                        <h3 style="color: #0c5460; margin-top: 10px; font-size: 20px;">📞 Доставка по договоренности с менеджером</h3>
                        <div style="background: white; padding: 15px; border-radius: 6px; margin: 15px 0;">
                            <p style="margin: 5px 0; font-size: 16px;">Вы выбрали индивидуальную доставку с нашим менеджером.</p>
                            <p style="margin: 5px 0; font-size: 16px;"><strong>💰 Стоимость:</strong> <span style="color: #28a745; font-weight: bold;">Бесплатно</span></p>
                        </div>
                        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 6px; margin-top: 15px;">
                            <p style="margin: 0; color: #155724; font-size: 14px;"><strong>📋 Статус:</strong> Наш менеджер свяжется с вами в ближайшее время для уточнения деталей доставки в удобное для вас время и место.</p>
                        </div>
                    </div>
                    <?php break;
                    
                case 'cdek':
                default:
                    if ($point_code && $point_data) { ?>
                        <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 1px solid #2196f3; padding: 20px; border-radius: 8px; position: relative;">
                            <div style="position: absolute; top: -10px; left: 20px; background: #2196f3; color: white; padding: 5px 15px; border-radius: 15px; font-size: 12px; font-weight: bold;">ПУНКТ ВЫДАЧИ СДЭК</div>
                            <h3 style="color: #1565c0; margin-top: 10px; font-size: 20px;">🏪 <?php echo esc_html($point_data['name']); ?></h3>
                            
                            <div style="background: white; padding: 20px; border-radius: 8px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                    <div>
                                        <p style="margin: 8px 0; font-size: 16px;"><strong>🏷️ Код пункта:</strong> <span style="background: #e3f2fd; padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?php echo esc_html($point_code); ?></span></p>
                                        <?php if (isset($point_data['location']['address_full'])) { ?>
                                            <p style="margin: 8px 0; font-size: 16px;"><strong>📍 Адрес:</strong><br><?php echo esc_html($point_data['location']['address_full']); ?></p>
                                        <?php } ?>
                                    </div>
                                    
                                    <div>
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
                                                <p style="margin: 8px 0; font-size: 16px;"><strong>📞 Телефоны:</strong><br>
                                                <?php foreach ($phone_numbers as $phone) { ?>
                                                    <a href="tel:<?php echo esc_attr($phone); ?>" style="color: #007cba; text-decoration: none; display: block;"><?php echo esc_html($phone); ?></a>
                                                <?php } ?>
                                                </p>
                                            <?php }
                                        } ?>
                                    </div>
                                </div>
                                
                                <?php 
                                // Режим работы
                                if (isset($point_data['work_time_list']) && is_array($point_data['work_time_list'])) { ?>
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px;">
                                        <p style="margin: 0 0 10px 0; font-weight: bold; color: #495057;">🕒 Режим работы:</p>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 5px;">
                                            <?php 
                                            $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                                            foreach ($point_data['work_time_list'] as $work_time) {
                                                if (isset($work_time['day']) && isset($work_time['time'])) {
                                                    $day_index = intval($work_time['day']) - 1;
                                                    if ($day_index >= 0 && $day_index < 7) { ?>
                                                        <div style="padding: 4px 8px; background: white; border-radius: 4px; font-size: 14px;">
                                                            <strong><?php echo $days[$day_index]; ?>:</strong> <?php echo esc_html($work_time['time']); ?>
                                                        </div>
                                                    <?php }
                                                }
                                            } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            
                            <div style="background: #e8f5e8; border: 1px solid #c3e6cb; padding: 15px; border-radius: 6px; margin-top: 15px;">
                                <p style="margin: 0; color: #155724; font-size: 14px;"><strong>💡 Полезные советы:</strong></p>
                                <ul style="margin: 10px 0 0 20px; color: #155724; font-size: 14px;">
                                    <li>При получении заказа обязательно возьмите с собой документ, удостоверяющий личность</li>
                                    <li>Код заказа: <strong><?php echo $order->get_order_number(); ?></strong></li>
                                    <li>Срок хранения заказа в пункте выдачи обычно составляет 7 дней</li>
                                </ul>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px;">
                            <h3 style="color: #856404; margin-top: 0;">⚠️ Внимание</h3>
                            <p>Информация о пункте выдачи СДЭК не найдена. Пожалуйста, свяжитесь с нами для уточнения деталей доставки.</p>
                        </div>
                    <?php }
                    break;
            } ?>
        </div>
    </section>
    <?php
}
?>

<section class="woocommerce-order-details">
	<?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>

	<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order details', 'woocommerce' ); ?></h2>

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

		<thead>
			<tr>
				<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
				<th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			do_action( 'woocommerce_order_details_before_order_table_items', $order );

			foreach ( $order_items as $item_id => $item ) {
				$product = $item->get_product();

				wc_get_template(
					'order/order-details-item.php',
					array(
						'order'              => $order,
						'item_id'            => $item_id,
						'item'               => $item,
						'show_purchase_note' => $show_purchase_note,
						'purchase_note'      => $product ? $product->get_purchase_note() : '',
						'product'            => $product,
					)
				);
			}

			do_action( 'woocommerce_order_details_after_order_table_items', $order );
			?>
		</tbody>

		<tfoot>
			<?php
			foreach ( $order->get_order_item_totals() as $key => $total ) {
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
					<td><?php echo ( 'payment_method' === $key ) ? esc_html( $total['value'] ) : wp_kses_post( $total['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<?php
			}
			?>
			<?php if ( $order->get_customer_note() ) : ?>
				<tr>
					<th><?php esc_html_e( 'Note:', 'woocommerce' ); ?></th>
					<td><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>

	<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
</section>

<?php
/**
 * Action hook fired after the order details.
 *
 * @since 4.4.0
 * @param WC_Order $order Order data.
 */
do_action( 'woocommerce_after_single_order_summary', $order );
?>