jQuery(document).ready(function($) {
    'use strict';

    function initializeDependentFields() {
        const rcSelectField = '#fok_property_rc_link';
        const sectionSelectField = '#fok_property_section_link';

        if (!$(rcSelectField).length || !$(sectionSelectField).length) {
            return; // Виходимо, якщо полів немає
        }
        
        const $rcSelect = $(rcSelectField);
        const $sectionSelect = $(sectionSelectField);

        const handleRcChange = function() {
            const rcId = $rcSelect.val();

            // Очищуємо будь-яке поточне значення в полі "Секція"
            $sectionSelect.val(null).trigger('change');

            // Отримуємо поточні налаштування Select2, які згенерував MetaBox
            let select2Options = $sectionSelect.data('options');
            if (typeof select2Options !== 'object' || select2Options === null) {
                // Якщо раптом опцій немає, беремо їх з data-select2-id
                // Це запасний варіант для деяких версій MetaBox
                select2Options = $($sectionSelect.data('select2-id')).data('options');
            }
             if (typeof select2Options !== 'object' || select2Options === null) {
                console.error('FlatOkbi: Не вдалося отримати налаштування Select2.');
                return;
            }


            // --- ГОЛОВНА ЛОГІКА ---
            // Ми модифікуємо query_args, які MetaBox буде використовувати для СВОГО AJAX-запиту

            if (rcId && rcId !== '') {
                // Якщо ЖК вибрано, додаємо до запиту meta_query для фільтрації по ID ЖК
                select2Options.ajax_data.field.query_args.meta_query = [{
                    key: 'fok_section_rc_link',
                    value: rcId,
                    compare: '='
                }];
                // Включаємо поле, якщо воно було вимкнено
                $sectionSelect.prop('disabled', false);
            } else {
                // Якщо ЖК не вибрано, вимикаємо поле і можемо скинути фільтр
                delete select2Options.ajax_data.field.query_args.meta_query;
                $sectionSelect.prop('disabled', true);
            }

            // Повністю "руйнуємо" старий екземпляр Select2
            if ($sectionSelect.data('select2')) {
                $sectionSelect.select2('destroy');
            }
            
            // Ініціалізуємо Select2 заново, але вже з НАШИМИ новими, модифікованими параметрами AJAX
            $sectionSelect.select2(select2Options);
        };

        // Запускаємо функцію при зміні ЖК
        $rcSelect.on('change', handleRcChange);

        // Запускаємо функцію при завантаженні сторінки, щоб застосувати фільтр або вимкнути поле
        handleRcChange();
    }

    // Запускаємо наш скрипт з невеликою затримкою, щоб гарантувати,
    // що MetaBox вже ініціалізував свої поля
    setTimeout(initializeDependentFields, 500);
});
