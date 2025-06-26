<?php
/**
 * TK Indoor - Preloader Component
 * A simple, modern loading animation for TK Indoor pages
 */

// Security check
if (!defined('ABSPATH')) exit;
?>

<div id="tk-indoor-preloader" class="tk-indoor-preloader">
    <div class="tk-indoor-preloader-content">
        <div class="tk-indoor-preloader-logo">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
        </div>
        <div class="tk-indoor-preloader-spinner">
            <div class="tk-indoor-spinner-dot"></div>
            <div class="tk-indoor-spinner-dot"></div>
            <div class="tk-indoor-spinner-dot"></div>
        </div>
        <p class="tk-indoor-preloader-text"><?php _e('Loading...', 'indoor-tasks'); ?></p>
    </div>
</div>

<style>
.tk-indoor-preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 1;
    visibility: visible;
    transition: opacity 0.5s ease, visibility 0.5s ease;
}

.tk-indoor-preloader.hidden {
    opacity: 0;
    visibility: hidden;
}

.tk-indoor-preloader-content {
    text-align: center;
    color: white;
}

.tk-indoor-preloader-logo {
    margin-bottom: 20px;
    animation: tk-indoor-pulse 2s infinite;
}

.tk-indoor-preloader-logo svg {
    width: 48px;
    height: 48px;
    stroke: white;
}

.tk-indoor-preloader-spinner {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 16px;
}

.tk-indoor-spinner-dot {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    animation: tk-indoor-bounce 1.4s infinite ease-in-out both;
}

.tk-indoor-spinner-dot:nth-child(1) { animation-delay: -0.32s; }
.tk-indoor-spinner-dot:nth-child(2) { animation-delay: -0.16s; }

.tk-indoor-preloader-text {
    font-size: 14px;
    font-weight: 500;
    margin: 0;
    opacity: 0.9;
}

@keyframes tk-indoor-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes tk-indoor-bounce {
    0%, 80%, 100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}

/* Mobile optimization */
@media (max-width: 768px) {
    .tk-indoor-preloader-logo svg {
        width: 40px;
        height: 40px;
    }
    
    .tk-indoor-preloader-text {
        font-size: 12px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hide preloader after page load
    const preloader = document.getElementById('tk-indoor-preloader');
    if (preloader) {
        setTimeout(() => {
            preloader.classList.add('hidden');
            setTimeout(() => {
                preloader.remove();
            }, 500);
        }, 1000);
    }
});
</script>
