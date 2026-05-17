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
          if (data.success) window.location.href = data.redirect;
        } catch (e) {
          window.location.href = "/branch2/public/pages/login.html";
        }
      });
    });
  }
});

// ============================================================
// MODAL DE CONFIRMACIÓN
// ============================================================
function mostrarConfirmacion(mensaje, onConfirm) {
  const anterior = document.getElementById("confirmModal");
  if (anterior) anterior.remove();

  const modal = document.createElement("div");
  modal.id = "confirmModal";
  modal.innerHTML = `
    <div class="confirm-box">
      <h3>Confirmar acción</h3>
      <p>${mensaje}</p>
      <div class="confirm-buttons">
        <button class="btn-confirmar">Aceptar</button>
        <button class="btn-cancelar">Cancelar</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  modal.style.display = "flex";

  modal.querySelector(".btn-confirmar").addEventListener("click", () => {
    modal.remove();
    if (onConfirm) onConfirm();
  });
  modal.querySelector(".btn-cancelar").addEventListener("click", () => modal.remove());
}

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
function mostrarToast(mensaje, tipo = "info") {
  const container = document.getElementById("toast-container");
  if (!container) return;

  const toast = document.createElement("div");
  toast.className = `toast ${tipo}`;
  toast.innerHTML = `
    ${mensaje}
    <button class="toast-close" onclick="this.parentElement.remove()">×</button>
  `;
  container.appendChild(toast);
  toast.style.animation = "slideIn 0.3s ease forwards";

  setTimeout(() => {
    toast.style.animation = "fadeOut 0.4s ease forwards";
    setTimeout(() => toast.remove(), 400);
  }, 3500);
}
