<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class FOK_Taxonomies
 *
 * Handles the registration of custom taxonomies.
 */
class FOK_Taxonomies {

    /**
     * Register custom taxonomies.
     */
    public static function register() {
        $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
        register_taxonomy('status', $property_types, [
            'labels' => [
                'name' => __('Статуси', 'okbi-apartments'), 
                'singular_name' => __('Статус', 'okbi-apartments')
            ], 
            'public' => true, 
            'hierarchical' => true, 
            'show_admin_column' => true, 
            'show_in_rest' => true, 
            'rewrite' => ['slug' => 'status']
        ]);

        register_taxonomy('lead_status', ['fok_lead'], [
            'labels' => [
                'name' => __('Статуси заявок', 'okbi-apartments'),
                'singular_name' => __('Статус заявки', 'okbi-apartments')
            ],
            'public' => false,
            'show_ui' => true,
            'hierarchical' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'lead-status']
        ]);
    }

    /**
     * Insert initial status terms if they don't exist.
     */
    public static function insert_initial_terms() {
        // Statuses for properties
        $property_statuses = [
            'Вільно'       => 'vilno',
            'Продано'      => 'prodano',
            'Заброньовано' => 'zabronovano'
        ];
        foreach ($property_statuses as $name => $slug) {
            if (!term_exists($slug, 'status')) {
                wp_insert_term($name, 'status', ['slug' => $slug]);
            }
        }

        // Statuses for leads
        $lead_statuses = [
            'Нова'       => 'new',
            'В роботі'   => 'in-progress',
            'Успіх'      => 'success',
            'Невдача'    => 'fail'
        ];
        foreach ($lead_statuses as $name => $slug) {
            if (!term_exists($slug, 'lead_status')) {
                wp_insert_term($name, 'lead_status', ['slug' => $slug]);
            }
        }
    }
}
