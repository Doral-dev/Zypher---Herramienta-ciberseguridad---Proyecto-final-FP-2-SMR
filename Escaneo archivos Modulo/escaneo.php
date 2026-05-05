<?php
// Escaneo archivos Modulo/escaneo.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Módulo de escaneo de archivos</title>

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #07111f;
      color: #eaf0ff;
    }

    .page {
      max-width: 1150px;
      margin: 0 auto;
      padding: 35px 20px;
    }

    h1 {
      margin: 0 0 25px;
      font-size: 34px;
    }

    .upload-box {
      border: 2px dashed #2f7dff;
      border-radius: 18px;
      background: #0b1b31;
      padding: 55px 20px;
      text-align: center;
      margin-bottom: 22px;
    }

    .upload-icon {
      font-size: 45px;
      margin-bottom: 12px;
    }

    .upload-box h2 {
      margin: 0 0 10px;
      font-size: 24px;
    }

    .upload-box p {
      color: #91a4c4;
      margin: 0 0 20px;
    }

    input[type="file"] {
      display: none;
    }

    .btn {
      border: 0;
      border-radius: 10px;
      padding: 13px 24px;
      cursor: pointer;
      font-size: 16px;
      color: #fff;
    }

    .btn-secondary {
      background: #142844;
      border: 1px solid #2f7dff;
    }

    .scan-action {
      text-align: center;
      margin-bottom: 28px;
    }

    .btn-primary {
      background: #1769ff;
      font-size: 20px;
      padding: 16px 55px;
      box-shadow: 0 0 25px rgba(23, 105, 255, 0.35);
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .selected-file {
      margin-top: 15px;
      color: #b9d4ff;
      font-size: 15px;
    }

    .message {
      display: none;
      margin-bottom: 20px;
      padding: 14px 18px;
      border-radius: 12px;
      background: #33151a;
      color: #ffb6bd;
      border: 1px solid #ff5f68;
    }

    .results {
      display: none;
    }

    .summary {
      display: flex;
      align-items: center;
      gap: 25px;
      background: #0b1b31;
      border: 1px solid #233a5c;
      border-radius: 18px;
      padding: 25px;
      margin-bottom: 25px;
    }

    .score {
      width: 130px;
      height: 130px;
      border: 12px solid #ff4d57;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .score strong {
      font-size: 34px;
    }

    .score span {
      color: #aab8d3;
    }

    .file-info {
      flex: 1;
      min-width: 0;
    }

    .status {
      display: inline-block;
      padding: 8px 14px;
      border-radius: 8px;
      margin-bottom: 12px;
      font-weight: bold;
    }

    .status.peligroso {
      background: #3a1118;
      color: #ff5f68;
      border: 1px solid #ff5f68;
    }

    .status.sospechoso {
      background: #3b2a10;
      color: #ffbf47;
      border: 1px solid #ffbf47;
    }

    .status.limpio {
      background: #12351e;
      color: #58d68d;
      border: 1px solid #58d68d;
    }

    .status.no-encontrado {
      background: #1f2c3d;
      color: #b9d4ff;
      border: 1px solid #4d6f9f;
    }

    .file-info h2 {
      margin: 0 0 8px;
      word-break: break-word;
    }

    .hash {
      color: #91a4c4;
      word-break: break-all;
      font-size: 14px;
    }

    .meta {
      display: flex;
      gap: 35px;
      color: #c5d1e8;
      flex-wrap: wrap;
    }

    .tabs {
      display: flex;
      justify-content: center;
      background: #0b1b31;
      border: 1px solid #233a5c;
      border-radius: 18px 18px 0 0;
      overflow: hidden;
    }

    .tab {
      padding: 16px 25px;
      color: #91a4c4;
      border-bottom: 3px solid transparent;
      cursor: pointer;
    }

    .tab.active {
      color: #fff;
      border-bottom-color: #2f7dff;
      background: #102744;
    }

    .content {
      background: #0b1b31;
      border: 1px solid #233a5c;
      border-top: 0;
      border-radius: 0 0 18px 18px;
      padding: 25px;
    }

    .tab-panel {
      display: none;
    }

    .tab-panel.active {
      display: block;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 25px;
    }

    .panel {
      background: #081629;
      border: 1px solid #233a5c;
      border-radius: 14px;
      padding: 20px;
    }

    .panel h3 {
      margin: 0 0 15px;
      font-size: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 13px 10px;
      border-bottom: 1px solid #1c3150;
      text-align: left;
      vertical-align: top;
    }

    th {
      color: #91a4c4;
      font-weight: normal;
      width: 42%;
    }

    td {
      color: #fff;
      word-break: break-word;
    }

    tr:last-child th,
    tr:last-child td {
      border-bottom: 0;
    }

    .danger {
      color: #ff5f68;
      font-weight: bold;
    }

    .valor-peligroso {
      color: #ff5f68;
      font-weight: bold;
    }

    .valor-sospechoso {
      color: #ffbf47;
      font-weight: bold;
    }

    .valor-limpio {
      color: #58d68d;
      font-weight: bold;
    }

    .empty {
      color: #91a4c4;
      padding: 18px 0;
    }

    .motor-list {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .motor-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #102744;
      border: 1px solid #2b4d78;
      padding: 8px 12px;
      border-radius: 10px;
      color: #eaf0ff;
    }

    .motor-logo {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #1769ff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: bold;
    }

    @media (max-width: 800px) {
      h1 {
        font-size: 28px;
      }

      .summary {
        flex-direction: column;
        align-items: flex-start;
      }

      .grid {
        grid-template-columns: 1fr;
      }

      .tabs {
        overflow-x: auto;
        justify-content: flex-start;
      }
    }
  </style>
</head>

<body>
  <main class="page">
    <h1>Módulo de escaneo de archivos</h1>

    <form id="scanForm" enctype="multipart/form-data">
      <section class="upload-box" id="uploadBox">
        <div class="upload-icon">⬆️</div>
        <h2>Arrastra tu archivo aquí</h2>
        <p>Suelta el archivo para analizarlo</p>

        <label class="btn btn-secondary" for="archivo">Seleccionar archivo</label>
        <input type="file" name="archivo" id="archivo">

        <div class="selected-file" id="selectedFile">Ningún archivo seleccionado</div>
      </section>

      <div class="scan-action">
        <button class="btn btn-primary" id="scanBtn" type="submit" disabled>
          Escanear archivo
        </button>
      </div>
    </form>

    <div class="message" id="message"></div>

    <section class="results" id="results">
      <section class="summary">
        <div class="score">
          <strong id="scoreMain">-</strong>
          <span id="scoreTotal">/ -</span>
        </div>

        <div class="file-info">
          <div class="status no-encontrado" id="estadoGeneral">Sin analizar</div>
          <h2 id="nombreArchivo">-</h2>
          <div class="hash" id="sha256">-</div>
        </div>

        <div class="meta">
          <div>
            <strong id="tamanoArchivo">-</strong><br>
            Tamaño
          </div>
          <div>
            <strong id="ultimoAnalisis">-</strong><br>
            Último análisis
          </div>
        </div>
      </section>

      <nav class="tabs">
        <div class="tab active" data-tab="deteccion">Detección</div>
        <div class="tab" data-tab="detalles">Detalles</div>
        <div class="tab" data-tab="relaciones">Relaciones</div>
      </nav>

      <section class="content">
        <div class="tab-panel active" id="tab-deteccion">
          <div class="grid">
            <div class="panel">
              <h3>Resultado del análisis</h3>
              <table>
                <tbody id="tablaDeteccion"></tbody>
              </table>
            </div>

            <div class="panel">
              <h3>Motores que lo detectan</h3>
              <table>
                <tbody id="tablaMotores"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="tab-panel" id="tab-detalles">
          <div class="panel">
            <h3>Detalles del archivo</h3>
            <table>
              <tbody id="tablaDetalles"></tbody>
            </table>
          </div>
        </div>

        <div class="tab-panel" id="tab-relaciones">
          <div class="grid">
            <div class="panel">
              <h3>Dominios contactados</h3>
              <table><tbody id="tablaDominios"></tbody></table>
            </div>

            <div class="panel">
              <h3>IPs contactadas</h3>
              <table><tbody id="tablaIps"></tbody></table>
            </div>

            <div class="panel">
              <h3>Archivos relacionados</h3>
              <table><tbody id="tablaRelacionados"></tbody></table>
            </div>

            <div class="panel">
              <h3>Archivos soltados</h3>
              <table><tbody id="tablaSoltados"></tbody></table>
            </div>
          </div>
        </div>
      </section>
    </section>
  </main>

  <script>
    const form = document.getElementById('scanForm');
    const inputArchivo = document.getElementById('archivo');
    const selectedFile = document.getElementById('selectedFile');
    const scanBtn = document.getElementById('scanBtn');
    const message = document.getElementById('message');
    const results = document.getElementById('results');

    inputArchivo.addEventListener('change', () => {
      const file = inputArchivo.files[0];

      if (!file) {
        selectedFile.textContent = 'Ningún archivo seleccionado';
        scanBtn.disabled = true;
        return;
      }

      selectedFile.textContent = file.name;
      scanBtn.disabled = false;
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (!inputArchivo.files[0]) {
        mostrarError('Selecciona un archivo primero');
        return;
      }

      ocultarError();
      scanBtn.disabled = true;
      scanBtn.textContent = 'Escaneando...';

      const formData = new FormData();
      formData.append('archivo', inputArchivo.files[0]);

      try {
        const response = await fetch('guardar_escaneo.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (!data.ok) {
          mostrarError(data.error || 'Error al escanear el archivo');
          return;
        }

        pintarResultado(data.resultado);
        results.style.display = 'block';

      } catch (error) {
        mostrarError('No se pudo conectar con guardar_escaneo.php');
      } finally {
        scanBtn.disabled = false;
        scanBtn.textContent = 'Escanear archivo';
      }
    });

    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
      });
    });

    function pintarResultado(resultado) {
      const deteccion = resultado.deteccion || {};
      const detalles = resultado.detalles || {};
      const relaciones = resultado.relaciones || {};

      pintarResumen(deteccion, detalles);
      pintarDeteccion(deteccion);
      pintarMotores(deteccion.motores_que_detectan || []);
      pintarDetalles(detalles);
      pintarRelaciones(relaciones);
    }

    function pintarResumen(deteccion, detalles) {
      const estado = deteccion.estado_general || 'No encontrado';
      const detecciones = deteccion.detecciones || '0/0';
      const partes = detecciones.split('/');

      document.getElementById('scoreMain').textContent = partes[0] || '0';
      document.getElementById('scoreTotal').textContent = '/ ' + (partes[1] || '0');
      document.getElementById('nombreArchivo').textContent = detalles.nombre || '-';
      document.getElementById('sha256').textContent = detalles.sha256 || '-';
      document.getElementById('tamanoArchivo').textContent = detalles.tamano || '-';
      document.getElementById('ultimoAnalisis').textContent = detalles.ultimo_analisis || '-';

      const estadoEl = document.getElementById('estadoGeneral');
      estadoEl.textContent = estado;
      estadoEl.className = 'status ' + estado.toLowerCase().replaceAll(' ', '-');
    }

    function pintarDeteccion(d) {
      const estado = d.estado_general || '-';
      const claseEstado = claseValorEstado(estado);

      const tbody = document.getElementById('tablaDeteccion');
      tbody.innerHTML = `
        <tr>
          <th>Estado general</th>
          <td class="${claseEstado}">${escapar(estado)}</td>
        </tr>
        <tr>
          <th>Detecciones</th>
          <td>${escapar(d.detecciones || '-')}</td>
        </tr>
        <tr>
          <th>Etiqueta de amenaza</th>
          <td>${escapar(d.etiqueta_amenaza || '-')}</td>
        </tr>
        <tr>
          <th>Categoría</th>
          <td>${escapar(d.categoria || '-')}</td>
        </tr>
        <tr>
          <th>Acción recomendada</th>
          <td>${escapar(d.accion_recomendada || '-')}</td>
        </tr>
        <tr>
          <th>Origen del resultado</th>
          <td>${escapar(d.origen_resultado || '-')}</td>
        </tr>
      `;
    }

    function pintarMotores(motores) {
      const tbody = document.getElementById('tablaMotores');
      tbody.innerHTML = '';

      if (!motores.length) {
        tbody.innerHTML = '<tr><td class="empty">No hay motores con detección</td></tr>';
        return;
      }

      const nombres = motores.map(item => {
        const motor = item.motor || '-';
        const inicial = motor.charAt(0).toUpperCase();

        return `
          <span class="motor-badge" title="${escapar(item.deteccion || '')}">
            <span class="motor-logo">${escapar(inicial)}</span>
            ${escapar(motor)}
          </span>
        `;
      }).join('');

      tbody.innerHTML = `
        <tr>
          <td>
            <div class="motor-list">${nombres}</div>
          </td>
        </tr>
      `;
    }

    function pintarDetalles(d) {
      const masNombres = Array.isArray(d.mas_nombres_archivo)
        ? d.mas_nombres_archivo.join(', ')
        : '';

      pintarTabla('tablaDetalles', [
        ['Nombre', d.nombre],
        ['Tamaño', d.tamano],
        ['Tipo', d.tipo],
        ['MD5', d.md5],
        ['SHA1', d.sha1],
        ['SHA256', d.sha256],
        ['Primera vez visto', d.primera_vez_visto],
        ['Último análisis', d.ultimo_analisis],
        ['Fecha de escaneo en Zypher', d.fecha_escaneo_zypher],
        ['Más nombres de archivo', masNombres]
      ]);
    }

    function pintarRelaciones(r) {
      pintarLista('tablaDominios', r.dominios_contactados || []);
      pintarLista('tablaIps', r.ips_contactadas || []);
      pintarLista('tablaRelacionados', r.archivos_relacionados || []);
      pintarLista('tablaSoltados', r.archivos_soltados || []);
    }

    function pintarTabla(id, filas) {
      const tbody = document.getElementById(id);
      tbody.innerHTML = '';

      filas.forEach(([campo, valor]) => {
        tbody.innerHTML += `
          <tr>
            <th>${escapar(campo)}</th>
            <td>${escapar(valor || '-')}</td>
          </tr>
        `;
      });
    }

    function pintarLista(id, items) {
      const tbody = document.getElementById(id);
      tbody.innerHTML = '';

      if (!items.length) {
        tbody.innerHTML = '<tr><td class="empty">Sin datos</td></tr>';
        return;
      }

      items.forEach(item => {
        tbody.innerHTML += `
          <tr>
            <th>${escapar(item.valor || '-')}</th>
            <td>${escapar(String(item.detecciones ?? 0))} detecciones</td>
          </tr>
        `;
      });
    }

    function claseValorEstado(estado) {
      estado = String(estado).toLowerCase();

      if (estado.includes('peligroso')) return 'valor-peligroso';
      if (estado.includes('sospechoso')) return 'valor-sospechoso';
      if (estado.includes('limpio')) return 'valor-limpio';

      return '';
    }

    function mostrarError(texto) {
      message.textContent = texto;
      message.style.display = 'block';
    }

    function ocultarError() {
      message.textContent = '';
      message.style.display = 'none';
    }

    function escapar(valor) {
      return String(valor)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }
  </script>
</body>
</html>
