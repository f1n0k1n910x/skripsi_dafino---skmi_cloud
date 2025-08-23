<?php
function getStorageStats(mysqli $conn): array {
    // Define total storage (adjust as needed)
    $totalStorageGB = 500;
    $totalStorageBytes = $totalStorageGB * 1024 * 1024 * 1024;

    // Calculate used storage from DB
    $stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM files");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $usedStorageBytes = $row['total_size'] ?? 0;
    $usedStorageGB = $usedStorageBytes / (1024 * 1024 * 1024);
    $usedPercentage = ($totalStorageBytes > 0) ? ($usedStorageBytes / $totalStorageBytes) * 100 : 0;
    if ($usedPercentage > 100) $usedPercentage = 100;

    $freeStorageGB = $totalStorageGB - $usedStorageGB;

    // Reuse helper function if available
    $isStorageFull = ($usedPercentage >= 100);

    return [
        'totalStorageBytes' => $totalStorageBytes,
        'usedStorageBytes'  => $usedStorageBytes,
        'usedPercentage'    => $usedPercentage,
        'isStorageFull'     => $isStorageFull,
        'totalStorageGB'    => $totalStorageGB,
        'usedStorageGB'     => $usedStorageGB,
        'freeStorageGB'     => $freeStorageGB,
    ];
}
