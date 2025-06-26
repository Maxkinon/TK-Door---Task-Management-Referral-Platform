// Register service worker for PWA
document.addEventListener('DOMContentLoaded', function() {
    // Check if running as PWA
    const isPWA = window.matchMedia('(display-mode: standalone)').matches || 
                  window.navigator.standalone === true ||
                  document.referrer.includes('android-app://');
    
    if (isPWA) {
        document.body.classList.add('is-pwa');
        
        // Show preloader for navigation in PWA mode
        window.addEventListener('beforeunload', function() {
            if (window.IndoorTasksPreloader) {
                window.IndoorTasksPreloader.show();
            }
        });
    }

    // Check if PWA is supported
    if ('serviceWorker' in navigator) {
        // Wait for the page to load
        window.addEventListener('load', function() {
            // Register the service worker
            navigator.serviceWorker.register('/wp-content/plugins/indoor-tasks/assets/pwa/service-worker.js')
                .then(function(registration) {
                    console.log('Service Worker registered with scope:', registration.scope);
                    
                    // Check if we should show install prompt
                    checkInstallPrompt();
                })
                .catch(function(error) {
                    console.log('Service Worker registration failed:', error);
                });
        });
    }
    
    // Handle install prompt
    let deferredPrompt;
    
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later
        deferredPrompt = e;
        // Show install button
        showInstallButton();
    });
    
    function showInstallButton() {
        // Create install button if it doesn't exist
        if (!document.getElementById('pwa-install-button')) {
            const installButton = document.createElement('button');
            installButton.id = 'pwa-install-button';
            installButton.innerHTML = 'Install App';
            installButton.className = 'pwa-install-button';
            installButton.style.position = 'fixed';
            installButton.style.bottom = '70px';
            installButton.style.right = '20px';
            installButton.style.backgroundColor = '#007bff';
            installButton.style.color = 'white';
            installButton.style.padding = '10px 15px';
            installButton.style.borderRadius = '50px';
            installButton.style.border = 'none';
            installButton.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
            installButton.style.zIndex = '9999';
            installButton.style.display = 'flex';
            installButton.style.alignItems = 'center';
            installButton.style.justifyContent = 'center';
            
            // Add click event
            installButton.addEventListener('click', () => {
                // Hide the install button
                installButton.style.display = 'none';
                // Show the prompt
                deferredPrompt.prompt();
                // Wait for the user to respond to the prompt
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    } else {
                        console.log('User dismissed the install prompt');
                        // Show the button again
                        installButton.style.display = 'flex';
                    }
                    // Clear the deferredPrompt
                    deferredPrompt = null;
                });
            });
            
            document.body.appendChild(installButton);
        }
    }
    
    // Check if we should show the install prompt
    function checkInstallPrompt() {
        // Check if the app is already installed
        if (window.matchMedia('(display-mode: standalone)').matches) {
            console.log('App is already installed');
            return;
        }
        
        // Show the install button if we have a deferred prompt
        if (deferredPrompt) {
            showInstallButton();
        }
    }
});
