<?php
/**
 * Services admin for Calendar Pet Sitting plugin
 *
 * @package Calendar_Petsitting
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar_Petsitting_Services_Admin class
 */
class Calendar_Petsitting_Services_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_post_petsitting_save_service', array($this, 'save_service'));
        add_action('admin_post_petsitting_delete_service', array($this, 'delete_service'));
        add_action('admin_post_petsitting_toggle_service', array($this, 'toggle_service'));
    }
    
    /**
     * Render services list page
     */
    public function render_services_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'edit':
                $this->render_edit_form($service_id);
                break;
            case 'add':
                $this->render_edit_form();
                break;
            default:
                $this->render_list();
                break;
        }
    }
    
    /**
     * Render services list
     */
    private function render_list() {
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        $services = $wpdb->get_results("SELECT * FROM $table_services ORDER BY name ASC");
        
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Services', 'calendar-petsitting'); ?>
                <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-services&action=add'); ?>" class="page-title-action">
                    <?php _e('Ajouter un service', 'calendar-petsitting'); ?>
                </a>
            </h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Nom', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Type', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Durée min', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Pas (min)', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Prix', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Statut', 'calendar-petsitting'); ?></th>
                        <th><?php _e('Actions', 'calendar-petsitting'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="7"><?php _e('Aucun service trouvé.', 'calendar-petsitting'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><strong><?php echo esc_html($service->name); ?></strong><br>
                                    <small><?php echo esc_html($service->description); ?></small>
                                </td>
                                <td><?php echo esc_html(ucfirst($service->type)); ?></td>
                                <td><?php echo esc_html($service->min_duration); ?></td>
                                <td><?php echo esc_html($service->step_minutes ?: '-'); ?></td>
                                <td><?php echo $this->format_price($service->price_cents); ?></td>
                                <td>
                                    <?php if ($service->active): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php _e('Actif', 'calendar-petsitting'); ?>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-dismiss" style="color: red;"></span> <?php _e('Inactif', 'calendar-petsitting'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-services&action=edit&id=' . $service->id); ?>" class="button button-small">
                                        <?php _e('Éditer', 'calendar-petsitting'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=petsitting_toggle_service&id=' . $service->id), 'toggle_service_' . $service->id); ?>" class="button button-small">
                                        <?php echo $service->active ? __('Désactiver', 'calendar-petsitting') : __('Activer', 'calendar-petsitting'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=petsitting_delete_service&id=' . $service->id), 'delete_service_' . $service->id); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('<?php esc_attr_e('Êtes-vous sûr de vouloir supprimer ce service ?', 'calendar-petsitting'); ?>')">
                                        <?php _e('Supprimer', 'calendar-petsitting'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render edit/add form
     */
    private function render_edit_form($service_id = 0) {
        global $wpdb;
        
        $service = null;
        if ($service_id > 0) {
            $table_services = Calendar_Petsitting_Database::get_table_name('services');
            $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_services WHERE id = %d", $service_id));
            
            if (!$service) {
                wp_die(__('Service non trouvé.', 'calendar-petsitting'));
            }
        }
        
        $is_edit = ($service !== null);
        $title = $is_edit ? __('Éditer le service', 'calendar-petsitting') : __('Ajouter un service', 'calendar-petsitting');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="petsitting_save_service">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="service_id" value="<?php echo esc_attr($service->id); ?>">
                <?php endif; ?>
                <?php wp_nonce_field('save_service', 'petsitting_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="name"><?php _e('Nom *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="name" name="name" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($service->name) : ''; ?>" required>
                            <p class="description"><?php _e('Nom du service affiché aux clients', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="description"><?php _e('Description', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <textarea id="description" name="description" class="large-text" rows="3"><?php echo $is_edit ? esc_textarea($service->description) : ''; ?></textarea>
                            <p class="description"><?php _e('Description détaillée du service', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="type"><?php _e('Type *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <select id="type" name="type" required>
                                <option value="daily" <?php echo ($is_edit && $service->type === 'daily') ? 'selected' : ''; ?>>
                                    <?php _e('Journalier', 'calendar-petsitting'); ?>
                                </option>
                                <option value="hourly" <?php echo ($is_edit && $service->type === 'hourly') ? 'selected' : ''; ?>>
                                    <?php _e('Horaire', 'calendar-petsitting'); ?>
                                </option>
                                <option value="minute" <?php echo ($is_edit && $service->type === 'minute') ? 'selected' : ''; ?>>
                                    <?php _e('Par minutes', 'calendar-petsitting'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Type de facturation du service', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="min_duration"><?php _e('Durée minimale *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="min_duration" name="min_duration" class="small-text" 
                                   value="<?php echo $is_edit ? esc_attr($service->min_duration) : '1'; ?>" 
                                   min="1" required>
                            <p class="description"><?php _e('Durée minimale (en jours pour journalier, en minutes pour horaire/minute)', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="step_minutes"><?php _e('Pas en minutes', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="step_minutes" name="step_minutes" class="small-text" 
                                   value="<?php echo $is_edit ? esc_attr($service->step_minutes) : ''; ?>" 
                                   min="1">
                            <p class="description"><?php _e('Incrément en minutes (optionnel, pour services horaires/minutes)', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="price_cents"><?php _e('Prix (€) *', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="price_cents" name="price_cents" class="small-text" 
                                   value="<?php echo $is_edit ? esc_attr($service->price_cents / 100) : ''; ?>" 
                                   min="0" step="0.01" required>
                            <p class="description"><?php _e('Prix en euros', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="active"><?php _e('Statut', 'calendar-petsitting'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="active" name="active" value="1" 
                                       <?php echo (!$is_edit || $service->active) ? 'checked' : ''; ?>>
                                <?php _e('Service actif', 'calendar-petsitting'); ?>
                            </label>
                            <p class="description"><?php _e('Seuls les services actifs sont visibles aux clients', 'calendar-petsitting'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($is_edit ? __('Mettre à jour', 'calendar-petsitting') : __('Ajouter', 'calendar-petsitting')); ?>
                
                <a href="<?php echo admin_url('admin.php?page=calendar-petsitting-services'); ?>" class="button button-secondary">
                    <?php _e('Retour à la liste', 'calendar-petsitting'); ?>
                </a>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save service
     */
    public function save_service() {
        // Verify nonce
        if (!isset($_POST['petsitting_nonce']) || !wp_verify_nonce($_POST['petsitting_nonce'], 'save_service')) {
            wp_die(__('Erreur de sécurité', 'calendar-petsitting'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'calendar-petsitting'));
        }
        
        global $wpdb;
        
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $is_edit = ($service_id > 0);
        
        // Sanitize and validate data
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $step_minutes_input = isset($_POST['step_minutes']) ? trim($_POST['step_minutes']) : '';
        $default_step = get_option('calendar_petsitting_default_step_minutes', 30);

        $data = array(
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
            'type' => $type,
            'min_duration' => isset($_POST['min_duration']) ? intval($_POST['min_duration']) : 0,
            'step_minutes' => ($type === 'daily') ? null : (!empty($step_minutes_input) ? intval($step_minutes_input) : intval($default_step)),
            'price_cents' => isset($_POST['price_cents']) ? intval(round(floatval(str_replace(',', '.', $_POST['price_cents'])) * 100)) : 0,
            'active' => isset($_POST['active']) ? 1 : 0
        );
        
        // Validation
        if (empty($data['name'])) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-services' . ($is_edit ? '&action=edit&id=' . $service_id : '&action=add') . '&error=name_required'));
            exit;
        }
        
        if (!in_array($data['type'], array('daily', 'hourly', 'minute'))) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-services' . ($is_edit ? '&action=edit&id=' . $service_id : '&action=add') . '&error=invalid_type'));
            exit;
        }
        
        if ($data['min_duration'] < 1) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-services' . ($is_edit ? '&action=edit&id=' . $service_id : '&action=add') . '&error=invalid_duration'));
            exit;
        }
        
        if ($data['price_cents'] < 0) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-services' . ($is_edit ? '&action=edit&id=' . $service_id : '&action=add') . '&error=invalid_price'));
            exit;
        }
        
    $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        if ($is_edit) {
            // Update existing service
            $formats = array('%s', '%s', '%s', '%d', '%d', '%d', '%d');
            // Si daily, on force NULL en base pour step_minutes en supprimant le format et la valeur
            if ($data['type'] === 'daily') {
                $data['step_minutes'] = null;
            }
            $result = $wpdb->update(
                $table_services,
                $data,
                array('id' => $service_id),
                $formats,
                array('%d')
            );
            
            if ($result !== false) {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&updated=1'));
            } else {
                $err = !empty($wpdb->last_error) ? '&db_error=' . urlencode($wpdb->last_error) : '';
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&action=edit&id=' . $service_id . '&error=update_failed' . $err));
            }
        } else {
            // Insert new service
            $formats = array('%s', '%s', '%s', '%d', '%d', '%d', '%d');
            if ($data['type'] === 'daily') {
                $data['step_minutes'] = null; // stocké NULL en base
            }
            $result = $wpdb->insert(
                $table_services,
                $data,
                $formats
            );
            
            if ($result) {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&created=1'));
            } else {
                $err = !empty($wpdb->last_error) ? '&db_error=' . urlencode($wpdb->last_error) : '';
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&action=add&error=create_failed' . $err));
            }
        }
        exit;
    }
    
    /**
     * Delete service
     */
    public function delete_service() {
        $service_id = intval($_GET['id']);
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_service_' . $service_id)) {
            wp_die(__('Erreur de sécurité', 'calendar-petsitting'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'calendar-petsitting'));
        }
        
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        $result = $wpdb->delete(
            $table_services,
            array('id' => $service_id),
            array('%d')
        );
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&deleted=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&error=delete_failed'));
        }
        exit;
    }
    
    /**
     * Toggle service active status
     */
    public function toggle_service() {
        $service_id = intval($_GET['id']);
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'toggle_service_' . $service_id)) {
            wp_die(__('Erreur de sécurité', 'calendar-petsitting'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'calendar-petsitting'));
        }
        
        global $wpdb;
        
        $table_services = Calendar_Petsitting_Database::get_table_name('services');
        
        // Get current status
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT active FROM $table_services WHERE id = %d",
            $service_id
        ));
        
        if ($current_status !== null) {
            $new_status = $current_status ? 0 : 1;
            
            $result = $wpdb->update(
                $table_services,
                array('active' => $new_status),
                array('id' => $service_id),
                array('%d'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&toggled=1'));
            } else {
                wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&error=toggle_failed'));
            }
        } else {
            wp_redirect(admin_url('admin.php?page=calendar-petsitting-services&error=service_not_found'));
        }
        exit;
    }
    
    /**
     * Show admin notices
     */
    private function show_admin_notices() {
        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Service créé avec succès.', 'calendar-petsitting') . '</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Service mis à jour avec succès.', 'calendar-petsitting') . '</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Service supprimé avec succès.', 'calendar-petsitting') . '</p></div>';
        }
        if (isset($_GET['toggled'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Statut du service modifié avec succès.', 'calendar-petsitting') . '</p></div>';
        }
        
        // Error messages
        $errors = array(
            'name_required' => __('Le nom du service est requis.', 'calendar-petsitting'),
            'invalid_type' => __('Type de service invalide.', 'calendar-petsitting'),
            'invalid_duration' => __('Durée minimale invalide.', 'calendar-petsitting'),
            'invalid_price' => __('Prix invalide.', 'calendar-petsitting'),
            'create_failed' => __('Erreur lors de la création du service.', 'calendar-petsitting'),
            'update_failed' => __('Erreur lors de la mise à jour du service.', 'calendar-petsitting'),
            'delete_failed' => __('Erreur lors de la suppression du service.', 'calendar-petsitting'),
            'toggle_failed' => __('Erreur lors de la modification du statut.', 'calendar-petsitting'),
            'service_not_found' => __('Service non trouvé.', 'calendar-petsitting')
        );
        
        if (isset($_GET['error']) && isset($errors[$_GET['error']])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($errors[$_GET['error']]) . '</p></div>';
            if (!empty($_GET['db_error'])) {
                echo '<div class="notice notice-error is-dismissible"><p><code>' . esc_html($_GET['db_error']) . '</code></p></div>';
            }
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