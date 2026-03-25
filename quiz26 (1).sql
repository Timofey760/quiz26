-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Мар 25 2026 г., 12:26
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `quiz26`
--
-- Создание базы данных (если не существует)
CREATE DATABASE IF NOT EXISTS quiz26 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE quiz26;
-- --------------------------------------------------------

--
-- Структура таблицы `answer_options`
--

CREATE TABLE `answer_options` (
  `id` int(10) UNSIGNED NOT NULL,
  `slide_id` int(10) UNSIGNED NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(10) UNSIGNED NOT NULL,
  `shape_type` enum('circle','square','diamond','star') DEFAULT 'circle',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `answer_options`
--

INSERT INTO `answer_options` (`id`, `slide_id`, `option_text`, `is_correct`, `option_order`, `shape_type`, `created_at`) VALUES
(97, 25, 'Венера', 0, 0, 'circle', '2026-03-25 11:22:39'),
(98, 25, 'Марс', 1, 1, 'square', '2026-03-25 11:22:39'),
(99, 25, 'Юпитер', 0, 2, 'diamond', '2026-03-25 11:22:39'),
(100, 25, 'Сатурн', 0, 3, 'star', '2026-03-25 11:22:39'),
(101, 26, 'Венера', 0, 0, 'circle', '2026-03-25 11:22:39'),
(102, 26, 'Марс', 0, 1, 'square', '2026-03-25 11:22:39'),
(103, 26, 'Земля', 1, 2, 'diamond', '2026-03-25 11:22:39'),
(104, 26, 'Меркурий', 0, 3, 'star', '2026-03-25 11:22:39'),
(105, 27, 'Юпитер', 1, 0, 'circle', '2026-03-25 11:22:39'),
(106, 27, 'Сатурн', 0, 1, 'square', '2026-03-25 11:22:39'),
(107, 27, 'Земля', 0, 2, 'diamond', '2026-03-25 11:22:39'),
(108, 27, 'Венера', 0, 3, 'star', '2026-03-25 11:22:39');

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#3498db',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `color`, `created_at`, `updated_at`) VALUES
(1, 'Общая', '#3498db', '2026-03-25 11:04:36', '2026-03-25 11:04:36'),
(2, 'Наука', '#2ecc71', '2026-03-25 11:04:36', '2026-03-25 11:04:36'),
(3, 'История', '#e74c3c', '2026-03-25 11:04:36', '2026-03-25 11:04:36'),
(4, 'Искусство', '#9b59b6', '2026-03-25 11:04:36', '2026-03-25 11:04:36');

-- --------------------------------------------------------

--
-- Структура таблицы `game_events`
--

CREATE TABLE `game_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `game_sessions`
--

CREATE TABLE `game_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `quiz_id` int(10) UNSIGNED NOT NULL,
  `host_user_id` int(10) UNSIGNED NOT NULL,
  `session_code` varchar(4) NOT NULL,
  `current_slide_id` int(10) UNSIGNED DEFAULT NULL,
  `slide_start_time` timestamp NULL DEFAULT NULL,
  `status` enum('waiting','active','finished') DEFAULT 'waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `player_answers`
--

CREATE TABLE `player_answers` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `player_id` int(10) UNSIGNED NOT NULL,
  `slide_id` int(10) UNSIGNED NOT NULL,
  `answer_option_id` int(10) UNSIGNED DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `response_time_ms` int(10) UNSIGNED DEFAULT NULL,
  `points_earned` int(10) UNSIGNED DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `tags` varchar(255) DEFAULT NULL,
  `slide_duration` int(10) UNSIGNED DEFAULT 30,
  `background_music` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `quizzes`
--

INSERT INTO `quizzes` (`id`, `user_id`, `category_id`, `title`, `description`, `is_public`, `tags`, `slide_duration`, `background_music`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Тестовая викторина', 'Это пример викторины для демонстрации функционала', 1, 'тест;пример;демо', 30, '/assets/music/default_bg.mp3', '2026-03-25 11:04:36', '2026-03-25 11:04:36');

-- --------------------------------------------------------

--
-- Структура таблицы `quiz_statistics`
--

CREATE TABLE `quiz_statistics` (
  `id` int(10) UNSIGNED NOT NULL,
  `quiz_id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `total_players` int(10) UNSIGNED DEFAULT 0,
  `average_score` decimal(5,2) DEFAULT 0.00,
  `average_response_time_ms` decimal(10,2) DEFAULT 0.00,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `session_players`
--

CREATE TABLE `session_players` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `player_name` varchar(50) NOT NULL,
  `player_token` varchar(64) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `slides`
--

CREATE TABLE `slides` (
  `id` int(10) UNSIGNED NOT NULL,
  `quiz_id` int(10) UNSIGNED NOT NULL,
  `slide_order` int(10) UNSIGNED NOT NULL,
  `question_text` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `font_size` int(10) UNSIGNED DEFAULT 24,
  `font_color` varchar(7) DEFAULT '#000000',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `slides`
--

INSERT INTO `slides` (`id`, `quiz_id`, `slide_order`, `question_text`, `image_path`, `font_size`, `font_color`, `created_at`, `updated_at`) VALUES
(25, 1, 0, 'Какая планета называется \"Красной планетой\"?', 'quiz_1_slide_0_1774437335_69c3c3d721d18.jpeg', 28, '#e74c3c', '2026-03-25 11:22:39', '2026-03-25 11:22:39'),
(26, 1, 1, 'Назовите 3 планету в Солнечной системе', 'quiz_1_slide_1_1774437530_69c3c49a6c182.png', 24, '#000000', '2026-03-25 11:22:39', '2026-03-25 11:22:39'),
(27, 1, 2, 'Назовите самую большую планету в Солнечной системе', 'quiz_1_slide_2_1774437671_69c3c5277c344.jpeg', 24, '#000000', '2026-03-25 11:22:39', '2026-03-25 11:22:39');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `avatar`, `created_at`, `updated_at`) VALUES
(1, 'demo', 'demo@example.com', '$2y$10$7b5asciRv5lt1nEBXQ4al.6vOuz.ILRCen3bnbH1VnwbF9M/cxI3G', NULL, '2026-03-25 11:04:35', '2026-03-25 11:10:47');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `answer_options`
--
ALTER TABLE `answer_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slide` (`slide_id`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

--
-- Индексы таблицы `game_events`
--
ALTER TABLE `game_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_type` (`event_type`);

--
-- Индексы таблицы `game_sessions`
--
ALTER TABLE `game_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_code` (`session_code`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `host_user_id` (`host_user_id`),
  ADD KEY `current_slide_id` (`current_slide_id`),
  ADD KEY `idx_code` (`session_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_game_sessions_status_created` (`status`,`created_at`);

--
-- Индексы таблицы `player_answers`
--
ALTER TABLE `player_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_player_slide` (`player_id`,`slide_id`),
  ADD KEY `slide_id` (`slide_id`),
  ADD KEY `answer_option_id` (`answer_option_id`),
  ADD KEY `idx_session_slide` (`session_id`,`slide_id`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_answers_session_slide` (`session_id`,`slide_id`);

--
-- Индексы таблицы `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_public` (`is_public`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_quizzes_user_public` (`user_id`,`is_public`);
ALTER TABLE `quizzes` ADD FULLTEXT KEY `idx_tags` (`tags`);

--
-- Индексы таблицы `quiz_statistics`
--
ALTER TABLE `quiz_statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `idx_quiz` (`quiz_id`),
  ADD KEY `idx_date` (`completed_at`);

--
-- Индексы таблицы `session_players`
--
ALTER TABLE `session_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `player_token` (`player_token`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_token` (`player_token`);

--
-- Индексы таблицы `slides`
--
ALTER TABLE `slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_order` (`quiz_id`,`slide_order`),
  ADD KEY `idx_slides_quiz_order` (`quiz_id`,`slide_order`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `answer_options`
--
ALTER TABLE `answer_options`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `game_events`
--
ALTER TABLE `game_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `game_sessions`
--
ALTER TABLE `game_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `player_answers`
--
ALTER TABLE `player_answers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `quiz_statistics`
--
ALTER TABLE `quiz_statistics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `session_players`
--
ALTER TABLE `session_players`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `slides`
--
ALTER TABLE `slides`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `answer_options`
--
ALTER TABLE `answer_options`
  ADD CONSTRAINT `answer_options_ibfk_1` FOREIGN KEY (`slide_id`) REFERENCES `slides` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `game_events`
--
ALTER TABLE `game_events`
  ADD CONSTRAINT `game_events_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `game_sessions`
--
ALTER TABLE `game_sessions`
  ADD CONSTRAINT `game_sessions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_sessions_ibfk_2` FOREIGN KEY (`host_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_sessions_ibfk_3` FOREIGN KEY (`current_slide_id`) REFERENCES `slides` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `player_answers`
--
ALTER TABLE `player_answers`
  ADD CONSTRAINT `player_answers_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `player_answers_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `session_players` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `player_answers_ibfk_3` FOREIGN KEY (`slide_id`) REFERENCES `slides` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `player_answers_ibfk_4` FOREIGN KEY (`answer_option_id`) REFERENCES `answer_options` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quizzes_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `quiz_statistics`
--
ALTER TABLE `quiz_statistics`
  ADD CONSTRAINT `quiz_statistics_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_statistics_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `session_players`
--
ALTER TABLE `session_players`
  ADD CONSTRAINT `session_players_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `slides`
--
ALTER TABLE `slides`
  ADD CONSTRAINT `slides_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
