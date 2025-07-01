<?php
/**
 * Plugin Name:         Flat Okbi
 * Plugin URI:          https://okbi.pp.ua
 * Description:         Плагін для керування каталогом квартир та житлових комплексів.
 * Version:             2.2.5
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
require_once plugin_dir_path( __FILE__ ) . 'includes/pricing-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/documentation-page.php';


// --- Шорткод та фронтенд логіка ---
add_action( 'init', 'fok_register_shortcode' );
function fok_register_shortcode() {
    add_shortcode( 'okbi_viewer', 'fok_render_viewer_shortcode' );
}

function fok_render_viewer_shortcode() {
    static $is_shortcode_rendered = false;
    if ($is_shortcode_rendered) return '';
    $is_shortcode_rendered = true;

    fok_enqueue_frontend_assets();

    $options = get_option( 'fok_global_settings' );
    $logo_id = $options['logo_id'] ?? '';
    $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
    $accent_color = $options['accent_color'] ?? '#0073aa';

    ob_start();
    ?>
    <div id="fok-viewer-fullscreen-container" style="--fok-accent-color: <?php echo esc_attr($accent_color); ?>;">
        <button id="fok-viewer-close" title="<?php esc_attr_e('Закрити', 'okbi-apartments'); ?>">&times;</button>

        <header class="fok-viewer-header">
            <div class="fok-logo">
                <?php if ( $logo_url ) : ?>
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Логотип', 'okbi-apartments' ); ?>">
                <?php endif; ?>
            </div>
            <div id="fok-rc-title-wrapper">
                <h2 id="fok-current-rc-title"></h2>
            </div>
            <div class="fok-header-actions">
                 <button id="fok-mobile-filter-trigger" title="<?php esc_attr_e('Фільтри', 'okbi-apartments'); ?>">
                    <span class="dashicons dashicons-filter"></span>
                </button>
            </div>
        </header>

        <main class="fok-viewer-content">
            <div id="fok-list-mode" class="active">
                <div class="fok-list-container">
                    <aside class="fok-list-sidebar">
                        <button id="fok-sidebar-close">&times;</button>
                        <h3><?php _e('Параметри пошуку', 'okbi-apartments'); ?></h3>
                        <form id="fok-filters-form">
                            <div class="fok-filter-group" data-dependency="apartment">
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
                            <div class="fok-filter-group fok-toggle-filter">
                                <label class="fok-toggle-switch" for="filter-status-toggle">
                                    <input type="checkbox" id="filter-status-toggle" name="status" value="vilno">
                                    <span class="fok-toggle-slider"></span>
                                    <span class="fok-toggle-label"><?php _e('Тільки вільні', 'okbi-apartments'); ?></span>
                                </label>
                            </div>
                            <div class="fok-filter-group fok-filter-property-types">
                                <label><?php _e('Тип нерухомості', 'okbi-apartments'); ?></label>
                                <div class="fok-checkbox-group">
                                    <label><input type="checkbox" name="property_types[]" value="apartment" checked> <?php _e('Квартири', 'okbi-apartments'); ?></label>
                                    <label><input type="checkbox" name="property_types[]" value="commercial_property" checked> <?php _e('Комерція', 'okbi-apartments'); ?></label>
                                    <label><input type="checkbox" name="property_types[]" value="storeroom" checked> <?php _e('Комори', 'okbi-apartments'); ?></label>
                                </div>
                            </div>
                        </form>
                    </aside>
                    <div class="fok-list-results" id="fok-results-container">
                         <div class="fok-loader"><div class="spinner"></div></div>
                         <div class="fok-list-content"></div>
                    </div>
                    <aside id="fok-details-panel">
                        <button id="fok-panel-close">&times;</button>
                        <div class="fok-panel-loader" style="display: none;"><div class="spinner"></div></div>
                        <div id="fok-panel-content"></div>
                    </aside>
                </div>
            </div>
        </main>
    </div>
    <div id="fok-lightbox" class="fok-lightbox-overlay">
        <button id="fok-lightbox-close" class="fok-lightbox-control">&times;</button>
        <button id="fok-lightbox-prev" class="fok-lightbox-control fok-lightbox-nav">&lt;</button>
        <img class="fok-lightbox-content" src="">
        <button id="fok-lightbox-next" class="fok-lightbox-control fok-lightbox-nav">&gt;</button>
    </div>
    <?php
    return ob_get_clean();
}

function fok_enqueue_frontend_assets() {
    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style('fok-frontend-style', plugin_dir_url( __FILE__ ) . 'assets/css/frontend-style.css', [], time()); // Використовуємо версію

    $options = get_option('fok_global_settings');
    $site_key = $options['recaptcha_site_key'] ?? '';

    if (!empty($site_key)) {
        wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key), [], null, true);
    }

    wp_enqueue_script('fok-frontend-script', plugin_dir_url( __FILE__ ) . 'assets/js/frontend-script.js', ['jquery'], time(), true); // Використовуємо версію

    // === ПОЧАТОК ЗМІН: Локалізація для фронтенду ===
    wp_localize_script( 'fok-frontend-script', 'fok_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'fok_viewer_nonce' ),
        'recaptcha_site_key' => $site_key
    ]);

    // Додаємо окремий об'єкт для перекладів
    wp_localize_script( 'fok-frontend-script', 'fok_i10n', [
        'error_try_again' => __( 'Сталася помилка. Спробуйте ще раз.', 'okbi-apartments' ),
        'server_error' => __( 'Помилка сервера.', 'okbi-apartments' ),
        'no_properties_in_rc' => __( 'Для цього ЖК ще не додано об\'єктів.', 'okbi-apartments' ),
        'plan_not_loaded' => __( 'На жаль, план для цього поверху ще не завантажено.', 'okbi-apartments' ),
        'data_loading_error' => __( 'Помилка завантаження даних.', 'okbi-apartments' ),
        'connection_error' => __( 'Помилка зв\'язку з сервером.', 'okbi-apartments' ),
        'section_or_floor_undefined' => __( 'Не вдалося визначити секцію або поверх для цього об\'єкта.', 'okbi-apartments' ),
        'sending' => __( 'Надсилаємо...', 'okbi-apartments' ),
        'send_request' => __( 'Надіслати заявку', 'okbi-apartments' ),
        'connection_error_short' => __( 'Помилка зв\'язку.', 'okbi-apartments' ),
    ]);
    // === КІНЕЦЬ ЗМІН ===
}


// --- AJAX обробники ---
add_action( 'wp_ajax_fok_filter_properties', 'fok_filter_properties_ajax_handler' );
add_action( 'wp_ajax_nopriv_fok_filter_properties', 'fok_filter_properties_ajax_handler' );

function fok_filter_properties_ajax_handler() {
    check_ajax_referer('fok_viewer_nonce', 'nonce');

    $rc_id = isset($_POST['rc_id']) ? intval($_POST['rc_id']) : 0;
    if (!$rc_id) {
        wp_send_json_error('ID житлового комплексу не вказано.');
    }

    $rc_post = get_post($rc_id);
    $rc_title = $rc_post ? $rc_post->post_title : '';

    $sections_query = new WP_Query([
        'post_type' => 'section', 'posts_per_page' => -1,
        'meta_query' => [['key' => 'fok_section_rc_link', 'value' => $rc_id]],
        'orderby' => 'title', 'order' => 'ASC',
    ]);

    $sections_data = [];
    if ($sections_query->have_posts()) {
        while ($sections_query->have_posts()) {
            $sections_query->the_post();
            $section_id = get_the_ID();
            
            $floors_data_json = get_post_meta($section_id, 'fok_section_floors_data', true);
            $floor_plans = json_decode($floors_data_json, true);

            if (is_array($floor_plans)) {
                foreach ($floor_plans as $index => $plan) {
                    if (!empty($plan['image'])) {
                        $image_url = wp_get_attachment_image_url((int)$plan['image'], 'large');
                        $floor_plans[$index]['image'] = $image_url ?: '';
                    }
                }
            } else {
                $floor_plans = [];
            }
            
            $sections_data[$section_id] = [
                'id' => $section_id, 'name' => get_the_title(),
                'grid_columns' => (int)get_post_meta($section_id, 'fok_section_grid_columns', true),
                'properties' => [],
                'floor_plans' => $floor_plans,
            ];
        }
    }
    wp_reset_postdata();

    // Запит для отримання ВСІХ об'єктів, як і раніше
    $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    $properties_query = new WP_Query([
        'post_type' => $property_types, 'posts_per_page' => -1,
        'meta_query' => [['key' => 'fok_property_rc_link', 'value' => $rc_id]],
    ]);
    
    $properties_by_section = [];
    if ($properties_query->have_posts()) {
        while ($properties_query->have_posts()) {
            $properties_query->the_post();
            $section_id = get_post_meta(get_the_ID(), 'fok_property_section_link', true);
            if ($section_id && isset($sections_data[$section_id])) {
                $properties_by_section[$section_id][] = get_post(get_the_ID());
            }
        }
    }
    wp_reset_postdata();

    // Розподіл по секціях (цей код залишається таким самим)
    foreach ($properties_by_section as $section_id => $properties) {
        usort($properties, function($a, $b) {
            $floor_a = (int)get_post_meta($a->ID, 'fok_property_floor', true);
            $floor_b = (int)get_post_meta($b->ID, 'fok_property_floor', true);
            if ($floor_a !== $floor_b) return $floor_a <=> $floor_b;
            return strnatcmp(get_post_meta($a->ID, 'fok_property_number', true), get_post_meta($b->ID, 'fok_property_number', true));
        });
        $regular_items = [];
        $parking_items = [];
        foreach ($properties as $property) {
            if (get_post_type($property->ID) === 'parking_space') {
                $parking_items[] = $property;
            } else {
                $regular_items[] = $property;
            }
        }
        $occupancy_map = [];
        $final_regular_properties = [];
        $manual_items = [];
        $auto_items = [];
        foreach ($regular_items as $property) {
            ( (int)get_post_meta($property->ID, 'fok_property_grid_column_start', true) > 0 ) ? $manual_items[] = $property : $auto_items[] = $property;
        }
        $sorted_regular_properties = array_merge($manual_items, $auto_items);
        foreach ($sorted_regular_properties as $property) {
            $property_id = $property->ID;
            $x_start = (int)get_post_meta($property_id, 'fok_property_grid_column_start', true);
            $y_start = (int)get_post_meta($property_id, 'fok_property_floor', true);
            $x_span = (int)get_post_meta($property_id, 'fok_property_grid_column_span', true) ?: 1;
            $y_span = (int)get_post_meta($property_id, 'fok_property_grid_row_span', true) ?: 1;
            if ($x_start <= 0) {
                $found_x = 1;
                while (true) {
                    $is_free = true;
                    for ($y = $y_start; $y < $y_start + $y_span; $y++) {
                        for ($x = $found_x; $x < $found_x + $x_span; $x++) {
                            if (!empty($occupancy_map[$y][$x])) {
                                $is_free = false; $found_x = $x + 1; break 2;
                            }
                        }
                    }
                    if ($is_free) { $x_start = $found_x; break; }
                }
            }
            for ($y = $y_start; $y < $y_start + $y_span; $y++) {
                for ($x = $x_start; $x < $x_start + $x_span; $x++) {
                    $occupancy_map[$y][$x] = $property_id;
                }
            }
            $status_terms = get_the_terms($property_id, 'status');
            $final_regular_properties[] = [
                'id' => $property_id, 'type' => get_post_type($property_id),
                'area' => (float)get_post_meta($property_id, 'fok_property_area', true),
                'floor' => $y_start,
                'status' => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown',
                'rooms' => (get_post_type($property_id) === 'apartment') ? (int)get_post_meta($property_id, 'fok_property_rooms', true) : 0,
                'has_discount' => (float)get_post_meta($property_id, 'fok_property_discount_percent', true) > 0,
                'grid_x_start' => $x_start, 'grid_y_start' => $y_start,
                'grid_x_span' => $x_span, 'grid_y_span' => $y_span,
            ];
        }
        
        $final_parking_items = [];
        $available_parking_count = 0;
        foreach ($parking_items as $spot) {
            $status_terms = get_the_terms($spot->ID, 'status');
            $status_slug = !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown';
            if ($status_slug === 'vilno') {
                $available_parking_count++;
            }
            $final_parking_items[] = [
                 'id' => $spot->ID, 'type' => 'parking_space',
                 'area' => (float)get_post_meta($spot->ID, 'fok_property_area', true),
                 'floor' => (int)get_post_meta($spot->ID, 'fok_property_floor', true),
                 'status' => $status_slug,
                 'property_number' => get_post_meta($spot->ID, 'fok_property_number', true),
                 'rooms' => 0, 
                 'has_discount' => (float)get_post_meta($spot->ID, 'fok_property_discount_percent', true) > 0,
                 'grid_x_start' => (int)get_post_meta($spot->ID, 'fok_property_grid_column_start', true),
                 'grid_y_start' => (int)get_post_meta($spot->ID, 'fok_property_floor', true),
                 'grid_x_span' => (int)get_post_meta($spot->ID, 'fok_property_grid_column_span', true) ?: 1,
                 'grid_y_span' => (int)get_post_meta($spot->ID, 'fok_property_grid_row_span', true) ?: 1,
            ];
        }

        $sections_data[$section_id]['properties'] = [
            'regular' => $final_regular_properties,
            'parking' => [
                'is_present' => count($final_parking_items) > 0,
                'total_count' => count($final_parking_items),
                'available_count' => $available_parking_count,
                'items' => $final_parking_items,
            ],
        ];
    }

    wp_send_json_success(['sections' => array_values($sections_data), 'rc_title' => $rc_title]);
}


add_action( 'wp_ajax_fok_get_property_details', 'fok_get_property_details_ajax_handler' );
add_action( 'wp_ajax_nopriv_fok_get_property_details', 'fok_get_property_details_ajax_handler' );

function fok_get_property_details_ajax_handler() {
    check_ajax_referer( 'fok_viewer_nonce', 'nonce' );

    if ( ! isset( $_POST['property_id'] ) ) {
        wp_send_json_error( 'Відсутній ID об\'єкта.' );
    }
    $property_id = absint( $_POST['property_id'] );
    $post_type = get_post_type($property_id);

    $section_id = get_post_meta($property_id, 'fok_property_section_link', true);
    $floor      = get_post_meta($property_id, 'fok_property_floor', true);

    $has_floor_plan = false;
    if ($section_id && $floor) {
        $floors_data_json = get_post_meta($section_id, 'fok_section_floors_data', true);
        if ($floors_data_json) {
            $floor_plans = json_decode($floors_data_json, true);
            if (is_array($floor_plans)) {
                foreach ($floor_plans as $plan) {
                    // Перевіряємо, чи існує план для цього поверху і чи є в ньому зображення
                    if (isset($plan['number']) && $plan['number'] == $floor && !empty($plan['image'])) {
                        $has_floor_plan = true;
                        break; // Знайшли, далі не шукаємо
                    }
                }
            }
        }
    }

    $allowed_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    if ( ! $post_type || !in_array($post_type, $allowed_types) ) {
        wp_send_json_error( 'Об\'єкт не знайдено.' );
    }

    $status_terms = get_the_terms( $property_id, 'status' );
    $area = (float) get_post_meta( $property_id, 'fok_property_area', true );
    $property_number = get_post_meta( $property_id, 'fok_property_number', true );
    $price_per_m2 = (float) get_post_meta( $property_id, 'fok_property_price_per_sqm', true );
    $manual_total_price = (float) get_post_meta( $property_id, 'fok_property_total_price_manual', true );
    $discount_percent = (float) get_post_meta( $property_id, 'fok_property_discount_percent', true );
    $currency = get_post_meta( $property_id, 'fok_property_currency', true );

    $base_total_price = 0;
    if ( $manual_total_price > 0 ) {
        $base_total_price = $manual_total_price;
    } else {
        $base_total_price = $area * $price_per_m2;
    }

    $final_price = $base_total_price;
    $has_discount = $discount_percent > 0 && $base_total_price > 0;
    if ( $has_discount ) {
        $final_price = $base_total_price * (1 - ($discount_percent / 100));
    }

    $type_names = [
        'apartment' => __('Квартира', 'okbi-apartments'),
        'commercial_property' => __('Комерційне приміщення', 'okbi-apartments'),
        'parking_space' => __('Паркомісце', 'okbi-apartments'),
        'storeroom' => __('Комора', 'okbi-apartments'),
    ];

    $data = [
        'id'            => $property_id,
        'type'          => $post_type,
        'type_name'     => $type_names[$post_type] ?? __('Нерухомість', 'okbi-apartments'),
        'property_number' => $property_number,
        'section_id'    => $section_id, // Змінено з get_post_meta, щоб використати змінну вище
        'status_name'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->name : 'Не вказано',
        'status_slug'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown',
        'gallery'       => [],
        'params'        => [
            'Номер' => $property_number,
            'Тип' => $type_names[$post_type],
            'Площа' => $area . ' м²',
            'Поверх' => $floor, // Змінено з get_post_meta
        ],
        'price_per_m2'    => number_format($price_per_m2, 0, '.', ' '),
        'total_price'     => number_format($final_price, 0, '.', ' '),
        'base_price'      => number_format($base_total_price, 0, '.', ' '),
        'currency'        => $currency ?: 'UAH',
        'has_discount'    => $has_discount,
        'discount_percent'=> $discount_percent,
        'has_floor_plan'  => $has_floor_plan, // ++ ДОДАЙТЕ ЦЕЙ РЯДОК ++
    ];

    if ($post_type === 'apartment') {
        $data['params']['К-сть кімнат'] = get_post_meta( $property_id, 'fok_property_rooms', true );
        $levels = (int) get_post_meta( $property_id, 'fok_property_grid_row_span', true ) ?: 1;
        if ($levels > 1) {
             $data['params']['Рівнів'] = $levels;
        }
    }

    $gallery = [];
    $image_ids = get_post_meta( $property_id, 'fok_property_layout_images', false );

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

add_action( 'wp_ajax_fok_submit_booking', 'fok_handle_booking_request' );
add_action( 'wp_ajax_nopriv_fok_submit_booking', 'fok_handle_booking_request' );

function fok_handle_booking_request() {
    check_ajax_referer( 'fok_viewer_nonce', 'nonce' );

    // --- ПОЧАТОК БЛОКУ ПЕРЕВІРКИ reCAPTCHA ---
    $options = get_option('fok_global_settings');
    $secret_key = $options['recaptcha_secret_key'] ?? '';

    // Якщо секретний ключ вказано, виконуємо перевірку
    if (!empty($secret_key)) {
        if (!isset($_POST['recaptcha_token']) || empty($_POST['recaptcha_token'])) {
            wp_send_json_error('Помилка перевірки. Будь ласка, спробуйте оновити сторінку.');
            return;
        }

        $token = sanitize_text_field($_POST['recaptcha_token']);
        
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret_key,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Не вдалося зв\'язатися з сервісом перевірки.');
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Перевіряємо, чи перевірка успішна і чи оцінка достатньо висока (0.5 - рекомендоване значення)
        if (!$response_body['success'] || $response_body['score'] < 0.5) {
            wp_send_json_error('Перевірка на робота не пройдена.');
            return;
        }
    }
    // --- КІНЕЦЬ БЛОКУ ПЕРЕВІРКИ reCAPTCHA ---

    $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
    $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
    
    if ( ! $property_id || empty( $name ) || empty( $phone ) ) {
        wp_send_json_error( 'Будь ласка, заповніть всі обов\'язкові поля.' );
    }
    
    $property = get_post( $property_id );
    $post_type = get_post_type($property_id);
    
    if ( ! $property || !in_array($post_type, ['apartment', 'commercial_property', 'parking_space', 'storeroom']) ) {
        wp_send_json_error( 'Об\'єкт не знайдено.' );
    }

    // ... (решта коду функції залишається без змін) ...

    $lead_title = "Заявка на '{$property->post_title}' від {$name}"; // Використовуємо $property->post_title замість get_the_title()
    $lead_content = "Ім'я клієнта: {$name}\nТелефон: {$phone}\n\nЗв'язатися для уточнення деталей.";
    
    $lead_id = wp_insert_post([
        'post_title'   => $lead_title,
        'post_content' => $lead_content,
        'post_type'    => 'fok_lead',
        'post_status'  => 'publish',
    ]);

    if ( is_wp_error($lead_id) ) {
        wp_send_json_error( 'Помилка системи. Не вдалося зберегти заявку.' );
    }

    update_post_meta($lead_id, '_lead_name', $name);
    update_post_meta($lead_id, '_lead_phone', $phone);
    update_post_meta($lead_id, '_lead_property_id', $property_id);
    update_post_meta($lead_id, '_lead_status', 'new');
    
    $rc_id = get_post_meta($property_id, 'fok_property_rc_link', true);
    $section_id = get_post_meta($property_id, 'fok_property_section_link', true);
    
    if ($rc_id) update_post_meta($lead_id, '_lead_rc_id', $rc_id);
    if ($section_id) update_post_meta($lead_id, '_lead_section_id', $section_id);

    $notification_email = !empty( $options['notification_email'] ) ? $options['notification_email'] : get_option( 'admin_email' );
    $subject = 'Нова заявка на об\'єкт з сайту: ' . get_bloginfo( 'name' );
    $domain = wp_parse_url(get_home_url(), PHP_URL_HOST);
    $from_email = 'no-reply@' . $domain;
    $from_name = get_bloginfo('name');
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>"
    ];
    $crm_link = admin_url( 'post.php?post=' . $lead_id . '&action=edit' );
    $property_number = get_post_meta( $property_id, 'fok_property_number', true );
    $property_floor = get_post_meta( $property_id, 'fok_property_floor', true );
    $jk_name = $rc_id ? get_the_title( $rc_id ) : 'Не вказано';
    $section_name = $section_id ? get_the_title( $section_id ) : 'Не вказано';
    $type_names = [
        'apartment' => __('Квартира', 'okbi-apartments'),
        'commercial_property' => __('Комерційне приміщення', 'okbi-apartments'),
        'parking_space' => __('Паркомісце', 'okbi-apartments'),
        'storeroom' => __('Комора', 'okbi-apartments'),
    ];
    $property_type_name = $type_names[$post_type] ?? ucfirst($post_type);
    $message  = "<p>Доброго дня!</p>";
    $message .= "<p>Ви отримали нову заявку на об'єкт нерухомості:</p>";
    $message .= "<ul style='list-style-type: none; padding-left: 0;'>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Ім'я клієнта:</strong> " . esc_html($name) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Телефон:</strong> " . esc_html($phone) . "</li>";
    $message .= "<hr style='border:0; border-top: 1px solid #eee; margin: 10px 0;'>";
    $message .= "<li style='margin-bottom: 5px;'><strong>ЖК:</strong> " . esc_html($jk_name) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Секція:</strong> " . esc_html($section_name) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Об'єкт №:</strong> " . esc_html($property_number) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Тип об'єкта:</strong> " . esc_html($property_type_name) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Поверх:</strong> " . esc_html($property_floor) . "</li>";
    $message .= "</ul>";
    
    if ($crm_link) {
        $message .= '<p style="margin-top: 20px;">';
        $message .= '<a href="' . esc_url($crm_link) . '" style="background-color: #0073aa; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">';
        $message .= 'Переглянути заявку в CRM';
        $message .= '</a>';
        $message .= '</p>';
    }

    $sent = wp_mail( $notification_email, $subject, $message, $headers );

    $tg_bot_token = $options['telegram_bot_token'] ?? '';
    $tg_chat_id = $options['telegram_chat_id'] ?? '';

    if ( !empty($tg_bot_token) && !empty($tg_chat_id) ) {
        // Формуємо текст повідомлення для Telegram (можна використовувати HTML-теги)
        $tg_message = "<b>🔥 Нова заявка з сайту!</b>\n\n";
        $tg_message .= "<b>Ім'я:</b> " . esc_html($name) . "\n";
        $tg_message .= "<b>Телефон:</b> " . esc_html($phone) . "\n\n";
        $tg_message .= "<b>Об'єкт:</b>\n";
        $tg_message .= "ЖК: " . esc_html($jk_name) . "\n";
        $tg_message .= "Секція: " . esc_html($section_name) . "\n";
        $tg_message .= "Тип: " . esc_html($property_type_name) . " №" . esc_html($property_number) . "\n";
        $tg_message .= "Поверх: " . esc_html($property_floor) . "\n\n";
        
        // Додаємо кнопку для переходу в CRM
        $tg_message .= "<a href='" . esc_url($crm_link) . "'>➡️ Переглянути заявку в CRM</a>";

        // Формуємо URL для запиту до Telegram API
        $tg_api_url = "https://api.telegram.org/bot{$tg_bot_token}/sendMessage";
        
        // Відправляємо запит
        wp_remote_post( $tg_api_url, [
            'body' => [
                'chat_id' => $tg_chat_id,
                'text' => $tg_message,
                'parse_mode' => 'HTML', // Вказуємо, що використовуємо HTML-розмітку
            ]
        ]);
    }

    if ( $sent ) {
        $status_term = get_term_by('slug', 'zabronovano', 'status');
        if ($status_term) {
            wp_set_object_terms($property_id, $status_term->term_id, 'status');
        }
        wp_send_json_success( 'Дякуємо! Ваша заявка прийнята.' );
    } else {
        wp_send_json_error( 'Дякуємо! Ваша заявка збережена, але сталася помилка при відправці сповіщення.' );
    }
}

// --- Адміністративна частина ---

// ++ НОВИЙ AJAX ОБРОБНИК: Видалення всіх даних ++
add_action( 'wp_ajax_fok_delete_all_data', 'fok_delete_all_data_handler' );
function fok_delete_all_data_handler() {
    // Перевірка безпеки
    check_ajax_referer( 'fok_delete_all_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'У вас недостатньо прав для виконання цієї дії.', 'okbi-apartments' ) ] );
    }

    $post_types_to_delete = [
        'residential_complex', 
        'section', 
        'apartment', 
        'commercial_property', 
        'parking_space', 
        'storeroom',
        'fok_lead'
    ];

    $query = new WP_Query([
        'post_type' => $post_types_to_delete,
        'posts_per_page' => -1,
        'fields' => 'ids', // Отримуємо тільки ID для ефективності
    ]);

    $deleted_count = 0;
    if ( $query->have_posts() ) {
        foreach ( $query->posts as $post_id ) {
            // Використовуємо 'true' для повного видалення, а не переміщення в кошик
            $result = wp_delete_post( $post_id, true ); 
            if ( $result !== false ) {
                $deleted_count++;
            }
        }
    }

    wp_send_json_success( [ 'message' => sprintf( __( 'Успішно видалено %d об\'єктів.', 'okbi-apartments' ), $deleted_count ) ] );
}


function fok_add_rc_trigger_code_metabox() {
    add_meta_box(
        'fok_rc_trigger_code',
        __( 'Код для запуску каталогу', 'okbi-apartments' ),
        'fok_render_rc_trigger_code_metabox_content',
        'residential_complex',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'fok_add_rc_trigger_code_metabox' );

function fok_render_rc_trigger_code_metabox_content($post) {
    ?>
    <p><?php _e('Щоб відкрити каталог для цього ЖК, додайте до будь-якої кнопки чи посилання на вашому сайті наступні атрибути:', 'okbi-apartments'); ?></p>
    <input
        type="text"
        readonly
        value="<?php echo esc_attr('class="fok-open-viewer" data-rc-id="' . $post->ID . '"'); ?>"
        style="width: 100%;"
        onfocus="this.select();"
    >
    <p class="description">
        <?php _e('Також не забудьте розмістити на цій же сторінці шорткод <code>[okbi_viewer]</code> (у будь-якому місці).', 'okbi-apartments'); ?>
    </p>
    <?php
}

function fok_generate_unique_id_on_save( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $allowed_post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    if ( !in_array(get_post_type($post_id), $allowed_post_types) ) return;

    $unique_id = get_post_meta( $post_id, 'fok_property_unique_id', true );
    if ( empty( $unique_id ) ) {
        $new_unique_id = 'manual-' . uniqid();
        update_post_meta( $post_id, 'fok_property_unique_id', $new_unique_id );
    }
}
add_action( 'save_post', 'fok_generate_unique_id_on_save', 10, 1 );

function fok_autogenerate_property_title_on_save( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $allowed_post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    $post_type = get_post_type($post_id);

    if ( !in_array($post_type, $allowed_post_types) ) return;

    $property_number = isset( $_POST['fok_property_number'] ) ? sanitize_text_field( $_POST['fok_property_number'] ) : '';

    if ( empty($property_number) ) {
        return;
    }

    $type_names_for_title = [
        'apartment'           => __('Квартира', 'okbi-apartments'),
        'commercial_property' => __('Комерція', 'okbi-apartments'),
        'parking_space'       => __('Паркомісце', 'okbi-apartments'),
        'storeroom'           => __('Комора', 'okbi-apartments'),
    ];

    $type_name = $type_names_for_title[$post_type] ?? __('Об\'єкт', 'okbi-apartments');
    $new_title = $type_name . ' №' . $property_number;
    $current_post = get_post($post_id);
    if ($current_post->post_title === $new_title) {
        return;
    }

    remove_action( 'save_post', 'fok_autogenerate_property_title_on_save', 20 );

    wp_update_post([
        'ID'         => $post_id,
        'post_title' => $new_title,
        'post_name'  => sanitize_title($new_title)
    ]);

    add_action( 'save_post', 'fok_autogenerate_property_title_on_save', 20, 1 );
}
add_action( 'save_post', 'fok_autogenerate_property_title_on_save', 20, 1 );

function fok_check_meta_box_dependency() { if ( ! function_exists( 'is_plugin_active' ) ) { include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); } if ( !is_plugin_active( 'meta-box/meta-box.php' ) && !is_plugin_active( 'meta-box-aio/meta-box-aio.php' ) ) { add_action( 'admin_notices', 'fok_meta_box_not_found_notice' ); } }
add_action( 'admin_init', 'fok_check_meta_box_dependency' );

function fok_meta_box_not_found_notice() { echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post( '<strong>Плагін "Flat Okbi":</strong> Для повноцінної роботи необхідно встановити та активувати безкоштовний плагін <a href="https://wordpress.org/plugins/meta-box/" target="_blank">MetaBox</a>.' ) . '</p></div>'; }

function fok_register_post_types() {
    $cpt_args = [
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => ['title', 'thumbnail', 'custom-fields'],
        'show_in_menu' => 'flat_okbi_settings',
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
        'public'        => false, // Робимо їх непублічними, щоб не були доступні на сайті
        'show_ui'       => true,  // Але показуємо в адмін-панелі
        'show_in_menu' => true,
        'menu_icon'     => 'dashicons-id-alt',
        'supports'      => ['title'], // Залишаємо підтримку тільки заголовка
        'capability_type' => 'post',
        // Забороняємо користувачам створювати заявки вручну через адмін-панель
        'capabilities' => [
            'create_posts' => 'do_not_allow', 
        ],
        'map_meta_cap' => true, // Необхідно для коректної роботи 'create_posts'
    ]);
}
add_action( 'init', 'fok_register_post_types' );

function fok_register_taxonomies() {
    $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    register_taxonomy('status', $property_types, ['labels' => ['name' => __('Статуси', 'okbi-apartments'), 'singular_name' => __('Статус', 'okbi-apartments')], 'public' => true, 'hierarchical' => true, 'show_admin_column' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'status']]);
}
add_action( 'init', 'fok_register_taxonomies' );

function fok_disable_gutenberg( $current_status, $post_type ) {
    $cpts_to_disable = ['residential_complex', 'section', 'apartment', 'commercial_property', 'parking_space', 'storeroom'];
    if ( in_array( $post_type, $cpts_to_disable, true ) ) { return false; } return $current_status;
}
add_filter( 'use_block_editor_for_post_type', 'fok_disable_gutenberg', 10, 2 );

function fok_remove_default_status_metabox() {
    $post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    foreach($post_types as $post_type) {
        remove_meta_box( 'tagsdiv-status', $post_type, 'side' );
        remove_meta_box( 'statusdiv', $post_type, 'side' );
    }
}
add_action( 'admin_menu', 'fok_remove_default_status_metabox' );

function fok_add_admin_list_filters() {
    global $typenow;
    $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];

    if ( $typenow === 'section' || in_array($typenow, $property_types) ) {
        $complexes = get_posts(['post_type' => 'residential_complex', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $current_filter = isset( $_GET['fok_rc_filter'] ) ? (int) $_GET['fok_rc_filter'] : 0;
        echo '<select name="fok_rc_filter"><option value="">' . __( 'Всі ЖК', 'okbi-apartments' ) . '</option>';
        if ( ! empty( $complexes ) ) {
            foreach ( $complexes as $complex ) {
                printf('<option value="%d" %s>%s</option>', esc_attr( $complex->ID ), selected( $current_filter, $complex->ID, false ), esc_html( $complex->post_title ));
            }
        }
        echo '</select>';
    }
    if ( in_array($typenow, $property_types) ) {
        $statuses = get_terms(['taxonomy' => 'status', 'hide_empty' => false]);
        $current_status = isset( $_GET['fok_status_filter'] ) ? $_GET['fok_status_filter'] : '';
        echo '<select name="fok_status_filter"><option value="">' . __( 'Всі статуси', 'okbi-apartments' ) . '</option>';
        if ( ! empty( $statuses ) ) {
            foreach ( $statuses as $status ) {
                printf('<option value="%s" %s>%s</option>', esc_attr( $status->slug ), selected( $current_status, $status->slug, false ), esc_html( $status->name ));
            }
        }
        echo '</select>';
    }
}
add_action( 'restrict_manage_posts', 'fok_add_admin_list_filters' );

function fok_filter_admin_list_query( $query ) {
    global $pagenow, $typenow;
    if ( $pagenow === 'edit.php' && $query->is_main_query() ) {
        if ( isset( $_GET['fok_rc_filter'] ) && (int) $_GET['fok_rc_filter'] > 0 ) {
            $rc_id = (int) $_GET['fok_rc_filter'];
            $meta_key = ( $typenow === 'section' ) ? 'fok_section_rc_link' : 'fok_property_rc_link';
            $meta_query = $query->get( 'meta_query' ) ?: array();
            $meta_query[] = ['key' => $meta_key, 'value' => $rc_id, 'compare' => '='];
            $query->set( 'meta_query', $meta_query );
        }

        $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
        if ( in_array($typenow, $property_types) && isset( $_GET['fok_status_filter'] ) && ! empty( $_GET['fok_status_filter'] ) ) {
            $status_slug = sanitize_text_field( $_GET['fok_status_filter'] );
            $tax_query = $query->get( 'tax_query' ) ?: array();
            $tax_query[] = ['taxonomy' => 'status', 'field' => 'slug', 'terms' => $status_slug];
            $query->set( 'tax_query', $tax_query );
        }
    }
}
add_action( 'parse_query', 'fok_filter_admin_list_query' );

function fok_plugin_activate() { fok_register_post_types(); fok_register_taxonomies(); fok_insert_initial_terms(); flush_rewrite_rules(); }
register_activation_hook( __FILE__, 'fok_plugin_activate' );

function fok_insert_initial_terms() { $statuses = [ 'Вільно' => 'vilno', 'Продано' => 'prodano', 'Заброньовано' => 'zabronovano' ]; foreach ( $statuses as $name => $slug ) { if ( !term_exists( $slug, 'status' ) ) { wp_insert_term( $name, 'status', ['slug' => $slug] ); } } }

function fok_add_settings_page() {
    add_menu_page(
        __( 'Каталог Flat Okbi', 'okbi-apartments' ),
        'Flat Okbi', 'manage_options', 'flat_okbi_settings',
        'fok_render_settings_page', 'dashicons-building', 20
    );

    add_submenu_page(
        'flat_okbi_settings',
        __( 'Налаштування', 'okbi-apartments' ),
        __( 'Налаштування', 'okbi-apartments' ),
        'manage_options', 'flat_okbi_settings', 'fok_render_settings_page'
    );

    add_submenu_page(
        'flat_okbi_settings',
        __( 'Керування цінами', 'okbi-apartments' ),
        __( 'Керування цінами', 'okbi-apartments' ),
        'manage_options', 
        'flat_okbi_pricing', // Slug нової сторінки
        'fok_render_pricing_page' // Функція для рендерингу сторінки
    );

    add_submenu_page(
        'flat_okbi_settings',
        __( 'Імпорт/Експорт', 'okbi-apartments' ),
        __( 'Імпорт/Експорт', 'okbi-apartments' ),
        'manage_options', 'flat_okbi_import', 'fok_render_importer_page'
    );
    add_submenu_page(
        'flat_okbi_settings',
        __( 'Документація', 'okbi-apartments' ),
        __( 'Документація', 'okbi-apartments' ),
        'manage_options',
        'flat_okbi_docs', // Slug нової сторінки
        'fok_render_documentation_page' // Функція для рендерингу сторінки
    );
}
add_action( 'admin_menu', 'fok_add_settings_page' );

function fok_render_settings_page() { ?> <div class="wrap"> <h1><?php echo esc_html( get_admin_page_title() ); ?></h1> <form action="options.php" method="post"> <?php settings_fields( 'fok_global_settings_group' ); do_settings_sections( 'flat_okbi_settings' ); submit_button( __( 'Зберегти налаштування', 'okbi-apartments' ) ); ?> </form> </div> <?php }

function fok_register_settings() {
    register_setting('fok_global_settings_group', 'fok_global_settings', 'fok_sanitize_settings');
    add_settings_section('fok_main_settings_section', __( 'Основні налаштування', 'okbi-apartments' ), null, 'flat_okbi_settings');
    add_settings_field('fok_logo_id', __('Логотип', 'okbi-apartments'), 'fok_render_logo_field', 'flat_okbi_settings', 'fok_main_settings_section');
    add_settings_field('fok_accent_color', __('Акцентний колір', 'okbi-apartments'), 'fok_render_accent_color_field', 'flat_okbi_settings', 'fok_main_settings_section');
    add_settings_field('fok_notification_email', __('Email для сповіщень', 'okbi-apartments'), 'fok_render_notification_email_field', 'flat_okbi_settings', 'fok_main_settings_section');
    add_settings_section('fok_telegram_settings_section', __( 'Сповіщення в Telegram', 'okbi-apartments' ), 'fok_render_telegram_description', 'flat_okbi_settings');
    add_settings_field('fok_telegram_bot_token', __('Токен Telegram-бота', 'okbi-apartments'), 'fok_render_telegram_bot_token_field', 'flat_okbi_settings', 'fok_telegram_settings_section');
    add_settings_field('fok_telegram_chat_id', __('ID чату для сповіщень', 'okbi-apartments'), 'fok_render_telegram_chat_id_field', 'flat_okbi_settings', 'fok_telegram_settings_section');
    add_settings_section('fok_recaptcha_settings_section', __( 'Налаштування Google reCAPTCHA v3', 'okbi-apartments' ), 'fok_render_recaptcha_description', 'flat_okbi_settings');
    add_settings_field('fok_recaptcha_site_key', __('Ключ сайту (Site Key)', 'okbi-apartments'), 'fok_render_recaptcha_site_key_field', 'flat_okbi_settings', 'fok_recaptcha_settings_section');
    add_settings_field('fok_recaptcha_secret_key', __('Секретний ключ (Secret Key)', 'okbi-apartments'), 'fok_render_recaptcha_secret_key_field', 'flat_okbi_settings', 'fok_recaptcha_settings_section');
}
add_action( 'admin_init', 'fok_register_settings' );

function fok_render_logo_field() { $options = get_option( 'fok_global_settings' ); $logo_id = $options['logo_id'] ?? ''; $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : ''; echo '<div class="fok-image-uploader"><img src="' . esc_url( $logo_url ) . '" style="max-width: 200px; height: auto; border: 1px solid #ccc; padding: 5px; margin-bottom: 10px; display: ' . ($logo_id ? 'block' : 'none') . ';" /><input type="hidden" name="fok_global_settings[logo_id]" value="' . esc_attr( $logo_id ) . '" /><button type="button" class="button fok-upload-button">' . __( 'Завантажити/Вибрати лого', 'okbi-apartments' ) . '</button><button type="button" class="button fok-remove-button" style="display: ' . ($logo_id ? 'inline-block' : 'none') . ';">' . __( 'Видалити', 'okbi-apartments' ) . '</button></div>'; }

function fok_render_accent_color_field() { $options = get_option( 'fok_global_settings' ); $color = $options['accent_color'] ?? '#0073aa'; echo '<input type="text" name="fok_global_settings[accent_color]" value="' . esc_attr( $color ) . '" class="fok-color-picker" />'; }

function fok_render_notification_email_field() {
    $options = get_option( 'fok_global_settings' );
    $email = $options['notification_email'] ?? '';
    echo '<input type="email" name="fok_global_settings[notification_email]" value="' . esc_attr( $email ) . '" class="regular-text" placeholder="' . esc_attr(get_option('admin_email')) . '" />';
    echo '<p class="description">' . __('Вкажіть email для отримання заявок. Якщо залишити порожнім, буде використано email адміністратора.', 'okbi-apartments') . '</p>';
}
function fok_render_recaptcha_description() {
    echo '<p>' . __( 'Додайте ключі Google reCAPTCHA v3 для захисту форми бронювання від спаму. Отримати ключі можна в <a href="https://www.google.com/recaptcha/admin/create" target="_blank">панелі адміністратора reCAPTCHA</a>.', 'okbi-apartments' ) . '</p>';
}

function fok_render_recaptcha_site_key_field() {
    $options = get_option( 'fok_global_settings' );
    $site_key = $options['recaptcha_site_key'] ?? '';
    echo '<input type="text" name="fok_global_settings[recaptcha_site_key]" value="' . esc_attr( $site_key ) . '" class="regular-text" />';
}

function fok_render_recaptcha_secret_key_field() {
    $options = get_option( 'fok_global_settings' );
    $secret_key = $options['recaptcha_secret_key'] ?? '';
    echo '<input type="text" name="fok_global_settings[recaptcha_secret_key]" value="' . esc_attr( $secret_key ) . '" class="regular-text" />';
}

function fok_render_telegram_description() {
    echo '<p>' . __( 'Налаштуйте відправку миттєвих сповіщень про нові заявки у ваш Telegram-чат.', 'okbi-apartments' ) . '</p>';
}

function fok_render_telegram_bot_token_field() {
    $options = get_option( 'fok_global_settings' );
    $bot_token = $options['telegram_bot_token'] ?? '';
    echo '<input type="text" name="fok_global_settings[telegram_bot_token]" value="' . esc_attr( $bot_token ) . '" class="regular-text" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" />';
    echo '<p class="description">' . __( 'Щоб отримати токен, створіть нового бота за допомогою <a href="https://t.me/BotFather" target="_blank">@BotFather</a>.', 'okbi-apartments' ) . '</p>';
}

function fok_render_telegram_chat_id_field() {
    $options = get_option( 'fok_global_settings' );
    $chat_id = $options['telegram_chat_id'] ?? '';
    echo '<input type="text" name="fok_global_settings[telegram_chat_id]" value="' . esc_attr( $chat_id ) . '" class="regular-text" placeholder="-100123456789" />';
    echo '<p class="description">' . __( 'Це ID вашого каналу або групи. Щоб його дізнатись, можна використати бота <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a> або аналогічного.', 'okbi-apartments' ) . '</p>';
}

function fok_enqueue_admin_scripts( $hook_suffix ) {
    // 1. Скрипти для сторінок налаштувань та імпорту
    if ( 'toplevel_page_flat_okbi_settings' === $hook_suffix ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_media();
        wp_enqueue_script('fok-admin-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js', array( 'wp-color-picker', 'jquery' ), time(), true);
    }

    if ( 'flat-okbi_page_flat_okbi_pricing' === $hook_suffix ) {
        wp_enqueue_style( 'fok-admin-pricing-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-pricing.css', [], time() );
        wp_enqueue_script( 'fok-admin-pricing-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-pricing.js', ['jquery'], time(), true );
        wp_localize_script( 'fok-admin-pricing-script', 'fok_pricing_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'fok_pricing_nonce' )
        ]);
        wp_localize_script( 'fok-admin-pricing-script', 'fok_pricing_i10n', [
            'loading' => __( 'Завантаження...', 'okbi-apartments' ),
            'saving' => __( 'Збереження...', 'okbi-apartments' ),
            'save_changes' => __( 'Зберегти зміни', 'okbi-apartments' ),
            'error_saving' => __( 'Помилка збереження.', 'okbi-apartments' ),
            'error_loading' => __( 'Помилка завантаження даних.', 'okbi-apartments' ),
        ]);
    }
    
    if ( 'flat-okbi_page_flat_okbi_import' === $hook_suffix ) {
        wp_enqueue_script('fok-admin-importer-script', plugin_dir_url(__FILE__) . 'assets/js/admin-importer.js', ['jquery'], time(), true);
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        wp_enqueue_script('fok-admin-settings-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js', ['jquery', 'wp-color-picker', 'wp-mediaelement'], time(), true);

        wp_localize_script('fok-admin-settings-script', 'fok_importer_page', [
            'nonce' => wp_create_nonce('fok_delete_all_nonce'),
            // === ПОЧАТОК ЗМІН: Локалізація для сторінки імпорту ===
            'confirm_delete' => __('Ви впевнені, що хочете видалити ВСІ житлові комплекси, секції, об\'єкти та заявки? Ця дія незворотна.', 'okbi-apartments'),
            'confirm_delete_final' => __('БУДЬ ЛАСКА, ПІДТВЕРДІТЬ. Всі дані плагіна буде видалено назавжди.', 'okbi-apartments'),
            'deleting' => __('Видалення...', 'okbi-apartments'),
            'error_prefix' => __('Помилка: ', 'okbi-apartments'),
            'server_error' => __('Помилка сервера.', 'okbi-apartments'),
            // === КІНЕЦЬ ЗМІН ===
        ]);
    }

    // ++ НОВИЙ БЛОК: Підключення стилів для сторінки документації ++
    if ( 'flat-okbi_page_flat_okbi_docs' === $hook_suffix ) {
        wp_enqueue_style( 'fok-admin-docs-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-docs.css', [], time() );
    }

    if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
        return;
    }

    $current_screen = get_current_screen();
    if ( ! isset( $current_screen->post_type ) ) {
        return;
    }

    // 2. Скрипти для сторінки "Секції"
    if ( 'section' === $current_screen->post_type ) {
        // Редактор сітки
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_style( 'fok-admin-grid-editor-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-grid-editor.css', [], time() );
        wp_enqueue_script( 'fok-admin-grid-editor-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-grid-editor.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'], time(), true );
        wp_localize_script( 'fok-admin-grid-editor-script', 'fok_editor_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'fok_grid_editor_nonce_action' )
        ]);
        // === ПОЧАТОК ЗМІН: Локалізація для редактора сітки ===
        wp_localize_script( 'fok-admin-grid-editor-script', 'fok_grid_i10n', [
            'error_prefix' => __('Помилка: ', 'okbi-apartments'),
            'unknown_error' => __('невідома помилка', 'okbi-apartments'),
            'server_error' => __('Помилка сервера.', 'okbi-apartments'),
            'all_objects_distributed' => __('Всі об\'єкти розподілені.', 'okbi-apartments'),
            'unsaved_changes' => __('Є незбережені зміни.', 'okbi-apartments'),
            'saving' => __('Збереження...', 'okbi-apartments'),
            'changes_saved_success' => __('Зміни успішно збережено.', 'okbi-apartments'),
            'resize_impossible' => __('Неможливо змінити розмір, місце зайняте іншим об\'єктом.', 'okbi-apartments'),
        ]);
        // === КІНЕЦЬ ЗМІН ===

        // Конструктор планів поверхів
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style( 'wp-jquery-ui-dialog' );
        wp_enqueue_style( 'fok-admin-floor-plan-editor-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-floor-plan-editor.css', [], time() );
        wp_enqueue_script( 'fok-admin-groups-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-groups.js', ['jquery', 'jquery-ui-sortable', 'jquery-ui-dialog'], time(), true );
        
        wp_localize_script('fok-admin-groups-script', 'fok_groups_data', [
            'nonce'      => wp_create_nonce('fok_floor_plans_nonce'),
            'post_id'    => get_the_ID(),
        ]);
         // === ПОЧАТОК ЗМІН: Локалізація для редактора планів поверхів ===
        wp_localize_script('fok-admin-groups-script', 'fok_groups_i10n', [
            'confirm_delete_floor' => __('Ви впевнені, що хочете видалити цей поверх?', 'okbi-apartments'),
            'save_and_close' => __('Зберегти і закрити', 'okbi-apartments'),
            'cancel' => __('Скасувати', 'okbi-apartments'),
            'no_objects_on_floor' => __('Немає об\'єктів на цьому поверсі.', 'okbi-apartments'),
            'specify_floor_number' => __('Вкажіть номер поверху.', 'okbi-apartments'),
            'confirm_delete_polygon' => __('Видалити полігон?', 'okbi-apartments'),
        ]);
        // === КІНЕЦЬ ЗМІН ===
    }

    // 3. Скрипти для сторінок об'єктів нерухомості
    $property_post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    if ( in_array( $current_screen->post_type, $property_post_types ) ) {
        wp_enqueue_script( 'fok-admin-logic', plugin_dir_url( __FILE__ ) . 'assets/js/admin-logic.js', ['jquery', 'select2'], time(), true );
    }
    
    // 4. Скрипти для сторінки "Житлові комплекси"
    if ( 'residential_complex' === $current_screen->post_type ) {
        wp_enqueue_script( 'fok-admin-rc-page-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-rc-page.js', ['jquery'], time(), true );
    }
    
}
add_action( 'admin_enqueue_scripts', 'fok_enqueue_admin_scripts' );



function fok_sanitize_settings( $input ) {
    $new_input = [];
    if ( isset( $input['logo_id'] ) ) { $new_input['logo_id'] = absint( $input['logo_id'] ); }
    if ( isset( $input['accent_color'] ) ) { $new_input['accent_color'] = sanitize_hex_color( $input['accent_color'] ); }
    if ( isset( $input['notification_email'] ) ) { $new_input['notification_email'] = sanitize_email( $input['notification_email'] ); }
    if ( isset( $input['recaptcha_site_key'] ) ) {
        $new_input['recaptcha_site_key'] = sanitize_text_field( $input['recaptcha_site_key'] );
    }
    if ( isset( $input['recaptcha_secret_key'] ) ) {
        $new_input['recaptcha_secret_key'] = sanitize_text_field( $input['recaptcha_secret_key'] );
    }
    if ( isset( $input['telegram_bot_token'] ) ) {
        $new_input['telegram_bot_token'] = sanitize_text_field( $input['telegram_bot_token'] );
    }
    if ( isset( $input['telegram_chat_id'] ) ) {
        $new_input['telegram_chat_id'] = sanitize_text_field( $input['telegram_chat_id'] );
    }
    return $new_input;
}


function fok_execute_section_sync( $rc_id ) {
    $submitted_text = get_post_meta( $rc_id, 'fok_rc_sections_list', true );
    $submitted_names = array_filter( array_map( 'trim', explode( "\n", $submitted_text ) ) );
    $submitted_names = array_unique( $submitted_names );

    $existing_sections_query = new WP_Query([
        'post_type'      => 'section',
        'posts_per_page' => -1,
        'meta_key'       => 'fok_section_rc_link',
        'meta_value'     => $rc_id,
        'fields'         => 'all',
    ]);

    $existing_sections_map = [];
    if ( $existing_sections_query->have_posts() ) {
        foreach ( $existing_sections_query->posts as $section ) {
            $existing_sections_map[ $section->post_title ] = $section->ID;
        }
    }

    $names_to_add = array_diff( $submitted_names, array_keys( $existing_sections_map ) );
    foreach ( $names_to_add as $name ) {
        if ( empty( $name ) ) continue;
        $new_section_id = wp_insert_post([
            'post_title'  => sanitize_text_field( $name ),
            'post_type'   => 'section',
            'post_status' => 'publish',
        ]);
        if ( $new_section_id && ! is_wp_error( $new_section_id ) ) {
            update_post_meta( $new_section_id, 'fok_section_rc_link', $rc_id );
        }
    }

    $names_to_delete = array_diff( array_keys( $existing_sections_map ), $submitted_names );
    foreach ( $names_to_delete as $name ) {
        $section_id_to_delete = $existing_sections_map[$name];
        wp_delete_post( $section_id_to_delete, true );
    }

    wp_cache_delete( 'posts', 'meta' );
    $final_sections_map = [];
    $final_query = new WP_Query([
        'post_type'      => 'section',
        'posts_per_page' => -1,
        'meta_key'       => 'fok_section_rc_link',
        'meta_value'     => $rc_id,
    ]);
    if ( $final_query->have_posts() ) {
        foreach ( $final_query->posts as $section ) {
            $final_sections_map[ $section->post_title ] = $section->ID;
        }
    }
    return $final_sections_map;
}


function fok_sync_sections_on_rc_save( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( get_post_type( $post_id ) !== 'residential_complex' ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( ! isset( $_POST['fok_rc_sections_list'] ) ) return;

    update_post_meta($post_id, 'fok_rc_sections_list', sanitize_textarea_field($_POST['fok_rc_sections_list']));
    fok_execute_section_sync( $post_id );
}
add_action( 'save_post', 'fok_sync_sections_on_rc_save', 10 );


// =========================================================================
// = КОД ДЛЯ ІНТЕРАКТИВНОГО РЕДАКТОРА СІТКИ
// =========================================================================

function fok_add_grid_editor_metabox() {
    add_meta_box(
        'fok_grid_editor',
        __( 'Редактор сітки', 'okbi-apartments' ),
        'fok_render_grid_editor_metabox_content',
        'section', // ЗМІНЕНО: тепер мета-блок на сторінці "Секція"
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'fok_add_grid_editor_metabox' );

function fok_render_grid_editor_metabox_content( $post ) {
    $sections_query = new WP_Query([
        'post_type' => 'section',
        'posts_per_page' => -1,
        'meta_key' => 'fok_section_rc_link',
        'meta_value' => $post->ID,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    ?>
    <div class="fok-grid-editor-wrapper" data-section-id="<?php echo esc_attr( $post->ID ); ?>">
        <div class="fok-editor-main-content">
            <div class="fok-editor-loader"><div class="spinner"></div></div>
            
            <div class="fok-editor-layout">
                <div class="fok-unassigned-pool">
                    <h4><?php _e( 'Нерозподілені об\'єкти', 'okbi-apartments' ); ?></h4>
                    <div class="fok-unassigned-list">
                        </div>
                </div>
                <div class="fok-editor-grid-container">
                    </div>
            </div>

             <div class="fok-editor-toolbar">
                <button type="button" class="button button-primary" id="fok-save-grid-changes">
                    <?php _e( 'Зберегти зміни', 'okbi-apartments' ); ?>
                </button>
                <span class="fok-save-status"></span>
            </div>
            <p class="description" style="margin-top: 10px;"><?php _e( '<b>Важливо:</b> не забудьте зберегти зміни перед оновленням або закриттям сторінки.', 'okbi-apartments' ); ?></p>
        </div>
    </div>
    <?php
    wp_nonce_field( 'fok_grid_editor_nonce_action', 'fok_grid_editor_nonce' );
}

function fok_get_section_grid_data_for_admin() {
    check_ajax_referer( 'fok_grid_editor_nonce_action', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'У вас недостатньо прав.' );
    }

    $section_id = isset( $_POST['section_id'] ) ? intval( $_POST['section_id'] ) : 0;
    if ( ! $section_id ) {
        wp_send_json_error( 'ID секції не вказано.' );
    }

    $grid_cols = (int) get_post_meta( $section_id, 'fok_section_grid_columns', true ) ?: 10;

    $properties_query = new WP_Query([
        'post_type' => ['apartment', 'commercial_property', 'storeroom'],
        'posts_per_page' => -1,
        'meta_query' => [
            ['key' => 'fok_property_section_link', 'value' => $section_id]
        ],
    ]);

    $assigned_properties = [];
    $unassigned_properties = [];
    $max_floor = -1;
    $min_floor = 1;

    if ( $properties_query->have_posts() ) {
        while ( $properties_query->have_posts() ) {
            $properties_query->the_post();
            $property_id = get_the_ID();
            $x_start = (int) get_post_meta( $property_id, 'fok_property_grid_column_start', true );
            $y_start = (int) get_post_meta( $property_id, 'fok_property_floor', true );

            $property_details = [
                'id'           => $property_id,
                'title'        => get_the_title(),
                'edit_link'    => get_edit_post_link( $property_id ),
                'type'         => get_post_type($property_id),
                'status'       => get_the_terms( $property_id, 'status' ) ? get_the_terms( $property_id, 'status' )[0]->slug : 'unknown',
                'x_start'      => $x_start,
                'y_start'      => $y_start,
                'x_span'       => (int) get_post_meta( $property_id, 'fok_property_grid_column_span', true ) ?: 1,
                'y_span'       => (int) get_post_meta( $property_id, 'fok_property_grid_row_span', true ) ?: 1,
            ];

            if ( $x_start > 0 ) {
                $assigned_properties[] = $property_details;
                $end_floor = $y_start + $property_details['y_span'] - 1;
                if ($y_start > $max_floor) $max_floor = $y_start;
                if ($end_floor > $max_floor) $max_floor = $end_floor;
                if ($y_start < $min_floor) $min_floor = $y_start;
            } else {
                $unassigned_properties[] = $property_details;
            }
        }
    }
    wp_reset_postdata();

    if (empty($assigned_properties) && empty($unassigned_properties)) {
        $max_floor = (int) get_post_meta( $section_id, 'fok_section_total_floors', true ) ?: 10;
        $min_floor = 1;
    } elseif (empty($assigned_properties)) {
         $max_floor = (int) get_post_meta( $section_id, 'fok_section_total_floors', true ) ?: 10;
    }

    wp_send_json_success([
        'grid_cols'           => $grid_cols,
        'max_floor'           => $max_floor,
        'min_floor'           => $min_floor,
        'assigned_properties'   => $assigned_properties,
        'unassigned_properties' => $unassigned_properties,
    ]);
}
add_action( 'wp_ajax_fok_get_section_grid_data', 'fok_get_section_grid_data_for_admin' );

function fok_save_section_grid_data() {
    check_ajax_referer( 'fok_grid_editor_nonce_action', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'У вас недостатньо прав.' );
    }

    $changes = isset( $_POST['changes'] ) ? json_decode( stripslashes( $_POST['changes'] ), true ) : [];

    if ( empty( $changes ) ) {
        wp_send_json_error( 'Немає даних для збереження.' );
    }

    foreach ( $changes as $change ) {
        $property_id = intval( $change['id'] );
        if ( get_post_status( $property_id ) ) {
            update_post_meta( $property_id, 'fok_property_grid_column_start', intval( $change['x_start'] ) );
            update_post_meta( $property_id, 'fok_property_floor', intval( $change['y_start'] ) );
            update_post_meta( $property_id, 'fok_property_grid_column_span', intval( $change['x_span'] ) );
            update_post_meta( $property_id, 'fok_property_grid_row_span', intval( $change['y_span'] ) );
        }
    }

    wp_send_json_success( 'Зміни успішно збережено.' );
}
add_action( 'wp_ajax_fok_save_grid_changes', 'fok_save_section_grid_data' );

/**
 * AJAX-обробник для отримання списку об'єктів для конкретного поверху секції.
 */
function fok_ajax_get_properties_for_floor() {
    check_ajax_referer( 'fok_floor_plans_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Недостатньо прав.' );
    }

    $section_id = isset( $_POST['section_id'] ) ? intval( $_POST['section_id'] ) : 0;
    $floor_number = isset( $_POST['floor_number'] ) ? sanitize_text_field( $_POST['floor_number'] ) : '';

    if ( ! $section_id || $floor_number === '' ) {
        wp_send_json_success( ['html' => ''] ); // Повертаємо пустий результат, якщо даних недостатньо
    }

    $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    $properties_query = new WP_Query([
        'post_type'      => $property_types,
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'fok_property_section_link', 'value' => $section_id],
            ['key' => 'fok_property_floor', 'value' => $floor_number],
        ],
        'orderby' => 'title',
        'order'   => 'ASC'
    ]);

    $output_html = '';
    if ( $properties_query->have_posts() ) {
        $output_html .= '<ul>';
        while ( $properties_query->have_posts() ) {
            $properties_query->the_post();
            $output_html .= '<li>' . get_the_title() . '</li>';
        }
        $output_html .= '</ul>';
        wp_reset_postdata();
    } else {
        $output_html = '<p style="font-style: italic; color: #777;">Об\'єктів на цьому поверсі не знайдено.</p>';
    }

    wp_send_json_success( ['html' => $output_html] );
}
add_action( 'wp_ajax_fok_get_properties_for_floor', 'fok_ajax_get_properties_for_floor' );

/**
 * AJAX-обробник для отримання списку об'єктів для редактора полігонів.
 * Повертає дані у форматі JSON.
 */
function fok_ajax_get_properties_for_floor_json() {
    check_ajax_referer( 'fok_floor_plans_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Недостатньо прав.' );
    }

    $section_id = isset( $_POST['section_id'] ) ? intval( $_POST['section_id'] ) : 0;
    $floor_number = isset( $_POST['floor_number'] ) ? sanitize_text_field( $_POST['floor_number'] ) : '';

    if ( ! $section_id || $floor_number === '' ) {
        wp_send_json_success( [] );
    }

    // --- ОСНОВНЕ ВИПРАВЛЕННЯ ТУТ ---
    // Тепер ми включаємо 'parking_space' у список типів для пошуку.
    $property_types = ['apartment', 'commercial_property', 'storeroom', 'parking_space'];
    
    $properties_query = new WP_Query([
        'post_type'      => $property_types,
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'fok_property_section_link', 'value' => $section_id],
            ['key' => 'fok_property_floor', 'value' => $floor_number],
        ],
        'orderby' => 'title',
        'order'   => 'ASC'
    ]);

    $properties_data = [];
    if ( $properties_query->have_posts() ) {
        while ( $properties_query->have_posts() ) {
            $properties_query->the_post();
            $properties_data[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success( $properties_data );
}
add_action( 'wp_ajax_fok_get_properties_for_floor_json', 'fok_ajax_get_properties_for_floor_json' );

/**
 * Додає нові колонки до списку заявок (fok_lead).
 *
 * @param array $columns Існуючий масив колонок.
 * @return array Модифікований масив колонок.
 */
function fok_set_lead_columns($columns) {
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = __('Заявка', 'okbi-apartments');
    $new_columns['lead_status'] = __('Статус', 'okbi-apartments');
    $new_columns['lead_phone'] = __('Телефон клієнта', 'okbi-apartments');
    // ++ НОВА КОЛОНКА ++
    $new_columns['lead_rc_section'] = __('ЖК / Секція', 'okbi-apartments');
    $new_columns['date'] = $columns['date'];

    return $new_columns;
}
add_filter('manage_fok_lead_posts_columns', 'fok_set_lead_columns');


function fok_render_lead_custom_columns($column_name, $post_id) {
    switch ($column_name) {
        case 'lead_status':
            // ... (код для статусу залишається без змін) ...
            $status_slug = get_post_meta($post_id, '_lead_status', true);
            $statuses = [
                'new' => ['text' => 'Нова', 'color' => '#0073aa'],
                'in_progress' => ['text' => 'В обробці', 'color' => '#ffb900'],
                'success' => ['text' => 'Успішно', 'color' => '#46b450'],
                'failed' => ['text' => 'Відмова', 'color' => '#dc3232'],
            ];
            $status_text = $statuses[$status_slug]['text'] ?? ucfirst($status_slug);
            $status_color = $statuses[$status_slug]['color'] ?? '#cccccc';
            echo '<span style="background-color:' . esc_attr($status_color) . '; color:#fff; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px;">' . esc_html($status_text) . '</span>';
            break;

        case 'lead_phone':
            // ... (код для телефону залишається без змін) ...
            $phone = get_post_meta($post_id, '_lead_phone', true);
            if ($phone) {
                echo '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>';
            }
            break;

        // ++ НОВИЙ ОБРОБНИК ДЛЯ КОЛОНКИ ЖК/СЕКЦІЯ ++
        case 'lead_rc_section':
            $rc_id = get_post_meta($post_id, '_lead_rc_id', true);
            $section_id = get_post_meta($post_id, '_lead_section_id', true);
            
            $output = [];
            if ($rc_id) {
                $output[] = '<strong>' . esc_html(get_the_title($rc_id)) . '</strong>';
            }
            if ($section_id) {
                $output[] = esc_html(get_the_title($section_id));
            }
            
            echo implode('<br>&rarr;&nbsp;', $output);
            break;
    }
}
add_action('manage_fok_lead_posts_custom_column', 'fok_render_lead_custom_columns', 10, 2);

/**
 * Перевіряє, чи активний на сайті SMTP-плагін, і показує сповіщення, якщо ні.
 * Сповіщення з'являється на сторінках плагіна "Flat Okbi" та на сторінках заявок.
 */
function fok_check_smtp_plugin_notice() {
    // ++ ВИПРАВЛЕНО: Перевіряємо обидва можливі варіанти шляху ++
    $is_smtp_active = is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') || // Варіант з нижнім підкресленням
                      is_plugin_active('wp-mail-smtp/wp-mail-smtp.php') || // Варіант з дефісом
                      is_plugin_active('fluent-smtp/fluent-smtp.php') || 
                      is_plugin_active('post-smtp/postman-smtp.php');

    // Якщо жоден SMTP-плагін не активний, показуємо сповіщення.
    if ( ! $is_smtp_active ) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p style="font-size: 14px;">
                <strong style="display: block; margin-bottom: 5px;"><?php _e( 'Flat Okbi: Рекомендація щодо налаштування пошти', 'okbi-apartments' ); ?></strong>
                <?php _e( 'Для гарантованої доставки email-сповіщень, ми рекомендуємо встановити та налаштувати SMTP-плагін.', 'okbi-apartments' ); ?>
                <a href="<?php echo esc_url(admin_url('plugin-install.php?s=WP+Mail+SMTP&tab=search&type=term')); ?>" class="button button-primary" style="margin: 10px 0;">
                    <?php _e( 'Встановити WP Mail SMTP', 'okbi-apartments' ); ?>
                </a>
                <br>
                <small><?php _e( 'Якщо ви вже використовуєте інший SMTP-плагін, можете проігнорувати це повідомлення.', 'okbi-apartments' ); ?></small>
            </p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'fok_check_smtp_plugin_notice' );

/**
 * Синхронізує статус об'єкта нерухомості при зміні статусу заявки.
 * Спрацьовує при збереженні поста типу 'fok_lead'.
 *
 * @param int $post_id ID заявки, що зберігається.
 * @param WP_Post $post Об'єкт заявки.
 */
function fok_sync_property_status_on_lead_save( $post_id, $post ) {
    // Перевірки, щоб уникнути зайвих спрацювань
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( ! isset( $_POST['_lead_status'] ) ) return;

    // Отримуємо ID пов'язаного об'єкта нерухомості
    $property_id = get_post_meta( $post_id, '_lead_property_id', true );
    if ( ! $property_id ) {
        return;
    }

    // Отримуємо новий статус заявки з форми
    $lead_status = sanitize_text_field( $_POST['_lead_status'] );

    $target_property_status_slug = '';

    // Визначаємо, який статус присвоїти об'єкту
    switch ( $lead_status ) {
        case 'success':
            // Якщо заявка успішна, об'єкт стає проданим
            $target_property_status_slug = 'prodano';
            break;
        case 'failed':
            // Якщо клієнт відмовився, об'єкт знову стає вільним
            $target_property_status_slug = 'vilno';
            break;
        case 'new':
        case 'in_progress':
            // Поки заявка нова або в роботі, об'єкт заброньований
            $target_property_status_slug = 'zabronovano';
            break;
    }

    // Якщо ми визначили цільовий статус, оновлюємо його
    if ( ! empty( $target_property_status_slug ) ) {
        $term = get_term_by( 'slug', $target_property_status_slug, 'status' );
        if ( $term && ! is_wp_error( $term ) ) {
            // Встановлюємо новий статус для об'єкта нерухомості
            wp_set_object_terms( $property_id, $term->term_id, 'status', false );
        }
    }
}
// "Вішаємо" нашу функцію на подію збереження заявки
add_action( 'save_post_fok_lead', 'fok_sync_property_status_on_lead_save', 10, 2 );

/**
 * Етап 1: Підготовка до імпорту.
 * Приймає файл, зберігає його тимчасово і повертає інформацію про нього.
 */
function fok_prepare_import_ajax() {
    // Перевірка безпеки
    check_ajax_referer('fok_import_nonce_action', 'fok_import_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Недостатньо прав.']);
    }

    if (empty($_FILES['properties_csv']['tmp_name'])) {
        wp_send_json_error(['message' => 'Файл не було завантажено.']);
    }
    
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/flat-okbi-importer';
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    $temp_filename = 'import_' . uniqid() . '_' . sanitize_file_name($_FILES['properties_csv']['name']);
    $temp_filepath = $temp_dir . '/' . $temp_filename;

    if (!move_uploaded_file($_FILES['properties_csv']['tmp_name'], $temp_filepath)) {
        wp_send_json_error(['message' => 'Не вдалося зберегти тимчасовий файл.']);
    }

    $row_count = 0;
    if (($handle = fopen($temp_filepath, "r")) !== FALSE) {
        // ++ ВИПРАВЛЕНО: Додано обробку BOM і коректне визначення розділювача ++
        $first_line = fgets($handle);
        if (substr($first_line, 0, 3) == pack('H*', 'EFBBBF')) {
            $first_line = substr($first_line, 3);
        }
        $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
        
        // Ми вже прочитали заголовок, тепер читаємо решту файлу для підрахунку
        while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if (count(array_filter($data)) > 0) {
                 $row_count++;
            }
        }
        fclose($handle);
    }

    if ($row_count === 0) {
        unlink($temp_filepath);
        wp_send_json_error(['message' => 'У файлі не знайдено даних для імпорту.']);
    }

    wp_send_json_success([
        'total_rows' => $row_count,
        'filepath'   => $temp_filepath,
        'filename'   => $temp_filename, 
    ]);
}
add_action('wp_ajax_fok_prepare_import', 'fok_prepare_import_ajax');

/**
 * Етап 2: Обробка одного "пакета" (порції) даних з файлу.
 */
function fok_process_import_batch_ajax() {
    // Перевірка безпеки
    check_ajax_referer('fok_import_nonce_action', 'fok_import_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Недостатньо прав.']);
    }

    $filepath = isset($_POST['filepath']) ? sanitize_text_field($_POST['filepath']) : '';
    $batch_number = isset($_POST['batch_number']) ? intval($_POST['batch_number']) : 1;
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
    
    if (empty($filepath) || !file_exists($filepath)) {
        wp_send_json_error(['message' => 'Помилка: Тимчасовий файл імпорту не знайдено.']);
    }

    $start_row = ($batch_number - 1) * $batch_size;

    $stats = ['processed' => 0, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
    $log_messages = [];

    $type_map = [
        'квартира' => 'apartment', 'Квартира' => 'apartment',
        'комерція' => 'commercial_property', 'Комерція' => 'commercial_property',
        'комерційне приміщення' => 'commercial_property', 'Комерційне приміщення' => 'commercial_property',
        'паркомісце' => 'parking_space', 'Паркомісце' => 'parking_space',
        'комора' => 'storeroom', 'Комора' => 'storeroom',
    ];

    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $first_line = fgets($handle);
        if (substr($first_line, 0, 3) == pack('H*', 'EFBBBF')) {
            $first_line = substr($first_line, 3);
        }
        $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
        $header = str_getcsv(trim($first_line), $delimiter);
        
        for ($i = 0; $i < $start_row; $i++) {
            if (fgetcsv($handle, 0, $delimiter) === false) break;
        }

        $current_row_in_batch = 0;
        while ((($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) && ($current_row_in_batch < $batch_size)) {
            if (!is_array($data) || !array_filter($data)) continue;
            
            $row_data = @array_combine($header, array_pad($data, count($header), ''));
            if ($row_data === false) {
                 $stats['errors']++;
                 continue;
            }

            $stats['processed']++;
            $current_row_in_batch++;
            
            $unique_id = sanitize_text_field(trim($row_data['unique_id']));
            $post_type_name = trim($row_data['post_type']);
            $post_type = $type_map[$post_type_name] ?? null;

            if (empty($unique_id) || empty($post_type)) {
                $stats['skipped']++;
                continue;
            }
            
            $rc_name = sanitize_text_field(trim($row_data['rc_name']));
            $section_name = sanitize_text_field(trim($row_data['section_name']));
            $rc_id = null;
            $section_id = null;
            
            if (empty($rc_name) || empty($section_name)) {
                $stats['errors']++;
                continue;
            }

            $rc_query = new WP_Query([
                'post_type' => 'residential_complex', 'post_status' => 'publish',
                'title' => $rc_name, 'posts_per_page' => 1, 'fields' => 'ids'
            ]);
            
            if ($rc_query->have_posts()) {
                $rc_id = $rc_query->posts[0];
            } else {
                $rc_id = wp_insert_post(['post_title' => $rc_name, 'post_type' => 'residential_complex', 'post_status' => 'publish']);
            }
            
            if (!$rc_id || is_wp_error($rc_id)) {
                $stats['errors']++;
                continue;
            }

            $section_query = new WP_Query([
                'post_type' => 'section', 'post_status' => 'publish',
                'title' => $section_name, 'posts_per_page' => 1, 'fields' => 'ids',
                'meta_query' => [['key' => 'fok_section_rc_link', 'value' => $rc_id]]
            ]);

            $is_new_section = !$section_query->have_posts();
            if ($is_new_section) {
                $section_id = wp_insert_post(['post_title' => $section_name, 'post_type' => 'section', 'post_status' => 'publish']);
                if ($section_id && !is_wp_error($section_id)) {
                    update_post_meta($section_id, 'fok_section_rc_link', $rc_id);
                }
            } else {
                $section_id = $section_query->posts[0];
            }
            
            if (!$section_id || is_wp_error($section_id)) {
                $stats['errors']++;
                continue;
            }
            
            // ++ ПОЧАТОК НОВОГО БЛОКУ: Оновлюємо текстове поле зі списком секцій в ЖК ++
            if ($is_new_section) {
                $existing_sections_text = get_post_meta($rc_id, 'fok_rc_sections_list', true) ?: '';
                $existing_sections_array = array_filter(array_map('trim', explode("\n", $existing_sections_text)));

                if (!in_array($section_name, $existing_sections_array)) {
                    $existing_sections_array[] = $section_name;
                    sort($existing_sections_array, SORT_NATURAL); // Сортуємо для порядку
                    $new_sections_text = implode("\n", $existing_sections_array);
                    update_post_meta($rc_id, 'fok_rc_sections_list', $new_sections_text);
                }
            }
            // ++ КІНЕЦЬ НОВОГО БЛОКУ ++

            $property_number = sanitize_text_field(trim($row_data['property_number']));
            $type_names_for_title = ['apartment' => 'Квартира', 'commercial_property' => 'Комерція', 'parking_space' => 'Паркомісце', 'storeroom' => 'Комора'];
            $post_title = ($type_names_for_title[$post_type] ?? 'Об\'єкт') . ' №' . $property_number;

            $property_query = new WP_Query(['post_type' => array_values(array_unique($type_map)), 'post_status' => 'any', 'posts_per_page' => 1, 'meta_query' => [['key' => 'fok_property_unique_id', 'value' => $unique_id]]]);
            
            $post_data = ['post_title' => $post_title, 'post_status' => 'publish', 'post_type' => $post_type];
            
            if (!$property_query->have_posts()) {
                $property_id = wp_insert_post($post_data);
                if ($property_id) $stats['imported']++;
            } else {
                $property_id = $property_query->posts[0]->ID;
                $post_data['ID'] = $property_id;
                wp_update_post($post_data);
                if ($property_id) $stats['updated']++;
            }
            wp_reset_postdata();

            if (!$property_id || is_wp_error($property_id)) {
                $stats['errors']++;
                continue;
            }
            
            update_post_meta($property_id, 'fok_property_unique_id', $unique_id);
            update_post_meta($property_id, 'fok_property_rc_link', $rc_id);
            update_post_meta($property_id, 'fok_property_section_link', $section_id);
            update_post_meta($property_id, 'fok_property_number', $property_number);
            update_post_meta($property_id, 'fok_property_floor', intval($row_data['floor'] ?? 0));
            update_post_meta($property_id, 'fok_property_grid_column_start', intval($row_data['grid_column_start'] ?? 1));
            update_post_meta($property_id, 'fok_property_grid_column_span', intval($row_data['grid_column_span'] ?? 1));
            update_post_meta($property_id, 'fok_property_grid_row_span', intval($row_data['grid_row_span'] ?? 1));
            update_post_meta($property_id, 'fok_property_area', floatval(str_replace(',', '.', ($row_data['area'] ?? 0))));
            update_post_meta($property_id, 'fok_property_price_per_sqm', floatval(str_replace(',', '.', ($row_data['price_per_sqm'] ?? 0))));
            update_post_meta($property_id, 'fok_property_total_price_manual', floatval(str_replace(',', '.', ($row_data['total_price'] ?? 0))));
            update_post_meta($property_id, 'fok_property_discount_percent', floatval(str_replace(',', '.', ($row_data['discount_percent'] ?? 0))));
            update_post_meta($property_id, 'fok_property_currency', sanitize_text_field(strtoupper(trim($row_data['currency']))));
            
            if ($post_type === 'apartment') {
                update_post_meta($property_id, 'fok_property_rooms', intval($row_data['rooms'] ?? 0));
            }
            
            $status_name = sanitize_text_field(trim($row_data['status']));
            if (!empty($status_name)) {
                $term = get_term_by('name', $status_name, 'status');
                if ($term && !is_wp_error($term)) {
                    wp_set_object_terms($property_id, $term->term_id, 'status', false);
                }
            } else {
                wp_set_object_terms($property_id, null, 'status', false);
            }
        }
        fclose($handle);
    } else {
        wp_send_json_error(['message' => 'Не вдалося повторно відкрити тимчасовий файл.']);
    }

    $log_messages[] = "Пакет №{$batch_number} оброблено. Створено: {$stats['imported']}, Оновлено: {$stats['updated']}, Помилок: {$stats['errors']}.";

    wp_send_json_success([
        'processed' => $stats['processed'],
        'imported' => $stats['imported'],
        'updated' => $stats['updated'],
        'errors' => $stats['errors'],
        'skipped' => $stats['skipped'],
        'log' => implode("\n", $log_messages)
    ]);
}
add_action('wp_ajax_fok_process_import_batch', 'fok_process_import_batch_ajax');


/**
 * Етап 3: Очищення. Видаляє тимчасовий файл після імпорту.
 */
function fok_cleanup_import_file_ajax() {
    check_ajax_referer('fok_import_nonce_action', 'fok_import_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error();
    }
    
    $filepath = isset($_POST['filepath']) ? sanitize_text_field($_POST['filepath']) : '';
    
    // Перевірка безпеки: переконуємось, що ми видаляємо файл саме з нашої тимчасової папки
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/flat-okbi-importer';

    if (!empty($filepath) && strpos($filepath, $temp_dir) === 0 && file_exists($filepath)) {
        unlink($filepath);
        wp_send_json_success(['message' => 'Тимчасовий файл видалено.']);
    } else {
        wp_send_json_error(['message' => 'Некоректний шлях до файлу.']);
    }
}
add_action('wp_ajax_fok_cleanup_import_file', 'fok_cleanup_import_file_ajax');

/**
 * AJAX-обробник для отримання списку об'єктів для таблиці цін.
 */
function fok_get_properties_for_pricing_ajax() {
    check_ajax_referer( 'fok_pricing_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ['message' => 'Недостатньо прав.'] );
    }

    $rc_id = isset($_POST['rc_id']) ? absint($_POST['rc_id']) : 0;
    $section_id = isset($_POST['section_id']) ? absint($_POST['section_id']) : 0;
    $property_type = isset($_POST['property_type']) ? sanitize_text_field($_POST['property_type']) : 'all';
    $rooms = isset($_POST['rooms']) ? sanitize_text_field($_POST['rooms']) : 'all';

    $meta_query = ['relation' => 'AND'];
    if ($rc_id) {
        $meta_query[] = ['key' => 'fok_property_rc_link', 'value' => $rc_id];
    }
    if ($section_id) {
        $meta_query[] = ['key' => 'fok_property_section_link', 'value' => $section_id];
    }

    // ++ ЛОГІКА ДЛЯ ФІЛЬТРУ КІМНАТ ++
    if ($property_type === 'apartment' && $rooms !== 'all') {
        if (strpos($rooms, '+') !== false) {
            // Для значень типу "5+"
            $meta_query[] = [
                'key' => 'fok_property_rooms',
                'value' => intval($rooms),
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
        } else {
            // Для точних значень (1, 2, 3, 4)
            $meta_query[] = [
                'key' => 'fok_property_rooms',
                'value' => intval($rooms),
                'compare' => '=',
                'type' => 'NUMERIC'
            ];
        }
    }

    $query_args = [
        'post_type' => ($property_type === 'all') ? ['apartment', 'commercial_property', 'parking_space', 'storeroom'] : [$property_type],
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => $meta_query
    ];
    
    $properties_query = new WP_Query($query_args);
    $properties_data = [];

    $type_names = [
        'apartment' => __('Квартира', 'okbi-apartments'),
        'commercial_property' => __('Комерція', 'okbi-apartments'),
        'parking_space' => __('Паркомісце', 'okbi-apartments'),
        'storeroom' => __('Комора', 'okbi-apartments'),
    ];

    if ($properties_query->have_posts()) {
        while ($properties_query->have_posts()) {
            $properties_query->the_post();
            $property_id = get_the_ID();
            $post_type = get_post_type($property_id);
            $area = (float) get_post_meta($property_id, 'fok_property_area', true);

            $properties_data[] = [
                'id' => $property_id,
                'title' => get_the_title(),
                'type' => $type_names[$post_type] ?? $post_type,
                'post_type' => $post_type,
                'floor' => get_post_meta($property_id, 'fok_property_floor', true), // ++ ДОДАНО ++
                'area' => $area,
                'price_per_sqm' => (float) get_post_meta($property_id, 'fok_property_price_per_sqm', true),
                'total_price' => (float) get_post_meta($property_id, 'fok_property_total_price_manual', true),
                'edit_link' => get_edit_post_link($property_id),
            ];
        }
    }
    wp_reset_postdata();

    wp_send_json_success($properties_data);
}
add_action('wp_ajax_fok_get_properties_for_pricing', 'fok_get_properties_for_pricing_ajax');


/**
 * AJAX-обробник для збереження змінених цін.
 */
function fok_save_price_changes_ajax() {
    check_ajax_referer( 'fok_pricing_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ['message' => 'Недостатньо прав.'] );
    }

    $changes = isset($_POST['changes']) ? json_decode(stripslashes($_POST['changes']), true) : [];

    if (empty($changes)) {
        wp_send_json_error(['message' => 'Немає даних для збереження.']);
    }

    $updated_count = 0;
    foreach ($changes as $id => $prices) {
        $property_id = absint($id);
        if (get_post_status($property_id)) {
            update_post_meta($property_id, 'fok_property_price_per_sqm', floatval($prices['price_per_sqm']));
            update_post_meta($property_id, 'fok_property_total_price_manual', floatval($prices['total_price']));
            $updated_count++;
        }
    }

    wp_send_json_success(['message' => sprintf(__('Успішно оновлено %d об\'єктів.', 'okbi-apartments'), $updated_count)]);
}
add_action('wp_ajax_fok_save_price_changes', 'fok_save_price_changes_ajax');

/**
 * AJAX-обробник для отримання секцій для обраного ЖК.
 */
function fok_get_sections_for_rc_ajax() {
    check_ajax_referer( 'fok_pricing_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error();
    }

    $rc_id = isset($_POST['rc_id']) ? absint($_POST['rc_id']) : 0;
    if (!$rc_id) {
        wp_send_json_success([]); // Повертаємо пустий масив, якщо ЖК не обрано
    }

    $sections_query = new WP_Query([
        'post_type' => 'section',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            ['key' => 'fok_section_rc_link', 'value' => $rc_id]
        ]
    ]);

    $sections = [];
    if ($sections_query->have_posts()) {
        while($sections_query->have_posts()) {
            $sections_query->the_post();
            $sections[] = [
                'id' => get_the_ID(),
                'title' => get_the_title()
            ];
        }
    }
    wp_reset_postdata();

    wp_send_json_success($sections);
}
add_action('wp_ajax_fok_get_sections_for_rc', 'fok_get_sections_for_rc_ajax');