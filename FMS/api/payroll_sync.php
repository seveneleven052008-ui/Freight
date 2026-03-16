<?php
// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust if you want to restrict to HR4's IP/domain
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Include database config
require_once __DIR__ . '/../config/database.php';

// Define a helper function to output JSON and exit
function sendResponse($statusCode, $success, $message, $data = null) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// 1. Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, 'Method Not Allowed. Please use POST.');
}

// 2. Validate Authorization Header
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    sendResponse(401, false, 'Unauthorized. Bearer token missing.');
}

$providedToken = $matches[1];

try {
    $pdo = getDBConnection();

    // Setup the api_keys table if it does not exist (fallback for MySQL CLI failure earlier)
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_name VARCHAR(100) NOT NULL,
        api_token VARCHAR(255) UNIQUE NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used TIMESTAMP NULL
    ) ENGINE=InnoDB;");

    // 3. Authenticate the Token
    $stmt = $pdo->prepare("SELECT id, service_name FROM api_keys WHERE api_token = ? AND is_active = 1");
    $stmt->execute([$providedToken]);
    $apiKey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$apiKey) {
        sendResponse(401, false, 'Unauthorized. Invalid or inactive token.');
    }

    // Update last_used
    $updateToken = $pdo->prepare("UPDATE api_keys SET last_used = NOW() WHERE id = ?");
    $updateToken->execute([$apiKey['id']]);

    // 4. Parse JSON Payload
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        sendResponse(400, false, 'Bad Request. Invalid JSON payload.');
    }

    // Prepare arrays to track successes and errors if HR4 sends an array of payslips
    $successes = [];
    $errors = [];

    // Allow single object or array of objects
    $payslipsData = isset($data['employee_id']) ? [$data] : $data;

    if (empty($payslipsData)) {
         sendResponse(400, false, 'Bad Request. Empty payload.');
    }

    $pdo->beginTransaction();

    $findUserStmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
    $checkPayslipStmt = $pdo->prepare("SELECT id FROM payslips WHERE employee_id = ? AND period_start = ? AND period_end = ?");
    $insertPayslipStmt = $pdo->prepare("INSERT INTO payslips (employee_id, month, period_start, period_end, gross_pay, deductions, net_pay, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $updatePayslipStmt = $pdo->prepare("UPDATE payslips SET month = ?, gross_pay = ?, deductions = ?, net_pay = ?, status = ? WHERE id = ?");

    foreach ($payslipsData as $index => $slip) {
        // Validate required fields
        $requiredFields = ['employee_id', 'month', 'period_start', 'period_end', 'gross_pay', 'net_pay'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($slip[$field]) || $slip[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $errors[] = "Item index {$index}: Missing required fields (" . implode(', ', $missingFields) . ")";
            continue;
        }

        // Find internal user ID
        $findUserStmt->execute([$slip['employee_id']]);
        $user = $findUserStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = "Item index {$index}: Employee with HR ID {$slip['employee_id']} not found in HR2 system.";
            continue;
        }

        $internalUserId = $user['id'];
        $deductions = $slip['deductions'] ?? 0;
        $status = $slip['status'] ?? 'Paid';

        // Check if payslip already exists for this exact period
        $checkPayslipStmt->execute([$internalUserId, $slip['period_start'], $slip['period_end']]);
        $existingPayslip = $checkPayslipStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingPayslip) {
            // Update
            $updatePayslipStmt->execute([
                $slip['month'],
                $slip['gross_pay'],
                $deductions,
                $slip['net_pay'],
                $status,
                $existingPayslip['id']
            ]);
            $successes[] = "Updated payslip for {$slip['employee_id']} ({$slip['month']})";
        } else {
            // Insert
            $insertPayslipStmt->execute([
                $internalUserId,
                $slip['month'],
                $slip['period_start'],
                $slip['period_end'],
                $slip['gross_pay'],
                $deductions,
                $slip['net_pay'],
                $status
            ]);
            $successes[] = "Inserted new payslip for {$slip['employee_id']} ({$slip['month']})";
        }
    }

    $pdo->commit();

    sendResponse(200, true, 'Payroll sync completed.', [
        'processed' => count($payslipsData),
        'success_count' => count($successes),
        'error_count' => count($errors),
        'successes' => $successes,
        'errors' => $errors
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // In production, log $e->getMessage() securely instead of sending it directly
    sendResponse(500, false, 'Internal Server Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, false, 'Internal Server Error: ' . $e->getMessage());
}
