<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class FOK_Assets
 *
 * Handles loading of all scripts and styles.
 */
class FOK_Assets {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style('fok-frontend-style', plugin_dir_url( __FILE__ ) . '../../assets/css/frontend-style.css', [], time());

        $options = get_option('fok_global_settings');
        $site_key = $options['recaptcha_site_key'] ?? '';

        if (!empty($site_key)) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key), [], null, true);
        }

        wp_enqueue_script('fok-frontend-script', plugin_dir_url( __FILE__ ) . '../../assets/js/frontend-script.js', ['jquery'], time(), true);

        wp_localize_script( 'fok-frontend-script', 'fok_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'fok_viewer_nonce' ),
            'recaptcha_site_key' => $site_key
        ]);

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
        
        // The fok_get_icon function is now in the FOK_Utils class.
        // We will call it statically.
        wp_localize_script( 'fok-frontend-script', 'fok_icons', [
            'parking' => FOK_Utils::get_icon('parking'),
        ]);
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts( $hook_suffix ) {
        // 1. Скрипти для сторінок налаштувань та імпорту
        if ( 'toplevel_page_flat_okbi_settings' === $hook_suffix ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_media();
            wp_enqueue_script('fok-admin-script', plugin_dir_url( __FILE__ ) . '../../assets/js/admin-settings.js', array( 'wp-color-picker', 'jquery' ), time(), true);
        }

        if ( 'flat-okbi_page_fok_pricing' === $hook_suffix ) {
            wp_enqueue_style( 'fok-admin-pricing-style', plugin_dir_url( __FILE__ ) . '../../assets/css/admin-pricing.css', [], time() );
            wp_enqueue_script( 'fok-admin-pricing-script', plugin_dir_url( __FILE__ ) . '../../assets/js/admin-pricing.js', ['jquery'], time(), true );
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
        
        if ( 'flat-okbi_page_fok_import' === $hook_suffix ) {
            wp_enqueue_script('fok-admin-importer-script', plugin_dir_url(__FILE__) . '../../assets/js/admin-importer.js', ['jquery'], time(), true);
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_media();
            wp_enqueue_script('fok-admin-settings-script', plugin_dir_url( __FILE__ ) . '../../assets/js/admin-settings.js', ['jquery', 'wp-color-picker', 'wp-mediaelement'], time(), true);

            wp_localize_script('fok-admin-importer-script', 'fok_importer_page', [
                'nonce' => wp_create_nonce('fok_delete_all_nonce'),
                'confirm_delete' => __('Ви впевнені, що хочете видалити ВСІ житлові комплекси, секції, об\'єкти та заявки? Ця дія незворотна.', 'okbi-apartments'),
                'confirm_delete_final' => __('БУДЬ ЛАСКА, ПІДТВЕРДІТЬ. Всі дані плагіна буде видалено назавжди.', 'okbi-apartments'),
                'deleting' => __('Видалення...', 'okbi-apartments'),
                'error_prefix' => __('Помилка: ', 'okbi-apartments'),
                'server_error' => __('Помилка сервера.', 'okbi-apartments'),
            ]);
            wp_localize_script('fok-admin-importer-script', 'fok_importer_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fok_import_nonce')
            ]);
        }

        if ( 'flat-okbi_page_fok_documentation' === $hook_suffix ) {
            wp_enqueue_style( 'fok-admin-docs-style', plugin_dir_url( __FILE__ ) . '../../assets/css/admin-docs.css', [], time() );
        }

        if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
            return;
        }

        $current_screen = get_current_screen();
        if ( ! isset( $current_screen->post_type ) ) {
            return;
        }

        if ( 'section' === $current_screen->post_type ) {
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_style( 'fok-admin-grid-editor-style', plugin_dir_url( __FILE__ ) . '../../assets/css/admin-grid-editor.css', [], time() );
            wp_enqueue_script( 'fok-admin-grid-editor-script', plugin_dir_url( __FILE__ ) . '../../assets/js/admin-grid-editor.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'], time(), true );
            wp_localize_script( 'fok-admin-grid-editor-script', 'fok_editor_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'fok_grid_editor_nonce_action' )
            ]);
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

            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_style( 'wp-jquery-ui-dialog' );
            wp_enqueue_style( 'fok-admin-floor-plan-editor-style', plugin_dir_url( __FILE__ ) . '../../assets/css/admin-floor-plan-editor.css', [], time() );
            wp_enqueue_script( 'fok-admin-groups-script', plugin_dir_url( __FILE__ ) . '../../assets/js/admin-groups.js', ['jquery', 'jquery-ui-sortable', 'jquery-ui-dialog'], time(), true );
            
            wp_localize_script('fok-admin-groups-script', 'fok_groups_data', [
                'nonce'      => wp_create_nonce('fok_floor_plans_nonce'),
                'post_id'    => get_the_ID(),
            ]);
            wp_localize_script('fok-admin-groups-script', 'fok_groups_i10n', [
                'confirm_delete_floor' => __('Ви впевнені, що хочете видалити цей поверх?', 'okbi-apartments'),
                'save_and_close' => __('Зберегти і закрити', 'okbi-apartments'),
                'cancel' => __('Скасувати', 'okbi-apartments'),
                'no_objects_on_floor' => __('Немає об\'єктів на цьому поверсі.', 'okbi-apartments'),
                'specify_floor_number' => __('Вкажіть номер поверху.', 'okbi-apartments'),
                'confirm_delete_polygon' => __('Видалити полігон?', 'okbi-apartments'),
            ]);
        }

        $property_post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
        if ( in_array( $current_screen->post_type, $property_post_types ) ) {
            wp_enqueue_script( 'fok-admin-logic', plugin_dir_url( __FILE__ ) . '../../assets/js/admin-logic.js', ['jquery', 'select2'], time(), true );
        }
        
        if ( 'residential_complex' === $current_screen->post_type ) {
            wp_enqueue_script( 'fok-admin-rc-page-script', plugin_dir_url( __FILE__ ) . '../../assets/js/admin-rc-page.js', ['jquery'], time(), true );
        }
    }
}
