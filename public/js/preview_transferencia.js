document.addEventListener("DOMContentLoaded", () => {

  const raw = sessionStorage.getItem("preview_transferencia");

  if (!raw) {
    document.getElementById("doc-capture").innerHTML =
      `<p style="color:red; text-align:center; padding:40px;">
         No hay datos de transferencia. Vuelve a la página anterior.
       </p>`;

    document.getElementById("prev-pdf").disabled = true;
    return;
  }

  const trf = JSON.parse(raw);

  document.getElementById("prev-titulo").textContent =
    `${trf.denom_origen} → ${trf.denom_destino} — Folio #${trf.vale}`;

  inyectarDatos(trf);

  document.getElementById("prev-volver").addEventListener("click", () => {
    window.location.href = "transferencia.html";
  });

  document.getElementById("prev-pdf").addEventListener("click", () => {
    descargarPDF(trf);
  });
});

/* ─── INYECTAR DATOS EN EL DOCUMENTO ────────────── */
function inyectarDatos(trf) {
  const fecha = new Date(trf.hora_creacion);
  const pad   = n => String(n).padStart(2, "0");

  document.getElementById("prev-folio").textContent   = trf.vale;
  document.getElementById("prev-dia").textContent     = pad(fecha.getDate());
  document.getElementById("prev-mes").textContent     = pad(fecha.getMonth() + 1);
  document.getElementById("prev-anio").textContent    = fecha.getFullYear();
  document.getElementById("prev-origen").textContent  = formatearAlmacen(trf.almacen_origen);
  document.getElementById("prev-destino").textContent = formatearAlmacen(trf.almacen_destino);

  const totalCantidad = trf.articulos.reduce((acc, a) => acc + (parseInt(a.cantidad) || 0), 0);
  document.getElementById("prev-total").textContent = totalCantidad;

  const tbody      = document.getElementById("prev-filas-articulos");
  const totalFilas = Math.max(8, trf.articulos.length);
  tbody.innerHTML  = "";

  for (let i = 0; i < totalFilas; i++) {
    const cod  = trf.articulos[i]?.codigo      ?? '';
    const desc = trf.articulos[i]?.descripcion ?? '';
    const cant = trf.articulos[i]?.cantidad    ?? '';

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="col-codigo">${escapeHtml(String(cod))}</td>
      <td class="col-desc">${escapeHtml(String(desc))}</td>
      <td class="col-cant">${cant}</td>
      <td class="col-obs"></td>
    `;
    tbody.appendChild(tr);
  }
}

/* ─── GENERAR PDF ────────────────────────────────── */
async function descargarPDF(trf) {
  const btnPdf = document.getElementById("prev-pdf");
  btnPdf.disabled = true;
  mostrarToast("Generando PDF...", "info");

  try {
    const elemento = document.getElementById("doc-capture");

    // ── Quitar temporalmente el background del CSS ──
    const bgOriginal = elemento.style.backgroundImage;
    elemento.style.backgroundImage = "none";
    elemento.style.backgroundColor = "transparent";

    const canvasDoc = await html2canvas(elemento, {
      scale:           2,
      useCORS:         false,
      backgroundColor: null,
      logging:         false
    });

    // ── Restaurar el fondo visual de la página ──
    elemento.style.backgroundImage = bgOriginal;
    elemento.style.backgroundColor = "";

    const marcaAgua = await cargarImagen('../img/plantillaEtecsa.webp');

    const final  = document.createElement("canvas");
    final.width  = canvasDoc.width;
    final.height = canvasDoc.height;
    const ctx    = final.getContext("2d");

    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, final.width, final.height);

    // Ahora sí, solo una capa de marca de agua con opacidad controlada
    const OPACIDAD_MARCA = 0.08;   // ajusta aquí libremente
    const TAMANO_MARCA   = 1.4;

    const mW = final.width * TAMANO_MARCA;
    const mH = marcaAgua.naturalHeight * (mW / marcaAgua.naturalWidth);
    const mX = (final.width  - mW) / 2;
    const mY = (final.height - mH) / 2;

    ctx.globalAlpha = OPACIDAD_MARCA;
    ctx.drawImage(marcaAgua, mX, mY, mW, mH);

    ctx.globalAlpha = 1;
    ctx.drawImage(canvasDoc, 0, 0);

    // 4. Generar PDF
    const imgData = final.toDataURL("image/png");

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
      orientation: "portrait",
      unit:        "mm",
      format:      "a4"
    });

    const pageW  = pdf.internal.pageSize.getWidth();
    const margen = 10;
    const imgW   = pageW - margen * 2;
    const imgH   = imgW / (final.width / final.height);

    pdf.addImage(imgData, "PNG", margen, margen, imgW, imgH);

    const nombre = `transferencia_${trf.denom_origen}_folio${trf.vale}`
      .replace(/[^a-zA-Z0-9_\-]/g, '_');

    pdf.save(`${nombre}.pdf`);
    mostrarToast("PDF descargado correctamente.", "success");

  } catch (e) {
    mostrarToast("Error al generar el PDF.", "error");
    console.error(e);
  } finally {
    btnPdf.disabled = false;
  }
}

/* ─── HELPER: cargar imagen como objeto Image ────── */
function cargarImagen(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload  = () => resolve(img);
    img.onerror = () => reject(new Error("No se pudo cargar: " + src));
    img.src     = src;
  });
}

/* ─── HELPER: escapar HTML ───────────────────────── */
function escapeHtml(t) {
  return String(t)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

/**
 * Si el código de almacén empieza en '0', lo representa como C + 3 dígitos.
 * Ej: "0001" → "C001", "0023" → "C023", "0847" → "C847"
 */
function formatearAlmacen(codigo) {
  const s = String(codigo).padStart(4, '0');
  return s.startsWith('0') ? 'C001' : s;
}