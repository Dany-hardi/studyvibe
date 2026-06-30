-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: studyvibe
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `api_keys`
--

DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_hash` varchar(64) NOT NULL,
  `label` varchar(100) NOT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`key_hash`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_keys`
--

LOCK TABLES `api_keys` WRITE;
/*!40000 ALTER TABLE `api_keys` DISABLE KEYS */;
INSERT INTO `api_keys` VALUES (1,'7b1becf169ee13595d3803bb74ff2b25640a4da7db9171264e5e0865b8f5de04','Clé mobile 15/06/2026',1,1,'2026-06-15 02:53:14'),(2,'d873af99ebd33d27b0e20d5c943fab287b677ebf569512d71e26a1dc9da682e9','Clé mobile 16/06/2026',1,1,'2026-06-16 02:31:06'),(3,'d9688042dfa103c90a9101380884b3f98fb63fcfc7a9ed510293d49a1a23412d','Clé mobile 21/06/2026',1,1,'2026-06-21 14:49:14');
/*!40000 ALTER TABLE `api_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_rate_limits`
--

DROP TABLE IF EXISTS `api_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_rate_ip_time` (`ip_address`,`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_rate_limits`
--

LOCK TABLES `api_rate_limits` WRITE;
/*!40000 ALTER TABLE `api_rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,'login_failed','Email: jean@gmail.com','165.210.39.251','2026-06-14 12:08:34'),(2,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-14 12:09:43'),(3,NULL,'login_failed','Email: teacher@studyvibe.edu','129.0.205.121','2026-06-14 12:10:34'),(4,2,'login_success','User #2 (teacher)','129.0.205.121','2026-06-14 12:10:44'),(5,NULL,'login_failed','Email: test@gmail.com','129.0.226.255','2026-06-14 12:10:59'),(6,NULL,'login_failed','Email: test@gmail.com','129.0.226.255','2026-06-14 12:11:05'),(7,NULL,'login_failed','Email: test@gmail.com','129.0.226.255','2026-06-14 12:14:45'),(8,NULL,'login_failed','Email: test@gmail.com','129.0.226.255','2026-06-14 12:14:54'),(9,2,'login_success','User #2 (teacher)','129.0.205.121','2026-06-14 12:14:54'),(10,NULL,'login_failed','Email: corneliasuziemassongo@gmail.com','102.244.222.52','2026-06-14 12:21:46'),(11,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 12:22:11'),(12,NULL,'login_failed','Email: ffffdse','92.222.177.11','2026-06-14 12:29:40'),(13,2,'login_success','User #2 (teacher)','129.0.205.121','2026-06-14 12:31:23'),(14,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 12:34:58'),(15,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 12:35:16'),(16,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 12:39:44'),(17,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 12:48:00'),(18,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 12:50:21'),(19,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 12:51:03'),(20,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 12:52:21'),(21,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 13:05:41'),(22,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 13:08:56'),(23,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 13:25:57'),(24,NULL,'login_failed','Email: promoter@studyvibe.edu','127.0.0.1','2026-06-14 13:26:51'),(25,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-14 13:27:01'),(26,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-14 16:34:28'),(27,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 16:52:25'),(28,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-14 16:52:40'),(29,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 16:54:47'),(30,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 17:05:30'),(31,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 17:09:05'),(32,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 17:14:15'),(33,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-14 17:14:26'),(34,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 17:20:12'),(35,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 17:25:26'),(36,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 17:27:25'),(37,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 17:38:00'),(38,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 17:40:35'),(39,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 17:42:05'),(40,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 17:42:46'),(41,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 17:42:54'),(42,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 17:45:07'),(43,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 17:46:07'),(44,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-14 19:23:29'),(45,20,'login_success','User #20 (student)','127.0.0.1','2026-06-14 19:24:12'),(46,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 19:30:02'),(47,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-14 19:30:11'),(48,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-14 19:31:19'),(49,20,'login_success','User #20 (student)','127.0.0.1','2026-06-15 00:52:06'),(50,20,'login_success','User #20 (student)','127.0.0.1','2026-06-15 01:50:03'),(51,20,'login_success','User #20 (student)','127.0.0.1','2026-06-15 02:51:11'),(52,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-15 02:52:35'),(53,1,'api_key_created','Clé mobile 15/06/2026','127.0.0.1','2026-06-15 02:53:14'),(54,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-15 02:53:38'),(55,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-15 03:02:28'),(56,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-15 03:03:13'),(57,20,'login_success','User #20 (student)','127.0.0.1','2026-06-15 03:03:29'),(58,20,'login_success','User #20 (student)','127.0.0.1','2026-06-15 03:11:38'),(59,21,'signup','New student: adelinetakou04@gmail.com','127.0.0.1','2026-06-15 03:32:53'),(60,21,'comment_posted','Lesson #6','127.0.0.1','2026-06-15 03:38:17'),(61,21,'comment_posted','Lesson #8','127.0.0.1','2026-06-15 03:46:36'),(62,21,'comment_posted','Lesson #10','127.0.0.1','2026-06-15 03:47:11'),(63,21,'certification_failed','Course #1, Score: 57.14%','127.0.0.1','2026-06-15 03:52:11'),(64,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-15 04:05:22'),(65,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-15 04:37:48'),(66,20,'login_success','User #20 (student)','127.0.0.1','2026-06-15 04:38:26'),(67,20,'login_success','User #20 (student)','127.0.0.1','2026-06-15 05:05:49'),(68,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-15 05:20:37'),(69,1,'export_certificates','1 lignes','127.0.0.1','2026-06-15 05:20:59'),(70,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-15 06:39:58'),(71,1,'export_excel','teachers','127.0.0.1','2026-06-15 06:40:07'),(72,1,'export_pdf_students','liste apprenants','127.0.0.1','2026-06-15 06:40:21'),(73,1,'export_pdf_logs','rapport audit','127.0.0.1','2026-06-15 06:40:39'),(74,1,'export_excel','certifications','127.0.0.1','2026-06-15 06:41:23'),(75,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-15 07:19:27'),(76,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-15 07:20:26'),(77,1,'teacher_assigned','Cours #1 « Philosophie des Algorithmes » → Prof. Thomas Messi Nguele','127.0.0.1','2026-06-15 07:20:54'),(78,1,'teacher_assigned','Cours #2 « Projets Machine learning » → Prof. Thomas Messi Nguele','127.0.0.1','2026-06-15 07:20:58'),(79,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-15 09:19:48'),(80,1,'export_excel_all','9 feuilles','127.0.0.1','2026-06-15 09:19:55'),(81,NULL,'password_reset_requested','adelinetakou04@gmail.com','127.0.0.1','2026-06-15 13:31:42'),(82,20,'login_success','User #20 (student)','127.0.0.1','2026-06-15 13:33:22'),(83,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-15 13:35:16'),(84,1,'export_excel','audit_logs','127.0.0.1','2026-06-15 13:36:05'),(85,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-15 13:37:24'),(86,22,'signup','New student: danyhardi06@gmail.com','127.0.0.1','2026-06-15 13:40:02'),(87,NULL,'email_verified','token','127.0.0.1','2026-06-15 13:40:41'),(88,22,'login_success','User #22 (student)','127.0.0.1','2026-06-15 13:40:47'),(89,22,'login_success','User #22 (student)','127.0.0.1','2026-06-15 14:19:17'),(90,22,'login_success','User #22 (student)','127.0.0.1','2026-06-16 01:11:02'),(91,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-16 01:13:41'),(92,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-16 01:13:54'),(93,2,'export_teacher_grades','Cours #1','127.0.0.1','2026-06-16 01:14:10'),(94,22,'login_success','User #22 (student)','127.0.0.1','2026-06-16 01:19:14'),(95,20,'login_success','User #20 (student)','127.0.0.1','2026-06-16 01:44:18'),(96,20,'login_success','User #20 (student)','127.0.0.1','2026-06-16 01:55:58'),(97,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-16 01:56:18'),(98,1,'newsletter_sent','Subject: Nouveaux Cours disponibles, sent: 20, failed: 0','127.0.0.1','2026-06-16 02:07:50'),(99,1,'api_key_created','Clé mobile 16/06/2026','127.0.0.1','2026-06-16 02:31:06'),(100,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-16 02:54:26'),(101,20,'login_success','User #20 (student)','127.0.0.1','2026-06-16 03:29:45'),(102,20,'login_success','User #20 (student)','127.0.0.1','2026-06-16 03:41:18'),(103,22,'login_success','User #22 (student)','127.0.0.1','2026-06-16 03:41:28'),(104,20,'login_success','User #20 (student)','127.0.0.1','2026-06-16 03:50:19'),(105,22,'login_success','User #22 (student)','127.0.0.1','2026-06-16 03:54:52'),(106,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-16 10:36:12'),(107,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-16 10:36:52'),(108,22,'login_success','User #22 (student)','127.0.0.1','2026-06-16 10:37:59'),(109,2,'login_success','User #2 (teacher)','129.0.205.243','2026-06-16 11:14:32'),(110,1,'login_success','User #1 (promoter)','129.0.205.243','2026-06-16 11:15:35'),(111,1,'newsletter_sent','Subject: Test No.2 de la Newletter - StudyVibe, sent: 20, failed: 0','129.0.205.243','2026-06-16 11:20:22'),(112,1,'newsletter_sent','Subject: Test No.2 de la Newletter - StudyVibe, sent: 20, failed: 0','129.0.205.243','2026-06-16 11:21:50'),(113,1,'newsletter_sent','Subject: Test No.2 de la Newletter - StudyVibe, sent: 20, failed: 0','129.0.205.243','2026-06-16 11:23:33'),(114,23,'signup','New student: wilfried.ngankeu@facsciences-uy1.cm','129.0.205.243','2026-06-16 11:27:34'),(115,NULL,'email_verified','token','127.0.0.1','2026-06-16 11:27:58'),(116,NULL,'password_reset_requested','wilfried.ngankeu@facsciences-uy1.cm','127.0.0.1','2026-06-16 11:28:30'),(117,NULL,'password_reset_done','User #23','127.0.0.1','2026-06-16 11:28:57'),(118,NULL,'login_failed','Email: wilfried.ngankeu@facsciences-uy1.cm','127.0.0.1','2026-06-16 11:29:23'),(119,NULL,'login_failed','Email: wilfried.ngankeu@facsciences-uy1.cm','127.0.0.1','2026-06-16 11:29:35'),(120,1,'login_success','User #1 (promoter)','129.0.205.243','2026-06-16 11:33:45'),(121,1,'newsletter_sent','Subject: Team StudyVibe, sent: 21, failed: 0','129.0.205.243','2026-06-16 11:36:22'),(122,1,'login_success','User #1 (promoter)','129.0.205.243','2026-06-16 11:41:28'),(123,1,'teacher_assigned','Cours #2 « Projets Machine learning » → Dr. Tapamo','129.0.205.243','2026-06-16 11:41:42'),(124,1,'teacher_assigned','Cours #2 « Projets Machine learning » → Dr. Tapamo','129.0.205.243','2026-06-16 11:41:48'),(125,1,'teacher_assigned','Cours #2 « Projets Machine learning » → Dr. Tapamo','129.0.205.243','2026-06-16 11:41:55'),(126,24,'login_success','User #24 (teacher)','129.0.205.243','2026-06-16 11:43:08'),(127,24,'questions_imported','lesson: 30 questions, course #2','129.0.205.243','2026-06-16 11:57:25'),(128,20,'login_success','User #20 (student)','129.0.205.243','2026-06-16 11:58:43'),(129,20,'login_success','User #20 (student)','127.0.0.1','2026-06-16 13:22:16'),(130,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-16 13:24:33'),(131,22,'login_success','User #22 (student)','127.0.0.1','2026-06-16 13:24:57'),(132,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-16 13:25:17'),(133,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-16 13:26:39'),(134,24,'export_teacher_grades','Cours #2','127.0.0.1','2026-06-16 13:27:10'),(135,20,'login_success','User #20 (student)','127.0.0.1','2026-06-16 13:27:41'),(136,NULL,'login_failed','Email: student@studyvibe.edu','127.0.0.1','2026-06-16 13:51:58'),(137,NULL,'login_failed','Email: student@studyvibe.edu','127.0.0.1','2026-06-16 13:52:44'),(138,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-16 13:58:48'),(139,24,'questions_imported','course: 60 questions, course #2','127.0.0.1','2026-06-16 14:12:10'),(140,22,'login_success','User #22 (student)','127.0.0.1','2026-06-16 14:15:01'),(141,22,'login_success','User #22 (student)','127.0.0.1','2026-06-17 15:59:43'),(142,NULL,'password_reset_requested','wilfried.uy1@gmail.com','127.0.0.1','2026-06-18 02:25:38'),(143,NULL,'password_reset_requested','wilfried.uy1@gmail.com','127.0.0.1','2026-06-18 02:26:28'),(144,NULL,'password_reset_done','User #20','127.0.0.1','2026-06-18 02:27:08'),(145,20,'login_success','User #20 (student)','127.0.0.1','2026-06-18 02:27:27'),(146,20,'certification_failed','Course #2, Score: 21.67%','127.0.0.1','2026-06-18 02:30:40'),(147,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-18 02:31:53'),(148,24,'export_teacher_grades','Cours #2','127.0.0.1','2026-06-18 02:33:02'),(149,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-18 03:10:04'),(150,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-18 16:13:22'),(151,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-19 20:20:13'),(152,1,'export_excel','certification_attempts','127.0.0.1','2026-06-19 20:21:10'),(153,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-19 20:23:05'),(154,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-20 07:33:10'),(155,NULL,'login_failed','Email: wilfried.uy1@gmail.com','127.0.0.1','2026-06-20 07:34:37'),(156,NULL,'password_reset_requested','wilfried.uy1@gmail.com','127.0.0.1','2026-06-20 07:35:05'),(157,NULL,'password_reset_done','User #20','127.0.0.1','2026-06-20 07:36:18'),(158,20,'login_success','User #20 (student)','127.0.0.1','2026-06-20 07:36:36'),(159,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-21 13:15:59'),(160,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-21 13:43:30'),(161,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 14:14:41'),(162,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 14:14:45'),(163,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 14:14:49'),(164,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 14:29:12'),(165,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-21 14:30:38'),(166,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-21 14:37:19'),(167,NULL,'login_failed','Email: promoter@studyvibe.edu','127.0.0.1','2026-06-21 14:47:56'),(168,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-21 14:48:14'),(169,1,'api_key_created','Clé mobile 21/06/2026','127.0.0.1','2026-06-21 14:49:14'),(170,1,'export_excel','students','127.0.0.1','2026-06-21 14:49:19'),(171,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-21 14:53:01'),(172,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 15:12:29'),(173,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 15:12:36'),(174,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 15:12:41'),(175,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 15:12:48'),(176,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-21 15:13:01'),(177,NULL,'password_reset_requested','danielwilfriedtakou@gmail.com','127.0.0.1','2026-06-21 15:25:39'),(178,NULL,'password_reset_done','User #24','127.0.0.1','2026-06-21 15:27:55'),(179,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-21 16:45:19'),(180,1,'teacher_assigned','Cours #1 « Philosophie des Algorithmes » → Dr. Tapamo','127.0.0.1','2026-06-21 16:46:01'),(181,NULL,'login_failed','Email: danyhardi06@gmail.com','127.0.0.1','2026-06-21 16:48:02'),(182,22,'login_success','User #22 (student)','127.0.0.1','2026-06-21 16:48:17'),(183,22,'comment_posted','Lesson #11','127.0.0.1','2026-06-21 16:57:03'),(184,NULL,'login_failed','Email: danielwilfriedtakou@gmail.com','127.0.0.1','2026-06-21 16:59:10'),(185,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-21 16:59:19'),(186,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-23 14:19:38'),(187,NULL,'login_failed','Email: danyhardi06@gmail.com','127.0.0.1','2026-06-23 14:51:37'),(188,22,'login_success','User #22 (student)','127.0.0.1','2026-06-23 14:51:49'),(189,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-23 14:54:45'),(190,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-23 14:54:57'),(191,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-23 14:55:15'),(192,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-23 15:49:21'),(193,NULL,'login_failed','Email: danyhardi06@gmail.com','127.0.0.1','2026-06-23 16:27:48'),(194,22,'login_success','User #22 (student)','127.0.0.1','2026-06-23 16:27:58'),(195,NULL,'login_failed','Email: student@studyvibe.edu','127.0.0.1','2026-06-23 16:32:48'),(196,22,'login_success','User #22 (student)','127.0.0.1','2026-06-23 16:44:44'),(197,3,'login_success','User #3 (student)','127.0.0.1','2026-06-23 16:49:41'),(198,22,'login_success','User #22 (student)','127.0.0.1','2026-06-23 16:53:35'),(199,22,'login_success','User #22 (student)','127.0.0.1','2026-06-23 17:07:40'),(200,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-23 17:30:40'),(201,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-23 17:30:48'),(202,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-23 17:31:26'),(203,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-23 17:32:09'),(204,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-23 21:56:11'),(205,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-23 21:56:19'),(206,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-23 21:56:37'),(207,22,'login_success','User #22 (student)','127.0.0.1','2026-06-23 21:56:48'),(208,NULL,'login_failed','Email: wilfried.uy1@gmail.com','127.0.0.1','2026-06-23 21:59:49'),(209,20,'login_success','User #20 (student)','127.0.0.1','2026-06-23 22:00:01'),(210,NULL,'signup','New teacher: teacher_test@studyvibe.edu','127.0.0.1','2026-06-23 22:00:13'),(211,20,'login_success','User #20 (student)','127.0.0.1','2026-06-23 23:06:24'),(212,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-23 23:06:55'),(213,20,'login_success','User #20 (student)','127.0.0.1','2026-06-23 23:10:16'),(214,20,'login_success','User #20 (student)','127.0.0.1','2026-06-23 23:28:46'),(215,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-24 00:00:59'),(216,1,'user_deactivated','User #25 (teacher): teacher_test@studyvibe.edu','127.0.0.1','2026-06-24 00:01:40'),(217,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-24 01:24:55'),(218,1,'user_deleted','User deleted: teacher_test@studyvibe.edu (role: teacher)','127.0.0.1','2026-06-24 01:25:25'),(219,1,'user_deactivated','User #22 (student): danyhardi06@gmail.com','127.0.0.1','2026-06-24 01:25:40'),(220,NULL,'login_failed','Email: danyhardi06@gmail.com','127.0.0.1','2026-06-24 01:25:52'),(221,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-24 01:26:10'),(222,1,'user_activated','User #22 (student): danyhardi06@gmail.com','127.0.0.1','2026-06-24 01:26:24'),(223,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-24 02:09:34'),(224,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-24 02:12:42'),(225,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-24 02:15:21'),(226,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-24 02:46:20'),(227,24,'export_live_grades','Session #5','127.0.0.1','2026-06-24 02:47:36'),(228,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-24 02:50:12'),(229,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-24 04:51:31'),(230,24,'questions_imported','live: 30 questions, course #1','127.0.0.1','2026-06-24 05:05:33'),(231,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-24 05:36:34'),(232,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-24 05:53:39'),(233,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-24 08:00:11'),(234,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-24 08:00:29'),(235,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-24 08:01:12'),(236,24,'questions_imported','live: 30 questions, course #1','127.0.0.1','2026-06-24 08:05:29'),(237,24,'export_live_grades','Session #7','127.0.0.1','2026-06-24 08:29:13'),(238,24,'export_live_grades','Session #7','127.0.0.1','2026-06-24 08:37:23'),(239,1,'login_success','User #1 (promoter)','127.0.0.1','2026-06-24 08:37:58'),(240,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-24 16:33:01'),(241,NULL,'login_failed','Email: danyhardi06@gmail.com','127.0.0.1','2026-06-24 16:33:38'),(242,22,'login_success','User #22 (teacher)','127.0.0.1','2026-06-24 16:33:47'),(243,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-24 16:34:05'),(244,NULL,'login_failed','Email: teacher@studyvibe.edu','127.0.0.1','2026-06-24 16:34:13'),(245,2,'login_success','User #2 (teacher)','127.0.0.1','2026-06-24 16:34:23'),(246,24,'login_success','User #24 (teacher)','127.0.0.1','2026-06-24 16:34:38'),(247,24,'export_live_grades','Session #99','127.0.0.1','2026-06-24 16:41:05');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `certificate_code` varchar(100) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `manual_issue` tinyint(1) NOT NULL DEFAULT 0,
  `issued_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_code` (`certificate_code`),
  KEY `student_id` (`student_id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
INSERT INTO `certificates` VALUES (1,20,2,'SV-2-BE40DC9A','2026-06-14 19:29:15',0,NULL);
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certification_attempts`
--

DROP TABLE IF EXISTS `certification_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certification_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `passed` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_questions` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `certification_attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certification_attempts_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certification_attempts`
--

LOCK TABLES `certification_attempts` WRITE;
/*!40000 ALTER TABLE `certification_attempts` DISABLE KEYS */;
INSERT INTO `certification_attempts` VALUES (1,20,1,70.00,0,'2026-06-14 12:38:22',NULL),(2,20,1,96.43,1,'2026-06-14 19:29:15',NULL),(3,21,1,57.14,0,'2026-06-15 03:52:11',NULL),(4,20,2,21.67,0,'2026-06-18 02:30:40',60);
/*!40000 ALTER TABLE `certification_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chapters`
--

DROP TABLE IF EXISTS `chapters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chapters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `chapters_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chapters`
--

LOCK TABLES `chapters` WRITE;
/*!40000 ALTER TABLE `chapters` DISABLE KEYS */;
INSERT INTO `chapters` VALUES (1,1,'Chapitre I : L\'Origine de la Logique Algorithmique',1),(2,1,'Chapitre II : L\'Éthique des Modèles Prédictifs.',2),(3,2,'Introduction aux Bases du ML',1);
/*!40000 ALTER TABLE `chapters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_questions`
--

DROP TABLE IF EXISTS `course_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_option` char(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `course_questions_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_questions`
--

LOCK TABLES `course_questions` WRITE;
/*!40000 ALTER TABLE `course_questions` DISABLE KEYS */;
INSERT INTO `course_questions` VALUES (31,1,'Quelle est l\'illusion épistémologique majeure concernant l\'algorithme dénoncée dans l\'introduction du cours ?','Qu\'il s\'agit d\'une simple heuristique mathématique non démontrable','Qu\'il représente une invention intrinsèquement moderne et consubstantielle à l\'avènement du silicium','Qu\'il est incapable de traiter des structures de données asymétriques','Qu\'il permet de résoudre le problème de l\'arrêt de Turing par la force brute','B'),(32,1,'Comment le « paradoxe fondateur » de l\'algorithmique éclaire-t-il la question de l\'intelligence artificielle ?','Il prouve que les machines ont développé une conscience rudimentaire avant l\'invention des transistors','Il démontre que le calcul binaire est une anomalie mathématique apparue par hasard','Il révèle que pour mécaniser la pensée il faut préalablement qu\'un esprit humain en ait extrait la structure reproductible','Il postule que l\'algorithme est le produit spontané d\'une évolution technologique déterministe','C'),(33,1,'Que nous enseigne fondamentalement l\'approche des mathématiciens babyloniens (ex: la tablette YBC 7289) sur la nature des algorithmes ?','Que la rigueur de la preuve géométrique est indispensable à la justesse du calcul','Que l\'efficacité empirique d\'une procédure peut précéder de plusieurs millénaires la théorie qui la fonde logiquement','Que l\'usage du système binaire était déjà maîtrisé au deuxième millénaire avant notre ère','Que toute procédure mathématique s\'appuie nécessairement sur une ontologie platonicienne','B'),(34,1,'En quoi la méthode de multiplication égyptienne (Papyrus Rhind) anticipe-t-elle l\'informatique moderne ?','Elle préfigure l\'architecture de von Neumann en séparant la mémoire du processeur','Elle est une implémentation archaïque du théorème d\'incomplétude de Gödel','Elle annonce l\'algorithme de hachage utilisé dans la technologie blockchain','Elle utilise une décomposition en puissances de 2 analogue à l\'exponentiation rapide contemporaine','D'),(35,1,'Quelle analogie structurelle et philosophique peut-on tracer entre les tablettes babyloniennes et les réseaux de neurones profonds (Deep Learning) actuels ?','Les deux nécessitent une puissance de calcul quantique pour fonctionner','L\'opacité épistémologique : ils fournissent des résultats redoutablement efficaces sans que leur causalité interne ne soit théoriquement explicite','Ils reposent tous deux sur les principes stricts de la logique syllogistique d\'Aristote','Ils ne peuvent opérer que sur des ensembles de nombres entiers naturels','B'),(36,1,'Quelle rupture épistémologique majeure Muhammad ibn Musa al-Khawarizmi introduit-il par rapport à ses prédécesseurs ?','Il découvre les lois de De Morgan avant leur formulation algébrique','Il invente le concept de machine universelle en s\'inspirant des engrenages grecs','Il adjoint à l\'exécution de ses procédures une justification géométrique marquant la distinction entre calculer et comprendre','Il prouve mathématiquement l\'impossibilité de résoudre l\'Entscheidungsproblem','C'),(37,1,'Selon Euclide dans ses « Éléments », quels sont les trois piliers théoriques qui définissent un algorithme formellement identifiable ?','La compilation l\'exécution et le débogage','L\'abstraction la modularité et l\'héritage de classes','L\'inférence sémantique la complétude et la cohérence','La procédure opératoire l\'exigence de la preuve et la garantie de la terminaison','D'),(38,1,'Quelle est l\'affirmation révolutionnaire introduite par la syllogistique d\'Aristote dans les « Premiers Analytiques » ?','La vérité d\'un énoncé dépend exclusivement de sa vérifiabilité empirique','La validité d\'une inférence est déterminée par sa structure formelle indépendamment du contenu matériel de ses prémisses','La logique humaine est irréductible à toute forme de catégorisation mécanique','Le principe du tiers exclu ne s\'applique pas aux syllogismes impliquant des concepts infinis','B'),(39,1,'Dans le dialogue platonicien du « Criton », quel conflit moral s\'apparente aux débats contemporains sur la justice algorithmique ?','La contradiction entre l\'efficacité d\'un calcul et sa consommation énergétique','Le risque inhérent à la perte de contrôle des données personnelles par les citoyens','Le conflit éthique entre l\'assujettissement aveugle à une procédure normative et la perception subjective de son injustice','La partialité des réseaux de neurones face aux décisions géopolitiques','C'),(40,1,'Quelle était la prémisse radicale sous-jacente à l\'Ars Magna de Raymond Lulle au XIIIe siècle ?','La vérité théologique peut être générée et démontrée par la combinatoire purement mécanique de concepts discrets','Le langage naturel humain est trop ambigu pour exprimer la moindre vérité mathématique','L\'âme humaine est une machine spirituelle fonctionnant selon le système dyadique','Les universaux n\'ont aucune existence réelle en dehors de l\'intellect de Dieu','A'),(41,1,'En quoi Raymond Lulle est-il considéré philosophiquement comme le concepteur du premier « algorithme persuasif » ?','Il utilisait les statistiques bayésiennes pour manipuler les masses','Il cherchait à automatiser la démonstration logique pour convertir mécaniquement les non-chrétiens à sa théologie','Il a inventé la première forme de propagande algorithmique imprimée','Il manipulait l\'algèbre de Boole pour fausser les débats philosophiques','B'),(42,1,'Si l\'on applique la thèse « nominaliste » de Guillaume d\'Ockham aux LLMs (Large Language Models) contemporains que conclut-on ?','Le modèle capte une essence ontologique réelle sous-jacente au langage humain','Le modèle génère une conscience rudimentaire grâce à la complexité de ses calculs','Le modèle ne manipule que des étiquettes arbitraires dont le sens n\'est qu\'une pure illusion statistique de cooccurrence','Le modèle transcende la syntaxe pour atteindre une sémantique authentiquement aristotélicienne','C'),(43,1,'Quelle est la tension philosophique profonde qui traverse la pensée de René Descartes concernant la mécanisation ?','Il invente le calcul binaire tout en refusant de l\'utiliser pour la théologie','Il prône la division analytique méthodique tout en affirmant l\'irréductibilité ontologique de l\'âme (res cogitans) à la mécanique','Il rejette la logique aristotélicienne mais s\'en sert pour prouver l\'existence de Dieu','Il soutient que les animaux ont une âme pensante mais que les humains sont des automates','B'),(44,1,'Que représentait précisément la « Characteristica Universalis » dans le projet monumental de Leibniz ?','Un système juridique universel remplaçant les lois nationales par des formules mathématiques','L\'assignation d\'un nombre premier indécomposable à chaque concept primitif pour faire de la pensée un calcul arithmétique','Une machine physique à cylindre cannelé capable d\'exécuter des divisions en virgule flottante','Le premier brouillon théorique de la Machine de Turing','B'),(45,1,'Quelle implication éthique vertigineuse (et terrifiante) sous-tend le « Calculus Ratiocinator » de Leibniz ?','L\'extinction programmée de la race humaine au profit d\'entités purement logiques','La réduction absolue de tout désaccord moral ou philosophique à un simple « bug » arithmétique rectifiable par le calcul','L\'effondrement des systèmes théologiques face à la rationalité des algorithmes génétiques','L\'incapacité de la machine à intégrer le principe du libre arbitre','B'),(46,1,'Quelle distinction épistémologique cruciale Blaise Pascal établit-il face à la mécanisation de sa propre « Pascaline » ?','La supériorité de la logique propositionnelle sur la logique des classes','La distinction entre l\'esprit de géométrie (formalisable) et l\'esprit de finesse (intuitif et irréductible aux procédures)','La différence entre les mathématiques continues et les mathématiques discrètes','L\'incapacité de la machine à calculer des probabilités aléatoires','B'),(47,1,'Que démontre ontologiquement la création de la machine arithmétique de Pascal en 1642 ?','Que la philosophie scolastique avait tort sur toute la ligne','Que la pensée arithmétique peut être extériorisée incarnée dans la matière inerte et rendue reproductible','Que le système binaire est supérieur au système décimal pour la mécanique matérielle','Que l\'intelligence artificielle forte est conceptuellement impossible','B'),(48,1,'Dans son ouvrage de 1854 quel est l\'axiome fondateur de George Boole concernant la pensée humaine ?','La cognition humaine est un phénomène exclusivement biologique insaisissable par les mathématiques','Les lois régissant le raisonnement logique valide sont strictement isomorphes à des équations algébriques','L\'esprit humain fonctionne selon un système quantique aux états superposés','La logique formelle doit être purgée de toute considération métaphysique ou arithmétique','B'),(49,1,'Quelle abstraction mathématique George Boole opère-t-il pour créer son algèbre logique ?','Il introduit les nombres complexes pour modéliser l\'incertitude du langage','Il réduit l\'univers infini des propositions sémantiques à une stochastique bayésienne','Il évacue le sens matériel en réduisant les états de vérité à la dualité arithmétique stricte du 0 et du 1','Il remplace les syllogismes par des probabilités continues','C'),(50,1,'Quelle loi algébrique est strictement spécifique à la logique booléenne et s\'oppose à l\'algèbre polynomiale classique ?','La loi de commutativité (A*B = B*A)','La loi de distributivité (A*(B+C) = A*B + A*C)','La loi d\'idempotence (A*A = A) stipulant que répéter une condition n\'altère pas sa vérité','La loi d\'associativité ((A+B)+C = A+(B+C))','C'),(51,1,'Dans l\'algèbre de Boole comment interprète-t-on sémantiquement l\'opération d\'addition logique (A + B) ?','La proposition n\'est vraie que si A et B sont conjointement et exclusivement vrais','La proposition est vraie dès lors qu\'au minimum l\'une des deux variables s\'actualise à l\'état vrai','La proposition inverse systématiquement la valeur de la variable A selon l\'état de B','La proposition est fausse si A et B partagent la même valeur de vérité','B'),(52,1,'Selon la première loi formulée par Augustus De Morgan à quoi équivaut mathématiquement la négation d\'une conjonction (NON (A ET B)) ?','À la disjonction des compléments : (NON A) OU (NON B)','Au produit arithmétique des variables inversées','À la tautologie universelle générant l\'état 1','À l\'inclusion stricte de A dans l\'ensemble B','A'),(53,1,'Quelle est la conséquence technologique et épistémologique inouïe du fait de réduire la logique à l\'algèbre de Boole ?','Si la vérité est réductible à l\'arithmétique alors tout dispositif physique capable d\'additionner des 0 et des 1 peut mécaniser le raisonnement','Cela prouve que l\'intelligence humaine est mathématiquement modélisable par des fonctions continues','Cela démontre l\'incapacité de la logique à traiter les paradoxes auto-référentiels','Cela oblige à abandonner la géométrie euclidienne pour la conception des ordinateurs','A'),(54,1,'En 1937 quelle découverte magistrale l\'étudiant Claude Shannon expose-t-il dans sa thèse au MIT ?','L\'isomorphisme structurel absolu entre les équations booléennes abstraites et les circuits électriques de commutation à relais','L\'impossibilité de résoudre le problème de l\'arrêt de Turing à l\'aide de circuits analogiques','Le premier protocole cryptographique inviolable basé sur les nombres premiers','La preuve mathématique que les réseaux de neurones peuvent simuler le cerveau humain','A'),(55,1,'Selon la thèse de Shannon à quel type d\'opérateur logique correspond un circuit électrique où les interrupteurs sont placés en série ?','À l\'opérateur de disjonction (OU)','À l\'opérateur de conjonction (ET) exigeant la fermeture simultanée pour laisser passer le courant','À la porte logique XOR (OU exclusif)','À l\'opérateur de complémentation (NON)','B'),(56,1,'Quel était l\'objectif ultime du « Programme de Hilbert » formulé au début du XXe siècle ?','Démontrer que la géométrie non-euclidienne était une aberration logique','Prouver que la conscience humaine est un simple algorithme biologique','Mettre toutes les mathématiques sur des fondements solides en prouvant leur complétude leur cohérence et leur décidabilité absolues','Inventer une machine capable de remplacer le travail intellectuel des mathématiciens','C'),(57,1,'Dans le triptyque de Hilbert que signifie exactement la notion de « Décidabilité » (formulée via l\'Entscheidungsproblem) ?','L\'absence absolue de paradoxes dans l\'axiomatique de Peano','La capacité d\'un système à générer spontanément de nouveaux théorèmes','L\'existence d\'une procédure algorithmique mécanique permettant de statuer incontestablement sur la véracité de toute proposition mathématique','La certitude que tout théorème vrai possède une démonstration unique','C'),(58,1,'Quel cataclysme épistémologique est provoqué par le premier théorème d\'incomplétude de Kurt Gödel en 1931 ?','Il prouve qu\'aucune machine ne pourra jamais battre un humain aux échecs','Il démontre l\'incomplétude intrinsèque de tout système formel suffisamment puissant ruinant ainsi l\'espoir d\'une axiomatique parfaite de l\'arithmétique','Il affirme que le calcul infinitésimal de Leibniz et Newton repose sur une faille logique','Il invalide l\'ensemble des lois de l\'algèbre booléenne','B'),(59,1,'Sur quel type d\'artefact logique Gödel s\'appuie-t-il pour construire sa fameuse proposition mathématique \"G\" ?','Une équation stochastique modélisant le mouvement brownien','Un syllogisme aristotélicien dont la prémisse majeure est fausse','Une équation auto-référentielle stipulant de manière méta-mathématique sa propre impossibilité de démonstration dans le système','Une contradiction flagrante dérivée de la loi de De Morgan','C'),(60,1,'Quelle est la distinction philosophique écrasante que la preuve de Gödel impose aux mathématiques modernes ?','La supériorité de l\'intuition géométrique sur l\'abstraction algébrique','La scission irrémédiable entre la notion de vérité mathématique et la notion de preuve formelle mécanisable','La différence entre les mathématiques pures et les mathématiques appliquées','L\'incompatibilité entre le système binaire et les nombres irrationnels','B'),(61,1,'Dans son article fondateur de 1936 quel concept théorique Alan Turing invente-t-il pour répondre à la question de Hilbert ?','Le transistor à effet de champ','Le théorème d\'incomplétude algorithmique','Une machine abstraite dotée d\'un ruban infini d\'une tête de lecture et d\'un registre d\'états de transition','L\'architecture de calcul quantique à qubits superposés','C'),(62,1,'Quelle est la caractéristique révolutionnaire de la « Machine de Turing Universelle » par rapport à un automate spécialisé ?','Elle peut simuler n\'importe quelle autre machine de Turing en lisant la description de celle-ci (son programme) directement comme une donnée sur son ruban','Elle fonctionne à une vitesse théoriquement infinie défiant les lois de la thermodynamique','Elle possède une conscience artificielle grâce à une boucle de rétroaction infinie','Elle rejette le principe du tiers exclu de la logique aristotélicienne','A'),(63,1,'Quelle est la conclusion définitive d\'Alan Turing concernant l\'Entscheidungsproblem de David Hilbert ?','La réponse est positive : un tel algorithme existe mais nécessite un ruban infini','La réponse est négative : il démontre par l\'absurde (via le problème de l\'arrêt) qu\'aucun algorithme universel de vérification ne peut mathématiquement exister','La question est mal posée car elle ignore les principes de la mécanique quantique','Le problème ne peut être résolu qu\'en utilisant l\'algèbre de Boole modifiée','B'),(64,1,'Que postule formellement la célèbre « Thèse de Church-Turing » ?','Les algorithmes finiront par remplacer intégralement la cognition humaine','L\'univers physique tout entier est assimilable à un gigantesque ordinateur cellulaire','Toute fonction calculable ou procédure mécanique humainement exécutable est mathématiquement simulable par une Machine de Turing','La logique mathématique est fondamentalement contradictoire et indécidable','C'),(65,1,'Quel changement de paradigme philosophique Alan Turing introduit-il avec son « Test de Turing » en 1950 ?','Il abandonne la question essentialiste de savoir si la machine « pense » pour adopter un critère béhavioriste d\'indiscernabilité avec l\'humain','Il prouve neurologiquement que le cerveau humain fonctionne exactement comme un ruban perforé','Il stipule que l\'intelligence artificielle doit être soumise à des contraintes morales asimoviennes','Il invalide la distinction de Pascal entre l\'esprit de finesse et l\'esprit de géométrie','A'),(66,1,'Face à l\'objection de Lady Lovelace (affirmant qu\'une machine ne peut rien créer d\'original) quelle parade Turing propose-t-il ?','Il rejette l\'idée même d\'originalité humaine la qualifiant d\'illusion déterministe','Il introduit le concept d\'apprentissage automatique (machine learning) où la machine dériverait ses propres règles par l\'expérience plutôt que par une programmation stricte','Il affirme que la génération de nombres pseudo-aléatoires suffit à simuler l\'inspiration artistique','Il propose d\'ajouter une composante analogique aux processeurs digitaux','B'),(67,1,'En quoi le concept archaïque de multiplication par doublement égyptien incarne-t-il la première tension fondatrice de l\'algorithmique ?','La prééminence de la forme sur le contenu sémantique','La tension entre universalité et particularité dans la géométrie du nil','Le clivage entre efficacité opératoire et compréhension théorique : une procédure fonctionne indépendamment de la théorie qui l\'explicite','L\'opposition irréconciliable entre la logique propositionnelle et le calcul intégral','C'),(68,1,'Comment le projet syllogistique d\'Aristote pose-t-il la matrice épistémologique des algorithmes de filtrage contemporains ?','En instaurant la possibilité de juger la validité d\'un processus exclusivement par sa syntaxe formelle en évacuant totalement la substance de son contenu matériel','En exigeant que toute donnée soit vérifiée par un processus démocratique préalable','En imposant l\'usage exclusif de métaphores pour coder la réalité','En limitant le traitement de l\'information aux données strictement quantitatives','A'),(69,1,'En quoi le rêve du « Calculus Ratiocinator » leibnizien constitue-t-il un écueil éthique majeur face à l\'IA contemporaine ?','Il ignore les lois fondamentales de la thermodynamique computationnelle','Il propage l\'illusion réductionniste selon laquelle la diversité irréductible des conflits humains n\'est qu\'un bug mathématique soluble par l\'optimisation d\'un code','Il favorise l\'émergence d\'une intelligence artificielle hostile et militarisée','Il nécessite l\'abolition du langage naturel au profit d\'un esperanto mathématique','B'),(70,1,'Pourquoi l\'argument de la « res cogitans » de Descartes complique-t-il philosophiquement l\'adoption de l\'IA forte ?','Parce qu\'il postule que l\'âme humaine possède une essence compréhensive et intentionnelle qui échappe structurellement à toute mécanisation analytique','Parce qu\'il démontre que la glande pinéale est le seul récepteur des ondes électromagnétiques','Parce qu\'il interdit l\'utilisation de méthodes quantitatives dans les sciences de l\'esprit','Parce qu\'il rejette formellement les mathématiques comme outil de connaissance certaine','A'),(71,1,'Dans la perspective de la philosophie de Pascal pourquoi une Intelligence Artificielle ne pourrait-elle jamais rendre une justice humaine équitable ?','Parce qu\'elle manque de la mémoire de stockage nécessaire pour analyser la jurisprudence complète','Parce que la justice relève de l\'esprit de finesse nécessitant une intuition morale contextuelle que l\'esprit de géométrie (l\'algorithme) ne peut pas instancier','Parce qu\'elle serait systématiquement hackée par des entités malveillantes','Parce que son code source est soumis aux biais de la logique d\'Aristote','B'),(72,1,'Quelle est la fonction conceptuelle de l\'état de « terminaison » dans l\'algorithme originel du PGCD d\'Euclide ?','Garantir que la machine ne consomme pas trop de mémoire RAM','Assurer que la procédure produise un résultat fini en un nombre d\'étapes limité évitant ainsi l\'écueil des boucles infinies de l\'indécidabilité','Prouver que le plus grand commun diviseur est un nombre premier','Permettre l\'intégration de variables stochastiques dans l\'équation','B'),(73,1,'Quel enseignement l\'informatique moderne tire-t-elle de l\'Ars Magna de Raymond Lulle concernant le péril des systèmes de recommandation ?','Que la vérité est toujours proportionnelle au temps de calcul alloué par le processeur','Que l\'automatisation combinatoire des concepts peut être instrumentalisée comme un outil de persuasion massive et de création de vérités artificielles','Que les algorithmes de tri sont incapables de traiter des chaînes de caractères complexes','Que la cryptographie asymétrique est la seule défense contre la manipulation idéologique','B'),(74,1,'Si l\'Entscheidungsproblem de Hilbert avait trouvé une réponse positive de la part de Turing quelle en aurait été la conséquence philosophique ?','L\'extinction immédiate de toutes les contradictions philosophiques humaines','La fin de la créativité mathématique car toute vérité aurait été réductible à la simple exécution mécanique d\'un programme informatique infaillible','La preuve irréfutable de l\'existence de Dieu via l\'algèbre de Boole','L\'incapacité d\'utiliser des algorithmes de chiffrement sécurisés','B'),(75,1,'Pourquoi le concept de « ruban infini » dans la Machine de Turing est-il un idéal-type épistémologique inatteignable matériellement ?','Parce qu\'il violerait la théorie de la relativité restreinte','Parce qu\'il suppose une mémoire de stockage illimitée affranchie des contraintes de l\'entropie et de la matérialité physique','Parce qu\'il serait impossible à coder en langage binaire','Parce qu\'il nécessiterait une tête de lecture capable de se déplacer plus vite que la lumière','B'),(76,1,'En quoi le théorème d\'incomplétude de Gödel protège-t-il paradoxalement la singularité de la cognition humaine face à la machine ?','Il garantit que les humains pourront toujours débrancher physiquement les machines','Il démontre que la vérité mathématique ne peut être épuisée par le formalisme mécanique réservant ainsi un espace irréductible à l\'intuition humaine transcendantale','Il interdit formellement le développement de réseaux de neurones récurrents','Il prouve que l\'univers est une simulation informatique incomplète','B'),(77,1,'Selon la réflexion du chapitre 1 quel est le danger ultime de l\'illusion tenace assimilant l\'algorithme à la modernité électronique ?','Elle nous empêche de comprendre le code source des systèmes d\'exploitation obsolètes','Elle occulte le fait que tout algorithme véhicule des choix idéologiques et éthiques sur la nature de la pensée forgés au cours de vingt-cinq siècles de philosophie','Elle nous fait surestimer la puissance des ordinateurs quantiques','Elle freine le développement économique des entreprises de la Silicon Valley','B'),(78,1,'Quelle aporie logique l\'intelligence artificielle générative contemporaine ravive-t-elle à l\'aune du débat sur les universaux ?','Le problème du voyageur de commerce et son explosion combinatoire P vs NP','L\'incapacité de trancher si la machine manipule de pures formes nominales vidées de sens ou si elle accède à une sémantique conceptuelle structurée','Le paradoxe de Fermi sur la probabilité de rencontrer une IA extraterrestre','L\'antinomie kantienne concernant les limites de l\'univers matériel','B'),(79,1,'Pourquoi l\'opacité d\'un algorithme de type Deep Learning est-elle radicalement différente de l\'opacité d\'un système expert classique ?','Parce que le système expert cache son code source sous brevet alors que le Deep Learning est toujours Open Source','Parce que le réseau neuronal construit lui-même ses propres pondérations causales créant une logique interne indéchiffrable même pour ses propres ingénieurs mathématiciens','Parce que le Deep Learning repose sur l\'algèbre de Boole tandis que le système expert utilise la géométrie d\'Euclide','Parce que l\'un est matériel et l\'autre est purement spirituel','B'),(80,1,'Quel lien direct relie l\'idempotence booléenne à l\'architecture informatique moderne du traitement de l\'information ?','Elle permet aux boucles conditionnelles (if/else) de tester une même variable de vérité à l\'infini sans altérer sa substance logique originelle','Elle empêche la surchauffe thermique des microprocesseurs lors de calculs intenses','Elle autorise le dépassement de la vitesse de la lumière pour le transfert des paquets de données','Elle rend obsolète l\'utilisation de la mémoire RAM dans les ordinateurs quantiques','A'),(81,1,'Comment la loi de De Morgan permet-elle l\'optimisation physique des processeurs (CPU) contemporains ?','En refroidissant les transistors à l\'azote liquide','En prouvant mathématiquement qu\'on peut remplacer toutes les portes ET et OU complexes par des assemblages standardisés de portes NON-OU et NON-ET universelles','En générant de l\'énergie électrique par inversion de flux quantique','En compressant les fichiers vidéos à un taux de zéro perte sémantique','B'),(82,1,'En quoi le geste de Turing d\'assimiler la donnée au programme (Machine Universelle) bouleverse-t-il l\'ontologie de la technique ?','Il transforme la machine d\'un outil physique à usage unique en un substrat malléable capable d\'incarner une infinité de machines conceptuelles virtuelles','Il prouve que la matière n\'existe pas et que tout est information','Il soumet l\'ingénierie matérielle aux lois rigoureuses de la biologie évolutive','Il abolit la distinction entre le temps et l\'espace dans le ruban de la machine','A'),(83,1,'Quelle asymétrie philosophique est mise en évidence par le Test de Turing (Jeu de l\'imitation) ?','La machine cherche à s\'élever au statut divin tandis que l\'homme se rabaisse au calcul animal','L\'évaluation de l\'intelligence artificielle est entièrement soumise à l\'anthropocentrisme et à la subjectivité faillible de l\'évaluateur humain trompé','La machine calcule la vérité absolue pendant que l\'humain reste piégé dans l\'allégorie de la caverne de Platon','Le programme est évalué sur sa moralité tandis que l\'homme est évalué sur sa vitesse de frappe','B'),(84,1,'Pourquoi l\'argument de l\'Entscheidungsproblem était-il le dernier rempart épistémologique du positivisme scientifique ?','Il promettait de transformer la politique en une science exacte sans idéologie','Il fondait l\'espoir qu\'absolument toute affirmation scientifique pourrait être mécaniquement classifiée comme vraie ou fausse éliminant l\'incertitude humaine','Il garantissait la viabilité économique de la conquête spatiale algorithmique','Il affirmait que Dieu était le grand architecte de l\'algèbre booléenne','B'),(85,1,'En philosophie de la technique que signifie l\'affirmation du cours : « La vérité est plus grande que la preuve » (référence à Gödel) ?','Que la religion est intrinsèquement supérieure à l\'informatique théorique','Que la sphère des théorèmes mathématiques vrais déborde et transcende irrémédiablement l\'ensemble restreint de ce qui est algorithmiquement démontrable par axiomes','Que la justice humaine doit primer sur la justice algorithmique en cas d\'incomplétude du code pénal','Que les algorithmes de cryptographie asymétrique finiront toujours par être brisés par la vérité quantique','B'),(86,1,'Si l\'on suit le raisonnement de la Leçon 1, pourquoi un algorithme de justice prédictive est-il philosophiquement vicié à la racine ?','Il consomme une quantité disproportionnée d\'énergie fossile','Il applique une logique aristotélicienne formelle (détachée du contenu) à une matière humaine qui requiert irreductiblement la nuance de l\'esprit de finesse pascalien','Il utilise des bases de données open-source potentiellement corrompues par des hackers nominaux','Il ignore les lois de De Morgan dans le traitement de ses portes logiques','B'),(90,2,'Quel est le rôle exact du biais (bias) dans une équation de neurone artificiel du type $z = w \\cdot x + b$ ?','Il décale la fonction d\'activation vers la gauche ou la droite pour permettre au neurone de s\'activer même lorsque toutes les entrées sont nulles.','Il multiplie la somme pondérée par un facteur d\'échelle dynamique afin d\'éviter la saturation précoce des gradients locaux.','Il normalise les valeurs d\'entrée entre zéro et un pour stabiliser les calculs matriciels lors de la propagation avant.','Il élimine les composantes de bruit de haute fréquence dans les signaux d\'entrée en ajustant la variance globale.','A'),(91,2,'Comment est calculée la somme pondérée au sein d\'un neurone artificiel standard avant l\'application de la fonction d\'activation ?','En calculant la moyenne géométrique de chaque valeur d\'entrée multipliée par son poids synaptique respectif sans ajouter de biais.','En effectuant le produit scalaire du vecteur des entrées par le vecteur des poids, auquel on ajoute ensuite la valeur du biais.','En sommant directement toutes les valeurs d\'entrée puis en multipliant ce résultat unique par la somme de tous les poids.','En divisant la somme cumulée des entrées par la somme absolue des poids ajustés pour obtenir une valeur relative.','B'),(92,2,'Pourquoi l\'introduction d\'une fonction d\'activation non linéaire est-elle indispensable dans un réseau de neurones multicouche ?','Pour permettre au réseau d\'apprendre des frontières de décision complexes et d\'éviter que le réseau ne se réduise à une simple transformation linéaire globale.','Pour s\'assurer que toutes les valeurs de sortie restent confinées entre zéro et un afin d\'éviter des instabilités numériques majeures.','Pour accélérer le temps de calcul des dérivées partielles en réduisant le nombre d\'opérations matricielles nécessaires dans le réseau.','Pour éliminer le besoin d\'ajuster les biais durant la phase de rétropropagation en forçant une symétrie parfaite des activations.','C'),(93,2,'Quelle est la définition structurelle rigoureuse d\'un perceptron multicouche (MLP) ?','Une architecture récursive où chaque neurone d\'une couche est connecté exclusivement à lui-même pour mémoriser les états temporels précédents.','Un ensemble de neurones disposés de façon circulaire où les informations circulent de manière bidirectionnelle continue sans fin précise.','Un réseau de neurones convolutionnel utilisant des filtres de taille variable pour extraire des caractéristiques spatiales complexes.','Un réseau de neurones à propagation directe composé d\'une couche d\'entrée, d\'une ou plusieurs couches cachées et d\'une couche de sortie.','D'),(94,2,'Dans un réseau de neurones, comment s\'interprète intuitivement la valeur d\'un poids synaptique (weight) ?','Il sert de seuil de tolérance statique en dessous duquel aucune information ne peut être transmise à la couche supérieure du réseau.','Il quantifie l\'importance ou l\'influence d\'une entrée spécifique sur l\'activation d\'un neurone de la couche suivante du réseau.','Il représente la vitesse de propagation temporelle du signal électrique simulé entre deux couches consécutives du système.','Il indique la marge d\'erreur maximale autorisée pour une prédiction donnée lors de la phase d\'évaluation des performances.','B'),(95,2,'Quelle est la principale caractéristique mathématique de la fonction d\'activation Sigmoïde ?','Elle prend en entrée n\'importe quel nombre réel et renvoie systématiquement une valeur comprise strictement entre la plage ouverte de $-1$ et $1$.','Elle annule systématiquement toutes les valeurs d\'entrée négatives et conserve telles quelles toutes les valeurs d\'entrée positives.','Elle écrase les valeurs d\'entrée dans un intervalle compris entre $0$ et $1$, ce qui la rend idéale pour modéliser des probabilités.','Elle applique une fonction polynomiale de second degré qui amplifie de manière exponentielle les valeurs proches de l\'origine.','C'),(96,2,'En quoi consiste fondamentalement l\'apprentissage supervisé appliqué aux réseaux de neurones ?','À regrouper des données non étiquetées en fonction de leurs similitudes statistiques intrinsèques sans intervention humaine préalable.','À générer de nouvelles données synthétiques réalistes en faisant rivaliser deux réseaux de neurones différents en boucle fermée.','À explorer un environnement dynamique en maximisant une récompense cumulative grâce à des actions sélectionnées par essais et erreurs.','À entraîner un modèle sur un ensemble de données contenant à la fois des exemples d\'entrée et leurs étiquettes cibles associées.','D'),(97,2,'Quelle est la nature mathématique des données qui pénètrent dans la couche d\'entrée d\'un réseau de neurones ?','Un vecteur ou un tenseur de valeurs numériques représentant les caractéristiques brutes ou prétraitées de l\'échantillon étudié.','Une suite d\'instructions algorithmiques décrivant la structure globale des données pour guider le choix de l\'architecture réseau.','Un ensemble de fonctions de distribution de probabilités modélisant les incertitudes de mesure sur l\'ensemble de l\'apprentissage.','Une matrice de gradients précalculés servant à initialiser les valeurs de départ avant de lancer la première propagation avant.','A'),(98,2,'Comment définit-on une couche cachée (hidden layer) au sein d\'une architecture de réseau de neurones ?','Une couche de neurones temporaire qui n\'intervient que durant la phase d\'évaluation pour stocker les métriques de performance globales.','Une couche d\'interface réseau chargée de crypter les données d\'entrée pour garantir la confidentialité des calculs effectués en interne.','Une couche de neurones située entre la couche d\'entrée et la couche de sortie, dont les activations ne sont pas directement observées.','Une couche de secours qui remplace dynamiquement une autre couche défaillante en cas de divergence des valeurs de gradients locaux.','C'),(99,2,'Quel est le rôle principal de la fonction d\'activation Softmax lorsqu\'elle est positionnée sur la couche de sortie ?','Elle applique une transformation linéaire simple pour conserver la valeur exacte des activations de la couche de sortie sans modification.','Elle force toutes les activations à devenir égales à zéro ou un pour effectuer une classification binaire stricte sans nuances.','Elle élimine les valeurs d\'activation extrêmes en les remplaçant par la moyenne arithmétique de l\'ensemble des sorties du réseau.','Elle convertit un vecteur de scores réels en une distribution de probabilités dont la somme de tous les éléments est égale à un.','D'),(100,2,'Quelle est la définition exacte d\'une époque (epoch) dans le processus d\'entraînement d\'un réseau de neurones ?','Un passage complet de l\'intégralité du jeu de données d\'entraînement à travers le réseau de neurones lors de la phase d\'apprentissage.','Le temps de calcul précis exprimé en secondes requis pour mettre à jour une seule fois l\'ensemble des poids d\'un neurone.','Le nombre total de couches cachées traversées par le signal lors d\'une phase complète d\'évaluation de la propagation avant.','Une étape de validation intermédiaire durant laquelle on évalue les performances du modèle uniquement sur l\'ensemble de test.','A'),(101,2,'Quel est l\'objectif premier d\'une fonction de coût (loss function) dans un réseau de neurones ?','Elle calcule les dérivées partielles de chaque paramètre pour mettre à jour directement les poids sans passer par la descente de gradient.','Elle mesure l\'écart ou la pénalité entre la prédiction générée par le réseau et la valeur cible réelle attendue pour un exemple.','Elle détermine automatiquement le nombre optimal de neurones à intégrer dans chaque couche cachée pour éviter le surapprentissage.','Elle normalise les poids du réseau à chaque itération pour s\'assurer que leur somme reste constante tout au long de l\'entraînement.','B'),(102,2,'Comment est formulée l\'erreur quadratique moyenne (MSE) pour un ensemble de prédictions ?','Comme la racine carrée de la somme des écarts absolus entre les prédictions et les valeurs réelles sur l\'ensemble des données.','Comme le produit des différences absolues entre les prédictions et les valeurs cibles divisé par le nombre total d\'échantillons.','Comme la somme des logarithmes naturels des rapports entre chaque prédiction finale et sa valeur cible correspondante.','Comme la moyenne des carrés des différences entre les valeurs prédites par le modèle et les valeurs réelles observées.','D'),(103,2,'En quoi consiste l\'étape d\'initialisation des poids dans un réseau de neurones ?','À attribuer des valeurs de départ aux poids du réseau pour briser la symétrie et lancer le processus d\'apprentissage efficacement.','À réinitialiser tous les poids à zéro au début de chaque époque pour s\'assurer que l\'apprentissage redémarre sur de bonnes bases.','À fixer des valeurs aléatoires infiniment grandes pour accélérer le processus de convergence lors des premières descentes de gradient.','À copier exactement les valeurs des données d\'entrée directement dans les poids pour guider le réseau vers la bonne solution.','A'),(104,2,'Quelle contrainte majeure présente un réseau constitué uniquement de neurones dotés de fonctions d\'activation linéaires ?','Il est incapable d\'ajuster ses biais de manière indépendante car les gradients de chaque couche deviennent identiques lors de la rétropropagation.','Il équivaut mathématiquement à un simple modèle linéaire à une seule couche, peu importe le nombre total de couches cachées ajoutées.','Il converge de manière instable car les valeurs de sortie augmentent de façon exponentielle à chaque nouvelle couche du réseau.','Il ne permet pas l\'utilisation de la descente de gradient car ses dérivées partielles sont systématiquement indéfinies en tout point.','B'),(105,2,'Qu\'est-ce que représente mathématiquement le gradient d\'une fonction de coût ?','Un scalaire indiquant la distance minimale absolue séparant la configuration actuelle des poids du minimum global de la fonction de coût.','Une matrice de second ordre décrivant la courbure locale de la surface de perte pour ajuster dynamiquement la taille du pas d\'apprentissage.','Le vecteur des dérivées partielles de la fonction de coût par rapport à chacun des paramètres, pointant vers la direction de plus forte pente.','Une valeur de probabilité mesurant la confiance globale du modèle quant à la justesse de ses prédictions sur les données d\'entraînement.','C'),(106,2,'Quel est le rôle du taux d\'apprentissage (learning rate) dans l\'algorithme de descente de gradient ?','Il détermine la taille de l\'incrément ou du pas utilisé pour mettre à jour les paramètres à chaque étape de la descente de gradient.','Il fixe le nombre maximal d\'itérations que l\'algorithme de descente de gradient est autorisé à effectuer avant de s\'arrêter.','Il ajuste la variance des données d\'entrée pour s\'assurer que les gradients restent stables tout au long du processus d\'apprentissage.','Il calcule automatiquement la valeur exacte du minimum global en résolvant un système d\'équations linéaires à chaque itération.','A'),(107,2,'Quel phénomène se produit si le taux d\'apprentissage choisi est excessivement élevé ?','L\'algorithme de descente de gradient s\'arrête instantanément car il détecte une fausse convergence due à une pente locale nulle.','L\'apprentissage devient extrêmement lent car l\'algorithme fait de minuscules pas et nécessite des millions d\'itérations supplémentaires.','Les mises à jour des paramètres peuvent osciller de manière chaotique et l\'algorithme risque de diverger au lieu de converger.','Le modèle mémorise parfaitement l\'ensemble des données d\'apprentissage sans être capable de se généraliser à de nouvelles données.','C'),(108,2,'Quel est l\'inconvénient majeur de l\'utilisation d\'un taux d\'apprentissage extrêmement faible ?','L\'algorithme de descente de gradient oscille violemment autour du minimum global sans jamais pouvoir se stabiliser à la fin.','L\'apprentissage prend énormément de temps et l\'algorithme risque de rester bloqué prématurément dans un minimum local sous-optimal.','La fonction de coût augmente à chaque itération car les pas effectués sont trop petits pour suivre la direction du gradient négatif.','Les poids du réseau de neurones sont réinitialisés à zéro à chaque étape car les valeurs de mise à jour deviennent insignifiantes.','B'),(109,2,'Qu\'est-ce qu\'un minimum local sur la surface de la fonction de coût d\'un réseau de neurones ?','Une région plate où toutes les dérivées secondes sont positives alors que les dérivées premières de la fonction de coût restent inchangées.','Le point culminant de la fonction de coût à partir duquel toutes les directions mènent à une réduction drastique de l\'erreur globale.','Un point de convergence idéal qui garantit que le réseau a atteint le niveau d\'erreur le plus bas possible sur tout l\'espace des phases.','Un point où la fonction de coût est plus basse que dans son voisinage immédiat, mais pas nécessairement le point le plus bas de tous.','D'),(110,2,'Quel est le principe directeur de la descente de gradient stochastique (SGD) ?','Elle utilise l\'intégralité du jeu de données pour calculer le gradient exact avant d\'effectuer une seule mise à jour des paramètres.','Elle évalue le gradient et met à jour les paramètres du modèle après l\'analyse de chaque exemple d\'entraînement individuel.','Elle ajuste les paramètres en faisant la moyenne pondérée des gradients calculés sur plusieurs modèles entraînés en parallèle.','Elle sélectionne de façon aléatoire un sous-ensemble fixe de paramètres à optimiser tout en gelant les autres à chaque itération.','B'),(111,2,'Quelle est la différence fondamentale entre la descente de gradient par lot (Batch) et la descente de gradient stochastique (SGD) ?','La descente par lot utilise des taux d\'apprentissage variables tandis que la méthode stochastique utilise un taux d\'apprentissage fixe.','La descente par lot utilise des dérivées secondes tandis que la méthode stochastique s\'appuie uniquement sur des dérivées premières.','La descente par lot applique des fonctions d\'activation linéaires tandis que la méthode stochastique nécessite des fonctions non linéaires.','La descente par lot calcule le gradient sur tout le jeu de données tandis que la méthode stochastique le fait exemple par exemple.','D'),(112,2,'Comment fonctionne la descente de gradient par mini-lots (Mini-batch Gradient Descent) ?','Elle calcule le gradient à partir d\'un unique exemple sélectionné au hasard, répétant cette opération jusqu\'à épuisement complet des données.','Elle divise la surface d\'erreur en plusieurs sous-régions géométriques indépendantes pour optimiser chaque couche de façon isolée.','Elle estime le gradient de la fonction de coût sur un sous-ensemble de taille intermédiaire d\'exemples d\'apprentissage à chaque étape.','Elle ajuste les paramètres en calculant alternativement le gradient sur un exemple isolé puis sur l\'ensemble complet des données.','C'),(113,2,'Quelle information fournit la dérivée d\'une fonction à une variable en un point donné pour la descente de gradient ?','Elle donne le taux de variation instantané de la fonction, indiquant si la courbe monte ou descend et avec quelle intensité locale.','Elle indique la valeur exacte du minimum global de la fonction sans nécessiter de calculs supplémentaires ou d\'itérations.','Elle mesure la distance géométrique séparant le point actuel de l\'axe des abscisses sur le repère de coordonnées cartésiennes.','Elle définit le rayon de courbure local de la fonction pour déterminer si la fonction est convexe ou non à cet endroit précis.','A'),(114,2,'Qu\'appelle-t-on l\'espace des paramètres dans le contexte de l\'optimisation d\'un réseau de neurones ?','L\'ensemble de toutes les fonctions de coût alternatives que l\'on peut choisir pour évaluer la qualité des prédictions d\'un modèle.','La liste de toutes les variables d\'entrée possibles que l\'on peut injecter dans le réseau pour résoudre un problème donné.','L\'espace géométrique de grande dimension dont chaque axe correspond à un poids ou à un biais ajustable du réseau de neurones.','Le domaine de définition de la fonction d\'activation qui limite la plage de valeurs que les neurones peuvent prendre en sortie.','C'),(115,2,'Comment le mécanisme de l\'élan (Momentum) améliore-t-il la descente de gradient classique ?','Il ajoute une fraction de la mise à jour précédente à la mise à jour actuelle pour accélérer la convergence et réduire les oscillations.','Il augmente de manière uniforme le taux d\'apprentissage à chaque fois que la pente de la fonction de coût devient plus raide.','Il remplace le calcul du gradient par une recherche aléatoire locale pour éviter que les paramètres ne s\'engorgent dans des plateaux.','Il réinitialise les poids du réseau lorsque l\'erreur stagne pour forcer l\'algorithme à explorer de nouvelles régions de l\'espace.','A'),(116,2,'Quel est le comportement du gradient lorsque les paramètres s\'approchent d\'un minimum local ou global ?','Il change brusquement de signe de manière répétée pour signaler à l\'algorithme qu\'il doit augmenter la taille de son pas d\'ajustement.','Il augmente de manière exponentielle pour forcer le modèle à franchir le minimum afin d\'explorer de nouvelles zones de coût.','Il reste parfaitement constant pour stabiliser les mises à jour et permettre une convergence douce sans oscillations inutiles.','Sa norme diminue progressivement pour s\'approcher de zéro, ce qui réduit naturellement l\'amplitude des mises à jour des poids.','D'),(117,2,'Quelle est la spécificité de l\'optimiseur Adam par rapport à la descente de gradient classique ?','Il utilise une vitesse de calcul fixe qui ignore les gradients passés pour se concentrer uniquement sur la pente de l\'étape courante.','Il calcule des taux d\'apprentissage adaptatifs pour chaque paramètre en utilisant les moments d\'ordre un et deux des gradients.','Il applique une méthode d\'optimisation sans dérivées en testant des variations aléatoires autour de la configuration de paramètres.','Il garantit de trouver le minimum global de n\'importe quelle fonction de coût non convexe en une seule époque d\'entraînement.','B'),(118,2,'Que signifie mathématiquement l\'obtention d\'un gradient nul $\\nabla L = 0$ lors de l\'apprentissage ?','Que le réseau de neurones a cessé de fonctionner car toutes ses fonctions d\'activation ont atteint leur valeur maximale possible.','Que l\'algorithme a trouvé la configuration de poids idéale qui produit une erreur de prédiction strictement égale à zéro.','Que le modèle a fini de lire l\'intégralité des exemples de données d\'apprentissage prévus pour l\'époque d\'entraînement en cours.','Que les paramètres se trouvent sur un point stationnaire, qui peut être un minimum, un maximum ou un point de selle.','D'),(119,2,'Qu\'est-ce qu\'un point de selle (saddle point) sur la surface de la fonction de coût ?','Un point où la fonction d\'activation produit des valeurs identiques pour tous les neurones d\'une même couche cachée.','Un point stationnaire où la pente est nulle, mais qui est un minimum pour certaines directions et un maximum pour d\'autres.','Un point d\'intersection où les courbes d\'apprentissage de l\'ensemble d\'entraînement et de l\'ensemble de validation se croisent.','Un point de divergence où la fonction de coût prend une valeur infinie en raison d\'une division par zéro dans les calculs.','B'),(120,2,'Quel est l\'objectif premier d\'une fonction de coût (loss function) dans un réseau de neurones ?','Calculer efficacement le gradient de la fonction de coût par rapport à chacun des poids et biais du réseau de neurones.','Propager les données d\'entrée à travers les différentes couches du réseau pour obtenir une prédiction sur la couche de sortie.','Modifier la structure même du réseau en ajoutant ou en supprimant des couches de neurones en fonction de l\'erreur observée.','Normaliser les activations de chaque couche pour s\'assurer que leur valeur moyenne reste stable d\'une époque à la suivante.','A'),(121,2,'Dans quel sens les informations d\'erreur circulent-elles durant l\'étape de rétropropagation ?','De la couche d\'entrée vers la couche de sortie, en traversant séquentiellement toutes les couches cachées du réseau de neurones.','De manière circulaire au sein de chaque couche cachée individuellement sans jamais traverser les frontières inter-couches.','De la couche de sortie vers la couche d\'entrée, en évaluant l\'influence de chaque neurone sur l\'erreur globale constatée.','De manière purement aléatoire entre les neurones pour distribuer l\'erreur de façon homogène sur l\'ensemble du réseau.','C'),(122,2,'Pourquoi la règle de dérivation en chaîne (Chain Rule) est-elle essentielle pour la rétropropagation ?','Elle permet d\'exprimer la dérivée d\'une fonction composée comme le produit des dérivées de ses fonctions composantes individuelles.','Elle sert à sommer les erreurs de chaque neurone pour obtenir une valeur globale utilisable par la fonction d\'activation.','Elle transforme des équations différentielles complexes en simples opérations d\'addition pour réduire le temps de calcul.','Elle permet de calculer la moyenne des gradients sur plusieurs époques pour stabiliser la trajectoire de l\'optimiseur.','A'),(123,2,'Quel est le rôle des dérivées partielles dans l\'évaluation de l\'erreur d\'un réseau ?','Elles indiquent comment l\'erreur globale est influencée par la modification d\'un seul paramètre spécifique du réseau de neurones.','Elles mesurent la vitesse d\'exécution de la propagation avant par rapport à la vitesse de calcul de la rétropropagation.','Elles calculent le nombre de neurones actifs au sein d\'une couche cachée pour optimiser l\'utilisation de la mémoire vive.','Elles déterminent la valeur cible exacte qu\'un neurone aurait dû produire pour obtenir une erreur de prédiction de zéro.','B'),(124,2,'Comment la rétropropagation détermine-t-elle la contribution d\'un poids interne à l\'erreur globale ?','En soustrayant la valeur d\'activation du neurone d\'entrée de la valeur de l\'erreur finale mesurée sur la couche de sortie du réseau.','En divisant l\'erreur totale par le nombre de connexions synaptiques actives présentes dans l\'architecture globale du réseau.','En appliquant la règle de dérivation en chaîne depuis la fonction de coût jusqu\'au poids visé en passant par ses connexions.','En mesurant directement la variation de la fonction de coût lorsqu\'on remplace la valeur de ce poids par une valeur aléatoire.','C'),(125,2,'Qu\'est-ce que l\'erreur locale (notée $\\delta$) d\'un neurone spécifique lors de la rétropropagation ?','La différence absolue entre le poids moyen de la couche associée et le poids spécifique du neurone évalué à ce moment-là.','Le pourcentage d\'échantillons d\'apprentissage pour lesquels le neurone a produit une valeur d\'activation erronée ou hors limites.','L\'écart de performance mesuré uniquement sur la couche d\'entrée lorsque le processus de rétropropagation est terminé.','La dérivée partielle de la fonction de coût par rapport à l\'entrée nette (somme pondérée non activée) de ce neurone.','D'),(126,2,'Pourquoi la phase de rétropropagation nécessite-t-elle de conserver les valeurs de la propagation avant (forward pass) ?','Pour pouvoir réinitialiser les poids à leur valeur d\'origine si l\'erreur calculée s\'avère plus élevée que prévu initialement.','Pour utiliser les valeurs d\'activation de chaque neurone qui interviennent directement dans le calcul des dérivées partielles.','Pour comparer à chaque étape les sorties intermédiaires réelles avec des cibles idéales prédéfinies pour chaque couche.','Pour s\'assurer que le réseau de neurones fonctionne de manière déterministe et n\'altère pas la structure des données d\'entrée.','B'),(127,2,'Quel est le problème de la disparition du gradient (vanishing gradient) lors de la rétropropagation ?','Les gradients deviennent si petits dans les premières couches que les poids de ces couches ne se mettent presque plus à jour.','Les gradients oscillent de manière imprévisible entre des valeurs positives et négatives très élevées à chaque étape.','Le gradient de la fonction de coût s\'annule complètement sur la couche de sortie alors qu\'il reste grand dans les autres couches.','Les poids du réseau diminuent jusqu\'à atteindre zéro, ce qui supprime toutes les connexions actives au sein du modèle.','C'),(128,2,'Qu\'est-ce que le problème de l\'explosion du gradient (exploding gradient) dans un réseau profond ?','Une accumulation d\'erreurs d\'arrondi numérique qui conduit à des valeurs de prédiction infinies dès la première couche cachée.','Une augmentation exponentielle de la fonction de coût qui empêche le réseau de calculer la moindre erreur sur la sortie.','Une situation où les gradients s\'accumulent et deviennent extrêmement grands, provoquant des mises à jour instables des poids.','Une activation simultanée de tous les neurones du réseau qui sature les capacités de calcul de l\'infrastructure matérielle.','D'),(129,2,'Comment le choix de la fonction d\'activation affecte-t-il la rétropropagation ?','La dérivée de la fonction d\'activation est un multiplicateur clé dans le calcul du gradient de l\'erreur pour chaque neurone.','La fonction d\'activation détermine si la rétropropagation doit être effectuée de manière stochastique ou par mini-lots d\'exemples.','La fonction d\'activation élimine le besoin d\'utiliser la règle de dérivation en chaîne en rendant tous les gradients constants.','Le choix de la fonction d\'activation modifie le nombre d\'époques d\'apprentissage requises pour charger les données d\'entrée.','A'),(130,2,'Quel rôle joue le graphe de calcul computationnel dans l\'implémentation de la rétropropagation ?','Il sert à visualiser l\'architecture physique du réseau de neurones pour aider les ingénieurs à concevoir de meilleurs modèles.','Il permet d\'enregistrer l\'ordre d\'exécution des opérations mathématiques pour calculer automatiquement les dérivées partielles.','Il stocke les étiquettes cibles de manière sécurisée pour éviter les fuites de données entre l\'apprentissage et le test.','Il réduit la dimensionnalité des données d\'entrée pour accélérer la vitesse de calcul de la propagation avant du réseau.','C'),(131,2,'Quelle est la complexité temporelle théorique de l\'algorithme de rétropropagation ?','Elle est cubique par rapport au nombre total de paramètres du réseau de neurones en raison des inversions matricielles requises.','Elle est exponentielle par rapport au nombre de couches cachées car les calculs de gradients se multiplient à chaque étape.','Elle est logarithmique par rapport au nombre d\'échantillons d\'apprentissage car l\'algorithme procède par élimination directe.','Elle est linéaire par rapport au nombre total de poids et de biais du réseau, ce qui la rend extrêmement efficace.','D'),(132,2,'En quoi la rétropropagation dépend-elle mathématiquement de la fonction de coût choisie ?','Le calcul du gradient initial à la couche de sortie commence par la dérivée de la fonction de coût par rapport aux prédictions.','La fonction de coût détermine si l\'on doit utiliser des fonctions d\'activation linéaires ou non linéaires dans le réseau.','La fonction de coût définit la vitesse à laquelle les données d\'entrée traversent le réseau durant la propagation avant.','Le choix de la fonction de coût modifie la dimension spatiale des matrices de poids utilisées dans les couches cachées.','A'),(133,2,'Quel est l\'effet direct de la rétropropagation sur les biais d\'un réseau de neurones ?','Elle les met à jour en ajoutant la valeur de l\'activation moyenne de la couche précédente pour chaque neurone du réseau.','Elle ajuste les biais en calculant leur gradient, qui dépend de l\'erreur locale du neurone mais pas de l\'activation d\'entrée.','Elle les réinitialise à des valeurs aléatoires chaque fois que le gradient d\'un poids associé s\'approche de la valeur zéro.','Elle les maintient constants tout au long de l\'apprentissage en déléguant l\'ajustement d\'erreur uniquement aux poids.','B'),(134,2,'Quelle est la différence entre la rétropropagation et la différenciation numérique pour calculer les gradients ?','La rétropropagation utilise des approximations par différences finies tandis que la différenciation numérique évalue les limites à la main.','La rétropropagation est beaucoup plus lente car elle nécessite de recalculer la propagation avant pour chaque paramètre isolé.','La rétropropagation calcule les gradients de manière exacte et analytique en un seul passage arrière, ce qui est très rapide.','La rétropropagation ne s\'applique qu\'aux fonctions convexes tandis que la différenciation numérique gère toutes les fonctions.','C'),(135,2,'Quelle est l\'équation fondamentale de l\'erreur locale (notée $\\delta^L$) pour la couche de sortie $L$ avec une activation $a^L$ ?','$\\delta^L = \\nabla_a C \\odot \\sigma\'(z^L)$ où $\\nabla_a C$ est la dérivée du coût et $\\sigma\'$ est la dérivée de l\'activation.','$\\delta^L = (a^L - y) + w^L \\cdot z^L$ où $y$ est la cible et $w^L$ est la matrice de poids associée à la dernière couche.','$\\delta^L = \\sum (w^L \\cdot a^{L-1} + b^L)$ représentant la somme pondérée des activations reçues par la couche finale.','$\\delta^L = \\sigma(z^L) \\cdot (1 - \\sigma(z^L))$ qui correspond uniquement à la dérivée locale de la fonction d\'activation.','A'),(136,2,'Pour une fonction de coût MSE pour un seul exemple $C = \\frac{1}{2} (a^L - y)^2$, quelle est la dérivée partielle $\\frac{\\partial C}{\\partial a^L}$ ?','$\\frac{\\partial C}{\\partial a^L} = \\frac{1}{2} (a^L - y)$ qui intègre le facteur d\'échelle constant de l\'erreur quadratique.','$\\frac{\\partial C}{\\partial a^L} = a^L - y$ qui représente la différence directe entre la valeur prédite et la valeur cible.','$\\frac{\\partial C}{\\partial a^L} = (a^L - y)^2$ qui conserve la puissance carrée pour pénaliser plus fortement les grands écarts.','$\\frac{\\partial C}{\\partial a^L} = 1$ car la dérivée d\'une différence linéaire par rapport à elle-même est toujours constante.','B'),(137,2,'Si la fonction d\'activation d\'un neurone est la Sigmoïde $\\sigma(z)$, quelle est l\'expression exacte de sa dérivée $\\sigma\'(z)$ ?','$\\sigma\'(z) = \\sigma(z) \\cdot (1 + \\sigma(z))$ qui augmente de manière exponentielle avec la valeur de l\'activation brute.','$\\sigma\'(z) = 1 - \\sigma(z)$ qui représente la probabilité complémentaire associée à la sortie du neurone évalué.','$\\sigma\'(z) = \\sigma(z) \\cdot (1 - \\sigma(z))$ qui s\'exprime directement en fonction de la valeur d\'activation existante.','$\\sigma\'(z) = \\sigma(z)^2$ qui applique une transformation quadratique simple à l\'activation obtenue lors de la passe avant.','C'),(138,2,'Dans la formulation matricielle de la rétropropagation, que représente l\'opérateur produit de Hadamard (noté $\\odot$) ?','Le produit matriciel standard où l\'on multiplie les lignes de la première matrice par les colonnes de la seconde.','Le produit vectoriel de deux espaces de dimension différente pour projeter les gradients dans un nouvel espace de coordonnées.','La division de chaque terme d\'une matrice par le terme correspondant d\'une autre matrice de même dimension géométrique.','La multiplication élément par élément (pixel par pixel) de deux vecteurs ou matrices ayant exactement les mêmes dimensions.','D'),(139,2,'Quelle est la formule exacte du gradient du coût par rapport à un poids spécifique $w_{jk}^l$ connectant le neurone $k$ au neurone $j$ ?','$\\frac{\\partial C}{\\partial w_{jk}^l} = w_{jk}^l \\cdot a_k^{l-1}$ qui multiplie la valeur actuelle du poids par l\'activation d\'entrée.','$\\frac{\\partial C}{\\partial w_{jk}^l} = a_k^{l-1} \\cdot \\delta_j^l$ qui est le produit de l\'activation du neurone d\'entrée et de l\'erreur locale.','$\\frac{\\partial C}{\\partial w_{jk}^l} = \\delta_j^l \\cdot \\sigma\'(z_j^l)$ qui combine l\'erreur locale et la dérivée de l\'activation du neurone.','$\\frac{\\partial C}{\\partial w_{jk}^l} = b_j^l \\cdot a_k^{l-1}$ qui utilise la valeur du biais de la couche courante comme facteur d\'échelle.','B'),(140,2,'Quelle est la formule exacte du gradient du coût par rapport au biais $b_j^l$ d\'un neurone donné lors de la rétropropagation ?','$\\frac{\\partial C}{\\partial b_j^l} = (a_j^l - y_j)$ qui correspond à l\'écart de prédiction brut mesuré sur le neurone de sortie.','$\\frac{\\partial C}{\\partial b_j^l} = w_{jk}^l \\cdot \\delta_j^l$ qui intègre l\'influence des poids connectés au neurone dans le calcul.','$\\frac{\\partial C}{\\partial b_j^l} = b_j^l \\cdot \\sigma\'(z_j^l)$ qui applique la dérivée d\'activation directement sur la valeur du biais.','$\\frac{\\partial C}{\\partial b_j^l} = \\delta_j^l$ signifiant que le gradient du biais est exactement égal à l\'erreur locale de ce neurone.','D'),(141,2,'Comment exprime-t-on l\'erreur locale $\\delta^l$ d\'une couche cachée $l$ en fonction de l\'erreur locale $\\delta^{l+1}$ de la couche suivante ?','$\\delta^l = \\delta^{l+1} \\cdot (w^{l+1})^T \\odot a^l$ qui utilise les activations de la couche courante comme facteur multiplicatif.','$\\delta^l = (w^{l+1})^T \\cdot \\delta^{l+1} + b^l$ qui ajoute la valeur du biais local pour décaler la valeur du gradient propagé.','$\\delta^l = ((w^{l+1})^T \\cdot \\delta^{l+1}) \\odot \\sigma\'(z^l)$ qui projette l\'erreur en arrière et applique la dérivée locale.','$\\delta^l = \\sigma\'(z^l) \\cdot \\delta^{l+1}$ qui applique uniquement la dérivée locale de la fonction d\'activation de la couche.','C'),(142,2,'Quel est le rôle mathématique de la matrice jacobienne dans le calcul de la rétropropagation multidimensionnelle ?','Elle rassemble toutes les dérivées partielles d\'une fonction vectorielle pour transformer les gradients d\'une couche à l\'autre.','Elle calcule la valeur d\'erreur globale moyenne en sommant toutes les contributions individuelles des neurones du réseau.','Elle inverse les valeurs des poids synaptiques pour s\'assurer que le réseau de neurones converge de façon stable.','Elle normalise les données de sortie pour les aligner sur une distribution statistique gaussienne centrée et réduite.','A'),(143,2,'Pour un neurone individuel, comment calcule-t-on la dérivée partielle de la somme pondérée $z$ par rapport à une entrée $a$ ?','$\\frac{\\partial z}{\\partial a} = b$ qui montre que la variation de la somme pondérée dépend uniquement de la valeur du biais.','$\\frac{\\partial z}{\\partial a} = a \\cdot w$ qui combine de manière quadratique la valeur d\'entrée et le poids associé au neurone.','$\\frac{\\partial z}{\\partial a} = w$ indiquant que le taux de variation de la somme pondérée par rapport à une entrée est le poids associé.','$\\frac{\\partial z}{\\partial a} = 1$ car l\'entrée est considérée comme une variable indépendante lors du calcul de la dérivée.','C'),(144,2,'Comment la fonction d\'activation ReLU, définie par $f(x) = \\max(0, x)$, se comporte-t-elle lors du calcul détaillé des gradients ?','Sa dérivée est égale à $1$ pour toutes les entrées strictement positives et égale à $0$ pour toutes les entrées négatives.','Sa dérivée est égale à la valeur d\'entrée elle-même pour les valeurs positives et égale à zéro pour les valeurs négatives.','Sa dérivée est constante et égale à $0.5$ sur tout son domaine de définition pour simplifier les calculs de rétropropagation.','Sa dérivée tend vers l\'infini lorsque la valeur d\'entrée s\'approche de zéro, ce qui provoque des instabilités numériques.','A'),(145,2,'Dans un graphe de calcul computationnel, comment un nœud d\'addition ($+$) distribue-t-il le gradient venant de l\'amont ?','Il multiplie le gradient amont par la valeur de l\'autre entrée du nœud avant de le transmettre aux branches aval.','Il divise le gradient amont de manière équitable entre toutes les branches d\'entrée connectées à ce nœud d\'addition.','Il annule le gradient amont pour toutes les branches dont la valeur d\'entrée associée est strictement inférieure à zéro.','Il transmet le gradient amont de manière identique et sans modification à chacune des branches d\'entrée du nœud.','D'),(146,2,'Dans un graphe de calcul computationnel, comment un nœud de multiplication ($*$) distribue-t-il le gradient venant de l\'amont ?','Il transmet le gradient amont à chaque branche après l\'avoir élevé au carré pour conserver l\'échelle des valeurs.','Il multiplie le gradient amont par la valeur de l\'autre entrée pour obtenir le gradient par rapport à chaque variable.','Il divise le gradient amont par la somme de toutes les entrées du nœud de multiplication pour équilibrer le flux.','Il transmet le gradient amont uniquement à la branche d\'entrée qui possède la valeur numérique la plus élevée.','B'),(147,2,'Quel est l\'impact d\'une initialisation avec des poids trop grands sur le calcul du gradient de la fonction d\'activation Sigmoïde ?','Elle augmente la valeur du gradient, ce qui provoque une accélération incontrôlée de la mise à jour des poids du réseau.','Elle force le gradient à osciller de manière instable entre des valeurs positives et négatives extrêmement élevées.','Elle élimine le biais en le réduisant à zéro, ce qui empêche le neurone de se décaler lors de la propagation avant.','Elle sature la Sigmoïde à $0$ ou $1$, ce qui produit une dérivée presque nulle et bloque complètement l\'apprentissage.','D'),(148,2,'Comment l\'erreur calculée sur la fonction de coût se propage-t-elle vers les couches les plus proches de l\'entrée du réseau ?','Elle est augmentée de manière uniforme à chaque couche traversée grâce à l\'ajout systématique des valeurs de biais locaux.','Elle s\'atténue ou s\'amplifie par multiplication successive des matrices de poids et des dérivées des fonctions d\'activation.','Elle est divisée par le nombre total de neurones présents dans chaque couche pour éviter la saturation des gradients.','Elle est conservée à l\'identique pour garantir que chaque couche du réseau apprenne exactement à la même vitesse.','B'),(149,2,'Soit un flux de calcul $x \\to y \\to z$, comment s\'exprime la dérivée globale $\\frac{\\partial z}{\\partial x}$ selon la règle de dérivation en chaîne ?','$\\frac{\\partial z}{\\partial x} = \\frac{\\partial z}{\\partial y} \\cdot \\frac{\\partial y}{\\partial x}$ qui est le produit exact des dérivées partielles intermédiaires.','$\\frac{\\partial z}{\\partial x} = \\frac{\\partial z}{\\partial y} + \\frac{\\partial y}{\\partial x}$ qui additionne les variations locales pour obtenir le taux global.','$\\frac{\\partial z}{\\partial x} = \\frac{\\partial y}{\\partial x} / \\frac{\\partial z}{\\partial y}$ qui évalue le rapport de proportionnalité inverse entre les variables.','$\\frac{\\partial z}{\\partial x} = (\\frac{\\partial z}{\\partial y})^2 \\cdot \\frac{\\partial y}{\\partial x}$ qui applique une pondération non linéaire à la première dérivée.','A');
/*!40000 ALTER TABLE `course_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `svg_icon` text DEFAULT NULL,
  `enrollment_key` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `eval_deadline` date DEFAULT NULL,
  `exam_duration_minutes` int(11) NOT NULL DEFAULT 90,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `cover_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_id` (`module_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `fk_courses_created_by` (`created_by`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_courses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,2,24,NULL,'Philosophie des Algorithmes','Une étude critique de l\'impact éthique et des fondements logiques de la machine moderne.','<svg class=\"w-12 h-12 text-[#004B23]\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253\"></path></svg>','ALGO2026','2026-06-14 11:58:51',NULL,NULL,NULL,90,1,'013e1cf7bdbc5f2e1cc343644391c359.jpg'),(2,2,24,NULL,'Projets Machine learning','','<svg class=\"w-12 h-12 text-[#004B23]\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253\"></path></svg>','ML2026','2026-06-14 16:54:12','2026-06-21','2026-06-25','2026-06-22',60,1,'b364678a47fdd32e5a3a99b514d6eb24.png'),(3,2,2,NULL,'Programmation Web','','<svg class=\"w-12 h-12 text-[#004B23]\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253\"></path></svg>','Web2026','2026-06-14 16:54:37',NULL,NULL,NULL,90,1,NULL);
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verification_tokens`
--

DROP TABLE IF EXISTS `email_verification_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_email_verify_hash` (`token_hash`),
  CONSTRAINT `email_verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verification_tokens`
--

LOCK TABLES `email_verification_tokens` WRITE;
/*!40000 ALTER TABLE `email_verification_tokens` DISABLE KEYS */;
INSERT INTO `email_verification_tokens` VALUES (1,22,'c23e36265f6823e14e6604418ab2bb2f6e0fc554f6f5aa6fb4fd7a5a17e0c5b6','2026-06-17 13:40:02','2026-06-15 14:40:41','2026-06-15 13:40:02'),(2,23,'bc5ff8be00fd59205c97d2f2388fb3e2451ff409adfa58abc70272eb57281dd1','2026-06-18 11:27:34','2026-06-16 12:27:58','2026-06-16 11:27:34');
/*!40000 ALTER TABLE `email_verification_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `progress_percent` int(11) DEFAULT 0,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_lesson_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_course` (`student_id`,`course_id`),
  KEY `course_id` (`course_id`),
  KEY `fk_enrollment_last_lesson` (`last_lesson_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enrollment_last_lesson` FOREIGN KEY (`last_lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (1,4,1,33,'2026-06-14 12:08:27',NULL),(3,5,1,0,'2026-06-14 12:09:10',NULL),(4,12,1,0,'2026-06-14 12:13:31',NULL),(8,9,1,0,'2026-06-14 12:14:26',NULL),(9,14,1,0,'2026-06-14 12:15:35',NULL),(11,20,1,100,'2026-06-14 12:36:21',6),(12,21,1,100,'2026-06-15 03:37:24',NULL),(13,22,1,100,'2026-06-15 14:13:34',6),(14,20,2,100,'2026-06-16 01:44:30',11),(15,22,2,100,'2026-06-16 14:15:11',11),(16,3,1,0,'2026-06-21 13:41:40',6),(17,20,3,0,'2026-06-23 22:00:22',NULL);
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_sessions`
--

DROP TABLE IF EXISTS `exam_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `started_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `submitted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `idx_exam_session_student_course` (`student_id`,`course_id`,`submitted`),
  CONSTRAINT `exam_sessions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_sessions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_sessions`
--

LOCK TABLES `exam_sessions` WRITE;
/*!40000 ALTER TABLE `exam_sessions` DISABLE KEYS */;
INSERT INTO `exam_sessions` VALUES (1,20,1,'2026-06-16 01:48:11','2026-06-16 03:18:11',0,'2026-06-16 01:48:11'),(2,22,1,'2026-06-16 11:05:47','2026-06-16 12:35:47',0,'2026-06-16 11:05:47'),(3,20,2,'2026-06-18 02:28:14','2026-06-18 03:58:14',1,'2026-06-18 02:28:14'),(4,20,2,'2026-06-18 02:30:50','2026-06-18 04:00:50',0,'2026-06-18 02:30:50'),(5,20,2,'2026-06-20 07:37:07','2026-06-20 09:07:07',0,'2026-06-20 07:37:07');
/*!40000 ALTER TABLE `exam_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_comments`
--

DROP TABLE IF EXISTS `lesson_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_reply` text DEFAULT NULL,
  `replied_by` int(11) DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `lesson_comments_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `lesson_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_comments`
--

LOCK TABLES `lesson_comments` WRITE;
/*!40000 ALTER TABLE `lesson_comments` DISABLE KEYS */;
INSERT INTO `lesson_comments` VALUES (1,6,21,'C\'est quoi un Ratiocinator?',NULL,'2026-06-15 03:38:17',0,NULL,NULL,NULL),(2,8,21,'Comment modeliser un ruban infini?',NULL,'2026-06-15 03:46:36',0,NULL,NULL,NULL),(3,10,21,'Comment mitiguer les risques lies a l\'IA ?',NULL,'2026-06-15 03:47:11',0,NULL,NULL,NULL),(4,11,22,'Interesting',NULL,'2026-06-21 16:57:03',0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `lesson_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_progress`
--

DROP TABLE IF EXISTS `lesson_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `content_consumed` tinyint(1) NOT NULL DEFAULT 0,
  `score` int(11) DEFAULT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_lesson` (`student_id`,`lesson_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `lesson_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_progress`
--

LOCK TABLES `lesson_progress` WRITE;
/*!40000 ALTER TABLE `lesson_progress` DISABLE KEYS */;
INSERT INTO `lesson_progress` VALUES (5,20,6,1,0,100,'2026-06-14 12:48:05'),(6,20,7,1,0,100,'2026-06-14 12:51:16'),(7,20,8,1,0,100,'2026-06-14 13:05:55'),(8,20,10,1,0,100,'2026-06-14 19:24:36'),(11,21,6,1,0,100,'2026-06-15 03:37:41'),(12,21,7,1,0,100,'2026-06-15 03:38:38'),(13,21,8,1,0,100,'2026-06-15 03:38:49'),(14,21,10,1,0,100,'2026-06-15 03:45:46'),(15,22,6,1,1,100,'2026-06-16 10:38:22'),(17,22,7,1,1,100,'2026-06-16 10:58:05'),(19,22,8,1,1,100,'2026-06-16 11:03:05'),(21,22,10,1,1,100,'2026-06-16 11:03:34'),(23,20,11,1,1,57,'2026-06-16 13:56:40'),(25,22,11,1,1,33,'2026-06-21 16:56:34');
/*!40000 ALTER TABLE `lesson_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_question_answers`
--

DROP TABLE IF EXISTS `lesson_question_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_question_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answered_correctly` tinyint(1) NOT NULL DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `selected_option` varchar(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_question` (`student_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `lesson_question_answers_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_question_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `lesson_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_question_answers`
--

LOCK TABLES `lesson_question_answers` WRITE;
/*!40000 ALTER TABLE `lesson_question_answers` DISABLE KEYS */;
INSERT INTO `lesson_question_answers` VALUES (1,21,4,1,'2026-06-15 03:38:38',NULL),(2,22,4,1,'2026-06-16 10:58:05',NULL),(3,20,5,1,'2026-06-16 12:28:19',NULL),(4,20,6,1,'2026-06-16 12:28:32',NULL),(5,20,7,1,'2026-06-16 12:28:51',NULL),(6,20,8,1,'2026-06-16 13:52:31',NULL),(7,20,9,1,'2026-06-16 13:52:37',NULL),(8,20,10,0,'2026-06-16 13:52:44',NULL),(9,20,11,1,'2026-06-16 13:53:44',NULL),(10,20,12,0,'2026-06-16 13:53:57',NULL),(11,20,13,1,'2026-06-16 13:54:09',NULL),(12,20,14,1,'2026-06-16 13:54:20',NULL),(13,20,15,0,'2026-06-16 13:54:26',NULL),(14,20,16,1,'2026-06-16 13:54:32',NULL),(15,20,17,0,'2026-06-16 13:54:38',NULL),(16,20,18,1,'2026-06-16 13:54:44',NULL),(17,20,19,0,'2026-06-16 13:54:52',NULL),(18,20,20,1,'2026-06-16 13:54:58',NULL),(19,20,21,0,'2026-06-16 13:55:05',NULL),(20,20,22,0,'2026-06-16 13:55:10',NULL),(21,20,23,1,'2026-06-16 13:55:18',NULL),(22,20,24,0,'2026-06-16 13:55:24',NULL),(23,20,25,1,'2026-06-16 13:55:30',NULL),(24,20,26,1,'2026-06-16 13:55:38',NULL),(25,20,27,1,'2026-06-16 13:55:45',NULL),(26,20,28,0,'2026-06-16 13:55:51',NULL),(27,20,29,1,'2026-06-16 13:55:57',NULL),(28,20,30,1,'2026-06-16 13:56:10',NULL),(29,20,31,0,'2026-06-16 13:56:17',NULL),(30,20,32,0,'2026-06-16 13:56:23',NULL),(31,20,33,0,'2026-06-16 13:56:33',NULL),(32,20,34,0,'2026-06-16 13:56:40',NULL),(33,22,5,1,'2026-06-21 16:51:38',NULL),(34,22,6,0,'2026-06-21 16:51:44',NULL),(35,22,7,1,'2026-06-21 16:51:51',NULL),(36,22,8,0,'2026-06-21 16:51:56',NULL),(37,22,9,0,'2026-06-21 16:52:02',NULL),(38,22,10,1,'2026-06-21 16:52:07',NULL),(39,22,11,0,'2026-06-21 16:52:18',NULL),(40,22,12,0,'2026-06-21 16:52:26',NULL),(41,22,13,0,'2026-06-21 16:52:35',NULL),(42,22,14,1,'2026-06-21 16:52:40',NULL),(43,22,15,0,'2026-06-21 16:52:45',NULL),(44,22,16,1,'2026-06-21 16:52:51',NULL),(45,22,17,0,'2026-06-21 16:52:57',NULL),(46,22,18,1,'2026-06-21 16:53:03',NULL),(47,22,19,0,'2026-06-21 16:53:08',NULL),(48,22,20,0,'2026-06-21 16:53:14',NULL),(49,22,21,1,'2026-06-21 16:53:20',NULL),(50,22,22,0,'2026-06-21 16:53:26',NULL),(51,22,23,0,'2026-06-21 16:53:32',NULL),(52,22,24,0,'2026-06-21 16:53:40',NULL),(53,22,25,0,'2026-06-21 16:53:46',NULL),(54,22,26,1,'2026-06-21 16:53:52',NULL),(55,22,27,0,'2026-06-21 16:53:58',NULL),(56,22,28,0,'2026-06-21 16:54:04',NULL),(57,22,29,1,'2026-06-21 16:54:10',NULL),(58,22,30,0,'2026-06-21 16:54:16',NULL),(59,22,31,0,'2026-06-21 16:56:15',NULL),(60,22,32,0,'2026-06-21 16:56:21',NULL),(61,22,33,1,'2026-06-21 16:56:27',NULL),(62,22,34,0,'2026-06-21 16:56:34',NULL);
/*!40000 ALTER TABLE `lesson_question_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_questions`
--

DROP TABLE IF EXISTS `lesson_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_option` char(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `lesson_questions_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_questions`
--

LOCK TABLES `lesson_questions` WRITE;
/*!40000 ALTER TABLE `lesson_questions` DISABLE KEYS */;
INSERT INTO `lesson_questions` VALUES (4,7,'What is CSS ?','Cascading Style Sheets','CSS','John Shapiro','Cascade Style shape','A'),(5,11,'Dans un réseau de neurones, qu\'est-ce qu\'un neurone représente mathématiquement ?','Une fonction booléenne','Un nombre réel entre 0 et 1 appelé activation','Un vecteur de poids','Une matrice de transformation','B'),(6,11,'Quel est le rôle de la fonction d\'activation sigmoïde dans un réseau de neurones ?','Normaliser les poids entre -1 et 1','Écraser toute valeur réelle dans l\'intervalle (0, 1)','Calculer la dérivée du coût','Initialiser les biais à zéro','B'),(7,11,'Dans l\'exemple de reconnaissance de chiffres manuscrits (MNIST), combien de neurones contient la couche d\'entrée ?','28','256','784','1024','C'),(8,11,'Que représentent les poids (weights) dans la connexion entre deux neurones ?','La valeur d\'activation du neurone suivant','Des coefficients qui pondèrent l\'influence de chaque activation de la couche précédente','Le biais ajouté après la somme pondérée','La dérivée de la fonction de coût','B'),(9,11,'À quoi sert le biais (bias) d\'un neurone ?','À normaliser les activations','À décaler le seuil à partir duquel le neurone s\'active significativement','À réduire le surapprentissage','À initialiser les poids aléatoirement','B'),(10,11,'Si un réseau possède 784 entrées, une couche cachée de 16 neurones, une autre de 16, et 10 sorties, combien de paramètres (poids + biais) contient-il au total ?','12 960','13 002','12 800','13 500','B'),(11,11,'Que signifie l\'activation élevée d\'un neurone de la couche de sortie correspondant au chiffre \'3\' ?','Le réseau est incertain','Le réseau pense que l\'image représente le chiffre 3','Le coût est minimal','Le biais de ce neurone est nul','B'),(12,11,'Comment 3Blue1Brown décrit-il intuitivement les neurones des couches cachées dans la reconnaissance de chiffres ?','Comme des détecteurs de couleurs','Comme des détecteurs de boucles, de lignes et de courbes spécifiques','Comme des classificateurs binaires','Comme des fonctions de hachage','B'),(13,11,'La fonction de coût (cost function) pour un exemple d\'entraînement est définie comme :','La somme des activations de sortie','La somme des carrés des différences entre les sorties prédites et les valeurs cibles','Le produit des poids de toutes les couches','La valeur maximale parmi les activations de sortie','B'),(14,11,'Que mesure la fonction de coût moyenne sur l\'ensemble d\'entraînement ?','La vitesse d\'apprentissage','La performance globale du réseau sur tous les exemples — plus elle est basse, mieux c\'est','Le nombre de couches cachées optimal','Le taux de dropout','B'),(15,11,'Quel est l\'objectif principal de la descente de gradient (gradient descent) ?','Augmenter la fonction de coût','Trouver les poids et biais qui minimisent la fonction de coût','Maximiser le nombre de neurones activés','Réduire le nombre de couches','B'),(16,11,'Intuitivement, que représente le gradient de la fonction de coût ?','La valeur actuelle du coût','La direction de montée la plus raide dans l\'espace des paramètres','La moyenne des activations','Le vecteur des biais','B'),(17,11,'Pour minimiser le coût, dans quelle direction se déplace-t-on par rapport au gradient ?','Dans le sens du gradient (gradient ascent)','Dans le sens opposé au gradient (gradient descent)','Perpendiculairement au gradient','Aléatoirement','B'),(18,11,'Pourquoi utilise-t-on la descente de gradient stochastique (SGD) plutôt que la descente de gradient classique ?','Parce qu\'elle converge vers un minimum global garanti','Parce qu\'utiliser un mini-batch est beaucoup plus rapide computationnellement qu\'utiliser tout le dataset','Parce qu\'elle ne nécessite pas de calculer des dérivées','Parce que le coût est alors nul','B'),(19,11,'Que signifie le terme \'taux d\'apprentissage\' (learning rate) dans la descente de gradient ?','Le nombre d\'époques d\'entraînement','La taille des pas effectués dans la direction opposée au gradient','Le nombre de neurones par couche','La valeur initiale des poids','B'),(20,11,'La rétropropagation (backpropagation) est un algorithme permettant de :','Initialiser les poids d\'un réseau','Calculer efficacement le gradient de la fonction de coût par rapport à tous les poids et biais','Réduire le nombre de couches d\'un réseau','Convertir les activations en probabilités','B'),(21,11,'Dans la rétropropagation, quel théorème mathématique fondamental est utilisé pour propager les gradients de couche en couche ?','Le théorème de Bayes','La règle de la chaîne (chain rule) de dérivation','Le théorème de Taylor','Le lemme de Fatou','B'),(22,11,'Quelle notation 3Blue1Brown utilise-t-il pour désigner l\'activation du k-ième neurone de la couche L ?','w(L,k)','b(L,k)','a(L,k)','C(L,k)','C'),(23,11,'Dans la notation de la série, que représente C₀ ?','Le coût moyen sur tout le dataset','La fonction de coût pour un seul exemple d\'entraînement','La couche de sortie','Le nombre de classes','B'),(24,11,'Qu\'est-ce que ∂C₀/∂w(L) mesure dans le contexte de la rétropropagation ?','La valeur du poids w(L) après mise à jour','La sensibilité du coût à une légère modification du poids w(L)','L\'activation du neurone L','Le biais optimal de la couche L','B'),(25,11,'Dans l\'expression z(L) = w(L)·a(L-1) + b(L), que représente z(L) ?','L\'activation après la fonction sigmoïde','La somme pondérée avant application de la fonction d\'activation','Le gradient du coût','La valeur cible (label)','B'),(26,11,'Selon la règle de la chaîne appliquée à la rétropropagation, ∂C₀/∂w(L) est égal à :','∂z(L)/∂w(L) uniquement','(∂C₀/∂a(L)) · (∂a(L)/∂z(L)) · (∂z(L)/∂w(L))','∂C₀/∂a(L) + ∂a(L)/∂z(L)','∂C₀/∂b(L) · ∂b(L)/∂w(L)','B'),(27,11,'Que vaut ∂z(L)/∂w(L) dans la dérivation de la rétropropagation ?','σ(z(L))','a(L-1) — l\'activation de la couche précédente','b(L)','1','B'),(28,11,'Que vaut ∂z(L)/∂b(L), la dérivée de z(L) par rapport au biais ?','a(L-1)','w(L)','0','1','D'),(29,11,'Pourquoi les neurones fortement activés ont-ils plus d\'influence sur l\'apprentissage des poids qui leur sont connectés ?','Parce que leur biais est plus grand','Parce que ∂z(L)/∂w(L) = a(L-1), donc un grand a(L-1) amplifie le gradient','Parce que la sigmoïde est plus grande','Parce que le coût est plus petit','B'),(30,11,'Le terme \'erreur\' (delta) d\'un neurone en rétropropagation représente intuitivement :','La valeur absolue de son activation','La responsabilité de ce neurone dans l\'erreur finale — à quel point changer son activation affecte le coût','Son biais actuel','Sa dérivée par rapport au temps','B'),(31,11,'Pourquoi dit-on que la rétropropagation est une application de la règle de la chaîne sur un graphe de calcul ?','Parce qu\'elle utilise des graphes orientés acycliques pour stocker les données','Parce que le réseau est une composition de fonctions, et la dérivée d\'une composition se calcule en multipliant les dérivées locales','Parce qu\'elle minimise le nombre d\'opérations matricielles','Parce qu\'elle propage les activations de droite à gauche','B'),(32,11,'Dans la descente de gradient stochastique, qu\'appelle-t-on un \'mini-batch\' ?','L\'ensemble complet des données d\'entraînement','Un sous-ensemble aléatoire des données utilisé pour estimer le gradient à chaque étape','Une seule couche du réseau','Un hyperparamètre fixé avant l\'entraînement','B'),(33,11,'Quelle est la différence essentielle entre un minimum local et un minimum global dans le paysage de la fonction de coût ?','Il n\'y a aucune différence en pratique','Le minimum global est le point le plus bas de tout l\'espace, tandis qu\'un minimum local n\'est que le plus bas dans son voisinage — la SGD ne garantit pas d\'atteindre le global','Le minimum local a toujours un coût nul','Le minimum global est toujours trouvé par la rétropropagation','B'),(34,11,'Que révèle 3Blue1Brown sur ce que les neurones des couches cachées détectent réellement (par opposition à ce qu\'on espère) ?','Ils détectent exactement des bords et des courbes comme prévu','Ils n\'apprennent rien d\'interprétable — leurs représentations internes sont souvent opaques et ne correspondent pas à des concepts humains simples','Ils mémorisent toujours le dataset d\'entraînement','Ils détectent uniquement la couleur des pixels','B');
/*!40000 ALTER TABLE `lesson_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_resources`
--

DROP TABLE IF EXISTS `lesson_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `url` varchar(1024) NOT NULL,
  `resource_type` enum('link','file','reference') NOT NULL DEFAULT 'link',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lesson_resources_lesson_id` (`lesson_id`),
  CONSTRAINT `lesson_resources_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_resources`
--

LOCK TABLES `lesson_resources` WRITE;
/*!40000 ALTER TABLE `lesson_resources` DISABLE KEYS */;
INSERT INTO `lesson_resources` VALUES (1,10,'Video Machine Learnia - Choisir le Bon Modele de Machine Learning','https://youtu.be/4mqKmTbAnHY?si=5Jk3De2AMDG3MMLy','link',1,'2026-06-16 03:29:23');
/*!40000 ALTER TABLE `lesson_resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_videos`
--

DROP TABLE IF EXISTS `lesson_videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL DEFAULT 'Vidéo',
  `url` varchar(1024) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lesson_videos_lesson_id` (`lesson_id`),
  CONSTRAINT `lesson_videos_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_videos`
--

LOCK TABLES `lesson_videos` WRITE;
/*!40000 ALTER TABLE `lesson_videos` DISABLE KEYS */;
INSERT INTO `lesson_videos` VALUES (1,7,'Vidéo principale','https://youtu.be/8hly31xKli0?si=GZRc5XtsGD5mkHWL',0,'2026-06-14 17:28:02'),(2,8,'Vidéo principale','https://youtu.be/3wLqsRLvV-c?si=Nbt14QdSyp5hNLFd',0,'2026-06-14 17:28:02'),(4,11,'Neural Networks 1','https://youtu.be/aircAruvnKk?si=XiJvgmGjSKqYZ-w_',1,'2026-06-16 11:52:42'),(5,11,'Neural Networks 2','https://youtu.be/IHZwWFHWa-w?si=bGMwGVVkuMekRnqO',2,'2026-06-16 11:52:42'),(6,11,'Neural Networks 3','https://youtu.be/Ilg3gGewQ5U?si=BHinzsJwBlASISR7',3,'2026-06-16 11:52:42'),(7,11,'Neural Networks 4','https://youtu.be/tIeHLnjs5U8?si=CL0kgkUb0iq6k8TY',4,'2026-06-16 11:52:42');
/*!40000 ALTER TABLE `lesson_videos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content_type` enum('text','pdf','video','mixed') NOT NULL DEFAULT 'text',
  `text_content` text DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `quiz_deadline` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chapter_id` (`chapter_id`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lessons`
--

LOCK TABLES `lessons` WRITE;
/*!40000 ALTER TABLE `lessons` DISABLE KEYS */;
INSERT INTO `lessons` VALUES (6,1,'1. Penser avant de calculer : Les racines antiques de la pensée algorithmique','text','INTRODUCTION\r\n\r\nIl existe une illusion tenace dans notre rapport aux machines : celle qui fait croire que l\'algorithme est une invention moderne, née avec l\'électronique, les transistors ou le silicium. Cette illusion est dangereuse, non pas parce qu\'elle est techniquement fausse — les ordinateurs sont bien une invention du XXe siècle — mais parce qu\'elle nous prive d\'une compréhension profonde de ce que penser veut dire.\r\nUn algorithme, dans son essence la plus nue, est une réponse à une question fondamentale : peut-on réduire la pensée à une suite finie d\'opérations élémentaires ? Cette question n\'a pas attendu Alan Turing pour être posée. Elle a été formulée, débattue, affinée et contestée pendant plus de vingt-cinq siècles, des mathématiciens babyloniens aux logiciens arabes, des philosophes grecs aux calculateurs leibniziens.\r\nCette première leçon vous invite à un voyage dans le temps. Un voyage nécessaire, parce qu\'on ne peut pas réfléchir sérieusement à l\'impact éthique des algorithmes modernes sans comprendre d\'où vient l\'idée même de procédure de pensée. Ce que nous appelons aujourd\'hui intelligence artificielle est l\'héritier direct — et parfois le débiteur inconscient — d\'une longue tradition intellectuelle que nous allons maintenant explorer.\r\n\r\nI. QU\'EST-CE QU\'UN ALGORITHME, PHILOSOPHIQUEMENT PARLANT ?\r\n1.1 La définition technique et ses limites\r\nLa définition standard d\'un algorithme, celle qu\'on trouve dans tous les manuels d\'informatique, est la suivante : une suite finie et ordonnée d\'instructions non ambiguës permettant de résoudre un problème ou une classe de problèmes.\r\nCette définition est correcte. Mais elle est philosophiquement pauvre. Elle décrit ce que fait un algorithme sans dire ce qu\'il est. C\'est comme définir la poésie comme \"une suite de mots organisés en vers\" : on a dit quelque chose de vrai, mais on a manqué l\'essentiel.\r\nPour comprendre ce qu\'est vraiment un algorithme, il faut poser trois questions que la définition technique esquive :\r\n\r\nQu\'est-ce qu\'une \"instruction\" ? Est-ce un acte physique ? Une représentation mentale ? Un symbole ?\r\nQu\'est-ce que \"résoudre\" un problème ? Calculer le bon résultat suffit-il, ou faut-il comprendre ce résultat ?\r\nQui exécute l\'algorithme ? Un humain ? Une machine ? Et la différence compte-t-elle ?\r\n\r\nCes questions ne sont pas des détails philosophiques secondaires. Elles sont au cœur des débats les plus vifs de l\'intelligence artificielle contemporaine — et nous y reviendrons tout au long de ce cours. Pour l\'instant, retenons qu\'un algorithme est fondamentalement une théorie de la pensée mécanique : l\'affirmation que certaines formes de raisonnement peuvent être décomposées en étapes reproductibles, transmissibles, et exécutables par n\'importe quel agent suffisamment attentif, qu\'il soit humain ou artificiel.\r\n1.2 Le paradoxe fondateur\r\nIl y a un paradoxe au cœur de l\'idée algorithmique : pour construire une procédure de pensée, il faut déjà penser. L\'algorithme n\'est jamais le produit spontané d\'une machine ; il est d\'abord le produit d\'un esprit humain qui a compris un problème, l\'a analysé, et en a extrait la structure reproductible.\r\nCe paradoxe est capital. Il signifie que la question philosophique première n\'est pas \"les machines peuvent-elles penser ?\" mais \"qu\'avons-nous dû comprendre sur la pensée pour pouvoir la mécaniser ?\" Cette seconde question nous renvoie inévitablement à l\'histoire.\r\n\r\nII. LES PREMIÈRES PROCÉDURES DE PENSÉE : L\'ANTIQUITÉ MATHÉMATIQUE\r\n2.1 Babylone : calculer sans nommer\r\nBien avant que le mot \"algorithme\" existe, les mathématiciens babyloniens (vers 2000 avant notre ère) avaient développé ce que nous reconnaissons aujourd\'hui comme des procédures algorithmiques.\r\nLes tablettes cunéiformes retrouvées à Nippur, à Ur, à Suse, contiennent des séquences d\'instructions pour résoudre des problèmes de géométrie, de comptabilité, d\'astronomie. Une tablette célèbre, connue sous la référence YBC 7289, présente une approximation de la racine carrée de 2 avec une précision remarquable : 1,41421296... La valeur réelle est 1,41421356... L\'erreur est de l\'ordre du millionième.\r\nCe qui est philosophiquement fascinant, c\'est que les Babyloniens n\'avaient pas de théorie de ce qu\'ils faisaient. Ils ne cherchaient pas à fonder logiquement leurs procédures. Ils avaient des recettes — des suites d\'opérations qui donnaient les bons résultats. Leurs textes mathématiques ressemblent d\'ailleurs à des recettes de cuisine : \"Prends le nombre. Multiplie-le par lui-même. Ajoute ceci. Divise cela.\"\r\nCette observation est importante pour la philosophie des algorithmes : une procédure peut être efficace sans être comprise. C\'est une vérité qui va résonner très fortement quand nous examinerons les réseaux de neurones profonds contemporains, dont les opérations sont souvent aussi opaques pour leurs créateurs que les tablettes babyloniennes l\'étaient pour leurs utilisateurs.\r\n2.2 L\'Égypte ancienne et la multiplication par doublements successifs\r\nLes mathématiciens égyptiens avaient, eux aussi, développé des procédures remarquables. Le Papyrus Rhind (vers 1650 avant notre ère) décrit une méthode de multiplication qui ne ressemble en rien à la nôtre.\r\nPour multiplier 13 par 17, l\'Égyptien antique procédait ainsi :\r\n\r\nIl partait de 1 et de 17\r\nIl doublait successivement : 2→34, 4→68, 8→136\r\nIl décomposait 13 en puissances de 2 : 13 = 8 + 4 + 1\r\nIl additionnait : 136 + 68 + 17 = 221\r\n\r\nCette méthode — la multiplication égyptienne ou multiplication par doublement — est exactement ce que nous appelons aujourd\'hui exponentiation rapide. Elle est utilisée dans les algorithmes cryptographiques modernes. Et elle date de 3600 ans.\r\nCe fait nous enseigne quelque chose d\'essentiel : la découverte d\'une procédure efficace peut précéder de plusieurs millénaires la compréhension de pourquoi elle est efficace. L\'algorithme précède sa propre théorie. Ce n\'est pas une anecdote historique ; c\'est une structure profonde de la connaissance mathématique et informatique.\r\n2.3 Al-Khawarizmi et la naissance du mot\r\nLe mot \"algorithme\" vient d\'un homme : Muhammad ibn Musa al-Khawarizmi, mathématicien perse né vers 780 après notre ère à Khwarezm, une région qui correspond aujourd\'hui à l\'Ouzbékistan.\r\nAl-Khawarizmi travaillait à la Maison de la Sagesse (Bayt al-Hikma) à Bagdad, sous le califat d\'Al-Ma\'mun. Il écrivit, vers 825, un traité intitulé Kitab al-mukhtasar fi hisab al-jabr wal-muqabala — \"Abrégé sur le calcul par la restauration et la comparaison.\" C\'est de ce titre que vient le mot algèbre.\r\nMais son influence sur le mot algorithme vient d\'un autre de ses ouvrages : Kitab al-jam wal-tafriq bi-hisab al-hind — \"Livre de l\'addition et de la soustraction selon le calcul indien.\" Ce traité introduisit en Occident le système de numération décimale positionnelle d\'origine indienne, avec le zéro. Quand le livre fut traduit en latin au XIIe siècle, les scribes latinisèrent le nom de l\'auteur : Algoritmi. Les procédures décrites dans ce livre devinrent des algorithmi, puis algoritmus, puis algorithm.\r\nPhilosophiquement, al-Khawarizmi est important pour deux raisons :\r\nPremièrement, il ne se contentait pas de donner des recettes. Il expliquait ses procédures géométriquement, cherchait à les justifier. Il y a chez lui une conscience naissante de la distinction entre calculer et comprendre.\r\nDeuxièmement, son travail posait implicitement une question radicale : si l\'on peut enseigner à n\'importe qui une procédure pour résoudre une classe entière de problèmes, a-t-on besoin d\'être intelligent pour faire des mathématiques ? Cette question, formulée ainsi, est exactement celle que posent aujourd\'hui les partisans et les critiques des LLMs (Large Language Models).\r\n\r\nIII. LA GRÈCE ANTIQUE : QUAND LA LOGIQUE DEVIENT PROCÉDURE\r\n3.1 Euclide et le premier algorithme célèbre\r\nL\'algorithme d\'Euclide pour calculer le plus grand commun diviseur de deux entiers, décrit dans les Éléments (vers 300 avant notre ère), est souvent présenté comme le premier algorithme de l\'histoire formellement identifiable.\r\nVoici le raisonnement qu\'Euclide propose (livre VII, propositions 1 et 2) :\r\n\r\nPour trouver le PGCD de deux nombres entiers a et b (avec a > b) :\r\n\r\nSi b divise a, alors b est le PGCD.\r\nSinon, remplacer a par le reste de la division de a par b, et recommencer.\r\n\r\n\r\nCe qui est philosophiquement remarquable dans cet algorithme, c\'est qu\'il est prouvé correct. Euclide ne se contente pas de dire \"ça marche\" ; il démontre pourquoi ça marche. C\'est la première fois dans l\'histoire que nous rencontrons clairement la distinction entre :\r\n\r\nLa procédure (ce qu\'on fait)\r\nLa preuve (pourquoi ce qu\'on fait est juste)\r\nLa terminaison (pourquoi la procédure s\'arrête)\r\n\r\nCes trois concepts — correction, preuve, terminaison — sont encore aujourd\'hui les trois piliers de la théorie des algorithmes. Euclide les a posés il y a 2300 ans sans disposer des mots pour les nommer.\r\n3.2 Aristote et la syllogistique : la logique comme machine à inférer\r\nAristote (384–322 avant notre ère) n\'a jamais utilisé le mot \"algorithme\". Pourtant, son projet logique est, philosophiquement, l\'ancêtre direct de toute la logique formelle sur laquelle repose l\'informatique moderne.\r\nDans ses Premiers Analytiques, Aristote développe la syllogistique : une théorie des formes valides de raisonnement. Le syllogisme classique est :\r\n\r\nTous les hommes sont mortels.\r\nSocrate est un homme.\r\nDonc, Socrate est mortel.\r\n\r\nCe qui est révolutionnaire dans ce projet, c\'est qu\'Aristote cherche à formaliser la validité du raisonnement. Il ne s\'intéresse pas à la vérité des prémisses (Socrate est-il vraiment un homme ?), mais à la structure de l\'inférence : si les prémisses sont vraies, la conclusion doit être vraie, indépendamment du contenu.\r\nC\'est une rupture philosophique majeure : la validité d\'un raisonnement peut être déterminée par sa forme, indépendamment de son contenu. On reconnaît ici l\'idée centrale de la logique formelle moderne et, au-delà, du calcul : ce qui compte n\'est pas le sens des symboles manipulés, mais les règles de leur manipulation.\r\nAristote identifie 256 formes syllogistiques possibles, dont 24 sont valides. Il a, en quelque sorte, dressé la liste complète des \"instructions\" d\'un certain système logique — un geste qui n\'est pas sans rappeler la spécification formelle d\'un langage de programmation.\r\n3.3 Le Criton de Platon et la question de l\'obéissance à la procédure\r\nIl y a dans le dialogue de Platon Criton une question qui peut sembler lointaine de l\'informatique, mais qui est d\'une actualité brûlante pour la philosophie des algorithmes : doit-on obéir à une procédure même quand on sait qu\'elle mène à un résultat injuste ?\r\nDans ce dialogue, Socrate, condamné à mort par Athènes, refuse de s\'évader alors qu\'il le pourrait, parce qu\'il a choisi de se soumettre aux lois de la cité. Sa position est que les lois sont une procédure collective, et que les transgresser au cas par cas — même pour une bonne raison — détruirait le fondement même de la vie en société.\r\nLe parallèle avec les algorithmes est saisissant. Un algorithme est précisément une procédure à laquelle on se soumet. La question \"doit-on toujours exécuter l\'algorithme même quand le résultat semble injuste ?\" est exactement la question que posent les critiques des algorithmes de justice prédictive, des systèmes de scoring de crédit, ou des algorithmes de modération de contenu. Nous y reviendrons dans les chapitres suivants.\r\n\r\nIV. LE GRAND SAUT MÉDIÉVAL : LOGIQUE, LANGUE ET MACHINE\r\n4.1 Raymond Lulle et l\'Ars Magna : la première machine à penser ?\r\nAu XIIIe siècle, le philosophe et théologien catalan Ramon Llull (1232–1316) conçoit quelque chose d\'extraordinaire : une machine logique destinée à produire toutes les vérités possibles par combinaison mécanique de concepts.\r\nSon Ars Magna (la Grande Art) est un système de disques concentriques rotatifs sur lesquels sont inscrits des attributs de Dieu, des dignités divines, des questions, des sujets, des prédicats. En faisant tourner les disques, on obtient mécaniquement des propositions combinées. Lulle pensait ainsi pouvoir démontrer mécaniquement les vérités de la théologie chrétienne.\r\nCe projet peut sembler naïf ou même grotesque. Mais philosophiquement, il est d\'une audace radicale : Lulle postule que la pensée correcte est une affaire de combinaisons formelles de symboles, et que cette combinaison peut être réalisée mécaniquement. C\'est exactement la thèse que défendra Leibniz quatre siècles plus tard, et que réalisera Turing six siècles plus tard.\r\nLulle est aussi important pour une autre raison : il voulait utiliser sa machine pour convaincre les non-chrétiens de la vérité du christianisme par la démonstration logique. C\'est la première formulation d\'un projet d\'algorithme persuasif — ce que nous appellerions aujourd\'hui un système de recommandation ou un moteur de désinformation, selon qu\'on en est bénéficiaire ou victime.\r\n4.2 La querelle des universaux et les fondements de la représentation\r\nEntre le XIe et le XIVe siècle, la scolastique médiévale fut agitée par une querelle philosophique qui peut sembler abstraite : la querelle des universaux. Les concepts généraux (comme \"cheval\", \"rouge\", \"justice\") ont-ils une existence réelle, ou ne sont-ils que des mots ?\r\nLes réalistes (comme Guillaume de Champeaux) soutenaient que les universaux existent vraiment, indépendamment des choses particulières.\r\nLes nominalistes (comme Guillaume d\'Ockham) soutenaient que les universaux ne sont que des noms, des étiquettes pratiques pour regrouper des individus similaires.\r\nCette dispute est directement pertinente pour la philosophie des algorithmes, parce qu\'elle anticipe une question fondamentale de l\'intelligence artificielle : quand un système traite le mot \"chat\", traite-t-il quelque chose qui représente vraiment l\'essence du chat, ou manipule-t-il simplement un symbole arbitraire dont les relations avec d\'autres symboles lui tiennent lieu de sens ?\r\nLa réponse qu\'on donne à cette question détermine entièrement la façon dont on évalue les capacités et les limites des LLMs. Les réalistes diraient que les LLMs peuvent potentiellement comprendre ; les nominalistes diraient qu\'ils ne font que calculer des cooccurrences de tokens. Nous sommes en 2024, et le débat n\'est pas clos.\r\n\r\nV. LA RÉVOLUTION RATIONALISTE : MÉCANISER LA RAISON\r\n5.1 Descartes et la méthode : l\'algorithme de la pensée\r\nDans son Discours de la méthode (1637), René Descartes propose quelque chose qui ressemble explicitement à un algorithme de la pensée philosophique. Sa méthode comprend quatre règles :\r\n\r\nL\'évidence : n\'accepter comme vrai que ce qui est clairement et distinctement perçu comme tel.\r\nL\'analyse : diviser chaque difficulté en autant de parties qu\'il est possible.\r\nLa synthèse : conduire ses pensées par ordre, des plus simples aux plus complexes.\r\nLe dénombrement : faire partout des recensements complets.\r\n\r\nCe programme est explicitement présenté par Descartes comme universel et transmissible : n\'importe qui, en suivant ces règles, devrait pouvoir atteindre la vérité. C\'est la définition même d\'un algorithme.\r\nMais Descartes est aussi l\'auteur d\'une thèse qui complique tout : dans la deuxième partie des Méditations, il distingue radicalement l\'âme pensante (res cogitans) du corps mécanique (res extensa). Les animaux, selon lui, ne sont que des machines biologiques — des automates complexes. Les humains, eux, ont une âme qui comprend, et cette compréhension ne peut pas être réduite à de la mécanique.\r\nIl y a donc une tension profonde chez Descartes : d\'un côté, il mécanise la méthode de la pensée ; de l\'autre, il en soustrait le contenu — la compréhension véritable — à toute réduction mécanique. Cette tension est exactement celle qu\'explore John Searle dans son célèbre argument de la \"chambre chinoise\", que nous étudierons dans un chapitre ultérieur.\r\n5.2 Leibniz et le Calculus Ratiocinator : le rêve de la raison universelle\r\nGottfried Wilhelm Leibniz (1646–1716) est peut-être le philosophe le plus directement influent sur la théorie des algorithmes. Il formule explicitement — et avec un enthousiasme qui n\'a pas vieilli — le projet de mécaniser entièrement la raison.\r\nSon projet comporte deux volets :\r\nLa Characteristica Universalis : un langage symbolique universel dans lequel toutes les idées pourraient être exprimées sans ambiguïté. Chaque concept serait assigné à un nombre premier ; les relations entre concepts seraient des relations arithmétiques entre ces nombres. La pensée deviendrait du calcul.\r\nLe Calculus Ratiocinator : un système de règles de calcul appliquées à la Characteristica Universalis, permettant de dériver mécaniquement toutes les vérités logiques et même, espérait Leibniz, de résoudre tous les désaccords philosophiques et théologiques. Face à un litige, les parties n\'auraient qu\'à dire : \"Calculons !\" (Calculemus !)\r\nCe projet est visionnaire à un point qui fait encore frissonner. Leibniz anticipe :\r\n\r\nLa logique symbolique (Boole, Frege)\r\nLa notion de langue formelle (Church, Backus-Naur)\r\nL\'idée d\'un calculateur universel (Turing)\r\nLa résolution automatique de théorèmes (Prolog, assistants de preuve)\r\n\r\nIl anticipe aussi — et c\'est philosophiquement lourd — l\'idée que les désaccords entre humains sont ultimement des erreurs de calcul qu\'un algorithme pourrait corriger. Cette idée est à la fois séduisante et terrifiante. Elle est séduisante parce qu\'elle promet la fin des conflits irrationnels. Elle est terrifiante parce qu\'elle réduit la diversité des valeurs humaines à un bug qu\'on pourrait patcher.\r\n5.3 Pascal et la machine arithmétique : le calcul incarné\r\nBlaise Pascal (1623–1662) n\'est pas seulement le théoricien des pascals et des triangles. En 1642, à l\'âge de dix-neuf ans, il construit la Pascaline : la première machine à calculer mécanique opérationnelle.\r\nLa Pascaline réalise concrètement, en métal et en engrenages, ce que Leibniz formulera théoriquement : la pensée arithmétique peut être incarnée dans un mécanisme. Elle peut être extérieure à l\'esprit humain, reproductible à volonté, et infaillible dans son domaine.\r\nMais Pascal lui-même était profondément ambivalent sur ce qu\'il avait fait. Dans ses Pensées, il distingue l\'esprit de géométrie (logique, systématique, réductible à des règles) et l\'esprit de finesse (intuitif, sensible aux nuances, irréductible à des procédures). La Pascaline peut exécuter l\'esprit de géométrie. L\'esprit de finesse lui échappe totalement.\r\nCette distinction pascalienne est une des plus profondes de la philosophie de l\'esprit. Elle dit que tout ce qui peut être formalisé peut être mécanisé — et que tout ce qui est vraiment humain résiste peut-être à la formalisation. La machine peut calculer. Peut-elle juger ?\r\n\r\nVI. SYNTHÈSE : CE QUE L\'HISTOIRE NOUS APPREND\r\n6.1 L\'algorithme comme révélateur de la pensée\r\nCe parcours historique nous enseigne quelque chose que les manuels d\'informatique n\'enseignent pas : construire un algorithme, c\'est toujours aussi construire une théorie de la pensée. Chaque algorithme est une affirmation sur ce que penser peut être — sur ce qui, dans la cognition humaine, est reproductible, transmissible, mécanisable.\r\nLes Babyloniens pensaient que calculer juste suffisait. Euclide pensait que calculer juste devait s\'accompagner d\'une preuve. Aristote pensait que la validité formelle était le cœur du raisonnement. Lulle pensait que la vérité était combinatoire. Leibniz pensait que toute pensée correcte était du calcul. Pascal pensait que le calcul n\'était qu\'une moitié de la pensée.\r\nCes positions ne sont pas des curiosités historiques. Elles structurent encore, souvent de manière invisible, les débats contemporains sur l\'IA.\r\n6.2 Trois tensions fondatrices\r\nDe cette histoire, trois tensions se dégagent qui traverseront tout ce cours :\r\n1. Efficacité vs. Compréhension\r\n\r\nUne procédure peut donner le bon résultat sans que quiconque comprenne pourquoi. Les tablettes babyloniennes, les réseaux de neurones profonds — même structure d\'opacité. Est-ce suffisant ? Suffisant pour quoi ?\r\n2. Forme vs. Contenu\r\n\r\nLa logique aristotélicienne postule que la validité est affaire de forme, non de contenu. Mais peut-on séparer la forme du contenu quand on parle de raisonnement éthique, de jugement esthétique, de décision politique ?\r\n3. Universalité vs. Particularité\r\n\r\nLe projet de Leibniz — et, à sa suite, celui de l\'IA — suppose qu\'il existe des procédures universelles applicables à toutes les situations. Mais la pensée humaine est peut-être irréductiblement située, contextuelle, particulière. Un algorithme peut-il être juste dans tous les contextes s\'il ignore le contexte ?\r\n6.3 Ce que nous n\'avons pas encore su faire\r\nDeux mille cinq cents ans après Euclide, nous savons encore très mal :\r\n\r\nFormaliser le jugement en situation d\'incertitude\r\nReproduire mécaniquement l\'esprit de finesse pascalien\r\nConstruire des procédures qui soient à la fois efficaces et compréhensibles Garantir qu\'un algorithme soit juste sans définir d\'abord ce que la justice signifie\r\n\r\nCe ne sont pas des problèmes techniques. Ce sont des problèmes philosophiques. Et c\'est exactement pourquoi ce cours existe.\r\n\r\nCONCLUSION\r\nNous avons parcouru, dans cette leçon, un territoire immense : des tablettes d\'argile de Babylone aux machines à engrenages de Pascal, des syllogismes d\'Aristote aux disques rotatifs de Lulle, des procédures euclidienne à la Characteristica Universalis de Leibniz.\r\nCe voyage n\'était pas une excursion nostalgique. C\'était une archéologie des présupposés. Chaque fois que nous déployons un algorithme moderne — pour recommander une vidéo, évaluer un dossier de crédit, diagnostiquer une maladie, conduire une voiture — nous héritons, consciemment ou non, de ces vingt-cinq siècles de réflexion sur ce que penser veut dire et sur ce qu\'on peut légitimement en mécaniser.\r\n\r\nLa leçon philosophique centrale de cette leçon 1 est simple à formuler, et difficile à assimiler vraiment : un algorithme n\'est jamais neutre. Il est toujours la réalisation matérielle d\'une certaine théorie de la pensée. Et cette théorie porte toujours, en elle, des choix sur ce qui compte, sur ce qui peut être formalisé, sur ce qui peut être délégué à une machine — et sur ce qui ne le peut pas.\r\nDans la leçon 2, nous verrons comment ces fondements logiques ont été formalisés au XIXe et au XXe siècle — de Boole à Frege, de Gödel à Turing — et comment cette formalisation a rendu possible la machine universelle.\r\n\r\nPOINTS CLÉS À RETENIR\r\n\r\nL\'algorithme, comme procédure de pensée formalisée, précède l\'informatique de plusieurs millénaires\r\nLes Babyloniens et les Égyptiens avaient des procédures algorithmiques efficaces sans théorie de leur efficacité — une structure qu\'on retrouve dans les réseaux de neurones modernes Al-Khawarizmi a donné son nom à l\'algorithme en introduisant en Occident des procédures systématiques de calcul Euclide a posé les trois piliers de la théorie des algorithmes : procédure, preuve, terminaison\r\nAristote a fondé la logique formelle en séparant la validité de la forme et le contenu des prémisses Leibniz a formulé explicitement le projet de mécaniser entièrement la raison — un projet dont l\'IA est l\'héritière directe Pascal a posé la limite fondamentale : si le calcul peut exécuter l\'esprit de géométrie, l\'esprit de finesse lui résiste peut-être irréductiblement\r\nTrois tensions traversent toute l\'histoire de la pensée algorithmique : efficacité vs. compréhension, forme vs. contenu, universalité vs. particularité\r\n\r\n\r\nQUESTIONS DE RÉFLEXION\r\n\r\nLes mathématiciens babyloniens utilisaient des procédures efficaces sans en comprendre les fondements. Pensez-vous que c\'est un problème ? Dans quel contexte cela pourrait-il devenir dangereux ?\r\nLeibniz espérait que son Calculus Ratiocinator permettrait de résoudre tous les désaccords philosophiques par le calcul. Pourquoi cette idée vous semble-t-elle séduisante ? Pourquoi vous semble-t-elle problématique ?\r\nLa distinction de Pascal entre esprit de géométrie et esprit de finesse vous paraît-elle toujours pertinente aujourd\'hui ? Y a-t-il des domaines où vous pensez que l\'esprit de finesse est irréductible à tout algorithme ?\r\nParmi les penseurs étudiés dans cette leçon, lequel vous semble avoir le mieux anticipé les défis que posent les algorithmes contemporains ? Justifiez votre réponse.',NULL,'',1,NULL),(7,1,'2. Practicals','video',NULL,NULL,'https://youtu.be/8hly31xKli0?si=GZRc5XtsGD5mkHWL',2,NULL),(8,1,'3. Le Grand Saut Quantitatif : Algèbre de Boole et l\'Automate Universel de Turing','mixed','1. George Boole : Quand la logique devient une algèbre\r\nAu XIXe siècle, le mathématicien britannique George Boole réalise le rêve de Leibniz. Il invente une algèbre révolutionnaire (l\'Algèbre de Boole) où les variables ne sont pas des nombres (1, 2, 3...), mais des états logiques : VRAI (1) ou FAUX (0).\r\n\r\nIl crée les trois opérateurs logiques fondamentaux que tu utilises tous les jours en programmation :\r\n\r\nAND (ET) : La condition n\'est vraie que si les deux propositions sont vraies.\r\n\r\nOR (OU) : La condition est vraie si au moins une des propositions est vraie.\r\n\r\nNOT (NON) : Inverse l\'état logique (le VRAI devient FAUX).\r\n\r\nC\'est grâce à Boole que des circuits électriques (courant passe = 1, courant ne passe pas = 0) ont pu commencer à \"calculer\" des décisions logiques.\r\n\r\n2. Alan Turing et la Machine Universelle (1936)\r\nEn 1936, un jeune mathématicien de 24 ans nommé Alan Turing va définitivement clore l\'origine de la logique algorithmique en publiant un article sur les \"Nombres Calculables\". Pour résoudre un problème mathématique, il imagine sur papier un appareil théorique : La Machine de Turing.\r\n\r\nCette machine virtuelle simplifiée à l\'extrême possède :\r\n\r\nUne ruban infini divisé en cases (la mémoire).\r\n\r\nUne tête de lecture/écriture (le processeur).\r\n\r\nUn registre d\'états et une table de transition (le programme/l\'algorithme).\r\n\r\nL\'intuition de Turing est gigantesque : il invente la Machine de Turing Universelle, un automate capable d\'imiter n\'importe quelle autre machine simplement en changeant les instructions écrites sur le ruban. L\'ordinateur moderne et le concept de logiciel (software) viennent de naître.','5c7c70a03ff2f4c7932d34e08b54afa8.pdf','https://youtu.be/3wLqsRLvV-c?si=Nbt14QdSyp5hNLFd',3,NULL),(10,2,'4. Introduction aux Modeles Predictifs','pdf',NULL,'7e3fd5de02b809d3cb145b5d1983c3ca.pdf','',1,NULL),(11,3,'1. Comprendre les bases du Machine Learning (Neural Networks)','video',NULL,NULL,NULL,1,NULL);
/*!40000 ALTER TABLE `lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `live_eval_answers`
--

DROP TABLE IF EXISTS `live_eval_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `live_eval_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` char(1) NOT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reg_question` (`registration_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `live_eval_answers_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `live_eval_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `live_eval_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `live_eval_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `live_eval_answers`
--

LOCK TABLES `live_eval_answers` WRITE;
/*!40000 ALTER TABLE `live_eval_answers` DISABLE KEYS */;
INSERT INTO `live_eval_answers` VALUES (3,7,60,'A','2026-06-24 08:23:56'),(4,7,61,'B','2026-06-24 08:24:06'),(5,7,62,'A','2026-06-24 08:24:52'),(6,7,63,'B','2026-06-24 08:25:37'),(7,7,64,'D','2026-06-24 08:26:19'),(8,7,65,'B','2026-06-24 08:27:04'),(9,7,66,'B','2026-06-24 08:27:49');
/*!40000 ALTER TABLE `live_eval_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `live_eval_questions`
--

DROP TABLE IF EXISTS `live_eval_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `live_eval_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `correct_option` char(1) NOT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  CONSTRAINT `live_eval_questions_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `live_eval_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `live_eval_questions`
--

LOCK TABLES `live_eval_questions` WRITE;
/*!40000 ALTER TABLE `live_eval_questions` DISABLE KEYS */;
INSERT INTO `live_eval_questions` VALUES (37,7,'Dans un réseau de neurones, qu\'est-ce qu\'un neurone représente mathématiquement ?','Une fonction booléenne','Un nombre réel entre 0 et 1 appelé activation','Un vecteur de poids','Une matrice de transformation','B',NULL,NULL,0),(38,7,'Quel est le rôle de la fonction d\'activation sigmoïde dans un réseau de neurones ?','Normaliser les poids entre -1 et 1','Écraser toute valeur réelle dans l\'intervalle (0, 1)','Calculer la dérivée du coût','Initialiser les biais à zéro','B',NULL,NULL,0),(39,7,'Dans l\'exemple de reconnaissance de chiffres manuscrits (MNIST), combien de neurones contient la couche d\'entrée ?','28','256','784','1024','C',NULL,NULL,0),(40,7,'Que représentent les poids (weights) dans la connexion entre deux neurones ?','La valeur d\'activation du neurone suivant','Des coefficients qui pondèrent l\'influence de chaque activation de la couche précédente','Le biais ajouté après la somme pondérée','La dérivée de la fonction de coût','B',NULL,NULL,0),(41,7,'À quoi sert le biais (bias) d\'un neurone ?','À normaliser les activations','À décaler le seuil à partir duquel le neurone s\'active significativement','À réduire le surapprentissage','À initialiser les poids aléatoirement','B',NULL,NULL,0),(42,7,'Si un réseau possède 784 entrées, une couche cachée de 16 neurones, une autre de 16, et 10 sorties, combien de paramètres (poids + biais) contient-il au total ?','12 960','13 002','12 800','13 500','B',NULL,NULL,0),(43,7,'Que signifie l\'activation élevée d\'un neurone de la couche de sortie correspondant au chiffre \'3\' ?','Le réseau est incertain','Le réseau pense que l\'image représente le chiffre 3','Le coût est minimal','Le biais de ce neurone est nul','B',NULL,NULL,0),(44,7,'Comment 3Blue1Brown décrit-il intuitivement les neurones des couches cachées dans la reconnaissance de chiffres ?','Comme des détecteurs de couleurs','Comme des détecteurs de boucles, de lignes et de courbes spécifiques','Comme des classificateurs binaires','Comme des fonctions de hachage','B',NULL,NULL,0),(45,7,'La fonction de coût (cost function) pour un exemple d\'entraînement est définie comme :','La somme des activations de sortie','La somme des carrés des différences entre les sorties prédites et les valeurs cibles','Le produit des poids de toutes les couches','La valeur maximale parmi les activations de sortie','B',NULL,NULL,0),(46,7,'Que mesure la fonction de coût moyenne sur l\'ensemble d\'entraînement ?','La vitesse d\'apprentissage','La performance globale du réseau sur tous les exemples — plus elle est basse, mieux c\'est','Le nombre de couches cachées optimal','Le taux de dropout','B',NULL,NULL,0),(47,7,'Quel est l\'objectif principal de la descente de gradient (gradient descent) ?','Augmenter la fonction de coût','Trouver les poids et biais qui minimisent la fonction de coût','Maximiser le nombre de neurones activés','Réduire le nombre de couches','B',NULL,NULL,0),(48,7,'Intuitivement, que représente le gradient de la fonction de coût ?','La valeur actuelle du coût','La direction de montée la plus raide dans l\'espace des paramètres','La moyenne des activations','Le vecteur des biais','B',NULL,NULL,0),(49,7,'Pour minimiser le coût, dans quelle direction se déplace-t-on par rapport au gradient ?','Dans le sens du gradient (gradient ascent)','Dans le sens opposé au gradient (gradient descent)','Perpendiculairement au gradient','Aléatoirement','B',NULL,NULL,0),(50,7,'Pourquoi utilise-t-on la descente de gradient stochastique (SGD) plutôt que la descente de gradient classique ?','Parce qu\'elle converge vers un minimum global garanti','Parce qu\'utiliser un mini-batch est beaucoup plus rapide computationnellement qu\'utiliser tout le dataset','Parce qu\'elle ne nécessite pas de calculer des dérivées','Parce que le coût est alors nul','B',NULL,NULL,0),(51,7,'Que signifie le terme \'taux d\'apprentissage\' (learning rate) dans la descente de gradient ?','Le nombre d\'époques d\'entraînement','La taille des pas effectués dans la direction opposée au gradient','Le nombre de neurones par couche','La valeur initiale des poids','B',NULL,NULL,0),(52,7,'La rétropropagation (backpropagation) est un algorithme permettant de :','Initialiser les poids d\'un réseau','Calculer efficacement le gradient de la fonction de coût par rapport à tous les poids et biais','Réduire le nombre de couches d\'un réseau','Convertir les activations en probabilités','B',NULL,NULL,0),(53,7,'Dans la rétropropagation, quel théorème mathématique fondamental est utilisé pour propager les gradients de couche en couche ?','Le théorème de Bayes','La règle de la chaîne (chain rule) de dérivation','Le théorème de Taylor','Le lemme de Fatou','B',NULL,NULL,0),(54,7,'Quelle notation 3Blue1Brown utilise-t-il pour désigner l\'activation du k-ième neurone de la couche L ?','w(L,k)','b(L,k)','a(L,k)','C(L,k)','C',NULL,NULL,0),(55,7,'Dans la notation de la série, que représente C₀ ?','Le coût moyen sur tout le dataset','La fonction de coût pour un seul exemple d\'entraînement','La couche de sortie','Le nombre de classes','B',NULL,NULL,0),(56,7,'Qu\'est-ce que ∂C₀/∂w(L) mesure dans le contexte de la rétropropagation ?','La valeur du poids w(L) après mise à jour','La sensibilité du coût à une légère modification du poids w(L)','L\'activation du neurone L','Le biais optimal de la couche L','B',NULL,NULL,0),(57,7,'Dans l\'expression z(L) = w(L)·a(L-1) + b(L), que représente z(L) ?','L\'activation après la fonction sigmoïde','La somme pondérée avant application de la fonction d\'activation','Le gradient du coût','La valeur cible (label)','B',NULL,NULL,0),(58,7,'Selon la règle de la chaîne appliquée à la rétropropagation, ∂C₀/∂w(L) est égal à :','∂z(L)/∂w(L) uniquement','(∂C₀/∂a(L)) · (∂a(L)/∂z(L)) · (∂z(L)/∂w(L))','∂C₀/∂a(L) + ∂a(L)/∂z(L)','∂C₀/∂b(L) · ∂b(L)/∂w(L)','B',NULL,NULL,0),(59,7,'Que vaut ∂z(L)/∂w(L) dans la dérivation de la rétropropagation ?','σ(z(L))','a(L-1) — l\'activation de la couche précédente','b(L)','1','B',NULL,NULL,0),(60,7,'Que vaut ∂z(L)/∂b(L), la dérivée de z(L) par rapport au biais ?','a(L-1)','w(L)','0','1','D',NULL,NULL,0),(61,7,'Pourquoi les neurones fortement activés ont-ils plus d\'influence sur l\'apprentissage des poids qui leur sont connectés ?','Parce que leur biais est plus grand','Parce que ∂z(L)/∂w(L) = a(L-1), donc un grand a(L-1) amplifie le gradient','Parce que la sigmoïde est plus grande','Parce que le coût est plus petit','B',NULL,NULL,0),(62,7,'Le terme \'erreur\' (delta) d\'un neurone en rétropropagation représente intuitivement :','La valeur absolue de son activation','La responsabilité de ce neurone dans l\'erreur finale — à quel point changer son activation affecte le coût','Son biais actuel','Sa dérivée par rapport au temps','B',NULL,NULL,0),(63,7,'Pourquoi dit-on que la rétropropagation est une application de la règle de la chaîne sur un graphe de calcul ?','Parce qu\'elle utilise des graphes orientés acycliques pour stocker les données','Parce que le réseau est une composition de fonctions, et la dérivée d\'une composition se calcule en multipliant les dérivées locales','Parce qu\'elle minimise le nombre d\'opérations matricielles','Parce qu\'elle propage les activations de droite à gauche','B',NULL,NULL,0),(64,7,'Dans la descente de gradient stochastique, qu\'appelle-t-on un \'mini-batch\' ?','L\'ensemble complet des données d\'entraînement','Un sous-ensemble aléatoire des données utilisé pour estimer le gradient à chaque étape','Une seule couche du réseau','Un hyperparamètre fixé avant l\'entraînement','B',NULL,NULL,0),(65,7,'Quelle est la différence essentielle entre un minimum local et un minimum global dans le paysage de la fonction de coût ?','Il n\'y a aucune différence en pratique','Le minimum global est le point le plus bas de tout l\'espace, tandis qu\'un minimum local n\'est que le plus bas dans son voisinage — la SGD ne garantit pas d\'atteindre le global','Le minimum local a toujours un coût nul','Le minimum global est toujours trouvé par la rétropropagation','B',NULL,NULL,0),(66,7,'Que révèle 3Blue1Brown sur ce que les neurones des couches cachées détectent réellement (par opposition à ce qu\'on espère) ?','Ils détectent exactement des bords et des courbes comme prévu','Ils n\'apprennent rien d\'interprétable — leurs représentations internes sont souvent opaques et ne correspondent pas à des concepts humains simples','Ils mémorisent toujours le dataset d\'entraînement','Ils détectent uniquement la couleur des pixels','B',NULL,NULL,0),(75,99,'Quelle est la capitale de la France ?','Londres','Paris','Berlin','Madrid','B',10,NULL,1),(76,99,'Combien font 2 + 2 ?','3','4','5','6','B',10,NULL,2);
/*!40000 ALTER TABLE `live_eval_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `live_eval_registrations`
--

DROP TABLE IF EXISTS `live_eval_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `live_eval_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `score` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session_email` (`session_id`,`email`),
  CONSTRAINT `live_eval_registrations_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `live_eval_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `live_eval_registrations`
--

LOCK TABLES `live_eval_registrations` WRITE;
/*!40000 ALTER TABLE `live_eval_registrations` DISABLE KEYS */;
INSERT INTO `live_eval_registrations` VALUES (7,7,'NGANKEU TAKOU DANIEL WILFRIED','danyhardi06@gmail.com','2026-06-24 08:05:56',13.33),(12,99,'Test Student','test@example.com','2026-06-24 08:33:30',0.00);
/*!40000 ALTER TABLE `live_eval_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `live_eval_sessions`
--

DROP TABLE IF EXISTS `live_eval_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `live_eval_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `session_code` varchar(64) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `default_time_limit` int(11) NOT NULL DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_code` (`session_code`),
  KEY `course_id` (`course_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `live_eval_sessions_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `live_eval_sessions_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `live_eval_sessions`
--

LOCK TABLES `live_eval_sessions` WRITE;
/*!40000 ALTER TABLE `live_eval_sessions` DISABLE KEYS */;
INSERT INTO `live_eval_sessions` VALUES (7,1,24,'INF222','b6b11608d8300e9b','2026-06-24 09:06:00','2026-06-24 10:20:00',0,45,'2026-06-24 08:05:17'),(99,1,24,'Test Live Sync','testcode99','2026-06-24 09:33:40','2026-06-24 10:33:30',0,10,'2026-06-24 08:33:30');
/*!40000 ALTER TABLE `live_eval_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`,`attempted_at`),
  KEY `idx_email_time` (`email`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'165.210.39.251','jean@gmail.com','2026-06-14 12:08:34'),(3,'129.0.226.255','test@gmail.com','2026-06-14 12:10:59'),(4,'129.0.226.255','test@gmail.com','2026-06-14 12:11:05'),(5,'129.0.226.255','test@gmail.com','2026-06-14 12:14:45'),(6,'129.0.226.255','test@gmail.com','2026-06-14 12:14:54'),(7,'102.244.222.52','corneliasuziemassongo@gmail.com','2026-06-14 12:21:46'),(8,'92.222.177.11','ffffdse','2026-06-14 12:29:40');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` VALUES (1,'Sciences Humaines & Lettres','Un module transversal explorant les structures philosophiques et la littérature classique.','2026-06-14 11:58:51'),(2,'Sciences Formelles & Code','Introduction aux fondements de l\'informatique théorique et de la logique pure.','2026-06-14 11:58:51'),(3,'Sciences Formelles','Introduction aux sciences Fondamentales','2026-06-14 16:53:30');
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_campaigns`
--

DROP TABLE IF EXISTS `newsletter_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `sent_by` int(11) NOT NULL,
  `recipient_count` int(11) NOT NULL DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sent_by` (`sent_by`),
  CONSTRAINT `newsletter_campaigns_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_campaigns`
--

LOCK TABLES `newsletter_campaigns` WRITE;
/*!40000 ALTER TABLE `newsletter_campaigns` DISABLE KEYS */;
INSERT INTO `newsletter_campaigns` VALUES (1,'Nouveaux Cours disponibles','Ceci est un Message de Test de la plateforme academique StudyVibe, qui teste sa nouvelle fonctionnalite, Newsletter.\r\n\r\nSi vous revecez ce message alors votre adresse mail est enregistree sur notre site. Merci de participer a ce test.\r\n\r\n#Daniel',1,20,'2026-06-16 02:07:50'),(2,'Test No.2 de la Newletter - StudyVibe','Vous recevez ce message car vous etes inscrits en tant qu\'apprenant sur la plateforme academiqiue StudyVibe.\r\nCeci est pour tester la regularite et la fonctionnalite de celle-ci.\r\n\r\nMerci pour votre Collaboration.\r\n#Daniel',1,20,'2026-06-16 11:20:22'),(3,'Test No.2 de la Newletter - StudyVibe','Vous recevez ce message car vous etes inscrits en tant qu\'apprenant sur la plateforme academiqiue StudyVibe.\r\nCeci est pour tester la regularite et la fonctionnalite de celle-ci.\r\n\r\nMerci pour votre Collaboration.\r\n#Daniel',1,20,'2026-06-16 11:21:50'),(4,'Test No.2 de la Newletter - StudyVibe','Vous recevez ce message car vous etes inscrits en tant qu\'apprenant sur la plateforme academiqiue StudyVibe.\r\nCeci est pour tester la regularite et la fonctionnalite de celle-ci.\r\n\r\nMerci pour votre Collaboration.\r\n#Daniel',1,20,'2026-06-16 11:23:33'),(5,'Team StudyVibe','Nous vous contactons aujourd\'hui dans le cadre d\'un test de notre système de newsletter StudyVibe.\r\n\r\nCe message a pour but de vérifier le bon fonctionnement de notre système de communication. Nous vous remercions chaleureusement pour votre collaboration et votre confiance envers notre plateforme.\r\n\r\nÀ très bientôt sur StudyVibe !\r\n\r\n#Daniel',1,21,'2026-06-16 11:36:22');
/*!40000 ALTER TABLE `newsletter_campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_subscribers`
--

DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT '',
  `user_id` int(11) DEFAULT NULL,
  `unsubscribe_token` varchar(64) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `unsubscribe_token` (`unsubscribe_token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `newsletter_subscribers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_subscribers`
--

LOCK TABLES `newsletter_subscribers` WRITE;
/*!40000 ALTER TABLE `newsletter_subscribers` DISABLE KEYS */;
INSERT INTO `newsletter_subscribers` VALUES (1,'danyhardi06@gmail.com','Fokoua Michael Cedric',22,'0b6c4fa73e7b9c2d2d9909266f7265dc3d30fb9905084d088d030f4d96350a16',1,'2026-06-15 13:40:09'),(2,'student@studyvibe.edu','Ngankeu Takou Daniel Wilfried',3,'35af8746d4e8e36e89b1dd8a4980da168236e16eeae1facd27181438a2d995f7',1,'2026-06-16 02:06:08'),(3,'jean@gmail.com','Jean',4,'5bc810cc07eb1fcf196acfec0e4160b760132f2b2d63887da378337abf68daa9',1,'2026-06-16 02:06:08'),(4,'kamguiaaube@gmail.com','Toi',5,'fdbc927d995b5548ea05cfa862a3582734b0ca997f936f822ab0e6936c4a144d',1,'2026-06-16 02:06:08'),(5,'brandonsicoding4@gmail.com','GRIOTE Project-Africa',6,'65f167bc5a2afb973a50fd0d830c7b8330625809d91f9e3948ff26ad888ef057',1,'2026-06-16 02:06:08'),(6,'jeean@gmail.com','Jean',7,'2a76832bf7d3db628573cabc38424707400a6178533bb62d07539c8b29dbaed4',1,'2026-06-16 02:06:08'),(7,'test@gmail.com','test',8,'7e57998136899ed34c3f49fda9c37246782e2c9891c7970b638b0dc5242af92a',1,'2026-06-16 02:06:08'),(8,'tchakuikengnelandrynoe@gmail.com','Cisco',9,'9e04c2ded990fa1a79407f43b423436140c8600be979d17e9077f05a41444e9e',1,'2026-06-16 02:06:08'),(9,'admin@test.com','Gold',10,'8b3fec7c8ea33a1fe6b349eadc998deda788ac8f3c670b1c05e75fe81bc15243',1,'2026-06-16 02:06:08'),(10,'princessetherese10@gmail.com','Tina alice',11,'364b372d60538dbdbe1ab942c199f7855bdc4eb7a6daff7b4c4c8c4809819a93',1,'2026-06-16 02:06:08'),(11,'marie@gmail.com','Marie Curie',12,'c4ef9f9ea6e7b7aafeeb6f4daadf743ffda9925936d1006274ac1984037c66fa',1,'2026-06-16 02:06:08'),(12,'m20410660@gmail.com','Bob',13,'194abeba7be3057f9d628afe7d6080778cc88a383992cd0e66b37bfebcf82de5',1,'2026-06-16 02:06:08'),(13,'louise@gmail.com','Louise',14,'6cc2c9153b5b48eb337e081c967f75635edd2cebca4e2268188d5872edea31f8',1,'2026-06-16 02:06:08'),(14,'bba@gmail.com','Esc1',15,'16759895e7b1e4986ba03678f47a3e763639b450ca1bcd63e644ea2d2d7d2a04',1,'2026-06-16 02:06:08'),(15,'ekotoemma5@gmail.com','T',16,'a7c1ae63690467faeb24cc3617485b54291bbfef736caf71f16bd12302519747',1,'2026-06-16 02:06:08'),(16,'kloesayissi@gmail.com','Bidjogo Manuela',17,'827f8756b1f2cb4aa02bfe8f992437cf550e726389f41601b82781aa419d243a',1,'2026-06-16 02:06:08'),(17,'gg@gmail.com','john',18,'2b6734473086e356d321833d76bdac8d7c62bc762c919dc4342ec0ac01783f3b',1,'2026-06-16 02:06:08'),(18,'rrrdxxxx@gmail.com','Frcxd',19,'cd7cae1d6606b1d02f15e25a172a1b5c704e78a55a61bdfdb29ba33aa7883458',1,'2026-06-16 02:06:08'),(19,'wilfried.uy1@gmail.com','NGANKEU TAKOU DANIEL WILFRIED',20,'d90c24660a3176c3cecc20d3b6d283761e3c5152f8e888535f5d38ef936f7ff8',1,'2026-06-16 02:06:08'),(20,'adelinetakou04@gmail.com','TANKE ADELE MIKELLE',21,'43f3cf534e4b0f479c137e0bbd86f0769cad19bd567dfdec4c05427f3fc68155',1,'2026-06-16 02:06:08'),(21,'wilfried.ngankeu@facsciences-uy1.cm','Takou Rochelle',23,'e7d1931ba29f04ba39ea683785a7e55359c8ab4a452d5b6faa3d8a0bde921e62',1,'2026-06-16 11:27:41');
/*!40000 ALTER TABLE `newsletter_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,22,'lesson_complete','Leçon complétée !','Félicitations, vous avez complété la leçon \"1. Comprendre les bases du Machine Learning (Neural Networks)\" avec un score de 33%.',NULL,0,'2026-06-21 16:56:34'),(3,22,'status','Compte suspendu','Votre compte a été suspendu par l\'administration.',NULL,0,'2026-06-24 01:25:40'),(4,22,'status','Compte réactivé','Votre compte a été réactivé par l\'administration. Vous pouvez à nouveau vous connecter.',NULL,0,'2026-06-24 01:26:24');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pwd_reset_hash` (`token_hash`),
  KEY `idx_pwd_reset_user` (`user_id`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
INSERT INTO `password_reset_tokens` VALUES (1,21,'6e2eb48ea680fb72d71b77f95871c88da60c5b21ffc766f7161de07742e10c8b','2026-06-15 15:31:39',NULL,'2026-06-15 13:31:39'),(2,23,'9025b545873ba32786eaf0ff3c748402df72409ad75ad8e1e96e8502a853b529','2026-06-16 13:28:27','2026-06-16 12:28:57','2026-06-16 11:28:27'),(3,20,'ed9745c88c096d1f58e76c863b88681a9c1317e924526a47d128273925b63550','2026-06-18 04:25:33','2026-06-18 03:26:24','2026-06-18 02:25:33'),(4,20,'47d36df9e6b3268491dd309743d69f745233a03c3d61cdea48fa722166d602e0','2026-06-18 04:26:24','2026-06-18 03:27:08','2026-06-18 02:26:24'),(5,20,'394492b41a5366ba9114f2310c0ad4966e5644da88ea433d90e81dad4ac44e51','2026-06-20 09:35:00','2026-06-20 08:36:18','2026-06-20 07:35:00'),(6,24,'a4545c499e6f0e16b5281dd58e01519bf4fb89c0e41f0bd12f3eb0a6f91726e0','2026-06-21 17:25:35','2026-06-21 16:27:55','2026-06-21 15:25:35');
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_badges`
--

DROP TABLE IF EXISTS `student_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `badge_type` varchar(50) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_badge` (`student_id`,`badge_type`),
  CONSTRAINT `student_badges_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_badges`
--

LOCK TABLES `student_badges` WRITE;
/*!40000 ALTER TABLE `student_badges` DISABLE KEYS */;
INSERT INTO `student_badges` VALUES (1,20,'first_lesson','2026-06-15 03:03:54'),(2,20,'course_complete','2026-06-15 03:03:54'),(5,21,'first_lesson','2026-06-15 03:37:41'),(9,21,'course_complete','2026-06-15 03:45:46'),(10,22,'first_lesson','2026-06-16 10:38:22'),(14,22,'course_complete','2026-06-16 11:03:34');
/*!40000 ALTER TABLE `student_badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `study_sessions`
--

DROP TABLE IF EXISTS `study_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `study_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `seconds_spent` int(11) NOT NULL DEFAULT 0,
  `session_date` date NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_daily_session` (`student_id`,`lesson_id`,`session_date`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `study_sessions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `study_sessions_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `study_sessions`
--

LOCK TABLES `study_sessions` WRITE;
/*!40000 ALTER TABLE `study_sessions` DISABLE KEYS */;
INSERT INTO `study_sessions` VALUES (1,20,6,92,'2026-06-15','2026-06-15 04:39:14'),(2,20,7,179,'2026-06-15','2026-06-15 03:15:45'),(3,21,6,61,'2026-06-15','2026-06-15 03:46:00'),(4,21,8,107,'2026-06-15','2026-06-15 03:46:40'),(7,21,10,36,'2026-06-15','2026-06-15 03:47:16'),(9,22,6,34,'2026-06-16','2026-06-16 10:41:25'),(11,22,7,625,'2026-06-16','2026-06-16 10:58:06'),(12,22,8,294,'2026-06-16','2026-06-16 11:03:05'),(13,22,10,22,'2026-06-16','2026-06-16 11:03:34'),(14,20,11,528,'2026-06-16','2026-06-16 13:57:36'),(21,22,11,19,'2026-06-16','2026-06-16 14:15:40'),(22,20,11,11,'2026-06-18','2026-06-18 02:27:51'),(23,22,11,482,'2026-06-21','2026-06-21 16:57:06'),(25,22,11,2068,'2026-06-23','2026-06-23 17:24:42');
/*!40000 ALTER TABLE `study_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `role` enum('promoter','teacher','student') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_approved` tinyint(1) NOT NULL DEFAULT 1,
  `lang` varchar(5) DEFAULT 'fr',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'promoter@studyvibe.edu','$2y$10$ArvXG82lO1UhOE035thJ0.kuBE/cs9ERQdwbeubhBSVlt2YKR44J2','Prof. Remy Magloire',NULL,'promoter','2026-06-14 11:58:51','2026-06-15 14:02:44',1,1,'en'),(2,'teacher@studyvibe.edu','$2y$10$sSL.CyqC0FhgxmRk5Zvb8ewpl23EVEaFkg3uXIfl7z4Ea3JPmZ/xW','Prof. Thomas Messi Nguele',NULL,'teacher','2026-06-14 11:58:51','2026-06-21 14:41:18',1,1,'fr'),(3,'student@studyvibe.edu','$2y$10$FzaQnla9XN2Gg3jSwdyl4Ozxr4.9EPJLU8B2c4E.k.LixX.OHLT0m','Ngankeu Takou Daniel Wilfried',NULL,'student','2026-06-14 11:58:51','2026-06-21 14:41:18',1,1,'fr'),(4,'jean@gmail.com','$2y$10$TF3QoRXJ38I8p4AmsfjhYuL82Y7lXiHIYOm.pXMKOIZ.MIuCl/GkC','Jean',NULL,'student','2026-06-14 12:07:38','2026-06-15 14:02:44',1,1,'fr'),(5,'kamguiaaube@gmail.com','$2y$10$xrD3CfnwCVN3L3vkOnchj.GtBjuhFQhLktCZEP4jaHSvwk8wt2l2u','Toi',NULL,'student','2026-06-14 12:08:29','2026-06-15 14:02:44',1,1,'fr'),(6,'brandonsicoding4@gmail.com','$2y$10$ZCt9if.lSZNEmyEx3dmLeeZy7ZmN1iF5Xjy5deCxtmmWYOOyT1sGm','GRIOTE Project-Africa',NULL,'student','2026-06-14 12:09:03','2026-06-15 14:02:44',1,1,'fr'),(7,'jeean@gmail.com','$2y$10$qvO6x5uLd6SOF0mVFchlL.8H7kCQhcpvr3agzZCQ1734s3R6W38iq','Jean',NULL,'student','2026-06-14 12:09:10','2026-06-15 14:02:44',1,1,'fr'),(8,'test@gmail.com','$2y$10$uKgJsu744ybwxQzI.ELEy.MxyjO8DCq0WBC9btwYqbgpk9kym3CIq','test',NULL,'student','2026-06-14 12:09:41','2026-06-15 14:02:44',1,1,'fr'),(9,'tchakuikengnelandrynoe@gmail.com','$2y$10$19R6lL3V/wkJz6AfntfP/eeG/kFVNaSHyFP6F9g9JhP6X3Ja.SYOa','Cisco',NULL,'student','2026-06-14 12:09:44','2026-06-15 14:02:44',1,1,'fr'),(10,'admin@test.com','$2y$10$1iBIYvkKz9sXfZv9d4oiaeYdUXs8RA/PTnXKPPQ1chM6C5obui6Fe','Gold',NULL,'student','2026-06-14 12:11:03','2026-06-15 14:02:44',1,1,'fr'),(11,'princessetherese10@gmail.com','$2y$10$5./pNjYrnFoag4T.CNNdkeqnWs9yBWo8wg43CeUdDROO8arHVsoMi','Tina alice',NULL,'student','2026-06-14 12:11:55','2026-06-15 14:02:44',1,1,'fr'),(12,'marie@gmail.com','$2y$10$RfpChQFF/Y497Rbc6NuNAOpkF0VsP2G4S5Ch9I1XX5HKkeepF6oyG','Marie Curie',NULL,'student','2026-06-14 12:12:51','2026-06-15 14:02:44',1,1,'fr'),(13,'m20410660@gmail.com','$2y$10$HfyjMUQnso4Y7aqkOom6EO7kINis5gMSo9JPjR0eACptfKCnK8td2','Bob',NULL,'student','2026-06-14 12:13:45','2026-06-15 14:02:44',1,1,'fr'),(14,'louise@gmail.com','$2y$10$qOVCtlV0Tdv0MGj3wVGv/.BKyhycCzY3pj/usezXZ8v3rudPQSgIq','Louise',NULL,'student','2026-06-14 12:15:04','2026-06-15 14:02:44',1,1,'fr'),(15,'bba@gmail.com','$2y$10$FhAWWC37jy8se8qF/vz0guzJSdauyY4me85oHmTmYOqkk6TAPBAYS','Esc1',NULL,'student','2026-06-14 12:16:12','2026-06-15 14:02:44',1,1,'fr'),(16,'ekotoemma5@gmail.com','$2y$10$KFkpyKJRrytyqGugByW39OAKqhlmgDVpefz5t2WomFZZj42Kufwge','T',NULL,'student','2026-06-14 12:16:20','2026-06-15 14:02:44',1,1,'fr'),(17,'kloesayissi@gmail.com','$2y$10$BD7p5XwLFSsTdB1jfE0SHeOgPvb.P/./lRLuwAj2.hvhGrx3tCCe.','Bidjogo Manuela',NULL,'student','2026-06-14 12:17:29','2026-06-15 14:02:44',1,1,'fr'),(18,'gg@gmail.com','$2y$10$Df3aRc1pAUpxxs3k5pbPJ.S1RnvNxoELMOUXy.k4vqEeh8IMiyh2G','john',NULL,'student','2026-06-14 12:27:48','2026-06-15 14:02:44',1,1,'fr'),(19,'rrrdxxxx@gmail.com','$2y$10$B1euFJBpZ1zBx.GhgnIHwOBl9y3X2IGo2C7We3/rDwd0llaOJMQAS','Frcxd',NULL,'student','2026-06-14 12:30:01','2026-06-15 14:02:44',1,1,'fr'),(20,'wilfried.uy1@gmail.com','$2y$10$ArvXG82lO1UhOE035thJ0.kuBE/cs9ERQdwbeubhBSVlt2YKR44J2','NGANKEU TAKOU DANIEL WILFRIED','35f98d6090a54b6b5d94c78d85d5ab6d.png','student','2026-06-14 12:36:05','2026-06-15 14:02:44',1,1,'fr'),(21,'adelinetakou04@gmail.com','$2y$10$9dXopPvRHjmAYierhhNic.vODKOOek3SDEldb/7txAetpWDXwTAN.','TANKE ADELE MIKELLE',NULL,'student','2026-06-15 03:32:53','2026-06-15 14:02:44',1,1,'fr'),(22,'danyhardi06@gmail.com','$2y$10$ArvXG82lO1UhOE035thJ0.kuBE/cs9ERQdwbeubhBSVlt2YKR44J2','Fokoua Michael Cedric','b8fb4ea5684dd6789fb708f32be821a6.png','teacher','2026-06-15 13:40:02','2026-06-15 14:40:41',1,1,'fr'),(23,'wilfried.ngankeu@facsciences-uy1.cm','$2y$10$SGK.5Cu7Qo3i.e1/tARPFuP8gvJX8pPNZT8Pzz4H8em4lVLFvxa5a','Takou Rochelle',NULL,'student','2026-06-16 11:27:34','2026-06-16 12:27:58',1,1,'fr'),(24,'danielwilfriedtakou@gmail.com','$2y$10$ArvXG82lO1UhOE035thJ0.kuBE/cs9ERQdwbeubhBSVlt2YKR44J2','Dr. Tapamo',NULL,'teacher','2026-06-16 11:40:37','2026-06-16 12:40:37',1,1,'fr');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `video_notes`
--

DROP TABLE IF EXISTS `video_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `video_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `timestamp_seconds` int(11) NOT NULL,
  `note_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `video_notes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `video_notes_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_notes`
--

LOCK TABLES `video_notes` WRITE;
/*!40000 ALTER TABLE `video_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `video_notes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-25  0:36:13
