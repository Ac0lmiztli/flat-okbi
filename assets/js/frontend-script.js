jQuery(document).ready(function($) {
    const $container = $('.fok-viewer-container');
    const $panel = $('#fok-details-panel'); 
    const $panelContentWrapper = $('#fok-panel-content-wrapper');
    const $panelContent = $('#fok-panel-content');
    const $lightbox = $('#fok-lightbox');
    const $lightboxImage = $('.fok-lightbox-content', $lightbox);
    let lastFetchedApartmentData = null;

    // --- ОБРОБНИКИ ПОДІЙ ---

    // Перемикання режимів
    $container.on('click', '.fok-view-modes button', function() {
        var mode = $(this).data('mode');
        $(this).addClass('active').siblings().removeClass('active');
        $('.fok-viewer-content > div').removeClass('active');
        $('#fok-' + mode + '-mode').addClass('active');
        if (mode === 'list') {
            triggerApartmentFilter();
        }
    });

    // Фільтри
    $container.on('click', '.fok-room-buttons .room-btn', function() {
        $(this).addClass('active').siblings().removeClass('active');
        $('#filter-rooms').val($(this).data('value')).trigger('change');
    });

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    const debouncedFilter = debounce(() => {
        if ($('#fok-list-mode').hasClass('active')) {
            triggerApartmentFilter();
        }
    }, 500);

    $container.on('change keyup', '#fok-filters-form select, #fok-filters-form input', debouncedFilter);
    $container.on('change', '#rc-switcher', debouncedFilter);

    // Клік по квартирі
    $container.on('click', '.fok-apartment-cell', function(e) {
        e.preventDefault();
        $('.fok-apartment-cell.active').removeClass('active');
        $(this).addClass('active');
        openDetailsPanel($(this).data('id'));
    });
    
    // Закриття панелі
    $panel.on('click', '#fok-panel-close', closeDetailsPanel);

    // Клік "Забронювати"
    $panelContent.on('click', '.fok-booking-btn-show', function(e) {
        e.preventDefault();
        renderBookingForm(lastFetchedApartmentData);
    });

    // Клік "назад" у формі
    $panelContent.on('click', '.fok-form-back-btn', function(e) {
        e.preventDefault();
        renderPanelContent(lastFetchedApartmentData, false);
    });
    
    // Відправка форми
    $panelContent.on('submit', '#fok-booking-form', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $message = $('#booking-form-message', $form);
        const $submitBtn = $('button[type="submit"]', $form);
        
        $submitBtn.prop('disabled', true).text('Надсилаємо...');
        
        setTimeout(() => {
            $message.removeClass('error').addClass('success').text('Дякуємо! Ваша заявка прийнята.').slideDown();
            setTimeout(() => renderPanelContent(lastFetchedApartmentData, false), 2000);
        }, 1000);
    });

    // Галерея
    $panelContent.on('click', '.fok-panel-gallery .thumb', function() {
        const $this = $(this);
        const newSrc = $this.data('full-src');
        if (!newSrc) return;
        $this.addClass('active').siblings().removeClass('active');
        $this.closest('.fok-panel-gallery').find('.main-image img').attr('src', newSrc);
    });

    // Лайтбокс
    $panelContent.on('click', '.fok-panel-gallery .main-image', function() {
        const src = $('img', this).attr('src');
        if (src) {
            $lightboxImage.attr('src', src);
            $lightbox.addClass('is-open');
        }
    });

    $lightbox.on('click', function(e) {
        if (e.target === this || $(e.target).hasClass('fok-lightbox-close')) {
            $lightbox.removeClass('is-open');
        }
    });

    // --- ФУНКЦІЇ РЕНДЕРИНГУ ---

    function openDetailsPanel(apartmentId) {
        const isAlreadyOpen = $panel.hasClass('is-open');
        $panel.addClass('is-open');

        const fetchAndRender = () => {
             $.ajax({
                url: fok_ajax.ajax_url, type: 'POST',
                data: { action: 'fok_get_apartment_details', nonce: fok_ajax.nonce, apartment_id: apartmentId },
                success: response => {
                    if (response.success) {
                        lastFetchedApartmentData = response.data;
                        renderPanelContent(response.data, true);
                    } else {
                        renderError('Помилка завантаження даних.');
                    }
                },
                error: () => renderError('Помилка зв\'язку з сервером.')
            });
        };
        
        if (isAlreadyOpen) {
            $panelContent.animate({ opacity: 0 }, 200, fetchAndRender);
        } else {
            $panelContent.css({ opacity: 0 });
            fetchAndRender();
        }
    }
    
    function closeDetailsPanel() {
        $panel.removeClass('is-open');
        $('.fok-apartment-cell.active').removeClass('active');
        lastFetchedApartmentData = null;
        setTimeout(() => $panelContent.html(''), 400); 
    }

    function renderPanelContent(data, withAnimation = true) {
        let galleryHtml = '';
        if (data.gallery && data.gallery.length > 0) {
            let thumbnailsHtml = '';
            if (data.gallery.length > 1) {
                data.gallery.forEach((img, index) => {
                    thumbnailsHtml += `<div class="thumb ${index === 0 ? 'active' : ''}" data-full-src="${img.full}"><img src="${img.thumb}" alt="Thumbnail"></div>`;
                });
            }
            galleryHtml = `<div class="fok-panel-gallery"><div class="main-image"><img src="${data.gallery[0].full}" alt="Apartment Layout"></div>${data.gallery.length > 1 ? `<div class="thumbnails">${thumbnailsHtml}</div>` : ''}</div>`;
        }
        
        let paramsHtml = '';
        for (const [key, value] of Object.entries(data.params)) {
            if(value) {
                paramsHtml += `<li><span>${key}</span><strong>${value}</strong></li>`;
            }
        }

        let headerHtml = `<div class="fok-panel-header"><div class="fok-panel-status status-${data.status_slug}">${data.status_name}</div>`;
        if (data.status_slug === 'vilno') {
            headerHtml += `<div class="fok-panel-price"><div class="total-price">${data.total_price} ${data.currency}</div><div class="price-per-m2">${data.price_per_m2} ${data.currency} / м²</div></div>`;
        }
        headerHtml += `</div>`;
        
        let bookingHtml = '';
        if (data.status_slug === 'vilno') {
            bookingHtml = `<div class="fok-panel-actions"><button class="fok-booking-btn-show fok-booking-button">Забронювати</button></div>`;
        }

        const contentHtml = `${galleryHtml}${headerHtml}<ul class="fok-panel-params">${paramsHtml}</ul>${bookingHtml}`;
        
        $panelContent.html(contentHtml);
        if (withAnimation) {
            $panelContent.animate({ opacity: 1 }, 250);
        } else {
            $panelContent.css('opacity', 1);
        }
    }

    function renderBookingForm(data) {
        const formHtml = `<div class="fok-booking-form-wrapper"><form id="fok-booking-form"><button type="button" class="fok-form-back-btn">&larr; Назад до квартири</button><p>Заявка на квартиру №${data.apartment_number}</p><div class="form-group"><label for="b_name">Ваше ім'я</label><input type="text" id="b_name" required></div><div class="form-group"><label for="b_phone">Телефон</label><input type="tel" id="b_phone" required></div><div id="booking-form-message"></div><button type="submit" class="fok-booking-button">Надіслати заявку</button></form></div>`;
        $panelContent.animate({ opacity: 0 }, 200, function() {
            $(this).html(formHtml).animate({ opacity: 1 }, 250);
        });
    }

    function renderError(message) {
        $panelContent.html(`<p>${message}</p>`).animate({ opacity: 1 }, 250);
    }

    // --- ФУНКЦІЯ ФІЛЬТРАЦІЇ ---
    function triggerApartmentFilter() {
        const $resultsContainer = $('#fok-results-container');
        const $content = $('.fok-list-content', $resultsContainer);
        const $loader = $('.fok-loader', $resultsContainer);
        const $formData = $('#fok-filters-form').serializeArray();
        
        $formData.push({name: 'rc_id', value: $('#rc-switcher').val()});
        $loader.show();
        $content.html('');

        $.ajax({
            url: fok_ajax.ajax_url, type: 'POST',
            data: { action: 'fok_filter_apartments', nonce: fok_ajax.nonce, form_data: $formData },
            success: response => {
                if (response.success) {
                    $content.html(response.data.html);
                } else {
                    $content.html('<p>Виникла помилка. Спробуйте ще раз.</p>');
                }
            },
            error: () => $content.html('<p>Помилка зв\'язку з сервером.</p>'),
            complete: () => $loader.hide()
        });
    }

    // Ініціалізація
    if ($container.length) {
       $('.fok-view-modes button[data-mode="list"]').addClass('active');
       $('#fok-list-mode').addClass('active');
       triggerApartmentFilter();
    }
});
