<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class FOK_Utils
 *
 * A collection of static utility functions.
 */
class FOK_Utils {

    /**
     * Returns the SVG code for a given icon name.
     *
     * @param string $icon_name The name of the icon.
     * @return string The SVG code.
     */
    public static function get_icon( $icon_name ) {
        $icons = [
            'filter' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>',
            'phone' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>',
            'close' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>',
            'search' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>',
            'parking' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="currentColor"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M13.2 3.41c-.78-.78-2.05-.78-2.83 0l-2.58 2.58c-.78.78-.78 2.05 0 2.83.78.78 2.05.78 2.83 0l2.58-2.58c.78-.78.78-2.05 0-2.83zM11.5 14.5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5-2.5-1.12-2.5-2.5zM19 13c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6-2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM4 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm1-5c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm13-1l-2.12-2.12c-1.17-1.17-3.07-1.17-4.24 0L7.52 9.01c-1.17 1.17-1.17 3.07 0 4.24L9.64 15.4c1.17 1.17 3.07 1.17 4.24 0L18.12 11c1.17-1.17 1.17-3.07 0-4.24zM12.07 14.2c-.39.39-1.02.39-1.41 0L7.83 11.37c-.39-.39-.39-1.02 0-1.41.39-.39 1.02-.39 1.41 0l2.83 2.83c.39.39.39 1.02 0 1.41z"/></svg>',
        ];
        return $icons[$icon_name] ?? '';
    }

    /**
     * Finds an attachment ID by its filename.
     *
     * @param string $filename The filename to search for.
     * @return int The attachment ID, or 0 if not found.
     */
    public static function get_attachment_id_by_filename($filename)
    {
        $attachment_id = 0;
        $attachment_query = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_wp_attached_file',
                    'value'   => $filename,
                    'compare' => 'LIKE',
                ],
            ],
            'fields' => 'ids',
        ]);
        if (!empty($attachment_query->posts)) {
            $attachment_id = $attachment_query->posts[0];
        }
        return $attachment_id;
    }
}
