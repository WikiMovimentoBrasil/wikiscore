-- --------------------------------------------------------
-- Servidor:                     tools.db.svc.wikimedia.cloud
-- Versão do servidor:           10.1.39-MariaDB - MariaDB Server
-- OS do Servidor:               Linux
-- HeidiSQL Versão:              12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `manage__contests` (
  `key` smallint(6) NOT NULL AUTO_INCREMENT,
  `name_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `start_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `end_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `name` tinytext COLLATE utf8mb4_unicode_ci NOT NULL,
  `revert_time` tinyint(4) NOT NULL DEFAULT '24',
  `official_list_pageid` int(11) NOT NULL,
  `category_pageid` int(11) DEFAULT NULL,
  `category_petscan` int(11) DEFAULT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_endpoint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `outreach_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `bytes_per_points` mediumint(9) NOT NULL DEFAULT '3000',
  `max_bytes_per_article` mediumint(9) NOT NULL DEFAULT '90000',
  `minimum_bytes` mediumint(9) DEFAULT NULL,
  `pictures_per_points` tinyint(4) NOT NULL DEFAULT '5',
  `pictures_mode` tinyint(4) NOT NULL DEFAULT '0',
  `max_pic_per_article` tinyint(4) DEFAULT NULL,
  `theme` tinytext COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` tinytext COLLATE utf8mb4_unicode_ci,
  `started_update` timestamp NULL DEFAULT NULL,
  `finished_update` timestamp NULL DEFAULT NULL,
  `next_update` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`),
  UNIQUE KEY `name_id` (`name_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manage__credentials` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_password` varchar(128) NOT NULL,
  `user_status` varchar(1) NOT NULL DEFAULT 'P',
  PRIMARY KEY (`user_id`) USING BTREE,
  UNIQUE KEY `user_email` (`user_email`) USING BTREE,
  KEY `user_name` (`user_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
