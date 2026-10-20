<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews;

use Shimomo\KeirinNewsCollector\CollectResult;
use Shimomo\KeirinNewsCollector\SourceFailure;

/**
 * ライブラリの failures を、ページに出す「配信元ごとの失敗」と、job summary にだけ出す「1件のデータ不備」に分ける。
 *
 * @author shimomo
 */
final class FailureSummary
{
    /**
     * 記事が返ってきている URL の失敗（日刊などの記事ページ取得失敗）は、タイトルとリンクが取れているので数えない。
     * 例外を伴わない失敗（1件のデータ不備）は、その配信元から記事が1件も返らなかったときだけ数える（itemDefects() 参照）。
     * 残りは配信元ごとに最初の1件だけを、最初に現れた順で返す。
     *
     * @param \Shimomo\KeirinNewsCollector\CollectResult $result
     * @param array<string, string> $names ソースキー → 表示名。無いキーは表示名にキーを使う
     * @return list<FailureNotice>
     */
    public static function fromResult(CollectResult $result, array $names): array
    {
        return self::summarize($result, $names, shown: true);
    }

    /**
     * 記事が返った配信元の、例外を伴わない失敗（必須項目の欠落など）。ページには出さず job summary にだけ出す。
     * KEIRIN.JP のトピック一覧のように壊れた項目が残り続けると、毎回同じ失敗が出てページの注意が消えなくなるため。
     * 配信元ごとに最初の1件だけを返す。
     *
     * @param \Shimomo\KeirinNewsCollector\CollectResult $result
     * @param array<string, string> $names
     * @return list<FailureNotice>
     */
    public static function itemDefects(CollectResult $result, array $names): array
    {
        return self::summarize($result, $names, shown: false);
    }

    /**
     * @param \Shimomo\KeirinNewsCollector\CollectResult $result
     * @param array<string, string> $names
     * @param bool $shown true ならページに出す失敗、false なら隠す不備
     * @return list<FailureNotice>
     */
    private static function summarize(CollectResult $result, array $names, bool $shown): array
    {
        $collected = [];
        $sourcesWithArticles = [];

        foreach ($result->articles as $article) {
            $collected[$article->source . "\0" . $article->url] = true;
            $sourcesWithArticles[$article->source] = true;
        }

        $notices = [];

        foreach ($result->failures as $failure) {
            if ($failure->url !== null && isset($collected[$failure->source . "\0" . $failure->url])) {
                continue;
            }

            if (self::isHiddenDefect($failure, $sourcesWithArticles) === $shown) {
                continue;
            }

            $notices[$failure->source] ??= new FailureNotice($failure->source, $names[$failure->source] ?? $failure->source, $failure->message);
        }

        return array_values($notices);
    }

    /**
     * @param \Shimomo\KeirinNewsCollector\SourceFailure $failure
     * @param array<string, true> $sourcesWithArticles
     * @return bool
     */
    private static function isHiddenDefect(SourceFailure $failure, array $sourcesWithArticles): bool
    {
        return $failure->previous === null && isset($sourcesWithArticles[$failure->source]);
    }
}
