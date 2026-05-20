document.getElementById("login-form").addEventListener("submit", async (e) => {
  e.preventDefault();

  const username = document.getElementById("login-username").value.trim();
  const password = document.getElementById("login-password").value;
  const errorEl  = document.getElementById("login-error");

  errorEl.textContent = "";

  if (!username || !password) {
    errorEl.textContent = "Complete todos los campos.";
    return;
  }

  const btn = document.querySelector("#login-form button");
  btn.disabled     = true;
  btn.textContent  = "Verificando...";

  try {
    const res  = await fetch("../api/auth/login.php", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({ username, password })
    });

    const data = await res.json();

    if (data.success) {
      if (data.csrf_token) {
        sessionStorage.setItem("csrf_token", data.csrf_token);
      }
      // Rutas válidas que puede devolver el servidor
      const rutasValidas = {
        "admin/index.html":    "/branch2/public/pages/admin/index.html",
        "visitante/index.html": "/branch2/public/pages/visitante/index.html",
      };
      window.location.href = rutasValidas[data.redirect] ?? "/branch2/public/pages/visitante/index.html";
    } else {
      errorEl.textContent = data.message || "Credenciales incorrectas.";
    }

  } catch (e) {
    errorEl.textContent = "Error de conexión. Intente nuevamente.";
  } finally {
    btn.disabled    = false;
    btn.textContent = "Entrar";
  }
});
