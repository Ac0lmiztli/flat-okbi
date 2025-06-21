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
                'name' => __( 'Загальна кількість поверхів', 'okbi-apartments' ),
                'type' => 'number',
                'min'  => 1,
                'desc' => __( 'Вкажіть, скільки всього поверхів у цій секції.', 'okbi-apartments' ),
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
                'id'          => 'fok_property_section_link',
                'name'        => 'Секція',
                'type'        => 'post',
                'post_type'   => 'section',
                'field_type'  => 'select_advanced',
                'placeholder' => 'Оберіть секцію',
                'required'    => false,
            ],
            [ 'id' => 'fok_property_floor', 'name' => __( 'Поверх', 'okbi-apartments' ), 'type' => 'number', 'min'  => 0, ],
            [
                'id'   => 'fok_property_levels',
                'name' => __( 'Кількість рівнів (поверхів)', 'okbi-apartments' ),
                'type' => 'number',
                'min'  => 1,
                'std'  => 1, // Значення за замовчуванням - 1
                'desc' => __('Для звичайних квартир залиште "1". Для дворівневих вкажіть "2".', 'okbi-apartments'),
                'visible' => ['post_type', '=', 'apartment'], // Показувати тільки для квартир
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
            // --- ЦІНВ ЗА ОБʼЄКТ ---
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
            // --- ЗНИЖКА ---
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
