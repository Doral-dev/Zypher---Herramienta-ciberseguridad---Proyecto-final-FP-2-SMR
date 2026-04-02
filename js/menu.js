function toggleMenu(element) {
  const submenu = element.nextElementSibling;
  submenu.classList.toggle("open");
}

function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const handle = document.getElementById("sidebarHandle");

  sidebar.classList.toggle("collapsed");
  handle.classList.toggle("collapsed");
}
