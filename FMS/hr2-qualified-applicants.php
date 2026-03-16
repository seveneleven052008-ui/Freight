<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'HR2 - Qualified Applicants Management';
$pdo = getDBConnection();

// Handle "Assign Role" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_role') {
    $applicantId = (int)($_POST['applicant_id'] ?? 0);
    $roleName = $_POST['role_name'] ?? 'Critical Role';
    
    if ($applicantId > 0) {
        try {
            $pdo->beginTransaction();

            // 1. Get applicant details
            $stmt = $pdo->prepare("SELECT name, position_applied FROM applicants WHERE applicant_id = ?");
            $stmt->execute([$applicantId]);
            $applicant = $stmt->fetch();

            if ($applicant) {
                // 2. Insert into critical_roles
                $insertRole = $pdo->prepare("INSERT INTO critical_roles (title, department, risk_level, succession_readiness, created_at) VALUES (?, ?, 'Medium', 0, NOW())");
                $insertRole->execute([$roleName, 'Human Resources']); // Defaulting to HR department for new hires
                
                // 3. Update applicant status to 'Hired'
                $updateStatus = $pdo->prepare("UPDATE applicants SET application_status = 'Hired' WHERE applicant_id = ?");
                $updateStatus->execute([$applicantId]);
                
                $pdo->commit();
                header('Location: hr2-qualified-applicants.php?assigned=1&role=' . urlencode($roleName));
                exit;
            } else {
                $pdo->rollBack();
                die("Applicant not found.");
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            die("Error processing assignment: " . $e->getMessage());
        }
    }
}

// Get only qualified applicants
$applicants = $pdo->query("SELECT * FROM applicants WHERE application_status = 'Qualified' ORDER BY created_at DESC")->fetchAll();

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Qualified Applicants (HR2)</h1>
        <p class="text-gray-600">Review qualified applicants approved by HR1 and assign them to critical roles within the system.</p>
    </div>

    <?php if (isset($_GET['assigned'])): ?>
        <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-700 rounded-lg flex items-center gap-2">
            <i class="fas fa-check-circle"></i>
            <span>Applicant successfully assigned to the role: <strong><?php echo htmlspecialchars($_GET['role']); ?></strong>.</span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-900">Applicant Name</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-900">Position Applied</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-900">Contact Info</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-900 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($applicants)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500 italic">No qualified applicants found. HR1 has not approved any candidates yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applicants as $applicant): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($applicant['name']); ?></span>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <?php echo htmlspecialchars($applicant['position_applied']); ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <?php echo htmlspecialchars($applicant['contact_info']); ?>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button onclick="viewDetails(<?php echo htmlspecialchars(json_encode($applicant)); ?>)" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">View Details</button>
                                <button onclick="showAssignModal(<?php echo htmlspecialchars(json_encode($applicant)); ?>)" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm hover:bg-indigo-700 transition-colors">Assign Role</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Details Modal (Shared with HR1 logic) -->
<div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Applicant Information</h3>
            <button onclick="closeModal('detailsModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <p class="text-sm text-gray-500 uppercase font-semibold">Full Name</p>
                <p id="modalName" class="text-gray-900 font-medium"></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 uppercase font-semibold">Position Applied For</p>
                <p id="modalPosition" class="text-gray-900"></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 uppercase font-semibold">Contact Information</p>
                <p id="modalContact" class="text-gray-900"></p>
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeModal('detailsModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Close</button>
        </div>
    </div>
</div>

<!-- Assign Role Modal -->
<div id="assignModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Assign Critical Role</h3>
            <button onclick="closeModal('assignModal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="assign_role">
            <input type="hidden" id="assignApplicantId" name="applicant_id">
            <div>
                <p class="text-sm text-gray-500 mb-1">Assigning role for:</p>
                <p id="assignModalName" class="text-gray-900 font-bold text-lg"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Critical Role Name</label>
                <input type="text" name="role_name" required placeholder="e.g. Senior Logistics Lead" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="pt-4 flex justify-end gap-3">
                <button type="button" onclick="closeModal('assignModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewDetails(applicant) {
    document.getElementById('modalName').textContent = applicant.name;
    document.getElementById('modalPosition').textContent = applicant.position_applied;
    document.getElementById('modalContact').textContent = applicant.contact_info;
    document.getElementById('detailsModal').classList.remove('hidden');
}

function showAssignModal(applicant) {
    document.getElementById('assignApplicantId').value = applicant.applicant_id;
    document.getElementById('assignModalName').textContent = applicant.name;
    document.getElementById('assignModal').classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>
