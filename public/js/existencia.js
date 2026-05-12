document.addEventListener("DOMContentLoaded", () => {

  const selectAlmacen    = document.getElementById("filtro-almacen");
  const selectArticulo   = document.getElementById("filtro-articulo");
  const selectIndicacion = document.getElementById("filtro-indicacion");
  const tbody            = document.getElementById("existencia-table-body");

  let dataExistencia = [];

  // ==========================
  // CARGA INICIAL
  // ==========================
  cargarFiltros();
  cargarExistencia();

  // ==========================
  // EVENTOS
  // ==========================
  selectAlmacen.addEventListener("change", aplicarFiltros);
  selectArticulo.addEventListener("change", aplicarFiltros);
  selectIndicacion.addEventListener("change", aplicarFiltros);

  // ==========================
  // CARGAR SELECTS
  // ==========================
  async function cargarFiltros() {
    try {
      // ---- Almacenes ----
      const resAlm = await fetch("../api/existencia/get_filtro_almacenes_existencia.php");
      const almacenes = await resAlm.json();

      selectAlmacen.innerHTML = `<option value="">Todos los almacenes</option>`;
      almacenes.forEach(a => {
        selectAlmacen.innerHTML += `<option value="${a}">${a}</option>`;
      });

      // ---- Artículos ----
      const resArt = await fetch("../api/existencia/get_filtro_articulos_existencia.php");
      const articulos = await resArt.json();

      selectArticulo.innerHTML = `<option value="">Todos los artículos</option>`;
      articulos.forEach(a => {
        selectArticulo.innerHTML += `<option value="${a}">${a}</option>`;
      });

    } catch (e) {
      console.error("Error cargando filtros:", e);
      mostrarToast("No se pudieron cargar los filtros", "error");
    }
  }

  // ==========================
  // CARGAR EXISTENCIA BASE
  // ==========================
  async function cargarExistencia() {
    try {
      const res = await fetch("../api/existencia/get_existencia_recursos.php");
      dataExistencia = await res.json();
      renderTabla(dataExistencia);

    } catch (e) {
      console.error("Error cargando existencia:", e);
      mostrarToast("No se pudo cargar la existencia de recursos", "error");
    }
  }

  // ==========================
  // APLICAR FILTROS (MULTIPLES)
  // ==========================
  function aplicarFiltros() {

    let filtrados = [...dataExistencia];

    // ---- Filtro almacén ----
    if (selectAlmacen.value) {
      filtrados = filtrados.filter(r =>
        String(r.almacen) === String(selectAlmacen.value)
      );
    }

    // ---- Filtro artículo ----
    if (selectArticulo.value) {
      filtrados = filtrados.filter(r =>
        String(r.material) === String(selectArticulo.value)  // antes era r.desc_articulo
      );
    }

    // ---- Filtro indicación ----
    if (selectIndicacion.value) {
      filtrados = filtrados.filter(r =>
        obtenerIndicacion(r) === selectIndicacion.value
      );
    }

    renderTabla(filtrados);
  }

  // ==========================
  // CLASIFICACIÓN DE INDICACIÓN
  // ==========================
  function obtenerIndicacion(row) {

    const promedio = Number(row.promedio_ventas);
    const disp     = Number(row.disponibilidad);

    // PRIORIDAD IMPORTANTE
    if (promedio === 0) return "azul";
    if (disp === 0) return "rojo";
    if (disp > 0 && disp < 1) return "amarillo";
    if (disp > 12) return "verde";

    return "gris";
  }

  // ==========================
  // RENDER TABLA + COLORES
  // ==========================
  function renderTabla(data) {

    tbody.innerHTML = "";

    if (!data.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align:center;">
            No hay resultados para los filtros seleccionados
          </td>
        </tr>
      `;
      return;
    }

    data.forEach(row => {

      const cantidad       = Number(row.cantidad);
      const promedioVentas = Number(row.promedio_ventas);
      const disponibilidad = Number(row.disponibilidad);

      const tr = document.createElement("tr");

      // ---- Color según indicación ----
      switch (obtenerIndicacion(row)) {
        case "azul":
          tr.classList.add("row-sin-ventas");
          break;
        case "rojo":
          tr.classList.add("row-danger");
          break;
        case "amarillo":
          tr.classList.add("row-warning");
          break;
        case "verde":
          tr.classList.add("row-optima");
          break;
        default:
          tr.classList.add("row-normal");
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

  document.getElementById("btn-exportar").addEventListener("click", () => {
      const params = new URLSearchParams();
      if (selectAlmacen.value)    params.append('almacen',    selectAlmacen.value);
      if (selectArticulo.value)   params.append('articulo',   selectArticulo.value);
      if (selectIndicacion.value) params.append('indicacion', selectIndicacion.value);

      window.location.href = `../api/existencia/export_existencia.php?${params.toString()}`;
  });

});

