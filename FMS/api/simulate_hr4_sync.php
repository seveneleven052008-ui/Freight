<?php
// We will query the generate_key script via HTTP to get a token, then mock HR4

// 1. Get a token
$keyResponse = file_get_contents('http://localhost/FMS/api/generate_key.php?service=HR4_Test_' . time());

preg_match('/<code>([a-f0-9]+)<\/code>/', $keyResponse, $matches);
if (empty($matches[1])) {
    die("Failed to grab a token from generate_key.php\n" . $keyResponse);
}

$token = $matches[1];

// 2. Mock HR4 Payload
$payload = [
    [
        "employee_id" => "EMP002",
        "month" => "January 2026",
        "period_start" => "2026-01-01",
        "period_end" => "2026-01-31",
        "gross_pay" => 6500.00,
        "deductions" => 1500.00,
        "net_pay" => 5000.00,
        "status" => "Paid"
    ],
    [
        "employee_id" => "EMP00X_NOT_EXIST", // Purposely invalid to test error handling
        "month" => "January 2026",
        "period_start" => "2026-01-01",
        "period_end" => "2026-01-31",
        "gross_pay" => 5500.00,
        "deductions" => 1000.00,
        "net_pay" => 4500.00,
    ]
];

$jsonPayload = json_encode($payload);

// 3. Send cURL request
$ch = curl_init('http://localhost/FMS/api/payroll_sync.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response body: " . $response . "\n";

?>
