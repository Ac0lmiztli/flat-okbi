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
                                    <label><input type="checkbox" name="property_types[]" value="parking_space" checked> <?php _e('Паркомісця', 'okbi-apartments'); ?></label>
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
    wp_enqueue_style('fok-frontend-style', plugin_dir_url( __FILE__ ) . 'assets/css/frontend-style.css', [], time());
    wp_enqueue_script('fok-frontend-script', plugin_dir_url( __FILE__ ) . 'assets/js/frontend-script.js', ['jquery'], time(), true);
    wp_localize_script( 'fok-frontend-script', 'fok_ajax', ['ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'fok_viewer_nonce' )]);
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

            // =================================================================
            //  ++ НОВИЙ КОД: Конвертуємо ID зображень в URL-адреси ++
            // =================================================================
            if (is_array($floor_plans)) {
                foreach ($floor_plans as $index => $plan) {
                    if (!empty($plan['image'])) {
                        // Отримуємо URL зображення за його ID (використовуємо розмір 'large')
                        $image_url = wp_get_attachment_image_url((int)$plan['image'], 'large');
                        $floor_plans[$index]['image'] = $image_url ?: ''; // Записуємо URL назад у масив
                    }
                }
            } else {
                $floor_plans = [];
            }
            // =================================================================
            //  ++ КІНЕЦЬ НОВОГО КОДУ ++
            // =================================================================

            $sections_data[$section_id] = [
                'id' => $section_id, 'name' => get_the_title(),
                'grid_columns' => (int)get_post_meta($section_id, 'fok_section_grid_columns', true),
                'properties' => [],
                'floor_plans' => $floor_plans,
            ];
        }
    }
    wp_reset_postdata();

    // ... (решта коду функції залишається без змін) ...
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

            // ++ ПОЧАТОК НОВОГО КОДУ: Додаємо зчитування координат ++
            $final_parking_items[] = [
                 'id' => $spot->ID, 
                 'type' => 'parking_space',
                 'area' => (float)get_post_meta($spot->ID, 'fok_property_area', true),
                 'floor' => (int)get_post_meta($spot->ID, 'fok_property_floor', true),
                 'status' => $status_slug,
                 'property_number' => get_post_meta($spot->ID, 'fok_property_number', true),
                 'rooms' => 0, 
                 'has_discount' => (float)get_post_meta($spot->ID, 'fok_property_discount_percent', true) > 0,
                 // Додаємо дані для сітки
                 'grid_x_start' => (int)get_post_meta($spot->ID, 'fok_property_grid_column_start', true),
                 'grid_y_start' => (int)get_post_meta($spot->ID, 'fok_property_floor', true), // Для паркінгу це те ж саме, що й поверх
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
        'section_id'    => get_post_meta( $property_id, 'fok_property_section_link', true ),
        'status_name'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->name : 'Не вказано',
        'status_slug'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown',
        'gallery'       => [],
        'params'        => [
            'Номер' => $property_number,
            'Тип' => $type_names[$post_type],
            'Площа' => $area . ' м²',
            'Поверх' => get_post_meta( $property_id, 'fok_property_floor', true ),
        ],
        'price_per_m2'    => number_format($price_per_m2, 0, '.', ' '),
        'total_price'     => number_format($final_price, 0, '.', ' '),
        'base_price'      => number_format($base_total_price, 0, '.', ' '),
        'currency'        => $currency ?: 'UAH',
        'has_discount'    => $has_discount,
        'discount_percent'=> $discount_percent,
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
    // ... (код перевірки та створення заявки залишається той самий) ...
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
    $property_title = get_the_title( $property_id );
    $lead_title = "Заявка на '{$property_title}' від {$name}";
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

    // --- ПОЧАТОК ФІНАЛЬНОЇ ЛОГІКИ ФОРМУВАННЯ ЛИСТА ---

    $options = get_option( 'fok_global_settings' );
    $notification_email = !empty( $options['notification_email'] ) ? $options['notification_email'] : get_option( 'admin_email' );
    $subject = 'Нова заявка на об\'єкт з сайту: ' . get_bloginfo( 'name' );

    // 1. Формуємо правильні дані відправника
    $domain = wp_parse_url(get_home_url(), PHP_URL_HOST);
    $from_email = 'no-reply@' . $domain;
    $from_name = get_bloginfo('name');
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>"
    ];

    // 2. Отримуємо всі необхідні дані для тіла листа
    $crm_link = admin_url( 'post.php?post=' . $lead_id . '&action=edit' ); // Надійно генеруємо посилання
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

    // 3. Формуємо тіло листа у форматі HTML з усіма даними
    $message  = "<p>Доброго дня!</p>";
    $message .= "<p>Ви отримали нову заявку на об'єкт нерухомості:</p>";
    $message .= "<ul style='list-style-type: none; padding-left: 0;'>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Ім'я клієнта:</strong> " . esc_html($name) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Телефон:</strong> " . esc_html($phone) . "</li>";
    $message .= "<hr style='border:0; border-top: 1px solid #eee; margin: 10px 0;'>";
    $message .= "<li style='margin-bottom: 5px;'><strong>ЖК:</strong> " . esc_html($jk_name) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Секція:</strong> " . esc_html($section_name) . "</li>"; // ++ ДОДАНО
    $message .= "<li style='margin-bottom: 5px;'><strong>Об'єкт №:</strong> " . esc_html($property_number) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Тип об'єкта:</strong> " . esc_html($property_type_name) . "</li>";
    $message .= "<li style='margin-bottom: 5px;'><strong>Поверх:</strong> " . esc_html($property_floor) . "</li>"; // ++ ДОДАНО
    $message .= "</ul>";
    
    if ($crm_link) {
        $message .= '<p style="margin-top: 20px;">';
        $message .= '<a href="' . esc_url($crm_link) . '" style="background-color: #0073aa; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">';
        $message .= 'Переглянути заявку в CRM';
        $message .= '</a>';
        $message .= '</p>';
    }

    $sent = wp_mail( $notification_email, $subject, $message, $headers );

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
        __( 'Імпорт/Експорт', 'okbi-apartments' ),
        __( 'Імпорт/Експорт', 'okbi-apartments' ),
        'manage_options', 'flat_okbi_import', 'fok_render_importer_page'
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

function fok_enqueue_admin_scripts( $hook_suffix ) {
    // 1. Скрипти для сторінок налаштувань та імпорту
    if ( 'toplevel_page_flat_okbi_settings' === $hook_suffix || 'flat-okbi_page_flat_okbi_import' === $hook_suffix ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_media();
        wp_enqueue_script('fok-admin-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js', array( 'wp-color-picker', 'jquery' ), time(), true);
        
        wp_localize_script('fok-admin-script', 'fok_delete_nonce', [
            'nonce' => wp_create_nonce('fok_delete_all_nonce')
        ]);
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
        // Скрипти для редактора сітки
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_style( 'fok-admin-grid-editor-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-grid-editor.css', [], time() );
        wp_enqueue_script( 'fok-admin-grid-editor-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-grid-editor.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'], time(), true );
        wp_localize_script( 'fok-admin-grid-editor-script', 'fok_editor_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'fok_grid_editor_nonce_action' )
        ]);

        // Скрипти для конструктора планів поверхів
        wp_enqueue_media();
        // ** ЗМІНЕНО: Додаємо залежності для модального вікна **
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-sortable');
        
        // ** ЗМІНЕНО: Підключаємо стандартні стилі для діалогових вікон WordPress **
        wp_enqueue_style( 'wp-jquery-ui-dialog' );
        wp_enqueue_style( 'fok-admin-floor-plan-editor-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-floor-plan-editor.css', [], time() );
        
        // ** ЗМІНЕНО: Додаємо 'jquery-ui-dialog' до залежностей нашого скрипта **
        wp_enqueue_script( 'fok-admin-groups-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-groups.js', ['jquery', 'jquery-ui-sortable', 'jquery-ui-dialog'], time(), true );
        
        // --- ОНОВЛЕННЯ: Передаємо дані напряму в JS ---
        wp_localize_script('fok-admin-groups-script', 'fok_groups_data', [
            'nonce'      => wp_create_nonce('fok_floor_plans_nonce'),
            'post_id'    => get_the_ID(),
        ]);
    }

    // 3. Скрипти для сторінок об'єктів нерухомості
    $property_post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    if ( in_array( $current_screen->post_type, $property_post_types ) ) {
        wp_enqueue_script(
            'fok-admin-logic',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin-logic.js',
            ['jquery', 'select2'],
            time(),
            true
        );
        wp_localize_script( 'fok-admin-logic', 'fok_admin_ajax', [
            'select_text' => __( 'Оберіть секцію...', 'okbi-apartments' ),
        ]);
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
