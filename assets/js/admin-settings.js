jQuery(document).ready(function($) {
    // Ініціалізація вбудованого в WordPress color picker
    $('.fok-color-picker').wpColorPicker();

    // --- Логіка для медіа-завантажувача ---
    var frame;

    // Обробник кліку на кнопку завантаження
    $('body').on('click', '.fok-upload-button', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $fieldContainer = $button.closest('.fok-image-uploader');
        var $imagePreview = $fieldContainer.find('img');
        var $hiddenInput = $fieldContainer.find('input[type="hidden"]');
        var $removeButton = $fieldContainer.find('.fok-remove-button');

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: 'Вибрати або завантажити логотип',
            button: {
                text: 'Використати це зображення'
            },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            
            $hiddenInput.val(attachment.id);
            $imagePreview.attr('src', attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url).show();
            $removeButton.show();
        });

        frame.open();
    });

    // Обробник кліку на кнопку видалення
    $('body').on('click', '.fok-remove-button', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $fieldContainer = $button.closest('.fok-image-uploader');
        var $imagePreview = $fieldContainer.find('img');
        var $hiddenInput = $fieldContainer.find('input[type="hidden"]');

        $hiddenInput.val('');
        $imagePreview.attr('src', '').hide();
        $button.hide();
    });

    // ++ ОНОВЛЕНИЙ БЛОК: Логіка для видалення всіх даних з локалізацією ++
    $('#fok-delete-all-data-btn').on('click', function(e) {
        e.preventDefault();
        
        const btn = $(this);
        const statusP = $('#fok-delete-status');

        // Використовуємо перекладені рядки з об'єкта fok_importer_page
        if (!confirm(fok_importer_page.confirm_delete)) {
            return;
        }

        if (!confirm(fok_importer_page.confirm_delete_final)) {
            return;
        }

        btn.prop('disabled', true);
        statusP.text(fok_importer_page.deleting).css('color', 'orange').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fok_delete_all_data',
                nonce: fok_importer_page.nonce
            },
            success: function(response) {
                if (response.success) {
                    statusP.text(response.data.message).css('color', 'green');
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    statusP.text(fok_importer_page.error_prefix + response.data.message).css('color', 'red');
                    btn.prop('disabled', false);
                }
            },
            error: function() {
                statusP.text(fok_importer_page.server_error).css('color', 'red');
                btn.prop('disabled', false);
            }
        });
    });
});