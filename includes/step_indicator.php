<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentStep = $current_step ?? 1;
$steps = [
    1 => 'Upload',
    2 => 'Profile',
    3 => 'Clean',
    4 => 'Analyze',
    5 => 'Visualize',
];
?>
<div class="step-wrapper mb-4">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <?php foreach ($steps as $stepNumber => $label): ?>
            <?php
            $isActive = $stepNumber === $currentStep;
            $isDone = $stepNumber < $currentStep;
            ?>
            <div class="step-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $isDone ? 'done' : ''; ?>">
                <span class="step-index badge <?php echo $isActive ? 'text-bg-primary' : ($isDone ? 'text-bg-success' : 'text-bg-secondary'); ?>">
                    <?php echo $stepNumber; ?>
                </span>
                <span class="step-label"><?php echo htmlspecialchars($label); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>