<?php
/**
 * Cron cleanup for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Cron_Cleanup class
 */
class Calendar_Petsitting_Cron_Cleanup {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('calendar_petsitting_cleanup', array($this, 'run_cleanup'));
    }
    
    /**
     * Run the cleanup process
     */
    public function run_cleanup() {
        $this->cleanup_old_bookings();
        $this->cleanup_orphaned_customers();
        
        // Log cleanup activity
        error_log('Calendar Pet Sitting: Cleanup process completed at ' . current_time('mysql'));
    }
    
    /**
     * Clean up old bookings based on retention period
     */
    private function cleanup_old_bookings() {
        global $wpdb;
        
        $retention_years = get_option('calendar_petsitting_retention_years', 2);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_years} years"));
        
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        $table_booking_items = Calendar_Petsitting_Database::get_table_name('booking_items');
        
        // Get bookings to delete
        $bookings_to_delete = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $table_bookings WHERE created_at < %s",
            $cutoff_date
        ));
        
        if (!empty($bookings_to_delete)) {
            $booking_ids_string = implode(',', array_map('intval', $bookings_to_delete));
            
            // Delete booking items first (due to foreign key constraint)
            $wpdb->query("DELETE FROM $table_booking_items WHERE booking_id IN ($booking_ids_string)");
            
            // Delete bookings
            $deleted_bookings = $wpdb->query("DELETE FROM $table_bookings WHERE id IN ($booking_ids_string)");
            
            if ($deleted_bookings > 0) {
                error_log("Calendar Pet Sitting: Deleted $deleted_bookings old bookings");
            }
        }
    }
    
    /**
     * Clean up customers who have no bookings
     */
    private function cleanup_orphaned_customers() {
        global $wpdb;
        
        $table_customers = Calendar_Petsitting_Database::get_table_name('customers');
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        
        // Delete customers who have no bookings
        $deleted_customers = $wpdb->query(
            "DELETE c FROM $table_customers c 
             LEFT JOIN $table_bookings b ON c.id = b.customer_id 
             WHERE b.customer_id IS NULL"
        );
        
        if ($deleted_customers > 0) {
            error_log("Calendar Pet Sitting: Deleted $deleted_customers orphaned customers");
        }
    }
    
    /**
     * Clean up old unavailabilities (optional)
     */
    private function cleanup_old_unavailabilities() {
        global $wpdb;
        
        $retention_years = get_option('calendar_petsitting_retention_years', 2);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_years} years"));
        
        $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
        $table_recurring = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
        
        // Delete old one-time unavailabilities
        $deleted_unavailabilities = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_unavailabilities WHERE end_datetime < %s",
            $cutoff_date
        ));
        
        // Delete old recurring unavailabilities that have ended
        $deleted_recurring = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_recurring WHERE end_date IS NOT NULL AND end_date < %s",
            date('Y-m-d', strtotime("-{$retention_years} years"))
        ));
        
        if ($deleted_unavailabilities > 0) {
            error_log("Calendar Pet Sitting: Deleted $deleted_unavailabilities old unavailabilities");
        }
        
        if ($deleted_recurring > 0) {
            error_log("Calendar Pet Sitting: Deleted $deleted_recurring old recurring unavailabilities");
        }
    }
    
    /**
     * Get cleanup statistics
     */
    public function get_cleanup_stats() {
        global $wpdb;
        
        $retention_years = get_option('calendar_petsitting_retention_years', 2);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_years} years"));
        
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        $table_customers = Calendar_Petsitting_Database::get_table_name('customers');
        $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
        
        $stats = array();
        
        // Count old bookings
        $stats['old_bookings'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_bookings WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Count orphaned customers
        $stats['orphaned_customers'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_customers c 
             LEFT JOIN $table_bookings b ON c.id = b.customer_id 
             WHERE b.customer_id IS NULL"
        );
        
        // Count old unavailabilities
        $stats['old_unavailabilities'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_unavailabilities WHERE end_datetime < %s",
            $cutoff_date
        ));
        
        // Total records
        $stats['total_bookings'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_bookings");
        $stats['total_customers'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_customers");
        $stats['total_unavailabilities'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_unavailabilities");
        
        return $stats;
    }
    
    /**
     * Manual cleanup trigger (for admin use)
     */
    public function manual_cleanup() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $this->run_cleanup();
        return true;
    }
}