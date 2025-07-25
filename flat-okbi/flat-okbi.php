<?php
/**
 * Plugin Name:         Flat Okbi
 * Plugin URI:          https://okbi.pp.ua
 * Description:         Плагін для керування каталогом квартир та житлових комплексів.
 * Version:             2.3.1
 * Requires at least:   5.2
 * Requires PHP:        7.2
 * Author:              Okbi
 * Author URI:          https://okbi.pp.ua
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         okbi-apartments
 * Domain Path:         /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Main Flat Okbi Plugin Class
 */
final class Flat_Okbi_Plugin {

    /**
     * The single instance of the class.
     * @var Flat_Okbi_Plugin
     */
    private static $instance = null;

    /**
     * Main Plugin Instance.
     * Ensures only one instance of the plugin is loaded.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
        $this->init_classes();
    }

    /**
     * Define Plugin Constants.
     */
    private function define_constants() {
        define( 'FOK_PLUGIN_FILE', __FILE__ );
        define( 'FOK_PLUGIN_PATH', plugin_dir_path( FOK_PLUGIN_FILE ) );
        define( 'FOK_PLUGIN_URL', plugin_dir_url( FOK_PLUGIN_FILE ) );
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once FOK_PLUGIN_PATH . 'includes/classes/class-fok-post-types.php';
        require_once FOK_PLUGIN_PATH . 'includes/classes/class-fok-taxonomies.php';
        require_once FOK_PLUGIN_PATH . 'includes/classes/class-fok-assets.php';
        require_once FOK_PLUGIN_PATH . 'includes/classes/class-fok-utils.php';
        require_once FOK_PLUGIN_PATH . 'includes/classes/class-fok-ajax.php';
        require_once FOK_PLUGIN_PATH . 'includes/classes/class-fok-shortcodes.php';
        require_once FOK_PLUGIN_PATH . 'includes/classes/class-fok-admin.php';
        require_once FOK_PLUGIN_PATH . 'includes/meta-fields.php';
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        // Load plugin textdomain
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

        // Register Post Types and Taxonomies
        add_action( 'init', [ 'FOK_Post_Types', 'register' ] );
        add_action( 'init', [ 'FOK_Taxonomies', 'register' ] );

        // Deep linking rewrite rules
        add_action('init', [ $this, 'add_rewrite_tags_and_rules' ], 10, 0);

        // Register activation/deactivation hooks
        register_activation_hook( FOK_PLUGIN_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( FOK_PLUGIN_FILE, [ $this, 'deactivate' ] );
    }

    /**
     * Initialize classes.
     */
    private function init_classes() {
        // These classes use constructors to add hooks.
        new FOK_Assets();
        new FOK_Ajax();
        new FOK_Shortcodes();
        new FOK_Admin();
        // FOK_Post_Types and FOK_Taxonomies only contain static methods,
        // so they are called directly via hooks and don't need to be instantiated.
    }

    /**
     * Load the plugin text domain for translation.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'okbi-apartments',
            false,
            dirname( plugin_basename( FOK_PLUGIN_FILE ) ) . '/languages/'
        );
    }

    /**
     * Plugin activation hook.
     * Creates post types, taxonomies, and flushes rewrite rules.
     */
    public function activate() {
        FOK_Post_Types::register();
        FOK_Taxonomies::register();
        FOK_Taxonomies::insert_initial_terms();
        $this->add_rewrite_tags_and_rules();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook.
     * Flushes rewrite rules.
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Add custom rewrite tags and rules for deep linking.
     */
    public function add_rewrite_tags_and_rules() {
        add_rewrite_tag('%property%', '([^&]+)');
        add_rewrite_rule('^(.?.+?)/property/(\d+)/?$', 'index.php?pagename=$matches[1]&property=$matches[2]', 'top');
    }
}

/**
 * Begins execution of the plugin.
 */
function run_flat_okbi_plugin() {
    return Flat_Okbi_Plugin::instance();
}

// Let's get this party started
run_flat_okbi_plugin();
