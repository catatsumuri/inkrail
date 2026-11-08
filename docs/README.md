# ドキュメントの編集

Astro Starlight による日本語ドキュメントです。Node.js 24.22.2 以上を使用します。
依存関係はアプリケーションから独立して管理します。

## ローカル起動

リポジトリのルートから実行します。

```sh
cd docs
npm ci
npm run dev
```

表示先: `http://localhost:4321/laravel-react-secure-starter-kit/`

## ページの追加

`src/content/docs/` 以下に Markdown または MDX ファイルを追加します。
各ファイルの先頭には `title` と `description` を指定してください。
既存の章のフォルダーに追加したページはサイドバーに自動表示されます。
章を増やす場合は `astro.config.mjs` の `sidebar` も更新します。

## ビルドの確認

```sh
npm run build
npm run preview
```

静的ファイルは `dist/` に出力されます。

## GitHub Pages

GitHub リポジトリの **Settings → Pages → Build and deployment → Source** を
**GitHub Actions** に設定してください。

`main` へのドキュメント変更の push で `.github/workflows/docs.yml` がビルド・デプロイします。
Pull Request ではビルドのみ実行します。Actions から手動実行もできます。

公開先: <https://catatsumuri.github.io/laravel-react-secure-starter-kit/>

リポジトリ名や公開先を変更する場合は `astro.config.mjs` の `site` と `base` を更新してください。

参考: [Starlight](https://starlight.astro.build/)、[Astro の GitHub Pages ガイド](https://docs.astro.build/en/guides/deploy/github/)
