document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const value = button.getAttribute('data-copy') || '';
        try {
            await navigator.clipboard.writeText(value);
            const oldText = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => { button.textContent = oldText; }, 1200);
        } catch (error) {
            window.prompt('Copy this URL:', value);
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.querySelector('.menu-toggle');
    const menu = document.querySelector('.main-menu');
    const submenuButtons = document.querySelectorAll('.submenu-toggle');

    if (!menuButton || !menu) {
        return;
    }

    function closeMenu() {
        menu.classList.remove('active');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.querySelector('.sr-only').textContent = 'Menu open';
    }

    menuButton.addEventListener('click', function () {
        const isOpen = menu.classList.toggle('active');
        menuButton.setAttribute('aria-expanded', String(isOpen));
        menuButton.querySelector('.sr-only').textContent = isOpen ? 'Menu close' : 'Menu open';
    });

    submenuButtons.forEach(function (submenuButton) {
        submenuButton.addEventListener('click', function () {
            const group = submenuButton.closest('.menu-group');
            const isOpen = group.classList.toggle('open');
            submenuButton.setAttribute('aria-expanded', String(isOpen));
        });
    });

    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target) && !menuButton.contains(event.target)) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenu();
            menuButton.focus();
        }
    });
});
