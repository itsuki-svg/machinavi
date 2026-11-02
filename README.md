# 地域ポータルサイト

店舗検索・口コミ・地図表示・お気に入り・クーポン・スタッフ紹介など、地域の店舗情報を集約するポータルサイトです。

## Features

- **店舗検索** — カテゴリ・エリア・キーワードでの絞り込み検索
- **店舗詳細** — 営業時間（営業中/閉店中のリアルタイム表示）、口コミ、画像ギャラリー
- **口コミ投稿** — 星評価＋テキスト＋画像付きレビュー、承認制で品質管理
- **お気に入り** — ログインユーザーのお気に入り店舗管理
- **クーポン** — 店舗ごとのクーポン発行・利用管理、ワンタイムコード
- **地図表示** — Google Maps 連携の店舗マップ
- **注目店 PR** — PR 枠・プレミアム枠での優先表示
- **スタッフ紹介** — 店舗ごとのスタッフプロフィール・出勤管理
- **ユーザー認証** — メール認証付き新規登録、パスワードリセット
- **管理者機能** — 店舗編集、口コミ承認、問い合わせ管理、広告管理
- **SEO** — サイトマップ自動生成、OGP 対応
- **PWA 対応** — Web App Manifest でホーム画面に追加可能

## Display Priority

店舗の表示順は `plan_tier` で決まります。premium は最上位表示（sort_priority 加算）、pr は PR 枠に表示、free は通常表示です。同一 tier 内では sort_priority DESC、id の順でソートされます。

## Database Schema

| テーブル | 概要 | 主なカラム |
|---------|------|-----------|
| `shops` | 店舗マスタ | id, name, name_kana, area, category, catchphrase, intro, rating, review_count, hours, closed, plan_tier (free/pr/premium), status |
| `shop_images` | 店舗画像 | id, shop_id FK, image_path, label, sort |
| `shop_tags` | 属性タグ | id, shop_id FK, tag, sort |
| `shop_prices` | 料金体系 | id, shop_id FK, name, price, note, sort |
| `shop_staff` | スタッフ | id, shop_id FK, name, role, message, image_path |
| `reviews` | 口コミ | id, shop_id FK, user_id, nickname, rating (1-5), body, status (pending/approved) |
| `users` | 会員 | id, email UNIQUE, password_hash, nickname, status |
| `favorites` | お気に入り | user_id + shop_id PK (複合キー) |
| `coupons` | クーポン | id, shop_id FK, title, description, active |
| `coupon_uses` | クーポン利用 | id, coupon_id FK, user_id FK, code, UNIQUE(coupon_id, user_id) |
| `attendance` | 出勤管理 | id, shop_id FK, staff_id, name, start_time, end_time, work_date |
| `ad_slots` | 広告枠 | id, slot_key (A/B/C), page, type (banner/adsense/pr_native), title, body, link_url |
| `inquiries` | お問い合わせ | id, type, name, email, body, status (open/done) |
| `password_resets` | パスワード再設定 | id, user_id FK, token_hash, expires_at, used |
| `email_verifications` | メール認証（仮登録） | id, email UNIQUE, password_hash, nickname, code_hash, attempts, expires_at |

`shops` を中心に `shop_images`, `shop_tags`, `shop_prices`, `shop_staff`, `reviews`, `coupons`, `attendance` が1対多でリレーションを持ちます。`users` は `favorites`, `reviews`, `coupon_uses`, `password_resets` と1対多です。`favorites` は user_id + shop_id の複合主キーで、`coupon_uses` は UNIQUE(coupon_id, user_id) で重複利用を防止します。`email_verifications` は新規登録時の仮データを保持し、メール認証完了後に `users` へ移行します。

## Screen Flow

トップページ (index.php) から店舗検索で一覧を表示し、店舗詳細 (shop.php) で口コミ投稿・お気に入り追加・クーポン取得・スタッフ紹介を確認できます。地図検索 (map.php) からピン選択で店舗詳細に遷移します。マイページ (mypage.php) でお気に入り一覧とクーポン一覧を管理します。新規登録 (register.php) はメール認証 (verify.php) を経て完了します。

## Security

| 項目 | 実装 |
|------|------|
| 認証 | メール認証付き新規登録 + bcrypt パスワードハッシュ |
| 管理者認証 | Basic 認証 (.htaccess) + アプリ内認証 (ADMIN_KEY) の二重認証 |
| CSRF | セッショントークン検証（全フォーム） |
| 口コミ管理 | 承認制（pending → approved）で品質管理 |
| 画像アップロード | MIME タイプ・拡張子・サイズのバリデーション |
| XSS 対策 | `htmlspecialchars(ENT_QUOTES, 'UTF-8')` で全出力をエスケープ |

## Tech Stack

| 項目 | 内容 |
|------|------|
| Backend | PHP 8.x (strict_types) |
| Database | MySQL 8.0 (15 テーブル) |
| Auth | メール認証・bcrypt・セッション管理 |
| Map | Google Maps |
| Admin | Basic 認証 + アプリ内認証 |
| Security | CSRF, 画像アップロードバリデーション |
| PWA | Web App Manifest |

## Directory Structure

```
machinavi/
├── index.php           # トップページ（店舗一覧・検索）
├── shop.php            # 店舗詳細・口コミ
├── map.php             # 地図ページ
├── mypage.php          # マイページ（お気に入り）
├── staff.php           # スタッフ紹介
├── register.php        # 新規登録
├── login.php / logout.php
├── contact.php         # お問い合わせ
├── guide.php           # 利用ガイド
├── privacy.php / terms.php
├── sitemap.php         # サイトマップ自動生成
├── db.php / auth.php   # DB接続・認証
├── admin/              # 管理画面（店舗編集・広告管理）
├── set/
│   ├── config.php      # 設定（config.example.php を参照）
│   └── schema.sql      # テーブル定義
└── uploads/            # ユーザーアップロード画像
```

## Setup

1. `set/config.example.php` を `config.php` にコピーし、公開ディレクトリの外に配置
2. `set/schema.sql` を MySQL に流す
3. `admin/.htaccess` の AuthUserFile パスを設定
