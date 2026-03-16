<?php
require_once 'config/config.php';
requireLogin();

$programId = $_GET['program_id'] ?? 0;
$title = $_GET['title'] ?? 'Training Lecture';

$pdo = getDBConnection();
$program = null;
if ($programId) {
    $stmt = $pdo->prepare("SELECT title, description FROM training_programs WHERE id = ?");
    $stmt->execute([$programId]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Sample lecture content based on program title
$lectureContent = [
    'New Hire Orientation' => [
        'title' => 'Welcome to Costa Cargo Freight System',
        'duration' => '30 minutes',
        'content' => 'This orientation introduces you to the company policies, workplace safety, and the freight management system.',
        'topics' => [
            'Company Overview & History',
            'Workplace Safety & Compliance',
            'Freight Management System Basics',
            'Employee Code of Conduct'
        ],
        'questions' => [
            [
                'id' => 1,
                'text' => 'What is the primary function of the Freight Management System?',
                'options' => [
                    'A) To track employee attendance',
                    'B) To manage shipments and cargo tracking',
                    'C) To process payroll',
                    'D) To schedule vacation days'
                ],
                'correct' => 'B'
            ],
            [
                'id' => 2,
                'text' => 'Which of the following is a core safety requirement?',
                'options' => [
                    'A) Wearing formal attire at all times',
                    'B) Using safety equipment in designated areas',
                    'C) Drinking beverages at workstations',
                    'D) Using mobile devices while operating equipment'
                ],
                'correct' => 'B'
            ],
            [
                'id' => 3,
                'text' => 'What should you do if you encounter a workplace hazard?',
                'options' => [
                    'A) Ignore it and report later',
                    'B) Immediately report it to your supervisor',
                    'C) Try to fix it yourself',
                    'D) Wait for the next safety meeting'
                ],
                'correct' => 'B'
            ]
        ]
    ],
    'Leadership Development Program' => [
        'title' => 'Effective Leadership in Modern Organizations',
        'duration' => '45 minutes',
        'content' => 'Learn key leadership principles and best practices for managing teams in today\'s dynamic business environment.',
        'topics' => [
            'Leadership Styles & Approaches',
            'Team Building & Motivation',
            'Decision Making & Problem Solving',
            'Communication Excellence'
        ],
        'questions' => [
            [
                'id' => 1,
                'text' => 'Which leadership style emphasizes collaboration and team input?',
                'options' => [
                    'A) Autocratic',
                    'B) Democratic',
                    'C) Laissez-faire',
                    'D) Situational'
                ],
                'correct' => 'B'
            ],
            [
                'id' => 2,
                'text' => 'What is a key element of effective team motivation?',
                'options' => [
                    'A) Micromanagement',
                    'B) Recognition and clear goals',
                    'C) Minimum communication',
                    'D) Strict hierarchical control'
                ],
                'correct' => 'B'
            ]
        ]
    ]
];

// Get appropriate lecture based on program title
$currentLecture = null;
if ($program) {
    $programTitle = $program['title'];
    foreach ($lectureContent as $key => $lecture) {
        if (stripos($programTitle, $key) !== false) {
            $currentLecture = $lecture;
            break;
        }
    }
}

// Use first available if no match
if (!$currentLecture) {
    $currentLecture = reset($lectureContent);
}

ob_start();
?>
<div class="p-8">
    <div class="mb-8">
        <a href="training-management.php?tab=orientation" class="text-indigo-600 hover:text-indigo-700 mb-4 inline-flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Back to Training
        </a>
        <h1 class="text-4xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($currentLecture['title']); ?></h1>
        <p class="text-gray-600">Program: <?php echo htmlspecialchars($program['title'] ?? 'Unknown'); ?></p>
    </div>

    <!-- Lecture Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-8 mb-8">
                <!-- Lecture Info -->
                <div class="mb-6 pb-6 border-b border-gray-200">
                    <div class="flex items-center gap-4 mb-4">
                        <i class="fas fa-book text-indigo-600 text-2xl"></i>
                        <div>
                            <p class="text-gray-600 text-sm">Duration</p>
                            <p class="text-gray-900 font-semibold"><?php echo htmlspecialchars($currentLecture['duration']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Lecture Description -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Lecture Overview</h2>
                    <p class="text-gray-700 leading-relaxed mb-6"><?php echo htmlspecialchars($currentLecture['content']); ?></p>
                </div>

                <!-- Examination Content -->
                <div class="mb-8 bg-yellow-50 rounded-lg p-6 border border-yellow-200">
                    <h2 class="text-2xl font-bold text-yellow-900 mb-4">Examination Instructions</h2>
                    <p class="text-yellow-800 leading-relaxed mb-4">
                        Please read each question carefully and select the answer that best reflects your understanding of the
                        lecture material. You must answer all questions before submitting the assessment. Your score will be
                        recorded and used to track your training completion.
                    </p>
                    <p class="text-yellow-800">
                        For any questions or clarifications, contact your training coordinator via the helpdesk.
                    </p>
                </div>

                <!-- Topics Covered -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Topics Covered:</h3>
                    <ul class="space-y-3">
                        <?php foreach ($currentLecture['topics'] as $topic): ?>
                            <li class="flex items-center gap-3">
                                <i class="fas fa-check-circle text-green-600"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars($topic); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Assessment Quiz -->
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Assessment Quiz</h2>
                    <p class="text-gray-600 mb-6">Answer the following questions to confirm your understanding of the lecture content.</p>

                    <form id="lectureAssessmentForm" onsubmit="submitAssessment(event, <?php echo $programId; ?>)" class="space-y-8">
                        <?php foreach ($currentLecture['questions'] as $idx => $question): ?>
                            <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                                <h4 class="font-semibold text-gray-900 mb-4">
                                    Question <?php echo $idx + 1; ?>: <?php echo htmlspecialchars($question['text']); ?>
                                </h4>
                                <div class="space-y-3">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input 
                                                type="radio" 
                                                name="question_<?php echo $question['id']; ?>" 
                                                value="<?php echo substr($option, 0, 1); ?>"
                                                required
                                                class="w-4 h-4"
                                            >
                                            <span class="text-gray-700"><?php echo htmlspecialchars($option); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <button type="submit" class="w-full px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-semibold">
                            Submit Assessment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-indigo-50 rounded-lg border border-indigo-200 p-6 sticky top-8">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-indigo-600"></i>
                    Lecture Information
                </h3>
                <div class="space-y-4 text-sm">
                    <div>
                        <p class="text-gray-600">Duration</p>
                        <p class="text-gray-900 font-semibold"><?php echo htmlspecialchars($currentLecture['duration']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Questions</p>
                        <p class="text-gray-900 font-semibold"><?php echo count($currentLecture['questions']); ?> Questions</p>
                    </div>
                    <div>
                        <p class="text-gray-600">Pass Score</p>
                        <p class="text-gray-900 font-semibold">70% or Higher</p>
                    </div>
                    <hr class="my-4">
                    <p class="text-gray-600 text-xs">Complete the assessment to finish this lecture and increase your training progress.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function submitAssessment(event, programId) {
    event.preventDefault();
    
    // Collect answers
    const answers = {};
    const form = event.target;
    const formData = new FormData(form);
    
    for (let [key, value] of formData.entries()) {
        answers[key] = value;
    }
    
    // Simple scoring logic (in production, validate on server)
    const correctAnswersMap = {
        'question_1': 'B',
        'question_2': 'B',
        'question_3': 'B'
    };
    
    let score = 0;
    for (let question in correctAnswersMap) {
        if (answers[question] === correctAnswersMap[question]) {
            score++;
        }
    }
    
    const totalQuestions = Object.keys(correctAnswersMap).length;
    const percentage = Math.round((score / totalQuestions) * 100);
    const passed = percentage >= 70;
    
    if (passed) {
        alert(`Congratulations! You scored ${percentage}%. You have passed this lecture.`);
        // Update participant progress
        fetch('training-ajax.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: `
                    <input name="action" value="update_completion">
                    <input name="program_id" value="${programId}">
                    <input name="completion_percentage" value="${percentage}">
                `
            }))
        })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'training-management.php?tab=records';
            }
        });
    } else {
        alert(`You scored ${percentage}%. You need 70% to pass. Please review the material and try again.`);
    }
}
</script>

<?php
include 'includes/layout.php';

