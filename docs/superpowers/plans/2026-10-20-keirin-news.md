# keirin-news Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** keirin-news-collector で 1 時間ごとに競輪の記事を集め、見出し・リンク・配信元・日時だけを 1 日ごと・3 段（新聞・メディア → 公式・団体 → 競輪場のお知らせ）で GitHub Pages に出す。

**Architecture:** autorace-news（`~/github/shimomo/autorace-news`、`main` の `54f2d12` 以降）を写して競輪向けに直す。収集側（`src/`・`bin/collect`）は名前空間を変えて写し、`FailureNotice` に表示名を足し、`CollectorFactory` で REST が拒まれる 9 キーを RSS（ライブラリの `FeedSource`）に差し替え、弥彦・和歌山を外す。ページ（`docs/`）は段・バッジ・時刻・フッターを競輪向けにする。

**Tech Stack:** PHP 8.4、shimomo/keirin-news-collector ^0.1、PHPUnit 13・Pint・Psalm 6、素の JavaScript と `node --test`（Node.js 22 以上）、GitHub Actions、GitHub Pages

**Spec:** `docs/superpowers/specs/2026-10-20-keirin-news-design.md`（土台は autorace-news の設計書。「設計書 5.3」のように参照する）

## Global Constraints

- 作業フォルダ: `/home/shimomo/github/shimomo/keirin-news`。`main` に直接コミットする（autorace-news と同じ運用）。写し元は `/home/shimomo/github/shimomo/autorace-news`（以下 `$A`）
- コミットは作者の GPG 署名付き。署名に失敗したら止めて、作者にパスフレーズを gpg-agent に覚えさせてもらう（`--no-gpg-sign` は使わない）
- PHP `^8.4`、`config.platform.php` は `8.4.3`。ライブラリは `shimomo/keirin-news-collector` `^0.1`
- 名前空間 `Shimomo\KeirinNews\`（`src/`）、テストは `Shimomo\KeirinNews\Tests\`。ライブラリは `Shimomo\KeirinNewsCollector\`
- PHP の書き方は autorace-news と同じ（`declare(strict_types=1);`、`final`／`final readonly`、全メソッドに PHPDoc、`@author shimomo`、`#[Test]`、日本語コメント）
- データの記事は 7 項目（`source`、`sourceName`、`id`、`title`、`url`、`categories`、`publishedAt`）だけ。`index.json` の `failures[]` は `source`・`sourceName`・`message` の 3 キー
- 外す配信元キー: `yahiko`、`wakayama`。RSS に差し替えるキー（9）: `keirin-web`、`iwakidaira`、`chiba`、`nagoya`、`mukomachi`、`kishiwada`、`hofu`、`sasebo`、`kumamoto`。配信元キーは 40 種
- 段: `news`（新聞・メディア）= `nikkansports`・`netkeirin`・`keirinsponichi`・`morecadence`、`official`（公式・団体）= `keirin.jp`・`keirin-web`・`keirin-marche`・`girls-keirin`・`keirin-fc`・`jik`・`ctc`、`track`（競輪場のお知らせ）= それ以外すべて
- 時刻を出さない配信元: `keirin.jp`・`kawasaki`・`ito`。隠すタグ: `ニュース`・`pickup`・`競輪`
- cron `47 * * * *`。それ以外の収集・警告・失敗の扱いは autorace-news と同じ
- テストはネットワークに出ない（`tests/Support/Feeds.php` のモック、ライブラリの `Sources\ArraySource`）
- コミットメッセージは英語の接頭辞付き 1 行＋必要なら日本語の本文、最後に `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`
- ページの文言（そのまま使う）:
  - 段の見出し `新聞・メディア` / `公式・団体` / `競輪場のお知らせ`、件数 `{n} 件`
  - 空の段: `この日の新聞・メディアの記事はありません。` / `この日の公式・団体の記事はありません。` / `この日の競輪場のお知らせはありません。`
  - 失敗・停止・日付なし・データなし・読み込み失敗の文言は autorace-news と同じ

## Review Focus

1. **競輪場のバッジ名:** 表にない競輪場は `sourceName` の末尾の「競輪」だけを取る（`伊東温泉競輪` → `伊東温泉`、`京都向日町競輪` → `京都向日町`）。`PIST6（千葉）` は表で `千葉`。→ Task 5 のテスト `trackLabelsDropTrailingKeirin`
2. **日付だけの配信元と本物の 00:00:** `keirin.jp`・`kawasaki`・`ito` は時刻を出さないが、ほかの配信元の 00:00 の記事（競輪スポニチの深夜など）は `00:00` と出す。→ Task 5 のテスト `hidesTimeOnlyForDateOnlySources`
3. **競輪場の失敗の注意:** `app.js` の表に無い `kumamoto` でも、注意は `熊本 の取得に失敗しました…` と名前で出る。→ Task 2 のテスト `noticesCarrySourceNames`、Task 5 のテスト `failureMessageUsesSourceName`
4. **熊本の除外の範囲:** 分類が「発売レース情報ピックアップ」と完全に一致する記事だけを落とし、ほかの記事と失敗は通す。→ Task 3 のテスト `dropsOnlyExactCategory`
5. **転送される RSS の URL:** `chiba`・`sasebo` は末尾 `/` なしの `/feed` を読み、どのキーも `wp-json` を要求しない。→ Task 4 のテスト `replacedKeysReadTheirFeeds`

---

### Task 1: autorace-news から写した土台

autorace-news の収集側を名前空間を変えて写し、写したテストがそのまま通る状態にする。

**Files:**
- Create（`$A` から写す）: `.editorconfig`、`.gitignore`、`LICENSE`、`pint.json`、`phpunit.xml.dist`（testsuite 名 `KeirinNews`）、`psalm.xml.dist`
- Create（写して名前空間を変える）: `src/StoredArticle.php`、`src/ArticleMerger.php`、`src/MergeResult.php`、`src/FailureNotice.php`、`src/FailureSummary.php`、`src/DataStore.php`、`src/CollectRun.php`、`src/RunReport.php`、`tests/StoredArticleTest.php`、`tests/ArticleMergerTest.php`、`tests/FailureSummaryTest.php`、`tests/DataStoreTest.php`、`tests/CollectRunTest.php`、`tests/Support/Articles.php`、`tests/Support/TempDirectory.php`、`tests/Support/Feeds.php`
- Create: `composer.json`、`composer.lock`
- 写さない: `KawaguchiFeedSource.php`・`CollectorFactory.php`・`bin/collect`・それらのテストと `tests/fixtures/kawaguchi/`（`CollectorFactory` と `bin/collect` は Task 4 で作る）
- 既存: 設計書とこの計画を最初のコミットに含める

**Interfaces:**
- Produces: autorace-news と同じクラスと署名（名前空間だけ `Shimomo\KeirinNews`）。`RunReport::summary()` の見出しは `## keirin-news 収集結果`

- [ ] **Step 1: git を初期化し、ファイルを写す**

```bash
git init -b main
```

`$A` から上のファイルを写し、PHP ファイルの中で `Shimomo\AutoraceNewsCollector` → `Shimomo\KeirinNewsCollector`、続けて `Shimomo\AutoraceNews` → `Shimomo\KeirinNews` の順に置き換える（逆順だと `Collector` 側が壊れる）。`RunReport::summary()` の `autorace-news` を `keirin-news` に変える。`.editorconfig` の `tests/fixtures/**` の節は残す（Task 4 でフィクスチャを置く）。

`composer.json` は `$A/composer.json` を写し、`name` を `shimomo/keirin-news`、`description` を `Hourly list of Keirin news headlines and links, published on GitHub Pages.`、`require` のライブラリを `"shimomo/keirin-news-collector": "^0.1"` にする（`platform.php` `8.4.3`、scripts はそのまま）。

- [ ] **Step 2: 依存を入れて、写したテストを通す**

Run: `composer install && composer test && composer lint && composer analyse`
Expected: PHPUnit `OK (30 tests, ...)`（autorace-news の CollectorFactory・Kawaguchi のテストを除いた数）、Pint の差分なし、Psalm `No errors found!`。`grep -rn Autorace src tests` が何も出さない

- [ ] **Step 3: コミット**

```bash
git add .
git commit -m "feat: copy the collection core from autorace-news" -m "設計書と実装計画を含む。名前空間を Shimomo\\KeirinNews に変え、ライブラリを keirin-news-collector にした。"
```

---

### Task 2: 失敗の注意に表示名を持たせる

設計書 5.4。`FailureNotice` に `sourceName` を足し、`index.json` の `failures[]` を 3 キーにする。

**Files:**
- Modify: `src/FailureNotice.php`、`src/FailureSummary.php`、`src/CollectRun.php`
- Test: `tests/FailureSummaryTest.php`、`tests/DataStoreTest.php`、`tests/CollectRunTest.php`

**Interfaces:**
- Consumes: Task 1 の各クラス、ライブラリの `Collector::sources()`（`Contracts\Source::key()`・`name()`）
- Produces:
  - `FailureNotice::__construct(string $source, string $sourceName, string $message)`、`toArray(): array{source: string, sourceName: string, message: string}`
  - `FailureSummary::fromResult(CollectResult $result, array<string, string> $names): list<FailureNotice>` — `$names` はキー → 表示名。無いキーは表示名にキーを使う
  - `CollectRun` は `collector->sources()` から、キーごとに最初の Source の `name()` で `$names` を作る

- [ ] **Step 1: 既存のテストを新しい形に直して、落ちることを確かめる**

`FailureSummaryTest` の `FailureNotice` を 3 引数にし、`fromResult($result, ['autorace.jp' => 'AUTORACE.JP', ...])` で呼ぶ。足すテスト:

```php
#[Test]
public function noticesCarrySourceNames(): void
{
    $result = new CollectResult([], [new SourceFailure('kumamoto', null, 'HTTP 503')]);

    self::assertSame(
        [['source' => 'kumamoto', 'sourceName' => '熊本競輪', 'message' => 'HTTP 503']],
        array_map(static fn(FailureNotice $n): array => $n->toArray(), FailureSummary::fromResult($result, ['kumamoto' => '熊本競輪'])),
    );
}

#[Test]
public function unknownKeyFallsBackToKey(): void
{
    $result = new CollectResult([], [new SourceFailure('newtrack', null, 'down')]);

    self::assertEquals([new FailureNotice('newtrack', 'newtrack', 'down')], FailureSummary::fromResult($result, []));
}
```

`DataStoreTest::savesOneFilePerDayNewestFirst` の `new FailureNotice('autorace.jp', 'HTTP 503')` を `new FailureNotice('autorace.jp', 'AUTORACE.JP', 'HTTP 503')` にし、期待を `'failures' => [['source' => 'autorace.jp', 'sourceName' => 'AUTORACE.JP', 'message' => 'HTTP 503']]` に、`CollectRunTest::writesCheckedAtAndFailures` の期待を `[['source' => 'autorace.jp', 'sourceName' => 'AUTORACE.JP', 'message' => 'HTTP 503']]`（ArraySource の名前）にする。

Run: `composer test`
Expected: FAIL（`FailureNotice` の引数の数、`failures[]` のキー）

- [ ] **Step 2: `FailureNotice`・`FailureSummary`・`CollectRun` を直す**

- [ ] **Step 3: テストを通す**

Run: `composer test && composer lint && composer analyse`
Expected: すべて成功

- [ ] **Step 4: コミット**

```bash
git add src tests
git commit -m "feat: carry source names in failure notices"
```

---

### Task 3: 熊本の分類を除くソース

設計書 5.3 の「熊本の除外」。任意の Source を包み、指定した分類を持つ記事を落とす。

**Files:**
- Create: `src/CategoryExcludingSource.php`
- Test: `tests/CategoryExcludingSourceTest.php`

**Interfaces:**
- Consumes: ライブラリの `Contracts\Source`、`FetchResult`、`Sources\ArraySource`（テスト）
- Produces: `final readonly class CategoryExcludingSource implements Source { __construct(Source $inner, list<string> $excludedCategories); key(): string; name(): string; fetch(?DateTimeImmutable $since): FetchResult }` — `key()`・`name()` は `$inner` のまま、失敗は素通し、分類の比較は完全一致

- [ ] **Step 1: 失敗するテストを書く**

```php
#[Test]
public function dropsOnlyExactCategory(): void
{
    $pickup = Articles::library('kumamoto', '1', '2026-10-19 10:00:00', '他場の場外発売', ['熊本競輪', '発売レース情報ピックアップ']);
    $notice = Articles::library('kumamoto', '2', '2026-10-19 11:00:00', 'お知らせ', ['お知らせ']);
    $similar = Articles::library('kumamoto', '3', '2026-10-19 12:00:00', '似た分類', ['発売レース情報']);
    $failure = new SourceFailure('kumamoto', null, 'broken item');
    $source = new CategoryExcludingSource(
        new ArraySource('kumamoto', '熊本競輪', [$pickup, $notice, $similar], [$failure]),
        ['発売レース情報ピックアップ'],
    );
    $result = $source->fetch(null);

    self::assertSame(['2', '3'], array_map(static fn($a): string => $a->id, $result->articles));
    self::assertSame([$failure], $result->failures);
    self::assertSame('kumamoto', $source->key());
    self::assertSame('熊本競輪', $source->name());
}

#[Test]
public function passesSinceToInner(): void
{
    $source = new CategoryExcludingSource(
        new ArraySource('kumamoto', '熊本競輪', [Articles::library('kumamoto', 'old', '2026-10-17 10:00:00'), Articles::library('kumamoto', 'new', '2026-10-19 10:00:00')]),
        ['発売レース情報ピックアップ'],
    );
    $since = new DateTimeImmutable('2026-10-18 00:00:00', new DateTimeZone('Asia/Tokyo'));

    self::assertSame(['new'], array_map(static fn($a): string => $a->id, $source->fetch($since)->articles));
}
```

Run: `vendor/bin/phpunit tests/CategoryExcludingSourceTest.php`
Expected: FAIL（クラスが無い）

- [ ] **Step 2: `CategoryExcludingSource` を書く**

- [ ] **Step 3: テストを通す**

Run: `composer test && composer lint && composer analyse`
Expected: すべて成功

- [ ] **Step 4: コミット**

```bash
git add src/CategoryExcludingSource.php tests/CategoryExcludingSourceTest.php
git commit -m "feat: source wrapper that drops excluded categories"
```

---

### Task 4: CollectorFactory と bin/collect

設計書 5.3。ライブラリの既定から 11 キーを外し、9 キーを RSS で足し直す。

**Files:**
- Create: `src/CollectorFactory.php`、`bin/collect`（`$A/bin/collect` を写し、名前空間と `use` を直す）
- Create: `tests/fixtures/nagoya/feed.xml`（名古屋の RSS を 1 回取って保存。2026-10-20 時点の先頭 2 件は下のテストの値）
- Test: `tests/CollectorFactoryTest.php`

**Interfaces:**
- Consumes: ライブラリの `Collector::default(?HttpFetcher, int)`・`without()`・`only()`・`sources()`、`Sources\FeedSource(FeedSite, HttpFetcher)`、`Sources\FeedSite(key, name, feedUrl, IdRule, ..., fixedCategories: ...)`、`Sources\IdRule::wordPressGuid()`、Task 3 の `CategoryExcludingSource`、`tests/Support/Feeds::fetcher()`・`fixture()`
- Produces: `final class CollectorFactory { public static function create(?HttpFetcher $fetcher = null): Collector }`。RSS の URL は `public const array FEEDS`（キー → URL）などで持ってよい

| key | name | URL | 追加 |
|---|---|---|---|
| `keirin-web` | 月刊競輪WEB | `https://keirin-web.com/feed/` | |
| `iwakidaira` | いわき平競輪 | `https://tairakeirin.com/feed/` | |
| `chiba` | PIST6（千葉） | `https://chiba.pist6.com/feed` | |
| `nagoya` | 名古屋競輪 | `https://nagoyakeirin.com/feed/` | |
| `mukomachi` | 京都向日町競輪 | `https://kyoto-mukomachikeirin.com/feed/` | |
| `kishiwada` | 岸和田競輪 | `https://chalionkun.com/feed/` | |
| `hofu` | 防府競輪 | `https://www.hofukeirin.jp/blog/feed/` | `fixedCategories: ['ホープくん日記']` |
| `sasebo` | 佐世保競輪 | `https://www.sasebokeirin.jp/feed` | |
| `kumamoto` | 熊本競輪 | `https://www.kumamotokeirin.jp/feed/` | `CategoryExcludingSource` で `['発売レース情報ピックアップ']` を除く |

- [ ] **Step 1: フィクスチャを保存し、失敗するテストを書く**

```bash
mkdir -p tests/fixtures/nagoya
curl -sf -A 'keirin-news-collector (+https://github.com/shimomo/keirin-news-collector)' https://nagoyakeirin.com/feed/ -o tests/fixtures/nagoya/feed.xml
```

保存した先頭 2 件の `guid`・`pubDate` を見て、下の期待値（2026-10-20 に確認した値）と違えば、テストの値を保存したものに合わせる。

```php
#[Test]
public function dropsBlockedKeysAndKeepsForty(): void
{
    $keys = array_values(array_unique(array_map(
        static fn(Source $s): string => $s->key(),
        CollectorFactory::create(Feeds::fetcher([]))->sources(),
    )));

    self::assertCount(40, $keys);
    self::assertNotContains('yahiko', $keys);
    self::assertNotContains('wakayama', $keys);
}

#[Test]
public function replacedKeysReadTheirFeeds(): void
{
    $order = ['keirin-web', 'iwakidaira', 'chiba', 'nagoya', 'mukomachi', 'kishiwada', 'hofu', 'sasebo', 'kumamoto'];
    $requested = [];
    $collector = CollectorFactory::create(Feeds::fetcher(array_fill(0, 9, Feeds::fixture('nagoya/feed.xml')), $requested));

    foreach ($order as $key) {
        $collector->only($key)->collect();
    }

    self::assertSame([
        'https://keirin-web.com/feed/',
        'https://tairakeirin.com/feed/',
        'https://chiba.pist6.com/feed',
        'https://nagoyakeirin.com/feed/',
        'https://kyoto-mukomachikeirin.com/feed/',
        'https://chalionkun.com/feed/',
        'https://www.hofukeirin.jp/blog/feed/',
        'https://www.sasebokeirin.jp/feed',
        'https://www.kumamotokeirin.jp/feed/',
    ], $requested);
}

#[Test]
public function feedIdsMatchWordPressRule(): void
{
    $result = CollectorFactory::create(Feeds::fetcher([Feeds::fixture('nagoya/feed.xml')]))->only('nagoya')->collect();

    self::assertSame('47378-20261017', $result->articles[0]->id);
    self::assertSame('名古屋競輪', $result->articles[0]->sourceName);
    self::assertSame('2026-10-17T17:00:00+09:00', $result->articles[0]->publishedAt->format(DATE_ATOM));
    self::assertSame('47299-20261004', $result->articles[1]->id);
}

#[Test]
public function hofuAddsFixedCategory(): void
{
    $result = CollectorFactory::create(Feeds::fetcher([Feeds::fixture('nagoya/feed.xml')]))->only('hofu')->collect();

    self::assertSame('ホープくん日記', $result->articles[0]->categories[0]);
}

#[Test]
public function kumamotoDropsSalesPickup(): void
{
    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0"><channel>
        <item><title>場外発売</title><link>https://www.kumamotokeirin.jp/1/</link><guid>https://www.kumamotokeirin.jp/?p=1</guid><pubDate>Mon, 19 Oct 2026 01:00:00 +0000</pubDate><category>発売レース情報ピックアップ</category></item>
        <item><title>お知らせ</title><link>https://www.kumamotokeirin.jp/2/</link><guid>https://www.kumamotokeirin.jp/?p=2</guid><pubDate>Mon, 19 Oct 2026 02:00:00 +0000</pubDate><category>お知らせ</category></item>
        </channel></rss>
        XML;
    $result = CollectorFactory::create(Feeds::fetcher([$xml]))->only('kumamoto')->collect();

    self::assertSame(['2-20261019'], array_map(static fn($a): string => $a->id, $result->articles));
}
```

Run: `vendor/bin/phpunit tests/CollectorFactoryTest.php`
Expected: FAIL（`CollectorFactory` が無い）

- [ ] **Step 2: `CollectorFactory::create()` と `bin/collect` を書く**

`Collector::default($fetcher)->without('yahiko', 'wakayama', <差し替える 9 キー>)->sources()` の後ろに、表の 9 つを足した `new Collector([...])` を返す。`$fetcher` は 1 つを共有する（既定は `new HttpFetcher(HttpClient::create(), new NativeSleeper())`）。`bin/collect` は `CollectorFactory::create()` を使う。`chmod +x bin/collect`。

- [ ] **Step 3: テストを通す**

Run: `composer test && composer lint && composer analyse`
Expected: すべて成功

- [ ] **Step 4: 実際のサイトで 1 回動かす（リポジトリの外の空の一時ディレクトリ `$CHECK` へ）**

Run: `php bin/collect "$CHECK" && python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); print(len(d["dates"]), d["failures"])' "$CHECK/index.json"`
Expected: 終了コード 0、2〜3 分かかる。`failures` が空か一時的なもの（2 回目で消える）。`yahiko`・`wakayama` が無く、`nagoya` などの記事の `url` が RSS のリンクになっている。`grep -lE 'bodyHtml|bodyText|summary|thumbnailUrl' "$CHECK"/*.json` が何も出さない。確認後 `$CHECK` を消す

- [ ] **Step 5: コミット**

```bash
git add src/CollectorFactory.php bin/collect tests/CollectorFactoryTest.php tests/fixtures/nagoya
git commit -m "feat: collect keirin sources with rss fallbacks" -m "Actions の IP から REST API が 403 になる 9 キーを RSS で読み、取得できない弥彦・和歌山を外す。"
```

---

### Task 5: app.js（競輪向け）

設計書 6.2〜6.4。`$A/docs/app.js` と `$A/tests/js/app.test.mjs` を写して直す。

**Files:**
- Create: `docs/app.js`、`tests/js/app.test.mjs`

**Interfaces:**
- Consumes: 日ファイルの記事の形、`index.json` の `failures[]`（`source`・`sourceName`・`message`。Task 2）
- Produces（autorace-news から変わる export）:
  - `SECTIONS` — `news`（新聞・メディア）・`official`（公式・団体）・`track`（競輪場のお知らせ）の順、空の文言は Global Constraints
  - `sectionOf(source): "news" | "official" | "track"`（表にない source は `track`）
  - `sourceLabel({ source, sourceName }): string` — 表の 11 媒体と `chiba` → `千葉`、それ以外は `sourceName` から末尾の `競輪` を 1 回取ったもの（`sourceName` も無ければ `source`）
  - `hasTime(source): boolean` — `keirin.jp`・`kawasaki`・`ito` で `false`
  - `visibleTags(categories)` — `ニュース`・`pickup`・`競輪` を除いた先頭 2 つ
  - `failureMessage({ source, sourceName, message })` — 配信元名は `sourceLabel` と同じ規則
  - `renderCard(article)` — タイトルは `escapeHtml(article.title)`（加工なし）、`hasTime` が偽なら `<span class="time">` を描かない
  - 消すもの: `stripTrailingHashtags`、`displayTitle`、`TRAILING_HASHTAGS`
  - そのまま: `formatTime`・`formatDateTime`・`dateLabel`・`resolveDate`・`isStale`・`escapeHtml`・`safeUrl`・`groupBySection`・`renderSections`・`staleMessage`・DOM 部分（DOM 部分は Task 6 で写す）

媒体の表示名: `nikkansports` 日刊スポーツ、`netkeirin` netkeirin、`keirinsponichi` 競輪スポニチ、`morecadence` More CADENCE、`keirin.jp` KEIRIN.JP、`keirin-web` 月刊競輪WEB、`keirin-marche` けいりんマルシェ、`girls-keirin` ガールズケイリン、`keirin-fc` 選手会FC、`jik` 養成所、`ctc` CTC。

- [ ] **Step 1: テストを写して直し、足す**

`$A/tests/js/app.test.mjs` を写し、ハッシュタグのテストと YouTube のテストを消し、段・ラベルのテストを競輪の値にする。足すテスト:

```js
test("sources map to keirin sections", () => {
  assert.equal(sectionOf("nikkansports"), "news");
  assert.equal(sectionOf("keirin-web"), "official");
  assert.equal(sectionOf("aomori"), "track");
  assert.equal(sectionOf("newtrack"), "track");
});

test("trackLabelsDropTrailingKeirin", () => {
  assert.equal(sourceLabel({ source: "ito", sourceName: "伊東温泉競輪" }), "伊東温泉");
  assert.equal(sourceLabel({ source: "mukomachi", sourceName: "京都向日町競輪" }), "京都向日町");
  assert.equal(sourceLabel({ source: "chiba", sourceName: "PIST6（千葉）" }), "千葉");
  assert.equal(sourceLabel({ source: "keirin-fc", sourceName: "競輪選手会オフィシャルファンクラブ" }), "選手会FC");
  assert.equal(sourceLabel({ source: "newtrack" }), "newtrack");
});

test("hidesTimeOnlyForDateOnlySources", () => {
  assert.doesNotMatch(renderCard(article({ source: "keirin.jp", publishedAt: "2026-10-19T00:00:00+09:00" })), /class="time"/);
  assert.doesNotMatch(renderCard(article({ source: "kawasaki", publishedAt: "2026-10-19T00:00:00+09:00" })), /class="time"/);
  assert.match(
    renderCard(article({ source: "keirinsponichi", publishedAt: "2026-10-20T00:00:00+09:00" })),
    /<span class="time">00:00<\/span>/,
  );
});

test("visibleTags hides generic keirin tag", () => {
  assert.deepEqual(visibleTags(["競輪"]), []);
  assert.deepEqual(visibleTags(["ミッドナイト競輪", "競輪"]), ["ミッドナイト競輪"]);
});

test("failureMessageUsesSourceName", () => {
  assert.equal(
    failureMessage({ source: "kumamoto", sourceName: "熊本競輪", message: "HTTP 503" }),
    "熊本 の取得に失敗しました（HTTP 503）。次の収集で取り直します。",
  );
  assert.equal(
    failureMessage({ source: "keirin.jp", sourceName: "KEIRIN.JP", message: "x" }),
    "KEIRIN.JP の取得に失敗しました（x）。次の収集で取り直します。",
  );
});
```

`renderSections` のテストは段の順（`新聞・メディア` < `公式・団体` < `競輪場のお知らせ`）と空の文言 `この日の公式・団体の記事はありません。` を確かめる形に直す。テスト用の `article()` の既定は `source: "netkeirin"` にする。

Run: `node --test "tests/js/*.test.mjs"`
Expected: FAIL（`docs/app.js` が無い）

- [ ] **Step 2: `docs/app.js` の純粋関数を写して直す**

`$A/docs/app.js` の「DOM を触る部分」より上を写し、上の Produces のとおりに直す。

- [ ] **Step 3: テストを通す**

Run: `node --test "tests/js/*.test.mjs"`
Expected: PASS（`fail 0`）、警告なし

- [ ] **Step 4: コミット**

```bash
git add docs/app.js tests/js/app.test.mjs
git commit -m "feat: page helpers for keirin sections, labels and times"
```

---

### Task 6: ページ本体（index.html、style.css、DOM 部分、favicon）

**Files:**
- Create: `docs/index.html`、`docs/style.css`、`docs/.nojekyll`、`docs/favicon.svg`、`docs/favicon.ico`、`docs/apple-touch-icon.png`
- Modify: `docs/app.js`（`$A/docs/app.js` の DOM 部分を写す。変更なし）

**Interfaces:**
- Consumes: Task 5 の export、`docs/data/` の JSON
- Produces: なし

- [ ] **Step 1: `docs/index.html` を写して直す**

`<title>Keirin News</title>`、見出し `Keirin News`、`meta description` は「競輪の選手ニュースを中心に、新聞・競輪メディア・公式・競輪場の見出しを1日ごとにまとめた一覧」。フッターは設計書 6.5 のとおり:

```html
<footer class="footer">
  見出しとリンクのみを掲載しています。記事は各配信元でご覧ください。<br />
  情報源:
  <a href="https://keirin.jp/pc/top" target="_blank" rel="noopener noreferrer">KEIRIN.JP</a> ・
  <a href="https://www.nikkansports.com/public_race/keirin/" target="_blank" rel="noopener noreferrer">日刊スポーツ</a> ・
  <a href="https://keirin.netkeiba.com/" target="_blank" rel="noopener noreferrer">netkeirin</a> ・
  <a href="https://keirinsponichi.jp/" target="_blank" rel="noopener noreferrer">競輪スポニチ</a>
  ほか（<a href="https://github.com/shimomo/keirin-news-collector#-収集元" target="_blank" rel="noopener noreferrer">一覧</a>）<br />
  対象外: 弥彦・和歌山（GitHub Actions から取得できないため）、取手・京王閣・小田原・岐阜・大垣・豊橋・富山・福井・奈良・松山・小倉・久留米（フィードも API も無いため）
</footer>
```

- [ ] **Step 2: `docs/style.css` を写して直す**

`--video` を `--track` に、`.src.video` を `.src.track` に改名する（値は同じ `#57606a` / `#8b949e`）。ほかは変えない。

- [ ] **Step 3: DOM 部分を写し、favicon を作る**

`$A/docs/app.js` の「DOM を触る部分」以降を末尾に写す。favicon は autorace-news と同じ作り（SVG を書き、同じ絵柄を PHP の GD で描く使い捨てスクリプトで 16・32・180px の PNG と ICO を作る。autorace-news で使ったスクリプトの方式）。色と絵柄は autorace-news（青・新聞）と tech-trends（オレンジ・矢印）と見分けがつくもの（例: 緑 `#1a7f37` に車輪）。

Run: `file docs/favicon.ico docs/apple-touch-icon.png`
Expected: `MS Windows icon resource - 2 icons, 16x16 ... 32x32 ...`、`PNG image data, 180 x 180`

- [ ] **Step 4: 実データで画面を確かめる**

`docs/` を一時ディレクトリ `$TMP` に写し、`php bin/collect "$TMP/data"`、`python3 -m http.server 8765 -d "$TMP"`。ブラウザ（使えなければ Playwright のヘッドレス Chrome でスクリーンショット）で確かめる:
- 段が「新聞・メディア → 公式・団体 → 競輪場のお知らせ」、各段に件数、空の段に文言
- 競輪場のバッジが場名だけ（例: 青森）、KEIRIN.JP のカードに時刻が無い
- 失敗があれば注意が表示名で出る
- ダーク表示、幅 640px 以下で 1 列、コンソールにエラーなし、`#2026-01-01` で注意と最新の日

確認後、サーバーを止めて `$TMP` を消す。`git status` に `docs/data` が出ないこと。

- [ ] **Step 5: テストを通してコミット**

Run: `node --test "tests/js/*.test.mjs" && composer test`
Expected: PASS

```bash
git add docs/index.html docs/style.css docs/app.js docs/.nojekyll docs/favicon.svg docs/favicon.ico docs/apple-touch-icon.png
git commit -m "feat: static page with keirin sections"
```

---

### Task 7: GitHub Actions と README

**Files:**
- Create: `.github/workflows/collect.yml`、`.github/workflows/ci.yml`、`README.md`

- [ ] **Step 1: ワークフローを写す**

`$A/.github/workflows/collect.yml` と `ci.yml` を写し、`collect.yml` の cron を `"47 * * * *"`、コメントを「毎時 47 分（autorace-news の 17 分とずらす）」にする。チェックアウトの `ref: ${{ github.ref_name }}` は残す。

- [ ] **Step 2: `README.md` を書く**

`$A/README.md` と同じ並びで、競輪向けに書く。違い:
- ページ URL `https://shimomo.github.io/keirin-news/`、使うライブラリ `keirin-news-collector`
- 何が見えるか: 3 段（新聞・メディア → 公式・団体 → 競輪場のお知らせ）、日付だけの配信元は時刻を出さないこと
- 仕組み: 毎時 47 分、40 配信元、9 キーを RSS（各最新 10 件）に差し替えた理由（Actions の IP から REST API が 403）、熊本の「発売レース情報ピックアップ」を除くこと、弥彦・和歌山を外した理由
- 公開の範囲: KEIRIN.JP・JKA 系・netkeirin・競輪スポニチのポリシーに触れ、見出しとリンクだけであること
- KEIRIN.JP の本文断片の取得失敗では、まれに注意が出ること

- [ ] **Step 3: CI と同じコマンドを手元で通す**

Run: `composer lint && composer analyse && composer test && node --test "tests/js/*.test.mjs"`
Expected: すべて成功。`python3 -c 'import yaml,sys; [yaml.safe_load(open(f)) for f in sys.argv[1:]]' .github/workflows/*.yml` がエラーなし

- [ ] **Step 4: コミット**

```bash
git add .github/workflows/collect.yml .github/workflows/ci.yml README.md
git commit -m "ci: add hourly collect and ci workflows with readme"
```

---

### Task 8: 公開と初回の収集

**外に出る操作なので、始める前に作者の了承を取る。**

- [ ] **Step 1: 作者に了承を取る**（了承が無ければ止める）

- [ ] **Step 2: リポジトリを作り、SSH で push する**

```bash
gh repo create shimomo/keirin-news --public --description "競輪の選手ニュースを中心に、新聞・競輪メディア・公式・競輪場の見出しを1日ごとにまとめた一覧" --homepage "https://shimomo.github.io/keirin-news/"
git remote add origin git@github.com:shimomo/keirin-news.git
git push -u origin main
```

Expected: `ci` ワークフローが緑

- [ ] **Step 3: Pages を有効にする**

```bash
gh api -X POST repos/shimomo/keirin-news/pages -f build_type=legacy -f 'source[branch]=main' -f 'source[path]=/docs'
```

- [ ] **Step 4: 初回の収集を手動で実行し、確かめる**

```bash
gh workflow run collect.yml
gh run watch
```

Expected: 成功し `data:` コミットが入る。`docs/data/index.json` の `failures` が空か一時的なもの。特に `chiba`・`sasebo` の記事が入っていること（RSS の 301 の先が Actions から取れるか）。失敗が残る配信元があれば、もう一度実行して一時的かどうかを見分け、結果を作者に伝える

- [ ] **Step 5: 公開ページを確かめる**

`https://shimomo.github.io/keirin-news/` を開き、Task 6 Step 4 と同じ点を確かめる。
