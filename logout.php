<?php
require __DIR__ . '/config.php';
logout();
header('Location: /login.php');
exit;