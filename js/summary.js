let uploadsPerMonthChart;
let fileTypeChart;
let storageUsageChart;

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

    // Summary Dashboard
    'activityLogTitle': { 'id': 'Log Aktivitas', 'en': 'Activity Log' },
    'totalFilesUploaded': { 'id': 'Total File Diunggah', 'en': 'Total Files Uploaded' },
    'totalFolders': { 'id': 'Total Folder', 'en': 'Total Folders' },
    'totalStorageUsed': { 'id': 'Total Penyimpanan Terpakai', 'en': 'Total Storage Used' },
    'ofUsedText': { 'id': 'dari', 'en': 'of' }, // "of X used"
    'storageUsedPercentage': { 'id': 'Penyimpanan Terpakai (%)', 'en': 'Storage Used (%)' },
    'uploadsPerMonth': { 'id': 'Unggahan Per Bulan', 'en': 'Uploads Per Month' },
    'fileTypeDistribution': { 'id': 'Distribusi Tipe File', 'en': 'File Type Distribution' },
    'lastUploadedFiles': { 'id': 'File Terakhir Diunggah', 'en': 'Last Uploaded Files' },
    'noRecentUploads': { 'id': 'Tidak ada unggahan terbaru.', 'en': 'No recent uploads.' },
    'storageUsagePerMonth': { 'id': 'Penggunaan Penyimpanan Per Bulan', 'en': 'Storage Usage Per Bulan' },
    'filesUploaded': { 'id': 'File Diunggah', 'en': 'Files Uploaded' },
    'numberofFiles': { 'id': 'Jumlah File', 'en': 'Number of Files' },
    'month': { 'id': 'Bulan', 'en': 'Month' },
    'storageUsed': { 'id': 'Penyimpanan Terpakai', 'en': 'Storage Used' },
};

let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

function applyTranslation(lang) {
    document.querySelectorAll('[data-lang-key]').forEach(element => {
        const key = element.getAttribute('data-lang-key');
        if (translations[key] && translations[key][lang]) {
            element.textContent = translations[key][lang];
        }
    });

    // Special handling for "of X used" text in cards
    const totalStorageCapacityElement = document.getElementById('totalStorageCapacity');
    if (totalStorageCapacityElement) {
        // Ambil teks yang sudah ada, pisahkan untuk mendapatkan nilai total storage
        const currentText = totalStorageCapacityElement.textContent;
        const totalStorageMatch = currentText.match(/(\d+(\.\d+)?\s*(B|KB|MB|GB|TB))/);
        const totalStorageValue = totalStorageMatch ? totalStorageMatch[0] : '500 GB'; // Default jika tidak ditemukan

        if (translations['ofUsedText'] && translations['ofUsedText'][lang]) {
            totalStorageCapacityElement.textContent = `${translations['ofUsedText'][lang]} ${totalStorageValue} ${lang === 'id' ? 'terpakai' : 'used'}`;
        }
    }

    // Update sidebar storage text
    const storageTextElement = document.getElementById('storageText');
    if (storageTextElement) {
        // Ambil teks yang sudah ada, pisahkan untuk mendapatkan nilai used dan total storage
        const currentText = storageTextElement.textContent;
        const parts = currentText.split(' ');
        const usedStorageValue = parts[0] + ' ' + parts[1]; // e.g., "100 MB"
        const totalStorageValue = parts[3] + ' ' + parts[4]; // e.g., "500 GB"

        if (translations['ofUsedText'] && translations['ofUsedText'][lang]) {
            storageTextElement.textContent = `${usedStorageValue} ${translations['ofUsedText'][lang]} ${totalStorageValue} ${lang === 'id' ? 'terpakai' : 'used'}`;
        }
    }

    // Update chart labels if charts are already rendered
    if (uploadsPerMonthChart) {
        uploadsPerMonthChart.data.datasets[0].label = translations['filesUploaded'][lang];
        uploadsPerMonthChart.options.scales.y.title.text = translations['numberofFiles'][lang];
        uploadsPerMonthChart.options.scales.x.title.text = translations['month'][lang];
        uploadsPerMonthChart.update();
    }
    if (fileTypeChart) {
        // Doughnut chart labels are dynamic, no direct translation needed for legend labels
        fileTypeChart.update();
    }
    if (storageUsageChart) {
        storageUsageChart.data.datasets[0].label = translations['storageUsed'][lang];
        storageUsageChart.options.scales.y.title.text = translations['storageUsed'][lang];
        storageUsageChart.options.scales.x.title.text = translations['month'][lang];
        storageUsageChart.update();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Get language from localStorage
    currentLanguage = localStorage.getItem('lang') || 'id';

    // Initial chart rendering using data passed from PHP
    renderCharts(
        initialChartData.monthLabels,
        initialChartData.monthData,
        initialChartData.fileTypeLabels,
        initialChartData.fileTypeData,
        initialChartData.storageMonthLabels,
        initialChartData.storageMonthData
    );

    // Fetch and update dashboard data every 30 seconds
    setInterval(updateDashboardData, 30000); // Update every 30 seconds

    // Mobile sidebar elements
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mainContent = document.getElementById('mainContent'); // Get main-content for animations

    // Sidebar menu items for active state management
    const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');

    // --- Responsive Class Handling ---
    function applyDeviceClass() {
        const width = window.innerWidth;
        const body = document.body;

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

    // Initial application of device class
    applyDeviceClass();
    window.addEventListener('resize', applyDeviceClass);
    window.addEventListener('orientationchange', applyDeviceClass);

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

    // Set active class for current page in sidebar
    const currentPage = window.location.pathname.split('/').pop();
    sidebarMenuItems.forEach(item => {
        item.classList.remove('active');
        const itemHref = item.getAttribute('href');
        if (itemHref === currentPage || (currentPage === 'index.php' && itemHref === 'index.php')) {
            item.classList.add('active');
        }
    });

    // Apply initial translation
    applyTranslation(currentLanguage);
});

// Function to get file icon class based on extension (replicated from PHP for client-side rendering)
// This function should be kept in sync with getFontAwesomeIconClass in functions.php
function getFileIconClass(fileName) {
    const ext = fileName.split('.').pop().toLowerCase();
    const iconClasses = {
        // Documents
        'pdf': 'fa-file-pdf',
        'doc': 'fa-file-word',
        'docx': 'fa-file-word',
        'xls': 'fa-file-excel',
        'xlsx': 'fa-file-excel',
        'ppt': 'fa-file-powerpoint',
        'pptx': 'fa-file-powerpoint',
        'txt': 'fa-file-alt',
        'rtf': 'fa-file-alt',
        'md': 'fa-file-alt',
        'csv': 'fa-file-csv',
        'odt': 'fa-file-alt',
        'odp': 'fa-file-powerpoint',
        'log': 'fa-file-alt',
        'tex': 'fa-file-alt',

        // Images
        'jpg': 'fa-file-image',
        'jpeg': 'fa-file-image',
        'png': 'fa-file-image',
        'gif': 'fa-file-image',
        'bmp': 'fa-file-image',
        'webp': 'fa-file-image',
        'svg': 'fa-file-image',
        'tiff': 'fa-file-image',

        // Audio
        'mp3': 'fa-file-audio',
        'wav': 'fa-file-audio',
        'ogg': 'fa-file-audio',
        'flac': 'fa-file-audio',
        'aac': 'fa-file-audio',
        'm4a': 'fa-file-audio',
        'alac': 'fa-file-audio',
        'wma': 'fa-file-audio',
        'opus': 'fa-file-audio',
        'amr': 'fa-file-audio',
        'mid': 'fa-file-audio',

        // Video
        'mp4': 'fa-file-video',
        'avi': 'fa-file-video',
        'mov': 'fa-file-video',
        'wmv': 'fa-file-video',
        'flv': 'fa-file-video',
        'webm': 'fa-file-video',
        '3gp': 'fa-file-video',
        'm4v': 'fa-file-video',
        'mpg': 'fa-file-video',
        'mpeg': 'fa-file-video',
        'ts': 'fa-file-video',
        'ogv': 'fa-file-video',

        // Archives
        'zip': 'fa-file-archive',
        'rar': 'fa-file-archive',
        '7z': 'fa-file-archive',
        'tar': 'fa-file-archive',
        'gz': 'fa-file-archive',
        'bz2': 'fa-file-archive',
        'xz': 'fa-file-archive',
        'iso': 'fa-file-archive',
        'cab': 'fa-file-archive',
        'arj': 'fa-file-archive',

        // Code
        'html': 'fa-file-code',
        'htm': 'fa-file-code',
        'css': 'fa-file-code',
        'js': 'fa-file-code',
        'php': 'fa-file-code',
        'py': 'fa-file-code',
        'java': 'fa-file-code',
        'json': 'fa-file-code',
        'xml': 'fa-file-code',
        'ts': 'fa-file-code',
        'tsx': 'fa-file-code',
        'jsx': 'fa-file-code',
        'vue': 'fa-file-code',
        'cpp': 'fa-file-code',
        'c': 'fa-file-code',
        'cs': 'fa-file-code',
        'rb': 'fa-file-code',
        'go': 'fa-file-code',
        'swift': 'fa-file-code',
        'sql': 'fa-database', // SQL uses fa-database in PHP, keep consistent
        'sh': 'fa-file-code',
        'bat': 'fa-file-code',
        'ini': 'fa-file-code',
        'yml': 'fa-file-code',
        'yaml': 'fa-file-code',
        'pl': 'fa-file-code',
        'r': 'fa-file-code',

        // Installation
        'exe': 'fa-box',
        'msi': 'fa-box',
        'apk': 'fa-box',
        'ipa': 'fa-box',
        'jar': 'fa-box',
        'appimage': 'fa-box',
        'dmg': 'fa-box',
        'bin': 'fa-box',

        // P2P
        'torrent': 'fa-magnet',
        'nzb': 'fa-magnet',
        'ed2k': 'fa-magnet',
        'part': 'fa-magnet',
        '!ut': 'fa-magnet',

        // Default
        'default': 'fa-file'
    };

    return `fa-solid ${iconClasses[ext] || iconClasses['default']} ${ext}`; // Tambahkan 'fa-solid' dan kelas ekstensi untuk styling
}

// Helper function to format bytes (replicate from PHP's formatBytes)
function formatBytes(bytes, precision = 2) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB']; // Keep consistent with PHP
    bytes = Math.max(bytes, 0);
    const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
    const p = Math.min(pow, units.length - 1);
    bytes /= (1 << (10 * p));
    return bytes.toFixed(precision) + ' ' + units[p];
}

function renderCharts(monthLabels, monthData, fileTypeLabels, fileTypeData, storageMonthLabels, storageMonthData) {
    // Destroy existing charts if they exist
    if (uploadsPerMonthChart) uploadsPerMonthChart.destroy();
    if (fileTypeChart) fileTypeChart.destroy();
    if (storageUsageChart) storageUsageChart.destroy();

    // Uploads Per Month Chart
    const uploadsPerMonthCtx = document.getElementById('uploadsPerMonthChart').getContext('2d');
    uploadsPerMonthChart = new Chart(uploadsPerMonthCtx, {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [{
                label: translations['filesUploaded'][currentLanguage] || 'Files Uploaded',
                data: monthData,
                backgroundColor: 'var(--primary-color)', // Use Material primary color
                borderColor: 'var(--primary-color)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: translations['numberofFiles'][currentLanguage] || 'Number of Files',
                        color: 'var(--text-color)'
                    },
                    ticks: {
                        color: 'var(--secondary-text-color)'
                    },
                    grid: {
                        color: 'var(--divider-color)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: translations['month'][currentLanguage] || 'Month',
                        color: 'var(--text-color)'
                    },
                    ticks: {
                        color: 'var(--secondary-text-color)'
                    },
                    grid: {
                        color: 'var(--divider-color)'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: 'var(--text-color)'
                    }
                }
            }
        }
    });

    // File Type Distribution Chart
    const fileTypeCtx = document.getElementById('fileTypeChart').getContext('2d');
    const fileTypeColors = [
        '#E81123', '#0078D7', '#4CAF50', '#FF8C00', '#8E24AA', /* Material-inspired colors */
        '#F7B500', '#666666', '#00B294', '#D24726', '#2B579A',
        '#107C10', '#FFB900', '#505050', '#999999', '#0056b3',
        '#C8C8C8', '#E1E1E1', '#2D2D30', '#333333', '#F0F0F0'
    ];
    fileTypeChart = new Chart(fileTypeCtx, {
        type: 'doughnut',
        data: {
            labels: fileTypeLabels,
            datasets: [{
                data: fileTypeData,
                backgroundColor: fileTypeColors,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: 'var(--text-color)'
                    }
                },
                title: {
                    display: false,
                    text: translations['fileTypeDistribution'][currentLanguage] || 'File Type Distribution'
                }
            }
        }
    });

    // Storage Usage Per Month Chart
    const storageUsageCtx = document.getElementById('storageUsageChart').getContext('2d');
    storageUsageChart = new Chart(storageUsageCtx, {
        type: 'bar',
        data: {
            labels: storageMonthLabels,
            datasets: [{
                label: translations['storageUsed'][currentLanguage] || 'Storage Used',
                data: storageMonthData,
                backgroundColor: 'var(--success-color)', // Use Material success color
                borderColor: 'var(--success-color)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: translations['month'][currentLanguage] || 'Month',
                        color: 'var(--text-color)'
                    },
                    ticks: {
                        color: 'var(--secondary-text-color)'
                    },
                    grid: {
                        color: 'var(--divider-color)'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: translations['storageUsed'][currentLanguage] || 'Storage Used',
                        color: 'var(--text-color)'
                    },
                    ticks: {
                        color: 'var(--secondary-text-color)',
                        callback: function(value, index, values) {
                            return formatBytes(value); // Custom formatting function
                        }
                    },
                    grid: {
                        color: 'var(--divider-color)'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: 'var(--text-color)'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += formatBytes(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
}

async function updateDashboardData() {
    try {
        const response = await fetch('summary.php?ajax=1');
        const data = await response.json();

        // Update dashboard cards
        document.getElementById('totalFilesCount').textContent = data.totalFiles;
        document.getElementById('totalFoldersCount').textContent = data.totalFolders;
        document.getElementById('totalStorageUsed').textContent = data.formattedUsedStorage;

        const totalStorageCapacityElement = document.getElementById('totalStorageCapacity');
        if (totalStorageCapacityElement) {
            totalStorageCapacityElement.textContent = `${translations['ofUsedText'][currentLanguage]} ${data.formattedTotalStorage} ${currentLanguage === 'id' ? 'terpakai' : 'used'}`;
        }

        document.getElementById('storageUsedPercentage').textContent = `${data.usedPercentage}%`;

        // Update sidebar storage info
        document.querySelector('.progress-bar').style.width = `${data.usedPercentage}%`;
        document.querySelector('.progress-bar-text').textContent = `${data.usedPercentage}%`;

        const storageTextElement = document.getElementById('storageText');
        if (storageTextElement) {
            storageTextElement.textContent = `${data.formattedUsedStorage} ${translations['ofUsedText'][currentLanguage]} ${data.formattedTotalStorage} ${currentLanguage === 'id' ? 'terpakai' : 'used'}`;
        }

        const storageInfoDiv = document.querySelector('.storage-info');
        let storageFullMessage = storageInfoDiv.querySelector('.storage-full-message');

        if (data.isStorageFull) {
            if (!storageFullMessage) {
                const p = document.createElement('p');
                p.className = 'storage-text storage-full-message';
                p.style.color = 'var(--error-color)';
                p.style.fontWeight = 'bold';
                p.setAttribute('data-lang-key', 'storageFull'); // Add data-lang-key for translation
                storageInfoDiv.appendChild(p);
            }
            // Update text if it exists (for translation)
            if (storageFullMessage) {
                storageFullMessage.textContent = translations['storageFull'][currentLanguage] || 'Storage Full!';
            }
        } else {
            if (storageFullMessage) {
                storageFullMessage.remove();
            }
        }


        // Update Last Uploaded Files list
        const lastUploadedFilesList = document.getElementById('lastUploadedFilesList');
        lastUploadedFilesList.innerHTML = '';
        if (data.lastUploadedFiles.length === 0) {
            const li = document.createElement('li');
            li.setAttribute('data-lang-key', 'noRecentUploads');
            li.textContent = translations['noRecentUploads'][currentLanguage] || 'No recent uploads.';
            lastUploadedFilesList.appendChild(li);
        } else {
            data.lastUploadedFiles.forEach(file => {
                const listItem = document.createElement('li');
                const uploadedDate = new Date(file.uploaded_at);
                const formattedDate = uploadedDate.getFullYear() + '-' +
                                    ('0' + (uploadedDate.getMonth()+1)).slice(-2) + '-' +
                                    ('0' + uploadedDate.getDate()).slice(-2) + ' ' +
                                    ('0' + uploadedDate.getHours()).slice(-2) + ':' +
                                    ('0' + uploadedDate.getMinutes()).slice(-2);
                listItem.innerHTML = `
                    <div class="file-info">
                        <i class="fas ${getFileIconClass(file.file_name)} file-icon"></i>
                        <span class="file-name">${htmlspecialchars(file.file_name)}</span>
                    </div>
                    <span class="file-meta">${formatBytes(file.file_size)} - ${formattedDate}</span>
                `;
                lastUploadedFilesList.appendChild(listItem);
            });
        }

        // Update charts
        renderCharts(
            data.monthLabels,
            data.monthData,
            data.fileTypeLabels,
            data.fileTypeData,
            data.storageMonthLabels,
            data.storageMonthData
        );

        // Re-apply translation after AJAX update
        applyTranslation(currentLanguage);

    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    }
}

// Helper function for htmlspecialchars (replicated from PHP)
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
