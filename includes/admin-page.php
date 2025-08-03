<?php

if (!defined('ABSPATH')) {
    exit;
}

// Обработка сохранения настроек
if (isset($_POST['submit'])) {
    if (wp_verify_nonce($_POST['cdek_settings_nonce'], 'cdek_settings')) {
        update_option('cdek_account', sanitize_text_field($_POST['cdek_account']));
        update_option('cdek_password', sanitize_text_field($_POST['cdek_password']));
        update_option('cdek_test_mode', isset($_POST['cdek_test_mode']) ? 1 : 0);
        update_option('cdek_sender_city', sanitize_text_field($_POST['cdek_sender_city']));
        update_option('cdek_yandex_api_key', sanitize_text_field($_POST['cdek_yandex_api_key']));
        
        // Email уведомления
        update_option('cdek_email_notifications_enabled', isset($_POST['cdek_email_notifications_enabled']) ? 1 : 0);
        update_option('cdek_admin_notification_email', sanitize_email($_POST['cdek_admin_notification_email']));
        update_option('cdek_email_from_name', sanitize_text_field($_POST['cdek_email_from_name']));
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
        });
    }
}

// Получение текущих настроек
$cdek_account = get_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
$cdek_password = get_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
$cdek_test_mode = get_option('cdek_test_mode', 0);
$cdek_sender_city = get_option('cdek_sender_city', '354');
$cdek_yandex_api_key = get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');

// Email уведомления
$cdek_email_notifications_enabled = get_option('cdek_email_notifications_enabled', 1);
$cdek_admin_notification_email = get_option('cdek_admin_notification_email', get_option('admin_email'));
$cdek_email_from_name = get_option('cdek_email_from_name', get_bloginfo('name'));

?>

<div class="wrap">
    <h1>Настройки СДЭК Доставка</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('cdek_settings', 'cdek_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">Account (Client ID)</th>
                <td>
                    <input type="text" name="cdek_account" value="<?php echo esc_attr($cdek_account); ?>" class="regular-text" />
                    <p class="description">Идентификатор клиента для API СДЭК</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Secure Password (Client Secret)</th>
                <td>
                    <input type="password" name="cdek_password" value="<?php echo esc_attr($cdek_password); ?>" class="regular-text" />
                    <p class="description">Секретный ключ для API СДЭК</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Тестовый режим</th>
                <td>
                    <label>
                        <input type="checkbox" name="cdek_test_mode" value="1" <?php checked($cdek_test_mode, 1); ?> />
                        Использовать тестовую среду СДЭК
                    </label>
                    <p class="description">В тестовом режиме будет использоваться api.edu.cdek.ru</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Город отправителя</th>
                <td>
                    <input type="text" name="cdek_sender_city" value="<?php echo esc_attr($cdek_sender_city); ?>" class="regular-text" />
                    <p class="description">Код города отправителя (по умолчанию 51 - Саратов)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">API ключ Яндекс.Карт</th>
                <td>
                    <input type="text" name="cdek_yandex_api_key" value="<?php echo esc_attr($cdek_yandex_api_key); ?>" class="regular-text" />
                    <p class="description">API ключ для работы с Яндекс.Картами</p>
                </td>
            </tr>
        </table>
        
        <h2>📧 Настройки Email уведомлений</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Включить email уведомления</th>
                <td>
                    <label>
                        <input type="checkbox" name="cdek_email_notifications_enabled" value="1" <?php checked($cdek_email_notifications_enabled, 1); ?> />
                        Отправлять email уведомления о доставке
                    </label>
                    <p class="description">Включает отправку уведомлений клиентам и администратору при выборе способа доставки</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Email администратора</th>
                <td>
                    <input type="email" name="cdek_admin_notification_email" value="<?php echo esc_attr($cdek_admin_notification_email); ?>" class="regular-text" />
                    <p class="description">Email для получения уведомлений о новых заказах (по умолчанию: email администратора сайта)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Имя отправителя</th>
                <td>
                    <input type="text" name="cdek_email_from_name" value="<?php echo esc_attr($cdek_email_from_name); ?>" class="regular-text" />
                    <p class="description">Имя, которое будет отображаться в поле "От кого" в письмах (по умолчанию: название сайта)</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Сохранить настройки'); ?>
    </form>
    
    <hr>
    
    <h2>Проверка подключения и расчета стоимости</h2>
    <p>Нажмите кнопки ниже для тестирования API СДЭК:</p>
    <p>
        <button type="button" id="test-cdek-connection" class="button button-secondary">Проверить подключение</button>
        <button type="button" id="test-cdek-calculation" class="button button-primary" style="margin-left: 10px;">Тестировать расчет стоимости</button>
        <button type="button" id="test-cdek-api-detailed" class="button button-secondary" style="margin-left: 10px;">Детальное тестирование API</button>
        <button type="button" id="test-saratov-kursk" class="button button-secondary" style="margin-left: 10px;">🎯 Тест Саратов-Курск</button>
        <button type="button" id="test-super-debug" class="button button-secondary" style="margin-left: 10px; background: #d63384; color: white;">💥 СУПЕР ДЕБАГ</button>
    </p>
    
    <h2>📧 Тестирование Email уведомлений</h2>
    <p>Проверьте работу email уведомлений:</p>
    <p>
        <button type="button" id="test-email-pickup" class="button button-secondary">📍 Тест: Самовывоз</button>
        <button type="button" id="test-email-manager" class="button button-secondary" style="margin-left: 10px;">📞 Тест: Менеджер</button>
        <button type="button" id="test-email-cdek" class="button button-secondary" style="margin-left: 10px;">🚚 Тест: СДЭК</button>
    </p>
    <div id="connection-result" style="margin-top: 10px;"></div>
    <div id="calculation-result" style="margin-top: 10px;"></div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-cdek-connection').on('click', function() {
            var button = $(this);
            var result = $('#connection-result');
            
            button.prop('disabled', true).text('Проверка...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_cdek_connection',
                nonce: '<?php echo wp_create_nonce('test_cdek_connection'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
                
                button.prop('disabled', false).text('Проверить подключение');
            });
        });
        
        $('#test-cdek-calculation').on('click', function() {
            var button = $(this);
            var result = $('#calculation-result');
            
            button.prop('disabled', true).text('Тестирование...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_cdek_calculation',
                nonce: '<?php echo wp_create_nonce('test_cdek_calculation'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
                
                button.prop('disabled', false).text('Тестировать расчет стоимости');
            });
        });
        
        $('#test-cdek-api-detailed').on('click', function() {
            var button = $(this);
            var result = $('#calculation-result');
            
            button.prop('disabled', true).text('Детальное тестирование...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_cdek_api_detailed',
                nonce: '<?php echo wp_create_nonce('test_cdek_api_detailed'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                }
                
                button.prop('disabled', false).text('Детальное тестирование API');
            });
        });
        
        $('#test-saratov-kursk').on('click', function() {
            var button = $(this);
            var result = $('#calculation-result');
            
            button.prop('disabled', true).text('🎯 Тестируем Саратов-Курск...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_saratov_kursk',
                nonce: '<?php echo wp_create_nonce('test_saratov_kursk'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>🎉 ' + response.data + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>❌ ' + response.data.message + '</p></div>');
                }
                
                button.prop('disabled', false).text('🎯 Тест Саратов-Курск');
            });
        });
        
        $('#test-super-debug').on('click', function() {
            var button = $(this);
            var result = $('#calculation-result');
            
            button.prop('disabled', true).text('💥 СУПЕР ДЕБАГ...');
            result.html('<div class="notice notice-info inline"><p>💥 Запущен СУПЕР ДЕБАГ! Проверяйте логи WordPress...</p></div>');
            
            $.post(ajaxurl, {
                action: 'super_debug',
                nonce: '<?php echo wp_create_nonce('super_debug'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>💥 ' + response.data + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>💥 ' + response.data + '</p></div>');
                }
                
                button.prop('disabled', false).text('💥 СУПЕР ДЕБАГ');
            });
        });
        
        // Тестирование email уведомлений
        $('#test-email-pickup').on('click', function() {
            testEmailNotification($(this), 'pickup', '📍 Тест: Самовывоз');
        });
        
        $('#test-email-manager').on('click', function() {
            testEmailNotification($(this), 'manager', '📞 Тест: Менеджер');
        });
        
        $('#test-email-cdek').on('click', function() {
            testEmailNotification($(this), 'cdek', '🚚 Тест: СДЭК');
        });
        
        function testEmailNotification(button, type, originalText) {
            var result = $('#calculation-result');
            
            button.prop('disabled', true).text('📧 Отправляем...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_cdek_email_notification',
                type: type,
                nonce: '<?php echo wp_create_nonce('test_cdek_email_notification'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>📧 ' + response.data + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>❌ ' + response.data + '</p></div>');
                }
                
                button.prop('disabled', false).text(originalText);
            });
        }
    });
    </script>
    
    <hr>
    
    <h2>Инструкции по настройке</h2>
    <div class="card">
        <h3>Получение API ключей СДЭК</h3>
        <ol>
            <li>Зарегистрируйтесь в <a href="https://lk.cdek.ru/" target="_blank">личном кабинете СДЭК</a></li>
            <li>Перейдите в раздел "Интеграция" → "API"</li>
            <li>Создайте новое приложение и получите Account и Secure password</li>
            <li>Введите полученные данные в форму выше</li>
        </ol>
        
        <h3>Настройка зон доставки</h3>
        <ol>
            <li>Перейдите в <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>">WooCommerce → Настройки → Доставка</a></li>
            <li>Выберите зону доставки или создайте новую</li>
            <li>Добавьте метод доставки "СДЭК — Пункт выдачи"</li>
            <li>Настройте параметры метода доставки</li>
        </ol>
        
        <h3>Коды городов СДЭК</h3>
        <p>Популярные коды городов:</p>
        <ul>
            <li>Москва: 44</li>
            <li>Санкт-Петербург: 137</li>
            <li>Новосибирск: 114</li>
            <li>Екатеринбург: 49</li>
            <li>Саратов: 51 (по умолчанию)</li>
        </ul>
        
        <h3>📧 Email уведомления</h3>
        <p>Плагин автоматически отправляет email уведомления:</p>
        <ul>
            <li><strong>Клиентам:</strong> Информация о выбранном способе доставки</li>
            <li><strong>Администратору:</strong> Уведомления о новых заказах с инструкциями</li>
            <li><strong>При самовывозе:</strong> Адрес и необходимость связаться с клиентом</li>
            <li><strong>При выборе менеджера:</strong> Требуется обсуждение деталей доставки</li>
            <li><strong>При доставке СДЭК:</strong> Информация о пункте выдачи</li>
        </ul>
        <p>Используйте кнопки тестирования выше для проверки работы email уведомлений.</p>
    </div>
</div>
