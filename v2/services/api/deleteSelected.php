<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../../config.php';
include '../folderService.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$itemsToDelete = $input['items'] ?? [];

if (empty($itemsToDelete)) {
    echo json_encode(['success' => false, 'message' => 'No items selected.']);
    exit;
}

$result = deleteSelectedItems($conn, $_SESSION['user_id'], $itemsToDelete, 'uploads/');

echo json_encode($result);

$conn->close();
