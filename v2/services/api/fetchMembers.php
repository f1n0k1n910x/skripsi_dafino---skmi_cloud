<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
require_once __DIR__ . '/../../../config.php';

$searchMemberQuery = $_GET['q'] ?? '';

$members = [];
$sql = "SELECT id, username, email, full_name, role, last_active, last_login FROM users";
$params = [];
$types = "";

if (!empty($searchMemberQuery)) {
    $sql .= " WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
    $searchTerm = '%' . $searchMemberQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}
$sql .= " ORDER BY username ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $isOnline = (strtotime($row['last_active']) > strtotime('-15 minutes'));
    $row['is_online'] = $isOnline;
    $members[] = $row;
}

header('Content-Type: application/json');
echo json_encode($members);
