/**
 * TKM Door Leaderboard - JavaScript functionality
 * Version: 1.0.0
 */

(function() {
    'use strict';
    
    let currentData = [];
    let selectedUserId = null;
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initLeaderboard();
    });
    
    function initLeaderboard() {
        currentData = window.tkmLeaderboard.initialData || [];
        selectedUserId = window.tkmLeaderboard.currentUserId;
        
        // Initialize filters
        initFilters();
        
        // Render initial data
        renderLeaderboard(currentData);
        renderTopThree(currentData.slice(0, 3));
        
        // Set initial selected user
        if (selectedUserId) {
            updateProfilePanel(window.tkmLeaderboard.currentUserProfile);
        }
    }
    
    function initFilters() {
        const searchInput = document.getElementById('search-users');
        const categoryFilter = document.getElementById('category-filter');
        const timeframeFilter = document.getElementById('timeframe-filter');
        
        if (searchInput) {
            searchInput.addEventListener('input', debounce(handleFilterChange, 300));
        }
        
        if (categoryFilter) {
            categoryFilter.addEventListener('change', handleFilterChange);
        }
        
        if (timeframeFilter) {
            timeframeFilter.addEventListener('change', handleFilterChange);
        }
    }
    
    function handleFilterChange() {
        const search = document.getElementById('search-users')?.value || '';
        const category = document.getElementById('category-filter')?.value || 'overall';
        const timeframe = document.getElementById('timeframe-filter')?.value || 'all_time';
        
        showLoading();
        
        // Make AJAX request to get filtered data
        fetch(window.tkmLeaderboard.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'get_leaderboard_data',
                nonce: window.tkmLeaderboard.nonce,
                search: search,
                category: category,
                timeframe: timeframe
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                currentData = data.data;
                renderLeaderboard(currentData);
                renderTopThree(currentData.slice(0, 3));
            } else {
                showMessage('Error loading leaderboard data', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showMessage('Network error occurred', 'error');
            console.error('Error:', error);
        });
    }
    
    function renderTopThree(topThree) {
        const podium = document.getElementById('top-three-podium');
        if (!podium || topThree.length === 0) return;
        
        const positions = ['second', 'first', 'third'];
        const medals = ['🥈', '🥇', '🥉'];
        
        podium.innerHTML = '';
        
        topThree.forEach((user, index) => {
            if (index >= 3) return;
            
            const podiumItem = document.createElement('div');
            podiumItem.className = `tkm-podium-item ${positions[index]}`;
            podiumItem.onclick = () => selectUser(user.user_id);
            
            podiumItem.innerHTML = `
                <div class="tkm-podium-rank">${medals[index]}</div>
                <img src="${user.avatar_url}" alt="${user.display_name}" class="tkm-podium-avatar">
                <div class="tkm-podium-name">${escapeHtml(user.display_name)}</div>
                <div class="tkm-podium-level">Level ${user.level}</div>
                <div class="tkm-podium-points">${formatNumber(user.total_points)} pts</div>
            `;
            
            podium.appendChild(podiumItem);
        });
    }
    
    function renderLeaderboard(data) {
        const listContent = document.getElementById('leaderboard-list');
        if (!listContent) return;
        
        listContent.innerHTML = '';
        
        data.forEach((user, index) => {
            const row = document.createElement('div');
            row.className = `tkm-leaderboard-row ${user.user_id == window.tkmLeaderboard.currentUserId ? 'current-user' : ''}`;
            row.onclick = () => selectUser(user.user_id);
            
            const pointsChange = formatPointsChange(user.points_change);
            
            row.innerHTML = `
                <div class="tkm-rank ${index < 10 ? 'top-rank' : ''}">#${user.rank}</div>
                <div class="tkm-user-info">
                    <img src="${user.avatar_url}" alt="${user.display_name}" class="tkm-user-avatar">
                    <div class="tkm-user-details">
                        <div class="tkm-user-name">${escapeHtml(user.display_name)}</div>
                        <div class="tkm-user-username">@${escapeHtml(user.username)}</div>
                    </div>
                </div>
                <div class="tkm-level-badge">L${user.level}</div>
                <div class="tkm-points">${formatNumber(user.total_points)}</div>
                <div class="tkm-points-change ${pointsChange.class}">${pointsChange.text}</div>
            `;
            
            listContent.appendChild(row);
        });
    }
    
    function selectUser(userId) {
        if (selectedUserId === userId) return;
        
        selectedUserId = userId;
        
        // Highlight selected row
        document.querySelectorAll('.tkm-leaderboard-row').forEach(row => {
            row.classList.remove('selected');
        });
        
        // Show loading in profile panel
        showProfileLoading();
        
        // Fetch user profile data
        fetch(window.tkmLeaderboard.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'get_user_profile',
                nonce: window.tkmLeaderboard.nonce,
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProfilePanel(data.data);
            } else {
                showMessage('Error loading user profile', 'error');
            }
        })
        .catch(error => {
            showMessage('Network error occurred', 'error');
            console.error('Error:', error);
        });
    }
    
    function updateProfilePanel(userData) {
        if (!userData) return;
        
        // Update avatar
        const avatar = document.getElementById('profile-avatar');
        if (avatar) avatar.src = userData.avatar_url;
        
        // Update name
        const name = document.getElementById('profile-name');
        if (name) name.textContent = userData.display_name;
        
        // Update level
        const level = document.getElementById('profile-level');
        if (level) level.textContent = userData.level;
        
        // Update stars
        updateStars(userData.level);
        
        // Update badges
        updateBadges(userData.badges);
        
        // Update achievements
        updateAchievements(userData.achievements);
        
        // Update progress
        updateProgress(userData.level, userData.level_progress);
        
        // Update stats
        updateStats(userData.stats);
    }
    
    function updateStars(level) {
        const stars = document.querySelectorAll('#profile-stars .tkm-star');
        stars.forEach((star, index) => {
            if (index < Math.min(5, level)) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
        });
    }
    
    function updateBadges(badges) {
        const badgesContainer = document.getElementById('profile-badges');
        if (!badgesContainer || !badges) return;
        
        badgesContainer.innerHTML = '';
        badges.forEach(badge => {
            const badgeEl = document.createElement('span');
            badgeEl.className = 'tkm-badge';
            badgeEl.style.backgroundColor = badge.color;
            badgeEl.textContent = badge.name;
            badgesContainer.appendChild(badgeEl);
        });
    }
    
    function updateAchievements(achievements) {
        const achievementsContainer = document.getElementById('profile-achievements');
        if (!achievementsContainer || !achievements) return;
        
        achievementsContainer.innerHTML = '';
        achievements.slice(0, 4).forEach(achievement => {
            const achievementEl = document.createElement('div');
            achievementEl.className = `tkm-achievement-card ${achievement.earned ? 'earned' : 'locked'}`;
            achievementEl.innerHTML = `
                <span class="tkm-achievement-icon">${achievement.icon}</span>
                <span class="tkm-achievement-name">${escapeHtml(achievement.name)}</span>
            `;
            achievementsContainer.appendChild(achievementEl);
        });
    }
    
    function updateProgress(level, progress) {
        const progressInfo = document.querySelector('.tkm-progress-info span:first-child');
        const progressPercent = document.querySelector('.tkm-progress-info span:last-child');
        const progressFill = document.querySelector('.tkm-progress-fill');
        
        if (progressInfo) progressInfo.textContent = `Level ${level} Progress`;
        if (progressPercent) progressPercent.textContent = `${progress}%`;
        if (progressFill) progressFill.style.width = `${progress}%`;
    }
    
    function updateStats(stats) {
        const statsContainer = document.getElementById('profile-stats');
        if (!statsContainer || !stats) return;
        
        const statItems = statsContainer.querySelectorAll('.tkm-stat-item');
        
        if (statItems[0]) {
            const value = statItems[0].querySelector('.tkm-stat-value');
            if (value) value.textContent = formatNumber(stats.completed_tasks || 0);
        }
        
        if (statItems[1]) {
            const value = statItems[1].querySelector('.tkm-stat-value');
            if (value) value.textContent = formatNumber(stats.referrals || 0);
        }
        
        if (statItems[2]) {
            const value = statItems[2].querySelector('.tkm-stat-value');
            if (value) value.textContent = formatNumber(stats.days_active || 0);
        }
    }
    
    function showLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.style.display = 'flex';
    }
    
    function hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.style.display = 'none';
    }
    
    function showProfileLoading() {
        const profileCard = document.querySelector('.tkm-profile-card');
        if (profileCard) {
            profileCard.style.opacity = '0.5';
            profileCard.style.pointerEvents = 'none';
        }
        
        setTimeout(() => {
            if (profileCard) {
                profileCard.style.opacity = '1';
                profileCard.style.pointerEvents = 'auto';
            }
        }, 500);
    }
    
    function showMessage(message, type = 'success') {
        const container = document.getElementById('message-container');
        if (!container) return;
        
        const messageEl = document.createElement('div');
        messageEl.className = `tkm-message ${type}`;
        
        const icon = type === 'success' ? '✅' : '❌';
        messageEl.innerHTML = `
            <span>${icon}</span>
            <span>${escapeHtml(message)}</span>
        `;
        
        container.appendChild(messageEl);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageEl.parentNode) {
                messageEl.parentNode.removeChild(messageEl);
            }
        }, 5000);
    }
    
    // Utility functions
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'k';
        }
        return num.toString();
    }
    
    function formatPointsChange(change) {
        if (change > 0) {
            return {
                text: `+${change}`,
                class: 'positive'
            };
        } else if (change < 0) {
            return {
                text: change.toString(),
                class: 'negative'
            };
        } else {
            return {
                text: '—',
                class: 'neutral'
            };
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Copy to clipboard functionality
    window.copyToClipboard = function(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                showMessage('Copied to clipboard!', 'success');
            }).catch(() => {
                showMessage('Failed to copy to clipboard', 'error');
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showMessage('Copied to clipboard!', 'success');
            } catch (err) {
                showMessage('Failed to copy to clipboard', 'error');
            } finally {
                textArea.remove();
            }
        }
    };
    
    // Export functions for global access
    window.tkmLeaderboardFunctions = {
        selectUser,
        showMessage,
        formatNumber,
        copyToClipboard: window.copyToClipboard
    };
    
})();
