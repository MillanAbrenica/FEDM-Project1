<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
raco_require_login();
$pageTitle = 'Import Data';
$activePage = 'workspace';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Data Import</h1>
      <p class="page-subtitle">Drag and drop your CSV or XLSX dataset to begin profiling.</p>

      <section class="card" style="max-width: 820px;">
        <form id="uploadForm" enctype="multipart/form-data">
          <div id="uploadZone" class="upload-zone">
            <div class="big"><i class="fa-solid fa-cloud-arrow-up"></i> Drop file here or click to browse</div>
            <div class="small">Accepted: CSV, XLSX · Max size: 20MB</div>
            <input type="file" id="datasetFile" name="dataset_file" accept=".csv,.xlsx" style="display:none;">
          </div>
          <div class="progress">
            <div id="uploadProgressBar"></div>
          </div>
          <div id="uploadStatus" class="text-muted" style="margin-top:8px;">No file selected.</div>
          <div style="margin-top: 12px;">
            <button type="submit" class="btn btn-primary">Upload and Profile</button>
          </div>
        </form>
      </section>
    </div>
  </main>
</div>
<script src="../assets/js/upload.js"></script>
</body>

</html>