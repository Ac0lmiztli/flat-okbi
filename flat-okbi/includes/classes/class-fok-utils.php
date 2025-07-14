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
            'filter'  => '<svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="style=linear"><g id="filter-rectangle"><path id="vector" d="M2 17.5H7" stroke="#000000" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path id="vector_2" d="M22 6.5H17" stroke="#000000" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path id="vector_3" d="M13 17.5H22" stroke="#000000" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path id="vector_4" d="M11 6.5H2" stroke="#000000" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path id="rec" d="M7 15.5C7 14.9477 7.44772 14.5 8 14.5H12C12.5523 14.5 13 14.9477 13 15.5V19.5C13 20.0523 12.5523 20.5 12 20.5H8C7.44772 20.5 7 20.0523 7 19.5V15.5Z" stroke="#000000" stroke-width="1.5"/><path id="rec_2" d="M17 4.5C17 3.94772 16.5523 3.5 16 3.5H12C11.4477 3.5 11 3.94772 11 4.5V8.5C11 9.05228 11.4477 9.5 12 9.5H16C16.5523 9.5 17 9.05228 17 8.5V4.5Z" stroke="#000000" stroke-width="1.5"/></g></g></svg>',
            'phone'   => '<svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="style=linear"><g id="call"><path id="vector" d="M21.97 18.33C21.97 18.69 21.89 19.06 21.72 19.42C21.55 19.78 21.33 20.12 21.04 20.44C20.55 20.98 20.01 21.37 19.4 21.62C18.8 21.87 18.15 22 17.45 22C16.43 22 15.34 21.76 14.19 21.27C13.04 20.78 11.89 20.12 10.75 19.29C9.6 18.45 8.51 17.52 7.47 16.49C6.44 15.45 5.51 14.36 4.68 13.22C3.86 12.08 3.2 10.94 2.72 9.81C2.24 8.67 2 7.58 2 6.54C2 5.86 2.12 5.21 2.36 4.61C2.6 4 2.98 3.44 3.51 2.94C4.15 2.31 4.85 2 5.59 2C5.87 2 6.15 2.06 6.4 2.18C6.66 2.3 6.89 2.48 7.07 2.74L9.39 6.01C9.57 6.26 9.7 6.49 9.79 6.71C9.88 6.92 9.93 7.13 9.93 7.32C9.93 7.56 9.86 7.8 9.72 8.03C9.59 8.26 9.4 8.5 9.16 8.74L8.4 9.53C8.29 9.64 8.24 9.77 8.24 9.93C8.24 10.01 8.25 10.08 8.27 10.16C8.3 10.24 8.33 10.3 8.35 10.36C8.53 10.69 8.84 11.12 9.28 11.64C9.73 12.16 10.21 12.69 10.73 13.22C11.27 13.75 11.79 14.24 12.32 14.69C12.84 15.13 13.27 15.43 13.61 15.61C13.66 15.63 13.72 15.66 13.79 15.69C13.87 15.72 13.95 15.73 14.04 15.73C14.21 15.73 14.34 15.67 14.45 15.56L15.21 14.81C15.46 14.56 15.7 14.37 15.93 14.25C16.16 14.11 16.39 14.04 16.64 14.04C16.83 14.04 17.03 14.08 17.25 14.17C17.47 14.26 17.7 14.39 17.95 14.56L21.26 16.91C21.52 17.09 21.7 17.3 21.81 17.55C21.91 17.8 21.97 18.05 21.97 18.33Z" stroke-width="1.5" stroke-miterlimit="10"/></g></g></svg>',
            'close'   => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.75 6.74512L17.25 17.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M17.25 6.74512L6.75 17.2499" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            'search'  => '<svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="style=linear"><g id="search-broken"><path id="vector" d="M18.5 18.5L21 21" stroke-width="1.5" stroke-linecap="round"/><circle id="vector_2" cx="11.0529" cy="11.0529" r="8.3029" stroke-width="1.5"/></g></g></svg>',
            'parking' => '<svg width="800px" height="800px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 14h1.5a3.5 3.5 0 0 0 0-7H9v10h2v-3zM4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm7 6h1.5a1.5 1.5 0 0 1 0 3H11V9z" fill="currentColor"/></svg>',
        ];

        return $icons[ $icon_name ] ?? '';
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
