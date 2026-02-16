document.addEventListener('click', (event) => {
    const toggle = event.target.closest('.dropdown-submenu > .dropdown-toggle');
    if (!toggle) {
        document.querySelectorAll('.dropdown-submenu .dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const submenu = toggle.nextElementSibling;
    if (!submenu) {
        return;
    }

    const parentMenu = toggle.closest('.dropdown-menu');
    if (parentMenu) {
        parentMenu.querySelectorAll('.dropdown-submenu .dropdown-menu.show').forEach(menu => {
            if (menu !== submenu) {
                menu.classList.remove('show');
            }
        });
    }

    submenu.classList.toggle('show');
});
