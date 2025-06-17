jQuery(document).ready(function($) {
    const wrapper = $('#fok-genplan-editor-wrapper');
    if (!wrapper.length || typeof fabric === 'undefined') {
        return;
    }

    const hiddenInput = wrapper.find('#fok_rc_genplan_polygons');
    const drawBtn = wrapper.find('#fok-draw-polygon-btn');
    const deleteBtn = wrapper.find('#fok-delete-selected');
    const sectionSelect = wrapper.find('#fok-polygon-section-link');
    const polygonList = wrapper.find('#fok-polygon-list ul');
    const uploadBtn = wrapper.find('#fok_upload_image_button');
    const canvasContainer = wrapper.find('#fok-genplan-canvas-container');
    const imageUrl = wrapper.data('imageUrl');

    if (!imageUrl) {
        canvasContainer.html('<div class="fok-canvas-loader">Будь ласка, спочатку завантажте зображення генплану.</div>');
        return;
    }
    
    // --- Стан програми ---
    let isInDrawMode = false;
    let tempPoints = []; // Масив з об'єктами тимчасових точок (кружечків)
    let tempPolygon = null; // Об'єкт тимчасового полігону

    const canvas = new fabric.Canvas('fok-genplan-canvas');
    const defaultStyle = {
        fill: 'rgba(34, 113, 177, 0.5)', stroke: '#2271b1', strokeWidth: 0.5,
        cornerColor: 'white', cornerStrokeColor: 'black', borderColor: '#2271b1',
        cornerSize: 10, transparentCorners: false, objectCaching: false, padding: 10
    };

    // --- Ініціалізація та завантаження ---
    fabric.Image.fromURL(imageUrl, function(img) {
        const scale = canvasContainer.width() / img.width;
        const canvasHeight = img.height * scale;
        canvas.setDimensions({ width: canvasContainer.width(), height: canvasHeight });
        canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), { scaleX: canvas.width / img.width, scaleY: canvas.height / img.height });
        loadPolygons();
    });

    function loadPolygons() {
        try {
            const data = JSON.parse(hiddenInput.val());
            if (Array.isArray(data)) {
                data.forEach(polyData => {
                    if (polyData.points && polyData.section_id) {
                        const polygon = new fabric.Polygon(polyData.points, { ...defaultStyle, section_id: polyData.section_id });
                        canvas.add(polygon);
                    }
                });
                renderPolygonList();
            }
        } catch(e) {}
    }

    // --- Збереження та оновлення списку ---
    function saveCanvasState() {
        const polygonsData = canvas.getObjects('polygon').map(poly => ({
            section_id: poly.section_id,
            points: poly.get('points').map(p => ({ x: p.x, y: p.y }))
        }));
        hiddenInput.val(JSON.stringify(polygonsData));
        renderPolygonList();
    }

    function renderPolygonList() {
        polygonList.empty();
        const polygons = canvas.getObjects('polygon');
        if (polygons.length === 0) {
            polygonList.append('<li>Немає створених полігонів.</li>');
            return;
        }
        polygons.forEach((poly, index) => {
            const sectionName = sectionSelect.find(`option[value="${poly.section_id}"]`).text() || 'Невідома секція';
            const li = $(`<li data-index="${index}"><span>${sectionName}</span></li>`);
            if (poly === canvas.getActiveObject()) li.addClass('fok-active-polygon-item');
            li.on('click', () => { if (!isInDrawMode) canvas.setActiveObject(poly).renderAll(); });
            polygonList.append(li);
        });
    }

    // --- Логіка малювання ---
    drawBtn.on('click', () => isInDrawMode ? finishDrawing() : startDrawing());

    function startDrawing() {
        const sectionId = sectionSelect.val();
        if (!sectionId) { alert('Будь ласка, спочатку виберіть секцію для прив\'язки.'); return; }
        if (canvas.getObjects('polygon').some(p => p.section_id == sectionId)) { alert('Полігон для цієї секції вже існує.'); return; }
        
        isInDrawMode = true;
        tempPoints = [];
        
        canvas.discardActiveObject();
        canvas.selection = false;
        canvas.defaultCursor = 'crosshair';
        canvas.forEachObject(o => o.selectable = false);
        
        drawBtn.text('Завершити малювання');
        deleteBtn.prop('disabled', true);
        sectionSelect.prop('disabled', true);
        
        canvas.on('mouse:down', handleCanvasClick);
        canvas.on('object:moving', handleTempPointMove);
        $(document).on('keydown.draw', handleKeyPress);
    }

        function handleCanvasClick(options) {
        if (!options.pointer) return; // ВИПРАВЛЕНО: тепер клік спрацює будь-де
        const pointer = options.pointer;
        
        const newPoint = new fabric.Circle({
            radius: 5, fill: 'white', stroke: '#2271b1', strokeWidth: 2,
            left: pointer.x, top: pointer.y, originX: 'center', originY: 'center',
            hasBorders: false, hasControls: false, name: 'temp_point'
        });
        
        canvas.add(newPoint);
        tempPoints.push(newPoint);
        
        redrawTempPolygon();
    }

    function handleTempPointMove(options) {
        if (!isInDrawMode || !options.target || options.target.name !== 'temp_point') return;
        redrawTempPolygon();
    }

    // --- НОВА ФУНКЦІЯ для перемальовування тимчасової фігури ---
    function redrawTempPolygon() {
        // Спочатку видаляємо старий тимчасовий полігон, якщо він є
        if (tempPolygon) {
            canvas.remove(tempPolygon);
        }
        
        const polyPoints = tempPoints.map(p => ({ x: p.left, y: p.top }));
        
        if (polyPoints.length > 1) {
            tempPolygon = new fabric.Polygon(polyPoints, {
                fill: 'rgba(34, 113, 177, 0.3)',
                stroke: '#2271b1',
                strokeWidth: 1,
                selectable: false,
                evented: false,
            });
            canvas.add(tempPolygon);
            // Поміщаємо полігон під точки
            tempPolygon.sendToBack();
        }
        canvas.renderAll();
    }

    function finishDrawing() {
        if (tempPoints.length < 3) { alert('Полігон повинен мати щонайменше 3 точки.'); return; }
        const finalPoints = tempPoints.map(p => ({ x: p.left, y: p.top }));
        const newPolygon = new fabric.Polygon(finalPoints, { ...defaultStyle, section_id: sectionSelect.val() });
        
        canvas.add(newPolygon);
        canvas.setActiveObject(newPolygon);
        exitDrawMode();
        saveCanvasState();
    }

    function exitDrawMode() {
        // Видаляємо всі тимчасові об'єкти (і точки, і полігон)
        tempPoints.forEach(p => canvas.remove(p));
        if (tempPolygon) canvas.remove(tempPolygon);
        
        tempPoints = []; tempPolygon = null;
        isInDrawMode = false;
        
        canvas.selection = true;
        canvas.defaultCursor = 'default';
        canvas.forEachObject(o => o.selectable = true);
        
        drawBtn.text('Малювати полігон');
        deleteBtn.prop('disabled', false);
        sectionSelect.prop('disabled', false);
        
        canvas.off('mouse:down', handleCanvasClick);
        canvas.off('object:moving', handleTempPointMove);
        $(document).off('keydown.draw');
    }

    function handleKeyPress(e) {
        if (e.key === 'Escape') exitDrawMode();
        if (e.key === 'Enter') finishDrawing();
    }
    
    // Пряме редагування точок для вже існуючих полігонів
    (function() {
        function renderPolygonControl(ctx, left, top, styleOverride, fabricObject) {
            ctx.save();
            ctx.translate(left, top);
            ctx.rotate(fabric.util.degreesToRadians(fabricObject.angle));
            ctx.beginPath(); ctx.arc(0, 0, 5, 0, 2 * Math.PI);
            ctx.fillStyle = '#fff'; ctx.fill();
            ctx.strokeStyle = '#e35122'; ctx.lineWidth = 2; ctx.stroke();
            ctx.restore();
        }
        function actionHandler(eventData, transform, x, y) {
            const polygon = transform.target;
            const currentControl = polygon.controls[transform.corner];
            const mouseLocalPosition = polygon.getLocalPointer(eventData, this.pointer);
            polygon.points[currentControl.pointIndex] = { x: mouseLocalPosition.x, y: mouseLocalPosition.y };
            return true;
        }
        canvas.on('object:selected', function(e) {
            if (isInDrawMode || !e.target || e.target.type !== 'polygon') return;
            const polygon = e.target;
            polygon.controls = {};
            polygon.points.forEach(function(point, index) {
                const controlName = 'p' + index;
                polygon.controls[controlName] = new fabric.Control({
                    positionHandler: function(dim, finalMatrix, fabricObject) {
                        const p = fabricObject.points[index];
                        return fabric.util.transformPoint({ x: p.x, y: p.y }, fabricObject.calcTransformMatrix());
                    },
                    actionHandler: actionHandler, render: renderPolygonControl,
                    pointIndex: index, cursorStyle: 'grab'
                });
            });
        });
        canvas.on('before:selection:cleared', function(e) {
            if (e.target && e.target.type === 'polygon') {
                e.target.controls = fabric.Object.prototype.controls;
            }
        });
    })();

    deleteBtn.on('click', function() {
        if (isInDrawMode) return;
        const activeObject = canvas.getActiveObject();
        if (activeObject && activeObject.type === 'polygon') {
            if (confirm('Ви впевнені, що хочете видалити вибраний полігон?')) {
                canvas.remove(activeObject);
                saveCanvasState();
            }
        } else {
            alert('Будь ласка, спочатку виберіть полігон на зображенні.');
        }
    });

    canvas.on({
        'object:modified': saveCanvasState,
        'selection:created': renderPolygonList,
        'selection:updated': renderPolygonList,
        'selection:cleared': renderPolygonList
    });
    
    uploadBtn.on('click', function(e) {
        e.preventDefault();
        const frame = wp.media({ title: 'Вибрати генплан', button: { text: 'Використовувати це зображення' }, multiple: false });
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $('#fok_rc_genplan_image').val(attachment.id);
            $('#publish').click(); 
        });
        frame.open();
    });
});
