<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/data.php';
require_once __DIR__ . '/../config/clean_utils.php';
raco_require_login();
$pdo = raco_pdo();

$uploadId = (int) ($_GET['upload_id'] ?? 0);
if ($uploadId <= 0) {
  header('Location: projects.php');
  exit;
}

$stmt = $pdo->prepare('SELECT id, original_filename, row_count FROM uploads WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $uploadId, 'user_id' => (int) $_SESSION['user_id']]);
$upload = $stmt->fetch();
if (!$upload) {
  header('Location: projects.php');
  exit;
}

$rows = raco_get_dataset_rows($pdo, $uploadId, 'original');
if (!$rows) {
  header('Location: profile.php?upload_id=' . $uploadId);
  exit;
}
$headers = $rows ? array_keys($rows[0]) : [];
$preview = array_slice($rows, 0, 10);

// Analyze data quality
$quality = raco_analyze_data_quality($headers, $rows);
$duplicates = raco_find_duplicates($rows);
$profiles = raco_build_profiles($headers, $rows);

$pageTitle = 'Clean';
$activePage = 'projects';
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-shell">
    <?php require __DIR__ . '/../includes/ribbon.php'; ?>
    <div class="page-wrap">
      <h1 class="page-title">Data Cleaning</h1>
      <p class="page-subtitle">Configure cleaning operations for <?php echo htmlspecialchars((string) $upload['original_filename']); ?></p>

      <!-- Data Quality Summary -->
      <section class="card" style="margin-bottom: 20px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
        <h3>Data Quality Overview</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
          <div style="padding: 15px; background: white; border-radius: 5px; border-left: 4px solid #007bff;">
            <div style="font-size: 12px; color: #666;">Total Rows</div>
            <div style="font-size: 24px; font-weight: bold; color: #007bff;"><?php echo $quality['total_rows']; ?></div>
          </div>
          <div style="padding: 15px; background: white; border-radius: 5px; border-left: 4px solid #28a745;">
            <div style="font-size: 12px; color: #666;">Completeness</div>
            <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo $quality['completeness']; ?>%</div>
          </div>
          <div style="padding: 15px; background: white; border-radius: 5px; border-left: 4px solid #dc3545;">
            <div style="font-size: 12px; color: #666;">Missing Cells</div>
            <div style="font-size: 24px; font-weight: bold; color: #dc3545;"><?php echo $quality['null_cells']; ?></div>
          </div>
          <div style="padding: 15px; background: white; border-radius: 5px; border-left: 4px solid #ffc107;">
            <div style="font-size: 12px; color: #666;">Exact Duplicates</div>
            <div style="font-size: 24px; font-weight: bold; color: #ffc107;"><?php echo $duplicates['total_duplicates']; ?></div>
          </div>
        </div>
      </section>

      <!-- Column Quality Issues -->
      <section class="card" style="margin-bottom: 20px;">
        <h3>Column Quality Issues</h3>
        <div class="table-wrap">
          <table style="font-size: 13px;">
            <thead>
              <tr>
                <th>Column</th>
                <th>Type</th>
                <th>Missing</th>
                <th>Unique</th>
                <th>Cardinality</th>
                <th>Sample Values</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($profiles as $profile): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($profile['column_name']); ?></strong></td>
                  <td><span style="background: #e9ecef; padding: 3px 8px; border-radius: 3px; font-size: 11px;"><?php echo htmlspecialchars($profile['detected_type']); ?></span></td>
                  <td>
                    <?php
                    $nullPct = $profile['null_count'] > 0 ? round(($profile['null_count'] / ($profile['null_count'] + $profile['non_null_count'])) * 100, 1) : 0;
                    $color = $nullPct > 20 ? '#dc3545' : ($nullPct > 10 ? '#ffc107' : '#28a745');
                    ?>
                    <span style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo $profile['null_count']; ?> (<?php echo $nullPct; ?>%)</span>
                  </td>
                  <td><?php echo $profile['unique_count']; ?></td>
                  <td>
                    <?php
                    $cardinality = $profile['non_null_count'] > 0 ? round(($profile['unique_count'] / $profile['non_null_count']) * 100, 1) : 0;
                    echo $cardinality;
                    ?>%
                  </td>
                  <td style="font-size: 11px; color: #666;">
                    <?php echo implode(', ', array_map(
                      static fn($s) => htmlspecialchars(strlen($s) > 20 ? substr($s, 0, 20) . '...' : $s),
                      $profile['sample_values']
                    )); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <div class="grid-2 two-col">
        <div>
          <section class="card">
            <h3>Original Data Preview (Top 10 Rows)</h3>
            <div class="table-wrap" style="margin-top:10px;">
              <table data-interactive="1">
                <thead>
                  <tr><?php foreach ($headers as $h): ?><th><?php echo htmlspecialchars($h); ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                  <?php foreach ($preview as $row): ?>
                    <tr>
                      <?php foreach ($headers as $h): ?>
                        <td><?php echo htmlspecialchars((string) ($row[$h] ?? '')); ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>

          <?php if ($duplicates['duplicate_count'] > 0): ?>
            <section class="card" style="margin-top: 20px;">
              <h3>Sample Duplicates Found (<?php echo $duplicates['total_duplicates']; ?> total)</h3>
              <p style="color: #666; font-size: 13px;">First 3 duplicate groups:</p>
              <?php
              $sampleDupes = array_slice($duplicates['duplicate_groups'], 0, 3);
              foreach ($sampleDupes as $groupIdx => $indices):
                if ($groupIdx >= 3) break;
              ?>
                <div style="margin: 10px 0; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 12px;">
                  <strong>Duplicate Group <?php echo $groupIdx + 1; ?> (<?php echo count($indices); ?> instances)</strong>
                  <?php foreach (array_slice($indices, 0, 2) as $idx): ?>
                    <div style="margin-top: 5px; padding: 5px; background: white;">
                      <?php echo implode(' | ', array_map(
                        static fn($v) => htmlspecialchars(strlen((string)$v) > 30 ? substr((string)$v, 0, 30) . '...' : (string)$v),
                        $rows[$idx] ?? []
                      )); ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </section>
          <?php endif; ?>
        </div>

        <section class="card collapsible-panel" data-collapsed="false">
          <div class="panel-header">
            <h3>Cleaning Operations</h3>
            <button type="button" class="toggle-panel"><i class="fa-solid fa-chevron-up"></i><span>Hide</span></button>
          </div>
          <div class="panel-body">
            <form id="cleanForm" method="post" action="../actions/clean_handler.php" class="stack">
              <input type="hidden" name="upload_id" value="<?php echo $uploadId; ?>">

              <h3>Global Operations</h3>
              <div>
                <label><input type="checkbox" id="remove_duplicates" name="remove_duplicates" value="1"> Remove exact duplicates (<?php echo $duplicates['total_duplicates']; ?> duplicates found)</label>
                <label><input type="checkbox" id="trim_whitespace" name="trim_whitespace" value="1"> Trim whitespace</label>
                <label><input type="checkbox" id="standardize_dates" name="standardize_dates" value="1"> Standardize dates (YYYY-MM-DD)</label>
                <label><input type="checkbox" id="normalize_spaces" name="normalize_spaces" value="1"> Normalize spaces (collapse multiple)</label>
                <label><input type="checkbox" id="remove_accents" name="remove_accents" value="1"> Remove accents (é→e, ñ→n)</label>
              </div>

              <h3>String Processing</h3>
              <div>
                <label>String Case</label>
                <select name="string_case">
                  <option value="none">None</option>
                  <option value="upper">UPPER</option>
                  <option value="lower">lower</option>
                  <option value="title">Title Case</option>
                </select>
              </div>

              <h3>Outlier Detection & Removal</h3>
              <div>
                <label>
                  <input type="checkbox" id="detect_outliers" name="detect_outliers" value="1">
                  Detect and remove outliers
                </label>
                <div style="margin-left: 20px; margin-top: 10px;">
                  <label>Method:
                    <select name="outlier_method">
                      <option value="iqr">IQR Method</option>
                      <option value="zscore">Z-Score Method</option>
                    </select>
                  </label>
                  <label style="display: block; margin-top: 5px;">IQR Multiplier:
                    <input type="number" name="iqr_multiplier" value="1.5" step="0.1" min="0.5" max="5" style="width: 60px;">
                  </label>
                </div>
              </div>

              <h3>Per Column Rules</h3>
              <div class="table-wrap">
                <table style="font-size: 12px;">
                  <thead>
                    <tr>
                      <th>Column</th>
                      <th>Missing Value</th>
                      <th>Custom Fill</th>
                      <th>Cast Type</th>
                      <th>Decimals</th>
                      <th>Min</th>
                      <th>Max</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($headers as $h): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($h); ?></td>
                        <td>
                          <select name="missing_strategy[<?php echo htmlspecialchars($h); ?>]">
                            <option value="none">None</option>
                            <option value="drop">Drop row</option>
                            <option value="mean">Fill mean</option>
                            <option value="median">Fill median</option>
                            <option value="mode">Fill mode</option>
                            <option value="custom">Custom</option>
                          </select>
                        </td>
                        <td><input type="text" name="missing_custom[<?php echo htmlspecialchars($h); ?>]" placeholder="Value"></td>
                        <td>
                          <select name="cast_type[<?php echo htmlspecialchars($h); ?>]">
                            <option value="">No cast</option>
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="float">float</option>
                            <option value="date">date</option>
                          </select>
                        </td>
                        <td>
                          <input type="number" name="decimals[<?php echo htmlspecialchars($h); ?>]" min="0" max="10" style="width: 50px;">
                        </td>
                        <td><input type="number" step="any" name="range_min[<?php echo htmlspecialchars($h); ?>]" style="width: 60px;"></td>
                        <td><input type="number" step="any" name="range_max[<?php echo htmlspecialchars($h); ?>]" style="width: 60px;"></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <h3>Advanced Filtering</h3>
              <div>
                <label>Regex pattern filter (keep rows matching this pattern)</label>
                <input type="text" name="regex_pattern" placeholder="/pattern/i" style="width: 100%;">
              </div>

              <h3>Validation Rules</h3>
              <div>
                <label style="display: block; margin-bottom: 8px;">
                  <input type="checkbox" id="validate_emails" name="validate_emails" value="1">
                  Validate email columns (remove invalid)
                </label>
                <div style="margin-left: 20px; margin-top: 5px; margin-bottom: 10px;">
                  <label style="display: block;">Email columns (comma-separated):
                    <input type="text" name="email_columns" placeholder="email, contact_email" style="width: 100%;">
                  </label>
                </div>

                <label style="display: block; margin-bottom: 8px;">
                  <input type="checkbox" id="validate_urls" name="validate_urls" value="1">
                  Validate URL columns (remove invalid)
                </label>
                <div style="margin-left: 20px; margin-top: 5px; margin-bottom: 10px;">
                  <label style="display: block;">URL columns (comma-separated):
                    <input type="text" name="url_columns" placeholder="website, link" style="width: 100%;">
                  </label>
                </div>

                <label style="display: block;">
                  <input type="checkbox" id="validate_phones" name="validate_phones" value="1">
                  Validate phone columns (remove invalid)
                </label>
                <div style="margin-left: 20px; margin-top: 5px;">
                  <label style="display: block;">Phone columns (comma-separated):
                    <input type="text" name="phone_columns" placeholder="phone, mobile" style="width: 100%;">
                  </label>
                </div>
              </div>

              <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Apply Cleaning</button>
            </form>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/table.js"></script>
<script src="../assets/js/clean.js"></script>
</body>

</html>