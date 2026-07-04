// js/admin/mb_utils.js
// Utilidades compartidas entre mb51.js y mb52.js.
// Este archivo debe cargarse ANTES que mb51.js / mb52.js en las páginas correspondientes.

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
