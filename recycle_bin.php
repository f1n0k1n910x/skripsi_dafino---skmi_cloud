<?php
include 'config.php';
include 'functions.php'; // Include functions.php file

session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Define $currentUserRole from session
$currentUserRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest'; // Default to 'guest' or 'user' if not set

// Current folder ID, default to NULL for root (not directly used in recycle bin, but kept for consistency)
$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : NULL;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
    
$userId = $_SESSION['user_id'];

// Get search query
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Get sorting parameters
$sizeFilter = isset($_GET['size']) ? $_GET['size'] : 'none'; // 'none', 'largest', 'smallest'
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'asc'; // 'asc', 'desc' (for alphabetical if size filter is 'none')
$fileTypeFilter = isset($_GET['file_type']) ? $_GET['file_type'] : 'all'; // 'all', 'document', 'music', etc.

// Define file categories for filtering (same as index.php)
$docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
$musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
$videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
$imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'];
$cadExt = ['dwg', 'dxf', 'dgn', 'iges', 'igs', 'step', 'stp', 'stl', '3ds', 'obj', 'sldprt', 'sldasm', 'ipt', 'iam', 'catpart', 'catproduct', 'prt', 'asm', 'fcstd', 'skp', 'x_t', 'x_b'];

// Restricted file types for admin/moderator only
$codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
$archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
$instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
$ptpExt = ['torrent','nzb','ed2k','part','!ut'];

// Map filter types to actual extensions
$filterExtensions = [];
switch ($fileTypeFilter) {
    case 'document': $filterExtensions = $docExt; break;
    case 'music': $filterExtensions = $musicExt; break;
    case 'video': $filterExtensions = $videoExt; break;
    case 'image': $filterExtensions = $imageExt; break;
    case 'cad': $filterExtensions = $cadExt; break;
    // Only include restricted types if user is admin or moderator
    case 'code': 
        if ($currentUserRole === 'admin' || $currentUserRole === 'moderator') {
            $filterExtensions = $codeExt;
        }
        break;
    case 'archive': 
        if ($currentUserRole === 'admin' || $currentUserRole === 'moderator') {
            $filterExtensions = $archiveExt;
        }
        break;
    case 'installation': 
        if ($currentUserRole === 'admin' || $currentUserRole === 'moderator') {
            $filterExtensions = $instExt;
        }
        break;
    case 'p2p': 
        if ($currentUserRole === 'admin' || $currentUserRole === 'moderator') {
            $filterExtensions = $ptpExt;
        }
        break;
    case 'all': default: $filterExtensions = []; break; // No specific filter
}

// Fetch deleted files and folders for the current user
$deletedItems = [];

// SQL for deleted files
$sqlFiles = "SELECT id, file_name, file_path, file_size, file_type, deleted_at FROM deleted_files WHERE user_id = ?";
$paramsFiles = [$userId];
$typesFiles = "i";

if (!empty($searchQuery)) {
    $sqlFiles .= " AND file_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $paramsFiles[] = $searchTerm;
    $typesFiles .= "s";
}

if (!empty($filterExtensions)) {
    $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
    $sqlFiles .= " AND file_type IN ($placeholders)";
    foreach ($filterExtensions as $ext) {
        $paramsFiles[] = $ext;
        $typesFiles .= "s";
    }
}

// Apply sorting based on size or alphabetical
if ($sizeFilter === 'largest') {
    $sqlFiles .= " ORDER BY file_size DESC";
} elseif ($sizeFilter === 'smallest') {
    $sqlFiles .= " ORDER BY file_size ASC";
} else {
    // Apply alphabetical sorting if no size filter or 'none'
    if ($sortOrder === 'asc') {
        $sqlFiles .= " ORDER BY file_name ASC";
    } else {
        $sqlFiles .= " ORDER BY file_name DESC";
    }
}

$stmtFiles = $conn->prepare($sqlFiles);
$stmtFiles->bind_param($typesFiles, ...$paramsFiles);
$stmtFiles->execute();
$resultFiles = $stmtFiles->get_result();
while ($row = $resultFiles->fetch_assoc()) {
    $row['item_type'] = 'file';
    $deletedItems[] = $row;
}
$stmtFiles->close();

// SQL for deleted folders (folders don't have size, so only alphabetical sorting applies)
$sqlFolders = "SELECT id, folder_name, deleted_at FROM deleted_folders WHERE user_id = ?";
$paramsFolders = [$userId];
$typesFolders = "i";

if (!empty($searchQuery)) {
    $sqlFolders .= " AND folder_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $paramsFolders[] = $searchTerm;
    $typesFolders .= "s";
}

// Apply alphabetical sorting for folders
if ($sortOrder === 'asc') {
    $sqlFolders .= " ORDER BY folder_name ASC";
} else {
    $sqlFolders .= " ORDER BY folder_name DESC";
}

$stmtFolders = $conn->prepare($sqlFolders);
$stmtFolders->bind_param($typesFolders, ...$paramsFolders);
$stmtFolders->execute();
$resultFolders = $stmtFolders->get_result();
while ($row = $resultFolders->fetch_assoc()) {
    $row['item_type'] = 'folder';
    $deletedItems[] = $row;
}
$stmtFolders->close();

// Final sorting of all deleted items (files and folders)
if ($sizeFilter === 'largest') {
    usort($deletedItems, function($a, $b) {
        $sizeA = $a['item_type'] === 'file' ? $a['file_size'] : 0; // Folders treated as 0 size for sorting
        $sizeB = $b['item_type'] === 'file' ? $b['file_size'] : 0;
        return $sizeB - $sizeA;
    });
} elseif ($sizeFilter === 'smallest') {
    usort($deletedItems, function($a, $b) {
        $sizeA = $a['item_type'] === 'file' ? $a['file_size'] : 0;
        $sizeB = $b['item_type'] === 'file' ? $b['file_size'] : 0;
        return $sizeA - $sizeB;
    });
} else {
    usort($deletedItems, function($a, $b) use ($sortOrder) {
        $nameA = $a['item_type'] === 'file' ? $a['file_name'] : $a['folder_name'];
        $nameB = $b['item_type'] === 'file' ? $b['file_name'] : $b['folder_name'];
        return ($sortOrder === 'asc') ? strcasecmp($nameA, $nameB) : strcasecmp($nameB, $nameA);
    });
}


// Simulated data for storage (Replace with actual data from your database/system)
$totalStorageGB = 500; 
$totalStorageBytes = $totalStorageGB * 1024 * 1024 * 1024; // Convert GB to Bytes

$usedStorageBytes = 0;
// Calculate used storage from files table (sum of file_size)
$stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM files");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['total_size']) {
    $usedStorageBytes = $row['total_size'];
}
$stmt->close(); // Close the statement

$usedStorageGB = $usedStorageBytes / (1024 * 1024 * 1024); // Convert bytes to GB

$usedPercentage = ($totalStorageBytes > 0) ? ($usedStorageBytes / $totalStorageBytes) * 100 : 0;
if ($usedPercentage > 100) $usedPercentage = 100; // Cap at 100%
$freeStorageGB = $totalStorageGB - $usedStorageGB;

// Check if storage is full
$isStorageFull = isStorageFull($conn, $totalStorageBytes);

// Define file categories for thumbnail preview (same as index.php)
// These are already defined above, no need to redefine.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin - SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/internal.css"> <!-- Import CSS -->
    <link rel="stylesheet" href="css/recycle_bin.css"> <!-- New CSS file -->
</head>
<body>
    <div class="sidebar mobile-hidden">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                <li><a href="control_center.php"><i class="fas fa-cogs"></i> <span data-lang-key="controlCenter">Control Center</span></a></li>
            <?php endif; ?>
            <?php if (in_array($currentUserRole, ['admin', 'moderator', 'user', 'member'])): ?>
                <li><a href="index.php"><i class="fas fa-folder"></i> <span data-lang-key="myDrive">My Drive</span></a></li>
                <li><a href="priority_files.php"><i class="fas fa-star"></i> <span data-lang-key="priorityFile">Priority File</span></a></li>
                <li><a href="recycle_bin.php" class="active"><i class="fas fa-trash"></i> <span data-lang-key="recycleBin">Recycle Bin</span></a></li>
            <?php endif; ?>
            <li><a href="summary.php"><i class="fas fa-chart-line"></i> <span data-lang-key="summary">Summary</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span data-lang-key="members">Members</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> <span data-lang-key="profile">Profile</span></a></li>
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
                <p class="storage-text storage-full-message" style="color: var(--error-color); font-weight: bold;" data-lang-key="storageFull">Storage Full! Cannot upload more files.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="my-drive-title" data-lang-key="recycleBinTitle">Recycle Bin</h1>
            <!-- Removed search-bar-desktop and profile-user desktop-only -->
        </div>

        <!-- Mobile Search Bar (moved below header for smaller screens) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInputMobile" placeholder="Search files in trash..." value="<?php echo htmlspecialchars($searchQuery); ?>" data-lang-key="searchTrashPlaceholder">
        </div>

        <div class="toolbar">
            <div class="toolbar-left">
                <button id="restoreSelectedBtn" style="background-color: var(--success-color);"><i class="fas fa-undo"></i> <span data-lang-key="restoreSelected">Restore Selected</span></button>
                <button id="deleteForeverSelectedBtn" style="background-color: var(--error-color);"><i class="fas fa-times-circle"></i> <span data-lang-key="deleteForeverSelected">Delete Forever Selected</span></button>
                <button id="emptyRecycleBinBtn" style="background-color: var(--error-color);"><i class="fas fa-trash-alt"></i> <span data-lang-key="emptyRecycleBin">Empty Recycle Bin</span></button>
            </div>
            <div class="toolbar-right">
                <!-- File Type Filter Button -->
                <div class="dropdown-container file-type-filter-dropdown-container">
                    <button id="fileTypeFilterBtn" class="filter-button"><i class="fas fa-filter"></i></button>
                    <div class="dropdown-content file-type-filter-dropdown-content">
                        <a href="#" data-filter="all" data-lang-key="allFiles">All Files</a>
                        <a href="#" data-filter="document" data-lang-key="documents">Documents</a>
                        <a href="#" data-filter="image" data-lang-key="images">Images</a>
                        <a href="#" data-filter="music" data-lang-key="music">Music</a>
                        <a href="#" data-filter="video" data-lang-key="videos">Videos</a>
                        <a href="#" data-filter="cad" data-lang-key="cadFiles">CAD Files</a>
                        <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                            <a href="#" data-filter="code" data-lang-key="codeFiles">Code Files</a>
                            <a href="#" data-filter="archive" data-lang-key="archives">Archives</a>
                            <a href="#" data-filter="installation" data-lang-key="installationFiles">Installation Files</a>
                            <a href="#" data-filter="p2p" data-lang-key="p2pFiles">Peer-to-Peer Files</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Size Filter Button (Replaces Release Date Filter) -->
                <div class="dropdown-container size-filter-dropdown-container">
                    <button id="sizeFilterBtn" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                    <div class="dropdown-content size-filter-dropdown-content" style="position: absolute; top: 100%; right: 3%; transform: translateX(-50%); margin-top: 8px; padding:5px 0; border-radius:4px;">
                        <a href="#" data-filter="largest" data-lang-key="largestSize">Largest First</a>
                        <a href="#" data-filter="smallest" data-lang-key="smallestSize">Smallest First</a>
                        <a href="#" data-filter="none" data-lang-key="noSizeFilter">No Size Filter</a>
                    </div>
                </div>

                <!-- View Toggle Buttons -->
                <div class="view-toggle">
                    <button id="listViewBtn" class="active"><i class="fas fa-list"></i></button>
                    <button id="gridViewBtn"><i class="fas fa-th-large"></i></button>
                </div>
            </div>
        </div>

        <!-- NEW: Filter buttons moved here for mobile/tablet -->
        <div class="toolbar-filter-buttons">
            <!-- File Type Filter Button -->
            <div class="dropdown-container file-type-filter-dropdown-container">
                <button id="fileTypeFilterBtnHeader" class="filter-button"><i class="fas fa-filter"></i></button>
                <div class="dropdown-content file-type-filter-dropdown-content">
                    <a href="#" data-filter="all" data-lang-key="allFiles">All Files</a>
                    <a href="#" data-filter="document" data-lang-key="documents">Documents</a>
                    <a href="#" data-filter="image" data-lang-key="images">Images</a>
                    <a href="#" data-filter="music" data-lang-key="music">Music</a>
                    <a href="#" data-filter="video" data-lang-key="videos">Videos</a>
                    <a href="#" data-filter="cad" data-lang-key="cadFiles">CAD Files</a>
                    <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                        <a href="#" data-filter="code" data-lang-key="codeFiles">Code Files</a>
                        <a href="#" data-filter="archive" data-lang-key="archives">Archives</a>
                        <a href="#" data-filter="installation" data-lang-key="installationFiles">Installation Files</a>
                        <a href="#" data-filter="p2p" data-lang-key="p2pFiles">Peer-to-Peer Files</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Size Filter Button (Replaces Release Date Filter) -->
            <div class="dropdown-container size-filter-dropdown-container">
                <button id="sizeFilterBtnHeader" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                <div class="dropdown-content size-filter-dropdown-content">
                    <a href="#" data-filter="largest" data-lang-key="largestSize">Largest First</a>
                    <a href="#" data-filter="smallest" data-lang-key="smallestSize">Smallest First</a>
                    <a href="#" data-filter="none" data-lang-key="noSizeFilter">No Size Filter</a>
                </div>
            </div>

            <!-- View Toggle Buttons -->
            <div class="view-toggle">
                <button id="listViewBtnHeader" class="active"><i class="fas fa-list"></i></button>
                <button id="gridViewBtnHeader"><i class="fas fa-th-large"></i></button>
            </div>
        </div>

        <div class="breadcrumbs">
            <span data-lang-key="recycleBinBreadcrumb"><i class="fas fa-trash"></i> Recycle Bin</span>
            <?php if (!empty($searchQuery)): ?>
                <span data-lang-key="breadcrumbSeparator">/</span> <span data-lang-key="searchResultsFor">Search results for "<?php echo htmlspecialchars($searchQuery); ?>"</span>
            <?php endif; ?>
        </div>

        <div class="file-list-container">
            <div id="fileListView" class="file-view">
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllCheckbox"></th>
                                <th data-lang-key="name">Name</th>
                                <th data-lang-key="type">Type</th>
                                <th data-lang-key="size">Size</th>
                                <th data-lang-key="deletedAt">Deleted At</th>
                                <th data-lang-key="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deletedItems) && !empty($searchQuery)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;" data-lang-key="noSearchResults">No deleted files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</td>
                                </tr>
                            <?php elseif (empty($deletedItems) && empty($searchQuery)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;" data-lang-key="recycleBinEmpty">Recycle Bin is empty.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($deletedItems as $item): ?>
                                <?php
                                    $itemId = $item['id'];
                                    $itemType = $item['item_type'];
                                    $itemName = $itemType === 'file' ? $item['file_name'] : $item['folder_name'];
                                    $itemSize = $itemType === 'file' ? formatBytes($item['file_size']) : 'Folder'; // Folder size not stored in deleted_folders
                                    $itemDeletedAt = date('Y-m-d H:i', strtotime($item['deleted_at']));
                                    $fileExt = $itemType === 'file' ? strtolower($item['file_type']) : 'folder';
                                    $iconClass = $itemType === 'file' ? getFontAwesomeIconClass($itemName) : 'fa-folder';
                                    $colorClass = $itemType === 'file' ? getFileColorClassPhp($itemName) : 'folder';
                                ?>
                                <tr class="file-item" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>" data-name="<?php echo htmlspecialchars($itemName); ?>" data-file-type="<?php echo $fileExt; ?>" tabindex="0">
                                    <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>"></td>
                                    <td class="file-name-cell" style="display:table-cell; vertical-align:middle;">
                                        <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                        <span><?php echo htmlspecialchars($itemName); ?></span>
                                    </td>
                                    <td data-lang-key="<?php echo $itemType; ?>Type"><?php echo ucfirst($itemType); ?></td>
                                    <td><?php echo $itemSize; ?></td>
                                    <td><?php echo $itemDeletedAt; ?></td>
                                    <td>
                                        <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="fileGridView" class="file-view hidden">
                <div class="file-grid">
                    <?php if (empty($deletedItems) && !empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;" data-lang-key="noSearchResults">No deleted files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</div>
                    <?php elseif (empty($deletedItems) && empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;" data-lang-key="recycleBinEmpty">Recycle Bin is empty.</div>
                    <?php endif; ?>

                    <?php foreach ($deletedItems as $item): ?>
                        <?php
                            $itemId = $item['id'];
                            $itemType = $item['item_type'];
                            $itemName = $itemType === 'file' ? $item['file_name'] : $item['folder_name'];
                            $itemSize = $itemType === 'file' ? formatBytes($item['file_size']) : 'Folder';
                            $itemDeletedAt = date('Y-m-d H:i', strtotime($item['deleted_at']));
                            $fileExt = $itemType === 'file' ? strtolower($item['file_type']) : 'folder';
                            $iconClass = $itemType === 'file' ? getFontAwesomeIconClass($itemName) : 'fa-folder';
                            $colorClass = $itemType === 'file' ? getFileColorClassPhp($itemName) : 'folder';
                        ?>
                        <div class="grid-item file-item" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>" data-name="<?php echo htmlspecialchars($itemName); ?>" data-file-type="<?php echo $fileExt; ?>" tabindex="0">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>">
                            <div class="grid-thumbnail">
                                <?php if ($itemType === 'file'): ?>
                                    <?php
                                    $filePath = htmlspecialchars($item['file_path']); // Note: physical file might be gone
                                    // In recycle bin, we don't expect files to exist physically, so no direct image/video preview
                                    // Just show the icon and type label
                                    ?>
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                <?php else: // Folder ?>
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                <?php endif; ?>
                                <span class="file-type-label" data-lang-key="<?php echo $fileExt; ?>Type"><?php echo ucfirst($fileExt); ?></span>
                            </div>
                            <span class="file-name"><?php echo htmlspecialchars($itemName); ?></span>
                            <span class="file-size"><?php echo $itemSize; ?></span>
                            <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>

    <!-- Custom context menu (shared UI, populated by JS) -->
    <div id="context-menu" class="context-menu" hidden>
        <ul>
            <li data-action="restore"><i class="fas fa-undo"></i> <span data-lang-key="restore">Restore</span></li>
            <li class="separator"></li>
            <li data-action="delete-forever"><i class="fas fa-times-circle"></i> <span data-lang-key="deleteForever">Delete Forever</span></li>
        </ul>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="mobileOverlay"></div>

    <script src="js/recycle_bin.js"></script> <!-- New JS file -->
</body>
</html>
