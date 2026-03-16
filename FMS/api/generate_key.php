<?php
// Initialize database connection
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

$serviceName = $_GET['service'] ?? 'HR4_Payroll_System';

// Setup the api_keys table if it does not exist
$pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    api_token VARCHAR(255) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL
) ENGINE=InnoDB;");

// Generate a secure pseudo-random 64 hex character token
$tokenBytes = random_bytes(32);
$apiToken = bin2hex($tokenBytes);

try {
    $stmt = $pdo->prepare("INSERT INTO api_keys (service_name, api_token, is_active) VALUES (?, ?, 1)");
    $stmt->execute([$serviceName, $apiToken]);
    
    echo "<h1>API Key Generated Successfully!</h1>";
    echo "<p><b>Service Name:</b> " . htmlspecialchars($serviceName) . "</p>";
    echo "<p><b>API Token:</b> <code>" . htmlspecialchars($apiToken) . "</code></p>";
    echo "<p>Please save this token securely. It will be required in the Authorization header: <code>Bearer &lt;token&gt;</code></p>";
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Integrity constraint violation
        echo "Error: Could not generate key. Possible duplicate service name mapping.";
    } else {
        echo "Database Error: " . htmlspecialchars($e->getMessage());
    }
}
