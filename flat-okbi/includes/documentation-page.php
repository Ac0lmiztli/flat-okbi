<?php
// includes/documentation-page.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Рендерить сторінку "Документація".
 */
function fok_render_documentation_page($fok_admin) {
    ?>
    <div class="wrap" id="fok-docs-page">
        <h1><?php _e('Документація по роботі з плагіном', 'okbi-apartments'); ?></h1>
        <div class="fok-docs-container">
            <div class="fok-docs-section card">
                <h2><?php _e('Імпорт та Експорт Даних', 'okbi-apartments'); ?></h2>
                
                <h3 class="fok-docs-subtitle"><?php _e('Імпорт з CSV-файлу', 'okbi-apartments'); ?></h3>
                <p><?php _e('Функція імпорту дозволяє швидко наповнювати сайт даними про об\'єкти нерухомості, автоматично створюючи житлові комплекси, секції та самі об\'єкти.', 'okbi-apartments'); ?></p>
                <p><?php _e('Для імпорту використовуйте CSV-файл у кодуванні <strong>UTF-8</strong>. Ви можете завантажити та використовувати як шаблон наш', 'okbi-apartments'); ?>
                    <a href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/properties-example.csv'); ?>" download>
                        <?php _e('файл-приклад', 'okbi-apartments'); ?>
                    </a>.
                </p>
                <h4><?php _e('Опис колонок CSV-файлу', 'okbi-apartments'); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 20%;"><strong><?php _e('Назва колонки', 'okbi-apartments'); ?></strong></th>
                            <th><strong><?php _e('Опис', 'okbi-apartments'); ?></strong></th>
                            <th style="width: 10%;"><strong><?php _e('Обов\'язкове?', 'okbi-apartments'); ?></strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>unique_id</code></td>
                            <td><?php _e('<strong>Унікальний ідентифікатор об\'єкта.</strong> Це може бути будь-який унікальний рядок (напр., артикул з CRM). За цим полем система визначає, створювати новий об\'єкт чи оновлювати існуючий. <strong>Ніколи не змінюйте цей ID після першого імпорту!</strong>', 'okbi-apartments'); ?></td>
                            <td><span class="fok-required"><?php _e('Так', 'okbi-apartments'); ?></span></td>
                        </tr>
                        <tr>
                            <td><code>post_type</code></td>
                            <td><?php _e('Тип нерухомості. Можливі значення: <code>Квартира</code>, <code>Комерція</code>, <code>Паркомісце</code>, <code>Комора</code>.', 'okbi-apartments'); ?></td>
                            <td><span class="fok-required"><?php _e('Так', 'okbi-apartments'); ?></span></td>
                        </tr>
                        <tr>
                            <td><code>rc_name</code></td>
                            <td><?php _e('Назва житлового комплексу. Якщо ЖК з такою назвою не існує, він буде створений автоматично.', 'okbi-apartments'); ?></td>
                            <td><span class="fok-required"><?php _e('Так', 'okbi-apartments'); ?></span></td>
                        </tr>
                        <tr>
                            <td><code>section_name</code></td>
                            <td><?php _e('Назва секції. Якщо секції з такою назвою немає в зазначеному ЖК, вона буде створена та прив\'язана до нього.', 'okbi-apartments'); ?></td>
                            <td><span class="fok-required"><?php _e('Так', 'okbi-apartments'); ?></span></td>
                        </tr>
                        <tr>
                            <td><code>property_number</code></td>
                            <td><?php _e('Номер об\'єкта (квартири, офісу тощо).', 'okbi-apartments'); ?></td>
                            <td><span class="fok-required"><?php _e('Так', 'okbi-apartments'); ?></span></td>
                        </tr>
                        <tr>
                            <td><code>floor</code></td>
                            <td><?php _e('Поверх, на якому розташований об\'єкт.', 'okbi-apartments'); ?></td>
                            <td><span class="fok-required"><?php _e('Так', 'okbi-apartments'); ?></span></td>
                        </tr>
                        <tr>
                            <td><code>rooms</code></td>
                            <td><?php _e('Кількість кімнат. Заповнюється тільки для квартир.', 'okbi-apartments'); ?></td>
                            <td><?php _e('Ні', 'okbi-apartments'); ?></td>
                        </tr>
                        <tr>
                            <td><code>area</code></td>
                            <td><?php _e('Загальна площа об\'єкта в м². Використовуйте крапку як роздільник (напр., <code>45.5</code>).', 'okbi-apartments'); ?></td>
                            <td><span class="fok-required"><?php _e('Так', 'okbi-apartments'); ?></span></td>
                        </tr>
                        <tr>
                            <td><code>price_per_sqm</code></td>
                            <td><?php _e('Ціна за квадратний метр.', 'okbi-apartments'); ?></td>
                            <td><?php _e('Ні', 'okbi-apartments'); ?></td>
                        </tr>
                        <tr>
                            <td><code>total_price</code></td>
                            <td><?php _e('Фіксована загальна ціна за об\'єкт. Має пріоритет над ціною за м². Важливо для паркомісць та комор.', 'okbi-apartments'); ?></td>
                            <td><?php _e('Ні', 'okbi-apartments'); ?></td>
                        </tr>
                        <tr>
                            <td><code>currency</code></td>
                            <td><?php _e('Валюта. Можливі значення: <code>UAH</code>, <code>USD</code>, <code>EUR</code>.', 'okbi-apartments'); ?></td>
                            <td><?php _e('Ні', 'okbi-apartments'); ?></td>
                        </tr>
                        <tr>
                            <td><code>discount_percent</code></td>
                            <td><?php _e('Знижка у відсотках (тільки число, без знака %).', 'okbi-apartments'); ?></td>
                            <td><?php _e('Ні', 'okbi-apartments'); ?></td>
                        </tr>
                        <tr>
                            <td><code>status</code></td>
                            <td><?php _e('Статус об\'єкта. Можливі значення: <code>Вільно</code>, <code>Продано</code>, <code>Заброньовано</code>.', 'okbi-apartments'); ?></td>
                            <td><?php _e('Ні', 'okbi-apartments'); ?></td>
                        </tr>
                        <tr>
                            <td><code>layout_images</code></td>
                            <td><?php _e('Зображення планувань. Вкажіть повні URL-адреси до зображень або назви файлів (напр. <code>plan.jpg</code>), які вже завантажені у Медіа-бібліотеку WordPress. Кілька зображень вказуйте через кому.', 'okbi-apartments'); ?></td>
                            <td><?php _e('Ні', 'okbi-apartments'); ?></td>
                        </tr>
                        <tr class="fok-optional-row">
                            <td><code>grid_column_start</code><br><code>grid_column_span</code><br><code>grid_row_span</code></td>
                            <td><?php _e('Координати для розташування об\'єкта в "шахматці". <strong>Ці поля є необов\'язковими.</strong> Якщо їх не заповнювати, об\'єкт потрапить до списку "Нерозподілені об\'єкти", і ви зможете розставити його вручну в зручному візуальному редакторі.', 'okbi-apartments'); ?></td>
                            <td><?php _e('Ні', 'okbi-apartments'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="fok-docs-section card">
                <h2><?php _e('Робота з візуальними редакторами', 'okbi-apartments'); ?></h2>
                
                <h3 class="fok-docs-subtitle"><?php _e('Редактор шахматки', 'okbi-apartments'); ?></h3>
                <p><?php _e('Цей інструмент знаходиться на сторінці редагування кожної окремої <strong>Секції</strong>. Він дозволяє візуально розставляти об\'єкти по поверхах так, як вони будуть відображатися для клієнтів.', 'okbi-apartments'); ?></p>
                <ul>
                    <li><strong><?php _e('Додавання об\'єкта на сітку:', 'okbi-apartments'); ?></strong> <?php _e('Просто перетягніть об\'єкт зі списку "Нерозподілені об\'єкти" на потрібну клітинку в сітці.', 'okbi-apartments'); ?></li>
                    <li><strong><?php _e('Переміщення об\'єкта:', 'okbi-apartments'); ?></strong> <?php _e('Наведіть на об\'єкт, затисніть ліву кнопку миші та перетягніть його на нове місце.', 'okbi-apartments'); ?></li>
                    <li><strong><?php _e('Зміна розміру (для дворівневих квартир):', 'okbi-apartments'); ?></strong> <?php _e('Наведіть на об\'єкт, і в його правому нижньому кутку з\'явиться синій маркер. Потягніть за нього, щоб змінити висоту або ширину об\'єкта.', 'okbi-apartments'); ?></li>
                    <li><strong><?php _e('Прибрати об\'єкт з сітки:', 'okbi-apartments'); ?></strong> <?php _e('Щоб повернути об\'єкт до списку нерозподілених, просто перетягніть його з сітки назад у блок "Нерозподілені об\'єкти".', 'okbi-apartments'); ?></li>
                </ul>
                <p class="fok-docs-notice"><strong><?php _e('Важливо:', 'okbi-apartments'); ?></strong> <?php _e('Після внесення будь-яких змін у редакторі шахматки, не забудьте натиснути синю кнопку "Зберегти зміни" внизу редактора. Інакше ваші зміни не будуть збережені.', 'okbi-apartments'); ?></p>

                <h3 class="fok-docs-subtitle"><?php _e('Редактор планів поверхів', 'okbi-apartments'); ?></h3>
                <p><?php _e('Цей інструмент також знаходиться на сторінці редагування <strong>Секції</strong>. Він дозволяє "намалювати" активні області поверх зображення з планом поверху. Клієнти зможуть натискати на ці області, щоб переглянути деталі квартири.', 'okbi-apartments'); ?></p>
                <ul>
                    <li><strong><?php _e('Крок 1: Додавання поверху.', 'okbi-apartments'); ?></strong> <?php _e('Натисніть кнопку "Додати поверх". Вкажіть номер поверху та завантажте зображення з планом.', 'okbi-apartments'); ?></li>
                    <li><strong><?php _e('Крок 2: Відкриття редактора.', 'okbi-apartments'); ?></strong> <?php _e('Кнопка "Редагувати план" стане активною тільки після завантаження зображення. Натисніть її, щоб відкрити спливаюче вікно редактора.', 'okbi-apartments'); ?></li>
                    <li><strong><?php _e('Крок 3: Малювання полігону.', 'okbi-apartments'); ?></strong> <?php _e('У вікні редактора оберіть зі списку потрібний об\'єкт (напр., "Квартира №25"). Після цього почніть малювати контур цієї квартири на зображенні, ставлячи точки кліком лівої кнопки миші. Щоб завершити малювання, натисніть праву кнопку миші.', 'okbi-apartments'); ?></li>
                    <li><strong><?php _e('Видалення полігону:', 'okbi-apartments'); ?></strong> <?php _e('Щоб видалити контур для певного об\'єкта, знайдіть його у списку справа і натисніть на іконку кошика навпроти його назви.', 'okbi-apartments'); ?></li>
                </ul>
                <p class="fok-docs-notice"><strong><?php _e('Важливо:', 'okbi-apartments'); ?></strong> <?php _e('Спочатку натисніть "Зберегти і закрити" у спливаючому вікні, а потім обов\'язково натисніть синю кнопку "Оновити" на самій сторінці Секції, щоб зберегти всі зміни.', 'okbi-apartments'); ?></p>
            </div>
        </div>

    </div>
    <?php
} 