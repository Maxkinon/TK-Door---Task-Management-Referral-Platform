/**
 * TKM Door Withdraw JavaScript
 * Handles withdrawal form interactions, validation, and real-time calculations
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeWithdraw();
});

function initializeWithdraw() {
    // Initialize form validation and interactions
    initWithdrawForm();
    
    // Set up real-time conversion calculations
    setupConversionCalculator();
    
    // Initialize method-specific help text
    setupMethodHelp();
    
    console.log('TKM Withdraw initialized');
}

/**
 * Initialize withdrawal form functionality
 */
function initWithdrawForm() {
    const form = document.getElementById('withdrawal-form');
    if (!form) return;
    
    const pointsInput = document.getElementById('points');
    const methodSelect = document.getElementById('method');
    const accountDetails = document.getElementById('account_details');
    const submitBtn = document.getElementById('submit-btn');
    
    // Real-time validation
    if (pointsInput) {
        pointsInput.addEventListener('input', function() {
            validatePointsInput(this);
            updateConversion();
        });
        
        pointsInput.addEventListener('blur', function() {
            validatePointsInput(this, true);
        });
    }
    
    if (methodSelect) {
        methodSelect.addEventListener('change', function() {
            updateMethodInfo(this.value);
            updateConversion();
            validateForm();
        });
    }
    
    if (accountDetails) {
        accountDetails.addEventListener('input', function() {
            validateAccountDetails(this);
        });
    }
    
    // Form submission
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm(true)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            showLoading('Processing withdrawal request...');
            
            // Disable submit button to prevent double submission
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <div class="tkm-btn-spinner"></div>
                    Processing...
                `;
            }
        });
    }
}

/**
 * Set up real-time conversion calculator
 */
function setupConversionCalculator() {
    window.updateConversion = function() {
        const pointsInput = document.getElementById('points');
        const methodSelect = document.getElementById('method');
        const conversionDisplay = document.getElementById('conversion-display');
        
        if (!pointsInput || !conversionDisplay) return;
        
        const points = parseInt(pointsInput.value) || 0;
        let conversionRate = window.tkmWithdraw ? window.tkmWithdraw.conversionRate : 0.01;
        
        // Get method-specific conversion rate if available
        if (methodSelect && methodSelect.value) {
            const selectedOption = methodSelect.querySelector(`option[value="${methodSelect.value}"]`);
            if (selectedOption && selectedOption.dataset.conversion) {
                conversionRate = parseFloat(selectedOption.dataset.conversion);
            }
        }
        
        const amount = points * conversionRate;
        conversionDisplay.textContent = `= $${amount.toFixed(2)} USD`;
        
        // Add animation to conversion display
        conversionDisplay.style.transform = 'scale(1.05)';
        setTimeout(() => {
            conversionDisplay.style.transform = 'scale(1)';
        }, 200);
    };
}

/**
 * Set up method-specific help text
 */
function setupMethodHelp() {
    const methodSelect = document.getElementById('method');
    const helpText = document.getElementById('method-help');
    
    if (!methodSelect || !helpText) return;
    
    const methodHelp = {
        'PayPal': 'Please enter your PayPal email address. Make sure it\'s the email associated with your verified PayPal account.',
        'Bank Transfer': 'Please provide your bank account details including account number, routing number, and account holder name.',
        'UPI': 'Please enter your UPI ID (example: yourname@paytm, yourname@gpay, or your UPI handle).',
        'Cryptocurrency': 'Please provide your wallet address. Double-check the address as transactions cannot be reversed.',
        'Gift Card': 'Specify your preferred gift card type and any additional requirements.'
    };
    
    window.updateMethodInfo = function(method) {
        if (methodHelp[method]) {
            helpText.textContent = methodHelp[method];
            helpText.style.color = '#00954b';
        } else {
            helpText.textContent = 'Please provide the necessary account information for your selected withdrawal method.';
            helpText.style.color = '#6c757d';
        }
        
        // Update minimum points if method has specific requirements
        const selectedOption = methodSelect.querySelector(`option[value="${method}"]`);
        if (selectedOption && selectedOption.dataset.min) {
            const minPoints = parseInt(selectedOption.dataset.min);
            const pointsInput = document.getElementById('points');
            if (pointsInput) {
                pointsInput.setAttribute('min', minPoints);
            }
        }
    };
}

/**
 * Validate points input
 */
function validatePointsInput(input, showErrors = false) {
    const value = parseInt(input.value);
    const min = parseInt(input.getAttribute('min')) || 0;
    const max = parseInt(input.getAttribute('max')) || Infinity;
    const currentBalance = window.tkmWithdraw ? window.tkmWithdraw.currentBalance : 0;
    
    let isValid = true;
    let errorMessage = '';
    
    if (isNaN(value) || value <= 0) {
        isValid = false;
        errorMessage = 'Please enter a valid amount';
    } else if (value < min) {
        isValid = false;
        errorMessage = `Minimum withdrawal amount is ${min.toLocaleString()} points`;
    } else if (value > currentBalance) {
        isValid = false;
        errorMessage = `Amount exceeds your balance of ${currentBalance.toLocaleString()} points`;
    }
    
    // Visual feedback
    if (isValid) {
        input.style.borderColor = '#28a745';
        removeFieldError(input);
    } else {
        input.style.borderColor = '#dc3545';
        if (showErrors) {
            showFieldError(input, errorMessage);
        }
    }
    
    return isValid;
}

/**
 * Validate account details
 */
function validateAccountDetails(textarea) {
    const value = textarea.value.trim();
    const isValid = value.length >= 5;
    
    if (isValid) {
        textarea.style.borderColor = '#28a745';
        removeFieldError(textarea);
    } else {
        textarea.style.borderColor = '#dc3545';
    }
    
    return isValid;
}

/**
 * Validate entire form
 */
function validateForm(showErrors = false) {
    const pointsInput = document.getElementById('points');
    const methodSelect = document.getElementById('method');
    const accountDetails = document.getElementById('account_details');
    
    let isValid = true;
    
    if (pointsInput) {
        isValid = validatePointsInput(pointsInput, showErrors) && isValid;
    }
    
    if (methodSelect) {
        if (!methodSelect.value) {
            isValid = false;
            if (showErrors) {
                showFieldError(methodSelect, 'Please select a withdrawal method');
            }
        } else {
            removeFieldError(methodSelect);
        }
    }
    
    if (accountDetails) {
        isValid = validateAccountDetails(accountDetails) && isValid;
        if (!isValid && showErrors) {
            showFieldError(accountDetails, 'Please provide valid account details (minimum 5 characters)');
        }
    }
    
    // Update submit button state
    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) {
        submitBtn.disabled = !isValid;
        if (isValid) {
            submitBtn.classList.remove('disabled');
        } else {
            submitBtn.classList.add('disabled');
        }
    }
    
    return isValid;
}

/**
 * Show field error
 */
function showFieldError(field, message) {
    removeFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'tkm-field-error';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        color: #dc3545;
        font-size: 0.85rem;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    `;
    
    field.parentNode.appendChild(errorDiv);
}

/**
 * Remove field error
 */
function removeFieldError(field) {
    const existingError = field.parentNode.querySelector('.tkm-field-error');
    if (existingError) {
        existingError.remove();
    }
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
 * Format currency
 */
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Add input formatting for better UX
 */
function addInputFormatting() {
    const pointsInput = document.getElementById('points');
    if (pointsInput) {
        pointsInput.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Add thousand separators for display (optional)
            if (this.value) {
                const number = parseInt(this.value);
                // You could add comma formatting here if desired
            }
        });
    }
}

// Add custom CSS for form interactions
const withdrawStyles = document.createElement('style');
withdrawStyles.textContent = `
    .tkm-btn.disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }
    
    .tkm-btn-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }
    
    .tkm-field-error {
        animation: shake 0.3s ease-in-out;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    .tkm-input:invalid,
    .tkm-select:invalid,
    .tkm-textarea:invalid {
        box-shadow: none;
    }
    
    .tkm-conversion-display {
        transition: all 0.3s ease;
    }
    
    @media (max-width: 768px) {
        .tkm-field-error {
            font-size: 0.8rem;
        }
    }
`;

document.head.appendChild(withdrawStyles);

// Initialize input formatting
document.addEventListener('DOMContentLoaded', function() {
    addInputFormatting();
});
