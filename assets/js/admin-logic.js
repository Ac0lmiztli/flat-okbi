jQuery(document).ready(function($) {
    'use strict';

    function initializeDependentFields() {
        const rcSelectField = '#fok_property_rc_link';
        const sectionSelectField = '#fok_property_section_link';

        if (!$(rcSelectField).length || !$(sectionSelectField).length) {
            return;
        }

        const $rcSelect = $(rcSelectField);
        const $sectionSelect = $(sectionSelectField);

        /**
         * Ця функція налаштовує поле Секції (його джерело даних) на основі ID обраного ЖК.
         * @param {string} rcId - ID житлового комплексу.
         */
        const configureSectionField = function(rcId) {
            let select2Options = $sectionSelect.data('options');
            if (typeof select2Options !== 'object' || select2Options === null) {
                const s2id = $sectionSelect.attr('data-select2-id');
                if (s2id) { select2Options = $(`[data-select2-id="${s2id}"]`).data('options'); }
            }
            if (typeof select2Options !== 'object' || select2Options === null) { return; }

            // Модифікуємо AJAX-запит для поля Секція
            if (rcId && rcId !== '') {
                select2Options.ajax_data.field.query_args.meta_query = [{
                    key: 'fok_section_rc_link',
                    value: rcId,
                    compare: '='
                }];
                $sectionSelect.prop('disabled', false);
            } else {
                delete select2Options.ajax_data.field.query_args.meta_query;
                $sectionSelect.prop('disabled', true);
            }

            // Переініціалізуємо поле Select2 з новими налаштуваннями
            if ($sectionSelect.data('select2')) {
                $sectionSelect.select2('destroy');
            }
            $sectionSelect.select2(select2Options);
        };

        /**
         * Обробник події: спрацьовує, коли користувач ВРУЧНУ змінює ЖК.
         */
        $rcSelect.on('change', function() {
            const newRcId = $(this).val();
            
            // 1. Очищуємо значення поля Секція, оскільки ЖК змінився.
            $sectionSelect.val(null).trigger('change');
            
            // 2. Переналаштовуємо поле Секція для нового ЖК.
            configureSectionField(newRcId);
        });

        /**
         * Початкове налаштування: спрацьовує один раз при завантаженні сторінки.
         * Налаштовує поле Секція, але НЕ очищує його значення.
         */
        const initialRcId = $rcSelect.val();
        if (initialRcId) {
            configureSectionField(initialRcId);
        }
    }

    // Запускаємо з невеликою затримкою, щоб гарантувати, що MetaBox вже ініціалізував свої поля.
    setTimeout(initializeDependentFields, 500);
});