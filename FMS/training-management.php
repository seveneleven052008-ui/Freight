<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'Training Management';
$pdo = getDBConnection();

$activeTab = $_GET['tab'] ?? 'orientation';
$scheduleView = $_GET['view'] ?? 'week'; // week or month
$searchQuery = $_GET['search'] ?? '';
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
$roleValue = strtolower($currentUser['role'] ?? '');
$isAdmin = in_array($roleValue, ['admin', 'administrator', 'hr manager', 'hr1 manager']) || (($currentUser['username'] ?? '') === 'admin');

// Get training programs - Filtered for Orientation
$programs = $pdo->query("
    SELECT tp.*, 
           COUNT(DISTINCT tpar.id) as participants,
           AVG(tpar.completion_percentage) as avg_completion
    FROM training_programs tp
    LEFT JOIN training_participants tpar ON tp.id = tpar.training_program_id
    WHERE tp.is_recommendation = 0
    GROUP BY tp.id
    ORDER BY tp.start_date DESC
")->fetchAll();

// Get training schedule with date filtering
$scheduleDateFilter = $scheduleView == 'week' 
    ? "AND ts.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
    : "AND ts.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";

$programFilter = '';
if (isset($_GET['program']) && is_numeric($_GET['program'])) {
    $programFilter = ' AND ts.training_program_id = ' . intval($_GET['program']);
}

// build schedule query; if filtering by program show all its sessions, otherwise apply date window
if ($programFilter) {
    $schedule = $pdo->query("
        SELECT ts.*, tp.title as program_title, tp.description
        FROM training_schedule ts
        JOIN training_programs tp ON ts.training_program_id = tp.id
        WHERE ts.training_program_id = " . intval($_GET['program']) . "
        ORDER BY ts.session_date, ts.session_time ASC
    ")->fetchAll();
} else {
    $schedule = $pdo->query("
        SELECT ts.*, tp.title as program_title, tp.description
        FROM training_schedule ts
        JOIN training_programs tp ON ts.training_program_id = tp.id
        WHERE ts.session_date >= CURDATE() $scheduleDateFilter $programFilter
        ORDER BY ts.session_date, ts.session_time ASC
    ")->fetchAll();
}

// if user has a program filter, check whether current user is enrolled and count sessions
$isEnrolled = false;
$myEnrollment = null;
$sessionCount = 0;
if (!empty($programFilter)) {
    // derive program id from GET param (already validated earlier)
    $progId = intval($_GET['program']);
    $stmt = $pdo->prepare("SELECT * FROM training_participants WHERE training_program_id = ? AND employee_id = ?");
    $stmt->execute([$progId, $_SESSION['user_id']]);
    $myEnrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($myEnrollment) {
        $isEnrolled = true;
    }
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM training_schedule WHERE training_program_id = ?");
    $cntStmt->execute([$progId]);
    $sessionCount = (int)$cntStmt->fetchColumn();
}


// Get all employees with training records statistics
if ($searchQuery) {
    $employeeRecords = $pdo->prepare("
        SELECT 
            tpar.id as participant_id,
            u.id as user_id,
            u.full_name as user_full_name,
            u.employee_id,
            COALESCE(tpar.enrolled_name, u.full_name) as display_name,
            tp.title as training_title,
            tpar.status,
            tpar.completion_percentage,
            tpar.enrolled_at,
            tpar.completed_at,
            (SELECT COUNT(*) FROM certificates c WHERE c.employee_id = u.id) as certifications_count
        FROM training_participants tpar
        JOIN users u ON tpar.employee_id = u.id
        JOIN training_programs tp ON tpar.training_program_id = tp.id
        WHERE u.role != 'admin' AND (u.full_name LIKE :searchName OR u.employee_id LIKE :searchId OR tpar.enrolled_name LIKE :searchEnrolled)
        ORDER BY tpar.enrolled_at DESC
    ");
    $employeeRecords->execute([
        'searchName' => "%$searchQuery%",
        'searchId'   => "%$searchQuery%",
        'searchEnrolled' => "%$searchQuery%"
    ]);
} else {
    $employeeRecords = $pdo->query("
        SELECT 
            tpar.id as participant_id,
            u.id as user_id,
            u.full_name as user_full_name,
            u.employee_id,
            COALESCE(tpar.enrolled_name, u.full_name) as display_name,
            tp.title as training_title,
            tpar.status,
            tpar.completion_percentage,
            tpar.enrolled_at,
            tpar.completed_at,
            (SELECT COUNT(*) FROM certificates c WHERE c.employee_id = u.id) as certifications_count
        FROM training_participants tpar
        JOIN users u ON tpar.employee_id = u.id
        JOIN training_programs tp ON tpar.training_program_id = tp.id
        WHERE u.role != 'admin'
        ORDER BY tpar.enrolled_at DESC
    ");
}
$allEmployeeRecords = $employeeRecords->fetchAll();

// Get training records for current user
$trainingRecords = $pdo->prepare("
    SELECT tp.*, tpar.completion_percentage, tpar.status
    FROM training_participants tpar
    JOIN training_programs tp ON tpar.training_program_id = tp.id
    WHERE tpar.employee_id = ?
    ORDER BY tp.start_date DESC
");
$trainingRecords->execute([$_SESSION['user_id']]);
$myRecords = $trainingRecords->fetchAll();

// build a set of program IDs the current user is enrolled in
$enrolledProgramIds = array_column($myRecords, 'id');
// map program_id => current user's participant status and completion (for display on cards)
$myParticipantByProgram = [];
foreach ($myRecords as $r) {
    $myParticipantByProgram[$r['id']] = ['status' => $r['status'], 'completion_percentage' => $r['completion_percentage']];
}

// Handle AJAX request for employee history
$employeeId = $_GET['employee_id'] ?? null;
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if ($isAjax && $employeeId) {
    $employeeHistory = $pdo->prepare("
        SELECT tp.*, tpar.completion_percentage, tpar.status, tpar.enrolled_at, tpar.completed_at
        FROM training_participants tpar
        JOIN training_programs tp ON tpar.training_program_id = tp.id
        WHERE tpar.employee_id = ?
        ORDER BY tpar.enrolled_at DESC
    ");
    $employeeHistory->execute([$employeeId]);
    $historyRecords = $employeeHistory->fetchAll();
    
    header('Content-Type: text/html');
    if (empty($historyRecords)) {
        echo '<div class="text-center py-8 text-gray-500">
            <i class="fas fa-inbox text-4xl mb-4 text-gray-400"></i>
            <p>No training history found for this employee.</p>
        </div>';
    } else {
        echo '<div class="space-y-4">';
        foreach ($historyRecords as $record) {
            echo '<div class="border border-gray-200 rounded-lg p-6">';
            echo '<div class="flex items-start justify-between mb-4">';
            echo '<div class="flex-1">';
            echo '<h4 class="text-lg font-semibold text-gray-900 mb-1">' . htmlspecialchars($record['title']) . '</h4>';
            echo '<p class="text-gray-600 text-sm mb-2">Category: ' . htmlspecialchars($record['category']) . '</p>';
            echo '</div>';
            $comp = intval($record['completion_percentage']);
            $displayStatus = $comp == 0 ? 'Upcoming' : ($comp == 100 ? 'Completed' : 'In Progress');
            echo '<span class="px-3 py-1 rounded-full text-sm ' . getStatusBadge($displayStatus) . '">';
            echo htmlspecialchars($displayStatus);
            echo '</span>';
            echo '</div>';
            
            echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">';
            echo '<div class="bg-green-50 p-3 rounded-lg">';
            echo '<p class="text-gray-600 text-sm mb-1">Completion</p>';
            echo '<p class="text-green-700 font-semibold">' . $record['completion_percentage'] . '%</p>';
            echo '</div>';
            echo '<div class="bg-blue-50 p-3 rounded-lg">';
            echo '<p class="text-gray-600 text-sm mb-1">Status</p>';
            echo '<p class="text-blue-700">' . htmlspecialchars($record['status']) . '</p>';
            echo '</div>';
            echo '<div class="bg-gray-50 p-3 rounded-lg">';
            echo '<p class="text-gray-600 text-sm mb-1">Enrolled</p>';
            echo '<p class="text-gray-900 text-sm">' . formatDate($record['enrolled_at']) . '</p>';
            echo '</div>';
            if ($record['completed_at']) {
                echo '<div class="bg-purple-50 p-3 rounded-lg">';
                echo '<p class="text-gray-600 text-sm mb-1">Completed</p>';
                echo '<p class="text-purple-700 text-sm">' . formatDate($record['completed_at']) . '</p>';
                echo '</div>';
            }
            echo '</div>';
            
            if ($record['description']) {
                echo '<p class="text-gray-600 text-sm">' . htmlspecialchars($record['description']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    exit;
}

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Training Management</h1>
        <p class="text-gray-600">
            Manage training programs, schedules, and monitor employee development
        </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <div class="flex gap-4">
            <a href="?tab=orientation" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'orientation' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Training Orientation</span>
                </div>
            </a>
            <a href="?tab=schedule" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'schedule' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-calendar"></i>
                    <span>Schedule</span>
                </div>
            </a>
            <a href="?tab=records" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'records' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-file-alt"></i>
                    <span>Training Records</span>
                </div>
            </a>

            <a href="?tab=recommendations" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'recommendations' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-chart-line"></i>
                    <span>Recommendations</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Training Programs Tab -->
    <?php if ($activeTab == 'orientation'): ?>
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Training Orientation</h2>
                <?php if ($isAdmin): ?>
                    <button onclick="showCreateProgramModal()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-plus"></i>
                        <span>Create Orientation</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($programs as $program): ?>
                    <div class="border border-gray-200 rounded-lg p-6 hover:border-indigo-300 transition-colors">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($program['title']); ?></h3>
                                <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm">
                                    <?php echo htmlspecialchars($program['category']); ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php
                                $comp = isset($myParticipantByProgram[$program['id']]) ? round($myParticipantByProgram[$program['id']]['completion_percentage']) : round($program['avg_completion'] ?? 0);
                                $displayStatus = $comp == 0 ? 'Upcoming' : ($comp == 100 ? 'Completed' : 'In Progress');
                                ?>
                                <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($displayStatus); ?>">
                                    <?php echo htmlspecialchars($displayStatus); ?>
                                </span>
                                <div class="relative">
                                    <?php if ($isAdmin): ?>
                                        <button onclick="showProgramActions(<?php echo $program['id']; ?>)" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                    <?php endif; ?>
                                    <div id="actions-<?php echo $program['id']; ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                        <button onclick="editProgram(<?php echo $program['id']; ?>)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-edit mr-2"></i> Edit
                                        </button>
                                        <button onclick="manageParticipants(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['title']); ?>')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-users mr-2"></i> Manage Participants
                                        </button>
                                        <button onclick="addSchedule(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['title']); ?>')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-calendar-plus mr-2"></i> Add Schedule
                                        </button>
                                        <button onclick="retakeOrientation(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['title']); ?>')" class="w-full text-left px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50">
                                            <i class="fas fa-redo mr-2"></i> Retake Orientation
                                        </button>
<?php if (!in_array($program['id'], $enrolledProgramIds)): ?>
                                        <button onclick="showTakeTrainingModal(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['title']); ?>')" class="w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                                            <i class="fas fa-play mr-2"></i> Take Training
                                        </button>
<?php else: ?>
                                        <button disabled class="w-full text-left px-4 py-2 text-sm text-gray-400">
                                            <i class="fas fa-check mr-2"></i> Enrolled
                                        </button>
<?php endif;?>
                                        <hr class="my-1">
                                        <button onclick="deleteProgram(<?php echo $program['id']; ?>)" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fas fa-trash mr-2"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Duration</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($program['duration']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Participants</p>
                                <p class="text-gray-900"><?php echo $program['participants']; ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Start Date</p>
                                <p class="text-gray-900"><?php echo formatDate($program['start_date']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Instructor</p>
                                <p class="text-gray-900 text-sm"><?php echo htmlspecialchars($program['instructor']); ?></p>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-gray-700">Completion</p>
                                <span class="text-gray-900"><?php echo $comp; ?>%</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div
                                    class="bg-purple-500 h-2 rounded-full"
                                    style="width: <?php echo $comp; ?>%"
                                ></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Schedule Tab -->
    <?php if ($activeTab == 'schedule'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Training Schedule</h2>
                <div class="flex gap-2">
                    <a href="?tab=schedule&view=week" class="px-4 py-2 rounded-lg transition-colors <?php echo $scheduleView == 'week' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        This Week
                    </a>
                    <a href="?tab=schedule&view=month" class="px-4 py-2 rounded-lg transition-colors <?php echo $scheduleView == 'month' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        This Month
                    </a>
                </div>
            </div>

            <?php if ($isEnrolled): ?>
                <div class="mb-4 p-4 bg-green-50 rounded-lg">
                    <strong>Your progress:</strong> <?php echo intval($myEnrollment['completion_percentage']); ?>% &mdash; <?php echo $sessionCount; ?> lecture<?php echo $sessionCount !== 1 ? 's' : ''; ?>
                </div>
            <?php endif; ?>
            <div class="space-y-4">
                <?php if (empty($schedule)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No training needs scheduled for <?php echo $scheduleView == 'week' ? 'this week' : 'this month'; ?>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($schedule as $session): ?>
                        <div class="border border-gray-200 rounded-lg p-6 hover:border-indigo-300 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex gap-6 flex-1">
                                    <div class="text-center min-w-[80px]">
                                        <p class="text-gray-600 text-sm mb-1">
                                            <?php echo date('M', strtotime($session['session_date'])); ?>
                                        </p>
                                        <p class="text-2xl font-bold text-gray-900">
                                            <?php echo date('d', strtotime($session['session_date'])); ?>
                                        </p>
                                        <p class="text-gray-600 text-sm mt-1">
                                            <?php echo date('h:i A', strtotime($session['session_time'])); ?>
                                        </p>
                                    </div>

                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($session['program_title']); ?></h3>
                                        <?php if (!empty($session['session_type'])): ?>
                                            <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($session['session_type']); ?></p>
                                        <?php endif; ?>
                                        <div class="flex items-center gap-4 text-sm text-gray-600">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($session['instructor']); ?></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($session['location']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button 
                                        onclick="showTrainingDetails(<?php echo htmlspecialchars(json_encode([
                                            'title' => $session['program_title'],
                                            'date' => $session['session_date'],
                                            'time' => $session['session_time'],
                                            'type' => $session['session_type'] ?? '',
                                            'instructor' => $session['instructor'],
                                            'location' => $session['location'],
                                            'description' => $session['description'] ?? ''
                                        ])); ?>)"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                                    >
                                        View Details
                                    </button>
                                    <?php if ($isEnrolled): ?>
                                        <button onclick="completeSession(<?php echo $session['id']; ?>, <?php echo isset($progId) ? $progId : 0; ?>)" class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                            Mark Lecture Complete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Training Records Tab -->
    <?php if ($activeTab == 'records'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Employee Training Records</h2>
                <form method="GET" action="" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="records">
                    <div class="relative">
                        <input 
                            type="text" 
                            id="employeeSearch"
                            name="search" 
                            value="<?php echo htmlspecialchars($searchQuery); ?>" 
                            placeholder="Search employees..." 
                            class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            onkeyup="handleSearch(event)"
                        >
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <?php if ($searchQuery): ?>
                        <a href="?tab=records" class="px-4 py-2 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="space-y-4">
                <?php if (empty($allEmployeeRecords)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No employee records found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($allEmployeeRecords as $employee): ?>
                        <div class="border border-gray-200 rounded-lg p-6 hover:border-indigo-300 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($employee['display_name']); ?></h3>
                                        <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs"><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                                    </div>
                                    
                                    <div class="flex items-center gap-3 mb-4">
                                        <?php 
                                        $comp = intval($employee['completion_percentage']);
                                        $displayStatus = $comp == 0 ? 'Upcoming' : ($comp == 100 ? 'Completed' : 'In Progress');
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($displayStatus); ?>">
                                            <?php echo htmlspecialchars($displayStatus); ?>: <?php echo $comp; ?>%
                                        </span>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm">
                                            Certifications: <?php echo $employee['certifications_count']; ?>
                                        </span>
                                    </div>
 
                                    <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                        <i class="fas fa-book text-gray-400"></i>
                                        <span>Training: <span class="font-medium text-gray-800"><?php echo htmlspecialchars($employee['training_title']); ?></span></span>
                                    </div>
 
                                    <div class="flex items-center gap-2 text-sm text-gray-600">
                                        <i class="fas fa-calendar-alt text-gray-400"></i>
                                        <span>Enrolled: <?php echo formatDate($employee['enrolled_at']); ?></span>
                                        <?php if ($employee['completed_at']): ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-check-circle text-green-500"></i>
                                            <span>Completed: <?php echo formatDate($employee['completed_at']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2 ml-4">
                                    <button 
                                        onclick="showEmployeeHistory(<?php echo $employee['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($employee['display_name'])); ?>')"
                                        class="text-indigo-600 hover:text-indigo-700 font-medium cursor-pointer"
                                    >
                                        View Full History
                                    </button>
                                    <?php if ($isAdmin): ?>
                                        <button 
                                            onclick="removeTrainingRecord(<?php echo $employee['participant_id']; ?>, this)"
                                            class="p-2 text-gray-400 hover:text-red-600 transition-colors"
                                            title="Remove Record"
                                        >
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recommendations Tab -->
    <?php if ($activeTab == 'recommendations'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Recommended Training</h2>
                    <p class="text-gray-600 mt-1">Based on your role and career progression paths</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="showCreateTrainingModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-2 shadow-md">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Training</span>
                    </button>
                    <button onclick="showTrainingNeedsModal()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 shadow-sm">
                        <i class="fas fa-chalkboard-teacher text-indigo-600"></i>
                        <span>Training Needs</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php
                $recommendStmt = $pdo->query("SELECT * FROM training_programs WHERE is_recommendation = 1 ORDER BY created_at DESC");
                $recommendations = $recommendStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($recommendations as $rec):
                    $icon = 'fa-lightbulb';
                    $color = 'indigo';
                    if (strpos(strtolower($rec['category']), 'safety') !== false) { $icon = 'fa-shield-alt'; $color = 'red'; }
                    elseif (strpos(strtolower($rec['category']), 'tech') !== false) { $icon = 'fa-laptop-code'; $color = 'blue'; }
                    elseif (strpos(strtolower($rec['category']), 'operations') !== false) { $icon = 'fa-truck-loading'; $color = 'green'; }
                ?>
                <div class="group relative border border-gray-200 rounded-xl p-6 hover:border-<?php echo $color; ?>-500 hover:shadow-lg transition-all duration-300 bg-gradient-to-br from-white to-gray-50">
                    <button onclick="deleteRecommendation(<?php echo $rec['id']; ?>, '<?php echo addslashes($rec['title']); ?>')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 transition-colors z-10 p-1 opacity-0 group-hover:opacity-100">
                        <i class="fas fa-times-circle text-xl"></i>
                    </button>
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-600 rounded-lg group-hover:bg-<?php echo $color; ?>-600 group-hover:text-white transition-colors">
                            <i class="fas <?php echo $icon; ?> text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-<?php echo $color; ?>-600 uppercase tracking-wider"><?php echo $rec['category']; ?></span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($rec['title']); ?></h3>
                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($rec['description']); ?></p>
                    
                    <div class="flex items-center gap-4 mb-6">
                        <div class="flex items-center gap-1 text-sm text-gray-500">
                            <i class="far fa-calendar-alt"></i>
                            <span><?php echo date('M d, Y', strtotime($rec['start_date'])); ?></span>
                        </div>
                        <div class="flex items-center gap-1 text-sm text-gray-500">
                            <i class="fas fa-signal text-<?php echo $color; ?>-500"></i>
                            <span><?php echo $rec['status']; ?></span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                        <div class="text-xs text-gray-400">
                            <p>Recommended for</p>
                            <p class="font-medium text-gray-600">Professional Growth</p>
                        </div>
                        <button onclick="showRecommendationInfo(<?php echo htmlspecialchars(json_encode($rec)); ?>)" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
                            View Details
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    <?php endif; ?>
</div>

<!-- Training Details Modal -->
<div id="trainingDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Training Details</h3>
            <button onclick="closeTrainingDetails()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div id="trainingDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeTrainingDetails()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Employee History Modal -->
<div id="employeeHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="employeeHistoryTitle">Training History</h3>
            <button onclick="closeEmployeeHistory()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div id="employeeHistoryContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i>
                    <p class="text-gray-600">Loading training history...</p>
                </div>
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeEmployeeHistory()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Create/Edit Program Modal -->
<div id="programModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="programModalTitle">Create Training Program</h3>
            <button onclick="closeProgramModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="programForm" onsubmit="saveProgram(event)" class="p-6">
            <input type="hidden" id="programId" name="id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" id="programTitle" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <input type="text" id="programCategory" name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration</label>
                        <input type="text" id="programDuration" name="duration" placeholder="e.g., 2 days" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" id="programStartDate" name="start_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" id="programEndDate" name="end_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="programStatus" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="Upcoming">Upcoming</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructor</label>
                        <input type="text" id="programInstructor" name="instructor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="programDescription" name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lectures</label>
                    <textarea id="programLectures" name="lectures" rows="4" placeholder="Enter one lecture or module title per line (e.g., Module Overview, Lesson 1: Introduction)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeProgramModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Save Program
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Participants Modal -->
<div id="participantsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="participantsModalTitle">Manage Participants</h3>
            <button onclick="closeParticipantsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <!-- Enrollment disabled as per new requirements -->
            </div>
            <div id="participantsContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i>
                    <p class="text-gray-600">Loading participants...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Take Training Modal -->
<div id="takeTrainingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="takeTrainingModalTitle">Enroll in Training</h3>
            <button onclick="closeTakeTrainingModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="takeTrainingForm" onsubmit="submitTakeTraining(event)" class="p-6">
            <input type="hidden" id="takeProgramId" name="program_id">
            <input type="hidden" name="employee_id" value="<?php echo $_SESSION['user_id']; ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" id="takeFullName" name="full_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>

            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeTakeTrainingModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Process Enrollment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lectures Modal (shown after enrollment) -->
<div id="lecturesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="lecturesModalTitle">Program Lectures</h3>
            <button onclick="closeLecturesModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="lecturesContent" class="p-6 font-sans text-gray-900" style="font-family: Arial, Helvetica, sans-serif;">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i>
                <p class="text-gray-600">Loading lectures...</p>
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
            <button onclick="closeLecturesModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
            <button onclick="finishLectures()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                Finish
            </button>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div id="scheduleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="scheduleModalTitle">Add Training Schedule</h3>
            <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="scheduleForm" onsubmit="saveSchedule(event)" class="p-6">
            <input type="hidden" id="scheduleProgramId" name="program_id">
            <input type="hidden" id="scheduleId" name="id">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Session Date *</label>
                        <input type="date" id="scheduleDate" name="session_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Session Time *</label>
                        <input type="time" id="scheduleTime" name="session_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Session Type</label>
                    <input type="text" id="scheduleType" name="session_type" placeholder="e.g., Workshop, Lecture" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" id="scheduleLocation" name="location" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructor</label>
                        <input type="text" id="scheduleInstructor" name="instructor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeScheduleModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Save Schedule
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Create Training Modal -->
<div id="createTrainingModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-8 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white transition-all duration-300 transform">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900">Create New Training</h3>
            <button onclick="closeCreateTrainingModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form id="createTrainingForm" onsubmit="submitCreateTraining(event)" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Title</label>
                    <input type="text" name="title" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all outline-none" placeholder="Enter training title">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Schedule Date</label>
                    <input type="date" name="start_date" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all outline-none">
                        <option value="General">General</option>
                        <option value="Technical">Technical</option>
                        <option value="Safety">Safety</option>
                        <option value="Compliance">Compliance</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Document (PDF, DOC)</label>
                    <div class="relative">
                        <input type="file" name="document" accept=".pdf,.doc,.docx" class="hidden" id="trainingDoc">
                        <label for="trainingDoc" class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-indigo-500 hover:bg-indigo-50 transition-all group">
                            <div class="text-center">
                                <i class="fas fa-file-upload text-2xl text-gray-400 group-hover:text-indigo-500 mb-1"></i>
                                <p class="text-xs text-gray-500 group-hover:text-indigo-600">Click to upload doc</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Video OR Provide Link</label>
                    <div class="grid grid-cols-1 gap-3">
                        <div class="relative">
                            <input type="file" name="video" accept="video/*" class="hidden" id="trainingVid">
                            <label for="trainingVid" class="flex items-center justify-center p-3 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-indigo-500 hover:bg-indigo-50 transition-all group">
                                <div class="text-center">
                                    <i class="fas fa-video text-xl text-gray-400 group-hover:text-indigo-500 mb-1"></i>
                                    <p class="text-xs text-gray-500 group-hover:text-indigo-600">Upload video</p>
                                </div>
                            </label>
                        </div>
                        <input type="text" name="video_url" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm" placeholder="Or paste video link (YouTube, etc.)">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="4" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all outline-none" placeholder="Provide details about this training"></textarea>
            </div>

            <div class="flex justify-end gap-4 mt-8">
                <button type="button" onclick="closeCreateTrainingModal()" class="px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-xl hover:bg-gray-200 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-8 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 hover:shadow-indigo-300 transition-all">
                    Done
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Take Training Modal Functions
let currentProgramTitleForLecture = '';

function showTakeTrainingModal(programId, programTitle) {
    currentProgramTitleForLecture = programTitle;
    document.getElementById('takeTrainingModalTitle').textContent = `Enroll in ${programTitle}`;
    document.getElementById('takeProgramId').value = programId;
    document.getElementById('takeFullName').value = '';

    document.getElementById('takeTrainingModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeTakeTrainingModal() {
    document.getElementById('takeTrainingModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitTakeTraining(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'enroll_participant');
    // using enroll_participant which now accepts full_name and health_condition
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeTakeTrainingModal();
            // show lectures for the program instead of navigating away
            const pid = formData.get('program_id');
            showLecturesModal(pid, currentProgramTitleForLecture);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error processing enrollment');
    });
}

// Training Details Modal Functions
function showTrainingDetails(session) {
    const modal = document.getElementById('trainingDetailsModal');
    const content = document.getElementById('trainingDetailsContent');
    
    const date = new Date(session.date);
    const formattedDate = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    const time = new Date('1970-01-01T' + session.time + 'Z');
    const formattedTime = time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    content.innerHTML = `
        <div class="space-y-4">
            <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-2">${session.title}</h4>
                ${session.type ? `<p class="text-gray-600 text-sm mb-4">${session.type}</p>` : ''}
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-calendar-alt text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Date</p>
                        <p class="text-gray-900 font-medium">${formattedDate}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-clock text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Time</p>
                        <p class="text-gray-900 font-medium">${formattedTime}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-user text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Instructor</p>
                        <p class="text-gray-900 font-medium">${session.instructor}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-map-marker-alt text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Location</p>
                        <p class="text-gray-900 font-medium">${session.location}</p>
                    </div>
                </div>
            </div>
            
            ${session.description ? `
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-gray-600 text-sm mb-2">Description</p>
                    <p class="text-gray-700">${session.description}</p>
                </div>
            ` : ''}

            ${session.document_path ? `
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-gray-600 text-sm mb-2">Training Document</p>
                    <a href="${session.document_path}" target="_blank" class="flex items-center gap-2 text-indigo-600 hover:text-indigo-700 font-medium">
                        <i class="fas fa-file-download"></i>
                        Download Document
                    </a>
                </div>
            ` : ''}
            
            ${session.video_path ? `
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-gray-600 text-sm mb-2">Training Video</p>
                    ${session.video_path.startsWith('http') ? `
                        <div class="aspect-video w-full mb-2">
                           <iframe src="${session.video_path.includes('youtube.com/watch?v=') ? session.video_path.replace('watch?v=', 'embed/') : session.video_path}" class="w-full h-full rounded-lg" frameborder="0" allowfullscreen></iframe>
                        </div>
                        <a href="${session.video_path}" target="_blank" class="text-xs text-indigo-600 hover:underline">Open link in new tab</a>
                    ` : `
                        <video controls class="w-full rounded-lg shadow-sm border">
                            <source src="${session.video_path}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    `}
                </div>
            ` : ''}
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeTrainingDetails() {
    const modal = document.getElementById('trainingDetailsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Employee History Modal Functions
function showEmployeeHistory(employeeId, employeeName) {
    const modal = document.getElementById('employeeHistoryModal');
    const title = document.getElementById('employeeHistoryTitle');
    const content = document.getElementById('employeeHistoryContent');
    
    title.textContent = `Training History - ${employeeName}`;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Load employee history via AJAX
    fetch(`?tab=records&employee_id=${employeeId}&ajax=1`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8 text-red-600">
                    <i class="fas fa-exclamation-circle text-3xl mb-4"></i>
                    <p>Error loading training history. Please try again.</p>
                </div>
            `;
            console.error('Error:', error);
        });
}

function closeEmployeeHistory() {
    const modal = document.getElementById('employeeHistoryModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Search functionality with debouncing
let searchTimeout;
function handleSearch(event) {
    clearTimeout(searchTimeout);
    
    // If Enter is pressed, submit immediately
    if (event.key === 'Enter') {
        event.target.closest('form').submit();
        return;
    }
    
    // Otherwise, debounce the search
    searchTimeout = setTimeout(() => {
        const searchValue = event.target.value.trim();
        const url = new URL(window.location.href);
        
        if (searchValue) {
            url.searchParams.set('search', searchValue);
        } else {
            url.searchParams.delete('search');
        }
        url.searchParams.set('tab', 'records');
        
        window.location.href = url.toString();
    }, 500); // Wait 500ms after user stops typing
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const trainingModal = document.getElementById('trainingDetailsModal');
    const historyModal = document.getElementById('employeeHistoryModal');
    
    if (event.target === trainingModal) {
        closeTrainingDetails();
    }
    
    if (event.target === historyModal) {
        closeEmployeeHistory();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeTrainingDetails();
        closeEmployeeHistory();
    }
});

// Smooth scroll for tabs
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth transitions
    const tabs = document.querySelectorAll('a[href*="tab="]');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            // Add loading state if needed
            const targetTab = this.getAttribute('href').split('tab=')[1]?.split('&')[0];
            if (targetTab) {
                // You can add loading indicators here
            }
        });
    });
    
    // Auto-focus search input when records tab is active
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'records') {
        const searchInput = document.getElementById('employeeSearch');
        if (searchInput && !urlParams.get('search')) {
            // Don't auto-focus if there's already a search query
            // searchInput.focus();
        }
    }

    // Animate progress bars in schedule tab
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const target = parseInt(bar.getAttribute('data-target')) || 0;
        const progressText = bar.closest('.training-item')?.querySelector('.progress-text');
        if (target > 0) {
            animateProgressBar(bar, target, progressText);
        }
    });

    // Add hover effects to cards
    const cards = document.querySelectorAll('.border.border-gray-200.rounded-lg');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'transform 0.2s ease, box-shadow 0.2s ease';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
});

// Animate Progress Bar
function animateProgressBar(element, target, percentageSpan) {
    if (!element) return;
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.style.width = target + '%';
            if (percentageSpan) percentageSpan.textContent = target + '%';
            clearInterval(timer);
        } else {
            element.style.width = current + '%';
            if (percentageSpan) percentageSpan.textContent = Math.floor(current) + '%';
        }
    }, 30);
}

// Recommendation Details
function showRecommendationInfo(rec) {
    showTrainingDetails({
        title: rec.title,
        type: `Category: ${rec.category}`,
        instructor: rec.instructor || 'Internal Academy',
        location: 'Online / Learning Management System',
        date: rec.start_date,
        time: '00:00:00',
        description: rec.description,
        document_path: rec.document_path,
        video_path: rec.video_path
    });
}

// Training Program CRUD Functions
function showCreateProgramModal() {
    document.getElementById('programModalTitle').textContent = 'Create Training Program';
    document.getElementById('programForm').reset();
    document.getElementById('programId').value = '';
    document.getElementById('programModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editProgram(programId) {
    fetch(`training-ajax.php?action=get_program&id=${programId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const program = data.program;
                document.getElementById('programModalTitle').textContent = 'Edit Training Program';
                document.getElementById('programId').value = program.id;
                document.getElementById('programTitle').value = program.title || '';
                document.getElementById('programCategory').value = program.category || '';
                document.getElementById('programDuration').value = program.duration || '';
                document.getElementById('programStatus').value = program.status || 'Upcoming';
                document.getElementById('programStartDate').value = program.start_date || '';
                document.getElementById('programEndDate').value = program.end_date || '';
                document.getElementById('programInstructor').value = program.instructor || '';
                document.getElementById('programDescription').value = program.description || '';
                document.getElementById('programModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading program details');
        });
}

function saveProgram(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const isEdit = !!document.getElementById('programId').value;
    formData.append('action', isEdit ? 'update_program' : 'create_program');
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let programId = data.program_id || document.getElementById('programId').value;
            const lectureText = formData.get('lectures') || '';
            const titles = lectureText.split(/\r?\n/).map(t => t.trim()).filter(t => t);
            const startDate = formData.get('start_date') || new Date().toISOString().slice(0, 10);
            const instructor = formData.get('instructor') || '';
            
            if (programId && titles.length > 0) {
                saveLecturesSequentially(programId, titles, startDate, instructor)
                    .then(() => {
                        alert(data.message + ' ' + titles.length + ' lecture(s) added.');
                        closeProgramModal();
                        location.reload();
                    })
                    .catch(err => {
                        alert(data.message + ' Some lectures failed to save: ' + (err.message || err));
                        closeProgramModal();
                        location.reload();
                    });
            } else {
                alert(data.message);
                closeProgramModal();
                location.reload();
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving program');
    });
}

function saveLecturesSequentially(programId, titles, startDate, instructor) {
    let chain = Promise.resolve();
    for (let i = 0; i < titles.length; i++) {
        if (!titles[i]) continue;
        const fd = new FormData();
        fd.append('action', 'create_schedule');
        fd.append('program_id', programId);
        fd.append('session_date', startDate);
        fd.append('session_time', (9 + Math.floor(i / 4)).toString().padStart(2, '0') + ':' + ((i % 4) * 15).toString().padStart(2, '0') + ':00');
        fd.append('session_type', titles[i]);
        fd.append('location', '');
        fd.append('instructor', instructor);
        chain = chain.then(() => fetch('training-ajax.php', { method: 'POST', body: fd }).then(r => r.json()))
            .then(data => { if (!data.success) throw new Error(data.message); });
    }
    return chain;
}

function deleteProgram(programId) {
    if (!confirm('Are you sure you want to delete this training program? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_program');
    formData.append('id', programId);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting program');
    });
}

function retakeOrientation(programId, programTitle) {
    if (!confirm(`Are you sure you want to retake "${programTitle}"? This will reset your progress.`)) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'retake_program');
    formData.append('program_id', programId);

    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // clear the fields in the enroll modal
            document.getElementById('takeFullName').value = '';
            document.getElementById('takeHealth').value = '';
            // Show the enrollment modal again as requested by user
            showTakeTrainingModal(programId, programTitle);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error resetting program');
    });
}

function closeProgramModal() {
    document.getElementById('programModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showProgramActions(programId) {
    const actionsMenu = document.getElementById(`actions-${programId}`);
    // Close all other action menus
    document.querySelectorAll('[id^="actions-"]').forEach(menu => {
        if (menu.id !== `actions-${programId}`) {
            menu.classList.add('hidden');
        }
    });
    actionsMenu.classList.toggle('hidden');
}

// Close action menus when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('[id^="actions-"]') && !event.target.closest('button[onclick*="showProgramActions"]')) {
        document.querySelectorAll('[id^="actions-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }
});

// Participants Management
let currentProgramId = null;

function manageParticipants(programId, programTitle) {
    currentProgramId = programId;
    document.getElementById('participantsModalTitle').textContent = `Manage Participants - ${programTitle}`;
    document.getElementById('participantsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    loadParticipants(programId);
}

function loadParticipants(programId) {
    const content = document.getElementById('participantsContent');
    content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i><p class="text-gray-600">Loading participants...</p></div>';
    
    fetch(`training-ajax.php?action=get_participants&program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.participants.length === 0) {
                    content.innerHTML = '<div class="text-center py-8 text-gray-500"><p>No participants enrolled yet.</p></div>';
                } else {
                    let html = '<div class="space-y-4">';
                    data.participants.forEach(participant => {
                        const nameToShow = participant.enrolled_name || participant.user_name || participant.full_name || 'Unknown';
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900">${nameToShow}</h4>
                                        <p class="text-sm text-gray-600">${participant.employee_id ? participant.employee_id + ' • ' : ''}${participant.department || ''}</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <label class="text-sm text-gray-600">Completion: </label>
                                            <span class="text-sm font-bold text-indigo-600">${participant.completion_percentage}%</span>
                                        </div>
                                        <div class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium">
                                            ${participant.status}
                                        </div>
                                        <button onclick="removeParticipant(${participant.id})" class="text-red-600 hover:text-red-700">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;
                }
            } else {
                content.innerHTML = `<div class="text-center py-8 text-red-600"><p>Error: ${data.message}</p></div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="text-center py-8 text-red-600"><p>Error loading participants</p></div>';
        });
}

function updateCompletion(participantId, percentage) {
    const formData = new FormData();
    formData.append('action', 'update_completion');
    formData.append('id', participantId);
    formData.append('completion_percentage', percentage);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the status if needed
            loadParticipants(currentProgramId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating completion');
    });
}

function updateParticipantStatus(participantId, status) {
    const formData = new FormData();
    formData.append('action', 'update_participant');
    formData.append('id', participantId);
    formData.append('status', status);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadParticipants(currentProgramId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating participant status');
    });
}

// mark a specific session complete for the current user
function completeSession(sessionId, programId) {
    const formData = new FormData();
    formData.append('action', 'complete_session');
    formData.append('session_id', sessionId);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            alert('Lecture marked complete (+' + data.added + '%)');
            // refresh the page so progress bar updates
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error marking lecture complete');
    });
}

let currentLecturesProgramId = null;

// display a read‑only list of all sessions for a program in a modal
// Create Training Functions
function showCreateTrainingModal() {
    document.getElementById('createTrainingForm').reset();
    document.getElementById('createTrainingModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCreateTrainingModal() {
    document.getElementById('createTrainingModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitCreateTraining(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'create_training_entry');
    formData.append('is_recommendation', '1');

    const doneBtn = form.querySelector('button[type="submit"]');
    const originalText = doneBtn.innerHTML;
    doneBtn.disabled = true;
    doneBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeCreateTrainingModal();
            location.reload(); // Reload to show new training if needed, or update UI dynamically
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error processing request');
    })
    .finally(() => {
        doneBtn.disabled = false;
        doneBtn.innerHTML = originalText;
    });
}

function deleteRecommendation(id, title) {
    if (!confirm(`Are you sure you want to delete "${title}" recommendation?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_program');
    formData.append('id', id);

    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error processing request');
    });
}

function showLecturesModal(programId, programTitle) {
    currentLecturesProgramId = programId;
    const modal = document.getElementById('lecturesModal');
    const titleElem = document.getElementById('lecturesModalTitle');
    const content = document.getElementById('lecturesContent');
    titleElem.textContent = programTitle || 'Program Lectures';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    content.innerHTML = `
        <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i><p class="text-gray-600">Loading lectures...</p></div>
    `;
    fetch(`training-ajax.php?action=get_schedule&program_id=${programId}`)
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                // if server returned an extra_content field, prepend it
                if (data.extra_content) {
                    content.innerHTML = data.extra_content;
                }

                if (data.schedule.length === 0 && !data.extra_content) {
                    content.innerHTML = '<div class="text-center py-8 text-gray-500"><p>No lectures available for this program.</p></div>';
                } else {
                    let html = '';
                    if (data.extra_content) html = data.extra_content;
                    if (data.schedule.length > 0) {
                        const progTitle = (programTitle || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        if (!data.extra_content) html = `<div class="prose prose-gray max-w-none mb-6 font-sans text-gray-900" style="font-family: Arial, Helvetica, sans-serif;"><h3 class="text-xl font-bold text-gray-900 mb-4">${progTitle}</h3>`;
                        data.schedule.forEach((s, idx) => {
                            const title = (s.session_type || 'Lecture').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            const details = [s.session_date, s.session_time, s.location].filter(Boolean).join(' • ').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            const openAttr = idx === 0 ? ' open' : '';
                            html += `
    <details${openAttr} class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">${title}</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        ${details ? `<p class="mt-2 mb-2">${details}</p>` : ''}
        </div>
    </details>`;
                        });
                        if (!data.extra_content) html += '</div>';
                    }
                    content.innerHTML = html || '<div class="text-center py-8 text-gray-500"><p>No lectures available for this program.</p></div>';
                }
            } else {
                content.innerHTML = `<div class="text-center py-8 text-red-600"><p>Error: ${data.message}</p></div>`;
            }
        })
        .catch(err => {
            console.error(err);
            content.innerHTML = '<div class="text-center py-8 text-red-600"><p>Error loading lectures</p></div>';
        });
}

function startExam(programId, programTitle) {
    // redirect to a hypothetical exam page; title passed as query for convenience
    window.location.href = `training-exam.php?program_id=${programId}&title=${encodeURIComponent(programTitle)}`;
}

function finishLectures() {
    if (!currentLecturesProgramId) {
        closeLecturesModal();
        return;
    }
    const formData = new FormData();
    formData.append('action', 'finish_orientation');
    formData.append('program_id', currentLecturesProgramId);
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            currentLecturesProgramId = null;
            document.getElementById('lecturesModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error completing orientation');
    });
}

function closeLecturesModal() {
    if (currentLecturesProgramId) {
        const formData = new FormData();
        formData.append('action', 'update_orientation_progress');
        formData.append('program_id', currentLecturesProgramId);
        formData.append('completion_percentage', 55);
        fetch('training-ajax.php', {
            method: 'POST',
            body: formData
        }).then(resp => resp.json()).then(data => {
            if (data.success) window.location.reload();
        }).catch(() => {});
    }
    document.getElementById('lecturesModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentLecturesProgramId = null;
}

function removeParticipant(participantId) {
    if (!confirm('Are you sure you want to remove this participant?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove_participant');
    formData.append('id', participantId);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadParticipants(currentProgramId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing participant');
    });
}

function showEnrollParticipantForm() {
    // Get list of employees (you may want to create an endpoint for this)
    const employeeId = prompt('Enter Employee ID to enroll:');
    if (!employeeId) return;
    
    const formData = new FormData();
    formData.append('action', 'enroll_participant');
    formData.append('program_id', currentProgramId);
    formData.append('employee_id', employeeId);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadParticipants(currentProgramId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error enrolling participant');
    });
}

function closeParticipantsModal() {
    document.getElementById('participantsModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentProgramId = null;
}

// Schedule Management
function addSchedule(programId, programTitle) {
    document.getElementById('scheduleModalTitle').textContent = `Add Schedule - ${programTitle}`;
    document.getElementById('scheduleForm').reset();
    document.getElementById('scheduleProgramId').value = programId;
    document.getElementById('scheduleId').value = '';
    document.getElementById('scheduleModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function saveSchedule(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', document.getElementById('scheduleId').value ? 'update_schedule' : 'create_schedule');
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeScheduleModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving schedule');
    });
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const programModal = document.getElementById('programModal');
    const participantsModal = document.getElementById('participantsModal');
    const scheduleModal = document.getElementById('scheduleModal');
    
    if (event.target === programModal) {
        closeProgramModal();
    }
    if (event.target === participantsModal) {
        closeParticipantsModal();
    }
    if (event.target === scheduleModal) {
        closeScheduleModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProgramModal();
        closeParticipantsModal();
        closeScheduleModal();
    }
});

function removeTrainingRecord(participantId, button) {
    if (!confirm('Are you sure you want to remove this training record? This action cannot be undone.')) {
        return;
    }

    const card = button.closest('.border.border-gray-200');
    
    // Add loading state
    button.disabled = true;
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('action', 'remove_participant');
    formData.append('id', participantId);

    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Smoothly remove from DOM
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateX(20px)';
            setTimeout(() => card.remove(), 300);
        } else {
            alert('Error: ' + data.message);
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An unexpected error occurred.');
        button.disabled = false;
        button.innerHTML = originalContent;
    });
}
// Training Needs Functions (Integrated with HR1)
function showTrainingNeedsModal() {
    document.getElementById('trainingNeedsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    loadHR1TrainingNeeds();
}

function closeTrainingNeedsModal() {
    document.getElementById('trainingNeedsModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function loadHR1TrainingNeeds() {
    const listContainer = document.getElementById('trainingNeedsList');
    const noState = document.getElementById('noNeedsState');
    const countBadge = document.getElementById('needsCountBadge');
    
    listContainer.innerHTML = `
        <div class="p-12 text-center text-gray-500">
            <i class="fas fa-spinner fa-spin text-3xl mb-4 text-indigo-600"></i>
            <p>Retrieving evaluation data from HR1...</p>
        </div>
    `;
    
    fetch('training-ajax.php?action=get_hr1_training_needs')
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                if (!data.needs || data.needs.length === 0) {
                    listContainer.innerHTML = '';
                    noState.classList.remove('hidden');
                    if (countBadge) countBadge.textContent = '0 Total';
                } else {
                    noState.classList.add('hidden');
                    if (countBadge) countBadge.textContent = `${data.needs.length} Total`;
                    listContainer.innerHTML = data.needs.map(need => {
                        const initials = need.full_name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                        const date = need.updated_at ? new Date(need.updated_at).toLocaleDateString() : 'Pending';
                        
                        return `
                            <div class="bg-white hover:bg-gray-50 transition-colors px-10 py-6 flex items-center group border-b border-gray-50 last:border-0">
                                <!-- NAME -->
                                <div class="flex items-center gap-4 w-[20%]">
                                    <div class="w-10 h-10 bg-[#eef2ff] text-[#6366f1] rounded-full flex items-center justify-center font-bold text-base shrink-0 border border-[#e0e7ff] shadow-sm">
                                        ${initials}
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <h4 class="font-bold text-[#1e293b] text-[13px] leading-tight truncate">${need.full_name}</h4>
                                    </div>
                                </div>

                                <!-- TITLE -->
                                <div class="w-[15%] pr-4 border-l border-gray-100 pl-4 ml-[-1px]">
                                    <span class="text-[13px] text-gray-600 font-semibold truncate block">${need.position || 'Employee'}</span>
                                </div>

                                <!-- CATEGORY -->
                                <div class="w-[15%] border-l border-gray-100 pl-4 ml-[-1px]">
                                    <span class="px-3 py-1 text-[10px] font-bold rounded-full bg-[#f1f5f9] text-[#475569] border border-[#e2e8f0]">
                                        ${need.user_dept || 'Operations'}
                                    </span>
                                </div>

                                <!-- SCHEDULE DATE -->
                                <div class="w-[15%] border-l border-gray-100 pl-4 ml-[-1px]">
                                    <div class="flex items-center gap-2 text-gray-400">
                                        <i class="far fa-calendar-alt text-[11px]"></i>
                                        <span class="text-[11px] font-medium">${date}</span>
                                    </div>
                                </div>

                                <!-- TRAINING NEEDS -->
                                <div class="w-[35%] border-l border-gray-100 pl-4 ml-[-1px]">
                                    <div class="flex flex-wrap gap-2">
                                        ${(need.critical_gaps || 'Full competency development required').split(',').map(gap => `
                                            <span class="px-3 py-1 text-[10px] font-bold rounded-full bg-[#eff6ff] text-[#2563eb] border border-[#dbeafe] shadow-sm">
                                                ${gap.trim()}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            } else {
                listContainer.innerHTML = `<div class="p-12 text-center text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>Error: ${data.message}</div>`;
            }
        });
}

function deleteTrainingNeed(id) {
    if (!confirm('Are you sure you want to dismiss this identification record?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_program'); // Reusing existing delete logic
    formData.append('id', id);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            loadHR1TrainingNeeds();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
<!-- Premium Training Needs Modal (HR1 Integrated) -->
<div id="trainingNeedsModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden z-[100] flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-[#f8fafc] rounded-3xl shadow-2xl max-w-7xl w-full max-h-[90vh] overflow-hidden flex flex-col border border-white border-opacity-20 animate-in fade-in zoom-in duration-300">
        <!-- Modern Header -->
        <div id="modalHeader" class="bg-white px-10 py-8 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-2xl font-bold text-[#1e293b] tracking-tight">Training Needs Awaiting Identification</h3>
            <div id="needsCountBadge" class="px-4 py-1.5 bg-[#eff6ff] text-[#2563eb] text-sm font-bold rounded-full border border-[#dbeafe]">
                0 Total
            </div>
        </div>

        <div class="px-10 py-5 bg-[#fafcfd] border-b border-gray-100 flex items-center text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] hidden md:flex">
            <span class="w-[20%]">Name</span>
            <span class="w-[15%]">Title</span>
            <span class="w-[15%]">Category</span>
            <span class="w-[15%]">Schedule Date</span>
            <span class="w-[35%]">Training Needs</span>
        </div>

        <!-- Card Container -->
        <div class="flex-1 overflow-auto bg-white p-0 space-y-0 divide-y divide-gray-50">
            <div id="trainingNeedsList">
                <!-- Dynamic Content -->
            </div>
            
            <div id="noNeedsState" class="hidden py-32 text-center">
                <div class="w-24 h-24 bg-[#f8fafc] rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner">
                    <i class="fas fa-clipboard-check text-5xl text-gray-200"></i>
                </div>
                <h4 class="text-2xl font-bold text-[#1e293b] mb-3">Everything is up-to-date!</h4>
                <p class="text-gray-400 max-w-sm mx-auto">No training needs are currently flagged in the evaluation system.</p>
            </div>
        </div>

        <!-- Sticky Close Button for Better UX -->
        <button onclick="closeTrainingNeedsModal()" class="absolute top-8 right-10 text-gray-300 hover:text-gray-600 transition-colors bg-white w-10 h-10 rounded-full flex items-center justify-center shadow-md hover:shadow-lg">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
</div>


<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
require_once 'includes/footer.php';
?>

