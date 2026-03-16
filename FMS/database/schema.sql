CREATE DATABASE IF NOT EXISTS freight_hr_system;
USE freight_hr_system;

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: freight_hr_system
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
-- Table structure for table `api_keys`
--

DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `api_token` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_token` (`api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_keys`
--

LOCK TABLES `api_keys` WRITE;
/*!40000 ALTER TABLE `api_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicants`
--

DROP TABLE IF EXISTS `applicants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicants` (
  `applicant_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `position_applied` varchar(150) NOT NULL,
  `contact_info` varchar(255) NOT NULL,
  `application_status` enum('Pending','Qualified','Hired','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicants`
--

LOCK TABLES `applicants` WRITE;
/*!40000 ALTER TABLE `applicants` DISABLE KEYS */;
/*!40000 ALTER TABLE `applicants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessment_categories`
--

DROP TABLE IF EXISTS `assessment_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assessment_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `score` int(11) DEFAULT NULL,
  `level` enum('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Beginner',
  PRIMARY KEY (`id`),
  KEY `assessment_id` (`assessment_id`),
  CONSTRAINT `assessment_categories_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `skill_assessments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_categories`
--

LOCK TABLES `assessment_categories` WRITE;
/*!40000 ALTER TABLE `assessment_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessment_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_records`
--

DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `hours` decimal(4,2) DEFAULT NULL,
  `status` enum('Present','Absent','Sick Leave','Annual Leave','Personal Leave','Holiday') DEFAULT 'Present',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`employee_id`,`attendance_date`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_records`
--

LOCK TABLES `attendance_records` WRITE;
/*!40000 ALTER TABLE `attendance_records` DISABLE KEYS */;
INSERT INTO `attendance_records` VALUES (1,1,'2025-01-13','08:45:00','17:30:00',8.75,'Present','2026-03-13 06:49:13'),(2,1,'2025-01-12','08:50:00','17:25:00',8.58,'Present','2026-03-13 06:49:13'),(3,1,'2025-01-11','09:00:00','17:30:00',8.50,'Present','2026-03-13 06:49:13'),(4,1,'2025-01-10','08:40:00','17:35:00',8.92,'Present','2026-03-13 06:49:13'),(5,1,'2025-01-09',NULL,NULL,0.00,'Sick Leave','2026-03-13 06:49:13'),(6,2,'2026-03-16','19:36:39','19:36:42',0.00,'Present','2026-03-16 18:36:39');
/*!40000 ALTER TABLE `attendance_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `badges`
--

DROP TABLE IF EXISTS `badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `badge_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(10) DEFAULT NULL,
  `earned_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `badges_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `badges`
--

LOCK TABLES `badges` WRITE;
/*!40000 ALTER TABLE `badges` DISABLE KEYS */;
INSERT INTO `badges` VALUES (1,1,'Quick Learner','Completed 3 courses in a month','┬¡╞Æ├£├ç','2024-12-28','2026-03-13 06:49:13'),(2,1,'Perfect Score','Achieved 100% on a course exam','┬¡╞Æ├å┬╗','2024-12-28','2026-03-13 06:49:13'),(3,1,'Compliance Expert','Completed all compliance courses','┬¡╞Æ├╕├¡┬┤┬⌐├à','2024-11-20','2026-03-13 06:49:13'),(4,1,'Knowledge Seeker','Enrolled in 5+ courses','┬¡╞Æ├┤├£','2024-10-15','2026-03-13 06:49:13');
/*!40000 ALTER TABLE `badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `certificate_name` varchar(200) NOT NULL,
  `certificate_id` varchar(50) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `instructor` varchar(100) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_id` (`certificate_id`),
  KEY `employee_id` (`employee_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `learning_courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
INSERT INTO `certificates` VALUES (1,2,2,'Safety & Compliance Fundamentals','CERT-2024-12345','2024-12-28','Sarah Thompson',95,'2026-03-13 06:49:13');
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competency_gaps`
--

DROP TABLE IF EXISTS `competency_gaps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competency_gaps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `required_competencies` int(11) DEFAULT 0,
  `current_competencies` int(11) DEFAULT 0,
  `gap_percentage` int(11) DEFAULT 0,
  `critical_gaps` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `competency_gaps_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competency_gaps`
--

LOCK TABLES `competency_gaps` WRITE;
/*!40000 ALTER TABLE `competency_gaps` DISABLE KEYS */;
/*!40000 ALTER TABLE `competency_gaps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competency_matrix`
--

DROP TABLE IF EXISTS `competency_matrix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competency_matrix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `competency` varchar(200) NOT NULL,
  `required_level` enum('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Intermediate',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competency_matrix`
--

LOCK TABLES `competency_matrix` WRITE;
/*!40000 ALTER TABLE `competency_matrix` DISABLE KEYS */;
INSERT INTO `competency_matrix` VALUES (1,'Fleet Operations Management','Expert','2026-03-13 06:49:13'),(2,'Safety & Compliance','Advanced','2026-03-13 06:49:13'),(3,'Route Optimization','Advanced','2026-03-13 06:49:13');
/*!40000 ALTER TABLE `competency_matrix` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_enrollments`
--

DROP TABLE IF EXISTS `course_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `completed_modules` int(11) DEFAULT 0,
  `total_modules` int(11) DEFAULT 0,
  `time_spent` varchar(50) DEFAULT NULL,
  `status` enum('Enrolled','In Progress','Completed','Dropped') DEFAULT 'Enrolled',
  `last_accessed` date DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`course_id`,`employee_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `learning_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_enrollments`
--

LOCK TABLES `course_enrollments` WRITE;
/*!40000 ALTER TABLE `course_enrollments` DISABLE KEYS */;
INSERT INTO `course_enrollments` VALUES (1,1,2,45,4,8,'2.5 hours','In Progress','2025-01-12','2026-03-13 06:49:13',NULL),(2,2,2,100,6,6,'4 hours','Completed','2024-12-28','2026-03-13 06:49:13',NULL),(3,3,2,100,10,10,NULL,'Completed','2026-03-13','2026-03-13 06:52:47','2026-03-13 06:53:02');
/*!40000 ALTER TABLE `course_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `critical_roles`
--

DROP TABLE IF EXISTS `critical_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `critical_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `current_holder_id` int(11) DEFAULT NULL,
  `risk_level` enum('Low','Medium','High') DEFAULT 'Medium',
  `retirement_date` date DEFAULT NULL,
  `succession_readiness` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `current_holder_id` (`current_holder_id`),
  CONSTRAINT `critical_roles_ibfk_1` FOREIGN KEY (`current_holder_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `critical_roles`
--

LOCK TABLES `critical_roles` WRITE;
/*!40000 ALTER TABLE `critical_roles` DISABLE KEYS */;
INSERT INTO `critical_roles` VALUES (1,'asa','as',NULL,'Medium','2026-03-21',0,'2026-03-13 06:36:51','2026-03-13 06:36:51'),(2,'Manager','IT Dept',2,'','2026-03-21',25,'2026-03-14 14:01:24','2026-03-16 17:10:03'),(3,'ahaha','asas',2,'Medium','2027-06-14',0,'2026-03-14 14:07:07','2026-03-14 14:07:07');
/*!40000 ALTER TABLE `critical_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_competencies`
--

DROP TABLE IF EXISTS `employee_competencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_competencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `competency_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `level` enum('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Beginner',
  `has_gap` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_competency` (`competency_id`,`employee_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `employee_competencies_ibfk_1` FOREIGN KEY (`competency_id`) REFERENCES `competency_matrix` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_competencies_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_competencies`
--

LOCK TABLES `employee_competencies` WRITE;
/*!40000 ALTER TABLE `employee_competencies` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_competencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `examinations`
--

DROP TABLE IF EXISTS `examinations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `examinations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Passed','Failed','Cancelled') DEFAULT 'Scheduled',
  `attempts_allowed` int(11) DEFAULT 3,
  `passing_score` int(11) DEFAULT 80,
  `score` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `examinations_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `learning_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `examinations_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `examinations`
--

LOCK TABLES `examinations` WRITE;
/*!40000 ALTER TABLE `examinations` DISABLE KEYS */;
INSERT INTO `examinations` VALUES (1,2,2,'2024-12-28','60 minutes','Passed',1,80,95,'2026-03-13 06:49:13'),(2,3,2,'2026-03-13','30 mins','Scheduled',3,60,NULL,'2026-03-13 06:53:02');
/*!40000 ALTER TABLE `examinations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `high_potential_employees`
--

DROP TABLE IF EXISTS `high_potential_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `high_potential_employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `current_role` varchar(100) DEFAULT NULL,
  `years_of_service` int(11) DEFAULT NULL,
  `performance_rating` decimal(3,1) DEFAULT NULL,
  `potential_score` int(11) DEFAULT NULL,
  `target_role` varchar(200) DEFAULT NULL,
  `development_areas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `high_potential_employees_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `high_potential_employees`
--

LOCK TABLES `high_potential_employees` WRITE;
/*!40000 ALTER TABLE `high_potential_employees` DISABLE KEYS */;
INSERT INTO `high_potential_employees` VALUES (1,2,'Taga buhat',2,5.0,90,'asaasa','','2026-03-14 14:12:48','2026-03-14 14:12:48');
/*!40000 ALTER TABLE `high_potential_employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `learning_courses`
--

DROP TABLE IF EXISTS `learning_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `level` enum('Beginner','Intermediate','Advanced') DEFAULT 'Beginner',
  `rating` decimal(3,1) DEFAULT 0.0,
  `reviews_count` int(11) DEFAULT 0,
  `enrolled_count` int(11) DEFAULT 0,
  `instructor` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `modules_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `learning_courses`
--

LOCK TABLES `learning_courses` WRITE;
/*!40000 ALTER TABLE `learning_courses` DISABLE KEYS */;
INSERT INTO `learning_courses` VALUES (1,'Advanced Fleet Management Techniques','Operations','6 hours','Advanced',4.8,124,89,'Dr. Michael Roberts','Master advanced strategies for managing large-scale fleet operations',8,'2026-03-13 06:49:13','2026-03-13 06:49:13'),(2,'Safety & Compliance Fundamentals','Compliance','4 hours','Beginner',4.9,256,187,'Sarah Thompson','Essential safety protocols and compliance requirements for freight operations',6,'2026-03-13 06:49:13','2026-03-13 06:49:13'),(3,'Leadership in Logistics','Leadership','8 hours','Intermediate',4.7,98,67,'Jennifer Martinez','Develop leadership skills specific to logistics and supply chain management',10,'2026-03-13 06:49:13','2026-03-13 06:49:13'),(4,'Route Optimization & Analytics','Technology','5 hours','Advanced',4.6,76,54,'David Chen','Learn data-driven approaches to optimize delivery routes and reduce costs',7,'2026-03-13 06:49:13','2026-03-13 06:49:13');
/*!40000 ALTER TABLE `learning_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_balance`
--

DROP TABLE IF EXISTS `leave_balance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `annual_total` int(11) DEFAULT 20,
  `annual_used` int(11) DEFAULT 0,
  `annual_remaining` int(11) DEFAULT 20,
  `sick_total` int(11) DEFAULT 10,
  `sick_used` int(11) DEFAULT 0,
  `sick_remaining` int(11) DEFAULT 10,
  `personal_total` int(11) DEFAULT 5,
  `personal_used` int(11) DEFAULT 0,
  `personal_remaining` int(11) DEFAULT 5,
  `year` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_balance` (`employee_id`,`year`),
  CONSTRAINT `leave_balance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_balance`
--

LOCK TABLES `leave_balance` WRITE;
/*!40000 ALTER TABLE `leave_balance` DISABLE KEYS */;
INSERT INTO `leave_balance` VALUES (1,2,20,0,20,10,0,10,5,0,5,2026,'2026-03-13 06:36:25'),(2,1,20,7,13,10,2,8,5,1,4,2025,'2026-03-13 06:49:13'),(3,1,20,0,20,10,0,10,5,0,5,2026,'2026-03-13 06:55:34');
/*!40000 ALTER TABLE `leave_balance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('Annual Leave','Sick Leave','Personal Leave','Other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `applied_date` date NOT NULL,
  `approver_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `approver_id` (`approver_id`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
INSERT INTO `leave_requests` VALUES (1,1,'Annual Leave','2025-02-10','2025-02-14',5,'Approved','2025-01-05',1,NULL,'2026-03-13 06:49:13','2026-03-13 06:49:13'),(2,1,'Sick Leave','2025-01-08','2025-01-09',2,'Approved','2025-01-07',1,NULL,'2026-03-13 06:49:13','2026-03-13 06:49:13'),(3,1,'Annual Leave','2025-03-15','2025-03-20',6,'Pending','2025-01-10',1,NULL,'2026-03-13 06:49:13','2026-03-13 06:49:13');
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_validation`
--

DROP TABLE IF EXISTS `leave_validation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_validation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `leave_date` date NOT NULL,
  `shift` varchar(100) NOT NULL,
  `validation_status` enum('Pending','Validated','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `leave_validation_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_validation`
--

LOCK TABLES `leave_validation` WRITE;
/*!40000 ALTER TABLE `leave_validation` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_validation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `type` enum('training','promotion','leave','certificate','succession','assessment','other') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','normal','high') DEFAULT 'normal',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'training','New Training Program Available','Leadership Development Program is now available for enrollment','normal',0,'2026-03-13 06:49:13'),(2,1,'promotion','Promotion Eligibility Update','You are now eligible for Senior Operations Manager position','high',0,'2026-03-13 06:49:13'),(3,1,'leave','Leave Request Approved','Your annual leave request from Feb 10-14 has been approved','normal',1,'2026-03-13 06:49:13'),(4,1,'certificate','Certificate Awarded','Congratulations! You have earned Safety & Compliance Fundamentals certificate','normal',1,'2026-03-13 06:49:13'),(5,1,'succession','Succession Planning Update','You have been identified as a successor for Fleet Operations Director role','high',1,'2026-03-13 06:49:13');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payslips`
--

DROP TABLE IF EXISTS `payslips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payslips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `month` varchar(20) NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `gross_pay` decimal(10,2) NOT NULL,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) NOT NULL,
  `status` enum('Pending','Paid','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `payslips_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payslips`
--

LOCK TABLES `payslips` WRITE;
/*!40000 ALTER TABLE `payslips` DISABLE KEYS */;
INSERT INTO `payslips` VALUES (1,1,'December 2024','2024-12-01','2024-12-31',6500.00,1450.00,5050.00,'Paid','2026-03-13 06:49:13'),(2,1,'November 2024','2024-11-01','2024-11-30',6500.00,1450.00,5050.00,'Paid','2026-03-13 06:49:13'),(3,1,'October 2024','2024-10-01','2024-10-31',6500.00,1450.00,5050.00,'Paid','2026-03-13 06:49:13');
/*!40000 ALTER TABLE `payslips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retirement_forecasts`
--

DROP TABLE IF EXISTS `retirement_forecasts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `retirement_forecasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `total_retirements` int(11) DEFAULT 0,
  `critical_roles_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_year` (`year`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retirement_forecasts`
--

LOCK TABLES `retirement_forecasts` WRITE;
/*!40000 ALTER TABLE `retirement_forecasts` DISABLE KEYS */;
INSERT INTO `retirement_forecasts` VALUES (1,2026,2,2,'2026-03-14 18:20:12'),(2,2027,1,1,'2026-03-14 18:20:12');
/*!40000 ALTER TABLE `retirement_forecasts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `skill_assessments`
--

DROP TABLE IF EXISTS `skill_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `skill_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `assessment_date` date NOT NULL,
  `overall_score` int(11) DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `skill_assessments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `skill_assessments`
--

LOCK TABLES `skill_assessments` WRITE;
/*!40000 ALTER TABLE `skill_assessments` DISABLE KEYS */;
/*!40000 ALTER TABLE `skill_assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `skill_development`
--

DROP TABLE IF EXISTS `skill_development`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `skill_development` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `required_skill` varchar(255) NOT NULL,
  `training_program` varchar(255) NOT NULL,
  `training_program_id` int(11) DEFAULT NULL,
  `status` enum('Sent','Scheduled','Completed') DEFAULT 'Sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `training_program_id` (`training_program_id`),
  CONSTRAINT `skill_development_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `skill_development_ibfk_2` FOREIGN KEY (`training_program_id`) REFERENCES `training_programs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `skill_development`
--

LOCK TABLES `skill_development` WRITE;
/*!40000 ALTER TABLE `skill_development` DISABLE KEYS */;
/*!40000 ALTER TABLE `skill_development` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `successors`
--

DROP TABLE IF EXISTS `successors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `successors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `critical_role_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `readiness_score` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_successor` (`critical_role_id`,`employee_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `successors_ibfk_1` FOREIGN KEY (`critical_role_id`) REFERENCES `critical_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `successors_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `successors`
--

LOCK TABLES `successors` WRITE;
/*!40000 ALTER TABLE `successors` DISABLE KEYS */;
INSERT INTO `successors` VALUES (2,2,2,0,NULL,'2026-03-14 14:11:06','2026-03-14 14:11:06','2026-03-14 14:11:06');
/*!40000 ALTER TABLE `successors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `talent_identification`
--

DROP TABLE IF EXISTS `talent_identification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `talent_identification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `talent_type` enum('Key Role Talent','High Potential') NOT NULL,
  `status` varchar(50) DEFAULT 'Sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `talent_identification_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `talent_identification`
--

LOCK TABLES `talent_identification` WRITE;
/*!40000 ALTER TABLE `talent_identification` DISABLE KEYS */;
/*!40000 ALTER TABLE `talent_identification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_participants`
--

DROP TABLE IF EXISTS `training_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_program_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `completion_percentage` int(11) DEFAULT 0,
  `status` enum('Enrolled','In Progress','Completed','Dropped') DEFAULT 'Enrolled',
  `enrolled_name` varchar(255) DEFAULT NULL,
  `health_condition` text DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_program_id` (`training_program_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `training_participants_ibfk_1` FOREIGN KEY (`training_program_id`) REFERENCES `training_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_participants_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_participants`
--

LOCK TABLES `training_participants` WRITE;
/*!40000 ALTER TABLE `training_participants` DISABLE KEYS */;
INSERT INTO `training_participants` VALUES (6,3,2,0,'Completed','Gloria','None','2026-03-14 15:43:29','2026-03-14 08:54:02'),(8,4,2,100,'Completed','Kaiser',NULL,'2026-03-14 16:01:40','2026-03-14 16:01:53');
/*!40000 ALTER TABLE `training_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_programs`
--

DROP TABLE IF EXISTS `training_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `status` enum('Upcoming','In Progress','Completed') DEFAULT 'Upcoming',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `instructor` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `is_recommendation` tinyint(1) DEFAULT 0,
  `requested_for` varchar(255) DEFAULT NULL,
  `training_time` time DEFAULT NULL,
  `competency_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `competency_id` (`competency_id`),
  CONSTRAINT `training_programs_ibfk_1` FOREIGN KEY (`competency_id`) REFERENCES `competency_matrix` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_programs`
--

LOCK TABLES `training_programs` WRITE;
/*!40000 ALTER TABLE `training_programs` DISABLE KEYS */;
INSERT INTO `training_programs` VALUES (1,'New Hire Orientation','Onboarding','3 days','In Progress','2025-01-15','2025-01-17','Dr. Michael Roberts','Comprehensive orientation for new employees',NULL,NULL,0,NULL,NULL,NULL,'2026-03-13 06:49:13','2026-03-13 06:49:13'),(2,'Safety & Compliance Orientation','Safety','2 days','Completed','2024-12-01','2024-12-02','Sarah Thompson','Safety protocols and compliance requirements',NULL,NULL,0,NULL,NULL,NULL,'2026-03-13 06:49:13','2026-03-13 06:49:13'),(3,'Operations Department Orientation','Department','1 week','Upcoming','2025-02-01','2025-02-07','Jennifer Martinez','Department-specific orientation',NULL,NULL,0,NULL,NULL,NULL,'2026-03-13 06:49:13','2026-03-13 06:49:13'),(4,'Fleet Management Orientation','Operations','5 days','In Progress','2025-01-20','2025-01-24','David Chen','Fleet management fundamentals',NULL,NULL,0,NULL,NULL,NULL,'2026-03-13 06:49:13','2026-03-13 06:49:13');
/*!40000 ALTER TABLE `training_programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_schedule`
--

DROP TABLE IF EXISTS `training_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_program_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `session_time` time NOT NULL,
  `session_type` varchar(200) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `instructor` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `training_program_id` (`training_program_id`),
  CONSTRAINT `training_schedule_ibfk_1` FOREIGN KEY (`training_program_id`) REFERENCES `training_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_schedule`
--

LOCK TABLES `training_schedule` WRITE;
/*!40000 ALTER TABLE `training_schedule` DISABLE KEYS */;
INSERT INTO `training_schedule` VALUES (1,3,'2026-03-14','12:00:00','Workshop','Quezon city','Kaiser Ferranco','2026-03-14 15:42:05'),(2,3,'2026-03-17','10:00:00','Workshop','bg','Kaiser Ferranco','2026-03-14 16:07:43');
/*!40000 ALTER TABLE `training_schedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Employee',
  `manager_id` int(11) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT 'Full-time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `manager_id` (`manager_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'employee','$2y$10$uUhM55hXYVJ6OI5JxBpN5uQF9.SC01JR4K7qMdv/kzumnZKGEPRW2','EMP002','John Smith','john.smith@freighthr.com','+1 234 567 8900','123 Main St','New York','1990-05-15',NULL,NULL,'2020-01-10','Operations','Logistics Coordinator','Employee',NULL,'Full-time','2026-03-13 06:34:30','2026-03-13 06:34:30'),(2,'admin','$2y$10$uUhM55hXYVJ6OI5JxBpN5uQF9.SC01JR4K7qMdv/kzumnZKGEPRW2','EMP003','John Wayne','johnwayne@freighthr.com','09087645342','Bignay','Valenzuela','2015-05-11','Single','2_1773685316.jpg','2015-06-01','Operations','Senior Operations Manager','Employee',1,'Full-time','2026-03-13 06:34:30','2026-03-16 19:00:23');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-17  3:24:16
