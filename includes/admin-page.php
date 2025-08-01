<?php
/**
 * СДЭК Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display admin page
 */
function cdek_delivery_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Настройки СДЭК Доставка', 'cdek-delivery'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('cdek_delivery_settings');
            do_settings_sections('cdek_delivery_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('API Логин СДЭК', 'cdek-delivery'); ?></th>
                    <td>
                        <input type="text" name="cdek_api_login" value="<?php echo esc_attr(get_option('cdek_api_login')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Логин для доступа к API СДЭК', 'cdek-delivery'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('API Пароль СДЭК', 'cdek-delivery'); ?></th>
                    <td>
                        <input type="password" name="cdek_api_password" value="<?php echo esc_attr(get_option('cdek_api_password')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Пароль для доступа к API СДЭК', 'cdek-delivery'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Яндекс.Карты API ключ', 'cdek-delivery'); ?></th>
                    <td>
                        <input type="text" name="cdek_yandex_api_key" value="<?php echo esc_attr(get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702')); ?>" class="regular-text" />
                        <p class="description"><?php _e('API ключ для работы с картами Яндекс', 'cdek-delivery'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Тестовый режим', 'cdek-delivery'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="cdek_test_mode" value="1" <?php checked(1, get_option('cdek_test_mode')); ?> />
                            <?php _e('Включить тестовый режим API СДЭК', 'cdek-delivery'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        
        <hr>
        
        <h2><?php _e('Проверка подключения', 'cdek-delivery'); ?></h2>
        <p>
            <button type="button" id="test-cdek-connection" class="button button-secondary">
                <?php _e('Тестировать подключение к СДЭК', 'cdek-delivery'); ?>
            </button>
        </p>
        <div id="test-result"></div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-cdek-connection').click(function() {
                var button = $(this);
                var result = $('#test-result');
                
                button.prop('disabled', true).text('<?php _e('Тестирование...', 'cdek-delivery'); ?>');
                result.html('<p><?php _e('Проверка подключения...', 'cdek-delivery'); ?></p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_cdek_connection',
                        nonce: '<?php echo wp_create_nonce('cdek_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error"><p><?php _e('Ошибка при тестировании подключения', 'cdek-delivery'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Тестировать подключение к СДЭК', 'cdek-delivery'); ?>');
                    }
                });
            });
        });
        </script>
    </div>
    <?php
}

/**
 * Register settings
 */
function cdek_delivery_admin_init() {
    register_setting('cdek_delivery_settings', 'cdek_api_login');
    register_setting('cdek_delivery_settings', 'cdek_api_password');
    register_setting('cdek_delivery_settings', 'cdek_yandex_api_key');
    register_setting('cdek_delivery_settings', 'cdek_test_mode');
}
add_action('admin_init', 'cdek_delivery_admin_init');