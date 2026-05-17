// js/visitante/articulos.js — solo lectura
document.addEventListener("DOMContentLoaded", () => {
  cargarArticulos();

  // Filtro con debounce
  let _timer = null;
  document.getElementById("art-filtro").addEventListener("input", () => {
    clearTimeout(_timer);
    _timer = setTimeout(() => {
      paginaActual = 1;
      renderizarTabla();
    }, 250);
  });
});

/* ─── ESTADO GLOBAL ─────────────────────────────── */
let articulosCargados = [];
let paginaActual      = 1;
const POR_PAGINA      = 50;

/* ─── CARGAR ────────────────────────────────────── */
async function cargarArticulos() {
  try {
    const res  = await fetch("../../api/articulo/get_articulos.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando artículos.", "error");
      return;
    }

    articulosCargados = data;
    paginaActual      = 1;
    renderizarTabla();

  } catch (e) {
    mostrarToast("Error cargando artículos.", "error");
  }
}

/* ─── FILTRAR ───────────────────────────────────── */
function articulosFiltrados() {
  const q = document.getElementById("art-filtro").value.trim().toLowerCase();
  if (!q) return articulosCargados;

  return articulosCargados.filter(a =>
    a.descripcion.toLowerCase().includes(q)       ||
    String(a.codigo_articulo).includes(q)          ||
    (a.familia     ?? "").toLowerCase().includes(q) ||
    (a.codigo_sigc ?? "").toString().includes(q)
  );
}

/* ─── RENDERIZAR TABLA (sin columna Acción) ─────── */
function renderizarTabla() {
  const filtrados = articulosFiltrados();
  const totalPags = Math.max(1, Math.ceil(filtrados.length / POR_PAGINA));
  if (paginaActual > totalPags) paginaActual = totalPags;

  const inicio = (paginaActual - 1) * POR_PAGINA;
  const pagina = filtrados.slice(inicio, inicio + POR_PAGINA);

  document.getElementById("art-pag-info").textContent =
    filtrados.length === 0
      ? "Sin resultados"
      : `Mostrando ${inicio + 1}–${Math.min(inicio + POR_PAGINA, filtrados.length)} de ${filtrados.length} artículo(s)`;

  const tbody = document.getElementById("art-tbody");
  tbody.innerHTML = "";

  if (pagina.length === 0) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Sin artículos</td></tr>`;
  } else {
    pagina.forEach(a => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(String(a.codigo_articulo))}</td>
        <td>${escapeHtml(a.codigo_sigc ?? "")}</td>
        <td>${escapeHtml(a.descripcion)}</td>
        <td>${escapeHtml(a.familia)}</td>
        <td>${escapeHtml(a.precio_usd ?? "")}</td>
        <td>${escapeHtml(a.precio_cup ?? "")}</td>
        <td>${escapeHtml(a.acta_precio)}</td>
        <td>${escapeHtml(a.garantia)}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  renderizarPaginacion(totalPags);
}

/* ─── PAGINACIÓN ─────────────────────────────────── */
function renderizarPaginacion(totalPags) {
  const cont = document.getElementById("art-paginacion");
  cont.innerHTML = "";
  if (totalPags <= 1) return;

  const crearBtn = (label, pagina, deshabilitado = false, activo = false) => {
    const btn       = document.createElement("button");
    btn.textContent = label;
    btn.className   = "art-pag-btn" + (activo ? " art-pag-btn-activo" : "");
    btn.disabled    = deshabilitado;
    btn.addEventListener("click", () => {
      paginaActual = pagina;
      renderizarTabla();
      document.getElementById("art-table").scrollIntoView({ behavior: "smooth", block: "start" });
    });
    return btn;
  };

  const rango = 2;
  const desde = Math.max(1, paginaActual - rango);
  const hasta  = Math.min(totalPags, paginaActual + rango);

  cont.appendChild(crearBtn("«", 1, paginaActual === 1));
  cont.appendChild(crearBtn("‹", paginaActual - 1, paginaActual === 1));

  if (desde > 1) cont.appendChild(crearBtn("...", desde - 1, true));

  for (let p = desde; p <= hasta; p++) {
    cont.appendChild(crearBtn(p, p, false, p === paginaActual));
  }

  if (hasta < totalPags) cont.appendChild(crearBtn("...", hasta + 1, true));

  cont.appendChild(crearBtn("›", paginaActual + 1, paginaActual === totalPags));
  cont.appendChild(crearBtn("»", totalPags, paginaActual === totalPags));
}

/* ─── HELPERS ────────────────────────────────────── */
function escapeHtml(t) {
  return String(t)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}
