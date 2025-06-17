jQuery(document).ready(function($) {
    // Чекаємо, поки об'єкти MetaBox та Fabric.js будуть доступні
    if (typeof rwmb === 'undefined' || typeof fabric === 'undefined') {
        console.error('FlatOkbi: MetaBox or Fabric.js is not loaded.');
        return;
    }

    const modal = $('#fok-floor-editor-modal');
    if (!modal.length) {
        console.error('FlatOkbi: Editor modal not found in DOM.');
        return;
    }

    let canvas;
    let currentFloorGroup; // Зберігаємо посилання на групу полів поверху, який редагуємо
    let isInDrawMode = false;
    let tempPoints = [];
    let tempPolygon = null;

    const defaultStyle = {
        fill: 'rgba(34, 113, 177, 0.5)', stroke: '#2271b1', strokeWidth: 0.5,
        cornerColor: 'white', cornerStrokeColor: 'black', borderColor: '#2271b1',
        cornerSize: 10, transparentCorners: false, objectCaching: false, padding: 10
    };
    
    // --- ГОЛОВНИЙ ОБРОБНИК КЛІКУ ---
    $('#section_details').on('click', '.fok-edit-floor-polygons', function(e) {
        e.preventDefault();
        
        currentFloorGroup = $(this).closest('.rwmb-clone');

        if (!currentFloorGroup.length) {
            alert('Критична помилка: не вдалося знайти батьківську обгортку для групи полів поверху. Перевірте структуру MetaBox.');
            return;
        }

        const imageInput = currentFloorGroup.find('input.rwmb-image_advanced');
        const attachments = imageInput.data('attachments');

        if (!imageInput.length || !attachments || attachments.length === 0) {
            alert('Будь ласка, спочатку завантажте зображення плану та збережіть зміни.');
            return;
        }

        const imageUrl = attachments[0].url;
        const floorNumber = currentFloorGroup.find('input[id*="number"]').val();
        const polygonsDataInput = currentFloorGroup.find('input[id*="polygons_data"]');
        const polygonsData = polygonsDataInput.val();
        
        openModal(floorNumber, imageUrl, polygonsData);
    });

    // --- Функції для роботи модального вікна та редактора ---

    function openModal(floorNumber, imageUrl, polygonsData) {
        modal.find('#fok-modal-floor-number').text(floorNumber || '');
        modal.show();
        
        if (canvas) {
            canvas.dispose();
            canvas = null;
        }
        
        const canvasContainer = modal.find('#fok-floor-canvas-container');
        const canvasElement = modal.find('#fok-floor-canvas');
        canvasElement.get(0).width = canvasContainer.width();
        canvasElement.get(0).height = 400;
        
        canvas = new fabric.Canvas('fok-floor-canvas');
        
        fabric.Image.fromURL(imageUrl, function(img) {
            const scale = canvasContainer.width() / img.width;
            const canvasHeight = img.height * scale;
            
            canvas.setDimensions({ width: canvasContainer.width(), height: canvasHeight });
            canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                scaleX: canvas.width / img.width,
                scaleY: canvas.height / img.height
            });
            
            loadPolygons(polygonsData);
        });

        attachModalEventListeners();
    }
    
    function loadPolygons(polygonsData) {
        if (!polygonsData) return;
        try {
            const data = JSON.parse(polygonsData);
            if (Array.isArray(data)) {
                data.forEach(polyData => {
                    if (polyData.points) {
                        const polygon = new fabric.Polygon(polyData.points, { ...defaultStyle, property_id: polyData.property_id || null });
                        canvas.add(polygon);
                    }
                });
            }
        } catch(e) {
            console.error("FlatOkbi: Error parsing polygon data.", e);
        }
        canvas.renderAll();
    }

    function attachModalEventListeners() {
        const drawBtn = modal.find('#fok-floor-draw-btn');
        drawBtn.off('click').on('click', () => isInDrawMode ? finishDrawing() : startDrawing());
        modal.find('#fok-floor-delete-btn').off('click').on('click', deleteSelectedPolygon);
        $(document).off('keydown.drawfloor').on('keydown.drawfloor', handleKeyPress);
        canvas.off('object:moving').on('object:moving', handleTempPointMove);
    }

    function startDrawing() {
        isInDrawMode = true;
        tempPoints = [];
        canvas.discardActiveObject().renderAll();
        canvas.selection = false;
        canvas.defaultCursor = 'crosshair';
        canvas.forEachObject(o => o.selectable = false);
        modal.find('#fok-floor-draw-btn').text('Завершити');
        canvas.on('mouse:down', handleCanvasClick);
    }
    
    function handleCanvasClick(options) {
        if (!options.pointer || options.target) return;
        const pointer = options.pointer;
        const newPoint = new fabric.Circle({ radius: 5, fill: 'white', stroke: '#2271b1', strokeWidth: 2, left: pointer.x, top: pointer.y, originX: 'center', originY: 'center', hasBorders: false, hasControls: false, name: 'temp_point' });
        canvas.add(newPoint);
        tempPoints.push(newPoint);
        redrawTempPolygon();
    }
    
    function handleTempPointMove() { if (isInDrawMode) redrawTempPolygon(); }
    
    function redrawTempPolygon() {
        if (tempPolygon) canvas.remove(tempPolygon);
        const polyPoints = tempPoints.map(p => ({ x: p.left, y: p.top }));
        if (polyPoints.length > 0) {
            tempPolygon = new fabric.Polygon(polyPoints, { fill: 'rgba(34, 113, 177, 0.3)', stroke: '#2271b1', strokeWidth: 1, selectable: false, evented: false });
            canvas.add(tempPolygon);
            tempPolygon.sendToBack();
        }
        canvas.renderAll();
    }

    function finishDrawing() {
        if (tempPoints.length < 3) { alert('Полігон повинен мати щонайменше 3 точки.'); return; }
        const finalPoints = tempPoints.map(p => ({ x: p.left, y: p.top }));
        const newPolygon = new fabric.Polygon(finalPoints, { ...defaultStyle });
        canvas.add(newPolygon);
        canvas.setActiveObject(newPolygon);
        exitDrawMode();
    }

    function exitDrawMode() {
        tempPoints.forEach(p => canvas.remove(p));
        if (tempPolygon) canvas.remove(tempPolygon);
        tempPoints = []; 
        tempPolygon = null;
        isInDrawMode = false;
        canvas.selection = true;
        canvas.defaultCursor = 'default';
        canvas.forEachObject(o => o.selectable = true);
        modal.find('#fok-floor-draw-btn').text('Малювати полігон');
        canvas.off('mouse:down', handleCanvasClick);
    }
    
    function handleKeyPress(e) {
        if (e.key === 'Escape' && isInDrawMode) exitDrawMode();
        if (e.key === 'Enter' && isInDrawMode) finishDrawing();
    }

    function deleteSelectedPolygon() {
        const activeObject = canvas.getActiveObject();
        if (activeObject && activeObject.type === 'polygon') {
            if (confirm('Видалити вибраний полігон?')) canvas.remove(activeObject);
        } else {
            alert('Спочатку виберіть полігон.');
        }
    }

    modal.find('#fok-modal-save').on('click', function() {
        const polygonsData = canvas.getObjects('polygon').map(poly => ({
            points: poly.get('points').map(p => ({ x: p.x, y: p.y })),
            property_id: poly.property_id || null
        }));
        if (currentFloorGroup) {
            currentFloorGroup.find('input[id*="polygons_data"]').val(JSON.stringify(polygonsData)).trigger('change');
        }
        closeModal();
    });
    
    modal.find('.fok-modal-close, .fok-modal-overlay').on('click', () => {
        if (confirm('Ви впевнені? Незбережені зміни буде втрачено.')) closeModal();
    });

    // **ОНОВЛЕНА ФУНКЦІЯ**
    function closeModal() {
        // Спочатку виходимо з режиму малювання, щоб очистити тимчасові об'єкти
        // поки `canvas` ще існує.
        if (isInDrawMode) {
            exitDrawMode();
        }
        
        // Тепер, коли все очищено, можна безпечно знищувати полотно.
        if (canvas) {
            canvas.dispose();
            canvas = null;
        }
        
        // І решта дій по закриттю вікна.
        modal.hide();
        currentFloorGroup = null;
        $(document).off('.drawfloor');
    }
});
