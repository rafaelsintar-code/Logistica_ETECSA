// activos.js - CRUD Activos con modales y toasts (IDs prefijados "act-")

document.addEventListener("DOMContentLoaded", () => {
  cargarActivos();

  const btnAdd = document.getElementById("act-btnAdd");
  const btnImportar = document.getElementById("act-btnImportar");
  const fileExcel = document.getElementById("act-fileExcel");

  if (btnAdd) btnAdd.addEventListener("click", agregarActivo);

  if (btnImportar && fileExcel) {
    btnImportar.addEventListener("click", () => fileExcel.click());
  }

  if (fileExcel) {
    fileExcel.addEventListener("change", importarActivosExcel);
  }
});

// =========================
// ESTADO LOCAL
// =========================
let activosCargados = [];

// =========================
// CARGAR
// =========================
function cargarActivos() {
  fetch("../api/activo/get_activos.php")
    .then(r => r.json())
    .then(data => {
      if (!Array.isArray(data)) {
        console.error("get_activos: respuesta inesperada", data);
        mostrarToast("Error cargando activos.", "error");
        activosCargados = [];
        mostrarTablaActivos([]);
        return;
      }
      activosCargados = data;
      mostrarTablaActivos(activosCargados);
    })
    .catch(err => {
      console.error("Error cargando activos:", err);
      mostrarToast("Error cargando activos.", "error");
    });
}

// =========================
// MOSTRAR TABLA
// =========================
function mostrarTablaActivos(activos) {
  const cuerpo = document.querySelector("#act-table tbody");
  if (!cuerpo) return;

  cuerpo.innerHTML = "";

  if (!activos || activos.length === 0) {
    cuerpo.innerHTML = `<tr><td colspan="4" style="text-align:center;">Sin activos</td></tr>`;
    return;
  }

  activos.forEach(a => {
    const id = String(a.id_activo);
    const nombre = String(a.nombre_activo ?? "");
    const tipo = String(a.tipo_activo ?? "");

    const fila = document.createElement("tr");
    fila.innerHTML = `
      <td>${escapeHtml(id)}</td>
      <td>${escapeHtml(nombre)}</td>
      <td>${escapeHtml(tipo)}</td>
      <td>
        <button type="button" class="btn-editar"
          onclick="abrirEditarActivo('${encodeURIComponent(id)}','${escapeJs(nombre)}','${escapeJs(tipo)}')">
          <img src="../img/icons/edit.svg" width="18">
        </button>
        <button type="button" class="btn-borrar"
          onclick="eliminarActivo('${encodeURIComponent(id)}')">
          <img src="../img/icons/trash.svg" width="18">
        </button>
      </td>
    `;
    cuerpo.appendChild(fila);
  });
}

// =========================
// HELPERS
// =========================
function escapeHtml(s){
  if (s === null || s === undefined) return "";
  return String(s)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;');
}

function escapeJs(s){
  if (s === null || s === undefined) return "";
  return String(s)
    .replace(/'/g,"\\'")
    .replace(/"/g,'\\"');
}

// =========================
// AGREGAR
// =========================
function agregarActivo() {
  const idEl = document.getElementById("act-id");
  const nombreEl = document.getElementById("act-nombre");
  const tipoEl = document.getElementById("act-tipo");

  if (!idEl || !nombreEl || !tipoEl) {
    mostrarToast("Campos del formulario no encontrados.", "error");
    return;
  }

  const id = idEl.value.trim();
  const nombre = nombreEl.value.trim();
  const tipo = tipoEl.value.trim();

  if (!id || !nombre || !tipo) {
    mostrarToast("Debe completar todos los campos.", "error");
    return;
  }

  if (activosCargados.some(a => String(a.id_activo) === id)) {
    mostrarToast("El ID ya existe.", "error");
    return;
  }

  if (activosCargados.some(a =>
      String(a.nombre_activo).toLowerCase() === nombre.toLowerCase())) {
    mostrarToast("Ya existe un activo con ese nombre.", "error");
    return;
  }

  mostrarConfirmacion("¿Desea agregar este activo?", () => {
    fetch("../api/activo/add_activo.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, nombre, tipo })
    })
    .then(r => r.json())
    .then(data => {
      if (data.error || data.success === false) {
        mostrarToast(data.message || data.error || "Error al agregar.", "error");
        return;
      }
      mostrarToast("Activo agregado correctamente.", "success");
      idEl.value = "";
      nombreEl.value = "";
      tipoEl.value = "";
      cargarActivos();
    })
    .catch(() => mostrarToast("Error al agregar activo.", "error"));
  });
}

// =========================
// EDITAR
// =========================
function abrirEditarActivo(idEnc, nombreEsc, tipoEsc) {
  const id = decodeURIComponent(idEnc);
  const nombre = nombreEsc.replace(/\\'/g,"'").replace(/\\"/g,'"');
  const tipo = tipoEsc.replace(/\\'/g,"'").replace(/\\"/g,'"');

  const modal = document.getElementById("act-editModal");
  const inNombre = document.getElementById("act-edit-nombre");
  const inTipo = document.getElementById("act-edit-tipo");
  const btnSave = document.getElementById("act-edit-save");
  const btnCancel = document.getElementById("act-edit-cancel");

  if (!modal || !inNombre || !inTipo || !btnSave || !btnCancel) return;

  inNombre.value = nombre;
  inTipo.value = tipo;

  modal.classList.remove("hidden");

  const save = btnSave.cloneNode(true);
  const cancel = btnCancel.cloneNode(true);
  btnSave.parentNode.replaceChild(save, btnSave);
  btnCancel.parentNode.replaceChild(cancel, btnCancel);

  save.addEventListener("click", () => {
    const nuevoNombre = inNombre.value.trim();
    const nuevoTipo = inTipo.value.trim();

    if (!nuevoNombre || !nuevoTipo) {
      mostrarToast("Campos inválidos.", "error");
      return;
    }

    const duplicado = activosCargados.some(a =>
      String(a.nombre_activo).toLowerCase() === nuevoNombre.toLowerCase()
      && String(a.id_activo) !== id
    );

    if (duplicado) {
      mostrarToast("Ya existe otro activo con ese nombre.", "error");
      return;
    }

    modal.classList.add("hidden");

    fetch("../api/activo/update_activo.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, nombre: nuevoNombre, tipo: nuevoTipo })
    })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        mostrarToast(data.error, "error");
        return;
      }
      mostrarToast("Activo actualizado correctamente.", "success");
      cargarActivos();
    })
    .catch(() => mostrarToast("Error al actualizar activo.", "error"));
  });

  cancel.addEventListener("click", () => modal.classList.add("hidden"));
}

// =========================
// ELIMINAR
// =========================
function eliminarActivo(idEnc) {
  const id = decodeURIComponent(idEnc);

  mostrarConfirmacion("¿Seguro que desea eliminar este activo?", () => {
    fetch("../api/activo/delete_activo.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
      if (data.error || data.success === false) {
        mostrarToast(data.message || data.error || "Error al eliminar.", "error");
        return;
      }
      mostrarToast("Activo eliminado correctamente.", "success");
      cargarActivos();
    })
    .catch(() => mostrarToast("Error al eliminar activo.", "error"));
  });
}

// =========================
// IMPORTAR EXCEL
// =========================
function importarActivosExcel() {
  const fileInput = document.getElementById("act-fileExcel");
  const archivo = fileInput.files[0];
  if (!archivo) return;

  const nombre = archivo.name.toLowerCase();
  if (!nombre.endsWith(".xls") && !nombre.endsWith(".xlsx")) {
    mostrarToast("El archivo debe ser XLS o XLSX.", "error");
    return;
  }

  const formData = new FormData();
  formData.append("excel", archivo);

  mostrarConfirmacion("¿Desea importar este archivo? Se sincronizarán los datos.", () => {
    fetch("../api/activo/importar_activos_excel.php", {
      method: "POST",
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        mostrarToast(data.error, "error");
      } else {
        mostrarToast("Datos importados correctamente.", "success");
        cargarActivos();
      }
    })
    .catch(() => mostrarToast("Error al importar archivo.", "error"));
  });
}
