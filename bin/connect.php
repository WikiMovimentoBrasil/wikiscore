<?php

//Define credenciais do banco de dados
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db_user = $ts_mycnf['user'];
$db_pass = $ts_mycnf['password'];
$db_host = 'tools.db.svc.eqiad.wmflabs';
$database = $ts_mycnf['user']."__wikiconcursos";

//Conecta ao servidor
mysqli_report(MYSQLI_REPORT_ERROR);
$con = mysqli_connect($db_host, $db_user, $db_pass);
if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit();
}

//Conecta ao banco de dados
if (!@mysqli_select_db($con, $database)) {
    $sql = "CREATE DATABASE $database";
    if (mysqli_query($con, $sql) === TRUE) {
        mysqli_select_db($con, $database);
    } else {
        echo "Error creating database.";
        die();
    }
} 

//Cria tabelas caso não existam
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS `manage__contests` (
        `key` smallint(6) NOT NULL AUTO_INCREMENT,
        `name_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
        `start_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
        `end_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
        `name` tinytext COLLATE utf8mb4_unicode_ci NOT NULL,
        `group` tinytext COLLATE utf8mb4_unicode_ci NOT NULL,
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
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS `manage__credentials` (
        `user_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_name` varchar(255) NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `user_password` varchar(128) NOT NULL,
        `user_status` varchar(1) NOT NULL DEFAULT 'P',
        `user_group` tinytext NOT NULL COLLATE utf8_general_ci,
        PRIMARY KEY (`user_id`) USING BTREE,
        UNIQUE KEY `user_email` (`user_email`) USING BTREE,
        KEY `user_name` (`user_name`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
");