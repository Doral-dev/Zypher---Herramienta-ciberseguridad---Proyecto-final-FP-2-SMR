function toggleMenu(element) {
  const submenu = element.nextElementSibling;
  submenu.classList.toggle("open");
}

function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  sidebar.style.display = (sidebar.style.display === "none") ? "block" : "none";
}
