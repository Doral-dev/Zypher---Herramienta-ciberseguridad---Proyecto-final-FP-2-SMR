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
      font-size: 48px;
      margin-bottom: 15px;
    }

    .upload-box h2 {
      margin: 0 0 10px;
      font-size: 24px;
    }

    .upload-box p {
      color: #91a4c4;
      margin-bottom: 20px;
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
      font-size: 38px;
    }

    .score span {
      color: #aab8d3;
    }

    .file-info {
      flex: 1;
    }

    .status {
      display: inline-block;
      background: #3a1118;
      color: #ff5f68;
      border: 1px solid #ff5f68;
      padding: 8px 14px;
      border-radius: 8px;
      margin-bottom: 12px;
      font-weight: bold;
    }

    .file-info h2 {
      margin: 0 0 8px;
    }

    .hash {
      color: #91a4c4;
      word-break: break-all;
    }

    .tags {
      margin-top: 15px;
    }

    .tag {
      display: inline-block;
      background: #173a68;
      color: #b9d4ff;
      padding: 6px 10px;
      border-radius: 8px;
      margin-right: 6px;
      font-size: 14px;
    }

    .meta {
      display: flex;
      gap: 35px;
      color: #c5d1e8;
    }

    .tabs {
      display: flex;
      background: #0b1b31;
      border: 1px solid #233a5c;
      border-radius: 18px 18px 0 0;
      overflow: hidden;
    }

    .tab {
      padding: 16px 25px;
      color: #91a4c4;
      border-bottom: 3px solid transparent;
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
    }

    th {
      color: #91a4c4;
      font-weight: normal;
      width: 45%;
    }

    td {
      color: #fff;
    }

    tr:last-child th,
    tr:last-child td {
      border-bottom: 0;
    }

    .danger {
      color: #ff5f68;
      font-weight: bold;
    }

    @media (max-width: 800px) {
      .summary {
        flex-direction: column;
        align-items: flex-start;
      }

      .meta {
        flex-direction: column;
        gap: 8px;
      }

      .grid {
        grid-template-columns: 1fr;
      }

      h1 {
        font-size: 28px;
      }
    }
  </style>
</head>

<body>
  <main class="page">
    <h1>Módulo de escaneo de archivos</h1>

    <section class="upload-box">
      <div class="upload-icon">⬆️</div>
      <h2>Arrastra tu archivo aquí</h2>
      <p>Suelta el archivo para analizarlo</p>
      <button class="btn btn-secondary">Seleccionar archivo</button>
    </section>

    <div class="scan-action">
      <button class="btn btn-primary">Escanear archivo</button>
    </div>

    <section class="summary">
      <div class="score">
        <strong>64</strong>
        <span>/ 66</span>
      </div>

      <div class="file-info">
        <div class="status">Peligroso</div>
        <h2>eicar.com-24960</h2>
        <div class="hash">275a021bbfb6489e54d471899f7db9d1663fc695ec2fe2a2c4538aabf651fd0f</div>

        <div class="tags">
          <span class="tag">powershell</span>
          <span class="tag">known-distributor</span>
        </div>
      </div>

      <div class="meta">
        <div>
          <strong>68 B</strong><br>
          Tamaño
        </div>
        <div>
          <strong>Hace 4 minutos</strong><br>
          Último análisis
        </div>
      </div>
    </section>

    <nav class="tabs">
      <div class="tab active">Detección</div>
      <div class="tab">Detalles</div>
      <div class="tab">Relaciones</div>
    </nav>

    <section class="content">
      <div class="grid">
        <div class="panel">
          <h3>Resultado del análisis</h3>

          <table>
            <tr>
              <th>Estado general</th>
              <td class="danger">Peligroso</td>
            </tr>
            <tr>
              <th>Detecciones</th>
              <td class="danger">64/66</td>
            </tr>
            <tr>
              <th>Etiqueta de amenaza</th>
              <td>virus.eicar/test</td>
            </tr>
            <tr>
              <th>Categoría</th>
              <td>virus, troyano</td>
            </tr>
            <tr>
              <th>Acción recomendada</th>
              <td>Cuarentena</td>
            </tr>
            <tr>
              <th>Origen del resultado</th>
              <td>ClamAV + VirusTotal</td>
            </tr>
          </table>
        </div>

        <div class="panel">
          <h3>Motores que lo detectan</h3>

          <table>
            <tr>
              <th>Motor</th>
              <th>Detección</th>
            </tr>
            <tr>
              <td>ClamAV</td>
              <td>Eicar-Test-Signature</td>
            </tr>
            <tr>
              <td>Kaspersky</td>
              <td>EICAR-Test-File</td>
            </tr>
            <tr>
              <td>Microsoft</td>
              <td>Virus:DOS/EICAR_Test_File</td>
            </tr>
            <tr>
              <td>Malwarebytes</td>
              <td>EICAR-AV-Test</td>
            </tr>
            <tr>
              <td>BitDefender</td>
              <td>EICAR-Test-File</td>
            </tr>
          </table>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
