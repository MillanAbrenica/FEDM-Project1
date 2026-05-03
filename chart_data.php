<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['labels' => [], 'datasets' => []]);
    exit;
}

if (empty($_SESSION['cleaned_dataset'])) {
    echo json_encode(['labels' => [], 'datasets' => []]);
    exit;
}

$cleaned = $_SESSION['cleaned_dataset'];
$headers = $cleaned['headers'];
$rows = $cleaned['rows'];

$xColumn = (string) ($_POST['x_column'] ?? '');
$yColumn = (string) ($_POST['y_column'] ?? '');
$chartType = (string) ($_POST['chart_type'] ?? 'bar');

if ($xColumn === '' || !in_array($xColumn, $headers, true)) {
    echo json_encode(['labels' => [], 'datasets' => []]);
    exit;
}

$labels = [];
$datasetValues = [];

if ($chartType === 'pie') {
    $counts = [];
    foreach ($rows as $row) {
        $label = (string) ($row[$xColumn] ?? 'N/A');
        if ($label === '') {
            $label = 'N/A';
        }
        $counts[$label] = ($counts[$label] ?? 0) + 1;
    }

    $labels = array_keys($counts);
    $datasetValues = array_values($counts);

    echo json_encode([
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Distribution',
            'data' => $datasetValues,
            'backgroundColor' => [
                '#0d6efd',
                '#6ea8fe',
                '#9ec5fe',
                '#cfe2ff',
                '#adb5bd',
                '#198754',
                '#ffc107',
                '#dc3545'
            ],
        ]],
    ]);
    exit;
}

if ($chartType === 'line') {
    foreach ($rows as $row) {
        $labels[] = (string) ($row[$xColumn] ?? '');
        $value = $row[$yColumn] ?? null;
        $datasetValues[] = is_numeric((string) $value) ? (float) $value : null;
    }

    echo json_encode([
        'labels' => $labels,
        'datasets' => [[
            'label' => $yColumn !== '' ? $yColumn : 'Values',
            'data' => $datasetValues,
            'borderColor' => '#0d6efd',
            'backgroundColor' => 'rgba(13,110,253,0.15)',
            'fill' => false,
            'tension' => 0.2,
        ]],
    ]);
    exit;
}

$aggregates = [];
foreach ($rows as $row) {
    $label = (string) ($row[$xColumn] ?? 'N/A');
    if ($label === '') {
        $label = 'N/A';
    }

    $value = $row[$yColumn] ?? 0;
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;
    $aggregates[$label] = ($aggregates[$label] ?? 0.0) + $numeric;
}

$labels = array_keys($aggregates);
$datasetValues = array_values($aggregates);

echo json_encode([
    'labels' => $labels,
    'datasets' => [[
        'label' => ($yColumn !== '' ? $yColumn : 'Value') . ' (sum)',
        'data' => $datasetValues,
        'backgroundColor' => '#0d6efd',
        'borderColor' => '#0d6efd',
        'borderWidth' => 1,
    ]],
]);
