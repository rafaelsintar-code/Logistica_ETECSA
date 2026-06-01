// js/admin/main.js
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

  // === Rutas del administrador (relativas al directorio pages/admin/) ===
  const RUTAS = {
    "Inicio":                      "index.html",
    "Existencia de Recursos":      "existencia_recursos.html",
    "Crear Solicitud de Recursos": "solicitud.html",
    "Transferencia de Recursos":   "transferencia.html",
    "Productos y Servicios":       "articulos.html",
    "Almacenes":                   "almacenes.html",
    "Importar MB52":               "mb52.html",
    "Importar MB51":               "mb51.html",
    "Gestionar Cuentas":           "usuarios.html",
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
            window.location.href = "../login.html";
          }
        } catch (e) {
          window.location.href = "../login.html";
        }
      });
    });
  }

  // === Badges de notificación para solicitudes y transferencias (admin) ===
  // Envuelve el botón en un wrapper relativo e inyecta el punto rojo
  function wrapButtonWithBadge(labelText) {
    let targetBtn = null;
    document.querySelectorAll(".sidebar-content button").forEach((btn) => {
      if (btn.textContent.trim() === labelText) targetBtn = btn;
    });
    if (!targetBtn) return null;

    // Evitar doble envoltorio
    if (targetBtn.parentElement.classList.contains("sidebar-btn-wrapper")) {
      return targetBtn.parentElement.querySelector(".sidebar-badge");
    }

    const wrapper = document.createElement("div");
    wrapper.className = "sidebar-btn-wrapper";
    targetBtn.parentNode.insertBefore(wrapper, targetBtn);
    wrapper.appendChild(targetBtn);

    const badge = document.createElement("span");
    badge.className = "sidebar-badge";
    wrapper.appendChild(badge);

    return badge;
  }

  const badgeSolicitud    = wrapButtonWithBadge("Crear Solicitud de Recursos");
  const badgeTransferencia = wrapButtonWithBadge("Transferencia de Recursos");

  async function actualizarBadges() {
    try {
      const res  = await fetch("../../api/solicitud/get_pending_counts.php");
      if (!res.ok) return; // silencioso si no es admin o hay error
      const data = await res.json();
      if (!data.success) return;

      if (badgeSolicitud) {
        badgeSolicitud.classList.toggle("visible", data.solicitudes > 0);
        badgeSolicitud.title = data.solicitudes > 0
          ? `${data.solicitudes} solicitud(es) sin confirmar`
          : "";
      }
      if (badgeTransferencia) {
        badgeTransferencia.classList.toggle("visible", data.transferencias > 0);
        badgeTransferencia.title = data.transferencias > 0
          ? `${data.transferencias} transferencia(s) sin confirmar`
          : "";
      }
    } catch (_) {
      // fallo silencioso — no romper la UI
    }
  }

  // Ejecutar al cargar y refrescar cada 30 segundos
  actualizarBadges();
  setInterval(actualizarBadges, 30000);
});

// mostrarConfirmacion y mostrarToast se definen en modal.js,
// que se carga después de este archivo en todas las páginas.
