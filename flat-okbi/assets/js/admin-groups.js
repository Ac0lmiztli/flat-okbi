jQuery(document).ready(function($) {
    'use strict';

    const mainContainer = $('#fok-floors-container');
    if (!mainContainer.length) return;

    const modal = $('#fok-floor-plan-modal');
    const modalImageContainer = modal.find('.fok-editor-image-container');
    const modalImage = modal.find('.fok-editor-bg-image');
    const modalSvg = modal.find('.fok-editor-svg');
    const modalObjectsList = modal.find('.fok-floor-objects-list');
    let currentRow = null;

    let polygonsData = [];
    let activePropertyId = null;
    let isDrawing = false;
    let isDraggingPoint = false;
    let draggedPointInfo = null;
    
    let floorPropertiesCache = {};
    const currentSectionId = fok_groups_data.post_id;
    const nonce = fok_groups_data.nonce;
    let mediaFrame;

    // =================================================================
    // ФУНКЦІЯ ВІДКРИТТЯ РЕДАКТОРА (без змін)
    // =================================================================
    function openFloorPlanEditor($row) {
        currentRow = $row; 
        const imageId = currentRow.find('.fok-floor-image-id').val();
        const polygonsJson = currentRow.find('.fok-floor-polygons-data').val();
        const floorNumber = currentRow.find('.fok-floor-number').val();

        try {
            polygonsData = JSON.parse(polygonsJson || '[]');
            if (!Array.isArray(polygonsData)) polygonsData = [];
        } catch (e) {
            polygonsData = [];
        }

        $.post(ajaxurl, { action: 'get-attachment', id: imageId }).done(function(response) {
            if (response.success) {
                modalImage.attr('src', response.data.url);
                modalImage.off('load').on('load', function() {
                    const w = this.naturalWidth;
                    const h = this.naturalHeight;
                    modalSvg.attr('viewBox', `0 0 ${w} ${h}`);
                    renderSVG(); 
                });
            }
        });

        modalObjectsList.html('<span class="spinner is-active"></span>');
        loadPropertiesForFloor(floorNumber, renderObjectList);

        modal.dialog({
            modal: true,
            width: '90%', 
            height: $(window).height() * 0.85, 
            closeOnEscape: true,
            create: function() { $(this).closest('.ui-dialog').addClass('wp-dialog'); },
            buttons: [
                {
                    text: "Зберегти і закрити",
                    class: 'button-primary',
                    click: function() {
                        currentRow.find('.fok-floor-polygons-data').val(JSON.stringify(polygonsData));
                        $(this).dialog("close");
                    }
                },
                {
                    text: "Скасувати",
                    class: 'button',
                    click: function() { $(this).dialog("close"); }
                }
            ],
            close: function() { resetEditorState(); }
        });
    }

    function resetEditorState() {
        currentRow = null;
        polygonsData = [];
        activePropertyId = null;
        isDrawing = false;
        isDraggingPoint = false;
        draggedPointInfo = null;
        modalImage.attr('src', '');
        modalSvg.empty();
        modalObjectsList.empty();
    }
    
    // =================================================================
    // ЛОГІКА РЕДАКТОРА (без змін)
    // =================================================================
    function getPolygonByPropertyId(propId) { return polygonsData.find(p => String(p.property_id) === String(propId)); }
    function getPolygonIndexByPropertyId(propId) { return polygonsData.findIndex(p => String(p.property_id) === String(propId)); }
    function startDrawingForProperty(propId) { /* ... код без змін ... */ }
    function stopDrawing() { /* ... код без змін ... */ }
    function renderAll() { /* ... код без змін ... */ }
    function renderSVG() { /* ... код без змін ... */ }
    function renderObjectList() { /* ... код без змін ... */ }
    function loadPropertiesForFloor(floorNumber, callback) { /* ... код без змін ... */ }


    // =================================================================
    // ГЛОБАЛЬНІ ОБРОБНИКИ ПОДІЙ
    // =================================================================
    
    // Кнопка "Додати поверх"
    $('#fok-add-floor-btn').on('click', function() { 
        const template = $('#fok-floor-template').html();
        mainContainer.append(template);
    });

    // Видалення поверху
    mainContainer.on('click', '.fok-delete-floor-btn', function() {
        if (confirm('Ви впевнені, що хочете видалити цей поверх?')) {
            $(this).closest('.fok-floor-row').remove();
        }
    });

    // Зміна номера поверху
    mainContainer.on('change', '.fok-floor-number', function() {
        delete floorPropertiesCache[$(this).val()];
    });

    // Кнопка "Редагувати план"
    mainContainer.on('click', '.fok-open-plan-editor-btn', function() {
        openFloorPlanEditor($(this).closest('.fok-floor-row'));
    });

    // ** ВИПРАВЛЕНО: Відновлено повну логіку для кнопки завантаження **
    mainContainer.on('click', '.fok-upload-image-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $row = $button.closest('.fok-floor-row'); // Визначаємо рядок, в якому натиснута кнопка

        // Створюємо новий frame для медіа-бібліотеки
        mediaFrame = wp.media({
            title: 'Вибрати або завантажити план поверху',
            button: {
                text: 'Використати це зображення'
            },
            multiple: false
        });

        // Коли зображення вибрано
        mediaFrame.on('select', function() {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            
            // Оновлюємо поля саме в тому рядку, де ми працюємо
            $row.find('.fok-floor-image-id').val(attachment.id);
            $row.find('.fok-image-preview-thumb').html(`<img src="${attachment.sizes.thumbnail.url}">`);
            $row.find('.fok-remove-image-btn').show();
            $row.find('.fok-open-plan-editor-btn').prop('disabled', false);
        });

        // Відкриваємо вікно медіа-бібліотеки
        mediaFrame.open();
    });

    mainContainer.on('click', '.fok-remove-image-btn', function(e) {
        e.preventDefault();
        const $row = $(this).closest('.fok-floor-row');
        $row.find('.fok-floor-image-id').val('');
        $row.find('.fok-image-preview-thumb').empty();
        $(this).hide();
        $row.find('.fok-open-plan-editor-btn').prop('disabled', true);
    });
    
    // Обробники подій всередині модального вікна (без змін)
    modalObjectsList.on('click', '.fok-property-list-item', function(e) { /* ... код без змін ... */ });
    modalObjectsList.on('click', '.fok-delete-polygon-btn', function(e) { /* ... код без змін ... */ });
    modalImageContainer.on('click', function(e) { /* ... код без змін ... */ });
    $(document).on('keydown', function(e) { if(isDrawing && e.key === "Escape") stopDrawing(); });
    modalImageContainer.on('contextmenu', function(e) { if (isDrawing) { e.preventDefault(); stopDrawing(); } });
    modalSvg.on('mousedown', '.fok-polygon-point', function(e) { /* ... код без змін ... */ });
    function handlePointDrag(e) { /* ... код без змін ... */ }
    function stopPointDrag() { /* ... код без змін ... */ }

    // Збереження всіх даних перед відправкою форми (без змін)
    $('form#post').on('submit', function() { /* ... код без змін ... */ });
    
    
    // =================================================================
    // ТІЛА ФУНКЦІЙ РЕДАКТОРА, ЩО ЗАЛИШИЛИСЯ БЕЗ ЗМІН
    // (для повноти коду)
    // =================================================================

    function startDrawingForProperty(propId) {
        if (!propId || isDraggingPoint) return;
        let polygon = getPolygonByPropertyId(propId);
        if (!polygon) {
            polygon = { property_id: String(propId), points: [] };
            polygonsData.push(polygon);
        }
        activePropertyId = String(propId);
        isDrawing = true;
        modalImageContainer.css('cursor', 'crosshair');
        renderAll();
    }
    
    function stopDrawing() {
        isDrawing = false;
        modalImageContainer.css('cursor', 'default');
        renderAll();
    }

    function renderAll() {
        renderObjectList();
        renderSVG();
    }
    
    function renderSVG() {
        modalSvg.empty();
        const imageW = modalImage.width();
        const imageH = modalImage.height();
        if (!imageW || !imageH) return;

        const naturalW = modalImage[0].naturalWidth;
        const naturalH = modalImage[0].naturalHeight;

        polygonsData.forEach((poly, polyIndex) => {
            if (!poly.points || poly.points.length === 0) return;

            const pointsAttr = poly.points.map(p => `${p.x * naturalW},${p.y * naturalH}`).join(' ');
            const $polygon = $(document.createElementNS('http://www.w3.org/2000/svg', 'polygon'));
            
            $polygon
                .attr('points', pointsAttr)
                .attr('data-property-id', poly.property_id)
                .toggleClass('active', String(poly.property_id) === String(activePropertyId));
            
            modalSvg.append($polygon);

            if (String(poly.property_id) === String(activePropertyId)) {
                poly.points.forEach((p, pointIndex) => {
                    const $pointCircle = $(document.createElementNS('http://www.w3.org/2000/svg', 'circle'));
                    $pointCircle
                        .attr('class', 'fok-polygon-point')
                        .attr('cx', p.x * naturalW)
                        .attr('cy', p.y * naturalH)
                        .attr('r', 5)
                        .attr('data-poly-index', polyIndex)
                        .attr('data-point-index', pointIndex);
                    
                    modalSvg.append($pointCircle);
                });
            }
        });
    }

    function renderObjectList() {
        const floorNumber = currentRow ? currentRow.find('.fok-floor-number').val() : null;
        if (!floorNumber) return;
        
        const properties = floorPropertiesCache[floorNumber] || [];
        modalObjectsList.empty();

        if (properties.length === 0) {
            modalObjectsList.html('<p>Немає об\'єктів на цьому поверсі.</p>');
            return;
        }

        properties.forEach(prop => {
            const polygon = getPolygonByPropertyId(prop.id);
            const hasPolygon = polygon && polygon.points.length > 0;
            const statusIcon = hasPolygon ? 'dashicons-yes-alt' : 'dashicons-edit-page';
            const itemClass = String(prop.id) === String(activePropertyId) ? 'active' : '';

            const $item = $(`
                <div class="fok-property-list-item ${itemClass}" data-property-id="${prop.id}">
                    <span>${prop.title}</span>
                    <div class="actions">
                        <span class="dashicons ${statusIcon}"></span>
                        ${hasPolygon ? '<span class="dashicons dashicons-trash fok-delete-polygon-btn" title="Видалити полігон"></span>' : ''}
                    </div>
                </div>
            `);
            modalObjectsList.append($item);
        });
    }

    function loadPropertiesForFloor(floorNumber, callback) {
        if (!floorNumber) {
            modalObjectsList.html('<p>Вкажіть номер поверху.</p>');
            return;
        }
        if (floorPropertiesCache[floorNumber]) {
            if (callback) callback();
            return;
        }
        modalObjectsList.html('<span class="spinner is-active"></span>');
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'fok_get_properties_for_floor_json', nonce: nonce, section_id: currentSectionId, floor_number: floorNumber },
            success: function(response) {
                floorPropertiesCache[floorNumber] = response.success ? response.data : [];
                if (callback) callback();
            },
            error: function() {
                floorPropertiesCache[floorNumber] = [];
                if (callback) callback();
            }
        });
    }
    
    modalObjectsList.on('click', '.fok-property-list-item', function(e) {
        if ($(e.target).hasClass('fok-delete-polygon-btn')) return;
        startDrawingForProperty($(this).data('property-id'));
    });

    modalObjectsList.on('click', '.fok-delete-polygon-btn', function(e) {
        e.stopPropagation();
        if (confirm('Видалити полігон?')) {
            const propId = $(this).closest('.fok-property-list-item').data('property-id');
            const polyIndex = getPolygonIndexByPropertyId(propId);
            if (polyIndex > -1) {
                polygonsData.splice(polyIndex, 1);
                if (String(activePropertyId) === String(propId)) activePropertyId = null;
                renderAll();
            }
        }
    });

    modalImageContainer.on('click', function(e) {
        if (isDrawing) {
            const rect = modalSvg[0].getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top) / rect.height;
            const polygon = getPolygonByPropertyId(activePropertyId);
            if (polygon) {
                polygon.points.push({ x: x.toFixed(4), y: y.toFixed(4) });
                renderSVG();
            }
        }
    });

    modalSvg.on('mousedown', '.fok-polygon-point', function(e) {
        e.preventDefault();
        e.stopPropagation();
        isDrawing = false;
        isDraggingPoint = true;
        
        const $point = $(this);
        draggedPointInfo = {
            polyIndex: $point.data('poly-index'),
            pointIndex: $point.data('point-index')
        };
        
        $(document).on('mousemove.dragPoint', handlePointDrag);
        $(document).on('mouseup.dragPoint', stopPointDrag);
    });

    function handlePointDrag(e) {
        if (!isDraggingPoint || !draggedPointInfo) return;
        
        const rect = modalSvg[0].getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width;
        const y = (e.clientY - rect.top) / rect.height;

        polygonsData[draggedPointInfo.polyIndex].points[draggedPointInfo.pointIndex] = {
            x: x.toFixed(4),
            y: y.toFixed(4)
        };
        
        renderSVG();
    }

    function stopPointDrag() {
        isDraggingPoint = false;
        draggedPointInfo = null;
        $(document).off('mousemove.dragPoint mouseup.dragPoint');
    }

    $('form#post').on('submit', function() {
        const floorsData = [];
        mainContainer.find('.fok-floor-row').each(function() {
            const $row = $(this);
            floorsData.push({
                number: $row.find('.fok-floor-number').val(),
                image: $row.find('.fok-floor-image-id').val(),
                polygons_data: $row.find('.fok-floor-polygons-data').val()
            });
        });
        $('#fok_section_floors_data').val(JSON.stringify(floorsData));
    });
});