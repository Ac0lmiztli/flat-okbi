<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class FOK_Ajax {
    public function __construct() {
        // Тут ми будемо реєструвати всі наші AJAX хуки
        add_action( 'wp_ajax_fok_filter_properties', [ $this, 'filter_properties' ] );
        add_action( 'wp_ajax_nopriv_fok_filter_properties', [ $this, 'filter_properties' ] );

        add_action( 'wp_ajax_fok_get_property_details', [ $this, 'get_property_details' ] );
        add_action( 'wp_ajax_nopriv_fok_get_property_details', [ $this, 'get_property_details' ] );

        add_action( 'wp_ajax_fok_submit_booking', [ $this, 'handle_booking_request' ] );
        add_action( 'wp_ajax_nopriv_fok_submit_booking', [ $this, 'handle_booking_request' ] );

        // Admin AJAX hooks
        add_action( 'wp_ajax_fok_delete_all_data', [ $this, 'delete_all_data' ] );
        add_action( 'wp_ajax_fok_get_section_grid_data', [ $this, 'get_section_grid_data' ] );
        add_action( 'wp_ajax_fok_save_grid_changes', [ $this, 'save_grid_changes' ] );
        add_action( 'wp_ajax_fok_get_properties_for_floor', [ $this, 'get_properties_for_floor' ] );
        add_action( 'wp_ajax_fok_get_properties_for_floor_json', [ $this, 'get_properties_for_floor_json' ] );
        add_action('wp_ajax_fok_prepare_import', [ $this, 'prepare_import' ]);
        add_action('wp_ajax_fok_process_import_batch', [ $this, 'process_import_batch' ]);
        add_action('wp_ajax_fok_cleanup_import_file', [ $this, 'cleanup_import_file' ]);
        add_action('wp_ajax_fok_get_properties_for_pricing', [ $this, 'get_properties_for_pricing' ]);
        add_action('wp_ajax_fok_save_price_changes', [ $this, 'save_price_changes' ]);
        add_action('wp_ajax_fok_get_sections_for_rc', [ $this, 'get_sections_for_rc' ]);
        add_action('wp_ajax_fok_validate_import_file', [ $this, 'validate_import_file' ]);
    }

    /**
     * AJAX handler for filtering properties.
     * It fetches sections and all related properties for a given Residential Complex (RC).
     */
    public function filter_properties() {
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

        $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
        $properties_query = new WP_Query([
            'post_type'      => $property_types,
            'posts_per_page' => -1,
            'meta_query'     => [['key' => 'fok_property_rc_link', 'value' => $rc_id]],
            'fields'         => 'ids',
        ]);

        $properties_by_section = [];
        if ( ! empty($properties_query->posts) ) {
            foreach ($properties_query->posts as $property_id) {
                $section_id = get_post_meta($property_id, 'fok_property_section_link', true);
                if ($section_id && isset($sections_data[$section_id])) {
                    $properties_by_section[$section_id][] = $property_id;
                }
            }
        }

        foreach ($properties_by_section as $section_id => $property_ids) {
            usort($property_ids, function($a_id, $b_id) {
                $floor_a = (int)get_post_meta($a_id, 'fok_property_floor', true);
                $floor_b = (int)get_post_meta($b_id, 'fok_property_floor', true);
                if ($floor_a !== $floor_b) return $floor_a <=> $floor_b;
                return strnatcmp(get_post_meta($a_id, 'fok_property_number', true), get_post_meta($b_id, 'fok_property_number', true));
            });

            $regular_items = [];
            $parking_items = [];
            foreach ($property_ids as $property_id) {
                if (get_post_type($property_id) === 'parking_space') {
                    $parking_items[] = $property_id;
                } else {
                    $regular_items[] = $property_id;
                }
            }
            $occupancy_map = [];
            $final_regular_properties = [];
            $manual_items = [];
            $auto_items = [];
            foreach ($regular_items as $property_id) {
                ( (int)get_post_meta($property_id, 'fok_property_grid_column_start', true) > 0 ) ? $manual_items[] = $property_id : $auto_items[] = $property_id;
            }
            $sorted_regular_properties = array_merge($manual_items, $auto_items);

            foreach ($sorted_regular_properties as $property_id) {
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
            foreach ($parking_items as $spot_id) {
                $status_terms = get_the_terms($spot_id, 'status');
                $status_slug = !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown';
                if ($status_slug === 'vilno') {
                    $available_parking_count++;
                }
                $final_parking_items[] = [
                    'id' => $spot_id,
                    'type' => 'parking_space',
                    'area' => (float)get_post_meta($spot_id, 'fok_property_area', true),
                    'floor' => (int)get_post_meta($spot_id, 'fok_property_floor', true),
                    'status' => $status_slug,
                    'property_number' => get_post_meta($spot_id, 'fok_property_number', true),
                    'rooms' => 0,
                    'has_discount' => (float)get_post_meta($spot_id, 'fok_property_discount_percent', true) > 0,
                    'grid_x_start' => (int)get_post_meta($spot_id, 'fok_property_grid_column_start', true),
                    'grid_y_start' => (int)get_post_meta($spot_id, 'fok_property_floor', true),
                    'grid_x_span' => (int)get_post_meta($spot_id, 'fok_property_grid_column_span', true) ?: 1,
                    'grid_y_span' => (int)get_post_meta($spot_id, 'fok_property_grid_row_span', true) ?: 1,
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

    /**
     * AJAX handler to get detailed information for a single property.
     */
    public function get_property_details() {
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
                        if (isset($plan['number']) && $plan['number'] == $floor && !empty($plan['image'])) {
                            $has_floor_plan = true;
                            break;
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
            'section_id'    => $section_id,
            'status_name'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->name : 'Не вказано',
            'status_slug'   => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->slug : 'unknown',
            'gallery'       => [],
            'params'        => [
                'Номер' => $property_number,
                'Тип' => $type_names[$post_type],
                'Площа' => $area . ' м²',
                'Поверх' => $floor,
            ],
            'price_per_m2'    => number_format($price_per_m2, 0, '.', ' '),
            'total_price'     => number_format($final_price, 0, '.', ' '),
            'base_price'      => number_format($base_total_price, 0, '.', ' '),
            'currency'        => $currency ?: 'UAH',
            'has_discount'    => $has_discount,
            'discount_percent'=> $discount_percent,
            'has_floor_plan'  => $has_floor_plan,
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

    /**
     * AJAX handler for submitting a booking request.
     */
    public function handle_booking_request() {
        check_ajax_referer( 'fok_viewer_nonce', 'nonce' );

        $options = get_option('fok_global_settings');
        $secret_key = $options['recaptcha_secret_key'] ?? '';

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

            if (!$response_body['success'] || $response_body['score'] < 0.5) {
                wp_send_json_error('Перевірка на робота не пройдена.');
                return;
            }
        }

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

        $lead_title = "Заявка на '{$property->post_title}' від {$name}";
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
            $tg_message = "<b>🔥 Нова заявка з сайту!</b>\n\n";
            $tg_message .= "<b>Ім'я:</b> " . esc_html($name) . "\n";
            $tg_message .= "<b>Телефон:</b> " . esc_html($phone) . "\n\n";
            $tg_message .= "<b>Об'єкт:</b>\n";
            $tg_message .= "ЖК: " . esc_html($jk_name) . "\n";
            $tg_message .= "Секція: " . esc_html($section_name) . "\n";
            $tg_message .= "Тип: " . esc_html($property_type_name) . " №" . esc_html($property_number) . "\n";
            $tg_message .= "Поверх: " . esc_html($property_floor) . "\n\n";
            
            $tg_message .= "<a href='" . esc_url($crm_link) . "'>➡️ Переглянути заявку в CRM</a>";

            $tg_api_url = "https://api.telegram.org/bot{$tg_bot_token}/sendMessage";
            
            wp_remote_post( $tg_api_url, [
                'body' => [
                    'chat_id' => $tg_chat_id,
                    'text' => $tg_message,
                    'parse_mode' => 'HTML',
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

    /**
     * AJAX handler for deleting all plugin data.
     */
    public function delete_all_data() {
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
            'fields' => 'ids',
        ]);

        $deleted_count = 0;
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $result = wp_delete_post( $post_id, true ); 
                if ( $result !== false ) {
                    $deleted_count++;
                }
            }
        }

        wp_send_json_success( [ 'message' => sprintf( __( 'Успішно видалено %d об\'єктів.', 'okbi-apartments' ), $deleted_count ) ] );
    }

    /**
     * AJAX handler to get data for the admin grid editor for a specific section.
     */
    public function get_section_grid_data() {
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

        // Завжди враховуємо налаштування кількості поверхів.
        // Висота сітки буде або за цим налаштуванням, або за найвищим об'єктом.
        $total_floors_setting = (int) get_post_meta($section_id, 'fok_section_total_floors', true) ?: 10;
        $max_floor = max($max_floor, $total_floors_setting);

        wp_send_json_success([
            'grid_cols'           => $grid_cols,
            'max_floor'           => $max_floor,
            'min_floor'           => $min_floor,
            'assigned_properties'   => $assigned_properties,
            'unassigned_properties' => $unassigned_properties,
        ]);
    }

    /**
     * AJAX handler to save changes from the admin grid editor.
     */
    public function save_grid_changes() {
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

    /**
     * AJAX handler to get a list of properties for a specific floor of a section (HTML response).
     */
    public function get_properties_for_floor() {
        check_ajax_referer( 'fok_floor_plans_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Недостатньо прав.' );
        }

        $section_id = isset( $_POST['section_id'] ) ? intval( $_POST['section_id'] ) : 0;
        $floor_number = isset( $_POST['floor_number'] ) ? sanitize_text_field( $_POST['floor_number'] ) : '';

        if ( ! $section_id || $floor_number === '' ) {
            wp_send_json_success( ['html' => ''] );
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

    /**
     * AJAX handler to get a list of properties for the polygon editor (JSON response).
     */
    public function get_properties_for_floor_json() {
        check_ajax_referer( 'fok_floor_plans_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Недостатньо прав.' );
        }

        $section_id = isset( $_POST['section_id'] ) ? intval( $_POST['section_id'] ) : 0;
        $floor_number = isset( $_POST['floor_number'] ) ? sanitize_text_field( $_POST['floor_number'] ) : '';

        if ( ! $section_id || $floor_number === '' ) {
            wp_send_json_success( [] );
        }

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
    public function validate_import_file() {
        check_ajax_referer('fok_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостатньо прав.']);
        }

        $filepath = isset($_POST['filepath']) ? sanitize_text_field($_POST['filepath']) : '';
        if (empty($filepath) || !file_exists($filepath)) {
            wp_send_json_error(['message' => 'Помилка: Тимчасовий файл імпорту не знайдено.']);
        }

        $errors = [];
        $row_number = 1; // Починаємо з 1 для заголовка
        $required_columns = ['unique_id', 'post_type', 'rc_name', 'section_name', 'property_number', 'floor'];

        if (($handle = fopen($filepath, "r")) !== FALSE) {
            $first_line = fgets($handle);
            if (substr($first_line, 0, 3) == pack('H*', 'EFBBBF')) {
                $first_line = substr($first_line, 3);
            }
            $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
            $header = str_getcsv(trim($first_line), $delimiter);

            $missing_headers = array_diff($required_columns, $header);
            if (!empty($missing_headers)) {
                wp_send_json_error(['message' => 'Помилка: У файлі відсутні обов\'язкові колонки: ' . implode(', ', $missing_headers)]);
            }

            while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                $row_number++;
                if (!is_array($data) || !array_filter($data)) continue;

                $row_data = @array_combine($header, array_pad($data, count($header), ''));
                if ($row_data === false) {
                    $errors[] = "Рядок {$row_number}: Некоректна кількість колонок.";
                    continue;
                }

                foreach ($required_columns as $column) {
                    if (!isset($row_data[$column]) || empty(trim($row_data[$column]))) {
                        $errors[] = "Рядок {$row_number}: Відсутнє обов'язкове значення в колонці '{$column}'.";
                    }
                }
            }
            fclose($handle);
        }

        if (!empty($errors)) {
            wp_send_json_error([
                'message' => 'Знайдено помилки у файлі імпорту. Будь ласка, виправте їх і спробуйте ще раз.',
                'errors' => $errors
            ]);
        }

        wp_send_json_success(['message' => 'Файл успішно пройшов перевірку. Починаємо імпорт...']);
    }
    /**
     * Prepares the import by uploading the CSV file and getting the total row count.
     */
    public function prepare_import() {
        check_ajax_referer('fok_import_nonce', 'nonce');

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
            $first_line = fgets($handle); // Read header
            while (($data = fgetcsv($handle, 0, (strpos($first_line, ';') !== false ? ';' : ','))) !== FALSE) {
                if (count(array_filter($data)) > 0) {
                    $row_count++;
                }
            }
            fclose($handle);
        }

        wp_send_json_success([
            'total_rows' => $row_count,
            'filepath'   => $temp_filepath,
        ]);
    }

    /**
     * Processes a single batch of the import file.
     * Receives the filepath and batch details via AJAX.
     */
    /**
     * Processes a single batch of the import file.
     * Receives the filepath and batch details via AJAX.
     */
    public function process_import_batch() {
        check_ajax_referer('fok_import_nonce', 'nonce');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error( ['message' => 'Недостатньо прав.'] );
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
                
                // Отримуємо дані з рядка (перевірки тут вже не потрібні, вони у `validate_import_file`)
                $unique_id = sanitize_text_field(trim($row_data['unique_id']));
                $post_type_name = trim($row_data['post_type']);
                $post_type = $type_map[$post_type_name] ?? null;
                $rc_name = sanitize_text_field(trim($row_data['rc_name']));
                $section_name = sanitize_text_field(trim($row_data['section_name']));
                $property_number = sanitize_text_field(trim($row_data['property_number']));
                $floor = sanitize_text_field(trim($row_data['floor']));

                $rc_id = null;
                $section_id = null;

                // Пошук або створення ЖК
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

                // Пошук або створення Секції
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
                
                // Оновлення списку секцій в картці ЖК
                if ($is_new_section) {
                    $existing_sections_text = get_post_meta($rc_id, 'fok_rc_sections_list', true) ?: '';
                    $existing_sections_array = array_filter(array_map('trim', explode("\n", $existing_sections_text)));

                    if (!in_array($section_name, $existing_sections_array)) {
                        $existing_sections_array[] = $section_name;
                        sort($existing_sections_array, SORT_NATURAL);
                        $new_sections_text = implode("\n", $existing_sections_array);
                        update_post_meta($rc_id, 'fok_rc_sections_list', $new_sections_text);
                    }
                }

                // Створення або оновлення Об'єкта нерухомості
                $type_names_for_title = ['apartment' => 'Квартира', 'commercial_property' => 'Комерція', 'parking_space' => 'Паркомісце', 'storeroom' => 'Комора'];
                $post_title = ($type_names_for_title[$post_type] ?? 'Об\'єкт') . ' №' . $property_number;

                $property_query = new WP_Query(['post_type' => array_values(array_unique($type_map)), 'post_status' => 'any', 'posts_per_page' => 1, 'meta_query' => [['key' => 'fok_property_unique_id', 'value' => $unique_id]]]);
                
                $post_data = ['post_title' => $post_title, 'post_status' => 'publish', 'post_type' => $post_type];
                
                if (!$property_query->have_posts()) {
                    $property_id = wp_insert_post($post_data);
                    if ($property_id && !is_wp_error($property_id)) {
                        $stats['imported']++;
                    }
                } else {
                    $property_id = $property_query->posts[0]->ID;
                    $post_data['ID'] = $property_id;
                    wp_update_post($post_data);
                    if ($property_id && !is_wp_error($property_id)) {
                        $stats['updated']++;
                    }
                }
                wp_reset_postdata();

                if (!$property_id || is_wp_error($property_id)) {
                    $stats['errors']++;
                    continue;
                }
                
                // Оновлення мета-полів об'єкта
                update_post_meta($property_id, 'fok_property_unique_id', $unique_id);
                update_post_meta($property_id, 'fok_property_rc_link', $rc_id);
                update_post_meta($property_id, 'fok_property_section_link', $section_id);
                update_post_meta($property_id, 'fok_property_number', $property_number);
                update_post_meta($property_id, 'fok_property_floor', intval($floor));
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
                
                // Оновлення статусу
                $status_name = sanitize_text_field(trim($row_data['status']));
                if (!empty($status_name)) {
                    $term = get_term_by('name', $status_name, 'status');
                    if ($term && !is_wp_error($term)) {
                        wp_set_object_terms($property_id, $term->term_id, 'status', false);
                    }
                } else {
                    wp_set_object_terms($property_id, null, 'status', false);
                }
                
                // *** ОНОВЛЕНА ЛОГІКА ОБРОБКИ ЗОБРАЖЕНЬ ***
                if (isset($row_data['layout_images']) && !empty(trim($row_data['layout_images']))) {
                    $image_sources = array_map('trim', explode(',', $row_data['layout_images']));
                    $image_ids = [];

                    foreach ($image_sources as $source) {
                        $attachment_id = 0;
                        if (filter_var($source, FILTER_VALIDATE_URL)) {
                            // Спочатку шукаємо зображення за URL
                            $attachment_id = attachment_url_to_postid($source);
                            
                            // Якщо не знайдено, завантажуємо його
                            if (empty($attachment_id)) {
                                require_once(ABSPATH . 'wp-admin/includes/media.php');
                                require_once(ABSPATH . 'wp-admin/includes/file.php');
                                require_once(ABSPATH . 'wp-admin/includes/image.php');
                                $tmp = download_url($source);
                                if (!is_wp_error($tmp)) {
                                    $attachment_id = media_handle_sideload(['name' => basename($source), 'tmp_name' => $tmp], $property_id, $post_title);
                                    if (is_wp_error($attachment_id)) {
                                        @unlink($tmp);
                                    }
                                }
                            }
                        } else {
                            // Якщо це не URL, шукаємо за іменем файлу.
                            // Переконайтеся, що у вас є функція FOK_Utils::get_attachment_id_by_filename
                            $attachment_id = FOK_Utils::get_attachment_id_by_filename($source);
                        }

                        if ($attachment_id && !is_wp_error($attachment_id)) {
                            $image_ids[] = $attachment_id;
                        }
                    }

                    if (!empty($image_ids)) {
                        delete_post_meta($property_id, 'fok_property_layout_images');
                        foreach($image_ids as $img_id) {
                            add_post_meta($property_id, 'fok_property_layout_images', $img_id);
                        }
                    }
                }
                // *** КІНЕЦЬ ОНОВЛЕНОЇ ЛОГІКИ ***
            }
            fclose($handle);
        } else {
            wp_send_json_error(['message' => 'Не вдалося повторно відкрити тимчасовий файл.']);
        }

        $log_messages[] = "Пакет №{$batch_number} оброблено.";

        wp_send_json_success(array_merge($stats, ['log' => implode("\n", $log_messages)]));
    }

    /**
     * AJAX handler for cleaning up the temporary import file.
     * Deletes the specified file from the uploads directory.
     */
    public function cleanup_import_file() {
        check_ajax_referer('fok_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостатньо прав.']);
        }
        
        $filepath = isset($_POST['filepath']) ? sanitize_text_field($_POST['filepath']) : '';
        
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/flat-okbi-importer';

        if (!empty($filepath) && strpos($filepath, $temp_dir) === 0 && file_exists($filepath)) {
            unlink($filepath);
            wp_send_json_success(['message' => 'Тимчасовий файл видалено.']);
        } else {
            wp_send_json_error(['message' => 'Некоректний шлях до файлу.']);
        }
    }

    /**
     * AJAX handler to get a list of properties for the pricing table.
     */
    public function get_properties_for_pricing() {
        check_ajax_referer('fok_pricing_nonce', 'nonce');
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

        if ($property_type === 'apartment' && $rooms !== 'all') {
            if (strpos($rooms, '+') !== false) {
                $meta_query[] = [
                    'key' => 'fok_property_rooms',
                    'value' => intval($rooms),
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                ];
            } else {
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
                    'floor' => get_post_meta($property_id, 'fok_property_floor', true),
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

    /**
     * AJAX handler to save changed prices.
     */
    public function save_price_changes() {
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

    /**
     * AJAX handler to get sections for a selected RC.
     */
    public function get_sections_for_rc() {
        check_ajax_referer( 'fok_pricing_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $rc_id = isset($_POST['rc_id']) ? absint($_POST['rc_id']) : 0;
        if (!$rc_id) {
            wp_send_json_success([]);
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
}
