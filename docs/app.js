// keirin-news: 1 日分の JSON を読んで 3 段に描く。ビルドなし、依存なし。
// 純粋関数は export してテストから使う。DOM を触るのは main() だけ。

const WEEKDAYS = ["日", "月", "火", "水", "木", "金", "土"];
/** GitHub Actions の定期実行は 1 日に数回しか動かないので、丸 1 日動いていなければ止まっているとみなす */
const STALE_AFTER_MS = 24 * 60 * 60 * 1000;

/** 段の並び（固定）。選手の話題が多い新聞・メディアを先頭に、競輪場の案内を最後に置く。表にない source は track。 */
export const SECTIONS = [
  { id: "news", title: "新聞・メディア", empty: "この日の新聞・メディアの記事はありません。" },
  { id: "official", title: "公式・団体", empty: "この日の公式・団体の記事はありません。" },
  { id: "track", title: "競輪場のお知らせ", empty: "この日の競輪場のお知らせはありません。" },
];

const SECTION_OF_SOURCE = new Map([
  ["nikkansports", "news"],
  ["netkeirin", "news"],
  ["keirinsponichi", "news"],
  ["morecadence", "news"],
  ["keirin.jp", "official"],
  ["keirin-web", "official"],
  ["keirin-marche", "official"],
  ["girls-keirin", "official"],
  ["keirin-fc", "official"],
  ["jik", "official"],
  ["ctc", "official"],
]);

/** 媒体と、名前の規則に合わない競輪場。ほかの競輪場は sourceName の末尾の「競輪」を取る */
const SOURCE_LABELS = new Map([
  ["nikkansports", "日刊スポーツ"],
  ["netkeirin", "netkeirin"],
  ["keirinsponichi", "競輪スポニチ"],
  ["morecadence", "More CADENCE"],
  ["keirin.jp", "KEIRIN.JP"],
  ["keirin-web", "月刊競輪WEB"],
  ["keirin-marche", "けいりんマルシェ"],
  ["girls-keirin", "ガールズケイリン"],
  ["keirin-fc", "選手会FC"],
  ["jik", "養成所"],
  ["ctc", "CTC"],
  ["chiba", "千葉"],
]);

/** 日付だけで時刻を持たない配信元（ライブラリが 00:00 にしている） */
const DATE_ONLY_SOURCES = new Set(["keirin.jp", "kawasaki", "ito"]);

/** どの記事にも付いていて、見分けの役に立たない分類 */
const HIDDEN_TAGS = new Set(["ニュース", "pickup", "競輪"]);

const timeFormatter = new Intl.DateTimeFormat("ja-JP", {
  timeZone: "Asia/Tokyo",
  hour: "2-digit",
  minute: "2-digit",
  hourCycle: "h23",
});
const dateTimeFormatter = new Intl.DateTimeFormat("ja-JP", {
  timeZone: "Asia/Tokyo",
  month: "2-digit",
  day: "2-digit",
  hour: "2-digit",
  minute: "2-digit",
  hourCycle: "h23",
});

/** @param {string} source */
export function sectionOf(source) {
  return SECTION_OF_SOURCE.get(source) ?? "track";
}

/** @param {{ source: string, sourceName?: string }} article */
export function sourceLabel(article) {
  return SOURCE_LABELS.get(article.source) ?? article.sourceName?.replace(/競輪$/, "") ?? article.source;
}

/** @param {string} source */
export function hasTime(source) {
  return !DATE_ONLY_SOURCES.has(source);
}

/** @param {string[]} categories */
export function visibleTags(categories) {
  return categories.filter((c) => !HIDDEN_TAGS.has(c)).slice(0, 2);
}

/**
 * @param {{ source: string, publishedAt: string }[]} articles
 * @returns {Record<"news" | "official" | "track", any[]>}
 */
export function groupBySection(articles) {
  const groups = { news: [], official: [], track: [] };
  for (const article of articles) groups[sectionOf(article.source)].push(article);
  for (const list of Object.values(groups)) {
    list.sort((a, b) => Date.parse(b.publishedAt) - Date.parse(a.publishedAt));
  }
  return groups;
}

/** @param {string} iso */
export function formatTime(iso) {
  return timeFormatter.format(new Date(iso));
}

/** @param {string} iso */
export function formatDateTime(iso) {
  return dateTimeFormatter.format(new Date(iso));
}

/** @param {string} date YYYY-MM-DD */
export function dateLabel(date) {
  const [y, m, d] = date.split("-").map(Number);
  const weekday = WEEKDAYS[new Date(Date.UTC(y, m - 1, d)).getUTCDay()];
  return `${date} (${weekday})`;
}

/**
 * @param {string} hash location.hash（"#2026-10-17" など）
 * @param {string[]} dates 新しい順
 * @returns {{ date: string | null, fallback: boolean }}
 */
export function resolveDate(hash, dates) {
  const latest = dates[0] ?? null;
  const wanted = hash.replace(/^#/, "");
  if (!wanted) return { date: latest, fallback: false };
  if (dates.includes(wanted)) return { date: wanted, fallback: false };
  return { date: latest, fallback: latest !== null };
}

/**
 * 最後の収集から 24 時間を超えていたら true。
 * @param {string} checkedAt
 * @param {Date} now
 */
export function isStale(checkedAt, now) {
  return now.getTime() - Date.parse(checkedAt) > STALE_AFTER_MS;
}

/** @param {unknown} value */
export function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

/** http(s) 以外（javascript: など）はリンクにしない。 @param {string} url */
export function safeUrl(url) {
  return /^https?:\/\//i.test(url) ? url : "#";
}

/** @param {{ source: string, sourceName?: string, title: string, url: string, categories: string[], publishedAt: string }} article */
export function renderCard(article) {
  const tags = visibleTags(article.categories)
    .map((tag) => `<span class="tag">${escapeHtml(tag)}</span>`)
    .join("");
  const time = hasTime(article.source) ? `<span class="time">${formatTime(article.publishedAt)}</span>` : "";
  return `<article class="card">
  <h3 class="title"><a href="${escapeHtml(safeUrl(article.url))}" target="_blank" rel="noopener noreferrer">${escapeHtml(article.title)}</a></h3>
  <p class="meta"><span class="src ${sectionOf(article.source)}">${escapeHtml(sourceLabel(article))}</span>${tags}${time}</p>
</article>`;
}

/** @param {Parameters<typeof renderCard>[0][]} articles */
export function renderSections(articles) {
  const groups = groupBySection(articles);
  return SECTIONS.map(({ id, title, empty }) => {
    const list = groups[id];
    const body = list.length
      ? `<div class="grid">\n${list.map(renderCard).join("\n")}\n</div>`
      : `<p class="empty">${empty}</p>`;
    return `<h2 class="section">${title} <span class="n">${list.length} 件</span></h2>\n${body}`;
  }).join("\n");
}

/** @param {{ source: string, sourceName?: string, message: string }} failure */
export function failureMessage(failure) {
  return `${sourceLabel(failure)} の取得に失敗しました（${failure.message}）。次の収集で取り直します。`;
}

/** @param {string} checkedAt */
export function staleMessage(checkedAt) {
  return `収集が止まっている可能性があります（最終収集: ${formatDateTime(checkedAt)}）`;
}

// ---- 以下は DOM を触る部分 -------------------------------------------------

async function fetchJson(path) {
  const res = await fetch(path, { cache: "no-cache" });
  if (!res.ok) throw new Error(`${path}: HTTP ${res.status}`);
  return res.json();
}

function el(id) {
  const node = document.getElementById(id);
  if (!node) throw new Error(`#${id} not found`);
  return node;
}

function setNotices(notices, warnings) {
  el("notices").innerHTML = [
    ...warnings.map((w) => `<p class="warning">${escapeHtml(w)}</p>`),
    ...notices.map((n) => `<p class="notice">${escapeHtml(n)}</p>`),
  ].join("");
}

async function render(index) {
  const { date, fallback } = resolveDate(location.hash, index.dates);
  const notices = [];
  const warnings = [];

  el("updated").textContent = index.checkedAt ? `最終更新 ${formatDateTime(index.checkedAt)} JST` : "";
  if (index.checkedAt && isStale(index.checkedAt, new Date())) warnings.push(staleMessage(index.checkedAt));
  for (const failure of index.failures ?? []) notices.push(failureMessage(failure));

  if (!date) {
    el("date").textContent = "データがありません";
    el("count").textContent = "";
    el("sections").innerHTML = "";
    setNotices(["まだ収集結果がありません。", ...notices], warnings);
    return;
  }

  const i = index.dates.indexOf(date);
  const newer = index.dates[i - 1];
  const older = index.dates[i + 1];
  const prevBtn = el("prev");
  const nextBtn = el("next");
  prevBtn.disabled = !older;
  nextBtn.disabled = !newer;
  prevBtn.onclick = () => {
    if (older) location.hash = older;
  };
  nextBtn.onclick = () => {
    if (newer) location.hash = newer;
  };

  const day = await fetchJson(`data/${date}.json`);
  el("date").textContent = dateLabel(day.date);
  el("count").textContent = `${day.articles.length} 件`;
  el("sections").innerHTML = renderSections(day.articles);

  if (fallback) notices.unshift("指定の日付が無いため最新を表示しています。");
  setNotices(notices, warnings);
}

async function main() {
  try {
    const index = await fetchJson("data/index.json");
    await render(index);
    window.addEventListener("hashchange", () => {
      render(index).catch(showError);
    });
  } catch (e) {
    showError(e);
  }
}

function showError(e) {
  setNotices([], [`読み込みに失敗しました: ${e instanceof Error ? e.message : String(e)}`]);
}

if (typeof document !== "undefined") main();
