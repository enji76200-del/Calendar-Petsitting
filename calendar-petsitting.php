<?php
/**
 * Plugin Name: Calendar Pet Sitting
 * Plugin URI: https://github.com/enji76200-del/Calendar-Petsitting
 * Description: Un plugin WordPress complet pour la gestion de réservations de services de pet-sitting avec calendrier interactif intégrable dans Divi.
 * Version: 1.0.0
 * Author: Pet Sitting Calendar
 * Text Domain: calendar-petsitting
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CALENDAR_PETSITTING_VERSION', '1.0.0');
define('CALENDAR_PETSITTING_PLUGIN_FILE', __FILE__);
define('CALENDAR_PETSITTING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CALENDAR_PETSITTING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CALENDAR_PETSITTING_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Calendar Pet Sitting Class
 */
class Calendar_Petsitting {
    
    /**
     * Single instance of the class
     *
     * @var Calendar_Petsitting
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return Calendar_Petsitting
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        $this->includes();
        $this->init_classes();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core includes
        require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'includes/class-database.php';
        require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'includes/class-booking-manager.php';
        require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'includes/class-availability-calculator.php';
        require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'includes/class-cron-cleanup.php';
        
        // Admin includes
        if (is_admin()) {
            require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'admin/class-admin.php';
            require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'admin/class-services-admin.php';
            require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'admin/class-bookings-admin.php';
            require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'admin/class-unavailabilities-admin.php';
            require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'admin/class-settings-admin.php';
        }
        
        // Public includes
        require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'public/class-public.php';
        require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'public/class-shortcode.php';
    }
    
    /**
     * Initialize classes
     */
    private function init_classes() {
        // Initialize database
        new Calendar_Petsitting_Database();
        
        // Initialize REST API
        new Calendar_Petsitting_REST_API();
        
        // Initialize booking manager
        new Calendar_Petsitting_Booking_Manager();
        
        // Initialize availability calculator
        new Calendar_Petsitting_Availability_Calculator();
        
        // Initialize cron cleanup
        new Calendar_Petsitting_Cron_Cleanup();
        
        // Initialize admin
        if (is_admin()) {
            new Calendar_Petsitting_Admin();
        }
        
        // Initialize public
        new Calendar_Petsitting_Public();
        
        // Initialize shortcode
        new Calendar_Petsitting_Shortcode();
    }
    
    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'calendar-petsitting',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        require_once CALENDAR_PETSITTING_PLUGIN_DIR . 'includes/class-database.php';
        $database = new Calendar_Petsitting_Database();
        $database->create_tables();
        
        // Schedule cleanup cron
        if (!wp_next_scheduled('calendar_petsitting_cleanup')) {
            wp_schedule_event(time(), 'daily', 'calendar_petsitting_cleanup');
        }
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Unschedule cleanup cron
        wp_clear_scheduled_hook('calendar_petsitting_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'timezone' => 'Europe/Paris',
            'retention_years' => 2,
            'default_step_minutes' => 30,
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option('calendar_petsitting_' . $key) === false) {
                update_option('calendar_petsitting_' . $key, $value);
            }
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    Calendar_Petsitting::get_instance();
});