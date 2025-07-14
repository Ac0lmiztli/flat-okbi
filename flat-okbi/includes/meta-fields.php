<?php
// includes/meta-fields.php

if ( ! defined( 'ABSPATH' ) ) exit;

// Реєстрація мета-полів для типів записів
add_filter( 'rwmb_meta_boxes', 'fok_register_meta_boxes' );

function fok_register_meta_boxes( $meta_boxes ) {

    // Мета-поля для ЖК
    $meta_boxes[] = [
        'title'      => __( 'Додаткова інформація про ЖК', 'okbi-apartments' ),
        'id'         => 'rc_details',
        'post_types' => ['residential_complex'],
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => [
            [
                'id'   => 'fok_rc_sections_list',
                'name' => __( 'Секції цього ЖК', 'okbi-apartments' ),
                'type' => 'textarea',
                'rows' => 10,
                'desc' => __('Введіть назви секцій, кожну з нового рядка. Вони будуть створені автоматично. Щоб видалити секцію, просто видаліть рядок з її назвою і збережіть ЖК.', 'okbi-apartments'),
                'placeholder' => "Секція 1\nСекція 2\nСекція 3",
            ],
        ],
    ];

    // Мета-поля для Секцій
    $meta_boxes[] = [
        'title'      => __( 'Параметри секції', 'okbi-apartments' ),
        'id'         => 'section_details',
        'post_types' => ['section'],
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => [
            [
                'id'   => 'fok_section_total_floors',
                'name' => __( 'Кількість поверхів (висота сітки)', 'okbi-apartments' ),
                'type' => 'number',
                'min'  => 1,
                'desc' => __( 'Вкажіть, скільки всього поверхів у цій секції.', 'okbi-apartments' ),
            ],
            [
                'id'   => 'fok_section_grid_columns',
                'name' => __( 'Кількість колонок (ширина сітки)', 'okbi-apartments' ),
                'type' => 'number',
                'min'  => 1,
                'desc' => __( 'Вкажіть максимальну кількість об\'єктів по горизонталі на будь-якому поверсі.', 'okbi-apartments' ),
            ],
        ],
    ];
    // Мета-поля для Заявок
    $meta_boxes[] = [
        'title'      => __( 'Деталі заявки', 'okbi-apartments' ),
        'id'         => 'lead_details',
        'post_types' => ['fok_lead'],
        'context'    => 'advanced',
        'priority'   => 'high',
        'fields'     => [
            [
                'name' => 'Статус заявки',
                'id'   => '_lead_status',
                'type' => 'select',
                'options' => [ 'new' => 'Нова', 'in_progress' => 'В обробці', 'success' => 'Успішно', 'failed' => 'Відмова' ],
                'std'  => 'new',
            ],
            // ++ НОВЕ ПОЛЕ: Дата та час створення ++
            [
                'name' => 'Дата та час заявки',
                'id'   => '_lead_creation_date',
                'type' => 'custom_html',
                // Використовуємо 'callback' для динамічного відображення дати
                'callback' => function() {
                    // get_the_date() автоматично візьме дату поточного поста (заявки)
                    return '<strong>' . get_the_date('j F Y, H:i') . '</strong>';
                }
            ],
            [
                'name' => 'Ім\'я клієнта',
                'id'   => '_lead_name',
                'type' => 'text',
                'attributes' => [ 'readonly' => 'readonly' ],
            ],
            [
                'name' => 'Телефон',
                'id'   => '_lead_phone',
                'type' => 'tel',
                'attributes' => [ 'readonly' => 'readonly' ],
            ],
            // ++ НОВІ ПОЛЯ: ЖК та Секція ++
            [
                'name' => 'Житловий комплекс',
                'id'   => '_lead_rc_id',
                'type' => 'custom_html',
                'save_field' => false, // Вказуємо MetaBox не чіпати це поле при збереженні
                'callback' => function() {
                    $post_id = get_the_ID();
                    if (!$post_id) return '<em>(помилка)</em>';
                    $rc_id = get_post_meta($post_id, '_lead_rc_id', true);
                    if ($rc_id && get_post($rc_id)) {
                        return sprintf('<strong><a href="%s" target="_blank">%s</a></strong>', get_edit_post_link($rc_id), get_the_title($rc_id));
                    }
                    return '<em>(не вказано)</em>';
                }
            ],
            [
                'name' => 'Секція',
                'id'   => '_lead_section_id',
                'type' => 'custom_html',
                'save_field' => false, // Вказуємо MetaBox не чіпати це поле при збереженні
                 'callback' => function() {
                    $post_id = get_the_ID();
                    if (!$post_id) return '<em>(помилка)</em>';
                    $section_id = get_post_meta($post_id, '_lead_section_id', true);
                    if ($section_id && get_post($section_id)) {
                        return sprintf('<strong><a href="%s" target="_blank">%s</a></strong>', get_edit_post_link($section_id), get_the_title($section_id));
                    }
                    return '<em>(не вказано)</em>';
                }
            ],
            [
                'name' => 'Об\'єкт, на який подано заявку',
                'id'   => '_lead_property_id',
                'type' => 'custom_html',
                'save_field' => false, // Вказуємо MetaBox не чіпати це поле при збереженні
                 'callback' => function() {
                    $post_id = get_the_ID();
                    if (!$post_id) return '<em>(помилка)</em>';
                    $property_id = get_post_meta($post_id, '_lead_property_id', true);
                    if ($property_id && get_post($property_id)) {
                        return sprintf('<strong><a href="%s" target="_blank">%s</a></strong>', get_edit_post_link($property_id), get_the_title($property_id));
                    }
                    return '<em>(не вказано)</em>';
                }
            ],
            [
                'name' => __('Коментар менеджера', 'okbi-apartments'),
                'id'   => '_lead_manager_comment',
                'type' => 'textarea',
                'rows' => 5,
                'placeholder' => __('Запишіть результат розмови або наступні кроки...', 'okbi-apartments'),
            ],
        ],
    ];
    $meta_boxes[] = [
    'title'      => __( 'Планування поверхів', 'okbi-apartments' ),
    'id'         => 'section_floor_plans',
    'post_types' => ['section'],
    'context'    => 'normal',
    'priority'   => 'high',
    'fields'     => [
        // Приховане поле, де будуть зберігатися всі дані у форматі JSON
        [
            'id'   => 'fok_section_floors_data',
            'type' => 'hidden',
        ],
        // Візуальна частина, яку побачить користувач
        [
            'type' => 'custom_html',
            'callback' => 'fok_render_floor_plan_editor',
        ],
    ],
];

// Допоміжна функція для відображення HTML нашого редактора
if ( ! function_exists( 'fok_render_floor_plan_editor' ) ) {
    function fok_render_floor_plan_editor() {
        global $post;
        $floors_data = get_post_meta( $post->ID, 'fok_section_floors_data', true );
        $floors = json_decode( $floors_data, true );
        if ( ! is_array( $floors ) ) {
            $floors = [];
        }

        ob_start();

        wp_nonce_field( 'fok_floor_plans_nonce', 'fok_floor_plans_nonce' );
        ?>
        <div class="fok-floor-editor">
            <script type="text/template" id="fok-floor-template">
                <?php fok_render_floor_row_template(); // Використовуємо наш новий шаблон для нового рядка ?>
            </script>

            <div id="fok-floors-container">
                <?php
                if ( ! empty( $floors ) ) {
                    foreach ( $floors as $index => $floor ) { // Додаємо індекс
                        fok_render_floor_row_template( $floor, $index );
                    }
                }
                ?>
            </div>

            <button type="button" id="fok-add-floor-btn" class="button">
                <span class="dashicons dashicons-plus-alt" style="vertical-align: text-top;"></span>
                <?php _e( 'Додати поверх', 'okbi-apartments' ); ?>
            </button>
            
            <?php // ++ ДОДАНО: Контейнер для модального вікна ++ ?>
            <div id="fok-floor-plan-modal" title="<?php esc_attr_e('Редактор плану поверху', 'okbi-apartments'); ?>" style="display:none;">
                <div class="fok-polygon-editor-wrapper">
                    <div class="fok-editor-main-area">
                        <div class="fok-editor-image-container">
                            <img src="" class="fok-editor-bg-image">
                            <svg class="fok-editor-svg" viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
                        </div>
                        <div class="fok-editor-controls">
                            <h4><?php _e('Об\'єкти на поверсі', 'okbi-apartments'); ?></h4>
                             <div class="fok-floor-objects-list"></div>
                             
                             <?php // ++ ДОДАНО: Панель інструментів ++ ?>
                            <div class="fok-editor-toolbar">
                                <label><?php _e('Інструмент:', 'okbi-apartments'); ?></label>
                                <div class="fok-tools-buttons">
                                    <button type="button" class="button fok-tool-btn is-active" data-tool="polygon" title="<?php esc_attr_e('Полігон (малювання по точках)', 'okbi-apartments'); ?>">
                                        <svg fill="currentColor" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path d="M30,6a3.9916,3.9916,0,0,0-7.9773-.2241L9.5864,8.2627A3.99,3.99,0,1,0,5,13.8579v8.2842A3.9915,3.9915,0,1,0,9.8579,27h8.2842a3.9912,3.9912,0,1,0,5.595-4.5864l2.487-12.4361A3.9945,3.9945,0,0,0,30,6ZM26,4a2,2,0,1,1-2,2A2.0023,2.0023,0,0,1,26,4ZM4,10a2,2,0,1,1,2,2A2.0023,2.0023,0,0,1,4,10ZM6,28a2,2,0,1,1,2-2A2.0023,2.0023,0,0,1,6,28Zm12.1421-3H9.8579A3.9942,3.9942,0,0,0,7,22.1421V13.8579a3.9871,3.9871,0,0,0,2.9773-3.6338L22.4136,7.7373a4.0053,4.0053,0,0,0,1.8493,1.8491l-2.487,12.4361A3.9874,3.9874,0,0,0,18.1421,25ZM22,28a2,2,0,1,1,2-2A2.0023,2.0023,0,0,1,22,28Z"/></svg>
                                    </button>
                                    <button type="button" class="button fok-tool-btn" data-tool="rectangle" title="<?php esc_attr_e('Прямокутник (малювання перетягуванням)', 'okbi-apartments'); ?>">
                                        <svg fill="currentColor" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path d="M27,22.142V9.858A3.9916,3.9916,0,1,0,22.142,5H9.858A3.9916,3.9916,0,1,0,5,9.858V22.142A3.9916,3.9916,0,1,0,9.858,27H22.142A3.9916,3.9916,0,1,0,27,22.142ZM26,4a2,2,0,1,1-2,2A2.0023,2.0023,0,0,1,26,4ZM4,6A2,2,0,1,1,6,8,2.002,2.002,0,0,1,4,6ZM6,28a2,2,0,1,1,2-2A2.0023,2.0023,0,0,1,6,28Zm16.142-3H9.858A3.9937,3.9937,0,0,0,7,22.142V9.858A3.9947,3.9947,0,0,0,9.858,7H22.142A3.9937,3.9937,0,0,0,25,9.858V22.142A3.9931,3.9931,0,0,0,22.142,25ZM26,28a2,2,0,1,1,2-2A2.0027,2.0027,0,0,1,26,28Z"/></svg>
                                    </button>
                                </div>
                            </div>
                            <?php // -- КІНЕЦЬ ДОДАНОГО КОДУ -- ?>

                             <div class="fok-tool-descriptions">
                                <p class="description fok-tool-desc" data-tool-desc="polygon">
                                    <?php _e('Оберіть об\'єкт зі списку, щоб почати малювати полігон. Клік лівою кнопкою миші додає точку. Наведіть курсор миші на ребро фігури і натисніть ліву кнопку миші щоб додати ще одну точку. Правим кліком миші можна видалити точку.', 'okbi-apartments'); ?>
                                </p>
                                <p class="description fok-tool-desc" data-tool-desc="rectangle" style="display: none;">
                                    <?php _e('Оберіть об\'єкт, потім затисніть ліву кнопку миші на плані та потягніть, щоб намалювати прямокутник. Відпустіть кнопку, щоб завершити.', 'okbi-apartments'); ?>
                                </p>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php // -- КІНЕЦЬ ДОДАНОГО КОДУ -- ?>
            
        </div>
        <?php
        return ob_get_clean();
    }
}

// ** ЗМІНЕНО: Шаблон рядка тепер значно простіший **
if ( ! function_exists( 'fok_render_floor_row_template' ) ) {
    function fok_render_floor_row_template( $data = [], $index = -1 ) {
        $number = $data['number'] ?? '';
        $image_id = $data['image'] ?? '';
        $polygons_json = $data['polygons_data'] ?? '[]';
        $image_thumb_src = $image_id ? wp_get_attachment_thumb_url( $image_id ) : '';
        ?>
        <div class="fok-floor-row" data-index="<?php echo $index; ?>">
            
            <div class="fok-floor-col-info">
                <span class="dashicons dashicons-menu handle"></span>
                <div class="fok-floor-field-group">
                    <label><?php _e( 'Номер поверху', 'okbi-apartments' ); ?></label>
                    <input type="number" class="fok-floor-number" value="<?php echo esc_attr( $number ); ?>">
                </div>
            </div>

            <div class="fok-floor-col-image">
                <div class="fok-floor-field-group">
                    <label><?php _e( 'Зображення плану', 'okbi-apartments' ); ?></label>
                    <div class="fok-image-preview-thumb">
                        <?php if ( $image_thumb_src ) echo '<img src="' . esc_url( $image_thumb_src ) . '">'; ?>
                    </div>
                    <input type="hidden" class="fok-floor-image-id" value="<?php echo esc_attr( $image_id ); ?>">
                </div>
                 <div class="fok-floor-field-group image-buttons">
                    <button type="button" class="button fok-upload-image-btn"><?php _e( 'Завантажити', 'okbi-apartments' ); ?></button>
                    <button type="button" class="button fok-remove-image-btn" style="<?php if ( ! $image_id ) echo 'display:none;'; ?>"><?php _e( 'Видалити', 'okbi-apartments' ); ?></button>
                </div>
            </div>

            <div class="fok-floor-col-actions">
                 <div class="fok-floor-field-group">
                    <button type="button" class="button button-primary fok-open-plan-editor-btn" <?php if ( ! $image_id ) echo 'disabled'; ?>>
                         <?php _e( 'Редагувати план', 'okbi-apartments' ); ?>
                     </button>
                </div>
                <div class="fok-floor-actions">
                    <button type="button" class="button-link-delete fok-delete-floor-btn"><span class="dashicons dashicons-trash"></span></button>
                </div>
            </div>
            
            <textarea class="fok-floor-polygons-data" style="display:none;"><?php echo esc_textarea( $polygons_json ); ?></textarea>

        </div>
        <?php
    }
}

    // Мета-поля для Нерухомості
    $meta_boxes[] = [
        'title'      => __( 'Параметри об\'єкта', 'okbi-apartments' ),
        'id'         => 'property_details',
        'post_types' => ['apartment', 'commercial_property', 'parking_space', 'storeroom'],
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => [
            [
                'id' => 'fok_property_unique_id', 'name' => __( 'Унікальний ID (для імпорту)', 'okbi-apartments' ),
                'type' => 'text', 'desc' => __( 'Не редагуйте це поле, воно генерується автоматично або під час імпорту.', 'okbi-apartments' ), 'readonly' => true,
            ],
            [
                'id' => 'fok_property_number', 'name' => __( 'Номер об\'єкта', 'okbi-apartments' ),
                'type' => 'text', 'desc' => __( 'Наприклад, "A-101" або "Квартира 15". Використовується для автоматичного сортування.', 'okbi-apartments' ),
            ],
            [
                'id' => 'fok_property_rc_link', 'name' => __( 'Житловий комплекс', 'okbi-apartments' ),
                'type' => 'post', 'post_type' => 'residential_complex', 'field_type' => 'select_advanced',
                'placeholder' => __( 'Оберіть ЖК', 'okbi-apartments' ), 'required' => true,
            ],
            [
                'id'          => 'fok_property_section_link',
                'name'        => 'Секція',
                'type'        => 'post',
                'post_type'   => 'section',
                'field_type'  => 'select_advanced',
                'placeholder' => 'Оберіть секцію',
                'required'    => false,
            ],
            [
                'id'   => 'fok_property_floor',
                'name' => __( 'Поверх / Ряд (Y)', 'okbi-apartments' ),
                'type' => 'number',
                'min'  => -5,
                'desc' => __( 'Номер поверху. Це також початкова позиція об\'єкта по вертикалі (координата Y) в сітці.', 'okbi-apartments' ),
            ],
            [
                'id'   => 'fok_property_grid_column_start',
                'name' => __( 'Колонка (X)', 'okbi-apartments' ),
                'type' => 'number',
                'min'  => 1,
                'desc' => __('Початкова позиція об\'єкта по горизонталі (координата X). <strong>Залиште порожнім для автоматичного розміщення.</strong>', 'okbi-apartments'),
            ],
            [
                'id'   => 'fok_property_grid_column_span',
                'name' => __( 'Ширина в клітинках (X Span)', 'okbi-apartments' ),
                'type' => 'number',
                'min'  => 1,
                'std'  => 1,
                'desc' => __('Скільки горизонтальних клітинок займає об\'єкт.', 'okbi-apartments'),
            ],
            [
                'id'   => 'fok_property_grid_row_span',
                'name' => __( 'Висота в клітинках / Рівні (Y Span)', 'okbi-apartments' ),
                'type' => 'number',
                'min'  => 1,
                'std'  => 1,
                'desc' => __('Скільки вертикальних клітинок (поверхів) займає об\'єкт. Для дворівневих вкажіть "2".', 'okbi-apartments'),
            ],
            [
                'id' => 'fok_property_rooms', 'name' => __( 'Кількість кімнат', 'okbi-apartments' ),
                'type' => 'number', 'min' => 1, 'visible' => ['post_type', '=', 'apartment'],
            ],
            [ 'id' => 'fok_property_area', 'name' => __( 'Площа, м²', 'okbi-apartments' ), 'type' => 'number', 'step' => '0.01', ],
            [
                'name' => __( 'Ціна за м²', 'okbi-apartments' ),
                'id'   => 'fok_property_price_per_sqm',
                'type' => 'number',
                'step' => '0.01',
                'desc' => __( 'Заповнюйте, якщо ціна розраховується на основі площі.', 'okbi-apartments' ),
            ],
            [
                'name' => __( 'Загальна ціна за об\'єкт', 'okbi-apartments' ),
                'id'   => 'fok_property_total_price_manual',
                'type' => 'number',
                'desc' => __( 'Заповнюйте, якщо ціна фіксована (напр. для паркомісць). Має пріоритет над ціною за м².', 'okbi-apartments' ),
            ],
            [
                'name'    => __( 'Валюта', 'okbi-apartments' ),
                'id'      => 'fok_property_currency',
                'type'    => 'select',
                'options' => [ 'UAH' => 'UAH', 'USD' => 'USD', 'EUR' => 'EUR', ],
            ],
            [
                'name' => __( 'Знижка, %', 'okbi-apartments' ),
                'id'   => 'fok_property_discount_percent',
                'type' => 'number',
                'min'  => 0,
                'max'  => 100,
                'step' => 1,
            ],
            [
                'id' => 'fok_property_status_link', 'name' => __( 'Статус', 'okbi-apartments' ),
                'type' => 'taxonomy', 'taxonomy' => 'status', 'field_type' => 'select_advanced',
                'placeholder' => __( 'Оберіть статус', 'okbi-apartments' ), 'remove_default' => true,
            ],
            [
                'id' => 'fok_property_layout_images', 'name' => __( 'Зображення планувань', 'okbi-apartments' ),
                'type' => 'image_advanced', 'max_file_uploads' => 5,
            ]
        ],
    ];

    // Мета-поля для таксономії Статусів
    $meta_boxes[] = [
        'title' => __( 'Налаштування статусу', 'okbi-apartments' ), 'id' => 'status_color',
        'taxonomies' => ['status'],
        'fields' => [ [ 'id' => 'fok_status_color', 'name' => __( 'Колір статусу', 'okbi-apartments' ), 'type' => 'color', ], ],
    ];

    return $meta_boxes;
}