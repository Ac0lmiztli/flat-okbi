jQuery(document).ready(function($) {
    'use strict';

    const $page = $('#fok-pricing-page');
    if (!$page.length) return;

    // Кешування елементів DOM
    const $rcFilter = $('#fok_rc_filter');
    const $sectionFilter = $('#fok_section_filter');
    const $propertyTypeFilter = $('#fok_property_type_filter');
    const $roomsFilter = $('#fok_rooms_filter');
    const $sectionFilterWrapper = $('#fok-section-filter-wrapper');
    const $roomsFilterWrapper = $('#fok-rooms-filter-wrapper');
    const $resetFiltersBtn = $('#fok-reset-filters');
    
    const $table = $('#fok-pricing-table');
    const $tableHead = $table.find('thead');
    const $tableBody = $table.find('tbody');
    const $loader = $('#fok-pricing-table-wrapper').find('.fok-loader-overlay');
    
    const $bulkActionSelector = $('#fok-bulk-action-selector');
    const $bulkApplyBtn = $('#fok-do-bulk-action');
    const $selectAllCheckbox = $('#fok-select-all');

    const $saveButton = $('#fok-save-price-changes');
    const $saveStatus = $('#fok-save-status');

    let priceChanges = {};

    // --- Стилі для іконки фіксованої ціни ---
    $('<style>')
    .prop('type', 'text/css')
    .html(`
        .total-price-wrapper {
            position: relative;
            display: inline-block;
        }
        .total-price-wrapper .lock-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #787c82;
            pointer-events: none;
            font-size: 16px;
        }
        .total-price-wrapper input.total-price {
            padding-right: 28px;
        }
    `)
    .appendTo('head');

    // --- Функція попередження про незбережені зміни ---
    const unsavedChangesHandler = function(e) {
        if (Object.keys(priceChanges).length > 0) {
            e.preventDefault();
            e.returnValue = ''; // Стандарт для більшості браузерів
            return ''; // Для застарілих браузерів
        }
    };
    
    // --- Модальне вікно ---
    const $modalBackdrop = $('#fok-price-modal-backdrop');
    const $modalApplyBtn = $('#fok-modal-apply-btn');
    const $modalCancelBtn = $('#fok-modal-cancel-btn');
    const $modalSelectedCount = $('#fok-modal-selected-count');
    const $increaseUnit = $('#fok-increase-unit');
    const $decreaseUnit = $('#fok-decrease-unit');

    function updateSectionFilter(rcId) {
        $sectionFilter.html('<option value="0">' + 'Всі секції' + '</option>');
        if (!rcId || rcId === '0') {
            $sectionFilterWrapper.hide();
            return;
        }
        $sectionFilterWrapper.show();
        $sectionFilter.prop('disabled', true);
        $.ajax({
            url: fok_pricing_ajax.ajax_url,
            type: 'POST',
            data: { action: 'fok_get_sections_for_rc', nonce: fok_pricing_ajax.nonce, rc_id: rcId },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(section) {
                        $sectionFilter.append(`<option value="${section.id}">${section.title}</option>`);
                    });
                }
            },
            complete: function() { $sectionFilter.prop('disabled', false); }
        });
    }

    function loadProperties() {
        const rcId = $rcFilter.val();
        const sectionId = $sectionFilter.val();
        const propertyType = $propertyTypeFilter.val();
        const rooms = $roomsFilter.val();

        if (rcId === '0') {
            $tableBody.html(`<tr><td colspan="7">${'Оберіть ЖК для початку роботи...'}</td></tr>`);
            return;
        }

        $.ajax({
            url: fok_pricing_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fok_get_properties_for_pricing',
                nonce: fok_pricing_ajax.nonce,
                rc_id: rcId,
                section_id: sectionId,
                property_type: propertyType,
                rooms: rooms
            },
            beforeSend: function() {
                $tableBody.empty();
                $loader.show();
                
                if (Object.keys(priceChanges).length > 0) {
                    if (!confirm(fok_pricing_i10n.unsaved_changes_warning)) {
                        $loader.hide();
                        // Тут можна додати логіку для відновлення попереднього стану фільтра, але поки що це ускладнить код.
                        // Просто перериваємо завантаження, щоб користувач міг зберегти дані.
                        return;
                    }
                    // Якщо користувач підтвердив, скидаємо зміни
                    priceChanges = {};
                     $saveButton.prop('disabled', true);
                    $('tr.is-changed').removeClass('is-changed');
                    $(window).off('beforeunload', unsavedChangesHandler);
                }

                $selectAllCheckbox.prop('checked', false);
                $saveButton.prop('disabled', true);
                $saveStatus.text('');
            },
            success: function(response) {
                if (response.success) { renderTable(response.data); } 
                else { $tableBody.html(`<tr><td colspan="7">${fok_pricing_i10n.error_loading}</td></tr>`); }
            },
            error: function() { $tableBody.html(`<tr><td colspan="7">${fok_pricing_i10n.error_loading}</td></tr>`); },
            complete: function() { $loader.hide(); }
        });
    }

    function renderTable(properties) {
        if (properties.length === 0) {
            $tableBody.html(`<tr><td colspan="7">${'Для обраних фільтрів об\'єкти не знайдені.'}</td></tr>`);
            return;
        }
        properties.forEach(prop => {
            const hasPricePerSqm = prop.post_type === 'apartment' || prop.post_type === 'commercial_property';
            const pricePerSqmInput = `<input type="number" class="price-per-sqm" value="${prop.price_per_sqm}" step="50" min="0" ${!hasPricePerSqm ? 'disabled' : ''}>`;
            const totalPriceInput = `
                <span class="total-price-wrapper">
                    <input type="number" class="total-price" value="${prop.total_price}" step="1000" min="0">
                    ${prop.is_manual_price ? '<span class="lock-icon dashicons dashicons-lock" title="Ціна встановлена вручну"></span>' : ''}
                </span>
            `;
            const rowHtml = `<tr data-id="${prop.id}" data-area="${prop.area}">
                <td class="check-column"><input type="checkbox" name="property_id[]" value="${prop.id}"></td>
                <td class="column-title" data-sort-value="${prop.title.toLowerCase()}"><strong><a href="${prop.edit_link}" target="_blank">${prop.title}</a></strong></td>
                <td>${prop.type}</td>
                <td data-sort-value="${prop.floor}">${prop.floor}</td>
                <td data-sort-value="${prop.area}">${prop.area > 0 ? prop.area : '—'}</td>
                <td>${pricePerSqmInput}</td>
                <td>${totalPriceInput}</td>
            </tr>`;
            $tableBody.append(rowHtml);
        });
        
        // ++ КРОК 2: Додатковий прохід для розрахунку початкових загальних цін ++
        $tableBody.find('tr').each(function() {
            const $row = $(this);
            const area = parseFloat($row.data('area')) || 0;
            const $pricePerSqmInput = $row.find('.price-per-sqm');
            const $totalPriceInput = $row.find('.total-price');
            const pricePerSqm = parseFloat($pricePerSqmInput.val()) || 0;
            const totalPrice = parseFloat($totalPriceInput.val()) || 0;

            if (area > 0 && pricePerSqm > 0 && totalPrice === 0) {
                const calculatedTotal = Math.round(pricePerSqm * area);
                $totalPriceInput.val(calculatedTotal);
            }
        });
    }
    
    function handlePriceChange(e, isBulkUpdate = false) {
        const $input = $(e.target);
        const $row = $input.closest('tr');
        const propId = $row.data('id');
        const area = parseFloat($row.data('area'));
        const $pricePerSqmInput = $row.find('.price-per-sqm');
        const $totalPriceInput = $row.find('.total-price');

        if (area > 0 && !isBulkUpdate) {
            if ($input.hasClass('price-per-sqm')) {
                const pricePerSqm = parseFloat($input.val());
                if (!isNaN(pricePerSqm)) { $totalPriceInput.val(Math.round(pricePerSqm * area)); }
                // При зміні ціни за м2, ціна стає динамічною, тому замок зникає
                 $row.find('.lock-icon').remove();
            } else if ($input.hasClass('total-price')) {
                 const totalPrice = parseFloat($input.val());
                 if (!isNaN(totalPrice)) { $pricePerSqmInput.val(Math.round(totalPrice / area)); }
                 // При ручній зміні загальної ціни, вона стає фіксованою (логіка на сервері),
                 // тому ми можемо додати замок тут для миттєвого фідбеку.
                 if (!$row.find('.lock-icon').length) {
                    $totalPriceInput.after('<span class="lock-icon dashicons dashicons-lock" title="Ціна встановлена вручну"></span>');
                 }
            }
        }
        $row.addClass('is-changed');
        
        const wasEmpty = Object.keys(priceChanges).length === 0;

        priceChanges[propId] = {
            price_per_sqm: $pricePerSqmInput.val(),
            total_price: $totalPriceInput.val()
        };
        $saveButton.prop('disabled', false);

        if (wasEmpty && Object.keys(priceChanges).length > 0) {
            $(window).on('beforeunload', unsavedChangesHandler);
        }
    }
    
    function saveChanges() {
        if (Object.keys(priceChanges).length === 0) return;
        $.ajax({
            url: fok_pricing_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fok_save_price_changes',
                nonce: fok_pricing_ajax.nonce,
                changes: JSON.stringify(priceChanges)
            },
            beforeSend: function() {
                $saveButton.prop('disabled', true).text(fok_pricing_i10n.saving);
                $saveStatus.text('').removeClass('success error');
            },
            success: function(response) {
                if (response.success) {
                    $saveStatus.text(response.data.message).addClass('success');
                    $('tr.is-changed').removeClass('is-changed');
                    priceChanges = {};
                    $(window).off('beforeunload', unsavedChangesHandler);
                } else {
                    $saveStatus.text(response.data.message || fok_pricing_i10n.error_saving).addClass('error');
                    $saveButton.prop('disabled', false); // Дозволяємо спробувати ще раз
                }
            },
            error: function() { 
                $saveStatus.text(fok_pricing_i10n.error_saving).addClass('error'); 
                $saveButton.prop('disabled', false); // Дозволяємо спробувати ще раз
            },
            complete: function() { $saveButton.text(fok_pricing_i10n.save_changes); }
        });
    }

    function toggleRoomsFilter() {
        if ($propertyTypeFilter.val() === 'apartment') {
            $roomsFilterWrapper.show();
        } else {
            $roomsFilterWrapper.hide();
            if ($roomsFilter.val() !== 'all') {
                $roomsFilter.val('all');
                loadProperties();
            }
        }
    }
    
    function applyBulkPriceChange() {
        const method = $('input[name="price_change_method"]:checked').val();
        const selectedRows = $tableBody.find('input[type="checkbox"]:checked').closest('tr');

        selectedRows.each(function() {
            const $row = $(this);
            const $pricePerSqmInput = $row.find('.price-per-sqm');
            const $totalPriceInput = $row.find('.total-price');
            
            let currentSqmPrice = parseFloat($pricePerSqmInput.val()) || 0;
            let currentTotalPrice = parseFloat($totalPriceInput.val()) || 0;
            const area = parseFloat($row.data('area')) || 0;

            let newSqmPrice = currentSqmPrice;
            let newTotalPrice = currentTotalPrice;

            switch(method) {
                case 'increase':
                    const incValue = parseFloat($('#fok-increase-value').val()) || 0;
                    if ($('#fok-increase-unit').val() === 'percent') {
                        newSqmPrice = currentSqmPrice * (1 + incValue / 100);
                        newTotalPrice = currentTotalPrice * (1 + incValue / 100);
                    } else { // amount
                        if ($('#fok-increase-target').val() === 'sqm' && area > 0) {
                            newSqmPrice = currentSqmPrice + incValue;
                            newTotalPrice = newSqmPrice * area;
                        } else { // total
                            newTotalPrice = currentTotalPrice + incValue;
                            if (area > 0) newSqmPrice = newTotalPrice / area;
                        }
                    }
                    break;
                case 'decrease':
                    const decValue = parseFloat($('#fok-decrease-value').val()) || 0;
                    if ($('#fok-decrease-unit').val() === 'percent') {
                        newSqmPrice = currentSqmPrice * (1 - decValue / 100);
                        newTotalPrice = currentTotalPrice * (1 - decValue / 100);
                    } else { // amount
                         if ($('#fok-decrease-target').val() === 'sqm' && area > 0) {
                            newSqmPrice = currentSqmPrice - decValue;
                            newTotalPrice = newSqmPrice * area;
                        } else { // total
                            newTotalPrice = currentTotalPrice - decValue;
                            if (area > 0) newSqmPrice = newTotalPrice / area;
                        }
                    }
                    break;
                case 'set_sqm':
                    newSqmPrice = parseFloat($('#fok-set-sqm-value').val()) || 0;
                    if (area > 0) newTotalPrice = newSqmPrice * area;
                    break;
                case 'set_total':
                    newTotalPrice = parseFloat($('#fok-set-total-value').val()) || 0;
                    if (area > 0) newSqmPrice = newTotalPrice / area;
                    break;
            }

            if (!$pricePerSqmInput.is(':disabled')) {
                $pricePerSqmInput.val(Math.round(newSqmPrice));
            }
            $totalPriceInput.val(Math.round(newTotalPrice));
            $totalPriceInput.trigger('change', [true]);
        });
        $modalBackdrop.hide();
    }
    
    function toggleChangeTargetVisibility() {
        const $target = $(this).closest('.fok-modal-input-group').find('.fok-change-target');
        if ($(this).val() === 'amount') {
            $target.show();
        } else {
            $target.hide();
        }
    }

    // --- Прив'язка обробників подій ---
    $rcFilter.on('change', function() { updateSectionFilter($(this).val()); loadProperties(); });
    $sectionFilter.on('change', loadProperties);
    $roomsFilter.on('change', loadProperties);
    $propertyTypeFilter.on('change', function() { toggleRoomsFilter(); loadProperties(); });
    $resetFiltersBtn.on('click', function() {
        $rcFilter.val('0');
        $sectionFilter.val('0');
        $propertyTypeFilter.val('all');
        $roomsFilter.val('all');
        $rcFilter.trigger('change');
    });
    $tableBody.on('change', 'input[type="number"]', handlePriceChange);
    $saveButton.on('click', saveChanges);
    $tableHead.on('click', 'th.sortable', function() {
        const $header = $(this);
        const column = $header.data('sortBy');
        const type = $header.data('sortType') || 'string';
        const currentOrder = $header.hasClass('asc') ? 'asc' : 'desc';
        const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        const rows = $tableBody.find('tr').get();
        const colIndex = $header.index();
        rows.sort(function(a, b) {
            let valA = $(a).children('td').eq(colIndex).data('sortValue');
            let valB = $(b).children('td').eq(colIndex).data('sortValue');
            if (type === 'number') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            }
            if (valA < valB) { return newOrder === 'asc' ? -1 : 1; }
            if (valA > valB) { return newOrder === 'asc' ? 1 : -1; }
            return 0;
        });
        $tableHead.find('th').removeClass('sorted asc desc').find('.sort-indicator').remove();
        $header.addClass('sorted ' + newOrder).find('span').after('<span class="sort-indicator"></span>');
        $.each(rows, function(index, row) { $tableBody.append(row); });
    });

    // --- Логіка масових дій ---
    $selectAllCheckbox.on('click', function() {
        const isChecked = $(this).prop('checked');
        $tableBody.find('input[type="checkbox"]').prop('checked', isChecked).closest('tr').toggleClass('is-selected', isChecked);
    });
    $tableBody.on('click', 'input[type="checkbox"]', function() {
        $(this).closest('tr').toggleClass('is-selected', $(this).prop('checked'));
    });
    $bulkApplyBtn.on('click', function() {
        const action = $bulkActionSelector.val();
        const selectedCount = $tableBody.find('input[type="checkbox"]:checked').length;
        if (action === 'change_price' && selectedCount > 0) {
            $modalSelectedCount.text(selectedCount);
            $modalBackdrop.show();
        } else {
            alert('Будь ласка, оберіть дію та хоча б один об\'єкт.');
        }
    });

    // Кнопки модального вікна
    $modalCancelBtn.on('click', () => $modalBackdrop.hide());
    $modalApplyBtn.on('click', applyBulkPriceChange);
    $increaseUnit.on('change', toggleChangeTargetVisibility);
    $decreaseUnit.on('change', toggleChangeTargetVisibility);

    // Ініціалізація
    toggleRoomsFilter();
    $('.fok-change-target').hide();
});