<?php
// Запобігаємо прямому доступу до файлу.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fok_render_importer_page() {
    ?>
    <div class="wrap">
        <h1><?php _e( 'Імпорт та Експорт', 'okbi-apartments' ); ?></h1>
        
        <?php
        if ( isset( $_GET['fok_import_status'] ) && $_GET['fok_import_status'] === 'success' ) {
            $imported = isset($_GET['imported']) ? intval($_GET['imported']) : 0;
            $updated = isset($_GET['updated']) ? intval($_GET['updated']) : 0;
            $errors = isset($_GET['errors']) ? intval($_GET['errors']) : 0;
            $skipped = isset($_GET['skipped']) ? intval($_GET['skipped']) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Імпорт завершено! Додано: %d, Оновлено: %d, Пропущено: %d, Помилок: %d.', 'okbi-apartments' ), $imported, $updated, $skipped, $errors ) . '</p></div>';
        } elseif ( isset( $_GET['fok_import_status'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>' . __('Помилка імпорту:', 'okbi-apartments') . '</strong> ' . esc_html( urldecode( $_GET['fok_import_status'] ) ) . '</p></div>';
        }
        ?>

        <div id="import-export-wrapper" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="card">
                <h2 class="title"><?php _e( 'Імпорт з CSV', 'okbi-apartments' ); ?></h2>
                <p><strong><?php _e( 'Необхідні колонки:', 'okbi-apartments' ); ?></strong></p>
                <code>unique_id,post_type,rc_name,section_name,property_number,floor,rooms,area,price_per_sqm,total_price,currency,discount_percent,status</code>
                <p><?php _e('Заповнюйте `price_per_sqm` АБО `total_price`. Якщо заповнені обидва, пріоритет у `total_price`.', 'okbi-apartments'); ?></p>
                <p><a href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/properties-example.csv'); ?>" download><?php _e('Завантажити приклад файлу', 'okbi-apartments'); ?></a></p>
                <hr>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                    <input type="hidden" name="action" value="fok_handle_csv_import">
                    <?php wp_nonce_field( 'fok_import_nonce_action', 'fok_import_nonce' ); ?>
                     <input type="file" name="properties_csv" accept=".csv, text/csv">
                    <?php submit_button( __( 'Почати імпорт', 'okbi-apartments' ), 'primary', 'submit-import' ); ?>
                </form>
            </div>
            <div class="card">
                <h2 class="title"><?php _e( 'Експорт в CSV', 'okbi-apartments' ); ?></h2>
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
                        <tr valign="top">
                            <th scope="row"><label><?php _e('Типи нерухомості', 'okbi-apartments'); ?></label></th>
                            <td>
                                <fieldset>
                                    <label><input type="checkbox" name="fok_export_types[]" value="apartment" checked> <?php _e('Квартири', 'okbi-apartments'); ?></label><br>
                                    <label><input type="checkbox" name="fok_export_types[]" value="commercial_property" checked> <?php _e('Комерція', 'okbi-apartments'); ?></label><br>
                                    <label><input type="checkbox" name="fok_export_types[]" value="parking_space" checked> <?php _e('Паркомісця', 'okbi-apartments'); ?></label><br>
                                    <label><input type="checkbox" name="fok_export_types[]" value="storeroom" checked> <?php _e('Комори', 'okbi-apartments'); ?></label>
                                </fieldset>
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

    if ( empty( $_FILES['properties_csv']['tmp_name'] ) ) {
        wp_redirect( add_query_arg( 'fok_import_status', urlencode('Файл не було завантажено.'), $redirect_url ) );
        exit;
    }
    
    $type_map = [
        'квартира' => 'apartment', 'Квартира' => 'apartment',
        'комерція' => 'commercial_property', 'Комерція' => 'commercial_property',
        'комерційне приміщення' => 'commercial_property', 'Комерційне приміщення' => 'commercial_property',
        'паркомісце' => 'parking_space', 'Паркомісце' => 'parking_space',
        'комора' => 'storeroom', 'Комора' => 'storeroom',
    ];
    $csv_file = $_FILES['properties_csv']['tmp_name'];
    $stats = ['imported' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];
    
    $rc_sections_map = [];
    $csv_data_rows = [];

    ini_set('auto_detect_line_endings', TRUE);
    if ( ( $handle = fopen( $csv_file, "r" ) ) === FALSE ) {
        wp_redirect( add_query_arg( 'fok_import_status', urlencode('Не вдалося відкрити файл.'), $redirect_url ) );
        exit;
    }
    
    $first_line = fgets($handle);
    $bom = pack('H*','EFBBBF');
    if (0 === strpos($first_line, $bom)) { $first_line = substr($first_line, 3); }
    $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
    $header = str_getcsv($first_line, $delimiter);

    while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== FALSE ) {
        if ( !is_array($data) || !array_filter($data) ) continue;
        $row_data = array_combine($header, array_pad($data, count($header), ''));
        $csv_data_rows[] = $row_data; 

        $rc_name = sanitize_text_field(trim($row_data['rc_name']));
        $section_name = sanitize_text_field(trim($row_data['section_name']));

        if ( !empty($rc_name) && !empty($section_name) ) {
            if ( !isset($rc_sections_map[$rc_name]) ) {
                $rc_sections_map[$rc_name] = [];
            }
            if ( !in_array($section_name, $rc_sections_map[$rc_name]) ) {
                $rc_sections_map[$rc_name][] = $section_name;
            }
        }
    }
    fclose($handle);

    $master_section_map = [];
    foreach ($rc_sections_map as $rc_name => $section_names) {
        $rc_query = new WP_Query(['post_type' => 'residential_complex', 'post_status' => 'publish', 'title' => $rc_name, 'posts_per_page' => 1]);
        $rc_id = $rc_query->have_posts() ? $rc_query->posts[0]->ID : wp_insert_post(['post_title' => $rc_name, 'post_type' => 'residential_complex', 'post_status' => 'publish']);

        if ($rc_id) {
            $existing_sections_text = get_post_meta($rc_id, 'fok_rc_sections_list', true);
            $existing_names = array_filter(array_map('trim', explode("\n", $existing_sections_text)));
            $all_names = array_unique(array_merge($existing_names, $section_names));
            $new_text = implode("\n", $all_names);
            
            update_post_meta($rc_id, 'fok_rc_sections_list', $new_text);
            $master_section_map[$rc_id] = fok_execute_section_sync($rc_id);
        }
    }

    foreach ($csv_data_rows as $row_data) {
        
        // --- ВИПРАВЛЕНО: Зчитуємо ВСІ дані з $row_data на другому етапі ---
        $unique_id        = sanitize_text_field(trim($row_data['unique_id']));
        $post_type_name   = trim($row_data['post_type']);
        $post_type        = $type_map[$post_type_name] ?? null;

        if (empty($unique_id) || empty($post_type)) {
            $stats['skipped']++;
            continue;
        }

        $rc_name          = sanitize_text_field(trim($row_data['rc_name']));
        $section_name     = sanitize_text_field(trim($row_data['section_name']));
        $property_number  = sanitize_text_field(trim($row_data['property_number']));
        $floor            = intval($row_data['floor'] ?? 0);
        $rooms            = intval($row_data['rooms'] ?? 0);
        $area             = floatval(str_replace(',', '.', ($row_data['area'] ?? 0)));
        $price_per_sqm    = floatval(str_replace(',', '.', ($row_data['price_per_sqm'] ?? 0)));
        $total_price      = floatval(str_replace(',', '.', ($row_data['total_price'] ?? 0)));
        $discount_percent = floatval(str_replace(',', '.', ($row_data['discount_percent'] ?? 0)));
        $currency         = sanitize_text_field(strtoupper(trim($row_data['currency'])));
        $status_name      = sanitize_text_field(trim($row_data['status']));

        $rc_post = get_page_by_title($rc_name, OBJECT, 'residential_complex');
        if (!$rc_post) { $stats['errors']++; continue; }
        $rc_id = $rc_post->ID;

        $section_id = $master_section_map[$rc_id][$section_name] ?? 0;
        if (!$section_id) { $stats['errors']++; continue; }

        $type_names_for_title = ['apartment' => 'Квартира', 'commercial_property' => 'Комерція', 'parking_space' => 'Паркомісце', 'storeroom' => 'Комора'];
        $post_title = ($type_names_for_title[$post_type] ?? 'Об\'єкт') . ' №' . $property_number;
        
        $property_query = new WP_Query(['post_type' => array_values(array_unique($type_map)), 'post_status' => 'any', 'posts_per_page' => 1, 'meta_query' => [['key' => 'fok_property_unique_id', 'value' => $unique_id]]]);

        $post_data = ['post_title' => $post_title, 'post_status' => 'publish', 'post_type' => $post_type];
        if ( !$property_query->have_posts() ) {
            $property_id = wp_insert_post($post_data);
            $stats['imported']++;
        } else {
            $property_id = $property_query->posts[0]->ID;
            $post_data['ID'] = $property_id;
            wp_update_post($post_data);
            $stats['updated']++;
        }
        wp_reset_postdata();

        if (!$property_id || is_wp_error($property_id)) { $stats['errors']++; continue; }

        update_post_meta($property_id, 'fok_property_unique_id', $unique_id);
        update_post_meta($property_id, 'fok_property_rc_link', $rc_id);
        update_post_meta($property_id, 'fok_property_section_link', $section_id);
        update_post_meta($property_id, 'fok_property_number', $property_number);
        update_post_meta($property_id, 'fok_property_floor', $floor);
        update_post_meta($property_id, 'fok_property_area', $area);
        update_post_meta($property_id, 'fok_property_price_per_sqm', $price_per_sqm);
        update_post_meta($property_id, 'fok_property_total_price_manual', $total_price);
        update_post_meta($property_id, 'fok_property_discount_percent', $discount_percent);
        update_post_meta($property_id, 'fok_property_currency', $currency);
        
        if ($post_type === 'apartment') {
            update_post_meta($property_id, 'fok_property_rooms', $rooms);
        }

        if (!empty($status_name)) {
            $term = get_term_by('name', $status_name, 'status');
            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($property_id, $term->term_id, 'status', false);
            }
        } else {
            wp_set_object_terms($property_id, null, 'status', false);
        }
    }

    ini_set('auto_detect_line_endings', FALSE);
    wp_redirect( add_query_arg( ['fok_import_status' => 'success'] + $stats, $redirect_url ) );
    exit;
}
add_action( 'admin_post_fok_handle_csv_import', 'fok_handle_csv_upload' );


function fok_handle_csv_export() {
    if ( ! isset( $_POST['submit-export'] ) ) return;
    if ( ! isset( $_POST['fok_export_nonce'] ) || ! wp_verify_nonce( $_POST['fok_export_nonce'], 'fok_export_nonce_action' ) ) return;
    if ( ! current_user_can('manage_options') ) return;

    $rc_id = isset($_POST['fok_export_rc_id']) ? $_POST['fok_export_rc_id'] : 'all';
    $post_types = isset($_POST['fok_export_types']) && is_array($_POST['fok_export_types']) ? $_POST['fok_export_types'] : ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
    
    if (empty($post_types)) return;

    $args = ['post_type' => $post_types, 'posts_per_page' => -1, 'post_status' => 'any'];
    if ($rc_id !== 'all') {
        $args['meta_query'] = [['key' => 'fok_property_rc_link', 'value' => intval($rc_id)]];
    }
    
    $properties = get_posts($args);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=properties-export-' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    fputcsv($output, ['unique_id','post_type','rc_name','section_name','property_number','floor','rooms','area','price_per_sqm','total_price','currency','discount_percent','status']);
    
    $type_names = [
        'apartment' => 'Квартира', 'commercial_property' => 'Комерція',
        'parking_space' => 'Паркомісце', 'storeroom' => 'Комора',
    ];

    foreach ($properties as $property) {
        $property_id = $property->ID;
        $post_type = $property->post_type;
        $rc_id = get_post_meta($property_id, 'fok_property_rc_link', true);
        $section_id = get_post_meta($property_id, 'fok_property_section_link', true);
        
        $row = [
            get_post_meta($property_id, 'fok_property_unique_id', true),
            $type_names[$post_type] ?? $post_type,
            $rc_id ? html_entity_decode(get_the_title($rc_id)) : '',
            $section_id ? html_entity_decode(get_the_title($section_id)) : '',
            get_post_meta($property_id, 'fok_property_number', true),
            get_post_meta($property_id, 'fok_property_floor', true),
            ($post_type === 'apartment') ? get_post_meta($property_id, 'fok_property_rooms', true) : '',
            get_post_meta($property_id, 'fok_property_area', true),
            get_post_meta($property_id, 'fok_property_price_per_sqm', true),
            get_post_meta($property_id, 'fok_property_total_price_manual', true),
            get_post_meta($property_id, 'fok_property_currency', true),
            get_post_meta($property_id, 'fok_property_discount_percent', true),
            get_the_terms($property_id, 'status') ? get_the_terms($property_id, 'status')[0]->name : ''
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
add_action( 'admin_init', 'fok_handle_csv_export');