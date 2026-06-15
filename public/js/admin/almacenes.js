document.addEventListener("DOMContentLoaded", () => {
  cargarAlmacenes();

  // Botón agregar → abre modal
  document.getElementById("alm-btnAdd").addEventListener("click", abrirModalAgregar);
  document.getElementById("alm-add-cancel").addEventListener("click", () => {
    document.getElementById("alm-addModal").classList.add("hidden");
  });
  document.getElementById("alm-add-save").addEventListener("click", agregarAlmacen);

  // Botones editar modal
  document.getElementById("alm-edit-cancel").addEventListener("click", () => {
    document.getElementById("alm-editModal").classList.add("hidden");
  });

  // Filtro con debounce
  let _timer = null;
  document.getElementById("alm-filtro").addEventListener("input", () => {
    clearTimeout(_timer);
    _timer = setTimeout(() => {
      paginaActual = 1;
      renderizarTabla();
    }, 250);
  });

  // Restricciones numéricas en campos de código
  ["alm-add-sap","alm-add-sigc","alm-add-tfa","alm-add-consig","alm-add-devolucion",
   "alm-edit-sap","alm-edit-sigc","alm-edit-tfa","alm-edit-consig","alm-edit-devolucion"]
    .forEach(id => limitarCodigoAlmacen(document.getElementById(id)));
});

/* ─── ESTADO GLOBAL ─────────────────────────────── */
let almacenesCargados = [];
let paginaActual      = 1;
const POR_PAGINA      = 50;

/* =========================
   CARGAR
========================= */
async function cargarAlmacenes() {
  try {
    const res  = await fetch("../../api/almacen/get_almacenes.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando almacenes.", "error");
      renderizarTabla();
      return;
    }

    almacenesCargados = data;
    paginaActual = 1;
    renderizarTabla();

  } catch (e) {
    mostrarToast("Error cargando almacenes.", "error");
  }
}

/* =========================
   FILTRAR + PAGINAR + TABLA
========================= */
function filtrarAlmacenes() {
  const q = (document.getElementById("alm-filtro").value || "").toLowerCase().trim();
  if (!q) return almacenesCargados;
  return almacenesCargados.filter(a =>
    (a.nombre            || "").toLowerCase().includes(q) ||
    padCodigo(a.almacen_sap).includes(q)                  ||
    padCodigo(a.almacen_sigc).includes(q)                 ||
    padCodigo(a.almacen_tfa).includes(q)                  ||
    padCodigo(a.almacen_consig).includes(q)               ||
    padCodigo(a.almacen_devolucion).includes(q)
  );
}

function renderizarTabla() {
  const lista   = filtrarAlmacenes();
  const total   = lista.length;
  const paginas = Math.max(1, Math.ceil(total / POR_PAGINA));
  if (paginaActual > paginas) paginaActual = paginas;

  const desde = (paginaActual - 1) * POR_PAGINA;
  const hasta = Math.min(desde + POR_PAGINA, total);
  const slice = lista.slice(desde, hasta);

  // Info paginación
  const info = document.getElementById("alm-pag-info");
  if (total === 0) {
    info.textContent = "Sin resultados";
  } else {
    info.textContent = `Mostrando ${desde + 1}–${hasta} de ${total} almacén${total !== 1 ? "es" : ""}`;
  }

  // Filas
  const tbody = document.getElementById("alm-tbody");
  tbody.innerHTML = "";

  if (slice.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">Sin almacenes</td></tr>`;
  } else {
    slice.forEach(a => {
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
            <img src="../../img/icons/edit.svg" width="18">
          </button>
          <button class="btn-borrar" onclick="eliminarAlmacen('${escapeJs(a.nombre)}')">
            <img src="../../img/icons/trash.svg" width="18">
          </button>
        </td>
      `;
      tbody.appendChild(fila);
    });
  }

  // Paginación
  renderizarPaginacion(paginas);
}

function renderizarPaginacion(totalPaginas) {
  const cont = document.getElementById("alm-paginacion");
  cont.innerHTML = "";
  if (totalPaginas <= 1) return;

  const crearBtn = (texto, pagina, deshabilitado = false, activo = false) => {
    const btn = document.createElement("button");
    btn.textContent = texto;
    btn.disabled    = deshabilitado;
    if (activo) btn.classList.add("active");
    btn.addEventListener("click", () => {
      paginaActual = pagina;
      renderizarTabla();
    });
    return btn;
  };

  cont.appendChild(crearBtn("«", 1,              paginaActual === 1));
  cont.appendChild(crearBtn("‹", paginaActual - 1, paginaActual === 1));

  const rango = 2;
  for (let p = 1; p <= totalPaginas; p++) {
    if (p === 1 || p === totalPaginas || Math.abs(p - paginaActual) <= rango) {
      cont.appendChild(crearBtn(p, p, false, p === paginaActual));
    } else if (
      (p === paginaActual - rango - 1 && p > 1) ||
      (p === paginaActual + rango + 1 && p < totalPaginas)
    ) {
      const dots = document.createElement("span");
      dots.textContent = "…";
      dots.style.padding = "0 4px";
      cont.appendChild(dots);
    }
  }

  cont.appendChild(crearBtn("›", paginaActual + 1, paginaActual === totalPaginas));
  cont.appendChild(crearBtn("»", totalPaginas,      paginaActual === totalPaginas));
}

/* =========================
   MODAL AGREGAR
========================= */
function abrirModalAgregar() {
  ["alm-add-nombre","alm-add-sap","alm-add-sigc","alm-add-tfa","alm-add-consig","alm-add-devolucion"]
    .forEach(id => { document.getElementById(id).value = ""; });
  document.getElementById("alm-addModal").classList.remove("hidden");
  document.getElementById("alm-add-nombre").focus();
}

async function agregarAlmacen() {
  const nombre = document.getElementById("alm-add-nombre").value.trim();

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
    sap    = leerCodigo("alm-add-sap");
    sigc   = leerCodigo("alm-add-sigc");
    tfa    = leerCodigo("alm-add-tfa");
    consig = leerCodigo("alm-add-consig");
    devol  = leerCodigo("alm-add-devolucion");
  } catch (e) {
    return;
  }

  if ([sap, sigc, tfa, consig, devol].every(v => v === null)) {
    mostrarToast("Debe ingresar al menos un código de almacén.", "error");
    return;
  }

  document.getElementById("alm-addModal").classList.add("hidden");

  mostrarConfirmacion("¿Desea agregar este almacén?", async () => {
    try {
      const res = await fetch("../../api/almacen/add_almacen.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
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

  document.getElementById("alm-edit-sap").value        = padCodigo(almacen.almacen_sap);
  document.getElementById("alm-edit-sigc").value       = padCodigo(almacen.almacen_sigc);
  document.getElementById("alm-edit-tfa").value        = padCodigo(almacen.almacen_tfa);
  document.getElementById("alm-edit-consig").value     = padCodigo(almacen.almacen_consig);
  document.getElementById("alm-edit-devolucion").value = padCodigo(almacen.almacen_devolucion);

  const modal   = document.getElementById("alm-editModal");
  const btnSave = document.getElementById("alm-edit-save");
  modal.classList.remove("hidden");

  // Reemplazar listener para evitar duplicados
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
      const res = await fetch("../../api/almacen/update_almacen.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
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
}

/* =========================
   ELIMINAR
========================= */
function eliminarAlmacen(nombre) {
  mostrarConfirmacion("¿Eliminar este almacén?", async () => {
    try {
      const res = await fetch("../../api/almacen/delete_almacen.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ nombre })
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

function escapeHtml(t) {
  return t.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function escapeJs(t) {
  return t.replace(/'/g, "\\'");
}

function limitarCodigoAlmacen(input) {
  if (!input) return;
  input.addEventListener("input", () => {
    input.value = input.value.replace(/\D/g, "");
    if (input.value.length > 4) input.value = input.value.slice(0, 4);
  });
  input.addEventListener("wheel", e => e.preventDefault());
  input.addEventListener("keydown", e => {
    const permitidas = ["Backspace","Delete","ArrowLeft","ArrowRight","Tab"];
    if (permitidas.includes(e.key) || (e.key >= "0" && e.key <= "9")) return;
    e.preventDefault();
  });
}
