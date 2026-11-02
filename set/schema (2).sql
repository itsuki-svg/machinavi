-- ============================================================
--  まちナビ  スキーマ（MySQL 8 / utf8mb4 / InnoDB）
--  Sakura Standard 用。phpMyAdmin もしくは mysql コマンドでインポート。
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+09:00';

-- ---------- 店舗 ----------
CREATE TABLE shops (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)  NOT NULL,
  name_kana     VARCHAR(120)  NOT NULL DEFAULT '',
  area          VARCHAR(60)   NOT NULL DEFAULT '',
  category      VARCHAR(60)   NOT NULL DEFAULT '',
  catchphrase   VARCHAR(200)  NOT NULL DEFAULT '',
  intro         TEXT,
  rating        DECIMAL(2,1)  NOT NULL DEFAULT 0.0,
  review_count  INT           NOT NULL DEFAULT 0,
  has_coupon    TINYINT(1)    NOT NULL DEFAULT 0,
  coupon_text   VARCHAR(200)  NOT NULL DEFAULT '',
  hours         VARCHAR(120)  NOT NULL DEFAULT '',
  closed        VARCHAR(60)   NOT NULL DEFAULT '',
  seats         VARCHAR(120)  NOT NULL DEFAULT '',
  address       VARCHAR(200)  NOT NULL DEFAULT '',
  phone         VARCHAR(40)   NOT NULL DEFAULT '',
  payment       VARCHAR(200)  NOT NULL DEFAULT '',
  plan_tier     ENUM('free','pr','premium') NOT NULL DEFAULT 'free',
  sort_priority INT           NOT NULL DEFAULT 0,
  status        ENUM('published','draft')   NOT NULL DEFAULT 'published',
  staff_key     VARCHAR(40)   NOT NULL DEFAULT '',
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status_tier (status, plan_tier, sort_priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 店舗写真（カルーセル） ----------
CREATE TABLE shop_images (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  shop_id    INT NOT NULL,
  image_path VARCHAR(300) NOT NULL DEFAULT '',  -- 空ならプレースホルダ表示
  label      VARCHAR(120) NOT NULL DEFAULT '',
  sort       INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_img_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  KEY idx_img (shop_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 属性タグ ----------
CREATE TABLE shop_tags (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  tag     VARCHAR(60) NOT NULL,
  sort    INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_tag_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  KEY idx_tag (shop_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 料金システム ----------
CREATE TABLE shop_prices (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  name    VARCHAR(120) NOT NULL,
  price   VARCHAR(60)  NOT NULL DEFAULT '',
  note    VARCHAR(200) NOT NULL DEFAULT '',
  sort    INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_price_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  KEY idx_price (shop_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- スタッフ ----------
CREATE TABLE shop_staff (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  name    VARCHAR(80)  NOT NULL,
  role    VARCHAR(60)  NOT NULL DEFAULT '',
  message VARCHAR(200) NOT NULL DEFAULT '',
  image_path VARCHAR(255) NOT NULL DEFAULT '',
  sort    INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_staff_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  KEY idx_staff (shop_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 広告枠（A/B/C を共通管理） ----------
CREATE TABLE ad_slots (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  slot_key     CHAR(1) NOT NULL,                              -- 'A' / 'B' / 'C'
  page         ENUM('list','detail','both') NOT NULL DEFAULT 'both',
  type         VARCHAR(20) NOT NULL DEFAULT 'banner',         -- banner / adsense / pr_native
  title        VARCHAR(120) NOT NULL DEFAULT '',
  body         VARCHAR(300) NOT NULL DEFAULT '',
  link_url     VARCHAR(300) NOT NULL DEFAULT '',
  image_path   VARCHAR(300) NOT NULL DEFAULT '',
  adsense_code TEXT,
  sort         INT NOT NULL DEFAULT 0,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  starts_at    DATETIME NULL,
  ends_at      DATETIME NULL,
  KEY idx_slot (page, slot_key, active, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 口コミ ----------
CREATE TABLE reviews (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  shop_id    INT NOT NULL,
  user_id    INT NULL,
  nickname   VARCHAR(60)  NOT NULL DEFAULT '匿名',
  rating     TINYINT      NOT NULL DEFAULT 5,
  body       TEXT         NOT NULL,
  status     ENUM('pending','approved') NOT NULL DEFAULT 'pending',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  KEY idx_review (shop_id, status, created_at),
  KEY idx_review_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 会員 ----------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nickname      VARCHAR(60)  NOT NULL DEFAULT '',
  status        VARCHAR(12)  NOT NULL DEFAULT 'active',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- クーポン ----------
CREATE TABLE coupons (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  shop_id     INT NOT NULL,
  title       VARCHAR(120) NOT NULL,
  description VARCHAR(300) NOT NULL DEFAULT '',
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_coupon_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  KEY idx_coupon (shop_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- クーポン利用 ----------
CREATE TABLE coupon_uses (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT NOT NULL,
  user_id   INT NOT NULL,
  code      VARCHAR(20) NOT NULL,
  used_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_use (coupon_id, user_id),
  CONSTRAINT fk_use_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_use_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 出勤（日付ごと） ----------
CREATE TABLE attendance (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  shop_id    INT NOT NULL,
  staff_id   INT NULL,
  name       VARCHAR(80)  NOT NULL,
  start_time VARCHAR(20)  NOT NULL DEFAULT '',
  end_time   VARCHAR(20)  NOT NULL DEFAULT '',
  note       VARCHAR(120) NOT NULL DEFAULT '',
  work_date  DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_att_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  KEY idx_att (shop_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  デモ用シード
-- ============================================================

-- ---------- お気に入り ----------
CREATE TABLE favorites (
  user_id    INT NOT NULL,
  shop_id    INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, shop_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------- お問い合わせ ----------
CREATE TABLE inquiries (
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


-- ---------- パスワード再設定トークン ----------
CREATE TABLE password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pr_token (token_hash),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------- 会員登録のメール認証（6桁コード・本登録前の保留データ） ----------
CREATE TABLE email_verifications (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nickname      VARCHAR(60)  NOT NULL DEFAULT '',
  code_hash     CHAR(64)     NOT NULL,
  attempts      TINYINT      NOT NULL DEFAULT 0,
  expires_at    DATETIME     NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ev_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shops
 (id,name,name_kana,area,category,catchphrase,intro,rating,review_count,has_coupon,coupon_text,hours,closed,seats,address,phone,payment,plan_tier,sort_priority,status)
VALUES
 (1,'BAR GLITTER','バー グリッター','さくら町','ミックスバー',
  '全員大歓迎・女性もOK。笑いと癒やしのポップなミックスバー。',
  'さくら町の仲通りから一本入ったビルにある、アットホームなバーです。バーが初めてという方も、ママと個性豊かなスタッフが温かくお迎えします。お一人様でも、お友達同士でもお気軽にどうぞ。気軽なトークからディープなお悩み相談まで、何でもウェルカムです。',
  4.5,32,1,'入店時に「まちナビを見た」で、ファーストドリンク1杯サービス',
  '20:00〜翌5:00','月曜日','カウンター10席 / テーブル2席（計18席）',
  '港区さくら町X-XX ○○ビル2F（さくら駅 徒歩5分）','03-XXXX-XXXX',
  '現金 / クレジット（Visa・Master）/ PayPay','pr',100,'published'),
 (2,'LOUNGE Aoi','ラウンジ アオイ','さくら町','ラウンジ','落ち着いた大人の隠れ家ラウンジ。','',4.2,18,0,'','20:00〜翌4:00','日曜日','カウンター8席','港区さくら町X-XX','','現金 / クレジット','free',0,'published'),
 (3,'SNACK Tsuki','スナック ツキ','さくら町','スナック','常連も一見も楽しめるアットホームなスナック。','',4.0,11,1,'初回ボトル割引','19:00〜翌3:00','不定休','カウンター6席','港区さくら町X-XX','','現金','free',0,'published'),
 (4,'BAR Helios','バー ヘリオス','さくら町','ショットバー','希少酒も揃うショットバー。','',4.4,24,0,'','19:00〜翌5:00','水曜日','カウンター12席','港区さくら町X-XX','','現金 / クレジット / PayPay','premium',50,'published'),
 (5,'CAFE & BAR Niji','カフェアンドバー ニジ','さくら町','カフェバー','昼はカフェ、夜はバー。虹色のくつろぎ空間。','',4.6,40,1,'平日ハッピーアワーあり','15:00〜翌2:00','火曜日','テーブル中心 計20席','港区さくら町X-XX','','現金 / クレジット / 各種QR','free',10,'published'),
 (6,'BAR Kuro','バー クロ','さくら町','ミックスバー','黒を基調としたシックな大人バー。','',4.1,15,0,'','20:00〜翌4:00','月曜日','カウンター10席','港区さくら町X-XX','','現金 / クレジット','free',0,'published'),
 (7,'Standing Nana','スタンディング ナナ','さくら町','立ち呑み','気軽に一杯、出会いの立ち呑み。','',4.3,21,1,'初回ワンドリンク無料','18:00〜翌1:00','日曜日','スタンディング 約15名','港区さくら町X-XX','','現金','free',0,'published');

INSERT INTO shop_images (shop_id,image_path,label,sort) VALUES
 (1,'','メイン写真（内観・集合写真）',0),
 (1,'','サブ写真 1',1),
 (1,'','サブ写真 2',2),
 (1,'','サブ写真 3',3);

INSERT INTO shop_tags (shop_id,tag,sort) VALUES
 (1,'ミックス（どなたでもOK）',0),(1,'一見さん・初心者歓迎',1),(1,'カラオケあり',2),
 (1,'チャージ制',3),(1,'観光客OK',4),(1,'ワイワイ賑やか',5),(1,'20〜30代中心',6),
 (1,'カジュアル客層',7),(1,'ママ在籍',8),(1,'個性派スタッフ在籍',9);

INSERT INTO shop_prices (shop_id,name,price,note,sort) VALUES
 (1,'初回限定プラン','¥3,500','90分 飲み放題（瓶酒・ウイスキー・ハウスボトル）',0),
 (1,'通常チャージ','¥1,500','時間制限なし / ショット代別',1),
 (1,'ショットドリンク','¥800〜','カクテル・ビール・ソフトドリンク各種',2),
 (1,'ボトルキープ','¥5,000〜','焼酎・ウイスキー・ジンなど（割材 ¥500〜）',3),
 (1,'カラオケ','¥200 / 1曲','音響システム完備',4);

INSERT INTO shop_staff (shop_id,name,role,message,sort) VALUES
 (1,'タカシ','ママ','お酒とトークで盛り上がりましょう。',0),
 (1,'ケンタ','スタッフ','アニメと筋トレが大好きです。',1),
 (1,'レイ','スタッフ','カクテル作りが得意です。一緒に歌いましょう。',2);

INSERT INTO ad_slots (slot_key,page,type,title,body,link_url,sort,active) VALUES
 ('A','list','banner','','さくら町でハシゴするならココ。朝まで営業・カラオケ無料の「BAR CHILL」新規特典あり','#',0,1),
 ('C','list','adsense','','',' ',0,1),
 ('A','detail','banner','','さくら町でハシゴするならココ。朝まで営業・カラオケ無料の「BAR CHILL」新規特典あり','#',0,1),
 ('B','detail','pr_native','コラム','バーのママに聞いた、入店時のNG行動5選','#',0,1),
 ('B','detail','pr_native','ショップ','今すぐ手に入れたい最旬メンズアンダーウェア','#',1,1),
 ('C','detail','banner','','さくら町の人気サウナ・イベント・アパレル「○○SPA」深夜限定 割引クーポン配布中','#',0,1);

INSERT INTO coupons (shop_id,title,description,active) VALUES
 (1,'初回限定 ファーストドリンク無料','入店時に提示でドリンク1杯サービス',1),
 (3,'初回ボトル割引','ボトルキープが初回20%オフ',1),
 (5,'平日ハッピーアワー','平日18時まで全ドリンク半額',1),
 (7,'初回ワンドリンク無料','立ち寄りで1杯無料',1);

UPDATE shops SET staff_key = SUBSTRING(MD5(CONCAT(RAND(), id, NOW())), 1, 12);
