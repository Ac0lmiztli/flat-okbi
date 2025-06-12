jQuery(document).ready(function($) {
    const $container = $('.fok-viewer-container');

    // Перемикання режимів відображення (Інтерактив / Список)
    $container.on('click', '.fok-view-modes button', function() {
        var $this = $(this);
        var mode = $this.data('mode');
        $this.addClass('active').siblings().removeClass('active');
        $('#fok-' + mode + '-mode', $container).addClass('active').siblings().removeClass('active');

        if (mode === 'list') {
            triggerApartmentFilter();
        }
    });

    // --- Обробники фільтрів ---
    $container.on('click', '.fok-room-buttons .room-btn', function() {
        var $this = $(this);
        $this.addClass('active').siblings().removeClass('active');
        $('#filter-rooms').val($this.data('value')).trigger('change');
    });

    $container.on('change', '#fok-filters-form select, #fok-filters-form input, #rc-switcher', function() {
        if ($('#fok-list-mode').hasClass('active')) {
            triggerApartmentFilter();
        }
    });

    // Функція для запуску фільтрації
    function triggerApartmentFilter() {
        const $form = $('#fok-filters-form', $container);
        if ($form.length === 0) return;

        let formData = $form.serializeArray();
        let rc_id = $('#rc-switcher', $container).val();
        
        formData.push({name: 'rc_id', value: rc_id});

        $.ajax({
            url: fok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fok_filter_apartments',
                nonce: fok_ajax.nonce,
                form_data: formData
            },
            beforeSend: function() {
                $('#fok-results-container .fok-loader').show();
            },
            success: function(response) {
                if (response.success) {
                    $('#fok-results-container .fok-list-content').html(response.data.html);
                } else {
                     $('#fok-results-container .fok-list-content').html('<p>Помилка: ' + response.data.message + '</p>');
                }
            },
            error: function() {
                 $('#fok-results-container .fok-list-content').html('<p>Сталася помилка AJAX. Спробуйте пізніше.</p>');
            },
            complete: function() {
                $('#fok-results-container .fok-loader').hide();
            }
        });
    }

    // Ініціалізуємо початковий вигляд
    $('.fok-view-modes button', $container).first().trigger('click');
});
