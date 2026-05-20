// csrf-fetch.js — envuelve fetch() para adjuntar automáticamente el token CSRF
// en todas las peticiones POST/PUT/DELETE.
// Para peticiones multipart (FormData) el token se añade como campo del formulario.
(function () {
  const _originalFetch = window.fetch.bind(window);

  window.fetch = function (resource, options) {
    options = options || {};
    const method = (options.method || "GET").toUpperCase();

    if (method !== "GET" && method !== "HEAD") {
      const token = sessionStorage.getItem("csrf_token") || "";

      if (options.body instanceof FormData) {
        // multipart: añadir como campo
        options.body.set("csrf_token", token);
      } else {
        // JSON u otro: añadir cabecera
        options.headers = options.headers || {};
        if (options.headers instanceof Headers) {
          options.headers.set("X-CSRF-Token", token);
        } else {
          options.headers["X-CSRF-Token"] = token;
        }
      }
    }

    return _originalFetch(resource, options);
  };
})();
