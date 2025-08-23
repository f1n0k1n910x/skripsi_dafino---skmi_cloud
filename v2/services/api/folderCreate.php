<?php
// v2/services/api/createFolder.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../folderService.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$folderName = $_POST['folderName'] ?? '';
$parentFolderId = !empty($_POST['parent_folder_id']) ? (int)$_POST['parent_folder_id'] : null;
$parentFolderPath = $_POST['parent_folder_path'] ?? '';

$response = createFolder($conn, $userId, $folderName, $parentFolderId, $parentFolderPath);

echo json_encode($response);
$conn->close();
