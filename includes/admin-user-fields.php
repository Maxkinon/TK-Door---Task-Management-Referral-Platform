<?php
/**
 * WordPress Admin User Profile Fields
 * 
 * Adds custom fields to the WordPress admin user edit screen
 * to allow editing of Indoor Tasks specific user data like country
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom fields to user profile in WordPress admin
 */
add_action('show_user_profile', 'indoor_tasks_show_extra_profile_fields');
add_action('edit_user_profile', 'indoor_tasks_show_extra_profile_fields');

function indoor_tasks_show_extra_profile_fields($user) {
    // Get current country
    $current_country = get_user_meta($user->ID, 'indoor_tasks_country', true);
    
    // Country mapping - same as used throughout the plugin
    $countries = array(
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina',
        'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium',
        'BR' => 'Brazil', 'CA' => 'Canada', 'CN' => 'China', 'CO' => 'Colombia',
        'EG' => 'Egypt', 'FR' => 'France', 'DE' => 'Germany', 'GH' => 'Ghana',
        'GR' => 'Greece', 'IN' => 'India', 'ID' => 'Indonesia', 'IT' => 'Italy',
        'JP' => 'Japan', 'KE' => 'Kenya', 'MY' => 'Malaysia', 'MX' => 'Mexico',
        'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'PK' => 'Pakistan',
        'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'RU' => 'Russia',
        'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'ZA' => 'South Africa', 'KR' => 'South Korea',
        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
        'TW' => 'Taiwan', 'TH' => 'Thailand', 'TR' => 'Turkey', 'AE' => 'UAE',
        'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam', 'ZW' => 'Zimbabwe'
    );
    
    // Get other Indoor Tasks user data for display
    $points = get_user_meta($user->ID, 'indoor_tasks_points', true) ?: '0';
    $wallet_balance = get_user_meta($user->ID, 'indoor_tasks_wallet_balance', true) ?: '0';
    
    // Use points as primary balance (this is the actual wallet balance)
    $display_balance = !empty($points) ? $points : $wallet_balance;
    
    $completed_tasks = get_user_meta($user->ID, 'indoor_tasks_completed_tasks', true) ?: '0';
    $kyc_status = get_user_meta($user->ID, 'indoor_tasks_kyc_status', true) ?: 'not_submitted';
    $phone_number = get_user_meta($user->ID, 'indoor_tasks_phone_number', true) ?: '';
    $referral_code = get_user_meta($user->ID, 'indoor_tasks_referral_code', true) ?: '';
    $referred_by = get_user_meta($user->ID, 'indoor_tasks_referred_by', true) ?: '';
    ?>
    
    <h3><?php _e('Indoor Tasks Information', 'indoor-tasks'); ?></h3>
    
    <table class="form-table">
        <tr>
            <th>
                <label for="indoor_tasks_country"><?php _e('Country', 'indoor-tasks'); ?></label>
            </th>
            <td>
                <select name="indoor_tasks_country" id="indoor_tasks_country" class="regular-text">
                    <option value=""><?php _e('Select Country', 'indoor-tasks'); ?></option>
                    <?php foreach ($countries as $code => $name): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($current_country, $code); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Select the user\'s country for task targeting and statistics.', 'indoor-tasks'); ?></p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="indoor_tasks_phone_number"><?php _e('Phone Number', 'indoor-tasks'); ?></label>
            </th>
            <td>
                <input type="tel" name="indoor_tasks_phone_number" id="indoor_tasks_phone_number" 
                       value="<?php echo esc_attr($phone_number); ?>" class="regular-text" />
                <p class="description"><?php _e('User\'s phone number for contact and verification.', 'indoor-tasks'); ?></p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="indoor_tasks_referral_code"><?php _e('Referral Code', 'indoor-tasks'); ?></label>
            </th>
            <td>
                <input type="text" name="indoor_tasks_referral_code" id="indoor_tasks_referral_code" 
                       value="<?php echo esc_attr($referral_code); ?>" class="regular-text" readonly />
                <p class="description"><?php _e('User\'s unique referral code (read-only).', 'indoor-tasks'); ?></p>
            </td>
        </tr>
        <?php if ($referred_by): ?>
        <tr>
            <th><?php _e('Referred By', 'indoor-tasks'); ?></th>
            <td>
                <strong><?php echo esc_html($referred_by); ?></strong>
                <p class="description"><?php _e('The referral code that was used when this user registered.', 'indoor-tasks'); ?></p>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    
    <h4><?php _e('Indoor Tasks Statistics', 'indoor-tasks'); ?></h4>
    <table class="form-table">
        <tr>
            <th><?php _e('Wallet Balance', 'indoor-tasks'); ?></th>
            <td>
                <strong>₹<?php echo number_format((float)$display_balance, 2); ?></strong>
                <p class="description"><?php _e('Current wallet balance in the Indoor Tasks system (shown in points).', 'indoor-tasks'); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php _e('Points', 'indoor-tasks'); ?></th>
            <td>
                <strong><?php echo number_format((int)$points); ?></strong>
                <p class="description"><?php _e('Current points earned by the user (this is the actual wallet balance).', 'indoor-tasks'); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php _e('Completed Tasks', 'indoor-tasks'); ?></th>
            <td>
                <strong><?php echo number_format((int)$completed_tasks); ?></strong>
                <p class="description"><?php _e('Total number of tasks completed by this user.', 'indoor-tasks'); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php _e('KYC Status', 'indoor-tasks'); ?></th>
            <td>
                <?php
                $kyc_statuses = array(
                    'not_submitted' => __('Not Submitted', 'indoor-tasks'),
                    'pending' => __('Pending Review', 'indoor-tasks'),
                    'approved' => __('Approved', 'indoor-tasks'),
                    'rejected' => __('Rejected', 'indoor-tasks')
                );
                $status_colors = array(
                    'not_submitted' => '#999',
                    'pending' => '#f39c12',
                    'approved' => '#27ae60',
                    'rejected' => '#e74c3c'
                );
                $status_label = isset($kyc_statuses[$kyc_status]) ? $kyc_statuses[$kyc_status] : $kyc_status;
                $status_color = isset($status_colors[$kyc_status]) ? $status_colors[$kyc_status] : '#999';
                ?>
                <span style="
                    background: <?php echo esc_attr($status_color); ?>;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: bold;
                ">
                    <?php echo esc_html($status_label); ?>
                </span>
                <p class="description"><?php _e('Current KYC verification status.', 'indoor-tasks'); ?></p>
            </td>
        </tr>
    </table>
    
    <style>
    .form-table th {
        width: 200px;
        padding-left: 10px;
    }
    .form-table td {
        padding-left: 15px;
    }
    #indoor_tasks_country {
        min-width: 250px;
    }
    </style>
    <?php
}

/**
 * Save custom fields when user profile is updated
 */
add_action('personal_options_update', 'indoor_tasks_save_extra_profile_fields');
add_action('edit_user_profile_update', 'indoor_tasks_save_extra_profile_fields');

function indoor_tasks_save_extra_profile_fields($user_id) {
    // Check user permissions
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    
    // Verify nonce for security (WordPress handles this automatically for user profile pages)
    
    // Save country
    if (isset($_POST['indoor_tasks_country'])) {
        $country = sanitize_text_field($_POST['indoor_tasks_country']);
        
        // Validate country code
        $valid_countries = array(
            'AF', 'AL', 'DZ', 'AR', 'AU', 'AT', 'BD', 'BE', 'BR', 'CA', 'CN', 'CO',
            'EG', 'FR', 'DE', 'GH', 'GR', 'IN', 'ID', 'IT', 'JP', 'KE', 'MY', 'MX',
            'NL', 'NZ', 'NG', 'PK', 'PH', 'PL', 'PT', 'RU', 'SA', 'SG', 'ZA', 'KR',
            'ES', 'LK', 'SE', 'CH', 'TW', 'TH', 'TR', 'AE', 'GB', 'US', 'VN', 'ZW'
        );
        
        if (empty($country) || in_array($country, $valid_countries)) {
            update_user_meta($user_id, 'indoor_tasks_country', $country);
            
            // Log the activity
            if (function_exists('indoor_tasks_add_user_activity')) {
                $old_country = get_user_meta($user_id, 'indoor_tasks_country', true);
                if ($old_country !== $country) {
                    $country_names = array(
                        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina',
                        'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium',
                        'BR' => 'Brazil', 'CA' => 'Canada', 'CN' => 'China', 'CO' => 'Colombia',
                        'EG' => 'Egypt', 'FR' => 'France', 'DE' => 'Germany', 'GH' => 'Ghana',
                        'GR' => 'Greece', 'IN' => 'India', 'ID' => 'Indonesia', 'IT' => 'Italy',
                        'JP' => 'Japan', 'KE' => 'Kenya', 'MY' => 'Malaysia', 'MX' => 'Mexico',
                        'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'PK' => 'Pakistan',
                        'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'RU' => 'Russia',
                        'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'ZA' => 'South Africa', 'KR' => 'South Korea',
                        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
                        'TW' => 'Taiwan', 'TH' => 'Thailand', 'TR' => 'Turkey', 'AE' => 'UAE',
                        'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam', 'ZW' => 'Zimbabwe'
                    );
                    
                    $old_country_name = isset($country_names[$old_country]) ? $country_names[$old_country] : $old_country;
                    $new_country_name = isset($country_names[$country]) ? $country_names[$country] : $country;
                    
                    if (empty($country)) {
                        $description = sprintf('Country changed from %s to [Not Set] by admin', $old_country_name);
                    } else {
                        $description = sprintf('Country changed from %s to %s by admin', $old_country_name, $new_country_name);
                    }
                    
                    indoor_tasks_add_user_activity($user_id, 'profile_update', $description);
                }
            }
        }
    }
    
    // Save phone number
    if (isset($_POST['indoor_tasks_phone_number'])) {
        $phone_number = sanitize_text_field($_POST['indoor_tasks_phone_number']);
        update_user_meta($user_id, 'indoor_tasks_phone_number', $phone_number);
    }
    
    // Save referral code (only if it's empty, don't allow changing existing codes)
    if (isset($_POST['indoor_tasks_referral_code'])) {
        $referral_code = sanitize_text_field($_POST['indoor_tasks_referral_code']);
        $existing_code = get_user_meta($user_id, 'indoor_tasks_referral_code', true);
        if (empty($existing_code) && !empty($referral_code)) {
            update_user_meta($user_id, 'indoor_tasks_referral_code', $referral_code);
        }
    }
    
    return true;
}

/**
 * Add Indoor Tasks column to Users list table
 */
add_filter('manage_users_columns', 'indoor_tasks_add_user_columns');

function indoor_tasks_add_user_columns($columns) {
    // Ensure phone column is added with proper priority
    $new_columns = [];
    
    // Add columns in specific order for better visibility
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        
        // Add our columns after username
        if ($key === 'username') {
            $new_columns['indoor_tasks_phone'] = __('Phone Number', 'indoor-tasks');
            $new_columns['indoor_tasks_country'] = __('Country', 'indoor-tasks');
            $new_columns['indoor_tasks_kyc'] = __('KYC Status', 'indoor-tasks');
            $new_columns['indoor_tasks_wallet'] = __('Wallet', 'indoor-tasks');
            $new_columns['indoor_tasks_tasks'] = __('Tasks', 'indoor-tasks');
        }
    }
    
    return $new_columns;
}

/**
 * Fill custom columns with data
 */
add_action('manage_users_custom_column', 'indoor_tasks_fill_user_columns', 10, 3);

function indoor_tasks_fill_user_columns($value, $column_name, $user_id) {
    switch ($column_name) {
        case 'indoor_tasks_country':
            $country_code = get_user_meta($user_id, 'indoor_tasks_country', true);
            if ($country_code) {
                $country_names = array(
                    'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina',
                    'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium',
                    'BR' => 'Brazil', 'CA' => 'Canada', 'CN' => 'China', 'CO' => 'Colombia',
                    'EG' => 'Egypt', 'FR' => 'France', 'DE' => 'Germany', 'GH' => 'Ghana',
                    'GR' => 'Greece', 'IN' => 'India', 'ID' => 'Indonesia', 'IT' => 'Italy',
                    'JP' => 'Japan', 'KE' => 'Kenya', 'MY' => 'Malaysia', 'MX' => 'Mexico',
                    'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'PK' => 'Pakistan',
                    'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'RU' => 'Russia',
                    'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'ZA' => 'South Africa', 'KR' => 'South Korea',
                    'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
                    'TW' => 'Taiwan', 'TH' => 'Thailand', 'TR' => 'Turkey', 'AE' => 'UAE',
                    'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam', 'ZW' => 'Zimbabwe'
                );
                
                $country_name = isset($country_names[$country_code]) ? $country_names[$country_code] : $country_code;
                return '<span title="' . esc_attr($country_name) . ' (' . esc_attr($country_code) . ')">' . 
                       esc_html($country_name) . '</span>';
            } else {
                return '<span style="color: #999;">—</span>';
            }
            break;
            
        case 'indoor_tasks_phone':
            $phone_number = get_user_meta($user_id, 'indoor_tasks_phone_number', true);
            if ($phone_number) {
                return '<span>' . esc_html($phone_number) . '</span>';
            } else {
                return '<span style="color: #999;">—</span>';
            }
            break;
            
        case 'indoor_tasks_kyc':
            $kyc_status = get_user_meta($user_id, 'indoor_tasks_kyc_status', true) ?: 'not_submitted';
            $kyc_statuses = array(
                'not_submitted' => array('label' => __('Not Submitted', 'indoor-tasks'), 'color' => '#999'),
                'pending' => array('label' => __('Pending', 'indoor-tasks'), 'color' => '#f39c12'),
                'approved' => array('label' => __('Verified', 'indoor-tasks'), 'color' => '#27ae60'),
                'rejected' => array('label' => __('Rejected', 'indoor-tasks'), 'color' => '#e74c3c')
            );
            
            $status_info = isset($kyc_statuses[$kyc_status]) ? $kyc_statuses[$kyc_status] : $kyc_statuses['not_submitted'];
            
            return sprintf(
                '<span style="color: %s; font-weight: 500;">%s</span>',
                esc_attr($status_info['color']),
                esc_html($status_info['label'])
            );
            break;
            
        case 'indoor_tasks_wallet':
            // Get current points balance (this is the actual wallet balance)
            $points_balance = get_user_meta($user_id, 'indoor_tasks_points', true) ?: '0';
            
            // Also check if there's a separate wallet balance
            $wallet_balance = get_user_meta($user_id, 'indoor_tasks_wallet_balance', true);
            
            // Use points as primary balance, wallet_balance as fallback
            $display_balance = !empty($points_balance) ? $points_balance : $wallet_balance;
            
            // Format the balance nicely
            if ($display_balance > 0) {
                return '<strong style="color: #27ae60;">' . number_format((float)$display_balance) . ' pts</strong>';
            } else {
                return '<span style="color: #999;">0 pts</span>';
            }
            break;
            
        case 'indoor_tasks_tasks':
            global $wpdb;
            
            // Get actual completed tasks from submissions table
            $completed_tasks = 0;
            $pending_tasks = 0;
            
            // Check if submissions table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}indoor_task_submissions'") === $wpdb->prefix . 'indoor_task_submissions';
            
            if ($table_exists) {
                // Count completed tasks
                $completed_tasks = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
                     WHERE user_id = %d AND status = 'approved'",
                    $user_id
                ));
                
                // Count pending tasks
                $pending_tasks = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}indoor_task_submissions 
                     WHERE user_id = %d AND status IN ('pending', 'submitted')",
                    $user_id
                ));
            } else {
                // Fallback to user meta if table doesn't exist
                $completed_tasks = get_user_meta($user_id, 'indoor_tasks_completed_tasks', true) ?: '0';
                $pending_tasks = get_user_meta($user_id, 'indoor_tasks_pending_tasks', true) ?: '0';
            }
            
            return sprintf(
                '<span title="Completed: %d, Pending: %d"><strong>%d</strong> / %d</span>',
                (int)$completed_tasks,
                (int)$pending_tasks,
                (int)$completed_tasks,
                (int)$pending_tasks
            );
            break;
            
        default:
            return $value;
    }
}

/**
 * Make custom columns sortable
 */
add_filter('manage_users_sortable_columns', 'indoor_tasks_sortable_user_columns');

function indoor_tasks_sortable_user_columns($columns) {
    $columns['indoor_tasks_country'] = 'indoor_tasks_country';
    $columns['indoor_tasks_kyc'] = 'indoor_tasks_kyc';
    $columns['indoor_tasks_wallet'] = 'indoor_tasks_wallet';
    $columns['indoor_tasks_tasks'] = 'indoor_tasks_tasks';
    
    return $columns;
}

/**
 * Handle sorting for custom columns
 */
add_action('pre_get_users', 'indoor_tasks_sort_users_by_meta');

function indoor_tasks_sort_users_by_meta($query) {
    if (!is_admin()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ('indoor_tasks_country' == $orderby) {
        $query->set('meta_key', 'indoor_tasks_country');
        $query->set('orderby', 'meta_value');
    } elseif ('indoor_tasks_kyc' == $orderby) {
        $query->set('meta_key', 'indoor_tasks_kyc_status');
        $query->set('orderby', 'meta_value');
    } elseif ('indoor_tasks_wallet' == $orderby) {
        $query->set('meta_key', 'indoor_tasks_wallet_balance');
        $query->set('orderby', 'meta_value_num');
    } elseif ('indoor_tasks_tasks' == $orderby) {
        $query->set('meta_key', 'indoor_tasks_completed_tasks');
        $query->set('orderby', 'meta_value_num');
    }
}

/**
 * Add CSS for user list table
 */
add_action('admin_head-users.php', 'indoor_tasks_user_list_css');

function indoor_tasks_user_list_css() {
    ?>
    <style>
    .column-indoor_tasks_country,
    .column-indoor_tasks_kyc,
    .column-indoor_tasks_wallet,
    .column-indoor_tasks_tasks {
        width: 100px;
    }
    
    .column-indoor_tasks_country {
        text-align: center;
    }
    
    .column-indoor_tasks_kyc {
        text-align: center;
    }
    
    .column-indoor_tasks_wallet {
        text-align: right;
        font-family: monospace;
    }
    
    .column-indoor_tasks_tasks {
        text-align: center;
        font-family: monospace;
    }
    </style>
    <?php
}
