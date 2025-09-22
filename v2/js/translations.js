// --- Translation Logic ---
const translations = {
  // General
  'filePreview': { 'id': 'Pratinjau File', 'en': 'File Previewaa' },
  'guest': { 'id': 'Tamu', 'en': 'Guest' },
  'home': { 'id': 'Beranda', 'en': 'Home' },
  'back': { 'id': 'Kembali', 'en': 'Back' },
  'downloadFile': { 'id': 'Unduh File', 'en': 'Download File' },
  'download': { 'id': 'Unduh', 'en': 'Download' },

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

  // Dashboard Header
  'profileDashboardTitle': { 'id': 'Dasbor Profil Saya', 'en': 'My Profile Dashboard' },
  'helloUserGreeting': { 'id': 'Halo', 'en': 'Hello' }, // Will be combined with username

  // Profile Card
  'welcomeMessage': { 'id': 'Selamat datang di SKMI Cloud Storage', 'en': 'Welcome to SKMI Cloud Storage' },
  'usernameLabel': { 'id': 'Nama Pengguna', 'en': 'Username' },
  'emailLabel': { 'id': 'Email', 'en': 'Email' },
  'phoneNumberLabel': { 'id': 'Nomor Telepon', 'en': 'Phone Number' },
  'dateOfBirthLabel': { 'id': 'Tanggal Lahir', 'en': 'Date of Birth' },
  'joinDateLabel': { 'id': 'Tanggal Bergabung', 'en': 'Join Date' },
  'accountStatusLabel': { 'id': 'Status Akun', 'en': 'Account Status' },
  'totalFilesLabel': { 'id': 'Total File', 'en': 'Total Files' },
  'storageUsedLabel': { 'id': 'Penyimpanan Terpakai', 'en': 'Storage Used' },
  'totalQuotaLabel': { 'id': 'Total Kuota', 'en': 'Total Quota' },
  'editProfileButton': { 'id': 'Edit Profil', 'en': 'Edit Profile' },
  'changePasswordButton': { 'id': 'Ubah Kata Sandi', 'en': 'Change Password' },
  'logoutButton': { 'id': 'Keluar', 'en': 'Logout' },

  // Right Column - Translation Buttons Card
  'languageSelectionTitle': { 'id': 'Pilihan Bahasa', 'en': 'Language Selection' },

  // Activity History Card
  'activityHistoryTitle': { 'id': 'Riwayat Aktivitas', 'en': 'Activity History' },
  'startDateLabel': { 'id': 'Tanggal Mulai:', 'en': 'Start Date:' },
  'endDateLabel': { 'id': 'Tanggal Akhir:', 'en': 'End Date:' },
  'showDataButton': { 'id': 'Tampilkan Data', 'en': 'Show Data' },
  'activityDetailsTitle': { 'id': 'Detail Aktivitas:', 'en': 'Activity Details:' },
  'noActivityHistory': { 'id': 'Tidak ada riwayat aktivitas.', 'en': 'No activity history.' },
  'noActivityWithinRange': { 'id': 'Tidak ada aktivitas dalam rentang tanggal ini.', 'en': 'No activity within this date range.' },
  'selectBothDates': { 'id': 'Mohon pilih kedua tanggal!', 'en': 'Please select both dates!' },
  'startDateCannotBeLater': { 'id': 'Tanggal mulai tidak boleh lebih lambat dari tanggal akhir.', 'en': 'Start date cannot be later than end date.' },

  // Activity Types (dynamic content)
  'change_password': { 'id': 'Ubah Kata Sandi', 'en': 'Change Password' },
  'update_profile': { 'id': 'Perbarui Profil', 'en': 'Update Profile' },
  'delete_profile_picture': { 'id': 'Hapus Foto Profil', 'en': 'Delete Profile Picture' },
  'add_email': { 'id': 'Tambah Email', 'en': 'Add Email' },
  'delete_email': { 'id': 'Hapus Email', 'en': 'Delete Email' },
  // Add more activity types as needed

  // Additional Emails Card
  'additionalEmailsTitle': { 'id': 'Email Tambahan', 'en': 'Additional Emails' },
  'enterNewEmail': { 'id': 'Masukkan email baru', 'en': 'Enter new email' },
  'addButton': { 'id': 'Tambah', 'en': 'Add' },
  'deleteThisEmail': { 'id': 'Hapus email ini', 'en': 'Delete this email' },
  'primaryEmailLabel': { 'id': ' (Email Utama)', 'en': ' (Primary Email)' }, // Will be appended to email address

  // Change Password Modal
  'changePasswordModalTitle': { 'id': 'Ubah Kata Sandi', 'en': 'Change Password' },
  'oldPasswordLabel': { 'id': 'Kata Sandi Lama:', 'en': 'Old Password:' },
  'newPasswordLabel': { 'id': 'Kata Sandi Baru:', 'en': 'New Password:' },
  'confirmNewPasswordLabel': { 'id': 'Konfirmasi Kata Sandi Baru:', 'en': 'Confirm New Password:' },
  'saveNewPasswordButton': { 'id': 'Simpan Kata Sandi Baru', 'en': 'Save New Password' },

  // Edit Profile Modal
  'editProfileModalTitle': { 'id': 'Edit Profil', 'en': 'Edit Profile' },
  'fullNameLabel': { 'id': 'Nama Lengkap:', 'en': 'Full Name:' },
  'profilePictureLabel': { 'id': 'Foto Profil:', 'en': 'Profile Picture:' },
  'currentLabel': { 'id': 'Saat Ini:', 'en': 'Current:' },
  'deleteProfilePictureButton': { 'id': 'Hapus Foto Profil', 'en': 'Delete Profile Picture' },
  'saveChangesButton': { 'id': 'Simpan Perubahan', 'en': 'Save Changes' },

  // Notifications (dynamic messages, but can have base translations)
  'failedToLoadProfileData': { 'id': 'Gagal memuat data profil.', 'en': 'Failed to load profile data.' },
  'errorChangingPassword': { 'id': 'Terjadi kesalahan saat mengubah kata sandi.', 'en': 'An error occurred while changing password.' },
  'errorUpdatingProfile': { 'id': 'Terjadi kesalahan saat memperbarui profil.', 'en': 'An error occurred while updating profile.' },
  'errorUploadingProfilePicture': { 'id': 'Terjadi kesalahan saat mengunggah foto profil.', 'en': 'An error occurred while uploading profile picture.' },
  'errorDeletingProfilePicture': { 'id': 'Terjadi kesalahan saat menghapus foto profil.', 'en': 'An error occurred while deleting profile picture.' },
  'errorAddingEmail': { 'id': 'Terjadi kesalahan saat menambahkan email.', 'en': 'An error occurred while adding email.' },
  'errorDeletingEmail': { 'id': 'Terjadi kesalahan saat menghapus email.', 'en': 'An error occurred while deleting email.' },
  'profilePictureDeletedSuccess': { 'id': 'Foto profil berhasil dihapus.', 'en': 'Profile picture deleted successfully.' },
  'passwordChangedSuccess': { 'id': 'Kata sandi berhasil diubah.', 'en': 'Password changed successfully.' },
  'profileUpdatedSuccess': { 'id': 'Profil berhasil diperbarui.', 'en': 'Profile updated successfully.' },
  'emailAddedSuccess': { 'id': 'Email berhasil ditambahkan!', 'en': 'Email added successfully!' },
  'emailDeletedSuccess': { 'id': 'Email tambahan berhasil dihapus.', 'en': 'Additional email deleted successfully.' },
  'oldPasswordIncorrect': { 'id': 'Kata sandi lama salah.', 'en': 'Old password is incorrect.' },
  'newPasswordMismatch': { 'id': 'Konfirmasi kata sandi baru tidak cocok.', 'en': 'New password confirmation does not match.' },
  'passwordTooShort': { 'id': 'Kata sandi baru harus minimal 6 karakter.', 'en': 'New password must be at least 6 characters long.' },
  'invalidEmailFormat': { 'id': 'Format email tidak valid.', 'en': 'Invalid email format.' },
  'emailAlreadyPrimary': { 'id': 'Email ini sudah menjadi email utama Anda.', 'en': 'This email is already your primary email.' },
  'emailAlreadyRegisteredUser': { 'id': 'Email ini sudah terdaftar sebagai email tambahan Anda.', 'en': 'This email is already registered as your additional email.' },
  'emailUsedByOtherPrimary': { 'id': 'Email ini sudah digunakan sebagai email utama oleh akun lain.', 'en': 'This email is already used as a primary email by another account.' },
  'emailUsedByOtherAdditional': { 'id': 'Email ini sudah digunakan sebagai email tambahan oleh akun lain.', 'en': 'This email is already used as an additional email by another account.' },
  'noCustomProfilePicture': { 'id': 'Tidak ada foto profil kustom untuk dihapus.', 'en': 'No custom profile picture to delete.' },
  'emailNotFoundOrUnauthorized': { 'id': 'Email tidak ditemukan atau tidak diizinkan untuk dihapus.', 'en': 'Email not found or not authorized to delete.' },
  'fileTypeNotAllowed': { 'id': 'Hanya file JPG, JPEG, PNG, dan GIF yang diizinkan.', 'en': 'Only JPG, JPEG, PNG, and GIF files are allowed.' },
  'fileSizeTooLarge': { 'id': 'Ukuran file terlalu besar. Maksimal 2MB.', 'en': 'File size is too large. Maximum 2MB.' },
  'failedToSaveMergedImage': { 'id': 'Gagal menyimpan foto profil yang digabungkan.', 'en': 'Failed to save merged profile picture.' },
  'failedToLoadImagesForMerging': { 'id': 'Gagal memuat gambar untuk penggabungan.', 'en': 'Failed to load images for merging.' },
  'backgroundNotFoundUploadOriginal': { 'id': 'Gambar latar belakang tidak ditemukan. Mengunggah PNG asli.', 'en': 'Background image not found. Uploading original PNG.' },
  'failedToUploadProfilePicture': { 'id': 'Gagal mengunggah foto profil.', 'en': 'Failed to upload profile picture.' },
  'failedToUpdateProfilePictureDB': { 'id': 'Gagal memperbarui foto profil di database.', 'en': 'Failed to update profile picture in database.' },
  'failedToDeleteProfilePictureFile': { 'id': 'Gagal menghapus file foto profil.', 'en': 'Failed to delete profile picture file.' },
  'emailCannotBeEmpty': { 'id': 'Email tidak boleh kosong.', 'en': 'Email cannot be empty.' },
  'deleteProfilePictureButtonConfirm': { 'id': 'Apakah Anda yakin ingin menghapus foto profil Anda? Ini akan kembali ke gambar kosong default.', 'en': 'Are you sure you want to delete your profile picture? This will revert to the default blank image.' },
  'deleteThisEmailConfirm': { 'id': 'Apakah Anda yakin ingin menghapus email ini?', 'en': 'Are you sure you want to delete this email?' },
};

let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

function applyTranslation(lang) {
  const mainContent = document.getElementById('mainContent');
  mainContent.style.opacity = '0'; // Hide content before translation

  document.querySelectorAll('[data-lang-key]').forEach(element => {
    const key = element.getAttribute('data-lang-key');
    if (translations[key] && translations[key][lang]) {
      // Handle special cases for dynamic text
      if (key === 'helloUserGreeting') {
        const username = document.getElementById('userInfoGreeting').textContent.split(' ')[1]; // Get current username
        element.textContent = `${translations[key][lang]} ${username}`;
      } else if (key === 'primaryEmailLabel') {
        const email = element.textContent.split(' ')[0]; // Get current email
        element.textContent = `${email}${translations[key][lang]}`;
      } else {
        element.textContent = translations[key][lang];
      }
    }
  });

  // Handle placeholders
  document.querySelectorAll('[data-lang-placeholder]').forEach(element => {
    const key = element.getAttribute('data-lang-placeholder');
    if (translations[key] && translations[key][lang]) {
      element.placeholder = translations[key][lang];
    }
  });

  // Handle titles
  document.querySelectorAll('[data-lang-title]').forEach(element => {
    const key = element.getAttribute('data-lang-title');
    if (translations[key] && translations[key][lang]) {
      element.title = translations[key][lang];
    }
  });

  // Handle activity log types and descriptions
  document.querySelectorAll('#activityListUl li').forEach(li => {
    const strongElement = li.querySelector('strong[data-lang-activity-type]');
    // const spanElement = li.querySelector('span[data-lang-activity-desc]'); // Description is dynamic, no direct translation needed here

    if (strongElement) {
      const activityTypeKey = strongElement.getAttribute('data-lang-activity-type');
      if (translations[activityTypeKey] && translations[activityTypeKey][lang]) {
        strongElement.textContent = `${translations[activityTypeKey][lang]}:`;
      }
    }
  });

  // Update sidebar menu items
  document.querySelectorAll('.sidebar-menu a').forEach(link => {
    const href = link.getAttribute('href');
    let key;
    if (href.includes('control_center.php')) key = 'controlCenter';
    else if (href.includes('index.php')) key = 'myDrive';
    else if (href.includes('priority_files.php')) key = 'priorityFile';
    else if (href.includes('recycle_bin.php')) key = 'recycleBin';
    else if (href.includes('summary.php')) key = 'summary';
    else if (href.includes('members.php')) key = 'members';
    else if (href.includes('profile.php')) key = 'profile';
    else if (href.includes('logout.php')) key = 'logout';

    if (key && translations[key] && translations[key][lang]) {
      const icon = link.querySelector('i');
      link.innerHTML = ''; // Clear existing content
      if (icon) link.appendChild(icon);
      link.appendChild(document.createTextNode(` ${translations[key][lang]}`));
    }
  });

  // Update sidebar storage info
  const sidebarStorageFullMessage = document.getElementById('sidebarStorageFullMessage');
  if (sidebarStorageFullMessage) {
    const key = 'storageFull';
    if (translations[key] && translations[key][lang]) {
      sidebarStorageFullMessage.textContent = translations[key][lang];
    }
  }
  const storageTitle = document.querySelector('.storage-info h4');
  if (storageTitle) {
    const key = 'storage';
    if (translations[key] && translations[key][lang]) {
      storageTitle.textContent = translations[key][lang];
    }
  }

  // Update active language button
  document.getElementById('langIdBtn').classList.remove('active-lang');
  document.getElementById('langEnBtn').classList.remove('active-lang');
  if (lang === 'id') {
    document.getElementById('langIdBtn').classList.add('active-lang');
  } else {
    document.getElementById('langEnBtn').classList.add('active-lang');
  }

  // Update notification messages if any are currently displayed
  const currentNotification = document.getElementById('customNotification');
  if (currentNotification.classList.contains('show')) {
    const messageKey = currentNotification.getAttribute('data-lang-key-notification');
    if (messageKey && translations[messageKey] && translations[messageKey][lang]) {
      currentNotification.textContent = translations[messageKey][lang];
    }
  }

  // After applying all translations, make content visible
  mainContent.style.opacity = '1';
}

window.translations = translations;
window.currentLanguage = currentLanguage;
window.applyTranslation = applyTranslation;