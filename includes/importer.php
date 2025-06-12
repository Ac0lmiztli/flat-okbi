<?php
// Запобігаємо прямому доступу до файлу.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Рендерить сторінку імпорту та експорту квартир.
 */
function fok_render_importer_page() {
    ?>
    <div class="wrap">
        <h1><?php _e( 'Імпорт та Експорт', 'okbi-apartments' ); ?></h1>
        
        <?php
        // Повідомлення про результат імпорту
        if ( isset( $_GET['fok_import_status'] ) && $_GET['fok_import_status'] === 'success' ) {
            $imported = isset($_GET['imported']) ? intval($_GET['imported']) : 0;
            $updated = isset($_GET['updated']) ? intval($_GET['updated']) : 0;
            $errors = isset($_GET['errors']) ? intval($_GET['errors']) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Імпорт завершено! Додано: %d, Оновлено: %d, Помилок: %d.', 'okbi-apartments' ), $imported, $updated, $errors ) . '</p></div>';
        } elseif ( isset( $_GET['fok_import_status'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>' . __('Помилка імпорту:', 'okbi-apartments') . '</strong> ' . esc_html( urldecode( $_GET['fok_import_status'] ) ) . '</p></div>';
        }
        ?>

        <div id="import-export-wrapper" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="card">
                <h2 class="title"><?php _e( 'Імпорт з CSV', 'okbi-apartments' ); ?></h2>
                <p><strong><?php _e( 'Крок 1: Підготуйте ваш CSV-файл', 'okbi-apartments' ); ?></strong></p>
                <p><?php _e( 'Файл має бути у форматі CSV з кодуванням UTF-8. Перша колонка `apartment_id` є обов\'язковою.', 'okbi-apartments' ); ?></p>
                <p><strong><?php _e( 'Необхідні колонки:', 'okbi-apartments' ); ?></strong></p>
                <code>apartment_id,jk_name,section_name,apartment_number,floor,rooms,area,price,currency,status</code>
                <p><a href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/example.csv'); ?>" download><?php _e('Завантажити приклад файлу', 'okbi-apartments'); ?></a></p>
                <hr>
                <p><strong><?php _e( 'Крок 2: Завантажте файл', 'okbi-apartments' ); ?></strong></p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'fok_import_nonce_action', 'fok_import_nonce' ); ?>
                     <input type="file" name="apartments_csv" accept=".csv, text/csv">
                    <?php submit_button( __( 'Почати імпорт', 'okbi-apartments' ), 'primary', 'submit-import' ); ?>
                </form>
            </div>
            <div class="card">
                <h2 class="title"><?php _e( 'Експорт в CSV', 'okbi-apartments' ); ?></h2>
                <p><?php _e('Ви можете вивантажити дані по всіх квартирах або по конкретному ЖК.', 'okbi-apartments'); ?></p>
                <form method="post">
                     <?php wp_nonce_field( 'fok_export_nonce_action', 'fok_export_nonce' ); ?>
                    <table class="form-table">
                         <tr valign="top">
                            <th scope="row"><label for="fok_export_rc_id"><?php _e('Виберіть ЖК', 'okbi-apartments'); ?></label></th>
                            <td>
                                <select name="fok_export_rc_id" id="fok_export_rc_id">
                                    <option value="all"><?php _e('Всі житлові комплекси', 'okbi-apartments'); ?></option>
                                    <?php
                                    $all_rcs = get_posts(['post_type' => 'residential_complex', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
                                    foreach ($all_rcs as $rc) {
                                        echo '<option value="' . esc_attr($rc->ID) . '">' . esc_html($rc->post_title) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                     <?php submit_button( __( 'Експортувати в CSV', 'okbi-apartments' ), 'secondary', 'submit-export' ); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function fok_handle_csv_upload() {
    if ( ! isset( $_POST['submit-import'] ) ) return;
    if ( ! isset( $_POST['fok_import_nonce'] ) || ! wp_verify_nonce( $_POST['fok_import_nonce'], 'fok_import_nonce_action' ) ) return;
    if ( ! current_user_can('manage_options') ) return;

    $redirect_url = admin_url( 'admin.php?page=flat_okbi_import' );

    if ( empty( $_FILES['apartments_csv']['tmp_name'] ) ) {
        wp_redirect( add_query_arg( 'fok_import_status', urlencode('Файл не було завантажено.'), $redirect_url ) );
        exit;
    }

    ini_set('auto_detect_line_endings', TRUE);
    $csv_file = $_FILES['apartments_csv']['tmp_name'];
    $stats = ['imported' => 0, 'updated' => 0, 'errors' => 0];
    
    if ( ( $handle = fopen( $csv_file, "r" ) ) !== FALSE ) {
        $first_line = fgets($handle);
        $bom = pack('H*','EFBBBF');
        if (0 === strpos($first_line, $bom)) { $first_line = substr($first_line, 3); }
        $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
        $header = str_getcsv($first_line, $delimiter);

        if(count($header) < 10) {
             wp_redirect( add_query_arg( 'fok_import_status', urlencode('Неправильний формат заголовка CSV.'), $redirect_url ) );
             exit;
        }

        while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== FALSE ) {
            if ( !is_array($data) || !array_filter($data) ) continue;
            if (count($data) < 10) { $stats['errors']++; continue; }

            $unique_id        = sanitize_text_field( trim($data[0]) );
            $jk_name          = sanitize_text_field( trim($data[1]) );
            $section_name     = sanitize_text_field( trim($data[2]) );
            $apartment_number = sanitize_text_field( trim($data[3]) );
            $floor            = intval( $data[4] );
            $rooms            = intval( $data[5] );
            $area             = floatval( str_replace(',', '.', $data[6]) );
            $price            = floatval( str_replace(',', '.', $data[7]) );
            $currency         = sanitize_text_field( strtoupper(trim($data[8])) );
            $status_name      = sanitize_text_field( trim($data[9]) );

            if (empty($unique_id) || empty($jk_name) || empty($section_name)) { $stats['errors']++; continue; }

            $jk_query = new WP_Query(['post_type' => 'residential_complex', 'post_status' => 'publish', 'title' => $jk_name, 'posts_per_page' => 1]);
            $jk_id = $jk_query->have_posts() ? $jk_query->posts[0]->ID : wp_insert_post(['post_title' => $jk_name, 'post_type' => 'residential_complex', 'post_status' => 'publish']);
            
            $section_query = new WP_Query(['post_type' => 'section', 'post_status' => 'publish', 'title' => $section_name, 'posts_per_page' => 1, 'meta_query' => [['key' => 'fok_section_rc_link', 'value' => $jk_id]]]);
            $section_id = $section_query->have_posts() ? $section_query->posts[0]->ID : wp_insert_post(['post_title' => $section_name, 'post_type' => 'section', 'post_status' => 'publish']);
            if ( !$section_query->have_posts() ) { update_post_meta($section_id, 'fok_section_rc_link', $jk_id); }
            
            $apartment_query = new WP_Query(['post_type' => 'apartment', 'post_status' => 'any', 'posts_per_page' => 1, 'meta_query' => [['key' => 'fok_apartment_unique_id', 'value' => $unique_id]]]);
            
            $post_data = ['post_title' => 'Квартира №' . $apartment_number, 'post_status' => 'publish', 'post_type' => 'apartment'];
            if ( !$apartment_query->have_posts() ) {
                $apartment_id = wp_insert_post($post_data);
                $stats['imported']++;
            } else {
                $apartment_id = $apartment_query->posts[0]->ID;
                $post_data['ID'] = $apartment_id;
                wp_update_post($post_data);
                $stats['updated']++;
            }
            wp_reset_postdata();

            update_post_meta($apartment_id, 'fok_apartment_unique_id', $unique_id);
            update_post_meta($apartment_id, 'fok_apartment_rc_link', $jk_id);
            update_post_meta($apartment_id, 'fok_apartment_section_link', $section_id);
            update_post_meta($apartment_id, 'fok_apartment_number', $apartment_number);
            update_post_meta($apartment_id, 'fok_apartment_floor', $floor);
            update_post_meta($apartment_id, 'fok_apartment_rooms', $rooms);
            update_post_meta($apartment_id, 'fok_apartment_area', $area);
            update_post_meta($apartment_id, 'fok_apartment_price', ['value' => $price, 'currency' => $currency]);
            
            if (!empty($status_name)) {
                $term = get_term_by('name', $status_name, 'status');
                if ($term && !is_wp_error($term)) {
                    wp_set_object_terms($apartment_id, $term->term_id, 'status', false);
                } else { $stats['errors']++; }
            } else {
                wp_set_object_terms($apartment_id, null, 'status', false);
            }
        }
        fclose( $handle );
    }

    ini_set('auto_detect_line_endings', FALSE);
    wp_redirect( add_query_arg( ['fok_import_status' => 'success'] + $stats, $redirect_url ) );
    exit;
}
add_action( 'admin_init', 'fok_handle_csv_upload' );

function fok_handle_csv_export() {
    if ( ! isset( $_POST['submit-export'] ) ) return;
    if ( ! isset( $_POST['fok_export_nonce'] ) || ! wp_verify_nonce( $_POST['fok_export_nonce'], 'fok_export_nonce_action' ) ) return;
    if ( ! current_user_can('manage_options') ) return;

    $rc_id = isset($_POST['fok_export_rc_id']) ? $_POST['fok_export_rc_id'] : 'all';
    $args = ['post_type' => 'apartment', 'posts_per_page' => -1, 'post_status' => 'publish'];
    if ($rc_id !== 'all') {
        $args['meta_query'] = [['key' => 'fok_apartment_rc_link', 'value' => intval($rc_id)]];
    }
    
    $apartments = get_posts($args);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=apartments-export-' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['apartment_id', 'jk_name', 'section_name', 'apartment_number', 'floor', 'rooms', 'area', 'price', 'currency', 'status']);
    
    foreach ($apartments as $apartment) {
        $apartment_id = $apartment->ID;
        $jk_id = get_post_meta($apartment_id, 'fok_apartment_rc_link', true);
        $section_id = get_post_meta($apartment_id, 'fok_apartment_section_link', true);
        $price_group = get_post_meta($apartment_id, 'fok_apartment_price', true);
        $status_terms = get_the_terms($apartment_id, 'status');

        $row = [
            get_post_meta($apartment_id, 'fok_apartment_unique_id', true),
            $jk_id ? html_entity_decode(get_the_title($jk_id)) : '',
            $section_id ? html_entity_decode(get_the_title($section_id)) : '',
            get_post_meta($apartment_id, 'fok_apartment_number', true),
            get_post_meta($apartment_id, 'fok_apartment_floor', true),
            get_post_meta($apartment_id, 'fok_apartment_rooms', true),
            get_post_meta($apartment_id, 'fok_apartment_area', true),
            $price_group['value'] ?? '',
            $price_group['currency'] ?? '',
            !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0]->name : ''
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
add_action( 'admin_init', 'fok_handle_csv_export');
