// csrf-fetch.js — envuelve fetch() para:
//   1. Adjuntar automáticamente el token CSRF en POST/PUT/DELETE.
//   2. Redirigir al login si el servidor devuelve 401 (sesión expirada).
(function () {
  const _originalFetch = window.fetch.bind(window);

  window.fetch = function (resource, options) {
    options = options || {};
    const method = (options.method || "GET").toUpperCase();

    if (method !== "GET" && method !== "HEAD") {
      const token = sessionStorage.getItem("csrf_token") || "";

      if (options.body instanceof FormData) {
        options.body.set("csrf_token", token);
      } else {
        options.headers = options.headers || {};
        if (options.headers instanceof Headers) {
          options.headers.set("X-CSRF-Token", token);
        } else {
          options.headers["X-CSRF-Token"] = token;
        }
      }
    }

    return _originalFetch(resource, options).then(function (response) {
      if (response.status === 401) {
        // Sesión expirada o no iniciada — limpiar y volver al login
        sessionStorage.removeItem("csrf_token");
        // Calcular la ruta a login.html desde cualquier página
        const depth = window.location.pathname.split("/").filter(Boolean).length;
        const base  = depth >= 2 ? "../".repeat(depth - 1) : "";
        window.location.href = base + "login.html";
      }
      return response;
    });
  };
})();
