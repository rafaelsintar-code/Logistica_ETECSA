// modal.js - funciones globales de confirmación y toast

// Mostrar modal de confirmación global
function mostrarConfirmacion(mensaje, callbackConfirmar = null) {
  const modal = document.getElementById("global-confirm");
  const mensajeTexto = document.getElementById("global-confirm-msg");
  const btnYes = document.getElementById("global-confirm-yes");
  const btnNo = document.getElementById("global-confirm-no");

  if (!modal || !mensajeTexto || !btnYes || !btnNo) {
    console.error("Modal de confirmación no encontrado en DOM");
    return;
  }

  // Set mensaje y mostrar
  mensajeTexto.textContent = mensaje;
  modal.classList.remove("hidden");

  // Reemplazar botones para limpiar listeners previos
  const nuevoYes = btnYes.cloneNode(true);
  const nuevoNo = btnNo.cloneNode(true);
  btnYes.parentNode.replaceChild(nuevoYes, btnYes);
  btnNo.parentNode.replaceChild(nuevoNo, btnNo);

  nuevoYes.addEventListener("click", () => {
    modal.classList.add("hidden");
    if (typeof callbackConfirmar === "function") callbackConfirmar();
  });

  nuevoNo.addEventListener("click", () => {
    modal.classList.add("hidden");
  });
}

function mostrarToast(mensaje, tipo = "info", opciones = {}) {
  const contenedor = document.getElementById("toast-container");
  if (!contenedor) return;

  const {
    autoCerrar = true,
    duracion = 3500
  } = opciones;

  const toast = document.createElement("div");
  toast.className = `toast ${tipo}`;

  const texto = document.createElement("span");
  texto.textContent = mensaje;

  toast.appendChild(texto);

  // Botón cerrar
  const btnCerrar = document.createElement("button");
  btnCerrar.className = "toast-close";
  btnCerrar.innerHTML = "&times;";
  btnCerrar.addEventListener("click", () => toast.remove());

  toast.appendChild(btnCerrar);
  contenedor.appendChild(toast);

  // Auto cierre opcional
  if (autoCerrar) {
    setTimeout(() => {
      if (toast.parentNode) toast.remove();
    }, duracion);
  }
}

