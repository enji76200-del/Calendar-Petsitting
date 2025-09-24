<?php
/**
 * Bookings admin for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Bookings_Admin class
 */
class Calendar_Petsitting_Bookings_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_post_petsitting_cancel_booking', array($this, 'cancel_booking'));
    }
    
    /**
     * Render bookings page
     */
    public function render_bookings_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'view':
                $this->render_booking_detail($booking_id);
                break;
            default:
                $this->render_list();
                break;
        }
    }
    
    /**
     * Render bookings list
     */
    private function render_list() {
        global $wpdb;
        
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        $table_customers = Calendar_Petsitting_Database::get_table_name('customers');
        $table_booking_items = Calendar_Petsitting_Database::get_table_name('booking_items');
        
        // Get bookings with customer info and item count
        $bookings = $wpdb->get_results("
            SELECT 
                b.id,
                b.total_cents,
                b.status,
                b.created_at,
                c.first_name,
                c.last_name,
                c.email,
                COUNT(bi.id) as item_count
            FROM $table_bookings b
            LEFT JOIN $table_customers c ON b.customer_id = c.id
            LEFT JOIN $table_booking_items bi ON b.id = bi.booking_id
            GROUP BY b.id
            ORDER BY b.created_at DESC
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Réservations', 'calendar-petsitting'); ?></h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Client', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Total', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Statut', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Créneaux', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Créé le', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Actions', 'calendar-petsitting'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="7"><?php _e('Aucune réservation trouvée.', 'calendar-petsitting'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($booking->id); ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></strong><br>
                                    <small><?php echo esc_html($booking->email); ?></small>
                                </td>
                                <td><?php echo $this->format_price($booking->total_cents); ?></td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    $status_text = '';
                                    switch ($booking->status) {
                                        case 'confirmed':
                                            $status_class = 'confirmed';
                                            $status_text = __('Confirmée', 'calendar-petsitting');
                                            break;
                                        case 'cancelled':
                                            $status_class = 'cancelled';
                                            $status_text = __('Annulée', 'calendar-petsitting');
                                            break;
                                        default:
                                            $status_class = 'pending';
                                            $status_text = __('En attente', 'calendar-petsitting');
                                            break;
                                    }
                                    ?>
                                    <span class="booking-status booking-status-<?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($booking->item_count); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_at))); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-bookings&action=view&id=' . $booking->id); ?>" class="button button-small">
                                        <?php _e('Voir', 'calendar-petsitting'); ?>
                                    </a>
                                    <?php if ($booking->status === 'confirmed'): ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=petsitting_cancel_booking&id=' . $booking->id), 'cancel_booking_' . $booking->id); ?>" 
                                           class="button button-small button-link-delete" 
                                           onclick="return confirm('<?php esc_attr_e('Êtes-vous sûr de vouloir annuler cette réservation ?', 'calendar-petsitting'); ?>')">
                                            <?php _e('Annuler', 'calendar-petsitting'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .booking-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .booking-status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        .booking-status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .booking-status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        </style>
        <?php
    }
    
    /**
     * Render booking detail
     */
    private function render_booking_detail($booking_id) {
        global $wpdb;
        
        $table_bookings = Calendar_Petsitting_Database::get_table_name('bookings');
        $table_customers = Calendar_Petsitting_Database::get_table_name('customers');
        $table_booking_items = Calendar_Petsitting_Database::get_table_name('booking_items');
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        // Get booking with customer info
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT 
                b.*,
                c.first_name,
                c.last_name,
                c.email,
                c.phone,
                c.address
            FROM $table_bookings b
            LEFT JOIN $table_customers c ON b.customer_id = c.id
            WHERE b.id = %d
        ", $booking_id));
        
        if (!$booking) {
            wp_die(__('Réservation non trouvée.', 'calendar-petsitting'));
        }
        
        // Get booking items with service info
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT 
                bi.*,
                s.name as service_name,
                s.type as service_type
            FROM $table_booking_items bi
            LEFT JOIN $table_services s ON bi.service_id = s.id
            WHERE bi.booking_id = %d
            ORDER BY bi.start_datetime ASC
        ", $booking_id));
        
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Détail de la réservation', 'calendar-petsitting'); ?> #<?php echo esc_html($booking->id); ?>
                <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-bookings'); ?>" class="page-title-action">
                    <?php _e('Retour à la liste', 'calendar-petsitting'); ?>
                </a>
            </h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <div class="postbox-container" style="width: 100%;">
                <div class="meta-box-sortables">
                    
                    <!-- Client Information -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Informations client', 'calendar-petsitting'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Nom', 'calendar-petsitting'); ?></th>
                                    <td><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Email', 'calendar-petsitting'); ?></th>
                                    <td><a href="mailto:<?php echo esc_attr($booking->email); ?>"><?php echo esc_html($booking->email); ?></a></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Téléphone', 'calendar-petsitting'); ?></th>
                                    <td><?php echo esc_html($booking->phone); ?></td>
                                </tr>
                                <?php if (!empty($booking->address)): ?>
                                <tr>
                                    <th scope="row"><?php _e('Adresse', 'calendar-petsitting'); ?></th>
                                    <td><?php echo esc_html($booking->address); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Booking Information -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Informations réservation', 'calendar-petsitting'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('ID', 'calendar-petsitting'); ?></th>
                                    <td>#<?php echo esc_html($booking->id); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Statut', 'calendar-petsitting'); ?></th>
                                    <td>
                                        <?php 
                                        switch ($booking->status) {
                                            case 'confirmed':
                                                echo '<span style="color: green; font-weight: bold;">' . __('Confirmée', 'calendar-petsitting') . '</span>';
                                                break;
                                            case 'cancelled':
                                                echo '<span style="color: red; font-weight: bold;">' . __('Annulée', 'calendar-petsitting') . '</span>';
                                                break;
                                            default:
                                                echo '<span style="color: orange; font-weight: bold;">' . __('En attente', 'calendar-petsitting') . '</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Total', 'calendar-petsitting'); ?></th>
                                    <td><strong><?php echo $this->format_price($booking->total_cents); ?></strong></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Créé le', 'calendar-petsitting'); ?></th>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_at))); ?></td>
                                </tr>
                                <?php if (!empty($booking->notes)): ?>
                                <tr>
                                    <th scope="row"><?php _e('Notes', 'calendar-petsitting'); ?></th>
                                    <td><?php echo esc_html($booking->notes); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                            
                            <?php if ($booking->status === 'confirmed'): ?>
                            <p>
                                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=petsitting_cancel_booking&id=' . $booking->id . '&redirect=detail'), 'cancel_booking_' . $booking->id); ?>" 
                                   class="button button-secondary" 
                                   onclick="return confirm('<?php esc_attr_e('Êtes-vous sûr de vouloir annuler cette réservation ?', 'calendar-petsitting'); ?>')">
                                    <?php _e('Annuler cette réservation', 'calendar-petsitting'); ?>
                                </a>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Booking Items -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Créneaux réservés', 'calendar-petsitting'); ?></h2>
                        </div>
                        <div class="inside">
                            <?php if (empty($items)): ?>
                                <p><?php _e('Aucun créneau trouvé.', 'calendar-petsitting'); ?></p>
                            <?php else: ?>
                                <table class="wp-list-table widefat">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Service', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Début', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Fin', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Prix unitaire', 'calendar-petsitting'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?php echo esc_html($item->service_name); ?></td>
                                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->start_datetime))); ?></td>
                                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->end_datetime))); ?></td>
                                                <td><?php echo $this->format_price($item->unit_price_cents); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Cancel booking
     */
    public function cancel_booking() {
        $booking_id = intval($_GET['id']);
        $redirect = isset($_GET['redirect']) ? sanitize_text_field($_GET['redirect']) : 'list';
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'cancel_booking_' . $booking_id)) {
            wp_die(__('Erreur de sécurité', 'calendar-petsitting'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'calendar-petsitting'));
        }
        
        // Use the booking manager to cancel the booking
        $booking_manager = new Calendar_Petsitting_Booking_Manager();
        
        try {
            $result = $booking_manager->cancel_booking($booking_id);
            
            if ($result) {
                if ($redirect === 'detail') {
                    wp_redirect(admin_url('admin.php?page=calendar-petsitting-bookings&action=view&id=' . $booking_id . '&cancelled=1'));
                } else {
                    wp_redirect(admin_url('admin.php?page=calendar-petsitting-bookings&cancelled=1'));
                }
            } else {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-bookings&error=cancel_failed'));
            }
        } catch (Exception $e) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-bookings&error=cancel_failed'));
        }
        exit;
    }
    
    /**
     * Show admin notices
     */
    private function show_admin_notices() {
        if (isset($_GET['cancelled'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Réservation annulée avec succès.', 'calendar-petsitting') . '</p></div>';
        }
        
        // Error messages
        $errors = array(
            'cancel_failed' => __('Erreur lors de l\'annulation de la réservation.', 'calendar-petsitting'),
        );
        
        if (isset($_GET['error']) && isset($errors[$_GET['error']])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($errors[$_GET['error']]) . '</p></div>';
        }
    }
    
    /**
     * Format price in cents to euros
     */
    private function format_price($price_cents) {
        $price_euros = $price_cents / 100;
        return number_format_i18n($price_euros, 2) . ' €';
    }
}