<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'Dashboard';
$pdo = getDBConnection();

// Get statistics
$currentUserId = $_SESSION['user_id'] ?? null; // assuming login stores user ID
$stats = [
    'critical_roles' => $pdo->query("SELECT COUNT(*) as count FROM critical_roles")->fetch()['count'],
    'high_potential' => $pdo->query("SELECT COUNT(*) as count FROM high_potential_employees")->fetch()['count'],
    'training_programs' => $pdo->query("SELECT COUNT(*) as count FROM training_programs")->fetch()['count'],
    // Calculate upcoming retirements from critical roles
    'upcoming_retirements' => $pdo->query("SELECT COUNT(*) as count FROM critical_roles WHERE retirement_date >= CURDATE()")->fetch()['count'],
    // only count scheduled assessments for the current user
    'pending_certifications' => 0,
];

// count user-specific pending certifications separately if we have an ID
if ($currentUserId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM skill_assessments WHERE employee_id = ? AND status = 'Scheduled'");
    $stmt->execute([$currentUserId]);
    $stats['pending_certifications'] = $stmt->fetch()['count'];
}

// Get retirement forecasting data
// First try to get from retirement_forecasts table, if empty, calculate from critical_roles
try {
    $retirements = $pdo->query("
        SELECT * FROM retirement_forecasts
        ORDER BY year ASC
    ")->fetchAll();
    
    // If no retirement forecasts exist, calculate from critical roles
    if (empty($retirements)) {
        $retirements = $pdo->query("
            SELECT YEAR(retirement_date) as year, COUNT(*) as total_retirements, COUNT(*) as critical_roles_count
            FROM critical_roles
            WHERE retirement_date IS NOT NULL AND retirement_date > '0000-00-00'
            GROUP BY YEAR(retirement_date)
            ORDER BY year ASC
        ")->fetchAll();
    }
} catch (PDOException $e) {
    // If there's a database error, show empty array
    $retirements = [];
}

// Get recent trainings
$trainings = $pdo->query("
    SELECT tp.*, 
           COUNT(DISTINCT tpar.id) as participants,
           AVG(tpar.completion_percentage) as avg_completion
    FROM training_programs tp
    LEFT JOIN training_participants tpar ON tp.id = tpar.training_program_id
    GROUP BY tp.id
    ORDER BY tp.start_date DESC
    LIMIT 3
")->fetchAll();

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">
            Welcome back, <?php echo htmlspecialchars(getUserName()); ?>
        </h1>
        <p class="text-gray-600">
            Here's an overview of your HR management system
        </p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer stat-card" data-stat="<?php echo $stats['critical_roles']; ?>">
            <div class="flex items-start justify-between mb-4">
                <div class="bg-blue-500 p-3 rounded-lg">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
                <span class="text-green-600 text-sm"></span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1 stat-number">0</h3>
            <p class="text-gray-600">Critical Roles</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer stat-card" data-stat="<?php echo $stats['upcoming_retirements']; ?>">
            <div class="flex items-start justify-between mb-4">
                <div class="bg-red-500 p-3 rounded-lg">
                    <i class="fas fa-clock text-white text-xl"></i>
                </div>
                <span class="text-red-600 text-sm"></span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1 stat-number">0</h3>
            <p class="text-gray-600">Upcoming Retirements</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer stat-card" data-stat="<?php echo $stats['high_potential']; ?>">
            <div class="flex items-start justify-between mb-4">
                <div class="bg-green-500 p-3 rounded-lg">
                    <i class="fas fa-chart-line text-white text-xl"></i>
                </div>
                <span class="text-green-600 text-sm"></span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1 stat-number">0</h3>
            <p class="text-gray-600">High Potential Employees</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer stat-card" data-stat="<?php echo $stats['training_programs']; ?>">
            <div class="flex items-start justify-between mb-4">
                <div class="bg-purple-500 p-3 rounded-lg">
                    <i class="fas fa-graduation-cap text-white text-xl"></i>
                </div>
                <span class="text-green-600 text-sm"></span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1 stat-number">0</h3>
            <p class="text-gray-600">Training Programs</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upcoming Retirements -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-2">
                    <i class="fas fa-clock text-orange-500"></i>
                    <h2 class="text-xl font-bold text-gray-900">Retirement Forecasting</h2>
                </div>
            </div>
            <div class="p-6">
                <div class="max-h-96 overflow-y-auto">
                    <div class="space-y-4">
                        <?php if (empty($retirements)): ?>
                            <p class="text-gray-500">No retirement forecasting data available</p>
                        <?php else: ?>
                            <?php foreach ($retirements as $forecast): ?>
                                <div class="border-l-4 border-orange-500 pl-4 py-2 hover:bg-orange-50 rounded-r-lg transition-colors cursor-pointer retirement-item" 
                                     onclick="showRetirementDetails(<?php echo htmlspecialchars(json_encode([
                                         'year' => $forecast['year'],
                                         'total_retirements' => $forecast['total_retirements'],
                                         'critical_roles_count' => $forecast['critical_roles_count']
                                     ])); ?>)">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <p class="text-gray-900 font-semibold">Year <?php echo htmlspecialchars($forecast['year']); ?></p>
                                            <p class="text-gray-600">Retirement Forecast</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-6">
                                        <div class="flex items-center gap-2 text-sm">
                                            <i class="fas fa-users text-gray-400"></i>
                                            <span class="text-gray-600">
                                                Total Retirements: <strong><?php echo htmlspecialchars($forecast['total_retirements']); ?></strong>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 text-sm">
                                            <i class="fas fa-briefcase text-gray-400"></i>
                                            <span class="text-gray-600">
                                                Critical Roles: <strong><?php echo htmlspecialchars($forecast['critical_roles_count']); ?></strong>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Training Programs -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-2">
                    <i class="fas fa-graduation-cap text-purple-500"></i>
                    <h2 class="text-xl font-bold text-gray-900">Training Programs</h2>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php if (empty($trainings)): ?>
                        <p class="text-gray-500">No training programs</p>
                    <?php else: ?>
                        <?php foreach ($trainings as $training): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-purple-300 hover:shadow-md transition-all cursor-pointer training-item" 
                                 data-completion="<?php echo round($training['avg_completion'] ?? 0); ?>"
                                 onclick="showTrainingDetails(<?php echo htmlspecialchars(json_encode([
                                     'id' => $training['id'],
                                     'title' => $training['title'],
                                     'participants' => $training['participants'],
                                     'completion' => round($training['avg_completion'] ?? 0),
                                     'status' => $training['status'],
                                     'category' => $training['category'] ?? '',
                                     'start_date' => $training['start_date'] ?? ''
                                 ])); ?>)">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <p class="text-gray-900 font-semibold"><?php echo htmlspecialchars($training['title']); ?></p>
                                        <p class="text-gray-600 text-sm">
                                            <?php echo $training['participants']; ?> participants
                                        </p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($training['status']); ?>">
                                        <?php echo htmlspecialchars($training['status']); ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-purple-500 h-2 rounded-full transition-all progress-bar"
                                            style="width: 0%"
                                            data-target="<?php echo round($training['avg_completion'] ?? 0); ?>"
                                        ></div>
                                    </div>
                                    <span class="text-gray-600 text-sm progress-text">
                                        0%
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mt-8 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="succession-planning.php?tab=high-potential" class="flex items-center gap-3 p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                <i class="fas fa-users text-indigo-600"></i>
                <span class="text-gray-700">Identify High Potentials</span>
            </a>
            <a href="training-management.php" class="flex items-center gap-3 p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                <i class="fas fa-graduation-cap text-indigo-600"></i>
                <span class="text-gray-700">Training</span>
            </a>
            <a href="competency-management.php?tab=assessments" class="flex items-center gap-3 p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                <i class="fas fa-award text-indigo-600"></i>
                <span class="text-gray-700">Run Skill Assessment</span>
            </a>
            <a href="competency-management.php" class="flex items-center gap-3 p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                <i class="fas fa-chart-line text-indigo-600"></i>
                <span class="text-gray-700">Check Qualifications</span>
            </a>
        </div>
    </div>
</div>

<!-- Retirement Details Modal -->
<div id="retirementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Retirement Details</h3>
            <button onclick="closeRetirementModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="retirementModalContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeRetirementModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Training Details Modal -->
<div id="trainingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Training Program Details</h3>
            <button onclick="closeTrainingModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="trainingModalContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button id="viewTrainingDetailsBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors mr-2">
                View Full Details
            </button>
            <button onclick="closeTrainingModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Animated Counter for Statistics
function animateCounter(element, target, duration = 2000) {
    let start = 0;
    const increment = target / (duration / 16);
    const timer = setInterval(() => {
        start += increment;
        if (start >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(start);
        }
    }, 16);
}

// Animate Progress Bars
function animateProgressBar(element, target) {
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

// Initialize animations on page load
document.addEventListener('DOMContentLoaded', function() {
    // Animate statistics counters
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        const statNumber = card.querySelector('.stat-number');
        const targetValue = parseInt(card.getAttribute('data-stat'));
        animateCounter(statNumber, targetValue);
    });

    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const target = parseInt(bar.getAttribute('data-target'));
        const progressText = bar.closest('.training-item').querySelector('.progress-text');
        animateProgressBar(bar, target);
        
        // Update text
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                progressText.textContent = target + '%';
                clearInterval(timer);
            } else {
                progressText.textContent = Math.floor(current) + '%';
            }
        }, 30);
    });

    // Add hover effects to stat cards
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// Retirement Details Modal
function showRetirementDetails(data) {
    const modal = document.getElementById('retirementModal');
    const content = document.getElementById('retirementModalContent');
    
    const date = new Date(data.date);
    const formattedDate = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    const daysUntil = Math.ceil((date - new Date()) / (1000 * 60 * 60 * 24));
    
    content.innerHTML = `
        <div class="space-y-4">
            <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-user text-orange-600"></i>
                    <h4 class="text-lg font-semibold text-gray-900">${data.name}</h4>
                </div>
                <p class="text-gray-700">${data.title}</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-calendar-alt text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Retirement Date</p>
                        <p class="text-gray-900 font-medium">${formattedDate}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-clock text-orange-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Days Remaining</p>
                        <p class="text-gray-900 font-medium">${daysUntil} days</p>
                    </div>
                </div>
            </div>
            
            <div class="pt-4 border-t border-gray-200">
                <div class="flex items-start gap-3">
                    <i class="fas fa-users text-green-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Successor(s)</p>
                        <p class="text-gray-900">${data.successors}</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeRetirementModal() {
    const modal = document.getElementById('retirementModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Training Details Modal
function showTrainingDetails(data) {
    const modal = document.getElementById('trainingModal');
    const content = document.getElementById('trainingModalContent');
    
    const startDate = data.start_date ? new Date(data.start_date).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    }) : 'Not specified';
    
    content.innerHTML = `
        <div class="space-y-4">
            <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded">
                <h4 class="text-lg font-semibold text-gray-900 mb-2">${data.title}</h4>
                ${data.category ? `<p class="text-gray-600 text-sm">Category: ${data.category}</p>` : ''}
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-users text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Participants</p>
                        <p class="text-gray-900 font-medium">${data.participants}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-chart-line text-green-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Completion Rate</p>
                        <p class="text-gray-900 font-medium">${data.completion}%</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-calendar text-blue-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Start Date</p>
                        <p class="text-gray-900 font-medium">${startDate}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-purple-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Status</p>
                        <p class="text-gray-900 font-medium">${data.status}</p>
                    </div>
                </div>
            </div>
            
            <div class="pt-4 border-t border-gray-200">
                <p class="text-gray-600 text-sm mb-2">Progress</p>
                <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                        <div class="bg-purple-500 h-3 rounded-full" style="width: ${data.completion}%"></div>
                    </div>
                    <span class="text-gray-900 font-medium">${data.completion}%</span>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    // configure the View Full Details button
    const viewBtn = document.getElementById('viewTrainingDetailsBtn');
    if (viewBtn) {
        viewBtn.onclick = function() {
            window.location.href = 'training-management.php?id=' + encodeURIComponent(data.id);
        };
    }
}

function closeTrainingModal() {
    const modal = document.getElementById('trainingModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const retirementModal = document.getElementById('retirementModal');
    const trainingModal = document.getElementById('trainingModal');
    
    if (event.target === retirementModal) {
        closeRetirementModal();
    }
    
    if (event.target === trainingModal) {
        closeTrainingModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeRetirementModal();
        closeTrainingModal();
    }
});

// Add smooth scroll behavior
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Refresh dashboard data (optional - can be called periodically)
function refreshDashboard() {
    // This could be used to refresh data via AJAX
    console.log('Refreshing dashboard data...');
    // You can implement AJAX call here to refresh statistics
}

// Auto-refresh every 5 minutes (optional)
// setInterval(refreshDashboard, 300000);
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
require_once 'includes/footer.php';
?>
