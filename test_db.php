<?php
require 'includes/config.php';
require 'includes/db.php';

echo password_hash('ваш_пароль', PASSWORD_DEFAULT);
echo "Подключение к БД успешно!";
?>
