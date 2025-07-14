jQuery(document).ready(function($) {
    'use strict';

    const mainContainer = $('#fok-floors-container');
    if (!mainContainer.length) return;

    mainContainer.sortable({
        handle: '.handle',
        placeholder: 'fok-floor-row-placeholder',
        forcePlaceholderSize: true
    });

    const modal = $('#fok-floor-plan-modal');
    const modalImageContainer = modal.find('.fok-editor-image-container');
    const modalImage = modal.find('.fok-editor-bg-image');
    const modalSvg = modal.find('.fok-editor-svg');
    const modalObjectsList = modal.find('.fok-floor-objects-list');
    let currentRow = null;

    let polygonsData = [];
    let activePropertyId = null;
    let isDrawing = false;
    let isDrawingRectangle = false;
    let rectangleStartPoint = null;
    let drawingMode = 'polygon';
    let isDraggingPoint = false;
    let draggedPointInfo = null;
    let potentialNewPoint = null;
    
    let floorPropertiesCache = {};
    const currentSectionId = fok_groups_data.post_id;
    const nonce = fok_groups_data.nonce;

    // =================================================================
    // ФУНКЦІЯ ВІДКРИТТЯ РЕДАКТОРА
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

        if (imageId) {
            const attachment = wp.media.attachment(imageId);
            attachment.fetch().done(function() {
                const imageUrl = attachment.get('url');
                modalImage.attr('src', imageUrl);
                modalImage.off('load').on('load', function() {
                    const w = this.naturalWidth;
                    const h = this.naturalHeight;
                    modalSvg.attr('viewBox', `0 0 ${w} ${h}`);
                    renderAll();
                });
            });
        }

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
                    text: fok_groups_i10n.save_and_close,
                    class: 'button-primary',
                    click: function() {
                        currentRow.find('.fok-floor-polygons-data').val(JSON.stringify(polygonsData));
                        $(this).dialog("close");
                    }
                },
                {
                    text: fok_groups_i10n.cancel,
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
        isDrawingRectangle = false;
        rectangleStartPoint = null;
        drawingMode = 'polygon';
        isDraggingPoint = false;
        draggedPointInfo = null;
        potentialNewPoint = null;
        modalImage.attr('src', '');
        modalSvg.empty();
        modalObjectsList.empty();
        
        modal.find('.fok-tool-btn').removeClass('is-active');
        modal.find('.fok-tool-btn[data-tool="polygon"]').addClass('is-active');
        modal.find('.fok-tool-desc').hide();
        modal.find('.fok-tool-desc[data-tool-desc="polygon"]').show();
    }
    
    // =================================================================
    // ЛОГІКА РЕДАКТОРА
    // =================================================================
    function getPolygonByPropertyId(propId) { return polygonsData.find(p => String(p.property_id) === String(propId)); }
    function getPolygonIndexByPropertyId(propId) { return polygonsData.findIndex(p => String(p.property_id) === String(propId)); }

    function setActiveProperty(propId) {
        if (isDrawing || isDrawingRectangle || isDraggingPoint) {
            cancelCurrentDrawing();
        }
        activePropertyId = propId ? String(propId) : null;
        renderAll();
    }

    function cancelCurrentDrawing() {
        if (isDrawing) {
            const polyIndex = getPolygonIndexByPropertyId(activePropertyId);
            if (polyIndex > -1 && polygonsData[polyIndex].points.length < 3) {
                 polygonsData.splice(polyIndex, 1);
            }
        }
        isDrawing = false;
        isDrawingRectangle = false;
        rectangleStartPoint = null;
        $('#fok-rectangle-preview').remove();
        modalImageContainer.css('cursor', 'default');
        renderAll();
    }
    
    function stopDrawing() {
        if (isDrawing) {
            const polyIndex = getPolygonIndexByPropertyId(activePropertyId);
            if (polyIndex > -1) {
                if (polygonsData[polyIndex].points.length < 3) {
                    polygonsData.splice(polyIndex, 1);
                }
            }
        }
        isDrawing = false;
        modalImageContainer.css('cursor', 'default');
        setActiveProperty(null);
    }

    function renderAll() {
        renderObjectList();
        renderSVG();
    }
    
    function renderSVG() {
        modalSvg.empty();
        const w = modalSvg[0].viewBox.baseVal.width;
        const h = modalSvg[0].viewBox.baseVal.height;

        if (!w || !h) return;

        polygonsData.forEach((poly, polyIndex) => {
            if (!poly.points || poly.points.length === 0) return;

            const pointsAttr = poly.points.map(p => `${p.x * w},${p.y * h}`).join(' ');
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
                        .attr('cx', p.x * w)
                        .attr('cy', p.y * h)
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
            modalObjectsList.html(`<p>${fok_groups_i10n.no_objects_on_floor}</p>`);
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
                        ${hasPolygon ? `<span class="dashicons dashicons-trash fok-delete-polygon-btn" title="${fok_groups_i10n.confirm_delete_polygon}"></span>` : ''}
                    </div>
                </div>
            `);
            modalObjectsList.append($item);
        });
    }

    function loadPropertiesForFloor(floorNumber, callback) {
        if (!floorNumber) {
            modalObjectsList.html(`<p>${fok_groups_i10n.specify_floor_number}</p>`);
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

    // =================================================================
    // ГЛОБАЛЬНІ ОБРОБНИКИ ПОДІЙ
    // =================================================================
    
    modal.on('click', '.fok-tool-btn', function() {
        const $btn = $(this);
        if ($btn.hasClass('is-active')) return;

        drawingMode = $btn.data('tool');

        modal.find('.fok-tool-btn').removeClass('is-active');
        $btn.addClass('is-active');

        modal.find('.fok-tool-desc').hide();
        modal.find(`.fok-tool-desc[data-tool-desc="${drawingMode}"]`).show();

        cancelCurrentDrawing();
    });
    
    $('#fok-add-floor-btn').on('click', function() { 
        const template = $('#fok-floor-template').html();
        mainContainer.append(template);
    });

    mainContainer.on('click', '.fok-delete-floor-btn', function() {
        if (confirm(fok_groups_i10n.confirm_delete_floor)) {
            $(this).closest('.fok-floor-row').remove();
        }
    });

    mainContainer.on('change', '.fok-floor-number', function() {
        const floorNum = $(this).val();
        if (floorPropertiesCache[floorNum]) {
            delete floorPropertiesCache[floorNum];
        }
    });

    mainContainer.on('click', '.fok-open-plan-editor-btn', function() {
        openFloorPlanEditor($(this).closest('.fok-floor-row'));
    });

    mainContainer.on('click', '.fok-upload-image-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $row = $button.closest('.fok-floor-row');
        
        const frame = wp.media({
            title: 'Вибрати або завантажити план поверху',
            button: { text: 'Використати це зображення' },
            multiple: false
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $row.find('.fok-floor-image-id').val(attachment.id);
            $row.find('.fok-image-preview-thumb').html(`<img src="${attachment.sizes.thumbnail.url}">`);
            $row.find('.fok-remove-image-btn').show();
            $row.find('.fok-open-plan-editor-btn').prop('disabled', false);
        });

        frame.open();
    });

    mainContainer.on('click', '.fok-remove-image-btn', function(e) {
        e.preventDefault();
        const $row = $(this).closest('.fok-floor-row');
        $row.find('.fok-floor-image-id').val('');
        $row.find('.fok-image-preview-thumb').empty();
        $(this).hide();
        $row.find('.fok-open-plan-editor-btn').prop('disabled', true);
    });
    
    modalObjectsList.on('click', '.fok-property-list-item', function(e) {
        if ($(e.target).hasClass('fok-delete-polygon-btn')) return;
        setActiveProperty($(this).data('property-id'));
    });
    
    modalObjectsList.on('click', '.fok-delete-polygon-btn', function(e) {
        e.stopPropagation();
        if (confirm(fok_groups_i10n.confirm_delete_polygon)) {
            const propId = $(this).closest('.fok-property-list-item').data('property-id');
            const polyIndex = getPolygonIndexByPropertyId(propId);
            if (polyIndex > -1) {
                polygonsData.splice(polyIndex, 1);
                if (String(activePropertyId) === String(propId)) {
                    setActiveProperty(null);
                }
                renderAll();
            }
        }
    });

    function handlePolygonDrawClick(e) {
        if (!activePropertyId) return;

        const polyIndex = getPolygonIndexByPropertyId(activePropertyId);
        
        if (!isDrawing) {
            if (polyIndex !== -1) return; 
            isDrawing = true;
            polygonsData.push({ property_id: activePropertyId, points: [] });
            modalImageContainer.css('cursor', 'crosshair');
        }
        
        const rect = modalSvg[0].getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width;
        const y = (e.clientY - rect.top) / rect.height;

        const currentPolyIndex = getPolygonIndexByPropertyId(activePropertyId);
        polygonsData[currentPolyIndex].points.push({ x: x.toFixed(4), y: y.toFixed(4) });
        renderSVG();
    }
    
    modalSvg.on('contextmenu', function(e) {
        if (isDrawing) {
            e.preventDefault();
            stopDrawing();
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

    modalSvg.on('contextmenu', '.fok-polygon-point', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $point = $(this);
        const polyIndex = $point.data('poly-index');
        const pointIndex = $point.data('point-index');

        if (polygonsData[polyIndex] && polygonsData[polyIndex].points.length > 3) {
            polygonsData[polyIndex].points.splice(pointIndex, 1);
            renderSVG();
        }
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

    // =================================================================
    // Логіка малювання та редагування
    // =================================================================

    modalSvg.on('mousedown', function(e) {
        if (e.target.closest('.fok-polygon-point')) return;
        
        if (potentialNewPoint) {
            e.preventDefault();
            e.stopPropagation();

            const { polyIndex, insertAtIndex, point } = potentialNewPoint;
            polygonsData[polyIndex].points.splice(insertAtIndex, 0, point);
            
            potentialNewPoint = null;
            updatePreviewPoint();
            renderSVG();
            return;
        }
        
        if (drawingMode === 'rectangle' && activePropertyId) {
            const existingPolygon = getPolygonByPropertyId(activePropertyId);
            if (existingPolygon) return;

            e.preventDefault();
            isDrawingRectangle = true;

            const rect = modalSvg[0].getBoundingClientRect();
            rectangleStartPoint = {
                x: (e.clientX - rect.left) / rect.width,
                y: (e.clientY - rect.top) / rect.height
            };
            
            const w = modalSvg[0].viewBox.baseVal.width;
            const h = modalSvg[0].viewBox.baseVal.height;

            const $previewRect = $(document.createElementNS('http://www.w3.org/2000/svg', 'rect'));
            $previewRect
                .attr('id', 'fok-rectangle-preview')
                .attr('x', rectangleStartPoint.x * w)
                .attr('y', rectangleStartPoint.y * h)
                .attr('width', 0)
                .attr('height', 0);
            modalSvg.append($previewRect);

            $(document).on('mousemove.drawRect', handleRectangleDrag);
            $(document).on('mouseup.drawRect', stopRectangleDrag);

        } else if (drawingMode === 'polygon' && activePropertyId) {
             handlePolygonDrawClick(e);
        }
    });

    function handleRectangleDrag(e) {
        if (!isDrawingRectangle || !rectangleStartPoint) return;

        const rect = modalSvg[0].getBoundingClientRect();
        const w = modalSvg[0].viewBox.baseVal.width;
        const h = modalSvg[0].viewBox.baseVal.height;

        const currentPos = {
            x: (e.clientX - rect.left) / rect.width,
            y: (e.clientY - rect.top) / rect.height
        };

        const newX = Math.min(rectangleStartPoint.x, currentPos.x);
        const newY = Math.min(rectangleStartPoint.y, currentPos.y);
        const newW = Math.abs(currentPos.x - rectangleStartPoint.x);
        const newH = Math.abs(currentPos.y - rectangleStartPoint.y);

        $('#fok-rectangle-preview')
            .attr('x', newX * w)
            .attr('y', newY * h)
            .attr('width', newW * w)
            .attr('height', newH * h)
            .attr('fill', 'rgba(0, 115, 255, 0.4)')
            .attr('stroke', '#0073ff')
            .attr('stroke-width', '2px')
            .attr('vector-effect', 'non-scaling-stroke');
    }

    function stopRectangleDrag(e) {
        isDrawingRectangle = false;
        $(document).off('mousemove.drawRect mouseup.drawRect');

        const $preview = $('#fok-rectangle-preview');
        if (!$preview.length) return;

        const w_svg = modalSvg[0].viewBox.baseVal.width;
        const h_svg = modalSvg[0].viewBox.baseVal.height;

        const finalX = parseFloat($preview.attr('x')) / w_svg;
        const finalY = parseFloat($preview.attr('y')) / h_svg;
        const finalW = parseFloat($preview.attr('width')) / w_svg;
        const finalH = parseFloat($preview.attr('height')) / h_svg;

        $preview.remove();

        if (finalW < 0.01 || finalH < 0.01) {
            rectangleStartPoint = null;
            return;
        }

        const points = [
            { x: finalX.toFixed(4), y: finalY.toFixed(4) },
            { x: (finalX + finalW).toFixed(4), y: finalY.toFixed(4) },
            { x: (finalX + finalW).toFixed(4), y: (finalY + finalH).toFixed(4) },
            { x: finalX.toFixed(4), y: (finalY + finalH).toFixed(4) }
        ];

        polygonsData.push({ property_id: activePropertyId, points: points });
        rectangleStartPoint = null;
        setActiveProperty(null);
    }

    // =================================================================
    // Додавання точок на ребра полігону
    // =================================================================

    function distance(p1, p2) {
        const p1_x = parseFloat(p1.x);
        const p1_y = parseFloat(p1.y);
        const p2_x = parseFloat(p2.x);
        const p2_y = parseFloat(p2.y);
        return Math.sqrt(Math.pow(p1_x - p2_x, 2) + Math.pow(p1_y - p2_y, 2));
    }

    function findClosestPointOnSegment(p1, p2, p) {
        const p1_x = parseFloat(p1.x), p1_y = parseFloat(p1.y);
        const p2_x = parseFloat(p2.x), p2_y = parseFloat(p2.y);
        const p_x = p.x, p_y = p.y;

        const dx = p2_x - p1_x;
        const dy = p2_y - p1_y;

        const l2 = dx * dx + dy * dy;
        if (l2 === 0) return { x: p1_x, y: p1_y };

        let t = ((p_x - p1_x) * dx + (p_y - p1_y) * dy) / l2;
        t = Math.max(0, Math.min(1, t));

        return {
            x: p1_x + t * dx,
            y: p1_y + t * dy
        };
    }

    function updatePreviewPoint() {
        $('#fok-preview-point').remove();

        if (potentialNewPoint) {
            const w = modalSvg[0].viewBox.baseVal.width;
            const h = modalSvg[0].viewBox.baseVal.height;
            const $previewPoint = $(document.createElementNS('http://www.w3.org/2000/svg', 'circle'));
            $previewPoint
                .attr('id', 'fok-preview-point')
                .attr('cx', parseFloat(potentialNewPoint.point.x) * w)
                .attr('cy', parseFloat(potentialNewPoint.point.y) * h)
                .attr('r', 5)
                .addClass('fok-polygon-point-preview');
            modalSvg.append($previewPoint);
            modalSvg.css('cursor', 'copy');
        } else {
            if (activePropertyId && !isDrawing) {
                 modalSvg.css('cursor', 'default');
            } else if (!isDrawing) {
                 modalSvg.css('cursor', 'default');
            }
        }
    }

    modalSvg.on('mousemove', function(e) {
        if (!activePropertyId || isDrawingRectangle || isDrawing || isDraggingPoint) {
             if (potentialNewPoint) {
                potentialNewPoint = null;
                updatePreviewPoint();
            }
            return;
        }

        const rect = modalSvg[0].getBoundingClientRect();
        const mousePos = {
            x: (e.clientX - rect.left) / rect.width,
            y: (e.clientY - rect.top) / rect.height
        };

        const polyIndex = getPolygonIndexByPropertyId(activePropertyId);
        if (polyIndex === -1 || !polygonsData[polyIndex].points || polygonsData[polyIndex].points.length < 2) {
             if (potentialNewPoint) {
                potentialNewPoint = null;
                updatePreviewPoint();
            }
            return;
        }

        const poly = polygonsData[polyIndex];
        let minDistance = Infinity;
        let bestInsertion = null;

        for (let i = 0; i < poly.points.length; i++) {
            const p1 = poly.points[i];
            const p2 = poly.points[(i + 1) % poly.points.length];
            const segmentPoint = findClosestPointOnSegment(p1, p2, mousePos);
            const dist = distance(mousePos, segmentPoint);
            
            if (dist < minDistance) {
                minDistance = dist;
                bestInsertion = {
                    polyIndex: polyIndex,
                    insertAtIndex: i + 1,
                    point: { x: segmentPoint.x.toFixed(4), y: segmentPoint.y.toFixed(4) }
                };
            }
        }

        const threshold = 10 / rect.width;
        if (minDistance < threshold) {
            if (JSON.stringify(bestInsertion) !== JSON.stringify(potentialNewPoint)) {
                potentialNewPoint = bestInsertion;
                updatePreviewPoint();
            }
        } else if (potentialNewPoint) {
            potentialNewPoint = null;
            updatePreviewPoint();
        }
    });

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

    // =================================================================
    // ++ НОВИЙ КОД: Логіка підсвічування при наведенні ++
    // =================================================================

    function highlightElements(propId, state) {
        if (!propId) return;
        modalSvg.find(`polygon[data-property-id="${propId}"]`).toggleClass('highlighted', state);
        modalObjectsList.find(`.fok-property-list-item[data-property-id="${propId}"]`).toggleClass('highlighted', state);
    }

    modalSvg.on('mouseenter', 'polygon', function() {
        if (isDrawing || isDrawingRectangle || isDraggingPoint) return;
        const propId = $(this).data('property-id');
        if (String(propId) === String(activePropertyId)) return;
        highlightElements(propId, true);
    });

    modalSvg.on('mouseleave', 'polygon', function() {
        const propId = $(this).data('property-id');
        highlightElements(propId, false);
    });

    modalObjectsList.on('mouseenter', '.fok-property-list-item', function() {
        if (isDrawing || isDrawingRectangle || isDraggingPoint) return;
        const propId = $(this).data('property-id');
        if (String(propId) === String(activePropertyId)) return;
        highlightElements(propId, true);
    });

    modalObjectsList.on('mouseleave', '.fok-property-list-item', function() {
        const propId = $(this).data('property-id');
        highlightElements(propId, false);
    });

});