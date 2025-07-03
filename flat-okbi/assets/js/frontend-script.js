jQuery(document).ready(function($) {
    // --- Глобальні змінні та кешування елементів ---
    const $body = $('body');
    const $viewer = $('#fok-viewer-fullscreen-container');
    if (!$viewer.length) return;

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
    let targetPropertyId = null;
    let lastFetchedData = null;
    let lastFetchedPropertyData = null;
    let chessboardHtmlCache = '';
    let currentGallery = [];
    let currentImageIndex = 0;
    let touchStartX = 0;
    let xhr;

    // --- Функції завантаження та рендерингу даних ---
    function loadProperties() {
        if (!currentRCId) return;
        if (xhr && xhr.readyState !== 4) xhr.abort();

        xhr = $.ajax({
            url: fok_ajax.ajax_url,
            type: 'POST',
            data: { action: 'fok_filter_properties', nonce: fok_ajax.nonce, rc_id: currentRCId },
            beforeSend: () => { $loader.addClass('is-loading'); $resultsContainer.hide(); },
            success: (response) => {
                if (response.success) {
                    lastFetchedData = response.data;
                    $rcTitle.text(response.data.rc_title);
                    renderChessboard(response.data.sections);
                    applyFilters();
                    initScrollHints();
                    if (window.location.hash === '#details' && targetPropertyId) {
                        _showDetailsPanelUI();
                    }
                } else {
                    $resultsContainer.html(`<p>${fok_i10n.error_try_again}</p>`);
                }
            },
            error: () => $resultsContainer.html(`<p>${fok_i10n.server_error}</p>`),
            complete: () => { $loader.removeClass('is-loading'); $resultsContainer.fadeIn(400); }
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
            
            if (data.type === 'parking_space') {
                let isParkingVisible = true;
                if (filters.status && data.status !== filters.status) {
                    isParkingVisible = false;
                }
                $cell.toggleClass('is-filtered', !isParkingVisible);
                return; 
            }

            let isVisible = true; 
            
            if (filters.types.length > 0 && !filters.types.includes(data.type)) {
                isVisible = false;
            }
            
            if (filters.status && data.status !== filters.status) isVisible = false; 
            if (data.area < filters.area_from || data.area > filters.area_to) isVisible = false; 
            if (data.floor < filters.floor_from || data.floor > filters.floor_to) isVisible = false; 

            if (data.type === 'apartment' && filters.rooms) { 
                if (filters.rooms === '3+' && data.rooms < 3) isVisible = false; 
                if (filters.rooms !== '3+' && String(data.rooms) !== String(filters.rooms)) isVisible = false; 
            } 

            $cell.toggleClass('is-filtered', !isVisible); 
        }); 

        $('.fok-parking-summary-block').removeClass('is-filtered');
    }

    function renderChessboard(sections) {
        let html = '';
        if (sections && Object.keys(sections).length > 0) {
            html += '<div class="fok-chessboard-container">';
            sections.forEach(section => {
                const regularProperties = section.properties.regular || [];
                const parkingData = section.properties.parking || { is_present: false };
                const floorPlans = section.floor_plans || [];
                if (regularProperties.length === 0 && !parkingData.is_present) return;

                html += `<div class="fok-section-block" data-section-id="${section.id}"><h4>${section.name}</h4>`;
                if (regularProperties.length > 0 || parkingData.is_present) {

                    if (regularProperties.length > 0) {
                        let minFloor = Infinity, maxFloor = -Infinity;
                        regularProperties.forEach(p => {
                            const endFloor = p.floor + (p.grid_y_span || 1) - 1;
                            if (p.floor < minFloor) minFloor = p.floor;
                            if (endFloor > maxFloor) maxFloor = endFloor;
                        });
                        if (minFloor === Infinity) { minFloor = 0; maxFloor = 0; }
                        const totalRows = (maxFloor - minFloor + 1) > 0 ? (maxFloor - minFloor + 1) : 0;
                        const totalCols = section.grid_columns || 10;

                        // --- START: Змінена структура ---
                        html += `<div class="fok-chessboard-grid-container">`; // 1. Новий flex-контейнер

                        // 2. Окрема колонка для номерів поверхів
                        html += `<div class="fok-floor-labels-column" style="--grid-rows: ${totalRows};">`;
                        for (let floorNum = maxFloor; floorNum >= minFloor; floorNum--) {
                            if (floorNum === 0) continue;
                            const rowNum = maxFloor - floorNum + 1;
                            const hasPlan = floorPlans.some(plan => plan.number == floorNum && plan.image);
                            const planButton = hasPlan ? `<button class="fok-open-plan-btn" data-floor="${floorNum}" title="Показати план поверху"><span class="dashicons dashicons-layout"></span></button>` : '';
                            html += `<div class="fok-floor-label" style="grid-row: ${rowNum};">${planButton}<span class="fok-floor-label-text">${floorNum}</span></div>`;
                        }
                        html += `</div>`;

                        // 3. Окрема обгортка для скролу сітки
                        html += `<div class="fok-chessboard-scroll-container">`;
                        html += `<div class="fok-chessboard-grid" style="--grid-rows: ${totalRows}; --grid-cols: ${totalCols};">`;
                        regularProperties.forEach(property => {
                            html += renderPropertyCell(property, maxFloor);
                        });
                        html += '</div></div>'; // Кінець grid та scroll-container
                        html += `</div>`;     // Кінець grid-container
                        // --- END: Змінена структура ---
                    }

                    if (parkingData.is_present) {
                        const parkingIcon = (typeof fok_icons !== 'undefined' && fok_icons.parking) ? fok_icons.parking : '<span class="dashicons dashicons-car"></span>';
                        html += `<div class="fok-parking-summary-block" data-parking-items='${JSON.stringify(parkingData.items)}'><span class="fok-parking-summary-title">${parkingIcon} Паркінг</span><span class="fok-parking-summary-stats">Вільно: <span class="available-count">${parkingData.available_count}</span> / ${parkingData.total_count}</span><span class="fok-parking-summary-toggle dashicons dashicons-arrow-down-alt2"></span></div><div class="fok-parking-details-container"></div>`;
                    }
                }
                html += '</div>';
            });
            html += '</div>';
        } else {
            html = `<p>${fok_i10n.no_properties_in_rc}</p>`;
        }
        $resultsContainer.html(html);
    }
    
    function renderPropertyCell(property, maxFloor) {
        let cellContent = '';
        const cellClass = `fok-apartment-cell cell-type-${property.type}`;
        
        let numberContent = '';
        if (property.type === 'parking_space' && property.property_number) {
            numberContent = `<span class="fok-cell-number">${property.property_number}</span>`;
        }

        switch (property.type) {
            case 'apartment': cellContent = property.rooms; break;
            case 'commercial_property': cellContent = '<span class="fok-cell-icon" title="Комерція">К</span>'; break;
            case 'parking_space': cellContent = '<span class="fok-cell-icon" title="Паркінг">П</span>'; break;
            case 'storeroom': cellContent = '<span class="fok-cell-icon" title="Комора">Т</span>'; break;
        }

        const discountIndicator = property.has_discount ? `<span class="fok-cell-discount" title="На цей об\'єкт діє знижка">%</span>` : '';
        
        let gridStyles = '';
        if (property.grid_x_start && property.grid_x_start > 0 && maxFloor !== null) {
            const rowNum = maxFloor - (property.grid_y_start + property.grid_y_span - 1) + 1;
            const colNum = property.grid_x_start;
            gridStyles = `grid-column: ${colNum} / span ${property.grid_x_span}; grid-row: ${rowNum} / span ${property.grid_y_span};`;
        }

        return `<div class="${cellClass}" style="${gridStyles}" data-id="${property.id}" data-type="${property.type}" data-rooms="${property.rooms || 0}" data-area="${property.area}" data-floor="${property.floor}" data-status="${property.status}">${discountIndicator} ${numberContent} <span class="fok-cell-area">${property.area} м&sup2;</span><span class="fok-cell-rooms status-${property.status}">${cellContent}</span></div>`;
    }
    
    function showFloorPlanView(sectionId, floorNumber) {
        const section = lastFetchedData.sections.find(s => s.id == sectionId);
        if (!section) return;

        const floorPlan = section.floor_plans.find(fp => fp.number == floorNumber);
        if (!floorPlan || !floorPlan.image) {
            alert(fok_i10n.plan_not_loaded);
            return;
        }

        if ($resultsContainer.find('.fok-chessboard-container').length > 0) {
            chessboardHtmlCache = $resultsContainer.html();
        }

        const floorPlanHtml = `
            <div class="fok-floor-plan-view">
                <div class="fok-floor-plan-header">
                    <h3>${section.name} - Поверх ${floorNumber}</h3>
                    <button class="fok-back-to-chessboard-btn">&larr; Назад до шахматки</button>
                </div>
                <div class="fok-plan-viewer-wrapper">
                    <div id="fok-plan-viewer">
                        <img id="fok-plan-image" src="${floorPlan.image}" alt="План поверху">
                        <svg id="fok-plan-svg" preserveAspectRatio="none"></svg>
                    </div>
                </div>
            </div>
        `;

        $resultsContainer.html(floorPlanHtml);

        const $planImage = $resultsContainer.find('#fok-plan-image');
        const $planSvg = $resultsContainer.find('#fok-plan-svg');
        
        const regularProperties = section.properties.regular || [];
        const parkingProperties = (section.properties.parking && section.properties.parking.items) ? section.properties.parking.items : [];
        const allProperties = [...regularProperties, ...parkingProperties];

        $planImage.off('load').on('load', function() {
            let polygons = [];
            if (floorPlan.polygons_data) {
                try {
                    const parsed = JSON.parse(floorPlan.polygons_data);
                    if (Array.isArray(parsed)) { polygons = parsed; }
                } catch (e) { console.error("Помилка парсингу полігонів", e); }
            }
            const imageWidth = $(this).width();
            const imageHeight = $(this).height();
            $planSvg.attr('viewBox', `0 0 ${imageWidth} ${imageHeight}`);

            polygons.forEach(poly => {
                if (!poly.property_id || !poly.points || poly.points.length === 0) return;
                const property = allProperties.find(p => p.id == poly.property_id);
                const status = property ? property.status : 'unknown';
                const pointsAttr = poly.points.map(p => `${p.x * imageWidth},${p.y * imageHeight}`).join(' ');

                const $polygon = $(document.createElementNS('http://www.w3.org/2000/svg', 'polygon'));
                $polygon.attr('points', pointsAttr).attr('data-property-id', poly.property_id).addClass(`status-${status}`);
                $planSvg.append($polygon);
            });
        });
    }

    function showChessboardView() {
        if (chessboardHtmlCache) {
            $resultsContainer.html(chessboardHtmlCache);
            initScrollHints();
        }
    }
    
    // --- Керування станом UI через хеш ---
    function _showViewerUI() {
        if ($viewer.hasClass('is-visible')) return;
        $body.addClass('fok-viewer-is-open');
        $viewer.addClass('is-visible');
        loadProperties();
    }

    function _hideViewerUI() {
        if (!$viewer.hasClass('is-visible')) return;
        $body.removeClass('fok-viewer-is-open');
        $viewer.removeClass('is-visible');
        _closeDetailsPanelUI(true);
        _closeSidebarUI();
        $resultsContainer.html('');
        $rcTitle.text('');
        currentRCId = null;
    }

    function _showSidebarUI() {
        $sidebar.addClass('is-open');
    }

    function _closeSidebarUI() {
        $sidebar.removeClass('is-open');
    }

    function _showDetailsPanelUI() {
        if (!targetPropertyId) return;
        
        if ($detailsPanel.hasClass('is-open') && lastFetchedPropertyData && lastFetchedPropertyData.id == targetPropertyId) {
            return;
        }

        $('.fok-apartment-cell.active').removeClass('active');
        $viewer.find(`.fok-apartment-cell[data-id="${targetPropertyId}"]`).addClass('active');

        const $polygons = $('#fok-plan-svg polygon');
        if ($polygons.length > 0) {
            $polygons.removeClass('is-active-on-plan');
            $polygons.filter(`[data-property-id="${targetPropertyId}"]`).addClass('is-active-on-plan');
        }
        
        $detailsPanel.addClass('is-open');
        loadPropertyDetails(targetPropertyId);
    }

    function _closeDetailsPanelUI(force = false) {
        $detailsPanel.removeClass('is-open');
        $('.fok-apartment-cell.active').removeClass('active');
        $('#fok-plan-svg polygon').removeClass('is-active-on-plan');
        if (force) {
            targetPropertyId = null;
            lastFetchedPropertyData = null;
            $panelContent.html('');
        }
    }
    
    function handleStateChange() {
        const hash = window.location.hash.substring(1);

        if (['viewer', 'filters', 'details'].includes(hash) && !$viewer.hasClass('is-visible')) {
            _showViewerUI();
        }
        
        switch (hash) {
            case 'viewer':
                _closeSidebarUI();
                _closeDetailsPanelUI();
                break;
            case 'filters':
                _closeDetailsPanelUI();
                _showSidebarUI();
                break;
            case 'details':
                _closeSidebarUI();
                _showDetailsPanelUI();
                break;
            default:
                _hideViewerUI();
                break;
        }
    }

    // --- Обробники подій ---
    $body.on('click', '.fok-open-viewer', function(e) {
        e.preventDefault();
        const rcId = $(this).data('rc-id');
        if (!rcId) return;
        currentRCId = rcId;
        window.location.hash = 'viewer';
    });

    $closeButton.on('click', () => {
        window.location.hash = '';
    });
    
    $resultsContainer.on('click', '.fok-apartment-cell', function(e) {
        e.preventDefault();
        const propertyId = $(this).data('id');
        if ($(this).hasClass('is-filtered')) return;

        targetPropertyId = propertyId;

        if (window.location.hash === '#details') {
            if (!lastFetchedPropertyData || lastFetchedPropertyData.id != propertyId) {
                _showDetailsPanelUI();
            }
        } else {
            window.location.hash = 'details';
        }
    });

    $detailsPanel.on('click', '#fok-panel-close', () => {
        window.location.hash = 'viewer';
    });

    $('#fok-mobile-filter-trigger').on('click', () => {
        window.location.hash = 'filters';
    });

    $('#fok-sidebar-close').on('click', () => {
        window.location.hash = 'viewer';
    });

    $(document).on('keydown', function(e) {
        if (!$viewer.hasClass('is-visible')) return;
        if (e.key === "Escape") {
            const currentHash = window.location.hash;
            if ($lightbox.hasClass('is-open')) {
                $lightbox.removeClass('is-open');
            } else if (currentHash === '#details' || currentHash === '#filters') {
                window.location.hash = 'viewer';
            } else {
                window.location.hash = '';
            }
        }
        if ($lightbox.hasClass('is-open')) {
            if (e.key === "ArrowLeft") showPrevImage();
            if (e.key === "ArrowRight") showNextImage();
        }
    });

    $(window).on('hashchange', handleStateChange);
    
    if (window.location.hash) {
        window.location.hash = '';
    }

    const debouncedApplyFilters = debounce(applyFilters, 400);
    $filtersForm.on('change keyup', 'input, select', debouncedApplyFilters);
    $viewer.on('click', '.fok-room-buttons .room-btn', function() { $(this).addClass('active').siblings().removeClass('active'); $filtersForm.find('#filter-rooms').val($(this).data('value')); debouncedApplyFilters(); });
    
    $panelContent.on('click', '.fok-booking-btn-show', () => renderBookingForm(lastFetchedPropertyData));
    $panelContent.on('click', '.fok-form-back-btn', () => renderPanelContent(lastFetchedPropertyData));
    
    $panelContent.on('submit', '#fok-booking-form', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $message = $form.find('#booking-form-message');
        const $submitBtn = $form.find('button[type="submit"]');
        $message.slideUp();
        $submitBtn.prop('disabled', true).text(fok_i10n.sending);
        const submitData = {
            action: 'fok_submit_booking',
            nonce: fok_ajax.nonce,
            property_id: lastFetchedPropertyData.id,
            name: $form.find('#b_name').val(),
            phone: $form.find('#b_phone').val()
        };
        const sendAjaxRequest = () => {
            $.ajax({
                url: fok_ajax.ajax_url,
                type: 'POST',
                data: submitData,
                success: (response) => {
                    const messageClass = response.success ? 'success' : 'error';
                    $message.removeClass('success error').addClass(messageClass).text(response.data).slideDown();
                    if (response.success) {
                        loadProperties();
                        setTimeout(() => window.location.hash = 'viewer', 3000);
                    } else {
                        $submitBtn.prop('disabled', false).text(fok_i10n.send_request);
                    }
                },
                error: () => {
                    $message.removeClass('success').addClass('error').text(fok_i10n.connection_error_short).slideDown();
                    $submitBtn.prop('disabled', false).text(fok_i10n.send_request);
                }
            });
        };
        if (fok_ajax.recaptcha_site_key && typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(function() {
                grecaptcha.execute(fok_ajax.recaptcha_site_key, { action: 'submit' }).then(function(token) {
                    submitData.recaptcha_token = token;
                    sendAjaxRequest();
                });
            });
        } else {
            sendAjaxRequest();
        }
    });
    
    $propertyTypeCheckboxes.on('change', updateRoomFilterState);
    
    $resultsContainer.on('click', '.fok-open-plan-btn', function() {
        const $button = $(this);
        const sectionId = $button.closest('.fok-section-block').data('section-id');
        const floorNumber = $button.data('floor');
        showFloorPlanView(sectionId, floorNumber);
    });

    $resultsContainer.on('click', '.fok-back-to-chessboard-btn', function() {
        showChessboardView();
    });
    
    $resultsContainer.on('click', '#fok-plan-svg polygon', function() {
        const $polygon = $(this);
        if ($polygon.hasClass('status-vilno')) {
            const propertyId = $polygon.data('property-id');
            if (!propertyId) return;

            targetPropertyId = propertyId;

            if (window.location.hash === '#details') {
                if (!lastFetchedPropertyData || lastFetchedPropertyData.id != propertyId) {
                    _showDetailsPanelUI();
                }
            } else {
                window.location.hash = 'details';
            }
        }
    });
    
    $resultsContainer.on('click', '.fok-parking-summary-block', function() {
        const $summaryBlock = $(this);
        const $detailsContainer = $summaryBlock.next('.fok-parking-details-container');
        $summaryBlock.toggleClass('active');
        $detailsContainer.toggleClass('is-open');
        if (!$detailsContainer.data('is-rendered')) {
            const items = $summaryBlock.data('parking-items');
            let finalHtml = '';
            if (items && items.length > 0) {
                const sectionId = $summaryBlock.closest('.fok-section-block').data('section-id');
                const sectionData = lastFetchedData.sections.find(s => s.id == sectionId);
                const levels = items.reduce((acc, item) => {
                    const level = item.floor;
                    if (!acc[level]) { acc[level] = []; }
                    acc[level].push(item);
                    return acc;
                }, {});
                const sortedLevels = Object.keys(levels).sort((a, b) => b - a);
                finalHtml += `<div class="fok-parking-details-container-inner">`;
                sortedLevels.forEach(level => {
                    const hasPlan = sectionData && sectionData.floor_plans.some(plan => plan.number == level && plan.image);
                    const planButton = hasPlan ? `<button class="fok-open-plan-btn" data-floor="${level}" title="Показати план поверху"><span class="dashicons dashicons-layout"></span></button>` : '';
                    finalHtml += `<div class="fok-parking-level-row">`;
                    finalHtml += `<div class="fok-floor-label">${planButton}<span class="fok-floor-label-text">${level}</span></div>`;
                    finalHtml += `<div class="fok-parking-spots-container">`;
                    const spotsInLevel = levels[level].sort((a, b) => (a.grid_x_start || 0) - (b.grid_x_start || 0));
                    spotsInLevel.forEach(item => {
                        finalHtml += renderPropertyCell(item, null);
                    });
                    finalHtml += `</div></div>`;
                });
                finalHtml += `</div>`;
            } else {
                finalHtml = '<p>Паркомісця відсутні.</p>';
            }
            $detailsContainer.html(finalHtml);
            $detailsContainer.data('is-rendered', true);
            applyFilters(); 
        }
    });

    $resultsContainer.on('scroll', '.fok-chessboard-grid', function() { updateScrollHints(this); });
    $panelContent.on('click', '.fok-panel-gallery .main-image', function() { if(currentGallery && currentGallery.length > 0) { openLightbox($(this).find('img').attr('src')); } });
    $panelContent.on('click', '.fok-panel-gallery .thumb', function() { const newSrc = $(this).data('full-src'); $(this).addClass('active').siblings().removeClass('active'); $(this).closest('.fok-panel-gallery').find('.main-image img').attr('src', newSrc); });
    $lightbox.on('click', function(e) { if (e.target === this || $(e.target).is('#fok-lightbox-close')) { $lightbox.removeClass('is-open'); } });
    $lightboxPrev.on('click', showPrevImage);
    $lightboxNext.on('click', showNextImage);
    $lightbox[0].addEventListener('touchstart', (e) => { touchStartX = e.touches[0].clientX; }, {passive: true});
    $lightbox[0].addEventListener('touchend', (e) => { handleSwipe(e); });
    $panelContent.on('click', '.fok-show-on-plan-btn', function() {
        const $button = $(this);
        const sectionId = $button.data('section-id');
        const floor = $button.data('floor');
        const propertyId = $button.data('property-id');

        if (!sectionId || !floor) {
            alert(fok_i10n.section_or_floor_undefined);
            return;
        }

        if ($detailsPanel.css('position') === 'fixed') {
            window.location.hash = 'viewer';
        }

        showFloorPlanView(sectionId, floor);

        setTimeout(() => {
            $('#fok-plan-svg polygon')
                .filter(`[data-property-id="${propertyId}"]`)
                .addClass('is-active-on-plan');
        }, 150);
    });
    $resultsContainer.on('click', '.fok-back-to-chessboard-btn', function() {
        $('#fok-plan-svg polygon').removeClass('is-highlighted');
        showChessboardView();
    });
    
    // --- Допоміжні функції ---
    function updateScrollHints(gridElement) { const wrapper = $(gridElement).parent(); const scrollLeft = gridElement.scrollLeft; const scrollWidth = gridElement.scrollWidth; const clientWidth = gridElement.clientWidth; wrapper.toggleClass('is-scrollable-start', scrollLeft > 5); wrapper.toggleClass('is-scrollable-end', scrollLeft < (scrollWidth - clientWidth) - 5); }
    function initScrollHints() { $('.fok-chessboard-grid').each(function() { if (this.scrollWidth > this.clientWidth) { updateScrollHints(this); } }); }
    function updateRoomFilterState() { const isApartmentChecked = $propertyTypeCheckboxes.filter('[value="apartment"]').is(':checked'); $filtersForm.find('[data-dependency="apartment"]').toggle(isApartmentChecked); }
    function debounce(func, wait) { let timeout; return function(...args) { const context = this; clearTimeout(timeout); timeout = setTimeout(() => func.apply(context, args), wait); }; }
    function openLightbox(clickedImageSrc) { currentImageIndex = currentGallery.findIndex(img => img.full === clickedImageSrc); if (currentImageIndex === -1) currentImageIndex = 0; updateLightboxImage(); $lightbox.addClass('is-open'); }
    function updateLightboxImage() { if (currentGallery.length === 0) return; $lightboxImage.attr('src', currentGallery[currentImageIndex].full); updateLightboxNav(); }
    function updateLightboxNav() { $lightboxPrev.toggle(currentGallery.length > 1); $lightboxNext.toggle(currentGallery.length > 1); }
    function showNextImage() { if (currentGallery.length <= 1) return; currentImageIndex = (currentImageIndex + 1) % currentGallery.length; updateLightboxImage(); }
    function showPrevImage() { if (currentGallery.length <= 1) return; currentImageIndex = (currentImageIndex - 1 + currentGallery.length) % currentGallery.length; updateLightboxImage(); }
    function handleSwipe(event) { const swipeThreshold = 50; const diffX = touchStartX - event.changedTouches[0].clientX; if (Math.abs(diffX) > swipeThreshold) { if (diffX > 0) showNextImage(); else showPrevImage(); } }
    function loadPropertyDetails(propertyId) {
        $panelContent.stop().css('opacity', 0); // Негайно ховаємо старий контент і зупиняємо будь-яку анімацію
        $panelLoader.show(); // І одразу показуємо завантажувач

        $.ajax({
            url: fok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fok_get_property_details',
                nonce: fok_ajax.nonce,
                property_id: propertyId
            },
            success: (response) => {
                $panelLoader.hide(); // Ховаємо завантажувач, як тільки отримали відповідь
                if (response.success) {
                    lastFetchedPropertyData = response.data;
                    currentGallery = response.data.gallery || [];
                    renderPanelContent(response.data); // Ця функція оновить HTML і плавно покаже його
                } else {
                    renderError(fok_i10n.data_loading_error);
                }
            },
            error: () => {
                $panelLoader.hide(); // Також ховаємо завантажувач у випадку помилки
                renderError(fok_i10n.connection_error);
            }
        });
    }
    function renderPanelContent(data) {
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
            galleryHtml = '<div class="fok-panel-gallery"><p>Зображення відсутні.</p></div>';
        }
        let paramsHtml = '';
        for (const [key, value] of Object.entries(data.params)) {
            if (value) {
                let buttonHtml = '';
                if (key === 'Поверх' && data.has_floor_plan) {
                    buttonHtml = `<button class="fok-show-on-plan-btn" data-section-id="${data.section_id}" data-floor="${value}" data-property-id="${data.id}" title="Показати на плані поверху"><span class="dashicons dashicons-location-alt"></span></button>`;
                }
                paramsHtml += `<li><span>${key}</span><strong>${value}${buttonHtml}</strong></li>`;
            }
        }
        let priceHtml = '';
        if (data.status_slug === 'vilno' && data.base_price.trim() !== '0') {
            if (data.has_discount) {
                priceHtml = `<div class="fok-panel-price with-discount"><div class="old-price">${data.base_price} ${data.currency}</div><div class="total-price">${data.total_price} ${data.currency}</div></div>`;
            } else {
                const propertyType = data.type;
                let pricePerM2Html = '';

                // ПОКАЗУЄМО ЦІНУ ЗА М², ТІЛЬКИ ДЛЯ КВАРТИР ТА КОМЕРЦІЇ
                if ( (propertyType === 'apartment' || propertyType === 'commercial_property') && parseFloat(data.price_per_m2.replace(/\s/g, '')) > 0 ) {
                    pricePerM2Html = `<div class="price-per-m2">${data.price_per_m2} ${data.currency} / м²</div>`;
                }

                priceHtml = `<div class="fok-panel-price"><div class="total-price">${data.total_price} ${data.currency}</div>${pricePerM2Html}</div>`;
            }
        }
        let infoBlockHtml = `<div class="fok-panel-info"><div class="fok-panel-status status-${data.status_slug}">${data.status_name}</div>${priceHtml}</div>`;
        let bookingHtml = data.status_slug === 'vilno' ? `<div class="fok-panel-actions"><button class="fok-booking-btn-show fok-booking-button">Забронювати</button></div>` : '';
        const contentHtml = `${galleryHtml}${infoBlockHtml}${bookingHtml}<ul class="fok-panel-params">${paramsHtml}</ul>`;
        $panelContent.html(contentHtml).animate({ opacity: 1 }, 250);
    }
    function renderBookingForm(data) { const formHtml = `<div class="fok-booking-form-wrapper"><button type="button" class="fok-form-back-btn">&larr; Назад до об'єкту</button><form id="fok-booking-form"><p>Заявка на ${data.type_name.toLowerCase()} №${data.property_number}</p><div class="form-group"><label for="b_name">Ваше ім'я</label><input type="text" id="b_name" required></div><div class="form-group"><label for="b_phone">Телефон</label><input type="tel" id="b_phone" required></div><div id="booking-form-message"></div><button type="submit" class="fok-booking-button">${fok_i10n.send_request}</button></form></div>`; $panelContent.animate({ opacity: 0 }, 200, function() { $(this).html(formHtml).animate({ opacity: 1 }, 250); }); }
    function renderError(message) { $panelContent.html(`<p class="error-message">${message}</p>`).animate({ opacity: 1 }, 250); }
    
    function ensureFiltersAreConsistent() {
        const parkingFilterCheckbox = $filtersForm.find('input[name="property_types[]"][value="parking_space"]');
        if (parkingFilterCheckbox.length) {
            parkingFilterCheckbox.closest('label').remove();
        }
    }

    ensureFiltersAreConsistent();
    updateRoomFilterState();

    // --- Оновлений блок: Ефект підсвічування поверху для розділеної сітки ---
    $resultsContainer.on('mouseenter', '.fok-apartment-cell', function() {
        const $cell = $(this);
        // Знаходимо спільний батьківський контейнер для колонок
        const $container = $cell.closest('.fok-chessboard-grid-container');
        if ($container.length === 0) return;

        // Отримуємо рядок, на якому знаходиться клітинка
        const startRow = parseInt($cell.css('grid-row-start'), 10);

        // Отримуємо висоту клітинки (для дворівневих квартир)
        const rowSpanText = $cell.css('grid-row-end') || 'span 1';
        const rowSpan = rowSpanText.includes('span') ? parseInt(rowSpanText.replace('span', '').trim(), 10) : 1;

        // Проходимо по всіх рядках, які займає клітинка
        for (let i = 0; i < rowSpan; i++) {
            const currentRow = startRow + i;
            // Тепер шукаємо відповідний номер поверху в сусідній колонці
            $container.find('.fok-floor-label').filter(function() {
                return parseInt($(this).css('grid-row-start')) === currentRow;
            }).addClass('is-highlighted');
        }

    }).on('mouseleave', '.fok-apartment-cell', function() {
        const $container = $(this).closest('.fok-chessboard-grid-container');
        // Знімаємо виділення з усіх номерів поверхів у межах поточного контейнера
        $container.find('.fok-floor-label.is-highlighted').removeClass('is-highlighted');
    });
});