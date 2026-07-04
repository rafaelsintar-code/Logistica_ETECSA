document.getElementById("login-form").addEventListener("submit", async (e) => {
  e.preventDefault();

  const username  = document.getElementById("login-username").value.trim();
  const password  = document.getElementById("login-password").value;
  const localMode = document.getElementById("login-local").checked;
  const errorEl   = document.getElementById("login-error");

  errorEl.textContent = "";

  if (!username || !password) {
    errorEl.textContent = "Complete todos los campos.";
    return;
  }

  const endpoint = localMode
    ? "../api/auth/login.php"
    : "../api/auth/login_ldap.php";

  const btn = document.querySelector("#login-form button");
  btn.disabled    = true;
  btn.textContent = "Verificando...";

  try {
    const res  = await fetch(endpoint, {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({ username, password })
    });

    const data = await res.json();

    if (data.success) {
      if (data.csrf_token) {
        sessionStorage.setItem("csrf_token", data.csrf_token);
      }
      const rutasValidas = {
        "admin/index.html":     "admin/index.html",
        "visitante/index.html": "visitante/index.html",
      };
      window.location.href = rutasValidas[data.redirect] ?? "visitante/index.html";
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
