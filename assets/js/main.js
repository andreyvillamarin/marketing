document.addEventListener('DOMContentLoaded', function() {
    // ---- Confirmación para botones de eliminación (código anterior) ----
    const deleteButtons = document.querySelectorAll('.btn-danger, .delete-link');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            const confirmation = confirm('¿Estás seguro de que deseas realizar esta acción? Es irreversible.');
            if (!confirmation) {
                event.preventDefault();
            }
        });
    });

    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="ids_tareas[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // ---- INICIO DEL CÓDIGO CORREGIDO PARA MENÚ MÓVIL ----
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const closeBtn = document.getElementById('close-btn');
    const sidebarNav = document.getElementById('sidebar-nav');
    const overlay = document.getElementById('overlay');

    if (hamburgerBtn && sidebarNav && overlay && closeBtn) {
        
        const openMenu = function() {
            sidebarNav.classList.add('sidebar-open');
            overlay.classList.add('active');
        };

        const closeMenu = function() {
            sidebarNav.classList.remove('sidebar-open');
            overlay.classList.remove('active');
        };

        // Event Listeners
        hamburgerBtn.addEventListener('click', openMenu);
        closeBtn.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && sidebarNav.classList.contains('sidebar-open')) {
                closeMenu();
            }
        });
    }
});