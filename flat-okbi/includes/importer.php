<?php
// includes/importer.php

// Запобігаємо прямому доступу до файлу.
if (!defined('ABSPATH')) {
    exit;
}

function fok_render_importer_page($fok_admin)
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
                <p>
                    <?php 
                    printf(
                        // Використовуємо printf для зручного вставлення посилання
                        wp_kses_post( __( 'Не знаєте, з чого почати? Перегляньте детальну <a href="%s">документацію по імпорту</a>.', 'okbi-apartments' ) ),
                        esc_url( admin_url( 'admin.php?page=flat_okbi_docs' ) )
                    ); 
                    ?>
                </p>
                
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
                                    $all_rcs = $fok_admin->get_all_rcs_cached();
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