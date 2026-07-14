<?php
require_once __DIR__ . '/../db.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$previewStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM courses WHERE (tutorial_text IS NULL OR TRIM(tutorial_text) = \'\') AND description IS NOT NULL AND TRIM(description) <> \'\'');
$previewStmt->execute();
$rowsToUpdate = (int)$previewStmt->fetchColumn();

if ($rowsToUpdate === 0) {
    echo "No course rows require tutorial_text backfill.\n";
    exit(0);
}

$updateStmt = $pdo->prepare('UPDATE courses SET tutorial_text = description WHERE (tutorial_text IS NULL OR TRIM(tutorial_text) = \'\') AND description IS NOT NULL AND TRIM(description) <> \'\'');
$updateStmt->execute();

$updatedCount = $updateStmt->rowCount();

$remainingStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM courses WHERE (tutorial_text IS NULL OR TRIM(tutorial_text) = \'\') AND description IS NOT NULL AND TRIM(description) <> \'\'');
$remainingStmt->execute();
$remainingCount = (int)$remainingStmt->fetchColumn();

echo "Backfilled tutorial_text for {$updatedCount} course row(s).\n";
echo "Remaining rows that still need a tutorial_text backfill: {$remainingCount}.\n";
