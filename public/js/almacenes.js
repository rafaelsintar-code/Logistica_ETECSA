document.addEventListener("DOMContentLoaded", () => {
  cargarAlmacenes();

  const btnAdd = document.getElementById("alm-btnAdd");
  if (btnAdd) btnAdd.addEventListener("click", agregarAlmacen);

  [
    "alm-sap",
    "alm-sigc",
    "alm-tfa",
    "alm-consig",
    "alm-devolucion"
  ].forEach(id => {
    const el = document.getElementById(id);
    if (el) limitarCodigoAlmacen(el);
  });
});

let almacenesCargados = [];

/* =========================
   CARGAR
========================= */
async function cargarAlmacenes() {
  try {
    const res = await fetch("../api/almacen/get_almacenes.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando almacenes.", "error");
      mostrarTabla([]);
      return;
    }

    almacenesCargados = data;
    mostrarTabla(data);

  } catch (e) {
    mostrarToast("Error cargando almacenes.", "error");
  }
}

/* =========================
   TABLA
========================= */
function mostrarTabla(lista) {
  const tbody = document.querySelector("#alm-table tbody");
  tbody.innerHTML = "";

  if (lista.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">Sin almacenes</td></tr>`;
    return;
  }

  lista.forEach(a => {
    const fila = document.createElement("tr");
    fila.innerHTML = `
      <td>${escapeHtml(a.nombre)}</td>
      <td>${padCodigo(a.almacen_sap)}</td>
      <td>${padCodigo(a.almacen_sigc)}</td>
      <td>${padCodigo(a.almacen_tfa)}</td>
      <td>${padCodigo(a.almacen_consig)}</td>
      <td>${padCodigo(a.almacen_devolucion)}</td>
      <td>
        <button class="btn-editar" onclick="abrirEditar('${escapeJs(a.nombre)}')">
          <img src="../img/icons/edit.svg" width="18">
        </button>
        <button class="btn-borrar" onclick="eliminarAlmacen('${escapeJs(a.nombre)}')">
          <img src="../img/icons/trash.svg" width="18">
        </button>
      </td>
    `;
    tbody.appendChild(fila);
  });
}

/* =========================
   AGREGAR
========================= */
function agregarAlmacen() {
  const nombre = document.getElementById("alm-nombre").value.trim();

  if (!nombre) {
    mostrarToast("El nombre del almacén es obligatorio.", "error");
    return;
  }

  if (almacenesCargados.some(a => a.nombre === nombre)) {
    mostrarToast("Ya existe un almacén con ese nombre.", "error");
    return;
  }

  let sap, sigc, tfa, consig, devol;
  try {
    sap    = leerCodigo("alm-sap");
    sigc   = leerCodigo("alm-sigc");
    tfa    = leerCodigo("alm-tfa");
    consig = leerCodigo("alm-consig");
    devol  = leerCodigo("alm-devolucion");
  } catch (e) {
    return;
  }

  if ([sap, sigc, tfa, consig, devol].every(v => v === null)) {
    mostrarToast("Debe ingresar al menos un código de almacén.", "error");
    return;
  }

  mostrarConfirmacion("¿Desea agregar este almacén?", async () => {
    try {
      const res = await fetch("../api/almacen/add_almacen.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          nombre,
          almacen_sap:        sap,
          almacen_sigc:       sigc,
          almacen_tfa:        tfa,
          almacen_consig:     consig,
          almacen_devolucion: devol
        })
      });

      const resp = await res.json();

      if (resp.success === false) {
        mostrarToast(resp.message || "Error al agregar almacén.", "error");
        return;
      }

      mostrarToast("Almacén agregado correctamente.", "success");
      limpiarFormulario();
      cargarAlmacenes();

    } catch (e) {
      mostrarToast("Error al agregar almacén.", "error");
    }
  });
}

/* =========================
   EDITAR
========================= */
function abrirEditar(nombre) {
  const almacen = almacenesCargados.find(a => a.nombre === nombre);
  if (!almacen) return;

  // Se muestra el código con ceros a la izquierda en el campo de edición
  document.getElementById("alm-edit-sap").value        = padCodigo(almacen.almacen_sap);
  document.getElementById("alm-edit-sigc").value       = padCodigo(almacen.almacen_sigc);
  document.getElementById("alm-edit-tfa").value        = padCodigo(almacen.almacen_tfa);
  document.getElementById("alm-edit-consig").value     = padCodigo(almacen.almacen_consig);
  document.getElementById("alm-edit-devolucion").value = padCodigo(almacen.almacen_devolucion);

  const modal     = document.getElementById("alm-editModal");
  const btnSave   = document.getElementById("alm-edit-save");
  const btnCancel = document.getElementById("alm-edit-cancel");

  modal.classList.remove("hidden");

  const nuevoSave = btnSave.cloneNode(true);
  btnSave.parentNode.replaceChild(nuevoSave, btnSave);

  nuevoSave.addEventListener("click", async () => {

    let sap, sigc, tfa, consig, devol;
    try {
      sap    = leerCodigo("alm-edit-sap");
      sigc   = leerCodigo("alm-edit-sigc");
      tfa    = leerCodigo("alm-edit-tfa");
      consig = leerCodigo("alm-edit-consig");
      devol  = leerCodigo("alm-edit-devolucion");
    } catch (e) {
      return;
    }

    modal.classList.add("hidden");

    try {
      const res = await fetch("../api/almacen/update_almacen.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          nombre,
          almacen_sap:        sap,
          almacen_sigc:       sigc,
          almacen_tfa:        tfa,
          almacen_consig:     consig,
          almacen_devolucion: devol
        })
      });

      const resp = await res.json();

      if (resp.success === false) {
        mostrarToast(resp.message || "Error al actualizar almacén.", "error");
        return;
      }

      mostrarToast("Almacén actualizado correctamente.", "success");
      cargarAlmacenes();

    } catch (e) {
      mostrarToast("Error al actualizar almacén.", "error");
    }
  });

  btnCancel.onclick = () => modal.classList.add("hidden");
}

/* =========================
   ELIMINAR
========================= */
function eliminarAlmacen(nombre) {
  mostrarConfirmacion("¿Eliminar este almacén?", async () => {
    try {
      const res = await fetch("../api/almacen/delete_almacen.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nombre })
      });

      const resp = await res.json();

      if (resp.success === false) {
        mostrarToast(resp.message || "Error al eliminar almacén.", "error");
        return;
      }

      mostrarToast("Almacén eliminado correctamente.", "success");
      cargarAlmacenes();

    } catch (e) {
      mostrarToast("Error al eliminar almacén.", "error");
    }
  });
}

/* =========================
   HELPERS
========================= */

// Formatea un código numérico a 4 dígitos con ceros a la izquierda
function padCodigo(v) {
  if (v === null || v === undefined || v === "") return "";
  return String(v).padStart(4, "0");
}

function leerCodigo(id) {
  const v = document.getElementById(id).value.trim();
  if (v === "") return null;
  if (!/^\d{4}$/.test(v)) {
    mostrarToast("Los códigos deben tener exactamente 4 dígitos.", "error");
    throw new Error("Código inválido");
  }
  return v;
}

function limpiarFormulario() {
  ["alm-nombre", "alm-sap", "alm-sigc", "alm-tfa", "alm-consig", "alm-devolucion"]
    .forEach(id => document.getElementById(id).value = "");
}

function escapeHtml(t) {
  return t.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function escapeJs(t) {
  return t.replace(/'/g, "\\'");
}

function limitarCodigoAlmacen(input) {
  input.addEventListener("input", () => {
    input.value = input.value.replace(/\D/g, "");
    if (input.value.length > 4) {
      input.value = input.value.slice(0, 4);
    }
  });

  input.addEventListener("wheel", e => e.preventDefault());

  input.addEventListener("keydown", e => {
    const permitidas = ["Backspace", "Delete", "ArrowLeft", "ArrowRight", "Tab"];
    if (permitidas.includes(e.key) || (e.key >= "0" && e.key <= "9")) return;
    e.preventDefault();
  });
}