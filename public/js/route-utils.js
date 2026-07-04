// route-utils.js — Utilidades de enrutamiento del lado cliente.
// Debe cargarse ANTES que session-guard.js, csrf-fetch.js e inactivity.js.
(function (global) {
  /**
   * Calcula la ruta relativa a login.html desde cualquier página protegida.
   * Funciona independientemente del nivel de anidación de la URL actual.
   *
   * Ejemplos:
   *   /pages/admin/index.html      → "../login.html"
   *   /pages/visitante/solicitud.html → "../login.html"
   */
  global.getLoginUrl = function () {
    var depth = window.location.pathname.split('/').filter(Boolean).length;
    var base  = depth >= 2 ? '../'.repeat(depth - 1) : '';
    return base + 'login.html';
  };

  /**
   * Limpia el token CSRF de sessionStorage y redirige al login.
   * Centraliza la lógica de cierre de sesión del lado cliente.
   */
  global.redirigirLogin = function () {
    sessionStorage.removeItem('csrf_token');
    window.location.href = global.getLoginUrl();
  };
}(window));
