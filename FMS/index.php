<?php
// Redirect to dashboard if logged in, otherwise to login
require_once 'config/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>
