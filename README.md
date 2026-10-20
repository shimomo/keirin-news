# keirin-news

競輪の選手ニュースを中心に、新聞・競輪メディア・公式・競輪場の見出しを GitHub Actions で定期的に（実際は 1 日に数回）集めて、1 日ごとに 1 ページで眺めるための仕組み。
収集には [keirin-news-collector](https://github.com/shimomo/keirin-news-collector) を使う。オートレース版の [autorace-news](https://github.com/shimomo/autorace-news) と同じ作り。
GitHub Actions が収集してリポジトリにコミットし、GitHub Pages がそれを配信する。サーバーもデータベースも使わない。

ページ: https://shimomo.github.io/keirin-news/

## 何が見えるか

- 1 日 1 ページ。記事は「新聞・メディア」（日刊スポーツ・netkeirin・競輪スポニチ・More CADENCE）→「公式・団体」（KEIRIN.JP・月刊競輪WEB・けいりんマルシェ・ガールズケイリン・選手会FC・養成所・CTC）→「競輪場のお知らせ」（競輪場 29 場）の 3 段に分かれる。選手の話題が多い新聞・メディアが先頭
- カードには見出し（記事へのリンク）・配信元・分類・時刻（JST）だけを出す。集めた記事は除外せずすべて出す。KEIRIN.JP・川崎・伊東は日付しか持たないので時刻を出さない
- 「‹ 前日」「翌日 ›」で記事のある過去の日に戻れる。URL の `#2026-10-19` でも指定できる
- 取得に失敗した配信元があると、ページ上部に注意が出る（次の収集で成功すれば消える）。最後の収集から 24 時間を超えると警告が出る（GitHub Actions の定期実行は 1 日に数回しか動かないことがあるため）

KEIRIN.JP・けいりんマルシェ・月刊競輪WEB（JKA）、netkeirin、競輪スポニチは、私的利用・引用の範囲を超える複製や転載を禁じているため、公開するのは見出しとリンクだけにしている。
本文・要約・画像はページにもデータファイルにも書き出さない。

## 仕組み

```
GitHub Actions（毎時 47 分ごろ）
  php bin/collect
    ├─ CollectorFactory::create()->collect(since)   ← keirin-news-collector（40 配信元。うち 9 か所は RSS に差し替え）
    ├─ 既存の docs/data/*.json と (source, id) で突き合わせて足す・上書きする
    └─ docs/data/YYYY-MM-DD.json と docs/data/index.json を書き出す
  → main ブランチにコミット & push
GitHub Pages（main ブランチの /docs）
  index.html + app.js が JSON を読んで 1 日分を 3 段で描く
```

- 初回（`docs/data/index.json` が無い、または記事のある日が無い）は、各配信元が 1 回で返せる分をすべて取り込む。2 回目以降は直近 48 時間分を毎回取り直す。古い日のデータは消さない
- 月刊競輪WEB・いわき平・千葉（PIST6）・名古屋・京都向日町・岸和田・防府・佐世保・熊本は、ライブラリの WordPress REST API ではなく RSS（各最新 10 件）で読む。これらのサイトは GitHub Actions の IP からの REST API を 403 で拒むため（トップページと RSS は通る）。ID はライブラリの WordPress 版と同じ「投稿 ID-公開日」。月刊競輪WEB は毎週まとめて 8 本ほど出すので、RSS を 2 ページ（20 件）読む
- 熊本の RSS からは「発売レース情報ピックアップ」（他場の場外発売の告知）を除く。ライブラリの REST 版と同じ結果にするため
- 弥彦（REST が 403 で RSS も無い）と和歌山（サイト全体が 403）は、Actions から取得できないので対象外
- 一部の配信元の失敗では Actions の実行を失敗にしない（一時的な失敗のたびに通知が来ないように）。失敗は job summary とページの注意に出る。すべての配信元が失敗したときと、スクリプト自体が落ちたときだけ赤くなる
- 記事 1 件のデータ不備（必須項目の欠落など）は、その配信元から記事が 1 件も返らなかったときだけ注意に出す。KEIRIN.JP のトピック一覧に壊れた項目が残っていて、毎回同じ失敗が出るため。ページに出さない不備は Actions の job summary に出る
- KEIRIN.JP のトピックの本文の取得が一時的に失敗すると、見出しとリンクは載るが「KEIRIN.JP の取得に失敗しました」の注意が出ることがある

## セットアップ

1. リポジトリを作成して SSH で push する（public。Pages を無料で使うため。`gh` のトークンに `workflow` 権限が無いと `.github/workflows/` を含む push が通らない）
2. Pages を有効化する（`main` ブランチの `/docs` を配信）

   ```bash
   gh api -X POST repos/shimomo/keirin-news/pages \
     -f build_type=legacy -f 'source[branch]=main' -f 'source[path]=/docs'
   ```

3. 最初の収集を手動で実行する

   ```bash
   gh workflow run collect.yml
   gh run watch
   ```

   数分後に https://shimomo.github.io/keirin-news/ に記事が出る。

以降は毎時 47 分ごろに自動で動く。GitHub の cron は数十分遅れることがある。

### 初回に一部の配信元が失敗したとき

その配信元の古い記事は取り込まれないまま、2 回目以降（直近 48 時間分）に進む。
取り直したいときは、Actions の収集が動いていない時間に `docs/data/index.json` を消してコミットし、もう一度手動で実行する。
`(source, id)` で突き合わせるので、記事が重複することはない。
手元のクローンは毎時のデータコミットで遅れているので、先に `git pull` する。

```bash
git pull --rebase
git rm docs/data/index.json
git commit -m "data: refetch everything"
git push
gh workflow run collect.yml
```

## ローカルで動かす

`docs/data/` は Actions がコミットする場所なので、手元で集めて画面を見るときは、
`docs/` を一時ディレクトリに写してそこへ書く（`docs/data/` に書くと作業ツリーが汚れ、次の `git pull` が止まる）。

```bash
composer install
tmp=$(mktemp -d) && cp -r docs/. "$tmp"
php bin/collect "$tmp/data"             # 引数なしなら docs/data/ に書く。2〜3 分かかる
python3 -m http.server 8765 -d "$tmp"   # http://localhost:8765/
composer test                           # PHPUnit
composer lint && composer analyse       # Pint / Psalm
node --test "tests/js/*.test.mjs"       # app.js のテスト（Node.js 22 以上）
```

テストはネットワークに出ない。

## 構成

- `bin/collect` 収集の入口
- `src/` 収集側のコード（PHP 8.4）。`CollectorFactory` が配信元の差し替えを担う
- `tests/` PHPUnit のテスト、`tests/js/` app.js のテスト
- `docs/` Pages の配信ルート。`index.html` `app.js` `style.css` と `data/`
- `docs/superpowers/` 設計書と実装計画
