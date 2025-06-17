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
                'id'   => 'fok_rc_address',
                'name' => __( 'Адреса', 'okbi-apartments' ),
                'type' => 'text',
            ],
            [
                'id'   => 'fok_rc_description',
                'name' => __( 'Короткий опис', 'okbi-apartments' ),
                'type' => 'textarea',
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
                'id'          => 'fok_section_rc_link',
                'name'        => __( 'Прив\'язка до ЖК', 'okbi-apartments' ),
                'type'        => 'post',
                'post_type'   => 'residential_complex',
                'field_type'  => 'select_advanced',
                'placeholder' => __( 'Оберіть ЖК', 'okbi-apartments' ),
                'required'    => true,
            ],
            [
                'id'     => 'fok_section_floors',
                'name'   => __( 'Планування поверхів', 'okbi-apartments' ),
                'type'   => 'group',
                'clone'  => true,
                'sort_clone' => true,
                'fields' => [
                    [
                        'name' => __( 'Номер поверху', 'okbi-apartments' ),
                        'id'   => 'number',
                        'type' => 'number',
                        'min'  => 0,
                    ],
                    [
                        'name' => __( 'Зображення плану', 'okbi-apartments' ),
                        'id'   => 'image',
                        'type' => 'single_image',
                    ],
                    // --- НОВІ ПОЛЯ ДЛЯ РЕДАКТОРА ---
                    [
                        'id'      => 'polygons_button',
                        'type'    => 'custom_html',
                        'std'     => '<button type="button" class="button fok-edit-floor-polygons">Редагувати полігони квартир</button>',
                        'desc'    => 'Збережіть зміни, щоб кнопка стала активною для нових поверхів.',
                    ],
                    [
                        'id'      => 'polygons_data',
                        'type'    => 'hidden', // Приховане поле для зберігання JSON-даних
                    ],
                    // --- КІНЕЦЬ НОВИХ ПОЛІВ ---
                ],
            ],
        ],
    ];

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
                'type' => 'text', 'desc' => __( 'Наприклад, "A-101" або "Квартира 15"', 'okbi-apartments' ),
            ],
            [
                'id' => 'fok_property_rc_link', 'name' => __( 'Житловий комплекс', 'okbi-apartments' ),
                'type' => 'post', 'post_type' => 'residential_complex', 'field_type' => 'select_advanced',
                'placeholder' => __( 'Оберіть ЖК', 'okbi-apartments' ), 'required' => true,
            ],
            [
                'id' => 'fok_property_section_link', 'name' => __( 'Секція', 'okbi-apartments' ),
                'type' => 'post', 'post_type' => 'section', 'field_type' => 'select_advanced',
                'placeholder' => __( 'Оберіть секцію', 'okbi-apartments' ), 'parent_field' => 'fok_property_rc_link',
                'query_args' => [ 'meta_key' => 'fok_section_rc_link', 'meta_value' => '{{parent}}', ],
            ],
            [ 'id' => 'fok_property_floor', 'name' => __( 'Поверх', 'okbi-apartments' ), 'type' => 'number', 'min'  => 0, ],
            [
                'id' => 'fok_property_rooms', 'name' => __( 'Кількість кімнат', 'okbi-apartments' ),
                'type' => 'number', 'min' => 1, 'visible' => ['post_type', '=', 'apartment'],
            ],
            [ 'id' => 'fok_property_area', 'name' => __( 'Площа, м²', 'okbi-apartments' ), 'type' => 'number', 'step' => '0.01', ],
            [
                'id' => 'fok_property_price', 'name' => __( 'Ціна', 'okbi-apartments' ), 'type' => 'group',
                'fields' => [
                    [ 'name' => __( 'Ціна за м²', 'okbi-apartments' ), 'id' => 'value', 'type' => 'number', 'step' => '0.01', ],
                    [ 'name' => __( 'Валюта', 'okbi-apartments' ), 'id' => 'currency', 'type' => 'select',
                        'options' => [ 'UAH' => 'UAH', 'USD' => 'USD', 'EUR' => 'EUR', ],
                    ],
                ],
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

// ---- КОД ДЛЯ ІНТЕРАКТИВНОГО РЕДАКТОРА ГЕНПЛАНУ ----
add_action('add_meta_boxes', 'fok_add_interactive_genplan_metabox');
function fok_add_interactive_genplan_metabox() {
    add_meta_box( 'fok_rc_interactive_genplan_metabox_id', 'Інтерактивний Генплан (Редактор)', 'fok_render_interactive_genplan_callback', 'residential_complex', 'normal', 'high' );
}

function fok_render_interactive_genplan_callback($post) {
    wp_nonce_field('fok_save_interactive_genplan_data', 'fok_interactive_genplan_nonce');
    $genplan_image_id = get_post_meta($post->ID, '_fok_rc_genplan_image', true);
    $genplan_polygons = get_post_meta($post->ID, '_fok_rc_genplan_polygons', true);
    $image_url = $genplan_image_id ? wp_get_attachment_image_url($genplan_image_id, 'full') : '';
    $image_meta = $genplan_image_id ? wp_get_attachment_metadata($genplan_image_id) : null;
    $sections_query = new WP_Query([ 'post_type' => 'section', 'posts_per_page' => -1, 'meta_key' => 'fok_section_rc_link', 'meta_value' => $post->ID, 'orderby' => 'title', 'order' => 'ASC', ]);
    ?>
    <div id="fok-genplan-editor-wrapper" data-image-url="<?php echo esc_url($image_url); ?>" data-image-width="<?php echo isset($image_meta['width']) ? esc_attr($image_meta['width']) : '0'; ?>" data-image-height="<?php echo isset($image_meta['height']) ? esc_attr($image_meta['height']) : '0'; ?>">
        <h4>1. Завантажте зображення генплану</h4>
        <input type="hidden" name="fok_rc_genplan_image" id="fok_rc_genplan_image" value="<?php echo esc_attr($genplan_image_id); ?>">
        <button type="button" class="button" id="fok_upload_image_button">Завантажити/змінити зображення</button>
        <p class="description"><?php _e('Для кращої чіткості рекомендується завантажувати зображення шириною від 1500px.', 'okbi-apartments'); ?></p>
        <hr>
        <h4>2. Налаштування полігонів для секцій</h4>
        <div id="fok-genplan-controls">
            <div class="fok-control-group">
                <label for="fok-polygon-section-link">Виберіть секцію для прив'язки:</label>
                <select id="fok-polygon-section-link">
                    <option value="">— Не вибрано —</option>
                    <?php if ($sections_query->have_posts()) { while ($sections_query->have_posts()) { $sections_query->the_post(); echo '<option value="' . get_the_ID() . '">' . get_the_title() . '</option>'; } } wp_reset_postdata(); ?>
                </select>
            </div>
            <div class="fok-control-group">
                <button type="button" class="button button-primary" id="fok-draw-polygon-btn">Малювати полігон</button>
                <button type="button" class="button button-secondary" id="fok-delete-selected">Видалити вибране</button>
            </div>
        </div>
        <div id="fok-genplan-canvas-container"><canvas id="fok-genplan-canvas"></canvas></div>
        <div id="fok-polygon-list">
            <h4>Створені полігони:</h4>
            <ul></ul>
        </div>
        <input type="hidden" name="fok_rc_genplan_polygons" id="fok_rc_genplan_polygons" value="<?php echo esc_attr($genplan_polygons); ?>">
    </div>
    <?php
}

add_action('save_post', 'fok_save_interactive_genplan_data');
function fok_save_interactive_genplan_data($post_id) {
    if (!isset($_POST['fok_interactive_genplan_nonce']) || !wp_verify_nonce($_POST['fok_interactive_genplan_nonce'], 'fok_save_interactive_genplan_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['fok_rc_genplan_image'])) { update_post_meta($post_id, '_fok_rc_genplan_image', sanitize_text_field($_POST['fok_rc_genplan_image'])); }
    if (isset($_POST['fok_rc_genplan_polygons'])) { update_post_meta($post_id, '_fok_rc_genplan_polygons', wp_kses_post($_POST['fok_rc_genplan_polygons'])); }
}

// ---- HTML ДЛЯ МОДАЛЬНОГО ВІКНА РЕДАКТОРА ПОВЕРХІВ ----
// Ми додамо це вікно один раз в футері адмін-панелі, а JS буде його використовувати
add_action('admin_footer', 'fok_add_floor_editor_modal');
function fok_add_floor_editor_modal() {
    $screen = get_current_screen();
    // Виводимо модальне вікно тільки на сторінці редагування секції
    if ( ! $screen || $screen->post_type !== 'section' ) {
        return;
    }
    ?>
    <div id="fok-floor-editor-modal" style="display: none;">
        <div class="fok-modal-overlay"></div>
        <div class="fok-modal-content">
            <button type="button" class="fok-modal-close">&times;</button>
            <div class="fok-modal-header">
                <h3>Редактор полігонів для поверху <span id="fok-modal-floor-number"></span></h3>
            </div>
            <div class="fok-modal-body">
                <div id="fok-floor-canvas-controls">
                    <div class="fok-control-group">
                        <label for="fok-floor-property-link">Прив'язати до об'єкта:</label>
                        <select id="fok-floor-property-link" data-placeholder="Спочатку виберіть полігон..."></select>
                    </div>
                    <div class="fok-control-group">
                        <button type="button" class="button button-primary" id="fok-floor-draw-btn">Малювати полігон</button>
                        <button type="button" class="button button-secondary" id="fok-floor-delete-btn">Видалити вибране</button>
                    </div>
                </div>
                <div id="fok-floor-canvas-container">
                    <canvas id="fok-floor-canvas"></canvas>
                </div>
            </div>
            <div class="fok-modal-footer">
                <button type="button" class="button button-primary" id="fok-modal-save">Зберегти та закрити</button>
            </div>
        </div>
    </div>
    <?php
}
