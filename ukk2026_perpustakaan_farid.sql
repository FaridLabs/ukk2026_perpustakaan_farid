-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ukk2026_perpustakaan_farid
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES (1,'admin','$2y$10$svzgTh8CZFB/.iiFgEXp1udRiydyZMVue8.UeWfDuPLABTMPmXdgy','Administrator Perpustakaan','admin_598ea9f9a5d8.jpg','2026-09-08 06:17:29');
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `anggota`
--

DROP TABLE IF EXISTS `anggota`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anggota` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nis` varchar(20) NOT NULL,
  `kode_rfid` varchar(50) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `kelas` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nis` (`nis`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `kode_rfid` (`kode_rfid`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anggota`
--

LOCK TABLES `anggota` WRITE;
/*!40000 ALTER TABLE `anggota` DISABLE KEYS */;
INSERT INTO `anggota` VALUES (16,'24250223','1705319278','BAGAS NATASAPUTRA','XII RPL 2',NULL,'085',NULL,NULL,'bagas','$2y$10$Oddy9NtoSApmKhOn.O.iv.DFAbT.kByXzDnAJCP3R0D/pCSHG1KpG',NULL,'2026-09-10 07:49:16'),(17,'5252536','1709911214','Yaya Dadan Jaya','XII RPL 2',NULL,'085',NULL,NULL,'yaya','$2y$10$y7AmlbS8f5urqQjB9olbl.HRXwM0eyHbV7mnNWmsMUA0h.bGglkpe',NULL,'2026-09-10 07:49:33'),(18,'123242','1705876302','farel','kuliah',NULL,'213314',NULL,NULL,'buku pinjam','$2y$10$bQMr39Kda.J0El4ucbTgaO.RbC7aONJ1jq4qODPa256pWfqg3Uy.q',NULL,'2026-10-08 04:34:12'),(21,'2425123','0493428243','Muhammad Farid Malik','XII RPL 2',NULL,'085798231433',NULL,NULL,'fariddd','$2y$10$yYiJ5mFk8rqm4iZVkYbQduGIlyDZQlWn8ypSJpYT58XEUTRlYWFfq',NULL,'2026-10-19 03:46:13'),(22,'2425888',NULL,'Budi Santoso','XII RPL 1',NULL,'08123456789',NULL,NULL,'budisantoso','$2y$10$OvFTpZHl2GgIDHVd9XTEm.00TViHhAbNqmj1azCbSPvLvWO9dBpTm',NULL,'2026-10-19 03:57:27');
/*!40000 ALTER TABLE `anggota` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `buku`
--

DROP TABLE IF EXISTS `buku`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buku` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_buku` varchar(20) NOT NULL,
  `judul` varchar(150) NOT NULL,
  `pengarang` varchar(100) DEFAULT NULL,
  `penerbit` varchar(100) DEFAULT NULL,
  `tahun_terbit` year(4) DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cover` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_buku` (`kode_buku`),
  KEY `kategori_id` (`kategori_id`),
  CONSTRAINT `buku_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buku`
--

LOCK TABLES `buku` WRITE;
/*!40000 ALTER TABLE `buku` DISABLE KEYS */;
INSERT INTO `buku` VALUES (13,'BK-2026091009395020','3756','Pidi Baiq','google',2020,1,'2026-09-10 07:39:50',NULL),(14,'BK-2026092103453597','Rekayasa Perangkat Lunak','Abijar','goggle',2020,3,'2026-09-21 01:45:35',NULL),(15,'BK-2026100806312225','rekayasa perangkat lunak','tok dalang','bagas',0000,1,'2026-10-08 04:31:22',NULL),(16,'BK-2026100806330530','3786mdpl','afgan','farel',2026,1,'2026-10-08 04:33:05',NULL),(17,'BK-2026100806373110','3756','afgan','farel',2030,4,'2026-10-08 04:37:31','cover_d18a73a134538c9c.jpg');
/*!40000 ALTER TABLE `buku` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `buku_item`
--

DROP TABLE IF EXISTS `buku_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buku_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_item` varchar(30) NOT NULL,
  `buku_id` int(11) NOT NULL,
  `status` enum('tersedia','dipinjam','rusak','hilang') NOT NULL DEFAULT 'tersedia',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_item` (`kode_item`),
  KEY `buku_id` (`buku_id`),
  KEY `idx_kode_item` (`kode_item`),
  CONSTRAINT `buku_item_ibfk_1` FOREIGN KEY (`buku_id`) REFERENCES `buku` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buku_item`
--

LOCK TABLES `buku_item` WRITE;
/*!40000 ALTER TABLE `buku_item` DISABLE KEYS */;
INSERT INTO `buku_item` VALUES (32,'562350038',13,'tersedia','2026-09-10 07:40:18'),(33,'BK-13-002',13,'tersedia','2026-09-10 07:40:18'),(34,'2022.04461.BOS',13,'tersedia','2026-09-10 07:40:19'),(35,'BK-13-004',13,'tersedia','2026-09-10 07:44:25'),(36,'BK-13-005',13,'tersedia','2026-09-10 07:52:53'),(37,'9786020385938',13,'tersedia','2026-09-10 07:53:34'),(41,'BK-14-001',14,'tersedia','2026-09-21 01:54:32'),(42,'BK-14-002',14,'tersedia','2026-09-21 01:55:15'),(43,'BK-14-003',14,'tersedia','2026-09-21 02:18:25'),(44,'BK-14-004',14,'tersedia','2026-09-21 02:18:36'),(45,'BK-14-005',14,'tersedia','2026-09-21 02:18:47'),(46,'BK-14-006',14,'tersedia','2026-10-08 04:18:42'),(47,'BK-14-007',14,'tersedia','2026-10-08 04:18:42'),(48,'BK-14-008',14,'tersedia','2026-10-08 04:18:42'),(49,'BK-14-009',14,'tersedia','2026-10-08 04:18:42'),(50,'BK-14-010',14,'tersedia','2026-10-08 04:18:42'),(51,'BK-15-001',15,'tersedia','2026-10-08 04:31:22'),(52,'BK-15-002',15,'tersedia','2026-10-08 04:31:22'),(53,'BK-15-003',15,'tersedia','2026-10-08 04:31:22'),(54,'BK-15-004',15,'tersedia','2026-10-08 04:31:22'),(55,'BK-15-005',15,'tersedia','2026-10-08 04:31:22'),(56,'BK-15-006',15,'tersedia','2026-10-08 04:31:22'),(57,'BK-15-007',15,'tersedia','2026-10-08 04:31:22'),(58,'BK-15-008',15,'tersedia','2026-10-08 04:31:22'),(59,'BK-15-009',15,'tersedia','2026-10-08 04:31:22'),(60,'BK-15-010',15,'tersedia','2026-10-08 04:31:22'),(61,'BK-16-001',16,'tersedia','2026-10-08 04:33:05'),(62,'BK-17-001',17,'tersedia','2026-10-08 04:37:31');
/*!40000 ALTER TABLE `buku_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kategori`
--

DROP TABLE IF EXISTS `kategori`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategori`
--

LOCK TABLES `kategori` WRITE;
/*!40000 ALTER TABLE `kategori` DISABLE KEYS */;
INSERT INTO `kategori` VALUES (1,'Fiksi'),(2,'Non-Fiksi'),(3,'Pelajaran'),(4,'Referensi'),(5,'Komik');
/*!40000 ALTER TABLE `kategori` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_aktivitas`
--

DROP TABLE IF EXISTS `log_aktivitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` enum('admin','siswa','sistem') NOT NULL DEFAULT 'sistem',
  `user_id` int(11) DEFAULT NULL,
  `nama_pelaku` varchar(100) NOT NULL,
  `aksi` varchar(50) NOT NULL,
  `modul` varchar(50) NOT NULL,
  `keterangan` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_log_role` (`role`),
  KEY `idx_log_modul` (`modul`),
  KEY `idx_log_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_aktivitas`
--

LOCK TABLES `log_aktivitas` WRITE;
/*!40000 ALTER TABLE `log_aktivitas` DISABLE KEYS */;
INSERT INTO `log_aktivitas` VALUES (1,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-12 00:49:54'),(2,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-12 08:46:04'),(3,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-15 04:56:09'),(4,'admin',1,'Administrator Perpustakaan','Logout','Autentikasi','Admin keluar (logout) dari sistem.','::1','2026-11-15 05:28:44'),(5,'siswa',21,'Muhammad Farid Malik','Login','Autentikasi','Siswa Muhammad Farid Malik (NIS: 2425123) login ke sistem.','::1','2026-11-15 05:28:54'),(6,'siswa',21,'Muhammad Farid Malik','Logout','Autentikasi','Siswa keluar (logout) dari sistem.','::1','2026-11-15 05:38:31'),(7,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-15 05:38:38'),(8,'admin',1,'Administrator Perpustakaan','update_profil','profil','Administrator memperbarui data profil','::1','2026-11-15 05:38:58'),(9,'admin',1,'Administrator Perpustakaan','update_profil','profil','Administrator memperbarui data profil','::1','2026-11-15 07:38:20'),(10,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-16 04:01:52'),(11,'admin',1,'Administrator Perpustakaan','update_pengaturan','pengaturan','Memperbarui pengaturan: App \'Pinjam.In\', Sekolah \'SMKN 1 Panyingkiran\', kuota 3 buku, durasi 7 hari, denda Rp 1.000/hari.','::1','2026-11-16 04:02:13'),(12,'admin',1,'Administrator Perpustakaan','update_pengaturan','pengaturan','Memperbarui pengaturan: App \'Pinjam.In\', Sekolah \'SMKN 1 Panyingkiran\', kuota 3 buku, durasi 7 hari, denda Rp 1.000/hari.','::1','2026-11-16 04:02:23'),(13,'admin',1,'Administrator Perpustakaan','update_pengaturan','pengaturan','Memperbarui pengaturan: App \'Pinjam.In\', Sekolah \'SMKN 1 Panyingkiran\', kuota 3 buku, durasi 7 hari, denda Rp 1.000/hari.','::1','2026-11-16 04:02:30'),(14,'admin',1,'Administrator Perpustakaan','Logout','Autentikasi','Admin keluar (logout) dari sistem.','::1','2026-11-16 04:02:41'),(15,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-16 06:35:14'),(16,'sistem',NULL,'admin','Login Gagal','Autentikasi','Percobaan login gagal menggunakan username \'admin\'.','::1','2026-11-16 07:19:55'),(17,'sistem',NULL,'admin','Login Gagal','Autentikasi','Percobaan login gagal menggunakan username \'admin\'.','::1','2026-11-16 07:20:28'),(18,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-16 07:20:37'),(19,'sistem',NULL,'admin','Login Gagal','Autentikasi','Percobaan login gagal menggunakan username \'admin\'.','::1','2026-11-16 10:04:03'),(20,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-16 10:04:10'),(21,'admin',1,'Administrator Perpustakaan','Logout','Autentikasi','Admin keluar (logout) dari sistem.','::1','2026-11-16 10:05:25'),(22,'sistem',NULL,'wrong','Login Gagal','Autentikasi','Percobaan login gagal menggunakan username \'wrong\'.','127.0.0.1','2026-11-16 10:29:28'),(23,'admin',1,'Admin','update_pengaturan','pengaturan','Memperbarui pengaturan: App \'Pinjam.In\', Sekolah \'SMKN 1 Panyingkiran\', kuota 3 buku, durasi 7 hari, denda Rp 1.000/hari.','127.0.0.1','2026-11-16 10:29:28'),(24,'sistem',NULL,'wrong','Login Gagal','Autentikasi','Percobaan login gagal menggunakan username \'wrong\'.','127.0.0.1','2026-11-16 10:34:57'),(25,'admin',1,'Admin','update_pengaturan','pengaturan','Memperbarui pengaturan: App \'Pinjam.In\', Sekolah \'SMKN 1 Panyingkiran\', kuota 3 buku, durasi 7 hari, denda Rp 1.000/hari.','127.0.0.1','2026-11-16 10:34:58'),(26,'sistem',NULL,'wrong','Login Gagal','Autentikasi','Percobaan login gagal menggunakan username \'wrong\'.','127.0.0.1','2026-11-16 10:42:03'),(27,'admin',1,'Admin','update_pengaturan','pengaturan','Memperbarui pengaturan: App \'Pinjam.In\', Sekolah \'SMKN 1 Panyingkiran\', kuota 3 buku, durasi 7 hari, denda Rp 1.000/hari.','127.0.0.1','2026-11-16 10:42:04'),(28,'sistem',NULL,'admin','Login Gagal','Autentikasi','Percobaan login gagal menggunakan username \'admin\'.','::1','2026-11-17 09:27:03'),(29,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-17 09:27:44'),(30,'admin',1,'Administrator Perpustakaan','Login','Autentikasi','Admin Administrator Perpustakaan (@admin) login ke sistem.','::1','2026-11-17 09:28:20'),(31,'siswa',16,'BAGAS NATASAPUTRA','Login','Autentikasi','Siswa BAGAS NATASAPUTRA (NIS: 24250223) login ke sistem.','::1','2026-11-17 09:28:39');
/*!40000 ALTER TABLE `log_aktivitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengaturan`
--

DROP TABLE IF EXISTS `pengaturan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengaturan` (
  `kunci` varchar(50) NOT NULL,
  `nilai` text NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengaturan`
--

LOCK TABLES `pengaturan` WRITE;
/*!40000 ALTER TABLE `pengaturan` DISABLE KEYS */;
INSERT INTO `pengaturan` VALUES ('denda_per_hari','1000','Tarif denda keterlambatan pengembalian buku per hari (Rupiah)'),('lama_pinjam_hari','7','Batas durasi peminjaman buku dalam satuan hari'),('maks_pinjam_buku','3','Batas maksimal buku yang dapat dipinjam siswa secara bersamaan'),('nama_aplikasi','Pinjam.In','Nama utama brand/aplikasi perpustakaan'),('nama_perpustakaan','Perpustakaan Digital SMKN 1 Panyingkiran','Nama resmi perpustakaan sekolah'),('nama_sekolah','SMKN 1 Panyingkiran','Nama instansi / sekolah pemilik perpustakaan'),('slogan_aplikasi','Perpustakaan Digital','Slogan atau sub-judul brand perpustakaan');
/*!40000 ALTER TABLE `pengaturan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi`
--

DROP TABLE IF EXISTS `transaksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_transaksi` varchar(30) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `buku_item_id` int(11) NOT NULL,
  `tgl_pinjam` date NOT NULL,
  `tgl_kembali_rencana` date NOT NULL,
  `tgl_kembali_aktual` date DEFAULT NULL,
  `denda` int(11) NOT NULL DEFAULT 0,
  `status_denda` enum('lunas','belum_lunas') NOT NULL DEFAULT 'lunas',
  `status` enum('dipinjam','dikembalikan','terlambat') NOT NULL DEFAULT 'dipinjam',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  KEY `anggota_id` (`anggota_id`),
  KEY `buku_item_id` (`buku_item_id`),
  CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`buku_item_id`) REFERENCES `buku_item` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi`
--

LOCK TABLES `transaksi` WRITE;
/*!40000 ALTER TABLE `transaksi` DISABLE KEYS */;
INSERT INTO `transaksi` VALUES (3,'TRX-20261109100433-21-154',21,62,'2026-11-09','2026-11-16','2026-11-09',0,'lunas','dikembalikan','2026-11-09 03:04:33');
/*!40000 ALTER TABLE `transaksi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'ukk2026_perpustakaan_farid'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-17 17:22:38
