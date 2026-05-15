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
      // Redirigir según rol
      window.location.href = data.redirect;
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