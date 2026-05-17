document.addEventListener("DOMContentLoaded", () => {

  const selectAlmacen    = document.getElementById("filtro-almacen");
  const selectArticulo   = document.getElementById("filtro-articulo");
  const selectIndicacion = document.getElementById("filtro-indicacion");

  let dataExistencia = [];
  let paginaActual   = 1;
  const POR_PAGINA   = 50;

  // ==========================
  // CARGA INICIAL
  // ==========================
  cargarFiltros();
  cargarExistencia();

  // ==========================
  // EVENTOS DE FILTRO
  // ==========================
  selectAlmacen.addEventListener("change",    () => { paginaActual = 1; aplicarFiltros(); });
  selectArticulo.addEventListener("change",   () => { paginaActual = 1; aplicarFiltros(); });
  selectIndicacion.addEventListener("change", () => { paginaActual = 1; aplicarFiltros(); });

  // ==========================
  // CARGAR SELECTS
  // ==========================
  async function cargarFiltros() {
    try {
      const resAlm    = await fetch("../api/existencia/get_filtro_almacenes_existencia.php");
      const almacenes = await resAlm.json();

      selectAlmacen.innerHTML = `<option value="">Todos los almacenes</option>`;
      almacenes.forEach(a => {
        const opt = document.createElement("option");
        opt.value = a;
        opt.textContent = a;
        selectAlmacen.appendChild(opt);
      });

      const resArt    = await fetch("../api/existencia/get_filtro_articulos_existencia.php");
      const articulos = await resArt.json();

      selectArticulo.innerHTML = `<option value="">Todos los artículos</option>`;
      articulos.forEach(a => {
        const opt = document.createElement("option");
        opt.value = a.material;
        opt.textContent = a.desc_articulo;
        selectArticulo.appendChild(opt);
      });

    } catch (e) {
      mostrarToast("No se pudieron cargar los filtros", "error");
    }
  }

  // ==========================
  // CARGAR EXISTENCIA
  // ==========================
  async function cargarExistencia() {
    try {
      const res      = await fetch("../api/existencia/get_existencia_recursos.php");
      dataExistencia = await res.json();
      paginaActual   = 1;
      aplicarFiltros();
    } catch (e) {
      mostrarToast("No se pudo cargar la existencia de recursos", "error");
    }
  }

  // ==========================
  // APLICAR FILTROS
  // ==========================
  function aplicarFiltros() {
    let filtrados = [...dataExistencia];

    if (selectAlmacen.value) {
      filtrados = filtrados.filter(r => String(r.almacen) === String(selectAlmacen.value));
    }
    if (selectArticulo.value) {
      filtrados = filtrados.filter(r => String(r.material) === String(selectArticulo.value));
    }
    if (selectIndicacion.value) {
      filtrados = filtrados.filter(r => obtenerIndicacion(r) === selectIndicacion.value);
    }

    renderTabla(filtrados);
  }

  // ==========================
  // CLASIFICACIÓN DE INDICACIÓN
  // ==========================
  function obtenerIndicacion(row) {
    const promedio = Number(row.promedio_ventas);
    const disp     = Number(row.disponibilidad);
    if (promedio === 0) return "azul";
    if (disp === 0)     return "rojo";
    if (disp > 0 && disp < 1) return "amarillo";
    if (disp > 12)      return "verde";
    return "gris";
  }

  // ==========================
  // RENDER TABLA + PAGINACIÓN
  // ==========================
  function renderTabla(filtrados) {
    const totalPags = Math.max(1, Math.ceil(filtrados.length / POR_PAGINA));
    if (paginaActual > totalPags) paginaActual = totalPags;

    const inicio = (paginaActual - 1) * POR_PAGINA;
    const pagina = filtrados.slice(inicio, inicio + POR_PAGINA);

    document.getElementById("existencia-pag-info").textContent =
      filtrados.length === 0
        ? "Sin resultados"
        : `Mostrando ${inicio + 1}–${Math.min(inicio + POR_PAGINA, filtrados.length)} de ${filtrados.length} registro(s)`;

    const tbody = document.getElementById("existencia-table-body");
    tbody.innerHTML = "";

    if (pagina.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No hay resultados para los filtros seleccionados</td></tr>`;
    } else {
      pagina.forEach(row => {
        const cantidad       = Number(row.cantidad);
        const promedioVentas = Number(row.promedio_ventas);
        const disponibilidad = Number(row.disponibilidad);

        const tr = document.createElement("tr");
        switch (obtenerIndicacion(row)) {
          case "azul":    tr.classList.add("row-sin-ventas"); break;
          case "rojo":    tr.classList.add("row-danger");     break;
          case "amarillo":tr.classList.add("row-warning");    break;
          case "verde":   tr.classList.add("row-optima");     break;
          default:        tr.classList.add("row-normal");
        }

        tr.innerHTML = `
          <td>${row.material}</td>
          <td>${row.desc_articulo}</td>
          <td>${row.almacen}</td>
          <td>${cantidad.toFixed(2)}</td>
          <td>${promedioVentas.toFixed(2)}</td>
          <td>${promedioVentas === 0 ? "—" : disponibilidad.toFixed(2)}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    renderizarPaginacion(filtrados, totalPags);
  }

  // ==========================
  // CONTROLES DE PAGINACIÓN
  // ==========================
  function renderizarPaginacion(filtrados, totalPags) {
    const cont = document.getElementById("existencia-paginacion");
    cont.innerHTML = "";
    if (totalPags <= 1) return;

    const crearBtn = (label, pagina, deshabilitado = false, activo = false) => {
      const btn       = document.createElement("button");
      btn.textContent = label;
      btn.className   = "art-pag-btn" + (activo ? " art-pag-btn-activo" : "");
      btn.disabled    = deshabilitado;
      btn.addEventListener("click", () => {
        paginaActual = pagina;
        renderTabla(filtrados);
        document.getElementById("existencia-table-body").closest("table")
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
  // EXPORTAR
  // ==========================
  document.getElementById("btn-exportar").addEventListener("click", () => {
    const params = new URLSearchParams();
    if (selectAlmacen.value)    params.append("almacen",    selectAlmacen.value);
    if (selectArticulo.value)   params.append("articulo",   selectArticulo.value);
    if (selectIndicacion.value) params.append("indicacion", selectIndicacion.value);
    window.location.href = `../api/existencia/export_existencia.php?${params.toString()}`;
  });
});
