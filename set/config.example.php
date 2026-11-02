<?php
// =====================================================================
//  config.php  （Web公開ディレクトリの外に置く）
//  このファイルを config.php にコピーし、実際の値を設定してください。
// =====================================================================

// --- データベース ---
$DB_HOST = 'localhost';
$DB_NAME = 'portal_db';
$DB_USER = 'portal_user';
$DB_PASS = 'your_password_here';

// --- サイトURL ---
define('SITE_URL', 'https://example.com');

// --- 管理画面用キー（長いランダム文字列を設定） ---
define('ADMIN_KEY', 'change_this_to_random_64char_hex_string');
