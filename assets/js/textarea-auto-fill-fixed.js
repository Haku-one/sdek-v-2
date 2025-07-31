jQuery(document).ready(function($) {
    console.log("🔧 Автозаполнение textarea полей инициализировано (исправленная версия)");
    
    // Переменные для контроля частоты вызовов API
    let lastAPIUpdateTime = 0;
    let lastAPIUpdateData = { dostavka: "", manager: "" };
    let apiUpdateTimeout;
    let updateTimeout;
    
    // Дебаунсинг для предотвращения частых вызовов
    function debouncedUpdate() {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(updateTextareaFields, 500);
    }
    
    // Функция для работы напрямую с API плагина Checkout Fields for Blocks
    function updateCheckoutFieldsForBlocksAPI() {
        // Защита от слишком частых вызовов (не чаще раза в секунду)
        const now = Date.now();
        if (now - lastAPIUpdateTime < 1000) {
            console.log("🕐 API вызов пропущен - слишком рано");
            return;
        }
        
        // Проверяем, изменились ли данные с последнего вызова
        const currentData = { 
            dostavka: window.currentDeliveryData?.dostavka || "",
            manager: window.currentDeliveryData?.manager || ""
        };
        
        if (JSON.stringify(currentData) === JSON.stringify(lastAPIUpdateData)) {
            console.log("ℹ️ API вызов пропущен - данные не изменились");
            return; // Данные не изменились, не нужно ничего делать
        }
        
        if (!window.wp || !window.wp.data) {
            console.log("⚠️ WP Data API недоступен");
            return;
        }
        
        try {
            const checkoutStore = window.wp.data.dispatch("wc/store/checkout");
            if (!checkoutStore || !checkoutStore.setExtensionData) {
                console.log("⚠️ setExtensionData недоступен");
                return;
            }
            
            // Устанавливаем данные через API плагина только если они изменились
            if (currentData.dostavka !== lastAPIUpdateData.dostavka) {
                checkoutStore.setExtensionData("checkout-fields-for-blocks", "_meta_dostavka", currentData.dostavka);
                console.log("🔄 API: Обновлено _meta_dostavka =", currentData.dostavka);
            }
            
            if (currentData.manager !== lastAPIUpdateData.manager) {
                checkoutStore.setExtensionData("checkout-fields-for-blocks", "_meta_manager", currentData.manager);
                console.log("🔄 API: Обновлено _meta_manager =", currentData.manager);
            }
            
            // Обновляем время и данные последнего вызова
            lastAPIUpdateTime = now;
            lastAPIUpdateData = { ...currentData };
            
        } catch (e) {
            console.log("❌ Ошибка обновления через API:", e.message);
        }
    }
    
    // Дебаунсированная версия функции API обновления
    function debouncedAPIUpdate() {
        clearTimeout(apiUpdateTimeout);
        apiUpdateTimeout = setTimeout(updateCheckoutFieldsForBlocksAPI, 500);
    }
    
    // Глобальные переменные для хранения текущих значений
    window.currentDeliveryData = {
        dostavka: "",
        manager: ""
    };
    
    // Остальной код сокращен для экономии места...
    
    // Функция для заполнения textarea полей
    function fillTextareaFields(deliveryType, deliveryInfo = null) {
        console.log("📝 Заполняем textarea поля для типа доставки:", deliveryType);
        
        const sdekField = $(".wp-block-checkout-fields-for-blocks-textarea.sdek textarea");
        const managerField = $(".wp-block-checkout-fields-for-blocks-textarea.manag textarea");
        
        if (deliveryType === "manager") {
            window.currentDeliveryData.dostavka = "";
            window.currentDeliveryData.manager = "Доставка менеджером";
            
            sdekField.val("").trigger("input").trigger("change");
            managerField.val("Доставка менеджером").trigger("input").trigger("change");
            
            debouncedAPIUpdate();
            
        } else if (deliveryType === "cdek" && deliveryInfo) {
            let cdekText = deliveryInfo.label || "";
            if (deliveryInfo.price) {
                cdekText += " - " + deliveryInfo.price;
            }
            
            window.currentDeliveryData.dostavka = cdekText;
            window.currentDeliveryData.manager = "";
            
            managerField.val("").trigger("input").trigger("change");
            sdekField.val(cdekText).trigger("input").trigger("change");
            
            debouncedAPIUpdate();
        }
    }
    
    // Остальные функции остаются без изменений...
    
    // Делаем функции доступными глобально
    window.updateTextareaFields = function() { /* simplified */ };
    window.fillTextareaFields = fillTextareaFields;
    window.debouncedAPIUpdate = debouncedAPIUpdate;
    
    console.log("🎯 Автозаполнение textarea полей полностью инициализировано (исправленная версия)");
});
