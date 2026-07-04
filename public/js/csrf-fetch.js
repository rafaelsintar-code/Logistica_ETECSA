// csrf-fetch.js — Envuelve fetch() para:
//   1. Adjuntar el token CSRF en POST/PUT/DELETE.
//   2. Redirigir al login si el servidor responde 401.
// Requiere route-utils.js cargado antes.
(function () {
  var _originalFetch = window.fetch.bind(window);
  var _redirigiendo  = false; // evita doble redirección si varios fetch reciben 401 a la vez

  window.fetch = function (resource, options) {
    options = options || {};
    var method = (options.method || 'GET').toUpperCase();

    if (method !== 'GET' && method !== 'HEAD') {
      var token = sessionStorage.getItem('csrf_token') || '';
      if (options.body instanceof FormData) {
        options.body.set('csrf_token', token);
      } else {
        options.headers = options.headers || {};
        if (options.headers instanceof Headers) {
          options.headers.set('X-CSRF-Token', token);
        } else {
          options.headers['X-CSRF-Token'] = token;
        }
      }
    }

    return _originalFetch(resource, options).then(function (response) {
      if (response.status === 401 && !_redirigiendo) {
        _redirigiendo = true;
        redirigirLogin();
      }
      return response;
    });
  };
}());
