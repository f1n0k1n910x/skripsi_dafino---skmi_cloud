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
    'ofUsed': { 'id': 'dari', 'en': 'of' },
    'usedTextId': 'terpakai',
    'usedTextEn': 'used',

    // Control Center Specific
    'controlCenterTitle': { 'id': 'Pusat Kontrol', 'en': 'Control Center' },
    'createNewMemberAccount': { 'id': 'Buat Akun Anggota Baru', 'en': 'Create New Member Account' },
    'username': { 'id': 'Nama Pengguna', 'en': 'Username' },
    'password': { 'id': 'Kata Sandi', 'en': 'Password' },
    'email': { 'id': 'Email', 'en': 'Email' },
    'fullName': { 'id': 'Nama Lengkap', 'en': 'Full Name' },
    'role': { 'id': 'Peran', 'en': 'Role' },
    'userRole': { 'id': 'Pengguna', 'en': 'User' },
    'memberRole': { 'id': 'Anggota', 'en': 'Member' },
    'moderatorRole': { 'id': 'Moderator', 'en': 'Moderator' },
    'adminRole': { 'id': 'Admin', 'en': 'Admin' },
    'createAccount': { 'id': 'Buat Akun', 'en': 'Create Account' },
    'memberMonitoring': { 'id': 'Pemantauan Anggota', 'en': 'Member Monitoring' },
    'searchMembersPlaceholder': { 'id': 'Cari anggota...', 'en': 'Search members...' },
    'id': { 'id': 'ID', 'en': 'ID' },
    'lastLogin': { 'id': 'Login Terakhir', 'en': 'Last Login' },
    'status': { 'id': 'Status', 'en': 'Status' },
    'actions': { 'id': 'Tindakan', 'en': 'Actions' },
    'onlineStatus': { 'id': 'Online', 'en': 'Online' },
    'offlineStatus': { 'id': 'Offline', 'en': 'Offline' },
    'viewDetails': { 'id': 'Lihat Detail', 'en': 'View Details' },
    'noMembersFound': { 'id': 'Tidak ada anggota ditemukan.', 'en': 'No members found.' },
    'memberDetailsTitle': { 'id': 'Detail Anggota', 'en': 'Member Details' },
    'lastActive': { 'id': 'Terakhir Aktif', 'en': 'Last Active' },
    'na': { 'id': 'N/A', 'en': 'N/A' }, // Not Applicable
    'allFieldsRequired': { 'id': 'Semua kolom wajib diisi.', 'en': 'All fields are required.' },
    'invalidEmailFormat': { 'id': 'Format email tidak valid.', 'en': 'Invalid email format.' },
    'usernameOrEmailExists': { 'id': 'Nama pengguna atau email sudah ada.', 'en': 'Username or email already exists.' },
    'accountCreatedSuccess': { 'id': 'Akun anggota berhasil dibuat!', 'en': 'Member account created successfully!' },
    'errorCreatingAccount': { 'id': 'Kesalahan saat membuat akun anggota:', 'en': 'Error creating member account:' },
    'unknownError': { 'id': 'Terjadi kesalahan yang tidak diketahui.', 'en': 'An unknown error occurred.' },
    'memberDetailsNotFound': { 'id': 'Detail anggota tidak ditemukan.', 'en': 'Member details not found.' },
    'errorDuringCreation': { 'id': 'Terjadi kesalahan saat pembuatan akun.', 'en': 'An error occurred during account creation.' },
};

let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to 'id'

function applyTranslation(lang) {
    document.querySelectorAll('[data-lang-key]').forEach(element => {
        const key = element.getAttribute('data-lang-key');
        if (translations[key] && translations[key][lang]) {
            // For input placeholders
            if (element.tagName === 'INPUT' && element.hasAttribute('placeholder')) {
                element.setAttribute('placeholder', translations[key][lang]);
            } else {
                element.textContent = translations[key][lang];
            }
        }
    });

    // Special handling for "of X used" text in storage info
    const storageTextElement = document.getElementById('storageText');
    if (storageTextElement) {
        // Menggunakan data dari objek phpData
        const usedBytes = phpData.usedStorageBytes;
        const totalBytes = phpData.totalStorageBytes;
        const formattedUsed = formatBytes(usedBytes);
        const formattedTotal = formatBytes(totalBytes);

        const ofText = translations['ofUsed'][lang];
        const usedText = translations['usedText' + (lang === 'id' ? 'Id' : 'En')];

        // Modified to match index.php's format for total storage (no decimal for GB)
        let displayTotal = formattedTotal;
        if (lang === 'id' && formattedTotal.endsWith(' GB')) {
            displayTotal = Math.round(totalBytes / (1024 * 1024 * 1024)) + ' GB';
        }

        storageTextElement.textContent = `${formattedUsed} ${ofText} ${displayTotal} ${usedText}`;
    }

    // Update N/A for Last Login if applicable
    document.querySelectorAll('.member-table tbody tr').forEach(row => {
        const lastLoginCell = row.querySelector('td:nth-child(6)');
        if (lastLoginCell && lastLoginCell.textContent.trim() === 'N/A') {
            lastLoginCell.innerHTML = `<span data-lang-key="na">${translations['na'][lang]}</span>`;
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const searchMemberInputDesktop = document.getElementById('searchMemberInputDesktop'); // Desktop search
    const searchMemberInputMobile = document.getElementById('searchMemberInputMobile'); // Mobile search
    const customNotification = document.getElementById('customNotification');
    const createMemberForm = document.getElementById('createMemberForm');
    const mainContent = document.getElementById('mainContent'); // Get main-content for animations

    // Function to show custom notification
    function showNotification(message, type) {
        customNotification.innerHTML = message;
        customNotification.className = 'notification show ' + type;
        setTimeout(() => {
            customNotification.classList.remove('show');
        }, 3000);
    }

    // Display message from session if exists (menggunakan data dari phpData)
    if (phpData.message && phpData.message.length > 0) {
        let translatedMessage = phpData.message;
        const messageKeyMap = {
            "All fields are required.": "allFieldsRequired",
            "Invalid email format.": "invalidEmailFormat",
            "Username or email already exists.": "usernameOrEmailExists",
            "Member account created successfully!": "accountCreatedSuccess",
            "Error creating member account: ": "errorCreatingAccount", // Partial match
            "An unknown error occurred.": "unknownError",
            "Member details not found.": "memberDetailsNotFound",
            "An error occurred during account creation.": "errorDuringCreation"
        };

        for (const originalMsg in messageKeyMap) {
            if (translatedMessage.startsWith(originalMsg)) {
                const key = messageKeyMap[originalMsg];
                translatedMessage = translations[key][currentLanguage] + translatedMessage.substring(originalMsg.length);
                break;
            }
        }
        showNotification(translatedMessage, phpData.messageType);
    }

    // --- Responsive Class Handling ---
    function applyDeviceClass() {
        const width = window.innerWidth;
        const body = document.body;

        body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop');

        if (width <= 767) {
            body.classList.add('mobile');
            sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default on mobile
        } else if (width >= 768 && width <= 1024) {
            if (window.matchMedia("(orientation: portrait)").matches) {
                body.classList.add('tablet-portrait');
                sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default on tablet portrait
            } else {
                body.classList.add('tablet-landscape');
                sidebar.classList.remove('mobile-hidden'); // Sidebar visible on tablet landscape
                sidebar.classList.remove('show-mobile-sidebar');
                mobileOverlay.classList.remove('show');
            }
        } else {
            body.classList.add('desktop');
            sidebar.classList.remove('mobile-hidden'); // Sidebar visible on desktop
            sidebar.classList.remove('show-mobile-sidebar');
            mobileOverlay.classList.remove('show');
        }
    }

    applyDeviceClass();
    window.addEventListener('resize', applyDeviceClass);
    window.addEventListener('orientationchange', applyDeviceClass);

    // --- Mobile Sidebar Toggle ---
    sidebarToggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show-mobile-sidebar');
        mobileOverlay.classList.toggle('show');
    });

    mobileOverlay.addEventListener('click', () => {
        sidebar.classList.remove('show-mobile-sidebar');
        mobileOverlay.classList.remove('show');
    });

    // --- Member Search Functionality (No Reload Page) ---
    function updateMemberList(query) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'control_center.php?search_member=' + encodeURIComponent(query) + '&ajax=1', true);
        xhr.onload = function() {
            if (this.status === 200) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(this.responseText, 'text/html');
                const newTableBody = doc.querySelector('.member-table tbody');
                const currentTableBody = document.querySelector('.member-table tbody');
                if (newTableBody && currentTableBody) {
                    currentTableBody.innerHTML = newTableBody.innerHTML;
                    applyTranslation(currentLanguage); // Apply translation after updating list
                }
            }
        };
        xhr.send();
    }

    function fetchMembers() {
        fetch('v2/services/api/fetchMembers.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.querySelector(".member-table tbody");
                tbody.innerHTML = ""; // 🔥 Clear old rows

                data.forEach(member => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td>${member.id}</td>
                        <td>${member.username}</td>
                        <td>${member.full_name}</td>
                        <td>
                            <span data-lang-key="${member.role ? member.role.toLowerCase() + 'Role' : ''}">
                                ${member.role ? capitalizeFirstLetter(member.role) : ''}
                            </span>
                        </td>
                        <td>${escapeHtml(member.email)}</td>
                        <td>${
                            member.last_login
                                ? formatDate(member.last_login)
                                : '<span data-lang-key="na">N/A</span>'
                        }</td>
                        <td>
                            <span class="status-indicator ${member.is_online ? 'online' : 'offline'}"></span>
                            <span data-lang-key="${member.is_online ? 'onlineStatus' : 'offlineStatus'}">
                                ${member.is_online ? 'Online' : 'Offline'}
                            </span>
                        </td>
                        <td class="action-buttons">
                            <button onclick="viewMemberDetails(${member.id})" data-lang-key="viewDetails">
                                View Details
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
                applyTranslation(currentLanguage); // Apply translation after fetching new members
            })
            .catch(error => console.error("Error fetching members:", error));
    }

    // Helpers (same as PHP version behavior)
    function escapeHtml(text) {
        if (typeof text !== "string" && typeof text !== "number") return "";
        return text.toString().replace(/&/g, "&amp;")
                            .replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;")
                            .replace(/"/g, "&quot;")
                            .replace(/'/g, "&#039;");
    }

    function capitalizeFirstLetter(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : "";
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        if (isNaN(date)) return '<span data-lang-key="na">N/A</span>';

        const year = date.getFullYear();
        const month = ("0" + (date.getMonth() + 1)).slice(-2);
        const day = ("0" + date.getDate()).slice(-2);
        const hours = ("0" + date.getHours()).slice(-2);
        const minutes = ("0" + date.getMinutes()).slice(-2);

        return `${year}-${month}-${day} ${hours}:${minutes}`;
    }

    searchMemberInputDesktop.addEventListener('keyup', function() {
        updateMemberList(this.value);
    });

    searchMemberInputMobile.addEventListener('keyup', function() {
        updateMemberList(this.value);
    });

    // --- Create Member Account (No Reload Page) ---
    createMemberForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        const formData = new FormData(this);
        formData.append('create_member_account', '1'); // Ensure the PHP script recognizes this as a creation request

        fetch('control_center.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) // Get response as text
        .then(text => {
            // Parse the response to find the message and messageType
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            const notificationDiv = doc.getElementById('customNotification');

            let message = translations['unknownError'][currentLanguage]; // Default unknown error
            let messageType = "error";

            if (notificationDiv) {
                const originalMessage = notificationDiv.textContent.trim();
                const messageKeyMap = {
                    "All fields are required.": "allFieldsRequired",
                    "Invalid email format.": "invalidEmailFormat",
                    "Username or email already exists.": "usernameOrEmailExists",
                    "Member account created successfully!": "accountCreatedSuccess",
                    "Error creating member account: ": "errorCreatingAccount", // Partial match
                };

                let foundTranslation = false;
                for (const originalMsg in messageKeyMap) {
                    if (originalMessage.startsWith(originalMsg)) {
                        const key = messageKeyMap[originalMsg];
                        translatedMessage = translations[key][currentLanguage] + originalMessage.substring(originalMsg.length);
                        foundTranslation = true;
                        break;
                    }
                }
                if (!foundTranslation) {
                    message = originalMessage; // Fallback to original if no specific translation found
                }

                if (notificationDiv.classList.contains('success')) {
                    messageType = 'success';
                } else if (notificationDiv.classList.contains('error')) {
                    messageType = 'error';
                }
            }

            showNotification(message, messageType);

            // If successful, clear form and update member list
            if (messageType === 'success') {
                createMemberForm.reset();
                fetchMembers(); // Refresh member list
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(translations['errorDuringCreation'][currentLanguage], 'error');
        });
    });


    // --- Member Details Modal ---
    const memberDetailsModal = document.getElementById('memberDetailsModal');
    const closeButtons = memberDetailsModal.querySelectorAll('.close-button');

    // Function to open modal
    function openModal(modalElement) {
        modalElement.classList.add('show');
    }

    // Function to close modal
    function closeModal(modalElement) {
        modalElement.classList.remove('show');
    }

    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            closeModal(memberDetailsModal);
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target == memberDetailsModal) {
            closeModal(memberDetailsModal);
        }
    });

    // Function to view member details (simplified for this example)
    window.viewMemberDetails = function(memberId) {
        // Menggunakan data dari objek phpData
        const member = phpData.members.find(m => m.id == memberId);

        if (member) {
            document.getElementById('memberDetailsUsername').textContent = member.username;
            document.getElementById('detailId').textContent = member.id;
            document.getElementById('detailFullName').textContent = member.full_name;
            document.getElementById('detailEmail').textContent = member.email;
            document.getElementById('detailRole').textContent = translations[member.role.toLowerCase() + 'Role'][currentLanguage] || ucfirst(member.role);
            document.getElementById('detailLastLogin').textContent = member.last_login ? new Date(member.last_login.replace(/-/g, '/')).toLocaleString() : translations['na'][currentLanguage];
            document.getElementById('detailLastActive').textContent = member.last_active ? new Date(member.last_active.replace(/-/g, '/')).toLocaleString() : translations['na'][currentLanguage];
            document.getElementById('detailStatus').textContent = member.is_online ? translations['onlineStatus'][currentLanguage] : translations['offlineStatus'][currentLanguage];
            openModal(memberDetailsModal); // Use openModal function
        } else {
            showNotification(translations['memberDetailsNotFound'][currentLanguage], 'error');
        }
    };

    // Set active class for current page in sidebar
    const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');
    const currentPage = window.location.pathname.split('/').pop();
    sidebarMenuItems.forEach(item => {
        item.classList.remove('active');
        const itemHref = item.getAttribute('href');
        if (itemHref === currentPage || (currentPage === 'index.php' && itemHref === 'index.php')) {
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

    // Initial translation application
    applyTranslation(currentLanguage);
});

// --- Show/Hide Password Function ---
function togglePassword(fieldId) {
    const passwordField = document.getElementById(fieldId);
    const toggleButton = passwordField.nextElementSibling; // Assuming button is next sibling

    if (passwordField.type === "password") {
        passwordField.type = "text";
        toggleButton.innerHTML = '<i class="fas fa-eye-slash"></i>'; // Change icon to eye-slash
    } else {
        passwordField.type = "password";
        toggleButton.innerHTML = '<i class="fas fa-eye"></i>'; // Change icon back to eye
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

// Helper function for ucfirst (replicate PHP's ucfirst)
function ucfirst(str) {
    if (typeof str !== 'string' || str.length === 0) {
        return '';
    }
    return str.charAt(0).toUpperCase() + str.slice(1);
}
