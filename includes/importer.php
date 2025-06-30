<?php
// includes/importer.php

// Запобігаємо прямому доступу до файлу.
if (!defined('ABSPATH')) {
    exit;
}

function fok_render_importer_page()
{
?>
    <div class="wrap">
        <h1><?php _e('Імпорт та Експорт', 'okbi-apartments'); ?></h1>

        <div id="fok-importer-message" class="notice is-dismissible" style="display: none;"></div>
        
        <div id="import-export-wrapper" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            
            <?php // Картка імпорту об'єктів ?>
            <div class="card">
                <h2 class="title"><?php _e('Імпорт Об\'єктів з CSV', 'okbi-apartments'); ?></h2>
                
                <p><?php _e('У колонці `layout_images` вказуйте імена файлів зображень (з розширенням), розділені комою. Зображення мають бути попередньо завантажені у Медіа-бібліотеку.', 'okbi-apartments'); ?></p>
                <p><a href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/properties-example.csv'); ?>" download><?php _e('Завантажити приклад файлу', 'okbi-apartments'); ?></a></p>
                <hr>
                
                <form id="fok-importer-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('fok_import_nonce_action', 'fok_import_nonce'); ?>
                    <p>
                        <label for="fok_properties_csv"><?php _e('Виберіть CSV-файл для імпорту:', 'okbi-apartments'); ?></label><br>
                        <input type="file" id="fok_properties_csv" name="properties_csv" accept=".csv, text/csv">
                    </p>
                    <button type="submit" id="submit-import-ajax" class="button button-primary"><?php _e('Почати імпорт', 'okbi-apartments'); ?></button>
                </form>

                <div id="fok-importer-status" style="display: none; margin-top: 20px;">
                    <div class="fok-progress-wrapper" style="background-color: #eee; border: 1px solid #ccc; padding: 2px; border-radius: 4px; position: relative; height: 24px;">
                        <div class="fok-progress-bar-inner" style="background-color: #0073aa; width: 0%; height: 100%; transition: width 0.3s ease;"></div>
                        <span class="fok-progress-text" style="position: absolute; top: 0; left: 0; width: 100%; text-align: center; color: #333; font-weight: bold; line-height: 24px; text-shadow: 1px 1px #fff;"><?php _e('0%', 'okbi-apartments'); ?></span>
                    </div>
                    <div class="fok-log-console" style="margin-top: 15px; max-height: 250px; overflow-y: scroll; background: #fafafa; border: 1px solid #e0e0e0; padding: 10px; font-family: monospace; font-size: 12px; white-space: pre-wrap;">
                    </div>
                </div>
            </div>

            <?php // Картка експорту об'єктів ?>
            <div class="card">
                <h2 class="title"><?php _e('Експорт Об\'єктів в CSV', 'okbi-apartments'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="action" value="fok_handle_csv_export">
                    <?php wp_nonce_field('fok_export_nonce_action', 'fok_export_nonce'); ?>
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
                    <?php submit_button(__('Експортувати Об\'єкти', 'okbi-apartments'), 'secondary', 'submit-export'); ?>
                </form>
            </div>

            <?php // Картка небезпечної зони ?>
            <div class="card" style="border-color: #dc3232;">
                <h2 class="title" style="color: #dc3232;"><?php _e('Небезпечна зона', 'okbi-apartments'); ?></h2>
                <p><?php _e('Ця дія назавжди видалить усі житлові комплекси, секції, об\'єкти нерухомості та заявки, створені цим плагіном. Будьте обережні, відновити дані буде неможливо.', 'okbi-apartments'); ?></p>
                <button type="button" class="button button-danger" id="fok-delete-all-data-btn"><?php _e('Видалити всі дані плагіна', 'okbi-apartments'); ?></button>
                <p id="fok-delete-status" style="margin-top: 15px; font-weight: bold;"></p>
            </div>

            <?php // Картка для експорту заявок ?>
            <div class="card">
                <h2 class="title"><?php _e('Експорт Заявок в CSV', 'okbi-apartments'); ?></h2>
                <p><?php _e('Ця дія експортує всі заявки (ліди), що є у системі, у єдиний CSV-файл.', 'okbi-apartments'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="action" value="fok_handle_leads_export">
                    <?php wp_nonce_field('fok_leads_export_nonce_action', 'fok_leads_export_nonce'); ?>
                    <?php submit_button(__('Експортувати Заявки', 'okbi-apartments'), 'secondary', 'submit-leads-export'); ?>
                </form>
            </div>
        </div>
        
    </div>
    <style>
        .button-danger { background-color: #dc3232; border-color: #dc3232; color: #fff; }
        .button-danger:hover, .button-danger:focus { background-color: #a02728; border-color: #a02728; color: #fff; }
        .log-entry.log-error { color: #dc3232; }
        .log-entry.log-success { color: #28a745; }
        .log-entry.log-final-success { color: #28a745; font-weight: bold; }
        .log-entry.log-info { color: #555; }
    </style>
<?php
}

function fok_get_attachment_id_by_filename($filename)
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


function fok_handle_csv_export()
{
    if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'fok_handle_csv_export') return;
    if (!isset($_POST['fok_export_nonce'])) return;
    if (!wp_verify_nonce($_POST['fok_export_nonce'], 'fok_export_nonce_action')) return;
    if (!current_user_can('manage_options')) return;

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
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    fputcsv($output, ['unique_id', 'post_type', 'rc_name', 'section_name', 'property_number', 'floor', 'grid_column_start', 'grid_column_span', 'grid_row_span', 'rooms', 'area', 'price_per_sqm', 'total_price', 'currency', 'discount_percent', 'status', 'layout_images']);

    $type_names = [
        'apartment' => 'Квартира', 'commercial_property' => 'Комерція',
        'parking_space' => 'Паркомісце', 'storeroom' => 'Комора',
    ];

    foreach ($properties as $property) {
        $property_id = $property->ID;
        $post_type = $property->post_type;
        $rc_id_meta = get_post_meta($property_id, 'fok_property_rc_link', true);
        $section_id = get_post_meta($property_id, 'fok_property_section_link', true);

        $image_ids = get_post_meta($property_id, 'fok_property_layout_images', false);
        $image_filenames = [];
        if (!empty($image_ids)) {
            foreach ($image_ids as $image_id) {
                $filepath = get_attached_file((int) $image_id);
                if ($filepath) {
                    $image_filenames[] = basename($filepath);
                }
            }
        }

        $row = [
            get_post_meta($property_id, 'fok_property_unique_id', true),
            $type_names[$post_type] ?? $post_type,
            $rc_id_meta ? html_entity_decode(get_the_title($rc_id_meta)) : '',
            $section_id ? html_entity_decode(get_the_title($section_id)) : '',
            get_post_meta($property_id, 'fok_property_number', true),
            get_post_meta($property_id, 'fok_property_floor', true),
            get_post_meta($property_id, 'fok_property_grid_column_start', true),
            get_post_meta($property_id, 'fok_property_grid_column_span', true),
            get_post_meta($property_id, 'fok_property_grid_row_span', true),
            ($post_type === 'apartment') ? get_post_meta($property_id, 'fok_property_rooms', true) : '',
            get_post_meta($property_id, 'fok_property_area', true),
            get_post_meta($property_id, 'fok_property_price_per_sqm', true),
            get_post_meta($property_id, 'fok_property_total_price_manual', true),
            get_post_meta($property_id, 'fok_property_currency', true),
            get_post_meta($property_id, 'fok_property_discount_percent', true),
            get_the_terms($property_id, 'status') ? get_the_terms($property_id, 'status')[0]->name : '',
            implode(',', $image_filenames)
        ];
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function fok_handle_leads_export() {
    if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'fok_handle_leads_export') return;
    if (!isset($_POST['fok_leads_export_nonce'])) return;
    if (!wp_verify_nonce($_POST['fok_leads_export_nonce'], 'fok_leads_export_nonce_action')) return;
    if (!current_user_can('manage_options')) return;

    $leads_query = new WP_Query([
        'post_type' => 'fok_lead',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $leads = $leads_query->posts;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=leads-export-' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    fputcsv($output, [
        'Дата заявки',
        'Статус',
        'Ім\'я клієнта',
        'Телефон',
        'ЖК',
        'Секція',
        'Об\'єкт',
        'Коментар менеджера'
    ]);

    $status_names = [
        'new' => 'Нова',
        'in_progress' => 'В обробці',
        'success' => 'Успішно',
        'failed' => 'Відмова',
    ];

    foreach ($leads as $lead) {
        $lead_id = $lead->ID;
        
        $status_slug = get_post_meta($lead_id, '_lead_status', true);
        $rc_id = get_post_meta($lead_id, '_lead_rc_id', true);
        $section_id = get_post_meta($lead_id, '_lead_section_id', true);
        $property_id = get_post_meta($lead_id, '_lead_property_id', true);

        $row = [
            get_the_date('Y-m-d H:i:s', $lead_id),
            $status_names[$status_slug] ?? $status_slug,
            get_post_meta($lead_id, '_lead_name', true),
            get_post_meta($lead_id, '_lead_phone', true),
            $rc_id ? html_entity_decode(get_the_title($rc_id)) : '',
            $section_id ? html_entity_decode(get_the_title($section_id)) : '',
            $property_id ? html_entity_decode(get_the_title($property_id)) : '',
            get_post_meta($lead_id, '_lead_manager_comment', true),
        ];
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

add_action('admin_init', function () {
    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'fok_handle_csv_export') {
        fok_handle_csv_export();
    }
    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'fok_handle_leads_export') {
        fok_handle_leads_export();
    }
});