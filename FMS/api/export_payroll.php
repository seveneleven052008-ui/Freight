<?php
require_once '../config/config.php';
requireLogin();

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Admin check
$stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$roleVal = strtolower($user['role'] ?? '');
$isAdmin = ($roleVal === 'admin') || (($user['username'] ?? '') === 'admin');

if (!$isAdmin) {
    die("Unauthorized access.");
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$monthFilter = $_GET['month'] ?? '';

// Build Query
$query = "
    SELECT 
        u.full_name,
        u.department,
        p.month,
        p.period_start,
        p.period_end,
        p.gross_pay,
        p.deductions,
        p.net_pay,
        p.status
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
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSV
$filename = "payroll_report_" . date('Y-m-d_His') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// CSV Headers
fputcsv($output, [
    'Employee Name',
    'Department',
    'Payroll Month',
    'Period Start',
    'Period End',
    'Gross Pay',
    'Deductions',
    'Net Pay',
    'Status'
]);

// CSV Data
foreach ($data as $row) {
    fputcsv($output, [
        $row['full_name'],
        $row['department'],
        $row['month'],
        $row['period_start'],
        $row['period_end'],
        number_format($row['gross_pay'], 2, '.', ''),
        number_format($row['deductions'], 2, '.', ''),
        number_format($row['net_pay'], 2, '.', ''),
        $row['status']
    ]);
}

fclose($output);
exit;
