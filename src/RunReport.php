<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews;

/**
 * 1回分の収集の結果。終了コードと Actions の job summary を決める。
 *
 * @author shimomo
 */
final readonly class RunReport
{
    /**
     * @param int $collected 今回ライブラリが返した件数
     * @param int $added 新しく足した件数
     * @param int $updated 既存から中身が変わった件数
     * @param list<FailureNotice> $failures
     * @param bool $allFailed すべての配信元キーが失敗した
     * @param list<FailureNotice> $itemDefects ページに出さない1件単位の不備（FailureSummary::itemDefects()）
     */
    public function __construct(
        public int $collected,
        public int $added,
        public int $updated,
        public array $failures,
        public bool $allFailed,
        public array $itemDefects = [],
    ) {
    }

    /**
     * 一部の配信元の失敗では 0 を返す。一時的な失敗のたびに毎時メール通知が飛ばないように。
     *
     * @return int
     */
    public function exitCode(): int
    {
        return $this->allFailed ? 1 : 0;
    }

    /**
     * @return string Markdown
     */
    public function summary(): string
    {
        $lines = [
            '## keirin-news 収集結果',
            '',
            sprintf('- 収集: %d 件（新規 %d 件・更新 %d 件）', $this->collected, $this->added, $this->updated),
        ];

        if ($this->failures === []) {
            $lines[] = '- 失敗: なし';
        } else {
            $lines[] = $this->allFailed ? '- 失敗: すべての配信元' : '- 失敗:';

            foreach ($this->failures as $failure) {
                $lines[] = sprintf('  - `%s`: %s', $failure->source, $failure->message);
            }
        }

        if ($this->itemDefects !== []) {
            $lines[] = '- 1件単位の不備（その配信元の記事は取れているので、ページには出さない）:';

            foreach ($this->itemDefects as $defect) {
                $lines[] = sprintf('  - `%s`: %s', $defect->source, $defect->message);
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
