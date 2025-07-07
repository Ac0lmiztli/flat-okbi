<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class FOK_Admin
 *
 * Handles all admin-side functionality, including meta boxes, admin columns,
 * list table filters, and settings pages.
 */
class FOK_Admin {

    /**
     * FOK_Admin constructor.
     */
    public function __construct() {
        // Core Admin Hooks
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'check_meta_box_dependency' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_pages' ] );

        // CPT/Taxonomy Admin List Enhancements
        add_filter( 'use_block_editor_for_post_type', [ $this, 'disable_gutenberg' ], 10, 2 );
        add_action( 'admin_menu', [ $this, 'remove_default_status_metabox' ] );
        add_action( 'restrict_manage_posts', [ $this, 'add_admin_list_filters' ] );
        add_action( 'parse_query', [ $this, 'filter_admin_list_query' ] );

        // Meta Box Registration
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );

        // Post Save Hooks
        add_action( 'save_post', [ $this, 'generate_unique_id_on_save' ], 10, 1 );
        add_action( 'save_post', [ $this, 'autogenerate_property_title_on_save' ], 20, 1 );
        add_action( 'save_post_residential_complex', [ $this, 'sync_sections_on_rc_save' ] );
        add_action( 'save_post_fok_lead', [ $this, 'sync_property_status_on_lead_save' ], 10, 2 );

        // Lead CPT Columns
        add_filter('manage_fok_lead_posts_columns', [ $this, 'set_lead_columns' ]);
        add_action('manage_fok_lead_posts_custom_column', [ $this, 'render_lead_columns' ], 10, 2);

        // Property CPT Columns
        $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
        foreach ($property_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", [ $this, 'set_property_columns' ]);
            add_action("manage_{$post_type}_posts_custom_column", [ $this, 'render_property_columns' ], 10, 2);
            add_filter("manage_edit-{$post_type}_sortable_columns", [ $this, 'make_property_columns_sortable' ]);
        }

        // Export handlers
        add_action('admin_post_fok_handle_csv_export', [ $this, 'handle_csv_export' ]);
        add_action('admin_post_fok_handle_leads_export', [ $this, 'handle_leads_export' ]);
        add_action('admin_post_fok_handle_delete_all', [$this, 'handle_delete_all_data']);

        // Cache management
        add_action( 'save_post_residential_complex', [ $this, 'clear_rc_cache_on_save' ] );
        add_action( 'delete_post', [ $this, 'clear_rc_cache_on_save' ] );

        // Custom sorting for property lists
        add_filter('posts_orderby', [$this, 'custom_property_orderby'], 10, 2);
    }

    /**
     * Register all admin pages for the plugin.
     */
    public function register_admin_pages() {
        add_menu_page(
            __( 'Flat Okbi', 'okbi-apartments' ),
            __( 'Flat Okbi', 'okbi-apartments' ),
            'manage_options',
            'fok_dashboard',
            [ $this, 'render_importer_page' ],
            'dashicons-admin-home',
            20
        );
        add_submenu_page(
            'fok_dashboard',
            __( 'Імпорт/Експорт', 'okbi-apartments' ),
            __( 'Імпорт/Експорт', 'okbi-apartments' ),
            'manage_options',
            'fok_import',
            [ $this, 'render_importer_page' ]
        );
        add_submenu_page(
            'fok_dashboard',
            __( 'Керування цінами', 'okbi-apartments' ),
            __( 'Керування цінами', 'okbi-apartments' ),
            'manage_options',
            'fok_pricing',
            [ $this, 'render_pricing_page' ]
        );
        add_submenu_page(
            'fok_dashboard',
            __( 'Налаштування', 'okbi-apartments' ),
            __( 'Налаштування', 'okbi-apartments' ),
            'manage_options',
            'fok_settings',
            [ $this, 'render_settings_page' ]
        );
        add_submenu_page(
            'fok_dashboard',
            __( 'Документація', 'okbi-apartments' ),
            __( 'Документація', 'okbi-apartments' ),
            'manage_options',
            'fok_documentation',
            [ $this, 'render_documentation_page' ]
        );
    }

    /**
     * Renders the Importer/Exporter page.
     */
    public function render_importer_page() {
        require_once FOK_PLUGIN_PATH . 'includes/importer.php';
        fok_render_importer_page($this);
    }

    /**
     * Renders the Pricing management page
     */
    public function render_pricing_page() {
        require_once FOK_PLUGIN_PATH . 'includes/pricing-page.php';
        fok_render_pricing_page($this);
    }

    /**
     * Renders the Documentation page
     */
    public function render_documentation_page() {
        require_once FOK_PLUGIN_PATH . 'includes/documentation-page.php';
        fok_render_documentation_page($this);
    }

    /**
     * Render the Settings page content.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Загальні налаштування', 'okbi-apartments'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('fok_global_settings_group');
                do_settings_sections('fok_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        register_setting('fok_global_settings_group', 'fok_global_settings', [ $this, 'sanitize_settings' ]);

        add_settings_section('fok_general_section', __('Основні налаштування', 'okbi-apartments'), null, 'fok_settings');
        add_settings_field('fok_logo_id', __('Логотип', 'okbi-apartments'), [ $this, 'render_logo_field' ], 'fok_settings', 'fok_general_section');
        add_settings_field('fok_accent_color', __('Акцентний колір', 'okbi-apartments'), [ $this, 'render_accent_color_field' ], 'fok_settings', 'fok_general_section');
        add_settings_field('fok_sales_phone', __('Телефон відділу продажів', 'okbi-apartments'), [ $this, 'render_sales_phone_field' ], 'fok_settings', 'fok_general_section');

        add_settings_section('fok_notifications_section', __('Сповіщення', 'okbi-apartments'), null, 'fok_settings');
        add_settings_field('fok_notification_email', __('Email для сповіщень', 'okbi-apartments'), [ $this, 'render_notification_email_field' ], 'fok_settings', 'fok_notifications_section');
        add_settings_field('fok_telegram_bot_token', __('Telegram Bot Token', 'okbi-apartments'), [ $this, 'render_telegram_bot_token_field' ], 'fok_settings', 'fok_notifications_section');
        add_settings_field('fok_telegram_chat_id', __('Telegram Chat ID', 'okbi-apartments'), [ $this, 'render_telegram_chat_id_field' ], 'fok_settings', 'fok_notifications_section');

        add_settings_section('fok_recaptcha_section', __('reCAPTCHA v3', 'okbi-apartments'), null, 'fok_settings');
        add_settings_field('fok_recaptcha_site_key', __('Ключ сайту (Site Key)', 'okbi-apartments'), [ $this, 'render_recaptcha_site_key_field' ], 'fok_settings', 'fok_recaptcha_section');
        add_settings_field('fok_recaptcha_secret_key', __('Секретний ключ (Secret Key)', 'okbi-apartments'), [ $this, 'render_recaptcha_secret_key_field' ], 'fok_settings', 'fok_recaptcha_section');
    }

    /**
     * Render settings fields.
     */
    public function render_logo_field() {
        $options = get_option('fok_global_settings');
        $logo_id = $options['logo_id'] ?? '';
        echo '<input type="hidden" name="fok_global_settings[logo_id]" id="fok_logo_id" value="' . esc_attr($logo_id) . '">';
        echo '<div id="fok-logo-preview">';
        if ($logo_id) { echo wp_get_attachment_image($logo_id, 'medium'); }
        echo '</div>';
        echo '<button type="button" class="button" id="fok-upload-logo-button">' . __('Завантажити лого', 'okbi-apartments') . '</button>';
        echo '<button type="button" class="button" id="fok-remove-logo-button" style="' . (empty($logo_id) ? 'display:none;' : '') . '">' . __('Видалити лого', 'okbi-apartments') . '</button>';
    }
    public function render_accent_color_field() {
        $options = get_option('fok_global_settings');
        $color = $options['accent_color'] ?? '#0073aa';
        echo '<input type="text" name="fok_global_settings[accent_color]" value="' . esc_attr($color) . '" class="fok-color-picker" />';
    }
    public function render_sales_phone_field() {
        $options = get_option('fok_global_settings');
        $phone = $options['sales_phone'] ?? '';
        echo '<input type="text" name="fok_global_settings[sales_phone]" value="' . esc_attr($phone) . '" class="regular-text" placeholder="+38 (000) 000-00-00" />';
    }
    public function render_notification_email_field() {
        $options = get_option('fok_global_settings');
        $email = $options['notification_email'] ?? get_option('admin_email');
        echo '<input type="email" name="fok_global_settings[notification_email]" value="' . esc_attr($email) . '" class="regular-text" />';
        echo '<p class="description">' . __('На цю адресу будуть надходити заявки на бронювання.', 'okbi-apartments') . '</p>';
    }
    public function render_telegram_bot_token_field() {
        $options = get_option('fok_global_settings');
        $bot_token = $options['telegram_bot_token'] ?? '';
        echo '<input type="text" name="fok_global_settings[telegram_bot_token]" value="' . esc_attr($bot_token) . '" class="regular-text" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" />';
        echo '<p class="description">' . __('Щоб отримати токен, створіть нового бота за допомогою <a href="https://t.me/BotFather" target="_blank">@BotFather</a>.', 'okbi-apartments') . '</p>';
    }
    public function render_telegram_chat_id_field() {
        $options = get_option('fok_global_settings');
        $chat_id = $options['telegram_chat_id'] ?? '';
        echo '<input type="text" name="fok_global_settings[telegram_chat_id]" value="' . esc_attr($chat_id) . '" class="regular-text" />';
        echo '<p class="description">' . __('Це ID вашого каналу або групи. Щоб його дізнатись, можна використати бота <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a> або аналогічного.', 'okbi-apartments') . '</p>';
    }
    public function render_recaptcha_site_key_field() {
        $options = get_option('fok_global_settings');
        $site_key = $options['recaptcha_site_key'] ?? '';
        echo '<input type="text" name="fok_global_settings[recaptcha_site_key]" value="' . esc_attr($site_key) . '" class="regular-text" />';
        echo '<p class="description">' . __('Вставте сюди ключ сайту для reCAPTCHA v3, який ви отримали в <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA admin panel</a>.', 'okbi-apartments') . '</p>';
    }
    public function render_recaptcha_secret_key_field() {
        $options = get_option('fok_global_settings');
        $secret_key = $options['recaptcha_secret_key'] ?? '';
        echo '<input type="text" name="fok_global_settings[recaptcha_secret_key]" value="' . esc_attr($secret_key) . '" class="regular-text" />';
    }

    /**
     * Sanitize settings fields.
     */
    public function sanitize_settings($input) {
        $new_input = [];
        if ( isset( $input['notification_email'] ) ) { $new_input['notification_email'] = sanitize_email( $input['notification_email'] ); }
        if ( isset( $input['telegram_bot_token'] ) ) { $new_input['telegram_bot_token'] = sanitize_text_field( $input['telegram_bot_token'] ); }
        if ( isset( $input['telegram_chat_id'] ) ) { $new_input['telegram_chat_id'] = sanitize_text_field( $input['telegram_chat_id'] ); }
        if ( isset( $input['recaptcha_site_key'] ) ) { $new_input['recaptcha_site_key'] = sanitize_text_field( $input['recaptcha_site_key'] ); }
        if ( isset( $input['recaptcha_secret_key'] ) ) { $new_input['recaptcha_secret_key'] = sanitize_text_field( $input['recaptcha_secret_key'] ); }
        return $new_input;
    }

    /**
     * Checks if the MetaBox plugin is active.
     */
    public function check_meta_box_dependency() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if ( !is_plugin_active( 'meta-box/meta-box.php' ) && !is_plugin_active( 'meta-box-aio/meta-box-aio.php' ) ) {
            add_action( 'admin_notices', [ $this, 'meta_box_not_found_notice' ] );
        }
    }

    /**
     * Renders an admin notice if MetaBox is not active.
     */
    public function meta_box_not_found_notice() {
        echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post( '<strong>Плагін "Flat Okbi":</strong> Для повноцінної роботи необхідно встановити та активувати безкоштовний плагін <a href="https://wordpress.org/plugins/meta-box/" target="_blank">MetaBox</a>.' ) . '</p></div>';
    }

    /**
     * Disables the Gutenberg block editor for CPTs.
     */
    public function disable_gutenberg( $current_status, $post_type ) {
        $cpts_to_disable = ['residential_complex', 'section', 'apartment', 'commercial_property', 'parking_space', 'storeroom'];
        if ( in_array( $post_type, $cpts_to_disable, true ) ) return false;
        return $current_status;
    }

    /**
     * Removes the default status metabox for properties.
     */
    public function remove_default_status_metabox() {
        $post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
        foreach($post_types as $post_type) {
            remove_meta_box( 'tagsdiv-status', $post_type, 'side' );
            remove_meta_box( 'statusdiv', $post_type, 'side' );
        }
    }

    /**
     * Adds custom dropdown filters to admin list tables.
     */
    public function add_admin_list_filters() {
        global $typenow;
        $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];

        if ( $typenow === 'section' || in_array($typenow, $property_types) ) {
            $complexes = $this->get_all_rcs_cached();
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

    /**
     * Modifies the main query for admin list tables based on selected filters.
     */
    public function filter_admin_list_query( $query ) {
        global $pagenow, $typenow;
        if ( $pagenow === 'edit.php' && $query->is_main_query() ) {
            // Handle custom sorting by RC or Section title
            $orderby = $query->get('orderby');
            if ( 'fok_rc' === $orderby ) {
                $query->set('meta_key', 'fok_property_rc_link');
                $query->set('orderby', 'rc_title');
            } elseif ( 'fok_section' === $orderby ) {
                $query->set('meta_key', 'fok_property_section_link');
                $query->set('orderby', 'section_title');
            }

            if ( isset( $_GET['fok_rc_filter'] ) && (int) $_GET['fok_rc_filter'] > 0 ) {
                $rc_id = (int) $_GET['fok_rc_filter'];
                $meta_key = ( $typenow === 'section' ) ? 'fok_section_rc_link' : 'fok_property_rc_link';
                $meta_query = $query->get( 'meta_query' ) ?: [];
                $meta_query[] = ['key' => $meta_key, 'value' => $rc_id, 'compare' => '='];
                $query->set( 'meta_query', $meta_query );
            }

            $property_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
            if ( in_array($typenow, $property_types) && isset( $_GET['fok_status_filter'] ) && ! empty( $_GET['fok_status_filter'] ) ) {
                $status_slug = sanitize_text_field( $_GET['fok_status_filter'] );
                $tax_query = $query->get( 'tax_query' ) ?: [];
                $tax_query[] = ['taxonomy' => 'status', 'field' => 'slug', 'terms' => $status_slug];
                $query->set( 'tax_query', $tax_query );
            }
        }
    }

    /**
     * Register all meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'fok_rc_trigger_code',
            __( 'Код для запуску каталогу', 'okbi-apartments' ),
            [ $this, 'render_rc_trigger_code_metabox' ],
            'residential_complex', 'side', 'high'
        );
        add_meta_box(
            'fok_grid_editor',
            __( 'Редактор сітки', 'okbi-apartments' ),
            [ $this, 'render_grid_editor_metabox' ],
            'section', 'normal', 'high'
        );
    }

    /**
     * Renders the 'Trigger Code' meta box.
     */
    public function render_rc_trigger_code_metabox($post) {
        ?>
        <p><?php _e('Щоб відкрити каталог для цього ЖК, додайте до будь-якої кнопки чи посилання на вашому сайті наступні атрибути:', 'okbi-apartments'); ?></p>
        <input type="text" readonly value="<?php echo esc_attr('class="fok-open-viewer" data-rc-id="' . $post->ID . '"'); ?>" style="width: 100%;" onfocus="this.select();">
        <p class="description"><?php _e('Також не забудьте розмістити на цій же сторінці шорткод <code>[okbi_viewer]</code> (у будь-якому місці).', 'okbi-apartments'); ?></p>
        <?php
    }

    /**
     * Renders the 'Grid Editor' meta box.
     */
    public function render_grid_editor_metabox( $post ) {
        ?>
        <div class="fok-grid-editor-wrapper" data-section-id="<?php echo esc_attr( $post->ID ); ?>">
            <div class="fok-editor-main-content">
                <div class="fok-editor-loader"><div class="spinner"></div></div>
                <div class="fok-editor-layout">
                    <div class="fok-unassigned-pool">
                        <h4><?php _e( 'Нерозподілені об\'єкти', 'okbi-apartments' ); ?></h4>
                        <div class="fok-unassigned-list"></div>
                    </div>
                    <div class="fok-editor-grid-container"></div>
                </div>
                 <div class="fok-editor-toolbar">
                    <button type="button" class="button button-primary" id="fok-save-grid-changes"><?php _e( 'Зберегти зміни', 'okbi-apartments' ); ?></button>
                    <span class="fok-save-status"></span>
                </div>
                <p class="description" style="margin-top: 10px;"><?php _e( '<b>Важливо:</b> не забудьте зберегти зміни перед оновленням або закриттям сторінки.', 'okbi-apartments' ); ?></p>
            </div>
        </div>
        <?php
        wp_nonce_field( 'fok_grid_editor_nonce_action', 'fok_grid_editor_nonce' );
    }

    /**
     * Generates a unique ID for a property on save.
     */
    public function generate_unique_id_on_save( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        $allowed_post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
        if ( !in_array(get_post_type($post_id), $allowed_post_types) ) return;

        if ( ! get_post_meta( $post_id, 'fok_property_unique_id', true ) ) {
            update_post_meta( $post_id, 'fok_property_unique_id', 'manual-' . uniqid() );
        }
    }

    /**
     * Automatically generates a property title on save.
     */
    public function autogenerate_property_title_on_save( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        $post_type = get_post_type($post_id);
        $allowed_post_types = ['apartment', 'commercial_property', 'parking_space', 'storeroom'];
        if ( !in_array($post_type, $allowed_post_types) ) return;
        $property_number = isset( $_POST['fok_property_number'] ) ? sanitize_text_field( $_POST['fok_property_number'] ) : '';
        if ( empty($property_number) ) return;

        $type_names = [
            'apartment' => __('Квартира', 'okbi-apartments'), 'commercial_property' => __('Комерція', 'okbi-apartments'),
            'parking_space' => __('Паркомісце', 'okbi-apartments'), 'storeroom' => __('Комора', 'okbi-apartments'),
        ];
        $type_name = $type_names[$post_type] ?? __('Об\'єкт', 'okbi-apartments');
        $new_title = $type_name . ' №' . $property_number;
        if (get_post($post_id)->post_title === $new_title) return;

        remove_action( 'save_post', [ $this, 'autogenerate_property_title_on_save' ], 20 );
        wp_update_post(['ID' => $post_id, 'post_title' => $new_title, 'post_name' => sanitize_title($new_title)]);
        add_action( 'save_post', [ $this, 'autogenerate_property_title_on_save' ], 20, 1 );
    }
    
    /**
     * Syncs sections from the metabox textarea on RC save.
     */
    public function sync_sections_on_rc_save( $post_id ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( ! isset( $_POST['fok_rc_sections_list'] ) ) return;

        update_post_meta($post_id, 'fok_rc_sections_list', sanitize_textarea_field($_POST['fok_rc_sections_list']));
        
        $submitted_text = get_post_meta( $post_id, 'fok_rc_sections_list', true );
        $submitted_names = array_filter( array_map( 'trim', explode( "\n", $submitted_text ) ) );
        $submitted_names = array_unique( $submitted_names );

        $existing_sections_query = new WP_Query(['post_type' => 'section', 'posts_per_page' => -1, 'meta_key' => 'fok_section_rc_link', 'meta_value' => $post_id, 'fields' => 'all']);
        $existing_sections_map = [];
        if ( $existing_sections_query->have_posts() ) {
            foreach ( $existing_sections_query->posts as $section ) {
                $existing_sections_map[ $section->post_title ] = $section->ID;
            }
        }
        wp_reset_postdata();

        $names_to_add = array_diff( $submitted_names, array_keys( $existing_sections_map ) );
        foreach ( $names_to_add as $name ) {
            if ( empty( $name ) ) continue;
            $new_section_id = wp_insert_post(['post_title' => sanitize_text_field( $name ), 'post_type' => 'section', 'post_status' => 'publish']);
            if ( $new_section_id && ! is_wp_error( $new_section_id ) ) {
                update_post_meta( $new_section_id, 'fok_section_rc_link', $post_id );
            }
        }
        $names_to_delete = array_diff( array_keys( $existing_sections_map ), $submitted_names );
        foreach ( $names_to_delete as $name ) {
            wp_delete_post( $existing_sections_map[$name], true );
        }
    }

    /**
     * Syncs property status based on lead status on save.
     */
    public function sync_property_status_on_lead_save( $post_id, $post ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( ! isset( $_POST['_lead_status'] ) ) return;
        $property_id = get_post_meta( $post_id, '_lead_property_id', true );
        if ( ! $property_id ) return;
        $lead_status = sanitize_text_field( $_POST['_lead_status'] );
        $target_property_status_slug = '';
        switch ( $lead_status ) {
            case 'success': $target_property_status_slug = 'prodano'; break;
            case 'failed': $target_property_status_slug = 'vilno'; break;
            case 'new': case 'in_progress': $target_property_status_slug = 'zabronovano'; break;
        }
        if ( ! empty( $target_property_status_slug ) ) {
            $term = get_term_by( 'slug', $target_property_status_slug, 'status' );
            if ( $term && ! is_wp_error( $term ) ) {
                wp_set_object_terms( $property_id, $term->term_id, 'status', false );
            }
        }
    }

    /**
     * Defines custom columns for the 'fok_lead' CPT list table.
     */
    function set_lead_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Заявка', 'okbi-apartments');
        $new_columns['lead_status'] = __('Статус', 'okbi-apartments');
        $new_columns['lead_phone'] = __('Телефон клієнта', 'okbi-apartments');
        $new_columns['lead_rc_section'] = __('ЖК / Секція', 'okbi-apartments');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * Renders content for custom lead columns.
     */
    function render_lead_columns($column, $post_id) {
        switch ($column) {
            case 'lead_status':
                echo esc_html( get_post_meta($post_id, '_lead_status', true) );
                break;
            case 'lead_phone':
                echo esc_html( get_post_meta($post_id, '_lead_phone', true) );
                break;
            case 'lead_rc_section':
                $property_id = get_post_meta($post_id, '_lead_property_id', true);
                if (!$property_id) { echo '—'; break; }
                $rc_id = get_post_meta($property_id, 'fok_property_rc_link', true);
                $section_id = get_post_meta($property_id, 'fok_property_section_link', true);
                $rc_title = $rc_id ? get_the_title($rc_id) : '—';
                $section_title = $section_id ? get_the_title($section_id) : '—';
                echo '<strong>' . esc_html($rc_title) . '</strong><br>' . esc_html($section_title);
                break;
        }
    }
    
    /**
     * Defines custom columns for the property CPTs list tables.
     */
    public function set_property_columns($columns) {
        // Insert Section and RC columns before the status column
        $new_columns = [];
        foreach ($columns as $key => $title) {
            if ($key === 'taxonomy-status') {
                $new_columns['fok_rc'] = __('ЖК', 'okbi-apartments');
                $new_columns['fok_section'] = __('Секція', 'okbi-apartments');
            }
            $new_columns[$key] = $title;
        }
        return $new_columns;
    }

    /**
     * Renders custom column content for the property CPTs.
     */
    public function render_property_columns($column, $post_id) {
        switch ($column) {
            case 'fok_rc':
                $rc_id = get_post_meta($post_id, 'fok_property_rc_link', true);
                if ($rc_id) {
                    echo esc_html(get_the_title($rc_id));
                } else {
                    echo '—';
                }
                break;
            
            case 'fok_section':
                $section_id = get_post_meta($post_id, 'fok_property_section_link', true);
                if ($section_id) {
                    echo esc_html(get_the_title($section_id));
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Retrieves all Residential Complexes, using a cache to avoid repeated queries.
     */
    public function get_all_rcs_cached() {
        $transient_name = 'fok_all_rcs_list';
        $rcs = get_transient( $transient_name );
        if ( false === $rcs ) {
            $rcs = get_posts(['post_type' => 'residential_complex', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
            set_transient( $transient_name, $rcs, HOUR_IN_SECONDS );
        }
        return $rcs;
    }

    /**
     * Clears the RC list cache on save/delete.
     */
    public function clear_rc_cache_on_save( $post_id ) {
        if ( get_post_type( $post_id ) === 'residential_complex' ) {
            delete_transient( 'fok_all_rcs_list' );
        }
    }

    /**
     * Handles the CSV export for properties.
     */
    public function handle_csv_export() {
        if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'fok_handle_csv_export') return;
        if (!isset($_POST['fok_export_nonce']) || !wp_verify_nonce($_POST['fok_export_nonce'], 'fok_export_nonce_action')) return;
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

        $type_names = [
            'apartment' => 'Квартира', 'commercial_property' => 'Комерція',
            'parking_space' => 'Паркомісце', 'storeroom' => 'Комора',
        ];

        fputcsv($output, ['unique_id', 'post_type', 'rc_name', 'section_name', 'property_number', 'floor', 'grid_column_start', 'grid_column_span', 'grid_row_span', 'rooms', 'area', 'price_per_sqm', 'total_price', 'currency', 'discount_percent', 'status', 'layout_images']);

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
                $this->get_property_status_name($property_id),
                implode(',', $image_filenames)
            ];
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Helper to safely get the status term name for a property.
     * @param int $property_id
     * @return string
     */
    private function get_property_status_name($property_id) {
        $terms = get_the_terms($property_id, 'status');
        if ( !is_wp_error($terms) && !empty($terms) ) {
            return $terms[0]->name;
        }
        return '';
    }

    /**
     * Handles the CSV export for leads.
     */
    public function handle_leads_export() {
        if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'fok_handle_leads_export') return;
        if (!isset($_POST['fok_leads_export_nonce']) || !wp_verify_nonce($_POST['fok_leads_export_nonce'], 'fok_leads_export_nonce_action')) return;
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
            'Коментар',
            'UTM-позначки',
            'Сторінка відправки'
        ]);

        foreach ($leads as $lead) {
            $lead_id = $lead->ID;
            $lead_status_terms = get_the_terms($lead_id, 'lead_status');
            $status_name = (!empty($lead_status_terms) && !is_wp_error($lead_status_terms)) ? $lead_status_terms[0]->name : 'Нова';
            
            $rc_id = get_post_meta($lead_id, 'fok_lead_rc_id', true);
            $section_id = get_post_meta($lead_id, 'fok_lead_section_id', true);
            $property_id = get_post_meta($lead_id, 'fok_lead_property_id', true);

            $row = [
                $lead->post_date,
                $status_name,
                get_post_meta($lead_id, 'fok_lead_name', true),
                get_post_meta($lead_id, 'fok_lead_phone', true),
                $rc_id ? get_the_title($rc_id) : '',
                $section_id ? get_the_title($section_id) : '',
                $property_id ? get_the_title($property_id) : '',
                get_post_meta($lead_id, 'fok_lead_comment', true),
                get_post_meta($lead_id, 'fok_lead_utm_tags', true),
                get_post_meta($lead_id, 'fok_lead_source_page', true),
            ];
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    /**
     * Defines which property columns are sortable.
     */
    public function make_property_columns_sortable($columns) {
        $columns['fok_rc'] = 'fok_rc';
        $columns['fok_section'] = 'fok_section';
        return $columns;
    }

    /**
     * Custom ORDER BY clause for sorting by linked post titles.
     */
    public function custom_property_orderby($orderby, $query) {
        global $wpdb;
        $order = $query->get('order') ?: 'ASC';
        
        switch ($query->get('orderby')) {
            case 'rc_title':
                $orderby = "(SELECT post_title FROM {$wpdb->posts} WHERE ID = {$wpdb->postmeta}.meta_value) " . esc_sql($order);
                break;
            case 'section_title':
                $orderby = "(SELECT post_title FROM {$wpdb->posts} WHERE ID = {$wpdb->postmeta}.meta_value) " . esc_sql($order);
                break;
        }

        return $orderby;
    }
}