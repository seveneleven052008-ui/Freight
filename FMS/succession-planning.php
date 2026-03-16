<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'Succession Planning';
$pdo = getDBConnection();

$activeTab = $_GET['tab'] ?? 'critical-roles';
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
$roleValue = strtolower($currentUser['role'] ?? '');
$isAdmin = in_array($roleValue, ['admin', 'administrator', 'hr manager', 'hr1 manager']) || (($currentUser['username'] ?? '') === 'admin');

/**
 * Add a new critical role to the system
 * 
 * @param PDO $pdo Database connection
 * @param array $data Role data (title, department, current_holder_id, retirement_date, risk_level, succession_readiness)
 * @return array Result with success status and message
 */
function addCriticalRole($pdo, $data) {
    try {
        // Normalize current holder ID: treat empty string or invalid value as NULL
        $currentHolderId = isset($data['current_holder_id']) && $data['current_holder_id'] !== ''
            ? (int)$data['current_holder_id']
            : null;

        $stmt = $pdo->prepare("
            INSERT INTO critical_roles (title, department, current_holder_id, retirement_date, risk_level, succession_readiness, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['title'],
            $data['department'],
            $currentHolderId,
            $data['retirement_date'],
            $data['risk_level'],
            $data['succession_readiness'] ?? 0
        ]);
        
        $roleId = $pdo->lastInsertId();
        
        // Update retirement forecasting if retirement date is provided
        if (!empty($data['retirement_date'])) {
            $retirementDate = new DateTime($data['retirement_date']);
            $year = (int)$retirementDate->format('Y');
            
            // Check if record exists for this year
            $check = $pdo->prepare("SELECT id FROM retirement_forecasts WHERE year = ?");
            $check->execute([$year]);
            
            if ($check->fetch()) {
                // Update existing record
                $update = $pdo->prepare("
                    UPDATE retirement_forecasts 
                    SET total_retirements = total_retirements + 1, 
                        critical_roles_count = critical_roles_count + 1
                    WHERE year = ?
                ");
                $update->execute([$year]);
            } else {
                // Create new record
                $insert = $pdo->prepare("
                    INSERT INTO retirement_forecasts (year, total_retirements, critical_roles_count, created_at)
                    VALUES (?, 1, 1, NOW())
                ");
                $insert->execute([$year]);
            }
        }
        
        return ['success' => true, 'message' => 'Critical role added successfully', 'id' => $roleId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error adding critical role: ' . $e->getMessage()];
    }
}

/**
 * Add a high potential employee record
 *
 * @param PDO   $pdo  Database connection
 * @param array $data High potential data (employee_id, current_role, years_of_service, performance_rating, potential_score, target_role, development_areas)
 * @return array Result with success status and message
 */
function addHighPotentialEmployee($pdo, $data) {
    try {
        $employeeId = isset($data['employee_id']) ? (int)$data['employee_id'] : 0;
        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Please select a valid employee'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO high_potential_employees (
                `employee_id`,
                `current_role`,
                `years_of_service`,
                `performance_rating`,
                `potential_score`,
                `target_role`,
                `development_areas`,
                `created_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $employeeId,
            $data['current_role'] ?? null,
            $data['years_of_service'] !== '' ? (int)$data['years_of_service'] : null,
            $data['performance_rating'] !== '' ? (float)$data['performance_rating'] : null,
            $data['potential_score'] !== '' ? (int)$data['potential_score'] : null,
            $data['target_role'] ?? null,
            $data['development_areas'] ?? null,
        ]);

        return ['success' => true, 'message' => 'High potential employee added successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error adding high potential employee: ' . $e->getMessage()];
    }
}

/**
 * Delete a high potential employee record
 *
 * @param PDO $pdo Database connection
 * @param int $highPotentialId The ID of the high_potential_employees row
 * @return array Result with success status and message
 */
function deleteHighPotentialEmployee($pdo, $highPotentialId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM high_potential_employees WHERE id = ?");
        $stmt->execute([(int)$highPotentialId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'High potential employee record not found or already deleted'];
        }

        return ['success' => true, 'message' => 'High potential employee record deleted successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error deleting high potential employee: ' . $e->getMessage()];
    }
}

/**
 * Delete a critical role and its related data
 *
 * @param PDO $pdo Database connection
 * @param int $criticalRoleId The ID of the critical role to delete
 * @return array Result with success status and message
 */
function deleteCriticalRole($pdo, $criticalRoleId) {
    try {
        // Get the critical role details before deletion
        $getRole = $pdo->prepare("SELECT retirement_date FROM critical_roles WHERE id = ?");
        $getRole->execute([(int)$criticalRoleId]);
        $role = $getRole->fetch();
        
        if (!$role) {
            return ['success' => false, 'message' => 'Critical role not found or already deleted'];
        }
        
        // Because successors.critical_role_id has ON DELETE CASCADE,
        // deleting the critical role will automatically remove its successors.
        $stmt = $pdo->prepare("DELETE FROM critical_roles WHERE id = ?");
        $stmt->execute([(int)$criticalRoleId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Critical role not found or already deleted'];
        }
        
        // Update retirement forecasting if retirement date exists
        if (!empty($role['retirement_date'])) {
            $retirementDate = new DateTime($role['retirement_date']);
            $year = (int)$retirementDate->format('Y');
            
            // Decrement the retirement forecast counters
            $update = $pdo->prepare("
                UPDATE retirement_forecasts 
                SET total_retirements = GREATEST(0, total_retirements - 1), 
                    critical_roles_count = GREATEST(0, critical_roles_count - 1)
                WHERE year = ?
            ");
            $update->execute([$year]);
        }

        return ['success' => true, 'message' => 'Critical role deleted successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error deleting critical role: ' . $e->getMessage()];
    }
}

/**
 * Add a successor to a critical role
 * 
 * @param PDO $pdo Database connection
 * @param int $criticalRoleId The ID of the critical role
 * @param int $employeeId The ID of the employee to add as successor
 * @return array Result with success status and message
 */
function addSuccessor($pdo, $criticalRoleId, $employeeId) {
    try {
        // Check if successor already exists
        $check = $pdo->prepare("SELECT id FROM successors WHERE critical_role_id = ? AND employee_id = ?");
        $check->execute([$criticalRoleId, $employeeId]);
        
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'Employee is already listed as a successor for this role'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO successors (critical_role_id, employee_id, added_at)
            VALUES (?, ?, NOW())
        ");
        
        $stmt->execute([$criticalRoleId, $employeeId]);
        
        // Update succession readiness
        updateSuccessionReadiness($pdo, $criticalRoleId);
        
        return ['success' => true, 'message' => 'Successor added successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error adding successor: ' . $e->getMessage()];
    }
}

/**
 * Calculate and update succession readiness percentage for a critical role
 * 
 * @param PDO $pdo Database connection
 * @param int $criticalRoleId The ID of the critical role
 * @return float The calculated readiness percentage
 */
function updateSuccessionReadiness($pdo, $criticalRoleId) {
    try {
        // Count number of successors
        $successorCount = $pdo->prepare("SELECT COUNT(*) as count FROM successors WHERE critical_role_id = ?");
        $successorCount->execute([$criticalRoleId]);
        $count = $successorCount->fetch()['count'];
        
        // Calculate readiness based on number of successors
        // 0 successors = 0%, 1 = 25%, 2 = 50%, 3 = 75%, 4+ = 100%
        $readiness = min(100, $count * 25);
        
        // Update the critical role
        $update = $pdo->prepare("UPDATE critical_roles SET succession_readiness = ? WHERE id = ?");
        $update->execute([$readiness, $criticalRoleId]);
        
        return $readiness;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Remove a successor from a critical role
 * 
 * @param PDO $pdo Database connection
 * @param int $criticalRoleId The ID of the critical role
 * @param int $employeeId The ID of the employee to remove as successor
 * @return array Result with success status and message
 */
function removeSuccessor($pdo, $criticalRoleId, $employeeId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM successors WHERE critical_role_id = ? AND employee_id = ?");
        $stmt->execute([$criticalRoleId, $employeeId]);
        
        // Update succession readiness
        updateSuccessionReadiness($pdo, $criticalRoleId);
        
        return ['success' => true, 'message' => 'Successor removed successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error removing successor: ' . $e->getMessage()];
    }
}

/**
 * Get all employees available to be assigned as successors
 * 
 * @param PDO $pdo Database connection
 * @param string $department Optional department filter
 * @return array List of employees
 */
function getAvailableEmployees($pdo, $department = null) {
    try {
        if ($department) {
            $stmt = $pdo->prepare("SELECT id, full_name, department, position FROM users WHERE department = ? ORDER BY full_name");
            $stmt->execute([$department]);
        } else {
            $stmt = $pdo->query("SELECT id, full_name, department, position FROM users ORDER BY full_name");
        }
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Sync existing critical roles to retirement forecasts
 * This aggregates all critical roles by retirement year and updates the retirement_forecasts table
 * 
 * @param PDO $pdo Database connection
 * @return array Result with success status and message
 */
function syncRetirementForecasts($pdo) {
    try {
        // First, clear existing forecasts to rebuild from scratch
        $pdo->query("TRUNCATE TABLE retirement_forecasts");
        
        // Get all critical roles grouped by retirement year
        $stmt = $pdo->query("
            SELECT YEAR(retirement_date) as year, COUNT(*) as count
            FROM critical_roles
            WHERE retirement_date IS NOT NULL AND retirement_date > '0000-00-00'
            GROUP BY YEAR(retirement_date)
            ORDER BY year ASC
        ");
        
        $forecasts = $stmt->fetchAll();
        
        // Insert aggregated data into retirement_forecasts
        $insert = $pdo->prepare("
            INSERT INTO retirement_forecasts (year, total_retirements, critical_roles_count, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        foreach ($forecasts as $forecast) {
            if ($forecast['year'] && $forecast['count'] > 0) {
                $insert->execute([
                    $forecast['year'],
                    $forecast['count'],
                    $forecast['count']
                ]);
            }
        }
        
        return ['success' => true, 'message' => 'Retirement forecasts synchronized successfully', 'count' => count($forecasts)];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error syncing retirement forecasts: ' . $e->getMessage()];
    }
}

// Sync retirement forecasts on page load (one-time or periodic sync)
if (isset($_GET['sync_forecasts']) && $_GET['sync_forecasts'] === '1') {
    $syncResult = syncRetirementForecasts($pdo);
    $_SESSION['success_message'] = $syncResult['message'];
    if (!$syncResult['success']) {
        $_SESSION['error_message'] = $syncResult['message'];
    }
    header('Location: succession-planning.php?tab=critical-roles');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_critical_role':
                $result = addCriticalRole($pdo, [
                    'title' => $_POST['title'] ?? '',
                    'department' => $_POST['department'] ?? '',
                    'current_holder_id' => $_POST['current_holder_id'] ?? null,
                    'retirement_date' => $_POST['retirement_date'] ?? '',
                    'risk_level' => $_POST['risk_level'] ?? 'Medium',
                    'succession_readiness' => 0
                ]);
                $_SESSION['success_message'] = $result['message'];
                if (!$result['success']) {
                    $_SESSION['error_message'] = $result['message'];
                }
                header('Location: succession-planning.php?tab=critical-roles');
                exit;
                
            case 'add_successor':
                $result = addSuccessor($pdo, $_POST['critical_role_id'], $_POST['employee_id']);
                $_SESSION['success_message'] = $result['message'];
                if (!$result['success']) {
                    $_SESSION['error_message'] = $result['message'];
                }
                header('Location: succession-planning.php?tab=critical-roles');
                exit;

            case 'add_high_potential':
                $result = addHighPotentialEmployee($pdo, [
                    'employee_id'       => $_POST['employee_id'] ?? null,
                    'current_role'      => $_POST['current_role'] ?? '',
                    'years_of_service'  => $_POST['years_of_service'] ?? '',
                    'performance_rating'=> $_POST['performance_rating'] ?? '',
                    'potential_score'   => $_POST['potential_score'] ?? '',
                    'target_role'       => $_POST['target_role'] ?? '',
                    'development_areas' => $_POST['development_areas'] ?? '',
                ]);
                $_SESSION['success_message'] = $result['message'];
                if (!$result['success']) {
                    $_SESSION['error_message'] = $result['message'];
                }
                header('Location: succession-planning.php?tab=high-potential');
                exit;
                
            case 'remove_successor':
                $result = removeSuccessor($pdo, $_POST['critical_role_id'], $_POST['employee_id']);
                $_SESSION['success_message'] = $result['message'];
                if (!$result['success']) {
                    $_SESSION['error_message'] = $result['message'];
                }
                header('Location: succession-planning.php?tab=critical-roles');
                exit;

            case 'delete_critical_role':
                $roleId = $_POST['critical_role_id'] ?? null;
                if ($roleId === null || $roleId === '') {
                    $_SESSION['error_message'] = 'Invalid critical role ID';
                    header('Location: succession-planning.php?tab=critical-roles');
                    exit;
                }

                $result = deleteCriticalRole($pdo, $roleId);
                $_SESSION['success_message'] = $result['message'];
                if (!$result['success']) {
                    $_SESSION['error_message'] = $result['message'];
                }
                header('Location: succession-planning.php?tab=critical-roles');
                exit;

            case 'delete_high_potential':
                $highPotentialId = $_POST['high_potential_id'] ?? null;
                if ($highPotentialId === null || $highPotentialId === '') {
                    $_SESSION['error_message'] = 'Invalid high potential employee ID';
                    header('Location: succession-planning.php?tab=high-potential');
                    exit;
                }

                $result = deleteHighPotentialEmployee($pdo, $highPotentialId);
                $_SESSION['success_message'] = $result['message'];
                if (!$result['success']) {
                    $_SESSION['error_message'] = $result['message'];
                }
                header('Location: succession-planning.php?tab=high-potential');
                exit;

            case 'post_applicant':
                $applicantId = (int)($_POST['applicant_id'] ?? 0);
                if ($applicantId > 0) {
                    try {
                        $pdo->beginTransaction();

                        // 1. Get applicant details
                        $stmt = $pdo->prepare("SELECT name, position_applied, contact_info FROM applicants WHERE applicant_id = ?");
                        $stmt->execute([$applicantId]);
                        $applicant = $stmt->fetch();

                        if ($applicant) {
                            // 2. Create a system user for the applicant
                            // Generating a simple username and default password for now
                            $username = strtolower(str_replace(' ', '.', $applicant['name'])) . rand(10, 99);
                            $employeeId = 'EMP' . rand(1000, 9999);
                            $password = password_hash('FreighT@2026', PASSWORD_DEFAULT);
                            
                            $insertUser = $pdo->prepare("
                                INSERT INTO users (username, password, employee_id, full_name, email, hire_date, department, position, role)
                                VALUES (?, ?, ?, ?, ?, NOW(), 'New Hire', ?, 'Employee')
                            ");
                            $insertUser->execute([
                                $username,
                                $password,
                                $employeeId,
                                $applicant['name'],
                                $applicant['contact_info'], // Using contact info as email for simplicity
                                $applicant['position_applied']
                            ]);
                            
                            $newUserId = $pdo->lastInsertId();

                            // 3. Store in high_potential_employees
                            $insertHP = $pdo->prepare("
                                INSERT INTO high_potential_employees (employee_id, current_role, potential_score, target_role, created_at)
                                VALUES (?, ?, 75, 'New Talent', NOW())
                            ");
                            $insertHP->execute([$newUserId, $applicant['position_applied']]);

                            // 4. Update applicant status to 'Hired'
                            $updateStatus = $pdo->prepare("UPDATE applicants SET application_status = 'Hired' WHERE applicant_id = ?");
                            $updateStatus->execute([$applicantId]);

                            $pdo->commit();
                            $_SESSION['success_message'] = 'Applicant ' . htmlspecialchars($applicant['name']) . ' has been hired and added to the High Potential list.';
                        } else {
                            $pdo->rollBack();
                            $_SESSION['error_message'] = 'Applicant not found.';
                        }
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $_SESSION['error_message'] = 'Error posting applicant: ' . $e->getMessage();
                    }
                }
                header('Location: succession-planning.php?tab=high-potential');
                exit;
        }
    }
}

// Get critical roles with successor details
$criticalRolesQuery = $pdo->query("
    SELECT cr.*, u.full_name as current_holder_name
    FROM critical_roles cr
    LEFT JOIN users u ON cr.current_holder_id = u.id
    ORDER BY cr.retirement_date ASC
");
$criticalRoles = $criticalRolesQuery->fetchAll();

// Get successors for each role
foreach ($criticalRoles as &$role) {
    $successorsQuery = $pdo->prepare("
        SELECT su.id, su.full_name
        FROM successors s
        JOIN users su ON s.employee_id = su.id
        WHERE s.critical_role_id = ?
    ");
    $successorsQuery->execute([$role['id']]);
    $role['successors_list'] = $successorsQuery->fetchAll();
    $role['successors'] = implode(', ', array_column($role['successors_list'], 'full_name'));
}
unset($role);

// Get all applicants for the modal
$allApplicants = $pdo->query("SELECT * FROM applicants WHERE application_status != 'Hired' ORDER BY created_at DESC")->fetchAll();

// Get high potential employees (including High Potential from HR4)
$highPotentials = $pdo->query("
    SELECT hpe.*, u.full_name, u.department
    FROM high_potential_employees hpe
    JOIN users u ON hpe.employee_id = u.id
    UNION
    SELECT 
        NULL as id,
        ti.employee_id,
        u.role as `current_role`,
        0 as years_of_service,
        0.0 as performance_rating,
        10 as potential_score,
        'To be Determined' as target_role,
        'Identified from HR4 Core Human Capital' as development_areas,
        ti.created_at,
        ti.created_at as updated_at,
        u.full_name,
        u.department
    FROM talent_identification ti
    JOIN users u ON ti.employee_id = u.id
    WHERE ti.talent_type = 'High Potential'
    AND ti.employee_id NOT IN (SELECT employee_id FROM high_potential_employees)
    ORDER BY potential_score DESC
")->fetchAll();

// Get retirement forecasts
$forecasts = $pdo->query("
    SELECT * FROM retirement_forecasts
    ORDER BY year ASC
")->fetchAll();

// If no retirement forecasts exist, calculate from critical roles
if (empty($forecasts)) {
    $forecasts = $pdo->query("
        SELECT YEAR(retirement_date) as year, COUNT(*) as total_retirements, COUNT(*) as critical_roles_count
        FROM critical_roles
        WHERE retirement_date IS NOT NULL AND retirement_date > '0000-00-00'
        GROUP BY YEAR(retirement_date)
        ORDER BY year ASC
    ")->fetchAll();
}

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_employee') {
    $name = $_GET['name'] ?? '';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE full_name LIKE ? LIMIT 1");
    $stmt->execute(["%$name%"]);
    $employee = $stmt->fetch();
    
    header('Content-Type: application/json');
    if ($employee) {
        echo json_encode(['employee_id' => $employee['id']]);
    } else {
        echo json_encode(['error' => 'Employee not found']);
    }
    exit;
}

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Succession Planning</h1>
        <p class="text-gray-600">
            Manage critical roles, identify successors, and forecast workforce transitions
        </p>
    </div>
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <div class="flex items-center justify-between">
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <button onclick="this.parentElement.parentElement.remove()" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <div class="flex items-center justify-between">
                <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                <button onclick="this.parentElement.parentElement.remove()" class="text-red-700 hover:text-red-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <div class="flex gap-4">
            <a href="?tab=critical-roles" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'critical-roles' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-bullseye"></i>
                    <span>Critical Roles</span>
                </div>
            </a>
            <a href="?tab=high-potential" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'high-potential' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-star"></i>
                    <span>High Potential Employees</span>
                </div>
            </a>
            <a href="?tab=forecasting" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'forecasting' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-calendar"></i>
                    <span>Retirement Forecasting</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Critical Roles Tab -->
    <?php if ($activeTab == 'critical-roles'): ?>
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900">Critical Role Mapping</h2>
                <div class="flex items-center gap-2">
                    <?php if ($isAdmin): ?>
                        <a href="?tab=critical-roles&sync_forecasts=1" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-sync"></i>
                            <span>Sync Forecasts</span>
                        </a>
                        <button onclick="showAddRoleModal()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            <i class="fas fa-plus"></i>
                            <span>Add Critical Role</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-4">
                <?php foreach ($criticalRoles as $role): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($role['title']); ?></h3>
                                    <span class="px-3 py-1 rounded-full text-sm <?php echo getRiskLevelBadge($role['risk_level']); ?>">
                                        <?php echo htmlspecialchars($role['risk_level']); ?> Risk
                                    </span>
                                </div>
                                <p class="text-gray-600"><?php echo htmlspecialchars($role['department']); ?></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Current Holder</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($role['current_holder_name'] ?? 'TBD'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Retirement Date</p>
                                <p class="text-gray-900"><?php echo formatDate($role['retirement_date']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Successors Identified</p>
                                <p class="text-gray-900"><?php echo substr_count($role['successors'] ?? '', ',') + (empty($role['successors']) ? 0 : 1); ?></p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-gray-700">Succession Readiness</p>
                                <span class="text-gray-900 readiness-percentage"><?php echo $role['succession_readiness']; ?>%</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div
                                    class="h-2 rounded-full readiness-bar <?php echo $role['succession_readiness'] >= 75 ? 'bg-green-500' : ($role['succession_readiness'] >= 50 ? 'bg-yellow-500' : 'bg-red-500'); ?>"
                                    style="width: 0%"
                                    data-target="<?php echo $role['succession_readiness']; ?>"
                                ></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-4">
                            <?php if (!empty($role['successors'])): ?>
                                <div class="flex-1">
                                    <p class="text-gray-700 mb-2">Identified Successors:</p>
                                    <div class="flex gap-2 flex-wrap">
                                    <?php 
                                    foreach ($role['successors_list'] as $successor): 
                                        $successorName = $successor['full_name'];
                                        $successorId = $successor['id'];
                                    ?>
                                        <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm flex items-center gap-2">
                                            <?php echo htmlspecialchars($successorName); ?>
                                            <?php if ($isAdmin): ?>
                                                <button 
                                                    onclick="removeSuccessor(<?php echo $role['id']; ?>, <?php echo $successorId; ?>, '<?php echo htmlspecialchars($successorName); ?>')"
                                                    class="text-indigo-700 hover:text-red-600 transition-colors"
                                                    title="Remove successor"
                                                >
                                                    <i class="fas fa-times text-xs"></i>
                                                </button>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 text-sm">No successors identified yet</p>
                            <?php endif; ?>
                             <div class="flex items-center gap-2 ml-4">
                                <?php if ($isAdmin): ?>
                                    <button 
                                        onclick="showAddSuccessorModal(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['title']); ?>', '<?php echo htmlspecialchars($role['department']); ?>')"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm"
                                    >
                                        <i class="fas fa-user-plus"></i> Add Successor
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this critical role? This will also remove its successors.');">
                                        <input type="hidden" name="action" value="delete_critical_role">
                                        <input type="hidden" name="critical_role_id" value="<?php echo $role['id']; ?>">
                                        <button 
                                            type="submit"
                                            class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm flex items-center gap-1"
                                        >
                                            <i class="fas fa-trash-alt"></i>
                                            <span>Delete</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- High Potential Tab -->
    <?php if ($activeTab == 'high-potential'): ?>
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">High Potential Employees</h2>
                <?php if ($isAdmin): ?>
                    <button onclick="showAddHighPotentialModal()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-list"></i>
                        <span>List of Employees</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="space-y-4">
                <?php foreach ($highPotentials as $employee): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($employee['full_name']); ?></h3>
                                    <p class="text-gray-600"><?php echo htmlspecialchars($employee['current_role']); ?></p>
                                    <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($employee['department']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-star text-yellow-500"></i>
                                <span class="text-gray-900 font-semibold"><?php echo $employee['potential_score']; ?></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Years of Service</p>
                                <p class="text-gray-900"><?php echo $employee['years_of_service']; ?> years</p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Performance Rating</p>
                                <p class="text-gray-900"><?php echo $employee['performance_rating']; ?>/5.0</p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Potential Score</p>
                                <p class="text-gray-900"><?php echo $employee['potential_score']; ?>/100</p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Target Role</p>
                                <p class="text-gray-900 text-sm"><?php echo htmlspecialchars($employee['target_role']); ?></p>
                            </div>
                        </div>

                        <?php if (!empty($employee['development_areas'])): ?>
                            <div>
                                <p class="text-gray-700 mb-2">Development Areas:</p>
                                <div class="flex gap-2 flex-wrap">
                                    <?php foreach (explode(', ', $employee['development_areas']) as $area): ?>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm">
                                            <?php echo htmlspecialchars(trim($area)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center justify-end mt-4">
                            <?php if ($isAdmin): ?>
                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to remove this employee from high potential list?');">
                                    <input type="hidden" name="action" value="delete_high_potential">
                                    <input type="hidden" name="high_potential_id" value="<?php echo $employee['id']; ?>">
                                    <button 
                                        type="submit"
                                        class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm flex items-center gap-1"
                                    >
                                        <i class="fas fa-trash-alt"></i>
                                        <span>Remove</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Forecasting Tab -->
    <?php if ($activeTab == 'forecasting'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Retirement & Turnover Forecasting</h2>

            <?php if (empty($forecasts)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-calendar-alt text-gray-300 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Retirement Forecasts Available</h3>
                    <p class="text-gray-500 mb-4">Add critical roles with retirement dates to see forecasting data.</p>
                    <a href="?tab=critical-roles" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-plus"></i>
                        <span>Add Critical Role</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <?php foreach ($forecasts as $forecast): ?>
                        <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow forecast-card" data-year="<?php echo $forecast['year']; ?>" data-retirements="<?php echo $forecast['total_retirements']; ?>">
                            <p class="text-gray-600 mb-2"><?php echo $forecast['year']; ?></p>
                            <h3 class="text-2xl font-bold text-gray-900 mb-1 forecast-number"><?php echo $forecast['total_retirements']; ?></h3>
                            <p class="text-gray-600 text-sm mb-3">Total Retirements</p>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-exclamation-circle text-orange-500"></i>
                                <span class="text-orange-700 text-sm">
                                    <?php echo $forecast['critical_roles_count']; ?> critical roles
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Critical Role Modal -->
<div id="addRoleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Add Critical Role</h3>
            <button onclick="closeAddRoleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="addRoleForm" method="POST" action="" class="p-6">
            <input type="hidden" name="action" value="add_critical_role">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Role Title *</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Department *</label>
                    <input type="text" name="department" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Current Holder</label>
                    <select name="current_holder_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Employee</option>
                        <?php 
                        $employees = getAvailableEmployees($pdo);
                        foreach ($employees as $emp): 
                        ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name'] . ' - ' . $emp['department']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Retirement Date *</label>
                    <input type="date" name="retirement_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Risk Level *</label>
                    <select name="risk_level" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeAddRoleModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Add Role
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Successor Modal -->
<div id="addSuccessorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Add Successor</h3>
            <button onclick="closeAddSuccessorModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="addSuccessorForm" method="POST" action="" class="p-6">
            <input type="hidden" name="action" value="add_successor">
            <input type="hidden" name="critical_role_id" id="successor_role_id">
            <div class="mb-4 p-4 bg-indigo-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Role:</p>
                <p class="font-semibold text-gray-900" id="successor_role_title"></p>
                <p class="text-sm text-gray-600" id="successor_role_department"></p>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-2">Select Employee *</label>
                <select name="employee_id" id="successor_employee_select" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select Employee</option>
                    <?php 
                    foreach ($highPotentials as $hp): 
                    ?>
                        <option value="<?php echo $hp['employee_id']; ?>"><?php echo htmlspecialchars($hp['full_name'] . ' - ' . $hp['department'] . ($hp['current_role'] ? ' (' . $hp['current_role'] . ')' : '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeAddSuccessorModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Add Successor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- List of Employees Modal -->
<div id="addHighPotentialModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">List of Employees</h3>
            <button onclick="closeAddHighPotentialModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-0 max-h-[60vh] overflow-y-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                    <tr>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-900">Applicant</th>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-900">Position</th>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-900 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($allApplicants)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-gray-500 italic">No applicants found from HR1.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allApplicants as $applicant): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($applicant['name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($applicant['contact_info']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php echo htmlspecialchars($applicant['position_applied']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button 
                                        onclick="postApplicant(<?php echo $applicant['applicant_id']; ?>, '<?php echo htmlspecialchars(addslashes($applicant['name'])); ?>')"
                                        class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors"
                                    >
                                        Post
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function postApplicant(id, name) {
    if (!confirm(`Are you sure you want to approve ${name} and add them to the High Potential database?`)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'post_applicant';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'applicant_id';
    idInput.value = id;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<script>
// Auto-dismiss success/error messages
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });

    // Animate progress bars
    const progressBars = document.querySelectorAll('.readiness-bar');
    progressBars.forEach(bar => {
        const target = parseInt(bar.getAttribute('data-target'));
        const percentageSpan = bar.closest('.mb-3').querySelector('.readiness-percentage');
        animateProgressBar(bar, target, percentageSpan);
    });

    // Animate forecast numbers
    const forecastCards = document.querySelectorAll('.forecast-card');
    forecastCards.forEach(card => {
        const numberElement = card.querySelector('.forecast-number');
        const targetValue = parseInt(card.getAttribute('data-retirements')) || 0;
        numberElement.textContent = '0';
        animateCounter(numberElement, targetValue);
    });

    // Add hover effects to cards
    const roleCards = document.querySelectorAll('.border.border-gray-200.rounded-lg.p-6');
    roleCards.forEach(card => {
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

// Animate Counter
function animateCounter(element, target, duration = 1500) {
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

// Add Critical Role Modal
function showAddRoleModal() {
    const modal = document.getElementById('addRoleModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAddRoleModal() {
    const modal = document.getElementById('addRoleModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('addRoleForm').reset();
}

// Add Successor Modal
function showAddSuccessorModal(roleId, roleTitle, roleDepartment) {
    const modal = document.getElementById('addSuccessorModal');
    document.getElementById('successor_role_id').value = roleId;
    document.getElementById('successor_role_title').textContent = roleTitle;
    document.getElementById('successor_role_department').textContent = roleDepartment;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAddSuccessorModal() {
    const modal = document.getElementById('addSuccessorModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('addSuccessorForm').reset();
}

// Add High Potential Modal
function showAddHighPotentialModal() {
    const modal = document.getElementById('addHighPotentialModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAddHighPotentialModal() {
    const modal = document.getElementById('addHighPotentialModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('addHighPotentialForm').reset();
}

// Remove Successor with Confirmation
function removeSuccessor(roleId, employeeId, successorName) {
    if (!confirm(`Are you sure you want to remove ${successorName} as a successor?`)) {
        return;
    }

    submitRemoveSuccessor(roleId, employeeId);
}

function submitRemoveSuccessor(roleId, employeeId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'remove_successor';
    form.appendChild(actionInput);
    
    const roleInput = document.createElement('input');
    roleInput.type = 'hidden';
    roleInput.name = 'critical_role_id';
    roleInput.value = roleId;
    form.appendChild(roleInput);
    
    const empInput = document.createElement('input');
    empInput.type = 'hidden';
    empInput.name = 'employee_id';
    empInput.value = employeeId;
    form.appendChild(empInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const roleModal = document.getElementById('addRoleModal');
    const successorModal = document.getElementById('addSuccessorModal');
    const highPotentialModal = document.getElementById('addHighPotentialModal');
    
    if (event.target === roleModal) {
        closeAddRoleModal();
    }
    
    if (event.target === successorModal) {
        closeAddSuccessorModal();
    }

    if (event.target === highPotentialModal) {
        closeAddHighPotentialModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAddRoleModal();
        closeAddSuccessorModal();
        closeAddHighPotentialModal();
    }
});

// Form validation
document.getElementById('addRoleForm')?.addEventListener('submit', function(e) {
    const title = this.querySelector('[name="title"]').value.trim();
    const department = this.querySelector('[name="department"]').value.trim();
    const retirementDate = this.querySelector('[name="retirement_date"]').value;
    
    if (!title || !department || !retirementDate) {
        e.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }
    
    const date = new Date(retirementDate);
    if (date < new Date()) {
        e.preventDefault();
        alert('Retirement date must be in the future');
        return false;
    }
});

document.getElementById('addSuccessorForm')?.addEventListener('submit', function(e) {
    const employeeId = this.querySelector('[name="employee_id"]').value;
    
    if (!employeeId) {
        e.preventDefault();
        alert('Please select an employee');
        return false;
    }
});

document.getElementById('addHighPotentialForm')?.addEventListener('submit', function(e) {
    const employeeId = this.querySelector('[name="employee_id"]').value;
    if (!employeeId) {
        e.preventDefault();
        alert('Please select an employee');
        return false;
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
require_once 'includes/footer.php';
?>
