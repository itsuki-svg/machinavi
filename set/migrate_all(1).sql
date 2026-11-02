-- ============================================================
--  まちナビ  差分マイグレーション（まとめ）
--  既存の本番DBに対して「不足している分だけ」を安全に追加します。
--  すでに適用済みの項目はスキップされるので、何度流してもエラーになりません。
--  ※ phpMyAdmin で対象データベースを選び、このSQLをそのまま実行してください。
--  ※ 新規にゼロから作る場合は schema.sql を使い、これは不要です。
-- ============================================================
SET NAMES utf8mb4;

-- 1) shop_staff にスタッフ画像列（image_path）
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_staff' AND COLUMN_NAME = 'image_path') = 0,
  'ALTER TABLE shop_staff ADD COLUMN image_path VARCHAR(255) NOT NULL DEFAULT '''' AFTER message',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) users に状態列（status: active / withdrawn）
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE users ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT ''active''',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) reviews に投稿者(会員)列（user_id）
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'user_id') = 0,
  'ALTER TABLE reviews ADD COLUMN user_id INT NULL AFTER shop_id',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3b) reviews.user_id のインデックス
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND INDEX_NAME = 'idx_review_user') = 0,
  'ALTER TABLE reviews ADD KEY idx_review_user (user_id)',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) お気に入りテーブル（無ければ作成）
CREATE TABLE IF NOT EXISTS favorites (
  user_id    INT NOT NULL,
  shop_id    INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, shop_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) お問い合わせ（無ければ作成）
CREATE TABLE IF NOT EXISTS inquiries (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  type       VARCHAR(20)  NOT NULL DEFAULT 'other',
  name       VARCHAR(120) NOT NULL DEFAULT '',
  email      VARCHAR(190) NOT NULL DEFAULT '',
  body       TEXT         NOT NULL,
  user_id    INT NULL,
  status     ENUM('open','done') NOT NULL DEFAULT 'open',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inq (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) パスワード再設定トークン（無ければ作成）
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pr_token (token_hash),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
