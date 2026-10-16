-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: houseflipper
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.4

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
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorias_despesa`
--

DROP TABLE IF EXISTS `categorias_despesa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_despesa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reforma',
  `ativo_flg` tinyint(1) NOT NULL DEFAULT '1',
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `categorias_despesa_tenant_id_nome_deletado_flg_unique` (`tenant_id`,`nome`,`deletado_flg`),
  KEY `categorias_despesa_tenant_id_index` (`tenant_id`),
  KEY `categorias_despesa_tipo_index` (`tipo`),
  CONSTRAINT `categorias_despesa_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `despesas`
--

DROP TABLE IF EXISTS `despesas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `despesas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `propriedade_id` bigint unsigned DEFAULT NULL,
  `projeto_id` bigint unsigned DEFAULT NULL,
  `etapa_id` bigint unsigned DEFAULT NULL,
  `tarefa_id` bigint unsigned DEFAULT NULL,
  `categoria_despesa_id` bigint unsigned NOT NULL,
  `descricao` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `data_despesa` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `forma_pagamento` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_pagamento` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `documento_numero` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `despesas_etapa_id_foreign` (`etapa_id`),
  KEY `despesas_tarefa_id_foreign` (`tarefa_id`),
  KEY `despesas_categoria_despesa_id_foreign` (`categoria_despesa_id`),
  KEY `despesas_tenant_id_index` (`tenant_id`),
  KEY `despesas_propriedade_id_index` (`propriedade_id`),
  KEY `despesas_projeto_id_index` (`projeto_id`),
  KEY `despesas_data_despesa_index` (`data_despesa`),
  KEY `despesas_status_pagamento_index` (`status_pagamento`),
  CONSTRAINT `despesas_categoria_despesa_id_foreign` FOREIGN KEY (`categoria_despesa_id`) REFERENCES `categorias_despesa` (`id`),
  CONSTRAINT `despesas_etapa_id_foreign` FOREIGN KEY (`etapa_id`) REFERENCES `projetos_etapas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `despesas_projeto_id_foreign` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `despesas_propriedade_id_foreign` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `despesas_tarefa_id_foreign` FOREIGN KEY (`tarefa_id`) REFERENCES `tarefas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `despesas_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `financing_details`
--

DROP TABLE IF EXISTS `financing_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financing_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `investment_analysis_id` bigint unsigned NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_selected` tinyint(1) NOT NULL DEFAULT '0',
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_system` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interest_rate_monthly` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `interest_rate_yearly` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `total_installments` smallint unsigned NOT NULL DEFAULT '0',
  `installment_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `paid_installments` smallint unsigned NOT NULL DEFAULT '0',
  `outstanding_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payoff_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `financing_details_analysis_fk_idx` (`investment_analysis_id`),
  KEY `financing_details_investment_analysis_id_is_selected_index` (`investment_analysis_id`,`is_selected`),
  CONSTRAINT `financing_details_investment_analysis_id_foreign` FOREIGN KEY (`investment_analysis_id`) REFERENCES `investment_analyses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `geocoding_cache`
--

DROP TABLE IF EXISTS `geocoding_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `geocoding_cache` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `normalized_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `place_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partial_match` tinyint(1) NOT NULL DEFAULT '0',
  `formatted_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_payload` json DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `queried_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `geocoding_cache_provider_address_hash_unique` (`provider`,`address_hash`),
  KEY `geocoding_cache_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `investment_analyses`
--

DROP TABLE IF EXISTS `investment_analyses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_analyses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opportunity_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `property_condition` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `market_region` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `average_price_per_m2` decimal(15,2) NOT NULL DEFAULT '0.00',
  `average_sold_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `suggested_sale_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `purchase_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `down_payment` decimal(15,2) NOT NULL DEFAULT '0.00',
  `financed_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `renovation_estimate` decimal(15,2) NOT NULL DEFAULT '0.00',
  `documentation_costs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `taxes_costs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `broker_commission` decimal(15,2) NOT NULL DEFAULT '0.00',
  `detailed_costs_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expected_sale_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expected_monthly_costs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expected_installment_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expected_outstanding_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_own_capital_invested` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expected_net_profit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expected_months_until_sale` smallint unsigned NOT NULL DEFAULT '1',
  `expected_roi_percentage` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `expected_monthly_roi_percentage` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `expected_annualized_roi_percentage` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `decision` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rejected',
  `decision_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `viability_completed_at` timestamp NULL DEFAULT NULL,
  `financing_completed_at` timestamp NULL DEFAULT NULL,
  `costs_completed_at` timestamp NULL DEFAULT NULL,
  `result_completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_analyses_opportunity_id_foreign` (`opportunity_id`),
  KEY `investment_analyses_user_id_is_primary_index` (`user_id`,`is_primary`),
  CONSTRAINT `investment_analyses_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `investment_analyses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `investment_costs`
--

DROP TABLE IF EXISTS `investment_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `investment_analysis_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_costs_investment_analysis_id_foreign` (`investment_analysis_id`),
  KEY `investment_costs_user_id_foreign` (`user_id`),
  CONSTRAINT `investment_costs_investment_analysis_id_foreign` FOREIGN KEY (`investment_analysis_id`) REFERENCES `investment_analyses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `investment_costs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `investment_results`
--

DROP TABLE IF EXISTS `investment_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `investment_analysis_id` bigint unsigned NOT NULL,
  `sale_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `sale_date` date DEFAULT NULL,
  `sale_costs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payoff_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_own_capital_invested` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_amount_received_after_payoff` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_profit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `roi_percentage` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `monthly_roi_percentage` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `annualized_roi_percentage` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `flip_duration_months` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `investment_results_investment_analysis_id_unique` (`investment_analysis_id`),
  CONSTRAINT `investment_results_investment_analysis_id_foreign` FOREIGN KEY (`investment_analysis_id`) REFERENCES `investment_analyses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `itbi_import_batches`
--

DROP TABLE IF EXISTS `itbi_import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `itbi_import_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_period` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'discovered',
  `discovered_at` timestamp NULL DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `processing_started_at` timestamp NULL DEFAULT NULL,
  `processing_finished_at` timestamp NULL DEFAULT NULL,
  `total_rows` int unsigned NOT NULL DEFAULT '0',
  `imported_rows` int unsigned NOT NULL DEFAULT '0',
  `updated_rows` int unsigned NOT NULL DEFAULT '0',
  `skipped_rows` int unsigned NOT NULL DEFAULT '0',
  `failed_rows` int unsigned NOT NULL DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `metadata_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `itbi_import_batches_provider_status_index` (`provider`,`status`),
  KEY `itbi_import_batches_reference_period_index` (`reference_period`),
  KEY `itbi_import_batches_file_hash_index` (`file_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `media`
--

DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collection_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversions_disk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL,
  `manipulations` json NOT NULL,
  `custom_properties` json NOT NULL,
  `generated_conversions` json NOT NULL,
  `responsive_images` json NOT NULL,
  `order_column` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_uuid_unique` (`uuid`),
  KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `media_order_column_index` (`order_column`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `opportunities`
--

DROP TABLE IF EXISTS `opportunities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opportunities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `property_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `source_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `neighborhood` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `asking_price` decimal(15,2) DEFAULT NULL,
  `estimated_market_price` decimal(15,2) DEFAULT NULL,
  `area_m2` decimal(10,2) DEFAULT NULL,
  `bedrooms` smallint unsigned DEFAULT NULL,
  `bathrooms` smallint unsigned DEFAULT NULL,
  `parking_spaces` smallint unsigned DEFAULT NULL,
  `conservation_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_favorite` tinyint(1) NOT NULL DEFAULT '0',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `discarded_reason` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opportunities_user_id_status_index` (`user_id`,`status`),
  KEY `opportunities_user_id_is_favorite_index` (`user_id`,`is_favorite`),
  KEY `opportunities_city_neighborhood_index` (`city`,`neighborhood`),
  CONSTRAINT `opportunities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `opportunity_photos`
--

DROP TABLE IF EXISTS `opportunity_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opportunity_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opportunity_id` bigint unsigned NOT NULL,
  `path` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` smallint unsigned NOT NULL DEFAULT '0',
  `is_cover` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opportunity_photos_opportunity_id_order_index` (`opportunity_id`,`order`),
  CONSTRAINT `opportunity_photos_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `opportunity_status_histories`
--

DROP TABLE IF EXISTS `opportunity_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opportunity_status_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opportunity_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `old_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opportunity_status_histories_user_id_foreign` (`user_id`),
  KEY `opportunity_status_histories_opportunity_id_created_at_index` (`opportunity_id`,`created_at`),
  CONSTRAINT `opportunity_status_histories_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opportunity_status_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projetos`
--

DROP TABLE IF EXISTS `projetos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projetos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `propriedade_id` bigint unsigned NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `orcamento_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `custo_real` decimal(15,2) NOT NULL DEFAULT '0.00',
  `data_inicio` date DEFAULT NULL,
  `data_termino_prevista` date DEFAULT NULL,
  `data_termino_real` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planejamento',
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `projetos_tenant_id_index` (`tenant_id`),
  KEY `projetos_propriedade_id_index` (`propriedade_id`),
  KEY `projetos_status_index` (`status`),
  KEY `projetos_deletado_flg_index` (`deletado_flg`),
  CONSTRAINT `projetos_propriedade_id_foreign` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projetos_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projetos_etapas`
--

DROP TABLE IF EXISTS `projetos_etapas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projetos_etapas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `projeto_id` bigint unsigned NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ordem` int NOT NULL DEFAULT '0',
  `orcamento` decimal(15,2) NOT NULL DEFAULT '0.00',
  `custo_real` decimal(15,2) NOT NULL DEFAULT '0.00',
  `data_inicio_prevista` date DEFAULT NULL,
  `data_termino_prevista` date DEFAULT NULL,
  `data_inicio_real` date DEFAULT NULL,
  `data_termino_real` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `progresso_pct` int NOT NULL DEFAULT '0',
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `projetos_etapas_tenant_id_index` (`tenant_id`),
  KEY `projetos_etapas_projeto_id_index` (`projeto_id`),
  KEY `projetos_etapas_status_index` (`status`),
  CONSTRAINT `projetos_etapas_projeto_id_foreign` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projetos_etapas_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `property_market_statistics`
--

DROP TABLE IF EXISTS `property_market_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_market_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `geographic_scope_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `geographic_scope_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_date` date DEFAULT NULL,
  `radius_meters` int unsigned DEFAULT NULL,
  `transaction_count` int unsigned NOT NULL DEFAULT '0',
  `median_price` decimal(15,2) DEFAULT NULL,
  `average_price` decimal(15,2) DEFAULT NULL,
  `median_price_m2` decimal(15,2) DEFAULT NULL,
  `average_price_m2` decimal(15,2) DEFAULT NULL,
  `min_price_m2` decimal(15,2) DEFAULT NULL,
  `max_price_m2` decimal(15,2) DEFAULT NULL,
  `percentile_25_price_m2` decimal(15,2) DEFAULT NULL,
  `percentile_75_price_m2` decimal(15,2) DEFAULT NULL,
  `standard_deviation_price_m2` decimal(15,2) DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `market_stats_scope_index` (`geographic_scope_type`,`geographic_scope_key`),
  KEY `property_market_statistics_property_type_reference_date_index` (`property_type`,`reference_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `propriedades`
--

DROP TABLE IF EXISTS `propriedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `propriedades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `apelido` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area_m2` decimal(10,2) DEFAULT NULL,
  `quartos` int DEFAULT NULL,
  `banheiros` int DEFAULT NULL,
  `vagas` int DEFAULT NULL,
  `valor_aquisicao` decimal(15,2) DEFAULT NULL,
  `data_aquisicao` date DEFAULT NULL,
  `valor_venda_alvo` decimal(15,2) DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prospeccao',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `propriedades_tenant_id_index` (`tenant_id`),
  KEY `propriedades_status_index` (`status`),
  KEY `propriedades_deletado_flg_index` (`deletado_flg`),
  CONSTRAINT `propriedades_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `propriedades_enderecos`
--

DROP TABLE IF EXISTS `propriedades_enderecos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `propriedades_enderecos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `propriedade_id` bigint unsigned NOT NULL,
  `cep` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logradouro` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complemento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cidade` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pais` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Brasil',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `propriedades_enderecos_propriedade_id_deletado_flg_unique` (`propriedade_id`,`deletado_flg`),
  KEY `propriedades_enderecos_tenant_id_index` (`tenant_id`),
  CONSTRAINT `propriedades_enderecos_propriedade_id_foreign` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `propriedades_enderecos_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `real_estate_transactions`
--

DROP TABLE IF EXISTS `real_estate_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `real_estate_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_batch_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'itbi',
  `source_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_reference` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_sql` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `declared_transaction_value` decimal(15,2) DEFAULT NULL,
  `tax_base_value` decimal(15,2) DEFAULT NULL,
  `property_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_nature` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_complement` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_reference` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `neighborhood` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'São Paulo',
  `state` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SP',
  `zip_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_type` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registry_office` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registry_registration` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `land_area_m2` decimal(12,2) DEFAULT NULL,
  `built_area_m2` decimal(12,2) DEFAULT NULL,
  `private_area_m2` decimal(12,2) DEFAULT NULL,
  `calculation_area_m2` decimal(12,2) DEFAULT NULL,
  `calculation_area_source` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_m2` decimal(15,2) DEFAULT NULL,
  `normalized_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `google_place_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geocoding_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geocoding_precision` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geocoding_confidence` tinyint unsigned DEFAULT NULL,
  `geocoded_at` timestamp NULL DEFAULT NULL,
  `iptu_use_description` text COLLATE utf8mb4_unicode_ci,
  `iptu_pattern_description` text COLLATE utf8mb4_unicode_ci,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transactions_source_unique` (`source_type`,`source_name`,`source_reference`),
  KEY `real_estate_transactions_import_batch_id_foreign` (`import_batch_id`),
  KEY `real_estate_transactions_property_sql_index` (`property_sql`),
  KEY `real_estate_transactions_transaction_date_index` (`transaction_date`),
  KEY `real_estate_transactions_address_hash_index` (`address_hash`),
  KEY `real_estate_transactions_building_key_index` (`building_key`),
  KEY `real_estate_transactions_latitude_longitude_index` (`latitude`,`longitude`),
  KEY `real_estate_transactions_district_index` (`district`),
  KEY `real_estate_transactions_neighborhood_index` (`neighborhood`),
  KEY `real_estate_transactions_property_type_index` (`property_type`),
  KEY `real_estate_transactions_price_m2_index` (`price_m2`),
  CONSTRAINT `real_estate_transactions_import_batch_id_foreign` FOREIGN KEY (`import_batch_id`) REFERENCES `itbi_import_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tarefas`
--

DROP TABLE IF EXISTS `tarefas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tarefas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `projeto_id` bigint unsigned NOT NULL,
  `etapa_id` bigint unsigned DEFAULT NULL,
  `responsavel_user_id` bigint unsigned DEFAULT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `data_prevista` date DEFAULT NULL,
  `data_conclusao` date DEFAULT NULL,
  `prioridade` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `custo_estimado` decimal(15,2) DEFAULT NULL,
  `custo_real` decimal(15,2) DEFAULT NULL,
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `tarefas_responsavel_user_id_foreign` (`responsavel_user_id`),
  KEY `tarefas_tenant_id_index` (`tenant_id`),
  KEY `tarefas_projeto_id_index` (`projeto_id`),
  KEY `tarefas_etapa_id_index` (`etapa_id`),
  KEY `tarefas_status_index` (`status`),
  CONSTRAINT `tarefas_etapa_id_foreign` FOREIGN KEY (`etapa_id`) REFERENCES `projetos_etapas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tarefas_projeto_id_foreign` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tarefas_responsavel_user_id_foreign` FOREIGN KEY (`responsavel_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tarefas_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_contato` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo_flg` tinyint(1) NOT NULL DEFAULT '1',
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  KEY `tenants_ativo_flg_index` (`ativo_flg`),
  KEY `tenants_deletado_flg_index` (`deletado_flg`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo_flg` tinyint(1) NOT NULL DEFAULT '1',
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_tenant_id_index` (`tenant_id`),
  KEY `users_deletado_flg_index` (`deletado_flg`),
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendas`
--

DROP TABLE IF EXISTS `vendas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `propriedade_id` bigint unsigned NOT NULL,
  `comprador_nome` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comprador_documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comprador_contato` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_venda` decimal(15,2) NOT NULL,
  `valor_comissao` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_impostos` decimal(15,2) NOT NULL DEFAULT '0.00',
  `outras_taxas` decimal(15,2) NOT NULL DEFAULT '0.00',
  `data_proposta` date DEFAULT NULL,
  `data_fechamento` date DEFAULT NULL,
  `data_escritura` date DEFAULT NULL,
  `forma_pagamento` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposta',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `data_inclusao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_modificacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deletado_flg` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `vendas_tenant_id_index` (`tenant_id`),
  KEY `vendas_propriedade_id_index` (`propriedade_id`),
  KEY `vendas_status_index` (`status`),
  KEY `vendas_data_fechamento_index` (`data_fechamento`),
  CONSTRAINT `vendas_propriedade_id_foreign` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendas_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work_budgets`
--

DROP TABLE IF EXISTS `work_budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_budgets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_id` bigint unsigned NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estimated_cost` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_budgets_work_id_foreign` (`work_id`),
  CONSTRAINT `work_budgets_work_id_foreign` FOREIGN KEY (`work_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'houseflipper'
--

--
-- Dumping routines for database 'houseflipper'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-16 14:32:41
