<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shimomo\KeirinNews\CollectRun;
use Shimomo\KeirinNews\DataStore;
use Shimomo\KeirinNews\RunReport;
use Shimomo\KeirinNews\StoredArticle;
use Shimomo\KeirinNews\Tests\Support\Articles;
use Shimomo\KeirinNews\Tests\Support\TempDirectory;
use Shimomo\KeirinNewsCollector\Collector;
use Shimomo\KeirinNewsCollector\SourceFailure;
use Shimomo\KeirinNewsCollector\Sources\ArraySource;

/**
 * @author shimomo
 */
final class CollectRunTest extends TestCase
{
    private string $dir = '';

    private ?DataStore $store = null;

    /**
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        $this->dir = TempDirectory::path();
        $this->store = new DataStore($this->dir);
    }

    /**
     * @return void
     */
    #[\Override]
    protected function tearDown(): void
    {
        TempDirectory::remove($this->dir);
    }

    /**
     * 初回は since なしで、各配信元が返す分をすべて取り込む。
     *
     * @return void
     */
    #[Test]
    public function firstRunTakesEverything(): void
    {
        $old = Articles::library('tamano', '1', '2026-07-01 10:00:00');
        $this->collect([new ArraySource('tamano', '玉野競輪', [$old])]);

        self::assertFileExists($this->dir . '/2026-07-01.json');
    }

    /**
     * 2回目以降は48時間前から。境界ちょうどは含む。
     *
     * @return void
     */
    #[Test]
    public function laterRunsLookBack48Hours(): void
    {
        $this->store()->save([Articles::stored('sanyo', '0', '2026-10-15 10:00:00')], '2026-10-20T11:17:05+09:00', []);
        $report = $this->collect([new ArraySource('sanyo', '山陽', [
            Articles::library('sanyo', 'old', '2026-10-18 12:17:04'),
            Articles::library('sanyo', 'edge', '2026-10-18 12:17:05'),
        ])]);

        self::assertSame(1, $report->added);
        self::assertSame(['edge'], array_map(static fn(StoredArticle $a): string => $a->id, array_values(array_filter(
            $this->store()->loadArticles(),
            static fn(StoredArticle $a): bool => $a->date() === '2026-10-18',
        ))));
    }

    /**
     * @return void
     */
    #[Test]
    public function writesCheckedAtAndFailures(): void
    {
        $this->collect([
            new ArraySource('youtube', 'YouTube', [Articles::library('youtube', 'v', '2026-10-20 07:00:00')]),
            new ArraySource('autorace.jp', 'AUTORACE.JP', [], [new SourceFailure('autorace.jp', null, 'HTTP 503')]),
        ]);
        $index = json_decode((string) file_get_contents($this->dir . '/index.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($index);
        self::assertSame('2026-10-20T12:17:05+09:00', $index['checkedAt']);
        self::assertSame([['source' => 'autorace.jp', 'sourceName' => 'AUTORACE.JP', 'message' => 'HTTP 503']], $index['failures']);
    }

    /**
     * @return void
     */
    #[Test]
    public function partialFailureStillSavesAndExitsZero(): void
    {
        $report = $this->collect([
            new ArraySource('youtube', 'YouTube', [Articles::library('youtube', 'v', '2026-10-20 07:00:00')]),
            new ArraySource('autorace.jp', 'AUTORACE.JP', [], [new SourceFailure('autorace.jp', null, 'HTTP 503')]),
        ]);

        self::assertFalse($report->allFailed);
        self::assertSame(0, $report->exitCode());
        self::assertFileExists($this->dir . '/2026-10-20.json');
    }

    /**
     * 玉野は posts・preexamination などの Source が同じキーを持つので、キー単位で数える。
     *
     * @return void
     */
    #[Test]
    public function allSourcesFailedExitsNonZero(): void
    {
        $report = $this->collect([
            new ArraySource('tamano', '玉野競輪', [], [new SourceFailure('tamano', null, 'posts down')]),
            new ArraySource('tamano', '玉野競輪', [], [new SourceFailure('tamano', null, 'preexamination down')]),
            new ArraySource('sanyo', '山陽', [], [new SourceFailure('sanyo', null, 'down')]),
        ]);

        self::assertTrue($report->allFailed);
        self::assertSame(1, $report->exitCode());
        self::assertFileExists($this->dir . '/index.json');
    }

    /**
     * 上書きで日付が変わった記事は、新しい日へ移り、元の日には残らない。
     *
     * @return void
     */
    #[Test]
    public function movesArticleWhenPublishedDateChanges(): void
    {
        $this->store()->save([Articles::stored('autorace.jp', '1', '2026-10-19 23:50:00')], '2026-10-20T11:17:05+09:00', []);
        $this->collect([new ArraySource('autorace.jp', 'AUTORACE.JP', [Articles::library('autorace.jp', '1', '2026-10-20 00:05:00')])]);

        self::assertFileDoesNotExist($this->dir . '/2026-10-19.json');
        self::assertCount(1, $this->store()->loadArticles());
    }

    /**
     * @return void
     */
    #[Test]
    public function summaryNamesFailedSources(): void
    {
        $report = $this->collect([new ArraySource('autorace.jp', 'AUTORACE.JP', [], [new SourceFailure('autorace.jp', null, 'HTTP 503')])]);

        self::assertStringContainsString('autorace.jp', $report->summary());
        self::assertStringContainsString('HTTP 503', $report->summary());
    }

    /**
     * @param list<\Shimomo\KeirinNewsCollector\Contracts\Source> $sources
     * @return \Shimomo\KeirinNews\RunReport
     */
    private function collect(array $sources): RunReport
    {
        $now = new DateTimeImmutable('2026-10-20 12:17:05', new DateTimeZone('Asia/Tokyo'));

        return (new CollectRun(new Collector($sources), $this->store()))->run($now);
    }

    /**
     * @return \Shimomo\KeirinNews\DataStore
     */
    private function store(): DataStore
    {
        self::assertNotNull($this->store);

        return $this->store;
    }
}
