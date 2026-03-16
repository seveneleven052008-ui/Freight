<?php
require_once 'config/config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = getDBConnection();

try {
    switch ($action) {
        case 'get_dev_plan':
            getDevPlan($pdo);
            break;
        case 'initiate_assessment_from_gap':
            initiateAssessmentFromGap($pdo);
            break;
        case 'get_assessment_details':
            getAssessmentDetails($pdo);
            break;
        case 'submit_assessment':
            submitAssessment($pdo);
            break;
        case 'schedule_assessment':
            scheduleAssessment($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Competency AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred processing your request.']);
}

function getDevPlan($pdo) {
    $gap_id = $_GET['gap_id'] ?? 0; // This is the employee's user ID from the UI loop
    
    if (empty($gap_id)) {
        echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
        return;
    }

    // Find all competencies where this employee has a gap
    $gapsStmt = $pdo->prepare("
        SELECT ec.competency_id, cm.competency
        FROM employee_competencies ec
        JOIN competency_matrix cm ON ec.competency_id = cm.id
        WHERE ec.employee_id = ? AND ec.has_gap = 1
    ");
    $gapsStmt->execute([$gap_id]);
    $gaps = $gapsStmt->fetchAll(PDO::FETCH_ASSOC);

    $recommendations = [];

    if (count($gaps) > 0) {
        // Extract just the IDs
        $competencyIds = array_column($gaps, 'competency_id');
        $placeholders = str_repeat('?,', count($competencyIds) - 1) . '?';

        // Find available training programs that fulfill these competencies
        // We only want programs that are 'Upcoming' or 'In Progress' 
        // AND where the employee is NOT ALREADY enrolled.
        $trainingsStmt = $pdo->prepare("
            SELECT tp.id, tp.title, cm.competency as competency_name
            FROM training_programs tp
            JOIN competency_matrix cm ON tp.competency_id = cm.id
            WHERE tp.competency_id IN ($placeholders)
              AND tp.status != 'Completed'
              AND NOT EXISTS (
                  SELECT 1 FROM training_participants tpar
                  WHERE tpar.training_program_id = tp.id AND tpar.employee_id = ?
              )
        ");
        
        // Merge the competency IDs with the employee ID at the end
        $params = array_merge($competencyIds, [$gap_id]);
        $trainingsStmt->execute($params);
        $recommendations = $trainingsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'employee_id' => $gap_id,
        'recommendations' => $recommendations
    ]);
}

function initiateAssessmentFromGap($pdo) {
    $employeeId = $_POST['employee_id'] ?? 0;
    if (!$employeeId) {
        echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
        return;
    }

    // Get current gaps
    $stmt = $pdo->prepare("SELECT role, department, critical_gaps FROM competency_gaps WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    $gapData = $stmt->fetch();

    if (!$gapData) {
        // Fallback to user table
        $stmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
        $stmt->execute([$employeeId]);
        $dep = $stmt->fetchColumn();
        $gapData = ['role' => 'Employee', 'department' => $dep, 'critical_gaps' => ''];
    }

    $pdo->beginTransaction();
    try {
        // Create assessment
        $ins = $pdo->prepare("INSERT INTO skill_assessments (employee_id, role, assessment_date, status) VALUES (?, ?, CURDATE(), 'In Progress')");
        $ins->execute([$employeeId, $gapData['role']]);
        $assessmentId = $pdo->lastInsertId();

        // Create categories based on gaps
        if (!empty($gapData['critical_gaps'])) {
            $gaps = explode(', ', $gapData['critical_gaps']);
            $catIns = $pdo->prepare("INSERT INTO assessment_categories (assessment_id, category_name, score, level) VALUES (?, ?, 0, 'Beginner')");
            foreach ($gaps as $gapName) {
                $catIns->execute([$assessmentId, trim($gapName)]);
            }
        } else {
            // Default category for new talent assessments
            $catIns = $pdo->prepare("INSERT INTO assessment_categories (assessment_id, category_name, score, level) VALUES (?, 'General Competency', 0, 'Beginner')");
            $catIns->execute([$assessmentId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'assessment_id' => $assessmentId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getAssessmentDetails($pdo) {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM assessment_categories WHERE assessment_id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function submitAssessment($pdo) {
    $assessmentId = $_POST['assessment_id'] ?? 0;
    $scores = $_POST['score'] ?? [];
    $levels = $_POST['level'] ?? [];

    if (!$assessmentId) {
        echo json_encode(['success' => false, 'message' => 'Assessment ID is required']);
        return;
    }

    $pdo->beginTransaction();
    try {
        // 1. Update categories
        $totalScore = 0;
        $count = 0;
        $catUpdate = $pdo->prepare("UPDATE assessment_categories SET score = ?, level = ? WHERE id = ?");
        
        // We need employee_id for updating matrix
        $saStmt = $pdo->prepare("SELECT employee_id FROM skill_assessments WHERE id = ?");
        $saStmt->execute([$assessmentId]);
        $employeeId = $saStmt->fetchColumn();

        foreach ($scores as $catId => $score) {
            $level = $levels[$catId];
            $catUpdate->execute([$score, $level, $catId]);
            $totalScore += $score;
            $count++;

            // 2. Update employee_competencies (Matrix)
            // Need competency_id. Assuming category_name matches competency name in matrix
            $getCatName = $pdo->prepare("SELECT category_name FROM assessment_categories WHERE id = ?");
            $getCatName->execute([$catId]);
            $catName = $getCatName->fetchColumn();

            $getCompId = $pdo->prepare("SELECT id, required_level FROM competency_matrix WHERE competency = ?");
            $getCompId->execute([$catName]);
            $comp = $getCompId->fetch(PDO::FETCH_ASSOC);

            if ($comp) {
                $compId = $comp['id'];
                $requiredLevel = $comp['required_level'];
                
                // Compare levels
                $levelMap = ['Beginner' => 1, 'Intermediate' => 2, 'Advanced' => 3, 'Expert' => 4];
                $hasGap = ($levelMap[$level] < $levelMap[$requiredLevel]) ? 1 : 0;


                // Check if entry exists in employee_competencies
                $checkEC = $pdo->prepare("SELECT id FROM employee_competencies WHERE employee_id = ? AND competency_id = ?");
                $checkEC->execute([$employeeId, $compId]);
                
                if ($checkEC->fetch()) {
                    $updEC = $pdo->prepare("UPDATE employee_competencies SET level = ?, has_gap = ? WHERE employee_id = ? AND competency_id = ?");
                    $updEC->execute([$level, $hasGap, $employeeId, $compId]);
                } else {
                    $insEC = $pdo->prepare("INSERT INTO employee_competencies (employee_id, competency_id, level, has_gap) VALUES (?, ?, ?, ?)");
                    $insEC->execute([$employeeId, $compId, $level, $hasGap]);
                }
            }
        }

        // 3. Update overall assessment
        $overallScore = $count > 0 ? round($totalScore / $count) : 0;
        $updSA = $pdo->prepare("UPDATE skill_assessments SET overall_score = ?, status = 'Completed' WHERE id = ?");
        $updSA->execute([$overallScore, $assessmentId]);

        // 4. Recalculate Competency Gaps table for this employee
        recalculateGaps($pdo, $employeeId);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function recalculateGaps($pdo, $employeeId) {
    // Get all competencies for the employee
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(has_gap) as gaps FROM employee_competencies WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    $stats = $stmt->fetch();
    
    $total = $stats['total'] ?: 1;
    $gapsCount = $stats['gaps'] ?: 0;
    $met = $total - $gapsCount;
    $gapPercentage = round(($gapsCount / $total) * 100);

    // Get list of critical gaps (names of competencies with has_gap = 1)
    $stmt = $pdo->prepare("
        SELECT cm.competency 
        FROM employee_competencies ec 
        JOIN competency_matrix cm ON ec.competency_id = cm.id 
        WHERE ec.employee_id = ? AND ec.has_gap = 1
    ");
    $stmt->execute([$employeeId]);
    $gapNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $criticalGaps = implode(', ', $gapNames);

    // Check if record exists in competency_gaps
    $check = $pdo->prepare("SELECT id FROM competency_gaps WHERE employee_id = ?");
    $check->execute([$employeeId]);
    
    if ($check->fetch()) {
        $upd = $pdo->prepare("
            UPDATE competency_gaps 
            SET required_competencies = ?, 
                current_competencies = ?, 
                gap_percentage = ?, 
                critical_gaps = ? 
            WHERE employee_id = ?
        ");
        $upd->execute([$total, $met, $gapPercentage, $criticalGaps, $employeeId]);
    } else {
        // Get user details for new record
        $uStmt = $pdo->prepare("SELECT role, department FROM users WHERE id = ?");
        $uStmt->execute([$employeeId]);
        $u = $uStmt->fetch();
        
        $ins = $pdo->prepare("
            INSERT INTO competency_gaps (employee_id, role, department, required_competencies, current_competencies, gap_percentage, critical_gaps)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$employeeId, $u['role'] ?? 'Employee', $u['department'] ?? '', $total, $met, $gapPercentage, $criticalGaps]);
    }
}
