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
});
