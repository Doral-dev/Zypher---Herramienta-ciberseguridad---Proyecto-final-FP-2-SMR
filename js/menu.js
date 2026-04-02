/**
 * Control de submenús del Dashboard Zypher
 */
function toggleSubmenu(button) {
    const submenu = button.nextElementSibling;
    
    // 1. Obtener todos los submenús abiertos
    const allSubmenus = document.querySelectorAll('.nav-submenu');
    
    // 2. Cerrar los que no sean el actual (efecto acordeón)
    allSubmenus.forEach(menu => {
        if (menu !== submenu) {
            menu.classList.remove('is-open');
        }
    });

    // 3. Alternar el estado del submenú actual
    if (submenu && submenu.classList.contains('nav-submenu')) {
        submenu.classList.toggle('is-open');
    }
}

// Opcional: Cerrar submenús si se hace click fuera del sidebar
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('dashboardSidebar');
    if (sidebar && !sidebar.contains(event.target)) {
        // Lógica opcional para cerrar menús al perder el foco
    }
});
