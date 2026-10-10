<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Smart Pharmacy Inventory Management System';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260531a">
    <script defer src="assets/js/app.js?v=20260531a"></script>
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-panel">
        <header class="topbar">
            <button class="menu-toggle" type="button" aria-label="Toggle navigation">☰</button>
            <div>
                <h1><?php echo h($pageTitle); ?></h1>
                <p>Simple, responsive pharmacy management for DBMS mini project use.</p>
            </div>
            <div class="user-chip"><?php echo h(currentUserName()); ?> · <?php echo h(currentUserLabel()); ?></div>
        </header>
        <section class="content-area">
            <?php echo renderFlash(); ?>