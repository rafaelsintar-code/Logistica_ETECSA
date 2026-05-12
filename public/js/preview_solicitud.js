document.addEventListener("DOMContentLoaded", () => {

  const raw = sessionStorage.getItem("preview_solicitud");

  if (!raw) {
    document.querySelector(".prev-documento").innerHTML =
      `<p style="color:red; text-align:center; padding:20px;">
         No hay datos de solicitud. Vuelve a la página anterior.
       </p>`;
    document.getElementById("prev-excel").disabled = true;
    document.getElementById("prev-pdf").disabled   = true;
    return;
  }

  const sol = JSON.parse(raw);

  // Título en barra de acciones
  document.getElementById("prev-titulo").textContent =
    `${sol.nombre_pv} — Vale #${sol.vale}`;

  // Inyectar valores dinámicos
  inyectarDatos(sol);

  // Botón volver
  document.getElementById("prev-volver").addEventListener("click", () => {
    window.location.href = "solicitud.html";
  });

  // Botones descarga
  document.getElementById("prev-excel").addEventListener("click", () => {
    descargarDocumento("../api/solicitud/generar_excel.php", sol, "xlsx");
  });

  document.getElementById("prev-pdf").addEventListener("click", () => {
    descargarDocumento("../api/solicitud/generar_pdf.php", sol, "pdf");
  });
});

/* ─── INYECTAR DATOS DINÁMICOS ──────────────────── */
function inyectarDatos(sol) {
  const fecha = new Date(sol.hora_creacion);
  const pad   = n => String(n).padStart(2, "0");

  // Fecha
  document.getElementById("prev-dia").textContent  = pad(fecha.getDate());
  document.getElementById("prev-mes").textContent  = pad(fecha.getMonth() + 1);
  document.getElementById("prev-anio").textContent = fecha.getFullYear();
  document.getElementById("prev-fecha-completa").textContent =
    `${pad(fecha.getDate())}/${pad(fecha.getMonth() + 1)}/${fecha.getFullYear()}`;

  // Almacén
  document.getElementById("prev-almacen").textContent =
    `${escapeHtml(sol.nombre_pv)} — ${sol.codigo_almacen}`;

  // Vale
  document.getElementById("prev-vale").textContent = sol.vale;

  // Filas artículos
  const tbody      = document.getElementById("prev-filas-articulos");
  const totalFilas = Math.max(8, sol.articulos.length);
  tbody.innerHTML  = "";

  for (let i = 0; i < totalFilas; i++) {
    const cod  = sol.articulos[i]?.codigo      ?? '';
    const desc = sol.articulos[i]?.descripcion ?? '';
    const cant = sol.articulos[i]?.cantidad    ?? '';
    const um   = cod ? 'U' : '';

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="doc-center">${i + 1}</td>
      <td class="doc-center">${escapeHtml(String(cod))}</td>
      <td>${escapeHtml(String(desc))}</td>
      <td class="doc-center">${um}</td>
      <td class="doc-center">${cant}</td>
      <td></td>
      <td></td>
    `;
    tbody.appendChild(tr);
  }
}

/* ─── DESCARGA DE DOCUMENTOS ────────────────────── */
async function descargarDocumento(endpoint, sol, tipo) {
  const btnPdf = document.getElementById("prev-pdf");
  const btnXls = document.getElementById("prev-excel");
  btnPdf.disabled = true;
  btnXls.disabled = true;

  try {
    const res = await fetch(endpoint, {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({
        solicitud_id: sol.id,
        articulos:    sol.articulos,
        vale:         sol.vale
      })
    });

    const data = await res.json();

    //const texto = await res.text();
    //console.log("Respuesta cruda PDF:", texto);

    if (data.success === false) {
      mostrarToast(data.message || "Error al generar el documento.", "error");
      return;
    }

    const campo  = tipo === "pdf" ? data.pdf : data.xlsx;
    const mime   = tipo === "pdf"
      ? "application/pdf"
      : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

    const binStr = atob(campo);
    const bytes  = new Uint8Array(binStr.length);
    for (let i = 0; i < binStr.length; i++) bytes[i] = binStr.charCodeAt(i);

    const blob = new Blob([bytes], { type: mime });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement("a");
    a.href     = url;
    a.download = data.filename;
    a.click();
    URL.revokeObjectURL(url);

    mostrarToast("Documento descargado correctamente.", "success");

  } catch (e) {
    mostrarToast("Error al descargar el documento.", "error");
  } finally {
    btnPdf.disabled = false;
    btnXls.disabled = false;
  }
}

/* ─── HELPERS ───────────────────────────────────── */
function escapeHtml(t) {
  return String(t)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}