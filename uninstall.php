<?php
/**
 * Calendar Pet Sitting Uninstall
 * 
 * Fired when the plugin is uninstalled.
 *
 * @package Calendar_Petsitting
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include database class
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

// Drop all plugin tables
Calendar_Petsitting_Database::drop_tables();

// Delete all plugin options
$options = array(
    'calendar_petsitting_timezone',
    'calendar_petsitting_retention_years',
    'calendar_petsitting_default_step_minutes',
    'calendar_petsitting_date_format',
    'calendar_petsitting_time_format',
    'calendar_petsitting_db_version'
);

foreach ($options as $option) {
    delete_option($option);
}

// Clear any scheduled cron jobs
wp_clear_scheduled_hook('calendar_petsitting_cleanup');

// Clear any cached data
wp_cache_flush();