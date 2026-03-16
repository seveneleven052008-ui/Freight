<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'Learning Management';
$pdo = getDBConnection();

$activeTab = $_GET['tab'] ?? 'catalog';
$userId = $_SESSION['user_id'];

// Handle Certificate Download
if (isset($_GET['action']) && $_GET['action'] == 'download_certificate' && isset($_GET['cert_id'])) {
    $certId = $_GET['cert_id'];
    $stmt = $pdo->prepare("SELECT c.*, lc.title as course_name FROM certificates c JOIN learning_courses lc ON c.course_id = lc.id WHERE c.certificate_id = ? AND c.employee_id = ?");
    $stmt->execute([$certId, $userId]);
    $cert = $stmt->fetch();

    if ($cert) {
        header('Content-Type: text/plain'); // Simple version for now
        header('Content-Disposition: attachment; filename="certificate_' . $certId . '.txt"');
        echo "CERTIFICATE OF COMPLETION\n\n";
        echo "This is to certify that\n";
        echo getUserName() . "\n\n";
        echo "has successfully completed the course\n";
        echo $cert['course_name'] . "\n\n";
        echo "on " . formatDate($cert['issue_date']) . "\n";
        echo "Score: " . $cert['score'] . "%\n";
        echo "ID: " . $certId . "\n\n";
        echo "Instructor: " . $cert['instructor'] . "\n";
        echo "Costa Cargo LMS\n";
        exit;
    }
}

// Note: Enrollment is now handled via AJAX in learning-ajax.php

// Get courses
$courses = $pdo->prepare("
    SELECT lc.*, 
           ce.progress, ce.status as enrollment_status, ce.completed_modules, ce.total_modules, ce.last_accessed
    FROM learning_courses lc
    LEFT JOIN course_enrollments ce ON lc.id = ce.course_id AND ce.employee_id = ?
    ORDER BY lc.created_at DESC
");
$courses->execute([$userId]);
$allCourses = $courses->fetchAll();

// Get my progress with exam status
$myProgress = $pdo->prepare("
    SELECT ce.*, lc.title as course_name, e.status as exam_status, e.id as exam_id
    FROM course_enrollments ce
    JOIN learning_courses lc ON ce.course_id = lc.id
    LEFT JOIN examinations e ON ce.course_id = e.course_id AND ce.employee_id = e.employee_id
    WHERE ce.employee_id = ?
    ORDER BY ce.last_accessed DESC
");
$myProgress->execute([$userId]);
$progress = $myProgress->fetchAll();

// Get certificates
$certificates = $pdo->prepare("SELECT * FROM certificates WHERE employee_id = ? ORDER BY issue_date DESC");
$certificates->execute([$userId]);
$myCertificates = $certificates->fetchAll();

// Get badges
$badges = $pdo->prepare("SELECT * FROM badges WHERE employee_id = ? ORDER BY earned_date DESC");
$badges->execute([$userId]);
$myBadges = $badges->fetchAll();

// Get examinations
$examinations = $pdo->prepare("
    SELECT e.*, lc.title as course_name
    FROM examinations e
    JOIN learning_courses lc ON e.course_id = lc.id
    WHERE e.employee_id = ?
    ORDER BY e.exam_date DESC
");
$examinations->execute([$userId]);
$myExams = $examinations->fetchAll();

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Learning Management System</h1>
        <p class="text-gray-600">
            Browse courses, track your progress, and earn certifications
        </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <div class="flex gap-4 overflow-x-auto">
            <a href="?tab=catalog" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'catalog' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-book-open"></i>
                    <span>Course Catalog</span>
                </div>
            </a>
            <a href="?tab=progress" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'progress' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-chart-line"></i>
                    <span>My Progress</span>
                </div>
            </a>
            <a href="?tab=certificates" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'certificates' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-award"></i>
                    <span>Certificates & Badges</span>
                </div>
            </a>
            <a href="?tab=examinations" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'examinations' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-file-alt"></i>
                    <span>Examinations</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Course Catalog Tab -->
    <?php if ($activeTab == 'catalog'): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Available Courses</h2>
            </div>

            <!-- Search and Filter Section -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Courses</label>
                        <input 
                            type="text" 
                            id="courseSearch" 
                            placeholder="Search by title or description..." 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            onkeyup="filterCourses()"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select id="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" onchange="filterCourses()">
                            <option value="">All Categories</option>
                            <?php 
                            $categories = array_unique(array_column($allCourses, 'category'));
                            foreach ($categories as $cat): 
                            ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                        <select id="levelFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" onchange="filterCourses()">
                            <option value="">All Levels</option>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <?php echo htmlspecialchars($_SESSION['message']); ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div id="coursesContainer" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($allCourses as $course): ?>
                    <div class="border border-gray-200 rounded-lg p-6 course-card" data-title="<?php echo htmlspecialchars(strtolower($course['title'])); ?>" data-description="<?php echo htmlspecialchars(strtolower($course['description'])); ?>" data-category="<?php echo htmlspecialchars($course['category']); ?>" data-level="<?php echo htmlspecialchars($course['level']); ?>">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($course['description']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mb-4 text-sm text-gray-600">
                            <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-sm">
                                <?php echo htmlspecialchars($course['category']); ?>
                            </span>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-sm">
                                <?php echo htmlspecialchars($course['level']); ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Duration</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($course['duration']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Modules</p>
                                <p class="text-gray-900"><?php echo $course['modules_count']; ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Instructor</p>
                                <p class="text-gray-900 text-sm"><?php echo htmlspecialchars($course['instructor']); ?></p>
                            </div>
                            <div>
                                <div class="flex items-center gap-1">
                                    <i class="fas fa-star text-yellow-500"></i>
                                    <span class="text-gray-900"><?php echo $course['rating']; ?></span>
                                    <span class="text-gray-600 text-sm">(<?php echo $course['reviews_count']; ?>)</span>
                                </div>
                            </div>
                        </div>
                        <?php if ($course['enrollment_status']): ?>
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-gray-700 text-sm">Your Progress</p>
                                    <span class="text-gray-900 text-sm"><?php echo $course['progress'] ?? 0; ?>%</span>
                                </div>
                                <div class="bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full course-progress-bar" style="width: 0%" data-target="<?php echo $course['progress'] ?? 0; ?>"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <button 
                            data-course-data='<?php echo htmlspecialchars(json_encode([
                                'id' => $course['id'],
                                'title' => $course['title'],
                                'description' => $course['description'],
                                'category' => $course['category'],
                                'level' => $course['level'],
                                'duration' => $course['duration'],
                                'modules_count' => (int)$course['modules_count'],
                                'instructor' => $course['instructor'],
                                'rating' => $course['rating'],
                                'reviews_count' => $course['reviews_count'],
                                'enrolled' => (bool)$course['enrollment_status'],
                                'completed_modules' => (int)($course['completed_modules'] ?? 0),
                                'total_modules' => (int)($course['total_modules'] ?? 0)
                            ]), ENT_QUOTES); ?>'
                            onclick="handleCourseAction(JSON.parse(this.dataset.courseData))" 
                            class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg <?php echo $course['enrollment_status'] ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'border border-indigo-600 text-indigo-600 hover:bg-indigo-50'; ?>"
                        >
                            <?php if ($course['enrollment_status']): ?>
                                <i class="fas fa-play"></i>
                                <span>Continue Learning</span>
                            <?php else: ?>
                                <span>Enroll Now</span>
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Progress Tab -->
    <?php if ($activeTab == 'progress'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">My Learning Progress</h2>
            <div class="space-y-4">
                <?php foreach ($progress as $prog): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($prog['course_name']); ?></h3>
                                <p class="text-gray-600 text-sm">
                                    Last accessed: <?php echo formatDate($prog['last_accessed']); ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($prog['status']); ?>">
                                <?php echo htmlspecialchars($prog['status']); ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Modules Completed</p>
                                <p class="text-gray-900"><?php echo $prog['completed_modules']; ?>/<?php echo $prog['total_modules']; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Time Spent</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($prog['time_spent'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Progress</p>
                                <p class="text-gray-900"><?php echo $prog['progress']; ?>%</p>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full learning-progress-bar <?php echo $prog['progress'] == 100 ? 'bg-green-500' : 'bg-indigo-500'; ?>" style="width: 0%" data-target="<?php echo $prog['progress']; ?>"></div>
                            </div>
                        </div>
                        <?php if ($prog['status'] != 'Completed'): ?>
                            <button 
                                data-module-data='<?php echo htmlspecialchars(json_encode([
                                    'id' => $prog['course_id'],
                                    'name' => $prog['course_name'],
                                    'completed_modules' => (int)$prog['completed_modules'],
                                    'total_modules' => (int)$prog['total_modules']
                                ]), ENT_QUOTES); ?>'
                                onclick="startCourseModule(JSON.parse(this.dataset.moduleData))"
                                class="flex items-center gap-2 text-indigo-600 hover:text-indigo-700 mt-4 cursor-pointer"
                            >
                                <i class="fas fa-play"></i>
                                <span>Continue Course</span>
                            </button>
                        <?php elseif ($prog['exam_id']): ?>
                            <div class="mt-4 flex items-center justify-between border-t pt-4">
                                <span class="text-sm font-medium text-gray-700 font-bold">Course Completed!</span>
                                <button onclick="startExamination(<?php echo $prog['exam_id']; ?>)" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 flex items-center gap-2">
                                    <i class="fas fa-file-signature"></i>
                                    <span><?php echo $prog['exam_status'] == 'Passed' ? 'Retake Examination' : 'Start Final Exam'; ?></span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Certificates & Badges Tab -->
    <?php if ($activeTab == 'certificates'): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">My Certificates</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (empty($myCertificates)): ?>
                    <div class="col-span-2 text-center py-16 text-gray-400">
                        <i class="fas fa-certificate text-5xl mb-4 block"></i>
                        <p class="text-lg font-semibold text-gray-500">No certificates yet</p>
                        <p class="text-sm mt-1">Complete a course to earn your first certificate!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($myCertificates as $cert): ?>
                        <div class="border-2 border-indigo-200 rounded-lg p-6 bg-gradient-to-br from-indigo-50 to-white">
                            <div class="flex items-start justify-between mb-4">
                                <i class="fas fa-award text-indigo-600 text-3xl"></i>
                                <span class="text-indigo-600 font-semibold">Score: <?php echo $cert['score']; ?>%</span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($cert['certificate_name']); ?></h3>
                            <p class="text-gray-600 text-sm mb-1">Instructor: <?php echo htmlspecialchars($cert['instructor']); ?></p>
                            <p class="text-gray-600 text-sm mb-4">
                                Issued: <?php echo formatDate($cert['issue_date']); ?>
                            </p>
                            <p class="text-gray-500 text-sm mb-4">ID: <?php echo htmlspecialchars($cert['certificate_id']); ?></p>
                            <button onclick="downloadCertificate('<?php echo htmlspecialchars($cert['certificate_id']); ?>')" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                Download Certificate
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Achievement Badges</h2>
            <?php if (empty($myBadges)): ?>
                <div class="text-center py-16 text-gray-400">
                    <i class="fas fa-medal text-5xl mb-4 block"></i>
                    <p class="text-lg font-semibold text-gray-500">No badges yet</p>
                    <p class="text-sm mt-1">Complete a course to earn your first badge!</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php
                    $badgeColors = [
                        'fa-truck'      => ['bg' => 'bg-blue-100',   'text' => 'text-blue-600'],
                        'fa-shield-alt' => ['bg' => 'bg-green-100',  'text' => 'text-green-600'],
                        'fa-crown'      => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'],
                        'fa-route'      => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
                        'fa-award'      => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-600'],
                    ];
                    foreach ($myBadges as $badge):
                        $icon  = htmlspecialchars($badge['icon'] ?? 'fa-award');
                        $color = $badgeColors[$icon] ?? $badgeColors['fa-award'];
                    ?>
                    <div class="border border-gray-100 rounded-xl p-6 text-center shadow-sm hover:shadow-md hover:border-indigo-200 transition-all">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center <?php echo $color['bg']; ?>">
                            <i class="fas <?php echo $icon; ?> text-3xl <?php echo $color['text']; ?>"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 mb-2 leading-tight"><?php echo htmlspecialchars($badge['badge_name']); ?></h3>
                        <p class="text-gray-500 text-xs mb-3 leading-relaxed"><?php echo htmlspecialchars($badge['description'] ?? ''); ?></p>
                        <span class="inline-block px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                            <?php echo formatDate($badge['earned_date']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Examinations Tab -->
    <?php if ($activeTab == 'examinations'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Course Examinations</h2>
            <div class="space-y-4">
                <?php if (empty($myExams)): ?>
                    <div class="text-center py-16 text-gray-400">
                        <i class="fas fa-file-alt text-5xl mb-4 block"></i>
                        <p class="text-lg font-semibold text-gray-500">No examinations yet</p>
                        <p class="text-sm mt-1">Complete a course to unlock its final examination!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($myExams as $exam): ?>
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($exam['course_name']); ?></h3>
                                    <p class="text-gray-600 text-sm">
                                        Exam Date: <?php echo formatDate($exam['exam_date']); ?>
                                    </p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($exam['status']); ?>">
                                    <?php echo htmlspecialchars($exam['status']); ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-gray-600 text-sm mb-1">Duration</p>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($exam['duration']); ?></p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-gray-600 text-sm mb-1">Passing Score</p>
                                    <p class="text-gray-900"><?php echo $exam['passing_score']; ?>%</p>
                                </div>
                                <?php if ($exam['status'] == 'Passed' && $exam['score']): ?>
                                    <div class="bg-green-50 p-3 rounded-lg">
                                        <p class="text-gray-600 text-sm mb-1">Your Score</p>
                                        <p class="text-green-700 font-semibold"><?php echo $exam['score']; ?>%</p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($exam['status'] == 'Scheduled'): ?>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <p class="text-gray-600 text-sm mb-1">Attempts Allowed</p>
                                        <p class="text-gray-900"><?php echo $exam['attempts_allowed']; ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($exam['status'] == 'Scheduled'): ?>
                                <button 
                                    data-exam-id="<?php echo $exam['id']; ?>"
                                    onclick="startExamination(this.dataset.examId)" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                                >
                                    Start Examination
                                </button>
                            <?php endif; ?>
                            <?php if ($exam['status'] == 'Passed'): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-green-600">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Examination passed successfully!</span>
                                    </div>
                                    <button 
                                        data-exam-id="<?php echo $exam['id']; ?>"
                                        onclick="startExamination(this.dataset.examId)" 
                                        class="text-indigo-600 hover:text-indigo-700 text-sm font-semibold flex items-center gap-1"
                                    >
                                        <i class="fas fa-redo"></i>
                                        Retake Examination
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>


<!-- Course Module Modal -->
<div id="courseModuleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-2xl font-bold text-gray-900" id="moduleTitle">Course Module</h3>
                <p class="text-gray-600 text-sm" id="moduleSubtitle">Module X of Y</p>
            </div>
            <button onclick="closeCourseModule()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="moduleContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-between">
            <button id="prevModuleBtn" onclick="navigateModule('prev')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors" disabled>
                <i class="fas fa-arrow-left mr-2"></i>Previous Module
            </button>
            <div class="flex gap-2">
                <button onclick="closeCourseModule()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Close
                </button>
                <button id="nextModuleBtn" onclick="navigateModule('next')" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-semibold">
                    Next Module <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Course Details Modal -->

<div id="courseDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="courseModalTitle">Course Details</h3>
            <button onclick="closeCourseDetailsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="courseDetailsContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeCourseDetailsModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Animate progress bars on page load
document.addEventListener('DOMContentLoaded', function() {
    // Animate course progress bars
    const courseBars = document.querySelectorAll('.course-progress-bar');
    courseBars.forEach(bar => {
        const target = parseInt(bar.getAttribute('data-target')) || 0;
        animateProgressBar(bar, target);
    });

    // Animate learning progress bars
    const learningBars = document.querySelectorAll('.learning-progress-bar');
    learningBars.forEach(bar => {
        const target = parseInt(bar.getAttribute('data-target')) || 0;
        animateProgressBar(bar, target);
    });

    // Add hover effects to course cards
    const courseCards = document.querySelectorAll('.border.border-gray-200.rounded-lg');
    courseCards.forEach(card => {
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

    // Add hover effects to badge cards
    const badgeCards = document.querySelectorAll('.border.border-gray-200.rounded-lg.text-center');
    badgeCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s ease';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
});

// Animate Progress Bar
function animateProgressBar(element, target) {
    if (!element) return;
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.style.width = target + '%';
            clearInterval(timer);
        } else {
            element.style.width = current + '%';
        }
    }, 30);
}

// Global variable to store current enrollment data for success handler
let currentEnrollmentData = null;
    
function handleCourseAction(courseData) {
    if (courseData.enrolled) {
        startCourseModule({
            id: courseData.id,
            name: courseData.title,
            completed_modules: courseData.completed_modules,
            total_modules: courseData.total_modules
        });
    } else {
        enrollCourseDirectly(courseData);
    }
}

function enrollCourseDirectly(courseData) {
    Swal.fire({
        title: 'Enroll in Course?',
        text: `Do you want to enroll in "${courseData.title}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#05386D',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, Enroll Now'
    }).then((result) => {
        if (result.isConfirmed) {
            // Store data for the success handler
            window.currentEnrollmentData = courseData;
            
            // Show loading
            Swal.fire({
                title: 'Enrolling...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData();
            formData.append('action', 'enroll');
            formData.append('course_id', courseData.id);

            fetch('learning-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Server response was not JSON:', text);
                    throw new Error('Invalid server response');
                }
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Enrolled!',
                        text: 'You have been successfully enrolled.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Start the module immediately
                        startCourseModule({
                            id: data.course_data?.id || window.currentEnrollmentData.id,
                            name: window.currentEnrollmentData.title,
                            completed_modules: 0,
                            total_modules: data.course_data?.total_modules || window.currentEnrollmentData.modules_count
                        });
                    });
                } else {
                    Swal.fire('Error', data.message || 'Enrollment failed', 'error');
                }
            })
            .catch(error => {
                console.error('Enrollment Error:', error);
                Swal.fire('Error', 'An unexpected error occurred during enrollment.', 'error');
            });
        }
    });
}



// Start Examination - navigate to exam page
function startExamination(examId) {
    Swal.fire({
        title: 'Start Examination?',
        text: 'Once you begin, the timer will start. Are you ready?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#05386D',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, Start Exam',
        cancelButtonText: 'Not Yet'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `learning-exam.php?exam_id=${examId}`;
        }
    });
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCoursePreview();
        closeCourseModule();
    }
});

// Course Module Functions
let currentCourseData = null;
let currentModuleIndex = 0;

function startCourseModule(courseData) {
    currentCourseData = courseData;
    currentModuleIndex = courseData.completed_modules || 0;

    // If they've completed all modules, show completion message
    if (currentModuleIndex >= courseData.total_modules) {
        alert('Congratulations! You have completed all modules in this course.');
        return;
    }

    showModuleContent();
}

function showModuleContent() {
    const modal = document.getElementById('courseModuleModal');
    const title = document.getElementById('moduleTitle');
    const subtitle = document.getElementById('moduleSubtitle');
    const content = document.getElementById('moduleContent');
    const prevBtn = document.getElementById('prevModuleBtn');
    const nextBtn = document.getElementById('nextModuleBtn');

    // Update title and subtitle
    title.textContent = currentCourseData.name;
    subtitle.textContent = `Module ${currentModuleIndex + 1} of ${currentCourseData.total_modules}`;

    // Generate module content based on course name and module number
    const moduleContent = generateModuleContent(currentCourseData.name, currentModuleIndex + 1);

    content.innerHTML = moduleContent;

    // Update navigation buttons
    prevBtn.disabled = currentModuleIndex === 0;
    nextBtn.innerHTML = currentModuleIndex + 1 >= currentCourseData.total_modules ?
        'Complete Course <i class="fas fa-check ml-2"></i>' :
        'Next Module <i class="fas fa-arrow-right ml-2"></i>';

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function generateModuleContent(courseName, moduleNumber) {
    const modules = {
        'New Hire Orientation': [
            {
                title: 'Welcome to Costa Cargo Freight System',
                content: `
                    <div class="space-y-6">
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-xl font-bold text-blue-900 mb-3">Module 1: Company Overview</h4>
                            <p class="text-blue-800 leading-relaxed mb-4">
                                Welcome to Costa Cargo Freight System! This module introduces you to our company history,
                                mission, values, and organizational structure.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h5 class="font-semibold text-blue-900 mb-2">Our Mission</h5>
                                    <p class="text-sm text-blue-700">To provide reliable, efficient, and sustainable freight solutions worldwide.</p>
                                </div>
                                <div>
                                    <h5 class="font-semibold text-blue-900 mb-2">Our Values</h5>
                                    <p class="text-sm text-blue-700">Safety, Integrity, Excellence, and Customer Focus</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="text-lg font-bold text-gray-900 mb-4">Key Learning Points</h4>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                    <span class="text-gray-700">Understanding company history and evolution</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                    <span class="text-gray-700">Company mission, vision, and core values</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                    <span class="text-gray-700">Organizational structure and key departments</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                    <span class="text-gray-700">Company culture and workplace expectations</span>
                                </li>
                            </ul>
                        </div>

                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-lightbulb text-yellow-600 text-xl"></i>
                                <div>
                                    <h5 class="font-semibold text-yellow-900">Did You Know?</h5>
                                    <p class="text-yellow-800 text-sm">Costa Cargo has been serving customers for over 25 years, with operations in 15 countries and a fleet of over 500 vehicles.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Workplace Safety & Compliance',
                content: `
                    <div class="space-y-6">
                        <div class="bg-red-50 rounded-lg p-6 border border-red-200">
                            <h4 class="text-xl font-bold text-red-900 mb-3">Module 2: Safety First</h4>
                            <p class="text-red-800 leading-relaxed mb-4">
                                Safety is our top priority at Costa Cargo. This module covers essential safety protocols,
                                emergency procedures, and compliance requirements.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-shield-alt text-blue-600"></i>
                                    Safety Equipment
                                </h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Safety vests and helmets</li>
                                    <li>• Steel-toed boots</li>
                                    <li>• High-visibility clothing</li>
                                    <li>• Personal protective equipment (PPE)</li>
                                </ul>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-exclamation-triangle text-orange-600"></i>
                                    Emergency Procedures
                                </h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Emergency exit locations</li>
                                    <li>• First aid kit locations</li>
                                    <li>• Emergency contact numbers</li>
                                    <li>• Evacuation procedures</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                            <h5 class="font-semibold text-green-900 mb-2">Safety Checklist</h5>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded">
                                    <span class="text-sm text-green-800">I understand the safety protocols</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded">
                                    <span class="text-sm text-green-800">I know the location of emergency exits</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded">
                                    <span class="text-sm text-green-800">I am familiar with PPE requirements</span>
                                </label>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Freight Management System Basics',
                content: `
                    <div class="space-y-6">
                        <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-200">
                            <h4 class="text-xl font-bold text-indigo-900 mb-3">Module 3: FMS Overview</h4>
                            <p class="text-indigo-800 leading-relaxed mb-4">
                                Learn the fundamentals of our Freight Management System (FMS) and how it supports
                                our daily operations and customer service.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-truck text-3xl text-indigo-600 mb-2"></i>
                                <h6 class="font-semibold text-gray-900">Shipment Tracking</h6>
                                <p class="text-sm text-gray-600">Monitor cargo from pickup to delivery</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-route text-3xl text-green-600 mb-2"></i>
                                <h6 class="font-semibold text-gray-900">Route Optimization</h6>
                                <p class="text-sm text-gray-600">Efficient delivery planning</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-chart-bar text-3xl text-blue-600 mb-2"></i>
                                <h6 class="font-semibold text-gray-900">Analytics</h6>
                                <p class="text-sm text-gray-600">Performance insights and reporting</p>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-6">
                            <h5 class="font-semibold text-gray-900 mb-3">Key FMS Features</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h6 class="text-indigo-600 font-medium mb-2">For Employees:</h6>
                                    <ul class="text-sm text-gray-700 space-y-1">
                                        <li>• Shipment status updates</li>
                                        <li>• Route planning tools</li>
                                        <li>• Customer communication</li>
                                        <li>• Performance tracking</li>
                                    </ul>
                                </div>
                                <div>
                                    <h6 class="text-indigo-600 font-medium mb-2">For Customers:</h6>
                                    <ul class="text-sm text-gray-700 space-y-1">
                                        <li>• Real-time tracking</li>
                                        <li>• Online booking</li>
                                        <li>• Invoice management</li>
                                        <li>• Support portal</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Employee Code of Conduct',
                content: `
                    <div class="space-y-6">
                        <div class="bg-purple-50 rounded-lg p-6 border border-purple-200">
                            <h4 class="text-xl font-bold text-purple-900 mb-3">Module 4: Professional Standards</h4>
                            <p class="text-purple-800 leading-relaxed mb-4">
                                Understanding our code of conduct ensures we maintain the highest standards of
                                professionalism and integrity in all our operations.
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                                    <i class="fas fa-handshake text-green-600"></i>
                                    Professional Conduct
                                </h5>
                                <p class="text-sm text-gray-700">Maintain professional relationships with colleagues, customers, and partners. Respect diversity and promote a positive work environment.</p>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                                    <i class="fas fa-lock text-blue-600"></i>
                                    Confidentiality
                                </h5>
                                <p class="text-sm text-gray-700">Protect sensitive company and customer information. Never share proprietary data or customer details without authorization.</p>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                                    <i class="fas fa-balance-scale text-orange-600"></i>
                                    Ethics & Compliance
                                </h5>
                                <p class="text-sm text-gray-700">Follow all laws, regulations, and company policies. Report any violations or concerns through proper channels.</p>
                            </div>
                        </div>

                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <h5 class="font-semibold text-yellow-900 mb-2">Remember</h5>
                            <p class="text-yellow-800 text-sm">Your actions reflect on the entire Costa Cargo team. Always act with integrity, professionalism, and respect for others.</p>
                        </div>
                    </div>
                `
            }
        ],
        'Leadership in Logistics': [
            {
                title: 'Leadership Foundations',
                content: `
                    <div class="space-y-6">
                        <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-200">
                            <h4 class="text-xl font-bold text-indigo-900 mb-3">Module 1: What is Leadership?</h4>
                            <p class="text-indigo-800 leading-relaxed mb-4">
                                Leadership is the art of inspiring and guiding others toward achieving common goals.
                                This module explores different leadership styles and their applications.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Leadership Styles</h5>
                                <ul class="space-y-2 text-sm">
                                    <li><strong>Autocratic:</strong> Centralized decision-making</li>
                                    <li><strong>Democratic:</strong> Team-based decisions</li>
                                    <li><strong>Laissez-faire:</strong> Minimal supervision</li>
                                    <li><strong>Transformational:</strong> Inspiring change</li>
                                </ul>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Key Leadership Traits</h5>
                                <ul class="space-y-2 text-sm">
                                    <li>• Vision and purpose</li>
                                    <li>• Communication skills</li>
                                    <li>• Emotional intelligence</li>
                                    <li>• Decision-making ability</li>
                                    <li>• Integrity and ethics</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                            <h5 class="font-semibold text-green-900 mb-2">Leadership Self-Assessment</h5>
                            <p class="text-green-800 text-sm mb-3">Rate yourself on these leadership qualities (1-5 scale):</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                                <div>Communication: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Empathy: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Vision: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Decision Making: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Team Building: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Integrity: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Team Building & Motivation',
                content: `
                    <div class="space-y-6">
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-xl font-bold text-blue-900 mb-3">Module 2: Building High-Performing Teams</h4>
                            <p class="text-blue-800 leading-relaxed mb-4">
                                Effective leaders understand how to build, motivate, and maintain high-performing teams.
                                This module covers essential team building strategies and motivation techniques.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-users text-blue-600"></i>
                                    Team Building Activities
                                </h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Icebreaker exercises</li>
                                    <li>• Trust-building activities</li>
                                    <li>• Problem-solving challenges</li>
                                    <li>• Team retreats and workshops</li>
                                    <li>• Cross-functional projects</li>
                                </ul>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-bullseye text-green-600"></i>
                                    Motivation Strategies
                                </h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Clear goal setting</li>
                                    <li>• Recognition and rewards</li>
                                    <li>• Career development opportunities</li>
                                    <li>• Work-life balance support</li>
                                    <li>• Meaningful work assignments</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <h5 class="font-semibold text-yellow-900 mb-2">Motivation Theory</h5>
                            <p class="text-yellow-800 text-sm mb-3">Understanding what drives people is key to effective leadership:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <strong>Intrinsic Motivation:</strong> Internal satisfaction from the work itself
                                </div>
                                <div>
                                    <strong>Extrinsic Motivation:</strong> External rewards and recognition
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Communication & Conflict Resolution',
                content: `
                    <div class="space-y-6">
                        <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                            <h4 class="text-xl font-bold text-green-900 mb-3">Module 3: Effective Communication</h4>
                            <p class="text-green-800 leading-relaxed mb-4">
                                Communication is the foundation of leadership. Learn to communicate clearly,
                                listen actively, and resolve conflicts constructively.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-comments text-blue-600 text-2xl mb-2"></i>
                                <h6 class="font-semibold text-gray-900 mb-1">Verbal</h6>
                                <p class="text-sm text-gray-600">Clear, concise speech</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-envelope text-green-600 text-2xl mb-2"></i>
                                <h6 class="font-semibold text-gray-900 mb-1">Written</h6>
                                <p class="text-sm text-gray-600">Professional documentation</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-users text-purple-600 text-2xl mb-2"></i>
                                <h6 class="font-semibold text-gray-900 mb-1">Non-verbal</h6>
                                <p class="text-sm text-gray-600">Body language & presence</p>
                            </div>
                        </div>

                        <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                            <h5 class="font-semibold text-red-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-gavel"></i>
                                Conflict Resolution Steps
                            </h5>
                            <ol class="list-decimal list-inside space-y-2 text-sm text-red-800">
                                <li>Identify the conflict and involved parties</li>
                                <li>Understand each perspective</li>
                                <li>Find common ground and shared goals</li>
                                <li>Brainstorm mutually beneficial solutions</li>
                                <li>Agree on a resolution and follow-up plan</li>
                                <li>Monitor progress and adjust as needed</li>
                            </ol>
                        </div>
                    </div>
                `
            },
            {
                title: 'Strategic Leadership & Change Management',
                content: `
                    <div class="space-y-6">
                        <div class="bg-purple-50 rounded-lg p-6 border border-purple-200">
                            <h4 class="text-xl font-bold text-purple-900 mb-3">Module 4: Leading Through Change</h4>
                            <p class="text-purple-800 leading-relaxed mb-4">
                                Strategic leadership involves guiding organizations through change while maintaining
                                stability and achieving long-term objectives.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Strategic Planning Process</h5>
                                <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                                    <li>Assess current situation</li>
                                    <li>Define vision and goals</li>
                                    <li>Analyze opportunities and threats</li>
                                    <li>Develop action plans</li>
                                    <li>Implement and monitor progress</li>
                                    <li>Adjust strategies as needed</li>
                                </ol>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Change Management</h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Communicate vision clearly</li>
                                    <li>• Address resistance proactively</li>
                                    <li>• Provide support and training</li>
                                    <li>• Celebrate small wins</li>
                                    <li>• Maintain open communication</li>
                                    <li>• Lead by example</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-200">
                            <h5 class="font-semibold text-indigo-900 mb-2">Leadership Legacy</h5>
                            <p class="text-indigo-800 text-sm">Great leaders create lasting impact by:</p>
                            <ul class="list-disc list-inside space-y-1 text-sm text-indigo-700 mt-2">
                                <li>Developing future leaders</li>
                                <li>Building sustainable systems</li>
                                <li>Fostering innovation and growth</li>
                                <li>Creating positive organizational culture</li>
                                <li>Driving meaningful change</li>
                            </ul>
                        </div>
                    </div>
                `
            },
            {
                title: 'Time Management & Productivity',
                content: `
                    <div class="space-y-6">
                        <div class="bg-amber-50 rounded-lg p-6 border border-amber-200">
                            <h4 class="text-xl font-bold text-amber-900 mb-3">Module 5: Maximizing Efficiency</h4>
                            <p class="text-amber-800 leading-relaxed mb-4">
                                High-performing logistics leaders must master their time. Learn advanced techniques to prioritize 
                                tasks and eliminate productivity bottlenecks.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white p-4 border border-gray-200 rounded-lg shadow-sm">
                                <h6 class="font-bold text-gray-900 mb-2 flex items-center gap-2">
                                    <i class="fas fa-hourglass-half text-amber-500"></i>
                                    Eisenhower Matrix
                                </h6>
                                <p class="text-xs text-gray-600 mb-3">Categorize tasks by Urgency and Importance to focus on what truly matters.</p>
                                <div class="grid grid-cols-2 gap-1 h-12">
                                    <div class="bg-red-500 rounded-sm"></div>
                                    <div class="bg-green-500 rounded-sm"></div>
                                    <div class="bg-yellow-500 rounded-sm"></div>
                                    <div class="bg-gray-300 rounded-sm"></div>
                                </div>
                            </div>
                            <div class="bg-white p-4 border border-gray-200 rounded-lg shadow-sm">
                                <h6 class="font-bold text-gray-900 mb-2 flex items-center gap-2">
                                    <i class="fas fa-tasks text-blue-500"></i>
                                    Batch Processing
                                </h6>
                                <p class="text-xs text-gray-600 mb-3">Group similar tasks together to maintain deep focus and reduce context-switching costs.</p>
                                <div class="flex gap-1 h-3 mt-2">
                                    <div class="h-3 w-3 bg-blue-500 rounded-full"></div>
                                    <div class="h-3 w-3 bg-blue-500 rounded-full"></div>
                                    <div class="h-3 w-3 bg-blue-500 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Emotional Intelligence (EQ)',
                content: `
                    <div class="space-y-6">
                        <div class="bg-emerald-50 rounded-lg p-6 border border-emerald-200">
                            <h4 class="text-xl font-bold text-emerald-900 mb-3">Module 6: Leading with Empathy</h4>
                            <p class="text-emerald-800 leading-relaxed mb-4">
                                EQ is often more important than IQ for leaders. Understand the four pillars of Emotional 
                                Intelligence to build stronger relationships.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-white border-b-2 border-emerald-500 rounded shadow-sm text-center">
                                <div class="text-lg font-bold text-emerald-700">Self-Awareness</div>
                                <p class="text-[10px] text-gray-400 uppercase">Internal Knowledge</p>
                            </div>
                            <div class="p-3 bg-white border-b-2 border-blue-500 rounded shadow-sm text-center">
                                <div class="text-lg font-bold text-blue-700">Self-Management</div>
                                <p class="text-[10px] text-gray-400 uppercase">Self Control</p>
                            </div>
                            <div class="p-3 bg-white border-b-2 border-purple-500 rounded shadow-sm text-center">
                                <div class="text-lg font-bold text-purple-700">Social Awareness</div>
                                <p class="text-[10px] text-gray-400 uppercase">Empathy & Context</p>
                            </div>
                            <div class="p-3 bg-white border-b-2 border-orange-500 rounded shadow-sm text-center">
                                <div class="text-lg font-bold text-orange-700">Relationship Mgmt</div>
                                <p class="text-[10px] text-gray-400 uppercase">Inspiration & Conflict</p>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Decision-Making Under Pressure',
                content: `
                    <div class="space-y-6">
                        <div class="bg-rose-50 rounded-lg p-6 border border-rose-200">
                            <h4 class="text-xl font-bold text-rose-900 mb-3">Module 7: The OODA Loop</h4>
                            <p class="text-rose-800 leading-relaxed mb-4">
                                In the fast-paced world of logistics, decisions must be made quickly and accurately. 
                                Master the OODA loop framework for rapid tactical response.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-4 bg-white p-3 rounded-lg border border-gray-100 shadow-sm relative overflow-hidden">
                                <div class="w-10 h-10 bg-rose-600 text-white rounded-full flex items-center justify-center font-bold shrink-0">O</div>
                                <div>
                                    <h6 class="font-bold">Observe</h6>
                                    <p class="text-xs text-gray-500">Scan the environment for changes and emerging data.</p>
                                </div>
                                <div class="absolute right-0 top-0 h-full w-1 bg-rose-600"></div>
                            </div>
                            <div class="flex items-center gap-4 bg-white p-3 rounded-lg border border-gray-100 shadow-sm relative overflow-hidden">
                                <div class="w-10 h-10 bg-rose-500 text-white rounded-full flex items-center justify-center font-bold shrink-0">O</div>
                                <div>
                                    <h6 class="font-bold">Orient</h6>
                                    <p class="text-xs text-gray-500">Put findings in context of past experience and mental models.</p>
                                </div>
                                <div class="absolute right-0 top-0 h-full w-1 bg-rose-500"></div>
                            </div>
                            <div class="flex items-center gap-4 bg-white p-3 rounded-lg border border-gray-100 shadow-sm relative overflow-hidden">
                                <div class="w-10 h-10 bg-rose-400 text-white rounded-full flex items-center justify-center font-bold shrink-0">D</div>
                                <div>
                                    <h6 class="font-bold">Decide</h6>
                                    <p class="text-xs text-gray-500">Determine the best course of action among alternatives.</p>
                                </div>
                                <div class="absolute right-0 top-0 h-full w-1 bg-rose-400"></div>
                            </div>
                            <div class="flex items-center gap-4 bg-white p-3 rounded-lg border border-gray-100 shadow-sm relative overflow-hidden">
                                <div class="w-10 h-10 bg-rose-300 text-white rounded-full flex items-center justify-center font-bold shrink-0">A</div>
                                <div>
                                    <h6 class="font-bold">Act</h6>
                                    <p class="text-xs text-gray-500">Execute the decision and observe the new result.</p>
                                </div>
                                <div class="absolute right-0 top-0 h-full w-1 bg-rose-300"></div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Performance Management',
                content: `
                    <div class="space-y-6">
                        <div class="bg-cyan-50 rounded-lg p-6 border border-cyan-200">
                            <h4 class="text-xl font-bold text-cyan-900 mb-3">Module 8: Driving Results</h4>
                            <p class="text-cyan-800 leading-relaxed mb-4">
                                Learn to set clear expectations, provide constructive feedback, and coach your team to 
                                reach their full operational potential.
                            </p>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-lg p-5">
                            <h5 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-line text-cyan-600"></i>
                                Goal Alignment
                            </h5>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Individual Goals</span>
                                    <span class="font-bold">85% Aligned</span>
                                </div>
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-cyan-500" style="width: 85%"></div>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Operational KPIs</span>
                                    <span class="font-bold">92% Met</span>
                                </div>
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500" style="width: 92%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Leading Diverse Teams',
                content: `
                    <div class="space-y-6">
                        <div class="bg-violet-50 rounded-lg p-6 border border-violet-200">
                            <h4 class="text-xl font-bold text-violet-900 mb-3">Module 9: Inclusion & Innovation</h4>
                            <p class="text-violet-800 leading-relaxed mb-4">
                                Diverse perspectives drive better problem-solving. Learn to create an inclusive 
                                environment where every team member feels valued and heard.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-lg flex items-center gap-4">
                                <div class="text-3xl text-violet-500">
                                    <i class="fas fa-globe-americas"></i>
                                </div>
                                <div>
                                    <h6 class="font-bold text-sm">Global Perspective</h6>
                                    <p class="text-[10px] text-gray-500">Leverage cultural differences for better market understanding.</p>
                                </div>
                            </div>
                            <div class="p-4 bg-white border border-gray-200 rounded-lg flex items-center gap-4">
                                <div class="text-3xl text-indigo-500">
                                    <i class="fas fa-brain"></i>
                                </div>
                                <div>
                                    <h6 class="font-bold text-sm">Cognitive Diversity</h6>
                                    <p class="text-[10px] text-gray-500">Encourage different thinking styles to solve complex logistics hurdles.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Ethics & Corporate Responsibility',
                content: `
                    <div class="space-y-6">
                        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                            <h4 class="text-xl font-bold text-gray-900 mb-3">Module 10: The Ethical Leader</h4>
                            <p class="text-gray-700 leading-relaxed mb-4">
                                Integrity is the non-negotiable trait of a leader. Understand our corporate values 
                                and your role in maintaining them within the logistics chain.
                            </p>
                        </div>
                        <div class="bg-gray-900 text-white rounded-xl p-6 text-center shadow-lg">
                            <div class="mb-4">
                                <i class="fas fa-balance-scale text-4xl text-yellow-400"></i>
                            </div>
                            <h5 class="text-xl font-bold mb-2">Lead with Integrity</h5>
                            <p class="text-sm text-gray-400 mb-6">"Ethics is doing the right thing, even when no one is watching."</p>
                            <div class="flex justify-center gap-4">
                                <div class="px-4 py-2 bg-white/10 rounded-full text-xs border border-white/20 whitespace-nowrap">Corporate Social Responsibility</div>
                                <div class="px-4 py-2 bg-white/10 rounded-full text-xs border border-white/20 whitespace-nowrap">Ethical Sourcing</div>
                            </div>
                        </div>
                    </div>
                `
            }
        ],
        'Advanced Fleet Management Techniques': [
            {
                title: 'Fleet Lifecycle Management',
                content: `
                    <div class="space-y-6">
                        <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-200">
                            <h4 class="text-xl font-bold text-indigo-900 mb-3">Module 1: The Lifecycle Strategy</h4>
                            <p class="text-indigo-800 leading-relaxed mb-4">
                                Managing a fleet effectively requires understanding the entire lifecycle of a vehicle, 
                                from procurement planning to final disposal.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white border-l-4 border-indigo-500 p-4 shadow-sm">
                                <h6 class="font-bold text-gray-900">Procurement Planning</h6>
                                <p class="text-sm text-gray-600">Selecting the right vehicles based on total cost of ownership (TCO) and operational needs.</p>
                            </div>
                            <div class="bg-white border-l-4 border-purple-500 p-4 shadow-sm">
                                <h6 class="font-bold text-gray-900">Optimal Replacement</h6>
                                <p class="text-sm text-gray-600">Determining the "sweet spot" for selling vehicles to minimize depreciation and maintenance costs.</p>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Telematics & GPS Integration',
                content: `
                    <div class="space-y-6">
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-xl font-bold text-blue-900 mb-3">Module 2: Connected Fleet</h4>
                            <p class="text-blue-800 leading-relaxed mb-4">
                                Telematics provides the visibility needed for real-time decision making and long-term strategic planning.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <div class="p-4 bg-gray-50 border-b border-gray-200 font-bold">Data Points Captured</div>
                            <div class="grid grid-cols-2 p-4 gap-4">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-location-arrow text-blue-500"></i>
                                    <span class="text-sm">Precise GPS Location</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-tachometer-alt text-red-500"></i>
                                    <span class="text-sm">Engine Diagnostics</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-user-clock text-green-500"></i>
                                    <span class="text-sm">Driver Hours (HOS)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-gas-pump text-orange-500"></i>
                                    <span class="text-sm">Fuel Consumption</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Fuel Management & Efficiency',
                content: `
                    <div class="space-y-6">
                        <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                            <h4 class="text-xl font-bold text-green-900 mb-3">Module 3: Fuel Economy Strategies</h4>
                            <p class="text-green-800 leading-relaxed mb-4">
                                Fuel is often the second largest expense. Learn how to monitor and reduce consumption across your entire fleet.
                            </p>
                        </div>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <h5 class="font-bold mb-3">Consumption Reduction Factors</h5>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between bg-white p-2 rounded">
                                    <span class="text-sm">Idle Time Reduction</span>
                                    <span class="text-green-600 font-bold">-15% Fuel</span>
                                </div>
                                <div class="flex items-center justify-between bg-white p-2 rounded">
                                    <span class="text-sm">Speed Management</span>
                                    <span class="text-green-600 font-bold">-10% Fuel</span>
                                </div>
                                <div class="flex items-center justify-between bg-white p-2 rounded">
                                    <span class="text-sm">Proper Tire Pressure</span>
                                    <span class="text-green-600 font-bold">-3% Fuel</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Driver Safety & Behavior',
                content: `
                    <div class="space-y-6">
                        <div class="bg-orange-50 rounded-lg p-6 border border-orange-200">
                            <h4 class="text-xl font-bold text-orange-900 mb-3">Module 4: Safety First</h4>
                            <p class="text-orange-800 leading-relaxed mb-4">
                                Improving driver behavior is critical for reducing accidents, insurance premiums, and vehicle wear.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-lg">
                                <h6 class="font-bold mb-2">Driver Scorecards</h6>
                                <p class="text-sm text-gray-600 italic">"What gets measured, gets managed."</p>
                                <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                    <li>• Harsh Braking events</li>
                                    <li>• Rapid Acceleration</li>
                                    <li>• Excessive Speeding</li>
                                </ul>
                            </div>
                            <div class="p-4 bg-white border border-gray-200 rounded-lg flex items-center justify-center">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">88/100</div>
                                    <p class="text-xs text-gray-400">Average Safety Score</p>
                                    <div class="w-full h-1 bg-gray-100 rounded-full mt-2 overflow-hidden">
                                        <div class="h-full bg-green-500" style="width: 88%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Predictive Maintenance',
                content: `
                    <div class="space-y-6">
                        <div class="bg-red-50 rounded-lg p-6 border border-red-200">
                            <h4 class="text-xl font-bold text-red-900 mb-3">Module 5: Proactive Upkeep</h4>
                            <p class="text-red-800 leading-relaxed mb-4">
                                Move beyond traditional schedules to AI-driven predictive maintenance that spots issues before they cause breakdowns.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                                <h5 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-stethoscope text-red-500"></i>
                                    Health Monitoring
                                </h5>
                                <p class="text-sm text-gray-600 leading-relaxed">
                                    Continuous engine diagnostic monitoring (DTC codes) alerts the shop immediately of any issues during a route.
                                </p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                                <h5 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-history text-blue-500"></i>
                                    Trend Analysis
                                </h5>
                                <p class="text-sm text-gray-600 leading-relaxed">
                                    Historical data patterns indicate exactly when a part is likely to fail, allowing for pre-failure replacement.
                                </p>
                            </div>
                        </div>
                        <div class="bg-gray-900 text-white rounded-lg p-5">
                            <div class="flex justify-between items-center mb-4">
                                <span class="text-sm font-bold uppercase tracking-wider text-gray-400">Maintenance ROI</span>
                                <span class="bg-green-500 text-white text-xs px-2 py-1 rounded">High Impact</span>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div class="text-2xl font-bold text-blue-400">-25%</div>
                                    <p class="text-[10px] text-gray-500 uppercase">Breakdowns</p>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-indigo-400">-15%</div>
                                    <p class="text-[10px] text-gray-500 uppercase">Repair Costs</p>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-purple-400">+10%</div>
                                    <p class="text-[10px] text-gray-500 uppercase">Uptime</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Fleet Electrification Strategies',
                content: `
                    <div class="space-y-6">
                        <div class="bg-emerald-50 rounded-lg p-6 border border-emerald-200">
                            <h4 class="text-xl font-bold text-emerald-900 mb-3">Module 6: Sustainable Future</h4>
                            <p class="text-emerald-800 leading-relaxed mb-4">
                                Transitioning to an Electric Vehicle (EV) fleet requires careful infrastructure planning and power management.
                            </p>
                        </div>
                        <div class="space-y-4">
                            <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-start gap-4">
                                <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-600 shrink-0">
                                    <i class="fas fa-bolt text-xl"></i>
                                </div>
                                <div>
                                    <h6 class="font-bold">Charging Infrastructure</h6>
                                    <p class="text-sm text-gray-600">Level 2 vs DC Fast Charging: Choosing the right charger for duty cycles.</p>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-start gap-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 shrink-0">
                                    <i class="fas fa-route text-xl"></i>
                                </div>
                                <div>
                                    <h6 class="font-bold">Range Optimization</h6>
                                    <p class="text-sm text-gray-600">How payload, weather, and topography affect EV range and route planning.</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-emerald-900 text-white rounded-xl p-6 relative overflow-hidden">
                            <div class="relative z-10">
                                <h5 class="text-lg font-bold mb-2">Environmental Impact</h5>
                                <p class="text-sm text-emerald-200 mb-4">Reducing our carbon footprint by 40% over the next 5 years through fleet electrification.</p>
                                <div class="h-2 bg-emerald-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-400" style="width: 25%"></div>
                                </div>
                                <div class="flex justify-between mt-2 text-xs text-emerald-300 font-medium">
                                    <span>Current Adoption</span>
                                    <span>25% Goal Reached</span>
                                </div>
                            </div>
                            <i class="fas fa-leaf text-9xl absolute -bottom-10 -right-10 text-emerald-800 opacity-20"></i>
                        </div>
                    </div>
                `
            },
            {
                title: 'Regulatory Compliance & ELD',
                content: `
                    <div class="space-y-6">
                        <div class="bg-rose-50 rounded-lg p-6 border border-rose-200">
                            <h4 class="text-xl font-bold text-rose-900 mb-3">Module 7: Compliance Mastery</h4>
                            <p class="text-rose-800 leading-relaxed mb-4">
                                Navigating FMCSA regulations and effectively using Electronic Logging Devices (ELD) to ensure fleet safety.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 border-2 border-dashed border-rose-200 rounded-lg bg-white">
                                <h6 class="font-bold text-rose-900 flex items-center gap-2 mb-2">
                                    <i class="fas fa-clock"></i>
                                    Hours of Service (HOS)
                                </h6>
                                <p class="text-xs text-gray-600">Managing driver duty cycles to prevent fatigue-related violations and accidents.</p>
                            </div>
                            <div class="p-4 border-2 border-dashed border-rose-200 rounded-lg bg-white">
                                <h6 class="font-bold text-rose-900 flex items-center gap-2 mb-2">
                                    <i class="fas fa-file-signature"></i>
                                    DVIR Process
                                </h6>
                                <p class="text-xs text-gray-600">Ensuring digital Driver Vehicle Inspection Reports are completed accurately every day.</p>
                            </div>
                        </div>
                        <div class="bg-rose-600 text-white p-4 rounded-lg flex items-start gap-4 shadow-lg">
                            <div class="bg-white/20 p-3 rounded-full">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div>
                                <h6 class="font-bold">Avoid DOT Fines</h6>
                                <p class="text-sm opacity-90">Incomplete logs are the #1 reason for DOT fines. Learn how ELD automation eliminates manual entry errors.</p>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Advanced Fleet Analytics',
                content: `
                    <div class="space-y-6">
                        <div class="bg-cyan-50 rounded-lg p-6 border border-cyan-200">
                            <h4 class="text-xl font-bold text-cyan-900 mb-3">Module 8: Data-Driven Logistics</h4>
                            <p class="text-cyan-800 leading-relaxed mb-4">
                                Leverage big data to optimize fleet size, routes, and operational workflows for maximum competitive advantage.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white p-4 rounded-lg border border-gray-200">
                                <h6 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Fleet Utilization</h6>
                                <div class="flex items-end gap-2 text-3xl font-bold text-cyan-600">
                                    92%
                                    <span class="text-xs text-green-500 mb-2">+4% vs LW</span>
                                </div>
                                <div class="flex gap-1 h-3 mt-4">
                                    <div class="flex-1 bg-cyan-600 rounded-sm"></div>
                                    <div class="flex-1 bg-cyan-500 rounded-sm"></div>
                                    <div class="flex-1 bg-cyan-400 rounded-sm"></div>
                                    <div class="flex-1 bg-cyan-300 rounded-sm"></div>
                                    <div class="flex-1 bg-gray-100 rounded-sm"></div>
                                </div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-gray-200">
                                <h6 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Operating Cost/Mile</h6>
                                <div class="flex items-end gap-2 text-3xl font-bold text-cyan-600">
                                    $1.84
                                    <span class="text-xs text-green-500 mb-2">-2% vs YTD</span>
                                </div>
                                <div class="flex gap-1 h-3 mt-4">
                                    <div class="flex-1 bg-green-500 rounded-sm"></div>
                                    <div class="flex-1 bg-green-400 rounded-sm"></div>
                                    <div class="flex-1 bg-green-300 rounded-sm"></div>
                                    <div class="flex-1 bg-gray-100 rounded-sm"></div>
                                    <div class="flex-1 bg-gray-100 rounded-sm"></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-indigo-600 text-white rounded-lg p-6 text-center shadow-xl">
                            <h5 class="text-xl font-bold mb-2">Final Certification Available!</h5>
                            <p class="mb-4 opacity-90 text-sm">Congratulations! You have mastered Advanced Fleet Management Techniques.</p>
                            <div class="flex justify-center gap-4">
                                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-medal text-yellow-400 text-2xl"></i>
                                </div>
                                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-graduation-cap text-2xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            }
        ],
        'Safety & Compliance Fundamentals': [
            {
                title: 'Introduction to Fleet Safety',
                content: `
                    <div class="space-y-6">
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-xl font-bold text-blue-900 mb-3">Module 1: Safety Culture</h4>
                            <p class="text-blue-800 leading-relaxed mb-4">
                                Safety is our top priority. Understanding the importance of a safety-first mindset is 
                                crucial for every member of the Costa Cargo team.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-lg">
                                <h6 class="font-bold mb-2 flex items-center gap-2">
                                    <i class="fas fa-shield-alt text-blue-600"></i>
                                    Goal Zero
                                </h6>
                                <p class="text-sm text-gray-600">Our ultimate objective is zero accidents, zero injuries, and zero damage.</p>
                            </div>
                            <div class="p-4 bg-white border border-gray-200 rounded-lg">
                                <h6 class="font-bold mb-2 flex items-center gap-2">
                                    <i class="fas fa-eye text-blue-600"></i>
                                    Vigilance
                                </h6>
                                <p class="text-sm text-gray-600">Staying alert and identifying potential risks before they become incidents.</p>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Workplace Safety Protocols',
                content: `
                    <div class="space-y-6">
                        <div class="bg-orange-50 rounded-lg p-6 border border-orange-200">
                            <h4 class="text-xl font-bold text-orange-900 mb-3">Module 2: On-Site Safety</h4>
                            <p class="text-orange-800 leading-relaxed mb-4">
                                Proper safety practices in the warehouse and depot are essential to prevent workplace injuries.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <h5 class="font-bold mb-3">Required PPE</h5>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="text-center">
                                    <i class="fas fa-hard-hat text-2xl text-yellow-600 mb-2"></i>
                                    <p class="text-xs font-bold">Hard Hat</p>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-vest text-2xl text-orange-500 mb-2"></i>
                                    <p class="text-xs font-bold">High-Viz Vest</p>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-boot text-2xl text-gray-700 mb-2"></i>
                                    <p class="text-xs font-bold">Steel Toe Boots</p>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-glasses text-2xl text-blue-400 mb-2"></i>
                                    <p class="text-xs font-bold">Safety Glasses</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Vehicle Maintenance & Inspection',
                content: `
                    <div class="space-y-6">
                        <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                            <h4 class="text-xl font-bold text-green-900 mb-3">Module 3: Daily Checks</h4>
                            <p class="text-green-800 leading-relaxed mb-4">
                                A safe vehicle starts with a thorough pre-trip inspection. Learn the key components to check every day.
                            </p>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded shadow-sm">
                                <span class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center font-bold">1</span>
                                <span class="text-sm">Brakes & Air Systems</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded shadow-sm">
                                <span class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center font-bold">2</span>
                                <span class="text-sm">Tires & Wheel Assemblies</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded shadow-sm">
                                <span class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center font-bold">3</span>
                                <span class="text-sm">Lights & Reflectors</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded shadow-sm">
                                <span class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center font-bold">4</span>
                                <span class="text-sm">Fluid Levels & Leaks</span>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Incident Reporting & Investigation',
                content: `
                    <div class="space-y-6">
                        <div class="bg-red-50 rounded-lg p-6 border border-red-200">
                            <h4 class="text-xl font-bold text-red-900 mb-3">Module 4: Response Protocol</h4>
                            <p class="text-red-800 leading-relaxed mb-4">
                                Knowing exactly what to do when an incident occurs is vital for ensuring everyone's safety and proper documentation.
                            </p>
                        </div>
                        <div class="bg-gray-900 text-white rounded-lg p-4">
                            <h5 class="font-bold text-red-400 mb-3 flex items-center gap-2">
                                <i class="fas fa-exclamation-circle"></i>
                                Emergency Steps
                            </h5>
                            <ul class="space-y-2 text-sm">
                                <li class="flex gap-2">
                                    <span class="text-red-400 font-bold">STOP:</span> Stop immediately in a safe location.
                                </li>
                                <li class="flex gap-2">
                                    <span class="text-red-400 font-bold">SECURE:</span> Turn on hazard lights and set up triangles.
                                </li>
                                <li class="flex gap-2">
                                    <span class="text-red-400 font-bold">REPORT:</span> Call local emergency services and your supervisor.
                                </li>
                                <li class="flex gap-2">
                                    <span class="text-red-400 font-bold">DOCUMENT:</span> Take photos and collect witness information.
                                </li>
                            </ul>
                        </div>
                    </div>
                `
            },
            {
                title: 'Hazard Identification & Risk Assessment',
                content: `
                    <div class="space-y-6">
                        <div class="bg-yellow-50 rounded-lg p-6 border border-yellow-200">
                            <h4 class="text-xl font-bold text-yellow-900 mb-3">Module 5: Proactive Safety</h4>
                            <p class="text-yellow-800 leading-relaxed mb-4">
                                Master the art of identifying hazards before they cause harm. Learn to use the Risk Matrix for effective mitigation.
                            </p>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 p-3 border-b border-gray-200 font-bold text-sm">Risk Assessment Matrix</div>
                            <div class="p-4">
                                <div class="grid grid-cols-3 gap-2 text-center text-[10px] font-bold uppercase mb-2">
                                    <div class="text-gray-400">Probability</div>
                                    <div class="text-gray-400">Severity</div>
                                    <div class="text-gray-400">Priority</div>
                                </div>
                                <div class="space-y-2">
                                    <div class="grid grid-cols-3 gap-2 items-center">
                                        <div class="bg-blue-100 text-blue-700 p-2 rounded">High</div>
                                        <div class="bg-red-100 text-red-700 p-2 rounded">Major</div>
                                        <div class="bg-red-600 text-white p-2 rounded animate-pulse">Critical</div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 items-center">
                                        <div class="bg-blue-50 text-blue-600 p-2 rounded">Medium</div>
                                        <div class="bg-orange-100 text-orange-700 p-2 rounded">Moderate</div>
                                        <div class="bg-orange-500 text-white p-2 rounded">High</div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 items-center">
                                        <div class="bg-blue-50 text-blue-600 p-2 rounded text-opacity-50">Low</div>
                                        <div class="bg-yellow-100 text-yellow-700 p-2 rounded">Minor</div>
                                        <div class="bg-yellow-500 text-white p-2 rounded">Normal</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-amber-100 text-amber-900 p-4 rounded-lg flex items-start gap-3 border border-amber-200">
                            <i class="fas fa-lightbulb text-xl mt-1"></i>
                            <p class="text-sm"><strong>Always Remember:</strong> If you see something unsafe, say something immediately. You have the authority and responsibility to STOP work if necessary.</p>
                        </div>
                    </div>
                `
            },
            {
                title: 'Regulatory Compliance Mastery',
                content: `
                    <div class="space-y-6">
                        <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-200">
                            <h4 class="text-xl font-bold text-indigo-900 mb-3">Module 6: Standard of Excellence</h4>
                            <p class="text-indigo-800 leading-relaxed mb-4">
                                Deep dive into the federal and local regulations that govern our industry and ensure we maintain our license to operate.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white p-4 rounded-lg border-2 border-indigo-100 shadow-sm">
                                <h6 class="font-bold text-gray-900 mb-2">DOT Regulations</h6>
                                <p class="text-xs text-gray-500 mb-3">Department of Transportation standards for vehicle safety and driver behavior.</p>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500" style="width: 100%"></div>
                                </div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border-2 border-indigo-100 shadow-sm">
                                <h6 class="font-bold text-gray-900 mb-2">OSHA Standards</h6>
                                <p class="text-xs text-gray-500 mb-3">Occupational Safety and Health Administration guidelines for workplace safety.</p>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-indigo-600 text-white rounded-xl p-6 text-center shadow-2xl relative overflow-hidden">
                            <div class="relative z-10">
                                <h5 class="text-xl font-bold mb-3 italic">"Compliance is not just a checkbox, it's a commitment to our families."</h5>
                                <div class="flex justify-center gap-6 mt-4">
                                    <div class="text-center">
                                        <div class="text-3xl font-bold">100%</div>
                                        <div class="text-[10px] uppercase opacity-75">Audit Ready</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-3xl font-bold">Safe</div>
                                        <div class="text-[10px] uppercase opacity-75">Always</div>
                                    </div>
                                </div>
                            </div>
                            <i class="fas fa-stamp text-9xl absolute -bottom-8 -right-8 text-black opacity-10 rotate-12"></i>
                        </div>
                    </div>
                `
            }
        ],
        'Route Optimization & Analytics': [
            {
                title: 'Introduction to Route Optimization',
                content: `
                    <div class="space-y-6">
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-xl font-bold text-blue-900 mb-3">Module 1: Fundamentals of Optimization</h4>
                            <p class="text-blue-800 leading-relaxed mb-4">
                                Route optimization is more than just finding the shortest path; it's about maximizing efficiency,
                                reducing costs, and meeting customer expectations.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-2">Key Objectives</h5>
                                <ul class="text-sm text-gray-700 space-y-1">
                                    <li>• Minimize fuel consumption</li>
                                    <li>• Reduce vehicle wear and tear</li>
                                    <li>• Improve driver productivity</li>
                                    <li>• Meet delivery windows</li>
                                </ul>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-2">Basic Variables</h5>
                                <ul class="text-sm text-gray-700 space-y-1">
                                    <li>• Distance and Time</li>
                                    <li>• Vehicle capacity</li>
                                    <li>• Driver hours</li>
                                    <li>• Traffic patterns</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Network Design Fundamentals',
                content: `
                    <div class="space-y-6">
                        <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-200">
                            <h4 class="text-xl font-bold text-indigo-900 mb-3">Module 2: Strategic Network Design</h4>
                            <p class="text-indigo-800 leading-relaxed mb-4">
                                Designing an efficient network involves optimizing the placement of hubs, depots, and distribution centers.
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h5 class="font-semibold text-gray-900 mb-4 text-center text-lg">Hub-and-Spoke Model</h5>
                            <div class="flex justify-center items-center gap-8 mb-4">
                                <div class="text-center">
                                    <div class="w-16 h-16 bg-indigo-600 text-white rounded-full flex items-center justify-center text-2xl mx-auto"><i class="fas fa-warehouse"></i></div>
                                    <p class="text-xs mt-2 font-bold">Main Hub</p>
                                </div>
                                <i class="fas fa-arrows-alt-h text-2xl text-gray-400"></i>
                                <div class="space-y-4">
                                    <div class="w-12 h-12 bg-indigo-400 text-white rounded-full flex items-center justify-center text-xl mx-auto"><i class="fas fa-truck"></i></div>
                                    <p class="text-xs font-bold text-center">Spokes</p>
                                </div>
                            </div>
                            <p class="text-sm text-center text-gray-600">Centralizing sorting and distribution improves resource utilization.</p>
                        </div>
                    </div>
                `
            },
            {
                title: 'Urban Logistics & Last Mile Delivery',
                content: `
                    <div class="space-y-6">
                        <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                            <h4 class="text-xl font-bold text-green-900 mb-3">Module 3: The Last Mile Challenge</h4>
                            <p class="text-green-800 leading-relaxed mb-4">
                                Last mile delivery is the most expensive and complex part of the supply chain.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Optimization Strategies</h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Dynamic routing</li>
                                    <li>• Micro-fulfillment centers</li>
                                    <li>• Parcel lockers</li>
                                    <li>• Crowd-sourced delivery</li>
                                </ul>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Urban Constraints</h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Traffic congestion</li>
                                    <li>• Limited parking</li>
                                    <li>• Delivery window restrictions</li>
                                    <li>• Environmental regulations</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Fleet Routing Algorithms',
                content: `
                    <div class="space-y-6">
                        <div class="bg-orange-50 rounded-lg p-6 border border-orange-200">
                            <h4 class="text-xl font-bold text-orange-900 mb-3">Module 4: Algorithms in Action</h4>
                            <p class="text-orange-800 leading-relaxed mb-4">
                                Modern route optimization relies on complex algorithms like Traveling Salesperson Problem (TSP)
                                and Vehicle Routing Problem (VRP).
                            </p>
                        </div>
                        <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                            <h5 class="font-semibold text-amber-900 mb-2">Algorithm Comparison</h5>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div class="p-3 bg-white rounded border border-amber-100">
                                    <p class="font-bold text-amber-900">Saving Algorithm</p>
                                    <p class="text-gray-600">Quickly finds near-optimal routes by combining stops.</p>
                                </div>
                                <div class="p-3 bg-white rounded border border-amber-100">
                                    <p class="font-bold text-amber-900">Genetic Algorithms</p>
                                    <p class="text-gray-600">Evolves solutions over generations for complex constraints.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Real-time Traffic & Rerouting',
                content: `
                    <div class="space-y-6">
                        <div class="bg-red-50 rounded-lg p-6 border border-red-200">
                            <h4 class="text-xl font-bold text-red-900 mb-3">Module 5: Dynamic Response</h4>
                            <p class="text-red-800 leading-relaxed mb-4">
                                Real-time data integration allows us to respond to unforeseen events like traffic jams, accidents, and weather.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white border-l-4 border-red-500 p-4 shadow-sm">
                                <h6 class="font-bold text-gray-900">Live Traffic Feed</h6>
                                <p class="text-sm text-gray-600">Integrating GPS and telematics data for up-to-the-minute updates.</p>
                            </div>
                            <div class="bg-white border-l-4 border-blue-500 p-4 shadow-sm">
                                <h6 class="font-bold text-gray-900">Automated Rerouting</h6>
                                <p class="text-sm text-gray-600">System automatically calculates alternatives once delay thresholds are met.</p>
                            </div>
                        </div>
                        <div class="bg-gray-100 p-4 rounded-lg flex items-center justify-between">
                            <div>
                                <h5 class="font-bold">Proactive Alerts</h5>
                                <p class="text-sm text-gray-600">Notify customers instantly of adjusted arrival times.</p>
                            </div>
                            <i class="fas fa-bell text-yellow-500 text-2xl animate-bounce"></i>
                        </div>
                    </div>
                `
            },
            {
                title: 'Analytics & Performance Metrics',
                content: `
                    <div class="space-y-6">
                        <div class="bg-cyan-50 rounded-lg p-6 border border-cyan-200">
                            <h4 class="text-xl font-bold text-cyan-900 mb-3">Module 6: Monitoring Success</h4>
                            <p class="text-cyan-800 leading-relaxed mb-4">
                                Analyzing route data helps identify trends, inefficiencies, and opportunities for continuous improvement.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="p-4 bg-white border border-gray-100 rounded-lg shadow-sm text-center">
                                <div class="text-cyan-600 text-2xl font-bold">12%</div>
                                <p class="text-xs uppercase text-gray-500 font-bold">Fuel Savings</p>
                            </div>
                            <div class="p-4 bg-white border border-gray-100 rounded-lg shadow-sm text-center">
                                <div class="text-green-600 text-2xl font-bold">98.5%</div>
                                <p class="text-xs uppercase text-gray-500 font-bold">On-Time Delivery</p>
                            </div>
                            <div class="p-4 bg-white border border-gray-100 rounded-lg shadow-sm text-center">
                                <div class="text-purple-600 text-2xl font-bold">-15%</div>
                                <p class="text-xs uppercase text-gray-500 font-bold">Idle Time</p>
                            </div>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <h5 class="font-bold mb-4">KPI Dashboard</h5>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>Route Compliance</span>
                                        <span>94%</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-cyan-500" style="width: 94%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>Capacity Utilization</span>
                                        <span>82%</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500" style="width: 82%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Advanced Optimization Case Studies',
                content: `
                    <div class="space-y-6">
                        <div class="bg-purple-50 rounded-lg p-6 border border-purple-200">
                            <h4 class="text-xl font-bold text-purple-900 mb-3">Module 7: Applied Excellence</h4>
                            <p class="text-purple-800 leading-relaxed mb-4">
                                Review real-world examples of how Costa Cargo has implemented advanced route optimization to transform its operations.
                            </p>
                        </div>
                        <div class="space-y-4">
                            <div class="p-4 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors">
                                <h6 class="font-bold flex items-center gap-2">
                                    <i class="fas fa-city text-indigo-500"></i>
                                    Metropolis Expansion
                                </h6>
                                <p class="text-sm text-gray-600 mt-1">How we reduced delivery times by 20% in high-density urban areas.</p>
                            </div>
                            <div class="p-4 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors">
                                <h6 class="font-bold flex items-center gap-2">
                                    <i class="fas fa-leaf text-green-500"></i>
                                    Green Path Initiative
                                </h6>
                                <p class="text-sm text-gray-600 mt-1">Reducing carbon footprint through eco-routing and fleet electrification.</p>
                            </div>
                            <div class="p-4 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors">
                                <h6 class="font-bold flex items-center gap-2">
                                    <i class="fas fa-snowflake text-blue-400"></i>
                                    Cold Chain Efficiency
                                </h6>
                                <p class="text-sm text-gray-600 mt-1">Optimizing routes for temperature-sensitive cargo under strict time constraints.</p>
                            </div>
                        </div>
                        <div class="bg-indigo-600 text-white rounded-lg p-6 text-center">
                            <h5 class="text-xl font-bold mb-2">Final Certification Ready!</h5>
                            <p class="mb-4 opacity-90">You have completed all 7 modules of Route Optimization & Analytics.</p>
                            <i class="fas fa-graduation-cap text-4xl opacity-50"></i>
                        </div>
                    </div>
                `
            }
        ]
    };

    // Get course-specific modules or use default
    const courseModules = modules[courseName] || modules['New Hire Orientation'];

    // Return the appropriate module content
    if (moduleNumber <= courseModules.length) {
        return courseModules[moduleNumber - 1].content;
    }

    // Fallback content
    return `
        <div class="text-center py-8">
            <i class="fas fa-book-open text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-600">Module content for "${courseName}" is being prepared.</p>
            <p class="text-gray-500 text-sm mt-2">Please check back later or contact your instructor.</p>
        </div>
    `;
}

function navigateModule(direction) {
    if (direction === 'prev' && currentModuleIndex > 0) {
        currentModuleIndex--;
        showModuleContent();
    } else if (direction === 'next') {
        if (currentModuleIndex + 1 >= currentCourseData.total_modules) {
            // Complete the course
            completeCourse();
        } else {
            currentModuleIndex++;
            showModuleContent();
        }
    }
}

function completeCourse() {
    Swal.fire({
        title: 'Course Completed!',
        text: 'Congratulations on finishing all modules. Your final examination is now available.',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Go to Examinations',
        cancelButtonText: 'Close',
        confirmButtonColor: '#05386D'
    }).then((result) => {
        const formData = new FormData();
        formData.append('action', 'update_progress');
        formData.append('course_id', currentCourseData.id);
        formData.append('completed_modules', currentCourseData.total_modules);
        formData.append('progress', 100);

        fetch('learning-ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                closeCourseModule();
                if (result.isConfirmed) {
                    window.location.href = 'learning.php?tab=examinations';
                } else {
                    window.location.reload();
                }
            } else {
                Swal.fire('Error', 'Failed to update progress: ' + data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'An error occurred while completing the course.', 'error');
        });
    });
}

function closeCourseModule() {
    const modal = document.getElementById('courseModuleModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentCourseData = null;
    currentModuleIndex = 0;
}


// Download Certificate
function downloadCertificate(certificateId) {
    // You can implement certificate download logic here
    window.location.href = `learning.php?action=download_certificate&cert_id=${certificateId}`;
}

// Start Examination
function startExamination(examId) {
    if (confirm('Are you ready to start the examination? You will not be able to pause once started.')) {
        window.location.href = `learning-exam.php?exam_id=${examId}`;
    }
}

// Course Details Modal (for future use)
function showCourseDetailsModal(courseData) {
    const modal = document.getElementById('courseDetailsModal');
    const title = document.getElementById('courseModalTitle');
    const content = document.getElementById('courseDetailsContent');
    
    title.textContent = courseData.title;
    content.innerHTML = `
        <div class="space-y-4">
            <p class="text-gray-600">${courseData.description}</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600 text-sm">Duration</p>
                    <p class="text-gray-900">${courseData.duration}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Modules</p>
                    <p class="text-gray-900">${courseData.modules_count}</p>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCourseDetailsModal() {
    const modal = document.getElementById('courseDetailsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('courseDetailsModal');
    if (event.target === modal) {
        closeCourseDetailsModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCourseDetailsModal();
    }
});

// Course Catalog Filter Functions
function filterCourses() {
    const searchQuery = document.getElementById('courseSearch').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const levelFilter = document.getElementById('levelFilter').value;
    
    const courseCards = document.querySelectorAll('.course-card');
    let visibleCount = 0;
    
    courseCards.forEach(card => {
        const title = card.dataset.title;
        const description = card.dataset.description;
        const category = card.dataset.category;
        const level = card.dataset.level;
        
        // Check if card matches all filters
        const matchesSearch = title.includes(searchQuery) || description.includes(searchQuery);
        const matchesCategory = !categoryFilter || category === categoryFilter;
        const matchesLevel = !levelFilter || level === levelFilter;
        
        if (matchesSearch && matchesCategory && matchesLevel) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Show/hide "no results" message
    const container = document.getElementById('coursesContainer');
    let noResultsMessage = container.querySelector('.no-results-message');
    
    if (visibleCount === 0) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-results-message col-span-full text-center py-12';
            noResultsMessage.innerHTML = `
                <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 text-lg">No courses match your filters</p>
                <p class="text-gray-500 text-sm">Try adjusting your search criteria</p>
            `;
            container.appendChild(noResultsMessage);
        }
    } else if (noResultsMessage) {
        noResultsMessage.remove();
    }
}

// Clear all filters
function clearFilters() {
    document.getElementById('courseSearch').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('levelFilter').value = '';
    filterCourses();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars
    animateProgressBars();
});

function animateProgressBars() {
    const progressBars = document.querySelectorAll('[class*="progress-bar"]');
    progressBars.forEach(bar => {
        const target = parseFloat(bar.dataset.target) || 0;
        let current = 0;
        const increment = target / 50;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                bar.style.width = target + '%';
                clearInterval(timer);
            } else {
                bar.style.width = current + '%';
            }
        }, 30);
    });
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
require_once 'includes/footer.php';
?>
