<?php
require_once 'config/config.php';
requireLogin();

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get Validated Leave for current user
$stmt = $pdo->prepare("
    SELECT lv.*, u.full_name, u.department
    FROM leave_validation lv
    JOIN users u ON lv.employee_id = u.id
    WHERE lv.employee_id = ? AND lv.validation_status = 'Validated'
    ORDER BY lv.leave_date DESC
");
$stmt->execute([$userId]);
$validatedLeave = $stmt->fetchAll();


// Get recent attendance status (mock from leave validation or actual table)
$attendanceStmt = $pdo->prepare("
    SELECT * FROM attendance_records WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 5
");
$attendanceStmt->execute([$userId]);
$recentAttendance = $attendanceStmt->fetchAll();

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <div class="flex items-center gap-4 mb-2">
            <h1 class="text-3xl font-bold text-gray-900">Leave & Attendance</h1>
            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-semibold border border-green-200">ESS Dashboard</span>
        </div>
        <p class="text-gray-600">Track your validated leave requests and attendance history.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Approved Leave Section -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-calendar-check text-indigo-600"></i>
                        Validated Leave Records
                    </h2>
                </div>
                <div class="p-0 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Leave Date</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assigned Shift</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Validated At</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php if (empty($validatedLeave)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-history text-4xl mb-4 opacity-20"></i>
                                            <p>No validated leave records found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($validatedLeave as $lv): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($lv['leave_date']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-600"><?php echo htmlspecialchars($lv['shift']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-100">
                                                <i class="fas fa-check mr-1"></i> Validated
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($lv['created_at']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Attendance Summary Section -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-gray-50">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-user-clock text-indigo-600"></i>
                        Recent Attendance
                    </h2>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <?php if (empty($recentAttendance)): ?>
                            <p class="text-center text-gray-400 py-4 italic">No recent attendance records.</p>
                        <?php else: ?>
                            <?php foreach ($recentAttendance as $att): ?>
                                <div class="flex items-start gap-4 p-3 rounded-lg border border-gray-50 hover:border-indigo-100 transition-all">
                                    <div class="w-12 h-12 rounded-lg flex flex-col items-center justify-center <?php echo $att['status'] === 'Present' ? 'bg-indigo-50 text-indigo-700' : 'bg-orange-50 text-orange-700'; ?>">
                                        <span class="text-xs font-bold uppercase"><?php echo date('M', strtotime($att['attendance_date'])); ?></span>
                                        <span class="text-lg font-bold leading-none"><?php echo date('d', strtotime($att['attendance_date'])); ?></span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($att['status']); ?></span>
                                            <span class="text-xs text-gray-500"><?php echo htmlspecialchars($att['hours']); ?> hrs</span>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            <?php echo $att['check_in'] ? $att['check_in'] . ' - ' . ($att['check_out'] ?: 'Present') : 'Leave day'; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <button class="w-full mt-6 py-3 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors font-semibold flex items-center justify-center gap-2 text-sm border border-gray-200">
                        View Full History
                        <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>
            
            <!-- Quick Tip Card -->
            <div class="mt-8 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-xl p-6 text-white shadow-lg">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h3 class="font-bold">Pro Tip</h3>
                </div>
                <p class="text-indigo-100 text-sm leading-relaxed mb-4">
                    Your attendance is updated automatically once your leave request is **Validated** by the HR3 scheduling department.
                </p>
                <div class="h-1 w-full bg-white/20 rounded-full overflow-hidden">
                    <div class="h-full bg-white w-2/3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>
