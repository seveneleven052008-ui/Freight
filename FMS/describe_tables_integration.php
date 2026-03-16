<?php
require_once 'c:/xampp/htdocs/FMS/config/config.php';
$pdo = getDBConnection();
echo "--- skill_assessments ---\n";
$stmt = $pdo->query("DESCRIBE skill_assessments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "\n--- employee_competencies ---\n";
$stmt = $pdo->query("DESCRIBE employee_competencies");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "\n--- training_participants ---\n";
$stmt = $pdo->query("DESCRIBE training_participants");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
