<?php
/**
 * SKMI Cloud Storage - Refactored Version
 * Main entry point for the Huda folder structure
 */

// Start session
session_start();

// Include configuration and functions
require_once '../config.php';
require_once '../functions.php';
require_once 'includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Initialize the application
$app = new SKMICloudApp();
$app->run();
?>
