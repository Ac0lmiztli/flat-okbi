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
                        'min'  => 0,
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
    $meta_boxes[] = array(
        'id'         => $prefix . 'apartment_details',
        'title'      => __( 'Параметри квартири', 'okbi-apartments' ),
        'post_types' => array( 'apartment' ),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => array(
            array(
                'id'          => $prefix . 'apartment_unique_id',
                'name'        => __( 'Унікальний ID', 'okbi-apartments' ),
                'type'        => 'text',
                'readonly'    => true,
                'desc'        => __( 'Цей ID використовується для оновлення даних при імпорті. Генерується автоматично.', 'okbi-apartments' ),
            ),
            array(
                'id'          => $prefix . 'apartment_rc_link',
                'name'        => __( 'Належить до ЖК', 'okbi-apartments' ),
                'type'        => 'post',
                'post_type'   => 'residential_complex',
                'field_type'  => 'select_advanced',
            ),
            array(
                'id'          => $prefix . 'apartment_section_link',
                'name'        => __( 'Належить до Секції', 'okbi-apartments' ),
                'type'        => 'post',
                'post_type'   => 'section',
                'field_type'  => 'select_advanced',
            ),
            array(
                'name'    => __( 'Ціна за м²', 'okbi-apartments' ),
                'id'      => $prefix . 'apartment_price',
                'type'    => 'group',
                'fields'  => array(
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
                'inline'  => true,
            ),
            array(
                'name' => __( 'Загальна площа', 'okbi-apartments' ),
                'id'   => $prefix . 'apartment_area',
                'type' => 'number',
                'min'  => 0,
                'step' => 'any',
                'append' => 'м²',
            ),
             array(
                'name' => __( 'Кількість кімнат', 'okbi-apartments' ),
                'id'   => $prefix . 'apartment_rooms',
                'type' => 'number',
                'min'  => 1,
                'step' => 1,
            ),
            array(
                'name' => __( 'Поверх', 'okbi-apartments' ),
                'id'   => $prefix . 'apartment_floor',
                'type' => 'number',
                'min'  => 0,
            ),
            array(
                'name' => __( 'Номер квартири', 'okbi-apartments' ),
                'id'   => $prefix . 'apartment_number',
                'type' => 'text',
            ),
            array(
                'id'               => $prefix . 'apartment_layout_images',
                'name'             => __( 'Зображення планування', 'okbi-apartments' ),
                'type'             => 'image_advanced',
            ),
        ),
    );

    // 4. Поле для статусу квартири (в бічній панелі)
    $meta_boxes[] = array(
        'id'         => $prefix . 'apartment_status_metabox',
        'title'      => __( 'Статус', 'okbi-apartments' ),
        'post_types' => array( 'apartment' ),
        'context'    => 'side',
        'priority'   => 'high',
        'fields'     => array(
            array(
                'id'         => 'status',
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
