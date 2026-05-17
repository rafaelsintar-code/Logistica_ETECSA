document.addEventListener("DOMContentLoaded", () => {
  cargarArticulos();

  document.getElementById("art-btnAdd").addEventListener("click", abrirModalAgregar);
  document.getElementById("art-add-cancel").addEventListener("click", () => {
    document.getElementById("art-addModal").classList.add("hidden");
  });
  document.getElementById("art-add-save").addEventListener("click", agregarArticulo);

  document.getElementById("art-btnImportar").addEventListener("click", () => {
    document.getElementById("art-fileExcel").click();
  });
  document.getElementById("art-fileExcel").addEventListener("change", importarArticulosExcel);

  // Filtro con debounce
  let _timer = null;
  document.getElementById("art-filtro").addEventListener("input", () => {
    clearTimeout(_timer);
    _timer = setTimeout(() => {
      paginaActual = 1;
      renderizarTabla();
    }, 250);
  });

  // Restricciones de tipo en inputs numéricos
  aplicarRestriccionNumerica("art-codigo");
  aplicarRestriccionNumerica("art-sigc");
  aplicarRestriccionNumerica("art-edit-sigc");
});

/* ─── ESTADO GLOBAL ─────────────────────────────── */
let articulosCargados = [];
let paginaActual      = 1;
const POR_PAGINA      = 50;

/* ─── RESTRICCIÓN NUMÉRICA ──────────────────────── */
// Bloquea cualquier carácter que no sea dígito en el input
function aplicarRestriccionNumerica(id) {
  const el = document.getElementById(id);
  if (!el) return;

  el.addEventListener("input", () => {
    el.value = el.value.replace(/\D/g, "");
  });

  el.addEventListener("keydown", e => {
    const permitidas = ["Backspace", "Delete", "ArrowLeft", "ArrowRight", "Tab"];
    if (!permitidas.includes(e.key) && !(e.key >= "0" && e.key <= "9")) {
      e.preventDefault();
    }
  });

  el.addEventListener("wheel", e => e.preventDefault());
  el.addEventListener("paste", e => {
    e.preventDefault();
    const texto = (e.clipboardData || window.clipboardData).getData("text");
    const soloDigitos = texto.replace(/\D/g, "");
    const max = parseInt(el.maxLength) || 999;
    el.value = (el.value + soloDigitos).slice(0, max);
  });
}

/* ─── VALIDAR CAMPOS ────────────────────────────── */
function validarArticulo(payload, esEdicion = false) {
  if (!esEdicion) {
    if (!payload.codigo_articulo) {
      mostrarToast("El código de artículo es obligatorio.", "error");
      return false;
    }
    if (!/^\d{10}$/.test(String(payload.codigo_articulo))) {
      mostrarToast("El código de artículo debe tener exactamente 10 dígitos.", "error");
      return false;
    }
  }

  if (payload.codigo_sigc !== null && payload.codigo_sigc !== '') {
    if (!/^\d{1,4}$/.test(String(payload.codigo_sigc))) {
      mostrarToast("El código SIGC debe ser numérico y tener máximo 4 dígitos.", "error");
      return false;
    }
  }

  if (!payload.descripcion) {
    mostrarToast("La descripción es obligatoria.", "error");
    return false;
  }
  if (payload.descripcion.length > 255) {
    mostrarToast("La descripción no puede superar 255 caracteres.", "error");
    return false;
  }

  if (!payload.familia) {
    mostrarToast("La familia es obligatoria.", "error");
    return false;
  }
  if (payload.familia.length > 100) {
    mostrarToast("La familia no puede superar 100 caracteres.", "error");
    return false;
  }

  if (payload.precio_usd && payload.precio_usd.length > 50) {
    mostrarToast("El precio USD no puede superar 50 caracteres.", "error");
    return false;
  }

  if (payload.precio_cup && payload.precio_cup.length > 50) {
    mostrarToast("El precio CUP no puede superar 50 caracteres.", "error");
    return false;
  }

  if (!payload.acta_precio) {
    mostrarToast("El acta de precio es obligatoria.", "error");
    return false;
  }
  if (payload.acta_precio.length > 100) {
    mostrarToast("El acta de precio no puede superar 100 caracteres.", "error");
    return false;
  }

  if (!payload.garantia) {
    mostrarToast("La garantía es obligatoria.", "error");
    return false;
  }
  if (payload.garantia.length > 100) {
    mostrarToast("La garantía no puede superar 100 caracteres.", "error");
    return false;
  }

  return true;
}

/* ─── CARGAR ────────────────────────────────────── */
async function cargarArticulos() {
  try {
    const res  = await fetch("../api/articulo/get_articulos.php");
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
    a.descripcion.toLowerCase().includes(q)     ||
    String(a.codigo_articulo).includes(q)        ||
    (a.familia    ?? '').toLowerCase().includes(q) ||
    (a.codigo_sigc ?? '').toString().includes(q)
  );
}

/* ─── RENDERIZAR TABLA + PAGINACIÓN ─────────────── */
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
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;">Sin artículos</td></tr>`;
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
        <td>
          <button class="btn-editar" onclick="abrirEditarArticulo('${a.codigo_articulo}')">
            <img src="../img/icons/edit.svg" width="18">
          </button>
          <button class="btn-borrar" onclick="eliminarArticulo('${a.codigo_articulo}')">
            <img src="../img/icons/trash.svg" width="18">
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  renderizarPaginacion(totalPags);
}

/* ─── CONTROLES DE PAGINACIÓN ───────────────────── */
function renderizarPaginacion(totalPags) {
  const cont = document.getElementById("art-paginacion");
  cont.innerHTML = "";
  if (totalPags <= 1) return;

  const crearBtn = (label, pagina, deshabilitado = false, activo = false) => {
    const btn     = document.createElement("button");
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

/* ─── MODAL AGREGAR ─────────────────────────────── */
function abrirModalAgregar() {
  ["art-codigo", "art-sigc", "art-descripcion", "art-familia",
   "art-precio-usd", "art-precio-cup", "art-acta", "art-garantia"]
    .forEach(id => document.getElementById(id).value = "");

  document.getElementById("art-addModal").classList.remove("hidden");
}

/* ─── AGREGAR ───────────────────────────────────── */
function agregarArticulo() {
  const payload = {
    codigo_articulo: document.getElementById("art-codigo").value.trim(),
    codigo_sigc:     document.getElementById("art-sigc").value.trim() || null,
    descripcion:     document.getElementById("art-descripcion").value.trim(),
    familia:         document.getElementById("art-familia").value.trim(),
    precio_usd:      document.getElementById("art-precio-usd").value.trim() || null,
    precio_cup:      document.getElementById("art-precio-cup").value.trim() || null,
    acta_precio:     document.getElementById("art-acta").value.trim(),
    garantia:        document.getElementById("art-garantia").value.trim()
  };

  if (!validarArticulo(payload, false)) return;

  mostrarConfirmacion("¿Desea agregar este artículo?", async () => {
    document.getElementById("art-addModal").classList.add("hidden");
    try {
      const res  = await fetch("../api/articulo/add_articulo.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify(payload)
      });
      const resp = await res.json();

      if (resp.success !== true) {
        mostrarToast(resp.message || "No se pudo agregar el artículo.", "error");
        return;
      }

      mostrarToast("Artículo agregado correctamente.", "success");
      cargarArticulos();

    } catch (e) {
      mostrarToast("Error al agregar artículo.", "error");
    }
  });
}

/* ─── EDITAR ────────────────────────────────────── */
function abrirEditarArticulo(codigo) {
  const art = articulosCargados.find(a => String(a.codigo_articulo) === String(codigo));
  if (!art) return;

  document.getElementById("art-edit-sigc").value        = art.codigo_sigc ?? "";
  document.getElementById("art-edit-descripcion").value = art.descripcion;
  document.getElementById("art-edit-familia").value     = art.familia;
  document.getElementById("art-edit-precio-usd").value  = art.precio_usd ?? "";
  document.getElementById("art-edit-precio-cup").value  = art.precio_cup ?? "";
  document.getElementById("art-edit-acta").value        = art.acta_precio;
  document.getElementById("art-edit-garantia").value    = art.garantia;

  const modal     = document.getElementById("art-editModal");
  const btnSave   = document.getElementById("art-edit-save");
  const btnCancel = document.getElementById("art-edit-cancel");

  modal.classList.remove("hidden");

  const newSave = btnSave.cloneNode(true);
  btnSave.parentNode.replaceChild(newSave, btnSave);

  newSave.addEventListener("click", async () => {
    const payloadEdit = {
      codigo_sigc:  document.getElementById("art-edit-sigc").value.trim() || null,
      descripcion:  document.getElementById("art-edit-descripcion").value.trim(),
      familia:      document.getElementById("art-edit-familia").value.trim(),
      precio_usd:   document.getElementById("art-edit-precio-usd").value.trim() || null,
      precio_cup:   document.getElementById("art-edit-precio-cup").value.trim() || null,
      acta_precio:  document.getElementById("art-edit-acta").value.trim(),
      garantia:     document.getElementById("art-edit-garantia").value.trim()
    };

    if (!validarArticulo(payloadEdit, true)) return;

    try {
      const res  = await fetch("../api/articulo/update_articulo.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ codigo_articulo: codigo, ...payloadEdit })
      });
      const resp = await res.json();

      if (resp.success !== true) {
        mostrarToast(resp.message || "No se pudo actualizar el artículo.", "error");
        return;
      }

      mostrarToast("Artículo actualizado correctamente.", "success");
      modal.classList.add("hidden");
      cargarArticulos();

    } catch (e) {
      mostrarToast("Error comunicándose con el servidor.", "error");
    }
  });

  btnCancel.onclick = () => modal.classList.add("hidden");
}

/* ─── ELIMINAR ──────────────────────────────────── */
function eliminarArticulo(codigo) {
  mostrarConfirmacion("¿Eliminar este artículo?", async () => {
    try {
      const res  = await fetch("../api/articulo/delete_articulo.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ codigo_articulo: codigo })
      });
      const resp = await res.json();

      if (resp.success !== true) {
        mostrarToast(resp.message || "No se pudo eliminar el artículo.", "error");
        return;
      }

      mostrarToast("Artículo eliminado.", "success");
      cargarArticulos();

    } catch (e) {
      mostrarToast("Error al eliminar artículo.", "error");
    }
  });
}

/* ─── IMPORTAR EXCEL ────────────────────────────── */
function importarArticulosExcel(e) {
  const archivo = e.target.files[0];
  e.target.value = "";

  if (!archivo) return;

  if (!archivo.name.match(/\.(xls|xlsx)$/i)) {
    mostrarToast("El archivo debe ser un Excel válido.", "error");
    return;
  }

  const formData = new FormData();
  formData.append("excel", archivo);

  mostrarConfirmacion("¿Desea importar los artículos desde Excel?", async () => {
    try {
      const res  = await fetch("../api/articulo/importar_articulos_excel.php", {
        method: "POST",
        body:   formData
      });
      const resp = await res.json();

      if (resp.success !== true) {
        mostrarToast(resp.message || "No se importaron registros.", "error");
        return;
      }

      mostrarToast(resp.message || "Importación finalizada.", "success");
      cargarArticulos();

    } catch (e) {
      mostrarToast("Error procesando el Excel.", "error");
    }
  });
}

/* ─── HELPERS ───────────────────────────────────── */
function escapeHtml(text) {
  if (!text) return "";
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}