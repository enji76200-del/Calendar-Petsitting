<?php
/**
 * Shortcode handler for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Shortcode class
 */
class Calendar_Petsitting_Shortcode {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('petsitting_calendar', array($this, 'render_calendar'));
    }
    
    /**
     * Render the calendar shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_calendar($atts) {
        $atts = shortcode_atts(array(
            'view' => 'month',
            'height' => '600',
            'service_id' => '',
            'theme' => 'default'
        ), $atts, 'petsitting_calendar');
        
        // Map simple view names to FullCalendar view names
        $view_mapping = array(
            'month' => 'dayGridMonth',
            'week' => 'timeGridWeek',
            'day' => 'timeGridDay'
        );
        $fullcalendar_view = isset($view_mapping[$atts['view']]) ? $view_mapping[$atts['view']] : 'dayGridMonth';
        
        // Generate unique ID for this calendar instance
        $calendar_id = 'petsitting-calendar-' . uniqid();
        
        // Get services for the booking form
        $services = $this->get_active_services();
        
        ob_start();
        ?>
        <div class="petsitting-calendar-container" data-theme="<?php echo esc_attr($atts['theme']); ?>">
            <div id="<?php echo esc_attr($calendar_id); ?>" 
                 class="petsitting-calendar" 
                 data-view="<?php echo esc_attr($fullcalendar_view); ?>"
                 data-height="<?php echo esc_attr($atts['height']); ?>"
                 data-service-id="<?php echo esc_attr($atts['service_id']); ?>">
            </div>
            
            <!-- Loading indicator -->
            <div class="petsitting-loading" style="display: none;">
                <div class="petsitting-spinner"></div>
                <p><?php _e('Chargement du calendrier...', 'calendar-petsitting'); ?></p>
            </div>
        </div>
        
        <!-- Booking Modal -->
        <div id="petsitting-booking-modal" class="petsitting-modal" style="display: none;">
            <div class="petsitting-modal-content">
                <div class="petsitting-modal-header">
                    <h3><?php _e('Nouvelle réservation', 'calendar-petsitting'); ?></h3>
                    <button type="button" class="petsitting-modal-close">&times;</button>
                </div>
                
                <div class="petsitting-modal-body">
                    <form id="petsitting-booking-form">
                        <div class="petsitting-form-section">
                            <h4><?php _e('Vos informations', 'calendar-petsitting'); ?></h4>
                            
                            <div class="petsitting-form-row">
                                <div class="petsitting-form-group">
                                    <label for="customer_first_name"><?php _e('Prénom *', 'calendar-petsitting'); ?></label>
                                    <input type="text" id="customer_first_name" name="customer[first_name]" required>
                                </div>
                                
                                <div class="petsitting-form-group">
                                    <label for="customer_last_name"><?php _e('Nom *', 'calendar-petsitting'); ?></label>
                                    <input type="text" id="customer_last_name" name="customer[last_name]" required>
                                </div>
                            </div>
                            
                            <div class="petsitting-form-row">
                                <div class="petsitting-form-group">
                                    <label for="customer_email"><?php _e('Email *', 'calendar-petsitting'); ?></label>
                                    <input type="email" id="customer_email" name="customer[email]" required>
                                </div>
                                
                                <div class="petsitting-form-group">
                                    <label for="customer_phone"><?php _e('Téléphone *', 'calendar-petsitting'); ?></label>
                                    <input type="tel" id="customer_phone" name="customer[phone]" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="petsitting-form-section">
                            <h4><?php _e('Service souhaité', 'calendar-petsitting'); ?></h4>
                            
                            <div class="petsitting-form-group">
                                <label for="service_id"><?php _e('Type de service *', 'calendar-petsitting'); ?></label>
                                <select id="service_id" name="service_id" required>
                                    <option value=""><?php _e('Sélectionnez un service', 'calendar-petsitting'); ?></option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo esc_attr($service->id); ?>" 
                                                data-type="<?php echo esc_attr($service->type); ?>"
                                                data-price="<?php echo esc_attr($service->price_cents); ?>"
                                                data-min-duration="<?php echo esc_attr($service->min_duration); ?>"
                                                data-step-minutes="<?php echo esc_attr($service->step_minutes); ?>">
                                            <?php echo esc_html($service->name); ?> 
                                            (<?php echo $this->format_price($service->price_cents); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="petsitting-form-section">
                            <h4><?php _e('Créneaux sélectionnés', 'calendar-petsitting'); ?></h4>
                            
                            <div id="selected-occurrences">
                                <!-- Dynamically populated by JavaScript -->
                            </div>
                            
                            <button type="button" id="add-occurrence" class="petsitting-btn petsitting-btn-secondary">
                                <?php _e('Ajouter un créneau', 'calendar-petsitting'); ?>
                            </button>
                        </div>
                        
                        <div class="petsitting-form-section">
                            <div class="petsitting-form-group">
                                <label for="booking_notes"><?php _e('Remarques (optionnel)', 'calendar-petsitting'); ?></label>
                                <textarea id="booking_notes" name="notes" rows="3" 
                                         placeholder="<?php esc_attr_e('Informations supplémentaires concernant votre demande...', 'calendar-petsitting'); ?>"></textarea>
                            </div>
                        </div>
                        
                        <div class="petsitting-form-section petsitting-booking-summary">
                            <div class="petsitting-summary-row">
                                <span class="petsitting-summary-label"><?php _e('Total:', 'calendar-petsitting'); ?></span>
                                <span class="petsitting-summary-value" id="booking-total">0,00 €</span>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="petsitting-modal-footer">
                    <button type="button" class="petsitting-btn petsitting-btn-secondary" id="cancel-booking">
                        <?php _e('Annuler', 'calendar-petsitting'); ?>
                    </button>
                    <button type="button" class="petsitting-btn petsitting-btn-primary" id="confirm-booking">
                        <?php _e('Confirmer la réservation', 'calendar-petsitting'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Success Modal -->
        <div id="petsitting-success-modal" class="petsitting-modal" style="display: none;">
            <div class="petsitting-modal-content">
                <div class="petsitting-modal-header">
                    <h3><?php _e('Réservation confirmée !', 'calendar-petsitting'); ?></h3>
                    <button type="button" class="petsitting-modal-close">&times;</button>
                </div>
                
                <div class="petsitting-modal-body">
                    <div class="petsitting-success-message">
                        <div class="petsitting-success-icon">✓</div>
                        <p><?php _e('Votre réservation a été enregistrée avec succès.', 'calendar-petsitting'); ?></p>
                        <p><?php _e('Nous vous contacterons prochainement pour confirmer les détails.', 'calendar-petsitting'); ?></p>
                    </div>
                </div>
                
                <div class="petsitting-modal-footer">
                    <button type="button" class="petsitting-btn petsitting-btn-primary" id="close-success-modal">
                        <?php _e('Fermer', 'calendar-petsitting'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof window.initPetsittingCalendar === 'function') {
                    window.initPetsittingCalendar('<?php echo esc_js($calendar_id); ?>');
                }
            });
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get active services
     */
    private function get_active_services() {
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        return $wpdb->get_results(
            "SELECT id, name, description, type, min_duration, step_minutes, price_cents 
             FROM $table_services 
             WHERE active = 1 
             ORDER BY name ASC"
        );
    }
    
    /**
     * Format price in cents to euros
     */
    private function format_price($price_cents) {
        $price_euros = $price_cents / 100;
        return number_format_i18n($price_euros, 2) . ' €';
    }
}