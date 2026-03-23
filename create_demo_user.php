<?php
require_once 'config/database.php';

// Хеширование пароля
$password = 'password123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Проверяем, существует ли пользователь
$check = $conn->query("SELECT id FROM users WHERE username = 'demo_user'");
if ($check->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $username = 'demo_user';
    $email = 'demo@example.com';
    $stmt->bind_param("sss", $username, $email, $password_hash);
    
    if ($stmt->execute()) {
        echo "✅ Демо-пользователь успешно создан!\n";
        echo "Логин: demo_user\n";
        echo "Пароль: password123\n";
    } else {
        echo "❌ Ошибка создания пользователя: " . $conn->error . "\n";
    }
    $stmt->close();
} else {
    echo "ℹ️ Демо-пользователь уже существует\n";
}

$conn->close();
?>