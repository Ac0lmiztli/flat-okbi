<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class FOK_Shortcodes
 *
 * Handles the registration and rendering of all plugin shortcodes.
 */
class FOK_Shortcodes {

    /**
     * FOK_Shortcodes constructor.
     */
    public function __construct() {
        // Register shortcodes
        add_shortcode( 'okbi_viewer', [ $this, 'render_viewer' ] );

        // Add rewrite rules for pretty URLs
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
    }

    /**
     * Adds custom rewrite rules.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            'property/([0-9]+)/?$',
            'index.php?pagename=viewer&property_id=$matches[1]',
            'top'
        );
    }

    /**
     * Adds custom query variables.
     *
     * @param array $vars The array of existing query variables.
     * @return array The modified array of query variables.
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'property_id';
        return $vars;
    }

    /**
     * Renders the main property viewer container.
     * Shortcode: [okbi_viewer]
     *
     * @param array $atts Shortcode attributes.
     * @return string The shortcode output.
     */
    public function render_viewer( $atts ) {
        static $is_shortcode_rendered = false;
        if ($is_shortcode_rendered) return '';
        $is_shortcode_rendered = true;

        // Pass property ID from URL to the frontend script if available
        $property_id_from_url = get_query_var( 'property_id' );

        $options = get_option( 'fok_global_settings' );
        $logo_id = $options['logo_id'] ?? '';
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $accent_color = $options['accent_color'] ?? '#0073aa';
        $rgb_color = '0, 115, 170';
        if (preg_match('/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i', $accent_color, $matches)) {
            $r = hexdec($matches[1]);
            $g = hexdec($matches[2]);
            $b = hexdec($matches[3]);
            $rgb_color = "{$r}, {$g}, {$b}";
        }

        ob_start();
        ?>
        <div 
            id="fok-viewer-fullscreen-container" 
            style="--fok-accent-color: <?php echo esc_attr($accent_color); ?>; --fok-accent-color-rgb: <?php echo esc_attr($rgb_color); ?>;"
            data-property-id="<?php echo esc_attr( $property_id_from_url ); ?>"
        >
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
                        <?php echo FOK_Utils::get_icon('filter'); ?>
                    </button>
                    <?php 
                    $phone_number = $options['sales_phone'] ?? '';
                    if ( !empty($phone_number) ) {
                        $formatted_phone_link = 'tel:' . preg_replace('/[^\d+]/', '', $phone_number);
                        echo '<a href="' . esc_attr($formatted_phone_link) . '" class="fok-header-phone-desktop fok-phone-detailed">' . FOK_Utils::get_icon('phone') . '<div class="fok-phone-text-wrapper"><span>Консультація</span><strong>' . esc_html($phone_number) . '</strong></div></a>';
                        echo '<a href="' . esc_attr($formatted_phone_link) . '" class="fok-header-phone-mobile">' . FOK_Utils::get_icon('phone') . '</a>';
                    }
                    ?>
                    <button id="fok-viewer-close" title="<?php esc_attr_e('Закрити', 'okbi-apartments'); ?>"><?php echo FOK_Utils::get_icon('close'); ?></button>
                </div>
            </header>

            <main class="fok-viewer-content">
                <div id="fok-list-mode" class="active">
                    <div class="fok-list-container">
                        <aside class="fok-list-sidebar">
                            <button id="fok-sidebar-close" title="<?php esc_attr_e('Закрити', 'okbi-apartments'); ?>"><?php echo FOK_Utils::get_icon('close'); ?></button>
                            <h3><?php echo FOK_Utils::get_icon('search'); ?><span><?php _e('Параметри пошуку', 'okbi-apartments'); ?></span></h3>
                            
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
                            <button id="fok-panel-close" title="<?php esc_attr_e('Закрити', 'okbi-apartments'); ?>"><?php echo FOK_Utils::get_icon('close'); ?></button>
                            <div class="fok-panel-loader" style="display: none;"><div class="spinner"></div></div>
                            <div id="fok-panel-content"></div>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
        <div id="fok-lightbox" class="fok-lightbox-overlay">
            <button id="fok-lightbox-close" class="fok-lightbox-control"><?php echo FOK_Utils::get_icon('close'); ?></button>
            <button id="fok-lightbox-prev" class="fok-lightbox-control fok-lightbox-nav">&lt;</button>
            <img class="fok-lightbox-content" src="">
            <button id="fok-lightbox-next" class="fok-lightbox-control fok-lightbox-nav">&gt;</button>
        </div>
        <?php
        return ob_get_clean();
    }
} 