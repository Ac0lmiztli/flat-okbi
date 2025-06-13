<?php
/**
 * Plugin Name:         Flat Okbi
 * Plugin URI:          https://okbi.pp.ua
 * Description:         Плагін для керування каталогом квартир та житлових комплексів.
 * Version:             1.6.14
 * Requires at least:   5.2
 * Requires PHP:        7.2
 * Author:              Okbi
 * Author URI:          https://okbi.pp.ua
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         okbi-apartments
 * Domain Path:         /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Підключення файлів
require_once plugin_dir_path( __FILE__ ) . 'includes/meta-fields.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer.php';


// --- Шорткод та фронтенд логіка ---
add_action( 'init', 'fok_register_shortcode' );
function fok_register_shortcode() {
    add_shortcode( 'okbi_viewer', 'fok_render_viewer_shortcode' );
}

function fok_render_viewer_shortcode( $atts ) {
    fok_enqueue_frontend_assets();
    $atts = shortcode_atts( ['rc_id' => 0], $atts, 'okbi_viewer' );
    $initial_rc_id = intval( $atts['rc_id'] );
    
    $options = get_option( 'fok_global_settings' );
    $logo_id = $options['logo_id'] ?? '';
    $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
    $accent_color = $options['accent_color'] ?? '#0073aa';
    
    $all_rcs = get_posts(['post_type' => 'residential_complex', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);

    ob_start();
    ?>
    <div class="fok-viewer-container" style="--fok-accent-color: <?php echo esc_attr($accent_color); ?>;" data-initial-rc="<?php echo esc_attr($initial_rc_id); ?>">
        <header class="fok-viewer-header">
            <div class="fok-logo">
                <?php if ( $logo_url ) : ?>
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Логотип', 'okbi-apartments' ); ?>">
                <?php endif; ?>
            </div>
            <nav class="fok-navigation">
                <div class="fok-rc-switcher">
                    <label for="rc-switcher"><?php _e( 'Інші ЖК:', 'okbi-apartments' ); ?></label>
                    <select name="rc-switcher" id="rc-switcher">
                        <?php
                        foreach ($all_rcs as $rc) {
                            printf('<option value="%d" %s>%s</option>',
                                esc_attr($rc->ID),
                                selected($initial_rc_id, $rc->ID, false),
                                esc_html($rc->post_title)
                            );
                        }
                        ?>
                    </select>
                </div>
                <div class="fok-view-modes">
                    <button data-mode="interactive" title="<?php esc_attr_e( 'Інтерактивний режим', 'okbi-apartments' ); ?>"><span class="dashicons dashicons-location-alt"></span></button>
                    <button data-mode="list" title="<?php esc_attr_e( 'Режим списку', 'okbi-apartments' ); ?>"><span class="dashicons dashicons-list-view"></span></button>
                </div>
            </nav>
        </header>
        <main class="fok-viewer-content">
            <div id="fok-interactive-mode">
                <h2><?php _e( 'Інтерактивний режим', 'okbi-apartments' ); ?></h2>
                <p><?php _e( 'Тут буде візуалізація генплану...', 'okbi-apartments' ); ?></p>
            </div>
            <div id="fok-list-mode">
                <div class="fok-list-container">
                    <aside class="fok-list-sidebar">
                        <h3><?php _e('Параметри пошуку', 'okbi-apartments'); ?></h3>
                        <form id="fok-filters-form">
                            <div class="fok-filter-group">
                                <label><?php _e('Кількість кімнат', 'okbi-apartments'); ?></label>
                                <div class="fok-room-buttons">
                                    <div class="room-btn active" data-value="">Всі</div>
                                    <div class="room-btn" data-value="1">1</div>
                                    <div class="room-btn" data-value="2">2</div>
                                    <div class="room-btn" data-value="3">3+</div>
                                </div>
                                <input type="hidden" name="rooms" id="filter-rooms" value="">
                            </div>
                             <div class="fok-filter-group">
                                <label for="filter-area-from"><?php _e('Площа, м²', 'okbi-apartments'); ?></label>
                                <div class="fok-filter-range">
                                    <input type="number" id="filter-area-from" name="area_from" placeholder="від">
                                    <span>-</span>
                                    <input type="number" id="filter-area-to" name="area_to" placeholder="до">
                                </div>
                            </div>
                            <div class="fok-filter-group">
                                <label for="filter-floor-from"><?php _e('Поверх', 'okbi-apartments'); ?></label>
                                <div class="fok-filter-range">
                                    <input type="number" id="filter-floor-from" name="floor_from" placeholder="від">
                                    <span>-</span>
                                    <input type="number" id="filter-floor-to" name="floor_to" placeholder="до">
                                </div>
                            </div>
                            <div class="fok-filter-group">
                                <label for="filter-status"><?php _e('Статус', 'okbi-apartments'); ?></label>
                                <select id="filter-status" name="status">
                                    <option value=""><?php _e('Будь-який', 'okbi-apartments'); ?></option>
                                    <?php $statuses = get_terms(['taxonomy' => 'status', 'hide_empty' => false]);
                                    foreach ($statuses as $status) {
                                        echo '<option value="' . esc_attr($status->slug) . '">' . esc_html($status->name) . '</option>';
                                    } ?>
                                </select>
                            </div>
                        </form>
                    </aside>
                    <div class="fok-list-results" id="fok-results-container">
                         <div class="fok-loader"></div>
                         <div class="fok-list-content"></div>
                    </div>
                    <!-- Панель з деталями -->
                    <aside id="fok-details-panel">
                        <button id="fok-panel-close">&times;</button>
                        <div id="fok-panel-content">
                            <!-- AJAX-контент буде завантажено сюди -->
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </div>
    <!-- HTML-розмітка для лайтбоксу -->
    <div id="fok-lightbox" class="fok-lightbox-overlay">
        <span class="fok-lightbox-close">&times;</span>
        <img class="fok-lightbox-content" src="">
    </div>
    <?php
    return ob_get_clean();
}

function fok_enqueue_frontend_assets() {
    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style('fok-frontend-style', plugin_dir_url( __FILE__ ) . 'assets/css/frontend-style.css', [], '1.6.14');
    wp_enqueue_script('fok-frontend-script', plugin_dir_url( __FILE__ ) . 'assets/js/frontend-script.js', ['jquery'], '1.6.14', true);
    wp_localize_script( 'fok-frontend-script', 'fok_ajax', ['ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'fok_viewer_nonce' )]);
}


// --- AJAX обробник ---
add_action( 'wp_ajax_fok_filter_apartments', 'fok_filter_apartments_ajax_handler' );
add_action( 'wp_ajax_nopriv_fok_filter_apartments', 'fok_filter_apartments_ajax_handler' );

function fok_filter_apartments_ajax_handler() {
    check_ajax_referer( 'fok_viewer_nonce', 'nonce' );

    parse_str(implode('&', array_map(function($item) { return $item['name'] . '=' . urlencode($item['value']); }, $_POST['form_data'])), $form_data);

    $args = ['post_type' => 'apartment', 'posts_per_page' => -1];
    $meta_query = ['relation' => 'AND'];
    $tax_query = ['relation' => 'AND'];

    if ( !empty($form_data['rc_id']) ) { $meta_query[] = ['key' => 'fok_apartment_rc_link', 'value' => intval($form_data['rc_id'])]; }
    if ( !empty($form_data['rooms']) ) { $compare = ($form_data['rooms'] === '3') ? '>=' : '='; $meta_query[] = ['key' => 'fok_apartment_rooms', 'value' => intval($form_data['rooms']), 'type' => 'NUMERIC', 'compare' => $compare]; }
    if ( !empty($form_data['area_from']) ) { $meta_query[] = ['key' => 'fok_apartment_area', 'value' => floatval($form_data['area_from']), 'type' => 'DECIMAL(10,2)', 'compare' => '>=']; }
    if ( !empty($form_data['area_to']) ) { $meta_query[] = ['key' => 'fok_apartment_area', 'value' => floatval($form_data['area_to']), 'type' => 'DECIMAL(10,2)', 'compare' => '<=']; }
    if ( !empty($form_data['floor_from']) ) { $meta_query[] = ['key' => 'fok_apartment_floor', 'value' => intval($form_data['floor_from']), 'type' => 'NUMERIC', 'compare' => '>=']; }
    if ( !empty($form_data['floor_to']) ) { $meta_query[] = ['key' => 'fok_apartment_floor', 'value' => intval($form_data['floor_to']), 'type' => 'NUMERIC', 'compare' => '<=']; }
    if (count($meta_query) > 1) { $args['meta_query'] = $meta_query; }
    if ( !empty($form_data['status']) ) { $tax_query[] = ['taxonomy' => 'status', 'field' => 'slug', 'terms' => sanitize_text_field($form_data['status'])]; $args['tax_query'] = $tax_query; }
    
    $query = new WP_Query($args);
    
    $sections_data = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $apartment_id = get_the_ID();
            $section_id = get_post_meta($apartment_id, 'fok_apartment_section_link', true);
            $floor = (int)get_post_meta($apartment_id, 'fok_apartment_floor', true);
            
            if (!$section_id || $floor === 0) continue;

            if (!isset($sections_data[$section_id])) {
                $section_post = get_post($section_id);
                $sections_data[$section_id] = ['name' => $section_post ? $section_post->post_title : __('Невідома секція', 'okbi-apartments'), 'floors' => []];
            }
            $status_terms = get_the_terms($apartment_id, 'status');
            $status_slug = !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown';
            
            $sections_data[$section_id]['floors'][$floor][] = [
                'id' => $apartment_id,
                'rooms' => (int)get_post_meta($apartment_id, 'fok_apartment_rooms', true),
                'area' => get_post_meta($apartment_id, 'fok_apartment_area', true),
                'status' => $status_slug
            ];
        }
    }
    wp_reset_postdata();

    ob_start();
    if (!empty($sections_data)) {
        echo '<div class="fok-chessboard">';
        foreach ($sections_data as $section_id => $section) {
            if (empty($section['floors'])) continue;
            
            ksort($section['floors'], SORT_NUMERIC);
            $floor_numbers = array_keys($section['floors']);
            $min_floor = min($floor_numbers);
            $max_floor = max($floor_numbers);
            
            echo '<div class="fok-section-block">';
            echo '<h4>' . esc_html($section['name']) . '</h4>';
            echo '<div class="fok-section-grid">';
            
            echo '<div class="fok-floor-labels">';
            for ($f = $max_floor; $f >= $min_floor; $f--) { echo '<div class="fok-floor-label">' . $f . '</div>'; }
            echo '</div>';
            
            echo '<div class="fok-floor-row-container">';
                for ($f = $max_floor; $f >= $min_floor; $f--) {
                    echo '<div class="fok-floor-row">';
                    if (isset($section['floors'][$f])) {
                        usort($section['floors'][$f], function($a, $b) { return $a['rooms'] <=> $b['rooms']; });
                        foreach ($section['floors'][$f] as $apartment) {
                            echo '<div class="fok-apartment-cell" data-id="' . esc_attr($apartment['id']) . '">';
                            echo '<span class="fok-cell-area">' . esc_html($apartment['area']) . ' м&sup2;</span>';
                            echo '<span class="fok-cell-rooms status-' . esc_attr($apartment['status']) . '">' . esc_html($apartment['rooms']) . '</span>';
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                }
            echo '</div>';
            
            echo '</div>'; 
            echo '</div>'; 
        }
        echo '</div>';
    } else {
        echo '<p>' . __('Квартир за вашими критеріями не знайдено.', 'okbi-apartments') . '</p>';
    }
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

// --- БЕКЕНД ЛОГІКА ---

function fok_generate_unique_id_on_save( $post_id ) { if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; if ( ! current_user_can( 'edit_post', $post_id ) ) return; if ( 'apartment' !== get_post_type($post_id) ) return; $unique_id = get_post_meta( $post_id, 'fok_apartment_unique_id', true ); if ( empty( $unique_id ) ) { $new_unique_id = 'manual-' . uniqid(); update_post_meta( $post_id, 'fok_apartment_unique_id', $new_unique_id ); } }
add_action( 'save_post_apartment', 'fok_generate_unique_id_on_save' );

function fok_check_meta_box_dependency() { if ( ! function_exists( 'is_plugin_active' ) ) { include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); } if ( !is_plugin_active( 'meta-box/meta-box.php' ) && !is_plugin_active( 'meta-box-aio/meta-box-aio.php' ) ) { add_action( 'admin_notices', 'fok_meta_box_not_found_notice' ); } }
add_action( 'admin_init', 'fok_check_meta_box_dependency' );
function fok_meta_box_not_found_notice() { echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post( '<strong>Плагін "Flat Okbi":</strong> Для повноцінної роботи необхідно встановити та активувати безкоштовний плагін <a href="https://wordpress.org/plugins/meta-box/" target="_blank">MetaBox</a>.' ) . '</p></div>'; }
function fok_register_post_types() { $cpt_args = [ 'public' => true, 'has_archive' => true, 'show_in_rest' => true ]; register_post_type('residential_complex', array_merge($cpt_args, ['labels' => ['name' => __('Житлові комплекси', 'okbi-apartments'), 'singular_name' => __('Житловий комплекс', 'okbi-apartments'), 'add_new_item' => __('Додати новий ЖК', 'okbi-apartments')], 'rewrite' => ['slug' => 'residential-complexes'], 'supports' => ['title', 'editor', 'thumbnail', 'excerpt'], 'menu_icon' => 'dashicons-building'])); register_post_type('section', array_merge($cpt_args, ['labels' => ['name' => __('Секції', 'okbi-apartments'), 'singular_name' => __('Секція', 'okbi-apartments'), 'add_new_item' => __('Додати нову секцію', 'okbi-apartments')], 'rewrite' => ['slug' => 'sections'], 'supports' => ['title', 'editor', 'thumbnail'], 'menu_icon' => 'dashicons-layout'])); register_post_type('apartment', array_merge($cpt_args, ['labels' => ['name' => __('Квартири', 'okbi-apartments'), 'singular_name' => __('Квартира', 'okbi-apartments'), 'add_new_item' => __('Додати нову квартиру', 'okbi-apartments')], 'rewrite' => ['slug' => 'apartments'], 'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'], 'menu_icon' => 'dashicons-admin-home'])); }
add_action( 'init', 'fok_register_post_types' );
function fok_register_taxonomies() { register_taxonomy('status', 'apartment', ['labels' => ['name' => __('Статуси', 'okbi-apartments'), 'singular_name' => __('Статус', 'okbi-apartments')], 'public' => true, 'hierarchical' => true, 'show_admin_column' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'status']]); }
add_action( 'init', 'fok_register_taxonomies' );
function fok_disable_gutenberg( $current_status, $post_type ) { if ( in_array( $post_type, array( 'residential_complex', 'section', 'apartment' ), true ) ) { return false; } return $current_status; }
add_filter( 'use_block_editor_for_post_type', 'fok_disable_gutenberg', 10, 2 );
function fok_remove_default_status_metabox() { remove_meta_box( 'tagsdiv-status', 'apartment', 'side' ); remove_meta_box( 'statusdiv', 'apartment', 'side' ); }
add_action( 'admin_menu', 'fok_remove_default_status_metabox' );
function fok_add_admin_list_filters() { global $typenow; if ( $typenow === 'section' || $typenow === 'apartment' ) { $complexes = get_posts(['post_type' => 'residential_complex', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']); $current_filter = isset( $_GET['fok_rc_filter'] ) ? (int) $_GET['fok_rc_filter'] : 0; echo '<select name="fok_rc_filter"><option value="">' . __( 'Всі ЖК', 'okbi-apartments' ) . '</option>'; if ( ! empty( $complexes ) ) { foreach ( $complexes as $complex ) { printf('<option value="%d" %s>%s</option>', esc_attr( $complex->ID ), selected( $current_filter, $complex->ID, false ), esc_html( $complex->post_title )); } } echo '</select>'; } if ( $typenow === 'apartment' ) { $statuses = get_terms(['taxonomy' => 'status', 'hide_empty' => false]); $current_status = isset( $_GET['fok_status_filter'] ) ? $_GET['fok_status_filter'] : ''; echo '<select name="fok_status_filter"><option value="">' . __( 'Всі статуси', 'okbi-apartments' ) . '</option>'; if ( ! empty( $statuses ) ) { foreach ( $statuses as $status ) { printf('<option value="%s" %s>%s</option>', esc_attr( $status->slug ), selected( $current_status, $status->slug, false ), esc_html( $status->name )); } } echo '</select>'; } }
add_action( 'restrict_manage_posts', 'fok_add_admin_list_filters' );
function fok_filter_admin_list_query( $query ) { global $pagenow, $typenow; if ( $pagenow === 'edit.php' && $query->is_main_query() ) { if ( isset( $_GET['fok_rc_filter'] ) && (int) $_GET['fok_rc_filter'] > 0 ) { $rc_id = (int) $_GET['fok_rc_filter']; $meta_key = ( $typenow === 'section' ) ? 'fok_section_rc_link' : 'fok_apartment_rc_link'; $meta_query = $query->get( 'meta_query' ) ?: array(); $meta_query[] = ['key' => $meta_key, 'value' => $rc_id, 'compare' => '=']; $query->set( 'meta_query', $meta_query ); } if ( $typenow === 'apartment' && isset( $_GET['fok_status_filter'] ) && ! empty( $_GET['fok_status_filter'] ) ) { $status_slug = sanitize_text_field( $_GET['fok_status_filter'] ); $tax_query = $query->get( 'tax_query' ) ?: array(); $tax_query[] = ['taxonomy' => 'status', 'field' => 'slug', 'terms' => $status_slug]; $query->set( 'tax_query', $tax_query ); } } }
add_action( 'parse_query', 'fok_filter_admin_list_query' );
function fok_plugin_activate() { fok_register_post_types(); fok_register_taxonomies(); fok_insert_initial_terms(); flush_rewrite_rules(); }
register_activation_hook( __FILE__, 'fok_plugin_activate' );
function fok_insert_initial_terms() { $statuses = [ 'Вільно' => 'vilno', 'Продано' => 'prodano', 'Заброньовано' => 'zabronovano' ]; foreach ( $statuses as $name => $slug ) { if ( !term_exists( $slug, 'status' ) ) { wp_insert_term( $name, 'status', ['slug' => $slug] ); } } }
function fok_add_settings_page() { add_menu_page(__( 'Налаштування Flat Okbi', 'okbi-apartments' ), 'Flat Okbi', 'manage_options', 'flat_okbi_settings', 'fok_render_settings_page', 'dashicons-admin-settings', 20); add_submenu_page('flat_okbi_settings', __( 'Імпорт/Експорт Квартир', 'okbi-apartments' ), __( 'Імпорт/Експорт', 'okbi-apartments' ), 'manage_options', 'flat_okbi_import', 'fok_render_importer_page'); }
add_action( 'admin_menu', 'fok_add_settings_page' );
function fok_render_settings_page() { ?> <div class="wrap"> <h1><?php echo esc_html( get_admin_page_title() ); ?></h1> <form action="options.php" method="post"> <?php settings_fields( 'fok_global_settings_group' ); do_settings_sections( 'flat_okbi_settings' ); submit_button( __( 'Зберегти налаштування', 'okbi-apartments' ) ); ?> </form> </div> <?php }
function fok_register_settings() { register_setting('fok_global_settings_group', 'fok_global_settings', 'fok_sanitize_settings'); add_settings_section('fok_main_settings_section', __( 'Основні налаштування', 'okbi-apartments' ), null, 'flat_okbi_settings'); add_settings_field('fok_logo_id', __('Логотип', 'okbi-apartments'), 'fok_render_logo_field', 'flat_okbi_settings', 'fok_main_settings_section'); add_settings_field('fok_accent_color', __('Акцентний колір', 'okbi-apartments'), 'fok_render_accent_color_field', 'flat_okbi_settings', 'fok_main_settings_section'); }
add_action( 'admin_init', 'fok_register_settings' );
function fok_render_logo_field() { $options = get_option( 'fok_global_settings' ); $logo_id = $options['logo_id'] ?? ''; $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : ''; echo '<div class="fok-image-uploader"><img src="' . esc_url( $logo_url ) . '" style="max-width: 200px; height: auto; border: 1px solid #ccc; padding: 5px; margin-bottom: 10px; display: ' . ($logo_id ? 'block' : 'none') . ';" /><input type="hidden" name="fok_global_settings[logo_id]" value="' . esc_attr( $logo_id ) . '" /><button type="button" class="button fok-upload-button">' . __( 'Завантажити/Вибрати лого', 'okbi-apartments' ) . '</button><button type="button" class="button fok-remove-button" style="display: ' . ($logo_id ? 'inline-block' : 'none') . ';">' . __( 'Видалити', 'okbi-apartments' ) . '</button></div>'; }
function fok_render_accent_color_field() { $options = get_option( 'fok_global_settings' ); $color = $options['accent_color'] ?? '#0073aa'; echo '<input type="text" name="fok_global_settings[accent_color]" value="' . esc_attr( $color ) . '" class="fok-color-picker" />'; }
function fok_enqueue_admin_scripts( $hook ) { $current_screen = get_current_screen(); if ( 'toplevel_page_flat_okbi_settings' === $current_screen->base || 'flat-okbi_page_flat_okbi_import' === $current_screen->base ) { wp_enqueue_style( 'wp-color-picker' ); wp_enqueue_media(); wp_enqueue_script('fok-admin-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js', array( 'wp-color-picker', 'jquery' ), '1.2.0', true); } }
add_action( 'admin_enqueue_scripts', 'fok_enqueue_admin_scripts' );
function fok_sanitize_settings( $input ) { $new_input = []; if ( isset( $input['logo_id'] ) ) { $new_input['logo_id'] = absint( $input['logo_id'] ); } if ( isset( $input['accent_color'] ) ) { $new_input['accent_color'] = sanitize_hex_color( $input['accent_color'] ); } return $new_input; }

/**
 * AJAX-обробник для отримання даних про одну квартиру для бічної панелі.
 */
function fok_get_apartment_details_ajax_handler() {
    check_ajax_referer( 'fok_viewer_nonce', 'nonce' );

    if ( ! isset( $_POST['apartment_id'] ) ) {
        wp_send_json_error( 'Відсутній ID квартири.' );
    }
    $apartment_id = absint( $_POST['apartment_id'] );

    $apartment = get_post( $apartment_id );
    if ( ! $apartment || $apartment->post_type !== 'apartment' ) {
        wp_send_json_error( 'Квартиру не знайдено.' );
    }

    $status_terms = get_the_terms( $apartment_id, 'status' );
    $price_group = get_post_meta( $apartment_id, 'fok_apartment_price', true );
    $area = (float) get_post_meta( $apartment_id, 'fok_apartment_area', true );
    $price_per_m2 = isset($price_group['value']) ? (float)$price_group['value'] : 0;
    $apartment_number = get_post_meta( $apartment_id, 'fok_apartment_number', true );
    $total_price = $area * $price_per_m2;
    
    $data = [
        'id'            => $apartment_id,
        'apartment_number' => $apartment_number,
        'status_name'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->name : 'Не вказано',
        'status_slug'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown',
        'gallery'       => [],
        'params'        => [
            'Номер квартири' => $apartment_number,
            'Площа'        => $area . ' м²',
            'Поверх'       => get_post_meta( $apartment_id, 'fok_apartment_floor', true ),
            'К-сть кімнат' => get_post_meta( $apartment_id, 'fok_apartment_rooms', true ),
        ],
        'price_per_m2'  => number_format($price_per_m2, 0, '.', ' '),
        'total_price'   => number_format($total_price, 0, '.', ' '),
        'currency'      => $price_group['currency'] ?? 'UAH',
    ];

    $gallery = [];
    $image_ids = get_post_meta( $apartment_id, 'fok_apartment_layout_images', false );

    if ( !empty( $image_ids ) ) {
        foreach ( $image_ids as $image_id ) {
             $full_url = wp_get_attachment_image_url( (int)$image_id, 'large' );
             $thumb_url = wp_get_attachment_image_url( (int)$image_id, 'thumbnail' );
             if($full_url && $thumb_url){
                 $gallery[] = [
                    'full'  => $full_url,
                    'thumb' => $thumb_url,
                ];
             }
        }
    }
    $data['gallery'] = $gallery;

    wp_send_json_success( $data );
}
add_action( 'wp_ajax_fok_get_apartment_details', 'fok_get_apartment_details_ajax_handler' );
add_action( 'wp_ajax_nopriv_fok_get_apartment_details', 'fok_get_apartment_details_ajax_handler' );
