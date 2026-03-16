<?php
require_once 'config/config.php';
requireLogin();

$pdo = getDBConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_to_training') {
    $employee_id = $_POST['employee_id'];
    $required_skill = $_POST['required_skill'];
    $training_program = $_POST['training_program'];

    // Get employee name
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee_name = $stmt->fetchColumn();

    if ($employee_name) {
        $stmt = $pdo->prepare("INSERT INTO skill_development (employee_id, employee_name, required_skill, training_program, status) VALUES (?, ?, ?, ?, 'Sent')");
        if ($stmt->execute([$employee_id, $employee_name, $required_skill, $training_program])) {
            $message = "Skill development request sent to HR2 successfully!";
        } else {
            $message = "Error sending request.";
        }
    }
}

// Get all employees for selection
$employees = $pdo->query("SELECT id, full_name, department FROM users WHERE role != 'admin' ORDER BY full_name")->fetchAll();

// Get existing requests for HR1 to view
$requests = $pdo->query("SELECT * FROM skill_development ORDER BY created_at DESC LIMIT 10")->fetchAll();

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">HR1 - Skill Identification</h1>
        <p class="text-gray-600">Identify skill gaps and send training requests to HR2.</p>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo strpos($message, 'Error') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Identification Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">New Request</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_to_training">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Employee</label>
                            <select name="employee_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                <option value="">Choose Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name'] . ' (' . $emp['department'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Required Skill</label>
                            <input type="text" name="required_skill" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Data Analysis">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Target Training Program</label>
                            <input type="text" name="training_program" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Advanced Excel Workshop">
                        </div>
                        <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-bold">
                            <i class="fas fa-paper-plane mr-2"></i> Send to Training
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Identifications</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skill</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No requests sent yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($req['employee_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($req['required_skill']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($req['training_program']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                                <?php echo $req['status'] === 'Sent' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'; ?>">
                                                <?php echo htmlspecialchars($req['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>
