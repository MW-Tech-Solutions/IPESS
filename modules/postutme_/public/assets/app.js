document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        window.lucide.createIcons();
    }

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
            const collapsed = document.body.classList.contains('sidebar-collapsed');
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            const icon = button.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', collapsed ? 'panel-left-open' : 'panel-left-close');
                window.lucide?.createIcons();
            }
        });
    });
});
