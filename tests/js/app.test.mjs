import assert from "node:assert/strict";
import { test } from "node:test";
import {
  dateLabel,
  failureMessage,
  formatDateTime,
  formatTime,
  groupBySection,
  isStale,
  renderCard,
  renderSections,
  resolveDate,
  sectionOf,
  sourceLabel,
  staleMessage,
  visibleTags,
} from "../../docs/app.js";

const article = (over = {}) => ({
  source: "netkeirin",
  sourceName: "netkeirin",
  id: "1",
  title: "【京王閣G3】鈴木竜士 悲願の地元記念Vへ",
  url: "https://keirin.netkeiba.com/news/?id=1",
  categories: [],
  publishedAt: "2026-10-19T23:45:00+09:00",
  ...over,
});

test("sources map to keirin sections", () => {
  assert.equal(sectionOf("nikkansports"), "news");
  assert.equal(sectionOf("morecadence"), "news");
  assert.equal(sectionOf("keirin.jp"), "official");
  assert.equal(sectionOf("keirin-web"), "official");
  assert.equal(sectionOf("aomori"), "track");
  assert.equal(sectionOf("newtrack"), "track");
});

test("media labels come from the table", () => {
  assert.equal(sourceLabel({ source: "keirinsponichi", sourceName: "競輪スポニチ" }), "競輪スポニチ");
  assert.equal(sourceLabel({ source: "keirin-fc", sourceName: "競輪選手会オフィシャルファンクラブ" }), "選手会FC");
  assert.equal(sourceLabel({ source: "jik", sourceName: "日本競輪選手養成所" }), "養成所");
  assert.equal(sourceLabel({ source: "ctc", sourceName: "CTC グレードレース情報" }), "CTC");
});

test("trackLabelsDropTrailingKeirin", () => {
  assert.equal(sourceLabel({ source: "aomori", sourceName: "青森競輪" }), "青森");
  assert.equal(sourceLabel({ source: "ito", sourceName: "伊東温泉競輪" }), "伊東温泉");
  assert.equal(sourceLabel({ source: "mukomachi", sourceName: "京都向日町競輪" }), "京都向日町");
  assert.equal(sourceLabel({ source: "chiba", sourceName: "PIST6（千葉）" }), "千葉");
  assert.equal(sourceLabel({ source: "newtrack" }), "newtrack");
});

test("hidesTimeOnlyForDateOnlySources", () => {
  assert.doesNotMatch(
    renderCard(article({ source: "keirin.jp", publishedAt: "2026-10-19T00:00:00+09:00" })),
    /class="time"/,
  );
  assert.doesNotMatch(renderCard(article({ source: "kawasaki", publishedAt: "2026-10-19T00:00:00+09:00" })), /class="time"/);
  assert.doesNotMatch(renderCard(article({ source: "ito", publishedAt: "2026-10-19T00:00:00+09:00" })), /class="time"/);
  assert.match(
    renderCard(article({ source: "keirinsponichi", publishedAt: "2026-10-20T00:00:00+09:00" })),
    /<span class="time">00:00<\/span>/,
  );
});

test("visibleTags hides generic keirin tags", () => {
  assert.deepEqual(visibleTags(["競輪"]), []);
  assert.deepEqual(visibleTags(["ミッドナイト競輪", "競輪"]), ["ミッドナイト競輪"]);
  assert.deepEqual(visibleTags(["ニュース", "レース情報", "pickup", "選手"]), ["レース情報", "選手"]);
});

test("formatsTimesInTokyoWith24HourClock", () => {
  assert.equal(formatTime("2026-10-19T18:32:00+09:00"), "18:32");
  assert.equal(formatTime("2026-10-20T00:10:00+09:00"), "00:10");
  assert.equal(formatTime("2026-10-19T15:10:00Z"), "00:10");
  assert.equal(formatDateTime("2026-10-20T09:17:00+09:00"), "10/20 09:17");
  assert.equal(dateLabel("2026-10-19"), "2026-10-19 (月)");
});

test("isStale after more than three hours", () => {
  const checkedAt = "2026-10-20T09:47:00+09:00";
  assert.equal(isStale(checkedAt, new Date("2026-10-20T12:47:00+09:00")), false);
  assert.equal(isStale(checkedAt, new Date("2026-10-20T12:47:01+09:00")), true);
});

test("resolveDate falls back to the latest", () => {
  assert.deepEqual(resolveDate("", ["2026-10-20", "2026-10-19"]), { date: "2026-10-20", fallback: false });
  assert.deepEqual(resolveDate("#2026-10-19", ["2026-10-20", "2026-10-19"]), {
    date: "2026-10-19",
    fallback: false,
  });
  assert.deepEqual(resolveDate("#2026-01-01", ["2026-10-20"]), { date: "2026-10-20", fallback: true });
  assert.deepEqual(resolveDate("", []), { date: null, fallback: false });
});

test("renderCard shows link, badge, tags and time", () => {
  const html = renderCard(
    article({ source: "nikkansports", sourceName: "日刊スポーツ", categories: ["ミッドナイト競輪", "競輪"] }),
  );
  assert.match(html, /href="https:\/\/keirin\.netkeiba\.com\/news\/\?id=1" target="_blank" rel="noopener noreferrer"/);
  assert.match(html, /<span class="src news">日刊スポーツ<\/span><span class="tag">ミッドナイト競輪<\/span><span class="time">23:45<\/span>/);
  assert.match(renderCard(article({ source: "aomori", sourceName: "青森競輪" })), /<span class="src track">青森<\/span>/);
});

test("renderCardEscapesTitleAndRejectsUnsafeUrl", () => {
  const html = renderCard(article({ title: `Q&A <b>"速報" #競輪</b>`, url: "javascript:alert(1)" }));
  assert.match(html, /Q&amp;A &lt;b&gt;&quot;速報&quot; #競輪&lt;\/b&gt;/);
  assert.match(html, /href="#"/);
  assert.doesNotMatch(html, /javascript:/);
});

test("renderSections keeps order, counts and empty messages", () => {
  const html = renderSections([
    article({ source: "aomori", sourceName: "青森競輪", id: "a", publishedAt: "2026-10-19T07:00:00+09:00" }),
    article({ source: "takeo", sourceName: "武雄競輪", id: "b", publishedAt: "2026-10-19T14:50:00+09:00" }),
    article({ source: "netkeirin", id: "c" }),
  ]);
  assert.ok(html.indexOf("新聞・メディア") < html.indexOf("公式・団体"));
  assert.ok(html.indexOf("公式・団体") < html.indexOf("競輪場のお知らせ"));
  assert.match(html, /新聞・メディア <span class="n">1 件<\/span>/);
  assert.match(html, /この日の公式・団体の記事はありません。/);
  assert.match(html, /競輪場のお知らせ <span class="n">2 件<\/span>/);
  const track = groupBySection([
    article({ source: "aomori", id: "a", publishedAt: "2026-10-19T07:00:00+09:00" }),
    article({ source: "takeo", id: "b", publishedAt: "2026-10-19T14:50:00+09:00" }),
  ]).track;
  assert.deepEqual(
    track.map((a) => a.id),
    ["b", "a"],
  );
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
  assert.equal(staleMessage("2026-10-20T09:47:00+09:00"), "収集が止まっている可能性があります（最終収集: 10/20 09:47）");
});
