<?php
require_once '../config/config.php';
$pdo = getDBConnection();

try {
    $pdo->exec("ALTER TABLE training_programs ADD COLUMN competency_id INT NULL");
    $pdo->exec("ALTER TABLE training_programs ADD FOREIGN KEY (competency_id) REFERENCES competency_matrix(id) ON DELETE SET NULL");
    echo "Successfully updated training_programs table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
