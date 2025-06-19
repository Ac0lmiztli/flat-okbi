jQuery(document).ready(function($) {
    'use strict';

    function initializeDependentFields() {
        const rcSelectField = '#fok_property_rc_link';
        const sectionSelectField = '#fok_property_section_link';

        if (!$(rcSelectField).length || !$(sectionSelectField).length) {
            console.warn('FlatOkbi: Поля ЖК або Секція не знайдено');
            return;
        }

        const $rcSelect = $(rcSelectField);
        const $sectionSelect = $(sectionSelectField);

        const handleRcChange = function() {
            const rcId = $rcSelect.val();
            console.log('FlatOkbi: Вибрано ЖК ID', rcId);

            // Очищаємо вибір секції
            $sectionSelect.val(null).trigger('change');

            // Отримуємо опції Select2
            let select2Options = $sectionSelect.data('options');

            if (typeof select2Options !== 'object' || select2Options === null) {
                // Пробуємо резервний варіант
                const s2id = $sectionSelect.attr('data-select2-id');
                if (s2id) {
                    select2Options = $(`[data-select2-id="${s2id}"]`).data('options');
                }
            }

            if (typeof select2Options !== 'object' || select2Options === null) {
                console.error('FlatOkbi: Не вдалося отримати налаштування Select2 для Секції.');
                return;
            }

            // Модифікуємо запит
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

            // Перезавантажуємо Select2
            if ($sectionSelect.data('select2')) {
                $sectionSelect.select2('destroy');
            }

            $sectionSelect.select2(select2Options);
        };

        $rcSelect.on('change', handleRcChange);
        handleRcChange(); // одразу при завантаженні
    }

    // Запускаємо після затримки (MetaBox має ініціалізуватись)
    setTimeout(initializeDependentFields, 500);
});
