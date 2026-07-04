// inactivity.js — Cierra la sesión del lado cliente tras 5 minutos sin actividad.
// Requiere route-utils.js cargado antes.
(function () {
  var TIMEOUT_MS = 5 * 60 * 1000;
  var AVISO_MS   = 60 * 1000;
  var timerExpiracion, timerAviso;

  function resetTimer() {
    clearTimeout(timerExpiracion);
    clearTimeout(timerAviso);

    timerAviso = setTimeout(function () {
      if (typeof mostrarToast === 'function') {
        mostrarToast('Tu sesión expirará en 1 minuto por inactividad.', 'info');
      }
    }, TIMEOUT_MS - AVISO_MS);

    timerExpiracion = setTimeout(function () {
      clearTimeout(timerAviso);
      fetch('../../api/auth/logout.php', { method: 'POST' })
        .catch(function () {})
        .finally(function () { redirigirLogin(); });
    }, TIMEOUT_MS);
  }

  ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (evt) {
    document.addEventListener(evt, resetTimer, { passive: true });
  });

  resetTimer();
}());
