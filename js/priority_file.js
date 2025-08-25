document.addEventListener('DOMContentLoaded', function() {
    const userGridContainer = document.getElementById('userGridContainer');
    const userPagination = document.getElementById('userPagination');
    const prevUserPageBtn = document.getElementById('prevUserPage');
    const nextUserPageBtn = document.getElementById('nextUserPage');
    const currentUserPageSpan = document.getElementById('currentUserPage');
    const totalUserPagesSpan = document.getElementById('totalUserPages');

    const starredItemsListDiv = document.getElementById('starredItemsList');
    const selectedUserNameSpan = document.getElementById('selectedUserName');
    const starredItemsTableBody = document.getElementById('starredItemsTableBody');
    const starredPagination = document.getElementById('starredPagination');
    const prevStarredPageBtn = document.getElementById('prevStarredPage');
    const nextStarredPageBtn = document.getElementById('nextStarredPage');
    const currentStarredPageSpan = document.getElementById('currentStarredPage');
    const totalStarredPagesSpan = document.getElementById('totalStarredPages');
    const customNotification = document.getElementById('customNotification');

    // Mobile sidebar elements
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mainContent = document.getElementById('mainContent'); // Get main-content for animations

    // Sidebar menu items for active state management
    const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');

    let currentUserPage = 1;
    let totalUserPages = 1;
    let selectedUserId = null;
    let currentStarredPage = 1;
    let totalStarredPages = 1;

    // PHP variables passed to JavaScript (defined in priority_files.php script tag)
    // const currentUserRole = "<?php echo $currentUserRole; ?>"; // This is now a global variable from the PHP file
    // const usedStorageBytes = <?php echo $usedStorageBytes; ?>; // This is now a global variable from the PHP file
    // const totalStorageBytes = <?php echo $totalStorageBytes; ?>; // This is now a global variable from the PHP file

    // Define restricted file extensions
    const restrictedExtensions = {
        'p2p': ['torrent', 'nzb', 'ed2k', 'part', '!ut'],
        'code': ['html', 'htm', 'css', 'js', 'php', 'py', 'java', 'json', 'xml', 'ts', 'tsx', 'jsx', 'vue', 'cpp', 'c', 'cs', 'rb', 'go', 'swift', 'sql', 'sh', 'bat', 'ini', 'yml', 'yaml', 'md', 'pl', 'r'],
        'installation': ['exe', 'msi', 'apk', 'ipa', 'sh', 'bat', 'jar', 'appimage', 'dmg', 'bin']
    };

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

        // Priority Files Page
        'priorityFilesTitle': { 'id': 'File Prioritas', 'en': 'Priority Files' },
        'previous': { 'id': 'Sebelumnya', 'en': 'Previous' },
        'next': { 'id': 'Berikutnya', 'en': 'Next' },
        'page': { 'id': 'Halaman', 'en': 'Page' },
        'starredItemsFor': { 'id': 'Item Prioritas untuk', 'en': 'Starred Items for' },
        'name': { 'id': 'Nama', 'en': 'Name' },
        'type': { 'id': 'Tipe', 'en': 'Type' },
        'size': { 'id': 'Ukuran', 'en': 'Size' },
        'lastModified': { 'id': 'Terakhir Dimodifikasi', 'en': 'Last Modified' },
        'actions': { 'id': 'Tindakan', 'en': 'Actions' },
        'noStarredItems': { 'id': 'Tidak ada item prioritas untuk pengguna ini.', 'en': 'No starred items for this user.' },
        'unpinFromPriority': { 'id': 'Hapus dari Prioritas', 'en': 'Unpin from Priority' },
        'download': { 'id': 'Unduh', 'en': 'Download' },
        'delete': { 'id': 'Hapus', 'en': 'Delete' },
        'confirmDelete': { 'id': 'Apakah Anda yakin ingin menghapus {type} ini secara permanen?', 'en': 'Are you sure you want to permanently delete this {type}?' },
        'failedToLoadUsers': { 'id': 'Gagal memuat profil pengguna.', 'en': 'Failed to load user profiles.' },
        'failedToLoadStarredItems': { 'id': 'Gagal memuat item prioritas.', 'en': 'Failed to load starred items.' },
        'failedToToggleStar': { 'id': 'Gagal mengubah status prioritas:', 'en': 'Failed to toggle star:' },
        'failedToDelete': { 'id': 'Gagal menghapus:', 'en': 'Failed to delete:' },
        'anErrorOccurredToggleStar': { 'id': 'Terjadi kesalahan saat mengubah status prioritas.', 'en': 'An error occurred while toggling star.' },
        'anErrorOccurredDelete': { 'id': 'Terjadi kesalahan saat menghapus item.', 'en': 'An error occurred while deleting item.' },
        'starToggledSuccess': { 'id': 'Status prioritas berhasil diubah.', 'en': 'Star status toggled successfully.' },
        'deleteSuccess': { 'id': 'Berhasil dihapus.', 'en': 'Successfully deleted.' },
        'usedTextId': 'terpakai',
        'usedTextEn': 'used',
    };

    let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

    function applyTranslation(lang) {
        document.querySelectorAll('[data-lang-key]').forEach(element => {
            const key = element.getAttribute('data-lang-key');
            if (translations[key] && translations[key][lang]) {
                element.textContent = translations[key][lang];
            }
        });

        // Special handling for "of X used" text in sidebar
        const storageTextElement = document.getElementById('storageText');
        if (storageTextElement) {
            // Retrieve values from data attributes instead of PHP variables directly
            const usedBytes = parseInt(storageTextElement.dataset.usedBytes);
            const totalBytes = parseInt(storageTextElement.dataset.totalBytes);
            storageTextElement.textContent = `${formatBytes(usedBytes)} ${translations['usedText' + (lang === 'id' ? 'Id' : 'En')]} ${formatBytes(totalBytes)} ${translations['usedText' + (lang === 'id' ? 'Id' : 'En')]}`;
        }

        // Update pagination text
        currentUserPageSpan.innerHTML = `<span data-lang-key="page">${translations['page'][lang]}</span> ${currentUserPage}`;
        currentStarredPageSpan.innerHTML = `<span data-lang-key="page">${translations['page'][lang]}</span> ${currentStarredPage}`;

        // Update starred items title
        if (selectedUserNameSpan.textContent) {
            document.getElementById('starredItemsTitle').innerHTML = `<span data-lang-key="starredItemsFor">${translations['starredItemsFor'][lang]}</span> <span id="selectedUserName">${selectedUserNameSpan.textContent}</span>`;
        }

        // Update table headers
        document.querySelector('.starred-table th[data-lang-key="name"]').textContent = translations['name'][lang];
        document.querySelector('.starred-table th[data-lang-key="type"]').textContent = translations['type'][lang];
        document.querySelector('.starred-table th[data-lang-key="size"]').textContent = translations['size'][lang];
        document.querySelector('.starred-table th[data-lang-key="lastModified"]').textContent = translations['lastModified'][lang];
        document.querySelector('.starred-table th[data-lang-key="actions"]').textContent = translations['actions'][lang];

        // Update button titles
        document.querySelectorAll('.unstar-btn').forEach(btn => {
            btn.title = translations['unpinFromPriority'][lang];
        });
        document.querySelectorAll('.starred-actions a[download]').forEach(btn => {
            btn.title = translations['download'][lang];
        });
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.title = translations['delete'][lang];
        });

        // Update "No starred items" message
        const noStarredItemsRow = starredItemsTableBody.querySelector('tr td[colspan="5"]');
        if (noStarredItemsRow && noStarredItemsRow.getAttribute('data-lang-key') === 'noStarredItems') {
            noStarredItemsRow.textContent = translations['noStarredItems'][lang];
        }
    }

    /*** Device detection & body class toggling ***/
    function setDeviceClass() {
        const ua = navigator.userAgent || '';
        const w = window.innerWidth;
        document.body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop'); // Clear all
        if (w <= 767) {
            document.body.classList.add('mobile');
            sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default on mobile
        } else if (w >= 768 && w <= 1024) {
            if (window.matchMedia("(orientation: portrait)").matches) {
                document.body.classList.add('tablet-portrait');
                sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default on tablet portrait
            } else {
                document.body.classList.add('tablet-landscape');
                sidebar.classList.remove('mobile-hidden'); // Sidebar visible on tablet landscape
                sidebar.classList.remove('show-mobile-sidebar');
                mobileOverlay.classList.remove('show');
            }
        } else {
            document.body.classList.add('desktop');
            sidebar.classList.remove('mobile-hidden'); // Sidebar visible on desktop
            sidebar.classList.remove('show-mobile-sidebar');
            mobileOverlay.classList.remove('show');
        }
    }
    window.addEventListener('resize', setDeviceClass);
    window.addEventListener('orientationchange', setDeviceClass); // Listen for orientation changes
    setDeviceClass(); // init

    // Function to show custom notification
    function showNotification(message, type) {
        customNotification.textContent = message;
        customNotification.className = 'notification show ' + type;
        setTimeout(() => {
            customNotification.classList.remove('show');
        }, 3000);
    }

    // Helper to format bytes (replicate from PHP)
    function formatBytes(bytes, precision = 2) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        bytes = Math.max(bytes, 0);
        const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
        const p = Math.min(pow, units.length - 1);
        bytes /= (1 << (10 * p));
        return bytes.toFixed(precision) + ' ' + units[p];
    }

    // Function to get file icon class (replicate from PHP)
    function getFileIconClass(fileName) {
        const extension = fileName.split('.').pop().toLowerCase();
        const iconClasses = {
            'pdf': 'fa-file-pdf', 'doc': 'fa-file-word', 'docx': 'fa-file-word',
            'xls': 'fa-file-excel', 'xlsx': 'fa-file-excel', 'ppt': 'fa-file-powerpoint',
            'pptx': 'fa-file-powerpoint', 'txt': 'fa-file-alt', 'rtf': 'fa-file-alt',
            'md': 'fa-file-alt', 'csv': 'fa-file-csv', 'odt': 'fa-file-alt',
            'odp': 'fa-file-powerpoint', 'log': 'fa-file-alt', 'tex': 'fa-file-alt',
            'jpg': 'fa-file-image', 'jpeg': 'fa-file-image', 'png': 'fa-file-image',
            'gif': 'fa-file-image', 'bmp': 'fa-file-image', 'webp': 'fa-file-image',
            'svg': 'fa-file-image', 'tiff': 'fa-file-image',
            'mp3': 'fa-file-audio', 'wav': 'fa-file-audio', 'ogg': 'fa-file-audio',
            'flac': 'fa-file-audio', 'aac': 'fa-file-audio', 'm4a': 'fa-file-audio',
            'alac': 'fa-file-audio', 'wma': 'fa-file-audio', 'opus': 'fa-file-audio',
            'amr': 'fa-file-audio', 'mid': 'fa-file-audio',
            'mp4': 'fa-file-video', 'avi': 'fa-file-video', 'mov': 'fa-file-video',
            'wmv': 'fa-file-video', 'flv': 'fa-file-video', 'webm': 'fa-file-video',
            '3gp': 'fa-file-video', 'm4v': 'fa-file-video', 'mpg': 'fa-file-video',
            'mpeg': 'fa-file-video', 'ts': 'fa-file-video', 'ogv': 'fa-file-video',
            'zip': 'fa-file-archive', 'rar': 'fa-file-archive', '7z': 'fa-file-archive',
            'tar': 'fa-file-archive', 'gz': 'fa-file-archive', 'bz2': 'fa-file-archive',
            'xz': 'fa-file-archive', 'iso': 'fa-file-archive', 'cab': 'fa-file-archive',
            'arj': 'fa-file-archive',
            'html': 'fa-file-code', 'htm': 'fa-file-code', 'css': 'fa-file-code',
            'js': 'fa-file-code', 'php': 'fa-file-code', 'py': 'fa-file-code',
            'java': 'fa-file-code', 'json': 'fa-file-code', 'xml': 'fa-file-code',
            'ts': 'fa-file-code', 'tsx': 'fa-file-code', 'jsx': 'fa-file-code',
            'vue': 'fa-file-code', 'cpp': 'fa-file-code', 'c': 'fa-file-code',
            'cs': 'fa-file-code', 'rb': 'fa-file-code', 'go': 'fa-file-code',
            'swift': 'fa-file-code', 'sql': 'fa-database', 'sh': 'fa-file-code',
            'bat': 'fa-file-code', 'ini': 'fa-file-code', 'yml': 'fa-file-code',
            'yaml': 'fa-file-code', 'pl': 'fa-file-code', 'r': 'fa-file-code',
            'exe': 'fa-box', 'msi': 'fa-box', 'apk': 'fa-box', 'ipa': 'fa-box',
            'jar': 'fa-box', 'appimage': 'fa-box', 'dmg': 'fa-box', 'bin': 'fa-box',
            'torrent': 'fa-magnet', 'nzb': 'fa-magnet', 'ed2k': 'fa-magnet',
            'part': 'fa-magnet', '!ut': 'fa-magnet',
            'dwg': 'fa-cube', 'dxf': 'fa-cube', 'dgn': 'fa-cube', 'iges': 'fa-cube',
            'igs': 'fa-cube', 'step': 'fa-cube', 'stp': 'fa-cube', 'stl': 'fa-cube',
            '3ds': 'fa-cube', 'obj': 'fa-cube', 'sldprt': 'fa-cube', 'sldasm': 'fa-cube',
            'ipt': 'fa-cube', 'iam': 'fa-cube', 'catpart': 'fa-cube', 'catproduct': 'fa-cube',
            'prt': 'fa-cube', 'asm': 'fa-cube', 'fcstd': 'fa-cube', 'skp': 'fa-cube',
            'x_t': 'fa-cube', 'x_b': 'fa-cube',
            'default': 'fa-file'
        };
        return iconClasses[extension] || iconClasses['default'];
    }

    // Function to get file color class (replicate from PHP)
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

    // Function to check if an extension is restricted for non-admin/moderator
    function isRestrictedExtension(fileName) {
        const extension = fileName.split('.').pop().toLowerCase();
        const isRestricted = Object.values(restrictedExtensions).some(arr => arr.includes(extension));
        return isRestricted;
    }

    // Load Users Grid
    async function loadUsers(page) {
        try {
            const response = await fetch(`priority_files.php?action=get_users&page=${page}`);
            const data = await response.json();

            userGridContainer.innerHTML = '';
            data.users.forEach(user => {
                const userCard = document.createElement('div');
                userCard.className = 'user-profile-card';
                if (user.id === selectedUserId) {
                    userCard.classList.add('active');
                }
                userCard.dataset.userId = user.id;
                userCard.dataset.userName = user.full_name || user.username;
                userCard.innerHTML = `
                    <img src="${user.profile_picture}" alt="${user.full_name || user.username}">
                    <h3>${user.full_name || user.username}</h3>
                    <p>${user.username}</p>
                `;
                userCard.addEventListener('click', () => {
                    selectUser(user.id, user.full_name || user.username);
                });
                userGridContainer.appendChild(userCard);
            });

            currentUserPage = page;
            totalUserPages = Math.ceil(data.total_users / data.per_page);
            currentUserPageSpan.innerHTML = `<span data-lang-key="page">${translations['page'][currentLanguage]}</span> ${currentUserPage}`;
            totalUserPagesSpan.textContent = totalUserPages;

            prevUserPageBtn.disabled = currentUserPage === 1;
            nextUserPageBtn.disabled = currentUserPage === totalUserPages;

        } catch (error) {
            console.error('Error loading users:', error);
            showNotification(translations['failedToLoadUsers'][currentLanguage], 'error');
        }
    }

    // Load Starred Items for a specific user
    async function loadStarredItems(userId, page) {
        try {
            const response = await fetch(`priority_files.php?action=get_starred_items&user_id=${userId}&page=${page}`);
            const data = await response.json();

            starredItemsTableBody.innerHTML = '';
            let itemsDisplayedCount = 0;

            if (data.items.length === 0) {
                starredItemsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px;" data-lang-key="noStarredItems">${translations['noStarredItems'][currentLanguage]}</td></tr>`;
            } else {
                data.items.forEach(item => {
                    const itemData = item.data;
                    const itemName = item.type === 'folder' ? itemData.folder_name : itemData.file_name;
                    const isFileRestricted = item.type === 'file' && isRestrictedExtension(itemName);

                    // Only display if user is admin/moderator OR if the file is not restricted
                    if (window.currentUserRole === 'admin' || window.currentUserRole === 'moderator' || !isFileRestricted) { // Use window.currentUserRole
                        const row = document.createElement('tr');
                        const iconClass = item.type === 'folder' ? 'fas fa-folder' : `fas ${getFileIconClass(itemName)}`;
                        const colorClass = item.type === 'folder' ? 'folder' : getFileColorClass(itemName);
                        const itemLink = item.type === 'folder' ? `index.php?folder=${itemData.id}` : `view.php?file_id=${itemData.id}`;

                        row.innerHTML = `
                            <td class="file-name-cell">
                                <i class="fas ${iconClass} file-icon ${colorClass}"></i>
                                <a href="${itemLink}">${itemName}</a>
                            </td>
                            <td>${itemData.display_type}</td>
                            <td>${itemData.display_size}</td>
                            <td>${itemData.display_date}</td>
                            <td class="starred-actions">
                                <button class="unstar-btn" data-id="${itemData.id}" data-type="${item.type}" data-name="${itemName}" title="${translations['unpinFromPriority'][currentLanguage]}"><i class="fas fa-star"></i></button>
                                ${item.type === 'file' ? `<a href="${itemData.file_path}" download="${itemData.file_name}" title="${translations['download'][currentLanguage]}"><button><i class="fas fa-download"></i></button></a>` : ''}
                                <button class="delete-btn" data-id="${itemData.id}" data-type="${item.type}" title="${translations['delete'][currentLanguage]}"><i class="fas fa-trash"></i></button>
                            </td>
                        `;
                        starredItemsTableBody.appendChild(row);
                        itemsDisplayedCount++;
                    }
                });

                if (itemsDisplayedCount === 0) {
                    starredItemsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px;" data-lang-key="noStarredItems">${translations['noStarredItems'][currentLanguage]}</td></tr>`;
                }
            }

            currentStarredPage = page;
            // Note: totalStarredPages calculation here is based on total items from backend,
            // not just displayed items. This is fine as pagination is handled by backend.
            totalStarredPages = Math.ceil(data.total_items / data.per_page);
            currentStarredPageSpan.innerHTML = `<span data-lang-key="page">${translations['page'][currentLanguage]}</span> ${currentStarredPage}`;
            totalStarredPagesSpan.textContent = totalStarredPages;

            prevStarredPageBtn.disabled = currentStarredPage === 1;
            nextStarredPageBtn.disabled = currentStarredPage === totalStarredPages;

            // Add event listeners for dynamically created buttons
            starredItemsTableBody.querySelectorAll('.unstar-btn').forEach(button => {
                button.addEventListener('click', (event) => {
                    const id = event.currentTarget.dataset.id;
                    const type = event.currentTarget.dataset.type;
                    const name = event.currentTarget.dataset.name;
                    toggleStar(id, type, name, true); // Always unstar from this page
                });
            });

            starredItemsTableBody.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', (event) => {
                    const id = event.currentTarget.dataset.id;
                    const type = event.currentTarget.dataset.type;
                    deleteItem(id, type);
                });
            });

        } catch (error) {
            console.error('Error loading starred items:', error);
            showNotification(translations['failedToLoadStarredItems'][currentLanguage], 'error');
        }
    }

    // Select a user and show their starred items
    function selectUser(userId, userName) {
        selectedUserId = userId;
        selectedUserNameSpan.textContent = userName;
        starredItemsListDiv.style.display = 'block';
        currentStarredPage = 1; // Reset starred items pagination
        loadStarredItems(selectedUserId, currentStarredPage);

        // Update active state for user cards
        document.querySelectorAll('.user-profile-card').forEach(card => {
            card.classList.remove('active');
            if (parseInt(card.dataset.userId) === userId) {
                card.classList.add('active');
            }
        });
    }

    // Toggle Star function (for unstarring from this page)
    async function toggleStar(id, type, name, unstar = false) {
        try {
            const response = await fetch('toggle_star.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id, type: type, name: name, unstar: unstar })
            });
            const data = await response.json();
            if (data.success) {
                showNotification(translations['starToggledSuccess'][currentLanguage], 'success');
                loadStarredItems(selectedUserId, currentStarredPage); // Reload current page
            } else {
                showNotification(`${translations['failedToToggleStar'][currentLanguage]} ${data.message}`, 'error');
            }
        } catch (error) {
            console.error('Error toggling star:', error);
            showNotification(translations['anErrorOccurredToggleStar'][currentLanguage], 'error');
        }
    }

    // Delete Item function (from this page)
    async function deleteItem(id, type) {
        const confirmMessage = translations['confirmDelete'][currentLanguage].replace('{type}', type);
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            const response = await fetch('delete.php', { // Use existing delete.php
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id, type: type })
            });
            const data = await response.json();
            if (data.success) {
                showNotification(translations['deleteSuccess'][currentLanguage], 'success');
                // Also unstar the item after deletion (if it was starred)
                // We don't need the name here, just the id and type to remove from starred.json
                toggleStar(id, type, '', true); // Pass true for unstar
                loadStarredItems(selectedUserId, currentStarredPage); // Reload current page
            } else {
                showNotification(`${translations['failedToDelete'][currentLanguage]} ${data.message}`, 'error');
            }
        } catch (error) {
            console.error('Error deleting item:', error);
            showNotification(translations['anErrorOccurredDelete'][currentLanguage], 'error');
        }
    }

    // Pagination Event Listeners for Users
    prevUserPageBtn.addEventListener('click', () => {
        if (currentUserPage > 1) {
            loadUsers(currentUserPage - 1);
        }
    });
    nextUserPageBtn.addEventListener('click', () => {
        if (currentUserPage < totalUserPages) {
            loadUsers(currentUserPage + 1);
        }
    });

    // Pagination Event Listeners for Starred Items
    prevStarredPageBtn.addEventListener('click', () => {
        if (currentStarredPage > 1) {
            loadStarredItems(selectedUserId, currentStarredPage - 1);
        }
    });
    nextStarredPageBtn.addEventListener('click', () => {
        if (currentStarredPage < totalStarredPages) {
            loadStarredItems(selectedUserId, currentStarredPage + 1);
        }
    });

    // --- Mobile Sidebar Toggle ---
    sidebarToggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show-mobile-sidebar');
        mobileOverlay.classList.toggle('show');
    });

    // Close mobile sidebar if overlay is clicked
    mobileOverlay.addEventListener('click', () => {
        if (sidebar.classList.contains('show-mobile-sidebar')) {
            sidebar.classList.remove('show-mobile-sidebar');
            mobileOverlay.classList.remove('show');
        }
    });

    // Set active class for current page in sidebar
    const currentPagePath = window.location.pathname.split('/').pop();
    sidebarMenuItems.forEach(item => {
        item.classList.remove('active');
        const itemHref = item.getAttribute('href');
        if (itemHref === currentPagePath) {
            item.classList.add('active');
        }
    });

    // --- Sidebar Menu Navigation with Fly Out Animation ---
    sidebarMenuItems.forEach(item => {
        item.addEventListener('click', function(event) {
            // Only apply animation if it's a navigation link and not the current active page
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

    // Initial load
    loadUsers(currentUserPage);
    applyTranslation(currentLanguage); // Apply translation on initial load
});
