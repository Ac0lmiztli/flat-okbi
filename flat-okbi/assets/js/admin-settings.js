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

        // Якщо frame вже існує, відкриваємо його знову
        if (frame) {
            frame.open();
            return;
        }

        // Створюємо новий frame для медіа-бібліотеки
        frame = wp.media({
            title: 'Вибрати або завантажити логотип',
            button: {
                text: 'Використати це зображення'
            },
            multiple: false // Дозволити вибір тільки одного файлу
        });

        // Коли зображення вибрано в медіа-бібліотеці
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            
            // Записуємо ID зображення в приховане поле
            $hiddenInput.val(attachment.id);
            // Показуємо прев'ю зображення
            $imagePreview.attr('src', attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url).show();
            // Показуємо кнопку "Видалити"
            $removeButton.show();
        });

        // Відкриваємо вікно медіа-бібліотеки
        frame.open();
    });

    // Обробник кліку на кнопку видалення
    $('body').on('click', '.fok-remove-button', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $fieldContainer = $button.closest('.fok-image-uploader');
        var $imagePreview = $fieldContainer.find('img');
        var $hiddenInput = $fieldContainer.find('input[type="hidden"]');

        // Очищуємо значення та ховаємо елементи
        $hiddenInput.val('');
        $imagePreview.attr('src', '').hide();
        $button.hide();
    });

    // ++ НОВИЙ БЛОК: Логіка для видалення всіх даних ++
    $('#fok-delete-all-data-btn').on('click', function(e) {
        e.preventDefault();
        
        const btn = $(this);
        const statusP = $('#fok-delete-status');

        if (!confirm('Ви впевнені, що хочете видалити ВСІ житлові комплекси, секції, об\'єкти та заявки? Ця дія незворотна.')) {
            return;
        }

        if (!confirm('БУДЬ ЛАСКА, ПІДТВЕРДІТЬ. Всі дані плагіна буде видалено назавжди.')) {
            return;
        }

        btn.prop('disabled', true);
        statusP.text('Видалення...').css('color', 'orange').show();

        $.ajax({
            url: ajaxurl, // ajaxurl - глобальна змінна WordPress
            type: 'POST',
            data: {
                action: 'fok_delete_all_data',
                nonce: fok_importer_page.nonce // Використовуємо nonce, переданий з PHP
            },
            success: function(response) {
                if (response.success) {
                    statusP.text(response.data.message).css('color', 'green');
                    // Оновлюємо сторінку через 3 секунди, щоб очистити кеш та списки
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    statusP.text('Помилка: ' + response.data.message).css('color', 'red');
                    btn.prop('disabled', false);
                }
            },
            error: function() {
                statusP.text('Помилка сервера.').css('color', 'red');
                btn.prop('disabled', false);
            }
        });
    });
});