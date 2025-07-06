jQuery(document).ready(function($) {
    'use strict';

    // --- Кешування елементів DOM ---
    const $form = $('#fok-importer-form');
    if (!$form.length) return;

    const $fileInput = $('#fok_properties_csv');
    const $submitButton = $form.find('#submit-import-ajax');
    const $statusWrapper = $('#fok-importer-status');
    const $progressBar = $statusWrapper.find('.fok-progress-bar-inner');
    const $progressText = $statusWrapper.find('.fok-progress-text');
    const $logConsole = $statusWrapper.find('.fok-log-console');
    const $generalMessage = $('#fok-importer-message');
    
    let totalRows = 0;
    let processedRows = 0;
    let importFilepath = '';
    let batchSize = 50; // Можна налаштувати. Скільки рядків обробляти за раз.
    let currentBatch = 1;
    let totalBatches = 0;
    
    // Глобальна статистика
    let stats = { imported: 0, updated: 0, skipped: 0, errors: 0 };

    /**
     * Запускає процес імпорту після вибору файлу.
     */
    $form.on('submit', function(e) {
        e.preventDefault();

        if ($fileInput[0].files.length === 0) {
            alert('Будь ласка, виберіть CSV-файл для імпорту.');
            return;
        }

        const formData = new FormData(this);
        formData.append('action', 'fok_prepare_import');
        formData.append('nonce', fok_importer_ajax.nonce); // Використовуємо nonce з локалізованого об'єкта

        resetUI();
        $submitButton.prop('disabled', true).text('Завантаження...');
        $statusWrapper.slideDown();

        // Етап 1: Відправляємо файл на підготовку
        $.ajax({
            url: fok_importer_ajax.ajax_url, // Використовуємо ajax_url з локалізованого об'єкта
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    totalRows = response.data.total_rows;
                    importFilepath = response.data.filepath; // Зберігаємо шлях до тимчасового файлу
                    totalBatches = Math.ceil(totalRows / batchSize);
                    
                    logMessage(`Файл успішно завантажено. Знайдено ${totalRows} рядків для імпорту.`, 'info');
                    $submitButton.text(`Імпорт... (0/${totalRows})`);
                    
                    // Запускаємо обробку першого пакета
                    processNextBatch();
                } else {
                    handleError(response.data.message);
                }
            },
            error: function() {
                handleError('Сталася помилка сервера під час завантаження файлу.');
            }
        });
    });

    /**
     * Рекурсивна функція для обробки пакетів даних.
     */
    function processNextBatch() {
        if (currentBatch > totalBatches) {
            // Всі пакети оброблено - завершуємо
            finishImport();
            return;
        }

        logMessage(`Обробка пакета ${currentBatch} з ${totalBatches}...`, 'info');

        $.ajax({
            url: fok_importer_ajax.ajax_url, // Використовуємо ajax_url
            type: 'POST',
            data: {
                action: 'fok_process_import_batch',
                nonce: fok_importer_ajax.nonce, // Використовуємо nonce
                filepath: importFilepath,
                batch_number: currentBatch,
                batch_size: batchSize
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    processedRows += data.processed;
                    
                    // Оновлюємо глобальну статистику
                    stats.imported += data.imported;
                    stats.updated += data.updated;
                    stats.skipped += data.skipped;
                    stats.errors += data.errors;

                    logMessage(data.log, 'success');
                    updateProgress();
                    
                    currentBatch++;
                    processNextBatch(); // Рекурсивний виклик для наступного пакета
                } else {
                    handleError(response.data.message);
                }
            },
            error: function() {
                handleError(`Сталася помилка сервера під час обробки пакета №${currentBatch}.`);
            }
        });
    }

    /**
     * Оновлює прогрес-бар та текст.
     */
    function updateProgress() {
        const percentage = totalRows > 0 ? Math.round((processedRows / totalRows) * 100) : 0;
        $progressBar.css('width', percentage + '%');
        $progressText.text(`${percentage}%`);
        $submitButton.text(`Імпорт... (${processedRows}/${totalRows})`);
    }

    /**
     * Завершує процес імпорту і показує фінальний результат.
     */
    function finishImport() {
        logMessage('Імпорт успішно завершено!', 'final-success');
        $submitButton.prop('disabled', false).text('Почати новий імпорт');
        $generalMessage
            .removeClass('notice-error')
            .addClass('notice-success')
            .html(`<p>Імпорт завершено! Додано: ${stats.imported}, Оновлено: ${stats.updated}, Пропущено: ${stats.skipped}, Помилок: ${stats.errors}.</p>`)
            .show();
        
        if (importFilepath) {
            $.post(fok_importer_ajax.ajax_url, { // Використовуємо ajax_url
                action: 'fok_cleanup_import_file',
                nonce: fok_importer_ajax.nonce, // Використовуємо nonce
                filepath: importFilepath
            }).done(function() {
                logMessage('Тимчасовий файл очищено.', 'info');
                importFilepath = ''; // Скидаємо шлях до файлу
            });
        }
    }

    /**
     * Обробляє помилки та зупиняє процес.
     */
    function handleError(message) {
        logMessage(message, 'error');
        $submitButton.prop('disabled', false).text('Спробувати ще раз');
        $generalMessage
            .removeClass('notice-success')
            .addClass('notice-error')
            .html(`<p><strong>Помилка імпорту:</strong> ${message}</p>`)
            .show();
    }
    
    /**
     * Додає повідомлення у лог-консоль.
     */
    function logMessage(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        $logConsole.append(`<div class="log-entry log-${type}">[${timestamp}] ${message}</div>`);
        $logConsole.scrollTop($logConsole[0].scrollHeight); // Авто-прокрутка вниз
    }

    /**
     * Скидає інтерфейс до початкового стану.
     */
    function resetUI() {
        $statusWrapper.hide();
        $logConsole.empty();
        $progressBar.css('width', '0%');
        $progressText.text('0%');
        $generalMessage.hide().empty().removeClass('notice-success notice-error');
        processedRows = 0;
        currentBatch = 1;
        stats = { imported: 0, updated: 0, skipped: 0, errors: 0 };
    }
});