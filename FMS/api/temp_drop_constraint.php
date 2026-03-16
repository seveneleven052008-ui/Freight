<?php
require_once '../config/config.php';
$pdo = getDBConnection();

try {
    // We need to drop the existing foreign keys on training_participants
    // Usually they are named something like training_participants_ibfk_1
    // Let's just query information_schema to find the exact FK name
    
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'training_participants' 
        AND TABLE_SCHEMA = 'freight_hr_system'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($fks as $fk) {
        $pdo->exec("ALTER TABLE training_participants DROP FOREIGN KEY `$fk`");
    }
    
    // Now drop the unique index
    $pdo->exec("ALTER TABLE training_participants DROP INDEX unique_participant");
    echo "Successfully dropped unique_participant constraint.\n";
    
    // Now recreate the foreign keys
    $pdo->exec("ALTER TABLE training_participants ADD CONSTRAINT fk_tp_prog FOREIGN KEY (training_program_id) REFERENCES training_programs(id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE training_participants ADD CONSTRAINT fk_tp_emp FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE");
    echo "Successfully recreated foreign keys.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
