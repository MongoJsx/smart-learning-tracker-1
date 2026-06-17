-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 12, 2026 at 08:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_651998018`
--

CREATE DATABASE IF NOT EXISTS `db_651998018` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_651998018`;

SET FOREIGN_KEY_CHECKS=0;


-- --------------------------------------------------------

--
-- Table structure for table `audio_summaries`
--

DROP TABLE IF EXISTS `audio_summaries`;

CREATE TABLE `audio_summaries` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `study_log_id` int(11) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `transcript` longtext DEFAULT NULL,
  `summary` longtext DEFAULT NULL,
  `ai_model` varchar(100) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_summaries_v2`
--

DROP TABLE IF EXISTS `audio_summaries_v2`;

CREATE TABLE `audio_summaries_v2` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `summary_type` varchar(255) NOT NULL DEFAULT 'medium',
  `prompt` text DEFAULT NULL,
  `summary_text` longtext NOT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `prompt_tokens` int(11) DEFAULT NULL,
  `completion_tokens` int(11) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'completed',
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_transcription_jobs`
--

DROP TABLE IF EXISTS `audio_transcription_jobs`;

CREATE TABLE `audio_transcription_jobs` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `file_id` int(11) NOT NULL,
  `study_log_id` int(11) DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `language` varchar(32) DEFAULT NULL,
  `diarization` int(11) NOT NULL DEFAULT 0,
  `speaker_count` int(11) DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_transcription_segments`
--

DROP TABLE IF EXISTS `audio_transcription_segments`;

CREATE TABLE `audio_transcription_segments` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `seq` int(11) NOT NULL,
  `start_ms` int(11) DEFAULT NULL,
  `end_ms` int(11) DEFAULT NULL,
  `speaker` varchar(50) DEFAULT NULL,
  `text` text NOT NULL,
  `confidence` int(11) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendar_notes`
--

DROP TABLE IF EXISTS `calendar_notes`;

CREATE TABLE `calendar_notes` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `note_date` datetime NOT NULL,
  `note_time` datetime DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `career_paths`
--

DROP TABLE IF EXISTS `career_paths`;

CREATE TABLE `career_paths` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `career_recommendations`
--

DROP TABLE IF EXISTS `career_recommendations`;

CREATE TABLE `career_recommendations` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `career_path_id` int(11) NOT NULL,
  `score` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_history`
--

DROP TABLE IF EXISTS `chat_history`;

CREATE TABLE `chat_history` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `room_id` varchar(50) DEFAULT 'default',
  `sender_type` varchar(20) NOT NULL,
  `message` text DEFAULT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_history`
--

INSERT INTO `chat_history` (`id`, `user_id`, `room_id`, `sender_type`, `message`, `attachment_url`, `is_deleted`, `created_at`) VALUES
(1, '1', 'default', 'user', 'สวัสดี', NULL, 1, '2026-05-04 05:14:48'),
(2, '1', 'default', 'assistant', 'รับทราบค่ะ ตอนนี้ระบบ AI ภายนอกมีปัญหาชั่วคราว แต่ฉันยังช่วยวางแผนการเรียน สรุปหัวข้อ และจัดแนวฝึกให้ได้ พิมพ์สิ่งที่อยากให้ช่วยต่อได้เลยค่ะ', NULL, 1, '2026-05-04 05:15:04'),
(3, '1', 'default', 'user', 'สวัสดีจ้า', NULL, 1, '2026-05-04 05:16:53'),
(4, '1', 'default', 'assistant', 'สวัสดีค่ะ วันนี้มีวันและเวลากำหนดไว้ว่า 2026-05-04 12:16:53 ค่ะ จะมีอะไรที่ฉันสามารถช่วยคุณได้บ้างคะ ทั้งเรื่องการวางแผนการเรียนหรืออื่นๆ ค่ะ', NULL, 1, '2026-05-04 05:17:00'),
(5, '1', 'default', 'user', 'วันจันทร์เรียนอะไรบ้าง', NULL, 1, '2026-05-04 05:18:12'),
(6, '1', 'default', 'assistant', 'วันจันทร์ คุณมีวิชา pro1 เรียนในช่วงเวลา 15:00-16:00 น. ห้อง 102 ค่ะ', NULL, 1, '2026-05-04 05:18:13'),
(7, '1', 'default', 'user', 'วันจันทร์เรียนอะไรบ้าง', NULL, 1, '2026-05-04 05:33:42'),
(8, '1', 'default', 'assistant', 'วันจันทร์นี้ คุณ เกศสุดา มีวิชา pro1 เวลา 15:00-16:00 โดยเรียนที่ห้อง 102 ค่ะ', NULL, 1, '2026-05-04 05:33:44'),
(9, '1', 'default', 'user', 'สวัสดี', NULL, 1, '2026-05-04 05:37:17'),
(10, '1', 'default', 'assistant', 'สวัสดีค่ะ มีอะไรที่ฉันสามารถช่วยคุณ เกศสุดา ได้บ้างค่ะ วันนี้คุณมีวิชา pro1 ในเวลา 08:00-09:00 นะคะ ต้องการความช่วยเหลือเกี่ยวกับเรียนหรืออะไรอื่นไหมค่ะ', NULL, 1, '2026-05-04 05:37:19'),
(11, '1', 'default', 'user', 'เพิ่มวิชาเรียนชื่อว่าโปรเจคid วันอังคาร เวลา 10โมงถึง12.00น ให้หน่อย', NULL, 1, '2026-05-04 05:38:11'),
(12, '1', 'default', 'assistant', 'ได้บันทึกเรียนสำหรับคุณ เกศสุดา แล้วค่ะ\n\nวันอาทิตย์: ไม่มีวิชา\nวันจันทร์: pro1 • 08:00-09:00 • ห้อง 102\nวันอังคาร: โปรเจคid • 10:00-12:00\nวันพุธ: ไม่มีวิชา\nวันพฤหัสบดี: ไม่มีวิชา\nวันศุกร์: ไม่มีวิชา\nวันเสาร์: ไม่มีวิชา\n\nหากต้องการเพิ่มวิชาหรือแก้ไขเวลาวิชา คุณสามารถบอกฉันได้ตลอดเวลาเลยค่ะ', NULL, 1, '2026-05-04 05:38:14'),
(13, '1', 'default', 'user', 'เพิ่มวิชาเรียนวันอังคาร ชื่อวิชา โปรเจกid___ เวลาเรียนเริ่ม 10โมง ถึง 12.00น ห้องเรียน 1032', NULL, 1, '2026-05-04 05:42:22'),
(14, '1', 'default', 'assistant', 'ได้ค่ะ หากต้องการเพิ่มวิชาผ่านแชต กรุณาพิมพ์รูปแบบนี้: เพิ่มวิชา [ชื่อวิชา] วัน[วันเรียน] [เวลาเริ่ม]-[เวลาเลิก] ห้อง [ห้องเรียน] เช่น เพิ่มวิชา คณิต วันจันทร์ 09:00-12:00 ห้อง 102 ค่ะ', NULL, 1, '2026-05-04 05:42:22'),
(15, '1', 'default', 'user', 'เพิ่มวิชาเรียน', NULL, 1, '2026-05-04 05:45:50'),
(16, '1', 'default', 'assistant', 'ได้ค่ะ หากต้องการเพิ่มวิชาผ่านแชต กรุณาพิมพ์รูปแบบนี้: เพิ่มวิชา [ชื่อวิชา] วัน[วันเรียน] [เวลาเริ่ม]-[เวลาเลิก] ห้อง [ห้องเรียน] เช่น เพิ่มวิชา คณิต วันจันทร์ 09:00-12:00 ห้อง 102 ค่ะ', NULL, 1, '2026-05-04 05:45:50'),
(17, '1', 'default', 'user', 'เพิ่มวิชาเรียนวันเสาร์วิชา สังคม เวลา 10-15.00', NULL, 1, '2026-05-04 05:46:15'),
(18, '1', 'default', 'assistant', 'ได้ค่ะ หากต้องการเพิ่มวิชาผ่านแชต กรุณาพิมพ์รูปแบบนี้: เพิ่มวิชา [ชื่อวิชา] วัน[วันเรียน] [เวลาเริ่ม]-[เวลาเลิก] ห้อง [ห้องเรียน] เช่น เพิ่มวิชา คณิต วันจันทร์ 09:00-12:00 ห้อง 102 ค่ะ', NULL, 1, '2026-05-04 05:46:15'),
(19, '1', 'default', 'user', 'เพิ่มวิชาเรียนวันอังคาร ชื่อวิชา โปรเจกid___ เวลาเรียนเริ่ม 10โมง ถึง 12.00น ห้องเรียน 10322', NULL, 1, '2026-05-04 05:50:16'),
(20, '1', 'default', 'assistant', 'ได้ค่ะ หากต้องการเพิ่มวิชาผ่านแชต กรุณาพิมพ์รูปแบบนี้: เพิ่มวิชา [ชื่อวิชา] วัน[วันเรียน] [เวลาเริ่ม]-[เวลาเลิก] ห้อง [ห้องเรียน] เช่น เพิ่มวิชา คณิต วันจันทร์ 09:00-12:00 ห้อง 102 ค่ะ', NULL, 1, '2026-05-04 05:50:16'),
(21, '1', 'default', 'user', 'เพิ่มวิชาเรียนวันอังคาร ชื่อวิชา โปรเจกid___ เวลาเรียนเริ่ม 10โมง ถึง 12.00น ห้องเรียน 10322', NULL, 1, '2026-05-04 05:52:16'),
(22, '1', 'default', 'assistant', 'เพิ่มวิชา โปรเจกid___ เรียบร้อยแล้วค่ะ\nตารางเรียน: วันอังคาร 10:00:00-12:00:00\nและบันทึกลงปฏิทินเรียนเรียบร้อยแล้วค่ะ', NULL, 1, '2026-05-04 05:52:16'),
(23, '1', 'default', 'user', 'วันพรุ่งนี้มีการแจ้งเตือนไรบ้าง', NULL, 1, '2026-05-04 05:55:53'),
(24, '1', 'default', 'assistant', 'วันพรุ่งนี้ คือ วันอังคาร ค่ะ มีการแจ้งเตือนดังนี้\n\n* วิชา โปรเจกid___ เวลา 10:00-12:00 น. ห้อง 1032 ค่ะ\n\nหากคุณต้องการทราบข้อมูลเพิ่มเติมหรือต้องการบันทึกเรียนอื่นๆ สามารถบอกฉันได้ตลอดเวลาเลยค่ะ', NULL, 1, '2026-05-04 05:55:54'),
(25, '1', 'default', 'user', 'เพิ่มวิชาเรียนวันพรุ่งนี้ วิชา รักการอ่าน เวลาเริ่ม 10-15', NULL, 1, '2026-05-04 06:46:01'),
(26, '1', 'default', 'assistant', 'ได้ค่ะ หากต้องการเพิ่มวิชาผ่านแชต กรุณาพิมพ์รูปแบบนี้: เพิ่มวิชา [ชื่อวิชา] วัน[วันเรียน] [เวลาเริ่ม]-[เวลาเลิก] ห้อง [ห้องเรียน] เช่น เพิ่มวิชา คณิต วันจันทร์ 09:00-12:00 ห้อง 102 ค่ะ', NULL, 1, '2026-05-04 06:46:01'),
(27, '1', 'default', 'user', 'สวัสดี', NULL, 1, '2026-05-04 06:53:38'),
(28, '1', 'default', 'assistant', 'สวัสดีค่ะ หวังว่าคุณจะมีวันเรียนที่ดีและมีประสิทธิภาพนะคะ หากคุณต้องการความช่วยเหลือใดๆ ไม่ว่าจะเป็นเรื่องตารางเรียน บันทึกเรียน หรือต้องการคำแนะนำใดๆ ฉันยินดีที่จะช่วยเหลือคุณตลอด 24 ชั่วโมงค่ะ', NULL, 1, '2026-05-04 06:53:40'),
(29, '1', 'room-1777974719791', 'user', 'ตอนนี้เวลากี่โมง', NULL, 0, '2026-05-05 09:52:10'),
(30, '1', 'room-1777974719791', 'assistant', 'ปัจจุบันเวลาคือ 16.52 น. ค่ะ', NULL, 0, '2026-05-05 09:52:14');

-- --------------------------------------------------------

--
-- Table structure for table `email_digests`
--

DROP TABLE IF EXISTS `email_digests`;

CREATE TABLE `email_digests` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `email_setting_id` int(11) DEFAULT NULL,
  `provider_account_id` int(11) DEFAULT NULL,
  `digest_type` varchar(255) NOT NULL DEFAULT 'daily',
  `date_from` datetime NOT NULL,
  `date_to` datetime NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Bangkok',
  `send_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00',
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` longtext DEFAULT NULL,
  `body_text` longtext DEFAULT NULL,
  `provider` varchar(255) NOT NULL DEFAULT 'gmail_api',
  `status` varchar(255) NOT NULL DEFAULT 'queued',
  `sent_at` datetime DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `provider_message_id` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_digest_items`
--

DROP TABLE IF EXISTS `email_digest_items`;

CREATE TABLE `email_digest_items` (
  `id` int(11) NOT NULL,
  `digest_id` int(11) NOT NULL,
  `calendar_event_id` int(11) DEFAULT NULL,
  `item_date` datetime NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `study_log_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_provider_accounts`
--

DROP TABLE IF EXISTS `email_provider_accounts`;

CREATE TABLE `email_provider_accounts` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(255) NOT NULL DEFAULT 'gmail',
  `auth_type` varchar(255) NOT NULL DEFAULT 'oauth',
  `provider_email` varchar(255) NOT NULL,
  `access_token` varchar(255) DEFAULT NULL,
  `refresh_token` varchar(255) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `scopes` varchar(255) DEFAULT NULL,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `last_error` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;

CREATE TABLE `failed_jobs` (
  `id` int(11) NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `study_log_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('pdf','word','audio','image','other') NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `study_log_id`, `original_name`, `file_path`, `file_type`, `mime_type`, `file_size`, `created_at`, `updated_at`) VALUES
(1, 1, 'รายงานผลการปฏิบัติงา1.docx', 'study-files/1/kJUY6zl45arsETPEbIORHhujdfVpvNFsGqUtFEyz.docx', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 115512, '2026-06-12 05:45:13', '2026-06-12 05:45:13'),
(2, 2, 'หัวข้อพิเศษของฝึกงาน2เดือน.docx', 'study-files/1/xyZEQtYrf6p8M4CID46lvF07gz7LfGxImCW8UZ8Y.docx', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 10981530, '2026-06-12 05:47:31', '2026-06-12 05:47:31'),
(3, 3, 'งานของที่เขาให้ทำตอนปฎิบัติงาน.docx', 'study-files/1/Molp24XQAMcXjLGfa6H02Pqqyd9kOL9DCWD6PFSR.docx', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 2400239, '2026-06-12 05:47:58', '2026-06-12 05:47:58'),
(4, 4, 'รายงานผลการปฏิบัติงาน.docx', 'study-files/1/uLE8HFk8DgALC0LQSJSpasTjVnXvRzXka0TJIsBW.docx', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 108775, '2026-06-12 05:48:26', '2026-06-12 05:48:26'),
(6, 6, 'เทคโนโลยีปัญญาประดิษฐ์กับการเปลี่ยนแปลงของโลก.pdf', 'study-files/1/yBoJvYq4VHoJHIeYZ09AOUh2f4lF1VX61fL2x0wQ.pdf', 'pdf', 'application/pdf', 1066030, '2026-06-12 05:56:23', '2026-06-12 05:56:23'),
(7, 7, 'งานของที่เขาให้ทำตอนปฎิบัติงาน.docx', 'study-files/1/BCZniOtSArisioO8FkoZJK0qLl7vzPoc7K644Ip2.docx', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 2400239, '2026-06-12 06:11:06', '2026-06-12 06:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `learning_goal_targets`
--

DROP TABLE IF EXISTS `learning_goal_targets`;

CREATE TABLE `learning_goal_targets` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `period_type` enum('daily','weekly','monthly') NOT NULL,
  `quest_type` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `target_value` int(11) NOT NULL,
  `current_value` int(11) DEFAULT 0,
  `reward_points` int(11) DEFAULT 10,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `learning_moods`
--

DROP TABLE IF EXISTS `learning_moods`;

CREATE TABLE `learning_moods` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `study_log_id` int(11) DEFAULT NULL,
  `detected_source` varchar(255) NOT NULL DEFAULT 'manual',
  `mood_label` varchar(100) NOT NULL,
  `mood_score` int(11) DEFAULT NULL,
  `mood_color` varchar(12) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `learning_notifications`
--

DROP TABLE IF EXISTS `learning_notifications`;

CREATE TABLE `learning_notifications` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `study_log_id` int(11) DEFAULT NULL,
  `calendar_event_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body` varchar(255) DEFAULT NULL,
  `notify_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `channel` varchar(255) NOT NULL DEFAULT 'in_app',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learning_notifications`
--

INSERT INTO `learning_notifications` (`id`, `user_id`, `subject_id`, `study_log_id`, `calendar_event_id`, `title`, `body`, `notify_at`, `delivered_at`, `channel`, `status`, `metadata`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, NULL, 'แจ้งเตือนเรียนวันนี้', 'วันนี้ (ศุกร์) คุณมี 4 รายการ: เรียน: โปรแกรม2 (อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด); เรียน: โปรแกรม2 (อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด); เรียน: โปรแกรม2 (อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด); +1 รายการ', '2026-06-12 00:00:00', NULL, 'in_app', 'pending', '{\"type\":\"today_schedule\",\"is_read\":false}', '2026-06-10 06:04:29', '2026-06-12 06:11:08'),
(2, 1, NULL, NULL, NULL, 'แจ้งเตือนเรียนพรุ่งนี้', 'พรุ่งนี้ (เสาร์) คุณไม่มีตารางเรียน', '2026-06-13 00:00:00', NULL, 'in_app', 'pending', '{\"type\":\"tomorrow_schedule\",\"is_read\":false}', '2026-06-10 06:04:29', '2026-06-12 03:58:32'),
(3, 1, 1, NULL, NULL, 'เก็บถาวรวิชาแล้ว', 'วิชา: รายวัน: หัวข้อพิเศษของฝึกงาน2เดือน', '2026-06-12 05:47:36', NULL, 'in_app', 'sent', '{\"type\":\"subject_archive\",\"is_read\":false}', '2026-06-12 05:47:36', '2026-06-12 05:47:36'),
(4, 1, 1, NULL, NULL, 'เก็บถาวรวิชาแล้ว', 'วิชา: รายวัน: งานของที่เขาให้ทำตอนปฎิบัติงาน', '2026-06-12 05:48:02', NULL, 'in_app', 'sent', '{\"type\":\"subject_archive\",\"is_read\":false}', '2026-06-12 05:48:02', '2026-06-12 05:48:02');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `summary` longtext DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `video_url` varchar(255) DEFAULT NULL,
  `audio_notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_summaries`
--

DROP TABLE IF EXISTS `lesson_summaries`;

CREATE TABLE `lesson_summaries` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `summary_type` varchar(255) NOT NULL DEFAULT 'topic',
  `date_from` datetime DEFAULT NULL,
  `date_to` datetime DEFAULT NULL,
  `source_study_log_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `content` longtext NOT NULL,
  `ai_model` varchar(100) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;

CREATE TABLE `migrations` (
  `id` int(11) NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(2, '2025_09_29_060510_create_personal_access_tokens_table', 1),
(3, '2025_09_29_060520_create_password_reset_tokens_table', 1),
(4, '2025_09_29_060530_create_failed_jobs_table', 1),
(5, '2025_09_29_060536_create_files_table', 1),
(6, '2025_09_29_060536_create_quiz_answers_table', 1),
(7, '2025_09_29_060536_create_quiz_questions_table', 1),
(8, '2025_09_29_060536_create_quiz_table', 1),
(9, '2025_09_29_060536_create_study_logs_table', 1),
(10, '2025_09_29_060536_create_subjects_table', 1),
(11, '2025_09_29_060536_create_summaries_table', 1),
(12, '2025_09_29_060500_create_users_table', 2),
(13, 'YYYY_MM_DD_add_password_to_users_table', 3),
(14, 'YYYY_MM_DD_add_provider_columns_to_users_table', 3),
(15, '2025_11_08_000001_add_missing_auth_columns_to_users_table', 4),
(16, '2024_01_01_000001_create_subjects_table', 5),
(17, '2024_01_01_000002_create_lessons_table', 5),
(18, '2024_01_01_000003_create_schedules_table', 5),
(19, '2024_01_01_000004_create_notifications_table', 5),
(20, '2024_01_01_000005_create_quizzes_table', 5),
(21, '2024_01_01_000006_create_mood_tracking_table', 5),
(22, '2024_01_01_000007_create_study_environments_table', 5),
(23, '2024_01_01_000008_update_quiz_questions_table', 5),
(24, '2024_01_01_000009_create_quiz_answers_table', 5),
(25, '2025_12_19_000001_create_study_calendar_events_table', 5),
(26, '2025_12_19_000002_create_learning_notifications_table', 5),
(27, '2025_12_19_000003_create_notification_email_settings_table', 5),
(28, '2025_12_19_000004_create_notification_email_logs_table', 5),
(29, '2025_12_19_000005_create_learning_goal_targets_table', 5),
(30, '2025_09_29_060537_create_study_logs_table', 6),
(31, '2025_09_29_060538_create_quiz_table', 6),
(32, '2025_09_29_060539_create_quiz_questions_table', 6),
(33, '2025_09_29_060540_create_quiz_answers_table', 6),
(34, '2025_09_29_060541_create_summaries_table', 6),
(35, '2025_09_29_060542_create_files_table', 6),
(36, '2026_01_01_000000_fix_auto_increment_tokens_and_notifications', 6),
(37, '2026_01_01_000001_create_email_provider_accounts_table', 6),
(38, '2026_01_17_000002_add_user_id_to_study_logs_table', 6),
(39, '2026_01_17_000003_add_schedule_time_to_subjects_table', 6),
(40, '2026_01_17_104146_update_study_logs_subject_fk_on_delete_cascade', 6),
(41, '2026_01_18_000001_fix_users_table_for_google_login', 6),
(42, '2026_01_29_000001_create_subjects_archives_table', 6),
(43, '2026_01_29_000002_add_event_type_schedule_type_role_columns', 6),
(44, '2026_02_02_145550_add_google_id_to_users_table', 6),
(45, '2026_02_02_145738_add_google_id_and_role_to_users_table', 6),
(46, '2026_02_03_000001_fix_personal_access_tokens_auto_increment', 6),
(47, '2026_02_03_144612_add_google_fields_to_users', 6),
(48, '2026_02_04_120000_add_semester_id_to_subjects_table', 6),
(49, '2026_02_10_000001_fix_notification_email_logs_auto_increment', 6),
(50, '2026_02_10_000003_fix_missing_auto_increment_ids', 7),
(51, '2026_02_12_000001_create_career_paths_table', 8),
(52, '2026_02_12_000002_create_career_recommendations_table', 8),
(53, '2026_02_12_000003_create_quiz_attempts_table', 9),
(54, '2026_03_22_071500_expand_audio_summary_text_columns', 10),
(55, '2026_04_29_165016_add_image_to_files_file_type_enum', 10),
(56, '2026_04_29_173500_add_log_type_to_study_logs_table', 10),
(57, '2026_05_04_140000_expand_career_recommendations_text_columns', 10);

-- --------------------------------------------------------

--
-- Table structure for table `mood_logs`
--

DROP TABLE IF EXISTS `mood_logs`;

CREATE TABLE `mood_logs` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `mood` varchar(255) NOT NULL,
  `energy_level` int(11) NOT NULL DEFAULT 5,
  `focus_level` int(11) NOT NULL DEFAULT 5,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_email_logs`
--

DROP TABLE IF EXISTS `notification_email_logs`;

CREATE TABLE `notification_email_logs` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `learning_notification_id` int(11) DEFAULT NULL,
  `email_digest_id` int(11) DEFAULT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `provider` varchar(255) NOT NULL DEFAULT 'gmail_smtp',
  `status` varchar(255) NOT NULL DEFAULT 'queued',
  `sent_at` datetime DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `message_id` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_email_settings`
--

DROP TABLE IF EXISTS `notification_email_settings`;

CREATE TABLE `notification_email_settings` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `email_enabled` int(11) NOT NULL DEFAULT 1,
  `email_address` varchar(255) DEFAULT NULL,
  `digest_type` varchar(255) NOT NULL DEFAULT 'daily',
  `days_ahead` int(11) NOT NULL DEFAULT 1,
  `send_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00',
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Bangkok',
  `last_sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_email_settings`
--

INSERT INTO `notification_email_settings` (`id`, `user_id`, `email_enabled`, `email_address`, `digest_type`, `days_ahead`, `send_time`, `timezone`, `last_sent_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '651463052@crru.ac.th', 'daily', 1, '2026-06-10 00:00:00', 'Asia/Bangkok', NULL, '2026-06-10 06:04:29', '2026-06-10 06:04:29');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;

CREATE TABLE `personal_access_tokens` (
  `id` int(11) NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` varchar(255) DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'api', '496cf7396701542fce884ebc2c04fe497cb7397f49bbced9a66e7bf57d366780', '[\"*\"]', '2026-06-12 06:55:27', NULL, '2026-06-10 06:04:28', '2026-06-12 06:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

DROP TABLE IF EXISTS `quizzes`;

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ai_model` varchar(100) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `subject_id`, `title`, `description`, `ai_model`, `metadata`, `created_at`, `updated_at`) VALUES
(1, 1, 'ิแอิ', NULL, 'llama-3.3-70b-versatile', '{\"source\":\"document\",\"difficulty\":\"medium\",\"file_id\":4,\"study_log_id\":4,\"requested_types\":[\"multiple_choice\",\"short_answer\"],\"file_name\":\"รายงานผลการปฏิบัติงาน.docx\"}', '2026-06-12 05:48:26', '2026-06-12 05:48:26'),
(3, 1, 'ทส', 'โปรแกรม2', 'llama-3.3-70b-versatile', '{\"source\":\"document\",\"difficulty\":\"medium\",\"file_id\":6,\"study_log_id\":6,\"requested_types\":[\"multiple_choice\",\"short_answer\"]}', '2026-06-12 05:56:23', '2026-06-12 05:56:23'),
(4, 1, 'ส้เยย', 'โปรแกรม2', 'llama-3.3-70b-versatile', '{\"source\":\"document\",\"difficulty\":\"medium\",\"file_id\":7,\"study_log_id\":7,\"requested_types\":[\"multiple_choice\",\"short_answer\"],\"file_name\":\"งานของที่เขาให้ทำตอนปฎิบัติงาน.docx\"}', '2026-06-12 06:11:06', '2026-06-12 06:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answers`
--

DROP TABLE IF EXISTS `quiz_answers`;

CREATE TABLE `quiz_answers` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `selected_answer` varchar(255) DEFAULT NULL,
  `is_correct` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `answered_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_answers`
--

INSERT INTO `quiz_answers` (`id`, `question_id`, `user_id`, `selected_answer`, `is_correct`, `score`, `metadata`, `answered_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, 0, 0, NULL, NULL, '2026-06-12 05:48:44', '2026-06-12 05:48:44'),
(2, 2, 1, 'การพัฒนาระบบเทคโนโลยีสารสนเทศ', 1, 1, NULL, NULL, '2026-06-12 05:48:44', '2026-06-12 05:48:44'),
(3, 3, 1, 'การเรียนรู้', 0, 0, NULL, NULL, '2026-06-12 05:48:44', '2026-06-12 05:48:44'),
(4, 4, 1, 'การฝึกงานที่เน้นการเรียนรู้หรือการติดตามพฤติกรรมการทำงาน หลักสูตรร่วมหาวิทยาลัยและอุตสาหกรรม', 0, 0, NULL, NULL, '2026-06-12 05:48:44', '2026-06-12 05:48:44'),
(5, 5, 1, '2536', 0, 0, NULL, NULL, '2026-06-12 05:48:44', '2026-06-12 05:48:44'),
(6, 7, 1, 'มีสิ', 0, 0, NULL, NULL, '2026-06-12 05:57:49', '2026-06-12 05:57:49'),
(7, 8, 1, 'เพื่อศึกษาความรู้พื้นฐานของปัญญาประดิษฐ์ (AI)', 1, 1, NULL, NULL, '2026-06-12 05:57:49', '2026-06-12 05:57:49'),
(8, 9, 1, '2565', 0, 0, NULL, NULL, '2026-06-12 05:57:49', '2026-06-12 05:57:49'),
(9, 10, 1, 'การเปลี่ยนแปลงงานที่ทำโดยมนุษย์', 1, 1, NULL, NULL, '2026-06-12 05:57:49', '2026-06-12 05:57:49'),
(10, 11, 1, 'การเพิ่มความสามารถในการเรียนรู้ของ Machine Learning', 1, 1, NULL, NULL, '2026-06-12 05:57:49', '2026-06-12 05:57:49'),
(11, 12, 1, 'ทั้งหมด', 1, 1, NULL, NULL, '2026-06-12 06:12:00', '2026-06-12 06:12:00'),
(12, 13, 1, 'หน้าจอของไม่รุ้', 0, 0, NULL, NULL, '2026-06-12 06:12:00', '2026-06-12 06:12:00'),
(13, 14, 1, 'เพื่อให้เป็นไปตาม PDPA', 0, 0, NULL, NULL, '2026-06-12 06:12:00', '2026-06-12 06:12:00'),
(14, 15, 1, 'สิทธิในการเข้าถึงข้อมูล', 0, 0, NULL, NULL, '2026-06-12 06:12:00', '2026-06-12 06:12:00'),
(15, 16, 1, 'การประเมิน', 0, 0, NULL, NULL, '2026-06-12 06:12:00', '2026-06-12 06:12:00');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

DROP TABLE IF EXISTS `quiz_attempts`;

CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `score` int(11) NOT NULL,
  `passed` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_attempts`
--

INSERT INTO `quiz_attempts` (`id`, `user_id`, `quiz_id`, `answers`, `score`, `passed`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '[]', 1, 0, '2026-06-12 05:48:44', '2026-06-12 05:48:44'),
(2, 1, 3, '[]', 3, 1, '2026-06-12 05:57:49', '2026-06-12 05:57:49'),
(3, 1, 4, '[]', 1, 0, '2026-06-12 06:12:00', '2026-06-12 06:12:00');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

DROP TABLE IF EXISTS `quiz_questions`;

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` varchar(255) NOT NULL DEFAULT 'multiple_choice',
  `options` longtext DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 1,
  `explanation` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quiz_id`, `question_text`, `question_type`, `options`, `correct_answer`, `points`, `explanation`, `created_at`, `updated_at`) VALUES
(1, 1, 'การบูรณาการการเรียนกับการทำงาน คืออะไร', 'short_answer', NULL, 'การจัดการศึกษาแบบผสมกลมกลืนระหว่างประสบการณ์ทำงานและวิชาชีพนอกห้องเรียนกับการเรียนในห้องเรียน', 1, 'การบูรณาการการเรียนกับการทำงานหมายถึงการผสมผสานระหว่างการเรียนและการทำงานจริง เพื่อให้นักศึกษาสามารถนำทฤษฎีไปใช้ในการปฏิบัติงานจริง และเพิ่มประสบการณ์จริงในการทำงาน', '2026-06-12 05:48:26', '2026-06-12 05:48:26'),
(2, 1, 'บริษัท คานา เอ็นเตอร์ไพรส์ จำกัด ประกอบธุรกิจอะไร', 'multiple_choice', '[\"การพัฒนาระบบเทคโนโลยีสารสนเทศ\",\"การผลิตสินค้าอุปโภค\",\"การให้บริการด้านการเงิน\",\"การขนส่งสินค้า\"]', 'การพัฒนาระบบเทคโนโลยีสารสนเทศ', 1, 'บริษัท คานา เอ็นเตอร์ไพรส์ จำกัด ประกอบธุรกิจด้านการพัฒนาระบบเทคโนโลยีสารสนเทศให้กับหน่วยงานภาครัฐและเอกชน', '2026-06-12 05:48:26', '2026-06-12 05:48:26'),
(3, 1, 'การบูรณาการการเรียนกับการทำงาน ต้องเป็นส่วนหนึ่งของอะไร', 'short_answer', NULL, 'การศึกษาในหลักสูตร', 1, 'การบูรณาการการเรียนกับการทำงานต้องเป็นส่วนหนึ่งของการศึกษาในหลักสูตร เพื่อให้นักศึกษาสามารถนำทฤษฎีไปใช้ในการปฏิบัติงานจริง', '2026-06-12 05:48:26', '2026-06-12 05:48:26'),
(4, 1, 'รูปแบบของการบูรณาการการเรียนกับการทำงาน มีอะไรบ้าง', 'multiple_choice', '[\"การกำหนดประสบการณ์ก่อนการศึกษา การเรียนสลับการทำงาน สหกิจศึกษา\",\"การฝึกงานที่เน้นการเรียนรู้หรือการติดตามพฤติกรรมการทำงาน หลักสูตรร่วมหาวิทยาลัยและอุตสาหกรรม\",\"ทั้งสองอย่างข้างต้น\",\"อื่นๆ\"]', 'ทั้งสองอย่างข้างต้น', 1, 'รูปแบบของการบูรณาการการเรียนกับการทำงานมีหลายรูปแบบ เช่น การกำหนดประสบการณ์ก่อนการศึกษา การเรียนสลับการทำงาน สหกิจศึกษา การฝึกงานที่เน้นการเรียนรู้หรือการติดตามพฤติกรรมการทำงาน หลักสูตรร่วมหาวิทยาลัยและอุตสาหกรรม', '2026-06-12 05:48:26', '2026-06-12 05:48:26'),
(5, 1, 'บริษัท คานา เอ็นเตอร์ไพรส์ จำกัด ก่อตั้งขึ้นเมื่อไร', 'short_answer', NULL, '16 พฤษภาคม พ.ศ. 2543', 1, 'บริษัท คานา เอ็นเตอร์ไพรส์ จำกัด ก่อตั้งขึ้นเมื่อวันที่ 16 พฤษภาคม พ.ศ. 2543 โดยคุณวิทูร หวังสงวนกิจ', '2026-06-12 05:48:26', '2026-06-12 05:48:26'),
(7, 3, 'เทคโนโลยีปัญญาประดิษฐ์ (AI) มีบทบาทสำคัญต่อการเปลี่ยนแปลงของโลกในปัจจุบันอย่างไร', 'short_answer', NULL, 'เทคโนโลยีปัญญาประดิษฐ์ (AI) มีบทบาทสำคัญต่อการเปลี่ยนแปลงของโลกในหลายด้าน เช่น เศรษฐกิจ การแพทย์ การศึกษา และการสื่อสาร', 1, 'เทคโนโลยีปัญญาประดิษฐ์ (AI) สามารถเรียนรู้ วิเคราะห์ และช่วยตัดสินใจ ส่งผลต่อหลายด้านของชีวิตประจำวัน', '2026-06-12 05:56:23', '2026-06-12 05:56:23'),
(8, 3, 'วัตถุประสงค์หลักของการศึกษาเทคโนโลยีปัญญาประดิษฐ์กับการเปลี่ยนแปลงของโลกคืออะไร', 'multiple_choice', '[\"เพื่อศึกษาความรู้พื้นฐานของปัญญาประดิษฐ์ (AI)\",\"เพื่อศึกษาการประยุกต์ใช้ AI ในภาคอุตสาหกรรม\",\"เพื่อศึกษาการพัฒนา AI ในประเทศจีน\",\"เพื่อศึกษาการใช้ AI ในด้านการแพทย์เท่านั้น\"]', 'เพื่อศึกษาความรู้พื้นฐานของปัญญาประดิษฐ์ (AI)', 1, 'วัตถุประสงค์หลักของการศึกษาครั้งนี้คือเพื่อศึกษาความรู้พื้นฐานของปัญญาประดิษฐ์ (AI) และเข้าใจความหมาย หลักการทำงาน และพัฒนาการของเทคนโลยีนี้', '2026-06-12 05:56:23', '2026-06-12 05:56:23'),
(9, 3, 'ปัญญาประดิษฐ์ (AI) เริ่มพัฒนามาตั้งแต่เมื่อไหร่', 'short_answer', NULL, 'กลางศตวรรษที่ 20', 1, 'เทคโนโลยีปัญญาประดิษฐ์ (AI) เริ่มพัฒนามาตั้งแต่กลางศตวรรษที่ 20 โดยมีเป้าหมายให้เครื่องจักรสามารถคิดและตัดสินใจได้คล้ายมนุษย์', '2026-06-12 05:56:23', '2026-06-12 05:56:23'),
(10, 3, 'ข้อใดคือข้อกังวลด้านแรงงานที่เกี่ยวข้องกับ AI', 'multiple_choice', '[\"การขาดแคลนแรงงาน\",\"การเปลี่ยนแปลงงานที่ทำโดยมนุษย์\",\"การเพิ่มความสามารถในการทำงานของมนุษย์\",\"การลดค่าจ้างของพนักงาน\"]', 'การเปลี่ยนแปลงงานที่ทำโดยมนุษย์', 1, 'AI ยังมีข้อกังวลด้านแรงงาน เนื่องจากสามารถทำงานบางอย่างแทนมนุษย์ได้ ทำให้เกิดการเปลี่ยนแปลงงานที่ทำโดยมนุษย์', '2026-06-12 05:56:23', '2026-06-12 05:56:23'),
(11, 3, 'ข้อใดคือผลกระทบของ AI ต่อด้าน Machine Learning', 'multiple_choice', '[\"การลดความสามารถในการเรียนรู้ของ Machine Learning\",\"การเพิ่มความสามารถในการเรียนรู้ของ Machine Learning\",\"การไม่มีผลกระทบใดๆ ต่อ Machine Learning\",\"การเปลี่ยนแปลง Machine Learning เป็นเทคโนโลยีใหม่\"]', 'การเพิ่มความสามารถในการเรียนรู้ของ Machine Learning', 1, 'AI ส่งผลกระทบต่อด้าน Machine Learning โดยการเพิ่มความสามารถในการเรียนรู้และปรับปรุงโมเดลการเรียนรู้ของ Machine Learning', '2026-06-12 05:56:23', '2026-06-12 05:56:23'),
(12, 4, 'ระบบ E-Granting ใช้เพื่ออะไร', 'multiple_choice', '[\"การจัดการข้อมูล\",\"การบันทึกข้อมูล\",\"การตรวจสอบข้อมูล\",\"ทั้งหมด\"]', 'ทั้งหมด', 1, 'ระบบ E-Granting เป็นระบบที่ใช้สำหรับดำเนินงานเกี่ยวกับการจัดการข้อมูล การบันทึกข้อมูล การตรวจสอบข้อมูล และการติดตามงานที่เกี่ยวข้องกับโครงการ', '2026-06-12 06:11:06', '2026-06-12 06:11:06'),
(13, 4, 'หน้าจอใดที่แสดงรายละเอียดเกี่ยวกับการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคลของผู้ใช้งาน', 'short_answer', NULL, 'หน้าประกาศความเป็นส่วนตัว PDPA', 1, 'หน้าประกาศความเป็นส่วนตัว PDPA เป็นหน้าจอที่อธิบายรายละเอียดเกี่ยวกับการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคลของผู้ใช้งาน เพื่อให้เป็นไปตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล หรือ PDPA', '2026-06-12 06:11:06', '2026-06-12 06:11:06'),
(14, 4, 'อะไรคือวัตถุประสงค์ของการแสดงรายละเอียดการจัดการข้อมูลส่วนบุคคล', 'multiple_choice', '[\"เพื่อให้เป็นไปตาม PDPA\",\"เพื่อแสดงรายละเอียดเกี่ยวกับวัตถุประสงค์ในการเก็บรวบรวมข้อมูลส่วนบุคคล\",\"เพื่อแสดงแหล่งที่มาของข้อมูล\",\"ทั้งหมด\"]', 'ทั้งหมด', 1, 'การแสดงรายละเอียดการจัดการข้อมูลส่วนบุคคล มีจุดมุ่งหมายเพื่อให้เป็นไปตาม PDPA และแสดงรายละเอียดเกี่ยวกับวัตถุประสงค์ในการเก็บรวบรวมข้อมูลส่วนบุคคล แหล่งที่มาของข้อมูล และการนำข้อมูลไปใช้ในการดำเนินงานภายในระบบ E-Granting', '2026-06-12 06:11:06', '2026-06-12 06:11:06'),
(15, 4, 'สิทธิใดที่เจ้าของข้อมูลส่วนบุคคลมี', 'multiple_choice', '[\"สิทธิในการเข้าถึงข้อมูล\",\"สิทธิในการแก้ไขข้อมูล\",\"สิทธิในการขอลบข้อมูล\",\"ทั้งหมด\"]', 'ทั้งหมด', 1, 'เจ้าของข้อมูลส่วนบุคคลมีสิทธิในการเข้าถึงข้อมูล สิทธิในการแก้ไขข้อมูล สิทธิในการขอลบข้อมูล และช่องทางการติดต่อเจ้าหน้าที่ที่เกี่ยวข้อง', '2026-06-12 06:11:06', '2026-06-12 06:11:06'),
(16, 4, 'อะไรคือหน้าที่ของหน้าจอการปรับปรุง Versionของการกำกับติดตามและการประเมินผล', 'short_answer', NULL, 'แสดงข้อมูลการอัปเดตหรือปรับปรุงระบบ', 1, 'หน้าจอการปรับปรุง Versionของการกำกับติดตามและการประเมินผล เป็นส่วนที่ใช้แสดงข้อมูลการอัปเดตหรือปรับปรุงระบบ เพื่อให้ผู้ใช้งานทราบถึงการเปลี่ยนแปลงและสามารถใช้งานระบบได้อย่างถูกต้อง', '2026-06-12 06:11:06', '2026-06-12 06:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) NOT NULL,
  `day_of_week` varchar(255) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `room` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `schedule_type` varchar(255) NOT NULL DEFAULT 'class'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `semester`
--

DROP TABLE IF EXISTS `semester`;

CREATE TABLE `semester` (
  `semester_id` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `academic_year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semester`
--

INSERT INTO `semester` (`semester_id`, `semester`, `academic_year`) VALUES
(1, 1, 2568),
(2, 2, 2568),
(3, 1, 2569);

-- --------------------------------------------------------

--
-- Table structure for table `study_calendar_events`
--

DROP TABLE IF EXISTS `study_calendar_events`;

CREATE TABLE `study_calendar_events` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `study_log_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `recurrence_rule` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'planned',
  `metadata` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `event_type` varchar(255) NOT NULL DEFAULT 'class'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `study_calendar_events`
--

INSERT INTO `study_calendar_events` (`id`, `user_id`, `subject_id`, `study_log_id`, `title`, `description`, `start_time`, `end_time`, `recurrence_rule`, `status`, `metadata`, `created_at`, `updated_at`, `event_type`) VALUES
(1, 1, 1, NULL, 'โปรแกรม2', NULL, '2026-06-12 10:00:00', '2026-06-12 12:00:00', NULL, 'planned', '{\"type\":\"class\",\"all_day\":false,\"source\":\"subject\",\"room\":null}', '2026-06-12 05:45:02', '2026-06-12 05:45:02', 'class'),
(2, 1, 1, 4, 'แบบฝึกหัดจากไฟล์: รายงานผลการปฏิบัติงาน', 'อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด', '2026-06-12 00:00:00', NULL, NULL, 'planned', '{\"source\":\"study_log\",\"all_day\":true}', '2026-06-12 05:48:29', '2026-06-12 05:48:29', 'class'),
(3, 1, 1, 6, 'แบบฝึกหัดจากไฟล์: เทคโนโลยีปัญญาประดิษฐ์กับการเปลี่ยนแปลงของโลก', 'อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด', '2026-06-12 00:00:00', NULL, NULL, 'planned', '{\"source\":\"study_log\",\"all_day\":true}', '2026-06-12 05:56:24', '2026-06-12 05:56:24', 'class'),
(4, 1, 1, 7, 'แบบฝึกหัดจากไฟล์: งานของที่เขาให้ทำตอนปฎิบัติงาน', 'อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด', '2026-06-12 00:00:00', NULL, NULL, 'planned', '{\"source\":\"study_log\",\"all_day\":true}', '2026-06-12 06:11:08', '2026-06-12 06:11:08', 'class');

-- --------------------------------------------------------

--
-- Table structure for table `study_environments`
--

DROP TABLE IF EXISTS `study_environments`;

CREATE TABLE `study_environments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `features` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `noise_level` varchar(255) NOT NULL,
  `has_wifi` int(11) NOT NULL DEFAULT 0,
  `location` varchar(255) DEFAULT NULL,
  `rating` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `study_logs`
--

DROP TABLE IF EXISTS `study_logs`;

CREATE TABLE `study_logs` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `note` text DEFAULT NULL,
  `log_date` datetime NOT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `mood` varchar(100) DEFAULT NULL,
  `log_type` varchar(50) NOT NULL DEFAULT 'study',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `study_logs`
--

INSERT INTO `study_logs` (`id`, `user_id`, `subject_id`, `title`, `note`, `log_date`, `duration_minutes`, `mood`, `log_type`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'สรุปเอกสาร: รายงานผลการปฏิบัติงา1', 'อัปโหลดไฟล์เพื่อสรุปเอกสาร', '2026-06-12 00:00:00', NULL, NULL, 'document_summary', '2026-06-12 05:45:11', '2026-06-12 05:45:11'),
(2, 1, 1, 'สรุปเอกสาร: หัวข้อพิเศษของฝึกงาน2เดือน', 'อัปโหลดไฟล์เพื่อสรุปเอกสาร', '2026-06-12 00:00:00', NULL, 'สนุก', 'document_summary', '2026-06-12 05:47:30', '2026-06-12 05:47:30'),
(3, 1, 1, 'สรุปเอกสาร: งานของที่เขาให้ทำตอนปฎิบัติงาน', 'อัปโหลดไฟล์เพื่อสรุปเอกสาร', '2026-06-12 00:00:00', NULL, 'สนุก', 'document_summary', '2026-06-12 05:47:57', '2026-06-12 05:47:57'),
(4, 1, 1, 'แบบฝึกหัดจากไฟล์: รายงานผลการปฏิบัติงาน', 'อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด', '2026-06-12 00:00:00', NULL, NULL, 'study', '2026-06-12 05:48:26', '2026-06-12 05:48:26'),
(6, 1, 1, 'แบบฝึกหัดจากไฟล์: เทคโนโลยีปัญญาประดิษฐ์กับการเปลี่ยนแปลงของโลก', 'อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด', '2026-06-12 00:00:00', NULL, NULL, 'study', '2026-06-12 05:56:23', '2026-06-12 05:56:23'),
(7, 1, 1, 'แบบฝึกหัดจากไฟล์: งานของที่เขาให้ทำตอนปฎิบัติงาน', 'อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด', '2026-06-12 00:00:00', NULL, NULL, 'study', '2026-06-12 06:11:06', '2026-06-12 06:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `study_notifications`
--

DROP TABLE IF EXISTS `study_notifications`;

CREATE TABLE `study_notifications` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` varchar(255) NOT NULL,
  `notify_at` datetime NOT NULL,
  `is_read` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `semester_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(12) DEFAULT NULL,
  `target_hours` int(11) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `start_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `user_id`, `semester_id`, `name`, `description`, `color`, `target_hours`, `start_date`, `created_at`, `updated_at`, `end_time`, `start_time`) VALUES
(1, 1, 1, 'โปรแกรม2', '12หแผ', '#2563eb', NULL, '2026-06-12 00:00:00', '2026-06-12 05:45:02', '2026-06-12 05:45:02', '12:00:00', '10:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `subjects_archives`
--

DROP TABLE IF EXISTS `subjects_archives`;

CREATE TABLE `subjects_archives` (
  `id` int(11) NOT NULL,
  `original_subject_id` int(11) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `target_hours` int(11) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects_archives`
--

INSERT INTO `subjects_archives` (`id`, `original_subject_id`, `user_id`, `name`, `description`, `color`, `target_hours`, `start_date`, `start_time`, `end_time`, `archived_at`) VALUES
(1, 1, 1, 'รายวัน: หัวข้อพิเศษของฝึกงาน2เดือน', '**สรุปผลลัพธ์**\n\n* **บทนำ**: เทคโนโลยีปัญญาประดิษฐ์ (AI) มีบทบาทสำคัญต่อการเปลี่ยนแปลงของโลกในยุคดิจิทัล\n* **ความเป็นมาของ AI**: เริ่มพัฒนามาตั้งแต่กลางศตวรรษที่ 20 โดยมีเป้าหมายให้เครื่องจักรสามารถคิดและตัดสินใจได้คล้ายมนุษย์\n* **วัตถุประสงค์ของการศึกษา AI**: เพื่อศึกษาความรู้พื้นฐานของ AI, วิเคราะห์บทบาทของ AI ต่อการเปลี่ยนแปลงของโลก, ศึกษาการประยุกต์ใช้ AI ในชีวิตประจำวัน, และวิเคราะห์ข้อดี ข้อจำกัด และผลกระทบของ AI\n* **ข้อดีของ AI**: ความแม่นยำสูง, ทำงานได้รวดเร็ว, ทำงานซ้ำ ๆ ได้อย่างต่อเนื่อง, และรองรับข้อมูลขนาดใหญ่\n\n**หัวข้อสำคัญ**\n\n* เทคโนโลยีปัญญาประดิษฐ์ (AI)\n* บทบาทของ AI ต่อการเปลี่ยนแปลงของโลก\n* การประยุกต์ใช้ AI ในชีวิตประจำวัน\n* ข้อดีและข้อจำกัดของ AI\n\n**action items**\n\n* ศึกษาความรู้พื้นฐานของ AI\n* วิเคราะห์บทบาทของ AI ต่อการเปลี่ยนแปลงของโลก\n* ศึกษาการประยุกต์ใช้ AI ในชีวิตประจำวัน\n* วิเคราะห์ข้อดีและข้อจำกัดของ AI', '#2563eb', NULL, '2026-06-12 00:00:00', '2026-06-12 10:00:00', '2026-06-12 12:00:00', '2026-06-12 05:47:36'),
(2, 1, 1, 'รายวัน: งานของที่เขาให้ทำตอนปฎิบัติงาน', 'ผลลัพธ์ของคู่มือการใช้งาน E-Granting เป็นดังนี้\n* หัวข้อสำคัญ \n  * ภาพรวมระบบหน้าหลัก\n  * การจัดการข้อมูลส่วนบุคคล\n  * การเปิดเผยข้อมูลส่วนบุคคล\n  * สิทธิของเจ้าของข้อมูลส่วนบุคคล\n  * การดำเนินงานให้สอดคล้องกับ PDPA\n* Action items\n  * ศึกษาขั้นตอนการใช้งานระบบ E-Granting\n  * เข้าใจเกี่ยวกับการจัดการข้อมูลส่วนบุคคลและการเปิดเผยข้อมูล\n  * ดำเนินการให้สอดคล้องกับ PDPA\n  * ใช้งานระบบ E-Granting ให้ถูกต้องและมีประสิทธิภาพ', '#2563eb', NULL, '2026-06-12 00:00:00', '2026-06-12 10:00:00', '2026-06-12 12:00:00', '2026-06-12 05:48:02');

-- --------------------------------------------------------

--
-- Table structure for table `summaries`
--

DROP TABLE IF EXISTS `summaries`;

CREATE TABLE `summaries` (
  `id` int(11) NOT NULL,
  `study_log_id` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `ai_model` varchar(100) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `topic_materials`
--

DROP TABLE IF EXISTS `topic_materials`;

CREATE TABLE `topic_materials` (
  `id` int(11) NOT NULL,
  `study_log_id` int(11) NOT NULL,
  `source_file_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `content` varchar(255) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `education_level` varchar(255) DEFAULT NULL,
  `provider` varchar(255) DEFAULT NULL,
  `provider_id` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `profile_pic`, `education_level`, `provider`, `provider_id`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`, `role`) VALUES
(1, 'CS 65 52 เกศสุดา คํามา', '651463052@crru.ac.th', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocI49Ao1agfEb9Gjg0dv9Ux9zruEdaGZ1jkyM6Xe7fN39K9lqg=s96-c', NULL, 'google', '107822641286723259563', '2026-06-09 23:04:28', NULL, '2026-01-27 06:13:23', '2026-06-09 23:04:28', 'admin'),
(2, 'Gapata Coda', 'gpttook39@gmail.com', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocLQJ6e9dSwi-DB0G49BEwVe4VIffg5380iz0LBc070imgAoJw=s96-c', NULL, 'google', '110660821686012368227', NULL, NULL, '2026-01-27 13:34:37', '2026-01-27 13:34:37', 'user'),
(3, 'Kedsuda Khamma', '651998018@crru.ac.th', '1234', 'https://lh3.googleusercontent.com/a/ACg8ocIC6otUHczyt8MhIPziGhRRSjWEk6JV-jqtvPBJgntOUgHAqEw=s96-c', NULL, 'google', '117514067624953301262', '2026-02-19 06:36:04', NULL, '2026-01-30 03:29:10', '2026-02-19 06:36:04', 'user'),
(7, 'อัศวินฆ่าไม่ตาย', 'kessuda.4839@gmail.com', '$2y$12$z.Yq5FG7KXLVGwJqWSbxLOQ6V3Pt/HqX/Xgr/mGHLbyzn7zyxKrnS', 'https://lh3.googleusercontent.com/a/ACg8ocJa-7EoAs_xg89sGIpYMgpYbs4azf19MYmUeWVKXHyRlRL4lKSt=s96-c', NULL, 'google', '107730244252730140542', '2026-02-19 06:33:42', NULL, '2026-02-19 06:33:42', '2026-02-19 06:33:42', 'user'),
(8, 'yop poi', 'poiyop780@gmail.com', '$2y$12$F/Fag5QipQTHu5YoEUZhLenvoVyBIWNTiXQeG3A5J2dZPs2f0jNk.', 'https://lh3.googleusercontent.com/a/ACg8ocLGZEx6WZ0zzpj2FtukTlGUkINX1Ax-OJS6GMzsy4t3i7AimA=s96-c', NULL, 'google', '113837388853339249551', '2026-06-06 21:35:46', NULL, '2026-06-06 21:35:46', '2026-06-06 21:35:46', 'user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audio_summaries`
--
ALTER TABLE `audio_summaries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_summaries_v2`
--
ALTER TABLE `audio_summaries_v2`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_transcription_jobs`
--
ALTER TABLE `audio_transcription_jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_transcription_segments`
--
ALTER TABLE `audio_transcription_segments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calendar_notes`
--
ALTER TABLE `calendar_notes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `career_paths`
--
ALTER TABLE `career_paths`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `career_recommendations`
--
ALTER TABLE `career_recommendations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_history`
--
ALTER TABLE `chat_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_digests`
--
ALTER TABLE `email_digests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_digest_items`
--
ALTER TABLE `email_digest_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_provider_accounts`
--
ALTER TABLE `email_provider_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `learning_goal_targets`
--
ALTER TABLE `learning_goal_targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_learning_goal_subject` (`subject_id`),
  ADD KEY `fk_learning_goal_schedule` (`schedule_id`);

--
-- Indexes for table `learning_moods`
--
ALTER TABLE `learning_moods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `learning_notifications`
--
ALTER TABLE `learning_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lesson_summaries`
--
ALTER TABLE `lesson_summaries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mood_logs`
--
ALTER TABLE `mood_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_email_logs`
--
ALTER TABLE `notification_email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_email_settings`
--
ALTER TABLE `notification_email_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `semester`
--
ALTER TABLE `semester`
  ADD PRIMARY KEY (`semester_id`);

--
-- Indexes for table `study_calendar_events`
--
ALTER TABLE `study_calendar_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `study_environments`
--
ALTER TABLE `study_environments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `study_logs`
--
ALTER TABLE `study_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `study_logs_log_type_index` (`log_type`);

--
-- Indexes for table `study_notifications`
--
ALTER TABLE `study_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects_archives`
--
ALTER TABLE `subjects_archives`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `summaries`
--
ALTER TABLE `summaries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `topic_materials`
--
ALTER TABLE `topic_materials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audio_summaries`
--
ALTER TABLE `audio_summaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_summaries_v2`
--
ALTER TABLE `audio_summaries_v2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_transcription_jobs`
--
ALTER TABLE `audio_transcription_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_transcription_segments`
--
ALTER TABLE `audio_transcription_segments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calendar_notes`
--
ALTER TABLE `calendar_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `career_paths`
--
ALTER TABLE `career_paths`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `career_recommendations`
--
ALTER TABLE `career_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_history`
--
ALTER TABLE `chat_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `email_digests`
--
ALTER TABLE `email_digests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_digest_items`
--
ALTER TABLE `email_digest_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_provider_accounts`
--
ALTER TABLE `email_provider_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `learning_goal_targets`
--
ALTER TABLE `learning_goal_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learning_moods`
--
ALTER TABLE `learning_moods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learning_notifications`
--
ALTER TABLE `learning_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_summaries`
--
ALTER TABLE `lesson_summaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `mood_logs`
--
ALTER TABLE `mood_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_email_logs`
--
ALTER TABLE `notification_email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_email_settings`
--
ALTER TABLE `notification_email_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `semester`
--
ALTER TABLE `semester`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `study_calendar_events`
--
ALTER TABLE `study_calendar_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `study_environments`
--
ALTER TABLE `study_environments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `study_logs`
--
ALTER TABLE `study_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `study_notifications`
--
ALTER TABLE `study_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subjects_archives`
--
ALTER TABLE `subjects_archives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `topic_materials`
--
ALTER TABLE `topic_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Extra indexes for foreign keys and faster search
--
ALTER TABLE `audio_summaries`
  ADD KEY `idx_audio_summaries_file_id` (`file_id`),
  ADD KEY `idx_audio_summaries_study_log_id` (`study_log_id`);

ALTER TABLE `audio_summaries_v2`
  ADD KEY `idx_audio_summaries_v2_job_id` (`job_id`);

ALTER TABLE `audio_transcription_jobs`
  ADD KEY `idx_audio_jobs_user_id` (`user_id`),
  ADD KEY `idx_audio_jobs_file_id` (`file_id`),
  ADD KEY `idx_audio_jobs_study_log_id` (`study_log_id`);

ALTER TABLE `audio_transcription_segments`
  ADD KEY `idx_audio_segments_job_id` (`job_id`);

ALTER TABLE `calendar_notes`
  ADD KEY `idx_calendar_notes_user_id` (`user_id`);

ALTER TABLE `career_recommendations`
  ADD KEY `idx_career_recs_user_id` (`user_id`),
  ADD KEY `idx_career_recs_subject_id` (`subject_id`),
  ADD KEY `idx_career_recs_path_id` (`career_path_id`);

ALTER TABLE `email_digests`
  ADD KEY `idx_email_digests_user_id` (`user_id`),
  ADD KEY `idx_email_digests_setting_id` (`email_setting_id`),
  ADD KEY `idx_email_digests_provider_account_id` (`provider_account_id`);

ALTER TABLE `email_digest_items`
  ADD KEY `idx_email_digest_items_digest_id` (`digest_id`),
  ADD KEY `idx_email_digest_items_event_id` (`calendar_event_id`),
  ADD KEY `idx_email_digest_items_subject_id` (`subject_id`),
  ADD KEY `idx_email_digest_items_study_log_id` (`study_log_id`);

ALTER TABLE `email_provider_accounts`
  ADD KEY `idx_email_provider_accounts_user_id` (`user_id`);

ALTER TABLE `files`
  ADD KEY `idx_files_study_log_id` (`study_log_id`);

ALTER TABLE `learning_goal_targets`
  ADD KEY `idx_learning_goal_user_id` (`user_id`);

ALTER TABLE `learning_moods`
  ADD KEY `idx_learning_moods_user_id` (`user_id`),
  ADD KEY `idx_learning_moods_study_log_id` (`study_log_id`);

ALTER TABLE `learning_notifications`
  ADD KEY `idx_learning_notifications_user_id` (`user_id`),
  ADD KEY `idx_learning_notifications_subject_id` (`subject_id`),
  ADD KEY `idx_learning_notifications_study_log_id` (`study_log_id`),
  ADD KEY `idx_learning_notifications_event_id` (`calendar_event_id`);

ALTER TABLE `lessons`
  ADD KEY `idx_lessons_subject_id` (`subject_id`);

ALTER TABLE `lesson_summaries`
  ADD KEY `idx_lesson_summaries_user_id` (`user_id`),
  ADD KEY `idx_lesson_summaries_subject_id` (`subject_id`);

ALTER TABLE `mood_logs`
  ADD KEY `idx_mood_logs_user_id` (`user_id`),
  ADD KEY `idx_mood_logs_subject_id` (`subject_id`);

ALTER TABLE `notification_email_logs`
  ADD KEY `idx_notification_email_logs_user_id` (`user_id`),
  ADD KEY `idx_notification_email_logs_learning_notification_id` (`learning_notification_id`),
  ADD KEY `idx_notification_email_logs_email_digest_id` (`email_digest_id`);

ALTER TABLE `notification_email_settings`
  ADD KEY `idx_notification_email_settings_user_id` (`user_id`);

ALTER TABLE `quizzes`
  ADD KEY `idx_quizzes_subject_id` (`subject_id`);

ALTER TABLE `quiz_answers`
  ADD KEY `idx_quiz_answers_question_id` (`question_id`),
  ADD KEY `idx_quiz_answers_user_id` (`user_id`);

ALTER TABLE `quiz_attempts`
  ADD KEY `idx_quiz_attempts_user_id` (`user_id`),
  ADD KEY `idx_quiz_attempts_quiz_id` (`quiz_id`);

ALTER TABLE `quiz_questions`
  ADD KEY `idx_quiz_questions_quiz_id` (`quiz_id`);

ALTER TABLE `schedules`
  ADD KEY `idx_schedules_user_id` (`user_id`),
  ADD KEY `idx_schedules_subject_id` (`subject_id`);

ALTER TABLE `study_calendar_events`
  ADD KEY `idx_calendar_events_user_id` (`user_id`),
  ADD KEY `idx_calendar_events_subject_id` (`subject_id`),
  ADD KEY `idx_calendar_events_study_log_id` (`study_log_id`);

ALTER TABLE `study_logs`
  ADD KEY `idx_study_logs_user_id` (`user_id`),
  ADD KEY `idx_study_logs_subject_id` (`subject_id`);

ALTER TABLE `study_notifications`
  ADD KEY `idx_study_notifications_user_id` (`user_id`),
  ADD KEY `idx_study_notifications_subject_id` (`subject_id`);

ALTER TABLE `subjects`
  ADD KEY `idx_subjects_user_id` (`user_id`),
  ADD KEY `idx_subjects_semester_id` (`semester_id`);

ALTER TABLE `subjects_archives`
  ADD KEY `idx_subjects_archives_original_subject_id` (`original_subject_id`),
  ADD KEY `idx_subjects_archives_user_id` (`user_id`);

ALTER TABLE `summaries`
  ADD KEY `idx_summaries_study_log_id` (`study_log_id`);

ALTER TABLE `topic_materials`
  ADD KEY `idx_topic_materials_study_log_id` (`study_log_id`),
  ADD KEY `idx_topic_materials_source_file_id` (`source_file_id`);

--
-- Constraints for dumped tables
--
ALTER TABLE `audio_summaries`
  ADD CONSTRAINT `fk_audio_summaries_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_audio_summaries_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `audio_summaries_v2`
  ADD CONSTRAINT `fk_audio_summaries_v2_job` FOREIGN KEY (`job_id`) REFERENCES `audio_transcription_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `audio_transcription_jobs`
  ADD CONSTRAINT `fk_audio_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_audio_jobs_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_audio_jobs_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `audio_transcription_segments`
  ADD CONSTRAINT `fk_audio_segments_job` FOREIGN KEY (`job_id`) REFERENCES `audio_transcription_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `calendar_notes`
  ADD CONSTRAINT `fk_calendar_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `career_recommendations`
  ADD CONSTRAINT `fk_career_recs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_career_recs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_career_recs_path` FOREIGN KEY (`career_path_id`) REFERENCES `career_paths` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `email_provider_accounts`
  ADD CONSTRAINT `fk_email_provider_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `email_digests`
  ADD CONSTRAINT `fk_email_digests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_digests_setting` FOREIGN KEY (`email_setting_id`) REFERENCES `notification_email_settings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_digests_provider_account` FOREIGN KEY (`provider_account_id`) REFERENCES `email_provider_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `email_digest_items`
  ADD CONSTRAINT `fk_email_digest_items_digest` FOREIGN KEY (`digest_id`) REFERENCES `email_digests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_digest_items_event` FOREIGN KEY (`calendar_event_id`) REFERENCES `study_calendar_events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_digest_items_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_digest_items_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `files`
  ADD CONSTRAINT `fk_files_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `learning_goal_targets`
  ADD CONSTRAINT `fk_learning_goal_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learning_goal_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learning_goal_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `learning_moods`
  ADD CONSTRAINT `fk_learning_moods_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learning_moods_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `learning_notifications`
  ADD CONSTRAINT `fk_learning_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learning_notifications_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learning_notifications_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learning_notifications_event` FOREIGN KEY (`calendar_event_id`) REFERENCES `study_calendar_events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `lessons`
  ADD CONSTRAINT `fk_lessons_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `lesson_summaries`
  ADD CONSTRAINT `fk_lesson_summaries_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lesson_summaries_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `mood_logs`
  ADD CONSTRAINT `fk_mood_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mood_logs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `notification_email_logs`
  ADD CONSTRAINT `fk_notification_email_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notification_email_logs_learning_notification` FOREIGN KEY (`learning_notification_id`) REFERENCES `learning_notifications` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notification_email_logs_email_digest` FOREIGN KEY (`email_digest_id`) REFERENCES `email_digests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `notification_email_settings`
  ADD CONSTRAINT `fk_notification_email_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quizzes_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `fk_quiz_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `quiz_answers`
  ADD CONSTRAINT `fk_quiz_answers_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_quiz_answers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `fk_quiz_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_quiz_attempts_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedules_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_schedules_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `study_calendar_events`
  ADD CONSTRAINT `fk_calendar_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calendar_events_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calendar_events_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `study_logs`
  ADD CONSTRAINT `fk_study_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_study_logs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `study_notifications`
  ADD CONSTRAINT `fk_study_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_study_notifications_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subjects_semester` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`semester_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `subjects_archives`
  ADD CONSTRAINT `fk_subjects_archives_original_subject` FOREIGN KEY (`original_subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subjects_archives_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `summaries`
  ADD CONSTRAINT `fk_summaries_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `topic_materials`
  ADD CONSTRAINT `fk_topic_materials_study_log` FOREIGN KEY (`study_log_id`) REFERENCES `study_logs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_topic_materials_source_file` FOREIGN KEY (`source_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
