<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get job ID from URL
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

if ($job_id <= 0) {
    // Invalid job ID, redirect to dashboard
    header('Location: dashboard.php');
    exit;
}

// Redirect to dashboard with a parameter to open the assessment modal
header('Location: dashboard.php?open_assessment=' . $job_id);
exit; 