// Повний вміст для assets/js/admin-importer.js

jQuery(document).ready(function($) {
    'use strict';

    const $form = $('#fok-importer-form');
    if (!$form.length) return;

    const $fileInput = $('#fok_properties_csv');
    const $submitButton = $form.find('#submit-import-ajax');
    const $statusWrapper = $('#fok-importer-status');
    const $progressBar = $statusWrapper.find('.fok-progress-bar-inner');
    const $progressText = $statusWrapper.find('.fok-progress-text');
    const $logConsole = $statusWrapper.find('.fok-log-console');
    const $generalMessage = $('#fok-importer-message');
    
    let importFilepath = '';
    const batchSize = 50;
    
    function logMessage(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        $logConsole.append(`<div class="log-entry log-${type}">[${timestamp}] ${message}</div>`);
        $logConsole.scrollTop($logConsole[0].scrollHeight);
    }
    
    function resetUI() {
        $generalMessage.hide().empty().removeClass('notice-success notice-error is-dismissible');
        $statusWrapper.hide();
        $logConsole.empty();
        $progressBar.css('width', '0%');
        $progressText.text('0%');
        $submitButton.prop('disabled', false).text('Почати імпорт');
    }

    /**
     * Обробляє помилки, розділяючи загальне повідомлення та детальний лог.
     */
    function handleError(message, errors = []) {
        // Показуємо загальне повідомлення про помилку зверху
        $generalMessage.removeClass('notice-success').addClass('notice notice-error is-dismissible').show();
        $generalMessage.html(`<p><strong>Помилка імпорту:</strong> ${message}</p>`);

        // Робимо видимим блок з логами
        $statusWrapper.slideDown();
        $progressBar.parent().hide(); // Ховаємо прогрес-бар при помилці валідації

        // Виводимо детальні помилки у лог-консоль
        if (errors.length > 0) {
            logMessage('Будь ласка, виправте наступні помилки у вашому файлі:', 'error');
            errors.forEach(function(error) {
                logMessage(error, 'error');
            });
        } else {
            // Якщо детальних помилок немає, просто логуємо основне повідомлення
            logMessage(message, 'error');
        }

        $submitButton.prop('disabled', false).text('Спробувати ще раз');
    }

    $form.on('submit', function(e) {
        e.preventDefault();

        if ($fileInput[0].files.length === 0) {
            alert('Будь ласка, виберіть CSV-файл для імпорту.');
            return;
        }

        resetUI();
        $submitButton.prop('disabled', true).text('Завантаження...');
        $statusWrapper.slideDown();
        $progressBar.parent().show();
        logMessage('Завантаження файлу на сервер...', 'info');

        const formData = new FormData(this);
        formData.append('action', 'fok_prepare_import');
        formData.append('nonce', fok_importer_ajax.nonce);

        $.ajax({
            url: fok_importer_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    importFilepath = response.data.filepath;
                    logMessage('Файл завантажено. Перевірка даних...', 'info');
                    validateFile(response.data.total_rows);
                } else {
                    handleError(response.data.message || 'Помилка підготовки файлу.');
                }
            },
            error: function() {
                handleError('Сталася помилка сервера під час завантаження файлу.');
            }
        });
    });

    function validateFile(totalRows) {
        if (totalRows === 0) {
            handleError('Файл порожній або містить тільки заголовок.');
            return;
        }

        $.ajax({
            url: fok_importer_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fok_validate_import_file',
                nonce: fok_importer_ajax.nonce,
                filepath: importFilepath
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    logMessage('Перевірка успішна. Починаємо імпорт...', 'success');
                    processBatches(totalRows);
                } else {
                    handleError(response.data.message, response.data.errors || []);
                }
            },
            error: function() {
                handleError('Сталася помилка сервера під час перевірки файлу.');
            }
        });
    }

    function processBatches(totalRows) {
        let processedRows = 0;
        let stats = { imported: 0, updated: 0, skipped: 0, errors: 0 };
        const totalBatches = Math.ceil(totalRows / batchSize);
        let currentBatch = 1;

        $progressBar.parent().show(); // Переконуємось, що прогрес-бар видимий

        function processNextBatch() {
            if (currentBatch > totalBatches) {
                logMessage('Імпорт успішно завершено!', 'final-success');
                $generalMessage
                    .addClass('notice notice-success is-dismissible')
                    .html(`<p>Імпорт завершено! Додано: ${stats.imported}, Оновлено: ${stats.updated}.</p>`)
                    .show();
                $submitButton.prop('disabled', false).text('Почати новий імпорт');
                
                if (importFilepath) {
                    $.post(fok_importer_ajax.ajax_url, {
                        action: 'fok_cleanup_import_file',
                        nonce: fok_importer_ajax.nonce,
                        filepath: importFilepath
                    });
                }
                return;
            }

            logMessage(`Обробка пакета ${currentBatch} з ${totalBatches}...`, 'info');
            
            $.ajax({
                url: fok_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fok_process_import_batch',
                    nonce: fok_importer_ajax.nonce,
                    filepath: importFilepath,
                    batch_number: currentBatch,
                    batch_size: batchSize
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        processedRows += response.data.processed;
                        stats.imported += response.data.imported;
                        stats.updated += response.data.updated;
                        stats.skipped += response.data.skipped;
                        stats.errors += response.data.errors;

                        const percentage = totalRows > 0 ? Math.round((processedRows / totalRows) * 100) : 0;
                        $progressBar.css('width', percentage + '%');
                        $progressText.text(`${percentage}%`);
                        $submitButton.text(`Імпорт... (${processedRows}/${totalRows})`);
                        
                        currentBatch++;
                        processNextBatch();
                    } else {
                        handleError(response.data.message || `Помилка обробки пакета №${currentBatch}.`);
                    }
                },
                error: function() {
                    handleError(`Критична помилка сервера під час обробки пакета №${currentBatch}.`);
                }
            });
        }
        
        processNextBatch();
    }
});