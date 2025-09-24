<?php
/**
 * Availability calculator for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Availability_Calculator class
 */
class Calendar_Petsitting_Availability_Calculator {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor can be used for hooks if needed
    }
    
    /**
     * Get availability data for a date range
     *
     * @param string $from Start date (ISO format)
     * @param string $to End date (ISO format)
     * @param int|null $service_id Optional service ID filter
     * @return array Availability data for FullCalendar
     */
    public function get_availability($from, $to, $service_id = null) {
        $events = array();
        
        // Get booked slots
        $booked_slots = $this->get_booked_slots($from, $to, $service_id);
        foreach ($booked_slots as $slot) {
            $events[] = array(
                'id' => 'booking-' . $slot->id,
                'title' => __('Réservé', 'calendar-petsitting'),
                'start' => $this->format_datetime_for_fullcalendar($slot->start_datetime),
                'end' => $this->format_datetime_for_fullcalendar($slot->end_datetime),
                'color' => '#dc3545',
                'display' => 'background',
                'overlap' => false,
                'constraint' => false
            );
        }
        
        // Get unavailable slots
        $unavailable_slots = $this->get_unavailable_slots($from, $to);
        foreach ($unavailable_slots as $slot) {
            $events[] = array(
                'id' => 'unavailable-' . $slot->id,
                'title' => $slot->reason ?: __('Indisponible', 'calendar-petsitting'),
                'start' => $this->format_datetime_for_fullcalendar($slot->start_datetime),
                'end' => $this->format_datetime_for_fullcalendar($slot->end_datetime),
                'color' => '#6c757d',
                'display' => 'background',
                'overlap' => false,
                'constraint' => false
            );
        }
        
        // Get recurring unavailable slots
        $recurring_unavailable = $this->get_recurring_unavailable_slots($from, $to);
        foreach ($recurring_unavailable as $slot) {
            $events[] = array(
                'id' => 'recurring-' . $slot['id'] . '-' . $slot['date'],
                'title' => $slot['reason'] ?: __('Indisponible', 'calendar-petsitting'),
                'start' => $slot['start'],
                'end' => $slot['end'],
                'color' => '#6c757d',
                'display' => 'background',
                'overlap' => false,
                'constraint' => false
            );
        }
        
        // Get business hours unavailable slots
        $business_hours_unavailable = $this->get_business_hours_unavailable_slots($from, $to);
        foreach ($business_hours_unavailable as $slot) {
            $events[] = array(
                'id' => 'business-hours-' . $slot['date'] . '-' . $slot['type'],
                'title' => __('Fermé', 'calendar-petsitting'),
                'start' => $slot['start'],
                'end' => $slot['end'],
                'color' => '#343a40',
                'display' => 'background',
                'overlap' => false,
                'constraint' => false
            );
        }
        
        return $events;
    }
    
    /**
     * Get booked time slots
     */
    private function get_booked_slots($from, $to, $service_id = null) {
        global $wpdb;
        
        $table_booking_items = Calendar_Petsitting_Database::get_table_name('booking_items');
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        
        $where_service = '';
        $params = array($from, $to);
        
        if ($service_id) {
            $where_service = 'AND bi.service_id = %d';
            $params[] = $service_id;
        }
        
        $query = $wpdb->prepare(
            "SELECT bi.id, bi.start_datetime, bi.end_datetime, bi.service_id
             FROM $table_booking_items bi
             JOIN $table_bookings b ON bi.booking_id = b.id
             WHERE b.status = 'confirmed'
             AND bi.start_datetime < %s
             AND bi.end_datetime > %s
             $where_service
             ORDER BY bi.start_datetime ASC",
            ...$params
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get unavailable time slots
     */
    private function get_unavailable_slots($from, $to) {
        global $wpdb;
        
        $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, start_datetime, end_datetime, reason
             FROM $table_unavailabilities
             WHERE start_datetime < %s
             AND end_datetime > %s
             ORDER BY start_datetime ASC",
            $to, $from
        ));
    }
    
    /**
     * Get recurring unavailable slots expanded for the date range
     */
    private function get_recurring_unavailable_slots($from, $to) {
        global $wpdb;
        
        $table_recurring = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
        
        $recurring_rules = $wpdb->get_results($wpdb->prepare(
            "SELECT id, weekday, start_time, end_time, start_date, end_date, reason
             FROM $table_recurring
             WHERE start_date <= %s
             AND (end_date IS NULL OR end_date >= %s)
             ORDER BY weekday, start_time ASC",
            $to, $from
        ));
        
        $slots = array();
        $timezone = get_option('calendar_petsitting_timezone', 'Europe/Paris');
        
        foreach ($recurring_rules as $rule) {
            $start_date = new DateTime($from, new DateTimeZone($timezone));
            $end_date = new DateTime($to, new DateTimeZone($timezone));
            
            // Find all occurrences of this weekday in the range
            $current_date = clone $start_date;
            while ($current_date <= $end_date) {
                $current_weekday = (int) $current_date->format('w'); // 0 = Sunday
                
                if ($current_weekday == $rule->weekday) {
                    $rule_start_date = new DateTime($rule->start_date);
                    $rule_end_date = $rule->end_date ? new DateTime($rule->end_date) : null;
                    
                    // Check if this occurrence is within the rule's date range
                    if ($current_date >= $rule_start_date && 
                        (!$rule_end_date || $current_date <= $rule_end_date)) {
                        
                        $start_datetime = clone $current_date;
                        $end_datetime = clone $current_date;
                        
                        // Set the time
                        $start_time_parts = explode(':', $rule->start_time);
                        $end_time_parts = explode(':', $rule->end_time);
                        
                        $start_datetime->setTime(
                            (int) $start_time_parts[0],
                            (int) $start_time_parts[1],
                            0
                        );
                        
                        $end_datetime->setTime(
                            (int) $end_time_parts[0],
                            (int) $end_time_parts[1],
                            0
                        );
                        
                        $slots[] = array(
                            'id' => $rule->id,
                            'date' => $current_date->format('Y-m-d'),
                            'start' => $this->format_datetime_for_fullcalendar($start_datetime->format('Y-m-d H:i:s')),
                            'end' => $this->format_datetime_for_fullcalendar($end_datetime->format('Y-m-d H:i:s')),
                            'reason' => $rule->reason
                        );
                    }
                }
                
                $current_date->add(new DateInterval('P1D'));
            }
        }
        
        return $slots;
    }
    
    /**
     * Get business hours unavailable slots expanded for the date range
     */
    private function get_business_hours_unavailable_slots($from, $to) {
        $business_hours = get_option('calendar_petsitting_business_hours', array());
        
        if (empty($business_hours)) {
            return array();
        }
        
        $slots = array();
        $timezone = get_option('calendar_petsitting_timezone', 'Europe/Paris');
        
        $start_date = new DateTime($from, new DateTimeZone($timezone));
        $end_date = new DateTime($to, new DateTimeZone($timezone));
        
        $current_date = clone $start_date;
        while ($current_date <= $end_date) {
            $weekday = (int) $current_date->format('w'); // 0 = Sunday
            
            if (isset($business_hours[$weekday])) {
                $day_hours = $business_hours[$weekday];
                
                if ($day_hours['closed']) {
                    // Entire day is closed
                    $start_datetime = clone $current_date;
                    $end_datetime = clone $current_date;
                    $start_datetime->setTime(0, 0, 0);
                    $end_datetime->setTime(23, 59, 59);
                    
                    $slots[] = array(
                        'date' => $current_date->format('Y-m-d'),
                        'type' => 'closed',
                        'start' => $this->format_datetime_for_fullcalendar($start_datetime->format('Y-m-d H:i:s')),
                        'end' => $this->format_datetime_for_fullcalendar($end_datetime->format('Y-m-d H:i:s'))
                    );
                } else {
                    // Add unavailable slots before opening time
                    $open_time_parts = explode(':', $day_hours['open_time']);
                    $close_time_parts = explode(':', $day_hours['close_time']);
                    
                    // Before opening hours
                    if ($open_time_parts[0] > 0 || $open_time_parts[1] > 0) {
                        $start_datetime = clone $current_date;
                        $end_datetime = clone $current_date;
                        $start_datetime->setTime(0, 0, 0);
                        $end_datetime->setTime((int) $open_time_parts[0], (int) $open_time_parts[1], 0);
                        
                        $slots[] = array(
                            'date' => $current_date->format('Y-m-d'),
                            'type' => 'before_hours',
                            'start' => $this->format_datetime_for_fullcalendar($start_datetime->format('Y-m-d H:i:s')),
                            'end' => $this->format_datetime_for_fullcalendar($end_datetime->format('Y-m-d H:i:s'))
                        );
                    }
                    
                    // After closing hours
                    if ($close_time_parts[0] < 23 || $close_time_parts[1] < 59) {
                        $start_datetime = clone $current_date;
                        $end_datetime = clone $current_date;
                        $start_datetime->setTime((int) $close_time_parts[0], (int) $close_time_parts[1], 0);
                        $end_datetime->setTime(23, 59, 59);
                        
                        $slots[] = array(
                            'date' => $current_date->format('Y-m-d'),
                            'type' => 'after_hours',
                            'start' => $this->format_datetime_for_fullcalendar($start_datetime->format('Y-m-d H:i:s')),
                            'end' => $this->format_datetime_for_fullcalendar($end_datetime->format('Y-m-d H:i:s'))
                        );
                    }
                }
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $slots;
    }
    
    /**
     * Check if a specific time slot is available
     *
     * @param string $start_datetime Start datetime
     * @param string $end_datetime End datetime
     * @param int|null $service_id Service ID
     * @param int|null $exclude_booking_id Exclude this booking ID from check
     * @return bool True if available
     */
    public function is_slot_available($start_datetime, $end_datetime, $service_id = null, $exclude_booking_id = null) {
        global $wpdb;
        
        // Check for existing bookings
        $table_booking_items = Calendar_Petsitting_Database::get_table_name('booking_items');
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        
        $booking_where = '';
        $booking_params = array($end_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime);
        
        if ($exclude_booking_id) {
            $booking_where = 'AND b.id != %d';
            $booking_params[] = $exclude_booking_id;
        }
        
        $existing_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM $table_booking_items bi
             JOIN $table_bookings b ON bi.booking_id = b.id
             WHERE b.status = 'confirmed'
             AND (
                 (bi.start_datetime < %s AND bi.end_datetime > %s) OR
                 (bi.start_datetime < %s AND bi.end_datetime > %s) OR
                 (bi.start_datetime >= %s AND bi.end_datetime <= %s)
             )
             $booking_where",
            ...$booking_params
        ));
        
        if ($existing_bookings > 0) {
            return false;
        }
        
        // Check for unavailabilities
        $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
        
        $unavailabilities = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM $table_unavailabilities
             WHERE (
                 (start_datetime < %s AND end_datetime > %s) OR
                 (start_datetime < %s AND end_datetime > %s) OR
                 (start_datetime >= %s AND end_datetime <= %s)
             )",
            $end_datetime, $start_datetime,
            $end_datetime, $end_datetime,
            $start_datetime, $end_datetime
        ));
        
        if ($unavailabilities > 0) {
            return false;
        }
        
        // Check for recurring unavailabilities
        if ($this->has_recurring_unavailability($start_datetime, $end_datetime)) {
            return false;
        }
        
        // Check business hours
        if (!$this->is_within_business_hours($start_datetime, $end_datetime)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if there's a recurring unavailability for the given time slot
     */
    private function has_recurring_unavailability($start_datetime, $end_datetime) {
        global $wpdb;
        
        $table_recurring = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
        
        $start = new DateTime($start_datetime);
        $end = new DateTime($end_datetime);
        
        $weekday = (int) $start->format('w'); // 0 = Sunday
        $start_time = $start->format('H:i:s');
        $end_time = $end->format('H:i:s');
        $date = $start->format('Y-m-d');
        
        $recurring_rules = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time, start_date, end_date
             FROM $table_recurring
             WHERE weekday = %d
             AND start_date <= %s
             AND (end_date IS NULL OR end_date >= %s)",
            $weekday, $date, $date
        ));
        
        foreach ($recurring_rules as $rule) {
            // Check if there's an overlap with the recurring rule
            if (($start_time < $rule->end_time && $end_time > $rule->start_time)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if the time slot is within business hours
     */
    private function is_within_business_hours($start_datetime, $end_datetime) {
        $business_hours = get_option('calendar_petsitting_business_hours', array());
        
        if (empty($business_hours)) {
            return true; // No business hours set, allow all times
        }
        
        $start = new DateTime($start_datetime);
        $end = new DateTime($end_datetime);
        
        // Check each day in the time slot
        $current_date = clone $start;
        while ($current_date < $end) {
            $weekday = (int) $current_date->format('w'); // 0 = Sunday
            
            if (isset($business_hours[$weekday])) {
                $day_hours = $business_hours[$weekday];
                
                // If the day is closed, reject the booking
                if ($day_hours['closed']) {
                    return false;
                }
                
                // Check if the time is within business hours
                $open_time_parts = explode(':', $day_hours['open_time']);
                $close_time_parts = explode(':', $day_hours['close_time']);
                
                $day_start = clone $current_date;
                $day_start->setTime((int) $open_time_parts[0], (int) $open_time_parts[1], 0);
                
                $day_end = clone $current_date;
                $day_end->setTime((int) $close_time_parts[0], (int) $close_time_parts[1], 0);
                
                // Check if any part of the booking is outside business hours for this day
                $booking_start_on_day = max($start, $day_start);
                $booking_end_on_day = min($end, $day_end);
                
                // If booking starts before business hours or ends after business hours
                if ($current_date->format('Y-m-d') === $start->format('Y-m-d') && $start < $day_start) {
                    return false;
                }
                
                if ($current_date->format('Y-m-d') === $end->format('Y-m-d') && $end > $day_end) {
                    return false;
                }
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return true;
    }
    
    /**
     * Format datetime for FullCalendar
     */
    private function format_datetime_for_fullcalendar($datetime) {
        $timezone = get_option('calendar_petsitting_timezone', 'Europe/Paris');
        
        // Create DateTime object with proper timezone
        if (is_string($datetime)) {
            $dt = new DateTime($datetime, new DateTimeZone($timezone));
        } else {
            $dt = $datetime;
            $dt->setTimezone(new DateTimeZone($timezone));
        }
        
        return $dt->format('c'); // ISO 8601 format
    }
    
    /**
     * Get available time slots for a specific service and date range
     *
     * @param int $service_id Service ID
     * @param string $date Date (Y-m-d format)
     * @return array Available time slots
     */
    public function get_available_slots_for_service($service_id, $date) {
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_services WHERE id = %d AND active = 1",
            $service_id
        ));
        
        if (!$service) {
            return array();
        }
        
        $slots = array();
        $timezone = get_option('calendar_petsitting_timezone', 'Europe/Paris');
        $date_obj = new DateTime($date, new DateTimeZone($timezone));
        
        switch ($service->type) {
            case 'daily':
                // For daily services, just check if the day is available
                $start_datetime = $date_obj->format('Y-m-d 00:00:00');
                $end_datetime = $date_obj->format('Y-m-d 23:59:59');
                
                if ($this->is_slot_available($start_datetime, $end_datetime, $service_id)) {
                    $slots[] = array(
                        'start' => $start_datetime,
                        'end' => $end_datetime,
                        'title' => __('Journée complète', 'calendar-petsitting')
                    );
                }
                break;
                
            case 'hourly':
            case 'minute':
                // Generate time slots throughout the day
                $step_minutes = $service->step_minutes ?: 30;
                $min_duration = $service->min_duration;
                
                $current_time = clone $date_obj;
                $current_time->setTime(8, 0, 0); // Start at 8:00 AM
                
                $end_of_day = clone $date_obj;
                $end_of_day->setTime(20, 0, 0); // End at 8:00 PM
                
                while ($current_time < $end_of_day) {
                    $slot_end = clone $current_time;
                    $slot_end->add(new DateInterval('PT' . $min_duration . 'M'));
                    
                    if ($slot_end <= $end_of_day) {
                        $start_datetime = $current_time->format('Y-m-d H:i:s');
                        $end_datetime = $slot_end->format('Y-m-d H:i:s');
                        
                        if ($this->is_slot_available($start_datetime, $end_datetime, $service_id)) {
                            $slots[] = array(
                                'start' => $start_datetime,
                                'end' => $end_datetime,
                                'title' => $current_time->format('H:i') . ' - ' . $slot_end->format('H:i')
                            );
                        }
                    }
                    
                    $current_time->add(new DateInterval('PT' . $step_minutes . 'M'));
                }
                break;
        }
        
        return $slots;
    }
}