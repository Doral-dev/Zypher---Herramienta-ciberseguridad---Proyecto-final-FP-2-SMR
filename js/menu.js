function toggleMenu(element) {
  const submenu = element.nextElementSibling;
  submenu.classList.toggle("open");
}

function toggleSidebar() {
  const sidebar = document.getElementById("dashboardSidebar");
  const main = document.getElementById("dashboardMain");
  const button = document.getElementById("sidebarToggleBtn");

  sidebar.classList.toggle("is-hidden");
  main.classList.toggle("is-full");
  button.classList.toggle("is-collapsed");
}
