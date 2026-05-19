// js/visitante/almacenes.js — solo lectura
document.addEventListener("DOMContentLoaded", () => {
  cargarAlmacenes();
});

/* =========================
   CARGAR
========================= */
async function cargarAlmacenes() {
  try {
    const res  = await fetch("../../api/almacen/get_almacenes.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
      mostrarToast("Error cargando almacenes.", "error");
      mostrarTabla([]);
      return;
    }

    mostrarTabla(data);

  } catch (e) {
    mostrarToast("Error cargando almacenes.", "error");
  }
}

/* =========================
   TABLA (sin columna Acción)
========================= */
function mostrarTabla(lista) {
  const tbody = document.querySelector("#alm-table tbody");
  tbody.innerHTML = "";

  if (lista.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Sin almacenes registrados</td></tr>`;
    return;
  }

  lista.forEach(a => {
    const fila = document.createElement("tr");
    fila.innerHTML = `
      <td>${escapeHtml(a.nombre)}</td>
      <td>${padCodigo(a.almacen_sap)}</td>
      <td>${padCodigo(a.almacen_sigc)}</td>
      <td>${padCodigo(a.almacen_tfa)}</td>
      <td>${padCodigo(a.almacen_consig)}</td>
      <td>${padCodigo(a.almacen_devolucion)}</td>
    `;
    tbody.appendChild(fila);
  });
}

/* =========================
   HELPERS
========================= */
function padCodigo(v) {
  if (v === null || v === undefined || v === "") return "";
  return String(v).padStart(4, "0");
}

function escapeHtml(t) {
  return String(t)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}
