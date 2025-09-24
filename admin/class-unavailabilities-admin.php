<?php
/**
 * Unavailabilities admin for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Unavailabilities_Admin class
 */
class Calendar_Petsitting_Unavailabilities_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_post_petsitting_save_unavailability', array($this, 'save_unavailability'));
        add_action('admin_post_petsitting_delete_unavailability', array($this, 'delete_unavailability'));
        add_action('admin_post_petsitting_save_recurring_unavailability', array($this, 'save_recurring_unavailability'));
        add_action('admin_post_petsitting_delete_recurring_unavailability', array($this, 'delete_recurring_unavailability'));
    }
    
    /**
     * Render unavailabilities page
     */
    public function render_unavailabilities_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'single';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'edit':
                if ($type === 'recurring') {
                    $this->render_recurring_form($id);
                } else {
                    $this->render_single_form($id);
                }
                break;
            case 'add':
                if ($type === 'recurring') {
                    $this->render_recurring_form();
                } else {
                    $this->render_single_form();
                }
                break;
            default:
                $this->render_list();
                break;
        }
    }
    
    /**
     * Render unavailabilities list
     */
    private function render_list() {
        global $wpdb;
        
        $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
        $table_recurring = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
        
        // Get single unavailabilities
        $single_unavailabilities = $wpdb->get_results("
            SELECT * FROM $table_unavailabilities 
            ORDER BY start_datetime ASC
        ");
        
        // Get recurring unavailabilities
        $recurring_unavailabilities = $wpdb->get_results("
            SELECT * FROM $table_recurring 
            ORDER BY weekday ASC, start_time ASC
        ");
        
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Indisponibilités', 'calendar-petsitting'); ?>
                <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=add&type=single'); ?>" class="page-title-action">
                    <?php _e('Ajouter une indisponibilité', 'calendar-petsitting'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=add&type=recurring'); ?>" class="page-title-action">
                    <?php _e('Ajouter une récurrence', 'calendar-petsitting'); ?>
                </a>
            </h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        
                        <!-- Single Unavailabilities -->
                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle"><?php _e('Indisponibilités ponctuelles', 'calendar-petsitting'); ?></h2>
                            </div>
                            <div class="inside">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Début', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Fin', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Raison', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Actions', 'calendar-petsitting'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($single_unavailabilities)): ?>
                                            <tr>
                                                <td colspan="4"><?php _e('Aucune indisponibilité ponctuelle.', 'calendar-petsitting'); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($single_unavailabilities as $unavailability): ?>
                                                <tr>
                                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($unavailability->start_datetime))); ?></td>
                                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($unavailability->end_datetime))); ?></td>
                                                    <td><?php echo esc_html($unavailability->reason ?: '-'); ?></td>
                                                    <td>
                                                        <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=edit&type=single&id=' . $unavailability->id); ?>" class="button button-small">
                                                            <?php _e('Éditer', 'calendar-petsitting'); ?>
                                                        </a>
                                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=petsitting_delete_unavailability&id=' . $unavailability->id), 'delete_unavailability_' . $unavailability->id); ?>" 
                                                           class="button button-small button-link-delete" 
                                                           onclick="return confirm('<?php esc_attr_e('Êtes-vous sûr de vouloir supprimer cette indisponibilité ?', 'calendar-petsitting'); ?>')">
                                                            <?php _e('Supprimer', 'calendar-petsitting'); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Recurring Unavailabilities -->
                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle"><?php _e('Indisponibilités récurrentes', 'calendar-petsitting'); ?></h2>
                            </div>
                            <div class="inside">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Jour', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Heure début', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Heure fin', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Période', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Raison', 'calendar-petsitting'); ?></th>
                                            <th><?php _e('Actions', 'calendar-petsitting'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recurring_unavailabilities)): ?>
                                            <tr>
                                                <td colspan="6"><?php _e('Aucune indisponibilité récurrente.', 'calendar-petsitting'); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recurring_unavailabilities as $recurring): ?>
                                                <tr>
                                                    <td><?php echo esc_html($this->get_weekday_name($recurring->weekday)); ?></td>
                                                    <td><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($recurring->start_time))); ?></td>
                                                    <td><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($recurring->end_time))); ?></td>
                                                    <td>
                                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($recurring->start_date))); ?>
                                                        <?php if ($recurring->end_date): ?>
                                                            - <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($recurring->end_date))); ?>
                                                        <?php else: ?>
                                                            - <?php _e('Indéfinie', 'calendar-petsitting'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo esc_html($recurring->reason ?: '-'); ?></td>
                                                    <td>
                                                        <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=edit&type=recurring&id=' . $recurring->id); ?>" class="button button-small">
                                                            <?php _e('Éditer', 'calendar-petsitting'); ?>
                                                        </a>
                                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=petsitting_delete_recurring_unavailability&id=' . $recurring->id), 'delete_recurring_unavailability_' . $recurring->id); ?>" 
                                                           class="button button-small button-link-delete" 
                                                           onclick="return confirm('<?php esc_attr_e('Êtes-vous sûr de vouloir supprimer cette récurrence ?', 'calendar-petsitting'); ?>')">
                                                            <?php _e('Supprimer', 'calendar-petsitting'); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render single unavailability form
     */
    private function render_single_form($unavailability_id = 0) {
        global $wpdb;
        
        $unavailability = null;
        if ($unavailability_id > 0) {
            $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
            $unavailability = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_unavailabilities WHERE id = %d", $unavailability_id));
            
            if (!$unavailability) {
                wp_die(__('Indisponibilité non trouvée.', 'calendar-petsitting'));
            }
        }
        
        $is_edit = ($unavailability !== null);
        $title = $is_edit ? __('Éditer l\'indisponibilité', 'calendar-petsitting') : __('Ajouter une indisponibilité', 'calendar-petsitting');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="petsitting_save_unavailability">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="unavailability_id" value="<?php echo esc_attr($unavailability->id); ?>">
                <?php endif; ?>
                <?php wp_nonce_field('save_unavailability', 'petsitting_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="start_datetime"><?php _e('Date/Heure de début *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" id="start_datetime" name="start_datetime" 
                                   value="<?php echo $is_edit ? esc_attr(date('Y-m-d\TH:i', strtotime($unavailability->start_datetime))) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="end_datetime"><?php _e('Date/Heure de fin *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" id="end_datetime" name="end_datetime" 
                                   value="<?php echo $is_edit ? esc_attr(date('Y-m-d\TH:i', strtotime($unavailability->end_datetime))) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="reason"><?php _e('Raison', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="reason" name="reason" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($unavailability->reason) : ''; ?>">
                            <p class="description"><?php _e('Raison de l\'indisponibilité (optionnel)', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($is_edit ? __('Mettre à jour', 'calendar-petsitting') : __('Ajouter', 'calendar-petsitting')); ?>
                
                <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-unavailabilities'); ?>" class="button button-secondary">
                    <?php _e('Retour à la liste', 'calendar-petsitting'); ?>
                </a>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render recurring unavailability form
     */
    private function render_recurring_form($recurring_id = 0) {
        global $wpdb;
        
        $recurring = null;
        if ($recurring_id > 0) {
            $table_recurring = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
            $recurring = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_recurring WHERE id = %d", $recurring_id));
            
            if (!$recurring) {
                wp_die(__('Récurrence non trouvée.', 'calendar-petsitting'));
            }
        }
        
        $is_edit = ($recurring !== null);
        $title = $is_edit ? __('Éditer la récurrence', 'calendar-petsitting') : __('Ajouter une récurrence', 'calendar-petsitting');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="petsitting_save_recurring_unavailability">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="recurring_id" value="<?php echo esc_attr($recurring->id); ?>">
                <?php endif; ?>
                <?php wp_nonce_field('save_recurring_unavailability', 'petsitting_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="weekday"><?php _e('Jour de la semaine *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <select id="weekday" name="weekday" required>
                                <option value=""><?php _e('Sélectionnez un jour', 'calendar-petsitting'); ?></option>
                                <?php 
                                $weekdays = array(
                                    0 => __('Dimanche', 'calendar-petsitting'),
                                    1 => __('Lundi', 'calendar-petsitting'),
                                    2 => __('Mardi', 'calendar-petsitting'),
                                    3 => __('Mercredi', 'calendar-petsitting'),
                                    4 => __('Jeudi', 'calendar-petsitting'),
                                    5 => __('Vendredi', 'calendar-petsitting'),
                                    6 => __('Samedi', 'calendar-petsitting')
                                );
                                foreach ($weekdays as $value => $name): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php echo ($is_edit && $recurring->weekday == $value) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="start_time"><?php _e('Heure de début *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="time" id="start_time" name="start_time" 
                                   value="<?php echo $is_edit ? esc_attr($recurring->start_time) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="end_time"><?php _e('Heure de fin *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="time" id="end_time" name="end_time" 
                                   value="<?php echo $is_edit ? esc_attr($recurring->end_time) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="start_date"><?php _e('Date de début *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="start_date" name="start_date" 
                                   value="<?php echo $is_edit ? esc_attr($recurring->start_date) : ''; ?>" required>
                            <p class="description"><?php _e('À partir de quelle date cette récurrence s\'applique', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="end_date"><?php _e('Date de fin', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="end_date" name="end_date" 
                                   value="<?php echo $is_edit ? esc_attr($recurring->end_date) : ''; ?>">
                            <p class="description"><?php _e('Jusqu\'à quelle date cette récurrence s\'applique (laisser vide pour indéfinie)', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="reason"><?php _e('Raison', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="reason" name="reason" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($recurring->reason) : ''; ?>">
                            <p class="description"><?php _e('Raison de l\'indisponibilité (optionnel)', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($is_edit ? __('Mettre à jour', 'calendar-petsitting') : __('Ajouter', 'calendar-petsitting')); ?>
                
                <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-unavailabilities'); ?>" class="button button-secondary">
                    <?php _e('Retour à la liste', 'calendar-petsitting'); ?>
                </a>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save single unavailability
     */
    public function save_unavailability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['petsitting_nonce'], 'save_unavailability')) {
            wp_die(__('Erreur de sécurité', 'calendar-petsitting'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'calendar-petsitting'));
        }
        
        global $wpdb;
        
        $unavailability_id = isset($_POST['unavailability_id']) ? intval($_POST['unavailability_id']) : 0;
        $is_edit = ($unavailability_id > 0);
        
        // Sanitize and validate data
        $start_datetime = sanitize_text_field($_POST['start_datetime']);
        $end_datetime = sanitize_text_field($_POST['end_datetime']);
        $reason = sanitize_text_field($_POST['reason']);
        
        // Validation
        if (empty($start_datetime) || empty($end_datetime)) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=' . ($is_edit ? 'edit&type=single&id=' . $unavailability_id : 'add&type=single') . '&error=dates_required'));
            exit;
        }
        
        if (strtotime($end_datetime) <= strtotime($start_datetime)) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=' . ($is_edit ? 'edit&type=single&id=' . $unavailability_id : 'add&type=single') . '&error=invalid_date_range'));
            exit;
        }
        
        $data = array(
            'start_datetime' => date('Y-m-d H:i:s', strtotime($start_datetime)),
            'end_datetime' => date('Y-m-d H:i:s', strtotime($end_datetime)),
            'reason' => $reason
        );
        
        $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
        
        if ($is_edit) {
            $result = $wpdb->update(
                $table_unavailabilities,
                $data,
                array('id' => $unavailability_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&updated=1'));
            } else {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=edit&type=single&id=' . $unavailability_id . '&error=update_failed'));
            }
        } else {
            $result = $wpdb->insert(
                $table_unavailabilities,
                $data,
                array('%s', '%s', '%s')
            );
            
            if ($result) {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&created=1'));
            } else {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=add&type=single&error=create_failed'));
            }
        }
        exit;
    }
    
    /**
     * Save recurring unavailability
     */
    public function save_recurring_unavailability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['petsitting_nonce'], 'save_recurring_unavailability')) {
            wp_die(__('Erreur de sécurité', 'calendar-petsitting'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'calendar-petsitting'));
        }
        
        global $wpdb;
        
        $recurring_id = isset($_POST['recurring_id']) ? intval($_POST['recurring_id']) : 0;
        $is_edit = ($recurring_id > 0);
        
        // Sanitize and validate data
        $weekday = intval($_POST['weekday']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $reason = sanitize_text_field($_POST['reason']);
        
        // Validation
        if ($weekday < 0 || $weekday > 6 || empty($start_time) || empty($end_time) || empty($start_date)) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=' . ($is_edit ? 'edit&type=recurring&id=' . $recurring_id : 'add&type=recurring') . '&error=fields_required'));
            exit;
        }
        
        if ($end_time <= $start_time) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=' . ($is_edit ? 'edit&type=recurring&id=' . $recurring_id : 'add&type=recurring') . '&error=invalid_time_range'));
            exit;
        }
        
        if ($end_date && strtotime($end_date) <= strtotime($start_date)) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=' . ($is_edit ? 'edit&type=recurring&id=' . $recurring_id : 'add&type=recurring') . '&error=invalid_date_range'));
            exit;
        }
        
        $data = array(
            'rule_type' => 'weekly',
            'weekday' => $weekday,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'reason' => $reason
        );
        
        $table_recurring = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
        
        if ($is_edit) {
            $result = $wpdb->update(
                $table_recurring,
                $data,
                array('id' => $recurring_id),
                array('%s', '%d', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&updated=1'));
            } else {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=edit&type=recurring&id=' . $recurring_id . '&error=update_failed'));
            }
        } else {
            $result = $wpdb->insert(
                $table_recurring,
                $data,
                array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&created=1'));
            } else {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&action=add&type=recurring&error=create_failed'));
            }
        }
        exit;
    }
    
    /**
     * Delete single unavailability
     */
    public function delete_unavailability() {
        $unavailability_id = intval($_GET['id']);
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_unavailability_' . $unavailability_id)) {
            wp_die(__('Erreur de sécurité', 'calendar-petsitting'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'calendar-petsitting'));
        }
        
        global $wpdb;
        
        $table_unavailabilities = Calendar_Petsitting_Database::get_table_name('unavailabilities');
        
        $result = $wpdb->delete(
            $table_unavailabilities,
            array('id' => $unavailability_id),
            array('%d')
        );
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&deleted=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&error=delete_failed'));
        }
        exit;
    }
    
    /**
     * Delete recurring unavailability
     */
    public function delete_recurring_unavailability() {
        $recurring_id = intval($_GET['id']);
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_recurring_unavailability_' . $recurring_id)) {
            wp_die(__('Erreur de sécurité', 'calendar-petsitting'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'calendar-petsitting'));
        }
        
        global $wpdb;
        
        $table_recurring = Calendar_Petsitting_Database::get_table_name('recurring_unavailabilities');
        
        $result = $wpdb->delete(
            $table_recurring,
            array('id' => $recurring_id),
            array('%d')
        );
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&deleted=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-unavailabilities&error=delete_failed'));
        }
        exit;
    }
    
    /**
     * Show admin notices
     */
    private function show_admin_notices() {
        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Indisponibilité créée avec succès.', 'calendar-petsitting') . '</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Indisponibilité mise à jour avec succès.', 'calendar-petsitting') . '</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Indisponibilité supprimée avec succès.', 'calendar-petsitting') . '</p></div>';
        }
        
        // Error messages
        $errors = array(
            'dates_required' => __('Les dates de début et fin sont requises.', 'calendar-petsitting'),
            'fields_required' => __('Tous les champs obligatoires doivent être remplis.', 'calendar-petsitting'),
            'invalid_date_range' => __('La date/heure de fin doit être postérieure à celle de début.', 'calendar-petsitting'),
            'invalid_time_range' => __('L\'heure de fin doit être postérieure à celle de début.', 'calendar-petsitting'),
            'create_failed' => __('Erreur lors de la création de l\'indisponibilité.', 'calendar-petsitting'),
            'update_failed' => __('Erreur lors de la mise à jour de l\'indisponibilité.', 'calendar-petsitting'),
            'delete_failed' => __('Erreur lors de la suppression de l\'indisponibilité.', 'calendar-petsitting')
        );
        
        if (isset($_GET['error']) && isset($errors[$_GET['error']])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($errors[$_GET['error']]) . '</p></div>';
        }
    }
    
    /**
     * Get weekday name
     */
    private function get_weekday_name($weekday) {
        $weekdays = array(
            0 => __('Dimanche', 'calendar-petsitting'),
            1 => __('Lundi', 'calendar-petsitting'),
            2 => __('Mardi', 'calendar-petsitting'),
            3 => __('Mercredi', 'calendar-petsitting'),
            4 => __('Jeudi', 'calendar-petsitting'),
            5 => __('Vendredi', 'calendar-petsitting'),
            6 => __('Samedi', 'calendar-petsitting')
        );
        
        return isset($weekdays[$weekday]) ? $weekdays[$weekday] : $weekday;
    }
}