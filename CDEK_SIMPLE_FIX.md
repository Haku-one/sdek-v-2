# СДЭК: Простое решение

## Удален сложный код
Убраны все сложные механизмы с sessionStorage, AJAX, глобальными переменными.

## Простое решение - 15 строк JavaScript
```javascript
function saveShippingData() {
    var text = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item__label').text().trim();
    var cost = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item__value').text().replace(/[^\d]/g, '');
    
    if (text && text !== 'Выберите пункт выдачи' && text.length > 10) {
        $('body').append('<input type="hidden" name="cdek_shipping_label" value="' + text + '">');
        $('body').append('<input type="hidden" name="cdek_shipping_cost" value="' + cost + '">');
        $('body').append('<input type="hidden" name="cdek_shipping_captured" value="1">');
        console.log('СДЭК: Сохранено - ' + text + ' (' + cost + ' руб.)');
    }
}
```

## Простое PHP сохранение - 10 строк
```php
function cdek_save_captured_shipping_data($order_id) {
    if (isset($_POST['cdek_shipping_captured']) && $_POST['cdek_shipping_captured'] === '1') {
        $label = sanitize_text_field($_POST['cdek_shipping_label']);
        $cost = sanitize_text_field($_POST['cdek_shipping_cost']);
        
        update_post_meta($order_id, '_cdek_shipping_label', $label);
        update_post_meta($order_id, '_cdek_shipping_cost', $cost);
        update_post_meta($order_id, '_cdek_shipping_captured', '1');
        
        cdek_force_create_correct_data($order_id, $label, $cost);
    }
}
```

## Резервный показ в email
Остался фильтр для показа данных СДЭК в email как запасной вариант.

**Итого: 25 строк кода вместо 200+**