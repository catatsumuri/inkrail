---
title: 開発・カスタマイズ
description: 機能を拡張する場所、翻訳の更新手順、テストと静的解析の実行方法。
---

この章では、スターターキットをアプリケーションに合わせて変更するときの入口を紹介します。画面、処理、翻訳を変更した後は、既存のテストと静的解析で影響を確認してください。

## ディレクトリ構成と拡張箇所

Laravel側で認証・データ更新・権限判定を行い、Inertiaを通じてReactの画面へデータを渡します。画面にボタンを表示するかどうかに加えて、サーバー側でも操作の権限や本人確認を検証します。

| 場所 | 主な役割 |
| --- | --- |
| `routes/web.php`・`routes/settings.php` | 画面と操作のルート、認証・本人確認・レート制限の適用 |
| `app/Http/Controllers/`・`app/Http/Requests/` | リクエストの受付、入力検証、レスポンスの作成 |
| `app/Actions/` | セッション失効、通知の送信、セキュリティイベントの記録などの処理 |
| `app/Providers/` | 認証処理の設定、イベントリスナー、閲覧権限の定義 |
| `app/Notifications/` | データベース・メール・Slack向けの通知形式 |
| `resources/js/pages/` | Inertiaで表示するReactの画面 |
| `resources/js/components/` | 画面間で共有するコンポーネント |
| `resources/js/layouts/` | 認証画面、ログイン後の画面、設定画面の共通レイアウト |
| `resources/css/app.css` | アプリケーションのスタイル |
| `config/` | セッション、通知先、認証、イベント保持期間などの設定 |
| `database/migrations/` | テーブル構造の変更 |
| `tests/Feature/`・`tests/Unit/` | 機能テストと単体テスト |

### 画面とルートを追加する

Reactの画面は `resources/js/pages/` に追加し、LaravelのルートやコントローラーからInertiaのページとして返します。共通レイアウトの選択は `resources/js/app.tsx` にあります。`auth/` 配下には認証画面用、`settings/` 配下にはアプリケーションと設定画面用のレイアウトを適用します。

フロントエンドから参照するコントローラーやルートのTypeScriptコードは、WayfinderのViteプラグインが生成します。`resources/js/actions/`・`resources/js/routes/`・`resources/js/wayfinder/` は生成先なので、ルートの変更はPHP側で行ってください。

### 追加機能を変更する

セッション管理の処理は `app/Actions/Sessions/`、通知の配信処理は `app/Actions/Notifications/` にあります。通知形式を変える場合は `GeneralNotification`、アカウント変更時の送信条件を変える場合は `SecurityNotificationServiceProvider` を確認してください。

セキュリティイベントの記録には `LogSecurityEvent` を使います。成功した状態変更は保存が完了したことを確認してから記録し、拒否された操作は `failure()` で記録します。追加情報には、パスワードやトークン、入力値全体などを渡さないでください。

認証機能の設定は `config/fortify.php` と `FortifyServiceProvider` にあります。Chiselで選択した認証機能によって、利用できる画面や処理が変わります。変更時には、その機能を利用しない構成への影響も確認してください。

詳しい要件は、[アカウント保護](../security/)、[セッション管理](../sessions/)、[セキュリティイベント](../events/)、[通知と運用アラート](../notifications/)を参照してください。

## 翻訳の追加

PHP側ではLaravelの `__()`、React側では `@erag/lang-sync-inertia` の `lang()` から取得した `__()` を使って翻訳します。原文の英語をキーにして、日本語などの訳文と対応させます。

```tsx
import { lang } from '@erag/lang-sync-inertia/react';

export function NoticeHeading() {
    const { __ } = lang();

    return <h2>{__('Maintenance notice')}</h2>;
}
```

### 翻訳の原稿を更新する

このプロジェクトで追加する原文は `lang/vendor-patches/source/react.json`、日本語訳は `lang/vendor-patches/locales/ja.json` に記載します。同じキーを両方へ追加してください。

原文側の例です。

```json
{
    "Maintenance notice": "Maintenance notice"
}
```

日本語側の例です。

```json
{
    "Maintenance notice": "メンテナンスのお知らせ"
}
```

原稿を変更したら、プロジェクトのルートで次を実行します。

```sh
composer patch-vendor
php artisan lang:update
```

`composer patch-vendor` は `scripts/patch-vendor.php` を実行し、原稿を `vendor/laravel-lang/starter-kits/` へ反映します。その後、`lang:update` が各言語の翻訳ファイルを更新します。Composerの依存関係をインストール・更新したときにも、この反映処理が実行されます。

:::note[編集するのは原稿です]
`vendor/` 内の翻訳と、生成された `lang/ja.json` などを直接編集しても、依存関係や翻訳の更新で上書きされる場合があります。Gitで管理する `lang/vendor-patches/` の原稿に変更を残してください。
:::

### 使用する言語

既定の言語は `.env` の `APP_LOCALE`、フォールバック先は `APP_FALLBACK_LOCALE` で設定します。たとえば、日本語を既定にする場合は次のように指定します。

```dotenv
APP_LOCALE=ja
APP_FALLBACK_LOCALE=en
```

設定をキャッシュしている環境では `php artisan config:cache` で更新してください。言語を追加するときは、対象言語の翻訳データも用意し、画面とサーバー側のメッセージを確認します。変数を含む文字列では、`:browser` などのプレースホルダーを訳文にも残し、表示時に値へ置き換わることを確認してください。

## テストと静的解析

各テストの英語キーと日本語の確認内容は、[テスト項目一覧](./tests/)に掲載しています。

コマンドはプロジェクトのルートで実行します。Sailを使う場合は、対応する `vendor/bin/sail composer`・`vendor/bin/sail npm`・`vendor/bin/sail artisan` から実行してください。

### 一通りの検査を実行する

```sh
composer ci:check
```

CIと同じ入口です。フロントエンドのコード検査とTypeScriptの型検査に続き、PHPの設定キャッシュのクリア、Pintによる整形確認、PHPStanによる静的解析、PHPUnitのテストを実行します。

### 検査を個別に実行する

| コマンド | 内容 |
| --- | --- |
| `npm run check` | Vite+によるフロントエンドのコード検査 |
| `npm run types:check` | TypeScriptの型検査 |
| `composer lint:check` | PintによるPHPの整形確認 |
| `composer types:check` | Larastanを使ったPHPStanの静的解析（レベル7） |
| `composer test` | 設定キャッシュのクリア、PHPの整形確認・静的解析、テスト |
| `php artisan test --filter=NotificationTest` | 指定した名前に一致するテストの実行 |
| `npm run build` | フロントエンドの本番用ビルド |

整形などの修正を適用する場合は `composer lint`、フロントエンドでは `npm run check:fix` を使います。これらはファイルを書き換えるため、実行後に差分を確認してください。

### テスト用のデータベース

テストの環境設定は `phpunit.xml` にあります。データベースを使うテストには `RefreshDatabase` を使用しているため、開発中や本番のデータベースと分けた、テスト専用の接続先を用意してください。必要に応じて `.env.testing` で設定します。

`php artisan test` を直接実行する前に設定キャッシュが残っている場合は、`php artisan config:clear` を実行してください。`composer test` はこの処理を含みます。

認証や権限を変更した場合は、成功時だけでなく、未ログイン・権限不足・本人確認の失敗時も確認します。通知やログの変更では、配信・記録に失敗しても元のアカウント操作を妨げないことも確認してください。
