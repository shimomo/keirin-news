<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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

    /**
     * KEIRIN.JP のトピック一覧に壊れた項目が1つ残っていると、毎回の収集で同じ失敗が出る。
     * 記事が返っている配信元の「1件のデータ不備」（例外を伴わない失敗）は、取得の失敗ではないので出さない。
     *
     * @return void
     */
    #[Test]
    public function ignoresItemFailuresWhenSourceReturnedArticles(): void
    {
        $result = new CollectResult(
            [Articles::library('keirin.jp', '1', '2026-10-20 00:00:00')],
            [new SourceFailure('keirin.jp', null, 'Topic entry is missing newsNo, keisaiSDate, newsTitle, or filePathPc.')],
        );

        self::assertSame([], FailureSummary::fromResult($result, self::NAMES));
    }

    /**
     * 書式が変わって全件が読めなくなった場合は、記事が0件になるので気づけるように出す。
     *
     * @return void
     */
    #[Test]
    public function keepsItemFailuresWhenSourceReturnedNothing(): void
    {
        $result = new CollectResult([], [new SourceFailure('aomori', 'https://aomorikeirin.com/1/', 'Unparseable date_gmt "x".')]);

        self::assertEquals([new FailureNotice('aomori', '青森競輪', 'Unparseable date_gmt "x".')], FailureSummary::fromResult($result, self::NAMES));
    }

    /**
     * 同じキーの別の Source（玉野の posts と preexamination など）が記事を返していても、取得の失敗は出す。
     *
     * @return void
     */
    #[Test]
    public function keepsFetchFailuresEvenWithArticles(): void
    {
        $result = new CollectResult(
            [Articles::library('tamano', '1', '2026-10-20 10:00:00')],
            [new SourceFailure('tamano', null, 'GET ... returned HTTP 503.', new RuntimeException('HTTP 503'))],
        );

        self::assertEquals([new FailureNotice('tamano', '玉野競輪', 'GET ... returned HTTP 503.')], FailureSummary::fromResult($result, self::NAMES));
    }
}
