<?php
$usedStorageBytes  = $usedStorageBytes  ?? 0;
$totalStorageBytes = $totalStorageBytes ?? 1; // jangan 0 biar tidak divide by zero
$isStorageFull     = $isStorageFull     ?? false;
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$sizeFilter = isset($_GET['size']) ? $_GET['size'] : 'none'; // 'asc', 'desc', 'none'
$fileTypeFilter = isset($_GET['file_type']) ? $_GET['file_type'] : 'all'; // 'all', 'document', 'music', etc.

$folders = [];

$folders = fetchFolders(
    $conn,
    $currentFolderId,
    $searchQuery,
    $filterExtensions,
    '',
    $sizeFilter
);

$files = fetchFiles(
    $conn,
    $currentFolderId,
    $searchQuery,
    $filterExtensions,
    $sizeFilter
);

$breadcrumbs = [];

$folderInfo = buildFolderBreadcrumbs($conn, $currentFolderId);
$breadcrumbs = $folderInfo['breadcrumbs'];
$currentFolderId = $folderInfo['currentFolderId'];
$currentFolderName = $folderInfo['currentFolderName'];
$currentFolderPath = $folderInfo['currentFolderPath'];

?>

<div class="sidebar mobile-hidden">
    <div class="sidebar-header">
        <img src="<?php echo $baseUrl; ?>/img/logo.png" alt="Logo">
    </div>
    <ul class="sidebar-menu">
        <li><a href="<?php echo $baseV2Url; ?>" class="active"><i class="fas fa-folder"></i> My Drive</a></li>
        <li><a href="priority_files.php"><i class="fas fa-star"></i> Priority File</a></li> <!-- NEW: Priority File Link -->
        <li><a href="views/pages/recycle-bin.php"><i class="fas fa-trash"></i> Recycle Bin</a></li> <!-- NEW: Recycle Bin Link -->
        <li><a href="summary.php"><i class="fas fa-chart-line"></i> Summary</a></li>
        <li><a href="members.php"><i class="fas fa-users"></i> Members</a></li> <!-- NEW: Members Link -->
        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    <div class="storage-info">
        <h4>Storage</h4>
        <div class="progress-bar-container">
            <div class="progress-bar" style="width: <?php echo round($usedPercentage, 2); ?>%;">
                <span class="progress-bar-text"><?php echo round($usedPercentage, 2); ?>%</span>
            </div>
        </div>
        <p class="storage-text"><?php echo formatBytes($usedStorageBytes); ?> of <?php echo formatBytes($totalStorageBytes); ?> used</p>
        <?php if ($isStorageFull): ?>
            <p class="storage-text" style="color: var(--metro-error); font-weight: bold;">Storage Full! Cannot upload more files.</p>
        <?php endif; ?>
    </div>
</div>