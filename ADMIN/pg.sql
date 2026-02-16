-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: pg
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

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
-- Table structure for table `applicants`
--

DROP TABLE IF EXISTS `applicants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicants`
--

LOCK TABLES `applicants` WRITE;
/*!40000 ALTER TABLE `applicants` DISABLE KEYS */;
INSERT INTO `applicants` VALUES (1,'general','ranog','a@gmail.com','2026-01-07 14:21:19'),(3,'general','ranog','aa@gmail.com','2026-01-07 14:23:01'),(4,'general','ranog','gaddafiumar4445@gmail.com','2026-01-07 14:29:13');
/*!40000 ALTER TABLE `applicants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `status` enum('Draft','Submitted','Admitted','Rejected') DEFAULT 'Draft',
  `current_step` int(11) DEFAULT 1,
  `submitted_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `application_number` (`application_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applications`
--

LOCK TABLES `applications` WRITE;
/*!40000 ALTER TABLE `applications` DISABLE KEYS */;
INSERT INTO `applications` VALUES (1,1,'JOSTUM/PG/2026/0448B','Admitted',10,'2026-01-06 18:11:32','2026-01-10 13:48:23'),(2,2,NULL,'Draft',10,NULL,'2026-01-06 16:58:11'),(3,3,NULL,'Draft',10,NULL,'2026-01-06 17:04:04'),(4,1,'JOSTUM/PG/2026/7D59F','Submitted',10,'2026-01-06 19:26:42','2026-01-06 18:26:42'),(5,1,'JOSTUM/PG/2026/2CE6D','Submitted',11,'2026-01-07 10:10:52','2026-01-07 09:10:52'),(6,1,'JOSTUM/PG/2026/2880D','Submitted',9,'2026-01-07 10:15:04','2026-01-07 09:15:04'),(7,1,'JOSTUM/PG/2026/B5ABA','Submitted',10,'2026-01-07 19:14:34','2026-01-07 18:14:34'),(8,4,'JOSTUM/PG/2026/C7052','Submitted',10,'2026-01-07 13:10:17','2026-01-07 12:10:17'),(9,1,'PG-2026-44237','Submitted',10,'2026-01-07 19:58:36','2026-01-07 18:58:36'),(11,16,'PG-2026-6E55E','Admitted',10,'2026-01-08 15:19:29','2026-01-10 18:02:08'),(12,17,'PG-2026-9DDDD','Admitted',10,'2026-01-08 17:46:47','2026-01-10 18:29:24');
/*!40000 ALTER TABLE `applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
  `comments` TEXT DEFAULT NULL,
  PRIMARY KEY (`doc_id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (10,1,'olevel_2','uploads/1/olevel_2_1767717181.pdf','2026-01-06 16:33:01'),(21,2,'passport','uploads/2/passport_1767718679.png','2026-01-06 16:57:59'),(22,2,'olevel_1','uploads/2/olevel_1_1767718679.jpeg','2026-01-06 16:57:59'),(23,2,'degree','uploads/2/degree_1767718679.jpeg','2026-01-06 16:57:59'),(24,2,'transcript','uploads/2/transcript_1767718679.pdf','2026-01-06 16:57:59'),(25,2,'nysc','uploads/2/nysc_1767718679.pdf','2026-01-06 16:57:59'),(26,2,'proposal','uploads/2/proposal_1767718679.pdf','2026-01-06 16:57:59'),(27,3,'passport','uploads/3/passport_1767719037.jpeg','2026-01-06 17:03:57'),(28,3,'olevel_1','uploads/3/olevel_1_1767719037.pdf','2026-01-06 17:03:57'),(29,3,'degree','uploads/3/degree_1767719037.pdf','2026-01-06 17:03:57'),(30,3,'transcript','uploads/3/transcript_1767719037.pdf','2026-01-06 17:03:57'),(31,3,'nysc','uploads/3/nysc_1767719037.jpeg','2026-01-06 17:03:57'),(32,3,'proposal','uploads/3/proposal_1767719037.pdf','2026-01-06 17:03:57'),(33,1,'passport','uploads/1/passport_1767719484.jpeg','2026-01-06 17:11:24'),(34,1,'olevel_1','uploads/1/olevel_1_1767719484.jpeg','2026-01-06 17:11:24'),(35,1,'degree','uploads/1/degree_1767719484.pdf','2026-01-06 17:11:24'),(36,1,'transcript','uploads/1/transcript_1767719484.pdf','2026-01-06 17:11:24'),(37,1,'nysc','uploads/1/nysc_1767719484.pdf','2026-01-06 17:11:24'),(38,1,'proposal','uploads/1/proposal_1767719484.pdf','2026-01-06 17:11:24'),(57,4,'passport','uploads/4/passport_1767723995.jpeg','2026-01-06 18:26:35'),(58,4,'olevel_1','uploads/4/olevel_1_1767723995.jpeg','2026-01-06 18:26:35'),(59,4,'degree','uploads/4/degree_1767723995.pdf','2026-01-06 18:26:35'),(60,4,'transcript','uploads/4/transcript_1767723995.pdf','2026-01-06 18:26:35'),(61,4,'nysc','uploads/4/nysc_1767723995.pdf','2026-01-06 18:26:35'),(62,4,'proposal','uploads/4/proposal_1767723995.pdf','2026-01-06 18:26:35'),(63,5,'passport','uploads/passports/1767725852_a182aaf85f53d2cb.jpg','2026-01-06 18:57:32'),(64,5,'olevel_1','uploads/olevel/1767725852_8c312945dbd22eef.jpg','2026-01-06 18:57:32'),(65,5,'degree','uploads/degree/1767725852_4858b3e922093e3e.jpg','2026-01-06 18:57:32'),(66,5,'transcript','uploads/transcripts/1767725852_cbe6bc562502c3e7.jpg','2026-01-06 18:57:32'),(67,5,'nysc','uploads/nysc/1767725852_8b461ce058587533.jpg','2026-01-06 18:57:33'),(68,5,'proposal','uploads/proposals/1767725853_5b86cdfd6ff14b66.pdf','2026-01-06 18:57:33'),(69,8,'passport','uploads/passports/1767787785_a9869e6d9d40b540.jpg','2026-01-07 12:09:45'),(70,8,'olevel_1','uploads/olevel/1767787785_dfeb7f2c8ec3c4bb.jpg','2026-01-07 12:09:45'),(71,8,'degree','uploads/degree/1767787785_aa20edc9e3032c20.jpg','2026-01-07 12:09:45'),(72,8,'transcript','uploads/transcripts/1767787785_df6e6a62ca74ee34.jpg','2026-01-07 12:09:45'),(73,8,'nysc','uploads/nysc/1767787785_0b72b72d7dbe6484.jpg','2026-01-07 12:09:45'),(74,8,'proposal','uploads/proposals/1767787785_0468f28c8a13a6c7.pdf','2026-01-07 12:09:45'),(82,7,'passport','uploads/passports/1767801810_7322987ace2bd930.jpg','2026-01-07 16:03:30'),(83,7,'olevel_1','uploads/olevel/1767801810_e1028d21cfa70f9c.jpg','2026-01-07 16:03:30'),(84,7,'olevel_2','uploads/olevel/1767801810_1e67fa41f918b49a.jpg','2026-01-07 16:03:30'),(85,7,'degree','uploads/degree/1767801810_eea4dd2a7e1ccea3.jpg','2026-01-07 16:03:30'),(86,7,'transcript','uploads/transcripts/1767801810_eb9e7ff6bbf76e2c.jpg','2026-01-07 16:03:30'),(87,7,'nysc','uploads/nysc/1767801810_e6745676c25b3df5.jpg','2026-01-07 16:03:30'),(88,7,'proposal','uploads/proposals/1767801810_01074594cd92b683.pdf','2026-01-07 16:03:30'),(89,9,'passport','uploads/passports/1767812275_e1932e51bc331317.jpg','2026-01-07 18:57:55'),(90,9,'olevel_1','uploads/olevel/1767812275_6046d4186744cf19.jpg','2026-01-07 18:57:55'),(91,9,'degree','uploads/degree/1767812275_bd0542ee00a7dd14.jpg','2026-01-07 18:57:55'),(92,9,'transcript','uploads/transcripts/1767812275_7ae429189e91dfa4.jpg','2026-01-07 18:57:55'),(93,9,'nysc','uploads/nysc/1767812275_fb953a076ee2358b.pdf','2026-01-07 18:57:55'),(104,11,'passport','uploads/passports/1767881445_5970034efd40ae1e.jpg','2026-01-08 14:10:45'),(105,11,'olevel_1','uploads/olevel/1767881445_e6e09f3d06c378d6.pdf','2026-01-08 14:10:45'),(106,11,'degree','uploads/degree/1767881445_63aa99206217080b.pdf','2026-01-08 14:10:45'),(107,11,'transcript','uploads/transcripts/1767881445_3ce83064e984fd38.pdf','2026-01-08 14:10:45'),(108,11,'nysc','uploads/nysc/1767881445_2b5c6f0fe26ba64e.pdf','2026-01-08 14:10:45'),(109,12,'passport','uploads/passports/1767890681_cf75ab73b2002724.jpg','2026-01-08 16:44:41'),(110,12,'olevel_1','uploads/olevel/1767890681_417808f1165492d0.pdf','2026-01-08 16:44:41'),(111,12,'olevel_2','uploads/olevel/1767890681_0a7e6ccab28d628d.pdf','2026-01-08 16:44:41'),(112,12,'degree','uploads/degree/1767890681_e1cb17b853236f19.pdf','2026-01-08 16:44:41'),(113,12,'transcript','uploads/transcripts/1767890681_e115770165dc5ff6.pdf','2026-01-08 16:44:41'),(114,12,'nysc','uploads/nysc/1767890681_fff373e03236aa0d.pdf','2026-01-08 16:44:41');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `higher_education`
--

DROP TABLE IF EXISTS `higher_education`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `higher_education` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `highest_qualification` varchar(50) DEFAULT NULL,
  `course_study` varchar(150) DEFAULT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `grad_year` int(11) DEFAULT NULL,
  `cgpa` decimal(4,2) DEFAULT NULL,
  `mode_study` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `higher_education_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `higher_education`
--

LOCK TABLES `higher_education` WRITE;
/*!40000 ALTER TABLE `higher_education` DISABLE KEYS */;
INSERT INTO `higher_education` VALUES (1,1,'MSc','Computer Science','Modibbo',2001,4.24,'FT'),(4,2,'MSc','Computer Science','Modibbo',2001,4.24,'FT'),(5,3,'BSc','Computer Science','KOKO',2021,4.24,'FT'),(8,4,'BSc','Computer Science','Modibbo',2022,4.24,'FT'),(14,8,'MSc','Computer ','Modibbo',2001,5.00,'FT'),(18,7,'BSc','Computer Science','Modibbo Adama University',2022,4.99,'PT'),(22,9,'BSc','Computer Science','Modibbo Adama University',2022,4.99,'PT'),(24,11,'BSc','Computer Science','Modibbo Adama University',2022,4.99,'FT'),(25,12,'MSc','Computer Science ','Modibbo Adama University ',2024,4.82,'FT');
/*!40000 ALTER TABLE `higher_education` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nysc_details`
--

DROP TABLE IF EXISTS `nysc_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nysc_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `nysc_status` varchar(50) DEFAULT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `completion_year` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `nysc_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nysc_details`
--

LOCK TABLES `nysc_details` WRITE;
/*!40000 ALTER TABLE `nysc_details` DISABLE KEYS */;
INSERT INTO `nysc_details` VALUES (1,1,'Exempted','84898938',2022),(5,2,'Exempted','84898938',2022),(6,3,'Completed','8938894389q89',2022),(9,4,'Completed','84898938',3033),(15,8,'Completed','Ajdjdjjd',2001),(16,7,'Completed','8488488484',2023),(18,9,'Completed','8488488484',2022),(20,11,'Completed','8488488484',2000),(21,12,'Completed','A83773737',2025);
/*!40000 ALTER TABLE `nysc_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `olevel_exams`
--

DROP TABLE IF EXISTS `olevel_exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `olevel_exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `sitting_number` tinyint(4) NOT NULL COMMENT '1 for First Sitting, 2 for Second Sitting',
  `exam_type` enum('WAEC','NECO','NABTEB','GCE') NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `exam_year` year(4) NOT NULL,
  `exam_number` varchar(50) NOT NULL COMMENT 'Registration Number',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sitting` (`application_id`,`sitting_number`),
  CONSTRAINT `fk_application_olevel` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olevel_exams`
--

LOCK TABLES `olevel_exams` WRITE;
/*!40000 ALTER TABLE `olevel_exams` DISABLE KEYS */;
INSERT INTO `olevel_exams` VALUES (5,7,1,'NECO','GDSS ARMY BARRACKS',2022,'A8484884','2026-01-07 15:32:28'),(6,7,2,'NECO','GDSS GURIN',2023,'Ablallal9949949','2026-01-07 15:32:28'),(13,9,1,'NABTEB','GDSS ARMY BARRACKS',2012,'A8484884','2026-01-07 18:56:32'),(15,11,1,'WAEC','GDSS ARMY BARRACKS',2019,'A84848849','2026-01-08 13:40:29'),(16,11,2,'NABTEB','GDSS GURIN',2011,'Ablallal9949949','2026-01-08 13:40:29'),(17,12,1,'WAEC','GDSS ARMY BARRACKS YOLA',2018,'483773737','2026-01-08 16:35:47'),(18,12,2,'NECO','Conel Isa College Yola',2018,'837737373','2026-01-08 16:35:47');
/*!40000 ALTER TABLE `olevel_exams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `olevel_results`
--

DROP TABLE IF EXISTS `olevel_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `olevel_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `grade` char(2) NOT NULL COMMENT 'e.g., A1, B3, C6, F9',
  PRIMARY KEY (`id`),
  KEY `idx_subject` (`subject_name`),
  KEY `fk_exam_results` (`exam_id`),
  CONSTRAINT `fk_exam_results` FOREIGN KEY (`exam_id`) REFERENCES `olevel_exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olevel_results`
--

LOCK TABLES `olevel_results` WRITE;
/*!40000 ALTER TABLE `olevel_results` DISABLE KEYS */;
INSERT INTO `olevel_results` VALUES (25,5,'Applied Electricity','C5'),(26,5,'Health Science','C4'),(27,5,'Auto Electrical Work','A1'),(28,6,'Health Science','C6'),(29,6,'Physical & Health Education','F9'),(30,6,'Music','B3'),(31,13,'Computer Studies','C4'),(34,15,'ICT','B2'),(35,15,'Auto Mechanics','D7'),(36,15,'Biology','D7'),(37,15,'Shorthand','C5'),(38,15,'Basic Electronics','B2'),(39,15,'Woodwork','B3'),(40,15,'Business Management','F9'),(41,15,'Technical Drawing','B3'),(42,15,'English Language','B2'),(43,16,'ICT','D7'),(44,16,'Auto Electrical Work','D7'),(45,16,'Mathematics','E8'),(46,16,'Clothing and Textiles','B3'),(47,16,'Geography','C5'),(48,16,'Physical & Health Education','C5'),(49,17,'Mathematics','A1'),(50,18,'Civic Education','C6'),(51,18,'Auto Electrical Work','B3'),(52,18,'Biology','B2');
/*!40000 ALTER TABLE `olevel_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `olevel_sittings`
--

DROP TABLE IF EXISTS `olevel_sittings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `olevel_sittings` (
  `sitting_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `sitting_number` int(11) NOT NULL CHECK (`sitting_number` in (1,2)),
  `exam_year` int(11) DEFAULT NULL,
  `exam_type` varchar(50) DEFAULT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `exam_type_other` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`sitting_id`),
  UNIQUE KEY `application_id` (`application_id`,`sitting_number`),
  CONSTRAINT `olevel_sittings_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olevel_sittings`
--

LOCK TABLES `olevel_sittings` WRITE;
/*!40000 ALTER TABLE `olevel_sittings` DISABLE KEYS */;
INSERT INTO `olevel_sittings` VALUES (6,2,1,2001,'WAEC','lkaklkakl',NULL),(7,3,1,2022,'NECO','dlakklalk',NULL),(8,1,1,2001,'WAEC','lkaklkakl',NULL),(11,4,1,2022,'WAEC','lkaklkakl',NULL),(12,8,1,2000,'WAEC','Hdhdhd',NULL);
/*!40000 ALTER TABLE `olevel_sittings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_details`
--

DROP TABLE IF EXISTS `personal_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `other_name` varchar(100) DEFAULT NULL,
  `dob` date NOT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `state_origin` varchar(100) DEFAULT NULL,
  `lga` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `personal_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_details`
--

LOCK TABLES `personal_details` WRITE;
/*!40000 ALTER TABLE `personal_details` DISABLE KEYS */;
INSERT INTO `personal_details` VALUES (1,1,'alalla','allaak','','2026-01-06','Male','Nigerian','Adamawa','Mayo-Belwa','+2349042340091','Karewa ward Bachure near jibwis mosque\r\nBachure Jimeta Yola North Adamawa State'),(4,2,'Umar','Rilwanu','','2026-01-06','Female','Nigerian','Adamawa','Jada','+2349042340091','Karewa ward Bachure near jibwis mosque'),(5,3,'lalklakl','klklaklalk','','2026-01-06','Female','Nigerian','Benue','Buruku','09042340091','No. 15 Jimeta'),(8,4,'lalal','allalk','','2026-01-06','Male','Nigerian','Benue','Makurdi','+2349042340091','Karewa ward Bachure near jibwis mosque\r\nBachure Jimeta Yola North Adamawa State'),(16,7,'GADDAFI','JOSTUM-PG','','2026-01-07','Male','Nigerian','Delta','Okpe','09042340091','Bachure gurum pawo'),(17,8,'Umar','Rilwanu','','2026-01-07','Male','Nigerian','Adamawa','Numan','+2349042340091','Karewa ward Bachure near jibwis mosque'),(24,9,'GADDAFIa','lalalal','','2026-01-07','Male','Nigerian','Enugu','Nkanu West','09042340091','kjakjakj'),(26,11,'GADDAFI','JOSTUM-PG','','2026-01-08','Male','Nigerian','Borno','Gwoza','09042340091','halla all alalla'),(27,12,'Umar','Gaddafi','','2026-01-08','Male','Nigerian','Adamawa','Ganye','+2349042340091','Karewa ward Bachure near jibwis mosque\r\nBachure Jimeta Yola North Adamawa State');
/*!40000 ALTER TABLE `personal_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programme_choices`
--

DROP TABLE IF EXISTS `programme_choices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programme_choices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `faculty` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `degree_type` varchar(50) DEFAULT NULL,
  `mode_of_study` varchar(20) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `programme_choices_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programme_choices`
--

LOCK TABLES `programme_choices` WRITE;
/*!40000 ALTER TABLE `programme_choices` DISABLE KEYS */;
INSERT INTO `programme_choices` VALUES (1,1,'Sciences','Computer Science','MSc','Full Time','Computer Science'),(4,2,'Sciences','Computer Science','MSc','Full Time','Computer Science'),(5,3,'Sciences','Computer Science','PGD','Full Time','Computer Science'),(8,4,'Sciences','Computer Science','PGD','Full Time','Computer Science'),(14,7,'Sciences','Computer Science','PGD','Full Time','Computer Science'),(15,8,'Sciences','Computer Science','PGD','Full Time','Computer Science'),(20,9,'Sciences','Computer Science','PGD','Full Time','Computer Science'),(22,11,'Faculty of Science','Department of Computer Science','MSc','Full Time','Computer Science'),(23,12,'Faculty of Science','Department of Computer Science','MSc','Full Time','Computer Science');
/*!40000 ALTER TABLE `programme_choices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referees`
--

DROP TABLE IF EXISTS `referees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referees` (
  `referee_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`referee_id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `referees_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referees`
--

LOCK TABLES `referees` WRITE;
/*!40000 ALTER TABLE `referees` DISABLE KEYS */;
INSERT INTO `referees` VALUES (5,2,'Rilwanu Umar','dklakll','klaklaklk','rilwanumar29@gmail.com','09042340091'),(6,3,'dlkallka','klakklakl','alklaklakl','gaddafi@gmail.com','09042340091'),(8,1,'Rilwanu Umar','dklakll','klaklaklk','rilwanumar29@gmail.com','09042340091'),(16,4,'Rilwanu Umar','dklakll','JOSTUM','rilwanumar29@gmail.com','09042340091'),(17,8,'Rilwanu Umar','Professor ','JOSTUM','rilwanumar29@gmail.com','09042340091'),(19,7,'Mr. Kenny John','Professor','JOstum','kenny@gmail.com','09042340091'),(20,9,'Mr. Kenny John','Professor','JOstum','kenny@gmail.com','09042340091'),(24,11,'Mr. Kenny John','Professor','JOstum','kenny@gmail.com','09042340091'),(25,12,'Eng. Dr. Omolaye Philip ','Director ICT','JOSTUM','omolayephilip@uam.com','0909566546');
/*!40000 ALTER TABLE `referees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_details`
--

DROP TABLE IF EXISTS `research_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `research_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `research_area` varchar(255) DEFAULT NULL,
  `reason_for_choosing` text DEFAULT NULL,
  `statement_of_purpose` text DEFAULT NULL,
  `career_objectives` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `research_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_details`
--

LOCK TABLES `research_details` WRITE;
/*!40000 ALTER TABLE `research_details` DISABLE KEYS */;
INSERT INTO `research_details` VALUES (1,1,'lalaklk','laklaklakl','klaklakla','akkldklaklk'),(4,2,'lalaklk','laklaklakl','klaklakla','akkldklaklk'),(5,3,'klaklakl','klaklklaklak','alklaklakl','klaklaklakl'),(8,4,'alal','lallal','lalalal','lalaklal'),(14,8,'Bdbdhdb','Bdhhdhdhdhd','Bdhdhhdhddhhdhdh','Jdjdjdjdhehjd'),(15,7,'LALAKKLAKKL','daklklkl','klalklaklkl','akklaklakl'),(16,9,'klakllkall','klkaklalk','laklaklkl','aklklaklak'),(18,11,'Artificial Intelligence and Machine Learning','Because it is the future','Statement of Purpose: AI and Machine Learning\r\nMy fascination with Artificial Intelligence began with a simple observation: while human intuition is profound, it is often limited by the scale of data it can process. In an era where information is generated at an exponential rate, the ability to transform raw data into actionable intelligence is not just a technical challenge—it is a societal necessity. My decision to pursue a specialization in AI and Machine Learning is driven by a commitment to building systems that enhance human capability and solve bottlenecks in critical sectors like healthcare and sustainable energy.\r\n\r\nThe Spark of Interest\r\nDuring my undergraduate studies in [Your Major, e.g., Computer Science], I was introduced to the mathematical elegance of neural networks. I realized that ML is the bridge between theoretical logic and real-world impact. Whether it was developing a simple linear regression model to predict housing prices or experimenting with Convolutional Neural Networks (CNNs) for image recognition, I found the iterative process of training and optimization deeply rewarding.\r\n\r\nProfessional and Academic Growth\r\nIn my recent project involving [mention a specific project, e.g., natural language processing or predictive analytics], I encountered the \"black box\" problem of AI. This experience taught me that creating a model is only half the battle; ensuring that the model is ethical, interpretable, and scalable is the true challenge. I am eager to transition from a consumer of AI tools to an architect of AI solutions, focusing on how Deep Learning and Reinforcement Learning can be applied to [mention your area of interest, e.g., autonomous systems or personalized medicine].\r\n\r\nWhy This Specialization?\r\nAI and ML represent the \"new electricity\" of the 21st century. I am particularly drawn to this field because it is inherently interdisciplinary. It demands a mastery of high-level mathematics, sophisticated programming, and an understanding of human psychology. I am motivated by the prospect of developing algorithms that can identify patterns invisible to the human eye, thereby democratizing access to expertise in fields where human specialists are scarce.\r\n\r\nFuture Goals\r\nMy immediate goal is to master the deployment of large-scale ML models within cloud environments. Long-term, I aspire to lead research initiatives that focus on \"AI for Good,\" specifically in developing low-resource language models that can bridge communication gaps in developing regions.\r\n\r\nThe [University Name] program, with its focus on [Specific Lab or Course Name], is the ideal environment for me to refine these skills. I am ready to contribute my technical foundation and my passion for innovation to your academic community.','Ambitious AI graduate with a strong foundation in Deep Learning frameworks (PyTorch, TensorFlow) and MLOps. Seeking a Junior ML Engineer position to design and deploy scalable predictive models, focusing on optimizing model latency and accuracy to drive data-driven decision-making.'),(19,12,'AI & Machine Learning ','Because it\'s the future ','I am motivated to pursue advanced study in Artificial Intelligence and Machine Learning because of my strong interest in building intelligent systems that can learn from data, adapt to real-world complexity, and drive meaningful innovation across industries. My academic background and hands-on experience have helped me develop a solid foundation in mathematics, statistics, programming, and data analysis, while exposure to algorithms, predictive modeling, and problem-solving has strengthened my analytical thinking. I am particularly fascinated by how machine learning enables systems to identify patterns, make informed decisions, and continuously improve performance, offering powerful solutions in areas such as healthcare, finance, automation, and sustainable development. Through this program, I aim to deepen my understanding of core AI concepts including supervised and unsupervised learning, deep learning, natural language processing, and ethical AI, while also gaining practical experience through research and real-world projects. I aspire to contribute to the development of responsible, scalable, and impactful AI technologies that enhance human capabilities and address complex societal challenges. This field aligns with my long-term goal of becoming a skilled AI professional who combines technical expertise with ethical awareness to create intelligent solutions that deliver lasting value.\r\n','To be a better Software engineer ');
/*!40000 ALTER TABLE `research_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'mag@gmail.com','$2y$10$3jeR6XgfL3mvbYjBzHjmfO2k6ueaJ84193CTlzqTgA7/O3MUWegcK','2026-01-06 16:12:21',NULL,NULL),(2,'emmanuelpaul4445@gmail.com','$2y$10$m6yR/tVQSEbdOydhfnVzDOKdAv6bPpIjooc2Otzm3bAlMlalmdRJO','2026-01-06 16:54:29',NULL,NULL),(3,'amag@gmail.com','$2y$10$wW2zGVasOwqI.XTYEiw9Ye1u66LZjOJDSyRLd6r9hNLLIwOHbvcji','2026-01-06 17:00:28',NULL,NULL),(4,'umgaddafi2@gmail.com','$2y$10$2fhzFGbST8bfPNG6nBzvnOtVSC0TUVHnulhzq4ngqHozBjHqatf86','2026-01-07 12:03:14',NULL,NULL),(5,'mg@gmail.com','$2y$10$IMgUoz/.oS7W7pNTJ6GFhOQ6PBhU823xqQXaUv4zOeh/ICZuHm17u','2026-01-07 19:10:05',NULL,NULL),(16,'gaddafiumar4445@gmail.com','$2y$10$dfyz8pisXS33WFXBTY1hjOWQbRaboHxnkmrQ3SiIT5LJLWtQgOXA6','2026-01-08 13:20:13',NULL,NULL),(17,'umgaddafi1@gmail.com','$2y$10$fepxKfByYQtkIPpywontWuY6ioozHiUpAnFYPZOYkgHB2JNZtcTZu','2026-01-08 16:23:44',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_experience`
--

DROP TABLE IF EXISTS `work_experience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_experience` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `employment_status` varchar(50) DEFAULT NULL,
  `employer` varchar(150) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `years_experience` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `work_experience_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_experience`
--

LOCK TABLES `work_experience` WRITE;
/*!40000 ALTER TABLE `work_experience` DISABLE KEYS */;
INSERT INTO `work_experience` VALUES (1,1,NULL,NULL,NULL,NULL),(4,2,NULL,NULL,NULL,NULL),(5,3,NULL,NULL,NULL,NULL),(8,4,NULL,NULL,NULL,NULL),(14,8,'Employed','Jostum','Programmer',5),(15,7,'Student',NULL,NULL,NULL),(16,9,'Employed','lakaklkl','klalkallk',7),(18,11,'Employed','Jostum','Programmer/Analyst',20),(19,12,'Employed','Access Bank','Customer Care',1);
/*!40000 ALTER TABLE `work_experience` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

--
-- Academic portal dashboard fields
--
ALTER TABLE `applications`
  ADD COLUMN `academic_session` varchar(50) DEFAULT NULL,
  ADD COLUMN `academic_status` varchar(30) DEFAULT NULL,
  ADD COLUMN `semester_week` int(11) DEFAULT NULL,
  ADD COLUMN `semester_total_weeks` int(11) DEFAULT NULL,
  ADD COLUMN `fee_total` int(11) DEFAULT 0,
  ADD COLUMN `fee_paid` int(11) DEFAULT 0,
  ADD COLUMN `next_fee_deadline` date DEFAULT NULL;

UPDATE `applications`
SET academic_session = '2024/2025 Session',
    academic_status = 'Active',
    semester_week = 6,
    semester_total_weeks = 14,
    fee_total = 240000,
    fee_paid = 160000,
    next_fee_deadline = '2026-02-28'
WHERE application_id = 1;

UPDATE `applications`
SET academic_session = '2024/2025 Session',
    academic_status = 'Active',
    semester_week = 5,
    semester_total_weeks = 14,
    fee_total = 240000,
    fee_paid = 120000,
    next_fee_deadline = '2026-03-05'
WHERE application_id = 11;

UPDATE `applications`
SET academic_session = '2024/2025 Session',
    academic_status = 'Active',
    semester_week = 6,
    semester_total_weeks = 14,
    fee_total = 240000,
    fee_paid = 90000,
    next_fee_deadline = '2026-03-05'
WHERE application_id = 12;

--
-- Table structure for table `supervisors`
--

DROP TABLE IF EXISTS `supervisors`;
CREATE TABLE `supervisors` (
  `supervisor_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `specialization_keywords` text DEFAULT NULL,
  `max_capacity` int(11) DEFAULT 8,
  `current_students` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`supervisor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `supervisors` WRITE;
INSERT INTO `supervisors` VALUES (1,NULL,NULL,'Dr. Esther I. Tarka','Irrigation, Sustainability, Agritech',8,1,'Active','2026-01-01 08:00:00');
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `current_stage` varchar(40) DEFAULT 'PROJECT_ACTIVE',
  `proposal_status` varchar(30) DEFAULT 'Pending',
  `report_status` varchar(30) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`project_id`),
  KEY `idx_project_student` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `projects` WRITE;
INSERT INTO `projects` VALUES (1,1,1,1,'Optimized Irrigation Scheduling for Sustainable Crop Yield','PROPOSAL_APPROVED','Approved','Pending','2026-01-01 09:00:00','2026-01-12 10:00:00');
UNLOCK TABLES;

--
-- Table structure for table `proposals`
--

DROP TABLE IF EXISTS `proposals`;
CREATE TABLE `proposals` (
  `proposal_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` varchar(30) DEFAULT 'Submitted',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  PRIMARY KEY (`proposal_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `proposals` WRITE;
INSERT INTO `proposals` VALUES (1,1,'uploads/supervision/proposal/proposal_20260110.pdf','Approved','2026-01-10 08:30:00',NULL,'2026-01-12 09:00:00','Good progress, proceed to chapter 1.');
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `version_no` int(11) DEFAULT 1,
  `status` varchar(30) DEFAULT 'Submitted',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  PRIMARY KEY (`report_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `reports` WRITE;
INSERT INTO `reports` VALUES (1,1,'uploads/supervision/chapter-1/chapter-1_20260114.pdf',1,'Submitted','2026-01-14 09:10:00',NULL,NULL,NULL);
UNLOCK TABLES;

--
-- Table structure for table `project_status_history`
--

DROP TABLE IF EXISTS `project_status_history`;
CREATE TABLE `project_status_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `project_status_history` WRITE;
INSERT INTO `project_status_history` VALUES
  (1,1,NULL,'PROJECT_ACTIVE',1,'STUDENT','Project created','2026-01-01 09:00:00'),
  (2,1,'PROJECT_ACTIVE','PROPOSAL_SUBMITTED',1,'STUDENT','Proposal uploaded','2026-01-10 08:30:00'),
  (3,1,'PROPOSAL_SUBMITTED','PROPOSAL_APPROVED',1,'SUPERVISOR','Proposal approved','2026-01-12 09:00:00'),
  (4,1,'PROPOSAL_APPROVED','REPORT_SUBMITTED',1,'STUDENT','Report upload: chapter-1','2026-01-14 09:10:00');
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(30) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_notify_user` (`user_id`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `notifications` WRITE;
INSERT INTO `notifications` VALUES
  (1,1,'Fees','Payment verification pending for January fees.','warning',0,'2026-01-18 09:00:00'),
  (2,1,'Supervisor Meeting','Supervisor meeting scheduled for 20 Jan 2026.','info',0,'2026-01-17 12:00:00'),
  (3,1,'Checklist','Proposal checklist update required.','info',1,'2026-01-15 15:30:00');
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` varchar(80) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_thread` (`thread_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `messages` WRITE;
INSERT INTO `messages` VALUES
  (1,'student-1-supervisor',2,1,'Dr. Tarka: Feedback on your proposal is ready.','2026-01-12 11:30:00',NULL),
  (2,'student-1-admin',3,1,'Dept Admin: Upload signed clearance form.','2026-01-16 10:15:00',NULL),
  (3,'student-1-library',4,1,'Library: Reminder to return borrowed book.','2026-01-14 08:20:00',NULL);
UNLOCK TABLES;

-- Dump completed on 2026-01-10 19:36:07

-- Google Authenticator (TOTP) fields
ALTER TABLE `users`
  ADD COLUMN `totp_secret` VARCHAR(64) NULL,
  ADD COLUMN `totp_enabled` TINYINT(1) DEFAULT 0,
  ADD COLUMN `totp_verified_at` DATETIME DEFAULT NULL;

-- Supervisor assignment schema (updated to match current admin UI)
DROP TABLE IF EXISTS `supervisor_profiles`;
CREATE TABLE `supervisor_profiles` (
  `supervisor_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `max_capacity` int DEFAULT 8,
  `current_students` int DEFAULT 0,
  `status` varchar(20) DEFAULT 'Active',
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `research_interests` text,
  `notes` text,
  `last_active` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `supervisor_students`;
CREATE TABLE `supervisor_students` (
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `current_chapter` varchar(50) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending Review',
  `last_submission` date DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `progress_pct` int DEFAULT 0,
  `supervisor_name` varchar(150) DEFAULT NULL,
  `notes` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Added for supervision workflow (2026-02-08)
ALTER TABLE `supervisor_students`
  ADD COLUMN `supervisor_user_id` int DEFAULT NULL,
  ADD COLUMN `student_user_id` int DEFAULT NULL,
  ADD COLUMN `application_id` int DEFAULT NULL,
  ADD COLUMN `application_number` varchar(50) DEFAULT NULL,
  ADD COLUMN `department_id` int DEFAULT NULL;

ALTER TABLE `supervisor_messages`
  ADD COLUMN `supervisor_user_id` int DEFAULT NULL,
  ADD COLUMN `student_user_id` int DEFAULT NULL,
  ADD COLUMN `sender_role` enum('SUPERVISOR','STUDENT') DEFAULT 'STUDENT',
  ADD COLUMN `subject` varchar(200) DEFAULT NULL;

ALTER TABLE `supervisor_milestones`
  ADD COLUMN `supervisor_user_id` int DEFAULT NULL,
  ADD COLUMN `student_user_id` int DEFAULT NULL,
  ADD COLUMN `application_id` int DEFAULT NULL,
  ADD COLUMN `acknowledged_at` datetime DEFAULT NULL;

DROP TABLE IF EXISTS `chapter_submissions`;
CREATE TABLE `chapter_submissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_user_id` int NOT NULL,
  `application_id` int DEFAULT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `chapter_no` tinyint NOT NULL,
  `chapter_label` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_ext` varchar(10) DEFAULT NULL,
  `status` enum('Submitted','Under Review','Changes Requested','Approved') NOT NULL DEFAULT 'Submitted',
  `supervisor_note` text,
  `supervisor_user_id` int DEFAULT NULL,
  `review_file_path` varchar(255) DEFAULT NULL,
  `version_no` int NOT NULL DEFAULT 1,
  `submitted_at` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chapter_student` (`student_user_id`),
  KEY `idx_chapter_number` (`chapter_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `student_notifications`;
CREATE TABLE `student_notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_user_id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_notify` (`student_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `student_tracking_updates`;
CREATE TABLE `student_tracking_updates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_user_id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `note` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'In Progress',
  `progress` int NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_tracking` (`student_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
