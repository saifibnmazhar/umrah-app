/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `airline_cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `airline_cities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `airline_id` bigint(20) unsigned NOT NULL,
  `city_code_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `airline_cities_airline_id_city_code_id_unique` (`airline_id`,`city_code_id`),
  KEY `airline_cities_airline_id_index` (`airline_id`),
  KEY `airline_cities_city_code_id_index` (`city_code_id`),
  CONSTRAINT `airline_cities_airline_id_foreign` FOREIGN KEY (`airline_id`) REFERENCES `airlines` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `airline_cities_city_code_id_foreign` FOREIGN KEY (`city_code_id`) REFERENCES `city_codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `airline_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `airline_classes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `airline_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `airline_classes_airline_id_class_id_unique` (`airline_id`,`class_id`),
  KEY `airline_classes_airline_id_index` (`airline_id`),
  KEY `airline_classes_class_id_index` (`class_id`),
  CONSTRAINT `airline_classes_airline_id_foreign` FOREIGN KEY (`airline_id`) REFERENCES `airlines` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `airline_classes_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `airlines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `airlines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `airlines_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `baggage_allowances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `baggage_allowances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_fare_id` bigint(20) unsigned NOT NULL,
  `passenger_type` enum('adult','child','infant') NOT NULL,
  `travel_direction` enum('inbound','outbound') NOT NULL,
  `allowance` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `baggage_allowance_unique` (`ticket_fare_id`,`passenger_type`,`travel_direction`),
  CONSTRAINT `baggage_allowances_ticket_fare_id_foreign` FOREIGN KEY (`ticket_fare_id`) REFERENCES `ticket_fares` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `banks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `currency` enum('SAR','BDT') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `office_id` bigint(20) unsigned NOT NULL,
  `district_id` bigint(20) unsigned NOT NULL,
  `package_id` bigint(20) unsigned NOT NULL,
  `fingerprint_charge_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `invoice_id` varchar(255) NOT NULL,
  `date_gap_id` bigint(20) unsigned NOT NULL,
  `fingerprint_location` enum('home','office') NOT NULL,
  `pax_qty` int(11) NOT NULL,
  `discount_type` enum('fixed_amount','percentage') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_invoice_id_unique` (`invoice_id`),
  KEY `bookings_user_id_foreign` (`user_id`),
  KEY `bookings_customer_id_foreign` (`customer_id`),
  KEY `bookings_office_id_foreign` (`office_id`),
  KEY `bookings_district_id_foreign` (`district_id`),
  KEY `bookings_package_id_foreign` (`package_id`),
  KEY `bookings_fingerprint_charge_id_foreign` (`fingerprint_charge_id`),
  KEY `bookings_branch_id_foreign` (`branch_id`),
  KEY `bookings_date_gap_id_foreign` (`date_gap_id`),
  CONSTRAINT `bookings_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `bookings_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `bookings_date_gap_id_foreign` FOREIGN KEY (`date_gap_id`) REFERENCES `flight_date_gaps` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `bookings_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `bookings_fingerprint_charge_id_foreign` FOREIGN KEY (`fingerprint_charge_id`) REFERENCES `fingerprint_charges` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `bookings_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `bookings_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `bookings_pax_qty_check` CHECK (`pax_qty` >= 1),
  CONSTRAINT `bookings_discount_value_check` CHECK (`discount_value` >= 0),
  CONSTRAINT `bookings_discount_amount_check` CHECK (`discount_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `contacts` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `city_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `city_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `city_name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `city_codes_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commission_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `commission_agents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visa_agent_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `contacts` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commission_agents_visa_agent_id_foreign` (`visa_agent_id`),
  CONSTRAINT `commission_agents_visa_agent_id_foreign` FOREIGN KEY (`visa_agent_id`) REFERENCES `visa_agents` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `rate` decimal(10,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_rates_user_id_foreign` (`user_id`),
  CONSTRAINT `currency_rates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `currency_rates_rate_check` CHECK (`rate` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `iqama_type` enum('self','referral') NOT NULL,
  `passport_no` varchar(255) NOT NULL,
  `iqama_no` varchar(255) NOT NULL,
  `mobile_no` varchar(255) NOT NULL,
  `ref_iqama_no` varchar(255) DEFAULT NULL,
  `ref_mobile_no` varchar(255) DEFAULT NULL,
  `ref_iqama_doc` varchar(512) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_iqama_no_unique` (`iqama_no`),
  UNIQUE KEY `customers_passport_no_unique` (`passport_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `districts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `districts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `division` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_type` enum('customer','passenger') NOT NULL,
  `owner_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fingerprint_charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fingerprint_charges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `district_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `fingerprint_charge` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fingerprint_charges_district_id_unique` (`district_id`),
  KEY `fingerprint_charges_user_id_foreign` (`user_id`),
  CONSTRAINT `fingerprint_charges_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fingerprint_charges_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fingerprint_charges_fingerprint_charge_check` CHECK (`fingerprint_charge` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flight_date_gaps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `flight_date_gaps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gap` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flight_date_gap_gap_unique` (`gap`),
  CONSTRAINT `flight_date_gaps_gap_check` CHECK (`gap` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_fare_id` bigint(20) unsigned NOT NULL,
  `inbound_date` date DEFAULT NULL,
  `outbound_date` date DEFAULT NULL,
  `pnr` varchar(255) NOT NULL,
  `ticket_qty` int(11) NOT NULL,
  `is_refundable` tinyint(1) NOT NULL,
  `is_exchangable` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_tickets_ticket_fare_id_foreign` (`ticket_fare_id`),
  CONSTRAINT `group_tickets_ticket_fare_id_foreign` FOREIGN KEY (`ticket_fare_id`) REFERENCES `ticket_fares` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `group_tickets_ticket_qty_check` CHECK (`ticket_qty` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoices_booking_id_foreign` (`booking_id`),
  KEY `invoices_branch_id_foreign` (`branch_id`),
  KEY `invoices_user_id_foreign` (`user_id`),
  CONSTRAINT `invoices_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `invoices_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `offices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `contacts` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_name` varchar(255) NOT NULL,
  `ticket_fare_id` bigint(20) unsigned NOT NULL,
  `visa_selling_price_id` bigint(20) unsigned NOT NULL,
  `regular_price` decimal(10,2) NOT NULL,
  `offer_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `packages_ticket_fare_id_unique` (`ticket_fare_id`),
  KEY `packages_visa_selling_price_id_foreign` (`visa_selling_price_id`),
  CONSTRAINT `packages_ticket_fare_id_foreign` FOREIGN KEY (`ticket_fare_id`) REFERENCES `ticket_fares` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `packages_visa_selling_price_id_foreign` FOREIGN KEY (`visa_selling_price_id`) REFERENCES `visa_selling_prices` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `packages_regular_price_check` CHECK (`regular_price` >= 0),
  CONSTRAINT `packages_offer_price_check` CHECK (`offer_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `passenger_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `passenger_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passenger_statuses_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `passengers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `passengers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `passenger_status_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `passport_no` varchar(255) NOT NULL,
  `mobile_no` varchar(255) NOT NULL,
  `date_of_birth` date NOT NULL,
  `passenger_type` enum('adult','child','infant') NOT NULL,
  `passport_expiry` date NOT NULL,
  `stay_duration` int(11) NOT NULL,
  `service_required` enum('all','visa_only','ticket_only') NOT NULL,
  `flight_date_from` date NOT NULL,
  `flight_date_to` date NOT NULL,
  `actual_flight_date` date DEFAULT NULL,
  `ticket_status` enum('pending','issued','re-issued','refunded') NOT NULL,
  `visa_status` enum('pending','submitted','issued') NOT NULL,
  `address` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `passengers_booking_id_foreign` (`booking_id`),
  KEY `passengers_passenger_status_id_foreign` (`passenger_status_id`),
  CONSTRAINT `passengers_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `passengers_passenger_status_id_foreign` FOREIGN KEY (`passenger_status_id`) REFERENCES `passenger_statuses` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `passengers_stay_duration_check` CHECK (`stay_duration` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `currency_rate_id` bigint(20) unsigned DEFAULT NULL,
  `bank_id` bigint(20) unsigned DEFAULT NULL,
  `ticket_agent_id` bigint(20) unsigned DEFAULT NULL,
  `visa_agent_id` bigint(20) unsigned DEFAULT NULL,
  `commission_agent_id` bigint(20) unsigned DEFAULT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','bank') NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `bdt_amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_booking_id_foreign` (`booking_id`),
  KEY `payments_branch_id_foreign` (`branch_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  KEY `payments_currency_rate_id_foreign` (`currency_rate_id`),
  KEY `payments_bank_id_foreign` (`bank_id`),
  KEY `payments_ticket_agent_id_foreign` (`ticket_agent_id`),
  KEY `payments_visa_agent_id_foreign` (`visa_agent_id`),
  KEY `payments_commission_agent_id_foreign` (`commission_agent_id`),
  CONSTRAINT `payments_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payments_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payments_commission_agent_id_foreign` FOREIGN KEY (`commission_agent_id`) REFERENCES `commission_agents` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payments_currency_rate_id_foreign` FOREIGN KEY (`currency_rate_id`) REFERENCES `currency_rates` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payments_ticket_agent_id_foreign` FOREIGN KEY (`ticket_agent_id`) REFERENCES `ticket_agents` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payments_visa_agent_id_foreign` FOREIGN KEY (`visa_agent_id`) REFERENCES `visa_agents` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payments_amount_check` CHECK (`amount` >= 0),
  CONSTRAINT `payments_bdt_amount_check` CHECK (`bdt_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `route_multi_segments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `route_multi_segments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `route_id` bigint(20) unsigned NOT NULL,
  `from_city_id` bigint(20) unsigned NOT NULL,
  `to_city_id` bigint(20) unsigned NOT NULL,
  `segment_direction` enum('inbound','outbound') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `route_multi_segments_route_id_foreign` (`route_id`),
  KEY `route_multi_segments_from_city_id_foreign` (`from_city_id`),
  KEY `route_multi_segments_to_city_id_foreign` (`to_city_id`),
  CONSTRAINT `route_multi_segments_from_city_id_foreign` FOREIGN KEY (`from_city_id`) REFERENCES `city_codes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `route_multi_segments_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `route_multi_segments_to_city_id_foreign` FOREIGN KEY (`to_city_id`) REFERENCES `city_codes` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `route_transits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `route_transits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `route_id` bigint(20) unsigned NOT NULL,
  `transit_city_id` bigint(20) unsigned NOT NULL,
  `transit_time` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `route_transits_route_id_foreign` (`route_id`),
  KEY `route_transits_transit_city_id_foreign` (`transit_city_id`),
  CONSTRAINT `route_transits_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `route_transits_transit_city_id_foreign` FOREIGN KEY (`transit_city_id`) REFERENCES `city_codes` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `airline_id` bigint(20) unsigned NOT NULL,
  `route_type` enum('oneway_inbound','oneway_outbound','round','multi_city') NOT NULL,
  `flight_type` enum('direct','transit') NOT NULL,
  `from_city_id` bigint(20) unsigned DEFAULT NULL,
  `to_city_id` bigint(20) unsigned DEFAULT NULL,
  `return_city_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `routes_airline_id_foreign` (`airline_id`),
  KEY `routes_from_city_id_foreign` (`from_city_id`),
  KEY `routes_to_city_id_foreign` (`to_city_id`),
  KEY `routes_return_city_id_foreign` (`return_city_id`),
  CONSTRAINT `routes_airline_id_foreign` FOREIGN KEY (`airline_id`) REFERENCES `airlines` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `routes_from_city_id_foreign` FOREIGN KEY (`from_city_id`) REFERENCES `city_codes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `routes_return_city_id_foreign` FOREIGN KEY (`return_city_id`) REFERENCES `city_codes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `routes_to_city_id_foreign` FOREIGN KEY (`to_city_id`) REFERENCES `city_codes` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_agents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `contacts` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_fares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_fares` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `airline_id` bigint(20) unsigned NOT NULL,
  `airline_classes_id` bigint(20) unsigned NOT NULL,
  `route_id` bigint(20) unsigned NOT NULL,
  `ticket_type` enum('regular','offer','group') NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date NOT NULL,
  `net_fare` decimal(10,2) NOT NULL,
  `selling_fare` decimal(10,2) NOT NULL,
  `offer_price` decimal(10,2) DEFAULT NULL,
  `child_fare_percentage` decimal(5,2) NOT NULL,
  `infant_fare_percentage` decimal(5,2) NOT NULL,
  `with_meal` tinyint(1) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_fares_airline_id_foreign` (`airline_id`),
  KEY `ticket_fares_airline_classes_id_foreign` (`airline_classes_id`),
  KEY `ticket_fares_user_id_foreign` (`user_id`),
  KEY `ticket_fares_route_id_index` (`route_id`),
  CONSTRAINT `ticket_fares_airline_classes_id_foreign` FOREIGN KEY (`airline_classes_id`) REFERENCES `airline_classes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `ticket_fares_airline_id_foreign` FOREIGN KEY (`airline_id`) REFERENCES `airlines` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `ticket_fares_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `ticket_fares_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `ticket_fares_net_fare_check` CHECK (`net_fare` >= 0),
  CONSTRAINT `ticket_fares_selling_fare_check` CHECK (`selling_fare` >= 0),
  CONSTRAINT `ticket_fares_offer_price_check` CHECK (`offer_price` >= 0),
  CONSTRAINT `ticket_fares_child_fare_percentage_check` CHECK (`child_fare_percentage` >= 0),
  CONSTRAINT `ticket_fares_infant_fare_percentage_check` CHECK (`infant_fare_percentage` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_type` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('debit','credit') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_type_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `office_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_branch_id_foreign` (`branch_id`),
  KEY `users_office_id_foreign` (`office_id`),
  CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `users_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `visa_agent_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `visa_agent_costs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visa_agent_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `visa_agent_cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `visa_agent_costs_visa_agent_id_unique` (`visa_agent_id`),
  KEY `visa_agent_costs_user_id_foreign` (`user_id`),
  CONSTRAINT `visa_agent_costs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `visa_agent_costs_visa_agent_id_foreign` FOREIGN KEY (`visa_agent_id`) REFERENCES `visa_agents` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `visa_agent_costs_visa_agent_cost_check` CHECK (`visa_agent_cost` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `visa_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `visa_agents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `contacts` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `visa_selling_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `visa_selling_prices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `selling_price` decimal(10,2) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `visa_selling_prices_user_id_foreign` (`user_id`),
  CONSTRAINT `visa_selling_prices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `visa_selling_prices_selling_price_check` CHECK (`selling_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_id` varchar(255) NOT NULL,
  `booking_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `currency_rate_id` bigint(20) unsigned DEFAULT NULL,
  `bank_id` bigint(20) unsigned DEFAULT NULL,
  `ticket_agent_id` bigint(20) unsigned DEFAULT NULL,
  `visa_agent_id` bigint(20) unsigned DEFAULT NULL,
  `commission_agent_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_type_id` bigint(20) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','bank') NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `bdt_amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_voucher_id_unique` (`voucher_id`),
  KEY `vouchers_booking_id_foreign` (`booking_id`),
  KEY `vouchers_payment_id_foreign` (`payment_id`),
  KEY `vouchers_branch_id_foreign` (`branch_id`),
  KEY `vouchers_user_id_foreign` (`user_id`),
  KEY `vouchers_currency_rate_id_foreign` (`currency_rate_id`),
  KEY `vouchers_bank_id_foreign` (`bank_id`),
  KEY `vouchers_ticket_agent_id_foreign` (`ticket_agent_id`),
  KEY `vouchers_visa_agent_id_foreign` (`visa_agent_id`),
  KEY `vouchers_commission_agent_id_foreign` (`commission_agent_id`),
  KEY `vouchers_transaction_type_id_foreign` (`transaction_type_id`),
  CONSTRAINT `vouchers_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_commission_agent_id_foreign` FOREIGN KEY (`commission_agent_id`) REFERENCES `commission_agents` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_currency_rate_id_foreign` FOREIGN KEY (`currency_rate_id`) REFERENCES `currency_rates` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_ticket_agent_id_foreign` FOREIGN KEY (`ticket_agent_id`) REFERENCES `ticket_agents` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_transaction_type_id_foreign` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_type` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_visa_agent_id_foreign` FOREIGN KEY (`visa_agent_id`) REFERENCES `visa_agents` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `vouchers_amount_check` CHECK (`amount` >= 0),
  CONSTRAINT `vouchers_bdt_amount_check` CHECK (`bdt_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_05_03_104336_create_branches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_05_03_104346_create_banks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_05_03_104346_create_districts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_05_03_104346_create_offices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_05_03_104347_create_airlines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_05_03_104347_create_city_codes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_05_03_104347_create_classes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_05_03_104400_create_airline_cities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_05_03_104400_create_airline_classes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_05_03_105853_add_branch_id_and_office_id_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_05_04_145914_create_visa_agents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_05_04_145935_create_ticket_agents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_05_04_145947_create_customers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_05_04_145959_create_commission_agents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_05_04_150011_create_fingerprint_charges_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_05_04_150023_create_visa_agent_costs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_05_04_150037_create_visa_selling_prices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_05_05_000001_create_routes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_05_05_000002_create_route_multi_segments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_05_05_000003_create_route_transits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_05_06_000001_create_ticket_fares_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_05_06_000002_create_group_tickets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_05_06_000003_create_baggage_allowances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_05_07_000001_create_flight_date_gap_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_05_07_000002_create_packages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_05_08_000001_create_passenger_statuses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_05_08_000002_create_bookings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_05_08_000003_create_passengers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_05_08_000004_create_documents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_05_09_000001_create_currency_rates_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_05_09_000002_create_transaction_type_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_05_09_000003_create_invoices_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_05_09_000004_create_payments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_05_09_000005_create_vouchers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_05_10_000001_drop_unique_route_id_from_ticket_fares_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_05_12_000001_make_group_ticket_dates_nullable',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_05_07_083510_rename_flight_date_gap_to_flight_date_gaps_table',5);
