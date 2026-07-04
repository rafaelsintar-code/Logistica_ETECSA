// session-guard.js — Verifica sesión activa al cargar una página protegida.
// Requiere route-utils.js cargado antes.
(function () {
  fetch('../../api/auth/session_check.php', {
    method: 'GET',
    credentials: 'same-origin'
  })
    .then(function (res) {
      if (res.status === 401) redirigirLogin();
    })
    .catch(function () {
      redirigirLogin();
    });
}());
