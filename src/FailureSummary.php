<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews;

use Shimomo\KeirinNewsCollector\CollectResult;

/**
 * ライブラリの failures を、ページに出す「配信元ごとの失敗」にまとめる。
 *
 * @author shimomo
 */
final class FailureSummary
{
    /**
     * 記事が返ってきている URL の失敗（日刊などの記事ページ取得失敗）は、タイトルとリンクが取れているので数えない。
     * 残りは配信元ごとに最初の1件だけを、最初に現れた順で返す。
     *
     * @param \Shimomo\KeirinNewsCollector\CollectResult $result
     * @param array<string, string> $names ソースキー → 表示名。無いキーは表示名にキーを使う
     * @return list<FailureNotice>
     */
    public static function fromResult(CollectResult $result, array $names): array
    {
        $collected = [];

        foreach ($result->articles as $article) {
            $collected[$article->source . "\0" . $article->url] = true;
        }

        $notices = [];

        foreach ($result->failures as $failure) {
            if ($failure->url !== null && isset($collected[$failure->source . "\0" . $failure->url])) {
                continue;
            }

            $notices[$failure->source] ??= new FailureNotice($failure->source, $names[$failure->source] ?? $failure->source, $failure->message);
        }

        return array_values($notices);
    }
}
