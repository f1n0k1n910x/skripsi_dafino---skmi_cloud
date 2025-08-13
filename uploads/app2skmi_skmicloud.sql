-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 11 Agu 2025 pada 16.03
-- Versi server: 10.11.14-MariaDB
-- Versi PHP: 8.4.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `app2skmi_skmicloud`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `activities`
--

CREATE TABLE `activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_type` varchar(10) DEFAULT NULL,
  `activity_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `activities`
--

INSERT INTO `activities` (`id`, `user_id`, `activity_type`, `description`, `item_id`, `item_type`, `activity_timestamp`, `timestamp`) VALUES
(125, 2, 'change_password', 'Changed account password.', NULL, NULL, '2025-07-31 02:53:28', '2025-07-31 09:53:28'),
(128, 2, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-07-31 03:21:38', '2025-07-31 10:21:38'),
(129, 2, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-07-31 03:21:53', '2025-07-31 10:21:53'),
(130, 2, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-07-31 03:23:22', '2025-07-31 10:23:22'),
(131, 2, 'delete_folder', 'Deleted folder (physical not found): dafinobaru1234', NULL, NULL, '2025-07-31 03:30:26', '2025-07-31 10:30:26'),
(132, 2, 'delete_folder', 'Deleted folder (physical not found): ibu123', NULL, NULL, '2025-07-31 03:30:27', '2025-07-31 10:30:27'),
(133, 2, 'delete_folder', 'Deleted folder (physical not found): nofsal', NULL, NULL, '2025-07-31 03:30:32', '2025-07-31 10:30:32'),
(134, 2, 'delete_folder', 'Deleted folder (physical not found): vika', NULL, NULL, '2025-07-31 03:30:41', '2025-07-31 10:30:41'),
(135, 2, 'create_folder', 'Created folder \"dafino\"', NULL, NULL, '2025-07-31 03:30:45', '2025-07-31 10:30:45'),
(136, 2, 'upload_file', 'Uploaded file \"register.php - cPanel File Manager v3.html\"', NULL, NULL, '2025-07-31 03:30:54', '2025-07-31 10:30:54'),
(137, 2, 'upload_file', 'Uploaded file \"Formulir pendaftaran sempro.docx\"', NULL, NULL, '2025-07-31 03:35:57', '2025-07-31 10:35:57'),
(138, 2, 'upload_file', 'Uploaded file \"Gambar 1 - Copy - Copy.png\"', NULL, NULL, '2025-07-31 03:47:43', '2025-07-31 10:47:43'),
(139, 2, 'upload_file', 'Uploaded file \"desain figma.png\"', NULL, NULL, '2025-07-31 03:48:25', '2025-07-31 10:48:25'),
(140, 2, 'upload_file', 'Uploaded file \"users.sql\"', NULL, NULL, '2025-07-31 03:48:39', '2025-07-31 10:48:39'),
(141, 2, 'upload_file', 'Uploaded file \"register.php - cPanel File Manager v3.html\"', NULL, NULL, '2025-07-31 03:48:40', '2025-07-31 10:48:40'),
(142, 2, 'create_folder', 'Created folder \"bagusan mana?\"', NULL, NULL, '2025-07-31 03:52:38', '2025-07-31 10:52:38'),
(143, 2, 'upload_file', 'Uploaded file \"12. BAB III METODOLOGI PENELITIAN.pdf\"', NULL, NULL, '2025-07-31 03:53:20', '2025-07-31 10:53:20'),
(144, 2, 'upload_file', 'Uploaded file \"01. Duitku Merchant Registration Form - CV SKM Indonesia.docx\"', NULL, NULL, '2025-07-31 03:54:24', '2025-07-31 10:54:24'),
(145, 2, 'upload_file', 'Uploaded file \"LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx\"', NULL, NULL, '2025-07-31 03:55:42', '2025-07-31 10:55:42'),
(147, 2, 'upload_file', 'Uploaded file \"LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx\"', NULL, NULL, '2025-07-31 03:57:45', '2025-07-31 10:57:45'),
(148, 2, 'upload_file', 'Uploaded file \"08 Red Sun (Maniac Agenda Mix).mp3\"', NULL, NULL, '2025-07-31 04:07:54', '2025-07-31 11:07:54'),
(222, 2, 'extract_file', 'Extracted ZIP file: archive_20250801_030820.zip. Result: Success', NULL, NULL, '2025-08-02 11:06:58', '2025-08-02 18:06:58'),
(254, 14, 'upload_file', 'Uploaded file \"Screenshot_20250809_101929_Chrome.jpg\"', NULL, NULL, '2025-08-09 04:34:30', '2025-08-09 11:34:30'),
(255, 14, 'upload_file', 'Uploaded file \"Photo Profile(1).png\"', NULL, NULL, '2025-08-09 04:35:06', '2025-08-09 11:35:06'),
(256, 14, 'upload_file', 'Uploaded file \"Screenshot_20250809_125635_Drive.jpg\"', NULL, NULL, '2025-08-09 07:26:38', '2025-08-09 14:26:38'),
(257, 14, 'rename_folder', 'Renamed folder \"vika\" to \"vika1\"', NULL, NULL, '2025-08-11 06:09:49', '2025-08-11 13:09:49'),
(258, 14, 'star_item', 'Starred folder: archive_20250801_030820 (ID: 72)', NULL, NULL, '2025-08-11 06:19:22', '2025-08-11 13:19:22'),
(259, 14, 'unstar_item', 'Unstarred folder:  (ID: 72)', NULL, NULL, '2025-08-11 06:19:28', '2025-08-11 13:19:28'),
(260, 14, 'star_item', 'Starred file: Screenshot_20250809_101929_Chrome.jpg (ID: 148)', NULL, NULL, '2025-08-11 06:23:14', '2025-08-11 13:23:14'),
(261, 14, 'unstar_item', 'Unstarred file:  (ID: 148)', NULL, NULL, '2025-08-11 06:28:20', '2025-08-11 13:28:20'),
(262, 14, 'star_item', 'Starred folder: archive_20250801_030820 (ID: 72)', NULL, NULL, '2025-08-11 06:34:24', '2025-08-11 13:34:24'),
(263, 14, 'star_item', 'Starred file: Photo Profile(1).png (ID: 149)', NULL, NULL, '2025-08-11 06:34:41', '2025-08-11 13:34:41'),
(264, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-11 06:36:05', '2025-08-11 13:36:05'),
(265, 14, 'add_email', 'Added additional email: mochamad.22160@mhs.unesa.ac.id', NULL, NULL, '2025-08-11 06:36:40', '2025-08-11 13:36:40'),
(266, 15, 'star_item', 'Starred folder: archive_20250805_090435 (ID: 75)', NULL, NULL, '2025-08-11 06:38:11', '2025-08-11 13:38:11'),
(267, 15, 'star_item', 'Starred file: archive_20250806_133635.zip (ID: 146)', NULL, NULL, '2025-08-11 06:38:52', '2025-08-11 13:38:52'),
(268, 15, 'star_item', 'Starred file: LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx (ID: 104)', NULL, NULL, '2025-08-11 06:50:08', '2025-08-11 13:50:08'),
(269, 14, 'delete_file', 'Deleted file: LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', NULL, NULL, '2025-08-11 06:55:40', '2025-08-11 13:55:40'),
(270, 14, 'unstar_item', 'Unstarred folder: archive_20250801_030820 (ID: 72)', NULL, NULL, '2025-08-11 07:10:35', '2025-08-11 14:10:35'),
(271, 14, 'star_item', 'Starred file: register.php - cPanel File Manager v3.html (ID: 100)', NULL, NULL, '2025-08-11 07:10:43', '2025-08-11 14:10:43'),
(272, 14, 'unstar_item', 'Unstarred file: Photo Profile(1).png (ID: 149)', NULL, NULL, '2025-08-11 07:20:08', '2025-08-11 14:20:08'),
(273, 14, 'star_item', 'Starred file: Screenshot_20250809_125635_Drive.jpg (ID: 150)', NULL, NULL, '2025-08-11 07:20:14', '2025-08-11 14:20:14'),
(274, 14, 'star_item', 'Starred folder: bagusan mana? (ID: 34)', NULL, NULL, '2025-08-11 08:55:35', '2025-08-11 15:55:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_starred` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `files`
--

INSERT INTO `files` (`id`, `file_name`, `file_path`, `file_size`, `file_type`, `folder_id`, `uploaded_at`, `user_id`, `created_at`, `is_starred`) VALUES
(99, 'users.sql', 'uploads/users.sql', 2997, 'sql', NULL, '2025-07-31 10:48:39', 2, '2025-07-31 03:48:39', 0),
(100, 'register.php - cPanel File Manager v3.html', 'uploads/register.php - cPanel File Manager v3.html', 139316, 'html', NULL, '2025-07-31 10:48:40', 2, '2025-07-31 03:48:40', 0),
(101, '12. BAB III METODOLOGI PENELITIAN.pdf', 'uploads/bagusan mana?/12. BAB III METODOLOGI PENELITIAN.pdf', 2050285, 'pdf', 34, '2025-07-31 10:53:20', 2, '2025-07-31 03:53:20', 0),
(102, '01. Duitku Merchant Registration Form - CV SKM Indonesia.docx', 'uploads/bagusan mana?/01. Duitku Merchant Registration Form - CV SKM Indonesia.docx', 39030, 'docx', 34, '2025-07-31 10:54:24', 2, '2025-07-31 03:54:24', 0),
(103, 'LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 'uploads/bagusan mana?/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 3259515, 'docx', 34, '2025-07-31 10:55:42', 2, '2025-07-31 03:55:42', 0),
(105, '08 Red Sun (Maniac Agenda Mix).mp3', 'uploads/bagusan mana?/08 Red Sun (Maniac Agenda Mix).mp3', 5449195, 'mp3', 34, '2025-07-31 11:07:54', 2, '2025-07-31 04:07:54', 0),
(133, 'LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1)(1).docx', 'uploads/archive_20250801_030820/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1)(1).docx', 3259515, 'docx', 72, '2025-08-02 18:06:58', NULL, '2025-08-02 11:06:58', 0),
(134, 'ant-design_code-filled.png', 'uploads/archive_20250801_030820/ant-design_code-filled.png', 484, 'png', 72, '2025-08-02 18:06:58', NULL, '2025-08-02 11:06:58', 0),
(139, 'archive_20250805_090435.zip', 'uploads/archive_20250805_090435.zip', 3025381, 'zip', NULL, '2025-08-05 16:04:35', NULL, '2025-08-05 09:04:35', 0),
(140, 'register.php - cPanel File Manager v3.html', 'uploads/archive_20250805_090435/register.php - cPanel File Manager v3.html', 139316, 'html', 75, '2025-08-05 16:04:42', NULL, '2025-08-05 09:04:42', 0),
(141, 'LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 'uploads/archive_20250805_090435/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 3259515, 'docx', 75, '2025-08-05 16:04:42', NULL, '2025-08-05 09:04:42', 0),
(146, 'archive_20250806_133635.zip', 'uploads/archive_20250806_133635.zip', 10302872, 'zip', NULL, '2025-08-06 20:36:36', NULL, '2025-08-06 13:36:36', 0),
(148, 'Screenshot_20250809_101929_Chrome.jpg', 'uploads/archive_20250801_030820/Screenshot_20250809_101929_Chrome.jpg', 167413, 'jpg', 72, '2025-08-09 11:34:30', 14, '2025-08-09 04:34:30', 0),
(149, 'Photo Profile(1).png', 'uploads/Photo Profile(1).png', 27702, 'png', NULL, '2025-08-09 11:35:06', 14, '2025-08-09 04:35:06', 0),
(150, 'Screenshot_20250809_125635_Drive.jpg', 'uploads/Screenshot_20250809_125635_Drive.jpg', 335849, 'jpg', NULL, '2025-08-09 14:26:38', 14, '2025-08-09 07:26:38', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `is_starred` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `folders`
--

INSERT INTO `folders` (`id`, `folder_name`, `parent_id`, `created_at`, `updated_at`, `user_id`, `is_starred`) VALUES
(34, 'bagusan mana?', NULL, '2025-07-31 10:52:38', '2025-08-08 09:53:01', NULL, 0),
(53, 'vika1', NULL, '2025-08-01 14:00:43', '2025-08-11 06:09:49', NULL, 0),
(72, 'archive_20250801_030820', NULL, '2025-08-02 18:06:58', '2025-08-02 11:06:58', NULL, 0),
(75, 'archive_20250805_090435', NULL, '2025-08-05 16:04:42', '2025-08-08 09:58:04', NULL, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `login_time`, `ip_address`) VALUES
(17, 2, '2025-07-31 02:50:00', '36.90.51.223'),
(19, 2, '2025-07-31 03:20:28', '36.90.51.223'),
(26, 2, '2025-07-31 10:03:00', '36.90.51.223'),
(35, 2, '2025-08-02 11:03:55', '36.90.49.17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `shared_links`
--

CREATE TABLE `shared_links` (
  `id` int(11) NOT NULL,
  `original_file` varchar(255) NOT NULL,
  `short_code` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `shared_links`
--

INSERT INTO `shared_links` (`id`, `original_file`, `short_code`, `created_at`) VALUES
(1, 'uploads/archive_20250718_042409.tar', 'I22xWC', '2025-07-23 02:46:51'),
(2, 'uploads/ant-design_code-filled.png', 'FidbVM', '2025-07-23 05:59:01'),
(3, 'uploads/0fimtgafvw461-removebg-preview.png', 'vwX35s', '2025-07-23 07:44:03'),
(4, 'uploads/11 It Has To Be This Way (Platinum Mix).mp3', 'no6NB8', '2025-07-23 07:44:08'),
(5, 'uploads/cloningan_gdrive.sql', 't0MY7G', '2025-07-23 07:57:27'),
(6, 'uploads/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1)_1.docx', 'P8p27D', '2025-07-23 08:04:35'),
(7, 'uploads/1-16. Mass Destruction.mp3', 'B4SUyv', '2025-07-24 05:31:43'),
(8, 'uploads/winrar-x64-701.exe', 'FXshkD', '2025-07-25 08:41:38'),
(9, 'uploads/archive_20250717_100442.tar.gz', 'IuUtRT', '2025-07-26 11:53:43'),
(10, 'uploads/cloningan_gdrive (1).sql', '8woGj1', '2025-07-30 01:16:52'),
(11, 'uploads/bagus.tar', 'GY1zIe', '2025-07-30 01:16:56'),
(12, 'uploads/Konten Blog SKMI ArtSpace.zip', 'BGmeFo', '2025-07-30 01:19:01'),
(13, 'uploads/Aldnoah Zero .mp3', 'VbOC2p', '2025-07-30 01:26:36'),
(14, 'uploads/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU Buka saja_1.docx', 'QbyKR2', '2025-07-31 01:47:13'),
(15, 'uploads/desain figma.png', 'jB6QxK', '2025-07-31 06:43:37'),
(16, 'uploads/register.php - cPanel File Manager v3.html', 'a6HqBR', '2025-07-31 07:02:35'),
(17, 'uploads/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 'J3pUdn', '2025-07-31 07:20:56'),
(18, 'uploads/users.sql', 'VdreQG', '2025-07-31 10:07:37'),
(19, 'uploads/archive_20250801_022042.zip', 'HOGc57', '2025-08-01 02:23:39'),
(20, 'uploads/Payment_174599582777K41.pdf', 'AIlu51', '2025-08-02 11:04:48'),
(21, 'uploads/Mouse Crusor.png', 'rDu14J', '2025-08-06 03:21:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'User',
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_login_time` datetime DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `account_status` varchar(50) DEFAULT 'Active',
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `is_member` tinyint(1) NOT NULL DEFAULT 0,
  `fullname` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `profile_picture`, `role`, `last_login_ip`, `last_login_time`, `full_name`, `phone_number`, `date_of_birth`, `account_status`, `last_active`, `last_login`, `is_member`, `fullname`) VALUES
(2, 'sukmoskmi', 'sukmo.skmi@gmail.com', '$2y$10$LZ0Uk5g4dEPBRs3tm9seKu30jcSsYDxtDs3cLf3pb4alxlEs8fAie', '2025-07-31 02:46:53', 'uploads/profile_pictures/profile_688ae1aa6244d.png', 'User', '36.90.49.17', '2025-08-02 11:03:55', 'Sukmo Hadi Winoto', '081330722624', '2025-07-31', 'Active', '2025-08-02 11:03:55', NULL, 0, NULL),
(14, 'dafinoskmi', 'dafinoharyonida@gmail.com', '$2y$10$bf7j/2DS/M8jiZVoZlXEaO8IHRJ1SV6ZUxoNlqTeokKADlvI4fpfW', '2025-08-09 04:08:10', 'uploads/profile_pictures/profile_68998f555f2d4.png', 'User', NULL, NULL, 'Mochammad Dafino Haryonida', '082332988490', '2003-03-15', 'Active', '2025-08-11 06:36:05', NULL, 0, NULL),
(15, 'dafino', 'dafinomochammad@gmail.com', '$2y$10$Lt8jumqWVe8V2pTGKozIP.NEhEb7qPReVWTq.D04G9MlfvG9aFmRW', '2025-08-11 06:37:59', NULL, 'User', NULL, NULL, NULL, NULL, NULL, 'Active', '2025-08-11 06:37:59', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_emails`
--

CREATE TABLE `user_emails` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user_emails`
--

INSERT INTO `user_emails` (`id`, `user_id`, `email`, `is_verified`, `created_at`) VALUES
(11, 14, 'mochamad.22160@mhs.unesa.ac.id', 0, '2025-08-11 06:36:40');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `fk_user_id` (`user_id`);

--
-- Indeks untuk tabel `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `fk_folders_user` (`user_id`);

--
-- Indeks untuk tabel `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `shared_links`
--
ALTER TABLE `shared_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `short_code` (`short_code`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `user_emails`
--
ALTER TABLE `user_emails`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activities`
--
ALTER TABLE `activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=275;

--
-- AUTO_INCREMENT untuk tabel `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT untuk tabel `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT untuk tabel `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT untuk tabel `shared_links`
--
ALTER TABLE `shared_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `user_emails`
--
ALTER TABLE `user_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_files_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `fk_folders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_emails`
--
ALTER TABLE `user_emails`
  ADD CONSTRAINT `user_emails_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
