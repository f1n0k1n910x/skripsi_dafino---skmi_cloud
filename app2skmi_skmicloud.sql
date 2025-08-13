-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 13 Agu 2025 pada 19.29
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
(274, 14, 'star_item', 'Starred folder: bagusan mana? (ID: 34)', NULL, NULL, '2025-08-11 08:55:35', '2025-08-11 15:55:35'),
(275, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-11 09:19:20', '2025-08-11 16:19:20'),
(276, 14, 'extract_file', 'Extracted ZIP file: archive_20250805_090435.zip. Result: Success', NULL, NULL, '2025-08-11 12:42:54', '2025-08-11 19:42:54'),
(277, 14, 'extract_file', 'Extracted ZIP file: archive_20250806_133635.zip. Result: Success', NULL, NULL, '2025-08-11 12:43:24', '2025-08-11 19:43:24'),
(278, 14, 'create_folder', 'Created folder \"dafino\"', NULL, NULL, '2025-08-11 12:54:41', '2025-08-11 19:54:41'),
(279, 14, 'unstar_item', 'Unstarred file: Screenshot_20250809_125635_Drive.jpg (ID: 150)', NULL, NULL, '2025-08-11 12:57:27', '2025-08-11 19:57:27'),
(280, 14, 'delete_file', 'Deleted file: archive_20250806_133635.zip', NULL, NULL, '2025-08-11 12:58:56', '2025-08-11 19:58:56'),
(281, 14, 'star_item', 'Starred file: archive_20250805_090435.zip (ID: 139)', NULL, NULL, '2025-08-11 12:59:13', '2025-08-11 19:59:13'),
(282, 14, 'unstar_item', 'Unstarred file: archive_20250805_090435.zip (ID: 139)', NULL, NULL, '2025-08-11 12:59:28', '2025-08-11 19:59:28'),
(283, 14, 'unstar_item', 'Unstarred folder: bagusan mana? (ID: 34)', NULL, NULL, '2025-08-11 12:59:32', '2025-08-11 19:59:32'),
(284, 14, 'delete_file', 'Deleted file: register.php - cPanel File Manager v3.html', NULL, NULL, '2025-08-11 13:00:13', '2025-08-11 20:00:13'),
(285, 14, 'unstar_item', 'Unstarred file:  (ID: 100)', NULL, NULL, '2025-08-11 13:00:14', '2025-08-11 20:00:14'),
(286, 14, 'star_item', 'Starred folder: archive_20250806_133635 (ID: 77)', NULL, NULL, '2025-08-11 13:00:22', '2025-08-11 20:00:22'),
(287, 14, 'star_item', 'Starred file: Photo Profile(1).png (ID: 149)', NULL, NULL, '2025-08-11 13:00:26', '2025-08-11 20:00:26'),
(288, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-11 13:05:01', '2025-08-11 20:05:01'),
(289, 14, 'upload_file', 'Uploaded file \"evil and insane.mp4\"', NULL, NULL, '2025-08-11 13:13:48', '2025-08-11 20:13:48'),
(290, 14, 'archive', 'Archived Photo Profile(1).png, Screenshot_20250809_125635_Drive.jpg to archive_20250811_131510.zip (Format: zip)', NULL, NULL, '2025-08-11 13:15:10', '2025-08-11 20:15:10'),
(291, 14, 'rename_file', 'Renamed file \"archive_20250811_131510.zip\" to \"arsip baru.zip\"', NULL, NULL, '2025-08-11 13:15:44', '2025-08-11 20:15:44'),
(292, 14, 'extract_file', 'Extracted ZIP file: arsip baru.zip. Result: Success', NULL, NULL, '2025-08-11 13:15:48', '2025-08-11 20:15:48'),
(293, 14, 'upload_file', 'Uploaded file \"Pedoman skripsi baru.pdf\"', NULL, NULL, '2025-08-11 13:23:35', '2025-08-11 20:23:35'),
(294, 14, 'upload_file', 'Uploaded file \"Pedoman skripsi baru.pdf\"', NULL, NULL, '2025-08-11 13:24:41', '2025-08-11 20:24:41'),
(295, 14, 'upload_file', 'Uploaded file \"Photo Profile(2).png\"', NULL, NULL, '2025-08-11 13:24:59', '2025-08-11 20:24:59'),
(296, 14, 'upload_file', 'Uploaded file \"LAPORAN_PELAKSANAAN_STUDI_INDEPENDEN BARU (1) (1).pdf\"', NULL, NULL, '2025-08-11 13:26:43', '2025-08-11 20:26:43'),
(297, 14, 'create_folder', 'Created folder \"Folder Kode\"', NULL, NULL, '2025-08-11 13:31:36', '2025-08-11 20:31:36'),
(298, 14, 'upload_file', 'Uploaded file \"register.php\"', NULL, NULL, '2025-08-11 13:31:56', '2025-08-11 20:31:56'),
(299, 14, 'upload_file', 'Uploaded file \"login.php\"', NULL, NULL, '2025-08-11 13:31:56', '2025-08-11 20:31:56'),
(300, 14, 'upload_file', 'Uploaded file \"internal.css\"', NULL, NULL, '2025-08-11 13:31:56', '2025-08-11 20:31:56'),
(301, 14, 'upload_file', 'Uploaded file \"members.php\"', NULL, NULL, '2025-08-11 13:31:57', '2025-08-11 20:31:57'),
(302, 14, 'upload_file', 'Uploaded file \"summary.php\"', NULL, NULL, '2025-08-11 13:31:57', '2025-08-11 20:31:57'),
(303, 14, 'upload_file', 'Uploaded file \"summary (4).php\"', NULL, NULL, '2025-08-11 13:31:57', '2025-08-11 20:31:57'),
(304, 14, 'upload_file', 'Uploaded file \"members (1).php\"', NULL, NULL, '2025-08-11 13:31:57', '2025-08-11 20:31:57'),
(305, 14, 'upload_file', 'Uploaded file \"profile (1).php\"', NULL, NULL, '2025-08-11 13:31:58', '2025-08-11 20:31:58'),
(306, 14, 'upload_file', 'Uploaded file \"summary (1).php\"', NULL, NULL, '2025-08-11 13:31:59', '2025-08-11 20:31:59'),
(307, 14, 'upload_file', 'Uploaded file \"index.php\"', NULL, NULL, '2025-08-11 13:32:00', '2025-08-11 20:32:00'),
(308, 14, 'upload_file', 'Uploaded file \"members (2).php\"', NULL, NULL, '2025-08-11 13:32:00', '2025-08-11 20:32:00'),
(309, 14, 'upload_file', 'Uploaded file \"index (4).php\"', NULL, NULL, '2025-08-11 13:32:00', '2025-08-11 20:32:00'),
(310, 14, 'upload_file', 'Uploaded file \"index (1).php\"', NULL, NULL, '2025-08-11 13:32:01', '2025-08-11 20:32:01'),
(311, 14, 'upload_file', 'Uploaded file \"view.php\"', NULL, NULL, '2025-08-11 13:32:01', '2025-08-11 20:32:01'),
(312, 14, 'upload_file', 'Uploaded file \"profile (2).php\"', NULL, NULL, '2025-08-11 13:32:01', '2025-08-11 20:32:01'),
(313, 14, 'upload_file', 'Uploaded file \"profile.php\"', NULL, NULL, '2025-08-11 13:32:02', '2025-08-11 20:32:02'),
(314, 14, 'upload_file', 'Uploaded file \"app2skmi_skmicloud (1).sql\"', NULL, NULL, '2025-08-11 13:32:45', '2025-08-11 20:32:45'),
(315, 14, 'upload_file', 'Uploaded file \"app2skmi_skmicloud.sql\"', NULL, NULL, '2025-08-11 13:32:45', '2025-08-11 20:32:45'),
(316, 14, 'upload_file', 'Uploaded file \"register (3).php\"', NULL, NULL, '2025-08-11 13:32:45', '2025-08-11 20:32:45'),
(317, 14, 'upload_file', 'Uploaded file \"toggle_star.php\"', NULL, NULL, '2025-08-11 13:32:46', '2025-08-11 20:32:46'),
(318, 14, 'upload_file', 'Uploaded file \"login (1).php\"', NULL, NULL, '2025-08-11 13:32:46', '2025-08-11 20:32:46'),
(319, 14, 'upload_file', 'Uploaded file \"priority_files (1).php\"', NULL, NULL, '2025-08-11 13:32:47', '2025-08-11 20:32:47'),
(320, 14, 'upload_file', 'Uploaded file \"register (2).php\"', NULL, NULL, '2025-08-11 13:32:47', '2025-08-11 20:32:47'),
(321, 14, 'upload_file', 'Uploaded file \"priority_files.php\"', NULL, NULL, '2025-08-11 13:32:47', '2025-08-11 20:32:47'),
(322, 14, 'upload_file', 'Uploaded file \"members (3).php\"', NULL, NULL, '2025-08-11 13:32:47', '2025-08-11 20:32:47'),
(323, 14, 'upload_file', 'Uploaded file \"register (1).php\"', NULL, NULL, '2025-08-11 13:32:47', '2025-08-11 20:32:47'),
(324, 14, 'upload_file', 'Uploaded file \"functions.php\"', NULL, NULL, '2025-08-11 13:32:48', '2025-08-11 20:32:48'),
(325, 14, 'upload_file', 'Uploaded file \"WhatsApp Image 2025-08-09 at 10.17.27_deb7291f.jpg\"', NULL, NULL, '2025-08-11 13:32:49', '2025-08-11 20:32:49'),
(326, 14, 'upload_file', 'Uploaded file \"WhatsApp Image 2025-08-09 at 10.18.43_d7e213ca.jpg\"', NULL, NULL, '2025-08-11 13:32:49', '2025-08-11 20:32:49'),
(327, 14, 'upload_file', 'Uploaded file \"profile (3).php\"', NULL, NULL, '2025-08-11 13:32:49', '2025-08-11 20:32:49'),
(328, 14, 'upload_file', 'Uploaded file \"index (3).php\"', NULL, NULL, '2025-08-11 13:32:49', '2025-08-11 20:32:49'),
(329, 14, 'upload_file', 'Uploaded file \"summary (2).php\"', NULL, NULL, '2025-08-11 13:32:49', '2025-08-11 20:32:49'),
(330, 14, 'upload_file', 'Uploaded file \"index (2).php\"', NULL, NULL, '2025-08-11 13:32:50', '2025-08-11 20:32:50'),
(331, 14, 'upload_file', 'Uploaded file \"Photo Profile (1).png\"', NULL, NULL, '2025-08-11 13:32:50', '2025-08-11 20:32:50'),
(332, 14, 'upload_file', 'Uploaded file \"profile (4).php\"', NULL, NULL, '2025-08-11 13:32:50', '2025-08-11 20:32:50'),
(333, 14, 'upload_file', 'Uploaded file \"index (5).php\"', NULL, NULL, '2025-08-11 13:32:51', '2025-08-11 20:32:51'),
(334, 14, 'upload_file', 'Uploaded file \"WhatsApp Image 2025-08-09 at 12.56.20_1eb0654e.jpg\"', NULL, NULL, '2025-08-11 13:32:51', '2025-08-11 20:32:51'),
(335, 14, 'upload_file', 'Uploaded file \"Pedoman skripsi baru.pdf\"', NULL, NULL, '2025-08-11 13:33:22', '2025-08-11 20:33:22'),
(336, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-11 14:21:59', '2025-08-11 21:21:59'),
(337, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-11 14:22:15', '2025-08-11 21:22:15'),
(338, 16, 'add_email', 'Added additional email: sukmo.skmi@gmail.com', NULL, NULL, '2025-08-12 02:36:32', '2025-08-12 09:36:32'),
(339, 16, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-12 02:38:46', '2025-08-12 09:38:46'),
(340, 16, 'upload_file', 'Uploaded file \"login (2).php\"', NULL, NULL, '2025-08-12 02:44:37', '2025-08-12 09:44:37'),
(341, 16, 'upload_file', 'Uploaded file \"register (4).php\"', NULL, NULL, '2025-08-12 02:44:37', '2025-08-12 09:44:37'),
(342, 16, 'star_item', 'Starred folder: Folder Kode (ID: 81)', NULL, NULL, '2025-08-12 03:02:58', '2025-08-12 10:02:58'),
(343, 16, 'upload_file', 'Uploaded file \"get_member_details.php\"', NULL, NULL, '2025-08-12 03:10:01', '2025-08-12 10:10:01'),
(344, 16, 'upload_file', 'Uploaded file \"login (2).php\"', NULL, NULL, '2025-08-12 03:10:01', '2025-08-12 10:10:01'),
(345, 16, 'upload_file', 'Uploaded file \"register (4).php\"', NULL, NULL, '2025-08-12 03:10:02', '2025-08-12 10:10:02'),
(346, 16, 'upload_file', 'Uploaded file \"members (4).php\"', NULL, NULL, '2025-08-12 03:10:03', '2025-08-12 10:10:03'),
(347, 16, 'unstar_item', 'Unstarred folder: Folder Kode (ID: 81)', NULL, NULL, '2025-08-12 03:13:04', '2025-08-12 10:13:04'),
(348, 16, 'star_item', 'Starred folder: Folder Kode (ID: 81)', NULL, NULL, '2025-08-12 04:19:41', '2025-08-12 11:19:41'),
(349, 14, 'star_item', 'Starred file: login (2).php (ID: 203)', NULL, NULL, '2025-08-12 06:17:29', '2025-08-12 13:17:29'),
(350, 14, 'upload_file', 'Uploaded file \"history.log\"', NULL, NULL, '2025-08-12 06:46:08', '2025-08-12 13:46:08'),
(351, 14, 'upload_file', 'Uploaded file \"protection.php\"', NULL, NULL, '2025-08-12 06:46:13', '2025-08-12 13:46:13'),
(352, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-12 06:53:12', '2025-08-12 13:53:12'),
(353, 14, 'delete_profile_picture', 'Deleted profile picture.', NULL, NULL, '2025-08-12 06:53:37', '2025-08-12 13:53:37'),
(354, 14, 'star_item', 'Starred file: history.log (ID: 210)', NULL, NULL, '2025-08-12 07:09:22', '2025-08-12 14:09:22'),
(355, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-12 07:32:22', '2025-08-12 14:32:22'),
(356, 14, 'create_folder', 'Created folder \"dafino\"', NULL, NULL, '2025-08-12 07:32:48', '2025-08-12 14:32:48'),
(357, 14, 'upload_file', 'Uploaded file \"protection.php\"', NULL, NULL, '2025-08-12 07:32:55', '2025-08-12 14:32:55'),
(358, 14, 'create_folder', 'Created folder \"Pak Sukmo\"', NULL, NULL, '2025-08-12 08:52:37', '2025-08-12 15:52:37'),
(359, 14, 'upload_file', 'Uploaded file \"app2skmi_skmicloud (1).sql\"', NULL, NULL, '2025-08-12 08:52:45', '2025-08-12 15:52:45'),
(360, 14, 'archive', 'Archived Pak Sukmo to archive_20250812_085251.zip (Format: zip)', NULL, NULL, '2025-08-12 08:52:51', '2025-08-12 15:52:51'),
(361, 14, 'rename_file', 'Renamed file \"archive_20250812_085251.zip\" to \"file abru.zip\"', NULL, NULL, '2025-08-12 08:57:35', '2025-08-12 15:57:35'),
(362, 14, 'extract_file', 'Extracted ZIP file: file abru.zip. Result: Success', NULL, NULL, '2025-08-12 08:57:45', '2025-08-12 15:57:45'),
(363, 14, 'archive', 'Archived history.log, protection.php to archive_20250812_093449.zip (Format: zip)', NULL, NULL, '2025-08-12 09:34:49', '2025-08-12 16:34:49'),
(364, 14, 'rename_file', 'Renamed file \"archive_20250812_093449.zip\" to \"file kdoe.zip\"', NULL, NULL, '2025-08-12 09:35:04', '2025-08-12 16:35:04'),
(365, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-13 08:34:33', '2025-08-13 15:34:33'),
(366, 14, 'upload_file', 'Uploaded file \"IDM_6.4x_Crack_v20.2.zip\"', NULL, NULL, '2025-08-13 09:18:48', '2025-08-13 16:18:48'),
(367, 14, 'upload_file', 'Uploaded file \"app2skmi_skmicloud (1).sql\"', NULL, NULL, '2025-08-13 09:19:11', '2025-08-13 16:19:11'),
(368, 14, 'rename_file', 'Renamed file \"app2skmi_skmicloud (1).sql\" to \"app2skmi_skmicloud.sql\"', NULL, NULL, '2025-08-13 09:19:21', '2025-08-13 16:19:21'),
(369, 14, 'archive', 'Archived app2skmi_skmicloud.sql, history.log to archive_20250813_091935.zip (Format: zip)', NULL, NULL, '2025-08-13 09:19:35', '2025-08-13 16:19:35'),
(370, 14, 'extract_file', 'Extracted ZIP file: archive_20250813_091935.zip. Result: Success', NULL, NULL, '2025-08-13 09:19:42', '2025-08-13 16:19:42'),
(371, 14, 'rename_file', 'Renamed file \"archive_20250813_091935.zip\" to \"arsip baru 123.zip\"', NULL, NULL, '2025-08-13 09:20:00', '2025-08-13 16:20:00'),
(372, 14, 'extract_file', 'Extracted ZIP file: arsip baru 123.zip. Result: Success', NULL, NULL, '2025-08-13 09:20:09', '2025-08-13 16:20:09'),
(373, 14, 'archive', 'Archived archive_20250813_091935, arsip baru 123 to archive_20250813_092040.zip (Format: zip)', NULL, NULL, '2025-08-13 09:20:40', '2025-08-13 16:20:40'),
(374, 14, 'star_item', 'Starred folder: arsip baru 123 (ID: 87)', NULL, NULL, '2025-08-13 09:21:49', '2025-08-13 16:21:49'),
(375, 14, 'star_item', 'Starred file: file abru.zip (ID: 214)', NULL, NULL, '2025-08-13 09:21:56', '2025-08-13 16:21:56'),
(376, 14, 'unstar_item', 'Unstarred file: history.log (ID: 210)', NULL, NULL, '2025-08-13 09:22:07', '2025-08-13 16:22:07'),
(377, 14, 'unstar_item', 'Unstarred folder: arsip baru 123 (ID: 87)', NULL, NULL, '2025-08-13 09:22:09', '2025-08-13 16:22:09'),
(378, 14, 'unstar_item', 'Unstarred file: file abru.zip (ID: 214)', NULL, NULL, '2025-08-13 09:22:11', '2025-08-13 16:22:11'),
(379, 14, 'update_profile', 'Updated profile information.', NULL, NULL, '2025-08-13 09:24:32', '2025-08-13 16:24:32'),
(380, 14, 'change_password', 'Changed account password.', NULL, NULL, '2025-08-13 09:24:57', '2025-08-13 16:24:57'),
(381, 14, 'delete_email', 'Deleted additional email with ID: 11', NULL, NULL, '2025-08-13 09:25:53', '2025-08-13 16:25:53'),
(382, 14, 'add_email', 'Added additional email: mochamad.22160@mhs.unesa.ac.id', NULL, NULL, '2025-08-13 09:26:02', '2025-08-13 16:26:02');

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
(210, 'history.log', 'uploads/history.log', 32652, 'log', NULL, '2025-08-12 13:46:08', 14, '2025-08-12 06:46:08', 0),
(211, 'protection.php', 'uploads/protection.php', 29416, 'php', NULL, '2025-08-12 13:46:13', 14, '2025-08-12 06:46:13', 0),
(212, 'protection.php', 'uploads/dafino/protection.php', 29416, 'php', 82, '2025-08-12 14:32:55', 14, '2025-08-12 07:32:55', 0),
(214, 'file abru.zip', 'uploads/file abru.zip', 4693, 'zip', NULL, '2025-08-12 15:52:51', NULL, '2025-08-12 08:52:51', 0),
(217, 'IDM_6.4x_Crack_v20.2.zip', 'uploads/IDM_6.4x_Crack_v20.2.zip', 62556, 'zip', NULL, '2025-08-13 16:18:48', 14, '2025-08-13 09:18:48', 0),
(218, 'app2skmi_skmicloud.sql', 'uploads/app2skmi_skmicloud.sql', 20801, 'sql', NULL, '2025-08-13 16:19:11', 14, '2025-08-13 09:19:11', 0),
(219, 'arsip baru 123.zip', 'uploads/arsip baru 123.zip', 6671, 'zip', NULL, '2025-08-13 16:19:35', NULL, '2025-08-13 09:19:35', 0),
(220, 'history.log', 'uploads/archive_20250813_091935/history.log', 32652, 'log', 86, '2025-08-13 16:19:42', NULL, '2025-08-13 09:19:42', 0),
(221, 'app2skmi_skmicloud.sql', 'uploads/archive_20250813_091935/app2skmi_skmicloud.sql', 20801, 'sql', 86, '2025-08-13 16:19:42', NULL, '2025-08-13 09:19:42', 0),
(222, 'history.log', 'uploads/arsip baru 123/history.log', 32652, 'log', 87, '2025-08-13 16:20:09', NULL, '2025-08-13 09:20:09', 0),
(223, 'app2skmi_skmicloud.sql', 'uploads/arsip baru 123/app2skmi_skmicloud.sql', 20801, 'sql', 87, '2025-08-13 16:20:09', NULL, '2025-08-13 09:20:09', 0),
(224, 'archive_20250813_092040.zip', 'uploads/archive_20250813_092040.zip', 13476, 'zip', NULL, '2025-08-13 16:20:40', NULL, '2025-08-13 09:20:40', 0);

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
(82, 'dafino', NULL, '2025-08-12 14:32:48', '2025-08-12 07:32:48', NULL, 0),
(86, 'archive_20250813_091935', NULL, '2025-08-13 16:19:42', '2025-08-13 09:19:42', NULL, 0),
(87, 'arsip baru 123', NULL, '2025-08-13 16:20:09', '2025-08-13 09:20:09', NULL, 0);

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
(60, 14, '2025-08-12 06:04:23', '36.90.48.76');

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
(21, 'uploads/Mouse Crusor.png', 'rDu14J', '2025-08-06 03:21:09'),
(22, 'uploads/Pedoman skripsi baru.pdf', 'gBKwfT', '2025-08-11 14:24:17');

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
(14, 'dafinoskmi', 'dafinoharyonida@gmail.com', '$2y$10$11.pkTQ8yWNRyKOXW4HUvehnsr6mrA45lZ6UqlySONrgmMkecH2uS', '2025-08-09 04:08:10', 'uploads/profile_pictures/profile_689c59d00fc71.jpg', 'User', '36.90.48.76', '2025-08-12 06:04:23', 'Mochammad Dafino Haryonida', '082332988490', '2003-03-15', 'Active', '2025-08-13 09:24:57', NULL, 1, NULL),
(15, 'dafino', 'dafinomochammad@gmail.com', '$2y$10$Lt8jumqWVe8V2pTGKozIP.NEhEb7qPReVWTq.D04G9MlfvG9aFmRW', '2025-08-11 06:37:59', NULL, 'User', NULL, NULL, NULL, NULL, NULL, 'Active', '2025-08-11 06:37:59', NULL, 0, NULL),
(16, 'sukmoskmi', 'sukmo@skmi.co.id', '$2y$10$S9TDeAhmU264HIoEwkam5O.tFOnygoIKle0cGmSvVaymI3mXMRcOu', '2025-08-12 02:34:59', 'uploads/profile_pictures/profile_689aa935b77c3.png', 'User', NULL, NULL, 'Sukmo Hadi Winoto', '081210910303 ', '2025-08-12', 'Active', '2025-08-12 02:38:46', NULL, 1, NULL);

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
(12, 16, 'sukmo.skmi@gmail.com', 0, '2025-08-12 02:36:32'),
(13, 14, 'mochamad.22160@mhs.unesa.ac.id', 0, '2025-08-13 09:26:02');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=383;

--
-- AUTO_INCREMENT untuk tabel `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=225;

--
-- AUTO_INCREMENT untuk tabel `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT untuk tabel `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT untuk tabel `shared_links`
--
ALTER TABLE `shared_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `user_emails`
--
ALTER TABLE `user_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
