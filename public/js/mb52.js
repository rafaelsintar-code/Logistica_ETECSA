document.addEventListener("DOMContentLoaded", () => {

  const fileInput    = document.getElementById("mb52-file");
  const fileNameSpan = document.getElementById("mb52-file-name");
  const importBtn    = document.getElementById("mb52-btn-import");
  const tbody        = document.getElementById("mb52-table-body");
  const filtroInput  = document.getElementById("mb52-filtro");

  let datosMB52 = [];

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
      const response = await fetch("../api/mb52/import_mb52_excel.php", {
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

      cargarTablaMB52();

    } catch (error) {
      mostrarToastMB52(error.message, "error");
    } finally {
      importBtn.disabled    = false;
      importBtn.textContent = "Importar";
    }
  }

  // ==========================
  // CARGAR TABLA MB52
  // ==========================
  async function cargarTablaMB52() {
    try {
      const res  = await fetch("../api/mb52/get_mb52.php");
      const data = await res.json();

      datosMB52 = Array.isArray(data) ? data : [];
      renderizarTabla(datosMB52);

    } catch (e) {
      console.error("Error cargando MB52:", e);
      mostrarToastMB52("No se pudo cargar la tabla MB52", "error");
    }
  }

  // ==========================
  // RENDERIZAR TABLA
  // ==========================
  function renderizarTabla(lista) {
    tbody.innerHTML = "";

    if (!lista.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="10" style="text-align:center;">No hay datos cargados</td>
        </tr>`;
      return;
    }

    const fragment = document.createDocumentFragment();
    lista.forEach(row => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${row.centro}</td>
        <td>${padCodigo(row.almacen)}</td>
        <td>${row.denom_almacen}</td>
        <td>${row.grupo_art}</td>
        <td>${row.material}</td>
        <td>${row.desc_articulo}</td>
        <td>${row.libre_utilizacion}</td>
        <td>${row.umb}</td>
        <td>${row.valor_lu}</td>
        <td>${row.bloqueado}</td>
      `;
      fragment.appendChild(tr);
    });
    tbody.appendChild(fragment);
  }

  // ==========================
  // FILTRO ÚNICO CON DEBOUNCE
  // ==========================
  function filtrar() {
    const term = filtroInput.value.trim().toLowerCase();

    if (!term) {
      renderizarTabla(datosMB52);
      return;
    }

    const resultado = datosMB52.filter(row =>
      padCodigo(row.almacen).toLowerCase().includes(term)        ||
      String(row.denom_almacen).toLowerCase().includes(term)     ||
      String(row.material).toLowerCase().includes(term)          ||
      String(row.desc_articulo).toLowerCase().includes(term)     ||
      String(row.grupo_art).toLowerCase().includes(term)
    );

    renderizarTabla(resultado);
  }

  function debounce(fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  }

  filtroInput.addEventListener("input", debounce(filtrar, 350));

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
// HELPERS
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