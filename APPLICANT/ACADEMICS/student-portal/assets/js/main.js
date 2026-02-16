
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const topbar = document.querySelector('.topbar');
    const sidebarToggler = document.getElementById('sidebarToggler');
    const content = document.getElementById('content');
    const navLinks = document.querySelectorAll('a[data-page]');

    function toggleSidebar() {
        const isMobile = window.innerWidth < 768;
        if (isMobile) {
             sidebar.classList.toggle('active'); // Off-canvas for mobile
        } else {
            // Collapse for desktop
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
            topbar.classList.toggle('collapsed');
        }
    }

    if (sidebarToggler) {
        sidebarToggler.addEventListener('click', toggleSidebar);
    }

    // Dynamic content loading
    navLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');

            // Remove active class from all links
            navLinks.forEach(navLink => navLink.classList.remove('active'));
            // Add active class to the clicked link
            this.classList.add('active');

            if (page) {
                loadContent(page);
            }
            
            // Close mobile sidebar on link click
            const isMobile = window.innerWidth < 768;
            if (isMobile && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    });

    // Load initial content based on hash
    function loadInitialPage() {
        const hash = window.location.hash.substring(1);
        const page = hash ? hash : 'dashboard';
        
        // Deactivate all nav links first
        navLinks.forEach(navLink => navLink.classList.remove('active'));
        
        // Activate the correct one
        const linkToActivate = document.querySelectorAll(`a[data-page='${page}']`);
        if (linkToActivate.length) {
            linkToActivate.forEach(link => link.classList.add('active'));
        } else {
            // Default to dashboard if hash is invalid
            document.querySelectorAll(`a[data-page='dashboard']`).forEach(link => link.classList.add('active'));
        }

        loadContent(page);
    }


    function loadContent(page) {
        const pagePath = `pages/${page}.php`;
        
        fetch(pagePath)
            .then(response => {
                if (!response.ok) {
                    // If page doesn't exist, load dashboard
                    if(page !== 'dashboard') {
                        console.warn(`Page '${page}' not found, loading dashboard.`);
                        window.location.hash = 'dashboard';
                        loadInitialPage();
                    }
                    throw new Error(`Page not found: ${pagePath}`);
                }
                return response.text();
            })
            .then(data => {
                content.innerHTML = data;
                window.location.hash = page; // Update hash on successful load
            })
            .catch(error => {
                console.error('Error loading page:', error);
                if (page !== 'dashboard') { // Avoid infinite loop
                    content.innerHTML = '<div class="alert alert-danger">Failed to load page content. Redirecting to dashboard.</div>';
                }
            });
    }
    
    // Check initial state on page load
    loadInitialPage();
    
    // Add event listener for hash changes
    window.addEventListener('hashchange', loadInitialPage);

});
