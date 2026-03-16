<?php
require_once '../config/config.php';
$pdo = getDBConnection();

try {
    // Add missing columns to training_participants
    $pdo->exec("ALTER TABLE training_participants ADD COLUMN enrolled_name VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE training_participants ADD COLUMN health_condition TEXT NULL");
    echo "Columns added.\n";
} catch (Exception $e) {
    // Will throw exception if columns already exist, which is fine
    echo "Notice: " . $e->getMessage() . "\n";
}
