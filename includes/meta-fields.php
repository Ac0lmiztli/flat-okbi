<?php
// Запобігаємо прямому доступу до файлу.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Реєструє кастомні поля (meta boxes) для плагіна.
 *
 * @param array $meta_boxes Існуючий масив meta boxes.
 * @return array Модифікований масив meta boxes.
 */
function fok_register_meta_boxes( $meta_boxes ) {

    $prefix = 'fok_';

    // --- Спільні поля для всіх типів нерухомості ---
    $shared_property_fields = array(
        array(
            'id'       => $prefix . 'property_unique_id',
            'name'     => __( 'Унікальний ID', 'okbi-apartments' ),
            'type'     => 'text',
            'readonly' => true,
            'desc'     => __( 'Цей ID використовується для оновлення даних при імпорті. Генерується автоматично.', 'okbi-apartments' ),
        ),
        array(
            'id'         => $prefix . 'property_rc_link',
            'name'       => __( 'Належить до ЖК', 'okbi-apartments' ),
            'type'       => 'post',
            'post_type'  => 'residential_complex',
            'field_type' => 'select_advanced',
        ),
        array(
            'id'         => $prefix . 'property_section_link',
            'name'       => __( 'Належить до Секції', 'okbi-apartments' ),
            'type'       => 'post',
            'post_type'  => 'section',
            'field_type' => 'select_advanced',
        ),
        array(
            'name'   => __( 'Ціна за м²', 'okbi-apartments' ),
            'id'     => $prefix . 'property_price',
            'type'   => 'group',
            'fields' => array(
                array(
                    'name' => __( 'Вартість', 'okbi-apartments' ),
                    'id'   => 'value',
                    'type' => 'number',
                    'min'  => 0,
                    'step' => 'any',
                ),
                array(
                    'name'    => __( 'Валюта', 'okbi-apartments' ),
                    'id'      => 'currency',
                    'type'    => 'select',
                    'options' => ['UAH' => 'UAH', 'USD' => 'USD', 'EUR' => 'EUR'],
                    'std' => 'UAH',
                ),
            ),
            'inline' => true,
        ),
        array(
            'name'   => __( 'Загальна площа', 'okbi-apartments' ),
            'id'     => $prefix . 'property_area',
            'type'   => 'number',
            'min'    => 0,
            'step'   => 'any',
            'append' => 'м²',
        ),
        array(
            'name' => __( 'Поверх', 'okbi-apartments' ),
            'id'   => $prefix . 'property_floor',
            'type' => 'number',
            'min'  => -10, // Дозволяє вводити поверхи до -10
            'step' => 1,   // Крок в одне ціле число (1, 0, -1, -2)
        ),
        array(
            'name' => __( 'Номер об\'єкта', 'okbi-apartments' ),
            'id'   => $prefix . 'property_number',
            'type' => 'text',
            'desc' => __('Номер квартири, паркомісця, комори і т.д.', 'okbi-apartments'),
        ),
        array(
            'id'   => $prefix . 'property_layout_images',
            'name' => __( 'Зображення/Планування', 'okbi-apartments' ),
            'type' => 'image_advanced',
        ),
    );


    // 1. Поля для "Житлового комплексу"
    $meta_boxes[] = array(
        'id'         => $prefix . 'rc_details',
        'title'      => __( 'Деталі ЖК', 'okbi-apartments' ),
        'post_types' => array( 'residential_complex' ),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => array(
            array(
                'id'               => $prefix . 'rc_genplan',
                'name'             => __( 'Зображення Генплану', 'okbi-apartments' ),
                'type'             => 'image_advanced',
                'max_file_uploads' => 1,
            ),
        ),
    );

    // 2. Поля для "Секції"
    $meta_boxes[] = array(
        'id'         => $prefix . 'section_details',
        'title'      => __( 'Деталі Секції', 'okbi-apartments' ),
        'post_types' => array( 'section' ),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => array(
            array(
                'id'          => $prefix . 'section_rc_link',
                'name'        => __( 'Прив\'язка до ЖК', 'okbi-apartments' ),
                'type'        => 'post',
                'post_type'   => 'residential_complex',
                'field_type'  => 'select_advanced',
                'placeholder' => __( 'Виберіть ЖК', 'okbi-apartments' ),
            ),
            array(
                'id'               => $prefix . 'section_image',
                'name'             => __( 'Зображення Секції', 'okbi-apartments' ),
                'type'             => 'image_advanced',
                'max_file_uploads' => 1,
            ),
            array(
                'id'     => $prefix . 'section_floors',
                'name'   => __( 'Планування поверхів', 'okbi-apartments' ),
                'type'   => 'group',
                'clone'  => true,
                'sort_clone' => true,
                'fields' => array(
                    array(
                        'name' => __( 'Номер поверху', 'okbi-apartments' ),
                        'id'   => 'floor_number',
                        'type' => 'number',
                    ),
                    array(
                        'name' => __( 'Зображення плану поверху', 'okbi-apartments' ),
                        'id'   => 'floor_plan_image',
                        'type' => 'single_image',
                    ),
                ),
            ),
        ),
    );
    
    // 3. Поля для "Квартири"
    $apartment_fields = $shared_property_fields;
    // Додаємо унікальне поле для квартир
    array_splice( $apartment_fields, 5, 0, array(
        array(
            'name' => __( 'Кількість кімнат', 'okbi-apartments' ),
            'id'   => $prefix . 'property_rooms',
            'type' => 'number',
            'min'  => 1,
            'step' => 1,
        )
    ) );
    $meta_boxes[] = array(
        'id'         => $prefix . 'apartment_details',
        'title'      => __( 'Параметри квартири', 'okbi-apartments' ),
        'post_types' => array( 'apartment' ),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => $apartment_fields,
    );

    // 4. Поля для "Комерції"
    $meta_boxes[] = array(
        'id'         => $prefix . 'commercial_details',
        'title'      => __( 'Параметри комерції', 'okbi-apartments' ),
        'post_types' => array( 'commercial_property' ),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => $shared_property_fields,
    );

    // 5. Поля для "Паркомісця"
    $meta_boxes[] = array(
        'id'         => $prefix . 'parking_details',
        'title'      => __( 'Параметри паркомісця', 'okbi-apartments' ),
        'post_types' => array( 'parking_space' ),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => $shared_property_fields,
    );

    // 6. Поля для "Комори"
    $meta_boxes[] = array(
        'id'         => $prefix . 'storeroom_details',
        'title'      => __( 'Параметри комори', 'okbi-apartments' ),
        'post_types' => array( 'storeroom' ),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => $shared_property_fields,
    );

    // 7. Поле для статусу (спільне для всіх типів нерухомості)
    $meta_boxes[] = array(
        'id'         => $prefix . 'property_status_metabox',
        'title'      => __( 'Статус', 'okbi-apartments' ),
        'post_types' => array( 'apartment', 'commercial_property', 'parking_space', 'storeroom' ),
        'context'    => 'side',
        'priority'   => 'high',
        'fields'     => array(
            array(
                'id'         => 'status', // This should not have a prefix as it's a built-in arg for taxonomy field
                'type'       => 'taxonomy',
                'taxonomy'   => 'status',
                'field_type' => 'radio_list',
                'query_args' => ['hide_empty' => false],
            ),
        ),
    );

    return $meta_boxes;
}
add_filter( 'rwmb_meta_boxes', 'fok_register_meta_boxes' );
