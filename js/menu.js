// Maneja la apertura de submenús individuales
function toggleSubmenu(button) {
  const submenu = button.nextElementSibling;
  
  // Cerramos otros submenús si quieres un efecto acordeón (opcional)
  // document.querySelectorAll('.nav-submenu').forEach(s => s.classList.remove('is-open'));

  if (submenu) {
    submenu.classList.toggle('is-open');
  }
}

// Maneja la apertura/cierre del sidebar completo
function toggleSidebar() {
  const sidebar = document.getElementById('dashboardSidebar');
  const main = document.getElementById('dashboardMain');

  if (sidebar && main) {
    sidebar.classList.toggle('is-hidden');
    main.classList.toggle('is-full');
  }
}
