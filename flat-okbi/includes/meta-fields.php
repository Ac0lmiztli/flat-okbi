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
                'type' => 'post',
                'post_type' => 'residential_complex',
                'field_type' => 'select_advanced',
                'attributes' => [ 'disabled' => 'disabled' ],
            ],
            [
                'name' => 'Секція',
                'id'   => '_lead_section_id',
                'type' => 'post',
                'post_type' => 'section',
                'field_type' => 'select_advanced',
                'attributes' => [ 'disabled' => 'disabled' ],
            ],
            [
                'name' => 'Об\'єкт, на який подано заявку',
                'id'   => '_lead_property_id',
                'type' => 'post',
                'post_type' => ['apartment', 'commercial_property', 'parking_space', 'storeroom'],
                'field_type' => 'select_advanced',
                'attributes' => [ 'disabled' => 'disabled' ],
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
                             <p class="description">
                                <?php _e('Оберіть об\'єкт зі списку, щоб почати малювати полігон. Клік лівою кнопкою миші додає точку. Клік правою кнопкою - завершує малювання.', 'okbi-apartments'); ?>
                             </p>
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
            <span class="dashicons dashicons-menu handle"></span>
            
            <div class="fok-floor-field-group">
                <label><?php _e( 'Номер поверху', 'okbi-apartments' ); ?></label>
                <input type="number" class="fok-floor-number" value="<?php echo esc_attr( $number ); ?>">
            </div>

            <div class="fok-floor-field-group">
                <label><?php _e( 'Зображення плану', 'okbi-apartments' ); ?></label>
                 <div class="fok-image-preview-thumb">
                    <?php if ( $image_thumb_src ) echo '<img src="' . esc_url( $image_thumb_src ) . '">'; ?>
                </div>
                <input type="hidden" class="fok-floor-image-id" value="<?php echo esc_attr( $image_id ); ?>">
                <button type="button" class="button fok-upload-image-btn"><?php _e( 'Завантажити', 'okbi-apartments' ); ?></button>
                <button type="button" class="button fok-remove-image-btn" style="<?php if ( ! $image_id ) echo 'display:none;'; ?>"><?php _e( 'Видалити', 'okbi-apartments' ); ?></button>
            </div>
            
            <div class="fok-floor-field-group">
                <label><?php _e( 'Редагування плану', 'okbi-apartments' ); ?></label>
                <button type="button" class="button button-primary fok-open-plan-editor-btn" <?php if ( ! $image_id ) echo 'disabled'; ?>>
                     <?php _e( 'Редагувати план', 'okbi-apartments' ); ?>
                </button>
            </div>

            <textarea class="fok-floor-polygons-data" style="display:none;"><?php echo esc_textarea( $polygons_json ); ?></textarea>
            
            <div class="fok-floor-actions">
                <button type="button" class="button-link-delete fok-delete-floor-btn"><span class="dashicons dashicons-trash"></span></button>
            </div>
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
                /* 'std' більше не потрібен, поле має бути порожнім за замовчуванням */
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