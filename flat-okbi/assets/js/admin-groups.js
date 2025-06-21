// assets/js/admin-groups.js
jQuery(document).ready(function($) {
    'use strict';

    const container = $('#fok-floors-container');
    if (!container.length) return;

    // Робимо рядки сортованими
    container.sortable({
        handle: '.handle',
        placeholder: 'fok-floor-row-placeholder',
        start: function(event, ui) {
            ui.placeholder.height(ui.item.height());
        }
    });

    // Додавання нового рядка
    $('#fok-add-floor-btn').on('click', function() {
        const template = $('#fok-floor-template').html();
        container.append(template);
    });

    // Видалення рядка
    container.on('click', '.fok-delete-floor-btn', function() {
        if (confirm('Ви впевнені, що хочете видалити цей поверх?')) {
            $(this).closest('.fok-floor-row').remove();
        }
    });

    // Завантаження зображення
    container.on('click', '.fok-upload-image-btn', function(e) {
        e.preventDefault();
        const row = $(this).closest('.fok-floor-row');
        const imageIdField = row.find('.fok-floor-image-id');
        const previewDiv = row.find('.fok-image-preview');
        const removeBtn = row.find('.fok-remove-image-btn');

        const frame = wp.media({
            title: 'Вибрати зображення',
            button: { text: 'Використати це зображення' },
            multiple: false
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            imageIdField.val(attachment.id);
            previewDiv.html('<img src="' + attachment.sizes.thumbnail.url + '" style="max-width:100px; height:auto;">');
            removeBtn.show();
        });

        frame.open();
    });

    // Видалення зображення
    container.on('click', '.fok-remove-image-btn', function() {
        const row = $(this).closest('.fok-floor-row');
        row.find('.fok-floor-image-id').val('');
        row.find('.fok-image-preview').empty();
        $(this).hide();
    });

    // Перед збереженням посту збираємо всі дані в одне приховане поле
    $('form#post').on('submit', function() {
        const floorsData = [];
        $('.fok-floor-row').each(function() {
            const row = $(this);
            const floor = {
                number: row.find('.fok-floor-number').val(),
                image: row.find('.fok-floor-image-id').val(),
                polygons_data: row.find('.fok-floor-polygons-data').val()
            };
            floorsData.push(floor);
        });

        // Зберігаємо масив як JSON-рядок у наше головне приховане поле
        $('#fok_section_floors_data').val(JSON.stringify(floorsData));
    });
});
