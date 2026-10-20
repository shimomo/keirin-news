<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shimomo\KeirinNews\FailureNotice;
use Shimomo\KeirinNews\FailureSummary;
use Shimomo\KeirinNews\Tests\Support\Articles;
use Shimomo\KeirinNewsCollector\CollectResult;
use Shimomo\KeirinNewsCollector\SourceFailure;

/**
 * @author shimomo
 */
final class FailureSummaryTest extends TestCase
{
    private const array NAMES = [
        'nikkansports' => '日刊スポーツ',
        'keirin.jp' => 'KEIRIN.JP',
        'aomori' => '青森競輪',
        'tamano' => '玉野競輪',
    ];

    /**
     * 記事ページだけ取れなかった場合、タイトルとリンクは取れているので失敗にしない。
     *
     * @return void
     */
    #[Test]
    public function ignoresArticlePageFailuresWhenArticleWasCollected(): void
    {
        $article = Articles::library('nikkansports', '202610190001', '2026-10-19 05:00:00');
        $result = new CollectResult([$article], [new SourceFailure('nikkansports', $article->url, 'GET ... returned HTTP 500.')]);

        self::assertSame([], FailureSummary::fromResult($result, self::NAMES));
    }

    /**
     * @return void
     */
    #[Test]
    public function urlMatchIsPerSource(): void
    {
        $article = Articles::library('keirin.jp', '1', '2026-10-19 05:00:00');
        $result = new CollectResult([$article], [new SourceFailure('nikkansports', $article->url, 'broken')]);

        self::assertEquals([new FailureNotice('nikkansports', '日刊スポーツ', 'broken')], FailureSummary::fromResult($result, self::NAMES));
    }

    /**
     * @return void
     */
    #[Test]
    public function keepsSourceWideFailures(): void
    {
        $result = new CollectResult([], [new SourceFailure('keirin.jp', null, 'GET https://keirin.jp/pc/topicslist returned HTTP 503.')]);

        self::assertEquals(
            [new FailureNotice('keirin.jp', 'KEIRIN.JP', 'GET https://keirin.jp/pc/topicslist returned HTTP 503.')],
            FailureSummary::fromResult($result, self::NAMES),
        );
    }

    /**
     * @return void
     */
    #[Test]
    public function oneNoticePerSourceWithFirstMessage(): void
    {
        $result = new CollectResult([], [
            new SourceFailure('aomori', null, 'first'),
            new SourceFailure('aomori', 'https://aomorikeirin.com/1/', 'second'),
            new SourceFailure('tamano', null, 'third'),
        ]);

        self::assertEquals(
            [new FailureNotice('aomori', '青森競輪', 'first'), new FailureNotice('tamano', '玉野競輪', 'third')],
            FailureSummary::fromResult($result, self::NAMES),
        );
    }

    /**
     * 競輪場は 29 あるので、ページの表に無くても表示名で出せるようにデータに名前を持たせる。
     *
     * @return void
     */
    #[Test]
    public function noticesCarrySourceNames(): void
    {
        $result = new CollectResult([], [new SourceFailure('kumamoto', null, 'HTTP 503')]);

        self::assertSame(
            [['source' => 'kumamoto', 'sourceName' => '熊本競輪', 'message' => 'HTTP 503']],
            array_map(
                static fn(FailureNotice $n): array => $n->toArray(),
                FailureSummary::fromResult($result, ['kumamoto' => '熊本競輪']),
            ),
        );
    }

    /**
     * @return void
     */
    #[Test]
    public function unknownKeyFallsBackToKey(): void
    {
        $result = new CollectResult([], [new SourceFailure('newtrack', null, 'down')]);

        self::assertEquals([new FailureNotice('newtrack', 'newtrack', 'down')], FailureSummary::fromResult($result, []));
    }
}
