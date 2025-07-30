/**
 * СДЭК Доставка - Диагностика Яндекс.Карт
 * Файл для отладки и диагностики проблем с загрузкой Яндекс.Карт
 */

(function() {
    'use strict';
    
    // Флаг для отслеживания диагностики
    window.cdekMapsDiagnostics = {
        started: false,
        errors: [],
        warnings: [],
        info: []
    };
    
    function log(type, message, data) {
        var timestamp = new Date().toISOString();
        var logEntry = {
            timestamp: timestamp,
            message: message,
            data: data || null
        };
        
        window.cdekMapsDiagnostics[type].push(logEntry);
        
        // Выводим в консоль с соответствующим уровнем
        switch(type) {
            case 'errors':
                console.error('[СДЭК Maps]', message, data);
                break;
            case 'warnings':
                console.warn('[СДЭК Maps]', message, data);
                break;
            case 'info':
                console.log('[СДЭК Maps]', message, data);
                break;
        }
    }
    
    function startDiagnostics() {
        if (window.cdekMapsDiagnostics.started) return;
        window.cdekMapsDiagnostics.started = true;
        
        log('info', 'Начинаем диагностику Яндекс.Карт');
        
        // Проверяем наличие API ключа
        if (typeof cdek_ajax !== 'undefined' && cdek_ajax.yandex_api_key) {
            log('info', 'API ключ найден', cdek_ajax.yandex_api_key.substring(0, 8) + '...');
        } else {
            log('warnings', 'API ключ не найден в cdek_ajax');
        }
        
        // Проверяем загрузку скрипта Яндекс.Карт
        var yandexScript = document.querySelector('script[src*="api-maps.yandex.ru"]');
        if (yandexScript) {
            log('info', 'Скрипт Яндекс.Карт найден', yandexScript.src);
            
            // Отслеживаем события загрузки
            yandexScript.addEventListener('load', function() {
                log('info', 'Скрипт Яндекс.Карт успешно загружен');
                checkYmapsAvailability();
            });
            
            yandexScript.addEventListener('error', function(e) {
                log('errors', 'Ошибка загрузки скрипта Яндекс.Карт', {
                    error: e,
                    src: yandexScript.src
                });
            });
        } else {
            log('warnings', 'Скрипт Яндекс.Карт не найден в DOM');
        }
        
        // Проверяем сетевые ошибки
        var originalFetch = window.fetch;
        window.fetch = function() {
            var url = arguments[0];
            if (typeof url === 'string' && url.includes('yandex.ru')) {
                log('info', 'Запрос к Яндекс API', url);
            }
            return originalFetch.apply(this, arguments).catch(function(error) {
                if (typeof url === 'string' && url.includes('yandex.ru')) {
                    log('errors', 'Ошибка сетевого запроса к Яндекс API', {
                        url: url,
                        error: error.message
                    });
                }
                throw error;
            });
        };
        
        // Периодически проверяем доступность ymaps
        var checkInterval = setInterval(function() {
            if (typeof ymaps !== 'undefined') {
                log('info', 'ymaps стал доступен');
                clearInterval(checkInterval);
                checkYmapsAvailability();
            }
        }, 1000);
        
        // Останавливаем проверку через 30 секунд
        setTimeout(function() {
            clearInterval(checkInterval);
            if (typeof ymaps === 'undefined') {
                log('errors', 'ymaps не стал доступен за 30 секунд');
                generateDiagnosticsReport();
            }
        }, 30000);
    }
    
    function checkYmapsAvailability() {
        try {
            if (typeof ymaps === 'undefined') {
                log('errors', 'ymaps не определен');
                return;
            }
            
            log('info', 'ymaps доступен, проверяем компоненты');
            
            // Проверяем основные компоненты
            var components = ['Map', 'Placemark', 'geocode', 'ready'];
            components.forEach(function(component) {
                if (ymaps[component]) {
                    log('info', 'Компонент доступен: ' + component);
                } else {
                    log('warnings', 'Компонент недоступен: ' + component);
                }
            });
            
            // Проверяем готовность API
            if (ymaps.ready) {
                ymaps.ready(function() {
                    log('info', 'Яндекс.Карты полностью готовы к использованию');
                });
            }
            
        } catch (error) {
            log('errors', 'Ошибка при проверке ymaps', error);
        }
    }
    
    function generateDiagnosticsReport() {
        var report = {
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            url: window.location.href,
            errors: window.cdekMapsDiagnostics.errors,
            warnings: window.cdekMapsDiagnostics.warnings,
            info: window.cdekMapsDiagnostics.info,
            environment: {
                protocol: window.location.protocol,
                host: window.location.host,
                jquery: typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'не найден',
                ymaps: typeof ymaps !== 'undefined' ? 'доступен' : 'недоступен'
            }
        };
        
        console.group('📊 Отчет диагностики СДЭК Карт');
        console.log('Полный отчет:', report);
        
        if (report.errors.length > 0) {
            console.error('❌ Найденные ошибки:', report.errors);
        }
        
        if (report.warnings.length > 0) {
            console.warn('⚠️ Предупреждения:', report.warnings);
        }
        
        console.log('ℹ️ Информация:', report.info);
        console.groupEnd();
        
        // Сохраняем отчет в localStorage для отладки
        try {
            localStorage.setItem('cdek_maps_diagnostics', JSON.stringify(report));
            log('info', 'Отчет сохранен в localStorage');
        } catch (e) {
            log('warnings', 'Не удалось сохранить отчет в localStorage', e);
        }
        
        return report;
    }
    
    // Функция для получения отчета диагностики (доступна глобально)
    window.getCdekMapsDiagnostics = function() {
        return generateDiagnosticsReport();
    };
    
    // Функция для очистки диагностики
    window.clearCdekMapsDiagnostics = function() {
        window.cdekMapsDiagnostics = {
            started: false,
            errors: [],
            warnings: [],
            info: []
        };
        try {
            localStorage.removeItem('cdek_maps_diagnostics');
        } catch (e) {
            // Игнорируем ошибки localStorage
        }
        console.log('[СДЭК Maps] Диагностика очищена');
    };
    
    // Автоматически запускаем диагностику при загрузке
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startDiagnostics);
    } else {
        startDiagnostics();
    }
    
    // Отслеживаем глобальные ошибки JavaScript
    window.addEventListener('error', function(e) {
        if (e.filename && e.filename.includes('yandex.ru')) {
            log('errors', 'JavaScript ошибка в Яндекс скрипте', {
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno
            });
        }
    });
    
    // Отслеживаем необработанные промисы
    window.addEventListener('unhandledrejection', function(e) {
        if (e.reason && e.reason.toString().includes('yandex')) {
            log('errors', 'Необработанное отклонение промиса (Яндекс)', e.reason);
        }
    });
    
})();