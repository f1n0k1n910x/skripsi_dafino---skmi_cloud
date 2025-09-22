<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../../config.php';

require '../../helpers/utils.php';

require '../../services/authService.php';
require '../../services/folderService.php';
require '../../services/fileService.php';

session_start();
checkAuth(); // Redirects if not logged in

$userId = $_SESSION['user_id'];

$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

$releaseFilter = isset($_GET['release']) ? $_GET['release'] : 'newest'; // Default to newest for trash
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'asc'; // 'asc', 'desc'
$fileTypeFilter = isset($_GET['file_type']) ? $_GET['file_type'] : 'all'; // 'all', 'document', 'music', etc.


$currentFolderPath = ''; // To build the full path for uploads and display
$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : NULL;

$deletedItems = [];

// Get deleted files
$deletedFiles = getDeletedFiles(
    $conn,
    $userId,
    $_GET['search'] ?? '',
    $_GET['extensions'] ?? [],
    $_GET['releaseFilter'] ?? 'all',
    $_GET['sortOrder'] ?? 'asc'
);

// Get deleted folders
$deletedFolders = getDeletedFolders(
    $conn,
    $userId,
    $_GET['search'] ?? '',
    $_GET['releaseFilter'] ?? 'all',
    $_GET['sortOrder'] ?? 'asc'
);

// Merge both into $deletedItems
$deletedItems = array_merge($deletedFiles, $deletedFolders);


?>

<?php include '../partials/recycle-bin-header.php'; ?>

<?php include '../../views/partials/sidebar.php'; ?>


    <div class="main-content">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="my-drive-title">Recycle Bin</h1>
            <!-- Removed search-bar-desktop and profile-user desktop-only -->
        </div>

        <!-- Mobile Search Bar (moved below header for smaller screens) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInputMobile" placeholder="Search files in trash..." value="<?php echo htmlspecialchars($searchQuery); ?>">
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
                        <a href="#" data-filter="all">All Files</a>
                        <a href="#" data-filter="document">Documents</a>
                        <a href="#" data-filter="image">Images</a>
                        <a href="#" data-filter="music">Music</a>
                        <a href="#" data-filter="video">Videos</a>
                        <a href="#" data-filter="code">Code Files</a>
                        <a href="#" data-filter="archive">Archives</a>
                        <a href="#" data-filter="installation">Installation Files</a>
                        <a href="#" data-filter="p2p">Peer-to-Peer Files</a>
                        <a href="#" data-filter="cad">CAD Files</a>
                    </div>
                </div>

                <!-- Release Date Filter Button -->
                <div class="dropdown-container release-filter-dropdown-container">
                    <button id="releaseFilterBtn" class="filter-button"><i class="fas fa-calendar-alt"></i></button>
                    <div class="dropdown-content release-filter-dropdown-content">
                        <a href="#" data-filter="newest">Newest Deleted</a>
                        <a href="#" data-filter="oldest">Oldest Deleted</a>
                        <a href="#" data-filter="all">All Dates</a>
                    </div>
                </div>

                <!-- Sort Order Filter Button -->
                <div class="dropdown-container sort-order-dropdown-container">
                    <button id="sortOrderBtn" class="filter-button"><i class="fas fa-sort-alpha-down"></i></button>
                    <div class="dropdown-content sort-order-dropdown-content">
                        <a href="#" data-sort="asc">A-Z</a>
                        <a href="#" data-sort="desc">Z-A</a>
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
                    <a href="#" data-filter="all">All Files</a>
                    <a href="#" data-filter="document">Documents</a>
                    <a href="#" data-filter="image">Images</a>
                    <a href="#" data-filter="music">Music</a>
                    <a href="#" data-filter="video">Videos</a>
                    <a href="#" data-filter="code">Code Files</a>
                    <a href="#" data-filter="archive">Archives</a>
                    <a href="#" data-filter="installation">Installation Files</a>
                    <a href="#" data-filter="p2p">Peer-to-Peer Files</a>
                    <a href="#" data-filter="cad">CAD Files</a>
                </div>
            </div>

            <!-- Release Date Filter Button -->
            <div class="dropdown-container release-filter-dropdown-container">
                <button id="releaseFilterBtnHeader" class="filter-button"><i class="fas fa-calendar-alt"></i></button>
                <div class="dropdown-content release-filter-dropdown-content">
                    <a href="#" data-filter="newest">Newest Deleted</a>
                    <a href="#" data-filter="oldest">Oldest Deleted</a>
                    <a href="#" data-filter="all">All Dates</a>
                </div>
            </div>

            <!-- Sort Order Filter Button -->
            <div class="dropdown-container sort-order-dropdown-container">
                <button id="sortOrderBtnHeader" class="filter-button"><i class="fas fa-sort-alpha-down"></i></button>
                <div class="dropdown-content sort-order-dropdown-content">
                    <a href="#" data-sort="asc">A-Z</a>
                    <a href="#" data-sort="desc">Z-A</a>
                </div>
            </div>

            <!-- View Toggle Buttons -->
            <div class="view-toggle">
                <button id="listViewBtnHeader" class="active"><i class="fas fa-list"></i></button>
                <button id="gridViewBtnHeader"><i class="fas fa-th-large"></i></button>
            </div>
        </div>

        <div class="breadcrumbs">
            <span><i class="fas fa-trash"></i> Recycle Bin</span>
            <?php if (!empty($searchQuery)): ?>
                <span>/</span> <span>Search results for "<?php echo htmlspecialchars($searchQuery); ?>"</span>
            <?php endif; ?>
        </div>

        <div class="file-list-container">
            <div id="fileListView" class="file-view">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCheckbox"></th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Deleted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deletedItems) && !empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No deleted files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</td>
                            </tr>
                        <?php elseif (empty($deletedItems) && empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">Recycle Bin is empty.</td>
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
                                <td class="file-name-cell">
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                    <span><?php echo htmlspecialchars($itemName); ?></span>
                                </td>
                                <td><?php echo ucfirst($itemType); ?></td>
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

            <div id="fileGridView" class="file-view hidden">
                <div class="file-grid">
                    <?php if (empty($deletedItems) && !empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">No deleted files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</div>
                    <?php elseif (empty($deletedItems) && empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">Recycle Bin is empty.</div>
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
                                    if (in_array($fileExt, $imageExt) && file_exists($filePath)):
                                    ?>
                                        <img src="<?php echo $filePath; ?>" alt="<?php echo $itemName; ?>">
                                    <?php elseif (in_array($fileExt, $docExt)):
                                        // Attempt to read content if file still exists
                                        $content = @file_get_contents($item['file_path'], false, null, 0, 500);
                                        if ($content !== false) {
                                            echo '<pre>' . htmlspecialchars(substr(strip_tags($content), 0, 200)) . '...</pre>';
                                        } else {
                                            echo '<i class="fas ' . $iconClass . ' file-icon ' . $colorClass . '"></i>';
                                        }
                                    ?>
                                    <?php elseif (in_array($fileExt, $musicExt) && file_exists($filePath)): ?>
                                        <audio controls style='width:100%; height: auto;'><source src='<?php echo $filePath; ?>' type='audio/<?php echo $fileExt; ?>'></audio>
                                    <?php elseif (in_array($fileExt, $videoExt) && file_exists($filePath)): ?>
                                        <video controls style='width:100%; height:100%;'><source src='<?php echo $filePath; ?>' type='video/<?php echo $fileExt; ?>'></video>
                                    <?php elseif (in_array($fileExt, $codeExt)):
                                        $code = @file_get_contents($item['file_path'], false, null, 0, 500);
                                        if ($code !== false) {
                                            echo '<pre>' . htmlspecialchars(substr($code, 0, 200)) . '...</pre>';
                                        } else {
                                            echo '<i class="fas ' . $iconClass . ' file-icon ' . $colorClass . '"></i>';
                                        }
                                    ?>
                                    <?php else: ?>
                                        <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                    <?php endif; ?>
                                <?php else: // Folder ?>
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                <?php endif; ?>
                                <span class="file-type-label"><?php echo ucfirst($fileExt); ?></span>
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
            <li data-action="restore"><i class="fas fa-undo"></i> Restore</li>
            <li class="separator"></li>
            <li data-action="delete-forever"><i class="fas fa-times-circle"></i> Delete Forever</li>
        </ul>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="mobileOverlay"></div>

    <script>
        const BASE_URL = '<?php echo getBaseUrl(); ?>';

        document.addEventListener('DOMContentLoaded', function() {
            const restoreSelectedBtn = document.getElementById('restoreSelectedBtn');
            const deleteForeverSelectedBtn = document.getElementById('deleteForeverSelectedBtn');
            const emptyRecycleBinBtn = document.getElementById('emptyRecycleBinBtn');
            
            // Dropdown elements (main toolbar)
            const releaseFilterDropdownContainer = document.querySelector('.toolbar .release-filter-dropdown-container');
            const releaseFilterBtn = document.getElementById('releaseFilterBtn');
            const releaseFilterDropdownContent = document.querySelector('.toolbar .release-filter-dropdown-content');

            const sortOrderDropdownContainer = document.querySelector('.toolbar .sort-order-dropdown-container');
            const sortOrderBtn = document.getElementById('sortOrderBtn');
            const sortOrderDropdownContent = document.querySelector('.toolbar .sort-order-dropdown-content');

            const fileTypeFilterDropdownContainer = document.querySelector('.toolbar .file-type-filter-dropdown-container');
            const fileTypeFilterBtn = document.getElementById('fileTypeFilterBtn');
            const fileTypeFilterDropdownContent = document.querySelector('.toolbar .file-type-filter-dropdown-content');

            // Dropdown elements (header)
            const fileTypeFilterBtnHeader = document.getElementById('fileTypeFilterBtnHeader');
            const releaseFilterBtnHeader = document.getElementById('releaseFilterBtnHeader');
            const sortOrderBtnHeader = document.getElementById('sortOrderBtnHeader');
            const listViewBtnHeader = document.getElementById('listViewBtnHeader');
            const gridViewBtnHeader = document.getElementById('gridViewBtnHeader');


            const listViewBtn = document.getElementById('listViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const fileListView = document.getElementById('fileListView');
            const fileGridView = document.getElementById('fileGridView');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const searchInput = document.getElementById('searchInput'); // Desktop search
            const searchInputMobile = document.getElementById('searchInputMobile'); // Mobile search
            const customNotification = document.getElementById('customNotification');

            // Context Menu elements
            const contextMenu = document.getElementById('context-menu');
            const contextRestore = document.querySelector('#context-menu [data-action="restore"]');
            const contextDeleteForever = document.querySelector('#context-menu [data-action="delete-forever"]');

            // Mobile sidebar elements
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');

            // Current state variables for AJAX filtering/sorting
            let currentSearchQuery = <?php echo json_encode($searchQuery); ?>;
            let currentReleaseFilter = <?php echo json_encode($releaseFilter); ?>;
            let currentSortOrder = <?php echo json_encode($sortOrder); ?>;
            let currentFileTypeFilter = <?php echo json_encode($fileTypeFilter); ?>;

            /*** Util helpers ****/
            function debounce(fn, ms=150){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
            function closestFileItem(el){ return el && el.closest('.file-item'); }

            /*** Device detection & body class toggling ***/
            function setDeviceClass() {
                const ua = navigator.userAgent || '';
                const isIPad = /iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                const w = window.innerWidth;
                document.body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop'); // Clear all
                if (w <= 767) {
                    document.body.classList.add('mobile');
                } else if (w >= 768 && w <= 1024) {
                    if (window.matchMedia("(orientation: portrait)").matches) {
                        document.body.classList.add('tablet-portrait');
                    } else {
                        document.body.classList.add('tablet-landscape');
                    }
                } else {
                    document.body.classList.add('desktop');
                }
            }
            window.addEventListener('resize', debounce(setDeviceClass, 150));
            window.addEventListener('orientationchange', setDeviceClass); // Listen for orientation changes
            setDeviceClass(); // init

            // Function to get file icon class based on extension (for JS side, if needed for dynamic elements)
            function getFileIconClass(fileName) {
                const extension = fileName.split('.').pop().toLowerCase();
                switch (extension) {
                    case 'pdf': return 'fa-file-pdf';
                    case 'doc':
                    case 'docx': return 'fa-file-word';
                    case 'xls':
                    case 'xlsx': return 'fa-file-excel';
                    case 'ppt':
                    case 'pptx': return 'fa-file-powerpoint';
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                    case 'bmp':
                    case 'webp': return 'fa-file-image';
                    case 'zip':
                    case 'rar':
                    case '7z': return 'fa-file-archive';
                    case 'txt':
                    case 'log':
                    case 'md': return 'fa-file-alt';
                    case 'exe':
                    case 'apk': return 'fa-box';
                    case 'mp3':
                    case 'wav':
                    case 'flac': return 'fa-file-audio';
                    case 'mp4':
                    case 'avi':
                    case 'mkv': return 'fa-file-video';
                    case 'html':
                    case 'htm': return 'fa-file-code';
                    case 'css': return 'fa-file-code';
                    case 'js': return 'fa-file-code';
                    case 'php': return 'fa-file-code';
                    case 'py': return 'fa-file-code';
                    case 'json': return 'fa-file-code';
                    case 'sql': return 'fa-database';
                    case 'svg': return 'fa-file-image';
                    case 'sh':
                    case 'bat': return 'fa-file-code';
                    case 'ini':
                    case 'yml':
                    case 'yaml': return 'fa-file-code';
                    case 'java': return 'fa-java';
                    case 'c':
                    case 'cpp': return 'fa-file-code';
                    case 'dwg':
                    case 'dxf':
                    case 'dgn':
                    case 'iges':
                    case 'igs':
                    case 'step':
                    case 'stp':
                    case 'stl':
                    case '3ds':
                    case 'obj':
                    case 'sldprt':
                    case 'sldasm':
                    case 'ipt':
                    case 'iam':
                    case 'catpart':
                    case 'catproduct':
                    case 'prt':
                    case 'asm':
                    case 'fcstd':
                    case 'skp':
                    case 'x_t':
                    case 'x_b': return 'fa-cube';
                    default: return 'fa-file';
                }
            }

            // Function to get file color class based on extension (for JS side, if needed for dynamic elements)
            function getFileColorClass(fileName) {
                const extension = fileName.split('.').pop().toLowerCase();
                const colorClasses = {
                    'pdf': 'file-color-pdf', 'doc': 'file-color-doc', 'docx': 'file-color-doc',
                    'xls': 'file-color-xls', 'xlsx': 'file-color-xls', 'ppt': 'file-color-ppt',
                    'pptx': 'file-color-ppt', 'txt': 'file-color-txt', 'rtf': 'file-color-txt',
                    'md': 'file-color-txt', 'csv': 'file-color-csv', 'odt': 'file-color-doc',
                    'odp': 'file-color-ppt', 'log': 'file-color-txt', 'tex': 'file-color-txt',
                    'jpg': 'file-color-image', 'jpeg': 'file-color-image', 'png': 'file-color-image',
                    'gif': 'file-color-image', 'bmp': 'file-color-image', 'webp': 'file-color-image',
                    'svg': 'file-color-image', 'tiff': 'file-color-image',
                    'mp3': 'file-color-audio', 'wav': 'file-color-audio', 'ogg': 'file-color-audio',
                    'flac': 'file-color-audio', 'aac': 'file-color-audio', 'm4a': 'file-color-audio',
                    'alac': 'file-color-audio', 'wma': 'file-color-audio', 'opus': 'file-color-audio',
                    'amr': 'file-color-audio', 'mid': 'file-color-audio',
                    'mp4': 'file-color-video', 'avi': 'file-color-video', 'mov': 'file-color-video',
                    'wmv': 'file-color-video', 'flv': 'file-color-video', 'webm': 'file-color-video',
                    '3gp': 'file-color-video', 'm4v': 'file-color-video', 'mpg': 'file-color-video',
                    'mpeg': 'file-color-video', 'ts': 'file-color-video', 'ogv': 'file-color-video',
                    'zip': 'file-color-archive', 'rar': 'file-color-archive', '7z': 'file-color-archive',
                    'tar': 'file-color-archive', 'gz': 'file-color-archive', 'bz2': 'file-color-archive',
                    'xz': 'file-color-archive', 'iso': 'file-color-archive', 'cab': 'file-color-archive',
                    'arj': 'file-color-archive',
                    'html': 'file-color-code', 'htm': 'file-color-code', 'css': 'file-color-code',
                    'js': 'file-color-code', 'php': 'file-color-code', 'py': 'file-color-code',
                    'java': 'file-color-code', 'json': 'file-color-code', 'xml': 'file-color-code',
                    'ts': 'file-color-code', 'tsx': 'file-color-code', 'jsx': 'file-color-code',
                    'vue': 'file-color-code', 'cpp': 'file-color-code', 'c': 'file-color-code',
                    'cs': 'file-color-code', 'rb': 'file-color-code', 'go': 'file-color-code',
                    'swift': 'file-color-code', 'sql': 'file-color-code', 'sh': 'file-color-code',
                    'bat': 'file-color-code', 'ini': 'file-color-code', 'yml': 'file-color-code',
                    'yaml': 'file-color-code', 'pl': 'file-color-code', 'r': 'file-color-code',
                    'exe': 'file-color-exe', 'msi': 'file-color-exe', 'apk': 'file-color-exe',
                    'ipa': 'file-color-exe', 'jar': 'file-color-exe', 'appimage': 'file-color-exe',
                    'dmg': 'file-color-exe', 'bin': 'file-color-exe',
                    'torrent': 'file-color-default', 'nzb': 'file-color-default', 'ed2k': 'file-color-default',
                    'part': 'file-color-default', '!ut': 'file-color-default',
                    'dwg': 'file-color-cad', 'dxf': 'file-color-cad', 'dgn': 'file-color-cad',
                    'iges': 'file-color-cad', 'igs': 'file-color-cad', 'step': 'file-color-cad',
                    'stp': 'file-color-cad', 'stl': 'file-color-cad', '3ds': 'file-color-cad',
                    'obj': 'file-color-cad', 'sldprt': 'file-color-cad', 'sldasm': 'file-color-cad',
                    'ipt': 'file-color-cad', 'iam': 'file-color-cad', 'catpart': 'file-color-cad',
                    'catproduct': 'file-color-cad', 'prt': 'file-color-cad', 'asm': 'file-color-cad',
                    'fcstd': 'file-color-cad', 'skp': 'file-color-cad', 'x_t': 'file-color-cad',
                    'x_b': 'file-color-cad',
                    'default': 'file-color-default'
                };
                return colorClasses[extension] || colorClasses['default'];
            }

            // Function to show custom notification
            function showNotification(message, type) {
                customNotification.innerHTML = message;
                customNotification.className = 'notification show ' + type;
                setTimeout(() => {
                    customNotification.classList.remove('show');
                }, 3000);
            }

            // --- View Toggle Logic ---
            function setupViewToggle(listViewBtnElement, gridViewBtnElement) {
                listViewBtnElement.addEventListener('click', () => {
                    listViewBtnElement.classList.add('active');
                    gridViewBtnElement.classList.remove('active');
                    fileListView.classList.remove('hidden');
                    fileGridView.classList.add('hidden');
                    localStorage.setItem('recycleBinView', 'list');
                });

                gridViewBtnElement.addEventListener('click', () => {
                    gridViewBtnElement.classList.add('active');
                    listViewBtnElement.classList.remove('active');
                    fileGridView.classList.remove('hidden');
                    fileListView.classList.add('hidden');
                    localStorage.setItem('recycleBinView', 'grid');
                });
            }

            setupViewToggle(listViewBtn, gridViewBtn); // For main toolbar
            setupViewToggle(listViewBtnHeader, gridViewBtnHeader); // For header toolbar

            const savedView = localStorage.getItem('recycleBinView');
            if (savedView === 'grid') {
                gridViewBtn.click();
                gridViewBtnHeader.click();
            } else {
                listViewBtn.click();
                listViewBtnHeader.click();
            }

            // --- Select All Checkbox Logic ---
            function updateSelectAllCheckboxListener() {
                const fileCheckboxes = document.querySelectorAll('.file-checkbox');
                selectAllCheckbox.checked = false;
                selectAllCheckbox.removeEventListener('change', handleSelectAllChange);
                selectAllCheckbox.addEventListener('change', handleSelectAllChange);

                fileCheckboxes.forEach(checkbox => {
                    checkbox.removeEventListener('change', handleIndividualCheckboxChange);
                    checkbox.addEventListener('change', handleIndividualCheckboxChange);
                });
            }

            function handleSelectAllChange() {
                const fileCheckboxes = document.querySelectorAll('.file-checkbox');
                fileCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            }

            function handleIndividualCheckboxChange() {
                const fileCheckboxes = document.querySelectorAll('.file-checkbox');
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    const allChecked = Array.from(fileCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
            }

            updateSelectAllCheckboxListener();

            // --- Restore Selected Files/Folders ---
            restoreSelectedBtn.addEventListener('click', async () => {
                const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                const selectedItems = Array.from(checkboxes).map(cb => {
                    return { id: cb.dataset.id, type: cb.dataset.type };
                });

                if (selectedItems.length === 0) {
                    showNotification('Please select at least one file or folder to restore!', 'error');
                    return;
                }

                if (!confirm('Are you sure you want to restore the selected items?')) {
                    return;
                }

                try {
                    const response = await fetch(`${BASE_URL}/v2/services/api/restore_items.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ items: selectedItems })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification('Failed to restore items: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while restoring items.', 'error');
                }
            });

            // --- Delete Forever Selected Files/Folders ---
            deleteForeverSelectedBtn.addEventListener('click', async () => {
                const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                const selectedItems = Array.from(checkboxes).map(cb => {
                    return { id: cb.dataset.id, type: cb.dataset.type };
                });

                if (selectedItems.length === 0) {
                    showNotification('Please select at least one file or folder to delete permanently!', 'error');
                    return;
                }

                if (!confirm('Are you sure you want to PERMANENTLY delete the selected items? This action cannot be undone!')) {
                    return;
                }

                try {
                    const response = await fetch('actions/delete_forever.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ items: selectedItems })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification('Failed to delete items permanently: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while deleting items permanently.', 'error');
                }
            });

            // --- Empty Recycle Bin ---
            emptyRecycleBinBtn.addEventListener('click', async () => {
                if (!confirm('Are you sure you want to EMPTY the entire Recycle Bin? All items will be PERMANENTLY deleted and this action cannot be undone!')) {
                    return;
                }

                try {
                    const response = await fetch('actions/empty_recycle_bin.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ user_id: <?php echo $userId; ?> })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification('Failed to empty Recycle Bin: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while emptying Recycle Bin.', 'error');
                }
            });

            // --- Search Functionality ---
            function performSearch(query) {
                currentSearchQuery = query.trim();
                updateRecycleBinContent();
            }

            searchInputMobile.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch(this.value);
                }
            });

            // --- File Type Filter ---
            function setupFileTypeFilterDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        currentFileTypeFilter = event.target.dataset.filter;
                        updateRecycleBinContent();
                    });
                });
            }

            setupFileTypeFilterDropdown('fileTypeFilterBtn', '.toolbar .file-type-filter-dropdown-content');
            setupFileTypeFilterDropdown('fileTypeFilterBtnHeader', '.toolbar-filter-buttons .file-type-filter-dropdown-content');


            // --- Release Date Filter ---
            function setupReleaseFilterDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        currentReleaseFilter = event.target.dataset.filter;
                        updateRecycleBinContent();
                    });
                });
            }

            setupReleaseFilterDropdown('releaseFilterBtn', '.toolbar .release-filter-dropdown-content');
            setupReleaseFilterDropdown('releaseFilterBtnHeader', '.toolbar-filter-buttons .release-filter-dropdown-content');


            // --- Sort Order Filter ---
            function setupSortOrderDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        currentSortOrder = event.target.dataset.sort;
                        updateRecycleBinContent();
                    });
                });
            }

            setupSortOrderDropdown('sortOrderBtn', '.toolbar .sort-order-dropdown-content');
            setupSortOrderDropdown('sortOrderBtnHeader', '.toolbar-filter-buttons .sort-order-dropdown-content');


            /*** Context menu element ***/
            function showContextMenuFor(fileEl, x, y) {
                if (!fileEl) return;
                contextMenu.dataset.targetId = fileEl.dataset.id;
                contextMenu.dataset.targetType = fileEl.dataset.type;
                contextMenu.dataset.targetName = fileEl.dataset.name;
                contextMenu.dataset.targetFileType = fileEl.dataset.fileType || '';

                // Position - keep inside viewport
                const rect = contextMenu.getBoundingClientRect();
                const menuWidth = rect.width || 200;
                const menuHeight = rect.height || 100; // Adjusted for fewer options

                let finalLeft = x;
                let finalTop = y;

                if (x + menuWidth > window.innerWidth) {
                    finalLeft = window.innerWidth - menuWidth - 10;
                }
                if (y + menuHeight > window.innerHeight) {
                    finalTop = window.innerHeight - menuHeight - 10;
                }

                contextMenu.style.left = finalLeft + 'px';
                contextMenu.style.top = finalTop + 'px';
                contextMenu.classList.add('visible');
                contextMenu.hidden = false;
                suppressOpenClickTemporarily();
            }

            function hideContextMenu(){ 
                contextMenu.classList.remove('visible'); 
                contextMenu.hidden = true; 
                contextMenu.dataset.targetId = '';
                contextMenu.dataset.targetType = '';
                contextMenu.dataset.targetName = '';
                contextMenu.dataset.targetFileType = '';
            }

            let _suppressOpenUntil = 0;
            function suppressOpenClickTemporarily(ms=350){
                _suppressOpenUntil = Date.now() + ms;
            }

            // No direct "open" action for recycle bin items, only restore/delete forever
            function handleMenuAction(action, id, type){
                switch(action){
                    case 'restore': restoreItem(id, type); break;
                    case 'delete-forever': deleteItemForever(id, type); break;
                    default: console.log('Unknown action', action);
                }
            }

            // Delegated click for item-more button
            document.addEventListener('click', function(e){
                const moreBtn = e.target.closest('.item-more');
                if (moreBtn) {
                    const file = closestFileItem(moreBtn);
                    const r = moreBtn.getBoundingClientRect();
                    showContextMenuFor(file, r.right - 5, r.bottom + 5);
                    e.stopPropagation();
                    return;
                }
                hideContextMenu();
            });

            // Desktop right-click (contextmenu)
            document.addEventListener('contextmenu', function(e){
                if (! (document.body.classList.contains('desktop') || document.body.classList.contains('tablet-landscape')) ) return;
                const file = closestFileItem(e.target);
                if (file) {
                    e.preventDefault();
                    showContextMenuFor(file, e.clientX, e.clientY);
                } else {
                    hideContextMenu();
                }
            });

            // Long-press for touch devices
            let lpTimer = null;
            let lpStart = null;
            const longPressDuration = 600;
            const longPressMoveThreshold = 10;

            document.addEventListener('pointerdown', function(e){
                if (! (document.body.classList.contains('mobile') ||
                    document.body.classList.contains('tablet-portrait') ||
                    document.body.classList.contains('device-ipad')) ) return;

                const file = closestFileItem(e.target);
                if (!file) return;
                if (e.target.classList.contains('file-checkbox')) return;

                if (e.pointerType !== 'touch') return;

                const startX = e.clientX, startY = e.clientY;
                lpStart = file;
                lpTimer = setTimeout(()=> {
                    showContextMenuFor(file, startX, startY);
                    lpTimer = null;
                    suppressOpenClickTemporarily(); 
                }, longPressDuration);

                function onMove(ev){
                    if (Math.hypot(ev.clientX - startX, ev.clientY - startY) > longPressMoveThreshold) {
                        clearLongPress();
                    }
                }
                function clearLongPress(){
                    if (lpTimer) clearTimeout(lpTimer);
                    lpTimer = null;
                    lpStart = null;
                    file.removeEventListener('pointermove', onMove);
                    file.removeEventListener('pointerup', clearLongPress);
                    file.removeEventListener('pointercancel', clearLongPress);
                }
                file.addEventListener('pointermove', onMove);
                file.addEventListener('pointerup', clearLongPress);
                file.addEventListener('pointercancel', clearLongPress);
            });

            // Keyboard support: ContextMenu key / Shift+F10 opens menu for focused item
            document.addEventListener('keydown', function(e){
                const focused = document.activeElement && document.activeElement.closest && document.activeElement.closest('.file-item');
                if (!focused) return;
                if (e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10')) {
                    e.preventDefault();
                    const rect = focused.getBoundingClientRect();
                    showContextMenuFor(focused, rect.left + 8, rect.bottom + 8);
                }
            });

            // Click inside context menu => execute actions
            contextMenu.addEventListener('click', function(e){
                const li = e.target.closest('[data-action]');
                if (!li) return;
                const action = li.dataset.action;
                const targetId = contextMenu.dataset.targetId;
                const targetType = contextMenu.dataset.targetType;

                handleMenuAction(action, targetId, targetType);
                hideContextMenu();
            });

            // Hide menu on outside clicks/touch
            document.addEventListener('click', function(e){ 
                if (!e.target.closest('#context-menu') && !e.target.closest('.item-more')) {
                    hideContextMenu(); 
                }
                // Close all dropdowns if clicked outside
                if (releaseFilterDropdownContainer && !releaseFilterDropdownContainer.contains(e.target)) {
                    releaseFilterDropdownContainer.classList.remove('show');
                }
                if (sortOrderDropdownContainer && !sortOrderDropdownContainer.contains(e.target)) {
                    sortOrderDropdownContainer.classList.remove('show');
                }
                if (fileTypeFilterDropdownContainer && !fileTypeFilterDropdownContainer.contains(e.target)) {
                    fileTypeFilterDropdownContainer.classList.remove('show');
                }
                const headerFileTypeDropdownContainer = document.querySelector('.toolbar-filter-buttons .file-type-filter-dropdown-container');
                const headerReleaseDropdownContainer = document.querySelector('.toolbar-filter-buttons .release-filter-dropdown-container');
                const headerSortOrderDropdownContainer = document.querySelector('.toolbar-filter-buttons .sort-order-dropdown-container');
                if (headerFileTypeDropdownContainer && !headerFileTypeDropdownContainer.contains(e.target)) {
                    headerFileTypeDropdownContainer.classList.remove('show');
                }
                if (headerReleaseDropdownContainer && !headerReleaseDropdownContainer.contains(e.target)) {
                    headerReleaseDropdownContainer.classList.remove('show');
                }
                if (headerSortOrderDropdownContainer && !headerSortOrderDropdownContainer.contains(e.target)) {
                    headerSortOrderDropdownContainer.classList.remove('show');
                }
            });
            window.addEventListener('blur', hideContextMenu);

            // --- Individual Restore/Delete Forever ---
            async function restoreItem(id, type) {
                if (!confirm(`Are you sure you want to restore this ${type}?`)) {
                    return;
                }
                try {
                    const response = await fetch('actions/restore_items.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ items: [{ id: id, type: type }] })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent();
                    } else {
                        showNotification(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while restoring the item.', 'error');
                }
            }

            async function deleteItemForever(id, type) {
                if (!confirm(`Are you sure you want to PERMANENTLY delete this ${type}? This action cannot be undone!`)) {
                    return;
                }
                try {
                    const response = await fetch('actions/delete_forever.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ items: [{ id: id, type: type }] })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent();
                    } else {
                        showNotification(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while deleting the item permanently.', 'error');
                }
            }

            // --- AJAX Content Update Function ---
            async function updateRecycleBinContent() {
                const params = new URLSearchParams();
                if (currentSearchQuery) {
                    params.set('search', currentSearchQuery);
                }
                if (currentReleaseFilter && currentReleaseFilter !== 'newest') { // 'newest' is default for trash
                    params.set('release', currentReleaseFilter);
                }
                if (currentSortOrder && currentSortOrder !== 'asc') {
                    params.set('sort', currentSortOrder);
                }
                if (currentFileTypeFilter && currentFileTypeFilter !== 'all') {
                    params.set('file_type', currentFileTypeFilter);
                }

                const url = `${BASE_URL}/v2/views/pages/recycle-bin.php?${params.toString()}&ajax=1`;

                try {
                    const response = await fetch(url);
                    const html = await response.text();

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    const newFileListView = tempDiv.querySelector('#fileListView table tbody').innerHTML;
                    const newFileGridView = tempDiv.querySelector('#fileGridView .file-grid').innerHTML;
                    const newStorageInfo = tempDiv.querySelector('.storage-info').innerHTML;
                    const newBreadcrumbs = tempDiv.querySelector('.breadcrumbs').innerHTML; // Update breadcrumbs too

                    document.querySelector('#fileListView table tbody').innerHTML = newFileListView;
                    document.querySelector('#fileGridView .file-grid').innerHTML = newFileGridView;
                    document.querySelector('.storage-info').innerHTML = newStorageInfo;
                    document.querySelector('.breadcrumbs').innerHTML = newBreadcrumbs;


                    updateSelectAllCheckboxListener();
                    history.pushState(null, '', `recycle_bin.php?${params.toString()}`);

                } catch (error) {
                    console.error('Error updating recycle bin content:', error);
                    showNotification('Failed to update recycle bin content. Please refresh the page.', 'error');
                }
            }

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            // Close sidebar when clicking overlay
            mobileOverlay.addEventListener('click', () => {
                sidebar.classList.remove('show-mobile-sidebar');
                mobileOverlay.classList.remove('show');
            });

            // Initial call to attach listeners
            updateSelectAllCheckboxListener();
        });
    </script>
</body>
</html>