<?php
include 'config.php';
include 'functions.php'; // For logActivity, getFileIconClassPhp, formatBytes

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($memberId === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid member ID.']);
    exit();
}

$memberDetails = [];

// Fetch member username
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Member not found.']);
    exit();
}

$memberDetails['username'] = $user['username'];

// Fetch total public files for this member
$stmt = $conn->prepare("SELECT COUNT(id) AS total_public_files FROM files WHERE user_id = ? AND folder_id IS NULL");
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
$memberDetails['total_public_files'] = $result->fetch_assoc()['total_public_files'];
$stmt->close();

// Fetch recent public files (e.g., last 5)
$recentPublicFiles = [];
$stmt = $conn->prepare("SELECT file_name, file_size, file_type FROM files WHERE user_id = ? AND folder_id IS NULL ORDER BY uploaded_at DESC LIMIT 5");
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentPublicFiles[] = $row;
}
$memberDetails['recent_public_files'] = $recentPublicFiles;
$stmt->close();

// Fetch recent activities for this member (e.g., last 10)
$recentActivities = [];
$stmt = $conn->prepare("SELECT activity_type, description, timestamp FROM activities WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10");
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentActivities[] = $row;
}
$memberDetails['recent_activities'] = $recentActivities;
$stmt->close();

echo json_encode(['success' => true, 'member' => $memberDetails]);

$conn->close();
?>
