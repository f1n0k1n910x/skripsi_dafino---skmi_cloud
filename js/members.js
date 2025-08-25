// Global Chart instances to allow for updates
let activityChartInstance;
let membersChartInstance;
let dailyChartInstance;
let currentPage = 1;

// Variables for member detail modal pagination
let currentMemberId = 0;
let currentFilesPage = 1;
let currentActivitiesPage = 1;
const itemsPerPageModal = 5; // 5 items per page for recent files/activities

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

    // Members Dashboard
    'membersDashboardTitle': { 'id': 'Dasbor Anggota', 'en': 'Members Dashboard' },
    'totalMembers': { 'id': 'Total Anggota', 'en': 'Total Members' },
    'activeUsers': { 'id': 'Pengguna Aktif', 'en': 'Active Users' },
    'totalFiles': { 'id': 'Total File', 'en': 'Total Files' },
    'available': { 'id': 'Tersedia', 'en': 'Available' },
    'totalStorageUsed': { 'id': 'Total Penyimpanan Terpakai', 'en': 'Total Storage Used' },
    'ofUsed': { 'id': 'dari', 'en': 'of' }, // "of X used"
    'weeklyActivities': { 'id': 'Aktivitas Mingguan', 'en': 'Weekly Activities' },
    'activities': { 'id': 'Aktivitas', 'en': 'Activities' },

    // Member List
    'memberList': { 'id': 'Daftar Anggota', 'en': 'Member List' },
    'no': { 'id': 'No', 'en': 'No' },
    'fullName': { 'id': 'Nama Lengkap', 'en': 'Full Name' },
    'username': { 'id': 'Nama Pengguna', 'en': 'Username' },
    'email': { 'id': 'Email', 'en': 'Email' },
    'lastLoginTime': { 'id': 'Waktu Login Terakhir', 'en': 'Last Login Time' },
    'status': { 'id': 'Status', 'en': 'Status' },
    'neverLoggedIn': { 'id': 'Belum pernah login', 'en': 'Never logged in' },
    'online': { 'id': 'Online', 'en': 'Online' },
    'offline': { 'id': 'Offline', 'en': 'Offline' },
    'noMembersFound': { 'id': 'Tidak ada anggota ditemukan', 'en': 'No members found' },

    // Pagination
    'previous': { 'id': 'Sebelumnya', 'en': 'Previous' },
    'next': { 'id': 'Berikutnya', 'en': 'Next' },

    // Activity Overview
    'activityOverview': { 'id': 'Ikhtisar Aktivitas', 'en': 'Activity Overview' },
    'activityDistribution': { 'id': 'Distribusi Aktivitas', 'en': 'Activity Distribution' },
    'topMembersByTotalFiles': { 'id': 'Anggota Teratas berdasarkan Total File', 'en': 'Top Members by Total Files' },
    'dailyActivityTrend': { 'id': 'Tren Aktivitas Harian', 'en': 'Daily Activity Trend' },

    // User Activity & Profile
    'userActivityProfile': { 'id': 'Aktivitas Pengguna & Profil', 'en': 'User Activity & Profile' },
    'recentActivities': { 'id': 'Aktivitas Terbaru', 'en': 'Recent Activities' },
    'myMiniProfile': { 'id': 'Profil Mini Saya', 'en': 'My Mini Profile' },
    'name': { 'id': 'Nama', 'en': 'Name' },
    'totalFilesMini': { 'id': 'Total File', 'en': 'Total Files' },
    'totalFilesPublicMini': { 'id': 'Total File (Publik)', 'en': 'Total Files (Public)' },
    'storageUsedMini': { 'id': 'Penyimpanan Terpakai', 'en': 'Storage Used' },
    'weeklyActivitiesMini': { 'id': 'Aktivitas Mingguan', 'en': 'Weekly Activities' },

    // Activity Descriptions (for recent activities)
    'upload_file': { 'id': 'mengunggah file', 'en': 'uploaded a file' },
    'delete_file': { 'id': 'menghapus file', 'en': 'deleted a file' },
    'delete_folder': { 'id': 'menghapus folder', 'en': 'deleted a folder' },
    'rename_file': { 'id': 'mengganti nama file', 'en': 'renamed a file' },
    'rename_folder': { 'id': 'mengganti nama folder', 'en': 'renamed a folder' },
    'create_folder': { 'id': 'membuat folder', 'en': 'created a folder' },
    'archive': { 'id': 'mengarsipkan', 'en': 'archived' },
    'download': { 'id': 'mengunduh', 'en': 'downloaded' },
    'login': { 'id': 'masuk', 'en': 'logged in' },
    'share_link': { 'id': 'membagikan tautan', 'en': 'shared a link' },
    // Add more activity types as needed

    // Member Detail Modal
    'totalFilesModal': { 'id': 'Total File', 'en': 'Total Files' },
    'totalFilesPublicModal': { 'id': 'Total File (Publik)', 'en': 'Total Files (Public)' },
    'recentFilesModal': { 'id': 'File Terbaru', 'en': 'Recent Files' },
    'recentActivitiesModal': { 'id': 'Aktivitas Terbaru', 'en': 'Recent Activities' },
    'invalidMemberId': { 'id': 'ID anggota tidak valid.', 'en': 'Invalid member ID.' },
    'memberNotFound': { 'id': 'Anggota tidak ditemukan.', 'en': 'Member not found.' },
    'loadingRecentFiles': { 'id': 'Memuat file terbaru...', 'en': 'Loading recent files...' },
    'loadingRecentActivities': { 'id': 'Memuat aktivitas terbaru...', 'en': 'Loading recent activities...' },
    'noRecentFiles': { 'id': 'Tidak ada file terbaru.', 'en': 'No recent files.' },
    'noRecentActivities': { 'id': 'Tidak ada aktivitas terbaru.', 'en': 'No recent activities.' },
    'failedToLoadRecentFiles': { 'id': 'Gagal memuat file terbaru.', 'en': 'Failed to load recent files.' },
    'failedToLoadRecentActivities': { 'id': 'Gagal memuat aktivitas terbaru.', 'en': 'Failed to load recent activities.' },
};

let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

// Add specific text for "used" in different languages if needed
translations['usedTextId'] = 'terpakai';
translations['usedTextEn'] = 'used';

function applyTranslation(lang) {
    document.querySelectorAll('[data-lang-key]').forEach(element => {
        const key = element.getAttribute('data-lang-key');
        if (translations[key] && translations[key][lang]) {
            element.textContent = translations[key][lang];
        }
    });

    // Special handling for "of X used" text
    const ofUsedElement = document.querySelector('.storage-text-card');
    if (ofUsedElement) {
        const totalStorageBytesText = document.getElementById('totalStorageBytesCount').textContent;
        if (translations['ofUsed'] && translations['ofUsed'][lang]) {
            ofUsedElement.innerHTML = `${translations['ofUsed'][lang]} <span id="totalStorageBytesCount">${totalStorageBytesText}</span> ${translations['usedText' + (lang === 'id' ? 'Id' : 'En')]}`;
        }
    }

    // Update dynamic counts and texts (using phpData)
    document.getElementById('totalUsersCount').textContent = phpData.initialTotalUsers;
    document.getElementById('totalPublicFilesCount').textContent = phpData.initialTotalPublicFiles;
    document.getElementById('usedStorageBytesCount').textContent = formatBytes(phpData.initialUsedStorageBytes);
    document.getElementById('totalStorageBytesCount').textContent = formatBytes(phpData.initialTotalStorageBytes);
    document.getElementById('weeklyActivitiesCount').textContent = phpData.initialWeeklyActivities;

    // Update sidebar storage text
    document.getElementById('storageText').textContent = `${formatBytes(phpData.initialUsedStorageBytes)} ${translations['ofUsed'][lang]} ${formatBytes(phpData.initialTotalStorageBytes)} ${translations['usedText' + (lang === 'id' ? 'Id' : 'En')]}`;

    // Update recent activities descriptions
    document.querySelectorAll('[data-lang-activity-desc-key]').forEach(element => {
        const key = element.getAttribute('data-lang-activity-desc-key');
        // The original description from PHP already contains the full text.
        // We need to re-render it based on the activity_type key.
        // Find the corresponding activity from phpData.initialRecentActivities
        const activity = phpData.initialRecentActivities.find(act => act.activity_type === key && element.closest('li').querySelector('strong').textContent === htmlspecialchars(act.username));
        if (activity && translations[key] && translations[key][lang]) {
            element.textContent = translations[key][lang]; // Update only the activity description part
        }
    });

    // Update timestamps in recent activities
    document.querySelectorAll('.recent-activities .timestamp').forEach(element => {
        const timestamp = element.getAttribute('data-timestamp');
        if (timestamp) {
            element.textContent = time_elapsed_string(timestamp, lang);
        }
    });

    // Update member table status
    document.querySelectorAll('.member-table tbody tr').forEach(row => {
        const statusSpan = row.querySelector('.status-indicator + span');
        if (statusSpan) {
            const isOnline = statusSpan.getAttribute('data-lang-key') === 'online';
            statusSpan.textContent = translations[isOnline ? 'online' : 'offline'][lang];
        }
        const neverLoggedInSpan = row.querySelector('[data-lang-key="neverLoggedIn"]');
        if (neverLoggedInSpan) {
            neverLoggedInSpan.textContent = translations['neverLoggedIn'][lang];
        }
    });

    // Update modal content
    const memberDetailName = document.getElementById('memberDetailName');
    if (memberDetailName.textContent.includes("'s Profile")) { // Check if it's an English profile name
        const username = memberDetailName.textContent.replace("'s Profile", "");
        memberDetailName.textContent = `${username}${lang === 'id' ? "'s Profil" : "'s Profile"}`;
    } else if (memberDetailName.textContent.includes(" Profil")) { // Check if it's an Indonesian profile name
        const username = memberDetailName.textContent.replace(" Profil", "");
        memberDetailName.textContent = `${username}${lang === 'id' ? "'s Profil" : "'s Profile"}`;
    }

    // Update modal pagination buttons
    document.querySelectorAll('#memberDetailModal .modal-pagination-controls button').forEach(button => {
        const span = button.querySelector('span');
        if (span) {
            const key = span.getAttribute('data-lang-key');
            if (translations[key] && translations[key][lang]) {
                span.textContent = translations[key][lang];
            }
        }
    });
}

// Function to open modal
function openModal(modalElement) {
    modalElement.classList.add('show');
}

// Function to close modal
function closeModal(modalElement) {
    modalElement.classList.remove('show');
}

// Close buttons for modals
document.querySelectorAll('.close-button').forEach(button => {
    button.addEventListener('click', () => {
        closeModal(document.getElementById('memberDetailModal'));
    });
});

// Close modal when clicking outside content
window.addEventListener('click', (event) => {
    const memberDetailModal = document.getElementById('memberDetailModal');
    if (event.target == memberDetailModal) {
        closeModal(memberDetailModal);
    }
    // Close mobile sidebar if overlay is clicked
    const sidebar = document.querySelector('.sidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');
    if (event.target == mobileOverlay && sidebar.classList.contains('show-mobile-sidebar')) {
        sidebar.classList.remove('show-mobile-sidebar');
        mobileOverlay.classList.remove('show');
    }
});

// Function to show member detail
async function showMemberDetail(memberId) {
    currentMemberId = memberId; // Set the global member ID
    currentFilesPage = 1; // Reset to first page for files
    currentActivitiesPage = 1; // Reset to first page for activities

    const memberDetailModal = document.getElementById('memberDetailModal');
    const memberDetailName = document.getElementById('memberDetailName');
    const memberTotalFiles = document.getElementById('memberTotalFiles');
    const memberTotalPublicFiles = document.getElementById('memberTotalPublicFiles');
    const recentFilesList = document.getElementById('recentFilesList');
    const recentActivitiesList = document.getElementById('recentActivitiesList');

    memberDetailName.textContent = translations['loadingRecentFiles'][currentLanguage] || 'Loading...';
    memberTotalFiles.textContent = '...';
    memberTotalPublicFiles.textContent = '...';
    recentFilesList.innerHTML = `<p>${translations['loadingRecentFiles'][currentLanguage] || 'Loading recent files...'}</p>`;
    recentActivitiesList.innerHTML = `<p>${translations['loadingRecentActivities'][currentLanguage] || 'Loading recent activities...'}</p>`;
    openModal(memberDetailModal);

    // Load initial data for both paginated sections
    await fetchMemberDetailsPaginated(memberId, currentFilesPage, currentActivitiesPage);
}

// Function to fetch paginated member details
async function fetchMemberDetailsPaginated(memberId, filesPage, activitiesPage) {
    const memberDetailName = document.getElementById('memberDetailName');
    const memberTotalFiles = document.getElementById('memberTotalFiles');
    const memberTotalPublicFiles = document.getElementById('memberTotalPublicFiles');
    const recentFilesList = document.getElementById('recentFilesList');
    const recentActivitiesList = document.getElementById('recentActivitiesList');

    try {
        const response = await fetch(`members.php?action=get_member_details_paginated&id=${memberId}&files_page=${filesPage}&activities_page=${activitiesPage}`);
        const data = await response.json();

        if (data.success) {
            const member = data.member;
            memberDetailName.textContent = `${htmlspecialchars(member.username)}${currentLanguage === 'id' ? "'s Profil" : "'s Profile"}`;
            memberTotalFiles.textContent = member.total_files;
            memberTotalPublicFiles.textContent = member.total_public_files;

            // Render Recent Files
            recentFilesList.innerHTML = '';
            if (member.recent_files.length > 0) {
                member.recent_files.forEach(file => {
                    recentFilesList.innerHTML += `<li><i class="fas ${getFileIconClass(file.file_name)}"></i> ${htmlspecialchars(file.file_name)} (${formatBytes(file.file_size)})</li>`;
                });
            } else {
                recentFilesList.innerHTML = `<li data-lang-key="noRecentFiles">${translations['noRecentFiles'][currentLanguage] || 'No recent files.'}</li>`;
            }
            updateFilesPagination(filesPage, member.total_files_pages);

            // Render Recent Activities
            recentActivitiesList.innerHTML = '';
            if (member.recent_activities.length > 0) {
                member.recent_activities.forEach(activity => {
                    let icon = 'fas fa-info-circle';
                    switch (activity.activity_type) {
                        case 'upload_file': icon = 'fas fa-upload'; break;
                        case 'delete_file': icon = 'fas fa-trash'; break;
                        case 'delete_folder': icon = 'fas fa-trash'; break;
                        case 'rename_file': icon = 'fas fa-pen'; break;
                        case 'rename_folder': icon = 'fas fa-pen'; break;
                        case 'create_folder': icon = 'fas fa-folder-plus'; break;
                        case 'archive': icon = 'fas fa-archive'; break;
                        case 'download': icon = 'fas fa-download'; break;
                        case 'login': icon = 'fas fa-sign-in-alt'; break;
                        case 'share_link': icon = 'fas fa-share-alt'; break;
                        default: icon = 'fas fa-info-circle'; break;
                    }
                    const activityDescription = translations[activity.activity_type] ? translations[activity.activity_type][currentLanguage] : activity.description;
                    recentActivitiesList.innerHTML += `<li><i class="${icon}"></i> ${htmlspecialchars(activityDescription)} <span class="timestamp">${time_elapsed_string(activity.timestamp, currentLanguage)}</span></li>`;
                });
            } else {
                recentActivitiesList.innerHTML = `<li data-lang-key="noRecentActivities">${translations['noRecentActivities'][currentLanguage] || 'No recent activities.'}</li>`;
            }
            updateActivitiesPagination(activitiesPage, member.total_activities_pages);

        } else {
            memberDetailName.textContent = 'Error';
            memberTotalFiles.textContent = 'N/A';
            memberTotalPublicFiles.textContent = 'N/A';
            recentFilesList.innerHTML = `<p>${translations['failedToLoadRecentFiles'][currentLanguage] || 'Failed to load recent files.'}</p>`;
            recentActivitiesList.innerHTML = `<p>${translations['failedToLoadRecentActivities'][currentLanguage] || 'Failed to load recent activities.'}</p>`;
            updateFilesPagination(1, 1); // Reset pagination on error
            updateActivitiesPagination(1, 1); // Reset pagination on error
        }
    } catch (error) {
        console.error('Error fetching member details:', error);
        memberDetailName.textContent = 'Error';
        memberTotalFiles.textContent = 'N/A';
        memberTotalPublicFiles.textContent = 'N/A';
        recentFilesList.innerHTML = `<p>${translations['failedToLoadRecentFiles'][currentLanguage] || 'Failed to load recent files.'}</p>`;
        recentActivitiesList.innerHTML = `<p>${translations['failedToLoadRecentActivities'][currentLanguage] || 'Failed to load recent activities.'}</p>`;
        updateFilesPagination(1, 1); // Reset pagination on error
        updateActivitiesPagination(1, 1); // Reset pagination on error
    }
}

// Pagination controls for Recent Files
document.getElementById('prevFilesPageBtn').addEventListener('click', () => {
    if (currentFilesPage > 1) {
        currentFilesPage--;
        fetchMemberDetailsPaginated(currentMemberId, currentFilesPage, currentActivitiesPage);
    }
});
document.getElementById('nextFilesPageBtn').addEventListener('click', () => {
    const totalPages = parseInt(document.getElementById('totalFilesPages').textContent);
    if (currentFilesPage < totalPages) {
        currentFilesPage++;
        fetchMemberDetailsPaginated(currentMemberId, currentFilesPage, currentActivitiesPage);
    }
});

// Pagination controls for Recent Activities
document.getElementById('prevActivitiesPageBtn').addEventListener('click', () => {
    if (currentActivitiesPage > 1) {
        currentActivitiesPage--;
        fetchMemberDetailsPaginated(currentMemberId, currentFilesPage, currentActivitiesPage);
    }
});
document.getElementById('nextActivitiesPageBtn').addEventListener('click', () => {
    const totalPages = parseInt(document.getElementById('totalActivitiesPages').textContent);
    if (currentActivitiesPage < totalPages) {
        currentActivitiesPage++;
        fetchMemberDetailsPaginated(currentMemberId, currentFilesPage, currentActivitiesPage);
    }
});

function updateFilesPagination(currentPage, totalPages) {
    document.getElementById('currentFilesPage').textContent = currentPage;
    document.getElementById('totalFilesPages').textContent = totalPages;
    document.getElementById('prevFilesPageBtn').disabled = currentPage === 1;
    document.getElementById('nextFilesPageBtn').disabled = currentPage === totalPages;
}

function updateActivitiesPagination(currentPage, totalPages) {
    document.getElementById('currentActivitiesPage').textContent = currentPage;
    document.getElementById('totalActivitiesPages').textContent = totalPages;
    document.getElementById('prevActivitiesPageBtn').disabled = currentPage === 1;
    document.getElementById('nextActivitiesPageBtn').disabled = currentPage === totalPages;
}


// Helper function for HTML escaping
function htmlspecialchars(str) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return str.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Helper function for time elapsed string (replicate PHP's time_elapsed_string)
function time_elapsed_string(datetime, lang = 'en') {
    const now = new Date();
    const then = new Date(datetime.replace(/-/g, '/')); // Handle different date formats
    const seconds = Math.floor((now - then) / 1000);

    let interval;
    let unit;
    let value;

    if (seconds < 60) {
        value = seconds;
        unit = lang === 'id' ? 'detik' : 'second';
    } else if (seconds < 3600) {
        value = Math.floor(seconds / 60);
        unit = lang === 'id' ? 'menit' : 'minute';
    } else if (seconds < 86400) {
        value = Math.floor(seconds / 3600);
        unit = lang === 'id' ? 'jam' : 'hour';
    } else if (seconds < 2592000) { // 30 days
        value = Math.floor(seconds / 86400);
        unit = lang === 'id' ? 'hari' : 'day';
    } else if (seconds < 31536000) { // 365 days
        value = Math.floor(seconds / 2592000);
        unit = lang === 'id' ? 'bulan' : 'month';
    } else {
        value = Math.floor(seconds / 31536000);
        unit = lang === 'id' ? 'tahun' : 'year';
    }

    const plural = (value > 1 && lang === 'en') ? 's' : '';
    const ago = lang === 'id' ? 'yang lalu' : 'ago';

    return `${value} ${unit}${plural} ${ago}`;
}

// Function to get file icon class based on extension (replicate PHP's getFileIconClassPhp)
function getFileIconClass(fileName) {
    const ext = fileName.split('.').pop().toLowerCase();
    switch (ext) {
        case 'pdf': return 'fa-file-pdf';
        case 'doc': case 'docx': return 'fa-file-word';
        case 'xls': case 'xlsx': return 'fa-file-excel';
        case 'ppt': case 'pptx': return 'fa-file-powerpoint';
        case 'txt': case 'md': case 'log': case 'csv': case 'tex': return 'fa-file-alt';
        case 'jpg': case 'jpeg': case 'png': case 'gif': case 'bmp': case 'webp': case 'svg': case 'tiff': return 'fa-file-image';
        case 'zip': case 'rar': case '7z': case 'tar': case 'gz': case 'bz2': case 'xz': case 'iso': case 'dmg': case 'cab': case 'arj': return 'fa-file-archive';
        case 'mp3': case 'wav': case 'ogg': case 'flac': case 'aac': case 'm4a': case 'alac': case 'wma': case 'opus': case 'amr': case 'mid': return 'fa-file-audio';
        case 'mp4': case 'avi': case 'mov': case 'wmv': case 'flv': case 'mkv': case 'webm': case '3gp': case 'm4v': case 'mpg': case 'mpeg': case 'ts': case 'ogv': return 'fa-file-video';
        case 'html': case 'htm': case 'css': case 'js': case 'php': case 'py': case 'java': case 'c': case 'cpp': case 'h': case 'json': case 'xml': case 'sql': case 'sh': case 'ts': case 'tsx': case 'jsx': case 'vue': case 'cs': case 'rb': case 'go': case 'swift': case 'bat': case 'ini': case 'yml': case 'yaml': case 'pl': case 'r': return 'fa-file-code';
        case 'exe': case 'msi': case 'apk': case 'ipa': case 'jar': case 'appimage': case 'bin': return 'fa-box';
        case 'torrent': case 'nzb': case 'ed2k': case 'part': case '!ut': return 'fa-magnet';
        default: return 'fa-file';
    }
}

// Helper function for formatBytes (replicate PHP's formatBytes)
function formatBytes(bytes, precision = 2) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    bytes = Math.max(bytes, 0);
    const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
    const unitIndex = Math.min(pow, units.length - 1);
    bytes /= (1 << (10 * unitIndex));
    return bytes.toFixed(precision) + ' ' + units[unitIndex];
}

// Function to update dashboard UI with new data
function updateDashboardUI(data) {
    // Update Summary Statistics Cards
    document.getElementById('totalUsersCount').textContent = data.totalUsers;
    document.getElementById('totalPublicFilesCount').textContent = data.totalPublicFiles;
    document.getElementById('usedStorageBytesCount').textContent = formatBytes(data.usedStorageBytes);
    document.getElementById('totalStorageBytesCount').textContent = formatBytes(data.totalStorageBytes);
    document.getElementById('weeklyActivitiesCount').textContent = data.weeklyActivities;

    // Update Storage Info in Sidebar
    document.querySelector('.progress-bar').style.width = `${data.usedPercentage.toFixed(2)}%`;
    document.querySelector('.progress-bar-text').textContent = `${data.usedPercentage.toFixed(2)}%`; // Update text inside progress bar
    document.getElementById('storageText').textContent = `${formatBytes(data.usedStorageBytes)} ${translations['ofUsed'][currentLanguage]} ${formatBytes(data.totalStorageBytes)} ${translations['usedText' + (currentLanguage === 'id' ? 'Id' : 'En')]}`;

    // Update storage full message in sidebar
    const storageInfoDiv = document.querySelector('.storage-info');
    let storageFullMessage = storageInfoDiv.querySelector('.storage-full-message');
    if (data.isStorageFull) {
        if (!storageFullMessage) {
            storageFullMessage = document.createElement('p');
            storageFullMessage.className = 'storage-text storage-full-message';
            storageFullMessage.style.color = 'var(--error-color)';
            storageFullMessage.style.fontWeight = 'bold';
            storageFullMessage.setAttribute('data-lang-key', 'storageFull');
            storageInfoDiv.appendChild(storageFullMessage);
        }
    } else {
        if (storageFullMessage) {
            storageFullMessage.remove();
        }
    }

    // Update Charts
    updateCharts(data.activityDistribution, data.topMembersPublicFiles, data.dailyActivities);

    // Update Recent Activities
    const recentActivitiesUl = document.querySelector('#recentActivitiesSection ul');
    recentActivitiesUl.innerHTML = ''; // Clear existing activities
    if (data.recentActivities.length > 0) {
        data.recentActivities.forEach(activity => {
            let icon = 'fas fa-info-circle';
            switch (activity.activity_type) {
                case 'upload_file': icon = 'fas fa-upload'; break;
                case 'delete_file': icon = 'fas fa-trash'; break;
                case 'delete_folder': icon = 'fas fa-trash'; break;
                case 'rename_file': icon = 'fas fa-pen'; break;
                case 'rename_folder': icon = 'fas fa-pen'; break;
                case 'create_folder': icon = 'fas fa-folder-plus'; break;
                case 'archive': icon = 'fas fa-archive'; break;
                case 'download': icon = 'fas fa-download'; break;
                case 'login': icon = 'fas fa-sign-in-alt'; break;
                case 'share_link': icon = 'fas fa-share-alt'; break;
                default: icon = 'fas fa-info-circle'; break;
            }
            const activityDescription = translations[activity.activity_type] ? translations[activity.activity_type][currentLanguage] : activity.description;
            const activityLi = `
                <li>
                    <i class="${icon}"></i>
                    <span class="activity-text"><strong>${htmlspecialchars(activity.username)}</strong> <span data-lang-activity-desc-key="${activity.activity_type}">${htmlspecialchars(activityDescription)}</span></span>
                    <span class="timestamp" data-timestamp="${activity.timestamp}"></span>
                </li>
            `;
            recentActivitiesUl.innerHTML += activityLi;
        });
    } else {
        recentActivitiesUl.innerHTML = `<li data-lang-key="noRecentActivities">${translations['noRecentActivities'][currentLanguage] || 'No recent activities.'}</li>`;
    }

    // Update Mini Profile
    const miniProfileSection = document.getElementById('miniProfileSection');
    miniProfileSection.querySelector('#miniProfileUsername').textContent = htmlspecialchars(data.currentUserProfile.username);
    miniProfileSection.querySelector('#miniProfileTotalFiles').textContent = data.currentUserProfile.total_files;
    miniProfileSection.querySelector('#miniProfilePublicFiles').textContent = data.currentUserProfile.public_files;
    miniProfileSection.querySelector('#miniProfileStorageUsed').textContent = data.currentUserProfile.storage_used;
    miniProfileSection.querySelector('#miniProfileWeeklyActivities').textContent = data.currentUserProfile.weekly_activities;

    // Re-apply translation after UI update
    applyTranslation(currentLanguage);
}

// --- PAGINATION FUNCTIONS ---
async function fetchMembers(page) {
    try {
        const response = await fetch(`members.php?action=get_members&page=${page}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
            currentPage = page;
            renderMemberTable(data.members);
            updatePaginationControls();
            applyTranslation(currentLanguage); // Apply translation after rendering table
        }
    } catch (error) {
        console.error("Could not fetch members data:", error);
    }
}

function renderMemberTable(members) {
    const memberTableBody = document.querySelector('#memberTable tbody');
    memberTableBody.innerHTML = ''; // Clear existing rows
    if (members.length > 0) {
        const startNum = (currentPage - 1) * phpData.membersPerPage;
        members.forEach((member, index) => {
            const row = `
                <tr data-member-id="${member.id}">
                    <td>${startNum + index + 1}</td>
                    <td>${htmlspecialchars(member.full_name)}</td>
                    <td>${htmlspecialchars(member.username)}</td>
                    <td>${htmlspecialchars(member.email)}</td>
                    <td>
                        ${member.last_login ? new Date(member.last_login.replace(/-/g, '/')).toLocaleString() : `<span data-lang-key="neverLoggedIn">${translations['neverLoggedIn'][currentLanguage]}</span>`}
                    </td>
                    <td>
                        <span class="status-indicator ${member.is_online ? 'online' : 'offline'}"></span>
                        <span data-lang-key="${member.is_online ? 'online' : 'offline'}">${translations[member.is_online ? 'online' : 'offline'][currentLanguage]}</span>
                    </td>
                </tr>
            `;
            memberTableBody.innerHTML += row;
        });
    } else {
        memberTableBody.innerHTML = `<tr><td colspan="6" class="text-center" data-lang-key="noMembersFound">${translations['noMembersFound'][currentLanguage]}</td></tr>`;
    }
    attachMemberRowClickListeners();
}

function setupPagination() {
    const paginationContainer = document.getElementById('pageNumbers');
    paginationContainer.innerHTML = ''; // Clear existing page buttons
    for (let i = 1; i <= phpData.totalPages; i++) {
        const button = document.createElement('button');
        button.className = 'page-number-btn';
        button.textContent = i;
        button.dataset.page = i;
        if (i === currentPage) {
            button.classList.add('active');
        }
        button.addEventListener('click', () => {
            fetchMembers(i);
        });
        paginationContainer.appendChild(button);
    }
}

function updatePaginationControls() {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');

    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === phpData.totalPages;

    document.querySelectorAll('.page-number-btn').forEach(btn => {
        btn.classList.remove('active');
        if (parseInt(btn.dataset.page) === currentPage) {
            btn.classList.add('active');
        }
    });
}

// --- END PAGINATION FUNCTIONS ---

// Function to update Chart.js instances
function updateCharts(activityDistribution, topMembersPublicFiles, dailyActivities) {
    // Activity Distribution Pie Chart
    const activityCtx = document.getElementById('activityDistributionChart').getContext('2d');
    const activityData = Object.values(activityDistribution);
    const activityLabels = Object.keys(activityDistribution).map(key => translations[key] ? translations[key][currentLanguage] : key);
    const activityColors = [
        '#3F51B5', '#4CAF50', '#FFC107', '#F44336', '#9C27B0', '#00BCD4', '#FFEB3B', '#607D8B'
    ]; // Material Design colors

    if (activityChartInstance) {
        activityChartInstance.data.labels = activityLabels;
        activityChartInstance.data.datasets[0].data = activityData;
        activityChartInstance.data.datasets[0].backgroundColor = activityColors;
        activityChartInstance.update();
    } else {
        activityChartInstance = new Chart(activityCtx, {
            type: 'pie',
            data: {
                labels: activityLabels,
                datasets: [{
                    data: activityData,
                    backgroundColor: activityColors,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: 'var(--text-color)',
                            font: {
                                family: 'Roboto'
                            }
                        }
                    },
                    title: {
                        display: false,
                        text: translations['activityDistribution'][currentLanguage] || 'Activity Distribution'
                    }
                }
            }
        });
    }

    // Top Members by Public Files Bar Chart
    const membersCtx = document.getElementById('topMembersPublicFilesChart').getContext('2d');
    const membersLabels = topMembersPublicFiles.map(m => m.username);
    const membersData = topMembersPublicFiles.map(m => m.public_files_count);

    if (membersChartInstance) {
        membersChartInstance.data.labels = membersLabels;
        membersChartInstance.data.datasets[0].data = membersData;
        membersChartInstance.update();
    } else {
        membersChartInstance = new Chart(membersCtx, {
            type: 'bar',
            data: {
                labels: membersLabels,
                datasets: [{
                    label: translations['totalFiles'][currentLanguage] || 'Total Files',
                    data: membersData,
                    backgroundColor: 'var(--primary-color)',
                    borderColor: 'var(--primary-dark-color)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: false,
                        text: translations['topMembersByTotalFiles'][currentLanguage] || 'Top Members by Total Files'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: 'var(--text-color)',
                            font: {
                                family: 'Roboto'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            color: 'var(--text-color)',
                            font: {
                                family: 'Roboto'
                            }
                        }
                    }
                }
            }
        });
    }

    // Daily Activity Line Chart
    const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');
    const dailyLabels = Object.keys(dailyActivities);
    const dailyData = Object.values(dailyActivities);

    if (dailyChartInstance) {
        dailyChartInstance.data.labels = dailyLabels;
        dailyChartInstance.data.datasets[0].data = dailyData;
        dailyChartInstance.update();
    } else {
        dailyChartInstance = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: translations['activities'][currentLanguage] || 'Activities',
                    data: dailyData,
                    borderColor: 'var(--success-color)',
                    backgroundColor: 'rgba(76, 175, 80, 0.2)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: false,
                        text: translations['dailyActivityTrend'][currentLanguage] || 'Daily Activity Trend'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: 'var(--text-color)',
                            font: {
                                family: 'Roboto'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            color: 'var(--text-color)',
                            font: {
                                family: 'Roboto'
                            }
                        }
                    }
                }
            }
        });
    }
}

// Function to fetch dashboard data via AJAX
async function fetchDashboardData() {
    try {
        const response = await fetch('members.php?action=get_dashboard_data');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        updateDashboardUI(data);
    } catch (error) {
        console.error("Could not fetch dashboard data:", error);
        // Optionally display an error message to the user
    }
}

// Attach click listeners to member table rows
function attachMemberRowClickListeners() {
    document.querySelectorAll('#memberTable tbody tr').forEach(row => {
        row.addEventListener('click', function() {
            const memberId = this.dataset.memberId;
            if (memberId) {
                showMemberDetail(memberId);
            }
        });
    });
}

// --- Responsive Class Handling ---
function applyDeviceClass() {
    const width = window.innerWidth;
    const body = document.body;
    const sidebar = document.querySelector('.sidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');

    // Remove all previous device classes
    body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop');

    if (width <= 767) {
        body.classList.add('mobile');
        sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default
    } else if (width >= 768 && width <= 1024) {
        if (window.matchMedia("(orientation: portrait)").matches) {
            body.classList.add('tablet-portrait');
            sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default
        } else {
            body.classList.add('tablet-landscape');
            sidebar.classList.remove('mobile-hidden'); // Show sidebar
            sidebar.classList.remove('show-mobile-sidebar'); // Ensure mobile sidebar is closed
            mobileOverlay.classList.remove('show'); // Hide overlay
        }
    } else {
        body.classList.add('desktop');
        sidebar.classList.remove('mobile-hidden'); // Show sidebar
        sidebar.classList.remove('show-mobile-sidebar'); // Ensure mobile sidebar is closed
        mobileOverlay.classList.remove('show'); // Hide overlay
    }
}

// Initial load and setup
document.addEventListener('DOMContentLoaded', function() {
    // Get language from localStorage
    currentLanguage = localStorage.getItem('lang') || 'id';

    // Initial chart rendering with data from PHP
    updateCharts(
        phpData.initialActivityDistribution,
        phpData.initialTopMembersPublicFiles,
        phpData.initialDailyActivities
    );

    // PAGINATION SETUP
    renderMemberTable(phpData.initialMembers);
    setupPagination();
    updatePaginationControls();

    // Pagination button event listeners
    document.getElementById('prevPageBtn').addEventListener('click', () => {
        if (currentPage > 1) {
            fetchMembers(currentPage - 1);
        }
    });

    document.getElementById('nextPageBtn').addEventListener('click', () => {
        if (currentPage < phpData.totalPages) {
            fetchMembers(currentPage + 1);
        }
    });

    // Attach click listeners to member table rows
    attachMemberRowClickListeners();

    // Fetch and update data every 30 seconds (example)
    setInterval(fetchDashboardData, 30000); // Refresh every 30 seconds

    // Initial application of device class
    applyDeviceClass();
    window.addEventListener('resize', applyDeviceClass);
    window.addEventListener('orientationchange', applyDeviceClass);

    // Mobile sidebar elements
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mainContent = document.getElementById('mainContent'); // Get main-content for animations

    // --- Mobile Sidebar Toggle ---
    sidebarToggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show-mobile-sidebar');
        mobileOverlay.classList.toggle('show');
    });

    // --- Sidebar Menu Navigation with Fly Out Animation ---
    document.querySelectorAll('.sidebar-menu a').forEach(item => {
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

    // Set active class for current page in sidebar
    const currentPagePath = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-menu a').forEach(item => {
        item.classList.remove('active');
        const itemHref = item.getAttribute('href');
        if (itemHref === currentPagePath) {
            item.classList.add('active');
        }
    });

    // Apply initial translation
    applyTranslation(currentLanguage);
});
