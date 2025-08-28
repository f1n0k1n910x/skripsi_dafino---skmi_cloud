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

// Current folder ID, default to NULL for root
$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : NULL;
$currentFolderName = 'Root';
$currentFolderPath = ''; // To build the full path for uploads and display

// Define the base upload directory
$baseUploadDir = 'uploads/'; // Adjust to your upload directory

// Fetch current folder details for breadcrumbs
$breadcrumbs = [];
if ($currentFolderId) {
    $path = [];
    $tempFolderId = $currentFolderId;
    while ($tempFolderId !== NULL) {
        $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM folders WHERE id = ?");
        $stmt->bind_param("i", $tempFolderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $folder = $result->fetch_assoc();
        if ($folder) {
            array_unshift($path, ['id' => $folder['id'], 'name' => $folder['folder_name']]);
            $tempFolderId = $folder['parent_id'];
        } else {
            // Folder not found, reset to root
            $currentFolderId = NULL;
            $currentFolderName = 'Root';
            $currentFolderPath = '';
            $breadcrumbs = [];
            break;
        }
    }
    $breadcrumbs = $path;
    if (!empty($path)) {
        $currentFolderName = end($path)['name'];
    }
    
    // Build current folder path for uploads
    $currentFolderPathArray = array_map(function($f) { return $f['name']; }, $path);
    $currentFolderPath = implode('/', $currentFolderPathArray);
}

// Get search query
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Get sorting parameters
$sizeFilter = isset($_GET['size']) ? $_GET['size'] : 'none'; // 'asc', 'desc', 'none'
$fileTypeFilter = isset($_GET['file_type']) ? $_GET['file_type'] : 'all'; // 'all', 'document', 'music', etc.

// Define file categories for filtering and thumbnail preview
$docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
$musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
$videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
$codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
$archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
$instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
$ptpExt = ['torrent','nzb','ed2k','part','!ut'];
$imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'];
$cadExt = ['dwg', 'dxf', 'dgn', 'iges', 'igs', 'step', 'stp', 'stl', '3ds', 'obj', 'sldprt', 'sldasm', 'ipt', 'iam', 'catpart', 'catproduct', 'prt', 'asm', 'fcstd', 'skp', 'x_t', 'x_b'];

// Define restricted file types
$restrictedFileTypes = array_merge($codeExt, $instExt, $ptpExt);

// Map filter types to actual extensions
$filterExtensions = [];
switch ($fileTypeFilter) {
    case 'document': $filterExtensions = $docExt; break;
    case 'music': $filterExtensions = $musicExt; break;
    case 'video': $filterExtensions = $videoExt; break;
    case 'code': $filterExtensions = $codeExt; break;
    case 'archive': $filterExtensions = $archiveExt; break;
    case 'installation': $filterExtensions = $instExt; break;
    case 'p2p': $filterExtensions = $ptpExt; break;
    case 'image': $filterExtensions = $imageExt; break;
    case 'cad': $filterExtensions = $cadExt; break;
    case 'all': default: $filterExtensions = []; break; // No specific filter
}

// --- NEW: Handle restricted file types for non-admin/moderator users ---
if ($currentUserRole !== 'admin' && $currentUserRole !== 'moderator') {
    // If a non-admin/moderator tries to filter by a restricted type, reset to 'all'
    if (in_array($fileTypeFilter, ['code', 'installation', 'p2p'])) {
        $fileTypeFilter = 'all';
        $filterExtensions = []; // Clear filter extensions
        // Optionally, add a notification here if this were a full page reload
    }
    // When displaying files, ensure restricted types are not shown
    // This will be handled in the SQL query below
}
// --- END NEW ---

// Function to check if a folder or its subfolders contain files of a specific type
function folderContainsFilteredFiles($conn, $folderId, $filterExtensions, $baseUploadDir, $currentUserRole, $restrictedFileTypes) {
    if (empty($filterExtensions)) {
        // If no specific file type filter, check if folder contains any files visible to the user
        $sql = "SELECT COUNT(id) FROM files WHERE folder_id = ?";
        $params = [$folderId];
        $types = "i";

        if ($currentUserRole !== 'admin' && $currentUserRole !== 'moderator') {
            // Exclude restricted file types for non-admin/moderator
            $placeholders = implode(',', array_fill(0, count($restrictedFileTypes), '?'));
            $sql .= " AND file_type NOT IN ($placeholders)";
            foreach ($restrictedFileTypes as $ext) {
                $params[] = $ext;
                $types .= "s";
            }
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        if ($row[0] > 0) {
            $stmt->close();
            return true;
        }
        $stmt->close();
    } else {
        // Specific file type filter is active
        $sql = "SELECT COUNT(id) FROM files WHERE folder_id = ?";
        $params = [$folderId];
        $types = "i";

        $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
        $sql .= " AND file_type IN ($placeholders)";
        foreach ($filterExtensions as $ext) {
            $params[] = $ext;
            $types .= "s";
        }

        // If current filter extensions contain restricted types AND user is not admin/moderator,
        // then this specific filter should not return true for restricted types.
        // However, the main query already handles this by setting $filterExtensions to empty if restricted.
        // So, this part only needs to ensure that if a restricted type is in $filterExtensions,
        // it's only counted if the user is admin/moderator.
        // This is implicitly handled by the $filterExtensions being empty for non-admins if they try to filter restricted types.
        // If $filterExtensions is NOT empty, it means the user is admin/moderator OR the filter is not restricted.
        // So, no additional WHERE clause needed here for $currentUserRole.

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        if ($row[0] > 0) {
            $stmt->close();
            return true;
        }
        $stmt->close();
    }

    // Recursively check subfolders
    $subfolders = [];
    $stmt = $conn->prepare("SELECT id FROM folders WHERE parent_id = ?");
    $stmt->bind_param("i", $folderId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subfolders[] = $row['id'];
    }
    $stmt->close();

    foreach ($subfolders as $subfolderId) {
        if (folderContainsFilteredFiles($conn, $subfolderId, $filterExtensions, $baseUploadDir, $currentUserRole, $restrictedFileTypes)) {
            return true;
        }
    }

    return false;
}


// Fetch folders in current directory
$folders = [];
$sqlFolders = "SELECT id, folder_name, created_at, updated_at FROM folders WHERE parent_id <=> ?";
$folderParams = [$currentFolderId];
$folderTypes = "i";

if (!empty($searchQuery)) {
    $sqlFolders .= " AND folder_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $folderParams[] = $searchTerm;
    $folderTypes .= "s";
}

// Apply sorting for folders (alphabetical by default, size sorting handled after fetching)
$sqlFolders .= " ORDER BY folder_name ASC";

$stmt = $conn->prepare($sqlFolders);
$stmt->bind_param($folderTypes, ...$folderParams);
$stmt->execute();
$result = $stmt->get_result();
$tempFolders = [];
while ($row = $result->fetch_assoc()) {
    // Apply file type filter to folders, considering user role
    if (empty($filterExtensions) || folderContainsFilteredFiles($conn, $row['id'], $filterExtensions, $baseUploadDir, $currentUserRole, $restrictedFileTypes)) {
        $tempFolders[] = $row;
    }
}
$stmt->close();

// Calculate folder sizes for sorting
foreach ($tempFolders as &$folder) {
    $folderPath = $baseUploadDir . getFolderPath($conn, $folder['id']);
    $folder['calculated_size'] = getFolderSize($folderPath);
}
unset($folder); // Unset reference

// Apply size sorting for folders
if ($sizeFilter === 'asc') {
    usort($tempFolders, function($a, $b) {
        return $a['calculated_size'] <=> $b['calculated_size'];
    });
} elseif ($sizeFilter === 'desc') {
    usort($tempFolders, function($a, $b) {
        return $b['calculated_size'] <=> $a['calculated_size'];
    });
}
$folders = $tempFolders;


// Fetch files in current directory
$files = [];
$sqlFiles = "SELECT id, file_name, file_path, file_size, file_type, uploaded_at FROM files WHERE folder_id <=> ?";
$params = [$currentFolderId];
$types = "i";

if (!empty($searchQuery)) {
    $sqlFiles .= " AND file_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $types .= "s";
}

// --- NEW: Restrict file types based on user role ---
if ($currentUserRole !== 'admin' && $currentUserRole !== 'moderator') {
    // If user is not admin/moderator, exclude restricted file types
    if (!empty($restrictedFileTypes)) {
        $placeholders = implode(',', array_fill(0, count($restrictedFileTypes), '?'));
        $sqlFiles .= " AND file_type NOT IN ($placeholders)";
        foreach ($restrictedFileTypes as $ext) {
            $params[] = $ext;
            $types .= "s";
        }
    }
}

if (!empty($filterExtensions)) {
    $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
    $sqlFiles .= " AND file_type IN ($placeholders)";
    foreach ($filterExtensions as $ext) {
        $params[] = $ext;
        $types .= "s";
    }
}

// Apply size sorting for files
if ($sizeFilter === 'asc') {
    $sqlFiles .= " ORDER BY file_size ASC";
} elseif ($sizeFilter === 'desc') {
    $sqlFiles .= " ORDER BY file_size DESC";
} else {
    // Default alphabetical sorting if no size filter
    $sqlFiles .= " ORDER BY file_name ASC";
}

$stmt = $conn->prepare($sqlFiles);
// Dynamically bind parameters
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $files[] = $row;
}
$stmt->close();


// Simulated data for storage (Replace with actual data from your database/system)
// Changed total storage to 700 GB
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

// Define file categories for thumbnail preview (already defined above for filtering)
// $docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
// $musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
// $videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
// $codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
// $archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
// $instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
// $ptpExt = ['torrent','nzb','ed2k','part','!ut'];
// $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff']; // Added image extensions
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/internal.css"> <!-- Import CSS -->
    <link rel="stylesheet" href="css/index.css"> <!-- Custom CSS for this page -->
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
            <li><a href="index.php" class="active"><i class="fas fa-folder"></i> <span data-lang-key="myDrive">My Drive</span></a></li>
            <li><a href="priority_files.php"><i class="fas fa-star"></i> <span data-lang-key="priorityFile">Priority File</span></a></li>
            <li><a href="recycle_bin.php"><i class="fas fa-trash"></i> <span data-lang-key="recycleBin">Recycle Bin</span></a></li>
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
            <h1 class="my-drive-title" data-lang-key="myDriveTitle">My Drive</h1>
            <div class="search-bar search-bar-desktop">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search files..." value="<?php echo htmlspecialchars($searchQuery); ?>" data-lang-placeholder="searchFiles">
            </div>
        </div>

        <!-- Mobile Search Bar (moved below header for smaller screens) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInputMobile" placeholder="Search files..." value="<?php echo htmlspecialchars($searchQuery); ?>" data-lang-placeholder="searchFiles">
        </div>

        <div class="toolbar">
            <div class="dropdown-container size-filter-dropdown-container" >
                <button id="sizeFilterBtn" class="filter-button">Action</button>
                <div class="dropdown-content size-filter-dropdown-content" style="position: absolute; top: 100%; left: 3%; transform: translateX(-50%); margin-top: 8px; padding:5px 0; border-radius:4px;">
                    <a id="uploadFileBtn" href="#" data-size="desc"><i class="fas fa-upload"></i> Upload File</a>
                    <a id="createFolderBtn" href="#" data-size="asc" ><i class="fas fa-folder-plus"> </i> Create Folder</a>
                    <a id="deleteSelectedBtn" href="#" data-size="none"><i class="fas fa-trash-alt"></i> Delete Selected</a>
                </div>
            </div>
            <div class="toolbar-right">
                <!-- Archive Button with Dropdown (Hidden on mobile/tablet portrait) -->
                <div class="dropdown-container archive-dropdown-container">
                    <button id="archiveSelectedBtn" class="filter-button"><i class="fas fa-archive"></i></button>
                    <div class="dropdown-content archive-dropdown-content">
                        <a href="#" data-format="zip">.zip (PHP Native)</a>
                    </div>
                </div>

                <!-- File Type Filter Button (Hidden on mobile/tablet portrait) -->
                <div class="dropdown-container file-type-filter-dropdown-container">
                    <button id="fileTypeFilterBtn" class="filter-button"><i class="fas fa-filter"></i></button>
                    <div class="dropdown-content file-type-filter-dropdown-content">
                        <a href="#" data-filter="all" data-lang-key="allFiles">All Files</a>
                        <a href="#" data-filter="document" data-lang-key="documents">Documents</a>
                        <a href="#" data-filter="image" data-lang-key="images">Images</a>
                        <a href="#" data-filter="music" data-lang-key="music">Music</a>
                        <a href="#" data-filter="video" data-lang-key="videos">Videos</a>
                        <a href="#" data-filter="archive" data-lang-key="archives">Archives</a>
                        <a href="#" data-filter="cad" data-lang-key="cadFiles">CAD Files</a>
                        <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                            <a href="#" data-filter="code" data-lang-key="codeFiles">Code Files</a>
                            <a href="#" data-filter="installation" data-lang-key="installationFiles">Installation Files</a>
                            <a href="#" data-filter="p2p" data-lang-key="p2pFiles">Peer-to-Peer Files</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Size Filter Button (Replaces Release Date Filter) -->
                <div class="dropdown-container size-filter-dropdown-container"  style="position: relative;">
                    <button id="sizeFilterBtn" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                    <div class="dropdown-content size-filter-dropdown-content" style="position: absolute; top: 100%; right: 3%; transform: translateX(-50%); margin-top: 8px; padding:5px 0; border-radius:4px;">
                        <a href="#" data-size="desc" data-lang-key="sizeLargeToSmall">Size (Large to Small)</a>
                        <a href="#" data-size="asc" data-lang-key="sizeSmallToLarge">Size (Small to Large)</a>
                        <a href="#" data-size="none" data-lang-key="defaultAlphabetical">Default (Alphabetical)</a>
                    </div>
                </div>
                <!-- View Toggle Buttons (Hidden on mobile/tablet portrait) -->
                <div class="view-toggle">
                    <button id="listViewBtn" class="active"><i class="fas fa-list"></i></button>
                    <button id="gridViewBtn"><i class="fas fa-th-large"></i></button>
                </div>
            </div>
        </div>

        <!-- NEW: Filter buttons moved here for mobile/tablet -->
        <div class="toolbar-filter-buttons">
            <!-- Archive Button with Dropdown -->
            <div class="dropdown-container archive-dropdown-container">
                <button id="archiveSelectedBtnHeader" class="filter-button" style="background-color: var(--warning-color);"><i class="fas fa-archive"></i></button>
                <div class="dropdown-content archive-dropdown-content">
                    <a href="#" data-format="zip">.zip (PHP Native)</a>
                </div>
            </div>

            <!-- File Type Filter Button -->
            <div class="dropdown-container file-type-filter-dropdown-container">
                <button id="fileTypeFilterBtnHeader" class="filter-button"><i class="fas fa-filter"></i></button>
                <div class="dropdown-content file-type-filter-dropdown-content">
                    <a href="#" data-filter="all" data-lang-key="allFiles">All Files</a>
                    <a href="#" data-filter="document" data-lang-key="documents">Documents</a>
                    <a href="#" data-filter="image" data-lang-key="images">Images</a>
                    <a href="#" data-filter="music" data-lang-key="music">Music</a>
                    <a href="#" data-filter="video" data-lang-key="videos">Videos</a>
                    <a href="#" data-filter="archive" data-lang-key="archives">Archives</a>
                    <a href="#" data-filter="cad" data-lang-key="cadFiles">CAD Files</a>
                    <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                        <a href="#" data-filter="code" data-lang-key="codeFiles">Code Files</a>
                        <a href="#" data-filter="installation" data-lang-key="installationFiles">Installation Files</a>
                        <a href="#" data-filter="p2p" data-lang-key="p2pFiles">Peer-to-Peer Files</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Size Filter Button (Replaces Release Date Filter) -->
            <div class="dropdown-container size-filter-dropdown-container">
                <button id="sizeFilterBtnHeader" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                <div class="dropdown-content size-filter-dropdown-content">
                    <a href="#" data-size="desc" data-lang-key="sizeLargeToSmall">Size (Large to Small)</a>
                    <a href="#" data-size="asc" data-lang-key="sizeSmallToLarge">Size (Small to Large)</a>
                    <a href="#" data-size="none" data-lang-key="defaultAlphabetical">Default (Alphabetical)</a>
                </div>
            </div>

            <!-- View Toggle Buttons -->
            <div class="view-toggle">
                <button id="listViewBtnHeader" class="active"><i class="fas fa-list"></i></button>
                <button id="gridViewBtnHeader"><i class="fas fa-th-large"></i></button>
            </div>
        </div>

        <div class="breadcrumbs">
            <a href="index.php"><i class="fas fa-home"></i> <span data-lang-key="root">Root</span></a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span>/</span> <a href="index.php?folder=<?php echo $crumb['id']; ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
            <?php endforeach; ?>
            <?php if (!empty($searchQuery)): ?>
                <span>/</span> <span data-lang-key="searchResultsFor">Search results for</span> "<?php echo htmlspecialchars($searchQuery); ?>"</span>
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
                            <th data-lang-key="lastModified">Last Modified</th>
                            <th data-lang-key="actions">Actions</th> <!-- Added Actions column header -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($folders) && empty($files) && !empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;" data-lang-key="noFilesOrFoldersFoundSearch">No files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</td>
                            </tr>
                        <?php elseif (empty($folders) && empty($files) && empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;" data-lang-key="noFilesOrFoldersFoundDirectory">No files or folders found in this directory.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($folders as $folder): ?>
                            <tr class="file-item" data-id="<?php echo $folder['id']; ?>" data-type="folder" data-name="<?php echo htmlspecialchars($folder['folder_name']); ?>" data-path="<?php echo htmlspecialchars($baseUploadDir . getFolderPath($conn, $folder['id'])); ?>" tabindex="0">
                                <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $folder['id']; ?>" data-type="folder"></td>
                                <td class="file-name-cell">
                                    <i class="fas fa-folder file-icon folder"></i>
                                    <a href="index.php?folder=<?php echo $folder['id']; ?>" class="file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($folder['folder_name']); ?></a>
                                </td>
                                <td data-lang-key="folder">Folder</td>
                                <td>
                                    <?php
                                        // NEW: Calculate and display folder size
                                        // Get the full physical path of the folder
                                        echo formatBytes($folder['calculated_size']);
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        // Display modification date if available, otherwise creation date
                                        $displayDate = $folder['updated_at'] ?? $folder['created_at'];
                                        echo date('Y-m-d H:i', strtotime($displayDate));
                                    ?>
                                </td>
                                <td>
                                    <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($files as $file): ?>
                            <tr class="file-item" data-id="<?php echo $file['id']; ?>" data-type="file" data-name="<?php echo htmlspecialchars($file['file_name']); ?>" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-file-type="<?php echo strtolower($file['file_type']); ?>" tabindex="0">
                                <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $file['id']; ?>" data-type="file"></td>
                                <td class="file-name-cell">
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                    <a href="view.php?file_id=<?php echo $file['id']; ?>" class="file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($file['file_name']); ?></a>
                                    </td>
                                <td><?php echo strtoupper($file['file_type']); ?></td>
                                <td><?php echo formatBytes($file['file_size']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></td>
                                <td>
                                    <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="fileGridView" class="file-view hidden">
                <div class="file-grid">
                    <?php if (empty($folders) && empty($files) && !empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;" data-lang-key="noFilesOrFoldersFoundSearch">No files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</div>
                    <?php elseif (empty($folders) && empty($files) && empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;" data-lang-key="noFilesOrFoldersFoundDirectory">No files or folders found in this directory.</div>
                    <?php endif; ?>

                    <?php foreach ($folders as $folder): ?>
                        <div class="grid-item file-item" data-id="<?php echo $folder['id']; ?>" data-type="folder" data-name="<?php echo htmlspecialchars($folder['folder_name']); ?>" data-path="<?php echo htmlspecialchars($baseUploadDir . getFolderPath($conn, $folder['id'])); ?>" tabindex="0">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                            <div class="grid-thumbnail">
                                <i class="fas fa-folder file-icon folder"></i>
                                <span class="file-type-label" data-lang-key="folder">Folder</span>
                            </div>
                            <a href="index.php?folder=<?php echo $folder['id']; ?>" class="file-name file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($folder['folder_name']); ?></a>
                            <span class="file-size">
                                <?php
                                    // NEW: Calculate and display folder size
                                    // Get the full physical path of the folder
                                    echo formatBytes($folder['calculated_size']);
                                ?>
                            </span>
                            <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($files as $file): ?>
                        <div class="grid-item file-item" data-id="<?php echo $file['id']; ?>" data-type="file" data-name="<?php echo htmlspecialchars($file['file_name']); ?>" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-file-type="<?php echo strtolower($file['file_type']); ?>" tabindex="0">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $file['id']; ?>" data-type="file">
                            <div class="grid-thumbnail">
                                <?php
                                $fileExt = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                $filePath = htmlspecialchars($file['file_path']);
                                $fileName = htmlspecialchars($file['file_name']);

                                if (in_array($fileExt, $imageExt)):
                                ?>
                                    <img src="<?php echo $filePath; ?>" alt="<?php echo $fileName; ?>">
                                <?php elseif (in_array($fileExt, $docExt)):
                                    $content = @file_get_contents($file['file_path'], false, null, 0, 500);
                                    if ($content !== false) {
                                        echo '<pre>' . htmlspecialchars(substr(strip_tags($content), 0, 200)) . '...</pre>';
                                    } else {
                                        echo '<i class="fas ' . getFontAwesomeIconClass($file['file_name']) . ' file-icon ' . getFileColorClassPhp($file['file_name']) . '"></i>';
                                    }
                                ?>
                                <?php elseif (in_array($fileExt, $musicExt)): ?>
                                    <audio controls style='width:100%; height: auto;'><source src='<?php echo $filePath; ?>' type='audio/<?php echo $fileExt; ?>'></audio>
                                <?php elseif (in_array($fileExt, $videoExt)): ?>
                                    <video controls style='width:100%; height:100%;'><source src='<?php echo $filePath; ?>' type='video/<?php echo $fileExt; ?>'></video>
                                <?php elseif (in_array($fileExt, $codeExt)):
                                    $code = @file_get_contents($file['file_path'], false, null, 0, 500);
                                    if ($code !== false) {
                                        echo '<pre>' . htmlspecialchars(substr($code, 0, 200)) . '...</pre>';
                                    } else {
                                        echo '<i class="fas ' . getFontAwesomeIconClass($file['file_name']) . ' file-icon ' . getFileColorClassPhp($file['file_name']) . '"></i>';
                                    }
                                ?>
                                <?php elseif (in_array($fileExt, $cadExt)): // NEW: CAD Thumbnail Preview ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                    <span style="font-size: 0.9em; margin-top: 5px;" data-lang-key="cadFile">CAD File</span>
                                <?php elseif (in_array($fileExt, $archiveExt) || in_array($fileExt, $instExt) || in_array($fileExt, $ptpExt)): ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                    <span style="font-size: 0.9em; margin-top: 5px;"><?php echo strtoupper($fileExt); ?> <span data-lang-key="file">File</span></span>
                                <?php else: ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                <?php endif; ?>
                                <span class="file-type-label"><?php echo strtoupper($fileExt); ?></span>
                            </div>
                            <a href="view.php?file_id=<?php echo $file['id']; ?>" class="file-name file-link-clickable" onclick="event.stopPropagation();"><?php echo $fileName; ?></a>
                            <span class="file-size"><?php echo formatBytes($file['file_size']); ?></span>
                            <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="uploadFileModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="uploadFile">Upload File</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="current_folder_id" value="<?php echo htmlspecialchars($currentFolderId); ?>">
                <input type="hidden" name="current_folder_path" value="<?php echo htmlspecialchars($currentFolderPath); ?>">
                <label for="fileToUpload" data-lang-key="selectFiles">Select File(s):</label>
                <input type="file" name="fileToUpload[]" id="fileToUpload" multiple required <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                <button type="submit" id="startUploadBtn" <?php echo $isStorageFull ? 'disabled' : ''; ?> data-lang-key="upload">Upload</button>
            </form>
        </div>
    </div>

    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="createNewFolder">Create New Folder</h2>
            <form id="createFolderForm" action="v2/services/api/folderCreate.php" method="POST">
                <input type="hidden" name="parent_folder_id" value="<?php echo htmlspecialchars($currentFolderId); ?>">
                <input type="hidden" name="parent_folder_path" value="<?php echo htmlspecialchars($currentFolderPath); ?>">
                <label for="folderName" data-lang-key="folderName">Folder Name:</label>
                <input type="text" name="folderName" id="folderName" required <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                <button type="submit" <?php echo $isStorageFull ? 'disabled' : ''; ?> data-lang-key="createFolder">Create Folder</button>
            </form>
        </div>
    </div>

    <div id="renameModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="rename">Rename <span id="renameItemType"></span></h2>
            <form id="renameForm" action="rename.php" method="POST">
                <input type="hidden" name="itemId" id="renameItemId">
                <input type="hidden" name="itemType" id="renameItemActualType">
                <label for="newName" data-lang-key="newName">New Name:</label>
                <input type="text" name="newName" id="newName" required>
                <button type="submit" data-lang-key="renameBtn">Rename</button>
            </form>
        </div>
    </div>

    <div id="uploadPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="back-button" id="uploadPreviewBackBtn"><i class="fas fa-chevron-left"></i></button>
                <h2 data-lang-key="fileUpload">File Upload</h2>
                <span class="close-button" id="closeUploadPreviewBtn">&times;</span>
            </div>
            <div id="uploadPreviewList">
                </div>
            </div>
    </div>

    <!-- Modal untuk Share Link -->
    <div id="shareLinkModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="shareLink">Share Link</h2>
            <p data-lang-key="shareLinkDescription">Here is the shareable link for your file:</p>
            <div class="share-link-container">
                <input type="text" id="shortLinkOutput" value="" readonly>
                <button id="copyShortLinkBtn"><i class="fas fa-copy"></i> <span data-lang-key="copy">Copy</span></button>
            </div>
            <p class="small-text" data-lang-key="anyoneWithLink">Anyone with this link can view the file.</p>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>

    <!-- Custom context menu (shared UI, populated by JS) -->
    <div id="context-menu" class="context-menu" hidden>
        <ul>
            <li data-action="rename"><i class="fas fa-pen"></i> <span data-lang-key="rename">Rename</span></li>
            <li data-action="download" class="hidden"><i class="fas fa-download"></i> <span data-lang-key="download">Download</span></li>
            <li data-action="share" class="hidden"><i class="fas fa-share-alt"></i> <span data-lang-key="shareLink">Share Link</span></li>
            <li data-action="extract" class="hidden"><i class="fas fa-file-archive"></i> <span data-lang-key="extractZip">Extract ZIP</span></li>
            <li data-action="toggle-star"><i class="fas fa-star"></i> <span data-lang-key="pinToPriority">Pin to Priority</span></li>
            <li class="separator"></li>
            <li data-action="delete"><i class="fas fa-trash"></i> <span data-lang-key="delete">Delete</span></li>
        </ul>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="mobileOverlay"></div>

    <script>
        // PHP variables passed to JS
        const phpVars = {
            currentUserRole: <?php echo json_encode($currentUserRole); ?>,
            restrictedFileTypes: <?php echo json_encode($restrictedFileTypes); ?>,
            currentFolderId: <?php echo json_encode($currentFolderId); ?>,
            searchQuery: <?php echo json_encode($searchQuery); ?>,
            sizeFilter: <?php echo json_encode($sizeFilter); ?>,
            fileTypeFilter: <?php echo json_encode($fileTypeFilter); ?>,
            usedStorageBytes: <?php echo json_encode($usedStorageBytes); ?>,
            totalStorageBytes: <?php echo json_encode($totalStorageBytes); ?>,
            isStorageFull: <?php echo json_encode($isStorageFull); ?>,
            docExt: <?php echo json_encode($docExt); ?>,
            musicExt: <?php echo json_encode($musicExt); ?>,
            videoExt: <?php echo json_encode($videoExt); ?>,
            codeExt: <?php echo json_encode($codeExt); ?>,
            archiveExt: <?php echo json_encode($archiveExt); ?>,
            instExt: <?php echo json_encode($instExt); ?>,
            ptpExt: <?php echo json_encode($ptpExt); ?>,
            imageExt: <?php echo json_encode($imageExt); ?>,
            cadExt: <?php echo json_encode($cadExt); ?>
        };
    </script>
    <?php include 'v2/assets/main-script.php'; ?>
</body>
</html>
