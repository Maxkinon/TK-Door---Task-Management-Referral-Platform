/**
 * TKM Door Task Detail - JavaScript Functionality
 * Version: 2.0.0
 * Features: Timer persistence, AJAX submission, file upload, modern UI interactions
 */

class TKMTaskDetail {
    constructor() {
        this.config = window.tkmTaskDetail || {};
        this.timer = null;
        this.startTime = null;
        this.duration = this.config.taskDuration || 1800; // 30 minutes default
        this.isTimerRunning = false;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initTimer();
        this.initFileUpload();
        this.loadTimerState();
    }
    
    bindEvents() {
        // Start task button
        const startBtn = document.getElementById('start-task-btn');
        if (startBtn) {
            startBtn.addEventListener('click', this.startTask.bind(this));
        }
        
        // Submission form
        const submitForm = document.getElementById('task-submission-form');
        if (submitForm) {
            submitForm.addEventListener('submit', this.submitTask.bind(this));
        }
        
        // Page visibility change (for timer persistence)
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
        
        // Before unload (warn about losing progress)
        window.addEventListener('beforeunload', this.handleBeforeUnload.bind(this));
        
        // File input change
        const fileInput = document.getElementById('proof-file');
        if (fileInput) {
            fileInput.addEventListener('change', this.handleFileChange.bind(this));
        }
    }
    
    initTimer() {
        const timerElement = document.getElementById('countdown-timer');
        if (!timerElement) return;
        
        // Get start time from data attributes or config
        const startTimeAttr = timerElement.dataset.startTime;
        const durationAttr = timerElement.dataset.duration;
        
        if (startTimeAttr && startTimeAttr !== 'null') {
            this.startTime = parseInt(startTimeAttr);
            this.duration = parseInt(durationAttr) || this.duration;
            this.startTimer();
        }
    }
    
    loadTimerState() {
        // Load timer state from localStorage for persistence
        const timerState = localStorage.getItem(`tkm_timer_${this.config.taskId}`);
        if (timerState) {
            const state = JSON.parse(timerState);
            if (state.startTime && state.duration) {
                this.startTime = state.startTime;
                this.duration = state.duration;
                
                // Check if timer should still be running
                const elapsed = Math.floor(Date.now() / 1000) - this.startTime;
                if (elapsed < this.duration) {
                    this.startTimer();
                } else {
                    this.handleTimerExpired();
                }
            }
        }
    }
    
    saveTimerState() {
        if (this.startTime) {
            const state = {
                startTime: this.startTime,
                duration: this.duration,
                taskId: this.config.taskId
            };
            localStorage.setItem(`tkm_timer_${this.config.taskId}`, JSON.stringify(state));
        }
    }
    
    startTask() {
        if (this.isTimerRunning) return;
        
        this.showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'start_task');
        formData.append('nonce', this.config.nonces.startTask);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.showLoading(false);
            
            if (data.success) {
                this.startTime = data.start_time;
                this.duration = data.duration;
                this.saveTimerState();
                this.startTimer();
                
                // Hide start section and show timer section
                const startSection = document.querySelector('.tkm-start-section');
                const timerSection = document.querySelector('.tkm-timer-section');
                const submissionSection = document.querySelector('.tkm-submission-section');
                const taskLinks = document.querySelector('.tkm-task-links');
                
                if (startSection) startSection.style.display = 'none';
                if (timerSection) timerSection.style.display = 'block';
                if (submissionSection) submissionSection.style.display = 'block';
                if (taskLinks) taskLinks.style.display = 'flex';
                
                this.showMessage('Task started! Timer is now running.', 'success');
            } else {
                this.showMessage(data.message || 'Failed to start task', 'error');
            }
        })
        .catch(error => {
            this.showLoading(false);
            this.showMessage('Network error. Please try again.', 'error');
            console.error('Start task error:', error);
        });
    }
    
    startTimer() {
        if (this.isTimerRunning) return;
        
        this.isTimerRunning = true;
        const timerDisplay = document.querySelector('.tkm-timer-display');
        const minutesSpan = document.getElementById('timer-minutes');
        const secondsSpan = document.getElementById('timer-seconds');
        
        if (!timerDisplay || !minutesSpan || !secondsSpan) return;
        
        this.timer = setInterval(() => {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - this.startTime;
            const remaining = Math.max(0, this.duration - elapsed);
            
            if (remaining <= 0) {
                this.handleTimerExpired();
                return;
            }
            
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            
            minutesSpan.textContent = minutes.toString().padStart(2, '0');
            secondsSpan.textContent = seconds.toString().padStart(2, '0');
            
            // Change color when time is running low (last 5 minutes)
            if (remaining <= 300) {
                timerDisplay.style.color = '#ef4444';
                if (remaining <= 60) {
                    timerDisplay.style.animation = 'pulse 1s infinite';
                }
            }
            
            this.saveTimerState();
        }, 1000);
    }
    
    handleTimerExpired() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        
        this.isTimerRunning = false;
        localStorage.removeItem(`tkm_timer_${this.config.taskId}`);
        
        const minutesSpan = document.getElementById('timer-minutes');
        const secondsSpan = document.getElementById('timer-seconds');
        
        if (minutesSpan) minutesSpan.textContent = '00';
        if (secondsSpan) secondsSpan.textContent = '00';
        
        this.showMessage('Timer expired! Please submit your task now.', 'warning');
        
        // Auto-focus on submission form
        const proofText = document.getElementById('proof-text');
        if (proofText) {
            proofText.focus();
        }
    }
    
    submitTask(event) {
        event.preventDefault();
        
        const form = event.target;
        const proofText = form.querySelector('#proof-text').value.trim();
        const proofFile = form.querySelector('#proof-file').files[0];
        
        if (!proofText && !proofFile) {
            this.showMessage('Please provide proof text or upload an image.', 'error');
            return;
        }
        
        // Validate file size (5MB max)
        if (proofFile && proofFile.size > 5 * 1024 * 1024) {
            this.showMessage('File size must be less than 5MB.', 'error');
            return;
        }
        
        this.showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'submit_task');
        formData.append('nonce', this.config.nonces.submitTask);
        formData.append('proof_text', proofText);
        
        if (proofFile) {
            formData.append('proof_file', proofFile);
        }
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.showLoading(false);
            
            if (data.success) {
                this.showMessage(data.message, 'success');
                
                // Stop timer
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
                this.isTimerRunning = false;
                localStorage.removeItem(`tkm_timer_${this.config.taskId}`);
                
                // Hide submission form and show success message
                form.style.display = 'none';
                
                // Reload page after 2 seconds to show updated status
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                
            } else {
                this.showMessage(data.message || 'Failed to submit task', 'error');
            }
        })
        .catch(error => {
            this.showLoading(false);
            this.showMessage('Network error. Please try again.', 'error');
            console.error('Submit task error:', error);
        });
    }
    
    initFileUpload() {
        const fileInput = document.getElementById('proof-file');
        const fileDisplay = document.querySelector('.tkm-file-upload-display');
        
        if (!fileInput || !fileDisplay) return;
        
        // Drag and drop functionality
        fileDisplay.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileDisplay.style.borderColor = 'var(--tkm-primary)';
            fileDisplay.style.background = 'rgba(0, 149, 75, 0.1)';
        });
        
        fileDisplay.addEventListener('dragleave', (e) => {
            e.preventDefault();
            fileDisplay.style.borderColor = 'var(--tkm-gray-300)';
            fileDisplay.style.background = 'var(--tkm-gray-50)';
        });
        
        fileDisplay.addEventListener('drop', (e) => {
            e.preventDefault();
            fileDisplay.style.borderColor = 'var(--tkm-gray-300)';
            fileDisplay.style.background = 'var(--tkm-gray-50)';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                this.handleFileChange({ target: fileInput });
            }
        });
    }
    
    handleFileChange(event) {
        const file = event.target.files[0];
        const fileDisplay = document.querySelector('.tkm-file-upload-display');
        
        if (!file || !fileDisplay) return;
        
        // Validate file type
        if (!file.type.startsWith('image/')) {
            this.showMessage('Please select an image file.', 'error');
            return;
        }
        
        // Validate file size
        if (file.size > 5 * 1024 * 1024) {
            this.showMessage('File size must be less than 5MB.', 'error');
            return;
        }
        
        // Update display
        const fileName = file.name;
        const fileSize = this.formatFileSize(file.size);
        
        fileDisplay.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
            </svg>
            <span><strong>${fileName}</strong> (${fileSize})</span>
        `;
        
        // Create preview if it's an image
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.createElement('img');
                preview.src = e.target.result;
                preview.style.maxWidth = '100px';
                preview.style.maxHeight = '100px';
                preview.style.objectFit = 'cover';
                preview.style.borderRadius = '4px';
                preview.style.marginTop = '8px';
                fileDisplay.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    showLoading(show) {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
    }
    
    showMessage(message, type = 'info') {
        const container = document.getElementById('message-container');
        if (!container) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `tkm-message ${type}`;
        messageDiv.textContent = message;
        
        container.appendChild(messageDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
        
        // Make it clickable to dismiss
        messageDiv.addEventListener('click', () => {
            messageDiv.remove();
        });
    }
    
    handleVisibilityChange() {
        if (document.hidden) {
            // Page is hidden, save timer state
            this.saveTimerState();
        } else {
            // Page is visible again, check timer state
            if (this.isTimerRunning) {
                this.loadTimerState();
            }
        }
    }
    
    handleBeforeUnload(event) {
        if (this.isTimerRunning) {
            event.preventDefault();
            event.returnValue = 'Your task timer is still running. Are you sure you want to leave?';
            return event.returnValue;
        }
    }
    
    // Public methods for external access
    getCurrentTime() {
        if (!this.startTime) return 0;
        const elapsed = Math.floor(Date.now() / 1000) - this.startTime;
        return Math.max(0, this.duration - elapsed);
    }
    
    stopTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.isTimerRunning = false;
        localStorage.removeItem(`tkm_timer_${this.config.taskId}`);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on the task detail page and have config
    if (window.tkmTaskDetail && document.body.classList.contains('tkm-door-task-detail')) {
        window.tkmTaskDetailInstance = new TKMTaskDetail();
    }
    
    // Add smooth scrolling to anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add hover effects to interactive elements
    document.querySelectorAll('.tkm-step-item').forEach(step => {
        step.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(8px)';
        });
        
        step.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Add pulse animation to timer when time is low
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    `;
    document.head.appendChild(style);
    
    // Add intersection observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe sections for scroll animations
    document.querySelectorAll('.tkm-feature-image, .tkm-task-header, .tkm-short-description, .tkm-full-description, .tkm-how-to-section, .tkm-guide-section, .tkm-action-section').forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(section);
    });
    
    // Add keyboard navigation support
    document.addEventListener('keydown', function(e) {
        // Escape key closes loading overlay
        if (e.key === 'Escape') {
            const overlay = document.getElementById('loading-overlay');
            if (overlay && overlay.style.display !== 'none') {
                overlay.style.display = 'none';
            }
            
            // Close any open messages
            document.querySelectorAll('.tkm-message').forEach(msg => msg.remove());
        }
        
        // Enter key on start button
        if (e.key === 'Enter' && e.target.id === 'start-task-btn') {
            e.target.click();
        }
    });
    
    // Add copy to clipboard functionality for links
    document.querySelectorAll('.tkm-task-link, .tkm-external-link').forEach(link => {
        link.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            navigator.clipboard.writeText(this.href).then(() => {
                // Show temporary tooltip
                const tooltip = document.createElement('div');
                tooltip.textContent = 'Link copied!';
                tooltip.style.cssText = `
                    position: absolute;
                    background: #333;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1000;
                    pointer-events: none;
                `;
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + 'px';
                tooltip.style.top = (rect.top - 30) + 'px';
                
                document.body.appendChild(tooltip);
                
                setTimeout(() => {
                    if (tooltip.parentNode) {
                        tooltip.remove();
                    }
                }, 2000);
            });
        });
    });
});

// Export for potential external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TKMTaskDetail;
}
