-- MySQL dump 10.13  Distrib 8.0.46, for Linux (aarch64)
--
-- Host: localhost    Database: tracs_db
-- ------------------------------------------------------
-- Server version	8.0.46

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
-- Table structure for table `activity_feed`
--

DROP TABLE IF EXISTS `activity_feed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_feed` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `activity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_feed`
--

LOCK TABLES `activity_feed` WRITE;
/*!40000 ALTER TABLE `activity_feed` DISABLE KEYS */;
INSERT INTO `activity_feed` VALUES (1,'domain_added','New domain transfer added: vickry','vickry',1,NULL,'2026-05-11 01:50:57'),(2,'domain_added','New domain transfer added: vickry.id','vickry.id',1,NULL,'2026-05-11 01:51:11'),(3,'domain_added','New domain transfer added: vickry.id','vickry.id',1,NULL,'2026-05-11 02:18:03');
/*!40000 ALTER TABLE `activity_feed` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `balance_transfers`
--

DROP TABLE IF EXISTS `balance_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `balance_transfers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sender_email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sender_user_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sender_type` enum('client_area','billing_console','billing_awan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client_area',
  `receiver_email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `receiver_user_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `receiver_type` enum('client_area','billing_console','billing_awan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client_area',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('done','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ticket_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_name`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_transfer_date` (`transfer_date`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_sender_email` (`sender_email`(64)),
  KEY `idx_receiver_email` (`receiver_email`(64)),
  KEY `idx_sender_uid` (`sender_user_id`(50)),
  KEY `idx_receiver_uid` (`receiver_user_id`(50)),
  KEY `idx_status` (`status`),
  KEY `idx_balance_transfers_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `balance_transfers`
--

LOCK TABLES `balance_transfers` WRITE;
/*!40000 ALTER TABLE `balance_transfers` DISABLE KEYS */;
INSERT INTO `balance_transfers` VALUES (1,NULL,NULL,'2026-05-09 23:11:03','budi.santoso@gmail.com','USR-10042','client_area','pt.maju@domain.co.id','USR-10088','billing_console',500000.00,'done','Rina','TKT-2025-001','2026-05-10 23:11:03','2026-05-10 23:11:03'),(2,NULL,NULL,'2026-05-09 23:11:18','budi.santoso@gmail.com','USR-10042','client_area','pt.maju@domain.co.id','USR-10088','billing_console',500000.00,'done','Rina','TKT-2025-001','2026-05-10 23:11:18','2026-05-10 23:11:18'),(3,NULL,NULL,'2026-05-08 23:11:18','ani.wijaya@yahoo.com','USR-10031','billing_awan','siti.rahayu@gmail.com','USR-10055','client_area',1250000.00,'done','Dian','TKT-2025-002','2026-05-10 23:11:18','2026-05-10 23:11:18'),(4,NULL,NULL,'2026-05-10 20:11:18','cv.berkah@hosting.id','USR-20011','billing_console','hendro.purnomo@gmail.com','USR-10099','billing_awan',750000.00,'pending','Rina',NULL,'2026-05-10 23:11:18','2026-05-10 23:11:18'),(5,NULL,NULL,'2026-05-05 23:11:18','tokosaya.id@gmail.com','USR-10072','client_area','pt.teknologi@company.com','USR-20031','billing_console',3000000.00,'done','Bimo','TKT-2025-003','2026-05-10 23:11:18','2026-05-10 23:11:18'),(6,NULL,NULL,'2026-05-10 16:11:00','vickry@gmail.com','1234','client_area','123@gmail.com','123','billing_console',12131.00,'done','vickry','123421','2026-05-10 23:12:43','2026-05-10 23:12:43'),(7,NULL,NULL,'2026-05-12 05:07:00','1232131@gmail.com','2','client_area','2131231@gmail.com','123113','billing_awan',12313131.00,'pending','lala',NULL,'2026-05-12 12:08:53','2026-05-12 12:08:53'),(8,1,'Administrator','2026-05-17 06:21:00','email@idcloudhost.com','123123','billing_console','email@idcloudhost.com','12123','client_area',123300.00,'done','Administrator','#412313','2026-05-17 13:22:36','2026-05-17 13:22:36');
/*!40000 ALTER TABLE `balance_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currency_history`
--

DROP TABLE IF EXISTS `currency_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(10) DEFAULT NULL,
  `to_currency` varchar(10) DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL,
  `result` decimal(18,2) DEFAULT NULL,
  `rate` decimal(18,6) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currency_history`
--

LOCK TABLES `currency_history` WRITE;
/*!40000 ALTER TABLE `currency_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `currency_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_price_audit_logs`
--

DROP TABLE IF EXISTS `domain_price_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_price_audit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `month_id` int unsigned NOT NULL,
  `tld_id` int unsigned DEFAULT NULL,
  `source_id` int unsigned DEFAULT NULL,
  `actor_user_id` int unsigned NOT NULL,
  `actor_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `change_reason` text COLLATE utf8mb4_unicode_ci,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_domain_price_audit_month` (`month_id`),
  KEY `idx_domain_price_audit_actor` (`actor_user_id`),
  CONSTRAINT `fk_domain_price_audit_logs_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_price_audit_logs`
--

LOCK TABLES `domain_price_audit_logs` WRITE;
/*!40000 ALTER TABLE `domain_price_audit_logs` DISABLE KEYS */;
INSERT INTO `domain_price_audit_logs` VALUES (6,103,NULL,NULL,1,'Administrator','created','status',NULL,'draft',NULL,'Created monthly draft record for 2026-05 with exchange rate IDR 18,000.00','172.18.0.1','2026-05-20 07:56:41');
/*!40000 ALTER TABLE `domain_price_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_price_entries`
--

DROP TABLE IF EXISTS `domain_price_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_price_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `month_id` int unsigned NOT NULL,
  `tld_id` int unsigned NOT NULL,
  `source_id` int unsigned DEFAULT NULL,
  `price_type` enum('cost_register','cost_renewal','cost_transfer','selling_website_register','selling_website_renewal','selling_website_transfer','selling_paas_register','selling_paas_renewal','selling_paas_transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `original_value` decimal(15,4) NOT NULL,
  `usd_value` decimal(15,4) NOT NULL,
  `idr_value` decimal(15,2) NOT NULL,
  `calculated_from_kurs` decimal(10,2) NOT NULL,
  `is_lowest` tinyint(1) NOT NULL DEFAULT '0',
  `comparison_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_entry_unique` (`month_id`,`tld_id`,`source_id`,`price_type`),
  KEY `fk_domain_price_entries_tld` (`tld_id`),
  KEY `fk_domain_price_entries_source` (`source_id`),
  CONSTRAINT `fk_domain_price_entries_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domain_price_entries_source` FOREIGN KEY (`source_id`) REFERENCES `domain_price_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_domain_price_entries_tld` FOREIGN KEY (`tld_id`) REFERENCES `domain_price_tlds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_price_entries`
--

LOCK TABLES `domain_price_entries` WRITE;
/*!40000 ALTER TABLE `domain_price_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `domain_price_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_price_months`
--

DROP TABLE IF EXISTS `domain_price_months`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_price_months` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` int NOT NULL,
  `exchange_rate_usd_idr` decimal(10,2) NOT NULL,
  `status` enum('draft','pending_review','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_by` int unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_note` text COLLATE utf8mb4_unicode_ci,
  `unlocked_by` int unsigned DEFAULT NULL,
  `unlocked_at` datetime DEFAULT NULL,
  `unlock_reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_months_month` (`month`),
  KEY `idx_domain_price_months_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_price_months`
--

LOCK TABLES `domain_price_months` WRITE;
/*!40000 ALTER TABLE `domain_price_months` DISABLE KEYS */;
INSERT INTO `domain_price_months` VALUES (103,'2026-05',2026,18000.00,'draft',1,'2026-05-20 07:56:41',NULL,'2026-05-20 07:56:41',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `domain_price_months` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_price_sources`
--

DROP TABLE IF EXISTS `domain_price_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_price_sources` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `source_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('registrar','internal','registry') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registrar',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_sources_name` (`source_name`),
  KEY `idx_domain_price_sources_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_price_sources`
--

LOCK TABLES `domain_price_sources` WRITE;
/*!40000 ALTER TABLE `domain_price_sources` DISABLE KEYS */;
INSERT INTO `domain_price_sources` VALUES (1,'Niagahoster','registrar',1,10,'2026-05-20 07:18:08'),(2,'ResellerClub','registrar',1,20,'2026-05-20 07:18:08'),(3,'Dewaweb','registrar',1,30,'2026-05-20 07:18:08'),(4,'Rumahweb','registrar',1,40,'2026-05-20 07:18:08'),(5,'Dewabiz','registrar',1,50,'2026-05-20 07:18:08'),(6,'Exabytes','registrar',1,60,'2026-05-20 07:18:08'),(7,'Qwords','registrar',1,70,'2026-05-20 07:18:08'),(8,'Jagoan Hosting','registrar',1,80,'2026-05-20 07:18:08'),(9,'Liquid Registrar','registrar',1,10,'2026-05-20 07:18:08'),(10,'Webnic Registrar','registrar',1,20,'2026-05-20 07:18:08'),(11,'IDCH Internal Pricing','internal',1,30,'2026-05-20 07:18:08'),(12,'IDCH Website Pricing','registrar',1,40,'2026-05-20 07:18:08'),(13,'PAAS Pricing','registrar',1,50,'2026-05-20 07:18:08'),(14,'PANDI Registry Pricing','registry',1,401,'2026-05-20 08:09:32'),(15,'IDCH ccTLD Pricing','internal',1,402,'2026-05-20 08:09:32');
/*!40000 ALTER TABLE `domain_price_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_price_summaries`
--

DROP TABLE IF EXISTS `domain_price_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_price_summaries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `month_id` int unsigned NOT NULL,
  `tld_id` int unsigned NOT NULL,
  `lowest_register_source_id` int unsigned DEFAULT NULL,
  `lowest_renewal_source_id` int unsigned DEFAULT NULL,
  `lowest_transfer_source_id` int unsigned DEFAULT NULL,
  `lowest_register_cost` decimal(15,2) DEFAULT NULL,
  `lowest_renewal_cost` decimal(15,2) DEFAULT NULL,
  `lowest_transfer_cost` decimal(15,2) DEFAULT NULL,
  `website_register_price` decimal(15,2) DEFAULT NULL,
  `website_renewal_price` decimal(15,2) DEFAULT NULL,
  `website_transfer_price` decimal(15,2) DEFAULT NULL,
  `paas_register_price` decimal(15,2) DEFAULT NULL,
  `paas_renewal_price` decimal(15,2) DEFAULT NULL,
  `paas_transfer_price` decimal(15,2) DEFAULT NULL,
  `website_margin_register` decimal(15,2) DEFAULT NULL,
  `website_margin_renewal` decimal(15,2) DEFAULT NULL,
  `website_margin_transfer` decimal(15,2) DEFAULT NULL,
  `paas_margin_register` decimal(15,2) DEFAULT NULL,
  `paas_margin_renewal` decimal(15,2) DEFAULT NULL,
  `paas_margin_transfer` decimal(15,2) DEFAULT NULL,
  `website_margin_register_pct` decimal(5,2) DEFAULT NULL,
  `website_margin_renewal_pct` decimal(5,2) DEFAULT NULL,
  `website_margin_transfer_pct` decimal(5,2) DEFAULT NULL,
  `paas_margin_register_pct` decimal(5,2) DEFAULT NULL,
  `paas_margin_renewal_pct` decimal(5,2) DEFAULT NULL,
  `paas_margin_transfer_pct` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `auto_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggested_action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manual_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detailed_note` text COLLATE utf8mb4_unicode_ci,
  `follow_up_status` enum('No Action','Need Review','Waiting Finance','Waiting Approval','Updated') COLLATE utf8mb4_unicode_ci DEFAULT 'No Action',
  `updated_by` int unsigned DEFAULT NULL,
  `website_below_cost_register` tinyint(1) NOT NULL DEFAULT '0',
  `website_below_cost_renewal` tinyint(1) NOT NULL DEFAULT '0',
  `paas_below_cost_register` tinyint(1) NOT NULL DEFAULT '0',
  `paas_below_cost_renewal` tinyint(1) NOT NULL DEFAULT '0',
  `prev_lowest_register_cost` decimal(15,2) DEFAULT NULL,
  `prev_lowest_renewal_cost` decimal(15,2) DEFAULT NULL,
  `cost_register_diff` decimal(15,2) DEFAULT NULL,
  `cost_renewal_diff` decimal(15,2) DEFAULT NULL,
  `cost_register_change_pct` decimal(7,2) DEFAULT NULL,
  `cost_renewal_change_pct` decimal(7,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_summaries_month_tld` (`month_id`,`tld_id`),
  KEY `fk_domain_price_summaries_tld` (`tld_id`),
  KEY `fk_domain_price_summaries_lowest_reg` (`lowest_register_source_id`),
  KEY `fk_domain_price_summaries_lowest_ren` (`lowest_renewal_source_id`),
  KEY `fk_domain_price_summaries_lowest_tra` (`lowest_transfer_source_id`),
  CONSTRAINT `fk_domain_price_summaries_lowest_reg` FOREIGN KEY (`lowest_register_source_id`) REFERENCES `domain_price_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_domain_price_summaries_lowest_ren` FOREIGN KEY (`lowest_renewal_source_id`) REFERENCES `domain_price_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_domain_price_summaries_lowest_tra` FOREIGN KEY (`lowest_transfer_source_id`) REFERENCES `domain_price_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_domain_price_summaries_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domain_price_summaries_tld` FOREIGN KEY (`tld_id`) REFERENCES `domain_price_tlds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_price_summaries`
--

LOCK TABLES `domain_price_summaries` WRITE;
/*!40000 ALTER TABLE `domain_price_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `domain_price_summaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_price_task_links`
--

DROP TABLE IF EXISTS `domain_price_task_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_price_task_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `month_id` int unsigned NOT NULL,
  `task_id` int NOT NULL,
  `assigned_to` int NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_task_links_month` (`month_id`),
  KEY `idx_dptl_assigned` (`assigned_to`),
  KEY `idx_dptl_task` (`task_id`),
  CONSTRAINT `fk_dptl_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `tracs_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dptl_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dptl_task` FOREIGN KEY (`task_id`) REFERENCES `tracs_reminders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_price_task_links`
--

LOCK TABLES `domain_price_task_links` WRITE;
/*!40000 ALTER TABLE `domain_price_task_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `domain_price_task_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_price_tlds`
--

DROP TABLE IF EXISTS `domain_price_tlds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_price_tlds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tld_name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tld_category` enum('gtld','cctld') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gtld',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_tlds_name` (`tld_name`),
  KEY `idx_domain_price_tlds_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_price_tlds`
--

LOCK TABLES `domain_price_tlds` WRITE;
/*!40000 ALTER TABLE `domain_price_tlds` DISABLE KEYS */;
INSERT INTO `domain_price_tlds` VALUES (1,'.com','gtld',1,10,'2026-05-20 07:18:08'),(2,'.id','cctld',1,5040,'2026-05-20 07:18:08'),(3,'.net','gtld',1,30,'2026-05-20 07:18:08'),(4,'.org','gtld',1,40,'2026-05-20 07:18:08'),(5,'.xyz','gtld',1,50,'2026-05-20 07:18:08'),(6,'.info','gtld',1,60,'2026-05-20 07:18:08'),(7,'.biz','gtld',1,70,'2026-05-20 07:18:08'),(8,'.co','gtld',1,80,'2026-05-20 07:18:08'),(9,'.co.id','cctld',1,5030,'2026-05-20 07:18:08'),(10,'.my.id','cctld',1,5050,'2026-05-20 07:18:08'),(11,'.web.id','cctld',1,5090,'2026-05-20 07:18:08'),(12,'.sch.id','cctld',1,5080,'2026-05-20 07:18:08'),(13,'.or.id','cctld',1,5060,'2026-05-20 07:18:08'),(14,'.ac.id','cctld',1,5010,'2026-05-20 07:18:08'),(15,'.go.id','gtld',0,150,'2026-05-20 07:18:08'),(16,'.BIZ.ID','cctld',1,5020,'2026-05-20 08:09:32'),(17,'.PONPES.ID','cctld',1,5070,'2026-05-20 08:09:32'),(18,'.NET.ID','cctld',1,5100,'2026-05-20 08:09:32');
/*!40000 ALTER TABLE `domain_price_tlds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_transfers`
--

DROP TABLE IF EXISTS `domain_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_transfers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_status` enum('pending transfer','locked','error epp code','move domain','done','cancelled','retransferred','transferred away','pending verification','renew period') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending transfer',
  `process_start_date` date DEFAULT NULL,
  `process_end_date` date DEFAULT NULL,
  `webnic_reseller_transfer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_domain_name` (`domain_name`),
  KEY `idx_transfer_status` (`transfer_status`),
  KEY `idx_process_start` (`process_start_date`),
  KEY `idx_process_end` (`process_end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_transfers`
--

LOCK TABLES `domain_transfers` WRITE;
/*!40000 ALTER TABLE `domain_transfers` DISABLE KEYS */;
INSERT INTO `domain_transfers` VALUES (1,'vickry','pending transfer','2026-08-12','2026-12-12','','',1,NULL,'2026-05-11 01:50:57','2026-05-11 01:50:57'),(2,'vickry.id','pending transfer',NULL,NULL,'','',1,NULL,'2026-05-11 01:51:11','2026-05-11 01:51:11'),(3,'vickry.id','pending transfer','2026-05-11','2026-05-15',NULL,'',1,NULL,'2026-05-11 02:18:03','2026-05-11 03:02:34');
/*!40000 ALTER TABLE `domain_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ops_status`
--

DROP TABLE IF EXISTS `ops_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ops_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `severity` enum('info','warning','critical','solved') DEFAULT 'info',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ops_status`
--

LOCK TABLES `ops_status` WRITE;
/*!40000 ALTER TABLE `ops_status` DISABLE KEYS */;
INSERT INTO `ops_status` VALUES (1,'SG Gateway latency increased above normal threshold  monitoring active.','warning',1,'2026-05-09 13:43:46'),(2,'login.masuk.email service partially degraded due to storage maintenance.','critical',1,'2026-05-09 14:43:46'),(3,'Domain transfer queue backlog successfully cleared.','solved',1,'2026-05-09 14:58:46'),(4,'SSL auto-renew scheduler completed without failure.','info',1,'2026-05-09 15:13:46'),(5,'Customer payment reconciliation delayed from bank provider.','warning',1,'2026-05-09 15:18:46'),(6,'Backup snapshot for node SG-02 completed successfully.','solved',1,'2026-05-09 15:23:46'),(7,'High memory usage detected on shared mail node.','critical',1,'2026-05-09 15:28:46'),(8,'Routine DNS propagation checks completed normally.','info',1,'2026-05-09 15:33:46'),(9,'Pending transfer-domain approval requires manual validation.','warning',1,'2026-05-09 15:35:46'),(10,'Temporary API timeout detected from registrar provider.','critical',1,'2026-05-09 15:38:46'),(11,'MBG HARI INI HIDUP JOKOWI','warning',1,'2026-05-10 01:44:13'),(12,'HIDUP JOKIW','info',1,'2026-05-11 05:41:33'),(13,'Meeting completed: Meeting CS','solved',0,'2026-05-14 13:55:02'),(14,'Meeting completed: Meeting jumat','solved',0,'2026-05-15 02:19:04'),(15,'Meeting completed: Test list','solved',0,'2026-05-16 09:48:07'),(16,'Meeting completed: test span','solved',0,'2026-05-16 13:37:23'),(17,'Meeting completed: test font','solved',0,'2026-05-17 00:35:11'),(18,'Meeting completed: test','solved',0,'2026-05-18 02:28:59'),(19,'Meeting completed: test meeting','solved',0,'2026-05-19 03:45:44'),(20,'Meeting completed: Title','solved',0,'2026-05-20 09:33:24');
/*!40000 ALTER TABLE `ops_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_activity_logs`
--

DROP TABLE IF EXISTS `tracs_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `description` text,
  `reference_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=311 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_activity_logs`
--

LOCK TABLES `tracs_activity_logs` WRITE;
/*!40000 ALTER TABLE `tracs_activity_logs` DISABLE KEYS */;
INSERT INTO `tracs_activity_logs` VALUES (1,1,'CASE_UPDATED',NULL,'Payment gateway timeout escalated to infra team.',NULL,NULL,'2026-05-08 20:21:11'),(2,1,'CASE_CREATED',NULL,'New SMTP latency issue created.',NULL,NULL,'2026-05-08 20:14:11'),(3,1,'DOMAIN_TRANSFER',NULL,'Transfer approved for clientdomain.id',NULL,NULL,'2026-05-08 20:08:11'),(4,1,'REMINDER_DONE',NULL,'Backup validation completed.',NULL,NULL,'2026-05-08 20:01:11'),(5,1,'FINANCE_CHECK',NULL,'Mutation verification completed.',NULL,NULL,'2026-05-08 19:54:11'),(6,1,'SSL_RENEWAL',NULL,'SSL successfully renewed.',NULL,NULL,'2026-05-08 19:46:11'),(7,1,'LOGIN_ALERT',NULL,'Suspicious login attempt detected.',NULL,NULL,'2026-05-08 19:31:11'),(8,1,'QUEUE_RESTART',NULL,'Mail queue restarted successfully.',NULL,NULL,'2026-05-08 19:26:11'),(9,1,'SYSTEM_NOTICE',NULL,'Disk usage warning acknowledged.',NULL,NULL,'2026-05-08 19:26:11'),(10,1,'CASE_UPDATED',NULL,'Cloudflare propagation issue updated.',NULL,NULL,'2026-05-08 18:56:11'),(11,1,'DOMAIN_RENEWAL',NULL,'examplehost.id renewed for 1 year.',NULL,NULL,'2026-05-08 18:26:11'),(12,1,'REMINDER_CREATED',NULL,'Registrar follow-up reminder created.',NULL,NULL,'2026-05-08 18:26:11'),(13,1,'SERVER_ALERT',NULL,'CPU usage spike normalized.',NULL,NULL,'2026-05-08 17:26:11'),(14,1,'TRANSFER_PENDING',NULL,'Awaiting EPP confirmation.',NULL,NULL,'2026-05-08 16:26:11'),(15,1,'CASE_COMPLETED',NULL,'Database replication fixed.',NULL,NULL,'2026-05-08 15:26:11'),(16,1,'completed','Checklist','Task marked complete',2,NULL,'2026-05-08 22:01:04'),(17,1,'completed','Checklist','Task marked complete',1,NULL,'2026-05-08 22:01:06'),(18,1,'completed','Checklist','Task marked complete',3,NULL,'2026-05-08 22:01:08'),(19,1,'completed','Checklist','Task marked complete',5,NULL,'2026-05-08 22:01:11'),(20,1,'completed','Checklist','Task marked complete',4,NULL,'2026-05-08 22:11:18'),(21,1,'completed','Checklist','Task marked complete',6,NULL,'2026-05-08 22:34:53'),(22,1,'created','Ops Status','Created ops status: MBG HARI INI',11,NULL,'2026-05-10 08:44:13'),(23,1,'updated','Cases','Updated case: Email login delay issue login.masuk.email',5,NULL,'2026-05-10 09:19:43'),(24,1,'created','Checklist','Added task: cek lampu',7,NULL,'2026-05-10 10:19:29'),(25,1,'completed','Checklist','Task marked complete',7,NULL,'2026-05-10 10:19:32'),(26,1,'updated','Checklist','Task marked incomplete',7,NULL,'2026-05-10 10:19:42'),(27,1,'completed','Checklist','Task marked complete',7,NULL,'2026-05-10 13:15:38'),(28,1,'updated','Ops Status','Updated ops status: MBG HARI INI HIDUP JOKOWI',11,NULL,'2026-05-11 08:33:38'),(29,1,'created','Ops Status','Created ops status: HIDUP JOKIW',12,NULL,'2026-05-11 12:41:33'),(30,1,'created','Shift Reports','Added shift report: Pending Service',1,NULL,'2026-05-11 12:42:27'),(31,1,'completed','Shift Reports','Resolved shift report: Pending Service',1,NULL,'2026-05-11 13:25:58'),(32,1,'created','Reminders','Created reminder: Check harga domain',1,NULL,'2026-05-11 14:34:06'),(33,1,'completed','Reminders','Reminder marked as complete',1,NULL,'2026-05-12 09:28:03'),(34,1,'created','Reminders','Created reminder: Meeting PANDI',2,NULL,'2026-05-12 09:28:48'),(35,1,'completed','Reminders','Reminder marked as complete',2,NULL,'2026-05-12 10:22:05'),(36,1,'created','Reminders','Created reminder: check domain',3,NULL,'2026-05-12 11:06:59'),(37,1,'completed','Reminders','Reminder marked as complete',3,NULL,'2026-05-12 11:07:01'),(38,1,'created','Checklist','Added task: chck ini itu',8,NULL,'2026-05-12 11:08:22'),(39,1,'completed','Checklist','Task marked complete',8,NULL,'2026-05-12 11:08:32'),(40,1,'updated','Checklist','Task marked incomplete',8,NULL,'2026-05-12 11:08:43'),(41,1,'completed','Checklist','Task marked complete',8,NULL,'2026-05-12 11:39:22'),(42,1,'created','Checklist','Added task: Check abuse domain',9,NULL,'2026-05-12 11:42:01'),(43,1,'completed','Checklist','Task marked complete',9,NULL,'2026-05-12 11:42:06'),(44,1,'created','Shift Reports','Added shift report: Kendala S3',2,NULL,'2026-05-12 12:07:32'),(45,1,'updated','Checklist','Task marked incomplete',9,NULL,'2026-05-12 13:38:32'),(46,1,'completed','Checklist','Task marked complete',9,NULL,'2026-05-12 13:38:33'),(47,1,'created','Shift Reports','Added shift report: Konfirmasi pembayaran, error VM Extreme',3,NULL,'2026-05-12 15:39:22'),(48,1,'created','Checklist','Added task: chek in out',10,NULL,'2026-05-12 15:45:38'),(49,1,'created','Checklist','Added task: check in out',11,NULL,'2026-05-12 15:45:46'),(50,1,'created','Checklist','Added task: check notif',12,NULL,'2026-05-12 15:47:07'),(51,1,'completed','Checklist','Task marked complete',12,NULL,'2026-05-12 15:47:22'),(52,1,'completed','Checklist','Task marked complete',11,NULL,'2026-05-12 15:47:22'),(53,1,'completed','Checklist','Task marked complete',10,NULL,'2026-05-12 15:47:23'),(54,1,'created','Checklist','Added task: Check UI',13,NULL,'2026-05-12 18:40:16'),(55,1,'created','Cancellation Feedback','Added new cancellation feedback for Reseller Hosting cPanel — Reason: Service No Longer Required',1,NULL,'2026-05-12 18:42:56'),(56,1,'created','Shift Reports','Added shift report: Hidup joko',4,NULL,'2026-05-13 20:11:34'),(57,1,'completed','Checklist','Task marked complete',13,NULL,'2026-05-14 15:47:43'),(58,1,'mom_scheduled','MOM','Scheduled MOM: Meeting CS',NULL,NULL,'2026-05-14 20:55:02'),(59,1,'mom_started','MOM','Meeting started: Meeting CS',NULL,NULL,'2026-05-14 20:55:12'),(60,1,'mom_updated','MOM','Updated MOM #1',NULL,NULL,'2026-05-14 20:55:36'),(61,1,'mom_completed','MOM','Completed MOM #1',NULL,NULL,'2026-05-14 20:55:58'),(62,1,'mom_screenshot_uploaded','MOM','Uploaded screenshot for MOM #1',NULL,NULL,'2026-05-14 20:57:07'),(63,1,'mom_summary_saved','MOM','Saved MOM summary #1',NULL,NULL,'2026-05-14 20:57:12'),(64,1,'mom_screenshot_uploaded','MOM','Uploaded screenshot for MOM #1',NULL,NULL,'2026-05-15 07:36:52'),(65,1,'mom_case_status_updated','MOM','Case #23 marked as active after MOM review',NULL,NULL,'2026-05-15 07:46:10'),(66,1,'mom_case_status_updated','MOM','Case #23 marked as solved after MOM review',NULL,NULL,'2026-05-15 07:46:19'),(67,1,'mom_case_status_updated','MOM','Case #23 marked as solved after MOM review',NULL,NULL,'2026-05-15 07:46:26'),(68,1,'mom_case_status_updated','MOM','Case #23 marked as solved after MOM review',NULL,NULL,'2026-05-15 07:46:27'),(69,1,'mom_scheduled','MOM','Scheduled MOM: Meeting jumat',NULL,NULL,'2026-05-15 09:19:04'),(70,1,'completed','Checklist','Task marked complete',3,NULL,'2026-05-15 09:54:28'),(71,1,'completed','Checklist','Task marked complete',4,NULL,'2026-05-15 09:54:31'),(72,1,'completed','Checklist','Task marked complete',2,NULL,'2026-05-15 09:54:32'),(73,1,'completed','Checklist','Task marked complete',12,NULL,'2026-05-15 10:49:25'),(74,1,'completed','Checklist','Task marked complete',11,NULL,'2026-05-15 10:49:26'),(75,1,'completed','Checklist','Task marked complete',13,NULL,'2026-05-15 10:50:14'),(76,1,'completed','Checklist','Task marked complete',10,NULL,'2026-05-15 11:28:01'),(77,1,'completed','Checklist','Task marked complete',9,NULL,'2026-05-15 11:31:31'),(78,1,'updated','Cases','Updated case: Payment gateway timeout error',4,NULL,'2026-05-15 11:32:04'),(79,1,'completed','Reminders','Reminder marked as complete',5,NULL,'2026-05-15 12:55:39'),(80,1,'completed','Checklist','Task marked complete',8,NULL,'2026-05-15 12:55:41'),(81,1,'completed','Checklist','Task marked complete',7,NULL,'2026-05-15 12:55:44'),(82,1,'completed','Checklist','Task marked complete',6,NULL,'2026-05-15 12:55:44'),(83,1,'completed','Checklist','Task marked complete',5,NULL,'2026-05-15 12:55:45'),(84,1,'completed','Checklist','Task marked complete',1,NULL,'2026-05-15 12:55:46'),(85,1,'created','Checklist','Added task: check transfer domain',14,NULL,'2026-05-15 12:57:13'),(86,1,'completed','Checklist','Task marked complete',14,NULL,'2026-05-15 12:57:19'),(87,1,'created','Checklist','Added task: check ini itu',15,NULL,'2026-05-15 12:57:26'),(88,1,'created','Checklist','Added task: check dribbble',16,NULL,'2026-05-15 12:58:22'),(89,1,'created','Checklist','Added task: Check Light theme',17,NULL,'2026-05-15 12:58:55'),(90,1,'completed','Checklist','Task marked complete',15,NULL,'2026-05-15 12:59:07'),(91,1,'completed','Checklist','Task marked complete',17,NULL,'2026-05-15 13:51:26'),(92,1,'updated','Checklist','Task marked incomplete',15,NULL,'2026-05-15 13:51:27'),(93,1,'completed','Checklist','Task marked complete',15,NULL,'2026-05-15 13:51:27'),(94,1,'completed','Checklist','Task marked complete',16,NULL,'2026-05-15 13:51:28'),(95,1,'created','Cancellation Feedback','Added new cancellation feedback for Dedicated Server — Reason: Network latency / packet loss',2,NULL,'2026-05-15 14:50:30'),(96,1,'completed','Shift Reports','Resolved shift report: Kendala S3',2,NULL,'2026-05-15 18:23:59'),(97,1,'updated','Cases','Updated case: Payment gateway timeout error',4,NULL,'2026-05-15 20:03:21'),(98,1,'created','Reminders','Created reminder: Check token codex 11.45',6,NULL,'2026-05-15 20:06:59'),(99,1,'deleted','Reminders','Deleted reminder: Check token codex 11.45',6,NULL,'2026-05-15 20:07:19'),(100,1,'created','Checklist','Added task: redesign login button',18,NULL,'2026-05-15 20:09:07'),(101,1,'created','Checklist','Added task: check database schema',19,NULL,'2026-05-15 21:28:11'),(102,1,'created','Checklist','Added task: prepare for deploy',20,NULL,'2026-05-15 21:28:29'),(103,1,'created','Checklist','Added task: redesign checklist box',21,NULL,'2026-05-15 21:34:35'),(104,1,'updated','Checklist','Updated task: redesign global checklist box',21,NULL,'2026-05-15 21:34:42'),(105,1,'created','Checklist','Added task: hide flow download CSV',22,NULL,'2026-05-15 21:38:52'),(106,1,'created','Checklist','Added task: fix overwith table history MoM',23,NULL,'2026-05-15 21:48:01'),(107,1,'created','Checklist','Added task: add download feature on feedback page',24,NULL,'2026-05-15 21:56:35'),(108,1,'created','Checklist','Added task: create theme memory',25,NULL,'2026-05-15 22:00:24'),(109,1,'created','Checklist','Added task: add user previllage',26,NULL,'2026-05-15 22:00:33'),(110,1,'created','Checklist','Added task: Create \"My Global Signature Key\"',27,NULL,'2026-05-15 22:13:42'),(111,1,'updated','Checklist','Updated task: create theme memory',25,NULL,'2026-05-15 22:49:00'),(112,1,'completed','Checklist','Task marked complete',16,NULL,'2026-05-16 08:35:07'),(113,1,'completed','Checklist','Task marked complete',17,NULL,'2026-05-16 08:35:08'),(114,1,'completed','Checklist','Task marked complete',18,NULL,'2026-05-16 08:35:10'),(115,1,'completed','Checklist','Task marked complete',22,NULL,'2026-05-16 08:35:35'),(116,1,'completed','Checklist','Task marked complete',24,NULL,'2026-05-16 08:35:35'),(117,1,'completed','Checklist','Task marked complete',11,NULL,'2026-05-16 11:16:10'),(118,1,'completed','Checklist','Task marked complete',12,NULL,'2026-05-16 11:16:10'),(119,1,'completed','Checklist','Task marked complete',13,NULL,'2026-05-16 11:16:11'),(120,1,'completed','Checklist','Task marked complete',14,NULL,'2026-05-16 11:16:14'),(121,1,'completed','Checklist','Task marked complete',15,NULL,'2026-05-16 11:16:15'),(122,1,'completed','Checklist','Task marked complete',21,NULL,'2026-05-16 11:16:24'),(123,1,'completed','Checklist','Task marked complete',5,NULL,'2026-05-16 11:17:50'),(124,1,'completed','Checklist','Task marked complete',6,NULL,'2026-05-16 11:18:05'),(125,1,'completed','Checklist','Task marked complete',7,NULL,'2026-05-16 11:18:06'),(126,1,'completed','Checklist','Task marked complete',8,NULL,'2026-05-16 11:18:09'),(127,1,'completed','Checklist','Task marked complete',9,NULL,'2026-05-16 11:18:10'),(128,1,'created','Checklist','Added task: check the logic of reminder list',28,NULL,'2026-05-16 11:18:23'),(129,1,'completed','Checklist','Task marked complete',1,NULL,'2026-05-16 11:25:13'),(130,1,'completed','Checklist','Task marked complete',2,NULL,'2026-05-16 11:25:13'),(131,1,'completed','Checklist','Task marked complete',3,NULL,'2026-05-16 11:25:14'),(132,1,'completed','Checklist','Task marked complete',4,NULL,'2026-05-16 11:25:15'),(133,1,'completed','Checklist','Task marked complete',10,NULL,'2026-05-16 11:25:26'),(134,1,'created','Checklist','Added task: add user id on every item',29,NULL,'2026-05-16 11:34:31'),(135,1,'completed','Checklist','Task marked complete',29,NULL,'2026-05-16 11:34:34'),(136,1,'created','Checklist','Added task: check vickry.id',30,NULL,'2026-05-16 11:55:53'),(137,1,'completed','Checklist','Task marked complete',28,NULL,'2026-05-16 12:59:16'),(138,1,'completed','Checklist','Task marked complete',25,NULL,'2026-05-16 13:05:14'),(139,1,'completed','Checklist','Task marked complete',30,NULL,'2026-05-16 13:18:47'),(140,1,'completed','Checklist','Task marked complete',30,NULL,'2026-05-16 13:28:28'),(141,1,'created','Checklist','Added task: Add logic on + Button',31,NULL,'2026-05-16 13:29:49'),(142,1,'created','Checklist','Added task: Remove weird side lint accent on toast',32,NULL,'2026-05-16 13:30:18'),(143,1,'updated','Cases','Updated case: Email login delay issue login.masuk.email',5,NULL,'2026-05-16 13:35:57'),(144,1,'updated','Cases','Updated case: Node storage usage above threshold',17,NULL,'2026-05-16 13:36:18'),(145,1,'completed','Checklist','Task marked complete',32,NULL,'2026-05-16 13:36:37'),(146,1,'created','Checklist','Added task: Add responsive for Vickry.id',33,NULL,'2026-05-16 14:00:44'),(147,1,'created','Checklist','Added task: Add responsive for Vickry.id',34,NULL,'2026-05-16 14:00:46'),(148,1,'deleted','Checklist','Deleted task: Add responsive for Vickry.id',33,NULL,'2026-05-16 14:00:57'),(149,1,'completed','Checklist','Task marked complete',31,NULL,'2026-05-16 14:12:25'),(150,1,'completed','Checklist','Task marked complete',19,NULL,'2026-05-16 14:12:34'),(151,1,'completed','Checklist','Task marked complete',23,NULL,'2026-05-16 14:12:43'),(152,1,'created','Checklist','Added task: check toast style',35,NULL,'2026-05-16 14:12:52'),(153,1,'completed','Checklist','Task marked complete',35,NULL,'2026-05-16 14:12:56'),(154,1,'updated','Checklist','Task marked incomplete',35,NULL,'2026-05-16 14:14:43'),(155,1,'completed','Checklist','Task marked complete',35,NULL,'2026-05-16 14:14:52'),(156,1,'completed','Checklist','Task marked complete',34,NULL,'2026-05-16 14:15:53'),(157,1,'updated','Checklist','Task marked incomplete',34,NULL,'2026-05-16 14:16:14'),(158,1,'created','Checklist','Added task: Check the line or box divider on every item on Dashboard',36,NULL,'2026-05-16 14:21:06'),(159,1,'completed','Checklist','Task marked complete',36,NULL,'2026-05-16 14:34:28'),(160,1,'updated','Checklist','Task marked incomplete',36,NULL,'2026-05-16 14:34:38'),(161,1,'completed','Checklist','Task marked complete',36,NULL,'2026-05-16 14:34:47'),(162,1,'created','Reminders','Created reminder: Check CSS ke Claude',7,NULL,'2026-05-16 15:09:55'),(163,1,'created','Reminders','Created reminder: Revert kalau tidak memungkinkan',8,NULL,'2026-05-16 15:10:17'),(164,1,'completed','Reminders','Reminder marked as complete',7,NULL,'2026-05-16 15:26:12'),(165,1,'created','Checklist','Added task: ganti warna checkbox',37,NULL,'2026-05-16 16:11:36'),(166,1,'completed','Checklist','Task marked complete',37,NULL,'2026-05-16 16:11:40'),(167,1,'updated','Checklist','Task marked incomplete',37,NULL,'2026-05-16 16:11:47'),(168,1,'completed','Checklist','Task marked complete',37,NULL,'2026-05-16 16:11:49'),(169,1,'completed','Checklist','Task marked complete',20,NULL,'2026-05-16 16:12:34'),(170,1,'updated','Checklist','Task marked incomplete',37,NULL,'2026-05-16 16:12:45'),(171,1,'completed','Checklist','Task marked complete',37,NULL,'2026-05-16 16:15:36'),(172,1,'updated','Checklist','Task marked incomplete',25,NULL,'2026-05-16 16:17:16'),(173,1,'completed','Checklist','Task marked complete',25,NULL,'2026-05-16 16:26:39'),(174,1,'completed','Reminders','Reminder marked as complete',8,NULL,'2026-05-16 16:26:48'),(175,1,'mom_scheduled','MOM','Scheduled MOM: Test list',3,NULL,'2026-05-16 16:48:07'),(176,1,'mom_updated','MOM','Updated MOM #3',3,NULL,'2026-05-16 16:59:25'),(177,1,'mom_auto_started','MOM','Meeting auto-started: Test list',NULL,NULL,'2026-05-16 16:59:26'),(178,1,'mom_completed','MOM','Completed MOM #3',3,NULL,'2026-05-16 16:59:29'),(179,1,'created','Checklist','Added task: bug table line',38,NULL,'2026-05-16 17:06:56'),(180,1,'updated','Cancellation Feedback','Updated cancellation feedback for Dedicated Server',2,NULL,'2026-05-16 19:19:13'),(181,1,'completed','Checklist','Task marked complete',38,NULL,'2026-05-16 19:37:16'),(182,1,'updated','Checklist','Task marked incomplete',37,NULL,'2026-05-16 19:37:58'),(183,1,'completed','Checklist','Task marked complete',37,NULL,'2026-05-16 19:38:01'),(184,1,'updated','Checklist','Task marked incomplete',38,NULL,'2026-05-16 19:44:42'),(185,1,'completed','Checklist','Task marked complete',38,NULL,'2026-05-16 19:44:43'),(186,1,'updated','Checklist','Task marked incomplete',36,NULL,'2026-05-16 20:27:23'),(187,1,'updated','Checklist','Task marked incomplete',38,NULL,'2026-05-16 20:27:25'),(188,1,'completed','Checklist','Task marked complete',38,NULL,'2026-05-16 20:27:27'),(189,1,'mom_scheduled','MOM','Scheduled MOM: test span',4,NULL,'2026-05-16 20:37:23'),(190,1,'mom_auto_started','MOM','Meeting auto-started: test span',NULL,NULL,'2026-05-16 20:37:23'),(191,1,'mom_completed','MOM','Completed MOM #4',4,NULL,'2026-05-16 20:37:29'),(192,1,'mom_screenshot_uploaded','MOM','Uploaded screenshot for MOM #4',4,NULL,'2026-05-16 20:37:54'),(193,1,'completed','Checklist','Task marked complete',36,NULL,'2026-05-17 00:21:55'),(194,1,'created','Checklist','Added task: Check Bug MoM',39,NULL,'2026-05-17 00:24:46'),(195,1,'created','Checklist','Added task: login button harus punya light theme',40,NULL,'2026-05-17 00:31:32'),(196,1,'created','Checklist','Added task: font judul new task harus bold',41,NULL,'2026-05-17 00:31:52'),(197,1,'created','Checklist','Added task: check UI table add cancellation_feedback.php',42,NULL,'2026-05-17 00:43:17'),(198,1,'completed','Checklist','Task marked complete',39,NULL,'2026-05-17 00:46:33'),(199,1,'created','Checklist','Added task: change the logic of submitter',43,NULL,'2026-05-17 07:18:09'),(200,1,'created','Checklist','Added task: ganti design choice box biar match dengan design tracs',44,NULL,'2026-05-17 07:30:51'),(201,1,'completed','Checklist','Task marked complete',42,NULL,'2026-05-17 07:33:46'),(202,1,'completed','Checklist','Task marked complete',41,NULL,'2026-05-17 07:33:49'),(203,1,'created','Shift Reports','Added shift report: test font',5,NULL,'2026-05-17 07:34:45'),(204,1,'mom_scheduled','MOM','Scheduled MOM: test font',5,NULL,'2026-05-17 07:35:11'),(205,1,'mom_auto_started','MOM','Meeting auto-started: test font',5,NULL,'2026-05-17 07:35:11'),(206,1,'created','Checklist','Added task: check lagi logic dari reminder',45,NULL,'2026-05-17 07:54:11'),(207,1,'created','Checklist','Added task: logic delete/close reminder',46,NULL,'2026-05-17 08:18:51'),(208,1,'completed','Checklist','Task marked complete',44,NULL,'2026-05-17 08:28:45'),(209,1,'completed','Checklist','Task marked complete',40,NULL,'2026-05-17 08:33:34'),(210,1,'created','Reminders','Created reminder: test remindr',12,NULL,'2026-05-17 08:51:14'),(211,1,'completed','Reminders','Reminder marked as complete',12,NULL,'2026-05-17 09:07:12'),(212,1,'completed','Reminders','Reminder marked as complete',12,NULL,'2026-05-17 09:07:18'),(213,1,'completed','Reminders','Reminder marked as complete',12,NULL,'2026-05-17 09:07:19'),(214,1,'created','Reminders','Created reminder: test button',13,NULL,'2026-05-17 09:08:38'),(215,1,'completed','Checklist','Task marked complete',46,NULL,'2026-05-17 11:22:25'),(216,1,'completed','Reminders','Reminder marked as complete',13,NULL,'2026-05-17 12:08:06'),(217,1,'completed','Checklist','Task marked complete',45,NULL,'2026-05-17 12:08:17'),(218,1,'mom_screenshot_uploaded','MOM','Uploaded screenshot for MOM #4',4,NULL,'2026-05-17 13:55:57'),(219,1,'mom_summary_saved','MOM','Saved MOM summary #4',4,NULL,'2026-05-17 13:57:57'),(220,1,'mom_decision_recorded','MOM','Decision recorded in MOM #4',4,NULL,'2026-05-17 13:58:27'),(221,1,'mom_updated','MOM','Updated MOM #4',4,NULL,'2026-05-17 13:58:50'),(222,1,'mom_summary_saved','MOM','Saved MOM summary #4',4,NULL,'2026-05-17 13:58:57'),(223,1,'mom_action_created','MOM','Created MOM action: ini nihh',4,NULL,'2026-05-17 14:04:40'),(224,1,'mom_summary_saved','MOM','Saved MOM summary #4',4,NULL,'2026-05-17 14:05:15'),(225,1,'completed','Checklist','Task marked complete',43,NULL,'2026-05-17 14:14:42'),(226,1,'completed','Checklist','Task marked complete',34,NULL,'2026-05-17 14:14:43'),(227,1,'completed','Checklist','Task marked complete',27,NULL,'2026-05-17 14:16:40'),(228,1,'updated','Checklist','Task marked incomplete',37,NULL,'2026-05-17 14:17:42'),(229,1,'completed','Checklist','Task marked complete',37,NULL,'2026-05-17 15:04:52'),(230,1,'updated','Checklist','Task marked incomplete',39,NULL,'2026-05-17 15:09:05'),(231,1,'completed','Checklist','Task marked complete',39,NULL,'2026-05-17 15:09:12'),(232,1,'created','Reminders','Created reminder: test otast',14,NULL,'2026-05-17 15:10:14'),(233,1,'completed','Reminders','Reminder marked as complete',14,NULL,'2026-05-17 15:11:05'),(234,1,'uncompleted','Reminders','Reminder marked as incomplete',13,NULL,'2026-05-17 15:16:06'),(235,1,'completed','Reminders','Reminder marked as complete',13,NULL,'2026-05-17 15:16:12'),(236,1,'mom_updated','MOM','Updated MOM #4',4,NULL,'2026-05-17 15:53:59'),(237,1,'mom_updated','MOM','Updated MOM #4',4,NULL,'2026-05-17 15:54:06'),(238,1,'mom_updated','MOM','Updated MOM #4',4,NULL,'2026-05-17 15:54:26'),(239,1,'created','Checklist','Added task: check bot autoclose intercom',47,NULL,'2026-05-17 16:09:53'),(240,1,'completed','Checklist','Task marked complete',47,NULL,'2026-05-17 16:14:57'),(241,1,'create_user','User Management','create_user · user #4',4,'172.18.0.1','2026-05-17 23:23:47'),(242,1,'update_user','User Management','update_user · user #3',3,'172.18.0.1','2026-05-17 23:24:05'),(243,1,'completed','Checklist','Task marked complete',26,NULL,'2026-05-17 23:29:14'),(244,1,'created','Checklist','Added task: add page/feature for task asignment',48,NULL,'2026-05-17 23:45:21'),(245,1,'created','Checklist','Added task: Edit logic when adding intern user',49,NULL,'2026-05-18 00:03:19'),(246,1,'created','Checklist','Added task: remove User management overview section',50,NULL,'2026-05-18 00:04:15'),(247,1,'create_user','User Management','create_user · user #5',5,'172.18.0.1','2026-05-18 00:04:37'),(248,1,'intern_profile_created','User Management','intern_profile_created · user #5',5,'172.18.0.1','2026-05-18 00:04:37'),(249,1,'intern_user_created','User Management','intern_user_created · user #5',5,'172.18.0.1','2026-05-18 00:04:37'),(250,1,'created','Checklist','Added task: add tv or monitor mode',51,NULL,'2026-05-18 00:05:47'),(251,1,'task_created','User Management','task_created · task #1',1,'','2026-05-18 08:23:54'),(252,1,'task_created','User Management','task_created · task #2',2,'','2026-05-18 09:19:06'),(253,1,'task_created','User Management','task_created · task #3',3,'','2026-05-18 09:25:41'),(254,1,'mom_completed','MOM','Completed MOM #5',5,NULL,'2026-05-18 09:28:02'),(255,1,'mom_scheduled','MOM','Scheduled MOM: test',6,NULL,'2026-05-18 09:28:59'),(256,1,'mom_auto_started','MOM','Meeting auto-started: test',NULL,NULL,'2026-05-18 09:30:25'),(257,1,'completed','Checklist','Task marked complete',48,NULL,'2026-05-18 09:31:57'),(258,1,'completed','Checklist','Task marked complete',49,NULL,'2026-05-18 09:31:59'),(259,1,'completed','Checklist','Task marked complete',50,NULL,'2026-05-18 09:32:05'),(260,1,'created','Checklist','Added task: create fitur like averto',55,NULL,'2026-05-18 09:35:06'),(261,1,'completed','Checklist','Task marked complete',51,NULL,'2026-05-18 09:47:06'),(262,1,'created','Checklist','Added task: update file .MD',56,NULL,'2026-05-18 14:02:41'),(263,1,'completed','Checklist','Task marked complete',56,NULL,'2026-05-18 14:58:28'),(264,1,'create_user','User Management','create_user · user #6',6,'172.18.0.1','2026-05-18 15:07:08'),(265,1,'created','Checklist','Added task: put out search input on cancellation feedback filter',57,NULL,'2026-05-18 16:23:20'),(266,1,'created','Checklist','Added task: add profile picture user',58,NULL,'2026-05-18 16:28:06'),(267,1,'user_avatar_updated','User Management','user_avatar_updated · user #1',1,'172.18.0.1','2026-05-18 18:54:35'),(268,1,'created','Checklist','Added task: fix bug when hover avatar',59,NULL,'2026-05-18 18:57:00'),(269,1,'created','Checklist','Added task: fix the width on data-tracs-select-for in Resolution on feedback page',60,NULL,'2026-05-18 19:02:27'),(270,1,'completed','Checklist','Task marked complete',58,NULL,'2026-05-19 09:35:14'),(271,1,'completed','Checklist','Task marked complete',57,NULL,'2026-05-19 09:35:16'),(272,1,'created','Checklist','Added task: change logic for reminders',61,NULL,'2026-05-19 09:35:40'),(273,1,'created','Reminders','Created reminder: test add',19,NULL,'2026-05-19 10:13:10'),(274,1,'completed','Reminders','Reminder marked as complete',19,NULL,'2026-05-19 10:14:42'),(275,1,'mom_scheduled','MOM','Scheduled MOM: test meeting',7,NULL,'2026-05-19 10:45:44'),(276,1,'mom_completed','MOM','Completed MOM #6',6,NULL,'2026-05-19 10:45:51'),(277,1,'mom_screenshot_uploaded','MOM','Uploaded screenshot for MOM #6',6,NULL,'2026-05-19 10:46:27'),(278,1,'deleted','Checklist','Deleted task: create fitur like averto',55,NULL,'2026-05-19 11:01:00'),(279,1,'mom_auto_started','MOM','Meeting auto-started: test meeting',NULL,NULL,'2026-05-19 12:01:30'),(280,1,'updated','Cases','Updated case: Login issue on email portal',1,NULL,'2026-05-19 12:35:51'),(281,1,'user_changed_own_password','User Management','user_changed_own_password · user #1',1,'172.18.0.1','2026-05-19 13:10:58'),(282,1,'completed','Checklist','Task marked complete',59,NULL,'2026-05-19 13:42:40'),(283,1,'completed','Checklist','Task marked complete',60,NULL,'2026-05-19 13:42:46'),(284,1,'completed','Checklist','Task marked complete',61,NULL,'2026-05-19 13:42:48'),(285,1,'created','Checklist','Added task: remove NDS Critical etc. inside Affected',62,NULL,'2026-05-19 14:24:13'),(286,1,'created','Checklist','Added task: fix sliding bug in affected',63,NULL,'2026-05-19 14:52:34'),(287,1,'updated','Checklist','Updated task: remove status Critical etc. inside Affected',62,NULL,'2026-05-19 14:54:14'),(288,1,'created','Checklist','Added task: safety check for the permalink (?) ocalhost:8080/mom.php?mom_id=3',64,NULL,'2026-05-19 15:13:28'),(289,1,'created','Checklist','Added task: Add feature crosscheck pricing domain',65,NULL,'2026-05-19 15:16:48'),(290,1,'completed','Checklist','Task marked complete',65,NULL,'2026-05-20 07:10:53'),(291,3,'created','Domain Price','Created monthly draft record for 2026-05',1,NULL,'2026-05-20 07:21:43'),(292,3,'submitted','Domain Price','Submitted monthly record 2026-05 for review',1,NULL,'2026-05-20 07:21:57'),(293,3,'created','Domain Price','Created monthly draft record for 2026-05',2,NULL,'2026-05-20 07:30:40'),(294,3,'created','Domain Price','Created monthly draft record for 2026-05',3,NULL,'2026-05-20 07:30:55'),(295,1,'created','Domain Price','Created monthly draft record for 2026-05',4,NULL,'2026-05-20 07:32:18'),(296,1,'created','Domain Price','Created monthly draft record for 2026-05',103,NULL,'2026-05-20 07:56:41'),(297,1,'created','Domain Price','Created monthly draft record for 2099-01',104,NULL,'2026-05-20 08:11:38'),(298,1,'updated','Domain Price','Saved matrix changes: saved 4, cleared 0 entries for month 2099-01',104,NULL,'2026-05-20 08:11:38'),(299,1,'created','Domain Price','Created monthly record for 2099-02 by duplicating',105,NULL,'2026-05-20 08:11:38'),(300,1,'created','Domain Price','Created monthly draft record for 2099-03',106,NULL,'2026-05-20 08:13:34'),(301,1,'updated','Domain Price','Saved matrix changes: saved 6, cleared 0 entries for month 2099-03',106,NULL,'2026-05-20 08:13:34'),(302,1,'created','Checklist','Added task: import harga Registrar',66,NULL,'2026-05-20 08:18:59'),(303,1,'mom_scheduled','MOM','Scheduled MOM: Title',8,NULL,'2026-05-20 16:33:24'),(304,1,'mom_completed','MOM','Completed MOM #7',7,NULL,'2026-05-20 16:33:32'),(305,1,'completed','Checklist','Task marked complete',64,NULL,'2026-05-20 16:57:22'),(306,1,'completed','Checklist','Task marked complete',63,NULL,'2026-05-20 17:07:52'),(307,1,'completed','Checklist','Task marked complete',62,NULL,'2026-05-20 17:07:53'),(308,1,'mom_auto_started','MOM','Meeting auto-started: Title',NULL,NULL,'2026-05-20 18:06:45'),(309,1,'created','Shift Reports','Added shift report: test widget',6,NULL,'2026-05-20 18:17:57'),(310,1,'mom_completed','MOM','Completed MOM #8',8,NULL,'2026-05-20 18:53:10');
/*!40000 ALTER TABLE `tracs_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_auth_events`
--

DROP TABLE IF EXISTS `tracs_auth_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_auth_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `result` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `identifier` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auth_events_created` (`created_at`),
  KEY `idx_auth_events_type` (`event_type`,`result`),
  KEY `idx_auth_events_user` (`user_id`),
  KEY `idx_auth_events_identifier` (`identifier`),
  KEY `idx_auth_events_ip` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Authentication security audit events without passwords or tokens';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_auth_events`
--

LOCK TABLES `tracs_auth_events` WRITE;
/*!40000 ALTER TABLE `tracs_auth_events` DISABLE KEYS */;
INSERT INTO `tracs_auth_events` VALUES (9,'login_failed','failed',NULL,'asdasd@hmail.com','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','invalid_credentials','2026-05-21 10:57:51'),(10,'login_failed','failed',NULL,'asdasd@hmail.com','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','invalid_credentials','2026-05-21 10:57:54'),(21,'login_blocked','locked',NULL,'asdasd@hmail.com','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','temporary_lock','2026-05-21 10:57:58'),(22,'login_failed','failed',NULL,'asdasd@hmail.com','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','invalid_credentials','2026-05-21 10:58:09'),(23,'login_failed','failed',NULL,'asdasd@hmail.com','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','invalid_credentials','2026-05-21 10:58:20'),(24,'captcha_challenge','shown',NULL,'asdasd@hmail.com','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','failed_attempt_threshold','2026-05-21 10:58:22'),(25,'login_failed','failed',NULL,'asdasd@hmail.com','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','invalid_credentials','2026-05-21 10:58:22'),(26,'logout','success',NULL,'','172.18.0.1','curl/8.7.1',NULL,'2026-05-21 10:58:47'),(33,'login_success','success',1,'admin@tracs.local','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',NULL,'2026-05-21 11:03:26'),(34,'login_blocked','blocked',1,'admin@tracs.local','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','two_factor_schema_missing','2026-05-21 14:06:36'),(35,'login_blocked','blocked',1,'admin@tracs.local','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','two_factor_schema_missing','2026-05-21 14:07:02');
/*!40000 ALTER TABLE `tracs_auth_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_cancellation_feedback`
--

DROP TABLE IF EXISTS `tracs_cancellation_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_cancellation_feedback` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitter_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cancelled_service` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_details` text COLLATE utf8mb4_unicode_ci,
  `whmcs_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_address` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_resolution` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cf_email` (`email_address`),
  KEY `idx_cf_date` (`created_at`),
  KEY `idx_feedback_created_by` (`created_by`),
  KEY `idx_cf_service` (`cancelled_service`(100)),
  KEY `idx_cf_reason` (`cancellation_reason`(150)),
  KEY `idx_cf_analytics` (`created_at`,`cancelled_service`(100),`cancellation_reason`(150),`payment_resolution`),
  KEY `idx_cf_filter` (`cancelled_service`(100),`cancellation_reason`(150),`payment_resolution`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_cancellation_feedback`
--

LOCK TABLES `tracs_cancellation_feedback` WRITE;
/*!40000 ALTER TABLE `tracs_cancellation_feedback` DISABLE KEYS */;
INSERT INTO `tracs_cancellation_feedback` VALUES (1,NULL,NULL,'Vickry','Reseller Hosting cPanel','Service No Longer Required','','TICKET #231234','','End of Billing Periode','2026-05-12 11:42:56','2026-05-12 11:42:56'),(2,NULL,NULL,'vickry','Dedicated Server','Network latency / packet loss','Test input ini itu dan ini itu','','','','2026-05-15 07:50:30','2026-05-16 12:19:13');
/*!40000 ALTER TABLE `tracs_cancellation_feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_cases`
--

DROP TABLE IF EXISTS `tracs_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_cases` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `notes` text,
  `status` enum('active','pending','stuck','completed') DEFAULT 'active',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `next_check_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_cases_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_cases`
--

LOCK TABLES `tracs_cases` WRITE;
/*!40000 ALTER TABLE `tracs_cases` DISABLE KEYS */;
INSERT INTO `tracs_cases` VALUES (1,1,1,NULL,'Login issue on email portal','User cannot login intermittently on email system','completed','high','2026-05-08 20:32:00','2026-05-08 18:32:47','2026-05-19 12:35:51'),(2,1,1,NULL,'Domain transfer stuck verification','Domain stuck at registrar approval stage','stuck','high','2026-05-08 23:32:47','2026-05-08 18:32:47','2026-05-08 18:32:47'),(3,1,1,NULL,'SSL renewal request','SSL renewal completed but not propagated','completed','medium','2026-05-09 18:32:47','2026-05-08 18:32:47','2026-05-08 18:32:47'),(4,1,1,NULL,'Payment gateway timeout error','','completed','critical','2026-05-08 19:02:00','2026-05-08 18:32:47','2026-05-15 20:03:21'),(5,1,1,NULL,'Email login delay issue login.masuk.email','','completed','critical','2026-05-08 18:47:00','2026-05-08 18:32:47','2026-05-16 13:35:57'),(6,1,1,NULL,'Payment gateway timeout on checkout API','Random timeout detected during callback validation from Midtrans.','active','critical','2026-05-08 20:43:11','2026-05-08 18:26:11','2026-05-08 20:26:11'),(7,1,1,NULL,'SMTP login latency on login.masuk.email','High response time reported from Jakarta region.','pending','critical','2026-05-08 21:06:11','2026-05-08 16:26:11','2026-05-08 20:26:11'),(8,1,1,NULL,'Domain transfer pending registry approval','Waiting manual approval from registrar.','stuck','high','2026-05-08 23:26:11','2026-05-06 20:26:11','2026-05-08 20:26:11'),(9,1,1,NULL,'SSL auto renewal failed for client node','LetsEncrypt renewal hook returned invalid challenge.','active','high','2026-05-09 01:26:11','2026-05-07 20:26:11','2026-05-08 20:26:11'),(10,1,1,NULL,'DNS propagation mismatch Cloudflare','Several NS still resolving old IP.','pending','medium','2026-05-09 04:26:11','2026-05-08 14:26:11','2026-05-08 20:26:11'),(11,1,1,NULL,'Backup replication lag detected','Remote storage replication delayed more than 2 hours.','active','critical','2026-05-08 20:51:11','2026-05-08 13:26:11','2026-05-08 20:26:11'),(12,1,1,NULL,'cPanel migration validation','Need to verify migrated accounts and email routes.','pending','medium','2026-05-09 20:26:11','2026-05-07 20:26:11','2026-05-08 20:26:11'),(13,1,1,NULL,'WHM service unstable after reboot','Temporary spike detected after nightly maintenance.','active','high','2026-05-08 22:26:11','2026-05-08 17:26:11','2026-05-08 20:26:11'),(14,1,1,NULL,'Client unable to receive external email','Possible MX misconfiguration after DNS update.','active','high','2026-05-08 21:26:11','2026-05-08 19:56:11','2026-05-08 20:26:11'),(15,1,1,NULL,'Invoice callback duplicated','Finance callback generated duplicated mutation logs.','pending','medium','2026-05-09 02:26:11','2026-05-08 12:26:11','2026-05-08 20:26:11'),(16,1,1,NULL,'Transfer domain awaiting EPP confirmation','Client has not confirmed EPP unlock status.','stuck','high','2026-05-09 06:26:11','2026-05-05 20:26:11','2026-05-08 20:26:11'),(17,1,1,NULL,'Node storage usage above threshold','Disk usage reached 91% on backup node.','completed','critical','2026-05-08 20:38:00','2026-05-08 19:26:11','2026-05-16 13:36:18'),(18,1,1,NULL,'Email queue delayed','Queue processing slower than expected.','active','high','2026-05-08 21:11:11','2026-05-08 18:26:11','2026-05-08 20:26:11'),(19,1,1,NULL,'SSL validation completed','SSL replacement deployed successfully.','completed','medium','2026-05-10 20:26:11','2026-05-03 20:26:11','2026-05-08 20:26:11'),(20,1,1,NULL,'Registrar API rate limit reached','Temporary cooldown applied by registrar.','pending','medium','2026-05-09 00:26:11','2026-05-08 16:26:11','2026-05-08 20:26:11'),(21,1,1,NULL,'Manual verification for payment mutation','Need finance team approval.','stuck','medium','2026-05-09 20:26:11','2026-05-07 20:26:11','2026-05-08 20:26:11'),(22,1,1,NULL,'Database replication healthy','Replication issue resolved after restart.','completed','low','2026-05-11 20:26:11','2026-05-02 20:26:11','2026-05-08 20:26:11'),(23,1,1,NULL,'API latency spike detected','Monitoring alert triggered from Singapore node.\n\n[MOM #1 - 2026-05-15 07:46]\nStatus updated from MOM review.\n\n[MOM #1 - 2026-05-15 07:46]\nStatus updated from MOM review.\n\n[MOM #1 - 2026-05-15 07:46]\nStatus updated from MOM review.\n\n[MOM #1 - 2026-05-15 07:46]\nStatus updated from MOM review.','completed','critical','2026-05-08 20:35:11','2026-05-08 20:06:11','2026-05-15 07:46:27'),(24,1,1,NULL,'Cloud backup checksum mismatch','Need manual checksum validation.','pending','high','2026-05-09 03:26:11','2026-05-08 08:26:11','2026-05-08 20:26:11'),(25,1,1,NULL,'Expired SSL cleanup pending','Old certificates still detected on edge node.','pending','low','2026-05-10 20:26:11','2026-05-06 20:26:11','2026-05-08 20:26:11');
/*!40000 ALTER TABLE `tracs_cases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_currency_history`
--

DROP TABLE IF EXISTS `tracs_currency_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_currency_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(10) DEFAULT NULL,
  `to_currency` varchar(10) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `result` decimal(15,2) DEFAULT NULL,
  `rate` decimal(15,6) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=736 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_currency_history`
--

LOCK TABLES `tracs_currency_history` WRITE;
/*!40000 ALTER TABLE `tracs_currency_history` DISABLE KEYS */;
INSERT INTO `tracs_currency_history` VALUES (1,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:07:17'),(2,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:07:18'),(3,'IDR','USD',2500000.00,143.96,0.000058,'2026-05-11 12:07:27'),(4,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:12:33'),(5,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:12:35'),(6,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:13:33'),(7,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:13:34'),(8,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:13:36'),(9,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:13:36'),(10,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:13:37'),(11,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:13:38'),(12,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:13:49'),(13,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:23:59'),(14,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:29:26'),(15,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:30:09'),(16,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:30:14'),(17,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:35:53'),(18,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:37:20'),(19,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:37:36'),(20,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:38:26'),(21,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:40:43'),(22,'IDR','USD',500000.00,28.79,0.000058,'2026-05-11 12:40:48'),(23,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:41:19'),(24,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:41:33'),(25,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:42:29'),(26,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:44:49'),(27,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:48:24'),(28,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:53:34'),(29,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:54:28'),(30,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:55:01'),(31,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 12:56:08'),(32,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 13:06:11'),(33,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 13:08:51'),(34,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 13:24:49'),(35,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 14:24:56'),(36,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 14:25:17'),(37,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 14:25:27'),(38,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 14:33:46'),(39,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 14:33:51'),(40,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 14:34:13'),(41,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:11:11'),(42,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:11:11'),(43,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:11:14'),(44,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:11:36'),(45,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:11:38'),(46,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:14:39'),(47,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:27:14'),(48,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:42:31'),(49,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:42:42'),(50,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:44:09'),(51,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:52:19'),(52,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:52:27'),(53,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:54:06'),(54,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 15:55:38'),(55,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 16:03:38'),(56,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 20:45:02'),(57,'IDR','USD',1000000.00,57.58,0.000058,'2026-05-11 20:46:44'),(58,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 08:31:34'),(59,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 08:47:57'),(60,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:06:31'),(61,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:11:30'),(62,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:11:35'),(63,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:11:36'),(64,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:11:48'),(65,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:12:53'),(66,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:13:14'),(67,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:13:16'),(68,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:13:22'),(69,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:13:22'),(70,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:15:44'),(71,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:15:47'),(72,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:17:50'),(73,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:17:54'),(74,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:20:48'),(75,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:20:54'),(76,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:21:18'),(77,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:22:41'),(78,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:22:45'),(79,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:23:10'),(80,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:25:44'),(81,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:27:38'),(82,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:28:51'),(83,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:31:18'),(84,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:35:10'),(85,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:37:14'),(86,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:38:34'),(87,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:38:56'),(88,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:39:01'),(89,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:42:45'),(90,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 09:44:57'),(91,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:08:03'),(92,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:22:50'),(93,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:29:15'),(94,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:29:19'),(95,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:29:25'),(96,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:29:31'),(97,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:31:07'),(98,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:33:29'),(99,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:38:07'),(100,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:39:41'),(101,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:41:32'),(102,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:43:23'),(103,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:43:56'),(104,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:45:51'),(105,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:46:55'),(106,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:48:47'),(107,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:49:52'),(108,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:50:37'),(109,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:51:24'),(110,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:51:39'),(111,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:51:40'),(112,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:53:25'),(113,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:54:52'),(114,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:55:50'),(115,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:56:02'),(116,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:56:06'),(117,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:56:07'),(118,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:57:33'),(119,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 10:59:37'),(120,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:00:35'),(121,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:00:52'),(122,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:00:57'),(123,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:02:18'),(124,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:03:25'),(125,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:04:30'),(126,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:06:41'),(127,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:07:00'),(128,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:08:16'),(129,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:08:23'),(130,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:08:43'),(131,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:08:45'),(132,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:11:19'),(133,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:13:18'),(134,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:15:25'),(135,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:16:48'),(136,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:17:03'),(137,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:17:18'),(138,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:21:56'),(139,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:23:22'),(140,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:25:28'),(141,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:27:11'),(142,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:28:15'),(143,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:31:39'),(144,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:36:10'),(145,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:37:55'),(146,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:39:29'),(147,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:41:45'),(148,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:42:02'),(149,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:42:28'),(150,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:44:52'),(151,'IDR','USD',10000700.00,573.94,0.000057,'2026-05-12 11:45:07'),(152,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:50:00'),(153,'IDR','USD',10000200.00,573.91,0.000057,'2026-05-12 11:51:21'),(154,'USD','IDR',1.00,17425.00,17425.000000,'2026-05-12 11:51:33'),(155,'USD','IDR',1.00,17425.00,17425.000000,'2026-05-12 11:51:34'),(156,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:51:53'),(157,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:52:35'),(158,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:54:42'),(159,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 11:56:06'),(160,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 12:06:53'),(161,'IDR','USD',1000543000.00,57421.00,0.000057,'2026-05-12 12:07:01'),(162,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 12:07:33'),(163,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 12:35:42'),(164,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 13:27:44'),(165,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 13:30:54'),(166,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 14:33:50'),(167,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 14:45:00'),(168,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:29:10'),(169,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:38:39'),(170,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:39:23'),(171,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:45:32'),(172,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:45:39'),(173,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:45:42'),(174,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:45:47'),(175,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:47:10'),(176,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 15:49:33'),(177,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 18:18:01'),(178,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 18:25:06'),(179,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 18:39:36'),(180,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 18:39:36'),(181,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 18:40:17'),(182,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 19:00:11'),(183,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 19:03:36'),(184,'IDR','USD',1000000.00,57.39,0.000057,'2026-05-12 19:04:28'),(185,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 12:45:52'),(186,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 12:47:07'),(187,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 20:07:35'),(188,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 20:09:29'),(189,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 20:10:57'),(190,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 20:11:21'),(191,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 20:11:35'),(192,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 20:11:48'),(193,'IDR','USD',1000000.00,57.15,0.000057,'2026-05-13 20:12:02'),(194,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-13 22:47:38'),(195,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-13 22:47:42'),(196,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-13 22:52:03'),(197,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 00:43:08'),(198,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 13:56:01'),(199,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 15:47:24'),(200,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 17:01:05'),(201,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 17:06:17'),(202,'USD','IDR',150000.00,2624451985.00,17496.346567,'2026-05-14 17:07:00'),(203,'IDR','USD',150000.00,8.57,0.000057,'2026-05-14 17:07:03'),(204,'IDR','USD',150000.00,8.57,0.000057,'2026-05-14 17:07:05'),(205,'IDR','USD',50000.00,2.86,0.000057,'2026-05-14 17:07:45'),(206,'USD','IDR',50000.00,874817328.00,17496.346560,'2026-05-14 17:07:46'),(207,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 17:33:25'),(208,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 17:35:24'),(209,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 17:35:45'),(210,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 17:35:52'),(211,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 17:50:50'),(212,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 18:39:02'),(213,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 18:46:21'),(214,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 18:59:52'),(215,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:04:22'),(216,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:18:08'),(217,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:18:40'),(218,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:19:17'),(219,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:19:40'),(220,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:21:53'),(221,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:28:52'),(222,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:31:23'),(223,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:38:53'),(224,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:49:59'),(225,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 19:54:49'),(226,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 20:28:08'),(227,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 20:37:54'),(228,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 20:43:32'),(229,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 20:44:27'),(230,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 20:45:21'),(231,'IDR','USD',1000000.00,57.16,0.000057,'2026-05-14 21:07:35'),(232,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 07:27:12'),(233,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 07:27:44'),(234,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 07:28:19'),(235,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 07:42:59'),(236,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 07:44:48'),(237,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:12:13'),(238,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:16:27'),(239,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:16:58'),(240,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:18:16'),(241,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:18:17'),(242,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:19:15'),(243,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:36:32'),(244,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:36:56'),(245,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:37:57'),(246,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:46:23'),(247,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 09:53:17'),(248,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 10:48:39'),(249,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 11:27:59'),(250,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 11:31:06'),(251,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 11:32:07'),(252,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 11:34:10'),(253,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 11:37:54'),(254,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 11:40:29'),(255,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 12:53:12'),(256,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 12:56:41'),(257,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 12:57:14'),(258,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 12:57:27'),(259,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 12:58:25'),(260,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 12:58:41'),(261,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 12:58:56'),(262,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:13:41'),(263,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:13:58'),(264,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:20:27'),(265,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:45:32'),(266,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:46:05'),(267,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:47:05'),(268,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:47:05'),(269,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:48:15'),(270,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:49:14'),(271,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:49:42'),(272,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 13:57:36'),(273,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:01:12'),(274,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:03:21'),(275,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:03:31'),(276,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:34:06'),(277,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:34:07'),(278,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:37:01'),(279,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:37:53'),(280,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:43:54'),(281,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:44:23'),(282,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:44:29'),(283,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:44:55'),(284,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:45:19'),(285,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:45:21'),(286,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:46:22'),(287,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:46:25'),(288,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:46:27'),(289,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:46:35'),(290,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:48:10'),(291,'IDR','USD',1.00,0.00,0.000057,'2026-05-15 14:48:35'),(292,'USD','IDR',1.00,17465.00,17465.000000,'2026-05-15 14:48:40'),(293,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:50:50'),(294,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:51:25'),(295,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 14:52:31'),(296,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 15:04:44'),(297,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 15:08:08'),(298,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 15:08:09'),(299,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 15:09:00'),(300,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 15:09:03'),(301,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 15:09:58'),(302,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 15:12:42'),(303,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 15:34:44'),(304,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 16:58:13'),(305,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 18:24:12'),(306,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 18:27:48'),(307,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 18:38:56'),(308,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:19:02'),(309,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:20:17'),(310,'IDR','USD',300000.00,17.18,0.000057,'2026-05-15 19:20:26'),(311,'USD','IDR',300000.00,5239499231.00,17464.997437,'2026-05-15 19:20:33'),(312,'USD','IDR',1.00,17465.00,17465.000000,'2026-05-15 19:20:39'),(313,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:21:13'),(314,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:28:08'),(315,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:28:22'),(316,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:28:48'),(317,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:29:16'),(318,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:30:02'),(319,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:30:24'),(320,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:34:04'),(321,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:34:51'),(322,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:35:18'),(323,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:35:22'),(324,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:35:40'),(325,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:35:43'),(326,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:36:14'),(327,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:36:45'),(328,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:41:15'),(329,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:43:35'),(330,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:43:58'),(331,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:46:48'),(332,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:46:50'),(333,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:46:51'),(334,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:48:25'),(335,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:50:27'),(336,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:52:11'),(337,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:54:15'),(338,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:55:40'),(339,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:57:29'),(340,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 19:59:34'),(341,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:00:43'),(342,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:03:11'),(343,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:03:22'),(344,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:03:34'),(345,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:04:16'),(346,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:07:04'),(347,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:07:18'),(348,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:07:31'),(349,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:08:49'),(350,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:09:08'),(351,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:19:42'),(352,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:20:46'),(353,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:36:26'),(354,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:43:38'),(355,'IDR','USD',1000000.00,57.26,0.000057,'2026-05-15 20:45:21'),(356,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:28:05'),(357,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:28:14'),(358,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:28:30'),(359,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:33:08'),(360,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:34:37'),(361,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:34:43'),(362,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:38:39'),(363,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:38:52'),(364,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:48:04'),(365,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:56:11'),(366,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:56:36'),(367,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 21:58:53'),(368,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 22:00:27'),(369,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 22:00:34'),(370,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 22:13:44'),(371,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-15 22:49:03'),(372,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 08:19:03'),(373,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 08:34:52'),(374,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 11:12:43'),(375,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 11:16:05'),(376,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 11:17:42'),(377,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 11:18:28'),(378,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 11:25:08'),(379,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 11:34:34'),(380,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 11:55:34'),(381,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 11:55:54'),(382,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 12:59:15'),(383,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:04:32'),(384,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:05:53'),(385,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:06:56'),(386,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:07:13'),(387,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:07:43'),(388,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:09:00'),(389,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:11:28'),(390,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:15:18'),(391,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:18:46'),(392,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:28:40'),(393,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:29:55'),(394,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:30:20'),(395,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:31:50'),(396,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:33:10'),(397,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:36:02'),(398,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:36:05'),(399,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:36:10'),(400,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:36:20'),(401,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:43:00'),(402,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:43:17'),(403,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:44:49'),(404,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:46:40'),(405,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:49:51'),(406,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:50:16'),(407,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:51:55'),(408,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 13:56:03'),(409,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:00:54'),(410,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:07:20'),(411,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:08:27'),(412,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:12:21'),(413,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:12:40'),(414,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:12:54'),(415,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:14:41'),(416,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:19:50'),(417,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:21:10'),(418,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:31:42'),(419,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:33:22'),(420,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:34:35'),(421,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:43:04'),(422,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:52:29'),(423,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:53:36'),(424,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 14:58:14'),(425,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:02:55'),(426,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:03:01'),(427,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:03:23'),(428,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:03:25'),(429,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:07:00'),(430,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:09:03'),(431,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:09:57'),(432,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:10:19'),(433,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:16:42'),(434,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 15:27:42'),(435,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:11:24'),(436,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:11:37'),(437,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:11:43'),(438,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:12:30'),(439,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:12:50'),(440,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:12:51'),(441,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:12:53'),(442,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:13:23'),(443,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:15:02'),(444,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:15:21'),(445,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:15:24'),(446,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:17:14'),(447,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:17:20'),(448,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:26:30'),(449,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:31:58'),(450,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:32:04'),(451,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 16:58:01'),(452,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 17:06:40'),(453,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 17:06:57'),(454,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 17:18:22'),(455,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 17:21:20'),(456,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 17:24:54'),(457,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 18:38:07'),(458,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 19:37:00'),(459,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 19:37:19'),(460,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 19:37:55'),(461,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 19:44:29'),(462,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 19:48:53'),(463,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 19:50:52'),(464,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 19:52:51'),(465,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:21:05'),(466,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:21:16'),(467,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:21:17'),(468,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:21:19'),(469,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:27:14'),(470,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:27:19'),(471,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:27:21'),(472,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:27:41'),(473,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:29:33'),(474,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:35:47'),(475,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 20:52:00'),(476,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 21:00:14'),(477,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 21:06:21'),(478,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 21:11:16'),(479,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 22:46:26'),(480,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 22:59:36'),(481,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:01:16'),(482,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:01:31'),(483,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:02:06'),(484,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:02:32'),(485,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:02:37'),(486,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:23:12'),(487,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:23:39'),(488,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:28:34'),(489,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-16 23:34:06'),(490,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 00:05:19'),(491,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 00:21:37'),(492,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 00:43:02'),(493,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 00:43:19'),(494,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 00:45:36'),(495,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 00:46:31'),(496,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 06:46:46'),(497,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 06:46:55'),(498,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:17:56'),(499,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:18:10'),(500,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:30:12'),(501,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:30:52'),(502,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:31:30'),(503,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:34:47'),(504,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:35:16'),(505,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:35:55'),(506,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:38:32'),(507,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:41:13'),(508,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:51:29'),(509,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:52:52'),(510,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:53:51'),(511,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:54:14'),(512,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:56:38'),(513,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 07:58:47'),(514,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:03:34'),(515,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:18:15'),(516,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:18:39'),(517,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:18:52'),(518,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:19:23'),(519,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:33:08'),(520,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:33:29'),(521,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:38:02'),(522,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:45:28'),(523,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:47:15'),(524,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 08:51:16'),(525,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 09:06:42'),(526,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 09:07:53'),(527,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 09:08:45'),(528,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 09:14:26'),(529,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 11:22:31'),(530,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 12:06:07'),(531,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 12:07:57'),(532,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 12:56:40'),(533,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 13:26:59'),(534,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 13:27:14'),(535,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 13:27:48'),(536,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 13:29:02'),(537,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 13:54:25'),(538,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 14:14:14'),(539,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 14:16:51'),(540,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 14:54:19'),(541,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 14:55:05'),(542,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 15:09:12'),(543,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 15:10:02'),(544,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 15:10:17'),(545,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 15:16:11'),(546,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 15:40:59'),(547,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 15:57:46'),(548,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 16:09:46'),(549,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 16:09:54'),(550,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 23:13:21'),(551,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 23:29:11'),(552,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 23:44:55'),(553,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 23:45:24'),(554,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-17 23:47:20'),(555,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 00:01:48'),(556,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 00:02:58'),(557,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 00:03:20'),(558,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 00:03:51'),(559,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 00:04:17'),(560,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 00:05:39'),(561,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 00:05:48'),(562,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 07:58:15'),(563,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:21:46'),(564,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:25:35'),(565,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:25:53'),(566,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:27:49'),(567,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:29:04'),(568,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:30:27'),(569,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:31:54'),(570,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:33:56'),(571,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:35:00'),(572,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:35:07'),(573,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:43:02'),(574,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:47:05'),(575,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:51:28'),(576,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:55:10'),(577,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:55:53'),(578,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 09:59:09'),(579,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 10:16:37'),(580,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 10:17:19'),(581,'IDR','USD',166000.00,9.44,0.000057,'2026-05-18 11:18:46'),(582,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 14:02:32'),(583,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 14:02:42'),(584,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 14:22:35'),(585,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 14:58:27'),(586,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:10:21'),(587,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:10:56'),(588,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:10:56'),(589,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:10:57'),(590,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:10:58'),(591,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:11:49'),(592,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:11:54'),(593,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:12:34'),(594,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:12:36'),(595,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:12:45'),(596,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:13:13'),(597,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:17:25'),(598,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:17:58'),(599,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:18:00'),(600,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:18:01'),(601,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:18:02'),(602,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:18:18'),(603,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:18:56'),(604,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:28:17'),(605,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:30:00'),(606,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:30:01'),(607,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:30:02'),(608,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:30:04'),(609,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:30:18'),(610,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:31:25'),(611,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:31:43'),(612,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:31:44'),(613,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:31:44'),(614,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:31:46'),(615,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:32:08'),(616,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:32:54'),(617,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:32:55'),(618,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:32:57'),(619,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:32:59'),(620,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 15:33:29'),(621,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 16:20:03'),(622,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 16:22:54'),(623,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 16:23:21'),(624,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 16:27:56'),(625,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 16:28:07'),(626,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 18:54:59'),(627,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 18:55:07'),(628,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 18:57:02'),(629,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 19:01:46'),(630,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 19:02:29'),(631,'IDR','USD',1000000.00,56.86,0.000057,'2026-05-18 19:55:20'),(632,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-18 23:53:10'),(633,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 09:34:45'),(634,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 09:35:04'),(635,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 09:35:42'),(636,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:13:04'),(637,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:13:11'),(638,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:14:42'),(639,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:17:07'),(640,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:34:08'),(641,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:43:42'),(642,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:43:49'),(643,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:47:43'),(644,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:51:03'),(645,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:51:59'),(646,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:52:34'),(647,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 10:59:32'),(648,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:01:07'),(649,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:01:14'),(650,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:02:36'),(651,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:03:15'),(652,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:04:20'),(653,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:04:21'),(654,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:04:23'),(655,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:04:27'),(656,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:04:55'),(657,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:04:57'),(658,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:07:08'),(659,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:07:12'),(660,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:10:36'),(661,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:12:07'),(662,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:26:56'),(663,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:31:39'),(664,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 11:31:40'),(665,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 12:01:33'),(666,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 12:02:00'),(667,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 12:35:16'),(668,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 12:35:52'),(669,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 12:36:47'),(670,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 13:10:11'),(671,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 13:11:14'),(672,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 13:43:06'),(673,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 14:24:16'),(674,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 14:51:51'),(675,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 14:52:37'),(676,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 14:53:51'),(677,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 14:54:16'),(678,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 15:12:54'),(679,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 15:13:29'),(680,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 15:16:38'),(681,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 15:16:49'),(682,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 19:36:08'),(683,'IDR','USD',1000000.00,56.55,0.000057,'2026-05-19 19:40:38'),(684,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 06:44:15'),(685,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 06:44:40'),(686,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 07:10:52'),(687,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 07:16:54'),(688,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 08:18:51'),(689,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 08:19:00'),(690,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 15:58:12'),(691,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 16:57:08'),(692,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 17:05:39'),(693,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 17:05:58'),(694,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 17:06:59'),(695,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 17:07:25'),(696,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 17:27:57'),(697,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 17:28:27'),(698,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:06:47'),(699,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:09:37'),(700,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:10:52'),(701,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:11:12'),(702,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:13:36'),(703,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:13:54'),(704,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:16:36'),(705,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:16:50'),(706,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:17:09'),(707,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:17:59'),(708,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:18:44'),(709,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:24:28'),(710,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:31:55'),(711,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:44:47'),(712,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:52:24'),(713,'IDR','USD',1000000.00,56.36,0.000056,'2026-05-20 18:53:25'),(714,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 08:19:35'),(715,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 08:20:15'),(716,'USD','IDR',1000000.00,17700551724.00,17700.551724,'2026-05-21 08:20:20'),(717,'USD','IDR',1.00,17701.00,17701.000000,'2026-05-21 08:20:22'),(718,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 08:22:46'),(719,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 08:24:17'),(720,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 08:47:33'),(721,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 09:14:33'),(722,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 09:34:10'),(723,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 09:44:11'),(724,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 09:46:30'),(725,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 09:52:43'),(726,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 09:53:34'),(727,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 09:53:51'),(728,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 09:56:06'),(729,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 10:02:53'),(730,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 10:04:34'),(731,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 10:16:55'),(732,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 10:24:45'),(733,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 10:25:22'),(734,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 10:31:18'),(735,'IDR','USD',1000000.00,56.50,0.000056,'2026-05-21 11:03:28');
/*!40000 ALTER TABLE `tracs_currency_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_divisions`
--

DROP TABLE IF EXISTS `tracs_divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_divisions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `supervisor_id` int unsigned DEFAULT NULL,
  `status` enum('active','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_divisions_code` (`code`),
  KEY `idx_tracs_divisions_status` (`status`),
  KEY `idx_tracs_divisions_supervisor` (`supervisor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_divisions`
--

LOCK TABLES `tracs_divisions` WRITE;
/*!40000 ALTER TABLE `tracs_divisions` DISABLE KEYS */;
INSERT INTO `tracs_divisions` VALUES (1,'Customer Support','CS','',1,'active',1,1,'2026-05-17 23:14:15','2026-05-17 23:14:15');
/*!40000 ALTER TABLE `tracs_divisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_domains`
--

DROP TABLE IF EXISTS `tracs_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_domains` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `domain` varchar(253) NOT NULL,
  `registrar` varchar(200) DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `ssl_active` tinyint(1) DEFAULT '0',
  `auto_renew` tinyint(1) DEFAULT '0',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_tracs_domains_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_domains`
--

LOCK TABLES `tracs_domains` WRITE;
/*!40000 ALTER TABLE `tracs_domains` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_domains` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_finance_transfers`
--

DROP TABLE IF EXISTS `tracs_finance_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_finance_transfers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `note` varchar(500) NOT NULL,
  `from_account` varchar(200) DEFAULT NULL,
  `to_account` varchar(200) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `direction` enum('in','out') DEFAULT 'out',
  `status` enum('completed','pending','failed') DEFAULT 'pending',
  `transfer_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_date` (`transfer_date`),
  KEY `idx_tracs_finance_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_finance_transfers`
--

LOCK TABLES `tracs_finance_transfers` WRITE;
/*!40000 ALTER TABLE `tracs_finance_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_finance_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_login_attempts`
--

DROP TABLE IF EXISTS `tracs_login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `identifier_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier_display` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_id` int DEFAULT NULL,
  `failed_attempts` int unsigned NOT NULL DEFAULT '0',
  `first_failed_at` datetime DEFAULT NULL,
  `last_failed_at` datetime DEFAULT NULL,
  `locked_until` datetime DEFAULT NULL,
  `captcha_required_until` datetime DEFAULT NULL,
  `last_result` enum('failed','locked','success') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'failed',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login_attempt_identifier_ip` (`identifier_hash`,`ip_address`),
  KEY `idx_login_attempt_identifier` (`identifier_hash`,`last_failed_at`),
  KEY `idx_login_attempt_ip` (`ip_address`,`last_failed_at`),
  KEY `idx_login_attempt_lock` (`locked_until`),
  KEY `idx_login_attempt_captcha` (`captcha_required_until`),
  KEY `idx_login_attempt_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Failed login counters and temporary login protection state';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_login_attempts`
--

LOCK TABLES `tracs_login_attempts` WRITE;
/*!40000 ALTER TABLE `tracs_login_attempts` DISABLE KEYS */;
INSERT INTO `tracs_login_attempts` VALUES (4,'15318cb9a99e1e2ee122487d83d29169b2abb52d855a690143d174c1c119d6c3','admin@tracs.local','172.18.0.1',1,0,'2026-05-21 10:56:22','2026-05-21 10:56:22',NULL,NULL,'success','curl/8.7.1','2026-05-21 10:56:22','2026-05-21 11:03:26'),(5,'400a96e0e8f5ea07402df9f8a40bf675364518fa721baa3282c4c9653ecf69a0','asdasd@hmail.com','172.18.0.1',1,0,'2026-05-21 10:57:51','2026-05-21 10:58:22',NULL,NULL,'success','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-21 10:57:51','2026-05-21 11:03:26');
/*!40000 ALTER TABLE `tracs_login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_meeting_actions`
--

DROP TABLE IF EXISTS `tracs_meeting_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_meeting_actions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(50) DEFAULT NULL,
  `meeting_id` int NOT NULL,
  `decision_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `assigned_to` int DEFAULT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','monitoring','waiting_internal','escalated','completed','cancelled','overdue') NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_meeting_id` (`meeting_id`),
  KEY `idx_decision_id` (`decision_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_priority` (`priority`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `fk_meeting_actions_decision` FOREIGN KEY (`decision_id`) REFERENCES `tracs_meeting_decisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_meeting_actions_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `tracs_meetings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_meeting_actions`
--

LOCK TABLES `tracs_meeting_actions` WRITE;
/*!40000 ALTER TABLE `tracs_meeting_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_meeting_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_meeting_decisions`
--

DROP TABLE IF EXISTS `tracs_meeting_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_meeting_decisions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `meeting_id` int NOT NULL,
  `note_id` int DEFAULT NULL,
  `decision_text` text NOT NULL,
  `decision_status` enum('open','approved','rejected','completed') NOT NULL DEFAULT 'open',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_meeting_id` (`meeting_id`),
  KEY `idx_note_id` (`note_id`),
  KEY `idx_decision_status` (`decision_status`),
  CONSTRAINT `fk_meeting_decisions_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `tracs_meetings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meeting_decisions_note` FOREIGN KEY (`note_id`) REFERENCES `tracs_meeting_notes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_meeting_decisions`
--

LOCK TABLES `tracs_meeting_decisions` WRITE;
/*!40000 ALTER TABLE `tracs_meeting_decisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_meeting_decisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_meeting_notes`
--

DROP TABLE IF EXISTS `tracs_meeting_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_meeting_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `meeting_id` int NOT NULL,
  `note_type` enum('discussion','agenda','highlight','system') NOT NULL DEFAULT 'discussion',
  `content` longtext NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_meeting_id` (`meeting_id`),
  KEY `idx_note_type` (`note_type`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_meeting_notes_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `tracs_meetings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_meeting_notes`
--

LOCK TABLES `tracs_meeting_notes` WRITE;
/*!40000 ALTER TABLE `tracs_meeting_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_meeting_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_meeting_participants`
--

DROP TABLE IF EXISTS `tracs_meeting_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_meeting_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `meeting_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` enum('organizer','participant','viewer') NOT NULL DEFAULT 'participant',
  `attendance_status` enum('invited','attended','absent') NOT NULL DEFAULT 'invited',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meeting_id` (`meeting_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_meeting_participant_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `tracs_meetings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_meeting_participants`
--

LOCK TABLES `tracs_meeting_participants` WRITE;
/*!40000 ALTER TABLE `tracs_meeting_participants` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_meeting_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_meetings`
--

DROP TABLE IF EXISTS `tracs_meetings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_meetings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(50) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `meeting_type` enum('weekly','training','coordination','urgent') NOT NULL DEFAULT 'weekly',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('draft','ongoing','completed','cancelled') NOT NULL DEFAULT 'draft',
  `objective` text,
  `meeting_date` datetime NOT NULL,
  `created_by` int NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_meeting_type` (`meeting_type`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_meeting_date` (`meeting_date`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_meetings`
--

LOCK TABLES `tracs_meetings` WRITE;
/*!40000 ALTER TABLE `tracs_meetings` DISABLE KEYS */;
INSERT INTO `tracs_meetings` VALUES (1,'MOM-2026-001','Weekly Operational Review','weekly','high','draft','Review unresolved operational issues and escalations','2026-05-13 22:51:18',1,NULL,NULL,'2026-05-13 15:51:18','2026-05-13 15:51:18',NULL);
/*!40000 ALTER TABLE `tracs_meetings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_mom_actions`
--

DROP TABLE IF EXISTS `tracs_mom_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_mom_actions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mom_id` int unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Action title',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Detailed description',
  `assigned_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name of person assigned',
  `priority` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL COMMENT 'When action should be completed',
  `linked_reminder_id` int DEFAULT NULL COMMENT 'Linked reminder ID for tracking',
  `linked_case_id` int DEFAULT NULL COMMENT 'Linked case ID if created as operational case',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_priority` (`priority`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_linked_reminder` (`linked_reminder_id`),
  CONSTRAINT `fk_actions_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_mom_actions`
--

LOCK TABLES `tracs_mom_actions` WRITE;
/*!40000 ALTER TABLE `tracs_mom_actions` DISABLE KEYS */;
INSERT INTO `tracs_mom_actions` VALUES (1,4,'ini nihh','itu nih','','medium','pending','2026-05-17 00:00:00',NULL,NULL,'2026-05-17 14:04:40','2026-05-17 14:04:40');
/*!40000 ALTER TABLE `tracs_mom_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_mom_agenda`
--

DROP TABLE IF EXISTS `tracs_mom_agenda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_mom_agenda` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mom_id` int unsigned NOT NULL,
  `topic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Agenda topic',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Notes on the topic',
  `status` enum('pending','completed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  CONSTRAINT `fk_agenda_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_mom_agenda`
--

LOCK TABLES `tracs_mom_agenda` WRITE;
/*!40000 ALTER TABLE `tracs_mom_agenda` DISABLE KEYS */;
INSERT INTO `tracs_mom_agenda` VALUES (1,2,'this','','pending','2026-05-15 19:02:39'),(2,2,'and that','','completed','2026-05-15 19:02:43'),(3,4,'ini itu','','pending','2026-05-17 13:58:37'),(4,4,'nah ini','','pending','2026-05-17 14:04:55');
/*!40000 ALTER TABLE `tracs_mom_agenda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_mom_audit_log`
--

DROP TABLE IF EXISTS `tracs_mom_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_mom_audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mom_id` int unsigned NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Action type (created, updated, decision_added, etc)',
  `details` text COLLATE utf8mb4_unicode_ci COMMENT 'Action details',
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  CONSTRAINT `fk_audit_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_mom_audit_log`
--

LOCK TABLES `tracs_mom_audit_log` WRITE;
/*!40000 ALTER TABLE `tracs_mom_audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_mom_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_mom_case_links`
--

DROP TABLE IF EXISTS `tracs_mom_case_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_mom_case_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mom_id` int unsigned NOT NULL,
  `case_id` int NOT NULL,
  `link_context` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Why case is linked (discussed, created, related)',
  `linked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mom_case` (`mom_id`,`case_id`),
  KEY `idx_case_id` (`case_id`),
  CONSTRAINT `fk_links_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_mom_case_links`
--

LOCK TABLES `tracs_mom_case_links` WRITE;
/*!40000 ALTER TABLE `tracs_mom_case_links` DISABLE KEYS */;
INSERT INTO `tracs_mom_case_links` VALUES (1,1,23,'related','2026-05-14 20:55:02'),(2,2,14,'related','2026-05-15 09:19:04'),(3,3,6,'related','2026-05-16 16:48:07'),(4,5,6,'related','2026-05-17 07:35:11'),(5,6,6,'related','2026-05-18 09:28:59'),(6,8,6,'related','2026-05-20 16:33:24');
/*!40000 ALTER TABLE `tracs_mom_case_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_mom_decisions`
--

DROP TABLE IF EXISTS `tracs_mom_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_mom_decisions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mom_id` int unsigned NOT NULL,
  `decision` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Decision text',
  `rationale` text COLLATE utf8mb4_unicode_ci COMMENT 'Why this decision was made',
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Person responsible for decision',
  `status` enum('pending','approved','implemented','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_decisions_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_mom_decisions`
--

LOCK TABLES `tracs_mom_decisions` WRITE;
/*!40000 ALTER TABLE `tracs_mom_decisions` DISABLE KEYS */;
INSERT INTO `tracs_mom_decisions` VALUES (1,4,'apa ini','ini nih','si itu','pending','2026-05-17 13:58:27','2026-05-17 13:58:27');
/*!40000 ALTER TABLE `tracs_mom_decisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_mom_notes`
--

DROP TABLE IF EXISTS `tracs_mom_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_mom_notes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mom_id` int unsigned NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Note content',
  `note_type` enum('discussion','decision','action','insight','risk') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'discussion',
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_note_type` (`note_type`),
  CONSTRAINT `fk_notes_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_mom_notes`
--

LOCK TABLES `tracs_mom_notes` WRITE;
/*!40000 ALTER TABLE `tracs_mom_notes` DISABLE KEYS */;
INSERT INTO `tracs_mom_notes` VALUES (1,4,'ini nih','discussion',1,'2026-05-17 13:58:43');
/*!40000 ALTER TABLE `tracs_mom_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_mom_screenshots`
--

DROP TABLE IF EXISTS `tracs_mom_screenshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_mom_screenshots` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mom_id` int unsigned NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stored filename',
  `attached_to_type` enum('discussion','action','decision','general') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `attached_to_id` int unsigned DEFAULT NULL COMMENT 'ID of related item (note, action, decision)',
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_attached_to` (`attached_to_type`,`attached_to_id`),
  CONSTRAINT `fk_screenshots_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_mom_screenshots`
--

LOCK TABLES `tracs_mom_screenshots` WRITE;
/*!40000 ALTER TABLE `tracs_mom_screenshots` DISABLE KEYS */;
INSERT INTO `tracs_mom_screenshots` VALUES (1,1,'mom_1_1778767027_c0033296.png','general',NULL,'2026-05-14 20:57:07'),(2,1,'mom_1_1778805412_27fb6c1d.png','general',NULL,'2026-05-15 07:36:52'),(3,4,'mom_4_1778938674_0dc94fb0.png','general',NULL,'2026-05-16 20:37:54'),(4,4,'mom_4_1779000957_166bf373.png','general',NULL,'2026-05-17 13:55:57'),(5,6,'mom_6_1779162387_ea59d4c5.png','general',NULL,'2026-05-19 10:46:27');
/*!40000 ALTER TABLE `tracs_mom_screenshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_moms`
--

DROP TABLE IF EXISTS `tracs_moms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_moms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Meeting title',
  `type` enum('weekly','training','coordination','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
  `objective` text COLLATE utf8mb4_unicode_ci,
  `participants` text COLLATE utf8mb4_unicode_ci,
  `meeting_at` datetime DEFAULT NULL COMMENT 'Planned meeting date and time',
  `meeting_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Meeting URL such as Google Meet or Zoom',
  `status` enum('upcoming','ongoing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upcoming' COMMENT 'Meeting lifecycle status',
  `created_by` int NOT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_reminder_id` int unsigned DEFAULT NULL COMMENT 'Reminder created for scheduled meeting',
  `ops_status_id` int unsigned DEFAULT NULL COMMENT 'Ops window status entry for meeting',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `summary` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Post-meeting MOM summary',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_meeting_at` (`meeting_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_moms`
--

LOCK TABLES `tracs_moms` WRITE;
/*!40000 ALTER TABLE `tracs_moms` DISABLE KEYS */;
INSERT INTO `tracs_moms` VALUES (1,'Meeting CS','weekly','','TIM CS','2026-05-15 14:00:00',NULL,'completed',1,NULL,4,13,'2026-05-14 20:55:12','2026-05-14 20:55:58',NULL,'','2026-05-14 20:55:02','2026-05-14 20:57:12'),(2,'Meeting jumat','weekly','','','2026-05-15 10:18:00','https://gmeet','completed',1,NULL,5,14,'2026-05-15 16:51:18','2026-05-15 16:57:44',NULL,NULL,'2026-05-15 09:19:04','2026-05-15 16:57:44'),(3,'Test list','weekly','','','2026-05-16 16:55:00',NULL,'completed',1,'Administrator',9,15,'2026-05-16 16:59:26','2026-05-16 16:59:29',NULL,NULL,'2026-05-16 16:48:07','2026-05-16 16:59:29'),(4,'test span','weekly','ini itu nih','tim ts, tim cs, tim sales','2026-05-16 20:36:00',NULL,'completed',1,'Administrator',10,16,'2026-05-16 20:37:23','2026-05-16 20:37:29',NULL,'ini itu dan itu','2026-05-16 20:37:23','2026-05-17 15:54:26'),(5,'test font','weekly','','','2026-05-17 07:35:00',NULL,'completed',1,'Administrator',11,17,'2026-05-17 07:35:11','2026-05-18 09:28:02',NULL,NULL,'2026-05-17 07:35:11','2026-05-18 09:28:02'),(6,'test','weekly','','','2026-05-18 09:30:00',NULL,'completed',1,'Administrator',18,18,'2026-05-18 09:30:25','2026-05-19 10:45:51',NULL,NULL,'2026-05-18 09:28:59','2026-05-19 10:45:51'),(7,'test meeting','weekly','','','2026-05-19 11:45:00',NULL,'completed',1,'Administrator',20,19,'2026-05-19 12:01:30','2026-05-20 16:33:32',NULL,NULL,'2026-05-19 10:45:44','2026-05-20 16:33:32'),(8,'Title','weekly','ini itu','','2026-05-20 17:33:00',NULL,'completed',1,'Administrator',21,20,'2026-05-20 18:06:45','2026-05-20 18:53:10',NULL,NULL,'2026-05-20 16:33:24','2026-05-20 18:53:10');
/*!40000 ALTER TABLE `tracs_moms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_password_reset_tokens`
--

DROP TABLE IF EXISTS `tracs_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_password_reset_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracs_prt_user` (`user_id`,`expires_at`),
  KEY `idx_tracs_prt_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_password_reset_tokens`
--

LOCK TABLES `tracs_password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `tracs_password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_permissions`
--

DROP TABLE IF EXISTS `tracs_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_permissions_key` (`permission_key`),
  KEY `idx_tracs_permissions_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=175 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_permissions`
--

LOCK TABLES `tracs_permissions` WRITE;
/*!40000 ALTER TABLE `tracs_permissions` DISABLE KEYS */;
INSERT INTO `tracs_permissions` VALUES (1,'users.view','Users','View users and team structure','2026-05-17 22:51:38'),(2,'users.create','Users','Create new users','2026-05-17 22:51:38'),(3,'users.update','Users','Update user identity and access fields','2026-05-17 22:51:38'),(4,'users.delete','Users','Soft-delete or permanently remove users','2026-05-17 22:51:38'),(5,'users.suspend','Users','Suspend user login access','2026-05-17 22:51:38'),(6,'users.activate','Users','Restore user login access','2026-05-17 22:51:38'),(7,'users.reset_password','Users','Reset user passwords','2026-05-17 22:51:38'),(8,'users.view_activity','Users','View user activity records','2026-05-17 22:51:38'),(9,'profile.view_own','Profile','View own profile','2026-05-17 22:51:38'),(10,'profile.update_own','Profile','Update own profile','2026-05-17 22:51:38'),(11,'profile.change_password_own','Profile','Change own password','2026-05-17 22:51:38'),(12,'profile.update_preferences_own','Profile','Update own preferences','2026-05-17 22:51:38'),(13,'divisions.view','Divisions','View divisions','2026-05-17 22:51:38'),(14,'divisions.create','Divisions','Create divisions','2026-05-17 22:51:38'),(15,'divisions.update','Divisions','Update divisions','2026-05-17 22:51:38'),(16,'divisions.archive','Divisions','Archive divisions','2026-05-17 22:51:38'),(17,'divisions.manage_members','Divisions','Move users between divisions','2026-05-17 22:51:38'),(18,'roles.view','Roles','View roles and permission matrix','2026-05-17 22:51:38'),(19,'roles.create','Roles','Create roles','2026-05-17 22:51:38'),(20,'roles.update','Roles','Update roles','2026-05-17 22:51:38'),(21,'roles.delete','Roles','Delete roles','2026-05-17 22:51:38'),(22,'roles.manage_permissions','Roles','Change role permissions','2026-05-17 22:51:38'),(23,'reports.view','Reports','View reports','2026-05-17 22:51:38'),(24,'reports.create','Reports','Create reports','2026-05-17 22:51:38'),(25,'reports.update','Reports','Update reports','2026-05-17 22:51:38'),(26,'reports.export','Reports','Export reports','2026-05-17 22:51:38'),(27,'cases.view','Cases','View cases','2026-05-17 22:51:38'),(28,'cases.manage','Cases','Create and update cases','2026-05-17 22:51:38'),(29,'reminders.view','Reminders','View reminders','2026-05-17 22:51:38'),(30,'reminders.manage','Reminders','Create and update reminders','2026-05-17 22:51:38'),(31,'checklist.view','Checklist','View checklist','2026-05-17 22:51:38'),(32,'checklist.manage','Checklist','Create and update checklist items','2026-05-17 22:51:38'),(33,'finance.view','Finance','View finance records','2026-05-17 22:51:38'),(34,'finance.manage','Finance','Create and update finance records','2026-05-17 22:51:38'),(35,'domains.view','Domains','View domain records','2026-05-17 22:51:38'),(36,'domains.manage','Domains','Create and update domain records','2026-05-17 22:51:38'),(37,'moms.view','MoM','View meeting minutes','2026-05-17 22:51:38'),(38,'moms.manage','MoM','Create and update meeting minutes','2026-05-17 22:51:38'),(39,'cancellation_feedback.view','Cancellation Feedback','View cancellation feedback','2026-05-17 22:51:38'),(40,'cancellation_feedback.manage','Cancellation Feedback','Create and update cancellation feedback','2026-05-17 22:51:38'),(41,'settings.manage','Settings','Manage sensitive system settings','2026-05-17 22:51:38'),(165,'dashboard.view','Dashboard','View operational dashboard','2026-05-18 00:01:17'),(167,'tasks.view_own','Tasks','View assigned tasks','2026-05-18 08:21:40'),(168,'tasks.update_own','Tasks','Update assigned task progress','2026-05-18 08:21:40'),(169,'tasks.create','Tasks','Create and assign tasks','2026-05-18 08:21:40'),(170,'tasks.monitor','Tasks','View task monitoring dashboard','2026-05-18 08:21:40'),(171,'tasks.review','Tasks','Review assigned task completion','2026-05-18 08:21:40'),(172,'domain_price.view','Domain Price','View domain price crosscheck panel','2026-05-20 07:18:08'),(173,'domain_price.manage','Domain Price','Create, update, and manage domain price drafts','2026-05-20 07:18:08'),(174,'domain_price.approve','Domain Price','Review, lock, and approve domain price snapshots','2026-05-20 07:18:08');
/*!40000 ALTER TABLE `tracs_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_reminders`
--

DROP TABLE IF EXISTS `tracs_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_reminders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `case_id` int DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `description` text,
  `due_date` datetime DEFAULT NULL,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `is_completed` tinyint(1) DEFAULT '0',
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int unsigned DEFAULT NULL,
  `linked_assignment_id` int unsigned DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `reset_at` datetime DEFAULT NULL,
  `recurrence_type` enum('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  `ticker_priority` enum('critical','high','medium','low','info') DEFAULT NULL,
  `ticker_visible_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_due` (`due_date`),
  KEY `idx_completed` (`is_completed`),
  KEY `idx_reminders_case_id` (`case_id`),
  KEY `idx_rem_ticker_active` (`user_id`,`is_completed`,`archived_at`,`due_date`,`ticker_visible_until`),
  KEY `idx_rem_created_by` (`created_by`),
  KEY `idx_reminders_linked_assignment` (`linked_assignment_id`),
  CONSTRAINT `fk_reminders_case` FOREIGN KEY (`case_id`) REFERENCES `tracs_cases` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_reminders`
--

LOCK TABLES `tracs_reminders` WRITE;
/*!40000 ALTER TABLE `tracs_reminders` DISABLE KEYS */;
INSERT INTO `tracs_reminders` VALUES (1,1,1,NULL,NULL,'Check harga domain','','2026-05-11 18:34:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-11 14:34:06','2026-05-12 09:28:03'),(2,1,1,NULL,NULL,'Meeting PANDI','https://s.id/RegistrarMeeting-20260512','2026-05-12 10:00:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-12 09:28:48','2026-05-12 10:22:05'),(3,1,1,NULL,NULL,'check domain','','2026-05-12 15:06:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-12 11:06:59','2026-05-12 11:07:01'),(4,1,1,NULL,NULL,'MOM: Meeting CS','MOM scheduled in TRACS. Open: mom.php?mom_id=1','2026-05-15 14:00:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-14 20:55:02','2026-05-14 20:55:58'),(5,1,1,NULL,NULL,'MOM: Meeting jumat','MOM scheduled in TRACS. Open: mom.php?mom_id=2','2026-05-15 10:18:00','medium',1,'2026-05-15 12:55:39',1,NULL,'2026-05-15 12:55:39',NULL,'none',NULL,NULL,'2026-05-15 09:19:04','2026-05-15 16:57:44'),(7,1,1,'Administrator',NULL,'Check CSS ke Claude','','2026-05-16 16:09:00','medium',1,'2026-05-16 15:26:12',1,NULL,'2026-05-16 15:26:12',NULL,'none',NULL,NULL,'2026-05-16 15:09:55','2026-05-16 15:26:12'),(8,1,1,'Administrator',NULL,'Revert kalau tidak memungkinkan','','2026-05-16 16:10:00','medium',1,'2026-05-16 16:26:48',1,NULL,'2026-05-16 16:26:48',NULL,'none',NULL,NULL,'2026-05-16 15:10:17','2026-05-16 16:26:48'),(9,1,1,'Administrator',NULL,'MOM: Test list','MOM scheduled in TRACS. Open: mom.php?mom_id=3','2026-05-16 18:47:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-16 16:48:07','2026-05-16 16:59:29'),(10,1,1,'Administrator',NULL,'MOM: test span','MOM scheduled in TRACS. Open: mom.php?mom_id=4','2026-05-16 20:36:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-16 20:37:23','2026-05-16 20:37:29'),(11,1,1,'Administrator',NULL,'MOM: test font','MOM scheduled in TRACS. Open: mom.php?mom_id=5','2026-05-17 07:35:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-17 07:35:11','2026-05-18 09:28:02'),(12,1,1,'Administrator',NULL,'test remindr','','2026-05-17 09:51:00','medium',1,'2026-05-17 09:07:19',1,NULL,'2026-05-17 09:07:19',NULL,'none',NULL,NULL,'2026-05-17 08:51:14','2026-05-17 09:07:19'),(13,1,1,'Administrator',NULL,'test button','','2026-05-17 10:08:00','medium',1,'2026-05-17 15:16:12',1,NULL,'2026-05-17 15:16:12',NULL,'none',NULL,NULL,'2026-05-17 09:08:38','2026-05-17 15:16:12'),(14,1,1,'Administrator',NULL,'test otast','','2026-05-17 16:10:00','medium',1,'2026-05-17 15:11:05',1,NULL,'2026-05-17 15:11:05',NULL,'none',NULL,NULL,'2026-05-17 15:10:14','2026-05-17 15:11:05'),(18,1,1,'Administrator',NULL,'MOM: test','MOM scheduled in TRACS. Open: mom.php?mom_id=6','2026-05-18 09:30:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-18 09:28:59','2026-05-19 10:45:51'),(19,1,1,'Administrator',NULL,'test add','','2026-05-19 11:13:00','medium',1,'2026-05-19 10:14:42',1,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-19 10:13:10','2026-05-19 10:14:42'),(20,1,1,'Administrator',NULL,'MOM: test meeting','MOM scheduled in TRACS. Open: mom.php?mom_id=7','2026-05-19 11:45:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-19 10:45:44','2026-05-20 16:33:32'),(21,1,1,'Administrator',NULL,'MOM: Title','MOM scheduled in TRACS. Open: mom.php?mom_id=8','2026-05-20 17:33:00','medium',1,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,'2026-05-20 16:33:24','2026-05-20 18:53:10');
/*!40000 ALTER TABLE `tracs_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_role_permissions`
--

DROP TABLE IF EXISTS `tracs_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_role_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_role_permission` (`role_id`,`permission_id`),
  KEY `idx_tracs_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_tracs_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `tracs_permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tracs_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `tracs_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=276 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_role_permissions`
--

LOCK TABLES `tracs_role_permissions` WRITE;
/*!40000 ALTER TABLE `tracs_role_permissions` DISABLE KEYS */;
INSERT INTO `tracs_role_permissions` VALUES (1,1,39,'2026-05-17 22:51:38'),(2,1,40,'2026-05-17 22:51:38'),(3,1,27,'2026-05-17 22:51:38'),(4,1,28,'2026-05-17 22:51:38'),(5,1,31,'2026-05-17 22:51:38'),(6,1,32,'2026-05-17 22:51:38'),(7,1,13,'2026-05-17 22:51:38'),(8,1,14,'2026-05-17 22:51:38'),(9,1,15,'2026-05-17 22:51:38'),(10,1,16,'2026-05-17 22:51:38'),(11,1,17,'2026-05-17 22:51:38'),(12,1,35,'2026-05-17 22:51:38'),(13,1,36,'2026-05-17 22:51:38'),(14,1,33,'2026-05-17 22:51:38'),(15,1,34,'2026-05-17 22:51:38'),(16,1,37,'2026-05-17 22:51:38'),(17,1,38,'2026-05-17 22:51:38'),(18,1,9,'2026-05-17 22:51:38'),(19,1,10,'2026-05-17 22:51:38'),(20,1,11,'2026-05-17 22:51:38'),(21,1,12,'2026-05-17 22:51:38'),(22,1,29,'2026-05-17 22:51:38'),(23,1,30,'2026-05-17 22:51:38'),(24,1,23,'2026-05-17 22:51:38'),(25,1,24,'2026-05-17 22:51:38'),(26,1,25,'2026-05-17 22:51:38'),(27,1,26,'2026-05-17 22:51:38'),(28,1,18,'2026-05-17 22:51:38'),(29,1,19,'2026-05-17 22:51:38'),(30,1,20,'2026-05-17 22:51:38'),(31,1,21,'2026-05-17 22:51:38'),(32,1,22,'2026-05-17 22:51:38'),(33,1,41,'2026-05-17 22:51:38'),(34,1,1,'2026-05-17 22:51:38'),(35,1,2,'2026-05-17 22:51:38'),(36,1,3,'2026-05-17 22:51:38'),(37,1,4,'2026-05-17 22:51:38'),(38,1,5,'2026-05-17 22:51:38'),(39,1,6,'2026-05-17 22:51:38'),(40,1,7,'2026-05-17 22:51:38'),(41,1,8,'2026-05-17 22:51:38'),(64,2,40,'2026-05-17 22:51:38'),(65,2,39,'2026-05-17 22:51:38'),(66,2,28,'2026-05-17 22:51:38'),(67,2,27,'2026-05-17 22:51:38'),(68,2,32,'2026-05-17 22:51:38'),(69,2,31,'2026-05-17 22:51:38'),(70,2,16,'2026-05-17 22:51:38'),(71,2,14,'2026-05-17 22:51:38'),(72,2,17,'2026-05-17 22:51:38'),(73,2,15,'2026-05-17 22:51:38'),(74,2,13,'2026-05-17 22:51:38'),(75,2,36,'2026-05-17 22:51:38'),(76,2,35,'2026-05-17 22:51:38'),(77,2,34,'2026-05-17 22:51:38'),(78,2,33,'2026-05-17 22:51:38'),(79,2,38,'2026-05-17 22:51:38'),(80,2,37,'2026-05-17 22:51:38'),(81,2,11,'2026-05-17 22:51:38'),(82,2,10,'2026-05-17 22:51:38'),(83,2,12,'2026-05-17 22:51:38'),(84,2,9,'2026-05-17 22:51:38'),(85,2,30,'2026-05-17 22:51:38'),(86,2,29,'2026-05-17 22:51:38'),(87,2,24,'2026-05-17 22:51:38'),(88,2,26,'2026-05-17 22:51:38'),(89,2,25,'2026-05-17 22:51:38'),(90,2,23,'2026-05-17 22:51:38'),(91,2,18,'2026-05-17 22:51:38'),(92,2,6,'2026-05-17 22:51:38'),(93,2,2,'2026-05-17 22:51:38'),(94,2,7,'2026-05-17 22:51:38'),(95,2,5,'2026-05-17 22:51:38'),(96,2,3,'2026-05-17 22:51:38'),(97,2,1,'2026-05-17 22:51:38'),(98,2,8,'2026-05-17 22:51:38'),(127,3,40,'2026-05-17 22:51:38'),(128,3,39,'2026-05-17 22:51:38'),(129,3,28,'2026-05-17 22:51:38'),(130,3,27,'2026-05-17 22:51:38'),(131,3,32,'2026-05-17 22:51:38'),(132,3,31,'2026-05-17 22:51:38'),(133,3,17,'2026-05-17 22:51:38'),(134,3,13,'2026-05-17 22:51:38'),(135,3,36,'2026-05-17 22:51:38'),(136,3,35,'2026-05-17 22:51:38'),(137,3,38,'2026-05-17 22:51:38'),(138,3,37,'2026-05-17 22:51:38'),(139,3,11,'2026-05-17 22:51:38'),(140,3,10,'2026-05-17 22:51:38'),(141,3,12,'2026-05-17 22:51:38'),(142,3,9,'2026-05-17 22:51:38'),(143,3,30,'2026-05-17 22:51:38'),(144,3,29,'2026-05-17 22:51:38'),(145,3,24,'2026-05-17 22:51:38'),(146,3,26,'2026-05-17 22:51:38'),(147,3,25,'2026-05-17 22:51:38'),(148,3,23,'2026-05-17 22:51:38'),(149,3,6,'2026-05-17 22:51:38'),(150,3,7,'2026-05-17 22:51:38'),(151,3,5,'2026-05-17 22:51:38'),(152,3,3,'2026-05-17 22:51:38'),(153,3,1,'2026-05-17 22:51:38'),(154,3,8,'2026-05-17 22:51:38'),(158,4,40,'2026-05-17 22:51:38'),(159,4,39,'2026-05-17 22:51:38'),(160,4,28,'2026-05-17 22:51:38'),(161,4,27,'2026-05-17 22:51:38'),(162,4,32,'2026-05-17 22:51:38'),(163,4,31,'2026-05-17 22:51:38'),(164,4,36,'2026-05-17 22:51:38'),(165,4,35,'2026-05-17 22:51:38'),(166,4,38,'2026-05-17 22:51:38'),(167,4,37,'2026-05-17 22:51:38'),(168,4,11,'2026-05-17 22:51:38'),(169,4,10,'2026-05-17 22:51:38'),(170,4,12,'2026-05-17 22:51:38'),(171,4,9,'2026-05-17 22:51:38'),(172,4,30,'2026-05-17 22:51:38'),(173,4,29,'2026-05-17 22:51:38'),(174,4,23,'2026-05-17 22:51:38'),(189,5,39,'2026-05-17 22:51:38'),(190,5,27,'2026-05-17 22:51:38'),(191,5,31,'2026-05-17 22:51:38'),(192,5,13,'2026-05-17 22:51:38'),(193,5,35,'2026-05-17 22:51:38'),(194,5,33,'2026-05-17 22:51:38'),(195,5,37,'2026-05-17 22:51:38'),(196,5,11,'2026-05-17 22:51:38'),(197,5,10,'2026-05-17 22:51:38'),(198,5,12,'2026-05-17 22:51:38'),(199,5,9,'2026-05-17 22:51:38'),(200,5,29,'2026-05-17 22:51:38'),(201,5,23,'2026-05-17 22:51:38'),(202,5,18,'2026-05-17 22:51:38'),(203,5,1,'2026-05-17 22:51:38'),(204,5,8,'2026-05-17 22:51:38'),(235,21,31,'2026-05-18 00:01:17'),(236,21,165,'2026-05-18 00:01:17'),(237,21,11,'2026-05-18 00:01:17'),(238,21,10,'2026-05-18 00:01:17'),(239,21,12,'2026-05-18 00:01:17'),(240,21,9,'2026-05-18 00:01:17'),(243,3,169,'2026-05-18 08:21:40'),(244,1,169,'2026-05-18 08:21:40'),(245,2,169,'2026-05-18 08:21:40'),(246,3,170,'2026-05-18 08:21:40'),(247,1,170,'2026-05-18 08:21:40'),(248,2,170,'2026-05-18 08:21:40'),(249,3,171,'2026-05-18 08:21:40'),(250,1,171,'2026-05-18 08:21:40'),(251,2,171,'2026-05-18 08:21:40'),(252,3,168,'2026-05-18 08:21:40'),(253,1,168,'2026-05-18 08:21:40'),(254,2,168,'2026-05-18 08:21:40'),(255,3,167,'2026-05-18 08:21:40'),(256,1,167,'2026-05-18 08:21:40'),(257,2,167,'2026-05-18 08:21:40'),(258,4,167,'2026-05-18 08:21:40'),(259,4,168,'2026-05-18 08:21:40'),(260,21,167,'2026-05-18 08:21:40'),(261,21,168,'2026-05-18 08:21:40'),(262,5,167,'2026-05-18 08:21:40'),(263,5,168,'2026-05-18 08:21:40'),(264,1,174,'2026-05-20 07:18:08'),(265,1,173,'2026-05-20 07:18:08'),(266,1,172,'2026-05-20 07:18:08'),(267,2,174,'2026-05-20 07:18:08'),(268,2,173,'2026-05-20 07:18:08'),(269,2,172,'2026-05-20 07:18:08'),(270,4,173,'2026-05-20 07:18:08'),(271,4,172,'2026-05-20 07:18:08'),(273,3,172,'2026-05-20 07:18:08'),(274,5,172,'2026-05-20 07:18:08');
/*!40000 ALTER TABLE `tracs_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_roles`
--

DROP TABLE IF EXISTS `tracs_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `hierarchy_level` int NOT NULL DEFAULT '40',
  `is_system_role` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_roles_slug` (`slug`),
  KEY `idx_tracs_roles_level` (`hierarchy_level`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_roles`
--

LOCK TABLES `tracs_roles` WRITE;
/*!40000 ALTER TABLE `tracs_roles` DISABLE KEYS */;
INSERT INTO `tracs_roles` VALUES (1,'Super Admin','super_admin','Full access to every TRACS module, role, permission, and setting.',100,1,'2026-05-17 22:51:38','2026-05-17 22:51:38'),(2,'Admin','admin','Operational administrator with broad user and operations access.',80,1,'2026-05-17 22:51:38','2026-05-17 22:51:38'),(3,'Supervisor / Leader','supervisor','Division leader scoped to team operations and permitted user actions.',60,1,'2026-05-17 22:51:38','2026-05-17 22:51:38'),(4,'Agent','agent','Operational agent access without User Management privileges.',40,1,'2026-05-17 22:51:38','2026-05-17 22:51:38'),(5,'Viewer / Auditor','viewer','Read-only auditor access.',20,1,'2026-05-17 22:51:38','2026-05-17 22:51:38'),(21,'Intern','intern','Temporary internship user with minimal safe access and dedicated monitoring metadata.',30,1,'2026-05-18 00:01:17','2026-05-18 00:01:17');
/*!40000 ALTER TABLE `tracs_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_shift_activities`
--

DROP TABLE IF EXISTS `tracs_shift_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_shift_activities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `shift_report_id` int unsigned DEFAULT NULL,
  `shift_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_type` enum('checklist','reminder','case','domain','finance','meeting','ticker','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `reference_id` int unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('completed','pending','attention','critical','info') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shift_name` (`shift_name`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_reference_id` (`reference_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_shift_handover` (`created_by`,`shift_name`,`created_at`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_shift_activities`
--

LOCK TABLES `tracs_shift_activities` WRITE;
/*!40000 ALTER TABLE `tracs_shift_activities` DISABLE KEYS */;
INSERT INTO `tracs_shift_activities` VALUES (1,NULL,'Shift 2','checklist',13,'Checklist completed: Check UI',NULL,'completed',1,'2026-05-15 10:50:14'),(2,NULL,'Shift 2','checklist',10,'Checklist completed: chek in out',NULL,'completed',1,'2026-05-15 11:28:01'),(3,NULL,'Shift 2','checklist',9,'Checklist completed: Check abuse domain',NULL,'completed',1,'2026-05-15 11:31:31'),(4,NULL,'Shift 2','reminder',5,'Reminder completed: MOM: Meeting jumat',NULL,'completed',1,'2026-05-15 12:55:39'),(5,NULL,'Shift 2','checklist',8,'Checklist completed: chck ini itu',NULL,'completed',1,'2026-05-15 12:55:41'),(6,NULL,'Shift 2','checklist',7,'Checklist completed: cek lampu',NULL,'completed',1,'2026-05-15 12:55:44'),(7,NULL,'Shift 2','checklist',6,'Checklist completed: Review Ticket Queue',NULL,'completed',1,'2026-05-15 12:55:44'),(8,NULL,'Shift 2','checklist',5,'Checklist completed: Check Backup Status',NULL,'completed',1,'2026-05-15 12:55:45'),(9,NULL,'Shift 2','checklist',1,'Checklist completed: Check Transaction List',NULL,'completed',1,'2026-05-15 12:55:46'),(10,NULL,'Shift 2','checklist',14,'Checklist completed: check transfer domain',NULL,'completed',1,'2026-05-15 12:57:19'),(11,NULL,'Shift 2','checklist',15,'Checklist completed: check ini itu',NULL,'completed',1,'2026-05-15 12:59:07'),(12,NULL,'Shift 2','checklist',17,'Checklist completed: Check Light theme',NULL,'completed',1,'2026-05-15 13:51:26'),(13,NULL,'Shift 2','checklist',15,'Checklist completed: check ini itu',NULL,'completed',1,'2026-05-15 13:51:27'),(14,NULL,'Shift 2','checklist',16,'Checklist completed: check dribbble',NULL,'completed',1,'2026-05-15 13:51:28'),(15,NULL,'Shift 2','checklist',16,'Checklist completed: check dribbble',NULL,'completed',1,'2026-05-16 08:35:07'),(16,NULL,'Shift 2','checklist',17,'Checklist completed: Check Light theme',NULL,'completed',1,'2026-05-16 08:35:08'),(17,NULL,'Shift 2','checklist',18,'Checklist completed: redesign login button',NULL,'completed',1,'2026-05-16 08:35:10'),(18,NULL,'Shift 2','checklist',22,'Checklist completed: hide flow download CSV',NULL,'completed',1,'2026-05-16 08:35:35'),(19,NULL,'Shift 2','checklist',24,'Checklist completed: add download feature on feedback page',NULL,'completed',1,'2026-05-16 08:35:36'),(20,NULL,'Shift 2','checklist',11,'Checklist completed: check in out',NULL,'completed',1,'2026-05-16 11:16:10'),(21,NULL,'Shift 2','checklist',12,'Checklist completed: check notif',NULL,'completed',1,'2026-05-16 11:16:10'),(22,NULL,'Shift 2','checklist',13,'Checklist completed: Check UI',NULL,'completed',1,'2026-05-16 11:16:11'),(23,NULL,'Shift 2','checklist',14,'Checklist completed: check transfer domain',NULL,'completed',1,'2026-05-16 11:16:14'),(24,NULL,'Shift 2','checklist',15,'Checklist completed: check ini itu',NULL,'completed',1,'2026-05-16 11:16:15'),(25,NULL,'Shift 2','checklist',21,'Checklist completed: redesign global checklist box',NULL,'completed',1,'2026-05-16 11:16:24'),(26,NULL,'Shift 2','checklist',5,'Checklist completed: Check Backup Status',NULL,'completed',1,'2026-05-16 11:17:50'),(27,NULL,'Shift 2','checklist',6,'Checklist completed: Review Ticket Queue',NULL,'completed',1,'2026-05-16 11:18:05'),(28,NULL,'Shift 2','checklist',7,'Checklist completed: cek lampu',NULL,'completed',1,'2026-05-16 11:18:06'),(29,NULL,'Shift 2','checklist',8,'Checklist completed: chck ini itu',NULL,'completed',1,'2026-05-16 11:18:09'),(30,NULL,'Shift 2','checklist',9,'Checklist completed: Check abuse domain',NULL,'completed',1,'2026-05-16 11:18:10'),(31,NULL,'Shift 2','checklist',1,'Checklist completed: Check Transaction List',NULL,'completed',1,'2026-05-16 11:25:13'),(32,NULL,'Shift 2','checklist',2,'Checklist completed: Check Transfer Domain',NULL,'completed',1,'2026-05-16 11:25:13'),(33,NULL,'Shift 2','checklist',3,'Checklist completed: Check Log Mutasi',NULL,'completed',1,'2026-05-16 11:25:14'),(34,NULL,'Shift 2','checklist',4,'Checklist completed: Check SSL Expiry',NULL,'completed',1,'2026-05-16 11:25:15'),(35,NULL,'Shift 2','checklist',10,'Checklist completed: chek in out',NULL,'completed',1,'2026-05-16 11:25:26'),(36,NULL,'Shift 2','checklist',29,'Checklist completed: add user id on every item',NULL,'completed',1,'2026-05-16 11:34:34'),(37,NULL,'Shift 2','checklist',28,'Checklist completed: check the logic of reminder list',NULL,'completed',1,'2026-05-16 12:59:16'),(38,NULL,'Shift 2','checklist',25,'Checklist completed: create theme memory',NULL,'completed',1,'2026-05-16 13:05:14'),(39,NULL,'Shift 2','checklist',30,'Checklist completed: check vickry.id',NULL,'completed',1,'2026-05-16 13:18:47'),(40,NULL,'Shift 2','checklist',30,'Checklist completed: check vickry.id',NULL,'completed',1,'2026-05-16 13:28:28'),(41,NULL,'Shift 2','checklist',32,'Checklist completed: Remove weird side lint accent on toast',NULL,'completed',1,'2026-05-16 13:36:37'),(42,NULL,'Shift 2','checklist',31,'Checklist completed: Add logic on + Button',NULL,'completed',1,'2026-05-16 14:12:25'),(43,NULL,'Shift 2','checklist',19,'Checklist completed: check database schema',NULL,'completed',1,'2026-05-16 14:12:35'),(44,NULL,'Shift 2','checklist',23,'Checklist completed: fix overwith table history MoM',NULL,'completed',1,'2026-05-16 14:12:43'),(45,NULL,'Shift 2','checklist',35,'Checklist completed: check toast style',NULL,'completed',1,'2026-05-16 14:12:56'),(46,NULL,'Shift 2','checklist',35,'Checklist completed: check toast style',NULL,'completed',1,'2026-05-16 14:14:52'),(47,NULL,'Shift 2','checklist',34,'Checklist completed: Add responsive for Vickry.id',NULL,'completed',1,'2026-05-16 14:15:53'),(48,NULL,'Shift 2','checklist',36,'Checklist completed: Check the line or box divider on every item on Dashboard',NULL,'completed',1,'2026-05-16 14:34:28'),(49,NULL,'Shift 2','checklist',36,'Checklist completed: Check the line or box divider on every item on Dashboard',NULL,'completed',1,'2026-05-16 14:34:47'),(50,NULL,'Shift 2','reminder',7,'Reminder completed: Check CSS ke Claude',NULL,'completed',1,'2026-05-16 15:26:12'),(51,NULL,'Shift 3','checklist',37,'Checklist completed: ganti warna checkbox',NULL,'completed',1,'2026-05-16 16:11:40'),(52,NULL,'Shift 3','checklist',37,'Checklist completed: ganti warna checkbox',NULL,'completed',1,'2026-05-16 16:11:49'),(53,NULL,'Shift 3','checklist',20,'Checklist completed: prepare for deploy',NULL,'completed',1,'2026-05-16 16:12:34'),(54,NULL,'Shift 3','checklist',37,'Checklist completed: ganti warna checkbox',NULL,'completed',1,'2026-05-16 16:15:36'),(55,NULL,'Shift 3','checklist',25,'Checklist completed: create theme memory',NULL,'completed',1,'2026-05-16 16:26:39'),(56,NULL,'Shift 3','reminder',8,'Reminder completed: Revert kalau tidak memungkinkan',NULL,'completed',1,'2026-05-16 16:26:48'),(57,NULL,'Shift 3','checklist',38,'Checklist completed: bug table line',NULL,'completed',1,'2026-05-16 19:37:16'),(58,NULL,'Shift 3','checklist',37,'Checklist completed: ganti warna checkbox',NULL,'completed',1,'2026-05-16 19:38:01'),(59,NULL,'Shift 3','checklist',38,'Checklist completed: bug table line',NULL,'completed',1,'2026-05-16 19:44:43'),(60,NULL,'Shift 3','checklist',38,'Checklist completed: bug table line',NULL,'completed',1,'2026-05-16 20:27:27'),(61,NULL,'Shift 1','checklist',36,'Checklist completed: Check the line or box divider on every item on Dashboard',NULL,'completed',1,'2026-05-17 00:21:55'),(62,NULL,'Shift 1','checklist',39,'Checklist completed: Check Bug MoM',NULL,'completed',1,'2026-05-17 00:46:33'),(63,NULL,'Shift 1','checklist',42,'Checklist completed: check UI table add cancellation_feedback.php',NULL,'completed',1,'2026-05-17 07:33:46'),(64,NULL,'Shift 1','checklist',41,'Checklist completed: font judul new task harus bold',NULL,'completed',1,'2026-05-17 07:33:49'),(65,NULL,'Shift 2','checklist',44,'Checklist completed: ganti design choice box biar match dengan design tracs',NULL,'completed',1,'2026-05-17 08:28:45'),(66,NULL,'Shift 2','checklist',40,'Checklist completed: login button harus punya light theme',NULL,'completed',1,'2026-05-17 08:33:34'),(67,NULL,'Shift 2','reminder',12,'Reminder completed: test remindr',NULL,'completed',1,'2026-05-17 09:07:12'),(68,NULL,'Shift 2','reminder',12,'Reminder completed: test remindr',NULL,'completed',1,'2026-05-17 09:07:18'),(69,NULL,'Shift 2','reminder',12,'Reminder completed: test remindr',NULL,'completed',1,'2026-05-17 09:07:19'),(70,NULL,'Shift 2','checklist',46,'Checklist completed: logic delete/close reminder',NULL,'completed',1,'2026-05-17 11:22:25'),(71,NULL,'Shift 2','reminder',13,'Reminder completed: test button',NULL,'completed',1,'2026-05-17 12:08:06'),(72,NULL,'Shift 2','checklist',45,'Checklist completed: check lagi logic dari reminder',NULL,'completed',1,'2026-05-17 12:08:17'),(73,NULL,'Shift 2','checklist',43,'Checklist completed: change the logic of submitter',NULL,'completed',1,'2026-05-17 14:14:42'),(74,NULL,'Shift 2','checklist',34,'Checklist completed: Add responsive for Vickry.id',NULL,'completed',1,'2026-05-17 14:14:43'),(75,NULL,'Shift 2','checklist',27,'Checklist completed: Create \"My Global Signature Key\"',NULL,'completed',1,'2026-05-17 14:16:40'),(76,NULL,'Shift 2','checklist',37,'Checklist completed: ganti warna checkbox',NULL,'completed',1,'2026-05-17 15:04:52'),(77,NULL,'Shift 2','checklist',39,'Checklist completed: Check Bug MoM',NULL,'completed',1,'2026-05-17 15:09:12'),(78,NULL,'Shift 2','reminder',14,'Reminder completed: test otast',NULL,'completed',1,'2026-05-17 15:11:05'),(79,NULL,'Shift 2','reminder',13,'Reminder completed: test button',NULL,'completed',1,'2026-05-17 15:16:12'),(80,NULL,'Shift 3','checklist',47,'Checklist completed: check bot autoclose intercom',NULL,'completed',1,'2026-05-17 16:14:57'),(81,NULL,'Shift 3','checklist',26,'Checklist completed: add user previllage',NULL,'completed',1,'2026-05-17 23:29:14'),(82,NULL,'Shift 2','checklist',48,'Checklist completed: add page/feature for task asignment',NULL,'completed',1,'2026-05-18 09:31:57'),(83,NULL,'Shift 2','checklist',49,'Checklist completed: Edit logic when adding intern user',NULL,'completed',1,'2026-05-18 09:31:59'),(84,NULL,'Shift 2','checklist',50,'Checklist completed: remove User management overview section',NULL,'completed',1,'2026-05-18 09:32:05'),(85,NULL,'Shift 2','checklist',51,'Checklist completed: add tv or monitor mode',NULL,'completed',1,'2026-05-18 09:47:06'),(86,NULL,'Shift 2','checklist',56,'Checklist completed: update file .MD',NULL,'completed',1,'2026-05-18 14:58:28'),(87,NULL,'Shift 2','checklist',58,'Checklist completed: add profile picture user',NULL,'completed',1,'2026-05-19 09:35:14'),(88,NULL,'Shift 2','checklist',57,'Checklist completed: put out search input on cancellation feedback filter',NULL,'completed',1,'2026-05-19 09:35:16'),(89,NULL,'Shift 2','reminder',19,'Reminder completed: test add',NULL,'completed',1,'2026-05-19 10:14:42'),(90,NULL,'Shift 2','checklist',59,'Checklist completed: fix bug when hover avatar',NULL,'completed',1,'2026-05-19 13:42:40'),(91,NULL,'Shift 2','checklist',60,'Checklist completed: fix the width on data-tracs-select-for in Resolution on feedback page',NULL,'completed',1,'2026-05-19 13:42:46'),(92,NULL,'Shift 2','checklist',61,'Checklist completed: change logic for reminders',NULL,'completed',1,'2026-05-19 13:42:48'),(93,NULL,'Shift 1','checklist',65,'Checklist completed: Add feature crosscheck pricing domain',NULL,'completed',1,'2026-05-20 07:10:53'),(94,NULL,'Shift 3','checklist',64,'Checklist completed: safety check for the permalink (?) ocalhost:8080/mom.php?mom_id=3',NULL,'completed',1,'2026-05-20 16:57:22'),(95,NULL,'Shift 3','checklist',63,'Checklist completed: fix sliding bug in affected',NULL,'completed',1,'2026-05-20 17:07:52'),(96,NULL,'Shift 3','checklist',62,'Checklist completed: remove status Critical etc. inside Affected',NULL,'completed',1,'2026-05-20 17:07:53');
/*!40000 ALTER TABLE `tracs_shift_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_shift_reports`
--

DROP TABLE IF EXISTS `tracs_shift_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_shift_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shift_name` varchar(50) NOT NULL DEFAULT 'Shift 1',
  `title` varchar(255) NOT NULL,
  `details` text,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('active','resolved') DEFAULT 'active',
  `active_date` date DEFAULT (curdate()),
  `created_by` int NOT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `active_date` (`active_date`,`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_shift_reports`
--

LOCK TABLES `tracs_shift_reports` WRITE;
/*!40000 ALTER TABLE `tracs_shift_reports` DISABLE KEYS */;
INSERT INTO `tracs_shift_reports` VALUES (1,'Shift 1','Pending Service','Sudah difollow up ke tim ts','medium','resolved','2026-05-11',1,NULL,'2026-05-11 12:42:27','2026-05-11 13:25:58','2026-05-11 13:25:58'),(2,'Shift 1','Kendala S3','Cek ke bang rengge','critical','resolved','2026-05-12',1,NULL,'2026-05-12 12:07:32','2026-05-15 18:23:59','2026-05-15 18:23:59'),(3,'Shift 2','Konfirmasi pembayaran, error VM Extreme','','medium','active','2026-05-12',1,NULL,'2026-05-12 15:39:22','2026-05-12 15:39:22',NULL),(4,'Shift 2','Hidup joko','','medium','active','2026-05-13',1,NULL,'2026-05-13 20:11:34','2026-05-13 20:11:34',NULL),(5,'Shift 1','test font','','medium','active','2026-05-17',1,'Administrator','2026-05-17 07:34:45','2026-05-17 07:34:45',NULL),(6,'Shift 2','test widget','ini itu dan akhh banyak tapi','medium','active','2026-05-20',1,'Administrator','2026-05-20 18:17:57','2026-05-20 18:17:57',NULL);
/*!40000 ALTER TABLE `tracs_shift_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_side_task_logs`
--

DROP TABLE IF EXISTS `tracs_side_task_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_side_task_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  KEY `idx_task_user` (`task_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_side_task_logs`
--

LOCK TABLES `tracs_side_task_logs` WRITE;
/*!40000 ALTER TABLE `tracs_side_task_logs` DISABLE KEYS */;
INSERT INTO `tracs_side_task_logs` VALUES (1,1,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(2,2,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(3,3,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(4,4,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(5,5,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(6,6,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(7,7,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(8,8,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(9,9,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(10,10,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(11,11,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(12,12,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(13,13,1,'Auto-reset daily checklist item','2026-05-15 09:36:30'),(14,3,1,'Checklist item completed and archived from active ticker','2026-05-15 09:54:28'),(15,4,1,'Checklist item completed and archived from active ticker','2026-05-15 09:54:31'),(16,2,1,'Checklist item completed and archived from active ticker','2026-05-15 09:54:32'),(17,12,1,'Checklist item completed and archived from active ticker','2026-05-15 10:49:25'),(18,11,1,'Checklist item completed and archived from active ticker','2026-05-15 10:49:26'),(19,13,1,'Checklist item completed and archived from active ticker','2026-05-15 10:50:14'),(20,10,1,'Checklist item completed and archived from active ticker','2026-05-15 11:28:01'),(21,9,1,'Checklist item completed and archived from active ticker','2026-05-15 11:31:31'),(22,8,1,'Checklist item completed and archived from active ticker','2026-05-15 12:55:41'),(23,7,1,'Checklist item completed and archived from active ticker','2026-05-15 12:55:44'),(24,6,1,'Checklist item completed and archived from active ticker','2026-05-15 12:55:44'),(25,5,1,'Checklist item completed and archived from active ticker','2026-05-15 12:55:45'),(26,1,1,'Checklist item completed and archived from active ticker','2026-05-15 12:55:46'),(27,14,1,'Checklist item completed and archived from active ticker','2026-05-15 12:57:19'),(28,15,1,'Checklist item completed and archived from active ticker','2026-05-15 12:59:07'),(29,17,1,'Checklist item completed and archived from active ticker','2026-05-15 13:51:26'),(30,15,1,'Checklist item reopened','2026-05-15 13:51:27'),(31,15,1,'Checklist item completed and archived from active ticker','2026-05-15 13:51:27'),(32,16,1,'Checklist item completed and archived from active ticker','2026-05-15 13:51:28'),(33,3,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(34,4,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(35,2,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(36,12,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(37,11,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(38,13,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(39,10,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(40,9,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(41,8,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(42,6,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(43,7,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(44,5,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(45,1,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(46,14,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(47,17,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(48,15,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(49,16,1,'Auto-reset daily checklist item','2026-05-16 08:19:00'),(50,16,1,'Checklist item completed and archived from active ticker','2026-05-16 08:35:07'),(51,17,1,'Checklist item completed and archived from active ticker','2026-05-16 08:35:08'),(52,18,1,'Checklist item completed and archived from active ticker','2026-05-16 08:35:10'),(53,22,1,'Checklist item completed and archived from active ticker','2026-05-16 08:35:35'),(54,24,1,'Checklist item completed and archived from active ticker','2026-05-16 08:35:35'),(55,11,1,'Checklist item completed and archived from active ticker','2026-05-16 11:16:10'),(56,12,1,'Checklist item completed and archived from active ticker','2026-05-16 11:16:10'),(57,13,1,'Checklist item completed and archived from active ticker','2026-05-16 11:16:11'),(58,14,1,'Checklist item completed and archived from active ticker','2026-05-16 11:16:14'),(59,15,1,'Checklist item completed and archived from active ticker','2026-05-16 11:16:15'),(60,21,1,'Checklist item completed and archived from active ticker','2026-05-16 11:16:24'),(61,5,1,'Checklist item completed and archived from active ticker','2026-05-16 11:17:50'),(62,6,1,'Checklist item completed and archived from active ticker','2026-05-16 11:18:05'),(63,7,1,'Checklist item completed and archived from active ticker','2026-05-16 11:18:06'),(64,8,1,'Checklist item completed and archived from active ticker','2026-05-16 11:18:09'),(65,9,1,'Checklist item completed and archived from active ticker','2026-05-16 11:18:10'),(66,1,1,'Checklist item completed and archived from active ticker','2026-05-16 11:25:13'),(67,2,1,'Checklist item completed and archived from active ticker','2026-05-16 11:25:13'),(68,3,1,'Checklist item completed and archived from active ticker','2026-05-16 11:25:14'),(69,4,1,'Checklist item completed and archived from active ticker','2026-05-16 11:25:15'),(70,10,1,'Checklist item completed and archived from active ticker','2026-05-16 11:25:26'),(71,29,1,'Checklist item completed and archived from active ticker','2026-05-16 11:34:34'),(72,28,1,'Checklist item completed and archived from active ticker','2026-05-16 12:59:16'),(73,25,1,'Checklist item completed and archived from active ticker','2026-05-16 13:05:14'),(74,30,1,'Checklist item completed and archived from active ticker','2026-05-16 13:18:47'),(75,30,1,'Checklist item completed and archived from active ticker','2026-05-16 13:28:27'),(76,32,1,'Checklist item completed and archived from active ticker','2026-05-16 13:36:37'),(77,31,1,'Checklist item completed and archived from active ticker','2026-05-16 14:12:25'),(78,19,1,'Checklist item completed and archived from active ticker','2026-05-16 14:12:34'),(79,23,1,'Checklist item completed and archived from active ticker','2026-05-16 14:12:43'),(80,35,1,'Checklist item completed and archived from active ticker','2026-05-16 14:12:56'),(81,35,1,'Checklist item reopened','2026-05-16 14:14:43'),(82,35,1,'Checklist item completed and archived from active ticker','2026-05-16 14:14:52'),(83,34,1,'Checklist item completed and archived from active ticker','2026-05-16 14:15:53'),(84,34,1,'Checklist item reopened','2026-05-16 14:16:14'),(85,36,1,'Checklist item completed and archived from active ticker','2026-05-16 14:34:28'),(86,36,1,'Checklist item reopened','2026-05-16 14:34:38'),(87,36,1,'Checklist item completed and archived from active ticker','2026-05-16 14:34:47'),(88,37,1,'Checklist item completed and archived from active ticker','2026-05-16 16:11:40'),(89,37,1,'Checklist item reopened','2026-05-16 16:11:47'),(90,37,1,'Checklist item completed and archived from active ticker','2026-05-16 16:11:49'),(91,20,1,'Checklist item completed and archived from active ticker','2026-05-16 16:12:34'),(92,37,1,'Checklist item reopened','2026-05-16 16:12:45'),(93,37,1,'Checklist item completed and archived from active ticker','2026-05-16 16:15:36'),(94,25,1,'Checklist item reopened','2026-05-16 16:17:16'),(95,25,1,'Checklist item completed and archived from active ticker','2026-05-16 16:26:39'),(96,38,1,'Checklist item completed and archived from active ticker','2026-05-16 19:37:16'),(97,37,1,'Checklist item reopened','2026-05-16 19:37:58'),(98,37,1,'Checklist item completed and archived from active ticker','2026-05-16 19:38:01'),(99,38,1,'Checklist item reopened','2026-05-16 19:44:42'),(100,38,1,'Checklist item completed and archived from active ticker','2026-05-16 19:44:43'),(101,36,1,'Checklist item reopened','2026-05-16 20:27:23'),(102,38,1,'Checklist item reopened','2026-05-16 20:27:25'),(103,38,1,'Checklist item completed and archived from active ticker','2026-05-16 20:27:27'),(104,36,1,'Checklist item completed and archived from active ticker','2026-05-17 00:21:55'),(105,39,1,'Checklist item completed and archived from active ticker','2026-05-17 00:46:33'),(106,42,1,'Checklist item completed and archived from active ticker','2026-05-17 07:33:46'),(107,41,1,'Checklist item completed and archived from active ticker','2026-05-17 07:33:49'),(108,44,1,'Checklist item completed and archived from active ticker','2026-05-17 08:28:45'),(109,40,1,'Checklist item completed and archived from active ticker','2026-05-17 08:33:34'),(110,46,1,'Checklist item completed and archived from active ticker','2026-05-17 11:22:25'),(111,45,1,'Checklist item completed and archived from active ticker','2026-05-17 12:08:17'),(112,43,1,'Checklist item completed and archived from active ticker','2026-05-17 14:14:42'),(113,34,1,'Checklist item completed and archived from active ticker','2026-05-17 14:14:43'),(114,27,1,'Checklist item completed and archived from active ticker','2026-05-17 14:16:40'),(115,37,1,'Checklist item reopened','2026-05-17 14:17:42'),(116,37,1,'Checklist item completed and archived from active ticker','2026-05-17 15:04:52'),(117,39,1,'Checklist item reopened','2026-05-17 15:09:05'),(118,39,1,'Checklist item completed and archived from active ticker','2026-05-17 15:09:12'),(119,47,1,'Checklist item completed and archived from active ticker','2026-05-17 16:14:57'),(120,26,1,'Checklist item completed and archived from active ticker','2026-05-17 23:29:14'),(121,48,1,'Checklist item completed and archived from active ticker','2026-05-18 09:31:57'),(122,49,1,'Checklist item completed and archived from active ticker','2026-05-18 09:31:59'),(123,50,1,'Checklist item completed and archived from active ticker','2026-05-18 09:32:05'),(124,51,1,'Checklist item completed and archived from active ticker','2026-05-18 09:47:06'),(125,56,1,'Checklist item completed and archived from active ticker','2026-05-18 14:58:28'),(126,58,1,'Checklist item completed and archived from active ticker','2026-05-19 09:35:14'),(127,57,1,'Checklist item completed and archived from active ticker','2026-05-19 09:35:16'),(128,59,1,'Checklist item completed and archived from active ticker','2026-05-19 13:42:40'),(129,60,1,'Checklist item completed and archived from active ticker','2026-05-19 13:42:46'),(130,61,1,'Checklist item completed and archived from active ticker','2026-05-19 13:42:48'),(131,65,1,'Checklist item completed and archived from active ticker','2026-05-20 07:10:53'),(132,64,1,'Checklist item completed and archived from active ticker','2026-05-20 16:57:22'),(133,63,1,'Checklist item completed and archived from active ticker','2026-05-20 17:07:52'),(134,62,1,'Checklist item completed and archived from active ticker','2026-05-20 17:07:53');
/*!40000 ALTER TABLE `tracs_side_task_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_side_tasks`
--

DROP TABLE IF EXISTS `tracs_side_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_side_tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `case_id` int DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `description` text,
  `is_completed` tinyint(1) DEFAULT '0',
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int unsigned DEFAULT NULL,
  `linked_assignment_id` int unsigned DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `reset_at` datetime DEFAULT NULL,
  `recurrence_type` enum('none','daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `ticker_priority` enum('critical','high','medium','low','info') DEFAULT NULL,
  `ticker_visible_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_tasks_case_id` (`case_id`),
  KEY `idx_tasks_ticker_active` (`user_id`,`is_completed`,`archived_at`,`reset_at`,`recurrence_type`),
  KEY `idx_tasks_created_by` (`created_by`),
  KEY `idx_tasks_linked_assignment` (`linked_assignment_id`),
  CONSTRAINT `fk_tasks_case` FOREIGN KEY (`case_id`) REFERENCES `tracs_cases` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_side_tasks`
--

LOCK TABLES `tracs_side_tasks` WRITE;
/*!40000 ALTER TABLE `tracs_side_tasks` DISABLE KEYS */;
INSERT INTO `tracs_side_tasks` VALUES (1,1,1,NULL,NULL,'Check Transaction List','Validate latest incoming payments.',1,'2026-05-16 11:25:13',1,NULL,'2026-05-16 11:25:13',NULL,'daily',NULL,NULL,'2026-05-08 20:26:11','2026-05-16 11:25:13'),(2,1,1,NULL,NULL,'Check Transfer Domain','Monitor pending registrar approvals.',1,'2026-05-16 11:25:13',1,NULL,'2026-05-16 11:25:13',NULL,'daily',NULL,NULL,'2026-05-08 20:26:11','2026-05-16 11:25:13'),(3,1,1,NULL,NULL,'Check Log Mutasi','Verify mutation consistency.',1,'2026-05-16 11:25:14',1,NULL,'2026-05-16 11:25:14',NULL,'daily',NULL,NULL,'2026-05-08 20:26:11','2026-05-16 11:25:14'),(4,1,1,NULL,NULL,'Check SSL Expiry','Review domains nearing expiration.',1,'2026-05-16 11:25:15',1,NULL,'2026-05-16 11:25:15',NULL,'daily',NULL,NULL,'2026-05-08 20:26:11','2026-05-16 11:25:15'),(5,1,1,NULL,NULL,'Check Backup Status','Ensure nightly backup completed.',1,'2026-05-16 11:17:50',1,NULL,'2026-05-16 11:17:50','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-08 20:26:11','2026-05-16 11:17:50'),(6,1,1,NULL,NULL,'Review Ticket Queue','Monitor unresolved cases.',1,'2026-05-16 11:18:05',1,NULL,'2026-05-16 11:18:05','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-08 20:26:11','2026-05-16 11:18:05'),(7,1,1,NULL,NULL,'cek lampu','cek lampu dapur',1,'2026-05-16 11:18:06',1,NULL,'2026-05-16 11:18:06','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-10 10:19:29','2026-05-16 11:18:06'),(8,1,1,NULL,NULL,'chck ini itu','',1,'2026-05-16 11:18:09',1,NULL,'2026-05-16 11:18:09','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-12 11:08:22','2026-05-16 11:18:09'),(9,1,1,NULL,NULL,'Check abuse domain','',1,'2026-05-16 11:18:10',1,NULL,'2026-05-16 11:18:10','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-12 11:42:01','2026-05-16 11:18:10'),(10,1,1,NULL,NULL,'chek in out','',1,'2026-05-16 11:25:26',1,NULL,'2026-05-16 11:25:26',NULL,'daily',NULL,NULL,'2026-05-12 15:45:38','2026-05-16 11:25:26'),(11,1,1,NULL,NULL,'check in out','',1,'2026-05-16 11:16:10',1,NULL,'2026-05-16 11:16:10','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-12 15:45:46','2026-05-16 11:16:10'),(12,1,1,NULL,NULL,'check notif','',1,'2026-05-16 11:16:10',1,NULL,'2026-05-16 11:16:10','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-12 15:47:07','2026-05-16 11:16:10'),(13,1,1,NULL,NULL,'Check UI','',1,'2026-05-16 11:16:11',1,NULL,'2026-05-16 11:16:11','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-12 18:40:16','2026-05-16 11:16:11'),(14,1,1,NULL,NULL,'check transfer domain','',1,'2026-05-16 11:16:14',1,NULL,'2026-05-16 11:16:14','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-15 12:57:13','2026-05-16 11:16:14'),(15,1,1,NULL,NULL,'check ini itu','',1,'2026-05-16 11:16:15',1,NULL,'2026-05-16 11:16:15','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-15 12:57:26','2026-05-16 11:16:15'),(16,1,1,NULL,NULL,'check dribbble','',1,'2026-05-16 08:35:07',1,NULL,'2026-05-16 08:35:07','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-15 12:58:22','2026-05-16 08:35:07'),(17,1,1,NULL,NULL,'Check Light theme','',1,'2026-05-16 08:35:08',1,NULL,'2026-05-16 08:35:08','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-15 12:58:55','2026-05-16 08:35:08'),(18,1,1,NULL,NULL,'redesign login button','',1,'2026-05-16 08:35:10',1,NULL,'2026-05-16 08:35:10','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-15 20:09:07','2026-05-16 08:35:10'),(19,1,1,NULL,NULL,'check database schema','',1,'2026-05-16 14:12:34',1,NULL,'2026-05-16 14:12:34',NULL,'daily',NULL,NULL,'2026-05-15 21:28:11','2026-05-16 14:12:34'),(20,1,1,NULL,NULL,'prepare for deploy','',1,'2026-05-16 16:12:34',1,NULL,'2026-05-16 16:12:34',NULL,'daily',NULL,NULL,'2026-05-15 21:28:29','2026-05-16 16:12:34'),(21,1,1,NULL,NULL,'redesign global checklist box','',1,'2026-05-16 11:16:24',1,NULL,'2026-05-16 11:16:24','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-15 21:34:35','2026-05-16 11:16:24'),(22,1,1,NULL,NULL,'hide flow download CSV','',1,'2026-05-16 08:35:35',1,NULL,'2026-05-16 08:35:35','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-15 21:38:51','2026-05-16 08:35:35'),(23,1,1,NULL,NULL,'fix overwith table history MoM','',1,'2026-05-16 14:12:43',1,NULL,'2026-05-16 14:12:43',NULL,'daily',NULL,NULL,'2026-05-15 21:48:01','2026-05-16 14:12:43'),(24,1,1,NULL,NULL,'add download feature on feedback page','',1,'2026-05-16 08:35:35',1,NULL,'2026-05-16 08:35:35','2026-05-17 00:00:00','daily',NULL,NULL,'2026-05-15 21:56:35','2026-05-16 08:35:35'),(25,1,1,NULL,NULL,'create theme memory','light, dark, current time',1,'2026-05-16 16:26:39',1,NULL,'2026-05-16 16:26:39',NULL,'daily',NULL,NULL,'2026-05-15 22:00:24','2026-05-16 16:26:39'),(26,1,1,NULL,NULL,'add user previllage','',1,'2026-05-17 23:29:14',1,NULL,'2026-05-17 23:29:14',NULL,'daily',NULL,NULL,'2026-05-15 22:00:33','2026-05-17 23:29:14'),(27,1,1,NULL,NULL,'Create \"My Global Signature Key\"','',1,'2026-05-17 14:16:40',1,NULL,'2026-05-17 14:16:40',NULL,'daily',NULL,NULL,'2026-05-15 22:13:42','2026-05-17 14:16:40'),(28,1,1,NULL,NULL,'check the logic of reminder list','',1,'2026-05-16 12:59:15',1,NULL,'2026-05-16 12:59:15',NULL,'daily',NULL,NULL,'2026-05-16 11:18:23','2026-05-16 12:59:15'),(29,1,1,NULL,NULL,'add user id on every item','',1,'2026-05-16 11:34:34',1,NULL,'2026-05-16 11:34:34',NULL,'daily',NULL,NULL,'2026-05-16 11:34:31','2026-05-16 11:34:34'),(30,1,1,'Administrator',NULL,'check vickry.id','',1,'2026-05-16 13:28:26',1,NULL,'2026-05-16 13:28:26',NULL,'daily',NULL,NULL,'2026-05-16 11:55:53','2026-05-16 13:28:26'),(31,1,1,'Administrator',NULL,'Add logic on + Button','',1,'2026-05-16 14:12:25',1,NULL,'2026-05-16 14:12:25',NULL,'daily',NULL,NULL,'2026-05-16 13:29:49','2026-05-16 14:12:25'),(32,1,1,'Administrator',NULL,'Remove weird side lint accent on toast','',1,'2026-05-16 13:36:37',1,NULL,'2026-05-16 13:36:37',NULL,'daily',NULL,NULL,'2026-05-16 13:30:18','2026-05-16 13:36:37'),(34,1,1,'Administrator',NULL,'Add responsive for Vickry.id','',1,'2026-05-17 14:14:43',1,NULL,'2026-05-17 14:14:43',NULL,'daily',NULL,NULL,'2026-05-16 14:00:45','2026-05-17 14:14:43'),(35,1,1,'Administrator',NULL,'check toast style','',1,'2026-05-16 14:14:52',1,NULL,'2026-05-16 14:14:52',NULL,'daily',NULL,NULL,'2026-05-16 14:12:52','2026-05-16 14:14:52'),(36,1,1,'Administrator',NULL,'Check the line or box divider on every item on Dashboard','item checklist, reminder, MoM should have divider line',1,'2026-05-17 00:21:55',1,NULL,'2026-05-17 00:21:55',NULL,'daily',NULL,NULL,'2026-05-16 14:21:06','2026-05-17 00:21:55'),(37,1,1,'Administrator',NULL,'ganti warna checkbox','',1,'2026-05-17 15:04:52',1,NULL,'2026-05-17 15:04:52',NULL,'daily',NULL,NULL,'2026-05-16 16:11:36','2026-05-17 15:04:52'),(38,1,1,'Administrator',NULL,'bug table line','',1,'2026-05-16 20:27:27',1,NULL,'2026-05-16 20:27:27',NULL,'daily',NULL,NULL,'2026-05-16 17:06:56','2026-05-16 20:27:27'),(39,1,1,'Administrator',NULL,'Check Bug MoM','',1,'2026-05-17 15:09:12',1,NULL,'2026-05-17 15:09:12',NULL,'daily',NULL,NULL,'2026-05-17 00:24:46','2026-05-17 15:09:12'),(40,1,1,'Administrator',NULL,'login button harus punya light theme','',1,'2026-05-17 08:33:34',1,NULL,'2026-05-17 08:33:34',NULL,'daily',NULL,NULL,'2026-05-17 00:31:32','2026-05-17 08:33:34'),(41,1,1,'Administrator',NULL,'font judul new task harus bold','',1,'2026-05-17 07:33:49',1,NULL,'2026-05-17 07:33:49',NULL,'daily',NULL,NULL,'2026-05-17 00:31:52','2026-05-17 07:33:49'),(42,1,1,'Administrator',NULL,'check UI table add cancellation_feedback.php','',1,'2026-05-17 07:33:46',1,NULL,'2026-05-17 07:33:46',NULL,'daily',NULL,NULL,'2026-05-17 00:43:17','2026-05-17 07:33:46'),(43,1,1,'Administrator',NULL,'change the logic of submitter','',1,'2026-05-17 14:14:42',1,NULL,'2026-05-17 14:14:42',NULL,'daily',NULL,NULL,'2026-05-17 07:18:09','2026-05-17 14:14:42'),(44,1,1,'Administrator',NULL,'ganti design choice box biar match dengan design tracs','',1,'2026-05-17 08:28:45',1,NULL,'2026-05-17 08:28:45',NULL,'daily',NULL,NULL,'2026-05-17 07:30:51','2026-05-17 08:28:45'),(45,1,1,'Administrator',NULL,'check lagi logic dari reminder','',1,'2026-05-17 12:08:17',1,NULL,'2026-05-17 12:08:17',NULL,'daily',NULL,NULL,'2026-05-17 07:54:11','2026-05-17 12:08:17'),(46,1,1,'Administrator',NULL,'logic delete/close reminder','',1,'2026-05-17 11:22:25',1,NULL,'2026-05-17 11:22:25',NULL,'daily',NULL,NULL,'2026-05-17 08:18:51','2026-05-17 11:22:25'),(47,1,1,'Administrator',NULL,'check bot autoclose intercom','',1,'2026-05-17 16:14:57',1,NULL,'2026-05-17 16:14:57',NULL,'daily',NULL,NULL,'2026-05-17 16:09:53','2026-05-17 16:14:57'),(48,1,1,'Administrator',NULL,'add page/feature for task asignment','',1,'2026-05-18 09:31:57',1,NULL,'2026-05-18 09:31:57',NULL,'daily',NULL,NULL,'2026-05-17 23:45:21','2026-05-18 09:31:57'),(49,1,1,'Administrator',NULL,'Edit logic when adding intern user','',1,'2026-05-18 09:31:59',1,NULL,'2026-05-18 09:31:59',NULL,'daily',NULL,NULL,'2026-05-18 00:03:19','2026-05-18 09:31:59'),(50,1,1,'Administrator',NULL,'remove User management overview section','',1,'2026-05-18 09:32:05',1,NULL,'2026-05-18 09:32:05',NULL,'daily',NULL,NULL,'2026-05-18 00:04:15','2026-05-18 09:32:05'),(51,1,1,'Administrator',NULL,'add tv or monitor mode','',1,'2026-05-18 09:47:06',1,NULL,'2026-05-18 09:47:06',NULL,'daily',NULL,NULL,'2026-05-18 00:05:47','2026-05-18 09:47:06'),(56,1,1,'Administrator',NULL,'update file .MD','',1,'2026-05-18 14:58:28',1,NULL,'2026-05-18 14:58:28',NULL,'daily',NULL,NULL,'2026-05-18 14:02:41','2026-05-18 14:58:28'),(57,1,1,'Administrator',NULL,'put out search input on cancellation feedback filter','',1,'2026-05-19 09:35:16',1,NULL,'2026-05-19 09:35:16',NULL,'daily',NULL,NULL,'2026-05-18 16:23:20','2026-05-19 09:35:16'),(58,1,1,'Administrator',NULL,'add profile picture user','',1,'2026-05-19 09:35:14',1,NULL,'2026-05-19 09:35:14',NULL,'daily',NULL,NULL,'2026-05-18 16:28:06','2026-05-19 09:35:14'),(59,1,1,'Administrator',NULL,'fix bug when hover avatar','',1,'2026-05-19 13:42:40',1,NULL,'2026-05-19 13:42:40',NULL,'daily',NULL,NULL,'2026-05-18 18:57:00','2026-05-19 13:42:40'),(60,1,1,'Administrator',NULL,'fix the width on data-tracs-select-for in Resolution on feedback page','',1,'2026-05-19 13:42:46',1,NULL,'2026-05-19 13:42:46',NULL,'daily',NULL,NULL,'2026-05-18 19:02:27','2026-05-19 13:42:46'),(61,1,1,'Administrator',NULL,'change logic for reminders','',1,'2026-05-19 13:42:48',1,NULL,'2026-05-19 13:42:48',NULL,'daily',NULL,NULL,'2026-05-19 09:35:40','2026-05-19 13:42:48'),(62,1,1,'Administrator',NULL,'remove status Critical etc. inside Affected','',1,'2026-05-20 17:07:53',1,NULL,'2026-05-20 17:07:53',NULL,'daily',NULL,NULL,'2026-05-19 14:24:13','2026-05-20 17:07:53'),(63,1,1,'Administrator',NULL,'fix sliding bug in affected','',1,'2026-05-20 17:07:52',1,NULL,'2026-05-20 17:07:52',NULL,'daily',NULL,NULL,'2026-05-19 14:52:34','2026-05-20 17:07:52'),(64,1,1,'Administrator',NULL,'safety check for the permalink (?) ocalhost:8080/mom.php?mom_id=3','ketika nomor dibagian ujung diganti harusnya tidak masuk ke meeting lain\n',1,'2026-05-20 16:57:22',1,NULL,'2026-05-20 16:57:22',NULL,'daily',NULL,NULL,'2026-05-19 15:13:28','2026-05-20 16:57:22'),(65,1,1,'Administrator',NULL,'Add feature crosscheck pricing domain','',1,'2026-05-20 07:10:53',1,NULL,'2026-05-20 07:10:53',NULL,'daily',NULL,NULL,'2026-05-19 15:16:48','2026-05-20 07:10:53'),(66,1,1,'Administrator',NULL,'import harga Registrar','',0,NULL,NULL,NULL,NULL,NULL,'daily',NULL,NULL,'2026-05-20 08:18:59','2026-05-20 08:18:59');
/*!40000 ALTER TABLE `tracs_side_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_task_assignments`
--

DROP TABLE IF EXISTS `tracs_task_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_task_assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `status` enum('assigned','not_started','in_progress','completed','completed_on_time','completed_late','overdue','need_review','reviewed','cancelled','reassigned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `progress_note` text COLLATE utf8mb4_unicode_ci,
  `completion_note` text COLLATE utf8mb4_unicode_ci,
  `review_note` text COLLATE utf8mb4_unicode_ci,
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_by` int unsigned DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `completion_seconds` int unsigned DEFAULT NULL,
  `overdue_seconds` int unsigned NOT NULL DEFAULT '0',
  `start_delay_seconds` int unsigned DEFAULT NULL,
  `linked_checklist_task_id` int unsigned DEFAULT NULL,
  `linked_reminder_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_task_user` (`task_id`,`user_id`),
  KEY `idx_tracs_task_assignments_user` (`user_id`,`status`),
  KEY `idx_tracs_task_assignments_status` (`status`),
  KEY `idx_tracs_task_assignments_checklist` (`linked_checklist_task_id`),
  KEY `idx_tracs_task_assignments_reminder` (`linked_reminder_id`),
  KEY `idx_tracs_task_assignments_timing` (`assigned_at`,`started_at`,`completed_at`),
  KEY `idx_tracs_task_assignments_review` (`reviewed_at`,`reviewed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_task_assignments`
--

LOCK TABLES `tracs_task_assignments` WRITE;
/*!40000 ALTER TABLE `tracs_task_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_task_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_task_logs`
--

DROP TABLE IF EXISTS `tracs_task_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_task_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int unsigned NOT NULL,
  `assignment_id` int unsigned DEFAULT NULL,
  `actor_user_id` int unsigned DEFAULT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracs_task_logs_task` (`task_id`,`created_at`),
  KEY `idx_tracs_task_logs_assignment` (`assignment_id`,`created_at`),
  KEY `idx_tracs_task_logs_actor` (`actor_user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_task_logs`
--

LOCK TABLES `tracs_task_logs` WRITE;
/*!40000 ALTER TABLE `tracs_task_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_task_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_task_reminders`
--

DROP TABLE IF EXISTS `tracs_task_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_task_reminders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int unsigned NOT NULL,
  `reminder_id` int unsigned DEFAULT NULL,
  `trigger_at` datetime DEFAULT NULL,
  `triggered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracs_task_reminders_assignment` (`assignment_id`),
  KEY `idx_tracs_task_reminders_trigger` (`trigger_at`,`triggered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_task_reminders`
--

LOCK TABLES `tracs_task_reminders` WRITE;
/*!40000 ALTER TABLE `tracs_task_reminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_task_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_task_reviews`
--

DROP TABLE IF EXISTS `tracs_task_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_task_reviews` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int unsigned NOT NULL,
  `reviewer_user_id` int unsigned DEFAULT NULL,
  `status` enum('pending','approved','changes_requested') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `review_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracs_task_reviews_assignment` (`assignment_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_task_reviews`
--

LOCK TABLES `tracs_task_reviews` WRITE;
/*!40000 ALTER TABLE `tracs_task_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_task_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_tasks`
--

DROP TABLE IF EXISTS `tracs_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_tasks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` enum('daily_checklist','case_follow_up','domain_transfer','balance_transfer','finance_log_mutasi','ssl_check','mom_follow_up','training_task','intern_task','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `assignment_scope` enum('users','roles','divisions','mixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'users',
  `due_at` datetime DEFAULT NULL,
  `recurrence_type` enum('none','daily') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `reference_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_review` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `assigned_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracs_tasks_due` (`due_at`),
  KEY `idx_tracs_tasks_category` (`category`),
  KEY `idx_tracs_tasks_priority` (`priority`),
  KEY `idx_tracs_tasks_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_tasks`
--

LOCK TABLES `tracs_tasks` WRITE;
/*!40000 ALTER TABLE `tracs_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracs_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_ticker_events`
--

DROP TABLE IF EXISTS `tracs_ticker_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_ticker_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `message` varchar(500) NOT NULL,
  `type` enum('info','success','warning','critical') DEFAULT 'info',
  `module` varchar(50) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`expires_at`),
  KEY `idx_ticker_events_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=221 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_ticker_events`
--

LOCK TABLES `tracs_ticker_events` WRITE;
/*!40000 ALTER TABLE `tracs_ticker_events` DISABLE KEYS */;
INSERT INTO `tracs_ticker_events` VALUES (218,1,1,'Administrator','Meeting auto-started: Title','warning','mom',8,'2026-05-20 18:06:45','2026-05-20 19:06:45'),(219,1,1,'Administrator','New shift report added: test widget','info','shift-reports',6,'2026-05-20 18:17:57','2026-05-20 19:17:57'),(220,1,1,'Administrator','Meeting completed: Title','success','mom',8,'2026-05-20 18:53:10','2026-05-20 19:53:10');
/*!40000 ALTER TABLE `tracs_ticker_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_ticker_messages`
--

DROP TABLE IF EXISTS `tracs_ticker_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_ticker_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `text` varchar(500) NOT NULL,
  `class` enum('normal','info','urgent','critical') DEFAULT 'normal',
  `enabled` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_ticker_messages_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_ticker_messages`
--

LOCK TABLES `tracs_ticker_messages` WRITE;
/*!40000 ALTER TABLE `tracs_ticker_messages` DISABLE KEYS */;
INSERT INTO `tracs_ticker_messages` VALUES (2,1,1,NULL,'HEYYYY ANTEK ANTEK ASENGGGG','info',1,'2026-05-10 08:34:41'),(3,1,1,NULL,'New domain transfer added: vickry','normal',1,'2026-05-11 08:50:57'),(4,1,1,NULL,'New domain transfer added: vickry.id','normal',1,'2026-05-11 08:51:11'),(5,1,1,NULL,'New domain transfer added: vickry.id','normal',1,'2026-05-11 09:18:03');
/*!40000 ALTER TABLE `tracs_ticker_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_user_activity_logs`
--

DROP TABLE IF EXISTS `tracs_user_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_user_activity_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int unsigned DEFAULT NULL,
  `target_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `before_data` longtext COLLATE utf8mb4_unicode_ci,
  `after_data` longtext COLLATE utf8mb4_unicode_ci,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracs_ual_actor` (`actor_user_id`,`created_at`),
  KEY `idx_tracs_ual_target` (`target_type`,`target_id`,`created_at`),
  KEY `idx_tracs_ual_action` (`action`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_user_activity_logs`
--

LOCK TABLES `tracs_user_activity_logs` WRITE;
/*!40000 ALTER TABLE `tracs_user_activity_logs` DISABLE KEYS */;
INSERT INTO `tracs_user_activity_logs` VALUES (1,1,'division',1,'create_division',NULL,'{\"id\":1,\"name\":\"Customer Support\",\"code\":\"CS\",\"description\":\"\",\"supervisor_id\":1,\"status\":\"active\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-05-17 23:14:15\",\"updated_at\":\"2026-05-17 23:14:15\"}',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 23:14:15'),(2,1,'user',3,'create_user',NULL,'{\"id\":3,\"email\":\"vickry@idcloudhost.co.id\",\"password\":\"[redacted]\",\"name\":\"Vickry\",\"created_at\":\"2026-05-17 23:15:52\",\"legacy_role\":\"admin\",\"is_active\":1,\"last_login_at\":null,\"updated_at\":\"2026-05-17 23:15:52\",\"username\":\"alfian\",\"phone\":\"081555784676\",\"position\":\"Customer Support\",\"status\":\"active\",\"division_id\":1,\"role_id\":1,\"shift_preference\":\"Shift 1, Shift 2, Shift 3\",\"avatar_initials_color\":null,\"created_by\":1,\"updated_by\":1,\"last_activity_at\":null,\"last_password_change_at\":\"[redacted]\",\"role_name\":\"Super Admin\",\"role_slug\":\"super_admin\",\"hierarchy_level\":100,\"is_system_role\":1,\"division_name\":\"Customer Support\",\"division_code\":\"CS\",\"display_name\":\"Vickry\"}',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 23:15:52'),(3,1,'user',4,'create_user',NULL,'{\"id\":4,\"email\":\"gagas@idcloudhost.co.id\",\"password\":\"[redacted]\",\"name\":\"Gagas\",\"created_at\":\"2026-05-17 23:23:47\",\"legacy_role\":\"operator\",\"is_active\":1,\"last_login_at\":null,\"updated_at\":\"2026-05-17 23:23:47\",\"username\":\"gagas\",\"phone\":null,\"position\":\"Customer Support\",\"status\":\"active\",\"division_id\":1,\"role_id\":4,\"shift_preference\":\"Shift 1, Shift 2, Shift 3\",\"avatar_initials_color\":null,\"created_by\":1,\"updated_by\":1,\"last_activity_at\":null,\"last_password_change_at\":\"[redacted]\",\"role_name\":\"Agent\",\"role_slug\":\"agent\",\"hierarchy_level\":40,\"is_system_role\":1,\"division_name\":\"Customer Support\",\"division_code\":\"CS\",\"display_name\":\"Gagas\"}',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 23:23:47'),(4,1,'user',3,'update_user','{\"id\":3,\"email\":\"vickry@idcloudhost.co.id\",\"password\":\"[redacted]\",\"name\":\"Vickry\",\"created_at\":\"2026-05-17 23:15:52\",\"legacy_role\":\"admin\",\"is_active\":1,\"last_login_at\":null,\"updated_at\":\"2026-05-17 23:15:52\",\"username\":\"alfian\",\"phone\":\"081555784676\",\"position\":\"Customer Support\",\"status\":\"active\",\"division_id\":1,\"role_id\":1,\"shift_preference\":\"Shift 1, Shift 2, Shift 3\",\"avatar_initials_color\":null,\"created_by\":1,\"updated_by\":1,\"last_activity_at\":null,\"last_password_change_at\":\"[redacted]\",\"role_name\":\"Super Admin\",\"role_slug\":\"super_admin\",\"hierarchy_level\":100,\"is_system_role\":1,\"division_name\":\"Customer Support\",\"division_code\":\"CS\",\"display_name\":\"Vickry\"}','{\"username\":{\"before\":\"alfian\",\"after\":\"vickry\"}}',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 23:24:05'),(5,1,'user',5,'create_user',NULL,'{\"id\":5,\"email\":\"internqa@tracs.local\",\"password\":\"[redacted]\",\"name\":\"Intern QA User\",\"created_at\":\"2026-05-18 00:04:37\",\"legacy_role\":\"operator\",\"is_active\":1,\"last_login_at\":null,\"updated_at\":\"2026-05-18 00:04:37\",\"username\":\"internqa\",\"phone\":null,\"position\":\"Intern\",\"status\":\"active\",\"division_id\":1,\"role_id\":21,\"shift_preference\":\"Shift 1\",\"avatar_initials_color\":null,\"created_by\":1,\"updated_by\":1,\"last_activity_at\":null,\"last_password_change_at\":\"[redacted]\",\"role_name\":\"Intern\",\"role_slug\":\"intern\",\"hierarchy_level\":30,\"is_system_role\":1,\"division_name\":\"Customer Support\",\"division_code\":\"CS\",\"display_name\":\"Intern QA User\",\"internship_days_remaining\":null,\"internship_monitor_state\":\"\",\"is_intern\":true}',NULL,'172.18.0.1','curl/8.7.1','2026-05-18 00:04:37'),(6,1,'user',5,'intern_profile_created',NULL,'{\"id\":1,\"user_id\":5,\"university_name\":\"Universitas QA\",\"study_program\":\"Information Systems\",\"internship_start_date\":\"2026-05-01\",\"internship_end_date\":\"2026-05-25\",\"mentor_user_id\":null,\"internship_status\":\"active\",\"evaluation_status\":\"not_started\",\"skill_level\":\"beginner\",\"allowed_task_scope\":\"observation_only\",\"special_notes\":\"Shell verification intern profile.\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-05-18 00:04:37\",\"updated_at\":\"2026-05-18 00:04:37\",\"mentor_name\":null}',NULL,'172.18.0.1','curl/8.7.1','2026-05-18 00:04:37'),(7,1,'user',5,'intern_user_created',NULL,'{\"user\":{\"id\":5,\"email\":\"internqa@tracs.local\",\"password\":\"[redacted]\",\"name\":\"Intern QA User\",\"created_at\":\"2026-05-18 00:04:37\",\"legacy_role\":\"operator\",\"is_active\":1,\"last_login_at\":null,\"updated_at\":\"2026-05-18 00:04:37\",\"username\":\"internqa\",\"phone\":null,\"position\":\"Intern\",\"status\":\"active\",\"division_id\":1,\"role_id\":21,\"shift_preference\":\"Shift 1\",\"avatar_initials_color\":null,\"created_by\":1,\"updated_by\":1,\"last_activity_at\":null,\"last_password_change_at\":\"[redacted]\",\"role_name\":\"Intern\",\"role_slug\":\"intern\",\"hierarchy_level\":30,\"is_system_role\":1,\"division_name\":\"Customer Support\",\"division_code\":\"CS\",\"display_name\":\"Intern QA User\",\"university_name\":\"Universitas QA\",\"study_program\":\"Information Systems\",\"internship_start_date\":\"2026-05-01\",\"internship_end_date\":\"2026-05-25\",\"mentor_user_id\":null,\"internship_status\":\"active\",\"evaluation_status\":\"not_started\",\"skill_level\":\"beginner\",\"allowed_task_scope\":\"observation_only\",\"special_notes\":\"Shell verification intern profile.\",\"mentor_name\":null,\"internship_days_remaining\":7,\"internship_monitor_state\":\"ending_soon\",\"is_intern\":true},\"intern_profile\":{\"id\":1,\"user_id\":5,\"university_name\":\"Universitas QA\",\"study_program\":\"Information Systems\",\"internship_start_date\":\"2026-05-01\",\"internship_end_date\":\"2026-05-25\",\"mentor_user_id\":null,\"internship_status\":\"active\",\"evaluation_status\":\"not_started\",\"skill_level\":\"beginner\",\"allowed_task_scope\":\"observation_only\",\"special_notes\":\"Shell verification intern profile.\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-05-18 00:04:37\",\"updated_at\":\"2026-05-18 00:04:37\",\"mentor_name\":null}}',NULL,'172.18.0.1','curl/8.7.1','2026-05-18 00:04:37'),(8,1,'task',1,'task_created',NULL,'{\"assignees\":[1],\"title\":\"Codex smoke task\"}',NULL,'','','2026-05-18 08:23:54'),(9,1,'task',2,'task_created',NULL,'{\"assignees\":[1],\"title\":\"Codex SLA smoke task\"}',NULL,'','','2026-05-18 09:19:06'),(10,1,'task',3,'task_created',NULL,'{\"assignees\":[1],\"title\":\"Codex metrics smoke task\"}',NULL,'','','2026-05-18 09:25:41'),(11,1,'user',6,'create_user',NULL,'{\"id\":6,\"email\":\"lala@idcloudhost.co.id\",\"password\":\"[redacted]\",\"name\":\"Lala\",\"created_at\":\"2026-05-18 15:07:08\",\"legacy_role\":\"operator\",\"is_active\":1,\"last_login_at\":null,\"updated_at\":\"2026-05-18 15:07:08\",\"username\":\"lala\",\"phone\":null,\"position\":null,\"status\":\"active\",\"division_id\":1,\"role_id\":4,\"shift_preference\":\"Shift 1, Shift 2\",\"avatar_initials_color\":null,\"created_by\":1,\"updated_by\":1,\"last_activity_at\":null,\"last_password_change_at\":\"[redacted]\",\"role_name\":\"Agent\",\"role_slug\":\"agent\",\"hierarchy_level\":40,\"is_system_role\":1,\"division_name\":\"Customer Support\",\"division_code\":\"CS\",\"display_name\":\"Lala\",\"internship_days_remaining\":null,\"internship_monitor_state\":\"\",\"is_intern\":false}',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 15:07:08'),(12,1,'user',1,'user_avatar_updated','{\"avatar_path\":\"\"}','{\"avatar_path\":\"/uploads/avatars/avatar_1_010adcd2923f054c86eabebe.webp\"}',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 18:54:35'),(13,1,'user',1,'user_changed_own_password','{\"last_password_change_at\":\"[redacted]\"}','{\"password_changed\":\"[redacted]\"}',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-19 13:10:58');
/*!40000 ALTER TABLE `tracs_user_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracs_users`
--

DROP TABLE IF EXISTS `tracs_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracs_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `position` varchar(120) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `username` varchar(80) DEFAULT NULL,
  `role` enum('admin','operator','viewer') NOT NULL DEFAULT 'operator',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `division_id` int unsigned DEFAULT NULL,
  `role_id` int unsigned DEFAULT NULL,
  `shift_preference` varchar(60) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `avatar_initials_color` varchar(20) DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `last_password_change_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_tracs_users_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_tracs_users_status` (`status`),
  KEY `idx_tracs_users_role_id` (`role_id`),
  KEY `idx_tracs_users_division_id` (`division_id`),
  KEY `idx_tracs_users_last_activity` (`last_activity_at`),
  CONSTRAINT `fk_tracs_users_division` FOREIGN KEY (`division_id`) REFERENCES `tracs_divisions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tracs_users_role` FOREIGN KEY (`role_id`) REFERENCES `tracs_roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracs_users`
--

LOCK TABLES `tracs_users` WRITE;
/*!40000 ALTER TABLE `tracs_users` DISABLE KEYS */;
INSERT INTO `tracs_users` VALUES (1,'admin@tracs.local',NULL,NULL,'$2y$10$r9kxqqf7CiTD4tTGxn7pTOF7iXKxKybgc99VSLbGQsfUG0.VJdjNa','Administrator','user1','admin',1,'active',NULL,1,NULL,'/uploads/avatars/avatar_1_010adcd2923f054c86eabebe.webp',NULL,NULL,1,'2026-05-21 11:03:26','2026-05-21 11:03:26','2026-05-19 13:10:58','2026-05-08 14:42:28','2026-05-21 11:03:26'),(2,'test@tracs.local',NULL,NULL,'$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Test User','user2','operator',1,'active',NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-08 17:34:28','2026-05-17 22:51:38'),(3,'vickry@idcloudhost.co.id','081555784676','Customer Support','$2y$10$xULXGcL4MbhvMApgnywmMOWDqUO5mgqrMUy.IWUIvV11rqpEn6CTO','Vickry','vickry','admin',1,'active',1,1,'Shift 1, Shift 2, Shift 3',NULL,NULL,1,1,NULL,'2026-05-20 08:14:35','2026-05-17 23:15:52','2026-05-17 23:15:52','2026-05-20 08:14:35'),(4,'gagas@idcloudhost.co.id',NULL,'Customer Support','$2y$10$wgHOHCi7AJUr3lfaz187qO91aW2D3Sei3widIpaZVoAh4zk3n7bcm','Gagas','gagas','operator',1,'active',1,4,'Shift 1, Shift 2, Shift 3',NULL,NULL,1,1,NULL,NULL,'2026-05-17 23:23:47','2026-05-17 23:23:47','2026-05-17 23:23:47'),(5,'internqa@tracs.local',NULL,'Intern','$2y$10$S/dCHnrVPgNlBDMJ1F9w5ura4naJQOAwjLqWYIWbhYgeEKsT1corq','Intern QA User','internqa','operator',1,'active',1,21,'Shift 1',NULL,NULL,1,1,NULL,NULL,'2026-05-18 00:04:37','2026-05-18 00:04:37','2026-05-18 00:04:37'),(6,'lala@idcloudhost.co.id',NULL,NULL,'$2y$10$ZuZj8USeOHiKuS0P3Aor1OxogB7u.I2ZfpBTZjRN9mgbMHmpTXp9O','Lala','lala','operator',1,'active',1,4,'Shift 1, Shift 2',NULL,NULL,1,1,NULL,NULL,'2026-05-18 15:07:08','2026-05-18 15:07:08','2026-05-18 15:07:08');
/*!40000 ALTER TABLE `tracs_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_intern_profiles`
--

DROP TABLE IF EXISTS `user_intern_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_intern_profiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `university_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `study_program` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internship_start_date` date NOT NULL,
  `internship_end_date` date NOT NULL,
  `mentor_user_id` int unsigned DEFAULT NULL,
  `internship_status` enum('upcoming','active','ending_soon','completed','extended','terminated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upcoming',
  `evaluation_status` enum('not_started','in_review','passed','needs_improvement','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `skill_level` enum('beginner','basic','intermediate','advanced') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'beginner',
  `allowed_task_scope` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `special_notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_intern_profiles_user` (`user_id`),
  KEY `idx_user_intern_profiles_status` (`internship_status`),
  KEY `idx_user_intern_profiles_start` (`internship_start_date`),
  KEY `idx_user_intern_profiles_end` (`internship_end_date`),
  KEY `idx_user_intern_profiles_mentor` (`mentor_user_id`),
  KEY `idx_user_intern_profiles_university` (`university_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_intern_profiles`
--

LOCK TABLES `user_intern_profiles` WRITE;
/*!40000 ALTER TABLE `user_intern_profiles` DISABLE KEYS */;
INSERT INTO `user_intern_profiles` VALUES (1,5,'Universitas QA','Information Systems','2026-05-01','2026-05-25',NULL,'active','not_started','beginner','observation_only','Shell verification intern profile.',1,1,'2026-05-18 00:04:37','2026-05-18 00:04:37');
/*!40000 ALTER TABLE `user_intern_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `vw_mom_overdue_actions`
--

DROP TABLE IF EXISTS `vw_mom_overdue_actions`;
/*!50001 DROP VIEW IF EXISTS `vw_mom_overdue_actions`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_mom_overdue_actions` AS SELECT 
 1 AS `id`,
 1 AS `mom_id`,
 1 AS `title`,
 1 AS `assigned_to`,
 1 AS `priority`,
 1 AS `due_date`,
 1 AS `mom_title`,
 1 AS `days_overdue`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_mom_summary`
--

DROP TABLE IF EXISTS `vw_mom_summary`;
/*!50001 DROP VIEW IF EXISTS `vw_mom_summary`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_mom_summary` AS SELECT 
 1 AS `id`,
 1 AS `title`,
 1 AS `type`,
 1 AS `status`,
 1 AS `created_by`,
 1 AS `created_at`,
 1 AS `pending_actions`,
 1 AS `completed_actions`,
 1 AS `total_decisions`,
 1 AS `total_notes`*/;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `vw_mom_overdue_actions`
--

/*!50001 DROP VIEW IF EXISTS `vw_mom_overdue_actions`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = latin1 */;
/*!50001 SET character_set_results     = latin1 */;
/*!50001 SET collation_connection      = latin1_swedish_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_mom_overdue_actions` AS select `a`.`id` AS `id`,`a`.`mom_id` AS `mom_id`,`a`.`title` AS `title`,`a`.`assigned_to` AS `assigned_to`,`a`.`priority` AS `priority`,`a`.`due_date` AS `due_date`,`m`.`title` AS `mom_title`,(to_days(now()) - to_days(`a`.`due_date`)) AS `days_overdue` from (`tracs_mom_actions` `a` join `tracs_moms` `m` on((`a`.`mom_id` = `m`.`id`))) where ((`a`.`status` not in ('completed','cancelled')) and (`a`.`due_date` < now())) order by `a`.`priority` desc,`a`.`due_date` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_mom_summary`
--

/*!50001 DROP VIEW IF EXISTS `vw_mom_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = latin1 */;
/*!50001 SET character_set_results     = latin1 */;
/*!50001 SET collation_connection      = latin1_swedish_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_mom_summary` AS select `m`.`id` AS `id`,`m`.`title` AS `title`,`m`.`type` AS `type`,`m`.`status` AS `status`,`m`.`created_by` AS `created_by`,`m`.`created_at` AS `created_at`,count(distinct (case when (`a`.`status` <> 'completed') then `a`.`id` end)) AS `pending_actions`,count(distinct (case when (`a`.`status` = 'completed') then `a`.`id` end)) AS `completed_actions`,count(distinct `d`.`id`) AS `total_decisions`,count(distinct `n`.`id`) AS `total_notes` from (((`tracs_moms` `m` left join `tracs_mom_actions` `a` on((`m`.`id` = `a`.`mom_id`))) left join `tracs_mom_decisions` `d` on((`m`.`id` = `d`.`mom_id`))) left join `tracs_mom_notes` `n` on((`m`.`id` = `n`.`mom_id`))) group by `m`.`id`,`m`.`title`,`m`.`type`,`m`.`status`,`m`.`created_by`,`m`.`created_at` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-21 14:09:29
