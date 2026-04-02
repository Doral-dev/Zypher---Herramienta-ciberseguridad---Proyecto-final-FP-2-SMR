function toggleMenu(element) {
  const submenu = element.nextElementSibling;
  submenu.classList.toggle("open");
}

function toggleSidebar() {
  const sidebar = document.getElementById("dashboardSidebar");
  const main = document.getElementById("dashboardMain");
  const button = document.getElementById("sidebarToggleBtn");

  if (sidebar) sidebar.classList.toggle("is-hidden");
  if (main) main.classList.toggle("is-full");
  if (button) button.classList.toggle("is-collapsed");
}
