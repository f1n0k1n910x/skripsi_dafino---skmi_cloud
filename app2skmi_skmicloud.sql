-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 06 Agu 2025 pada 19.54
-- Versi server: 10.11.13-MariaDB
-- Versi PHP: 8.3.23

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
(224, 10, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-05 03:14:56', '2025-08-05 10:14:56'),
(225, 10, 'add_email', 'Added additional email: mochamad.22160@mhs.unesa.ac.id', NULL, NULL, '2025-08-05 03:31:36', '2025-08-05 10:31:36'),
(228, 10, 'delete_folder', 'Deleted folder: skmibaru', NULL, NULL, '2025-08-05 04:02:48', '2025-08-05 11:02:48'),
(229, 10, 'extract_file', 'Extracted ZIP file: archive_20250801_030820.zip. Result: Success', NULL, NULL, '2025-08-05 07:14:26', '2025-08-05 14:14:26'),
(230, 10, 'delete_file', 'Deleted file: archive_20250801_030820.zip', NULL, NULL, '2025-08-05 09:02:14', '2025-08-05 16:02:14'),
(231, 10, 'delete_folder', 'Deleted folder: archive_20250801_030820(1)', NULL, NULL, '2025-08-05 09:04:29', '2025-08-05 16:04:29'),
(232, 10, 'archive', 'Archived LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx, register.php - cPanel File Manager v3.html to archive_20250805_090435.zip (Format: zip)', NULL, NULL, '2025-08-05 09:04:35', '2025-08-05 16:04:35'),
(233, 10, 'extract_file', 'Extracted ZIP file: archive_20250805_090435.zip. Result: Success', NULL, NULL, '2025-08-05 09:04:42', '2025-08-05 16:04:42'),
(234, 10, 'upload_file', 'Uploaded file \"Photo Profile.png\"', NULL, NULL, '2025-08-06 02:59:50', '2025-08-06 09:59:50'),
(235, 10, 'upload_file', 'Uploaded file \"Photo Profile.png\"', NULL, NULL, '2025-08-06 03:00:06', '2025-08-06 10:00:06'),
(236, 10, 'upload_file', 'Uploaded file \"Mouse Crusor.png\"', NULL, NULL, '2025-08-06 03:00:31', '2025-08-06 10:00:31'),
(237, 10, 'upload_file', 'Uploaded file \"DRAF_CONTOH_MoA_Magang.docx\"', NULL, NULL, '2025-08-06 08:43:17', '2025-08-06 15:43:17');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `files`
--

INSERT INTO `files` (`id`, `file_name`, `file_path`, `file_size`, `file_type`, `folder_id`, `uploaded_at`, `user_id`, `created_at`) VALUES
(99, 'users.sql', 'uploads/users.sql', 2997, 'sql', NULL, '2025-07-31 10:48:39', 2, '2025-07-31 03:48:39'),
(100, 'register.php - cPanel File Manager v3.html', 'uploads/register.php - cPanel File Manager v3.html', 139316, 'html', NULL, '2025-07-31 10:48:40', 2, '2025-07-31 03:48:40'),
(101, '12. BAB III METODOLOGI PENELITIAN.pdf', 'uploads/bagusan mana?/12. BAB III METODOLOGI PENELITIAN.pdf', 2050285, 'pdf', 34, '2025-07-31 10:53:20', 2, '2025-07-31 03:53:20'),
(102, '01. Duitku Merchant Registration Form - CV SKM Indonesia.docx', 'uploads/bagusan mana?/01. Duitku Merchant Registration Form - CV SKM Indonesia.docx', 39030, 'docx', 34, '2025-07-31 10:54:24', 2, '2025-07-31 03:54:24'),
(103, 'LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 'uploads/bagusan mana?/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 3259515, 'docx', 34, '2025-07-31 10:55:42', 2, '2025-07-31 03:55:42'),
(104, 'LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 'uploads/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 3259515, 'docx', NULL, '2025-07-31 10:57:45', 2, '2025-07-31 03:57:45'),
(105, '08 Red Sun (Maniac Agenda Mix).mp3', 'uploads/bagusan mana?/08 Red Sun (Maniac Agenda Mix).mp3', 5449195, 'mp3', 34, '2025-07-31 11:07:54', 2, '2025-07-31 04:07:54'),
(133, 'LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1)(1).docx', 'uploads/archive_20250801_030820/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1)(1).docx', 3259515, 'docx', 72, '2025-08-02 18:06:58', NULL, '2025-08-02 11:06:58'),
(134, 'ant-design_code-filled.png', 'uploads/archive_20250801_030820/ant-design_code-filled.png', 484, 'png', 72, '2025-08-02 18:06:58', NULL, '2025-08-02 11:06:58'),
(139, 'archive_20250805_090435.zip', 'uploads/archive_20250805_090435.zip', 3025381, 'zip', NULL, '2025-08-05 16:04:35', NULL, '2025-08-05 09:04:35'),
(140, 'register.php - cPanel File Manager v3.html', 'uploads/archive_20250805_090435/register.php - cPanel File Manager v3.html', 139316, 'html', 75, '2025-08-05 16:04:42', NULL, '2025-08-05 09:04:42'),
(141, 'LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 'uploads/archive_20250805_090435/LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).docx', 3259515, 'docx', 75, '2025-08-05 16:04:42', NULL, '2025-08-05 09:04:42'),
(142, 'Photo Profile.png', 'uploads/vika/Photo Profile.png', 27702, 'png', 53, '2025-08-06 09:59:50', 10, '2025-08-06 02:59:50'),
(143, 'Photo Profile.png', 'uploads/Photo Profile.png', 27702, 'png', NULL, '2025-08-06 10:00:06', 10, '2025-08-06 03:00:06'),
(144, 'Mouse Crusor.png', 'uploads/Mouse Crusor.png', 12775, 'png', NULL, '2025-08-06 10:00:31', 10, '2025-08-06 03:00:31'),
(145, 'DRAF_CONTOH_MoA_Magang.docx', 'uploads/DRAF_CONTOH_MoA_Magang.docx', 2962269, 'docx', NULL, '2025-08-06 15:43:17', 10, '2025-08-06 08:43:17');

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
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `folders`
--

INSERT INTO `folders` (`id`, `folder_name`, `parent_id`, `created_at`, `updated_at`, `user_id`) VALUES
(34, 'bagusan mana?', NULL, '2025-07-31 10:52:38', '2025-07-31 03:52:38', NULL),
(53, 'vika', NULL, '2025-08-01 14:00:43', '2025-08-01 07:00:43', NULL),
(72, 'archive_20250801_030820', NULL, '2025-08-02 18:06:58', '2025-08-02 11:06:58', NULL),
(75, 'archive_20250805_090435', NULL, '2025-08-05 16:04:42', '2025-08-05 09:04:42', NULL);

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
(35, 2, '2025-08-02 11:03:55', '36.90.49.17'),
(39, 10, '2025-08-05 06:48:45', '36.90.49.17'),
(40, 10, '2025-08-05 08:31:56', '36.90.49.17'),
(41, 11, '2025-08-05 09:02:38', '36.90.49.17'),
(42, 10, '2025-08-06 01:26:35', '36.90.49.17'),
(43, 10, '2025-08-06 06:30:26', '36.90.49.17'),
(44, 10, '2025-08-06 07:14:41', '36.90.49.17'),
(45, 10, '2025-08-06 08:35:26', '36.90.49.17'),
(46, 10, '2025-08-06 08:36:57', '36.90.49.17'),
(47, 11, '2025-08-06 08:38:04', '36.90.49.17'),
(48, 10, '2025-08-06 09:48:24', '36.90.49.17'),
(49, 10, '2025-08-06 12:46:08', '182.253.116.17');

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
  `full_name` varchar(255) NOT NULL,
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
(10, 'dafinoskmi', 'dafinoharyonida@gmail.com', '$2y$10$2Js8TXupiCebwMsCrLKV../K4tRjdLXF/3zvtEOLCDpsWVH2/4Fka', '2025-08-05 03:11:50', 'uploads/profile_pictures/profile_6891773053f37.png', 'User', '182.253.116.17', '2025-08-06 12:46:08', 'Mochammad Dafino Haryonida', '082332988490', '2003-03-15', 'Active', '2025-08-06 05:46:08', '2025-08-05 03:11:50', 1, NULL),
(11, 'dafinobaru', 'dafinomochammad@gmail.com', '$2y$10$xLSMaUtx3e/Jt7oJQQOZZe4F39STqnRPgBO0EKInwXZLXKCbHtvVC', '2025-08-05 03:44:31', NULL, 'User', '36.90.49.17', '2025-08-06 08:38:04', '', NULL, NULL, 'Active', '2025-08-06 01:38:04', '2025-08-05 03:44:31', 1, NULL);

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
(9, 10, 'mochamad.22160@mhs.unesa.ac.id', 0, '2025-08-05 03:31:36');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=238;

--
-- AUTO_INCREMENT untuk tabel `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT untuk tabel `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT untuk tabel `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT untuk tabel `shared_links`
--
ALTER TABLE `shared_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `user_emails`
--
ALTER TABLE `user_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
