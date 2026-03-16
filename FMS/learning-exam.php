<?php
require_once 'config/config.php';
requireLogin();

$examId = $_GET['exam_id'] ?? 0;
$pdo = getDBConnection();

$stmt = $pdo->prepare("
    SELECT e.*, lc.title as course_name, lc.description as course_desc
    FROM examinations e
    JOIN learning_courses lc ON e.course_id = lc.id
    WHERE e.id = ? AND e.employee_id = ?
");
$stmt->execute([$examId, $_SESSION['user_id']]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Examination not found or you don't have access.");
}

$pageTitle = "Examination: " . $exam['course_name'];
ob_start();
?>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <nav class="flex mb-4" aria-label="Breadcrumb">
                        <ol class="flex items-center space-x-2 text-sm text-gray-500">
                            <li><a href="learning.php" class="hover:text-indigo-600 transition-colors">LMS</a></li>
                            <li><i class="fas fa-chevron-right text-xs"></i></li>
                            <li><a href="learning.php?tab=examinations" class="hover:text-indigo-600 transition-colors">Examinations</a></li>
                        </ol>
                    </nav>
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight"><?php echo htmlspecialchars($exam['course_name']); ?></h1>
                    <p class="mt-2 text-lg text-gray-600">Final Assessment</p>
                </div>
                <div class="flex items-center gap-4">
                    <div id="timerDisplay" class="bg-indigo-50 border border-indigo-100 rounded-xl px-6 py-3 text-center">
                        <p class="text-xs font-bold text-indigo-600 uppercase tracking-widest mb-1">Time Remaining</p>
                        <p class="text-2xl font-mono font-bold text-indigo-900" id="time">30:00</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exam Content -->
        <form id="examForm" class="space-y-8">
            <input type="hidden" name="action" value="submit_exam">
            <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
            
            <div class="space-y-6" id="questionsContainer">
                <!-- Questions will be loaded here -->
            </div>

            <div class="pt-8">
                <button type="submit" class="w-full flex justify-center py-4 px-6 border border-transparent rounded-xl shadow-lg text-lg font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform hover:-translate-y-1">
                    Submit Examination
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const examData = {
    'New Hire Orientation': [
        {
            q: "What is Costa Cargo's primary mission?",
            a: ["To be the fastest", "To provide reliable, efficient, and sustainable freight solutions", "To maximize profit", "To own the most trucks"],
            correct: 1
        },
        {
            q: "Which of these is NOT a core value of the company?",
            a: ["Safety", "Integrity", "Speed at any cost", "Customer Focus"],
            correct: 2
        },
        {
            q: "What is the primary function of the FMS?",
            a: ["Social Networking", "Shipment management and tracking", "Food ordering", "Personal finance"],
            correct: 1
        }
    ],
    'Leadership Development Program': [
        {
            q: "Which leadership style involves centralized decision-making?",
            a: ["Democratic", "Autocratic", "Laissez-faire", "Transformational"],
            correct: 1
        },
        {
            q: "What is a key trait of a high-performing team?",
            a: ["Individual focus", "Lack of communication", "Shared goals and trust", "Minimal effort"],
            correct: 2
        },
        {
            q: "What does EQ stand for in a leadership context?",
            a: ["Extra Quality", "Emotional Quotient", "Efficient Quantity", "Expert Quarterly"],
            correct: 1
        },
        {
            q: "Which of these is a component of SMART goals?",
            a: ["Strategic", "Measurable", "Always-on", "Rapid"],
            correct: 1
        },
        {
            q: "Conflict resolution is best handled through:",
            a: ["Ignoring the issue", "Open communication and mediation", "Firing the dissenters", "Wait and see"],
            correct: 1
        }
    ]
};

const courseName = "<?php echo $exam['course_name']; ?>";
const genericQuestions = [
    {
        q: "What is the primary focus of the " + courseName + " course?",
        a: ["Advanced theory", "Practical application", "Historical context", "None of the above"],
        correct: 1
    },
    {
        q: "In the context of this module, efficiency refers to:",
        a: ["Working harder", "Optimizing resources", "Increasing cost", "Ignoring safety"],
        correct: 1
    },
    {
        q: "Which of the following is an essential requirement for success in this field?",
        a: ["Constant learning", "Fixed mindset", "Avoiding feedback", "Passive approach"],
        correct: 0
    },
    {
        q: "Compliance with standards is important because:",
        a: ["It's just a rule", "It ensures safety and quality", "It's optional", "It makes work slower"],
        correct: 1
    },
    {
        q: "Professionalism in the workplace includes:",
        a: ["Arriving late", "Punctuality and respect", "Sharing gossip", "Avoiding responsibility"],
        correct: 1
    }
];

const questions = examData[courseName] || genericQuestions;

function loadQuestions() {
    const container = document.getElementById('questionsContainer');
    questions.forEach((q, i) => {
        const div = document.createElement('div');
        div.className = "bg-white p-8 rounded-2xl shadow-sm border border-gray-100 transition-all hover:border-indigo-200";
        div.innerHTML = `
            <p class="text-xl font-bold text-gray-900 mb-6">Question ${i + 1}: ${q.q}</p>
            <div class="space-y-3">
                ${q.a.map((opt, oi) => `
                    <label class="flex items-center p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-indigo-50 hover:border-indigo-300 transition-all group">
                        <input type="radio" name="q${i}" value="${oi}" class="h-5 w-5 text-indigo-600 border-gray-300 focus:ring-indigo-500" required>
                        <span class="ml-4 text-gray-700 group-hover:text-indigo-900 font-medium">${opt}</span>
                    </label>
                `).join('')}
            </div>
        `;
        container.appendChild(div);
    });
}

function startTimer(duration) {
    let timer = duration, minutes, seconds;
    const display = document.querySelector('#time');
    const interval = setInterval(function () {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        display.textContent = minutes + ":" + seconds;

        if (--timer < 0) {
            clearInterval(interval);
            Swal.fire({
                title: 'Time is up!',
                text: 'Your exam is being submitted automatically.',
                icon: 'warning',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                submitExam();
            });
        }
    }, 1000);
}

function submitExam() {
    const form = document.getElementById('examForm');
    const formData = new FormData(form);
    let score = 0;
    
    questions.forEach((q, i) => {
        if (formData.get('q' + i) == q.correct) {
            score++;
        }
    });
    
    const finalScore = Math.round((score / questions.length) * 100);
    
    const submissionData = new FormData();
    submissionData.append('action', 'submit_exam');
    submissionData.append('exam_id', '<?php echo $examId; ?>');
    submissionData.append('score', finalScore);

    Swal.fire({
        title: 'Submitting...',
        text: 'Please wait while we process your results.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('learning-ajax.php', {
        method: 'POST',
        body: submissionData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: data.status === 'Passed' ? 'Congratulations!' : 'Exam Completed',
                text: `You scored ${finalScore}%. ${data.status === 'Passed' ? 'You passed the exam!' : 'You did not reach the passing score.'}`,
                icon: data.status === 'Passed' ? 'success' : 'info',
                confirmButtonText: data.status === 'Passed' ? 'View Certificate' : 'View Progress'
            }).then(() => {
                window.location.href = data.status === 'Passed' ? 'learning.php?tab=certificates' : 'learning.php?tab=progress';
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to submit exam', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error', 'A server error occurred. Please try again.', 'error');
    });
}

document.getElementById('examForm').onsubmit = function(e) {
    e.preventDefault();
    submitExam();
};

document.addEventListener('DOMContentLoaded', () => {
    loadQuestions();
    startTimer(30 * 60);
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
require_once 'includes/footer.php';
?>
