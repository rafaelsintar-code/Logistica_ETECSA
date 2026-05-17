document.addEventListener("DOMContentLoaded", () => {

  const fileInput    = document.getElementById("mb51-file");
  const fileNameSpan = document.getElementById("mb51-file-name");
  const importBtn    = document.getElementById("mb51-btn-import");
  const filtroInput  = document.getElementById("mb51-filtro");

  let datosMB51  = [];
  let paginaActual = 1;
  const POR_PAGINA = 50;

  // ==========================
  // SELECCIÓN DE ARCHIVO
  // ==========================
  fileInput.addEventListener("change", () => {
    if (fileInput.files.length > 0) {
      fileNameSpan.textContent = fileInput.files[0].name;
      importBtn.disabled = false;
    } else {
      fileNameSpan.textContent = "Ningún archivo seleccionado";
      importBtn.disabled = true;
    }
  });

  // ==========================
  // CLICK IMPORTAR
  // ==========================
  importBtn.addEventListener("click", () => {
    if (fileInput.files.length === 0) {
      mostrarToast("Seleccione un archivo primero", "error");
      return;
    }
    mostrarConfirmacion(
      "Esta acción eliminará los datos actuales de MB51 y cargará los nuevos. ¿Desea continuar?",
      importarMB51
    );
  });

  // ==========================
  // IMPORTACIÓN MB51
  // ==========================
  async function importarMB51() {
    const formData = new FormData();
    formData.append("excel", fileInput.files[0]);

    importBtn.disabled    = true;
    importBtn.textContent = "Importando...";

    try {
      const response = await fetch("../api/mb51/import_mb51_excel.php", {
        method: "POST",
        body:   formData
      });

      const result = await response.json();

      if (!response.ok || result.error) {
        throw new Error(result.error || "Error durante la importación");
      }

      const hayErrores = result.errores_excel || result.errores_nomenclador;

      mostrarToastMB51(`
        <strong>Importación finalizada</strong><br>
        Importados: ${result.importados}<br>
        Errores Excel: ${result.errores_excel}<br>
        Errores Nomenclador: ${result.errores_nomenclador}
        ${result.reporte_txt
          ? `<br><a href="#" id="mb51-descargar-reporte"
               style="color:#1d4ed8; font-weight:600; text-decoration:underline;">
               &#8659; Descargar reporte de errores
             </a>`
          : ''
        }
      `, hayErrores ? "info" : "success");

      if (result.reporte_txt) {
        setTimeout(() => {
          const enlace = document.getElementById("mb51-descargar-reporte");
          if (enlace) {
            enlace.addEventListener("click", e => {
              e.preventDefault();
              descargarTxt(result.reporte_txt, "reporte_errores_mb51.txt");
            });
          }
        }, 100);
      }

      fileInput.value          = "";
      fileNameSpan.textContent = "Ningún archivo seleccionado";
      importBtn.disabled       = true;
      filtroInput.value        = "";

      cargarTablaMB51();

    } catch (error) {
      mostrarToastMB51(error.message, "error");
    } finally {
      importBtn.disabled    = false;
      importBtn.textContent = "Importar";
    }
  }

  // ==========================
  // CARGAR DATOS
  // ==========================
  async function cargarTablaMB51() {
    try {
      const res  = await fetch("../api/mb51/get_mb51.php");
      const data = await res.json();
      datosMB51    = Array.isArray(data) ? data : [];
      paginaActual = 1;
      renderizarTabla();
    } catch (e) {
      mostrarToastMB51("No se pudo cargar la tabla MB51", "error");
    }
  }

  // ==========================
  // FILTRADO
  // ==========================
  function datosFiltrados() {
    const term = filtroInput.value.trim().toLowerCase();
    if (!term) return datosMB51;
    return datosMB51.filter(row =>
      String(row.material).toLowerCase().includes(term)      ||
      String(row.desc_articulo).toLowerCase().includes(term) ||
      padCodigo(row.almacen).toLowerCase().includes(term)
    );
  }

  // ==========================
  // RENDERIZAR TABLA + PAGINACIÓN
  // ==========================
  function renderizarTabla() {
    const filtrados  = datosFiltrados();
    const totalPags  = Math.max(1, Math.ceil(filtrados.length / POR_PAGINA));
    if (paginaActual > totalPags) paginaActual = totalPags;

    const inicio  = (paginaActual - 1) * POR_PAGINA;
    const pagina  = filtrados.slice(inicio, inicio + POR_PAGINA);

    document.getElementById("mb51-pag-info").textContent =
      filtrados.length === 0
        ? "Sin resultados"
        : `Mostrando ${inicio + 1}–${Math.min(inicio + POR_PAGINA, filtrados.length)} de ${filtrados.length} registro(s)`;

    const tbody = document.getElementById("mb51-table-body");
    tbody.innerHTML = "";

    if (pagina.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">No hay datos cargados</td></tr>`;
    } else {
      const fragment = document.createDocumentFragment();
      pagina.forEach(row => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${row.material}</td>
          <td>${row.desc_articulo}</td>
          <td>${row.cantidad}</td>
          <td>${row.fecha_doc}</td>
          <td>${padCodigo(row.almacen)}</td>
          <td>${row.clase_mov}</td>
          <td>${row.desc_clase_mov}</td>
          <td>${row.fecha_cont ?? ""}</td>
        `;
        fragment.appendChild(tr);
      });
      tbody.appendChild(fragment);
    }

    renderizarPaginacion(totalPags);
  }

  // ==========================
  // CONTROLES DE PAGINACIÓN
  // ==========================
  function renderizarPaginacion(totalPags) {
    const cont = document.getElementById("mb51-paginacion");
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
        document.getElementById("mb51-table-body").closest("table")
          .scrollIntoView({ behavior: "smooth", block: "start" });
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

  // ==========================
  // FILTRO CON DEBOUNCE
  // ==========================
  let _timer;
  filtroInput.addEventListener("input", () => {
    clearTimeout(_timer);
    _timer = setTimeout(() => {
      paginaActual = 1;
      renderizarTabla();
    }, 350);
  });

  // ==========================
  // TOAST ESPECIAL MB51
  // ==========================
  function mostrarToastMB51(html, tipo = "info") {
    const contenedor = document.getElementById("toast-container");
    if (!contenedor) return;
    const toast = document.createElement("div");
    toast.className = `toast ${tipo}`;
    toast.innerHTML = `
      <div>${html}</div>
      <button class="toast-close">&times;</button>
    `;
    toast.querySelector(".toast-close").addEventListener("click", () => toast.remove());
    contenedor.appendChild(toast);
  }

  // Carga inicial
  cargarTablaMB51();
});

// ==========================
// HELPERS GLOBALES
// ==========================
function padCodigo(v) {
  if (v === null || v === undefined || v === "") return "";
  return String(v).padStart(4, "0");
}

function descargarTxt(base64, nombre) {
  const binStr = atob(base64);
  const bytes  = new Uint8Array(binStr.length);
  for (let i = 0; i < binStr.length; i++) bytes[i] = binStr.charCodeAt(i);
  const blob = new Blob([bytes], { type: "text/plain;charset=utf-8" });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement("a");
  a.href     = url;
  a.download = nombre;
  a.click();
  URL.revokeObjectURL(url);
}
