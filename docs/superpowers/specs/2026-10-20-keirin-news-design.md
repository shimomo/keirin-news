# keirin-news 設計書

作成日: 2026-10-20

## 1. 目的と背景

競輪の選手に関するニュース（主に新聞や競輪メディアが取り上げる内容）を、1 日 1 回確認するための一覧ページ。
[keirin-news-collector](https://github.com/shimomo/keirin-news-collector)（以下「ライブラリ」）で集めた記事の**タイトル・リンク・配信元・日時だけ**を、GitHub Pages で公開する。

利用者は作者本人。ページは公開されるが、ほかの人に見せることは目的ではない。

同じ作者の [autorace-news](https://github.com/shimomo/autorace-news)（オートレース版。以下「autorace-news」）と**同じ仕組み**で作る。
autorace-news の設計書 `~/github/shimomo/autorace-news/docs/superpowers/specs/2026-10-20-autorace-news-design.md` が土台で、この設計書はそこからの違いと、競輪に固有の決定を書く。
ここに書いていない振る舞いは autorace-news と同じにする。

### なぜ作るか

- 競輪では、選手に焦点を当てたニュースを見落とさずに追いたい
- 収集元は 42 か所あるが、競輪場 31 場の公式サイトはイベント・ファンバス・設備点検などの案内がほとんどで、選手の話題はほぼない（2026-10-20 に 1 週間分を集めて確認）。選手の話題は新聞・競輪メディアに集まっている

## 2. ゴール / 非ゴール / アンチゴール

### ゴール

- GitHub Actions が 1 時間ごとに自動で収集し、手作業なしで更新され続ける
- 1 日分の記事が「新聞・メディア」「公式・団体」「競輪場のお知らせ」の 3 段に分かれて見える。新聞・メディアが先頭、競輪場のお知らせが最後
- 日付単位で過去に戻れる
- 収集が止まっている、または一部の配信元で失敗していることに、ページ上で気づける

### 非ゴール（今回は作らない）

- 弥彦競輪と和歌山競輪の収集（GitHub Actions から取得できないため。5.3）
- 記事の除外・絞り込み・検索。集めた記事はすべて出す（例外は 5.3 の熊本の「発売レース情報ピックアップ」。ライブラリ自体が除いている分）
- 本文・要約・サムネイル画像の表示
- 同じ話題を複数の媒体が書いた記事の束ね
- 通知、既読管理、「前回見たあとに増えた分」の目印
- ライブラリにない情報源の追加、作者の自前サーバー（ライブラリの `tools/smoke.php` にある「autorace サーバ」）での収集

### アンチゴール（これになったら失敗）

- 見出し・リンク・配信元・日時以外（本文、要約、画像）を公開してしまう
- 選手の話題が、競輪場の案内に埋もれて目に入らない
- 収集が止まっているのに、ページ上で気づけない
- ページを見るのにビルド工程が必要になる

## 3. 制約

- GitHub Actions と GitHub Pages だけで動かす。サーバー、DB、外部サービス、追加の Secrets は使わない
- 収集は PHP。ライブラリ `shimomo/keirin-news-collector`（Packagist、`^0.1`、PHP 8.4 以上）を Composer で入れて使う。`composer.json` の `config.platform.php` は autorace-news と同じ `8.4.3`（PHPUnit 13 の依存が 8.4.3 以上を要るため）
- 表示ページは素の HTML・CSS・JavaScript。ビルド工程を持たない
- デザインは autorace-news の `docs/`（`index.html`・`style.css`・`app.js`）と同じ。さらにその元は tech-trends
- リポジトリは public の `shimomo/keirin-news`。ページは `https://shimomo.github.io/keirin-news/`。ローカルの作業フォルダは `~/github/shimomo/keirin-news`
- KEIRIN.JP・けいりんマルシェ・月刊競輪WEB（JKA）、netkeirin、競輪スポニチは、私的利用・引用の範囲を超える複製や転載を禁じている。公開するのは見出しとリンクだけにとどめ、本文・要約・画像はデータファイルにも書き出さない（autorace-news と同じ線）
- 作者のコミットは GPG 署名付き。署名にはパスフレーズが要るので、作業前に作者がターミナルで gpg-agent に覚えさせる（autorace-news の作業時と同じ）

## 4. 全体像

```
[GitHub Actions: 1 時間ごと（毎時 47 分）]
   php bin/collect
     ├─ CollectorFactory::create()->collect(since)   ← ライブラリ（40 配信元。うち 9 か所は RSS に差し替え）
     ├─ 既存の docs/data/*.json と (source, id) で突き合わせて足す・上書きする
     └─ docs/data/YYYY-MM-DD.json と docs/data/index.json を書き出す
   → main ブランチにコミット & push
[GitHub Pages: main ブランチの /docs]
   index.html + app.js が JSON を読んで 1 日分を 3 段で描く
```

### コードの作り方

autorace-news（`main` の `54f2d12` 以降）のコードを写し、名前を競輪向けに変える。

- 名前空間 `Shimomo\AutoraceNews` → `Shimomo\KeirinNews`、ライブラリの名前空間 `Shimomo\AutoraceNewsCollector` → `Shimomo\KeirinNewsCollector`
- そのまま写すもの（中身は同じ）: `StoredArticle`、`ArticleMerger`、`MergeResult`、`DataStore`、`bin/collect`、それぞれのテスト、`tests/Support/`、`.github/workflows/`（cron だけ変える）、`.editorconfig`・`.gitignore`・`pint.json`・`phpunit.xml.dist`・`psalm.xml.dist`・`LICENSE`
- 変えるもの: `FailureNotice`・`FailureSummary`・`CollectRun`・`RunReport`（5.4）、`CollectorFactory`（5.3）、`docs/`（6 章）、`README.md`
- 写さないもの: `KawaguchiFeedSource` とそのテスト・フィクスチャ（オートレース固有）

## 5. 収集

### 5.1 実行

- `collect.yml` を cron `47 * * * *` と `workflow_dispatch` で動かす。autorace-news（毎時 17 分）とずらす
- チェックアウトはブランチの先頭（`ref: ${{ github.ref_name }}`）。autorace-news のレビューで直した点
- 1 回の収集は 2〜3 分（40 配信元に 1 秒間隔）。`timeout-minutes: 15` のまま

### 5.2 取得範囲

autorace-news と同じ（初回は `since` なし、2 回目以降は現在時刻 − 48 時間、`(source, id)` で上書き、古いデータは消さない）。

ライブラリについて知っておくこと:

- WordPress 系と RSS 差し替え分（5.3）の `id` は `{投稿ID}-{公開日 YYYYMMDD}`。同じ投稿を日付と中身だけ書き換えて再掲するサイトがあるため、再掲は別の記事として扱われる（ライブラリの方針どおり）
- KEIRIN.JP・川崎・伊東は日付しか持たず、`publishedAt` は 00:00。ライブラリが `since` を日単位に切り下げて比べる
- 日刊スポーツの Atom は最新 10 件だけ。G1 開催中でも 1 時間に 2 本程度なので、毎時の収集で足りる

### 5.3 収集元（CollectorFactory）

`CollectorFactory::create(?HttpFetcher $fetcher = null): Collector` が `bin/collect` の Collector を組む。

1. `Collector::default($fetcher)` から、次の 11 キーを `without()` で外す: `yahiko`、`wakayama`、`keirin-web`、`iwakidaira`、`chiba`、`nagoya`、`mukomachi`、`kishiwada`、`hofu`、`sasebo`、`kumamoto`
2. 外した 11 キーのうち 9 キーを、ライブラリの `FeedSource` ＋ `FeedSite`（`IdRule::wordPressGuid()`、本文は既定の `BodyMode::Feed`）で足し直す。同じ `HttpFetcher` を共有する

| key | 表示名（`FeedSite` の name） | フィード URL | 追加の設定 |
|---|---|---|---|
| `keirin-web` | 月刊競輪WEB | `https://keirin-web.com/feed/` と `https://keirin-web.com/feed/?paged=2` | 2 ページ読む（実装時の追加。毎週まとめて 8 本ほど出し、10 件では溢れかけるため。重なりは `(source, id)` で吸収） |
| `iwakidaira` | いわき平競輪 | `https://tairakeirin.com/feed/` | |
| `chiba` | PIST6（千葉） | `https://chiba.pist6.com/feed` | 末尾 `/` なし（`/feed/` は 301 でここへ転送される） |
| `nagoya` | 名古屋競輪 | `https://nagoyakeirin.com/feed/` | |
| `mukomachi` | 京都向日町競輪 | `https://kyoto-mukomachikeirin.com/feed/` | |
| `kishiwada` | 岸和田競輪 | `https://chalionkun.com/feed/` | |
| `hofu` | 防府競輪 | `https://www.hofukeirin.jp/blog/feed/` | `fixedCategories: ['ホープくん日記']`（ライブラリの REST 版と同じ） |
| `sasebo` | 佐世保競輪 | `https://www.sasebokeirin.jp/feed` | 末尾 `/` なし（同上） |
| `kumamoto` | 熊本競輪 | `https://www.kumamotokeirin.jp/feed/` | 分類に「発売レース情報ピックアップ」を含む記事を除く（下記） |

- **なぜ差し替えるか:** この 9 キーのサイトは、GitHub Actions の IP からの WordPress REST API を 403 で拒む（Xserver などの国外 IP 制限。autorace-news の川口と同じ）。トップページと RSS は Actions から 200 で取れる（2026-10-20、ライブラリの一時ブランチで確認。`chiba`・`sasebo` は 301 の転送先を手元で確認）
- **熊本の除外:** ライブラリの REST 版は分類 ID 160「発売レース情報ピックアップ」（他場の場外発売の告知）を除いている。RSS では除けない（2026-10-20 時点で 10 件中 6 件がこの分類）ので、`FeedSource` を包んで、`categories` にこの名前を含む記事を落とすソース（`Contracts\Source` の実装）を足す。名前・キーは包んだ `FeedSource` のものをそのまま返し、失敗も素通しする
- **弥彦（`yahiko`）:** REST は 403、`/feed/` は 404 で、Actions から取る手段が無い。対象外
- **和歌山（`wakayama`）:** サイト全体が Actions からも作者の PC からも 403。対象外
- RSS は各最新 10 件。毎時の収集なら足りる。過去分は初回に取り込める 10 件まで

配信元キーは 40 種（ライブラリの既定 42 − 2）。

### 5.4 失敗の扱い

autorace-news と同じ（記事が返ってきている URL の失敗は数えない、配信元ごとに 1 件、全配信元キーが失敗したときとスクリプトが落ちたときだけ Actions を赤にする）。
記事ページを取るソース（日刊スポーツ、川崎・伊東の news.xml、本文を記事ページから取る FeedSource の函館・宇都宮・立川・別府・静岡）のページ取得失敗は、失敗の URL が記事の URL と一致するので数えない。

**実装時の追加（2026-10-20）:** 例外を伴わない失敗（記事 1 件のデータ不備。必須項目の欠落、日付の読み取り失敗、空のタイトルなど）は、その配信元から記事が 1 件も返らなかったときだけ数える。
KEIRIN.JP のトピック一覧に必須項目の欠けた項目が 1 つ残っており、毎回の収集で同じ失敗が出てページの注意が消えなくなるため（作者の PC から 48 時間分で確認）。
書式が変わって全件が読めなくなった場合は記事が 0 件になるので、注意は出る。取得の失敗（HTTP エラーなど、例外を伴うもの）は今までどおり数える。
ページに出さない不備は、Actions の job summary に配信元ごとに出す（`FailureSummary::itemDefects()` → `RunReport`）。一部の項目だけ読めない場合や、同じキーに複数のフィードがある競輪場（玉野・函館・平塚など）で 1 つのフィードの全項目が読めない場合は、ページには出ず job summary でだけ分かる。

**KEIRIN.JP は例外で、失敗として数える（作者が受け入れ済み）:**

- トピックスの本文断片（`/pc/dfw/portal/guest/news/…html`）の取得に失敗すると、失敗の URL は断片の URL、記事の URL は `/pc/topicsdetail?nnum=…` で一致しない。見出しとリンクは載るが、ページに「KEIRIN.JP の取得に失敗しました」の注意が出る
- グレードレースレポートは、ページが取れないと記事自体が返らないので、当然失敗に数える
- 2026-10-20 の 2 回の収集（作者の PC と Actions）では KEIRIN.JP の失敗は 0 件。一時的な失敗のときだけ注意が出る（URL の形で除く案は、本物の失敗まで隠れるので採らない。ライブラリを直す案も今回は採らない）

**autorace-news との違い:** `index.json` の `failures` に配信元の表示名を持たせる。

```json
{ "source": "kumamoto", "sourceName": "熊本競輪", "message": "GET https://www.kumamotokeirin.jp/feed/ returned HTTP 503." }
```

- `FailureNotice` を `(string $source, string $sourceName, string $message)` にし、`toArray()` を上の 3 キーにする
- `sourceName` は `Collector::sources()` のうち同じキーを持つ最初の Source の `name()`
- 競輪場は 31 あり、ページの表示名を全部 `app.js` に持たせないため（autorace-news では「表にない配信元はキー名が出る」小さな問題として見送った点を、ここでは最初から直す）

### 5.5 書き出すファイル

autorace-news と同じ形（日ファイルの記事は 7 項目、`index.json` は `dates`・`checkedAt`・`failures`）。違いは 5.4 の `failures[].sourceName` だけ。

## 6. ページ

画面見本: `~/github/shimomo/autorace-news/.superpowers/brainstorm/*/content/keirin-layout.html`（作業フォルダにだけある。git 管理外）。作者が確認済み。

autorace-news の `docs/` を写し、次を変える。

### 6.1 名前と文言

- `<title>` と見出し: `Keirin News`
- `meta description`: 「競輪の選手ニュースを中心に、新聞・競輪メディア・公式・競輪場の見出しを1日ごとにまとめた一覧」
- favicon: autorace-news・tech-trends とタブで見分けがつく色と絵柄（作る人に任せる）

### 6.2 段の割り当て

| 段 | id | 配信元（source） | 空のときの文言 |
|---|---|---|---|
| 新聞・メディア | `news` | `nikkansports`、`netkeirin`、`keirinsponichi`、`morecadence` | この日の新聞・メディアの記事はありません。 |
| 公式・団体 | `official` | `keirin.jp`、`keirin-web`、`keirin-marche`、`girls-keirin`、`keirin-fc`、`jik`、`ctc` | この日の公式・団体の記事はありません。 |
| 競輪場のお知らせ | `track` | 上記以外すべて（競輪場 29 場） | この日の競輪場のお知らせはありません。 |

- 段の順は上の表のとおりで固定。段の中は `publishedAt` の新しい順
- 表にない source は「競輪場のお知らせ」に入れる（ライブラリに今後増えるのは競輪場の見込みが高いため。autorace-news では公式に入れていた点の違い）

### 6.3 バッジ

| source | 文字 | 色 |
|---|---|---|
| `nikkansports` | 日刊スポーツ | 新聞色（ライト `#0969da` / ダーク `#58a6ff`） |
| `netkeirin` | netkeirin | 新聞色 |
| `keirinsponichi` | 競輪スポニチ | 新聞色 |
| `morecadence` | More CADENCE | 新聞色 |
| `keirin.jp` | KEIRIN.JP | 公式色（`#d9480f` / `#ff8c4b`） |
| `keirin-web` | 月刊競輪WEB | 公式色 |
| `keirin-marche` | けいりんマルシェ | 公式色 |
| `girls-keirin` | ガールズケイリン | 公式色 |
| `keirin-fc` | 選手会FC | 公式色 |
| `jik` | 養成所 | 公式色 |
| `ctc` | CTC | 公式色 |
| `chiba` | 千葉 | 競輪場色（`#57606a` / `#8b949e`） |
| そのほか | `sourceName` の末尾の「競輪」を取ったもの（例: 青森競輪 → 青森、伊東温泉競輪 → 伊東温泉） | 競輪場色 |

- CSS クラスは段の id と同じ（`.src.news` / `.src.official` / `.src.track`）
- 失敗の注意の配信元名も同じ規則で作る（`failures[].sourceName` を使う。5.4）

### 6.4 表示時の加工

- **時刻:** `keirin.jp`・`kawasaki`・`ito` は日付しか持たないので、カードに時刻を出さない（`<span class="time">` を描かない）
- **分類のタグ:** `ニュース`・`pickup`・`競輪` を除き、残りの先頭 2 つ（日刊スポーツの「競輪」のように、どの記事にも付いて見分けの役に立たないもの）
- **YouTube の末尾ハッシュタグの除去は無い**（競輪のライブラリに YouTube は無い）。`stripTrailingHashtags` と `displayTitle` は写さない
- エスケープ、`http(s)` 以外の URL をリンクにしないこと、日付の移動、警告と注意の出し方は autorace-news と同じ

### 6.5 フッター

```
見出しとリンクのみを掲載しています。記事は各配信元でご覧ください。
情報源: KEIRIN.JP ・ 日刊スポーツ ・ netkeirin ・ 競輪スポニチ ほか（一覧）
対象外: 弥彦・和歌山（GitHub Actions から取得できないため）、取手・京王閣・小田原・岐阜・大垣・豊橋・富山・福井・奈良・松山・小倉・久留米（フィードも API も無いため）
```

- 「一覧」は `https://github.com/shimomo/keirin-news-collector#-収集元` へのリンク（新しいタブ）
- KEIRIN.JP・日刊スポーツ・netkeirin・競輪スポニチはそれぞれのトップへのリンク（`https://keirin.jp/pc/top`、`https://www.nikkansports.com/public_race/keirin/`、`https://keirin.netkeiba.com/`、`https://keirinsponichi.jp/`。2026-10-20 に 200 を確認）

## 7. GitHub Actions

autorace-news の `collect.yml`・`ci.yml` と同じ。違いは cron（`47 * * * *`）だけ。

## 8. リポジトリ構成

autorace-news と同じ。`src/` に `CollectorFactory.php` と熊本の除外用ソース（名前は作る人に任せる）が加わり、`KawaguchiFeedSource.php` は無い。

## 9. テスト

ネットワークには出ない。autorace-news のテストを写したうえで、次を足す・変える。

- **CollectorFactory:** 配信元キーが 40 種（`yahiko`・`wakayama` が無い）。5.3 の 9 キーは REST の Source ではなく、表のフィード URL を読む（モックの HttpClient で要求された URL を確かめる）。`hofu` の記事に「ホープくん日記」が付く
- **熊本の除外:** 分類に「発売レース情報ピックアップ」を含む記事が落ち、ほかは残る。キー・名前・失敗は包んだソースのまま
- **RSS 差し替えの ID:** 実際の RSS（どれか 1 か所を `tests/fixtures/` に保存）から作った記事の `id` が `{投稿ID}-{YYYYMMDD}`（JST の公開日）
- **FailureNotice / FailureSummary / DataStore:** `failures[]` が `source`・`sourceName`・`message` の 3 キー。`sourceName` は Collector の Source の `name()` から
- **app.js:** 3 段の振り分け（表にない source は `track`）、バッジの文字（表の値と「競輪」を取る規則、`chiba` → 千葉）、`keirin.jp`・`kawasaki`・`ito` で時刻が出ないこと、隠すタグ（`競輪` を含む）、失敗の注意に `sourceName` 由来の名前が出ること

## 10. セットアップ

autorace-news と同じ手順。ただし push は SSH（`git@github.com:shimomo/keirin-news.git`）で行う（`gh` のトークンに `workflow` 権限が無く、`.github/workflows/` を含む push が通らないため）。

1. `gh repo create shimomo/keirin-news --public --description "…" --homepage "https://shimomo.github.io/keirin-news/"`（`--push` は使わない）→ SSH の remote を足して push
2. Pages を有効化する（`main` ブランチの `/docs`）
3. 最初の収集を手動で実行し、`index.json` の `failures` と公開ページを確かめる（`chiba`・`sasebo` の RSS が Actions から取れることもここで確かめる）

公開（1〜3）は作者の了承を取ってから行う。

## 11. 決めたこと

### 作者の決定

- 競輪も一覧にする。目的は選手に焦点を当てたニュースの確認
- 競輪場のお知らせは一覧に出し、最後の段に回す
- 画面のまとめ（6 章の内容、見本 `keirin-layout.html`）を確認済み
- 作り方のまとめ（autorace-news と同じ作りの別リポジトリ、見出しとリンクだけ、RSS への差し替え、毎時 47 分）を確認済み
- 弥彦と和歌山は対象から外し、フッターに対象外と書く
- KEIRIN.JP の本文断片の取得失敗で、まれに失敗の注意が出ることを受け入れる（5.4）

### 設計側の判断（作者が説明を聞いて異論なし）

- 段の中身の割り当て（6.2）、バッジは場名だけ（6.3）
- KEIRIN.JP・川崎・伊東は時刻を出さない、`競輪` などのタグを隠す
- コードは autorace-news から写して直す

### この設計書を書く段階での判断（未確認）

- 熊本の RSS 版で「発売レース情報ピックアップ」を除く（ライブラリの REST 版と同じ結果にするため）
- `failures[]` に `sourceName` を持たせ、表にない配信元も名前で出す（5.4）
- 実装時の追加: 記事が返った配信元の 1 件単位の不備はページに出さず job summary にだけ出す（5.4）。月刊競輪WEB は RSS を 2 ページ読む（5.3）
- 表にない source は「競輪場のお知らせ」に入れる（6.2）
- フッターの情報源は主な 4 つ＋ライブラリの一覧へのリンク（6.5）
- `stripTrailingHashtags` は写さない

## 12. 作る人に任せること

- 熊本の除外用ソースのクラス名と置き場所
- 保存する RSS フィクスチャの選び方
- favicon の色と絵柄
- README の文面（autorace-news の README と同じ並び。5.3 の差し替えと対象外の理由を書く）

## 13. 未確認・リスク

- `chiba`・`sasebo` の RSS（`/feed`）は、Actions からは転送前の 301 までしか確かめていない。最初の手動実行で確かめる
- 9 か所の RSS は各最新 10 件だけ。熊本は 10 件中 6 件ほどが除外対象なので、実質 4 件ほど。毎時の収集なら取りこぼさない見込み
- 公開ページでは、一時的な失敗（YouTube のような）があれば注意が出る。競輪場が 29 あるので、注意が出る頻度は autorace-news より高いかもしれない
- 見出しとリンクだけでも、公開にリスクはゼロではない（各サイトのポリシー）。autorace-news と同じく、作者が承知のうえで公開を決めた
