// --- Translation Data (Global) ---
const translations = {
    // Sidebar
    'controlCenter': { 'id': 'Control Center', 'en': 'Control Center' },
    'myDrive': { 'id': 'Drive Saya', 'en': 'My Drive' },
    'priorityFile': { 'id': 'File Prioritas', 'en': 'Priority File' },
    'recycleBin': { 'id': 'Tempat Sampah', 'en': 'Recycle Bin' },
    'summary': { 'id': 'Ringkasan', 'en': 'Summary' },
    'members': { 'id': 'Anggota', 'en': 'Members' },
    'profile': { 'id': 'Profil', 'en': 'Profile' },
    'logout': { 'id': 'Keluar', 'en': 'Logout' },
    'storage': { 'id': 'Penyimpanan', 'en': 'Storage' },
    'storageFull': { 'id': 'Penyimpanan Penuh!', 'en': 'Storage Full!' },

    // Recycle Bin Page Specific
    'recycleBinTitle': { 'id': 'Tempat Sampah', 'en': 'Recycle Bin' },
    'searchTrashPlaceholder': { 'id': 'Cari file di tempat sampah...', 'en': 'Search files in trash...' },
    'restoreSelected': { 'id': 'Pulihkan Terpilih', 'en': 'Restore Selected' },
    'deleteForeverSelected': { 'id': 'Hapus Permanen Terpilih', 'en': 'Delete Forever Selected' },
    'emptyRecycleBin': { 'id': 'Kosongkan Tempat Sampah', 'en': 'Empty Recycle Bin' },
    'allFiles': { 'id': 'Semua File', 'en': 'All Files' },
    'documents': { 'id': 'Dokumen', 'en': 'Documents' },
    'images': { 'id': 'Gambar', 'en': 'Images' },
    'music': { 'id': 'Musik', 'en': 'Music' },
    'videos': { 'id': 'Video', 'en': 'Videos' },
    'codeFiles': { 'id': 'File Kode', 'en': 'Code Files' },
    'archives': { 'id': 'Arsip', 'en': 'Archives' },
    'installationFiles': { 'id': 'File Instalasi', 'en': 'Installation Files' },
    'p2pFiles': { 'id': 'File Peer-to-Peer', 'en': 'Peer-to-Peer Files' },
    'cadFiles': { 'id': 'File CAD', 'en': 'CAD Files' },
    'largestSize': { 'id': 'Ukuran Terbesar', 'en': 'Largest First' },
    'smallestSize': { 'id': 'Ukuran Terkecil', 'en': 'Smallest First' },
    'noSizeFilter': { 'id': 'Tanpa Filter Ukuran', 'en': 'No Size Filter' },
    'az': { 'id': 'A-Z', 'en': 'A-Z' }, // Still used for alphabetical if no size filter
    'za': { 'id': 'Z-A', 'en': 'Z-A' }, // Still used for alphabetical if no size filter
    'recycleBinBreadcrumb': { 'id': 'Tempat Sampah', 'en': 'Recycle Bin' },
    'breadcrumbSeparator': { 'id': '/', 'en': '/' },
    'searchResultsFor': { 'id': 'Hasil pencarian untuk', 'en': 'Search results for' },
    'name': { 'id': 'Nama', 'en': 'Name' },
    'type': { 'id': 'Tipe', 'en': 'Type' },
    'size': { 'id': 'Ukuran', 'en': 'Size' },
    'deletedAt': { 'id': 'Dihapus Pada', 'en': 'Deleted At' },
    'actions': { 'id': 'Tindakan', 'en': 'Actions' },
    'noSearchResults': { 'id': 'Tidak ada file atau folder yang dihapus yang cocok dengan', 'en': 'No deleted files or folders found matching' },
    'recycleBinEmpty': { 'id': 'Tempat Sampah kosong.', 'en': 'Recycle Bin is empty.' },
    'fileType': { 'id': 'File', 'en': 'File' }, // For item type in table
    'folderType': { 'id': 'Folder', 'en': 'Folder' }, // For item type in table
    'restore': { 'id': 'Pulihkan', 'en': 'Restore' },
    'deleteForever': { 'id': 'Hapus Permanen', 'en': 'Delete Forever' },
    'confirmRestoreSelected': { 'id': 'Anda yakin ingin memulihkan item yang dipilih?', 'en': 'Are you sure you want to restore the selected items?' },
    'confirmDeleteForeverSelected': { 'id': 'Anda yakin ingin MENGHAPUS PERMANEN item yang dipilih? Tindakan ini tidak dapat dibatalkan!', 'en': 'Are you sure you want to PERMANENTLY delete the selected items? This action cannot be undone!' },
    'confirmEmptyRecycleBin': { 'id': 'Anda yakin ingin MENGOSONGKAN seluruh Tempat Sampah? Semua item akan dihapus PERMANEN dan tindakan ini tidak dapat dibatalkan!', 'en': 'Are you sure you want to EMPTY the entire Recycle Bin? All items will be PERMANENTLY deleted and this action cannot be undone!' },
    'selectItemToRestore': { 'id': 'Pilih setidaknya satu file atau folder untuk dipulihkan!', 'en': 'Please select at least one file or folder to restore!' },
    'selectItemToDelete': { 'id': 'Pilih setidaknya satu file atau folder untuk dihapus secara permanen!', 'en': 'Please select at least one file or folder to delete permanently!' },
    'restoreSuccess': { 'id': 'Item berhasil dipulihkan.', 'en': 'Item restored successfully.' },
    'restoreFailed': { 'id': 'Gagal memulihkan item:', 'en': 'Failed to restore items:' },
    'deleteSuccess': { 'id': 'Item berhasil dihapus secara permanen.', 'en': 'Item deleted permanently.' },
    'deleteFailed': { 'id': 'Gagal menghapus item secara permanen:', 'en': 'Failed to delete items permanently:' },
    'emptyBinSuccess': { 'id': 'Tempat Sampah berhasil dikosongkan.', 'en': 'Recycle Bin emptied successfully.' },
    'emptyBinFailed': { 'id': 'Gagal mengosongkan Tempat Sampah:', 'en': 'Failed to empty Recycle Bin:' },
    'errorOccurred': { 'id': 'Terjadi kesalahan.', 'en': 'An error occurred.' },
    'updateFailed': { 'id': 'Gagal memperbarui konten tempat sampah. Harap segarkan halaman.', 'en': 'Failed to update recycle bin content. Please refresh the page.' },
    'file': { 'id': 'File', 'en': 'File' }, // For item type in table
    'folder': { 'id': 'Folder', 'en': 'Folder' }, // For item type in table
    // Add more file type translations if needed for grid view labels
    'documentType': { 'id': 'Dokumen', 'en': 'Document' },
    'imageType': { 'id': 'Gambar', 'en': 'Image' },
    'musicType': { 'id': 'Musik', 'en': 'Music' },
    'videoType': { 'id': 'Video', 'en': 'Video' },
    'codeType': { 'id': 'Kode', 'en': 'Code' },
    'archiveType': { 'id': 'Arsip', 'en': 'Archive' },
    'installationType': { 'id': 'Instalasi', 'en': 'Installation' },
    'p2pType': { 'id': 'P2P', 'en': 'P2P' },
    'cadType': { 'id': 'CAD', 'en': 'CAD' },
    'defaultType': { 'id': 'Lainnya', 'en': 'Other' },
};

let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

function applyTranslation(lang) {
    document.querySelectorAll('[data-lang-key]').forEach(element => {
        const key = element.getAttribute('data-lang-key');
        if (translations[key] && translations[key][lang]) {
            if (element.tagName === 'INPUT' && element.hasAttribute('placeholder')) {
                element.setAttribute('placeholder', translations[key][lang]);
            } else {
                element.textContent = translations[key][lang];
            }
        }
    });

    // Special handling for "of X used" text in sidebar
    const storageTextElement = document.getElementById('storageText');
    if (storageTextElement) {
        // These values are passed from PHP, so we need to ensure they are available in JS scope.
        // For this, we'll assume they are set as global JS variables or data attributes in the HTML.
        // For now, I'll use placeholder values. In a real scenario, you'd pass them from PHP.
        // Example: <body data-used-storage="<?php echo $usedStorageBytes; ?>" data-total-storage="<?php echo $totalStorageBytes; ?>">
        const usedBytes = parseFloat(document.body.dataset.usedStorage) || 0; // Placeholder
        const totalBytes = parseFloat(document.body.dataset.totalStorage) || 0; // Placeholder

        // To fix this, you need to add data attributes to the body tag in recycle_bin.php:
        // <body data-used-storage="<?php echo $usedStorageBytes; ?>" data-total-storage="<?php echo $totalStorageBytes; ?>">
        // And then retrieve them here:
        // const usedBytes = parseFloat(document.body.dataset.usedStorage);
        // const totalBytes = parseFloat(document.body.dataset.totalStorage);

        const formattedUsed = formatBytes(usedBytes);
        const formattedTotal = formatBytes(totalBytes);
        const ofText = translations['ofUsed'] ? translations['ofUsed'][lang] : (lang === 'id' ? 'dari' : 'of');
        const usedSuffix = translations['usedText' + (lang === 'id' ? 'Id' : 'En')] || (lang === 'id' ? 'terpakai' : 'used');
        storageTextElement.textContent = `${formattedUsed} ${ofText} ${formattedTotal} ${usedSuffix}`;
    }

    // Special handling for search results breadcrumb
    const searchResultsSpan = document.querySelector('[data-lang-key="searchResultsFor"]');
    if (searchResultsSpan) {
        // This value is passed from PHP, so we need to ensure it's available in JS scope.
        // Example: <input type="hidden" id="phpSearchQuery" value="<?php echo htmlspecialchars($searchQuery); ?>">
        const originalQuery = document.getElementById('phpSearchQuery') ? document.getElementById('phpSearchQuery').value : ''; // Placeholder
        // To fix this, add a hidden input in recycle_bin.php:
        // <input type="hidden" id="phpSearchQuery" value="<?php echo htmlspecialchars($searchQuery); ?>">
        // And then retrieve it here.

        searchResultsSpan.textContent = `${translations['searchResultsFor'][lang]} "${originalQuery}"`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const restoreSelectedBtn = document.getElementById('restoreSelectedBtn');
    const deleteForeverSelectedBtn = document.getElementById('deleteForeverSelectedBtn');
    const emptyRecycleBinBtn = document.getElementById('emptyRecycleBinBtn');
    
    // Dropdown elements (main toolbar)
    const fileTypeFilterDropdownContainer = document.querySelector('.toolbar .file-type-filter-dropdown-container');
    const fileTypeFilterBtn = document.getElementById('fileTypeFilterBtn');
    const fileTypeFilterDropdownContent = document.querySelector('.toolbar .file-type-filter-dropdown-content');

    const sizeFilterDropdownContainer = document.querySelector('.toolbar .size-filter-dropdown-container');
    const sizeFilterBtn = document.getElementById('sizeFilterBtn');
    const sizeFilterDropdownContent = document.querySelector('.toolbar .size-filter-dropdown-content');

    // Dropdown elements (header)
    const fileTypeFilterBtnHeader = document.getElementById('fileTypeFilterBtnHeader');
    const sizeFilterBtnHeader = document.getElementById('sizeFilterBtnHeader');
    const listViewBtnHeader = document.getElementById('listViewBtnHeader');
    const gridViewBtnHeader = document.getElementById('gridViewBtnHeader');


    const listViewBtn = document.getElementById('listViewBtn');
    const gridViewBtn = document.getElementById('gridViewBtn');
    const fileListView = document.getElementById('fileListView');
    const fileGridView = document.getElementById('fileGridView');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
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

    // Sidebar menu items for active state management
    const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');
    const mainContent = document.getElementById('mainContent'); // Get main-content for animations

    // Current state variables for AJAX filtering/sorting
    // These values need to be passed from PHP to JavaScript.
    // Add hidden inputs or data attributes in recycle_bin.php for these.
    // Example: <input type="hidden" id="phpCurrentSearchQuery" value="<?php echo json_encode($searchQuery); ?>">
    // <input type="hidden" id="phpCurrentSizeFilter" value="<?php echo json_encode($sizeFilter); ?>">
    // <input type="hidden" id="phpCurrentSortOrder" value="<?php echo json_encode($sortOrder); ?>">
    // <input type="hidden" id="phpCurrentFileTypeFilter" value="<?php echo json_encode($fileTypeFilter); ?>">
    let currentSearchQuery = JSON.parse(document.getElementById('phpCurrentSearchQuery')?.value || '""');
    let currentSizeFilter = JSON.parse(document.getElementById('phpCurrentSizeFilter')?.value || '"none"');
    let currentSortOrder = JSON.parse(document.getElementById('phpCurrentSortOrder')?.value || '"asc"');
    let currentFileTypeFilter = JSON.parse(document.getElementById('phpCurrentFileTypeFilter')?.value || '"all"');
    const userId = JSON.parse(document.getElementById('phpUserId')?.value || 'null'); // Also pass userId from PHP

    // To fix this, add hidden inputs in recycle_bin.php:
    // <input type="hidden" id="phpCurrentSearchQuery" value="<?php echo json_encode($searchQuery); ?>">
    // <input type="hidden" id="phpCurrentSizeFilter" value="<?php echo json_encode($sizeFilter); ?>">
    // <input type="hidden" id="phpCurrentSortOrder" value="<?php echo json_encode($sortOrder); ?>">
    // <input type="hidden" id="phpCurrentFileTypeFilter" value="<?php echo json_encode($fileTypeFilter); ?>">
    // <input type="hidden" id="phpUserId" value="<?php echo json_encode($userId); ?>">


    /*** Util helpers ****/
    function debounce(fn, ms=150){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
    function closestFileItem(el){ return el && el.closest('.file-item'); }

    // Helper function for formatBytes (replicate PHP's formatBytes)
    function formatBytes(bytes, precision = 2) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        bytes = Math.max(bytes, 0);
        const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
        const unitIndex = Math.min(pow, units.length - 1);
        bytes /= (1 << (10 * unitIndex));
        return bytes.toFixed(precision) + ' ' + units[unitIndex];
    }

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
            showNotification(translations['selectItemToRestore'][currentLanguage], 'error');
            return;
        }

        if (!confirm(translations['confirmRestoreSelected'][currentLanguage])) {
            return;
        }

        try {
            const response = await fetch('actions/restore_items.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ items: selectedItems })
            });
            const data = await response.json();
            if (data.success) {
                showNotification(translations['restoreSuccess'][currentLanguage], 'success');
                updateRecycleBinContent(); // Update content without full reload
            } else {
                showNotification(translations['restoreFailed'][currentLanguage] + ' ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['restoreFailed'][currentLanguage], 'error');
        }
    });

    // --- Delete Forever Selected Files/Folders ---
    deleteForeverSelectedBtn.addEventListener('click', async () => {
        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
        const selectedItems = Array.from(checkboxes).map(cb => {
            return { id: cb.dataset.id, type: cb.dataset.type };
        });

        if (selectedItems.length === 0) {
            showNotification(translations['selectItemToDelete'][currentLanguage], 'error');
            return;
        }

        if (!confirm(translations['confirmDeleteForeverSelected'][currentLanguage])) {
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
                showNotification(translations['deleteSuccess'][currentLanguage], 'success');
                updateRecycleBinContent(); // Update content without full reload
            } else {
                showNotification(translations['deleteFailed'][currentLanguage] + ' ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['deleteFailed'][currentLanguage], 'error');
        }
    });

    // --- Empty Recycle Bin ---
    emptyRecycleBinBtn.addEventListener('click', async () => {
        if (!confirm(translations['confirmEmptyRecycleBin'][currentLanguage])) {
            return;
        }

        try {
            const response = await fetch('actions/empty_recycle_bin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_id: userId }) // Use userId from PHP
            });
            const data = await response.json();
            if (data.success) {
                showNotification(translations['emptyBinSuccess'][currentLanguage], 'success');
                updateRecycleBinContent(); // Update content without full reload
            } else {
                showNotification(translations['emptyBinFailed'][currentLanguage] + ' ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['emptyBinFailed'][currentLanguage], 'error');
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

    // --- Dropdown Exclusive Logic ---
    const allDropdownContainers = document.querySelectorAll('.dropdown-container');

    function closeAllDropdowns() {
        allDropdownContainers.forEach(container => {
            container.classList.remove('show');
        });
    }

    function setupDropdown(buttonId, dropdownContentSelector, filterType) {
        const button = document.getElementById(buttonId);
        const dropdownContent = document.querySelector(dropdownContentSelector);
        const dropdownContainer = button.closest('.dropdown-container');

        if (!button || !dropdownContent || !dropdownContainer) return;

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const isShowing = dropdownContainer.classList.contains('show');
            closeAllDropdowns(); // Close all other dropdowns
            if (!isShowing) {
                dropdownContainer.classList.add('show'); // Open this one
            }
        });

        dropdownContent.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                dropdownContainer.classList.remove('show');
                if (filterType === 'file_type') {
                    currentFileTypeFilter = event.target.dataset.filter;
                } else if (filterType === 'size') {
                    currentSizeFilter = event.target.dataset.filter;
                    // If size filter is 'none', reset sortOrder to 'asc' for alphabetical
                    if (currentSizeFilter === 'none') {
                        currentSortOrder = 'asc';
                    }
                }
                updateRecycleBinContent();
            });
        });
    }

    setupDropdown('fileTypeFilterBtn', '.toolbar .file-type-filter-dropdown-content', 'file_type');
    setupDropdown('fileTypeFilterBtnHeader', '.toolbar-filter-buttons .file-type-filter-dropdown-content', 'file_type');
    setupDropdown('sizeFilterBtn', '.toolbar .size-filter-dropdown-content', 'size');
    setupDropdown('sizeFilterBtnHeader', '.toolbar-filter-buttons .size-filter-dropdown-content', 'size');


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
        if (!e.target.closest('.dropdown-container')) {
            closeAllDropdowns();
        }
    });
    window.addEventListener('blur', hideContextMenu);

    // --- Individual Restore/Delete Forever ---
    async function restoreItem(id, type) {
        if (!confirm(translations['confirmRestoreSelected'][currentLanguage])) {
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
                showNotification(translations['restoreSuccess'][currentLanguage], 'success');
                updateRecycleBinContent();
            } else {
                showNotification(translations['restoreFailed'][currentLanguage] + ' ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['restoreFailed'][currentLanguage], 'error');
        }
    }

    async function deleteItemForever(id, type) {
        if (!confirm(translations['confirmDeleteForeverSelected'][currentLanguage])) {
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
                showNotification(translations['deleteSuccess'][currentLanguage], 'success');
                updateRecycleBinContent();
            } else {
                showNotification(translations['deleteFailed'][currentLanguage] + ' ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['deleteFailed'][currentLanguage], 'error');
        }
    }

    // --- AJAX Content Update Function ---
    async function updateRecycleBinContent() {
        const params = new URLSearchParams();
        if (currentSearchQuery) {
            params.set('search', currentSearchQuery);
        }
        if (currentSizeFilter && currentSizeFilter !== 'none') {
            params.set('size', currentSizeFilter);
        } else {
            // If no size filter, use alphabetical sort order
            params.set('sort', currentSortOrder);
        }
        if (currentFileTypeFilter && currentFileTypeFilter !== 'all') {
            params.set('file_type', currentFileTypeFilter);
        }

        const url = `recycle_bin.php?${params.toString()}&ajax=1`;

        try {
            const response = await fetch(url);
            const html = await response.text();

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;

            // Extract the relevant parts from the fetched HTML
            const newFileListView = tempDiv.querySelector('#fileListView table tbody')?.innerHTML;
            const newFileGridView = tempDiv.querySelector('#fileGridView .file-grid')?.innerHTML;
            const newStorageInfo = tempDiv.querySelector('.storage-info')?.innerHTML;
            const newBreadcrumbs = tempDiv.querySelector('.breadcrumbs')?.innerHTML;

            // Update the DOM elements if they exist
            if (newFileListView) document.querySelector('#fileListView table tbody').innerHTML = newFileListView;
            if (newFileGridView) document.querySelector('#fileGridView .file-grid').innerHTML = newFileGridView;
            if (newStorageInfo) document.querySelector('.storage-info').innerHTML = newStorageInfo;
            if (newBreadcrumbs) document.querySelector('.breadcrumbs').innerHTML = newBreadcrumbs;

            // Update the PHP variables in JS scope after AJAX call
            // This requires the PHP to return these values, perhaps as JSON or hidden inputs.
            // For now, we'll assume they are updated by the server response implicitly.
            // A more robust solution would be to return JSON with updated state.
            // Example:
            // const updatedState = JSON.parse(tempDiv.querySelector('#updatedStateJson')?.textContent || '{}');
            // if (updatedState.searchQuery) currentSearchQuery = updatedState.searchQuery;
            // ... and so on for other filters/sorts.

            updateSelectAllCheckboxListener();
            history.pushState(null, '', `recycle_bin.php?${params.toString()}`);
            applyTranslation(currentLanguage); // Apply translation after content update

        } catch (error) {
            console.error('Error updating recycle bin content:', error);
            // showNotification(translations['updateFailed'][currentLanguage], 'error'); // Baris ini dihapus
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

    // --- Sidebar Menu Navigation with Fly Out Animation ---
    sidebarMenuItems.forEach(item => {
        item.addEventListener('click', function(event) {
            // Only apply animation if it.s a navigation link and not the current active page
            if (this.getAttribute('href') && !this.classList.contains('active')) {
                event.preventDefault(); // Prevent default navigation immediately
                const targetUrl = this.getAttribute('href');

                mainContent.classList.add('fly-out'); // Start fly-out animation

                mainContent.addEventListener('animationend', function handler() {
                    mainContent.removeEventListener('animationend', handler);
                    window.location.href = targetUrl; // Navigate after animation
                });
            }
        });
    });

    // Initial call to attach listeners
    updateSelectAllCheckboxListener();

    // Set active class for current page in sidebar
    const currentPage = window.location.pathname.split('/').pop();
    sidebarMenuItems.forEach(item => {
        item.classList.remove('active');
        const itemHref = item.getAttribute('href');
        if (itemHref === currentPage || (currentPage === 'recycle_bin.php' && itemHref === 'recycle_bin.php')) {
            item.classList.add('active');
        }
    });

    // Apply initial translation
    applyTranslation(currentLanguage);
});
