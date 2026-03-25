<?php
// Простой скрипт для вывода хэша пароля '123'
$password = '123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash;
?>