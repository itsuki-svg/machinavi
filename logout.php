<?php
require __DIR__ . '/auth.php';
unset($_SESSION['uid']);
header('Location: ' . $BASE . '/index.php');
exit;
