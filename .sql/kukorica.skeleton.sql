-- phpMyAdmin SQL Dump
-- version 3.5.4
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Oct 04, 2016 at 03:44 PM
-- Server version: 5.5.47
-- PHP Version: 5.6.24

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `kukorica`
--

-- --------------------------------------------------------

--
-- Table structure for table `movies`
--

CREATE TABLE IF NOT EXISTS `movies` (
  `imdb_id` int(11) unsigned NOT NULL DEFAULT '0',
  `url` varchar(2048) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `title_long` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `slug` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `year` smallint(4) unsigned NOT NULL DEFAULT '0',
  `rating` float(4,2) unsigned NOT NULL DEFAULT '0.00',
  `runtime` int(10) unsigned NOT NULL DEFAULT '0',
  `genres` text COLLATE utf8_unicode_ci NOT NULL,
  `cast` text COLLATE utf8_unicode_ci NOT NULL,
  `directors` text COLLATE utf8_unicode_ci NOT NULL,
  `language` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `mpa_rating` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'N/A',
  `synopsis` text COLLATE utf8_unicode_ci NOT NULL,
  `yt_trailer_code` varchar(11) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `google_video` varchar(2048) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `background_image` text COLLATE utf8_unicode_ci NOT NULL,
  `small_cover_image` text COLLATE utf8_unicode_ci NOT NULL,
  `medium_cover_image` text COLLATE utf8_unicode_ci NOT NULL,
  `large_cover_image` text COLLATE utf8_unicode_ci NOT NULL,
  `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `date_uploaded` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `date_uploaded_unix` int(10) unsigned NOT NULL DEFAULT '0',
  `locked` tinyint(4) NOT NULL DEFAULT '0',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`imdb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `torrents`
--

CREATE TABLE IF NOT EXISTS `torrents` (
  `site_id` tinyint(3) unsigned NOT NULL,
  `torrent_id` int(10) unsigned NOT NULL,
  `imdb_id` int(10) unsigned NOT NULL DEFAULT '0',
  `url` varchar(2048) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `hash` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `quality` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `seeds` int(10) unsigned NOT NULL DEFAULT '0',
  `peers` int(10) unsigned NOT NULL DEFAULT '0',
  `size` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  `date_uploaded` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `date_uploaded_unix` int(10) unsigned NOT NULL DEFAULT '0',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`site_id`,`torrent_id`),
  KEY `imdb_id` (`imdb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
