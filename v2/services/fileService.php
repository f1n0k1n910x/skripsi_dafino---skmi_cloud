<?php
function getFiles($conn, $folderId) {
    $stmt = $conn->prepare("SELECT id, file_name, file_path FROM files WHERE folder_id " . 
        ($folderId ? "= ?" : "IS NULL"));
    if ($folderId) {
        $stmt->bind_param("i", $folderId);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function saveFile($conn, $folderId, $fileName, $filePath) {
    $stmt = $conn->prepare("INSERT INTO files (folder_id, file_name, file_path) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $folderId, $fileName, $filePath);
    return $stmt->execute();
}

function deleteFile($conn, $fileId) {
    $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
    $stmt->bind_param("i", $fileId);
    return $stmt->execute();
}
