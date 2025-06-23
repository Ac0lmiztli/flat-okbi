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
                } else { gridContainer.html(`<p class="error">Помилка: ${response.data || 'невідома помилка'}</p>`); }
            },
            error: () => gridContainer.html('<p class="error">Помилка сервера.</p>'),
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
        const listHtml = properties.length
            ? properties.map(prop => `<div class="fok-unassigned-item status-${prop.status}" data-id="${prop.id}" data-prop='${JSON.stringify(prop)}'>${prop.title}</div>`).join('')
            : '<p class="empty-list">Всі об\'єкти розподілені.</p>';
        unassignedList.html(listHtml);
    }
    
    function initInteractivity() {
        const grid = gridContainer.find('.fok-grid');
        
        makeInteractive(grid.find('.fok-property-block'));

        unassignedList.find('.fok-unassigned-item').draggable({
            helper: 'clone', revert: 'invalid', zIndex: 100,
        });

        grid.find('.fok-grid-cell').droppable({
            accept: '.fok-unassigned-item, .fok-property-block',
            hoverClass: 'is-hovered',
            drop: function(event, ui) {
                const $cell = $(this);
                const $item = ui.draggable;
                const propId = $item.data('id');
                let $propBlock = grid.find(`.fok-property-block[data-id="${propId}"]`);

                if ($item.hasClass('fok-unassigned-item')) {
                    const propData = $item.data('prop');
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
                // Використовуємо дані з data-prop, якщо вони є, для коректного відновлення
                const propData = $propBlock.data('prop') || { title: $propBlock.find('.fok-prop-title').text(), status: 'unknown' };
                
                const unassignedItemHtml = `<div class="fok-unassigned-item status-${propData.status}" data-id="${propId}" data-prop='${JSON.stringify(propData)}'>${propData.title}</div>`;
                const $newUnassignedItem = $(unassignedItemHtml).appendTo(unassignedList);

                $newUnassignedItem.draggable({
                    helper: 'clone', revert: 'invalid', zIndex: 100,
                });

                $propBlock.remove();

                changedProperties[propId] = {
                    id: propId,
                    x_start: 0, y_start: 0,
                    x_span: 1, y_span: 1
                };
                updateSaveButtonState();
            }
        });
    }

    function makeInteractive($elements) {
        if (!$elements.length) return;
        
        $elements.draggable({
            // containment: 'parent', // ВИДАЛЕНО ЦЕ ОБМЕЖЕННЯ
            zIndex: 100
        });
        
        $elements.find('.fok-resize-handle').on('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $handle = $(this);
            const $propBlock = $handle.closest('.fok-property-block');

            $propBlock.draggable('disable');

            const startData = {
                mouseX: e.pageX,
                mouseY: e.pageY,
                width: $propBlock.outerWidth(),
                height: $propBlock.outerHeight()
            };

            $(document).on('mousemove.fokResize', function(moveEvent) {
                const newWidth = startData.width + (moveEvent.pageX - startData.mouseX);
                const newHeight = startData.height + (moveEvent.pageY - startData.mouseY);
                $propBlock.outerWidth(newWidth);
                $propBlock.outerHeight(newHeight);
            });

            $(document).on('mouseup.fokResize', function() {
                $(document).off('mousemove.fokResize mouseup.fokResize');
                
                $propBlock.draggable('enable');

                updatePropertySize($propBlock);
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

    function updatePropertySize($propBlock) {
        if (!gridCellSize.width || !gridCellSize.height) return;

        const newHeight = Math.max(1, Math.round($propBlock.outerHeight() / (gridCellSize.height + gridCellSize.gap)));
        const newWidth = Math.max(1, Math.round($propBlock.outerWidth() / (gridCellSize.width + gridCellSize.gap)));
        
        $propBlock.css({
            'width': '', 'height': '',
            'grid-row-end': `span ${newHeight}`,
            'grid-column-end': `span ${newWidth}`
        });
        updateChangedData($propBlock);
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
        // Додаємо data-prop до блоку, щоб зберегти повну інформацію про нього
        return `<div class="fok-property-block status-${prop.status}" data-id="${prop.id}" data-prop='${JSON.stringify(prop)}'
                     style="grid-row: ${row} / span ${prop.y_span}; grid-column: ${col} / span ${prop.x_span};">
                    <div class="fok-prop-title">${prop.title}</div>
                    <a href="${prop.edit_link}" target="_blank" class="fok-prop-edit-link" title="Редагувати">✏️</a>
                    <div class="fok-resize-handle"></div>
                </div>`;
    }

    function updateSaveButtonState(isInitial = false) {
        const hasChanges = Object.keys(changedProperties).length > 0;
        saveButton.prop('disabled', !hasChanges);
        if (isInitial) {
             saveStatus.text('').removeClass('success error');
        } else if (hasChanges) {
             saveStatus.text('Є незбережені зміни.').removeClass('success error');
        }
    }

    saveButton.on('click', function() {
        if (Object.keys(changedProperties).length === 0) return;
        $.ajax({
            url: fok_editor_ajax.ajax_url, type: 'POST',
            data: { action: 'fok_save_grid_changes', nonce: fok_editor_ajax.nonce, changes: JSON.stringify(Object.values(changedProperties)) },
            beforeSend: () => saveButton.prop('disabled', true).next().text('Збереження...').removeClass('success error'),
            success: (response) => {
                const statusClass = response.success ? 'success' : 'error';
                saveStatus.text(response.data).addClass(statusClass).removeClass(response.success ? 'error' : 'success');
                if (response.success) {
                    changedProperties = {};
                    setTimeout(() => saveStatus.fadeOut(400, () => saveStatus.text('').show()), 3000);
                }
            },
            error: () => saveStatus.text('Помилка сервера.').addClass('error'),
            complete: () => updateSaveButtonState()
        });
    });

    loadSectionGrid(currentSectionId);
});