<?php
require_once '../config/config.php';
$pdo = getDBConnection();

// Set dummy data to test integration
$pdo->exec("
    INSERT IGNORE INTO competency_matrix (id, competency, required_level) VALUES (99, 'Test Integration Skill', 'Advanced');
    INSERT IGNORE INTO employee_competencies (competency_id, employee_id, level, has_gap) VALUES (99, 1, 'Beginner', 1);
    
    INSERT IGNORE INTO training_programs (id, title, category, status, start_date, end_date, competency_id) 
    VALUES (999, 'Test Integration Training', 'Testing', 'In Progress', '2025-01-01', '2025-12-31', 99);
    
    INSERT IGNORE INTO training_participants (id, training_program_id, employee_id, completion_percentage, status) 
    VALUES (9999, 999, 1, 0, 'In Progress');
    
    -- Give employee a base gap record
    INSERT IGNORE INTO competency_gaps (employee_id, required_competencies, current_competencies, gap_percentage)
    VALUES (1, 1, 0, 100);
");

echo "Test Data created. Now completing the training...\n";

// simulate completion POST request logic directly to verify DB hooks
$_POST['id'] = 9999;
$_POST['completion_percentage'] = 100;

require_once '../training-ajax.php'; // wait, it echoes. Let's not require it if it echoes JSON immediately. We can just test the function directly.
