CREATE DATABASE IF NOT EXISTS `DatabaseName`;
USE `DatabaseName`;

CREATE TABLE IF NOT EXISTS `articles` (
  `key` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `articleID` mediumint(9) unsigned NOT NULL,
  PRIMARY KEY (`key`),
  UNIQUE KEY `articleID` (`articleID`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `credencials` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_password` varchar(128) NOT NULL,
  `user_status` varchar(1) NOT NULL DEFAULT 'P',
  `user_data` text,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_email` (`user_email`),
  KEY `user_name` (`user_name`),
  KEY `user_status` (`user_status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `edits` (
  `n` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `diff` int(9) unsigned NOT NULL,
  `article` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `timestamp` timestamp NULL DEFAULT NULL,
  `user` tinytext COLLATE utf8mb4_unicode_ci,
  `bytes` int(11) DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `new_page` tinyint(1) unsigned DEFAULT NULL,
  `valid_edit` tinyint(1) unsigned DEFAULT NULL,
  `valid_user` tinyint(1) unsigned DEFAULT NULL,
  `pictures` tinyint(1) unsigned DEFAULT NULL,
  `reverted` tinyint(1) unsigned DEFAULT NULL,
  `by` tinytext COLLATE utf8mb4_unicode_ci,
  `when` timestamp NULL DEFAULT NULL,
  `obs` text NULL DEFAULT NULL,
  PRIMARY KEY (`n`),
  UNIQUE KEY `diff` (`diff`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `n` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `user` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `timestamp` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`n`),
  UNIQUE KEY `user` (`user`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
