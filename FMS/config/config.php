<?php
// Application Configuration
session_start();

// Base URL
define('BASE_URL', 'http://localhost');

// Application Settings
define('APP_NAME', 'Freight Management System HR2');
define('APP_VERSION', '1.0.0');

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Check if session is expired
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Include database connection
require_once __DIR__ . '/database.php';

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? 'Employee';
}

function getUserName() {
    return $_SESSION['full_name'] ?? 'User';
}

function getUserEmail() {
    return $_SESSION['email'] ?? '';
}

function getUserDepartment() {
    return $_SESSION['department'] ?? '';
}

function getUserPosition() {
    return $_SESSION['position'] ?? '';
}

function getUserProfilePicture() {
    return $_SESSION['profile_picture'] ?? '';
}

function formatDate($date) {
    if (empty($date)) return '';
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return '';
    return date('M d, Y h:i A', strtotime($datetime));
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getStatusBadge($status) {
    $badges = [
        'Completed' => 'bg-green-100 text-green-700',
        'In Progress' => 'bg-blue-100 text-blue-700',
        'Upcoming' => 'bg-gray-100 text-gray-700',
        'Pending' => 'bg-yellow-100 text-yellow-700',
        'Approved' => 'bg-green-100 text-green-700',
        'Rejected' => 'bg-red-100 text-red-700',
        'Paid' => 'bg-green-100 text-green-700',
        'Scheduled' => 'bg-blue-100 text-blue-700',
        'Passed' => 'bg-green-100 text-green-700',
        'Failed' => 'bg-red-100 text-red-700',
    ];
    return $badges[$status] ?? 'bg-gray-100 text-gray-700';
}

function getRiskLevelBadge($level) {
    $badges = [
        'High' => 'bg-red-100 text-red-700',
        'Medium' => 'bg-yellow-100 text-yellow-700',
        'Low' => 'bg-green-100 text-green-700',
    ];
    return $badges[$level] ?? 'bg-gray-100 text-gray-700';
}

function getLevelBadge($level) {
    $badges = [
        'Expert' => 'bg-green-100 text-green-700',
        'Advanced' => 'bg-blue-100 text-blue-700',
        'Intermediate' => 'bg-yellow-100 text-yellow-700',
        'Beginner' => 'bg-orange-100 text-orange-700',
    ];
    return $badges[$level] ?? 'bg-gray-100 text-gray-700';
}
