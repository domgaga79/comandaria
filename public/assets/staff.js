const staffMenuToggle = document.querySelector('.cd-menu-toggle');

if (staffMenuToggle) {
    const closeStaffMenu = () => staffMenuToggle.setAttribute('aria-expanded', 'false');

    staffMenuToggle.addEventListener('click', () => {
        const expanded = staffMenuToggle.getAttribute('aria-expanded') === 'true';
        staffMenuToggle.setAttribute('aria-expanded', String(!expanded));
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && staffMenuToggle.getAttribute('aria-expanded') === 'true') {
            closeStaffMenu();
            staffMenuToggle.focus();
        }
    });
}
