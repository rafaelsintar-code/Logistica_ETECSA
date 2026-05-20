// js/visitante/main.js
document.addEventListener("DOMContentLoaded", () => {
  const menuBtn = document.getElementById("menu-toggle");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("overlay");

  // === Sidebar toggle ===
  if (menuBtn && sidebar && overlay) {
    menuBtn.addEventListener("click", () => {
      const isOpen = sidebar.classList.toggle("open");
      overlay.classList.toggle("active", isOpen);
    });
    overlay.addEventListener("click", () => {
      sidebar.classList.remove("open");
      overlay.classList.remove("active");
    });
  }

  // === Rutas del visitante ===
  const base = "/branch2/public/pages/visitante/";

  const RUTAS = {
    "Inicio":                      base + "index.html",
    "Existencia de Recursos":      base + "existencia_recursos.html",
    "Crear Solicitud de Recursos": base + "solicitud.html",
    "Transferencia de Recursos":   base + "transferencia.html",
    "Productos y Servicios":       base + "articulos.html",
    "Almacenes":                   base + "almacenes.html",
  };

  document.querySelectorAll(".sidebar-content button").forEach((btn) => {
    btn.addEventListener("click", () => {
      const ruta = RUTAS[btn.textContent.trim()];
      if (!ruta) return;
      window.location.href = ruta;
      sidebar.classList.remove("open");
      overlay.classList.remove("active");
    });
  });

  // === Cerrar sesión ===
  const logoutBtn = document.querySelector(".logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      mostrarConfirmacion("¿Desea cerrar sesión?", async () => {
        try {
          const res  = await fetch("../../api/auth/logout.php", { method: "POST" });
          const data = await res.json();
          if (data.success) {
            sessionStorage.removeItem("csrf_token");
            window.location.href = data.redirect;
          }
        } catch (e) {
          window.location.href = "/branch2/public/pages/login.html";
        }
      });
    });
  }
});

// mostrarConfirmacion y mostrarToast se definen en modal.js,
// que se carga después de este archivo en todas las páginas.
