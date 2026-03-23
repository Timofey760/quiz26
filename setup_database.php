<?php
// setup_database.php
$host = 'localhost';
$user = 'root';
$pass = '123456';
$dbname = 'quiz26';

// Создаем подключение без выбора базы данных
$conn = new mysqli($host, $user, $pass);

// Проверяем подключение
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Создаем базу данных
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "✅ База данных '$dbname' успешно создана или уже существует\n";
} else {
    echo "❌ Ошибка создания базы данных: " . $conn->error . "\n";
}

// Выбираем базу данных
$conn->select_db($dbname);

// SQL для создания таблиц (упрощенная версия для быстрого старта)
$tables_sql = "
-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- Таблица категорий
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#3498db',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Таблица викторин
CREATE TABLE IF NOT EXISTS quizzes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    is_public TINYINT(1) DEFAULT 0,
    tags VARCHAR(255) DEFAULT NULL,
    slide_duration INT UNSIGNED DEFAULT 30,
    background_music VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Таблица слайдов
CREATE TABLE IF NOT EXISTS slides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    slide_order INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    font_size INT UNSIGNED DEFAULT 24,
    font_color VARCHAR(7) DEFAULT '#000000',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz_order (quiz_id, slide_order)
) ENGINE=InnoDB;

-- Таблица вариантов ответов
CREATE TABLE IF NOT EXISTS answer_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slide_id INT UNSIGNED NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    option_order INT UNSIGNED NOT NULL,
    shape_type ENUM('circle', 'square', 'diamond', 'star') DEFAULT 'circle',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (slide_id) REFERENCES slides(id) ON DELETE CASCADE,
    INDEX idx_slide (slide_id)
) ENGINE=InnoDB;

-- Таблица игровых сессий
CREATE TABLE IF NOT EXISTS game_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    host_user_id INT UNSIGNED NOT NULL,
    session_code VARCHAR(4) NOT NULL UNIQUE,
    current_slide_id INT UNSIGNED DEFAULT NULL,
    slide_start_time TIMESTAMP NULL DEFAULT NULL,
    status ENUM('waiting', 'active', 'finished') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_code (session_code)
) ENGINE=InnoDB;

-- Таблица участников
CREATE TABLE IF NOT EXISTS session_players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    player_name VARCHAR(50) NOT NULL,
    player_token VARCHAR(64) NOT NULL UNIQUE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    INDEX idx_session (session_id)
) ENGINE=InnoDB;

-- Таблица ответов
CREATE TABLE IF NOT EXISTS player_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    slide_id INT UNSIGNED NOT NULL,
    answer_option_id INT UNSIGNED DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    response_time_ms INT UNSIGNED DEFAULT NULL,
    points_earned INT UNSIGNED DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES session_players(id) ON DELETE CASCADE,
    FOREIGN KEY (slide_id) REFERENCES slides(id) ON DELETE CASCADE,
    INDEX idx_session_slide (session_id, slide_id)
) ENGINE=InnoDB;

-- Таблица статистики
CREATE TABLE IF NOT EXISTS quiz_statistics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    total_players INT UNSIGNED DEFAULT 0,
    average_score DECIMAL(5,2) DEFAULT 0,
    average_response_time_ms DECIMAL(10,2) DEFAULT 0,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) DELETE CASCADE,
    INDEX idx_quiz (quiz_id)
) ENGINE=InnoDB;
";

// Выполняем создание таблиц
if ($conn->multi_query($tables_sql)) {
    do {
        // Очищаем результаты
        while ($conn->more_results() && $conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    } while ($conn->next_result());
    echo "✅ Таблицы успешно созданы\n";
} else {
    echo "❌ Ошибка создания таблиц: " . $conn->error . "\n";
}

// Добавляем тестовую категорию
$conn->query("INSERT INTO categories (name, color) VALUES ('Общая', '#3498db') ON DUPLICATE KEY UPDATE id=id");

$conn->close();
echo "\n🎉 База данных готова к использованию!\n";
echo "Теперь запустите create_demo_user.php для создания тестового пользователя\n";
?>