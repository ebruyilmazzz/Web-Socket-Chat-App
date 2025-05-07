-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 07 May 2025, 11:08:04
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `kiosk`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `counter`
--

CREATE TABLE `counter` (
  `id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('waiting','called','finished') NOT NULL DEFAULT 'waiting',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `departman_id` int(11) DEFAULT NULL,
  `personel_id` int(11) DEFAULT NULL,
  `desk_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `department`
--

CREATE TABLE `department` (
  `id` int(11) NOT NULL,
  `departman_adi` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `department`
--

INSERT INTO `department` (`id`, `departman_adi`) VALUES
(1, 'hasar'),
(2, 'mekanik'),
(3, 'onarim');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `desk`
--

CREATE TABLE `desk` (
  `id` int(11) NOT NULL,
  `desk_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `desk`
--

INSERT INTO `desk` (`id`, `desk_name`) VALUES
(1, 'H1'),
(2, 'H2'),
(3, 'H3'),
(4, 'M1'),
(5, 'O1'),
(6, 'O2');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `personnel`
--

CREATE TABLE `personnel` (
  `id` int(11) NOT NULL,
  `personel_adi` varchar(255) NOT NULL,
  `section_id` int(11) NOT NULL,
  `desk_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `personnel`
--

INSERT INTO `personnel` (`id`, `personel_adi`, `section_id`, `desk_id`) VALUES
(6, 'Hüseyin Yılmaz', 1, 1),
(7, 'Mehmet Kara', 1, 2),
(8, 'Zeynep Demir', 1, 3),
(9, 'Ali Can', 2, 4),
(10, 'Elif Şahin', 3, 5),
(11, 'Ebru Yılmaz', 3, 6);

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `counter`
--
ALTER TABLE `counter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `id_2` (`id`),
  ADD KEY `departman_id` (`departman_id`,`personel_id`),
  ADD KEY `personel_id` (`personel_id`),
  ADD KEY `fk_desk_id` (`desk_id`);

--
-- Tablo için indeksler `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `desk`
--
ALTER TABLE `desk`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `personnel`
--
ALTER TABLE `personnel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `fk_personnel_desk_id` (`desk_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `counter`
--
ALTER TABLE `counter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=389;

--
-- Tablo için AUTO_INCREMENT değeri `department`
--
ALTER TABLE `department`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `desk`
--
ALTER TABLE `desk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `personnel`
--
ALTER TABLE `personnel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `counter`
--
ALTER TABLE `counter`
  ADD CONSTRAINT `counter_ibfk_1` FOREIGN KEY (`personel_id`) REFERENCES `personnel` (`id`),
  ADD CONSTRAINT `counter_ibfk_2` FOREIGN KEY (`departman_id`) REFERENCES `department` (`id`),
  ADD CONSTRAINT `fk_desk_id` FOREIGN KEY (`desk_id`) REFERENCES `desk` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `personnel`
--
ALTER TABLE `personnel`
  ADD CONSTRAINT `fk_personnel_desk_id` FOREIGN KEY (`desk_id`) REFERENCES `desk` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `personnel_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `department` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
