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

    function loadProperties() {
        if (!currentRCId) return;
        if (xhr && xhr.readyState !== 4) xhr.abort();

        xhr = $.ajax({
            url: fok_ajax.ajax_url,
            type: 'POST',
            data: { action: 'fok_filter_properties', nonce: fok_ajax.nonce, rc_id: currentRCId },
            beforeSend: () => {
                $loader.addClass('is-loading');
                $resultsContainer.hide();
            },
            success: (response) => {
                if (response.success) {
                    $rcTitle.text(response.data.rc_title);
                    renderChessboard(response.data.sections);
                    applyFilters();
                    initScrollHints();
                } else {
                    $resultsContainer.html('<p>Сталася помилка. Спробуйте ще раз.</p>');
                }
            },
            error: () => $resultsContainer.html('<p>Помилка сервера.</p>'),
            complete: () => {
                $loader.removeClass('is-loading');
                $resultsContainer.fadeIn(400);
            }
        });
    }

    /**
     * --- ФІНАЛЬНА ВЕРСІЯ РЕНДЕРИНГУ: Номери поверхів є частиною сітки ---
     */
    function renderChessboard(sections) {
        let html = '';
        if (sections && Object.keys(sections).length > 0) {
            html += '<div class="fok-chessboard-container">';
            for (const sectionId in sections) {
                const section = sections[sectionId];
                if (!section.properties || section.properties.length === 0) continue;

                // Визначаємо діапазон поверхів, включно з негативними
                const floors = section.properties.map(p => p.floor);
                const minFloor = Math.min(...floors);
                const maxFloor = Math.max(...floors);
                const totalRows = maxFloor - minFloor + 1;

                const totalCols = section.grid_columns || 10;
                
                html += `<div class="fok-section-block">
                            <h4>${section.name}</h4>
                            <div class="fok-chessboard-grid-wrapper">
                                <div class="fok-chessboard-grid" style="--grid-rows: ${totalRows}; --grid-cols: ${totalCols};">`;
                
                // Рендеримо мітки поверхів як елементи першої колонки сітки
                for (let floorNum = maxFloor; floorNum >= minFloor; floorNum--) {
                    if (floorNum === 0) continue; // Пропускаємо нульовий поверх
                    const rowNum = maxFloor - floorNum + 1;
                    html += `<div class="fok-floor-label" style="grid-column: 1; grid-row: ${rowNum};">${floorNum}</div>`;
                }

                // Рендеримо об'єкти нерухомості
                section.properties.forEach(property => {
                    html += renderPropertyCell(property, maxFloor);
                });

                html += '</div></div></div>'; // chessboard-grid, wrapper, section-block
            }
            html += '</div>'; // chessboard-container
        } else {
            html = '<p>Для цього ЖК ще не додано об\'єктів.</p>';
        }
        $resultsContainer.html(html);
    }
    
    function renderPropertyCell(property, maxFloor) {
        let cellContent = '';
        const cellClass = `fok-apartment-cell cell-type-${property.type}`;
        
        switch (property.type) {
            case 'apartment': cellContent = property.rooms; break;
            case 'commercial_property': cellContent = '<span class="fok-cell-icon" title="Комерція">К</span>'; break;
            case 'parking_space': cellContent = '<span class="fok-cell-icon" title="Паркінг">П</span>'; break;
            case 'storeroom': cellContent = '<span class="fok-cell-icon" title="Комора">Т</span>'; break;
        }

        const discountIndicator = property.has_discount ? `<span class="fok-cell-discount" title="На цей об\'єкт діє знижка">%</span>` : '';
        
        // Розрахунок позиції в сітці
        const rowNum = maxFloor - (property.grid_y_start + property.grid_y_span - 1) + 1;
        const colNum = property.grid_x_start + 1; // +1, бо перша колонка для номерів поверхів
        
        const gridStyles = `
            grid-column: ${colNum} / span ${property.grid_x_span};
            grid-row: ${rowNum} / span ${property.grid_y_span};
        `;

        return `<div class="${cellClass}" 
                    style="${gridStyles}"
                    data-id="${property.id}"
                    data-type="${property.type}"
                    data-rooms="${property.rooms}"
                    data-area="${property.area}"
                    data-floor="${property.floor}"
                    data-status="${property.status}">
                    ${discountIndicator}
                    <span class="fok-cell-area">${property.area} м&sup2;</span>
                    <span class="fok-cell-rooms status-${property.status}">${cellContent}</span>
                </div>`;
    }

    function updateScrollHints(gridElement) {
        const wrapper = $(gridElement).parent();
        const scrollLeft = gridElement.scrollLeft;
        const scrollWidth = gridElement.scrollWidth;
        const clientWidth = gridElement.clientWidth;

        wrapper.toggleClass('is-scrollable-start', scrollLeft > 5);
        wrapper.toggleClass('is-scrollable-end', scrollLeft < (scrollWidth - clientWidth) - 5);
    }

    function initScrollHints() {
        $('.fok-chessboard-grid').each(function() {
            if (this.scrollWidth > this.clientWidth) {
                updateScrollHints(this);
            }
        });
    }

    function applyFilters() {
        const areaFromValue = $('#filter-area-from').val();
        const areaToValue = $('#filter-area-to').val();
        const floorFromValue = $('#filter-floor-from').val();
        const floorToValue = $('#filter-floor-to').val();
        const filters = {
            rooms: $('#filter-rooms').val(),
            area_from: areaFromValue === '' ? -Infinity : parseFloat(areaFromValue),
            area_to: areaToValue === '' ? Infinity : parseFloat(areaToValue),
            floor_from: floorFromValue === '' ? -Infinity : parseInt(floorFromValue, 10),
            floor_to: floorToValue === '' ? Infinity : parseInt(floorToValue, 10),
            status: $('#filter-status-toggle').is(':checked') ? 'vilno' : '',
            types: $('input[name="property_types[]"]:checked').map(function() { return this.value; }).get()
        };
        $('.fok-apartment-cell').each(function() {
            const $cell = $(this);
            const data = $cell.data();
            let isVisible = true;
            if (filters.types.length > 0 && !filters.types.includes(data.type)) isVisible = false;
            if (filters.status && data.status !== filters.status) isVisible = false;
            if (data.area < filters.area_from || data.area > filters.area_to) isVisible = false;
            if (data.floor < filters.floor_from || data.floor > filters.floor_to) isVisible = false;
            if (data.type === 'apartment' && filters.rooms) {
                if (filters.rooms === '3+' && data.rooms < 3) isVisible = false;
                if (filters.rooms !== '3+' && String(data.rooms) !== String(filters.rooms)) isVisible = false;
            }
            $cell.toggleClass('is-filtered', !isVisible);
        });
    }

    function openViewer(rcId) { if (!rcId) return; currentRCId = rcId; $body.addClass('fok-viewer-is-open'); $viewer.addClass('is-visible'); loadProperties(); }
    function closeViewer() { $body.removeClass('fok-viewer-is-open'); $viewer.removeClass('is-visible'); closeDetailsPanel(true); $sidebar.removeClass('is-open'); $resultsContainer.html(''); $rcTitle.text(''); currentRCId = null; }
    function loadPropertyDetails(propertyId) { $panelContent.animate({ opacity: 0 }, 200, function() { $panelLoader.show(); $.ajax({ url: fok_ajax.ajax_url, type: 'POST', data: { action: 'fok_get_property_details', nonce: fok_ajax.nonce, property_id: propertyId }, success: (response) => { $panelLoader.hide(); if (response.success) { lastFetchedPropertyData = response.data; currentGallery = response.data.gallery || []; renderPanelContent(response.data); } else { renderError('Помилка завантаження даних.'); } }, error: () => { $panelLoader.hide(); renderError('Помилка зв\'язку з сервером.'); } }); }); }
    function openDetailsPanel(propertyId) { if ($viewer.find(`.fok-apartment-cell[data-id="${propertyId}"]`).hasClass('is-filtered')) { return; } $('.fok-apartment-cell.active').removeClass('active'); $viewer.find(`.fok-apartment-cell[data-id="${propertyId}"]`).addClass('active'); $detailsPanel.addClass('is-open'); loadPropertyDetails(propertyId); }
    function closeDetailsPanel(force = false) { $detailsPanel.removeClass('is-open'); $('.fok-apartment-cell.active').removeClass('active'); lastFetchedPropertyData = null; if (force) { $panelContent.html(''); } else { setTimeout(() => $panelContent.html(''), 400); } }
    function renderPanelContent(data) { let galleryHtml = ''; if (data.gallery && data.gallery.length > 0) { let thumbnailsHtml = ''; if (data.gallery.length > 1) { data.gallery.forEach((img, index) => { thumbnailsHtml += `<div class="thumb ${index === 0 ? 'active' : ''}" data-full-src="${img.full}"><img src="${img.thumb}" alt="Thumbnail"></div>`; }); } galleryHtml = `<div class="fok-panel-gallery"><div class="main-image"><img src="${data.gallery[0].full}" alt="Layout"></div>${data.gallery.length > 1 ? `<div class="thumbnails">${thumbnailsHtml}</div>` : ''}</div>`; } else { galleryHtml = '<div class="fok-panel-gallery"><p>Зображення відсутні.</p></div>'; } let paramsHtml = ''; for (const [key, value] of Object.entries(data.params)) { if (value) { paramsHtml += `<li><span>${key}</span><strong>${value}</strong></li>`; } } let priceHtml = ''; if (data.status_slug === 'vilno' && data.base_price.trim() !== '0') { if (data.has_discount) { priceHtml = `<div class="fok-panel-price with-discount"><div class="old-price">${data.base_price} ${data.currency}</div><div class="total-price">${data.total_price} ${data.currency}</div></div>`; } else { priceHtml = `<div class="fok-panel-price"><div class="total-price">${data.total_price} ${data.currency}</div><div class="price-per-m2">${data.price_per_m2} ${data.currency} / м²</div></div>`; } } let infoBlockHtml = `<div class="fok-panel-info"><div class="fok-panel-status status-${data.status_slug}">${data.status_name}</div>${priceHtml}</div>`; let bookingHtml = data.status_slug === 'vilno' ? `<div class="fok-panel-actions"><button class="fok-booking-btn-show fok-booking-button">Забронювати</button></div>` : ''; const contentHtml = `${galleryHtml}${infoBlockHtml}${bookingHtml}<ul class="fok-panel-params">${paramsHtml}</ul>`; $panelContent.html(contentHtml).animate({ opacity: 1 }, 250); }
    function renderBookingForm(data) { const formHtml = `<div class="fok-booking-form-wrapper"><button type="button" class="fok-form-back-btn">&larr; Назад до об'єкту</button><form id="fok-booking-form"><p>Заявка на ${data.type_name.toLowerCase()} №${data.property_number}</p><div class="form-group"><label for="b_name">Ваше ім'я</label><input type="text" id="b_name" required></div><div class="form-group"><label for="b_phone">Телефон</label><input type="tel" id="b_phone" required></div><div id="booking-form-message"></div><button type="submit" class="fok-booking-button">Надіслати заявку</button></form></div>`; $panelContent.animate({ opacity: 0 }, 200, function() { $(this).html(formHtml).animate({ opacity: 1 }, 250); }); }
    function renderError(message) { $panelContent.html(`<p class="error-message">${message}</p>`).animate({ opacity: 1 }, 250); }
    function updateRoomFilterState() { const isApartmentChecked = $propertyTypeCheckboxes.filter('[value="apartment"]').is(':checked'); $filtersForm.find('[data-dependency="apartment"]').toggle(isApartmentChecked); }
    function openLightbox(clickedImageSrc) { currentImageIndex = currentGallery.findIndex(img => img.full === clickedImageSrc); if (currentImageIndex === -1) currentImageIndex = 0; updateLightboxImage(); $lightbox.addClass('is-open'); }
    function updateLightboxImage() { if(currentGallery.length === 0) return; $lightboxImage.attr('src', currentGallery[currentImageIndex].full); updateLightboxNav(); }
    function updateLightboxNav() { $lightboxPrev.toggle(currentGallery.length > 1); $lightboxNext.toggle(currentGallery.length > 1); }
    function showNextImage() { if (currentGallery.length <= 1) return; currentImageIndex = (currentImageIndex + 1) % currentGallery.length; updateLightboxImage(); }
    function showPrevImage() { if (currentGallery.length <= 1) return; currentImageIndex = (currentImageIndex - 1 + currentGallery.length) % currentGallery.length; updateLightboxImage(); }
    function handleSwipe(event) { const swipeThreshold = 50; const diffX = touchStartX - event.changedTouches[0].clientX; if (Math.abs(diffX) > swipeThreshold) { if (diffX > 0) showNextImage(); else showPrevImage(); } }
    function debounce(func, wait) { let timeout; return function(...args) { const context = this; clearTimeout(timeout); timeout = setTimeout(() => func.apply(context, args), wait); }; }

    // --- Обробники подій ---
    const debouncedApplyFilters = debounce(applyFilters, 400);
    $filtersForm.on('change keyup', 'input, select', debouncedApplyFilters);
    $viewer.on('click', '.fok-room-buttons .room-btn', function() { $(this).addClass('active').siblings().removeClass('active'); $filtersForm.find('#filter-rooms').val($(this).data('value')); debouncedApplyFilters(); });
    $body.on('click', '.fok-open-viewer', function(e) { e.preventDefault(); openViewer($(this).data('rc-id')); });
    $closeButton.on('click', closeViewer);
    updateRoomFilterState(); 
    $propertyTypeCheckboxes.on('change', updateRoomFilterState);
    $resultsContainer.on('click', '.fok-apartment-cell', function(e) { e.preventDefault(); openDetailsPanel($(this).data('id')); });
    $detailsPanel.on('click', '#fok-panel-close', () => closeDetailsPanel());
    $panelContent.on('click', '.fok-panel-gallery .thumb', function() { const newSrc = $(this).data('full-src'); $(this).addClass('active').siblings().removeClass('active'); $(this).closest('.fok-panel-gallery').find('.main-image img').attr('src', newSrc); });
    $panelContent.on('click', '.fok-panel-gallery .main-image', function() { if(currentGallery && currentGallery.length > 0) { openLightbox($(this).find('img').attr('src')); } });
    $panelContent.on('click', '.fok-booking-btn-show', () => renderBookingForm(lastFetchedPropertyData));
    $panelContent.on('click', '.fok-form-back-btn', () => renderPanelContent(lastFetchedPropertyData));
    $panelContent.on('submit', '#fok-booking-form', function(e) { e.preventDefault(); const $form = $(this); const $message = $form.find('#booking-form-message'); const $submitBtn = $form.find('button[type="submit"]'); $message.slideUp(); $submitBtn.prop('disabled', true).text('Надсилаємо...'); $.ajax({ url: fok_ajax.ajax_url, type: 'POST', data: { action: 'fok_submit_booking', nonce: fok_ajax.nonce, property_id: lastFetchedPropertyData.id, name: $form.find('#b_name').val(), phone: $form.find('#b_phone').val() }, success: (response) => { const messageClass = response.success ? 'success' : 'error'; $message.removeClass('success error').addClass(messageClass).text(response.data).slideDown(); if (response.success) { loadProperties(); setTimeout(() => closeDetailsPanel(true), 3000); } else { $submitBtn.prop('disabled', false).text('Надіслати заявку'); } }, error: () => { $message.removeClass('success').addClass('error').text('Помилка зв\'язку.').slideDown(); $submitBtn.prop('disabled', false).text('Надіслати заявку'); } }); });
    $lightbox.on('click', function(e) { if (e.target === this || $(e.target).is('#fok-lightbox-close')) { $lightbox.removeClass('is-open'); } });
    $lightboxPrev.on('click', showPrevImage);
    $lightboxNext.on('click', showNextImage);
    $lightbox[0].addEventListener('touchstart', (e) => { touchStartX = e.touches[0].clientX; }, {passive: true});
    $lightbox[0].addEventListener('touchend', (e) => { handleSwipe(e); });
    $(document).on('keydown', function(e) { if (!$viewer.hasClass('is-visible')) return; if (e.key === "Escape") { if ($lightbox.hasClass('is-open')) { $lightbox.removeClass('is-open'); } else if ($detailsPanel.hasClass('is-open')) { closeDetailsPanel(); } else { closeViewer(); } } if ($lightbox.hasClass('is-open')) { if (e.key === "ArrowLeft") showPrevImage(); if (e.key === "ArrowRight") showNextImage(); } });
    $('#fok-mobile-filter-trigger').on('click', () => $sidebar.addClass('is-open'));
    $('#fok-sidebar-close').on('click', () => $sidebar.removeClass('is-open'));
    $resultsContainer.on('scroll', '.fok-chessboard-grid', function() {
        updateScrollHints(this);
    });
});