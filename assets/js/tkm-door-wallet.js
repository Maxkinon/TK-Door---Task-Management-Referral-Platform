/**
 * TKM Door Wallet JavaScript
 * Handles wallet interactions, transaction filtering, and data refresh
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeWallet();
});

function initializeWallet() {
    // Initialize transaction filtering
    initTransactionFiltering();
    
    // Set up refresh functionality
    setupRefreshHandler();
    
    // Initialize any other wallet features
    console.log('TKM Wallet initialized');
}

/**
 * Initialize transaction filtering functionality
 */
function initTransactionFiltering() {
    const filterTabs = document.querySelectorAll('.tkm-tab');
    const transactionRows = document.querySelectorAll('.tkm-transaction-row');
    
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            filterTabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Get filter value
            const filter = this.getAttribute('data-filter');
            
            // Filter transactions
            filterTransactions(filter, transactionRows);
        });
    });
}

/**
 * Filter transaction rows based on type
 */
function filterTransactions(filter, rows) {
    rows.forEach(row => {
        const transactionType = row.getAttribute('data-type');
        
        if (filter === 'all' || transactionType === filter) {
            row.style.display = '';
            // Add smooth show animation
            row.style.opacity = '0';
            row.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, 50);
        } else {
            row.style.display = 'none';
        }
    });
    
    // Check if any transactions are visible
    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
    
    if (visibleRows.length === 0) {
        showEmptyState(filter);
    } else {
        hideEmptyState();
    }
}

/**
 * Show empty state for filtered results
 */
function showEmptyState(filter) {
    const tableBody = document.getElementById('transactions-list');
    const existingEmptyState = tableBody.querySelector('.tkm-filter-empty-state');
    
    if (existingEmptyState) {
        existingEmptyState.remove();
    }
    
    const emptyState = document.createElement('div');
    emptyState.className = 'tkm-filter-empty-state tkm-empty-state';
    
    const filterLabels = {
        'reward': 'earned transactions',
        'withdrawal': 'withdrawals',
        'bonus': 'bonuses',
        'admin': 'admin transactions'
    };
    
    const filterLabel = filterLabels[filter] || 'transactions';
    
    emptyState.innerHTML = `
        <div class="tkm-empty-icon">🔍</div>
        <h4>No ${filterLabel} found</h4>
        <p>You don't have any ${filterLabel} in your transaction history yet.</p>
    `;
    
    tableBody.appendChild(emptyState);
}

/**
 * Hide empty state
 */
function hideEmptyState() {
    const existingEmptyState = document.querySelector('.tkm-filter-empty-state');
    if (existingEmptyState) {
        existingEmptyState.remove();
    }
}

/**
 * Set up refresh functionality
 */
function setupRefreshHandler() {
    // This function is called from the refresh button onclick
    window.tkm_refreshTransactions = function() {
        showLoading('Refreshing transactions...');
        
        // Simulate refresh delay (in real implementation, this would make an AJAX call)
        setTimeout(() => {
            hideLoading();
            showMessage('Transactions refreshed successfully!', 'success');
            
            // In a real implementation, you would refresh the transaction data here
            console.log('Transactions refreshed');
        }, 1500);
    };
}

/**
 * Show loading overlay
 */
function showLoading(message = 'Loading...') {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        const messageElement = overlay.querySelector('p');
        if (messageElement) {
            messageElement.textContent = message;
        }
        overlay.style.display = 'flex';
    }
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

/**
 * Show message to user
 */
function showMessage(message, type = 'info') {
    const container = document.getElementById('message-container');
    if (!container) return;
    
    const messageElement = document.createElement('div');
    messageElement.className = `tkm-message tkm-message-${type}`;
    
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    messageElement.innerHTML = `
        <div class="tkm-message-icon">${icons[type] || icons.info}</div>
        <div class="tkm-message-text">${message}</div>
        <button class="tkm-message-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    // Add close button styles
    const closeButton = messageElement.querySelector('.tkm-message-close');
    if (closeButton) {
        closeButton.style.cssText = `
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            margin-left: 1rem;
            opacity: 0.7;
        `;
        
        closeButton.addEventListener('mouseenter', function() {
            this.style.opacity = '1';
        });
        
        closeButton.addEventListener('mouseleave', function() {
            this.style.opacity = '0.7';
        });
    }
    
    container.appendChild(messageElement);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (messageElement.parentElement) {
            messageElement.remove();
        }
    }, 5000);
}

/**
 * Format currency amount
 */
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format points with proper number formatting
 */
function formatPoints(points) {
    return new Intl.NumberFormat('en-US').format(points);
}

/**
 * Animate number counting effect
 */
function animateNumber(element, start, end, duration = 1000) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        
        if (current >= end) {
            current = end;
            clearInterval(timer);
        }
        
        element.textContent = formatPoints(Math.floor(current));
    }, 16);
}

/**
 * Add smooth scroll behavior for navigation
 */
function smoothScrollTo(element) {
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

/**
 * Handle window resize for responsive adjustments
 */
window.addEventListener('resize', function() {
    // Handle any responsive adjustments if needed
    console.log('Window resized');
});

// Add CSS for message animations
const messageStyles = document.createElement('style');
messageStyles.textContent = `
    .tkm-message {
        animation: slideInRight 0.3s ease-out;
        margin-bottom: 1rem;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .tkm-filter-empty-state {
        grid-column: 1 / -1;
        padding: 2rem;
        text-align: center;
        color: #6c757d;
    }
    
    @media (max-width: 768px) {
        .tkm-message {
            margin: 0 1rem 1rem 1rem;
        }
    }
`;

document.head.appendChild(messageStyles);
