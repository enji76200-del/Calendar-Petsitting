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
            
            // Accept both from/to and start/end parameters (FullCalendar compatibility)
            $from = $request->get_param('from') ?: $request->get_param('start');
            $to = $request->get_param('to') ?: $request->get_param('end');
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
     * Get admin services
     */
    public function get_admin_services($request) {
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        $services = $wpdb->get_results(
            "SELECT * FROM $table_services ORDER BY name ASC"
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $services
        ));
    }
    
    /**
     * Create service
     */
    public function create_service($request) {
        global $wpdb;
        
        $data = array(
            'name' => sanitize_text_field($request->get_param('name')),
            'description' => sanitize_textarea_field($request->get_param('description')),
            'type' => sanitize_text_field($request->get_param('type')),
            'min_duration' => intval($request->get_param('min_duration')),
            'step_minutes' => $request->get_param('step_minutes') ? intval($request->get_param('step_minutes')) : null,
            'price_cents' => intval(floatval($request->get_param('price_cents')) * 100),
            'active' => $request->get_param('active') ? 1 : 0
        );
        
        // Validation
        if (empty($data['name']) || !in_array($data['type'], array('daily', 'hourly', 'minute'))) {
            return new WP_Error('invalid_data', __('Données invalides', 'calendar-petsitting'), array('status' => 400));
        }
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        $result = $wpdb->insert(
            $table_services,
            $data,
            array('%s', '%s', '%s', '%d', '%d', '%d', '%d')
        );
        
        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'id' => $wpdb->insert_id,
                    'message' => __('Service créé avec succès', 'calendar-petsitting')
                )
            ));
        } else {
            return new WP_Error('create_failed', __('Erreur lors de la création du service', 'calendar-petsitting'), array('status' => 500));
        }
    }
    
    /**
     * Get service
     */
    public function get_service($request) {
        global $wpdb;
        
        $service_id = intval($request->get_param('id'));
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_services WHERE id = %d",
            $service_id
        ));
        
        if (!$service) {
            return new WP_Error('not_found', __('Service non trouvé', 'calendar-petsitting'), array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $service
        ));
    }
    
    /**
     * Update service
     */
    public function update_service($request) {
        global $wpdb;
        
        $service_id = intval($request->get_param('id'));
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        // Check if service exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_services WHERE id = %d",
            $service_id
        ));
        
        if (!$exists) {
            return new WP_Error('not_found', __('Service non trouvé', 'calendar-petsitting'), array('status' => 404));
        }
        
        $data = array(
            'name' => sanitize_text_field($request->get_param('name')),
            'description' => sanitize_textarea_field($request->get_param('description')),
            'type' => sanitize_text_field($request->get_param('type')),
            'min_duration' => intval($request->get_param('min_duration')),
            'step_minutes' => $request->get_param('step_minutes') ? intval($request->get_param('step_minutes')) : null,
            'price_cents' => intval(floatval($request->get_param('price_cents')) * 100),
            'active' => $request->get_param('active') ? 1 : 0
        );
        
        // Validation
        if (empty($data['name']) || !in_array($data['type'], array('daily', 'hourly', 'minute'))) {
            return new WP_Error('invalid_data', __('Données invalides', 'calendar-petsitting'), array('status' => 400));
        }
        
        $result = $wpdb->update(
            $table_services,
            $data,
            array('id' => $service_id),
            array('%s', '%s', '%s', '%d', '%d', '%d', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'message' => __('Service mis à jour avec succès', 'calendar-petsitting')
                )
            ));
        } else {
            return new WP_Error('update_failed', __('Erreur lors de la mise à jour du service', 'calendar-petsitting'), array('status' => 500));
        }
    }
    
    /**
     * Delete service
     */
    public function delete_service($request) {
        global $wpdb;
        
        $service_id = intval($request->get_param('id'));
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        $result = $wpdb->delete(
            $table_services,
            array('id' => $service_id),
            array('%d')
        );
        
        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'message' => __('Service supprimé avec succès', 'calendar-petsitting')
                )
            ));
        } else {
            return new WP_Error('delete_failed', __('Erreur lors de la suppression du service', 'calendar-petsitting'), array('status' => 500));
        }
    }
    
    /**
     * Get bookings
     */
    public function get_bookings($request) {
        global $wpdb;
        
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        $table_customers = Calendar_Petsitting_Database::get_table_name('customers');
        
        $page = max(1, intval($request->get_param('page')));
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 20)));
        $offset = ($page - 1) * $per_page;
        
        // Optional filters
        $status = $request->get_param('status');
        $where_clause = '1=1';
        $where_params = array();
        
        if ($status && in_array($status, array('confirmed', 'cancelled', 'pending'))) {
            $where_clause .= ' AND b.status = %s';
            $where_params[] = $status;
        }
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                b.id,
                b.total_price_cents,
                b.status,
                b.created_at,
                c.first_name,
                c.last_name,
                c.email
            FROM $table_bookings b
            LEFT JOIN $table_customers c ON b.customer_id = c.id
            WHERE $where_clause
            ORDER BY b.created_at DESC
            LIMIT %d OFFSET %d",
            array_merge($where_params, array($per_page, $offset))
        ));
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_bookings b WHERE $where_clause",
            $where_params
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $bookings,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => intval($total),
                'total_pages' => ceil($total / $per_page)
            )
        ));
    }
    
    /**
     * Get unavailabilities
     */
    public function get_unavailabilities($request) {
        global $wpdb;
        
        $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
        $table_recurring = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
        
        $type = $request->get_param('type'); // 'single', 'recurring', or both
        
        $data = array();
        
        if (!$type || $type === 'single') {
            $single = $wpdb->get_results(
                "SELECT *, 'single' as type FROM $table_unavailabilities ORDER BY start_datetime ASC"
            );
            $data = array_merge($data, $single);
        }
        
        if (!$type || $type === 'recurring') {
            $recurring = $wpdb->get_results(
                "SELECT *, 'recurring' as type FROM $table_recurring ORDER BY weekday ASC, start_time ASC"
            );
            $data = array_merge($data, $recurring);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $data
        ));
    }
    
    /**
     * Create unavailability
     */
    public function create_unavailability($request) {
        global $wpdb;
        
        $type = $request->get_param('type'); // 'single' or 'recurring'
        
        if ($type === 'recurring') {
            $data = array(
                'rule_type' => 'weekly',
                'weekday' => intval($request->get_param('weekday')),
                'start_time' => sanitize_text_field($request->get_param('start_time')),
                'end_time' => sanitize_text_field($request->get_param('end_time')),
                'start_date' => sanitize_text_field($request->get_param('start_date')),
                'end_date' => $request->get_param('end_date') ? sanitize_text_field($request->get_param('end_date')) : null,
                'reason' => sanitize_text_field($request->get_param('reason'))
            );
            
            // Validation
            if ($data['weekday'] < 0 || $data['weekday'] > 6 || 
                empty($data['start_time']) || empty($data['end_time']) || 
                empty($data['start_date'])) {
                return new WP_Error('invalid_data', __('Données invalides', 'calendar-petsitting'), array('status' => 400));
            }
            
            $table = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
            $result = $wpdb->insert(
                $table,
                $data,
                array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
        } else {
            $data = array(
                'start_datetime' => date('Y-m-d H:i:s', strtotime($request->get_param('start_datetime'))),
                'end_datetime' => date('Y-m-d H:i:s', strtotime($request->get_param('end_datetime'))),
                'reason' => sanitize_text_field($request->get_param('reason'))
            );
            
            // Validation
            if (empty($data['start_datetime']) || empty($data['end_datetime']) ||
                strtotime($data['end_datetime']) <= strtotime($data['start_datetime'])) {
                return new WP_Error('invalid_data', __('Données invalides', 'calendar-petsitting'), array('status' => 400));
            }
            
            $table = Calendar_Petsitting_Database::get_table_name('unavailabilities');
            $result = $wpdb->insert(
                $table,
                $data,
                array('%s', '%s', '%s')
            );
        }
        
        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'id' => $wpdb->insert_id,
                    'message' => __('Indisponibilité créée avec succès', 'calendar-petsitting')
                )
            ));
        } else {
            return new WP_Error('create_failed', __('Erreur lors de la création de l\'indisponibilité', 'calendar-petsitting'), array('status' => 500));
        }
    }
}