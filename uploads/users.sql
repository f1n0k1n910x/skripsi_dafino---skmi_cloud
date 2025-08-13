-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 31 Jul 2025 pada 09.29
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
  `is_member` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `profile_picture`, `role`, `last_login_ip`, `last_login_time`, `full_name`, `phone_number`, `date_of_birth`, `account_status`, `last_active`, `last_login`, `is_member`) VALUES
(1, 'dafinok1n910x', 'dafinoharyonida@gmail.com', '$2y$10$pKJHXzPjMcLeI1OmeZZFMeSd3NQKEiEGwQHd0/oi3dxm.9UzoY8XS', '2025-07-15 06:06:19', 'uploads/profile_pictures/profile_6889f1213cea9.png', 'User', '36.90.51.223', '2025-07-31 01:46:57', 'dafinok1n910x baru', '782012894', '2025-07-23', 'Active', '2025-07-31 01:46:57', NULL, 0),
(3, 'dafinok1n910z', 'dafinomochammad@gmail.com', '$2y$10$CuEBtApGUX3ipcMJVyuRCOl3npFtMezn8jAaA65PekwbQ8juVcYAK', '2025-07-25 01:40:44', 'uploads/profile_pictures/profile_6882e592cb7d4.png', 'User', '::1', '2025-07-30 02:45:36', 'Mochammad Dafino Haryonida', '082332988490', '2025-07-25', 'Active', '2025-07-30 00:53:31', NULL, 0);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
