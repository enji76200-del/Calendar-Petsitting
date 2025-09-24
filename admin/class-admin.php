<?php
/**
 * Admin functionality for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Admin class
 */
class Calendar_Petsitting_Admin {
    
    /**
     * Admin class instances
     */
    private $services_admin;
    private $bookings_admin;
    private $unavailabilities_admin;
    private $settings_admin;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Initialize admin classes
        $this->services_admin = new Calendar_Petsitting_Services_Admin();
        $this->bookings_admin = new Calendar_Petsitting_Bookings_Admin();
        $this->unavailabilities_admin = new Calendar_Petsitting_Unavailabilities_Admin();
        $this->settings_admin = new Calendar_Petsitting_Settings_Admin();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Pet Sitting Calendar', 'calendar-petsitting'),
            __('Pet Sitting', 'calendar-petsitting'),
            'manage_options',
            'calendar-petsitting',
            array($this, 'admin_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'calendar-petsitting',
            __('Services', 'calendar-petsitting'),
            __('Services', 'calendar-petsitting'),
            'manage_options',
            'calendar-petsitting-services',
            array($this, 'services_page')
        );
        
        add_submenu_page(
            'calendar-petsitting',
            __('Réservations', 'calendar-petsitting'),
            __('Réservations', 'calendar-petsitting'),
            'manage_options',
            'calendar-petsitting-bookings',
            array($this, 'bookings_page')
        );
        
        add_submenu_page(
            'calendar-petsitting',
            __('Indisponibilités', 'calendar-petsitting'),
            __('Indisponibilités', 'calendar-petsitting'),
            'manage_options',
            'calendar-petsitting-unavailabilities',
            array($this, 'unavailabilities_page')
        );
        
        add_submenu_page(
            'calendar-petsitting',
            __('Paramètres', 'calendar-petsitting'),
            __('Paramètres', 'calendar-petsitting'),
            'manage_options',
            'calendar-petsitting-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'calendar-petsitting') === false) {
            return;
        }
        
        wp_enqueue_style(
            'calendar-petsitting-admin',
            CALENDAR_PETSITTING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CALENDAR_PETSITTING_VERSION
        );
        
        wp_enqueue_script(
            'calendar-petsitting-admin',
            CALENDAR_PETSITTING_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CALENDAR_PETSITTING_VERSION,
            true
        );
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Pet Sitting Calendar', 'calendar-petsitting'); ?></h1>
            
            <div class="petsitting-dashboard">
                <div class="petsitting-dashboard-cards">
                    <div class="petsitting-card">
                        <h3><?php _e('Services', 'calendar-petsitting'); ?></h3>
                        <p><?php _e('Gérez vos types de services et leurs tarifs', 'calendar-petsitting'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-services'); ?>" class="button button-primary">
                            <?php _e('Gérer les services', 'calendar-petsitting'); ?>
                        </a>
                    </div>
                    
                    <div class="petsitting-card">
                        <h3><?php _e('Réservations', 'calendar-petsitting'); ?></h3>
                        <p><?php _e('Consultez et gérez toutes les réservations', 'calendar-petsitting'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-bookings'); ?>" class="button button-primary">
                            <?php _e('Voir les réservations', 'calendar-petsitting'); ?>
                        </a>
                    </div>
                    
                    <div class="petsitting-card">
                        <h3><?php _e('Indisponibilités', 'calendar-petsitting'); ?></h3>
                        <p><?php _e('Définissez vos périodes d\'indisponibilité', 'calendar-petsitting'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-unavailabilities'); ?>" class="button button-primary">
                            <?php _e('Gérer les indisponibilités', 'calendar-petsitting'); ?>
                        </a>
                    </div>
                    
                    <div class="petsitting-card">
                        <h3><?php _e('Paramètres', 'calendar-petsitting'); ?></h3>
                        <p><?php _e('Configurez les options du plugin', 'calendar-petsitting'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-settings'); ?>" class="button button-primary">
                            <?php _e('Paramètres', 'calendar-petsitting'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="petsitting-quick-stats">
                    <h3><?php _e('Statistiques rapides', 'calendar-petsitting'); ?></h3>
                    <?php $this->display_quick_stats(); ?>
                </div>
                
                <div class="petsitting-shortcode-info">
                    <h3><?php _e('Utilisation du shortcode', 'calendar-petsitting'); ?></h3>
                    <p><?php _e('Pour afficher le calendrier sur votre site, utilisez le shortcode suivant:', 'calendar-petsitting'); ?></p>
                    <code>[petsitting_calendar]</code>
                    
                    <h4><?php _e('Options disponibles:', 'calendar-petsitting'); ?></h4>
                    <ul>
                        <li><code>view</code> - Vue par défaut (month, week, day)</li>
                        <li><code>height</code> - Hauteur du calendrier en pixels</li>
                        <li><code>service_id</code> - Filtrer par service spécifique</li>
                    </ul>
                    
                    <p><strong><?php _e('Exemple:', 'calendar-petsitting'); ?></strong></p>
                    <code>[petsitting_calendar view="week" height="700"]</code>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Services page
     */
    public function services_page() {
        $this->services_admin->render_services_page();
    }
    
    /**
     * Bookings page
     */
    public function bookings_page() {
        $this->bookings_admin->render_bookings_page();
    }
    
    /**
     * Unavailabilities page
     */
    public function unavailabilities_page() {
        $this->unavailabilities_admin->render_unavailabilities_page();
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $timezone = get_option('calendar_petsitting_timezone', 'Europe/Paris');
        $retention_years = get_option('calendar_petsitting_retention_years', 2);
        $default_step_minutes = get_option('calendar_petsitting_default_step_minutes', 30);
        ?>
        <div class="wrap">
            <h1><?php _e('Paramètres', 'calendar-petsitting'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('calendar_petsitting_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Fuseau horaire', 'calendar-petsitting'); ?></th>
                        <td>
                            <select name="timezone">
                                <option value="Europe/Paris" <?php selected($timezone, 'Europe/Paris'); ?>>Europe/Paris</option>
                                <option value="Europe/London" <?php selected($timezone, 'Europe/London'); ?>>Europe/London</option>
                                <option value="America/New_York" <?php selected($timezone, 'America/New_York'); ?>>America/New_York</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Durée de conservation (années)', 'calendar-petsitting'); ?></th>
                        <td>
                            <input type="number" name="retention_years" value="<?php echo esc_attr($retention_years); ?>" min="1" max="10">
                            <p class="description"><?php _e('Les réservations seront automatiquement supprimées après cette période', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Pas par défaut (minutes)', 'calendar-petsitting'); ?></th>
                        <td>
                            <input type="number" name="default_step_minutes" value="<?php echo esc_attr($default_step_minutes); ?>" min="5" max="120" step="5">
                            <p class="description"><?php _e('Pas de temps par défaut pour les services horaires/minute', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'calendar_petsitting_settings')) {
            return;
        }
        
        update_option('calendar_petsitting_timezone', sanitize_text_field($_POST['timezone']));
        update_option('calendar_petsitting_retention_years', intval($_POST['retention_years']));
        update_option('calendar_petsitting_default_step_minutes', intval($_POST['default_step_minutes']));
        
        echo '<div class="notice notice-success"><p>' . __('Paramètres sauvegardés.', 'calendar-petsitting') . '</p></div>';
    }
    
    /**
     * Display quick stats
     */
    private function display_quick_stats() {
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        $table_customers = Calendar_Petsitting_Database::get_table_name('customers');
        
        $services_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_services WHERE active = 1");
        $bookings_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_bookings WHERE status = 'confirmed'");
        $customers_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_customers");
        
        ?>
        <div class="petsitting-stats-grid">
            <div class="petsitting-stat-item">
                <div class="petsitting-stat-number"><?php echo $services_count; ?></div>
                <div class="petsitting-stat-label"><?php _e('Services actifs', 'calendar-petsitting'); ?></div>
            </div>
            
            <div class="petsitting-stat-item">
                <div class="petsitting-stat-number"><?php echo $bookings_count; ?></div>
                <div class="petsitting-stat-label"><?php _e('Réservations', 'calendar-petsitting'); ?></div>
            </div>
            
            <div class="petsitting-stat-item">
                <div class="petsitting-stat-number"><?php echo $customers_count; ?></div>
                <div class="petsitting-stat-label"><?php _e('Clients', 'calendar-petsitting'); ?></div>
            </div>
        </div>
        <?php
    }
}