<?php
include 'config.php';
session_start();

header('Content-Type: application/json');

$response = ['labels' => [], 'data' => []];

if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

if (!$startDate || !$endDate) {
    echo json_encode($response);
    exit();
}

// Fetch activity data for the chart from the 'activities' table within the date range
$activity_logs = [];
$stmt_activities = $conn->prepare("SELECT activity_timestamp FROM activities WHERE user_id = ? AND activity_timestamp BETWEEN ? AND ? ORDER BY activity_timestamp ASC");
$stmt_activities->bind_param("iss", $user_id, $startDate, $endDate);
$stmt_activities->execute();
$result_activities = $stmt_activities->get_result();
while ($row = $result_activities->fetch_assoc()) {
    $activity_logs[] = $row['activity_timestamp'];
}
$stmt_activities->close();

// Process activity logs to get daily counts
$daily_activities = [];
foreach ($activity_logs as $timestamp) {
    $date = date('Y-m-d', strtotime($timestamp));
    if (!isset($daily_activities[$date])) {
        $daily_activities[$date] = 0;
    }
    $daily_activities[$date]++;
}

// Sort by date
ksort($daily_activities);

// Prepare data for Chart.js
$response['labels'] = array_keys($daily_activities);
$response['data'] = array_values($daily_activities);

echo json_encode($response);

$conn->close();
?>
