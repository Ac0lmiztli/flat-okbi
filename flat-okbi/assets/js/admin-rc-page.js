jQuery(document).ready(function($) {
    'use strict';

    $('#fok-trash-rc-content-btn').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Ви впевнені, що хочете перемістити в кошик всі секції та об\'єкти цього ЖК?')) {
            return;
        }

        const btn = $(this);
        const rcId = btn.data('rc-id');
        const nonce = btn.data('nonce');
        const statusP = $('#fok-trash-status');

        btn.prop('disabled', true);
        statusP.text('Видалення...').css('color', 'orange').show();

        $.ajax({
            url: ajaxurl, // ajaxurl - глобальна змінна WordPress
            type: 'POST',
            data: {
                action: 'fok_trash_rc_content',
                rc_id: rcId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    statusP.text(response.data).css('color', 'green');
                } else {
                    statusP.text('Помилка: ' + response.data).css('color', 'red');
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