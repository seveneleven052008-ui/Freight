<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/header.php';
?>

<div class="min-h-screen bg-gray-50 flex">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    
    <main class="flex-1 overflow-auto">
        <?php if (isset($content)) echo $content; ?>
