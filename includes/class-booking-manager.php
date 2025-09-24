<?php
/**
 * Booking manager for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Booking_Manager class
 */
class Calendar_Petsitting_Booking_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor can be used for hooks if needed
    }
    
    /**
     * Create a new booking
     *
     * @param array $customer_data Customer information
     * @param int $service_id Service ID
     * @param array $occurrences Array of booking occurrences
     * @param string $notes Optional notes
     * @return int Booking ID
     * @throws Exception If booking creation fails
     */
    public function create_booking($customer_data, $service_id, $occurrences, $notes = '') {
        global $wpdb;
        
        // Validate input data
        $this->validate_booking_data($customer_data, $service_id, $occurrences);
        
        // Check for overlaps and availability
        $this->check_availability($service_id, $occurrences);
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Create or get customer
            $customer_id = $this->create_or_get_customer($customer_data);
            
            // Get service information
            $service = $this->get_service($service_id);
            if (!$service) {
                throw new Exception(__('Service non trouvé', 'calendar-petsitting'));
            }
            
            // Calculate total price
            $total_price = $this->calculate_total_price($service, $occurrences);
            
            // Create booking record
            $booking_id = $this->create_booking_record($customer_id, $total_price, $notes);
            
            // Create booking items
            $this->create_booking_items($booking_id, $service_id, $service, $occurrences);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return $booking_id;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * Validate booking data
     */
    private function validate_booking_data($customer_data, $service_id, $occurrences) {
        // Validate customer data
        $required_fields = array('first_name', 'last_name', 'email', 'phone');
        foreach ($required_fields as $field) {
            if (empty($customer_data[$field])) {
                throw new Exception(sprintf(__('Le champ %s est requis', 'calendar-petsitting'), $field));
            }
        }
        
        // Validate email
        if (!is_email($customer_data['email'])) {
            throw new Exception(__('Adresse email invalide', 'calendar-petsitting'));
        }
        
        // Validate phone (basic check)
        $phone = preg_replace('/[^0-9+\-\s\(\)]/', '', $customer_data['phone']);
        if (strlen($phone) < 8) {
            throw new Exception(__('Numéro de téléphone invalide', 'calendar-petsitting'));
        }
        
        // Validate service ID
        if (!is_numeric($service_id) || $service_id <= 0) {
            throw new Exception(__('ID de service invalide', 'calendar-petsitting'));
        }
        
        // Validate occurrences
        if (empty($occurrences) || !is_array($occurrences)) {
            throw new Exception(__('Au moins une occurrence est requise', 'calendar-petsitting'));
        }
        
        $timezone = get_option('calendar_petsitting_timezone', 'Europe/Paris');
        $now = new DateTime('now', new DateTimeZone($timezone));
        
        foreach ($occurrences as $index => $occurrence) {
            if (empty($occurrence['start_datetime']) || empty($occurrence['end_datetime'])) {
                throw new Exception(sprintf(__('Dates de début et fin requises pour l\'occurrence %d', 'calendar-petsitting'), $index + 1));
            }
            
            try {
                $start = new DateTime($occurrence['start_datetime'], new DateTimeZone($timezone));
                $end = new DateTime($occurrence['end_datetime'], new DateTimeZone($timezone));
            } catch (Exception $e) {
                throw new Exception(sprintf(__('Format de date invalide pour l\'occurrence %d', 'calendar-petsitting'), $index + 1));
            }
            
            if ($start >= $end) {
                throw new Exception(sprintf(__('La date de fin doit être postérieure à la date de début pour l\'occurrence %d', 'calendar-petsitting'), $index + 1));
            }
            
            if ($start < $now) {
                throw new Exception(sprintf(__('Les réservations dans le passé ne sont pas autorisées (occurrence %d)', 'calendar-petsitting'), $index + 1));
            }
            
            // Check if the duration is reasonable (not more than 7 days)
            $duration_days = $end->diff($start)->days;
            if ($duration_days > 7) {
                throw new Exception(sprintf(__('La durée de l\'occurrence %d ne peut pas dépasser 7 jours', 'calendar-petsitting'), $index + 1));
            }
        }
    }
    
    /**
     * Check availability for all occurrences
     */
    private function check_availability($service_id, $occurrences) {
        $calculator = new Calendar_Petsitting_Availability_Calculator();
        
        foreach ($occurrences as $occurrence) {
            $start = $occurrence['start_datetime'];
            $end = $occurrence['end_datetime'];
            
            if (!$calculator->is_slot_available($start, $end, $service_id)) {
                throw new Exception(__('Ce créneau n\'est pas disponible', 'calendar-petsitting'));
            }
        }
    }
    
    /**
     * Create or get existing customer
     */
    private function create_or_get_customer($customer_data) {
        global $wpdb;
        
        $table_customers = Calendar_Petsitting_Database::get_table_name('customers');
        
        // Check if customer exists by email
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_customers WHERE email = %s",
            $customer_data['email']
        ));
        
        if ($existing_customer) {
            // Update existing customer data
            $wpdb->update(
                $table_customers,
                array(
                    'first_name' => sanitize_text_field($customer_data['first_name']),
                    'last_name' => sanitize_text_field($customer_data['last_name']),
                    'phone' => sanitize_text_field($customer_data['phone']),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing_customer->id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            return $existing_customer->id;
        } else {
            // Create new customer
            $result = $wpdb->insert(
                $table_customers,
                array(
                    'first_name' => sanitize_text_field($customer_data['first_name']),
                    'last_name' => sanitize_text_field($customer_data['last_name']),
                    'email' => sanitize_email($customer_data['email']),
                    'phone' => sanitize_text_field($customer_data['phone'])
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception(__('Erreur lors de la création du client', 'calendar-petsitting'));
            }
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Get service information
     */
    private function get_service($service_id) {
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_services WHERE id = %d AND active = 1",
            $service_id
        ));
    }
    
    /**
     * Calculate total price for all occurrences
     */
    private function calculate_total_price($service, $occurrences) {
        $total_price = 0;
        
        foreach ($occurrences as $occurrence) {
            $start = new DateTime($occurrence['start_datetime']);
            $end = new DateTime($occurrence['end_datetime']);
            
            switch ($service->type) {
                case 'daily':
                    $days = $end->diff($start)->days;
                    if ($days == 0) {
                        $days = 1; // Minimum 1 day
                    }
                    $total_price += $service->price_cents * $days;
                    break;
                    
                case 'hourly':
                    $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                    $hours = ceil($minutes / 60);
                    $total_price += $service->price_cents * $hours;
                    break;
                    
                case 'minute':
                    $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                    $units = ceil($minutes / ($service->step_minutes ?: 15));
                    $total_price += $service->price_cents * $units;
                    break;
            }
        }
        
        return $total_price;
    }
    
    /**
     * Create booking record
     */
    private function create_booking_record($customer_id, $total_price, $notes) {
        global $wpdb;
        
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        
        $result = $wpdb->insert(
            $table_bookings,
            array(
                'customer_id' => $customer_id,
                'total_price_cents' => $total_price,
                'notes' => sanitize_textarea_field($notes),
                'status' => 'confirmed'
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            throw new Exception(__('Erreur lors de la création de la réservation', 'calendar-petsitting'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create booking items
     */
    private function create_booking_items($booking_id, $service_id, $service, $occurrences) {
        global $wpdb;
        
        $table_booking_items = Calendar_Petsitting_Database::get_table_name('booking_items');
        
        foreach ($occurrences as $occurrence) {
            $start = new DateTime($occurrence['start_datetime']);
            $end = new DateTime($occurrence['end_datetime']);
            
            // Calculate unit price for this occurrence
            $unit_price = 0;
            switch ($service->type) {
                case 'daily':
                    $days = $end->diff($start)->days;
                    if ($days == 0) {
                        $days = 1;
                    }
                    $unit_price = $service->price_cents * $days;
                    break;
                    
                case 'hourly':
                    $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                    $hours = ceil($minutes / 60);
                    $unit_price = $service->price_cents * $hours;
                    break;
                    
                case 'minute':
                    $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                    $units = ceil($minutes / ($service->step_minutes ?: 15));
                    $unit_price = $service->price_cents * $units;
                    break;
            }
            
            $result = $wpdb->insert(
                $table_booking_items,
                array(
                    'booking_id' => $booking_id,
                    'service_id' => $service_id,
                    'start_datetime' => $start->format('Y-m-d H:i:s'),
                    'end_datetime' => $end->format('Y-m-d H:i:s'),
                    'unit_price_cents' => $unit_price
                ),
                array('%d', '%d', '%s', '%s', '%d')
            );
            
            if ($result === false) {
                throw new Exception(__('Erreur lors de la création des éléments de réservation', 'calendar-petsitting'));
            }
        }
    }
    
    /**
     * Cancel a booking
     */
    public function cancel_booking($booking_id) {
        global $wpdb;
        
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        
        $result = $wpdb->update(
            $table_bookings,
            array('status' => 'cancelled'),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception(__('Erreur lors de l\'annulation de la réservation', 'calendar-petsitting'));
        }
        
        return true;
    }
}