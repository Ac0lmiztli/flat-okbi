jQuery(document).ready(function($) {
    // --- Глобальні змінні ---
    const $body = $('body');
    const $viewer = $('#fok-viewer-fullscreen-container');
    if (!$viewer.length) return;

    // --- Змінні для кешування елементів ---
    const $closeButton = $('#fok-viewer-close');
    const $resultsContainer = $viewer.find('#fok-results-container .fok-list-content');
    const $loader = $viewer.find('#fok-results-container .fok-loader');
    const $filtersForm = $viewer.find('#fok-filters-form');
    const $sidebar = $viewer.find('.fok-list-sidebar');
    const $detailsPanel = $viewer.find('#fok-details-panel');
    const $panelContent = $viewer.find('#fok-panel-content');
    const $panelLoader = $viewer.find('.fok-panel-loader');
    const $rcTitle = $viewer.find('#fok-current-rc-title');
    const $lightbox = $('#fok-lightbox');
    const $lightboxImage = $lightbox.find('.fok-lightbox-content');
    const $lightboxPrev = $lightbox.find('#fok-lightbox-prev');
    const $lightboxNext = $lightbox.find('#fok-lightbox-next');
    const $propertyTypeCheckboxes = $filtersForm.find('input[name="property_types[]"]');

    // --- Змінні стану ---
    let currentRCId = null;
    let lastFetchedPropertyData = null;
    let currentGallery = [];
    let currentImageIndex = 0;
    let touchStartX = 0;
    let xhr;
    let interactiveCanvas = null;
    

    // --- Функції керування каталогом ---
    function openViewer(rcId) {
        if (!rcId) return;
        currentRCId = rcId;
        $body.addClass('fok-viewer-is-open');
        $viewer.addClass('is-visible');
        loadProperties();
    }

    function closeViewer() {
        $body.removeClass('fok-viewer-is-open');
        $viewer.removeClass('is-visible');
        closeDetailsPanel(true);
        $sidebar.removeClass('is-open');
        $resultsContainer.html('');
        $rcTitle.text('');
        currentRCId = null;
    }

    // --- Функції завантаження даних ---
    const loadProperties = debounce(() => {
        if (!currentRCId) return;
        if (xhr && xhr.readyState !== 4) xhr.abort();
        
        const formData = $filtersForm.serialize();
        xhr = $.ajax({
            url: fok_ajax.ajax_url, type: 'POST',
            data: { action: 'fok_filter_properties', nonce: fok_ajax.nonce, rc_id: currentRCId, form_data: formData },
            beforeSend: () => { 
                $loader.show(); 
                $resultsContainer.hide(); 
            },
            success: (response) => {
                if (response.success) {
                    $rcTitle.text(response.data.rc_title);
                    $resultsContainer.html(response.data.html);
                    
                    if (response.data.html.includes('fok-chessboard') === false) {
                        closeDetailsPanel(true);
                    }
                } else {
                    $resultsContainer.html('<p>Сталася помилка. Спробуйте ще раз.</p>');
                }
            },
            error: () => $resultsContainer.html('<p>Помилка сервера.</p>'),
            complete: () => { 
                $loader.hide();
                $resultsContainer.fadeIn(400); 
            }
        });
    }, 500);

    function loadPropertyDetails(propertyId) {
        $panelContent.animate({ opacity: 0 }, 200, function() {
            $panelLoader.show();
            $.ajax({
                url: fok_ajax.ajax_url, type: 'POST',
                data: { action: 'fok_get_property_details', nonce: fok_ajax.nonce, property_id: propertyId },
                success: (response) => {
                    $panelLoader.hide();
                    if (response.success) {
                        lastFetchedPropertyData = response.data;
                        currentGallery = response.data.gallery || [];
                        renderPanelContent(response.data);
                    } else {
                        renderError('Помилка завантаження даних.');
                    }
                },
                error: () => {
                    $panelLoader.hide();
                    renderError('Помилка зв\'язку з сервером.');
                }
            });
        });
    }

    // --- Функції рендерингу та панелей ---
    function openDetailsPanel(propertyId) {
        $('.fok-apartment-cell.active').removeClass('active');
        $viewer.find(`.fok-apartment-cell[data-id="${propertyId}"]`).addClass('active');
        $detailsPanel.addClass('is-open');
        loadPropertyDetails(propertyId);
    }
    
    function closeDetailsPanel(force = false) {
        $detailsPanel.removeClass('is-open');
        $('.fok-apartment-cell.active').removeClass('active');
        lastFetchedPropertyData = null;
        if (force) {
            $panelContent.html('');
        } else {
            setTimeout(() => $panelContent.html(''), 400);
        }
    }

    // ОНОВЛЕНА ФУНКЦІЯ
    function renderPanelContent(data) {
        // 1. Блок галереї (залишається без змін)
        let galleryHtml = '';
        if (data.gallery && data.gallery.length > 0) {
            let thumbnailsHtml = '';
            if (data.gallery.length > 1) {
                data.gallery.forEach((img, index) => {
                    thumbnailsHtml += `<div class="thumb ${index === 0 ? 'active' : ''}" data-full-src="${img.full}"><img src="${img.thumb}" alt="Thumbnail"></div>`;
                });
            }
            galleryHtml = `<div class="fok-panel-gallery"><div class="main-image"><img src="${data.gallery[0].full}" alt="Layout"></div>${data.gallery.length > 1 ? `<div class="thumbnails">${thumbnailsHtml}</div>` : ''}</div>`;
        } else {
            galleryHtml = '<p>Зображення відсутні.</p>';
        }

        // 2. Блок параметрів "Площа", "Поверх" і т.д. (залишається без змін)
        let paramsHtml = '';
        for (const [key, value] of Object.entries(data.params)) {
            if (value) paramsHtml += `<li><span>${key}</span><strong>${value}</strong></li>`;
        }

        // 3. НОВИЙ БЛОК: Створюємо блок для статусу та ціни
        let infoBlockHtml = `<div class="fok-panel-info">`; // Новий контейнер для порядку
        infoBlockHtml += `<div class="fok-panel-status status-${data.status_slug}">${data.status_name}</div>`;
        if (data.status_slug === 'vilno') {
            infoBlockHtml += `<div class="fok-panel-price"><div class="total-price">${data.total_price} ${data.currency}</div><div class="price-per-m2">${data.price_per_m2} ${data.currency} / м²</div></div>`;
        }
        infoBlockHtml += `</div>`;

        // 4. Блок з кнопкою "Забронювати" (залишається без змін)
        let bookingHtml = data.status_slug === 'vilno' ? `<div class="fok-panel-actions"><button class="fok-booking-btn-show fok-booking-button">Забронювати</button></div>` : '';

        // 5. ФІНАЛЬНА ЗБІРКА: збираємо всі блоки у правильному порядку
        // Заголовок (headerHtml) повністю видалено
        // Новий блок (infoBlockHtml) додано ПІСЛЯ галереї
        const contentHtml = `
            ${galleryHtml}
            ${infoBlockHtml}
            <ul class="fok-panel-params">${paramsHtml}</ul>
            ${bookingHtml}
        `;
        
        $panelContent.html(contentHtml).animate({ opacity: 1 }, 250);
    }
    
    function renderBookingForm(data) {
        const formHtml = `<div class="fok-booking-form-wrapper"><button type="button" class="fok-form-back-btn">&larr; Назад до об'єкту</button><form id="fok-booking-form"><p>Заявка на ${data.type_name.toLowerCase()} №${data.property_number}</p><div class="form-group"><label for="b_name">Ваше ім'я</label><input type="text" id="b_name" required></div><div class="form-group"><label for="b_phone">Телефон</label><input type="tel" id="b_phone" required></div><div id="booking-form-message"></div><button type="submit" class="fok-booking-button">Надіслати заявку</button></form></div>`;
        $panelContent.animate({ opacity: 0 }, 200, function() {
            $(this).html(formHtml).animate({ opacity: 1 }, 250);
        });
    }

    function renderError(message) { $panelContent.html(`<p class="error-message">${message}</p>`).animate({ opacity: 1 }, 250); }

    function updateRoomFilterState() {
        const isApartmentChecked = $propertyTypeCheckboxes.filter('[value="apartment"]').is(':checked');
        $filtersForm.find('[data-dependency="apartment"]').toggle(isApartmentChecked);
    }

    // --- Функції Лайтбоксу ---
    function openLightbox(clickedImageSrc) {
        currentImageIndex = currentGallery.findIndex(img => img.full === clickedImageSrc);
        if (currentImageIndex === -1) currentImageIndex = 0;
        updateLightboxImage();
        $lightbox.addClass('is-open');
    }
    function updateLightboxImage() {
        if(currentGallery.length === 0) return;
        $lightboxImage.attr('src', currentGallery[currentImageIndex].full);
        updateLightboxNav();
    }
    function updateLightboxNav() {
        $lightboxPrev.toggle(currentGallery.length > 1);
        $lightboxNext.toggle(currentGallery.length > 1);
    }
    function showNextImage() {
        if (currentGallery.length <= 1) return;
        currentImageIndex = (currentImageIndex + 1) % currentGallery.length;
        updateLightboxImage();
    }
    function showPrevImage() {
        if (currentGallery.length <= 1) return;
        currentImageIndex = (currentImageIndex - 1 + currentGallery.length) % currentGallery.length;
        updateLightboxImage();
    }
    function handleSwipe(event) {
        const swipeThreshold = 50;
        const diffX = touchStartX - event.changedTouches[0].clientX;
        if (Math.abs(diffX) > swipeThreshold) {
            if (diffX > 0) showNextImage();
            else showPrevImage();
        }
    }


    // --- Обробники подій ---
    $body.on('click', '.fok-open-viewer', function(e) {
        e.preventDefault();
        openViewer($(this).data('rc-id'));
    });

    $closeButton.on('click', closeViewer);
    
    $viewer.on('click', '.fok-view-modes button', function() {
        const $button = $(this);
        if ($button.hasClass('active')) return; // Нічого не робити, якщо режим вже активний

        const mode = $button.data('mode');
        $button.addClass('active').siblings().removeClass('active');
        
        $('.fok-viewer-content > div').removeClass('active');
        const $modeContainer = $('#fok-' + mode + '-mode').addClass('active');

        // Якщо перейшли в інтерактивний режим і він ще не завантажений
        if (mode === 'interactive' && !interactiveCanvas) {
            loadInteractiveGenplan(currentRCId, $modeContainer);
        }
    });

    $viewer.on('click', '.fok-room-buttons .room-btn', function() {
        $(this).addClass('active').siblings().removeClass('active');
        $filtersForm.find('#filter-rooms').val($(this).data('value')).trigger('change');
    });

    $filtersForm.on('change keyup', 'input, select', loadProperties);
    
    // Initial state for room filter
    updateRoomFilterState(); 
    $propertyTypeCheckboxes.on('change', updateRoomFilterState);


    $resultsContainer.on('click', '.fok-apartment-cell', function(e) {
        e.preventDefault();
        openDetailsPanel($(this).data('id'));
    });

    $detailsPanel.on('click', '#fok-panel-close', () => closeDetailsPanel());

    $panelContent.on('click', '.fok-panel-gallery .thumb', function() {
        const newSrc = $(this).data('full-src');
        $(this).addClass('active').siblings().removeClass('active');
        $(this).closest('.fok-panel-gallery').find('.main-image img').attr('src', newSrc);
    });
    
    $panelContent.on('click', '.fok-panel-gallery .main-image', function() {
        if(currentGallery && currentGallery.length > 0) {
           openLightbox($(this).find('img').attr('src'));
        }
    });

    $panelContent.on('click', '.fok-booking-btn-show', () => renderBookingForm(lastFetchedPropertyData));
    
    $panelContent.on('click', '.fok-form-back-btn', () => renderPanelContent(lastFetchedPropertyData));
    
    $panelContent.on('submit', '#fok-booking-form', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $message = $form.find('#booking-form-message');
        const $submitBtn = $form.find('button[type="submit"]');

        $message.slideUp();
        $submitBtn.prop('disabled', true).text('Надсилаємо...');

        $.ajax({
            url: fok_ajax.ajax_url, type: 'POST',
            data: {
                action: 'fok_submit_booking', nonce: fok_ajax.nonce,
                property_id: lastFetchedPropertyData.id,
                name: $form.find('#b_name').val(),
                phone: $form.find('#b_phone').val()
            },
            success: (response) => {
                const messageClass = response.success ? 'success' : 'error';
                $message.removeClass('success error').addClass(messageClass).text(response.data).slideDown();
                if (response.success) {
                    // We need to reload details to get the new status
                    setTimeout(() => loadPropertyDetails(lastFetchedPropertyData.id), 3000);
                } else {
                    $submitBtn.prop('disabled', false).text('Надіслати заявку');
                }
            },
            error: () => {
                $message.removeClass('success').addClass('error').text('Помилка зв\'язку.').slideDown();
                $submitBtn.prop('disabled', false).text('Надіслати заявку');
            }
        });
    });

    // Події лайтбоксу
    $lightbox.on('click', function(e) {
        if (e.target === this || $(e.target).is('#fok-lightbox-close')) {
            $lightbox.removeClass('is-open');
        }
    });
    $lightboxPrev.on('click', showPrevImage);
    $lightboxNext.on('click', showNextImage);

    $lightbox[0].addEventListener('touchstart', (e) => { touchStartX = e.touches[0].clientX; }, {passive: true});
    $lightbox[0].addEventListener('touchend', (e) => { handleSwipe(e); });

    $(document).on('keydown', function(e) {
        if (!$viewer.hasClass('is-visible')) return;
        if (e.key === "Escape") {
            if ($lightbox.hasClass('is-open')) {
                $lightbox.removeClass('is-open');
            } else {
                closeViewer();
            }
        }
        if ($lightbox.hasClass('is-open')) {
            if (e.key === "ArrowLeft") showPrevImage();
            if (e.key === "ArrowRight") showNextImage();
        }
    });

    $('#fok-mobile-filter-trigger').on('click', () => $sidebar.addClass('is-open'));
    $('#fok-sidebar-close').on('click', () => $sidebar.removeClass('is-open'));
    
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function loadInteractiveGenplan(rcId, container) {
        const $loader = $('<div class="fok-loader"><div class="spinner"></div></div>');
        const $canvasContainer = $('<div id="fok-interactive-canvas-container"></div>');
        const $canvasEl = $('<canvas id="fok-genplan-canvas"></canvas>');
        
        container.html('').append($loader).append($canvasContainer);
        $canvasContainer.append($canvasEl);

        $.ajax({
            url: fok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fok_get_genplan_data',
                nonce: fok_ajax.nonce,
                rc_id: rcId
            },
            success: function(response) {
                $loader.remove();
                if (response.success) {
                    const data = response.data;
                    
                    interactiveCanvas = new fabric.Canvas('fok-genplan-canvas');
                    
                    fabric.Image.fromURL(data.image_url, function(img) {
                        const scale = $canvasContainer.width() / img.width;
                        const canvasHeight = img.height * scale;
                        
                        interactiveCanvas.setDimensions({ width: $canvasContainer.width(), height: canvasHeight });
                        interactiveCanvas.setBackgroundImage(img, interactiveCanvas.renderAll.bind(interactiveCanvas), {
                            scaleX: interactiveCanvas.width / img.width,
                            scaleY: interactiveCanvas.height / img.height
                        });

                        // Малюємо полігони
                        if(data.polygons && Array.isArray(data.polygons)) {
                            data.polygons.forEach(polyData => {
                                const polygon = new fabric.Polygon(polyData.points, {
                                    fill: 'rgba(0, 115, 170, 0.4)',
                                    stroke: '#005a87',
                                    strokeWidth: 2,
                                    objectCaching: false,
                                    selectable: false,
                                    hoverCursor: 'pointer',
                                    // Додаємо кастомні дані
                                    section_id: polyData.section_id,
                                    section_name: polyData.section_name
                                });
                                interactiveCanvas.add(polygon);
                            });
                        }
                    });

                } else {
                    container.html('<p>' + response.data + '</p>');
                }
            },
            error: function() {
                $loader.remove();
                container.html('<p>Помилка завантаження даних генплану.</p>');
            }
        });
    }
});
