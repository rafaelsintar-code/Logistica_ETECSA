// inactivity.js — cierra la sesión del lado del cliente tras 5 minutos sin actividad.
// Debe cargarse en todas las páginas protegidas (admin y visitante), después de modal.js.
(function () {
  const TIMEOUT_MS   = 5 * 60 * 1000; // 5 minutos
  const AVISO_MS     = 60 * 1000;      // avisar 1 minuto antes
  let timerExpiracion, timerAviso;

  function resetTimer() {
    clearTimeout(timerExpiracion);
    clearTimeout(timerAviso);

    timerAviso = setTimeout(function () {
      if (typeof mostrarToast === "function") {
        mostrarToast("Tu sesión expirará en 1 minuto por inactividad.", "info");
      }
    }, TIMEOUT_MS - AVISO_MS);

    timerExpiracion = setTimeout(async function () {
      clearTimeout(timerAviso);
      try {
        await fetch("../../api/auth/logout.php", { method: "POST" });
      } catch (_) {}
      sessionStorage.removeItem("csrf_token");
      // Calcular ruta relativa a login.html
      const depth = window.location.pathname.split("/").filter(Boolean).length;
      const base  = depth >= 2 ? "../".repeat(depth - 1) : "";
      window.location.href = base + "login.html";
    }, TIMEOUT_MS);
  }

  // Reiniciar el timer ante cualquier actividad del usuario
  ["mousemove", "mousedown", "keydown", "touchstart", "scroll", "click"].forEach(function (evt) {
    document.addEventListener(evt, resetTimer, { passive: true });
  });

  // Arrancar al cargar la página
  resetTimer();
})();
