<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$auth->logout();

header("Location: index.php?success=Logged out successfully");
exit();
?>