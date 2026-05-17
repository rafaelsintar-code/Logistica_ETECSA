document.addEventListener("DOMContentLoaded", () => {
  cargarAlmacenesSelects();
  cargarTransferencias();

  document.getElementById("trf-select-origen")
    .addEventListener("change", onOrigenChange);

  document.getElementById("trf-select-destino")
    .addEventListener("change", onDestinoChange);

  document.getElementById("trf-btnAgregar")
    .addEventListener("click", agregarTransferencia);

  document.getElementById("trf-filtro-periodo")
    .addEventListener("change", () => renderizarTabla());

  document.getElementById("trf-filtro-articulos")
    .addEventListener("input", debounceFiltro);

  document.getElementById("trf-btn-limpiar")
    .addEventListener("click", limpiarSeleccionModal);
});

/* ─── ESTADO GLOBAL ─────────────────────────────── */
let almacenesDisponibles     = [];
let articulosOrigenCargados  = [];   // artículos del almacén origen activo en el modal
let transferenciasGuardadas  = [];
const seleccionPorId         = new Map();
let filaActiva               = null;
let seleccionTemporal        = new Map();
let _timerFiltro             = null;

/* ─── PERSISTENCIA sessionStorage ──────────────────
   Igual que solicitud: Map<id, Map<codigo, {articulo, cantidad}>>
─────────────────────────────────────────────────── */
function guardarSeleccion() {
  const serializable = [...seleccionPorId.entries()].map(([id, mapa]) => [
    id,
    [...mapa.entries()].map(([codigo, val]) => [codigo, {
      cantidad: val.cantidad,
      articulo: {
        codigo_articulo: val.articulo.codigo_articulo,
        desc_articulo:   val.articulo.desc_articulo,
        stock:           val.articulo.stock
      }
    }])
  ]);
  sessionStorage.setItem("trf_seleccion", JSON.stringify(serializable));
}

function restaurarSeleccion() {
  const raw = sessionStorage.getItem("trf_seleccion");
  if (!raw) return;
  try {
    const parsed = JSON.parse(raw);
    parsed.forEach(([id, entradas]) => {
      seleccionPorId.set(id, new Map(
        entradas.map(([codigo, val]) => [codigo, val])
      ));
    });
  } catch (e) {
    sessionStorage.removeItem("trf_seleccion");
  }
}

/* ─── CARGAR AMBOS SELECTS DE ALMACENES ─────────── */
async function cargarAlmacenesSelects() {
  try {
    const res  = await fetch("../api/solicitud/get_almacenes_solicitud.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando almacenes.", "error");
      return;
    }

    almacenesDisponibles = data;
    poblarSelectOrigen();

  } catch (e) {
    mostrarToast("Error cargando almacenes.", "error");
  }
}

function poblarSelectOrigen() {
  const sel = document.getElementById("trf-select-origen");
  // Mantener solo el placeholder
  sel.innerHTML = '<option value="">-- Almacén origen --</option>';

  almacenesDisponibles.forEach(a => {
    const opt        = document.createElement("option");
    opt.value        = a.codigo_almacen;
    opt.textContent  = `${a.codigo_almacen} — ${a.nombre_pv}`;
    opt.dataset.denom = a.nombre_pv;
    sel.appendChild(opt);
  });
}

function poblarSelectDestino(codigoOrigenExcluir) {
  const sel = document.getElementById("trf-select-destino");
  sel.innerHTML = '<option value="">-- Almacén destino --</option>';

  almacenesDisponibles
    .filter(a => String(a.codigo_almacen) !== String(codigoOrigenExcluir))
    .forEach(a => {
      const opt        = document.createElement("option");
      opt.value        = a.codigo_almacen;
      opt.textContent  = `${a.codigo_almacen} — ${a.nombre_pv}`;
      opt.dataset.denom = a.nombre_pv;
      sel.appendChild(opt);
    });

  sel.disabled = false;
}

/* ─── EVENTOS DE LOS SELECTS ────────────────────── */
function onOrigenChange() {
  const val = document.getElementById("trf-select-origen").value;
  document.getElementById("trf-btnAgregar").disabled = true;

  if (val === "") {
    document.getElementById("trf-select-destino").innerHTML =
      '<option value="">-- Almacén destino --</option>';
    document.getElementById("trf-select-destino").disabled = true;
    return;
  }

  poblarSelectDestino(val);
  document.getElementById("trf-select-destino").value = "";
}

function onDestinoChange() {
  const origen  = document.getElementById("trf-select-origen").value;
  const destino = document.getElementById("trf-select-destino").value;
  document.getElementById("trf-btnAgregar").disabled = (origen === "" || destino === "");
}

/* ─── CARGAR TRANSFERENCIAS DESDE BD ────────────── */
async function cargarTransferencias() {

  // Limpiar expiradas
  try {
    const resLimpiar  = await fetch("../api/transferencia/limpiar_transferencias.php", { method: "POST" });
    const dataLimpiar = await resLimpiar.json();
    if (dataLimpiar.success && dataLimpiar.eliminadas.length > 0) {
      dataLimpiar.eliminadas.forEach(id => seleccionPorId.delete(id));
      guardarSeleccion();
      mostrarToast(`${dataLimpiar.eliminadas.length} transferencia(s) expirada(s) eliminada(s).`, "info");
    }
  } catch (e) {}

  // Cargar vigentes
  try {
    const res  = await fetch("../api/transferencia/get_transferencias.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando transferencias.", "error");
      return;
    }

    transferenciasGuardadas = data;
    restaurarSeleccion();

    data.forEach(t => {
      if (!seleccionPorId.has(t.id)) seleccionPorId.set(t.id, new Map());
    });

    // Purgar ids que ya no existen en BD
    const idsActuales = new Set(data.map(t => t.id));
    [...seleccionPorId.keys()].forEach(id => {
      if (!idsActuales.has(id)) seleccionPorId.delete(id);
    });

    renderizarTabla();

  } catch (e) {
    mostrarToast("Error cargando transferencias.", "error");
  }
}

/* ─── FILTRO POR PERÍODO ────────────────────────── */
function filtrarPorPeriodo(lista) {
  const periodo = document.getElementById("trf-filtro-periodo").value;
  const ahora   = new Date();

  return lista.filter(t => {
    const fecha = new Date(t.hora_creacion);
    switch (periodo) {
      case "hoy":    return fecha.toDateString() === ahora.toDateString();
      case "semana": { const h = new Date(ahora); h.setDate(h.getDate() - 7);  return fecha >= h; }
      case "mes":    { const h = new Date(ahora); h.setDate(h.getDate() - 30); return fecha >= h; }
      case "anio":   return fecha.getFullYear() === ahora.getFullYear();
      default:       return true;
    }
  });
}

/* ─── RENDERIZAR TABLA PRINCIPAL ────────────────── */
function renderizarTabla() {
  const tbody     = document.getElementById("trf-tbody");
  const filtradas = filtrarPorPeriodo(transferenciasGuardadas);

  tbody.innerHTML = "";

  if (filtradas.length === 0) {
    tbody.innerHTML = `
      <tr id="trf-empty-row">
        <td colspan="8" style="text-align:center;">Sin transferencias para este período</td>
      </tr>`;
    return;
  }

  filtradas.forEach(t => {
    const confirmada  = t.hora_confirmacion !== null;
    const fechaCreada = formatearFecha(t.hora_creacion);
    const fechaConf   = confirmada ? formatearFecha(t.hora_confirmacion) : null;

    const tr = document.createElement("tr");
    tr.id    = `trf-fila-${t.id}`;

    tr.innerHTML = `
      <td>${escapeHtml(String(t.almacen_origen))} — ${escapeHtml(t.denom_origen)}</td>
      <td>${escapeHtml(String(t.almacen_destino))} — ${escapeHtml(t.denom_destino)}</td>
      <td>${escapeHtml(t.usuario)}</td>
      <td>${fechaCreada}</td>
      <td style="text-align:center; font-weight:600;
          color:${confirmada ? "#27ae60" : "#888"};
          font-style:${confirmada ? "normal" : "italic"};">
        ${confirmada ? `Vale #${t.vale}` : "Pendiente"}
      </td>
      <td style="text-align:center;">
        ${confirmada
          ? `<span style="color:#27ae60; font-weight:600;">${fechaConf}</span>`
          : `<button class="sol-btn sol-btn-confirmar" onclick="confirmarTransferencia(${t.id})">
               <span class="sol-btn-icon">✓</span> Confirmar
             </button>`
        }
      </td>
      <td style="text-align:center;">
        ${!confirmada
          ? `<button class="sol-btn sol-btn-agregar" onclick="abrirModalArticulos(${t.id})">
               <span class="sol-btn-icon">＋</span> Artículos
             </button>`
          : `—`
        }
      </td>
      <td style="text-align:center; white-space:nowrap;">
        ${!confirmada
          ? `<button class="sol-btn sol-btn-doc" onclick="abrirPreview(${t.id})" title="Ver documento">
               &#128196;
             </button>
             <button class="sol-btn sol-btn-descartar" title="Descartar"
               onclick="descartarTransferencia(${t.id})">✕</button>`
          : ``
        }
      </td>
    `;

    tbody.appendChild(tr);
  });
}

/* ─── AGREGAR NUEVA TRANSFERENCIA ───────────────── */
async function agregarTransferencia() {
  const selOrigen  = document.getElementById("trf-select-origen");
  const selDestino = document.getElementById("trf-select-destino");

  const codigoOrigen  = selOrigen.value;
  const codigoDestino = selDestino.value;
  if (!codigoOrigen || !codigoDestino) return;

  const optOrigen  = selOrigen.options[selOrigen.selectedIndex];
  const optDestino = selDestino.options[selDestino.selectedIndex];

  const btnAgregar = document.getElementById("trf-btnAgregar");
  btnAgregar.disabled = true;

  try {
    const res = await fetch("../api/transferencia/add_transferencia.php", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({
        almacen_origen:  codigoOrigen,
        denom_origen:    optOrigen.dataset.denom,
        almacen_destino: codigoDestino,
        denom_destino:   optDestino.dataset.denom
      })
    });

    const resp = await res.json();

    if (resp.success === false) {
      mostrarToast(resp.message || "Error al crear transferencia.", "error");
      return;
    }

    transferenciasGuardadas.unshift({
      id:                resp.id,
      almacen_origen:    codigoOrigen,
      denom_origen:      optOrigen.dataset.denom,
      almacen_destino:   codigoDestino,
      denom_destino:     optDestino.dataset.denom,
      usuario:           resp.usuario,
      hora_creacion:     resp.hora_creacion,
      hora_confirmacion: null,
      vale:              null
    });

    seleccionPorId.set(resp.id, new Map());
    guardarSeleccion();
    renderizarTabla();
    mostrarToast("Transferencia creada.", "success");

  } catch (e) {
    mostrarToast("Error al crear transferencia.", "error");
  } finally {
    btnAgregar.disabled = false;
  }
}

/* ─── DESCARTAR TRANSFERENCIA ───────────────────── */
function descartarTransferencia(id) {
  mostrarConfirmacion("¿Descartar esta transferencia?", async () => {
    try {
      const res = await fetch("../api/transferencia/delete_transferencia.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ id })
      });

      const resp = await res.json();

      if (resp.success === false) {
        mostrarToast(resp.message || "Error al eliminar transferencia.", "error");
        return;
      }

      transferenciasGuardadas = transferenciasGuardadas.filter(t => t.id !== id);
      seleccionPorId.delete(id);
      guardarSeleccion();
      renderizarTabla();
      mostrarToast("Transferencia descartada.", "success");

    } catch (e) {
      mostrarToast("Error al eliminar transferencia.", "error");
    }
  });
}

/* ─── CONFIRMAR TRANSFERENCIA ───────────────────── */
function confirmarTransferencia(id) {
  const transferencia = transferenciasGuardadas.find(t => t.id === id);
  if (!transferencia) return;

  const seleccion = seleccionPorId.get(id);
  if (!seleccion || seleccion.size === 0) {
    mostrarToast("Debe agregar al menos un artículo antes de confirmar.", "error");
    return;
  }

  mostrarConfirmacion("¿Confirmar esta transferencia? Se actualizará el stock de ambos almacenes.", async () => {
    const fila = document.getElementById(`trf-fila-${id}`);
    if (fila) fila.querySelectorAll("button").forEach(b => b.disabled = true);

    // Construir array de artículos para el endpoint
    const articulos = [];
    seleccion.forEach(({ articulo, cantidad }) => {
      articulos.push({ codigo: articulo.codigo_articulo, cantidad });
    });

    try {
      const res = await fetch("../api/transferencia/confirmar_transferencia.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ id, articulos })
      });

      const resp = await res.json();

      if (resp.success === false) {
        mostrarToast(resp.message || "Error al confirmar transferencia.", "error");
        if (fila) fila.querySelectorAll("button").forEach(b => b.disabled = false);
        return;
      }

      transferencia.vale              = resp.vale;
      transferencia.hora_confirmacion = resp.hora_confirmacion;

      seleccionPorId.delete(id);
      guardarSeleccion();

      renderizarTabla();
      mostrarToast(`Transferencia confirmada. Vale #${resp.vale} asignado.`, "success");

    } catch (e) {
      mostrarToast("Error al confirmar transferencia.", "error");
      if (fila) fila.querySelectorAll("button").forEach(b => b.disabled = false);
    }
  });
}

/* ─── ABRIR PREVIEW ─────────────────────────────── */
function abrirPreview(id) {
  const transferencia = transferenciasGuardadas.find(t => t.id === id);
  if (!transferencia) return;

  const vale = transferencia.vale !== null
    ? transferencia.vale
    : Math.max(...transferenciasGuardadas.map(t => t.vale || 0), 0) + 1;

  const seleccion = seleccionPorId.get(id) || new Map();
  const articulos = [];
  seleccion.forEach(({ articulo, cantidad }) => {
    articulos.push({
      codigo:      articulo.codigo_articulo,
      descripcion: articulo.desc_articulo,
      cantidad
    });
  });

  sessionStorage.setItem("preview_transferencia", JSON.stringify({
    id,
    almacen_origen:    transferencia.almacen_origen,
    denom_origen:      transferencia.denom_origen,
    almacen_destino:   transferencia.almacen_destino,
    denom_destino:     transferencia.denom_destino,
    usuario:           transferencia.usuario,
    hora_creacion:     transferencia.hora_creacion,
    hora_confirmacion: transferencia.hora_confirmacion,
    vale,
    articulos
  }));

  window.location.href = "preview_transferencia.html";
}

/* ─── ABRIR MODAL DE ARTÍCULOS ──────────────────── */
async function abrirModalArticulos(id) {
  filaActiva = id;

  const transferencia = transferenciasGuardadas.find(t => t.id === id);
  if (!transferencia) return;

  // Cargar artículos del almacén origen con stock > 0
  try {
    const res  = await fetch(`../api/transferencia/get_articulos_almacen.php?almacen=${transferencia.almacen_origen}`);
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando artículos del almacén.", "error");
      return;
    }

    articulosOrigenCargados = data;

  } catch (e) {
    mostrarToast("Error cargando artículos del almacén.", "error");
    return;
  }

  if (articulosOrigenCargados.length === 0) {
    mostrarToast("El almacén origen no tiene artículos con stock disponible.", "error");
    return;
  }

  // Restaurar selección temporal desde el Map guardado
  seleccionTemporal = new Map();
  const seleccionGuardada = seleccionPorId.get(id);
  if (seleccionGuardada) {
    seleccionGuardada.forEach((val, key) => seleccionTemporal.set(key, val.cantidad));
  }

  // Subtítulo del modal
  document.getElementById("trf-modal-subtitulo").textContent =
    `Origen: ${transferencia.denom_origen} → Destino: ${transferencia.denom_destino}`;

  document.getElementById("trf-filtro-articulos").value = "";
  renderizarTablaArticulos(articulosOrigenCargados);
  document.getElementById("trf-modalArticulos").classList.remove("hidden");

  // Re-enlazar botón confirmar para evitar listeners duplicados
  const btnConf   = document.getElementById("trf-modal-confirmar");
  const nuevoConf = btnConf.cloneNode(true);
  btnConf.parentNode.replaceChild(nuevoConf, btnConf);
  nuevoConf.addEventListener("click", confirmarSeleccionArticulos);

  document.getElementById("trf-modal-cancelar").onclick = () => {
    document.getElementById("trf-modalArticulos").classList.add("hidden");
  };
}

/* ─── RENDERIZAR TABLA DE ARTÍCULOS EN MODAL ─────── */
function renderizarTablaArticulos(lista) {
  const tbody = document.getElementById("trf-tbody-articulos");
  tbody.innerHTML = "";

  if (lista.length === 0) {
    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">Sin resultados</td></tr>`;
    return;
  }

  lista.forEach(art => {
    const clave    = String(art.codigo_articulo);
    const yaEsta   = seleccionTemporal.has(clave);
    const cantidad = yaEsta ? seleccionTemporal.get(clave) : 1;
    const stock    = parseInt(art.stock) || 0;

    const tr = document.createElement("tr");
    tr.dataset.descripcion = art.desc_articulo.toLowerCase();

    tr.innerHTML = `
      <td style="text-align:center;">
        <input type="checkbox" class="trf-chk-articulo" data-codigo="${clave}" ${yaEsta ? "checked" : ""}>
      </td>
      <td>${escapeHtml(art.desc_articulo)}</td>
      <td style="text-align:center; font-weight:600; color:#27ae60;">${stock}</td>
      <td>
        <input type="number" class="trf-cantidad-articulo" data-codigo="${clave}"
          value="${cantidad}" min="1" max="${stock}"
          style="width:70px; padding:4px 6px; border-radius:6px; border:1px solid #ccc;"
          ${yaEsta ? "" : "disabled"}>
      </td>
    `;

    const chk = tr.querySelector(".trf-chk-articulo");
    const inp = tr.querySelector(".trf-cantidad-articulo");

    chk.addEventListener("change", () => {
      inp.disabled = !chk.checked;
      if (chk.checked) {
        seleccionTemporal.set(clave, parseInt(inp.value) || 1);
        inp.focus();
      } else {
        seleccionTemporal.delete(clave);
      }
    });

    inp.addEventListener("input", () => {
      if (inp.disabled) return;
      let val = parseInt(inp.value) || 1;
      if (val > stock) { val = stock; inp.value = stock; }
      if (val < 1)     { val = 1;     inp.value = 1; }
      seleccionTemporal.set(clave, val);
    });

    tbody.appendChild(tr);
  });
}

/* ─── FILTRO ARTÍCULOS CON DEBOUNCE ─────────────── */
function debounceFiltro() {
  clearTimeout(_timerFiltro);
  _timerFiltro = setTimeout(() => {
    const q     = document.getElementById("trf-filtro-articulos").value.trim().toLowerCase();
    const filas = document.querySelectorAll("#trf-tbody-articulos tr[data-descripcion]");
    filas.forEach(tr => {
      tr.style.display = (q === "" || tr.dataset.descripcion.includes(q)) ? "" : "none";
    });
  }, 250);
}

/* ─── LIMPIAR SELECCIÓN DEL MODAL ───────────────── */
function limpiarSeleccionModal() {
  seleccionTemporal.clear();
  document.querySelectorAll("#trf-tbody-articulos tr[data-descripcion]").forEach(tr => {
    const chk = tr.querySelector(".trf-chk-articulo");
    const inp = tr.querySelector(".trf-cantidad-articulo");
    if (chk) chk.checked    = false;
    if (inp) { inp.disabled = true; inp.value = 1; }
  });
  mostrarToast("Selección limpiada.", "success");
}

/* ─── CONFIRMAR SELECCIÓN DEL MODAL ─────────────── */
function confirmarSeleccionArticulos() {
  if (!filaActiva) return;

  for (const [codigo, cantidad] of seleccionTemporal) {
    if (!cantidad || cantidad < 1) {
      mostrarToast("La cantidad debe ser mayor a 0.", "error");
      return;
    }
    // Validar contra stock disponible
    const art = articulosOrigenCargados.find(a => String(a.codigo_articulo) === codigo);
    if (art && cantidad > parseInt(art.stock)) {
      mostrarToast(`Cantidad supera el stock disponible para "${art.desc_articulo}" (máx. ${art.stock}).`, "error");
      return;
    }
  }

  const seleccion = seleccionPorId.get(filaActiva);
  seleccion.clear();
  seleccionTemporal.forEach((cantidad, clave) => {
    const articulo = articulosOrigenCargados.find(a => String(a.codigo_articulo) === clave);
    if (articulo) seleccion.set(clave, { articulo, cantidad });
  });

  guardarSeleccion();
  document.getElementById("trf-modalArticulos").classList.add("hidden");

  const total = seleccion.size;
  mostrarToast(
    total > 0 ? `${total} artículo(s) seleccionado(s).` : "No se seleccionó ningún artículo.",
    total > 0 ? "success" : "error"
  );
}

/* ─── HELPERS ───────────────────────────────────── */
function formatearFecha(isoString) {
  if (!isoString) return "—";
  const d   = new Date(isoString);
  const pad = n => String(n).padStart(2, "0");
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function escapeHtml(t) {
  return String(t)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}