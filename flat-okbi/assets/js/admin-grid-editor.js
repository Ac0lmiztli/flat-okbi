jQuery(document).ready(function($) {
    'use strict';

    // --- Базові налаштування ---
    const editorWrapper = $('.fok-grid-editor-wrapper');
    if (!editorWrapper.length) return;

    const currentSectionId = editorWrapper.data('section-id');
    const gridContainer = editorWrapper.find('.fok-editor-grid-container');
    const unassignedList = editorWrapper.find('.fok-unassigned-list');
    const loader = editorWrapper.find('.fok-editor-loader');
    const saveButton = $('#fok-save-grid-changes');
    const saveStatus = editorWrapper.find('.fok-save-status');

    let changedProperties = {};
    let gridCellSize = { width: 60, height: 40, gap: 4 };

    // --- Функції перевірки колізій ---
    function isAreaFree(grid, tRow, tCol, rSpan, cSpan, draggedId) {
        for (let r = tRow; r < tRow + rSpan; r++) {
            for (let c = tCol; c < tCol + cSpan; c++) {
                const existingBlock = grid.find('.fok-property-block').filter(function() {
                    if ($(this).data('id') === draggedId) return false;
                    
                    const blockRowStart = parseInt($(this).css('grid-row-start'));
                    const blockColStart = parseInt($(this).css('grid-column-start')) - 1;
                    const blockRowSpan = parseInt(($(this).css('grid-row-end') || 'span 1').replace('span ', ''));
                    const blockColSpan = parseInt(($(this).css('grid-column-end') || 'span 1').replace('span ', ''));

                    return (r >= blockRowStart && r < blockRowStart + blockRowSpan && c >= blockColStart && c < blockColStart + blockColSpan);
                });
                
                if (existingBlock.length > 0) return false;
            }
        }
        return true;
    }

    // --- Основна логіка ---
    function loadSectionGrid() {
        $.ajax({
            url: fok_editor_ajax.ajax_url, type: 'POST',
            data: { action: 'fok_get_section_grid_data', nonce: fok_editor_ajax.nonce, section_id: currentSectionId },
            beforeSend: () => {
                loader.show();
                gridContainer.add(unassignedList).empty().hide();
                changedProperties = {}; updateSaveButtonState(true);
            },
            success: (response) => {
                if (response.success) {
                    renderGrid(response.data);
                    renderUnassignedList(response.data.unassigned_properties);
                    initInteractivity();
                    
                    // Оновлюємо поля в метабоксі "Параметри секції"
                    const { grid_cols, max_floor } = response.data;
                    $('#fok_section_grid_columns').val(grid_cols);
                    $('#fok_section_total_floors').val(max_floor);

                } else { 
                    // Використовуємо переклад
                    gridContainer.html(`<p class="error">${fok_grid_i10n.error_prefix} ${response.data || fok_grid_i10n.unknown_error}</p>`); 
                }
            },
            error: () => {
                // Використовуємо переклад
                gridContainer.html(`<p class="error">${fok_grid_i10n.server_error}</p>`);
            },
            complete: () => {
                loader.hide();
                gridContainer.add(unassignedList).fadeIn(300, () => {
                    const firstCell = $('.fok-grid-cell').first();
                    if (firstCell.length) {
                        gridCellSize.width = firstCell.outerWidth();
                        gridCellSize.height = firstCell.outerHeight();
                    }
                });
            }
        });
    }

    function renderGrid(data) {
        const { grid_cols, max_floor, min_floor, assigned_properties } = data;
        const totalRows = (max_floor >= min_floor) ? (max_floor - min_floor + 1) : 10;
        const effectiveMaxFloor = (max_floor >= min_floor) ? max_floor : totalRows;

        let gridHtml = `<div class="fok-grid" style="--grid-cols: ${grid_cols}; --grid-rows: ${totalRows};">`;
        for (let r = 1; r <= totalRows; r++) {
            const floor = effectiveMaxFloor - r + 1;
            gridHtml += `<div class="fok-grid-floor-label" style="grid-row: ${r};">${floor}</div>`;
            for (let c = 1; c <= grid_cols; c++) {
                gridHtml += `<div class="fok-grid-cell" style="grid-row: ${r}; grid-column: ${c + 1};" data-row="${r}" data-col="${c}" data-floor="${floor}"></div>`;
            }
        }
        assigned_properties.forEach(prop => {
            const rowStart = effectiveMaxFloor - (prop.y_start + prop.y_span - 1) + 1;
            const colStart = prop.x_start + 1;
            gridHtml += createPropertyBlockHtml(prop, rowStart, colStart);
        });
        gridHtml += '</div>';
        gridContainer.html(gridHtml);
    }

    function renderUnassignedList(properties) {
        // Використовуємо переклад
        const listHtml = properties.length
            ? properties.map(prop => `<div class="fok-unassigned-item status-${prop.status}" data-id="${prop.id}" data-prop='${JSON.stringify(prop)}'>${prop.title}</div>`).join('')
            : `<p class="empty-list">${fok_grid_i10n.all_objects_distributed}</p>`;
        unassignedList.html(listHtml);
    }
    
    function initInteractivity() {
        const grid = gridContainer.find('.fok-grid');
        
        makeInteractive(grid.find('.fok-property-block'));

        unassignedList.find('.fok-unassigned-item').draggable({
            helper: 'clone',
            revert: 'invalid',
            zIndex: 100,
        });

        grid.find('.fok-grid-cell').droppable({
            accept: '.fok-unassigned-item, .fok-property-block',
            
            // Ми більше не використовуємо hoverClass, натомість додаємо власну логіку
            // hoverClass: 'is-hovered', 

            over: function(event, ui) {
                const $cell = $(this);
                const $item = ui.draggable;
                const grid = $cell.closest('.fok-grid');
                let propData = $item.data('prop');

                // Визначаємо розміри об'єкта, який перетягуємо
                let ySpan = 1, xSpan = 1;
                if ($item.hasClass('fok-property-block')) {
                    ySpan = parseInt(($item.css('grid-row-end') || 'span 1').replace('span ', ''), 10);
                    xSpan = parseInt(($item.css('grid-column-end') || 'span 1').replace('span ', ''), 10);
                } else if (propData) { // Для нерозподілених об'єктів
                    ySpan = propData.y_span || 1;
                    xSpan = propData.x_span || 1;
                }

                const targetRow = $cell.data('row');
                const targetCol = $cell.data('col');

                // Підсвічуємо всі клітинки, які займе об'єкт
                for (let r = 0; r < ySpan; r++) {
                    for (let c = 0; c < xSpan; c++) {
                        grid.find(`.fok-grid-cell[data-row="${targetRow + r}"][data-col="${targetCol + c}"]`).addClass('is-hovered-area');
                    }
                }
            },

            out: function(event, ui) {
                // Коли курсор залишає клітинку, знімаємо все підсвічування
                const grid = $(this).closest('.fok-grid');
                grid.find('.fok-grid-cell.is-hovered-area').removeClass('is-hovered-area');
            },

            drop: function(event, ui) {
                const $cell = $(this);
                const $item = ui.draggable;
                const propId = $item.data('id');
                let propData = $item.data('prop');
                const grid = $cell.closest('.fok-grid');
                const totalCols = parseInt(grid.css('--grid-cols'));
                const totalRows = parseInt(grid.css('--grid-rows'));

                // Прибираємо підсвічування після того, як відпустили об'єкт
                grid.find('.fok-grid-cell.is-hovered-area').removeClass('is-hovered-area');

                const targetRow = $cell.data('row');
                const targetCol = $cell.data('col');

                let ySpan = 1, xSpan = 1;
                if ($item.hasClass('fok-property-block')) {
                    ySpan = parseInt(($item.css('grid-row-end') || 'span 1').replace('span ', ''), 10);
                    xSpan = parseInt(($item.css('grid-column-end') || 'span 1').replace('span ', ''), 10);
                } else if (propData) {
                    ySpan = propData.y_span || 1;
                    xSpan = propData.x_span || 1;
                }
                
                // Перевірка на вихід за межі сітки
                if (targetCol + xSpan - 1 > totalCols || targetRow + ySpan - 1 > totalRows) {
                    if ($item.hasClass('fok-property-block')) {
                        $item.animate({ top: 0, left: 0 }, 300); // Повертаємо блок на місце
                    }
                    return; // Скасовуємо розміщення
                }

                if (!isAreaFree(grid, targetRow, targetCol, ySpan, xSpan, propId)) {
                    if ($item.hasClass('fok-property-block')) {
                        $item.animate({ top: 0, left: 0 }, 300);
                    }
                    return; 
                }
                
                let $propBlock = grid.find(`.fok-property-block[data-id="${propId}"]`);

                if ($item.hasClass('fok-unassigned-item')) {
                    propData = $item.data('prop');
                    const newBlockHtml = createPropertyBlockHtml(propData, 1, 1);
                    $propBlock = $(newBlockHtml).appendTo(grid);
                    $item.remove();
                    makeInteractive($propBlock);
                }
                
                $propBlock.css({ top: 0, left: 0 });
                updatePropertyPosition($propBlock, $cell);
            }
        });

        unassignedList.parent().droppable({
            accept: '.fok-property-block',
            hoverClass: 'is-hovered',
            drop: function(event, ui) {
                const $propBlock = ui.draggable;
                const propId = $propBlock.data('id');
                const propData = $propBlock.data('prop') || { title: $propBlock.find('.fok-prop-title').text(), status: 'unknown' };
                
                const unassignedItemHtml = `<div class="fok-unassigned-item status-${propData.status}" data-id="${propId}" data-prop='${JSON.stringify(propData)}'>${propData.title}</div>`;
                const $newUnassignedItem = $(unassignedItemHtml).appendTo(unassignedList);

                $newUnassignedItem.draggable({
                    helper: 'clone', revert: 'invalid', zIndex: 100,
                });

                $propBlock.remove();

                changedProperties[propId] = {
                    id: propId,
                    x_start: 0, 
                    y_start: -100, // Встановлюємо спеціальне значення для нерозподілених
                    x_span: 1, 
                    y_span: 1
                };
                updateSaveButtonState();
            }
        });
    }

    function makeInteractive($elements) {
        if (!$elements.length) return;

        $elements.draggable({
            zIndex: 100,
            revert: 'invalid'
        });

        $elements.find('.fok-resize-handle').on('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $handle = $(this);
            const $propBlock = $handle.closest('.fok-property-block');
            const grid = gridContainer.find('.fok-grid');

            $propBlock.draggable('disable');

            const startData = {
                mouseX: e.pageX,
                mouseY: e.pageY,
                width: $propBlock.outerWidth(),
                height: $propBlock.outerHeight(),
                startRow: parseInt($propBlock.css('grid-row-start')),
                startCol: parseInt($propBlock.css('grid-column-start')),
                startRowSpan: parseInt(($propBlock.css('grid-row-end') || 'span 1').replace('span ', ''), 10),
                startColSpan: parseInt(($propBlock.css('grid-column-end') || 'span 1').replace('span ', ''), 10),
                originalTop: parseInt($propBlock.css('top')) || 0
            };

            $(document).on('mousemove.fokResize', function(moveEvent) {
                const deltaX = moveEvent.pageX - startData.mouseX;
                const deltaY = moveEvent.pageY - startData.mouseY;

                const newWidth = startData.width + deltaX;
                const newHeight = startData.height - deltaY;

                $propBlock.outerWidth(newWidth);
                $propBlock.outerHeight(newHeight);
                $propBlock.css('top', (startData.originalTop + deltaY) + 'px');
            });

            $(document).on('mouseup.fokResize', function() {
                $(document).off('mousemove.fokResize mouseup.fokResize');
                $propBlock.draggable('enable');
                $propBlock.css('top', startData.originalTop + 'px');

                const propId = $propBlock.data('id');
                const newRowSpan = Math.max(1, Math.round($propBlock.outerHeight() / (gridCellSize.height + gridCellSize.gap)));
                const newColSpan = Math.max(1, Math.round($propBlock.outerWidth() / (gridCellSize.width + gridCellSize.gap)));
                const newStartRow = startData.startRow - (newRowSpan - startData.startRowSpan);

                const totalCols = parseInt(grid.css('--grid-cols'));
                const totalRows = parseInt(grid.css('--grid-rows'));
                const logicalStartCol = startData.startCol - 1;
                const endCol = logicalStartCol + newColSpan - 1;
                const endRow = newStartRow + newRowSpan - 1;

                if (newStartRow > 0 && endRow <= totalRows && endCol <= totalCols && isAreaFree(grid, newStartRow, logicalStartCol, newRowSpan, newColSpan, propId)) {
                    // 1. Оновлюємо CSS-стилі блоку
                    updatePropertySize($propBlock, newRowSpan, newColSpan, newStartRow);

                    // 2. === ГОЛОВНЕ ВИПРАВЛЕННЯ ===
                    // Викликаємо функцію, що реєструє зміни для збереження.
                    updateChangedData($propBlock);

                } else {
                    alert(fok_grid_i10n.resize_impossible);
                    $propBlock.css({
                        'width': '',
                        'height': '',
                        'grid-row-start': startData.startRow,
                        'grid-row-end': `span ${startData.startRowSpan}`,
                        'grid-column-end': `span ${startData.startColSpan}`
                    });
                }
            });
        });
    }

    // --- Функції оновлення даних ---
    function updatePropertyPosition($propBlock, $cell) {
        $propBlock.css({
            'grid-row-start': $cell.data('row'),
            'grid-column-start': $cell.data('col') + 1,
        });
        updateChangedData($propBlock);
    }

    function updatePropertySize($element, newRowSpan, newColSpan, newStartRow = null) {
        // Створюємо об'єкт зі змінами в CSS
        const cssChanges = {
            'width': '',
            'height': '',
            'grid-row-end': `span ${newRowSpan}`,
            'grid-column-end': `span ${newColSpan}`
        };

        // Якщо прийшла нова стартова позиція, додаємо її
        if (newStartRow !== null) {
            cssChanges['grid-row-start'] = newStartRow;
        }

        // Застосовуємо всі CSS-зміни
        $element.css(cssChanges);
    }
    
    function updateChangedData($propBlock) {
        const propertyId = $propBlock.data('id');
        if (!propertyId) return;

        const rowStart = parseInt($propBlock.css('grid-row-start'), 10) || 1;
        const colStart = (parseInt($propBlock.css('grid-column-start'), 10) || 2) - 1;
        const rowSpan = parseInt(($propBlock.css('grid-row-end') || 'span 1').replace('span ', ''), 10) || 1;
        const colSpan = parseInt(($propBlock.css('grid-column-end') || 'span 1').replace('span ', ''), 10) || 1;
        const max_floor = parseInt($('.fok-grid-floor-label').first().text(), 10) || 10;
        
        changedProperties[propertyId] = {
            id: propertyId,
            x_start: colStart, y_start: max_floor - (rowStart + rowSpan - 1) + 1,
            x_span: colSpan, y_span: rowSpan
        };
        updateSaveButtonState();
    }
    
    // --- Допоміжні та сервісні функції ---
    function createPropertyBlockHtml(prop, row, col) {
        // Використовуємо переклад
        return `<div class="fok-property-block status-${prop.status}"
                     data-id="${prop.id}"
                     data-prop='${JSON.stringify(prop)}'
                     style="grid-area: ${row} / ${col} / span ${prop.y_span} / span ${prop.x_span};">
            <div class="fok-prop-title">${prop.title}</div>
            <div class="fok-resize-handle"></div>
        </div>`;
    }

    // --- Логіка Контекстного Меню ---
    function showContextMenu(e, propData) {
        $('.fok-context-menu').remove(); // Видаляємо попередні меню
        
        const statuses = {
            'vilno': 'Вільно',
            'zabronovano': 'Заброньовано',
            'prodano': 'Продано'
        };

        let statusSubMenu = '';
        for (const [slug, name] of Object.entries(statuses)) {
            if (propData.status !== slug) { // Не показуємо поточний статус
                statusSubMenu += `<li><a href="#" class="fok-cm-action" data-action="change-status" data-status="${slug}">${name}</a></li>`;
            }
        }
        
        const menuHtml = `
            <div class="fok-context-menu">
                <ul>
                    <li><span class="fok-cm-info">Площа: ${propData.area} м²</span></li>
                    <li class="fok-cm-separator"></li>
                    <li><a href="${propData.edit_link}" target="_blank">Редагувати об'єкт</a></li>
                    <li>
                        <a href="#" class="has-submenu">Змінити статус на</a>
                        <div class="fok-cm-submenu">
                            <ul>${statusSubMenu}</ul>
                        </div>
                    </li>
                </ul>
            </div>
        `;

        const $menu = $(menuHtml);
        $menu.css({
            top: e.pageY + 5,
            left: e.pageX + 5
        });

        $('body').append($menu);

        // Додаємо клас is-visible з невеликою затримкою, щоб анімація спрацювала
        setTimeout(() => {
            $menu.addClass('is-visible');
        }, 10);

        // Обробник для закриття меню
        $(document).on('click.contextMenu', function(event) {
            if (!$(event.target).closest('.fok-context-menu').length) {
                $('.fok-context-menu').remove();
                $(document).off('click.contextMenu');
            }
        });

        // Обробник для дій меню
        $menu.on('click', '.fok-cm-action', function(e) {
            e.preventDefault();
            const $action = $(this);
            const action = $action.data('action');
            
            if (action === 'change-status') {
                const newStatus = $action.data('status');
                updatePropertyStatus(propData.id, newStatus);
            }

            $('.fok-context-menu').remove();
            $(document).off('click.contextMenu');
        });
    }

    function updatePropertyStatus(propertyId, newStatus) {
        $.ajax({
            url: fok_editor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fok_update_property_status',
                nonce: fok_editor_ajax.nonce,
                property_id: propertyId,
                status: newStatus
            },
            beforeSend: function() {
                loader.show();
            },
            success: function(response) {
                if (response.success) {
                    const $propertyBlock = $(`.fok-property-block[data-id="${propertyId}"]`);
                    const propData = $propertyBlock.data('prop');
                    
                    // Оновлюємо статус в data-prop
                    propData.status = response.data.new_status_slug;
                    $propertyBlock.data('prop', propData);

                    // Оновлюємо клас для зміни кольору
                    $propertyBlock.removeClass('status-vilno status-zabronovano status-prodano')
                                  .addClass(`status-${response.data.new_status_slug}`);

                    // Також оновлюємо дані у списку нерозподілених, якщо вони там є
                    const $unassignedItem = $(`.fok-unassigned-item[data-id="${propertyId}"]`);
                    if ($unassignedItem.length) {
                         const unassignedPropData = $unassignedItem.data('prop');
                         unassignedPropData.status = response.data.new_status_slug;
                         $unassignedItem.data('prop', unassignedPropData);
                         $unassignedItem.removeClass('status-vilno status-zabronovano status-prodano')
                                        .addClass(`status-${response.data.new_status_slug}`);
                    }

                } else {
                    alert('Помилка при оновленні статусу: ' + response.data.message);
                }
            },
            error: function() {
                alert('Помилка сервера. Не вдалося оновити статус.');
            },
            complete: function() {
                loader.hide();
            }
        });
    }


    function updateSaveButtonState(isInitial = false) {
        const hasChanges = Object.keys(changedProperties).length > 0;
        saveButton.prop('disabled', !hasChanges);

        if (hasChanges) {
            saveButton.addClass('has-changes');
            saveStatus.addClass('active').text(fok_grid_i10n.unsaved_changes);
        } else {
            saveButton.removeClass('has-changes');
            saveStatus.removeClass('active').text('');
        }

        if (isInitial) {
            saveButton.removeClass('has-changes');
            saveStatus.removeClass('active').text('');
            saveButton.prop('disabled', true);
        }
    }

    saveButton.on('click', function() {
        if (Object.keys(changedProperties).length === 0) return;
        $.ajax({
            url: fok_editor_ajax.ajax_url, type: 'POST',
            data: { action: 'fok_save_grid_changes', nonce: fok_editor_ajax.nonce, changes: JSON.stringify(Object.values(changedProperties)) },
            // Використовуємо переклад
            beforeSend: () => saveButton.prop('disabled', true).next().text(fok_grid_i10n.saving).removeClass('success error'),
            success: (response) => {
                const statusClass = response.success ? 'success' : 'error';
                saveStatus.text(response.data).addClass(statusClass).removeClass(response.success ? 'error' : 'success');
                if (response.success) {
                    changedProperties = {};
                    setTimeout(() => saveStatus.fadeOut(400, () => saveStatus.text('').show()), 3000);
                }
            },
            error: () => {
                // Використовуємо переклад
                saveStatus.text(fok_grid_i10n.server_error).addClass('error')
            },
            complete: () => updateSaveButtonState()
        });
    });

    // Додаємо обробник для контекстного меню
    gridContainer.on('contextmenu', '.fok-property-block', function(e) {
        e.preventDefault();
        const propData = $(this).data('prop');
        if (propData) {
            showContextMenu(e, propData);
        }
    });

    loadSectionGrid(currentSectionId);
});