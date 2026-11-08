# ドキュメントの編集

Astro Starlight による日本語・英語ドキュメントです。Node.js 24.22.2 以上を使用します。
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

日本語は `src/content/docs/`、英語は `src/content/docs/en/` 以下に
同じ相対パスの Markdown または MDX ファイルを追加します。
例: `sessions/index.md` と `en/sessions/index.md` が対応する翻訳です。
ヘッダーの言語メニューから、同じページの日本語・英語を切り替えられます。
各ファイルの先頭には `title` と `description` を指定してください。
既存の章のフォルダーに追加したページはサイドバーに自動表示されます。
章を増やす場合は `astro.config.mjs` の `sidebar` も更新します。
グループ名には `translations.en` で英語のラベルを指定します。

## デザイン

共通スタイルは `src/styles/custom.css`、トップページは各言語の `index.mdx`、
機能カードは `src/components/FeatureGrid.astro` で管理します。
ライト・ダークテーマとモバイル表示に対応しています。

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

英語版: <https://catatsumuri.github.io/laravel-react-secure-starter-kit/en/>

リポジトリ名や公開先を変更する場合は `astro.config.mjs` の `site` と `base` を更新してください。

参考: [Starlight](https://starlight.astro.build/)、[Astro の GitHub Pages ガイド](https://docs.astro.build/en/guides/deploy/github/)
