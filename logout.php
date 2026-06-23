<?php
require_once __DIR__ . '/includes/auth.php';
logout();
header('Location: /rvc.rts/login.php');
exit;
