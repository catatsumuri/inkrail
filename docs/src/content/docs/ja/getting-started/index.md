---
title: システム要件
description: このスターターキットの導入に共通するシステム要件。
sidebar:
  order: 0
---

ローカルで開発するために必要な環境をまとめています。まずは **PHP 8.4、Composer 2、Node.js 24、SQLite** を揃える構成で始められます。

## 共通の要件

| 項目 | 要件・推奨環境 |
| --- | --- |
| PHP | 8.4 以上の 8.x |
| PHP 拡張 | XML・BCMath と、利用するデータベースの PDO ドライバー。その他は下記の「PHP 拡張の確認」を参照 |
| Composer | 2.2 以上の 2.x |
| Node.js | 24.11 以降の 24.x。24.22.2 以降を推奨 |
| npm | Node.js に同梱されるもの |
| データベース | 初期設定は SQLite。SQLite 3.26 以上と PHP の `pdo_sqlite` 拡張が必要 |

Node.js は React の開発サーバーとビルドに使います。上記の推奨バージョンなら、このリポジトリのドキュメントも起動できます。

SQLite はファイルにデータを保存するため、別途データベースサーバーを立てずに始められます。MySQL や PostgreSQL を使う場合は、サーバーと接続設定、対応する PHP の PDO ドライバーを用意してください。[Laravel のデータベース要件](https://laravel.com/docs/13.x/database#introduction)も参照できます。

## 導入方法ごとに用意するもの

| 方法 | 自分の PC に用意するもの |
| --- | --- |
| Laravel Installer（`laravel new`） | 上記の PHP・Composer・Node.js・npm と Laravel Installer |
| Laravel Sail | 起動済みの Docker と Docker Compose。macOS・Linux に対応し、Windows は WSL2 を使います |

Sail では、アプリを動かす PHP・Composer・Node.js をコンテナ内で使います。初回のプロジェクト作成や依存関係の準備は、Sail の導入手順で説明します。コンテナにも上記のバージョン条件を満たす環境を選びます。[Sail の対応環境](https://laravel.com/docs/13.x/sail#introduction)を参照してください。

## PHP 拡張を用意する

**Composer をインストールしただけでは、必要な PHP 拡張が揃わない場合があります。** XML・BCMath が不足すると Composer の依存関係の導入で、SQLite ドライバーが不足するとマイグレーションで止まります。

Debian の ARM64 環境（PHP 8.4）では、Composer のインストール後に次のパッケージを追加し、アプリの作成から初期画面の表示まで確認しました。

| パッケージ | 確認した段階 |
| --- | --- |
| `php-xml`、`php-bcmath` | Composer の依存関係インストール |
| `php-sqlite3` | SQLite を使うデータベースのマイグレーション。ドライバー不足で失敗したため追加 |

```sh
sudo apt install php-xml php-bcmath php-sqlite3
```

パッケージ名は利用する OS と PHP のバージョンに合わせてください。導入の流れは [Laravel Installer の手順](./laravel-new/)で説明しています。

<details>
<summary>PHP 拡張の確認</summary>

[Laravel に必要な PHP 拡張](https://laravel.com/docs/13.x/deployment#server-requirements)に加え、このキットの依存パッケージやテスト用の拡張も必要です。主に `bcmath`、`curl`、`dom`、`fileinfo`、`iconv`、`mbstring`、`openssl`、`pdo`、`xml`、`xmlwriter` と、利用するデータベースの PDO ドライバーを有効にしてください。PHP 標準の拡張も必要ですが、多くは通常の PHP 配布物に含まれています。

ログ表示ツールの Pail を使う場合は `pcntl` も必要です。

依存パッケージのインストール後は、次のコマンドで不足を確認できます。Sail を使う場合はコンテナ内で実行してください。

```sh
composer check-platform-reqs
```

</details>

## このキットの設定について

- **セッションを失効する機能（revoke）には `SESSION_DRIVER=database` が必要です。** Redis や file などでもアプリは利用できますが、このキットの失効機能は使えません。
- **メール配信サービスは、起動だけなら不要です。** 初期設定の `MAIL_MAILER=log` はメールの内容をログに出力します。実際に届ける場合はメーラーを設定します。
- **Redis や Slack は必須ではありません。** 初期設定ではセッション・キュー・キャッシュにデータベースを使います。

## 次に進む

- [Laravel Installer（laravel new）](./laravel-new/)
- [Laravel Sail](./sail/)
