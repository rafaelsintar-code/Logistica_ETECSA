document.addEventListener("DOMContentLoaded", () => {
  cargarAlmacenesSelect();
  cargarSolicitudes();

  document.getElementById("sol-select-almacen")
    .addEventListener("change", () => {
      const val = document.getElementById("sol-select-almacen").value;
      document.getElementById("sol-btnAgregar").disabled = val === "";
    });

  document.getElementById("sol-btnAgregar")
    .addEventListener("click", agregarSolicitud);

  document.getElementById("sol-filtro-periodo")
    .addEventListener("change", () => renderizarTabla());

  document.getElementById("sol-filtro-articulos")
    .addEventListener("input", debounceFiltro);

  document.getElementById("sol-btn-limpiar")
    .addEventListener("click", limpiarSeleccionModal);
});

/* ─── ESTADO GLOBAL ─────────────────────────────── */
let articulosCargados   = [];
let solicitudesCargadas = [];
const seleccionPorId    = new Map();
let filaActiva          = null;
let seleccionTemporal   = new Map();
let _timerFiltro        = null;

/* ─── PERSISTENCIA sessionStorage ──────────────────
   seleccionPorId: Map<id, Map<codigo, {articulo, cantidad}>>
   No es serializable directo — convertimos a array anidado.
─────────────────────────────────────────────────── */
function guardarSeleccion() {
  const serializable = [...seleccionPorId.entries()].map(([id, mapa]) => [
    id,
    [...mapa.entries()].map(([codigo, val]) => [codigo, {
      cantidad: val.cantidad,
      articulo: {
        codigo_articulo: val.articulo.codigo_articulo,
        descripcion:     val.articulo.descripcion,
        familia:         val.articulo.familia ?? ''
      }
    }])
  ]);
  sessionStorage.setItem("sol_seleccion", JSON.stringify(serializable));
}

function restaurarSeleccion() {
  const raw = sessionStorage.getItem("sol_seleccion");
  if (!raw) return;
  try {
    const parsed = JSON.parse(raw);
    parsed.forEach(([id, entradas]) => {
      seleccionPorId.set(id, new Map(
        entradas.map(([codigo, val]) => [codigo, val])
      ));
    });
  } catch (e) {
    sessionStorage.removeItem("sol_seleccion");
  }
}

/* ─── CARGAR SELECT DE ALMACENES ────────────────── */
async function cargarAlmacenesSelect() {
  try {
    const res  = await fetch("../api/solicitud/get_almacenes_solicitud.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando almacenes.", "error");
      return;
    }

    const select = document.getElementById("sol-select-almacen");
    data.forEach(a => {
      const opt          = document.createElement("option");
      opt.value          = a.codigo_almacen;
      opt.textContent    = `${a.codigo_almacen} — ${a.tipo_almacen} — ${a.nombre_pv}`;
      opt.dataset.tipo   = a.tipo_almacen;
      opt.dataset.nombre = a.nombre_pv;
      select.appendChild(opt);
    });

  } catch (e) {
    mostrarToast("Error cargando almacenes.", "error");
  }
}

/* ─── CARGAR SOLICITUDES DESDE BD ───────────────── */
async function cargarSolicitudes() {

  // Limpiar expiradas
  try {
    const resLimpiar  = await fetch("../api/solicitud/limpiar_solicitudes.php", { method: "POST" });
    const dataLimpiar = await resLimpiar.json();
    if (dataLimpiar.success && dataLimpiar.eliminadas.length > 0) {
      // Limpiar del sessionStorage las selecciones de solicitudes eliminadas
      dataLimpiar.eliminadas.forEach(id => seleccionPorId.delete(id));
      guardarSeleccion();
      mostrarToast(`${dataLimpiar.eliminadas.length} solicitud(es) expirada(s) eliminada(s).`, "info");
    }
  } catch (e) {}

  // Cargar solicitudes vigentes
  try {
    const res  = await fetch("../api/solicitud/get_solicitudes.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando solicitudes.", "error");
      return;
    }

    solicitudesCargadas = data;

    // Restaurar selecciones guardadas
    restaurarSeleccion();

    // Inicializar Map para las que no tienen selección guardada
    data.forEach(s => {
      if (!seleccionPorId.has(s.id)) {
        seleccionPorId.set(s.id, new Map());
      }
    });

    // Limpiar selecciones de ids que ya no existen en BD
    const idsActuales = new Set(data.map(s => s.id));
    [...seleccionPorId.keys()].forEach(id => {
      if (!idsActuales.has(id)) seleccionPorId.delete(id);
    });

    renderizarTabla();

  } catch (e) {
    mostrarToast("Error cargando solicitudes.", "error");
  }
}

/* ─── FILTRO POR PERÍODO ────────────────────────── */
function filtrarPorPeriodo(lista) {
  const periodo = document.getElementById("sol-filtro-periodo").value;
  const ahora   = new Date();

  return lista.filter(s => {
    const fecha = new Date(s.hora_creacion);
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
  const tbody     = document.getElementById("sol-tbody");
  const filtradas = filtrarPorPeriodo(solicitudesCargadas);

  tbody.innerHTML = "";

  if (filtradas.length === 0) {
    tbody.innerHTML = `
      <tr id="sol-empty-row">
        <td colspan="9" style="text-align:center;">Sin solicitudes para este período</td>
      </tr>`;
    return;
  }

  filtradas.forEach(s => {
    const confirmada  = s.hora_confirmacion !== null;
    const fechaCreada = formatearFecha(s.hora_creacion);
    const fechaConf   = confirmada ? formatearFecha(s.hora_confirmacion) : null;

    const tr = document.createElement("tr");
    tr.id    = `sol-fila-${s.id}`;

    tr.innerHTML = `
      <td>${escapeHtml(String(s.codigo_almacen))}</td>
      <td>${escapeHtml(s.tipo_almacen)}</td>
      <td>${escapeHtml(s.nombre_pv)}</td>
      <td>${escapeHtml(s.usuario)}</td>
      <td>${fechaCreada}</td>
      <td style="text-align:center; font-weight:600;
          color:${confirmada ? "#27ae60" : "#888"};
          font-style:${confirmada ? "normal" : "italic"};">
        ${confirmada ? `Vale #${s.vale}` : "Pendiente"}
      </td>
      <td style="text-align:center;">
        ${confirmada
          ? `<span style="color:#27ae60; font-weight:600;">${fechaConf}</span>`
          : `<button class="sol-btn sol-btn-confirmar" onclick="confirmarSolicitud(${s.id})">
               <span class="sol-btn-icon">✓</span> Confirmar
             </button>`
        }
      </td>
      <td style="text-align:center;">
        ${!confirmada
          ? `<button class="sol-btn sol-btn-agregar" onclick="abrirModalArticulos(${s.id})">
               <span class="sol-btn-icon">＋</span> Artículos
             </button>`
          : `—`
        }
      </td>
      <td style="text-align:center; white-space:nowrap;">
        ${!confirmada
          ? `<button class="sol-btn sol-btn-doc" onclick="abrirPreview(${s.id})" title="Ver documento">
               &#128196;
             </button>
             <button class="sol-btn sol-btn-descartar" title="Descartar" onclick="descartarSolicitud(${s.id})">
               ✕
             </button>`
          : ``
        }
      </td>
    `;

    tbody.appendChild(tr);
  });
}

/* ─── AGREGAR NUEVA SOLICITUD ───────────────────── */
async function agregarSolicitud() {
  const select = document.getElementById("sol-select-almacen");
  const codigo = select.value;
  if (!codigo) return;

  const opt        = select.options[select.selectedIndex];
  const btnAgregar = document.getElementById("sol-btnAgregar");
  btnAgregar.disabled = true;

  try {
    const res = await fetch("../api/solicitud/add_solicitud.php", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({
        codigo_almacen: codigo,
        tipo_almacen:   opt.dataset.tipo,
        nombre_pv:      opt.dataset.nombre
      })
    });

    const resp = await res.json();

    if (resp.success === false) {
      mostrarToast(resp.message || "Error al crear solicitud.", "error");
      return;
    }

    solicitudesCargadas.unshift({
      id:                resp.id,
      codigo_almacen:    codigo,
      tipo_almacen:      opt.dataset.tipo,
      nombre_pv:         opt.dataset.nombre,
      usuario:           resp.usuario,
      hora_creacion:     resp.hora_creacion,
      hora_confirmacion: null,
      vale:              null
    });

    seleccionPorId.set(resp.id, new Map());
    guardarSeleccion();
    renderizarTabla();
    mostrarToast("Solicitud creada.", "success");

  } catch (e) {
    mostrarToast("Error al crear solicitud.", "error");
  } finally {
    btnAgregar.disabled = false;
  }
}

/* ─── DESCARTAR SOLICITUD ───────────────────────── */
function descartarSolicitud(id) {
  mostrarConfirmacion("¿Descartar esta solicitud?", async () => {
    try {
      const res = await fetch("../api/solicitud/delete_solicitud.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ id })
      });

      const resp = await res.json();

      if (resp.success === false) {
        mostrarToast(resp.message || "Error al eliminar solicitud.", "error");
        return;
      }

      solicitudesCargadas = solicitudesCargadas.filter(s => s.id !== id);
      seleccionPorId.delete(id);
      guardarSeleccion();
      renderizarTabla();
      mostrarToast("Solicitud descartada.", "success");

    } catch (e) {
      mostrarToast("Error al eliminar solicitud.", "error");
    }
  });
}

/* ─── CONFIRMAR SOLICITUD ───────────────────────── */
function confirmarSolicitud(id) {
  const solicitud = solicitudesCargadas.find(s => s.id === id);
  if (!solicitud) return;

  const seleccion = seleccionPorId.get(id);
  if (!seleccion || seleccion.size === 0) {
    mostrarToast("Debe agregar al menos un artículo antes de confirmar.", "error");
    return;
  }

  mostrarConfirmacion("¿Confirmar esta solicitud?", async () => {
    const fila = document.getElementById(`sol-fila-${id}`);
    if (fila) fila.querySelectorAll("button").forEach(b => b.disabled = true);

    try {
      const res = await fetch("../api/solicitud/confirmar_solicitud.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ id })
      });

      const resp = await res.json();

      if (resp.success === false) {
        mostrarToast(resp.message || "Error al confirmar solicitud.", "error");
        if (fila) fila.querySelectorAll("button").forEach(b => b.disabled = false);
        return;
      }

      solicitud.vale              = resp.vale;
      solicitud.hora_confirmacion = resp.hora_confirmacion;

      // Las confirmadas ya no necesitan selección en memoria
      seleccionPorId.delete(id);
      guardarSeleccion();

      renderizarTabla();
      mostrarToast(`Solicitud confirmada. Vale #${resp.vale} asignado.`, "success");

    } catch (e) {
      mostrarToast("Error al confirmar solicitud.", "error");
      if (fila) fila.querySelectorAll("button").forEach(b => b.disabled = false);
    }
  });
}

/* ─── ABRIR PREVIEW ─────────────────────────────── */
function abrirPreview(id) {
  const solicitud = solicitudesCargadas.find(s => s.id === id);
  if (!solicitud) return;

  const vale = solicitud.vale !== null
    ? solicitud.vale
    : Math.max(...solicitudesCargadas.map(s => s.vale || 0), 0) + 1;

  const seleccion = seleccionPorId.get(id) || new Map();
  const articulos = [];
  seleccion.forEach(({ articulo, cantidad }) => {
    articulos.push({
      codigo:      articulo.codigo_articulo,
      descripcion: articulo.descripcion,
      cantidad
    });
  });

  sessionStorage.setItem("preview_solicitud", JSON.stringify({
    id,
    codigo_almacen:    solicitud.codigo_almacen,
    tipo_almacen:      solicitud.tipo_almacen,
    nombre_pv:         solicitud.nombre_pv,
    usuario:           solicitud.usuario,
    hora_creacion:     solicitud.hora_creacion,
    hora_confirmacion: solicitud.hora_confirmacion,
    vale,
    articulos
  }));

  window.location.href = "preview_solicitud.html";
}

/* ─── CARGAR ARTÍCULOS (una sola vez) ──────────── */
async function cargarArticulos() {
  if (articulosCargados.length > 0) return;
  try {
    const res  = await fetch("../api/articulo/get_articulos.php");
    const data = await res.json();
    if (!Array.isArray(data)) {
      mostrarToast("Error cargando artículos.", "error");
      return;
    }
    articulosCargados = data;
  } catch (e) {
    mostrarToast("Error cargando artículos.", "error");
  }
}

/* ─── ABRIR MODAL DE ARTÍCULOS ──────────────────── */
async function abrirModalArticulos(id) {
  filaActiva = id;
  await cargarArticulos();

  if (articulosCargados.length === 0) {
    mostrarToast("No hay artículos disponibles.", "error");
    return;
  }

  seleccionTemporal = new Map();
  const seleccion   = seleccionPorId.get(id);
  if (seleccion) {
    seleccion.forEach((val, key) => seleccionTemporal.set(key, val.cantidad));
  }

  document.getElementById("sol-filtro-articulos").value = "";
  renderizarTablaArticulos(articulosCargados);
  document.getElementById("sol-modalArticulos").classList.remove("hidden");

  const btnConf   = document.getElementById("sol-modal-confirmar");
  const nuevoConf = btnConf.cloneNode(true);
  btnConf.parentNode.replaceChild(nuevoConf, btnConf);
  nuevoConf.addEventListener("click", confirmarSeleccionArticulos);

  document.getElementById("sol-modal-cancelar").onclick = () => {
    document.getElementById("sol-modalArticulos").classList.add("hidden");
  };
}

/* ─── RENDERIZAR TABLA DE ARTÍCULOS ─────────────── */
function renderizarTablaArticulos(lista) {
  const tbody = document.getElementById("sol-tbody-articulos");
  tbody.innerHTML = "";

  if (lista.length === 0) {
    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">Sin resultados</td></tr>`;
    return;
  }

  lista.forEach(art => {
    const clave    = String(art.codigo_articulo);
    const yaEsta   = seleccionTemporal.has(clave);
    const cantidad = yaEsta ? seleccionTemporal.get(clave) : 1;

    const tr = document.createElement("tr");
    tr.dataset.descripcion = art.descripcion.toLowerCase();

    tr.innerHTML = `
      <td style="text-align:center;">
        <input type="checkbox" class="sol-chk-articulo" data-codigo="${clave}" ${yaEsta ? "checked" : ""}>
      </td>
      <td>${escapeHtml(art.descripcion)}</td>
      <td>${escapeHtml(art.familia ?? "")}</td>
      <td>
        <input type="number" class="sol-cantidad-articulo" data-codigo="${clave}"
          value="${cantidad}" min="1"
          style="width:65px; padding:4px 6px; border-radius:6px; border:1px solid #ccc;"
          ${yaEsta ? "" : "disabled"}>
      </td>
    `;

    const chk = tr.querySelector(".sol-chk-articulo");
    const inp = tr.querySelector(".sol-cantidad-articulo");

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
      if (!inp.disabled) seleccionTemporal.set(clave, parseInt(inp.value) || 1);
    });

    tbody.appendChild(tr);
  });
}

/* ─── FILTRO ARTÍCULOS CON DEBOUNCE ─────────────── */
function debounceFiltro() {
  clearTimeout(_timerFiltro);
  _timerFiltro = setTimeout(() => {
    const q     = document.getElementById("sol-filtro-articulos").value.trim().toLowerCase();
    const filas = document.querySelectorAll("#sol-tbody-articulos tr[data-descripcion]");
    filas.forEach(tr => {
      tr.style.display = (q === "" || tr.dataset.descripcion.includes(q)) ? "" : "none";
    });
  }, 250);
}

/* ─── LIMPIAR SELECCIÓN DEL MODAL ───────────────── */
function limpiarSeleccionModal() {
  seleccionTemporal.clear();
  document.querySelectorAll("#sol-tbody-articulos tr[data-descripcion]").forEach(tr => {
    const chk = tr.querySelector(".sol-chk-articulo");
    const inp = tr.querySelector(".sol-cantidad-articulo");
    if (chk) chk.checked    = false;
    if (inp) { inp.disabled = true; inp.value = 1; }
  });
  mostrarToast("Selección limpiada.", "success");
}

/* ─── CONFIRMAR SELECCIÓN DEL MODAL ─────────────── */
function confirmarSeleccionArticulos() {
  if (!filaActiva) return;

  for (const [, cantidad] of seleccionTemporal) {
    if (!cantidad || cantidad < 1) {
      mostrarToast("La cantidad debe ser mayor a 0.", "error");
      return;
    }
  }

  const seleccion = seleccionPorId.get(filaActiva);
  seleccion.clear();
  seleccionTemporal.forEach((cantidad, clave) => {
    const articulo = articulosCargados.find(a => String(a.codigo_articulo) === clave);
    if (articulo) seleccion.set(clave, { articulo, cantidad });
  });

  // Persistir inmediatamente tras confirmar
  guardarSeleccion();

  document.getElementById("sol-modalArticulos").classList.add("hidden");

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

function escaparJs(t) {
  return String(t).replace(/'/g, "\\'");
}