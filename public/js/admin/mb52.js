document.addEventListener("DOMContentLoaded", () => {

  const fileInput    = document.getElementById("mb52-file");
  const fileNameSpan = document.getElementById("mb52-file-name");
  const importBtn    = document.getElementById("mb52-btn-import");
  const filtroInput  = document.getElementById("mb52-filtro");

  // ── Estado de paginación del servidor ──────────────────────────────────────
  let paginaActual   = 1;
  let totalRegistros = 0;
  const POR_PAGINA   = 100;

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
      "Esta acción eliminará los datos actuales de MB52 y cargará los nuevos. ¿Desea continuar?",
      importarMB52
    );
  });

  // ==========================
  // IMPORTACIÓN MB52
  // ==========================
  async function importarMB52() {
    const formData = new FormData();
    formData.append("excel", fileInput.files[0]);

    importBtn.disabled    = true;
    importBtn.textContent = "Importando...";

    try {
      const response = await fetch("../../api/mb52/import_mb52_excel.php", {
        method: "POST",
        body:   formData
      });

      const result = await response.json();

      if (!response.ok || result.error) {
        throw new Error(result.error || "Error durante la importación");
      }

      const hayErrores = result.errores_excel || result.errores_nomenclador;

      mostrarToastMB52(`
        <strong>Importación finalizada</strong><br>
        Importados: ${result.importados}<br>
        Errores Excel: ${result.errores_excel}<br>
        Errores Nomenclador: ${result.errores_nomenclador}
        ${result.reporte_txt
          ? `<br><a href="#" id="mb52-descargar-reporte"
               style="color:#1d4ed8; font-weight:600; text-decoration:underline;">
               &#8659; Descargar reporte de errores
             </a>`
          : ''
        }
      `, hayErrores ? "info" : "success");

      if (result.reporte_txt) {
        setTimeout(() => {
          const enlace = document.getElementById("mb52-descargar-reporte");
          if (enlace) {
            enlace.addEventListener("click", e => {
              e.preventDefault();
              descargarTxt(result.reporte_txt, "reporte_errores_mb52.txt");
            });
          }
        }, 100);
      }

      fileInput.value          = "";
      fileNameSpan.textContent = "Ningún archivo seleccionado";
      importBtn.disabled       = true;
      filtroInput.value        = "";
      paginaActual             = 1;

      cargarTablaMB52();

    } catch (error) {
      mostrarToastMB52(error.message, "error");
    } finally {
      importBtn.disabled    = false;
      importBtn.textContent = "Importar";
    }
  }

  // ==========================
  // CARGAR DATOS (paginación servidor)
  // ==========================
  async function cargarTablaMB52() {
    const filtro = filtroInput.value.trim();
    const params = new URLSearchParams({
      pagina: paginaActual,
      limite: POR_PAGINA,
      ...(filtro ? { filtro } : {})
    });

    try {
      const res  = await fetch(`../../api/mb52/get_mb52.php?${params}`);
      const data = await res.json();

      if (!res.ok || !Array.isArray(data.datos)) {
        mostrarToastMB52("No se pudo cargar la tabla MB52", "error");
        return;
      }

      totalRegistros = data.total;
      renderizarTabla(data.datos);
    } catch (e) {
      mostrarToastMB52("No se pudo cargar la tabla MB52", "error");
    }
  }

  // ==========================
  // RENDERIZAR TABLA
  // ==========================
  function renderizarTabla(filas) {
    const totalPags = Math.max(1, Math.ceil(totalRegistros / POR_PAGINA));
    const inicio    = (paginaActual - 1) * POR_PAGINA + 1;
    const fin       = Math.min(paginaActual * POR_PAGINA, totalRegistros);

    document.getElementById("mb52-pag-info").textContent =
      totalRegistros === 0
        ? "Sin resultados"
        : `Mostrando ${inicio}–${fin} de ${totalRegistros} registro(s)`;

    const tbody = document.getElementById("mb52-table-body");
    tbody.innerHTML = "";

    if (filas.length === 0) {
      tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;">No hay datos cargados</td></tr>`;
    } else {
      const fragment = document.createDocumentFragment();
      filas.forEach(row => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${escapeHtml(String(row.centro))}</td>
          <td>${escapeHtml(padCodigo(row.almacen))}</td>
          <td>${escapeHtml(row.denom_almacen)}</td>
          <td>${escapeHtml(String(row.grupo_art))}</td>
          <td>${escapeHtml(String(row.material))}</td>
          <td>${escapeHtml(row.desc_articulo)}</td>
          <td>${escapeHtml(String(row.libre_utilizacion))}</td>
          <td>${escapeHtml(row.umb)}</td>
          <td>${escapeHtml(String(row.valor_lu))}</td>
          <td>${escapeHtml(String(row.bloqueado))}</td>
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
    const cont = document.getElementById("mb52-paginacion");
    cont.innerHTML = "";
    if (totalPags <= 1) return;

    const crearBtn = (label, pagina, deshabilitado = false, activo = false) => {
      const btn       = document.createElement("button");
      btn.textContent = label;
      btn.className   = "art-pag-btn" + (activo ? " art-pag-btn-activo" : "");
      btn.disabled    = deshabilitado;
      btn.addEventListener("click", () => {
        paginaActual = pagina;
        cargarTablaMB52();
        document.getElementById("mb52-table-body").closest("table")
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
      cargarTablaMB52();
    }, 350);
  });

  // ==========================
  // TOAST ESPECIAL MB52
  // ==========================
  function mostrarToastMB52(html, tipo = "info") {
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
  cargarTablaMB52();
});

// ==========================
// HELPERS GLOBALES
// ==========================
function escapeHtml(t) {
  return String(t)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
