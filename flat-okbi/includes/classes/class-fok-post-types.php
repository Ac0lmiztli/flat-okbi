<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class FOK_Post_Types
 *
 * Handles the registration of custom post types.
 */
class FOK_Post_Types {

    /**
     * Register custom post types.
     */
    public static function register() {
        $cpt_args = [
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => true,
            'supports'     => ['title', 'thumbnail', 'custom-fields'],
            'show_in_menu' => 'fok_dashboard',
        ];

        $top_level_cpt_args = array_merge($cpt_args, [
            'supports' => ['title', 'thumbnail', 'excerpt'],
        ]);

        register_post_type('residential_complex', array_merge($top_level_cpt_args, [
            'labels'    => ['name' => __('Житлові комплекси', 'okbi-apartments'), 'singular_name' => __('Житловий комплекс', 'okbi-apartments'), 'add_new_item' => __('Додати новий ЖК', 'okbi-apartments')],
            'rewrite'   => ['slug' => 'residential-complexes'],
            'menu_icon' => 'dashicons-building',
        ]));

        register_post_type('section', array_merge($top_level_cpt_args, [
            'labels'    => ['name' => __('Секції', 'okbi-apartments'), 'singular_name' => __('Секція', 'okbi-apartments'), 'add_new_item' => __('Додати нову секцію', 'okbi-apartments')],
            'rewrite'   => ['slug' => 'sections'],
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-layout',
        ]));

        register_post_type('apartment', array_merge($cpt_args, [
            'labels'    => ['name' => __('Квартири', 'okbi-apartments'), 'singular_name' => __('Квартира', 'okbi-apartments'), 'add_new_item' => __('Додати нову квартиру', 'okbi-apartments')],
            'rewrite'   => ['slug' => 'apartments'],
            'menu_icon' => 'dashicons-admin-home',
        ]));

        register_post_type('commercial_property', array_merge($cpt_args, [
            'labels'    => ['name' => __('Комерція', 'okbi-apartments'), 'singular_name' => __('Комерція', 'okbi-apartments'), 'add_new_item' => __('Додати комерцію', 'okbi-apartments')],
            'rewrite'   => ['slug' => 'commercial'],
            'menu_icon' => 'dashicons-store',
        ]));

        register_post_type('parking_space', array_merge($cpt_args, [
            'labels'    => ['name' => __('Паркомісця', 'okbi-apartments'), 'singular_name' => __('Паркомісце', 'okbi-apartments'), 'add_new_item' => __('Додати паркомісце', 'okbi-apartments')],
            'rewrite'   => ['slug' => 'parking'],
            'menu_icon' => 'dashicons-car',
        ]));

        register_post_type('storeroom', array_merge($cpt_args, [
            'labels'    => ['name' => __('Комори', 'okbi-apartments'), 'singular_name' => __('Комора', 'okbi-apartments'), 'add_new_item' => __('Додати комору', 'okbi-apartments')],
            'rewrite'   => ['slug' => 'storerooms'],
            'menu_icon' => 'dashicons-archive',
        ]));
        
        register_post_type('fok_lead', [
            'labels'        => [
                'name'          => __('Заявки', 'okbi-apartments'),
                'singular_name' => __('Заявка', 'okbi-apartments'),
                'add_new_item'  => __('Додати нову заявку', 'okbi-apartments'),
                'edit_item'     => __('Редагувати заявку', 'okbi-apartments'),
                'all_items'     => __('Всі заявки', 'okbi-apartments'),
                'view_item'     => __('Переглянути заявку', 'okbi-apartments'),
            ],
            'public'        => false, 
            'show_ui'       => true,
            'show_in_menu'  => true, // Return to top-level menu
            'menu_icon'     => 'dashicons-id-alt',
            'supports'      => ['title'],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'do_not_allow', 
            ],
            'map_meta_cap' => true,
        ]);
    }
}