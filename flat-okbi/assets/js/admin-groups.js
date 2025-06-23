// assets/js/admin-groups.js

jQuery(document).ready(function($) {
    'use strict';

    const mainContainer = $('#fok-floors-container');
    if (!mainContainer.length) return;

    // Обробник для кнопки видалення поверху
    mainContainer.on('click', '.fok-delete-floor-btn', function() {
        if (confirm('Ви впевнені, що хочете видалити цей поверх та всі його налаштування?')) {
            $(this).closest('.fok-floor-row').remove();
        }
    });

    // Обробник для збереження даних при оновленні запису
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

    // ++ ДОДАНО: Обробники для завантаження та видалення зображень ++
    let mediaFrame;

    // Клік на кнопку "Додати зображення"
    mainContainer.on('click', '.fok-upload-image-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $row = $button.closest('.fok-floor-row');
        const $imageIdField = $row.find('.fok-floor-image-id');
        const $previewDiv = $row.find('.fok-image-preview-thumb');
        const $removeBtn = $row.find('.fok-remove-image-btn');

        mediaFrame = wp.media({
            title: 'Вибрати зображення плану поверху',
            button: { text: 'Використати це зображення' },
            multiple: false
        });

        mediaFrame.on('select', function() {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            $imageIdField.val(attachment.id);
            $previewDiv.html(`<img src="${attachment.sizes.thumbnail.url}" style="max-width:100px; height:auto;">`);
            $removeBtn.show();
            // Переініціалізуємо редактор, щоб з'явилось велике зображення
            initializeFloorEditor($row);
        });

        mediaFrame.open();
    });

    // Клік на кнопку "Видалити" зображення
    mainContainer.on('click', '.fok-remove-image-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $row = $button.closest('.fok-floor-row');
        
        $row.find('.fok-floor-image-id').val('');
        $row.find('.fok-image-preview-thumb').empty();
        $button.hide();
        // Переініціалізуємо редактор, щоб прибрати зображення
        initializeFloorEditor($row);
    });
    // ++ КІНЕЦЬ ДОДАНОГО КОДУ ++


    const currentSectionId = fok_groups_data.post_id;
    const nonce = fok_groups_data.nonce;
    let floorPropertiesCache = {}; // Кеш для об'єктів по поверхах

    // Ініціалізація редактора для існуючих та нових рядків поверхів
    function initializeFloorEditor($row) {
        const editorWrapper = $row.find('.fok-polygon-editor-wrapper');
        const imageContainer = editorWrapper.find('.fok-editor-image-container');
        const svg = editorWrapper.find('.fok-editor-svg');
        const polygonsTextarea = $row.find('.fok-floor-polygons-data');
        const objectsListContainer = editorWrapper.find('.fok-floor-objects-list');

        let polygonsData = [];
        try {
            const parsedData = JSON.parse(polygonsTextarea.val() || '[]');
            polygonsData = Array.isArray(parsedData) ? parsedData : [];
        } catch (e) {
            polygonsData = [];
            console.error("Error parsing polygons JSON:", e);
        }

        let activePropertyId = null;
        let isDrawing = false;
        
        // --- ОСНОВНА ЛОГІКА ---

        function getPolygonByPropertyId(propId) {
            return polygonsData.find(p => p.property_id == propId);
        }

        function getPolygonIndexByPropertyId(propId) {
            return polygonsData.findIndex(p => p.property_id == propId);
        }
        
        function startDrawingForProperty(propId) {
            if (!propId) return;

            let polygon = getPolygonByPropertyId(propId);
            if (!polygon) {
                polygon = { property_id: propId, points: [] };
                polygonsData.push(polygon);
            }
            
            activePropertyId = propId;
            isDrawing = true;
            
            imageContainer.css('cursor', 'crosshair');
            renderAll();
        }
        
        function stopDrawing() {
            isDrawing = false;
            imageContainer.css('cursor', 'default');
            // activePropertyId = null; // Не скидаємо, щоб полігон залишався активним
            renderAll();
        }

        function saveData() {
            polygonsTextarea.val(JSON.stringify(polygonsData));
        }

        // --- ЛОГІКА РЕНДЕРИНГУ ---

        function renderAll() {
            renderObjectList();
            renderSVG();
        }

        function renderObjectList() {
            const floorNumber = $row.find('.fok-floor-number').val();
            const properties = floorPropertiesCache[floorNumber] || [];
            objectsListContainer.empty();

            if (properties.length === 0) {
                objectsListContainer.html('<p>Немає об\'єктів на цьому поверсі.</p>');
                return;
            }

            properties.forEach(prop => {
                const polygon = getPolygonByPropertyId(prop.id);
                const hasPolygon = polygon && polygon.points.length > 0;
                const statusIcon = hasPolygon ? 'dashicons-yes-alt' : 'dashicons-edit-page';
                const itemClass = prop.id == activePropertyId ? 'active' : '';

                const $item = $(`
                    <div class="fok-property-list-item ${itemClass}" data-property-id="${prop.id}">
                        <span>${prop.title}</span>
                        <div class="actions">
                            <span class="dashicons ${statusIcon}"></span>
                            ${hasPolygon ? '<span class="dashicons dashicons-trash fok-delete-polygon-btn" title="Видалити полігон"></span>' : ''}
                        </div>
                    </div>
                `);
                objectsListContainer.append($item);
            });
        }

        function renderSVG() {
            svg.empty();
            polygonsData.forEach(poly => {
                if (!poly.points || poly.points.length === 0) return;

                const pointsAttr = poly.points.map(p => `${p.x * 100},${p.y * 100}`).join(' ');
                const $polygon = $(document.createElementNS('http://www.w3.org/2000/svg', 'polygon'));
                
                $polygon
                    .attr('points', pointsAttr)
                    .attr('data-property-id', poly.property_id)
                    .toggleClass('active', poly.property_id == activePropertyId);
                svg.append($polygon);
            });
        }

        // --- ОБРОБНИКИ ПОДІЙ ---

        // Клік по об'єкту у списку
        objectsListContainer.on('click', '.fok-property-list-item', function(e) {
            if ($(e.target).hasClass('fok-delete-polygon-btn')) return; // Ігноруємо клік по кнопці видалення

            const propId = $(this).data('property-id');
            startDrawingForProperty(propId);
        });

        // Видалення полігону
        objectsListContainer.on('click', '.fok-delete-polygon-btn', function(e) {
            e.stopPropagation();
            if (confirm('Видалити полігон для цього об\'єкта?')) {
                const propId = $(this).closest('.fok-property-list-item').data('property-id');
                const polyIndex = getPolygonIndexByPropertyId(propId);
                if (polyIndex > -1) {
                    polygonsData.splice(polyIndex, 1);
                    if (activePropertyId == propId) {
                        activePropertyId = null;
                    }
                    saveData();
                    renderAll();
                }
            }
        });

        // Малювання на зображенні
        imageContainer.on('click', function(e) {
            if (!isDrawing) return;

            const rect = svg[0].getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top) / rect.height;

            const polygon = getPolygonByPropertyId(activePropertyId);
            if (polygon) {
                polygon.points.push({ x: x.toFixed(4), y: y.toFixed(4) });
                saveData();
                renderSVG();
            }
        });
        
        // Завершення малювання по правому кліку або Escape
        $(document).on('keydown', function(e) {
            if(isDrawing && e.key === "Escape") stopDrawing();
        });
        imageContainer.on('contextmenu', function(e) {
            if (isDrawing) {
                e.preventDefault();
                stopDrawing();
            }
        });

        // Ініціалізація
        const floorNumber = $row.find('.fok-floor-number').val();
        if (floorNumber) {
            loadPropertiesForFloor(floorNumber, renderAll);
        } else {
            objectsListContainer.html('<p style="font-style: italic; color: #777;">Вкажіть номер поверху.</p>');
        }
    }

    /**
     * Завантажує список об'єктів для конкретного поверху і кешує його.
     * Викликає callback після завантаження.
     */
    function loadPropertiesForFloor(floorNumber, callback) {
        if (floorPropertiesCache[floorNumber]) {
            if(callback) callback();
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fok_get_properties_for_floor_json', // Новий AJAX action
                nonce: nonce,
                section_id: currentSectionId,
                floor_number: floorNumber
            },
            success: function(response) {
                if (response.success) {
                    floorPropertiesCache[floorNumber] = response.data;
                } else {
                    floorPropertiesCache[floorNumber] = [];
                }
                if(callback) callback();
            },
            error: function() {
                floorPropertiesCache[floorNumber] = [];
                 if(callback) callback();
            }
        });
    }

    // --- Глобальні обробники подій для контейнера ---
    mainContainer.on('click', '.fok-upload-image-btn', function(e) {
        // ... (ваш існуючий код для завантаження зображення)
    });
    
    // ... інші ваші обробники ...
    
    // Збереження даних при відправці форми
    $('form#post').on('submit', function() {
        mainContainer.find('.fok-floor-row').each(function() {
            // Дані полігонів вже оновлюються в реальному часі в textarea,
            // тут збираємо основні дані про поверхи
            const floorsData = [];
            $('.fok-floor-row').each(function() {
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

    // Ініціалізація для всіх існуючих рядків
    mainContainer.find('.fok-floor-row').each(function() {
        initializeFloorEditor($(this));
    });

    // Ініціалізація для нових рядків
    $('#fok-add-floor-btn').on('click', function() {
        const template = $('#fok-floor-template').html();
        const $newRow = $(template).appendTo(mainContainer);
        initializeFloorEditor($newRow);
    });
    
    // Оновлення при зміні номера поверху
    mainContainer.on('blur', '.fok-floor-number', function() {
        const $row = $(this).closest('.fok-floor-row');
        const newFloorNumber = $(this).val();
        loadPropertiesForFloor(newFloorNumber, () => {
             // Переініціалізуємо редактор, щоб оновити списки
             initializeFloorEditor($row);
        });
    });

});