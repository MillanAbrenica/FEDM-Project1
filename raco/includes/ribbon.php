<div class="ribbon">
  <div class="ribbon-group">
    <div class="ribbon-title">Project</div>
    <div class="ribbon-actions">
      <a href="../pages/workspace.php" class="ribbon-btn"><i class="fa-solid fa-plus"></i><span>New Project</span></a>
      <a href="../pages/projects.php" class="ribbon-btn"><i class="fa-solid fa-folder-open"></i><span>Open</span></a>
      <button type="button" class="ribbon-btn" onclick="window.print()"><i class="fa-solid fa-floppy-disk"></i><span>Save</span></button>
      <a href="../pages/projects.php" class="ribbon-btn"><i class="fa-solid fa-copy"></i><span>Save As</span></a>
    </div>
  </div>

  <div class="ribbon-group">
    <div class="ribbon-title">Data Clean</div>
    <div class="ribbon-actions">
      <button type="button" class="ribbon-btn" onclick="window.detectIssues && window.detectIssues()"><i class="fa-solid fa-magnifying-glass"></i><span>Detect Issues</span></button>
      <a href="../pages/clean.php" class="ribbon-btn"><i class="fa-solid fa-broom"></i><span>Clean</span></a>
      <button type="button" class="ribbon-btn" onclick="window.standardizeData && window.standardizeData()"><i class="fa-solid fa-scale-balanced"></i><span>Standardize</span></button>
      <button type="button" class="ribbon-btn" onclick="window.removeDuplicatesUI && window.removeDuplicatesUI()"><i class="fa-solid fa-clone"></i><span>Remove Duplicates</span></button>
    </div>
  </div>

  <div class="ribbon-group">
    <div class="ribbon-title">Filter</div>
    <div class="ribbon-actions">
      <button type="button" class="ribbon-btn" onclick="window.toggleAllFilters && window.toggleAllFilters()"><i class="fa-solid fa-filter"></i><span>Filter Rows</span></button>
      <button type="button" class="ribbon-btn" onclick="window.sortFirstTable && window.sortFirstTable()"><i class="fa-solid fa-sort"></i><span>Sort</span></button>
    </div>
  </div>

  <div class="ribbon-group">
    <div class="ribbon-title">Preview</div>
    <div class="ribbon-actions">
      <button type="button" class="ribbon-btn" onclick="location.reload()"><i class="fa-solid fa-rotate-right"></i><span>Refresh</span></button>
    </div>
  </div>

  <div class="ribbon-group">
    <div class="ribbon-title">Export</div>
    <div class="ribbon-actions">
      <button type="button" class="ribbon-btn" onclick="window.exportCurrent && window.exportCurrent('csv')"><i class="fa-solid fa-file-csv"></i><span>Export CSV</span></button>
      <button type="button" class="ribbon-btn" onclick="window.exportCurrent && window.exportCurrent('json')"><i class="fa-solid fa-file-code"></i><span>Export JSON</span></button>
    </div>
  </div>
</div>