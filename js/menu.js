function toggleMenu(element) {
  const submenu = element.nextElementSibling;
  submenu.classList.toggle("open");
}

function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("collapsed");
}
