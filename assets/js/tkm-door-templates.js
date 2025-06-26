/**
 * TKM Door Templates - JavaScript Enhancements
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Mobile sidebar toggle functionality
    const sidebarToggle = document.querySelector('.tk-mobile-menu-toggle');
    const sidebar = document.querySelector('.tk-sidebar-nav');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Auto-refresh data every 30 seconds for dashboard
    if (document.body.classList.contains('tkm-dashboard')) {
        setInterval(function() {
            // Only refresh if page is visible
            if (!document.hidden) {
                refreshDashboardStats();
            }
        }, 30000);
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Task card interactions
    initTaskCardInteractions();
    
    // Task image lazy loading error handling
    initTaskImageHandling();
    
    // Filter form enhancements
    initFilterEnhancements();
    
    // Loading states for form submissions
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('tkm-loading');
                
                // Re-enable after 5 seconds as fallback
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.classList.remove('tkm-loading');
                }, 5000);
            }
        });
    });
    
    // Tooltip functionality for truncated text
    document.querySelectorAll('.tkm-task-title, .tkm-activity-title').forEach(element => {
        if (element.scrollWidth > element.clientWidth) {
            element.title = element.textContent;
        }
    });
    
    // Progressive Web App prompt handling
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showInstallPrompt();
    });
    
    function showInstallPrompt() {
        // You can customize this to show your own install prompt
        const installBanner = document.createElement('div');
        installBanner.className = 'tkm-install-prompt';
        installBanner.innerHTML = `
            <div class="tkm-install-content">
                <span>Install this app for a better experience!</span>
                <button class="tkm-install-btn">Install</button>
                <button class="tkm-dismiss-btn">×</button>
            </div>
        `;
        
        document.body.appendChild(installBanner);
        
        installBanner.querySelector('.tkm-install-btn').addEventListener('click', () => {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                deferredPrompt = null;
                installBanner.remove();
            });
        });
        
        installBanner.querySelector('.tkm-dismiss-btn').addEventListener('click', () => {
            installBanner.remove();
        });
    }
    
    // Dashboard stats refresh function
    function refreshDashboardStats() {
        const statsCards = document.querySelectorAll('.tkm-stat-value');
        if (statsCards.length === 0) return;
        
        // Add loading state
        statsCards.forEach(card => card.classList.add('tkm-skeleton'));
        
        // Make AJAX request to refresh stats
        fetch(ajaxurl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=refresh_dashboard_stats&nonce=' + (window.tkm_nonce || '')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatsDisplay(data.data);
            }
        })
        .catch(error => {
            console.log('Stats refresh failed:', error);
        })
        .finally(() => {
            // Remove loading state
            statsCards.forEach(card => card.classList.remove('tkm-skeleton'));
        });
    }
    
    function updateStatsDisplay(stats) {
        const elements = {
            todayPoints: document.querySelector('[data-stat="today-points"] .tkm-stat-value'),
            completedTasks: document.querySelector('[data-stat="completed-tasks"] .tkm-stat-value'),
            pendingTasks: document.querySelector('[data-stat="pending-tasks"] .tkm-stat-value'),
            availableTasks: document.querySelector('[data-stat="available-tasks"] .tkm-stat-value')
        };
        
        if (elements.todayPoints && stats.today_points !== undefined) {
            elements.todayPoints.textContent = formatNumber(stats.today_points);
        }
        if (elements.completedTasks && stats.completed_tasks !== undefined) {
            elements.completedTasks.textContent = formatNumber(stats.completed_tasks);
        }
        if (elements.pendingTasks && stats.pending_tasks !== undefined) {
            elements.pendingTasks.textContent = formatNumber(stats.pending_tasks);
        }
        if (elements.availableTasks && stats.available_tasks !== undefined) {
            elements.availableTasks.textContent = formatNumber(stats.available_tasks);
        }
    }
    
    function formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Alt + D for Dashboard
        if (e.altKey && e.key === 'd') {
            e.preventDefault();
            const dashboardLink = document.querySelector('a[href*="dashboard"]');
            if (dashboardLink) dashboardLink.click();
        }
        
        // Alt + T for Tasks
        if (e.altKey && e.key === 't') {
            e.preventDefault();
            const tasksLink = document.querySelector('a[href*="tasks"]');
            if (tasksLink) tasksLink.click();
        }
        
        // Alt + A for Task Archive
        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            const archiveLink = document.querySelector('a[href*="archive"]');
            if (archiveLink) archiveLink.click();
        }
    });
    
    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Local storage for user preferences
    const preferences = {
        get: function(key, defaultValue = null) {
            try {
                const value = localStorage.getItem('tkm_' + key);
                return value ? JSON.parse(value) : defaultValue;
            } catch (e) {
                return defaultValue;
            }
        },
        
        set: function(key, value) {
            try {
                localStorage.setItem('tkm_' + key, JSON.stringify(value));
            } catch (e) {
                console.log('Failed to save preference:', key);
            }
        }
    };
    
    // Save and restore filter preferences
    const filterSelects = document.querySelectorAll('.tkm-filter-select');
    filterSelects.forEach(select => {
        const key = select.name + '_filter';
        const savedValue = preferences.get(key);
        
        if (savedValue && select.querySelector(`option[value="${savedValue}"]`)) {
            select.value = savedValue;
        }
        
        select.addEventListener('change', function() {
            preferences.set(key, this.value);
        });
    });
    
    // Service Worker registration for PWA
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/wp-content/plugins/indoor-tasks/assets/pwa/service-worker.js')
            .then(registration => {
                console.log('ServiceWorker registered successfully:', registration.scope);
            })
            .catch(error => {
                console.log('ServiceWorker registration failed:', error);
            });
    }
});

/**
 * Initialize task card interactions
 */
function initTaskCardInteractions() {
    // Add hover effects and click feedback
    document.querySelectorAll('.tkm-task-card').forEach(card => {
        // Add keyboard navigation
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');
        card.setAttribute('aria-label', 'View task details');
        
        // Handle keyboard navigation
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                card.click();
            }
        });
        
        // Add click sound effect (optional)
        card.addEventListener('click', function() {
            // Add a subtle click feedback
            card.style.transform = 'translateY(-1px)';
            setTimeout(() => {
                card.style.transform = '';
            }, 100);
        });
    });
    
    // Handle Start Task button interactions
    document.querySelectorAll('.tkm-start-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Prevent double-clicking
            if (btn.classList.contains('processing')) {
                e.preventDefault();
                return;
            }
            
            btn.classList.add('processing');
            btn.textContent = 'Loading...';
            
            // Reset after 3 seconds if navigation didn't occur
            setTimeout(() => {
                btn.classList.remove('processing');
                btn.textContent = 'Start Task';
            }, 3000);
        });
    });
}

/**
 * Initialize task image handling
 */
function initTaskImageHandling() {
    document.querySelectorAll('.tkm-task-image img').forEach(img => {
        img.addEventListener('error', function() {
            // Create a fallback SVG
            const fallbackSvg = `
                <svg width="80" height="80" xmlns="http://www.w3.org/2000/svg">
                    <rect width="80" height="80" fill="#e5e7eb" rx="8"/>
                    <g transform="translate(28, 28)">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" 
                              fill="#9ca3af" stroke="none"/>
                    </g>
                </svg>
            `;
            
            this.src = 'data:image/svg+xml;base64,' + btoa(fallbackSvg);
            this.classList.add('tkm-fallback-image');
        });
        
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
    });
}

/**
 * Initialize filter enhancements
 */
function initFilterEnhancements() {
    // Add loading states to filter selects
    document.querySelectorAll('.tkm-filter-select').forEach(select => {
        select.addEventListener('change', function() {
            // Show loading state
            const form = this.closest('form');
            if (form) {
                form.classList.add('tkm-loading');
                
                // Add loading indicator
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'tkm-filter-loading';
                loadingDiv.innerHTML = `
                    <div class="tkm-loading-spinner"></div>
                    <span>Updating tasks...</span>
                `;
                form.appendChild(loadingDiv);
            }
        });
    });
    
    // Preserve scroll position on filter changes
    if (window.location.hash) {
        setTimeout(() => {
            const element = document.querySelector(window.location.hash);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
            }
        }, 100);
    }
}

// Export functions for external use
window.TKMDoor = {
    initTaskCardInteractions,
    initTaskImageHandling,
    initFilterEnhancements
};

// Add CSS for install prompt
const installPromptCSS = `
.tkm-install-prompt {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 8px;
    z-index: 1000;
    animation: tkm-slide-up 0.3s ease;
}

.tkm-install-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
}

.tkm-install-btn {
    background: #00954b;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
}

.tkm-dismiss-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
}

@keyframes tkm-slide-up {
    from { transform: translateX(-50%) translateY(100%); }
    to { transform: translateX(-50%) translateY(0); }
}

@media (max-width: 768px) {
    .tkm-install-prompt {
        left: 10px;
        right: 10px;
        transform: none;
    }
    
    .tkm-install-content {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
}
`;

// Inject CSS
const style = document.createElement('style');
style.textContent = installPromptCSS;
document.head.appendChild(style);
