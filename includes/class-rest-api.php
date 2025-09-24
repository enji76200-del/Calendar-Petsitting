<?php
/**
 * REST API handler for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_REST_API class
 */
class Calendar_Petsitting_REST_API {
    
    /**
     * API namespace
     */
    const NAMESPACE_V1 = 'petsitting/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Public routes (no authentication required)
        register_rest_route(self::NAMESPACE_V1, '/availability', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_availability'),
            'permission_callback' => '__return_true',
            'args' => array(
                'view' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('day', 'week', 'month'),
                    'default' => 'month'
                ),
                'from' => array(
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date-time'
                ),
                'to' => array(
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date-time'
                ),
                'service_id' => array(
                    'required' => false,
                    'type' => 'integer'
                )
            )
        ));
        
        register_rest_route(self::NAMESPACE_V1, '/services', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_services'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route(self::NAMESPACE_V1, '/book', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_booking'),
            'permission_callback' => '__return_true',
            'args' => array(
                'customer' => array(
                    'required' => true,
                    'type' => 'object',
                    'properties' => array(
                        'first_name' => array('type' => 'string'),
                        'last_name' => array('type' => 'string'),
                        'email' => array('type' => 'string', 'format' => 'email'),
                        'phone' => array('type' => 'string')
                    )
                ),
                'service_id' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'occurrences' => array(
                    'required' => true,
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'start_datetime' => array('type' => 'string', 'format' => 'date-time'),
                            'end_datetime' => array('type' => 'string', 'format' => 'date-time')
                        )
                    )
                ),
                'notes' => array(
                    'required' => false,
                    'type' => 'string'
                )
            )
        ));
        
        // Admin routes (require authentication)
        register_rest_route(self::NAMESPACE_V1, '/admin/services', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_admin_services'),
                'permission_callback' => array($this, 'check_admin_permissions')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_service'),
                'permission_callback' => array($this, 'check_admin_permissions')
            )
        ));
        
        register_rest_route(self::NAMESPACE_V1, '/admin/services/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_service'),
                'permission_callback' => array($this, 'check_admin_permissions')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_service'),
                'permission_callback' => array($this, 'check_admin_permissions')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_service'),
                'permission_callback' => array($this, 'check_admin_permissions')
            )
        ));
        
        register_rest_route(self::NAMESPACE_V1, '/admin/bookings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_bookings'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        register_rest_route(self::NAMESPACE_V1, '/admin/unavailabilities', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_unavailabilities'),
                'permission_callback' => array($this, 'check_admin_permissions')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_unavailability'),
                'permission_callback' => array($this, 'check_admin_permissions')
            )
        ));
    }
    
    /**
     * Check admin permissions
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get availability data
     */
    public function get_availability($request) {
        try {
            $calculator = new Calendar_Petsitting_Availability_Calculator();
            
            $from = $request->get_param('from');
            $to = $request->get_param('to');
            $service_id = $request->get_param('service_id');
            
            // Validate date parameters
            if (!$from || !$to) {
                return new WP_Error('invalid_dates', __('Paramètres de date requis', 'calendar-petsitting'), array('status' => 400));
            }
            
            $availability = $calculator->get_availability($from, $to, $service_id);
            
            return rest_ensure_response($availability);
            
        } catch (Exception $e) {
            return new WP_Error('availability_error', $e->getMessage(), array('status' => 400));
        }
    }
    
    /**
     * Get active services
     */
    public function get_services($request) {
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, description, type, min_duration, step_minutes, price_cents 
             FROM $table_services 
             WHERE active = %d 
             ORDER BY name ASC",
            1
        ));
        
        return rest_ensure_response($services);
    }
    
    /**
     * Create a booking
     */
    public function create_booking($request) {
        $booking_manager = new Calendar_Petsitting_Booking_Manager();
        
        $customer_data = $request->get_param('customer');
        $service_id = $request->get_param('service_id');
        $occurrences = $request->get_param('occurrences');
        $notes = $request->get_param('notes');
        
        try {
            $booking_id = $booking_manager->create_booking(
                $customer_data,
                $service_id,
                $occurrences,
                $notes
            );
            
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'booking_id' => $booking_id,
                    'message' => __('Réservation créée avec succès', 'calendar-petsitting')
                )
            ));
        } catch (Exception $e) {
            return new WP_Error('booking_error', $e->getMessage(), array('status' => 400));
        }
    }
    
    /**
     * Get admin services (placeholder)
     */
    public function get_admin_services($request) {
        // Implementation will be added in admin phase
        return rest_ensure_response(array('success' => true, 'data' => array()));
    }
    
    /**
     * Create service (placeholder)
     */
    public function create_service($request) {
        // Implementation will be added in admin phase
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Get service (placeholder)
     */
    public function get_service($request) {
        // Implementation will be added in admin phase
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Update service (placeholder)
     */
    public function update_service($request) {
        // Implementation will be added in admin phase
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Delete service (placeholder)
     */
    public function delete_service($request) {
        // Implementation will be added in admin phase
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Get bookings (placeholder)
     */
    public function get_bookings($request) {
        // Implementation will be added in admin phase
        return rest_ensure_response(array('success' => true, 'data' => array()));
    }
    
    /**
     * Get unavailabilities (placeholder)
     */
    public function get_unavailabilities($request) {
        // Implementation will be added in admin phase
        return rest_ensure_response(array('success' => true, 'data' => array()));
    }
    
    /**
     * Create unavailability (placeholder)
     */
    public function create_unavailability($request) {
        // Implementation will be added in admin phase
        return rest_ensure_response(array('success' => true));
    }
}