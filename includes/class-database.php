<?php
/**
 * Database handler for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Database class
 */
class Calendar_Petsitting_Database {
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'check_database_version'));
        // Also check immediately on construction to handle first-load timing
        // and environments where the DB version option exists but tables are missing.
        $this->check_database_version();
    }
    
    /**
     * Check database version and update if needed
     */
    public function check_database_version() {
        global $wpdb;
        $installed_version = get_option('calendar_petsitting_db_version');

        $needs_install = ($installed_version !== self::DB_VERSION) || !$this->tables_exist();

        if ($needs_install) {
            $this->create_tables();
            update_option('calendar_petsitting_db_version', self::DB_VERSION);
        }
    }

    /**
     * Check if required tables exist in the current database
     */
    private function tables_exist() {
        global $wpdb;
        $tables = array(
            self::get_table_name('services'),
            self::get_table_name('customers'),
            self::get_table_name('bookings'),
            self::get_table_name('booking_items'),
            self::get_table_name('unavailabilities'),
            self::get_table_name('recurring_unavailabilities')
        );

        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Services table
        $table_services = $wpdb->prefix . 'ps_services';
        $sql_services = "CREATE TABLE $table_services (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            type enum('daily','hourly','minute') NOT NULL DEFAULT 'daily',
            min_duration int(11) NOT NULL DEFAULT 1,
            step_minutes int(11) DEFAULT NULL,
            price_cents bigint(20) NOT NULL DEFAULT 0,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type_active (type, active)
        ) $charset_collate;";
        
        // Customers table
        $table_customers = $wpdb->prefix . 'ps_customers';
        $sql_customers = "CREATE TABLE $table_customers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY name_lookup (last_name, first_name)
        ) $charset_collate;";
        
        // Bookings table
        $table_bookings = $wpdb->prefix . 'ps_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            total_price_cents bigint(20) NOT NULL DEFAULT 0,
            notes text,
            status enum('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY status_created (status, created_at),
            CONSTRAINT fk_booking_customer FOREIGN KEY (customer_id) REFERENCES $table_customers(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Booking items table
        $table_booking_items = $wpdb->prefix . 'ps_booking_items';
        $sql_booking_items = "CREATE TABLE $table_booking_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            service_id bigint(20) unsigned NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            unit_price_cents bigint(20) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY service_id (service_id),
            KEY datetime_range (start_datetime, end_datetime),
            KEY overlap_check (start_datetime, end_datetime),
            CONSTRAINT fk_booking_item_booking FOREIGN KEY (booking_id) REFERENCES $table_bookings(id) ON DELETE CASCADE,
            CONSTRAINT fk_booking_item_service FOREIGN KEY (service_id) REFERENCES $table_services(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Unavailabilities table
        $table_unavailabilities = $wpdb->prefix . 'ps_unavailabilities';
        $sql_unavailabilities = "CREATE TABLE $table_unavailabilities (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY datetime_range (start_datetime, end_datetime)
        ) $charset_collate;";
        
        // Recurring unavailabilities table
        $table_recurring_unavailabilities = $wpdb->prefix . 'ps_recurring_unavailabilities';
        $sql_recurring_unavailabilities = "CREATE TABLE $table_recurring_unavailabilities (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rule_type enum('weekly') NOT NULL DEFAULT 'weekly',
            weekday tinyint(1) NOT NULL COMMENT '0=Sunday, 6=Saturday',
            start_time time NOT NULL,
            end_time time NOT NULL,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rule_lookup (rule_type, weekday, start_date, end_date)
        ) $charset_collate;";
        
        // Require WordPress upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        dbDelta($sql_services);
        dbDelta($sql_customers);
        dbDelta($sql_bookings);
        dbDelta($sql_booking_items);
        dbDelta($sql_unavailabilities);
        dbDelta($sql_recurring_unavailabilities);
        
        // Insert default services if they don't exist
        $this->insert_default_services();
    }
    
    /**
     * Insert default services
     */
    private function insert_default_services() {
        global $wpdb;
        
        $table_services = $wpdb->prefix . 'ps_services';
        
        // Check if services already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_services");
        
        if ($count == 0) {
            $default_services = array(
                array(
                    'name' => __('Garde journalière', 'calendar-petsitting'),
                    'description' => __('Service de garde d\'animaux pour une journée complète', 'calendar-petsitting'),
                    'type' => 'daily',
                    'min_duration' => 1,
                    'step_minutes' => null,
                    'price_cents' => 5000, // 50€
                    'active' => 1
                ),
                array(
                    'name' => __('Promenade (1h)', 'calendar-petsitting'),
                    'description' => __('Promenade d\'une heure pour votre animal', 'calendar-petsitting'),
                    'type' => 'hourly',
                    'min_duration' => 60,
                    'step_minutes' => 30,
                    'price_cents' => 2000, // 20€
                    'active' => 1
                ),
                array(
                    'name' => __('Visite express (30min)', 'calendar-petsitting'),
                    'description' => __('Visite rapide pour nourrir et câliner votre animal', 'calendar-petsitting'),
                    'type' => 'minute',
                    'min_duration' => 30,
                    'step_minutes' => 15,
                    'price_cents' => 1500, // 15€
                    'active' => 1
                )
            );
            
            foreach ($default_services as $service) {
                $wpdb->insert(
                    $table_services,
                    $service,
                    array('%s', '%s', '%s', '%d', '%d', '%d', '%d')
                );
            }
        }
    }
    
    /**
     * Get table name with prefix
     *
     * @param string $table Table name without prefix
     * @return string Full table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'ps_' . $table;
    }
    
    /**
     * Drop all plugin tables (used in uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            'ps_booking_items',
            'ps_bookings',
            'ps_customers',
            'ps_services',
            'ps_unavailabilities',
            'ps_recurring_unavailabilities'
        );
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        // Delete options
        delete_option('calendar_petsitting_db_version');
    }
}