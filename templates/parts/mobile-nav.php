<?php
/**
 * Mobile Bottom Navigation Only
 * Enhanced mobile bottom navigation for Indoor Tasks
 */

// Don't show on auth pages
if (!is_user_logged_in() || is_page_template('indoor-tasks/templates/tk-indoor-auth.php')) {
    return;
}

// Get current user data
$user_id = get_current_user_id();
$user_data = get_userdata($user_id);
$display_name = $user_data->display_name;
$avatar_url = get_avatar_url($user_id, array('size' => 80));

// Get current page
$current_page = '';
if (indoor_tasks_is_page('dashboard')) {
    $current_page = 'dashboard';
} elseif (indoor_tasks_is_page('tasks') || indoor_tasks_is_page('task-list')) {
    $current_page = 'tasks';
} elseif (indoor_tasks_is_page('wallet')) {
    $current_page = 'wallet';
} elseif (indoor_tasks_is_page('withdraw') || indoor_tasks_is_page('withdrawal')) {
    $current_page = 'withdraw';
} elseif (indoor_tasks_is_page('profile')) {
    $current_page = 'profile';
} elseif (indoor_tasks_is_page('kyc')) {
    $current_page = 'kyc';
}

// Get wallet balance
$wallet_balance = indoor_tasks_get_wallet_balance($user_id);

// Get unread notifications
$unread_notifications = indoor_tasks_get_unread_notifications_count($user_id);
?>
<!-- Mobile Bottom Navigation - Enhanced with 5 Essential Tabs -->
<div class="intk-mobile-bottom-nav">
    <a href="<?php echo indoor_tasks_get_page_url('dashboard'); ?>" class="intk-nav-tab <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
        <div class="intk-nav-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <polyline points="9,22 9,12 15,12 15,22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <span class="intk-nav-label"><?php _e('Home', 'indoor-tasks'); ?></span>
    </a>
    
    <a href="<?php echo indoor_tasks_get_page_url('tasks'); ?>" class="intk-nav-tab <?php echo $current_page === 'tasks' ? 'active' : ''; ?>">
        <div class="intk-nav-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 11l3 3 8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c1.45 0 2.82.34 4.03.94" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <span class="intk-nav-label"><?php _e('Tasks', 'indoor-tasks'); ?></span>
    </a>
    
    <a href="<?php echo indoor_tasks_get_page_url('wallet'); ?>" class="intk-nav-tab <?php echo $current_page === 'wallet' ? 'active' : ''; ?>">
        <div class="intk-nav-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M18 12a2 2 0 0 0 0 4h4v-4h-4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <span class="intk-nav-label"><?php _e('Wallet', 'indoor-tasks'); ?></span>
        <div class="intk-balance-indicator">$<?php echo number_format($wallet_balance, 2); ?></div>
    </a>
    
    <a href="<?php echo indoor_tasks_get_page_url('notifications'); ?>" class="intk-nav-tab <?php echo $current_page === 'notifications' ? 'active' : ''; ?>">
        <div class="intk-nav-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php if ($unread_notifications > 0): ?>
            <div class="intk-notification-badge"><?php echo min($unread_notifications, 99); ?></div>
            <?php endif; ?>
        </div>
        <span class="intk-nav-label"><?php _e('Alerts', 'indoor-tasks'); ?></span>
    </a>
    
    <a href="<?php echo indoor_tasks_get_page_url('profile'); ?>" class="intk-nav-tab <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
        <div class="intk-nav-icon">
            <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display_name); ?>" class="intk-profile-avatar">
        </div>
        <span class="intk-nav-label"><?php _e('Profile', 'indoor-tasks'); ?></span>
    </a>
</div>

<style>
/* Enhanced Mobile Bottom Navigation */
.intk-mobile-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    z-index: 1000;
    padding: 8px 0 calc(8px + env(safe-area-inset-bottom, 0px));
    transition: transform 0.3s ease;
}

@media (max-width: 768px) {
    .intk-mobile-bottom-nav {
        display: flex;
        justify-content: space-around;
        align-items: stretch;
    }
    
    /* Adjust content padding for mobile nav */
    .main-content,
    .content-area,
    .dashboard-main {
        padding-bottom: calc(80px + env(safe-area-inset-bottom, 0px)) !important;
    }
}

.intk-nav-tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #6b7280;
    transition: all 0.2s ease;
    padding: 8px 4px;
    position: relative;
    min-height: 60px;
    border-radius: 12px;
    margin: 0 2px;
}

.intk-nav-tab:hover {
    background: #f9fafb;
    color: #00954b;
    transform: translateY(-1px);
}

.intk-nav-tab.active {
    color: #00954b;
    background: linear-gradient(135deg, rgba(0, 149, 75, 0.1) 0%, rgba(0, 149, 75, 0.05) 100%);
}

.intk-nav-tab.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 32px;
    height: 3px;
    background: #00954b;
    border-radius: 0 0 3px 3px;
}

.intk-nav-icon {
    position: relative;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
}

.intk-nav-icon svg {
    transition: all 0.2s ease;
}

.intk-nav-tab:hover .intk-nav-icon svg {
    transform: scale(1.1);
}

.intk-profile-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid #e5e7eb;
    transition: border-color 0.2s ease;
}

.intk-nav-tab.active .intk-profile-avatar {
    border-color: #00954b;
}

.intk-nav-label {
    font-size: 11px;
    font-weight: 500;
    text-align: center;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 60px;
}

.intk-balance-indicator {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #00954b;
    color: white;
    font-size: 8px;
    font-weight: 600;
    padding: 2px 4px;
    border-radius: 8px;
    min-width: 16px;
    text-align: center;
    line-height: 1;
    transform: scale(0.85);
}

.intk-notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: 600;
    padding: 1px 5px;
    border-radius: 10px;
    min-width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    animation: pulse 2s infinite;
}

/* Pulse animation for notifications */
@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
}

/* Active state animations */
.intk-nav-tab.active .intk-nav-icon {
    animation: activeIcon 0.3s ease;
}

@keyframes activeIcon {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

/* Special styling for wallet tab */
.intk-nav-tab[href*="wallet"] .intk-nav-icon {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-radius: 8px;
    padding: 4px;
}

.intk-nav-tab[href*="wallet"].active .intk-nav-icon {
    background: linear-gradient(135deg, #00954b 0%, #16a34a 100%);
}

.intk-nav-tab[href*="wallet"].active .intk-nav-icon svg {
    color: white;
}

/* Responsive adjustments */
@media (max-width: 480px) {
    .intk-nav-tab {
        padding: 6px 2px;
        min-height: 56px;
    }
    
    .intk-nav-label {
        font-size: 10px;
        max-width: 50px;
    }
    
    .intk-nav-icon {
        width: 20px;
        height: 20px;
    }
    
    .intk-nav-icon svg {
        width: 18px;
        height: 18px;
    }
    
    .intk-profile-avatar {
        width: 20px;
        height: 20px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .intk-mobile-bottom-nav {
        background: #1f2937;
        border-top-color: #374151;
    }
    
    .intk-nav-tab {
        color: #9ca3af;
    }
    
    .intk-nav-tab:hover {
        background: #374151;
        color: #10b981;
    }
    
    .intk-nav-tab.active {
        color: #10b981;
        background: rgba(16, 185, 129, 0.1);
    }
    
    .intk-nav-tab.active::before {
        background: #10b981;
    }
}

/* Safe area handling for devices with notches */
@supports (padding: max(0px)) {
    .intk-mobile-bottom-nav {
        padding-bottom: max(8px, env(safe-area-inset-bottom));
    }
}

/* Hide mobile nav when keyboard is visible on iOS */
@media (max-width: 768px) and (max-height: 500px) and (orientation: landscape) {
    .intk-mobile-bottom-nav {
        transform: translateY(100%);
    }
}
</style>
