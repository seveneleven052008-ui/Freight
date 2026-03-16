<?php
/**
 * Learning Management AJAX Handler
 */
require_once 'config/config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = getDBConnection();

try {
    switch ($action) {
        case 'enroll':
            enrollInCourse($pdo);
            break;
        case 'update_progress':
            updateCourseProgress($pdo);
            break;
        case 'submit_exam':
            submitExamination($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function enrollInCourse($pdo) {
    $courseId = $_POST['course_id'] ?? 0;
    $userId = $_SESSION['user_id'];

    if (!$courseId) {
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        return;
    }

    // Check if already enrolled
    $check = $pdo->prepare("SELECT id FROM course_enrollments WHERE course_id = ? AND employee_id = ?");
    $check->execute([$courseId, $userId]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Already enrolled']);
        return;
    }

    // Get module count
    $stmt = $pdo->prepare("SELECT modules_count FROM learning_courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();

    $enroll = $pdo->prepare("INSERT INTO course_enrollments (course_id, employee_id, progress, completed_modules, total_modules, status, enrolled_at) VALUES (?, ?, 0, 0, ?, 'Enrolled', NOW())");
    $enroll->execute([$courseId, $userId, $course['modules_count'] ?? 10]);

    echo json_encode(['success' => true, 'message' => 'Enrolled successfully']);
}

function updateCourseProgress($pdo) {
    $courseId = $_POST['course_id'] ?? 0;
    $completedModules = $_POST['completed_modules'] ?? 0;
    $progress = $_POST['progress'] ?? 0;
    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("UPDATE course_enrollments SET completed_modules = ?, progress = ?, last_accessed = NOW(), status = 'In Progress' WHERE course_id = ? AND employee_id = ?");
    $stmt->execute([$completedModules, $progress, $courseId, $userId]);

    if ($progress >= 100) {
        $stmt = $pdo->prepare("UPDATE course_enrollments SET status = 'Completed', completed_at = NOW() WHERE course_id = ? AND employee_id = ?");
        $stmt->execute([$courseId, $userId]);
        
        // Auto-schedule exam
        ensureExamScheduled($pdo, $courseId, $userId);
    }

    echo json_encode(['success' => true]);
}

function ensureExamScheduled($pdo, $courseId, $userId) {
    $check = $pdo->prepare("SELECT id FROM examinations WHERE course_id = ? AND employee_id = ?");
    $check->execute([$courseId, $userId]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO examinations (course_id, employee_id, exam_date, status, duration, passing_score, attempts_allowed) VALUES (?, ?, NOW(), 'Scheduled', '30 mins', 60, 3)");
        $stmt->execute([$courseId, $userId]);
    }
}

function submitExamination($pdo) {
    $examId = $_POST['exam_id'] ?? 0;
    $score = $_POST['score'] ?? 0;
    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT e.*, lc.title as course_name FROM examinations e JOIN learning_courses lc ON e.course_id = lc.id WHERE e.id = ? AND e.employee_id = ?");
    $stmt->execute([$examId, $userId]);
    $exam = $stmt->fetch();

    if (!$exam) {
        echo json_encode(['success' => false, 'message' => 'Exam not found']);
        return;
    }

    $status = ($score >= $exam['passing_score']) ? 'Passed' : 'Failed';
    
    $update = $pdo->prepare("UPDATE examinations SET score = ?, status = ? WHERE id = ?");
    $update->execute([$score, $status, $examId]);

    if ($status === 'Passed') {
        issueCertificate($pdo, $exam['course_id'], $userId, $exam['course_name'], $score);
        
        // Define badge icon based on course category
        $icon = 'fa-award';
        $cStmt = $pdo->prepare("SELECT category FROM learning_courses WHERE id = ?");
        $cStmt->execute([$exam['course_id']]);
        $courseCat = $cStmt->fetch();
        $category = $courseCat['category'] ?? '';
        
        if (strpos($category, 'Fleet') !== false) $icon = 'fa-truck';
        elseif (strpos($category, 'Safety') !== false) $icon = 'fa-shield-alt';
        elseif (strpos($category, 'Leadership') !== false) $icon = 'fa-crown';
        elseif (strpos($category, 'Logistics') !== false) $icon = 'fa-route';

        issueBadge($pdo, $userId, $exam['course_name'] . " Expert", "Successfully passed the final exam for " . $exam['course_name'], $icon);
    }

    echo json_encode(['success' => true, 'status' => $status, 'score' => $score]);
}

function issueCertificate($pdo, $courseId, $userId, $courseName, $score) {
    // Get instructor from course
    $cStmt = $pdo->prepare("SELECT instructor FROM learning_courses WHERE id = ?");
    $cStmt->execute([$courseId]);
    $course = $cStmt->fetch();
    $instructor = $course['instructor'] ?? 'LMS Instructor';

    $certNo = 'CERT-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $stmt = $pdo->prepare("INSERT INTO certificates (employee_id, course_id, certificate_name, certificate_id, issue_date, instructor, score) VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
    $stmt->execute([$userId, $courseId, $courseName . " Completion", $certNo, $instructor, $score]);
}

function issueBadge($pdo, $userId, $badgeName, $description, $icon = 'fa-award') {
    $stmt = $pdo->prepare("INSERT INTO badges (employee_id, badge_name, description, icon, earned_date) VALUES (?, ?, ?, ?, CURDATE())");
    $stmt->execute([$userId, $badgeName, $description, $icon]);
}
?>
