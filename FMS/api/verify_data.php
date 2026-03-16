<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "<h2>Checking API Keys Table</h2>";
$stmt = $pdo->query("SELECT id, service_name, is_active, created_at, last_used FROM api_keys");
$keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($keys)) {
    echo "No API keys found.<br>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Service</th><th>Active</th><th>Created</th><th>Last Used</th></tr>";
    foreach ($keys as $key) {
        echo "<tr>";
        echo "<td>{$key['id']}</td>";
        echo "<td>{$key['service_name']}</td>";
        echo "<td>{$key['is_active']}</td>";
        echo "<td>{$key['created_at']}</td>";
        echo "<td>{$key['last_used']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Checking Inserted Payslip Data from HR4 (Employee EMP002)</h2>";
// Find internal user ID
$stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
$stmt->execute(['EMP002']);
$user = $stmt->fetch();

if (!$user) {
    echo "User EMP002 not found in HR2.";
} else {
    $stmt = $pdo->prepare("SELECT * FROM payslips WHERE employee_id = ? ORDER BY period_start DESC");
    $stmt->execute([$user['id']]);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($payslips)) {
        echo "No payslips found for EMP002.<br>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Month</th><th>Period Start</th><th>Period End</th><th>Gross Pay</th><th>Net Pay</th><th>Status</th></tr>";
        foreach ($payslips as $slip) {
            echo "<tr>";
            echo "<td>{$slip['id']}</td>";
            echo "<td>{$slip['month']}</td>";
            echo "<td>{$slip['period_start']}</td>";
            echo "<td>{$slip['period_end']}</td>";
            echo "<td>\${$slip['gross_pay']}</td>";
            echo "<td>\${$slip['net_pay']}</td>";
            echo "<td>{$slip['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}
?>
