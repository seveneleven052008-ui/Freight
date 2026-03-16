<?php
/**
 * Password Setup Utility
 * Run this file once to set up proper password hashes
 * 
 * Usage: php setup_password.php
 * Or access via browser: http://localhost/setup_password.php
 */

require_once 'config/database.php';

$defaultPassword = 'password'; // Change this to your desired default password
$hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

try {
    $pdo = getDBConnection();
    
    // Update all user passwords
    $stmt = $pdo->prepare("UPDATE users SET password = ?");
    $stmt->execute([$hashedPassword]);
    
    $count = $stmt->rowCount();
    
    echo "Successfully updated passwords for {$count} users.\n";
    echo "Default password: {$defaultPassword}\n";
    echo "Hashed password: {$hashedPassword}\n";
    echo "\nYou can now log in with any username and password: '{$defaultPassword}'\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
