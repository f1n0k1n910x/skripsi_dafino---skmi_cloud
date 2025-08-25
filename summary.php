<?php
include 'config.php';
include 'functions.php';

// Pastikan session_start() ada di sini, di awal file
session_start();

// Define $currentUserRole from session
// Ini penting untuk menghindari "Undefined variable" dan untuk logika sidebar
$currentUserRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest'; // Default to 'guest' if not set

// Check if user is logged in (assuming session_start() is handled elsewhere or at the top of config.php)
// If not, you might want to redirect or handle unauthorized access.
// For this summary page, we'll assume it's accessible if config.php and functions.php are included.
// Tambahan: Jika halaman summary hanya untuk user login, tambahkan cek ini:
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


// Check if it's an AJAX request
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// 1. Total Files Uploaded
$totalFiles = 0;
$stmt = $conn->prepare("SELECT COUNT(id) AS total_files FROM files");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['total_files']) {
    $totalFiles = $row['total_files'];
}
$stmt->close();

// 2. Total Folders
$totalFolders = 0;
$stmt = $conn->prepare("SELECT COUNT(id) AS total_folders FROM folders");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['total_folders']) {
    $totalFolders = $row['total_folders'];
}
$stmt->close();

// 3. Total Storage Usage
$totalStorageGB = 500; // Misalnya, total kapasitas penyimpanan 500 GB
$totalStorageBytes = $totalStorageGB * 1024 * 1024 * 1024; // Convert GB to Bytes

$usedStorageBytes = 0;
$stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM files");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['total_size']) {
    $usedStorageBytes = $row['total_size'];
}
$stmt->close();
$usedStorageGB = $usedStorageBytes / (1024 * 1024 * 1024);

$usedPercentage = ($totalStorageBytes > 0) ? ($usedStorageBytes / $totalStorageBytes) * 100 : 0;
if ($usedPercentage > 100) $usedPercentage = 100;
$freeStorageGB = $totalStorageGB - $usedStorageGB;

// Check if storage is full
// Pastikan fungsi isStorageFull() tersedia di functions.php
$isStorageFull = isStorageFull($conn, $totalStorageBytes);


// 4. Last Uploaded Files (e.g., last 5 files)
$lastUploadedFiles = [];
$stmt = $conn->prepare("SELECT file_name, file_size, uploaded_at FROM files ORDER BY uploaded_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $lastUploadedFiles[] = $row;
}
$stmt->close();

// 5. File Type Distribution
$fileTypeDistribution = [];
$stmt = $conn->prepare("SELECT file_type, COUNT(id) as count FROM files GROUP BY file_type");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileTypeDistribution[$row['file_type']] = $row['count'];
}
$stmt->close();

// Prepare data for Chart.js (File Type Distribution)
$fileTypeLabels = array_map('strtoupper', array_keys($fileTypeDistribution));
$fileTypeData = array_values($fileTypeDistribution);

// 6. Uploads per Month (Januari - Desember for current year)
$uploadsPerMonth = [];
$currentYear = date('Y');
// Initialize all 12 months with 0 for the current year
for ($m = 1; $m <= 12; $m++) {
    $monthKey = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $uploadsPerMonth[$monthKey] = 0;
}

$stmt = $conn->prepare("SELECT DATE_FORMAT(uploaded_at, '%Y-%m') as month, COUNT(id) as count FROM files WHERE YEAR(uploaded_at) = ? GROUP BY month ORDER BY month ASC");
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $uploadsPerMonth[$row['month']] = $row['count'];
}
$stmt->close();

// Ensure data is ordered correctly for the chart (Januari to Desember)
$allMonthsUploads = [];
for ($m = 1; $m <= 12; $m++) {
    $monthKey = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $allMonthsUploads[$monthKey] = $uploadsPerMonth[$monthKey] ?? 0;
}
$monthLabels = array_map(function($date) {
    return date('M', strtotime($date . '-01')); // Format to "Jan"
}, array_keys($allMonthsUploads));
$monthData = array_values($allMonthsUploads);


// 7. Storage Usage Per Month (Januari - Desember for current year)
$storageUsagePerMonth = [];
// Initialize all 12 months with 0 bytes for the current year
for ($m = 1; $m <= 12; $m++) {
    $month = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $storageUsagePerMonth[$month] = 0;
}

// Query to get total file size uploaded per month for the current year
$stmt = $conn->prepare("SELECT DATE_FORMAT(uploaded_at, '%Y-%m') as month, SUM(file_size) as total_size FROM files WHERE YEAR(uploaded_at) = ? GROUP BY month ORDER BY month ASC");
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $storageUsagePerMonth[$row['month']] = $row['total_size'];
}
$stmt->close();

// Ensure data is ordered correctly for the chart (Januari to Desember)
$allStorageMonths = [];
for ($m = 1; $m <= 12; $m++) {
    $monthKey = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $allStorageMonths[$monthKey] = $storageUsagePerMonth[$monthKey] ?? 0;
}
$storageMonthLabels = array_map(function($date) {
    return date('M', strtotime($date . '-01')); // Format to "Jan"
}, array_keys($allStorageMonths));
$storageMonthData = array_values($allStorageMonths);


// If it's an AJAX request, return JSON data
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'totalFiles' => $totalFiles,
        'totalFolders' => $totalFolders,
        'usedStorageBytes' => $usedStorageBytes,
        'totalStorageGB' => $totalStorageGB,
        'usedPercentage' => round($usedPercentage, 2),
        'lastUploadedFiles' => $lastUploadedFiles,
        'fileTypeLabels' => $fileTypeLabels,
        'fileTypeData' => $fileTypeData,
        'monthLabels' => $monthLabels,
        'monthData' => $monthData,
        'storageMonthLabels' => $storageMonthLabels,
        'storageMonthData' => $storageMonthData,
        'formattedUsedStorage' => formatBytes($usedStorageBytes),
        'formattedTotalStorage' => formatBytes($totalStorageBytes), // Use totalStorageBytes here
        'isStorageFull' => $isStorageFull // Pass storage full status
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary - SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/summary.css"> <!-- Link ke file CSS eksternal -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="sidebar mobile-hidden">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                <li><a href="control_center.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'control_center.php') ? 'active' : ''; ?>"><i class="fas fa-cogs"></i> <span data-lang-key="controlCenter">Control Center</span></a></li>
            <?php endif; ?>
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'user' || $currentUserRole === 'member'): ?>
                <li><a href="index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"><i class="fas fa-folder"></i> <span data-lang-key="myDrive">My Drive</span></a></li>
                <li><a href="priority_files.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'priority_files.php') ? 'active' : ''; ?>"><i class="fas fa-star"></i> <span data-lang-key="priorityFile">Priority File</span></a></li>
                <li><a href="recycle_bin.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'recycle_bin.php') ? 'active' : ''; ?>"><i class="fas fa-trash"></i> <span data-lang-key="recycleBin">Recycle Bin</span></a></li>
            <?php endif; ?>
            <li><a href="summary.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'summary.php') ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span data-lang-key="summary">Summary</span></a></li>
            <li><a href="members.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'members.php') ? 'active' : ''; ?>"><i class="fas fa-users"></i> <span data-lang-key="members">Members</span></a></li>
            <li><a href="profile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>"><i class="fas fa-user"></i> <span data-lang-key="profile">Profile</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span data-lang-key="logout">Logout</span></a></li>
        </ul>
        <div class="storage-info">
            <h4 data-lang-key="storage">Storage</h4>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo round($usedPercentage, 2); ?>%;">
                    <span class="progress-bar-text"><?php echo round($usedPercentage, 2); ?>%</span>
                </div>
            </div>
            <p class="storage-text" id="storageText"><?php echo formatBytes($usedStorageBytes); ?> of <?php echo formatBytes($totalStorageBytes); ?> used</p>
            <?php if ($isStorageFull): ?>
                <p class="storage-text storage-full-message" style="color: var(--error-color); font-weight: bold;" data-lang-key="storageFull">Storage Full!</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="summary-title" data-lang-key="activityLogTitle">Activity Log</h1>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3 data-lang-key="totalFilesUploaded">Total Files Uploaded</h3>
                <p class="count" id="totalFilesCount"><?php echo $totalFiles; ?></p>
            </div>

            <div class="card green">
                <h3 data-lang-key="totalFolders">Total Folders</h3>
                <p class="count" id="totalFoldersCount"><?php echo $totalFolders; ?></p>
            </div>

            <div class="card orange">
                <h3 data-lang-key="totalStorageUsed">Total Storage Used</h3>
                <p class="count" id="totalStorageUsed"><?php echo formatBytes($usedStorageBytes); ?></p>
                <p class="storage-text-card" id="totalStorageCapacity" data-lang-key="ofUsedText">of 500 GB used</p>
            </div>

             <div class="card red">
                <h3 data-lang-key="storageUsedPercentage">Storage Used (%)</h3>
                <p class="count" id="storageUsedPercentage"><?php echo round($usedPercentage, 2); ?>%</p>
            </div>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
            <div class="chart-container">
                <h3 data-lang-key="uploadsPerMonth">Uploads Per Month</h3>
                <canvas id="uploadsPerMonthChart"></canvas>
            </div>

            <div class="chart-container">
                <h3 data-lang-key="fileTypeDistribution">File Type Distribution</h3>
                <canvas id="fileTypeChart"></canvas>
            </div>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="last-uploaded-files">
                <h3 data-lang-key="lastUploadedFiles">Last Uploaded Files</h3>
                <ul id="lastUploadedFilesList">
                    <?php if (empty($lastUploadedFiles)): ?>
                        <li data-lang-key="noRecentUploads">No recent uploads.</li>
                    <?php else: ?>
                        <?php foreach ($lastUploadedFiles as $file): ?>
                            <li>
                                <div class="file-info">
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon"></i>
                                    <span class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></span>
                                </div>
                                <span class="file-meta"><?php echo formatBytes($file['file_size']); ?> - <?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="chart-container">
                <h3 data-lang-key="storageUsagePerMonth">Storage Usage Per Month</h3>
                <canvas id="storageUsageChart"></canvas>
            </div>
        </div>
    </div>

    <div class="overlay" id="mobileOverlay"></div>

    <script>
        // Data PHP yang dibutuhkan oleh JavaScript
        const initialChartData = {
            monthLabels: <?php echo json_encode($monthLabels); ?>,
            monthData: <?php echo json_encode($monthData); ?>,
            fileTypeLabels: <?php echo json_encode($fileTypeLabels); ?>,
            fileTypeData: <?php echo json_encode($fileTypeData); ?>,
            storageMonthLabels: <?php echo json_encode($storageMonthLabels); ?>,
            storageMonthData: <?php echo json_encode($storageMonthData); ?>
        };
    </script>
    <script src="js/summary.js"></script> <!-- Link ke file JavaScript eksternal -->
</body>
</html>
