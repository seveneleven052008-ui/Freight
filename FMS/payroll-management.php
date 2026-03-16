<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'Payroll Management';
$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get user info to check role
$userInfo = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userInfo->execute([$userId]);
$personalInfo = $userInfo->fetch();

// Determine if current user should be treated as an admin
$roleValue = strtolower($personalInfo['role'] ?? '');
$isAdmin = ($roleValue === 'admin') || (($personalInfo['username'] ?? '') === 'admin');

if (!$isAdmin) {
    // Redirect non-admins to ESS
    header('Location: ess.php');
    exit;
}

// Handle Filters
$statusFilter = $_GET['status'] ?? '';
$monthFilter = $_GET['month'] ?? '';

// Build Query for all Payslips
$query = "
    SELECT 
        p.*, 
        u.full_name,
        u.department,
        u.position
    FROM payslips p
    JOIN users u ON p.employee_id = u.id
    WHERE 1=1
";
$params = [];

if ($statusFilter) {
    $query .= " AND p.status = ?";
    $params[] = $statusFilter;
}

if ($monthFilter) {
    $query .= " AND p.month LIKE ?";
    $params[] = "%$monthFilter%";
}

$query .= " ORDER BY p.period_start DESC, u.full_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct months for filter dropdown
$monthsStmt = $pdo->query("SELECT DISTINCT month FROM payslips ORDER BY period_start DESC");
$availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate Summary Stats
$stats = [
    'total_payslips' => count($payslips),
    'total_gross' => 0,
    'total_net' => 0,
    'total_deductions' => 0
];

foreach ($payslips as $slip) {
    $stats['total_gross'] += $slip['gross_pay'];
    $stats['total_net'] += $slip['net_pay'];
    $stats['total_deductions'] += $slip['deductions'];
}

// Handle Single Payslip View
$viewSlipId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$currentSlip = null;
if ($viewSlipId > 0) {
    foreach ($payslips as $slip) {
        if ($slip['id'] == $viewSlipId) {
            $currentSlip = $slip;
            break;
        }
    }
}

ob_start();
?>

<div class="p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Payroll Administration</h1>
            <p class="text-gray-600">
                View and manage all employee payslip records synchronized from the HR4 system.
            </p>
        </div>
        <div class="flex gap-3">
             <button onclick="window.location.href='api/generate_key.php?service=HR4_System'" class="flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 transition-colors border border-indigo-200" title="Generate API token for HR4 integration">
                <i class="fas fa-key"></i>
                <span>API Keys</span>
            </button>
            <a href="api/export_payroll.php?month=<?php echo urlencode($monthFilter); ?>&status=<?php echo urlencode($statusFilter); ?>" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                <i class="fas fa-file-export"></i>
                <span>Export Report</span>
            </a>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-indigo-500">
            <div class="flex items-center gap-4">
                <div class="bg-indigo-100 p-3 rounded-lg text-indigo-600">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Payslips</h3>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_payslips']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
            <div class="flex items-center gap-4">
                <div class="bg-blue-100 p-3 rounded-lg text-blue-600">
                    <i class="fas fa-coins text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Gross Pay</h3>
                    <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($stats['total_gross']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
            <div class="flex items-center gap-4">
                <div class="bg-red-100 p-3 rounded-lg text-red-600">
                    <i class="fas fa-hand-holding-usd text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Deductions</h3>
                    <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($stats['total_deductions']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
            <div class="flex items-center gap-4">
                <div class="bg-green-100 p-3 rounded-lg text-green-600">
                    <i class="fas fa-money-check-alt text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Net Pay</h3>
                    <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($stats['total_net']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($currentSlip): ?>
        <!-- Detailed Payslip View for Admin -->
        <div class="mb-12">
            <div class="flex items-center justify-between mb-4">
                <a href="payroll-management.php" class="text-indigo-600 hover:text-indigo-700 flex items-center gap-2 font-medium">
                    <i class="fas fa-arrow-left"></i>
                    Back to List
                </a>
            </div>

            <div class="max-w-4xl mx-auto">
                <!-- Detailed Payslip Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                    <!-- Teal Header -->
                    <div class="bg-[#00bcd4] p-6 text-white relative">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-3xl font-light mb-2">Payslip</h3>
                                <div class="flex items-center gap-2 text-[#e0f7fa]">
                                    <span class="text-sm font-medium">(Selected Payroll Record)</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <label class="text-[10px] uppercase font-bold tracking-widest text-[#e0f7fa] opacity-80">Select Payroll Records</label>
                                <select onchange="window.location.href='?view=' + this.value" class="bg-white/10 border border-white/20 text-white rounded-lg px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-white/50 cursor-pointer backdrop-blur-sm">
                                    <?php foreach ($payslips as $slip): ?>
                                        <option value="<?php echo $slip['id']; ?>" <?php echo $slip['id'] == $viewSlipId ? 'selected' : ''; ?> class="text-gray-900 text-sm">
                                            <?php echo htmlspecialchars($slip['full_name']); ?> - <?php echo htmlspecialchars($slip['month']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="p-8">
                        <!-- Employee Info Section -->
                        <div class="mb-8 p-4 bg-gray-50 rounded-lg flex justify-between items-center text-sm">
                            <div class="flex gap-4">
                                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold text-lg">
                                    <?php echo substr($currentSlip['full_name'], 0, 1); ?>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($currentSlip['full_name']); ?></p>
                                    <p class="text-gray-500"><?php echo htmlspecialchars($currentSlip['position']); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-gray-500">Period: <span class="font-bold text-gray-900"><?php echo htmlspecialchars($currentSlip['month']); ?></span></p>
                                <p class="text-gray-500">Status: <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-bold"><?php echo htmlspecialchars($currentSlip['status']); ?></span></p>
                            </div>
                        </div>

                        <!-- Table Headers -->
                        <div class="grid grid-cols-12 border-b border-gray-100 pb-4 mb-6 px-4">
                            <span class="col-span-6 text-[#9c27b0] text-[11px] font-bold uppercase tracking-[0.2em]">Particulars</span>
                            <span class="col-span-3 text-[#9c27b0] text-[11px] font-bold uppercase tracking-[0.2em] text-center">Earnings</span>
                            <span class="col-span-3 text-[#9c27b0] text-[11px] font-bold uppercase tracking-[0.2em] text-right">Deductions</span>
                        </div>

                        <!-- Table Body -->
                        <div class="space-y-6 px-4">
                            <!-- Basic Salary Row -->
                            <div class="grid grid-cols-12 items-center">
                                <span class="col-span-6 text-gray-700 font-medium tracking-wide">Basic Salary</span>
                                <span class="col-span-3 text-gray-900 font-semibold text-center"><?php echo formatCurrency($currentSlip['gross_pay']); ?></span>
                                <span class="col-span-3 text-gray-400 text-right">-</span>
                            </div>

                            <!-- Overtime (MOCKED) -->
                            <div class="grid grid-cols-12 items-center">
                                <span class="col-span-6 text-gray-700 font-medium tracking-wide">Overtime Pay</span>
                                <span class="col-span-3 text-gray-900 font-semibold text-center"><?php echo formatCurrency(0); ?></span>
                                <span class="col-span-3 text-gray-400 text-right">-</span>
                            </div>

                            <!-- Allowances (MOCKED) -->
                            <div class="grid grid-cols-12 items-center">
                                <span class="col-span-6 text-gray-700 font-medium tracking-wide">Allowances</span>
                                <span class="col-span-3 text-gray-900 font-semibold text-center"><?php echo formatCurrency(0); ?></span>
                                <span class="col-span-3 text-gray-400 text-right">-</span>
                            </div>

                            <!-- Deductions Row -->
                            <div class="grid grid-cols-12 items-center">
                                <span class="col-span-6 text-gray-700 font-medium tracking-wide">Statutory Deductions</span>
                                <span class="col-span-3 text-gray-400 text-center">-</span>
                                <span class="col-span-3 text-red-500 font-semibold text-right"><?php echo formatCurrency($currentSlip['deductions']); ?></span>
                            </div>

                            <!-- Other Deductions (MOCKED) -->
                            <div class="grid grid-cols-12 items-center">
                                <span class="col-span-6 text-gray-700 font-medium tracking-wide">Other Deductions</span>
                                <span class="col-span-3 text-gray-400 text-center">-</span>
                                <span class="col-span-3 text-red-500 font-semibold text-right"><?php echo formatCurrency(0); ?></span>
                            </div>
                        </div>

                        <!-- Summary Section -->
                        <div class="mt-12 pt-8 border-t border-gray-50 bg-gray-50/50 -mx-8 -mb-8 px-8 pb-8">
                            <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                                <div>
                                    <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Take Home Pay</p>
                                    <p class="text-3xl font-black text-[#00bcd4]"><?php echo formatCurrency($currentSlip['net_pay']); ?></p>
                                </div>
                                <div class="flex gap-3">
                                    <button class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition-colors flex items-center gap-2">
                                        <i class="fas fa-print"></i>
                                        Print
                                    </button>
                                    <button class="px-6 py-3 bg-[#00bcd4] text-white rounded-xl font-bold shadow-lg shadow-cyan-500/30 hover:bg-[#00acc1] transition-transform active:scale-95 flex items-center gap-2">
                                        <i class="fas fa-download"></i>
                                        PDF Version
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow <?php echo $currentSlip ? 'opacity-50 pointer-events-none' : 'mb-8'; ?>">
        <div class="p-4 border-b border-gray-200">
            <form id="filterForm" method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Month</label>
                    <select name="month" class="w-full sm:w-64 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Months</option>
                        <?php foreach($availableMonths as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $monthFilter === $m ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                    <select name="status" class="w-full sm:w-64 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Statuses</option>
                        <option value="Paid" <?php echo $statusFilter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <?php if ($statusFilter || $monthFilter): ?>
                    <div>
                        <a href="payroll-management.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Payslips Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Pay</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Deductions</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Pay</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($payslips)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                No payslip records found matching the criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payslips as $slip): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                            <span class="text-indigo-700 font-bold"><?php echo substr($slip['full_name'], 0, 1); ?></span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($slip['full_name']); ?></div>
                                            <div class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($slip['employee_id']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($slip['department']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($slip['position']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($slip['month']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo formatDate($slip['period_start']); ?> - <?php echo formatDate($slip['period_end']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                                    <?php echo formatCurrency($slip['gross_pay']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 text-right">
                                    <?php echo formatCurrency($slip['deductions']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right font-bold">
                                    <?php echo formatCurrency($slip['net_pay']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusBadge($slip['status']); ?>">
                                        <?php echo htmlspecialchars($slip['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                    <a href="?view=<?php echo $slip['id']; ?><?php echo $monthFilter ? '&month='.urlencode($monthFilter) : ''; ?><?php echo $statusFilter ? '&status='.urlencode($statusFilter) : ''; ?>" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 p-2 rounded-lg inline-block" title="View Detailed Payslip">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="text-gray-400 hover:text-gray-600 bg-gray-50 p-2 rounded-lg inline-block" title="View Payslip PDF">
                                        <i class="fas fa-file-pdf"></i>
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

<?php
$content = ob_get_clean();
require 'includes/layout.php';
?>
