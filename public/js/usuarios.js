document.addEventListener("DOMContentLoaded", () => {
  cargarUsuarios();

  document.getElementById("usr-btnAdd")?.addEventListener("click", abrirCrear);
  document.getElementById("usr-add-save")?.addEventListener("click", confirmarCrear);
  document.getElementById("usr-add-cancel")?.addEventListener("click", cerrarCrear);

  document.getElementById("usr-edit-cancel")?.addEventListener("click", cerrarEditar);
  document.getElementById("usr-delete-cancel")?.addEventListener("click", cerrarEliminar);
});

let usuariosCargados = [];

/* =========================
   UTIL
========================= */
function ocultarTodosLosModales() {
  document.querySelectorAll(".confirm-modal").forEach(m => {
    if (m.id !== "global-confirm") {
      m.classList.add("hidden");
    }
  });
}

/* =========================
   CARGAR
========================= */
async function cargarUsuarios() {
  try {
    const res = await fetch("../api/usuario/get_usuarios.php");
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
    tbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align:center;">
          No hay usuarios registrados
        </td>
      </tr>`;
    return;
  }

  lista.forEach(u => {
    const tr = document.createElement("tr");

    tr.innerHTML = `
      <td>${escapeHtml(u.username)}</td>
      <td>${escapeHtml(u.nombre)}</td>
      <td>${escapeHtml(u.correo)}</td>
      <td>${escapeHtml(u.rol)}</td>
      <td>
        <span class="${u.activo ? "badge-success" : "badge-danger"}">
          ${u.activo ? "Activo" : "Inactivo"}
        </span>
      </td>
      <td>
        <button class="btn-editar">
          <img src="../img/icons/edit.svg" width="18">
        </button>
        <button class="btn-borrar">
          <img src="../img/icons/trash.svg" width="18">
        </button>
      </td>
    `;

    tr.querySelector(".btn-editar")
      .addEventListener("click", () => abrirEditar(u.username));

    tr.querySelector(".btn-borrar")
      .addEventListener("click", () => abrirEliminar(u.username));

    tbody.appendChild(tr);
  });
}

/* =========================
   CREAR
========================= */
function abrirCrear() {
  document.getElementById("usr-addModal").classList.remove("hidden");
}

function cerrarCrear() {
  document.getElementById("usr-addModal").classList.add("hidden");
}

function confirmarCrear() {
  const payload = {
    username: document.getElementById("usr-username").value.trim(),
    nombre: document.getElementById("usr-nombre").value.trim(),
    correo: document.getElementById("usr-correo").value.trim(),
    password: document.getElementById("usr-password").value,
    rol: document.getElementById("usr-rol").value
  };

  if (!payload.username || !payload.nombre || !payload.correo || !payload.password) {
    mostrarToast("Todos los campos son obligatorios", "error");
    return;
  }

  ocultarTodosLosModales();

  mostrarConfirmacion("¿Crear este usuario?", async () => {
    await crearUsuario(payload);
  });
}

async function crearUsuario(payload) {
  try {
    const res = await fetch("../api/usuario/add_usuario.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    const resp = await res.json();

    if (!resp.success) {
      mostrarToast(resp.error || "No se pudo crear", "error");
      return;
    }

    mostrarToast("Usuario creado", "success");
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

  const modal = document.getElementById("usr-editModal");

  document.getElementById("usr-edit-nombre").value = u.nombre;
  document.getElementById("usr-edit-correo").value = u.correo;
  document.getElementById("usr-edit-password").value = "";
  document.getElementById("usr-edit-rol").value = u.rol;
  document.getElementById("usr-edit-activo").checked = u.activo;

  modal.classList.remove("hidden");

  document.getElementById("usr-edit-save").onclick = () => {
    const payload = {
      username,
      nombre: document.getElementById("usr-edit-nombre").value.trim(),
      correo: document.getElementById("usr-edit-correo").value.trim(),
      password: document.getElementById("usr-edit-password").value,
      rol: document.getElementById("usr-edit-rol").value,
      activo: document.getElementById("usr-edit-activo").checked
    };

    if (!payload.nombre || !payload.correo) {
      mostrarToast("Nombre y correo obligatorios", "error");
      return;
    }

    ocultarTodosLosModales();

    mostrarConfirmacion("¿Guardar cambios?", async () => {
      await actualizarUsuario(payload);
    });
  };
}

async function actualizarUsuario(payload) {
  try {
    const res = await fetch("../api/usuario/update_usuario.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
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
   ELIMINAR
========================= */
function abrirEliminar(username) {
  document.getElementById("usr-deleteModal").classList.remove("hidden");

  document.getElementById("usr-delete-confirm").onclick = () => {
    ocultarTodosLosModales();

    mostrarConfirmacion("¿Eliminar definitivamente?", async () => {
      await eliminarUsuario(username);
    });
  };
}

async function eliminarUsuario(username) {
  try {
    const res = await fetch("../api/usuario/delete_usuario.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ username })
    });

    const resp = await res.json();

    if (!resp.success) {
      mostrarToast(resp.error || "No se pudo eliminar", "error");
      return;
    }

    mostrarToast("Usuario eliminado", "success");
    cargarUsuarios();

  } catch {
    mostrarToast("Error eliminando", "error");
  }
}

function cerrarEditar() {
  document.getElementById("usr-editModal").classList.add("hidden");
}

function cerrarEliminar() {
  document.getElementById("usr-deleteModal").classList.add("hidden");
}

/* =========================
   HELPERS
========================= */
function escapeHtml(t) {
  if (!t) return "";
  return t.replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;");
}
