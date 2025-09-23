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

// --- Configuration ---
$limit = 5; // Number of members per page
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$searchQuery = isset($_GET['search_member']) ? trim($_GET['search_member']) : '';
$offset = ($page - 1) * $limit;

/**
 * Fetch total members count (with optional search filter)
 */
function getTotalMembers(mysqli $conn, string $searchQuery = ''): int {
    $sql = "SELECT COUNT(id) AS total FROM users";
    $params = [];
    $types  = "";

    if ($searchQuery !== '') {
        $sql .= " WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
        $searchTerm = '%' . $searchQuery . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
        $types  = "sss";
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    return (int) $row['total'];
}

/**
 * Fetch members (with search, pagination)
 */
function getMembers(mysqli $conn, string $searchQuery, int $limit, int $offset): array {
    $sql = "SELECT id, username, email, full_name, role, last_active, last_login 
            FROM users";
    $params = [];
    $types  = "";

    if ($searchQuery !== '') {
        $sql .= " WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
        $searchTerm = '%' . $searchQuery . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
        $types  = "sss";
    }

    $sql .= " ORDER BY username ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types   .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_online'] = strtotime($row['last_active']) > strtotime('-15 minutes');
        $members[] = $row;
    }

    $stmt->close();
    return $members;
}

// --- Main Logic ---
$totalMembers = getTotalMembers($conn, $searchQuery);
$totalPages   = max(1, ceil($totalMembers / $limit));
$members      = getMembers($conn, $searchQuery, $limit, $offset);

// --- Handle AJAX Request for Members ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Output JSON instead of HTML
    $response = [
        'table_body' => '',
        'pagination' => ''
    ];
    
    // Generate table body HTML
    ob_start();
    ?>
    <?php if (!empty($members)): ?>
        <?php foreach ($members as $member): ?>
            <tr>
                <td><?= htmlspecialchars($member['id']) ?></td>
                <td><?= htmlspecialchars($member['username']) ?></td>
                <td><?= htmlspecialchars($member['full_name']) ?></td>
                <td><?= htmlspecialchars(ucfirst($member['role'])) ?></td>
                <td><?= htmlspecialchars($member['email']) ?></td>
                <td>
                    <?= !empty($member['last_login']) 
                        ? date('Y-m-d H:i', strtotime($member['last_login'])) 
                        : 'N/A' ?>
                </td>
                <td>
                    <span class="status-indicator <?= $member['is_online'] ? 'online' : 'offline' ?>"></span>
                    <?= $member['is_online'] ? 'Online' : 'Offline' ?>
                </td>
                <td>
                    <button onclick="viewMemberDetails(<?= $member['id'] ?>)">View Details</button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="8" style="text-align: center;">No members found.</td></tr>
    <?php endif; ?>
    <?php
    $response['table_body'] = ob_get_clean();
    
    // Generate pagination HTML
    ob_start();
    ?>
    <?php if ($totalPages > 1): ?>
        <?php if ($page > 1): ?>
            <button onclick="loadMembers(<?= $page - 1 ?>, '<?= htmlspecialchars($searchQuery ?? '') ?>')">
                Previous
            </button>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <button class="<?= $i == $page ? 'active' : '' ?>"
                    onclick="loadMembers(<?= $i ?>, '<?= htmlspecialchars($searchQuery ?? '') ?>')">
                <?= $i ?>
            </button>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <button onclick="loadMembers(<?= $page + 1 ?>, '<?= htmlspecialchars($searchQuery ?? '') ?>')">
                Next
            </button>
        <?php endif; ?>
    <?php endif; ?>
    <?php
    $response['pagination'] = ob_get_clean();
    
    // Output JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

?>

<?php include '../partials/control-center-header.php'; ?>

<?php include '../../views/partials/sidebar.php'; ?>


    <div class="main-content" id="mainContent">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="control-center-title" data-lang-key="controlCenterTitle">Control Center</h1>
            <!-- Desktop search bar removed from here -->
        </div>

        <?php if (!empty($message)): ?>
            <div id="customNotification" class="notification <?php echo $messageType; ?> show">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title" data-lang-key="createNewMemberAccount">Create New Member Account</h2>
        <div class="form-section">
            <form action="control-center.php" method="POST" id="createMemberForm">
                <div>
                    <label for="new_username" data-lang-key="username">Username:</label>
                    <input type="text" id="new_username" name="new_username" required>
                </div>
                <div>
                    <label for="new_password" data-lang-key="password">Password:</label>
                    <div class="password-input-container">
                        <input type="password" id="new_password" name="new_password" required>
                        <button type="button" class="toggle-password-btn" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="new_email" data-lang-key="email">Email:</label>
                    <input type="email" id="new_email" name="new_email" required>
                </div>
                <div>
                    <label for="new_full_name" data-lang-key="fullName">Full Name:</label>
                    <input type="text" id="new_full_name" name="new_full_name" required>
                </div>
                <div>
                    <label for="new_role" data-lang-key="role">Role:</label>
                    <select id="new_role" name="new_role" required>
                        <option value="user" data-lang-key="userRole">User</option>
                        <option value="member" data-lang-key="memberRole">Member</option>
                        <?php if ($currentUserRole === 'admin'): // Only admin can create other admins/moderators ?>
                            <option value="moderator" data-lang-key="moderatorRole">Moderator</option>
                            <option value="admin" data-lang-key="adminRole">Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" name="create_member_account" data-lang-key="createAccount">Create Account</button>
            </form>
            <?php if (isset($message)): ?>
            <div id="customNotification" class="<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

        </div>

        <div class="member-monitoring-header">
            <h2 class="section-title" data-lang-key="memberMonitoring">Member Monitoring</h2>
            <div class="search-bar search-bar-desktop">
                <i class="fas fa-search"></i>
                <input type="text" id="searchMemberInputDesktop" placeholder="Search members..." value="<?php echo htmlspecialchars($searchQuery); ?>" data-lang-key="searchMembersPlaceholder">
            </div>
        </div>

        <!-- Mobile Search Bar (moved below member monitoring section) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchMemberInputMobile" placeholder="Search members..." value="<?php echo htmlspecialchars($searchQuery); ?>" data-lang-key="searchMembersPlaceholder">
        </div>

        <div class="member-list-section">
            <div class="table-container">
                <table class="member-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="memberTableBody">
                        <?php if (!empty($members)): ?>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><?= htmlspecialchars($member['id']) ?></td>
                                    <td><?= htmlspecialchars($member['username']) ?></td>
                                    <td><?= htmlspecialchars($member['full_name']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($member['role'])) ?></td>
                                    <td><?= htmlspecialchars($member['email']) ?></td>
                                    <td>
                                        <?= !empty($member['last_login']) 
                                            ? date('Y-m-d H:i', strtotime($member['last_login'])) 
                                            : 'N/A' ?>
                                    </td>
                                    <td>
                                        <span class="status-indicator <?= $member['is_online'] ? 'online' : 'offline' ?>"></span>
                                        <?= $member['is_online'] ? 'Online' : 'Offline' ?>
                                    </td>
                                    <td>
                                        <button onclick="viewMemberDetails(<?= $member['id'] ?>)">View Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align: center;">No members found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination-container" id="memberPaginationContainer">
                <div class="pagination" id="memberPagination">
                    <tfoot class="pagination-footer">
                        <tr>
                            <td colspan="8">
                                <div class="pagination" id="memberPagination">
                                    <?php if ($totalPages > 1): ?>
                                        <?php if ($page > 1): ?>
                                            <button type="button" data-page="<?= $page - 1 ?>" 
                                                    onclick="loadMembers(<?= $page - 1 ?>, '<?= htmlspecialchars($searchQuery ?? '') ?>')">
                                                Previous
                                            </button>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <button type="button" data-page="<?= $i ?>" 
                                                    class="<?= $i == $page ? 'active' : '' ?>" 
                                                    onclick="loadMembers(<?= $i ?>, '<?= htmlspecialchars($searchQuery ?? '') ?>')">
                                                <?= $i ?>
                                            </button>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <button type="button" data-page="<?= $page + 1 ?>" 
                                                    onclick="loadMembers(<?= $page + 1 ?>, '<?= htmlspecialchars($searchQuery ?? '') ?>')">
                                                Next
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
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
            // Attach event listeners to initial pagination buttons
            attachPaginationEvents();

            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const searchMemberInputDesktop = document.getElementById('searchMemberInputDesktop'); // Desktop search
            const searchMemberInputMobile = document.getElementById('searchMemberInputMobile'); // Mobile search
            const customNotification = document.getElementById('customNotification');
            const createMemberForm = document.getElementById('createMemberForm');
            const mainContent = document.getElementById('mainContent'); // Get main-content for animations
            const memberTableBody = document.getElementById('memberTableBody');
            const memberPagination = document.getElementById('memberPagination');

            // Function to show custom notification
            function showNotification(message, type) {
                customNotification.innerHTML = message;
                customNotification.className = 'notification show ' + type;
                setTimeout(() => {
                    customNotification.classList.remove('show');
                }, 3000);
            }

            // Display message from session if exists
            <?php if (!empty($message)): ?>
                // Translate the message before showing
                let translatedMessage = "<?php echo $message; ?>";
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
                showNotification(translatedMessage, "<?php echo $messageType; ?>");
            <?php endif; ?>

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

            // --- Member Search and Pagination Functionality (No Reload Page) ---
            window.loadMembers = function(page = 1, searchQuery = '') {
                const currentSearchQuery = searchQuery || 
                    (document.getElementById('searchMemberInputDesktop') ? document.getElementById('searchMemberInputDesktop').value : '') || 
                    (document.getElementById('searchMemberInputMobile') ? document.getElementById('searchMemberInputMobile').value : '');
                
                // Get DOM elements safely
                const memberTableBody = document.getElementById('memberTableBody');
                const memberPagination = document.getElementById('memberPagination');
                
                // Check if elements exist
                if (!memberTableBody) {
                    console.error('memberTableBody element not found');
                    return;
                }
                
                // Show loading indicator
                memberTableBody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Loading...</td></tr>';
                if (memberPagination) {
                    memberPagination.innerHTML = '<button disabled>Loading...</button>';
                }
                
                // Build URL parameters correctly
                const params = new URLSearchParams();
                params.append('search_member', currentSearchQuery);
                params.append('page', page);
                params.append('ajax', '1');
                
                // Use fetch API for cleaner code
                fetch(`control-center.php?${params.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received data for page:', page, data); // Debug log
                    
                    // Update table body
                    if (data.table_body && memberTableBody) {
                        memberTableBody.innerHTML = data.table_body;
                    }
                    
                    // Update pagination
                    if (data.pagination && memberPagination) {
                        memberPagination.innerHTML = data.pagination;
                        
                        // Re-attach event listeners to new pagination buttons
                        setTimeout(() => {
                            attachPaginationEvents();
                        }, 100);
                    }
                    
                    // Update search inputs to reflect current search
                    const searchDesktop = document.getElementById('searchMemberInputDesktop');
                    const searchMobile = document.getElementById('searchMemberInputMobile');
                    if (searchDesktop) searchDesktop.value = currentSearchQuery;
                    if (searchMobile) searchMobile.value = currentSearchQuery;
                    
                    // Update URL without reloading page
                    const newUrl = `?search_member=${encodeURIComponent(currentSearchQuery)}&page=${page}`;
                    window.history.pushState({ 
                        search: currentSearchQuery, 
                        page: page 
                    }, '', newUrl);
                    
                    console.log('Updated URL to page:', page); // Debug log
                    
                    // Re-apply translations if needed
                    if (typeof applyTranslation === 'function') {
                        applyTranslation(currentLanguage);
                    }
                })
                .catch(error => {
                    console.error('Error loading members:', error);
                    if (memberTableBody) {
                        memberTableBody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Error loading members.</td></tr>';
                    }
                });
            }

            // Function to attach event listeners to pagination buttons
            function attachPaginationEvents() {
                const paginationContainer = document.getElementById('memberPagination');
                if (!paginationContainer) return;
                
                const buttons = paginationContainer.querySelectorAll('button');
                buttons.forEach(button => {
                    // Remove existing click events and add new ones
                    button.replaceWith(button.cloneNode(true));
                });
                
                // Re-select the buttons after cloning
                const newButtons = paginationContainer.querySelectorAll('button');
                newButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const page = this.getAttribute('data-page') || 
                                    parseInt(this.textContent) || 
                                    1;
                        const search = document.getElementById('searchMemberInputDesktop') ? 
                                    document.getElementById('searchMemberInputDesktop').value : '';
                        loadMembers(page, search);
                    });
                });
            }

            // Search functionality
            searchMemberInputDesktop.addEventListener('keyup', debounce(function() {
                loadMembers(1, this.value);
            }, 300));

            searchMemberInputMobile.addEventListener('keyup', debounce(function() {
                loadMembers(1, this.value);
            }, 300));

            // Debounce function to prevent too many requests
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Handle browser back/forward buttons
            window.addEventListener('popstate', function(event) {
                const urlParams = new URLSearchParams(window.location.search);
                const page = parseInt(urlParams.get('page')) || 1;
                const search = urlParams.get('search_member') || '';
                
                // Update search inputs
                const searchDesktop = document.getElementById('searchMemberInputDesktop');
                const searchMobile = document.getElementById('searchMemberInputMobile');
                if (searchDesktop) searchDesktop.value = search;
                if (searchMobile) searchMobile.value = search;
                
                // Load the members for that page
                loadMembers(page, search);
            });

            // --- Create Member Account (No Reload Page) ---
            createMemberForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                const formData = new FormData(this);
                formData.append('create_member_account', '1'); // Ensure the PHP script recognizes this as a creation request

                fetch('control-center.php', {
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
                        loadMembers(1); // Refresh member list, go to page 1
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
                // In a real application, you would fetch more details via AJAX
                // For now, we'll just find the member in the current list
                // This part needs to fetch from the *full* list of members, not just the paginated one.
                // For simplicity, we'll assume the `members` array in PHP is available globally in JS
                // or fetch it again. For a robust solution, a dedicated API endpoint for member details is better.
                fetch(`v2/services/api/getMemberDetails.php?id=${memberId}`) // Assuming a new API endpoint
                    .then(response => response.json())
                    .then(member => {
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
                    })
                    .catch(error => {
                        console.error('Error fetching member details:', error);
                        showNotification(translations['memberDetailsNotFound'][currentLanguage], 'error');
                    });
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

        function loadMembers(page, search) {
            window.location.href = `?page=${page}&search_member=${encodeURIComponent(search)}`;
        }
    </script>
</body>
</html>