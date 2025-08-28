import { initTranslation, getCurrentLanguage, applyTranslation } from './translation.js';

// Global variable for Chart.js instance
let activityChart;

// Helper function to format bytes (replicate from PHP's formatBytes)
function formatBytes(bytes, precision = 2) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    bytes = Math.max(bytes, 0);
    const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
    const p = Math.min(pow, units.length - 1);
    bytes /= (1 << (10 * p));
    return bytes.toFixed(precision) + ' ' + units[p];
}

// Function to show custom notification
function showNotification(message, type) {
    const customNotification = document.getElementById('customNotification');
    customNotification.textContent = message;
    customNotification.className = 'notification show ' + type;
    setTimeout(() => {
        customNotification.classList.remove('show');
    }, 3000);
}

// Function to initialize or update the chart
function updateActivityChart(labels, data) {
    const ctx = document.getElementById('activityLineChart').getContext('2d');
    if (activityChart) {
        activityChart.data.labels = labels;
        activityChart.data.datasets[0].data = data;
        activityChart.update();
    } else {
        activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Number of Activities',
                    data: data,
                    borderColor: 'var(--primary-color)',
                    backgroundColor: 'rgba(63, 81, 181, 0.1)',
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: 'var(--primary-color)',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: 'var(--text-color)'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count',
                            color: 'var(--text-color)'
                        },
                        ticks: {
                            precision: 0,
                            color: 'var(--secondary-text-color)'
                        },
                        grid: {
                            color: 'var(--divider-color)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date',
                            color: 'var(--text-color)'
                        },
                        ticks: {
                            color: 'var(--secondary-text-color)'
                        },
                        grid: {
                            color: 'var(--divider-color)'
                        }
                    }
                }
            }
        });
    }
}

// Function to process log data into chart format
function processLogsForChart(logs) {
    const dailyCounts = {};
    logs.forEach(log => {
        const date = new Date(log.timestamp).toISOString().split('T')[0]; // Format YYYY-MM-DD
        dailyCounts[date] = (dailyCounts[date] || 0) + 1;
    });

    const sortedDates = Object.keys(dailyCounts).sort();
    const labels = sortedDates;
    const data = sortedDates.map(date => dailyCounts[date]);

    return { labels, data };
}

// Function to update all UI elements with new data
function updateProfileUI(data) {
    const user = data.user;

    // Update Dashboard Header
    document.getElementById('userInfoGreeting').textContent = `Hello ${user.full_name || user.username}`;
    document.getElementById('userInfoAvatar').src = user.profile_picture || 'img/photo_profile.png';

    // Update Sidebar Storage Info
    document.getElementById('sidebarProgressBar').style.width = `${data.usedPercentage.toFixed(2)}%`;
    document.getElementById('sidebarProgressBarText').textContent = `${data.usedPercentage.toFixed(2)}%`;
    document.getElementById('sidebarStorageText').textContent = `${formatBytes(data.usedStorageBytes)} of ${formatBytes(data.totalStorageBytes)} used`;

    const sidebarStorageFullMessage = document.getElementById('sidebarStorageFullMessage');
    if (data.isStorageFull) {
        if (!sidebarStorageFullMessage) {
            const p = document.createElement('p');
            p.id = 'sidebarStorageFullMessage';
            p.className = 'storage-text';
            p.style.color = 'var(--error-color)';
            p.style.fontWeight = 'bold';
            p.textContent = 'Storage Full!';
            document.querySelector('.storage-info').appendChild(p);
        }
    } else {
        if (sidebarStorageFullMessage) {
            sidebarStorageFullMessage.remove();
        }
    }

    // Update Profile Card
    document.getElementById('profileCardPicture').src = user.profile_picture || 'img/photo_profile.png';
    document.getElementById('profileCardFullName').textContent = user.full_name || 'Full Name';
    document.getElementById('profileInfoUsername').textContent = user.username;
    document.getElementById('profileInfoEmail').textContent = user.email;
    document.getElementById('profileInfoPhoneNumber').textContent = user.phone_number || 'N/A';
    document.getElementById('profileInfoDateOfBirth').textContent = user.date_of_birth || 'N/A';
    document.getElementById('profileInfoJoinDate').textContent = new Date(user.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
    document.getElementById('profileInfoAccountStatus').textContent = data.current_account_status;

    document.getElementById('profileStatsTotalFiles').textContent = data.total_files;
    document.getElementById('profileStatsStorageUsed').textContent = formatBytes(data.usedStorageBytes);
    document.getElementById('profileStatsTotalQuota').textContent = formatBytes(data.totalStorageBytes);

    // Update Activity Chart
    updateActivityChart(data.chart_labels, data.chart_data);

    // Update Detailed Activity List
    const activityListUl = document.getElementById('activityListUl');
    activityListUl.innerHTML = ''; // Clear existing list
    if (data.activity_logs.length > 0) {
        data.activity_logs.forEach(log => {
            const li = document.createElement('li');
            li.innerHTML = `
                <strong data-lang-activity-type="${log.activity_type}">${log.activity_type}:</strong>
                <span data-lang-activity-desc="${log.description}">${log.description}</span>
                <span style="float: right; color: var(--secondary-text-color); font-size: 0.9em;">${new Date(log.timestamp).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
            `;
            activityListUl.appendChild(li);
        });
    } else {
        const li = document.createElement('li');
        li.setAttribute('data-lang-key', 'noActivityHistory');
        li.textContent = 'No activity history.';
        activityListUl.appendChild(li);
    }

    // Update Additional Emails List
    const additionalEmailsList = document.getElementById('additionalEmailsList');
    additionalEmailsList.innerHTML = ''; // Clear existing list
    if (data.additional_emails.length > 0) {
        data.additional_emails.forEach(email_item => {
            const li = document.createElement('li');
            li.className = 'email-item';
            li.innerHTML = `
                <span>${email_item.email}</span>
                <form class="delete-email-form" data-email-id="${email_item.id}">
                    <input type="hidden" name="email_id_to_delete" value="${email_item.id}">
                    <button type="submit" name="delete_additional_email" class="delete-email-btn" title="Delete this email" data-lang-title="deleteThisEmail">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </form>
            `;
            additionalEmailsList.appendChild(li);
        });
    }
    // Always display primary email
    const primaryLi = document.createElement('li');
    primaryLi.className = 'email-item';
    primaryLi.style.fontWeight = 'bold';
    primaryLi.style.backgroundColor = 'var(--background-color)';
    primaryLi.style.borderRadius = '0'; // Siku-siku
    primaryLi.style.padding = '8px';
    primaryLi.innerHTML = `
        <span data-lang-key="primaryEmailLabel">${user.email} (Primary Email)</span>
        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
    `;
    additionalEmailsList.appendChild(primaryLi);

    // Update Edit Profile Modal fields
    document.getElementById('edit_full_name').value = user.full_name || '';
    document.getElementById('edit_phone_number').value = user.phone_number || '';
    document.getElementById('edit_date_of_birth').value = user.date_of_birth || '';
    document.getElementById('currentProfilePicLink').href = user.profile_picture || 'img/photo_profile.png';
    document.getElementById('currentProfilePicLink').textContent = (user.profile_picture ? user.profile_picture.split('/').pop() : 'photo_profile.png');

    // Re-apply translation after UI update
    applyTranslation(currentLanguage);
}

// Function to fetch profile data via AJAX
async function fetchProfileData() {
    try {
        const response = await fetch('profile.php?action=get_profile_data');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        if (data.error) {
            // Optionally redirect if user not found
            // window.location.href = 'login.php';
            return;
        }
        updateProfileUI(data);
        // Store all activity logs for client-side filtering
        window.allActivityLogs = data.activity_logs;
    } catch (error) {
        console.error("Could not fetch profile data:", error);
    }
}

// Function to update the real-time clock
function updateClock() {
    const now = new Date();

    let hours = now.getHours();
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();
    const ampm = hours >= 12 ? 'P.M.' : 'A.M.';

    hours = hours % 12;
    hours = hours ? hours : 12; // Convert 0 to 12

    const timeString = `${pad(hours)}:${pad(minutes)}:${pad(seconds)} ${ampm}`;
    document.getElementById("profileClock").innerText = timeString;

    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    const dayName = days[now.getDay()];
    const day = now.getDate();
    const month = months[now.getMonth()];
    const year = now.getFullYear();

    const dateString = `${dayName}, ${day} ${month} ${year}`;
    document.getElementById("profileDate").innerText = dateString;
}

function pad(n) {
    return n < 10 ? '0' + n : n;
}

// --- Responsive Class Handling ---
function applyDeviceClass() {
    const width = window.innerWidth;
    const body = document.body;
    const sidebar = document.querySelector('.sidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const profileTitle = document.querySelector('.profile-title'); // Get the title element
    const userInfo = document.querySelector('.user-info'); // Get user-info element

    // Remove all previous device classes
    body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop');

    if (width <= 767) {
        body.classList.add('mobile');
        sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default
        profileTitle.style.display = 'none'; // Hide title on mobile
        userInfo.style.display = 'none'; // Hide user info on mobile
    } else if (width >= 768 && width <= 1024) {
        if (window.matchMedia("(orientation: portrait)").matches) {
            body.classList.add('tablet-portrait');
            sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default
            profileTitle.style.display = 'none'; // Hide title on tablet portrait
            userInfo.style.display = 'none'; // Hide user info on tablet portrait
        } else {
            body.classList.add('tablet-landscape');
            sidebar.classList.remove('mobile-hidden'); // Show sidebar
            sidebar.classList.remove('show-mobile-sidebar'); // Ensure mobile sidebar is closed
            mobileOverlay.classList.remove('show'); // Hide overlay
            profileTitle.style.display = 'block'; // Show title on tablet landscape
            userInfo.style.display = 'flex'; // Show user info on tablet landscape
        }
    } else {
        body.classList.add('desktop');
        sidebar.classList.remove('mobile-hidden'); // Show sidebar
        sidebar.classList.remove('show-mobile-sidebar'); // Ensure mobile sidebar is closed
        mobileOverlay.classList.remove('show'); // Hide overlay
        profileTitle.style.display = 'block'; // Show title on desktop
        userInfo.style.display = 'flex'; // Show user info on desktop
    }
}

// --- Translation Logic ---

let currentLanguage = getCurrentLanguage() || 'id'; // Default to Indonesian

// Override showNotification to handle translation keys
const originalShowNotification = showNotification;
showNotification = function (message, type, langKey = null) {
    const customNotification = document.getElementById('customNotification');
    if (langKey && translations[langKey] && translations[langKey][currentLanguage]) {
        customNotification.textContent = translations[langKey][currentLanguage];
        customNotification.setAttribute('data-lang-key-notification', langKey); // Store key for re-translation
    } else {
        customNotification.textContent = message;
        customNotification.removeAttribute('data-lang-key-notification');
    }
    customNotification.className = 'notification show ' + type;
    setTimeout(() => {
        customNotification.classList.remove('show');
        customNotification.removeAttribute('data-lang-key-notification');
    }, 3000);
};


document.addEventListener('DOMContentLoaded', function () {
    // Init translation system
    initTranslation();

    // Initial UI update with data from PHP (server-side rendered)
    // This ensures the page is not blank while AJAX loads
    updateActivityChart(initialChartLabels, initialChartData);
    window.allActivityLogs = initialActivityLogs;

    // Initialize real-time clock
    updateClock();
    setInterval(updateClock, 1000);

    // Elements for modals and forms
    const changePasswordModal = document.getElementById('changePasswordModal');
    const changePasswordForm = document.getElementById('changePasswordForm');
    const editProfileModal = document.getElementById('editProfileModal');
    const editProfileForm = document.getElementById('editProfileForm');
    const closeButtons = document.querySelectorAll('.close-button');
    const profilePictureContainer = document.querySelector('.profile-picture-container');
    const profilePictureInput = document.getElementById('profilePictureInput');
    const addEmailForm = document.getElementById('addEmailForm');
    const additionalEmailsList = document.getElementById('additionalEmailsList');
    const deleteProfilePictureBtn = document.getElementById('deleteProfilePictureBtn');

    // Mobile sidebar elements
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mainContent = document.getElementById('mainContent'); // Get main-content for animations

    // Notification display from initial PHP load
    if (initialNotificationMessage && initialNotificationType) {
        showNotification(initialNotificationMessage, initialNotificationType);
    }

    // Apply initial translation based on stored preference
    applyTranslation(currentLanguage);
    document.body.style.visibility = 'visible'; // Make body visible after initial translation

    // Event listeners for translation buttons
    document.getElementById('langIdBtn').addEventListener('click', () => {
        currentLanguage = 'id';
        localStorage.setItem('lang', 'id');
        applyTranslation('id');
    });

    document.getElementById('langEnBtn').addEventListener('click', () => {
        currentLanguage = 'en';
        localStorage.setItem('lang', 'en');
        applyTranslation('en');
    });

    // Open Edit Profile Modal
    document.getElementById('editProfileBtn').addEventListener('click', () => {
        editProfileModal.classList.add('show');
        // Populate fields are handled by updateProfileUI on initial load and refresh
        applyTranslation(currentLanguage); // Re-apply translation to modal content
    });

    // Open Change Password Modal
    document.getElementById('changePasswordBtn').addEventListener('click', () => {
        changePasswordModal.classList.add('show');
        changePasswordForm.reset(); // Clear form fields when opening
        applyTranslation(currentLanguage); // Re-apply translation to modal content
    });

    // Close Modals
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            changePasswordModal.classList.remove('show');
            editProfileModal.classList.remove('show');
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target == changePasswordModal) {
            changePasswordModal.classList.remove('show');
        }
        if (event.target == editProfileModal) {
            editProfileModal.classList.remove('show');
        }
        // Close mobile sidebar if overlay is clicked
        if (event.target == mobileOverlay && sidebar.classList.contains('show-mobile-sidebar')) {
            sidebar.classList.remove('show-mobile-sidebar');
            mobileOverlay.classList.remove('show');
        }
    });

    // Handle Change Password Form Submission via AJAX
    changePasswordForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                showNotification(result.message, 'success', 'passwordChangedSuccess');
                changePasswordModal.classList.remove('show');
                this.reset();
            } else {
                // Map specific error messages to translation keys
                let langKey = 'errorChangingPassword';
                if (result.message.includes('Old password is incorrect')) {
                    langKey = 'oldPasswordIncorrect';
                } else if (result.message.includes('New password confirmation does not match')) {
                    langKey = 'newPasswordMismatch';
                } else if (result.message.includes('New password must be at least 6 characters long')) {
                    langKey = 'passwordTooShort';
                }
                showNotification(result.message, 'error', langKey);
            }
        } catch (error) {
            console.error('Error changing password:', error);
            showNotification('An error occurred while changing password.', 'error', 'errorChangingPassword');
        }
    });

    // Handle Edit Profile Form Submission via AJAX
    editProfileForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                showNotification(result.message, 'success', 'profileUpdatedSuccess');
                editProfileModal.classList.remove('show');
                fetchProfileData(); // Refresh UI after successful update
            } else {
                let langKey = 'errorUpdatingProfile';
                if (result.message.includes('Only JPG, JPEG, PNG, and GIF files are allowed')) {
                    langKey = 'fileTypeNotAllowed';
                } else if (result.message.includes('File size is too large')) {
                    langKey = 'fileSizeTooLarge';
                } else if (result.message.includes('Failed to save merged profile picture')) {
                    langKey = 'failedToSaveMergedImage';
                } else if (result.message.includes('Failed to load images for merging')) {
                    langKey = 'failedToLoadImagesForMerging';
                } else if (result.message.includes('Background image not found')) {
                    langKey = 'backgroundNotFoundUploadOriginal';
                } else if (result.message.includes('Failed to upload profile picture')) {
                    langKey = 'failedToUploadProfilePicture';
                }
                showNotification(result.message, 'error', langKey);
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            showNotification('An error occurred while updating profile.', 'error', 'errorUpdatingProfile');
        }
    });

    // Handle profile picture click to trigger file input
    profilePictureContainer.addEventListener('click', () => {
        profilePictureInput.click();
    });

    // Handle file input change for profile picture upload (directly from profile card)
    profilePictureInput.addEventListener('change', async function () {
        if (this.files.length === 0) {
            return;
        }
        const file = this.files[0];
        const formData = new FormData();
        formData.append('profile_picture', file);
        formData.append('edit_profile_submit', '1');
        // Send current full name, phone number, and date of birth to avoid overwriting
        // These values are fetched from the current UI state, which should be up-to-date
        formData.append('full_name', document.getElementById('edit_full_name').value);
        formData.append('phone_number', document.getElementById('edit_phone_number').value);
        formData.append('date_of_birth', document.getElementById('edit_date_of_birth').value);

        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                showNotification(result.message, 'success', 'profileUpdatedSuccess');
                fetchProfileData(); // Refresh UI after successful update
            } else {
                let langKey = 'errorUploadingProfilePicture';
                if (result.message.includes('Only JPG, JPEG, PNG, and GIF files are allowed')) {
                    langKey = 'fileTypeNotAllowed';
                } else if (result.message.includes('File size is too large')) {
                    langKey = 'fileSizeTooLarge';
                } else if (result.message.includes('Failed to save merged profile picture')) {
                    langKey = 'failedToSaveMergedImage';
                } else if (result.message.includes('Failed to load images for merging')) {
                    langKey = 'failedToLoadImagesForMerging';
                } else if (result.message.includes('Background image not found')) {
                    langKey = 'backgroundNotFoundUploadOriginal';
                } else if (result.message.includes('Failed to upload profile picture')) {
                    langKey = 'failedToUploadProfilePicture';
                }
                showNotification(result.message, 'error', langKey);
            }
        } catch (error) {
            console.error('Error uploading profile picture:', error);
            showNotification('An error occurred while uploading profile picture.', 'error', 'errorUploadingProfilePicture');
        }
    });

    // Handle Delete Profile Picture
    deleteProfilePictureBtn.addEventListener('click', async () => {
        if (confirm(translations['deleteProfilePictureButtonConfirm'][currentLanguage] || 'Are you sure you want to delete your profile picture? This will revert to the default blank image.')) {
            const formData = new FormData();
            formData.append('delete_profile_picture', '1');
            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showNotification(result.message, 'success', 'profilePictureDeletedSuccess');
                    fetchProfileData(); // Refresh UI after successful deletion
                } else {
                    let langKey = 'errorDeletingProfilePicture';
                    if (result.message.includes('No custom profile picture to delete')) {
                        langKey = 'noCustomProfilePicture';
                    } else if (result.message.includes('Failed to update profile picture in database')) {
                        langKey = 'failedToUpdateProfilePictureDB';
                    } else if (result.message.includes('Failed to delete profile picture file')) {
                        langKey = 'failedToDeleteProfilePictureFile';
                    }
                    showNotification(result.message, 'error', langKey);
                }
            } catch (error) {
                console.error('Error deleting profile picture:', error);
                showNotification('An error occurred while deleting profile picture.', 'error', 'errorDeletingProfilePicture');
            }
        }
    });

    // Handle Add Email Form Submission via AJAX
    addEmailForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('add_email', '1'); // Explicitly set add_email flag
        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                showNotification(result.message, 'success', 'emailAddedSuccess');
                this.reset(); // Clear the input field
                fetchProfileData(); // Refresh UI to show new email
            } else {
                let langKey = 'errorAddingEmail';
                if (result.message.includes('Email cannot be empty')) {
                    langKey = 'emailCannotBeEmpty';
                } else if (result.message.includes('Invalid email format')) {
                    langKey = 'invalidEmailFormat';
                } else if (result.message.includes('This email is already your primary email')) {
                    langKey = 'emailAlreadyPrimary';
                } else if (result.message.includes('This email is already registered as your additional email')) {
                    langKey = 'emailAlreadyRegisteredUser';
                } else if (result.message.includes('This email is already used as a primary email by another account')) {
                    langKey = 'emailUsedByOtherPrimary';
                } else if (result.message.includes('This email is already used as an additional email by another account')) {
                    langKey = 'emailUsedByOtherAdditional';
                }
                showNotification(result.message, 'error', langKey);
            }
        } catch (error) {
            console.error('Error adding email:', error);
            showNotification('An error occurred while adding email.', 'error', 'errorAddingEmail');
        }
    });

    // Handle Delete Additional Email via Event Delegation
    additionalEmailsList.addEventListener('submit', async function (e) {
        if (e.target.classList.contains('delete-email-form')) {
            e.preventDefault();
            if (confirm(translations['deleteThisEmailConfirm'][currentLanguage] || 'Are you sure you want to delete this email?')) {
                const formData = new FormData(e.target);
                formData.append('delete_additional_email', '1'); // Explicitly set delete_additional_email flag
                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showNotification(result.message, 'success', 'emailDeletedSuccess');
                        fetchProfileData(); // Refresh UI to remove deleted email
                    } else {
                        let langKey = 'errorDeletingEmail';
                        if (result.message.includes('Email not found or not authorized to delete')) {
                            langKey = 'emailNotFoundOrUnauthorized';
                        }
                        showNotification(result.message, 'error', langKey);
                    }
                } catch (error) {
                    console.error('Error deleting email:', error);
                    showNotification('An error occurred while deleting email.', 'error', 'errorDeletingEmail');
                }
            }
        }
    });

    // Calendar filter function (client-side filtering)
    window.filterActivityData = function () {
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const filterResultDiv = document.getElementById('filterResult');
        const activityListUl = document.getElementById('activityListUl');

        const startDate = startDateInput.value;
        const endDate = endDateInput.value;

        if (!startDate || !endDate) {
            showNotification(translations['selectBothDates'][currentLanguage] || "Please select both dates!", "error", 'selectBothDates');
            return;
        }

        const startDateTime = new Date(startDate + 'T00:00:00');
        const endDateTime = new Date(endDate + 'T23:59:59'); // End of the day

        if (startDateTime > endDateTime) {
            showNotification(translations['startDateCannotBeLater'][currentLanguage] || "Start date cannot be later than end date.", "error", 'startDateCannotBeLater');
            return;
        }

        filterResultDiv.textContent = `${translations['showDataButton'][currentLanguage] || 'Showing data'} from ${startDate} to ${endDate}`;

        const filteredLogs = window.allActivityLogs.filter(log => {
            const logTimestamp = new Date(log.timestamp);
            return logTimestamp >= startDateTime && logTimestamp <= endDateTime;
        });

        const filteredChartData = processLogsForChart(filteredLogs);
        updateActivityChart(filteredChartData.labels, filteredChartData.data);

        // Update detailed activity list
        activityListUl.innerHTML = ''; // Clear existing list
        if (filteredLogs.length === 0) {
            const li = document.createElement('li');
            li.setAttribute('data-lang-key', 'noActivityWithinRange');
            li.textContent = translations['noActivityWithinRange'][currentLanguage] || 'No activity within this date range.';
            activityListUl.appendChild(li);
        } else {
            filteredLogs.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp)); // Sort newest to oldest
            filteredLogs.forEach(log => {
                const li = document.createElement('li');
                li.innerHTML = `
                    <strong data-lang-activity-type="${log.activity_type}">${translations[log.activity_type]?.[currentLanguage] || log.activity_type}:</strong>
                    <span data-lang-activity-desc="${log.description}">${log.description}</span>
                    <span style="float: right; color: var(--secondary-text-color); font-size: 0.9em;">${new Date(log.timestamp).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                `;
                activityListUl.appendChild(li);
            });
        }
    };

    // Initial application of device class
    applyDeviceClass();
    window.addEventListener('resize', applyDeviceClass);
    window.addEventListener('orientationchange', applyDeviceClass);

    // --- Mobile Sidebar Toggle ---
    sidebarToggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show-mobile-sidebar');
        mobileOverlay.classList.toggle('show');
    });

    // --- Sidebar Menu Navigation with Fly Out Animation ---
    const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');
    sidebarMenuItems.forEach(item => {
        item.addEventListener('click', function (event) {
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
        if (itemHref === currentPage || (currentPage === 'profile.php' && itemHref === 'profile.php')) {
            item.classList.add('active');
        }
    });

    // Refresh data periodically (e.g., every 30 seconds)
    setInterval(fetchProfileData, 30000);
});
