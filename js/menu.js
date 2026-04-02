// Función para abrir/cerrar submenús
function zyToggleMenu(button) {
    const container = button.nextElementSibling;
    const arrow = button.querySelector('.zy-arrow');
    
    // Toggle de la clase is-open
    if (container.classList.contains('is-open')) {
        container.classList.remove('is-open');
        if(arrow) arrow.style.transform = "rotate(0deg)";
    } else {
        container.classList.add('is-open');
        if(arrow) arrow.style.transform = "rotate(180deg)";
    }
}

// Función para esconder el sidebar completo
function zyToggleSidebar() {
    const sidebar = document.getElementById('sidebarWrapper');
    const main = document.getElementById('dashboardMain');
    
    sidebar.classList.toggle('is-closed');
    if (main) {
        main.classList.toggle('full-width');
    }
}
