jQuery(document).ready(function($) {
    'use strict';

    const container = $('#fok-floors-container');
    if (!container.length) return;

    // --- ОНОВЛЕННЯ: Беремо дані з об'єкта, переданого через wp_localize_script ---
    const currentPostId = fok_groups_data.post_id;
    const nonce = fok_groups_data.nonce;
    // ----------------------------------------------------------------------

    /**
     * Завантажує список об'єктів для конкретного рядка поверху.
     * @param {jQuery} $row - jQuery об'єкт рядка (.fok-floor-row).
     */
    function loadPropertiesForFloorRow($row) {
        const floorNumber = $row.find('.fok-floor-number').val();
        const listContainer = $row.find('.fok-floor-objects-list');

        if (floorNumber === '') {
            listContainer.html('<p style="font-style: italic; color: #777;">Вкажіть номер поверху.</p>');
            return;
        }

        listContainer.html('<span class="spinner is-active" style="float:none;"></span>');

        $.ajax({
            url: ajaxurl, // Глобальна змінна WordPress
            type: 'POST',
            data: {
                action: 'fok_get_properties_for_floor',
                nonce: nonce, // Використовуємо nonce, переданий з PHP
                section_id: currentPostId, // Використовуємо ID поста, переданий з PHP
                floor_number: floorNumber
            },
            success: function(response) {
                if (response.success) {
                    listContainer.html(response.data.html);
                } else {
                    listContainer.text('Помилка завантаження.');
                }
            },
            error: function() {
                listContainer.text('Помилка сервера.');
            }
        });
    }

    // --- Ініціалізація та обробники подій ---

    container.sortable({
        handle: '.handle',
        placeholder: 'fok-floor-row-placeholder',
        start: (event, ui) => ui.placeholder.height(ui.item.height())
    });

    $('#fok-add-floor-btn').on('click', function() {
        const template = $('#fok-floor-template').html();
        const $newRow = $(template).appendTo(container);
        loadPropertiesForFloorRow($newRow); // Завантажуємо для нового рядка
    });

    container.on('click', '.fok-delete-floor-btn', function() {
        if (confirm('Ви впевнені, що хочете видалити цей поверх?')) {
            $(this).closest('.fok-floor-row').remove();
        }
    });

    container.on('click', '.fok-upload-image-btn', function(e) {
        e.preventDefault();
        const $row = $(this).closest('.fok-floor-row');
        const $imageIdField = $row.find('.fok-floor-image-id');
        const $previewDiv = $row.find('.fok-image-preview');
        const $removeBtn = $row.find('.fok-remove-image-btn');

        const frame = wp.media({
            title: 'Вибрати зображення',
            button: { text: 'Використати це зображення' },
            multiple: false
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $imageIdField.val(attachment.id);
            $previewDiv.html(`<img src="${attachment.sizes.thumbnail.url}" style="max-width:100px; height:auto;">`);
            $removeBtn.show();
        });

        frame.open();
    });

    container.on('click', '.fok-remove-image-btn', function() {
        const $row = $(this).closest('.fok-floor-row');
        $row.find('.fok-floor-image-id').val('');
        $row.find('.fok-image-preview').empty();
        $(this).hide();
    });

    container.on('blur', '.fok-floor-number', function() {
        loadPropertiesForFloorRow($(this).closest('.fok-floor-row'));
    });

    $('form#post').on('submit', function() {
        const floorsData = [];
        $('.fok-floor-row').each(function() {
            const $row = $(this);
            floorsData.push({
                number: $row.find('.fok-floor-number').val(),
                image: $row.find('.fok-floor-image-id').val(),
                polygons_data: $row.find('.fok-floor-polygons-data').val()
            });
        });
        $('#fok_section_floors_data').val(JSON.stringify(floorsData));
    });

    $('.fok-floor-row').each(function() {
        loadPropertiesForFloorRow($(this));
    });
});