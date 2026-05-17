document.addEventListener("DOMContentLoaded", () => {
  cargarUsuarios();

  document.getElementById("usr-btnAdd")?.addEventListener("click", abrirCrear);
  document.getElementById("usr-add-save")?.addEventListener("click", confirmarCrear);
  document.getElementById("usr-add-cancel")?.addEventListener("click", cerrarCrear);
  document.getElementById("usr-edit-cancel")?.addEventListener("click", cerrarEditar);
});

let usuariosCargados = [];

/* =========================
   UTIL
========================= */
function ocultarTodosLosModales() {
  document.querySelectorAll(".confirm-modal").forEach(m => {
    if (m.id !== "global-confirm") m.classList.add("hidden");
  });
}

function escapeHtml(t) {
  if (!t) return "";
  return String(t)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

function formatFecha(ts) {
  if (!ts) return "—";
  const d = new Date(ts);
  if (isNaN(d)) return "—";
  const pad = n => String(n).padStart(2, "0");
  return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function esBloqueado(u) {
  if (!u.bloqueado_hasta) return false;
  return new Date(u.bloqueado_hasta) > new Date();
}

function limpiarFormCrear() {
  ["usr-username","usr-nombre","usr-correo","usr-password"].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = "";
  });
  const rol = document.getElementById("usr-rol");
  if (rol) rol.value = "Administrador";
}

/* =========================
   CARGAR
========================= */
async function cargarUsuarios() {
  try {
    const res  = await fetch("../api/usuario/get_usuarios.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando usuarios", "error");
      return;
    }

    usuariosCargados = data;
    renderTabla(data);

  } catch (e) {
    mostrarToast("No se pudo cargar usuarios", "error");
  }
}

/* =========================
   TABLA
========================= */
function renderTabla(lista) {
  const tbody = document.querySelector("#usr-table tbody");
  tbody.innerHTML = "";

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No hay usuarios registrados</td></tr>`;
    return;
  }

  lista.forEach(u => {
    const tr   = document.createElement("tr");
    const bloq = esBloqueado(u);
    const estadoBadge = bloq
      ? `<span class="badge-danger">Bloqueado</span>`
      : u.activo
        ? `<span class="badge-success">Activo</span>`
        : `<span class="badge-danger">Inactivo</span>`;

    const btnDesbloquear = bloq
      ? `<button class="btn-borrar btn-desbloquear" title="Desbloquear cuenta" style="font-size:0.75rem;">
           Desbloquear
         </button>`
      : "";

    tr.innerHTML = `
      <td>${escapeHtml(u.username)}</td>
      <td>${escapeHtml(u.nombre)}</td>
      <td>${escapeHtml(u.correo)}</td>
      <td>${escapeHtml(u.rol)}</td>
      <td>${estadoBadge}</td>
      <td>${formatFecha(u.ultimo_acceso)}</td>
      <td>
        <button class="btn-editar" title="Editar">
          <img src="../img/icons/edit.svg" width="18">
        </button>
        ${btnDesbloquear}
      </td>
    `;

    tr.querySelector(".btn-editar")
      .addEventListener("click", () => abrirEditar(u.username));

    if (bloq) {
      tr.querySelector(".btn-desbloquear")
        .addEventListener("click", () => desbloquearUsuario(u.username));
    }

    tbody.appendChild(tr);
  });
}

/* =========================
   CREAR
========================= */
function abrirCrear() {
  limpiarFormCrear();
  document.getElementById("usr-addModal").classList.remove("hidden");
}

function cerrarCrear() {
  document.getElementById("usr-addModal").classList.add("hidden");
  limpiarFormCrear();
}

function confirmarCrear() {
  const payload = {
    username: document.getElementById("usr-username").value.trim(),
    nombre:   document.getElementById("usr-nombre").value.trim(),
    correo:   document.getElementById("usr-correo").value.trim(),
    password: document.getElementById("usr-password").value,
    rol:      document.getElementById("usr-rol").value
  };

  if (!payload.username || !payload.nombre || !payload.correo || !payload.password) {
    mostrarToast("Todos los campos son obligatorios", "error");
    return;
  }

  if (payload.password.length < 8) {
    mostrarToast("La contraseña debe tener al menos 8 caracteres", "error");
    return;
  }

  ocultarTodosLosModales();
  mostrarConfirmacion("¿Crear este usuario?", async () => {
    await crearUsuario(payload);
  });
}

async function crearUsuario(payload) {
  try {
    const res  = await fetch("../api/usuario/add_usuario.php", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify(payload)
    });
    const resp = await res.json();

    if (!resp.success) {
      mostrarToast(resp.error || "No se pudo crear", "error");
      return;
    }

    mostrarToast("Usuario creado", "success");
    limpiarFormCrear();
    cargarUsuarios();

  } catch {
    mostrarToast("Error creando usuario", "error");
  }
}

/* =========================
   EDITAR
========================= */
function abrirEditar(username) {
  const u = usuariosCargados.find(x => x.username === username);
  if (!u) return;

  document.getElementById("usr-edit-nombre").value   = u.nombre;
  document.getElementById("usr-edit-correo").value   = u.correo;
  document.getElementById("usr-edit-password").value = "";
  document.getElementById("usr-edit-rol").value      = u.rol;
  document.getElementById("usr-edit-activo").checked = u.activo;

  document.getElementById("usr-editModal").classList.remove("hidden");

  document.getElementById("usr-edit-save").onclick = () => {
    const payload = {
      username,
      nombre:   document.getElementById("usr-edit-nombre").value.trim(),
      correo:   document.getElementById("usr-edit-correo").value.trim(),
      password: document.getElementById("usr-edit-password").value,
      rol:      document.getElementById("usr-edit-rol").value,
      activo:   document.getElementById("usr-edit-activo").checked
    };

    if (!payload.nombre || !payload.correo) {
      mostrarToast("Nombre y correo obligatorios", "error");
      return;
    }

    if (payload.password && payload.password.length < 8) {
      mostrarToast("La contraseña debe tener al menos 8 caracteres", "error");
      return;
    }

    ocultarTodosLosModales();
    mostrarConfirmacion("¿Guardar cambios?", async () => {
      await actualizarUsuario(payload);
    });
  };
}

function cerrarEditar() {
  document.getElementById("usr-editModal").classList.add("hidden");
}

async function actualizarUsuario(payload) {
  try {
    const res  = await fetch("../api/usuario/update_usuario.php", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify(payload)
    });
    const resp = await res.json();

    if (!resp.success) {
      mostrarToast(resp.error || "No se pudo actualizar", "error");
      return;
    }

    mostrarToast("Usuario actualizado", "success");
    cargarUsuarios();

  } catch {
    mostrarToast("Error actualizando", "error");
  }
}

/* =========================
   DESBLOQUEAR
========================= */
async function desbloquearUsuario(username) {
  mostrarConfirmacion(`¿Desbloquear la cuenta de "${username}"?`, async () => {
    try {
      const res  = await fetch("../api/usuario/desbloquear_usuario.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ username })
      });
      const resp = await res.json();

      if (!resp.success) {
        mostrarToast(resp.message || "No se pudo desbloquear", "error");
        return;
      }

      mostrarToast(`Cuenta de "${username}" desbloqueada`, "success");
      cargarUsuarios();

    } catch {
      mostrarToast("Error desbloqueando usuario", "error");
    }
  });
}
