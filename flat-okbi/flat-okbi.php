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
    wp_enqueue_script('fabric-js', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js', array(), '5.3.1', true);
    wp_enqueue_script('fok-frontend-script', plugin_dir_url( __FILE__ ) . 'assets/js/frontend-script.js', ['jquery', 'fabric-js'], time(), true);
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

    $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];

    $args = [
        'post_type' => $property_types,
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'fok_property_rc_link',
                'value' => $rc_id
            ]
        ],
        'orderby' => ['meta_value_num' => 'ASC'],
        'meta_key' => 'fok_property_floor',
    ];

    $query = new WP_Query($args);

    $sections_data = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $property_id = get_the_ID();
            $post_type = get_post_type($property_id);
            $section_id = get_post_meta($property_id, 'fok_property_section_link', true);
            $floor = (int)get_post_meta($property_id, 'fok_property_floor', true);

            if (!$section_id) continue;

            if (!isset($sections_data[$section_id])) {
                $section_post = get_post($section_id);
                $sections_data[$section_id] = [
                    'name' => $section_post ? $section_post->post_title : __('Невідома секція', 'okbi-apartments'),
                    'floors' => []
                ];
            }

            $status_terms = get_the_terms($property_id, 'status');
            $status_slug = !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown';

            $property_data = [
                'id' => $property_id,
                'type' => $post_type,
                'area' => (float) get_post_meta($property_id, 'fok_property_area', true),
                'floor' => $floor,
                'status' => $status_slug,
                'rooms' => ($post_type === 'apartment') ? (int)get_post_meta($property_id, 'fok_property_rooms', true) : 0,
            ];

            $sections_data[$section_id]['floors'][$floor][] = $property_data;
        }
    }
    wp_reset_postdata();

    wp_send_json_success(['sections' => $sections_data, 'rc_title' => $rc_title]);
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

    // Отримуємо всі необхідні дані
    $status_terms = get_the_terms( $property_id, 'status' );
    $area = (float) get_post_meta( $property_id, 'fok_property_area', true );
    $property_number = get_post_meta( $property_id, 'fok_property_number', true );
    $price_per_m2 = (float) get_post_meta( $property_id, 'fok_property_price_per_sqm', true );
    $manual_total_price = (float) get_post_meta( $property_id, 'fok_property_total_price_manual', true );
    $discount_percent = (float) get_post_meta( $property_id, 'fok_property_discount_percent', true );
    $currency = get_post_meta( $property_id, 'fok_property_currency', true );

    // --- НОВА ЛОГІКА РОЗРАХУНКУ ЦІНИ ---
    
    // 1. Визначаємо базову ціну. Пріоритет у ручного введення.
    $base_total_price = 0;
    if ( $manual_total_price > 0 ) {
        $base_total_price = $manual_total_price;
    } else {
        $base_total_price = $area * $price_per_m2;
    }

    // 2. Розраховуємо фінальну ціну з урахуванням знижки
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
        'status_name'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->name : 'Не вказано',
        'status_slug'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown',
        'gallery'       => [],
        'params'        => [
            'Номер' => $property_number,
            'Тип' => $type_names[$post_type],
            'Площа' => $area . ' м²',
            'Поверх' => get_post_meta( $property_id, 'fok_property_floor', true ),
        ],
        // Передаємо всі варіанти цін на фронтенд
        'price_per_m2'    => number_format($price_per_m2, 0, '.', ' '),
        'total_price'     => number_format($final_price, 0, '.', ' '),
        'base_price'      => number_format($base_total_price, 0, '.', ' '),
        'currency'        => $currency ?: 'UAH',
        'has_discount'    => $has_discount,
        'discount_percent'=> $discount_percent,
    ];

    if ($post_type === 'apartment') {
        $data['params']['К-сть кімнат'] = get_post_meta( $property_id, 'fok_property_rooms', true );
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
    $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
    $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
    if ( ! $property_id || empty( $name ) || empty( $phone ) ) {
        wp_send_json_error( 'Будь ласка, заповніть всі обов\'язкові поля.' );
    }
    $property = get_post( $property_id );
    if ( ! $property || !in_array(get_post_type($property), ['apartment', 'commercial_property', 'parking_space', 'storeroom']) ) {
        wp_send_json_error( 'Об\'єкт не знайдено.' );
    }
    $options = get_option( 'fok_global_settings' );
    $notification_email = !empty( $options['notification_email'] ) ? $options['notification_email'] : get_option( 'admin_email' );
    $subject = 'Нова заявка на об\'єкт з сайту ' . get_bloginfo( 'name' );
    $property_link = get_permalink( $property_id );
    $property_number = get_post_meta( $property_id, 'fok_property_number', true );
    $jk_id = get_post_meta( $property_id, 'fok_property_rc_link', true );
    $jk_name = $jk_id ? get_the_title( $jk_id ) : 'Не вказано';
    $message  = "Доброго дня!\n\n";
    $message .= "Ви отримали нову заявку на об'єкт нерухомості:\n\n";
    $message .= "Ім'я клієнта: " . $name . "\n";
    $message .= "Телефон: " . $phone . "\n\n";
    $message .= "Інформація про об'єкт:\n";
    $message .= "ЖК: " . $jk_name . "\n";
    $message .= "Об'єкт №: " . $property_number . "\n";
    $message .= "Посилання на об'єкт: " . $property_link . "\n\n";
    $message .= "Будь ласка, зв'яжіться з клієнтом найближчим часом.\n";
    $sent = wp_mail( $notification_email, $subject, $message );
    if ( $sent ) {
        wp_send_json_success( 'Дякуємо! Ваша заявка прийнята.' );
    } else {
        wp_send_json_error( 'Помилка відправки. Спробуйте пізніше.' );
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

function fok_check_meta_box_dependency() { if ( ! function_exists( 'is_plugin_active' ) ) { include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); } if ( !is_plugin_active( 'meta-box/meta-box.php' ) && !is_plugin_active( 'meta-box-aio/meta-box-aio.php' ) ) { add_action( 'admin_notices', 'fok_meta_box_not_found_notice' ); } }
add_action( 'admin_init', 'fok_check_meta_box_dependency' );

function fok_meta_box_not_found_notice() { echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post( '<strong>Плагін "Flat Okbi":</strong> Для повноцінної роботи необхідно встановити та активувати безкоштовний плагін <a href="https://wordpress.org/plugins/meta-box/" target="_blank">MetaBox</a>.' ) . '</p></div>'; }

function fok_register_post_types() {
    $cpt_args = [
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'show_in_menu' => 'flat_okbi_settings',
    ];
    
    $top_level_cpt_args = array_merge($cpt_args, [
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
    ]);

    register_post_type('residential_complex', array_merge($top_level_cpt_args, [
        'labels'    => ['name' => __('Житлові комплекси', 'okbi-apartments'), 'singular_name' => __('Житловий комплекс', 'okbi-apartments'), 'add_new_item' => __('Додати новий ЖК', 'okbi-apartments')],
        'rewrite'   => ['slug' => 'residential-complexes'],
        'menu_icon' => 'dashicons-building',
    ]));
    
    register_post_type('section', array_merge($top_level_cpt_args, [
        'labels'    => ['name' => __('Секції', 'okbi-apartments'), 'singular_name' => __('Секція', 'okbi-apartments'), 'add_new_item' => __('Додати нову секцію', 'okbi-apartments')],
        'rewrite'   => ['slug' => 'sections'],
        'supports'  => ['title', 'editor', 'thumbnail'],
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

/**
 * --- ВИПРАВЛЕНО: Надійне підключення адміністративних скриптів ---
 */
function fok_enqueue_admin_scripts( $hook_suffix ) {
    // Скрипти для сторінок налаштувань та імпорту
    if ( 'toplevel_page_flat_okbi_settings' === $hook_suffix || 'flat-okbi_page_flat_okbi_import' === $hook_suffix ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_media();
        wp_enqueue_script('fok-admin-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js', array( 'wp-color-picker', 'jquery' ), time(), true);
    }
    
    // Виходимо, якщо це не сторінка редагування запису
    if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
        return;
    }

    // Використовуємо надійний спосіб отримання даних про поточний екран
    $current_screen = get_current_screen();
    if ( ! isset( $current_screen->post_type ) ) {
        return;
    }

    $property_post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];

    // Логіка для сторінок редагування нерухомості
    if ( in_array( $current_screen->post_type, $property_post_types ) ) {
        wp_enqueue_script(
            'fok-admin-logic',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin-logic.js',
            ['jquery', 'select2'],
            time(), // Додаємо версію, щоб уникнути кешування
            true
        );
        wp_localize_script( 'fok-admin-logic', 'fok_admin_ajax', [
            'select_text' => __( 'Оберіть секцію...', 'okbi-apartments' ),
        ]);
    }

    // Логіка для сторінки редагування ЖК
    if ( 'residential_complex' === $current_screen->post_type ) {
        wp_enqueue_style('fok-admin-interactive-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-interactive.css', [], time());
        wp_enqueue_script('fok-admin-interactive-rc-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-interactive-rc.js', ['jquery', 'fabric-js'], time(), true);
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