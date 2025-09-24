<?php
/**
 * Public-facing functionality for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Public class
 */
class Calendar_Petsitting_Public {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_head', array($this, 'add_ajax_url'));
        
        // AJAX handlers
        add_action('wp_ajax_petsitting_get_availability', array($this, 'ajax_get_availability'));
        add_action('wp_ajax_nopriv_petsitting_get_availability', array($this, 'ajax_get_availability'));
    }
    
    /**
     * Enqueue public scripts
     */
    public function enqueue_scripts() {
        // FullCalendar
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            array(),
            '6.1.10',
            true
        );
        
        // jQuery (if not already loaded)
        wp_enqueue_script('jquery');
        
        // Plugin main script
        wp_enqueue_script(
            'calendar-petsitting-public',
            CALENDAR_PETSITTING_PLUGIN_URL . 'assets/js/public.js',
            array('jquery', 'fullcalendar'),
            CALENDAR_PETSITTING_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('calendar-petsitting-public', 'calendarPetsitting', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('petsitting/v1/'),
            'nonce' => wp_create_nonce('calendar_petsitting_nonce'),
            'timezone' => get_option('calendar_petsitting_timezone', 'Europe/Paris'),
            'dateFormat' => get_option('calendar_petsitting_date_format', 'd/m/Y'),
            'timeFormat' => get_option('calendar_petsitting_time_format', 'H:i'),
            'strings' => array(
                'loading' => __('Chargement...', 'calendar-petsitting'),
                'error' => __('Une erreur s\'est produite', 'calendar-petsitting'),
                'success' => __('Réservation créée avec succès !', 'calendar-petsitting'),
                'confirmBooking' => __('Confirmer la réservation', 'calendar-petsitting'),
                'cancel' => __('Annuler', 'calendar-petsitting'),
                'close' => __('Fermer', 'calendar-petsitting'),
                'selectService' => __('Sélectionnez un service', 'calendar-petsitting'),
                'addOccurrence' => __('Ajouter une occurrence', 'calendar-petsitting'),
                'removeOccurrence' => __('Supprimer', 'calendar-petsitting'),
                'firstName' => __('Prénom', 'calendar-petsitting'),
                'lastName' => __('Nom', 'calendar-petsitting'),
                'email' => __('Email', 'calendar-petsitting'),
                'phone' => __('Téléphone', 'calendar-petsitting'),
                'service' => __('Service', 'calendar-petsitting'),
                'notes' => __('Remarques', 'calendar-petsitting'),
                'booking' => __('Réservation', 'calendar-petsitting'),
                'occurrences' => __('Créneaux sélectionnés', 'calendar-petsitting'),
                'total' => __('Total', 'calendar-petsitting'),
                'validation' => array(
                    'required' => __('Ce champ est requis', 'calendar-petsitting'),
                    'email' => __('Veuillez saisir une adresse email valide', 'calendar-petsitting'),
                    'phone' => __('Veuillez saisir un numéro de téléphone valide', 'calendar-petsitting'),
                    'minOccurrences' => __('Au moins un créneau doit être sélectionné', 'calendar-petsitting')
                )
            )
        ));
    }
    
    /**
     * Enqueue public styles
     */
    public function enqueue_styles() {
        // FullCalendar CSS
        wp_enqueue_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css',
            array(),
            '6.1.10'
        );
        
        // Plugin main stylesheet
        wp_enqueue_style(
            'calendar-petsitting-public',
            CALENDAR_PETSITTING_PLUGIN_URL . 'assets/css/public.css',
            array('fullcalendar'),
            CALENDAR_PETSITTING_VERSION
        );
    }
    
    /**
     * Add AJAX URL to head for JavaScript access
     */
    public function add_ajax_url() {
        echo '<script type="text/javascript">var ajaxurl = "' . admin_url('admin-ajax.php') . '";</script>';
    }
    
    /**
     * AJAX handler for getting availability
     */
    public function ajax_get_availability() {
        check_ajax_referer('calendar_petsitting_nonce');
        
        try {
            $calculator = new Calendar_Petsitting_Availability_Calculator();
            
            $from = sanitize_text_field($_POST['from']);
            $to = sanitize_text_field($_POST['to']);
            $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : null;
            
            $availability = $calculator->get_availability($from, $to, $service_id);
            
            wp_send_json_success($availability);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}