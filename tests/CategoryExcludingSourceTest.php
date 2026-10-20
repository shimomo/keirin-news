<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shimomo\KeirinNews\CategoryExcludingSource;
use Shimomo\KeirinNews\Tests\Support\Articles;
use Shimomo\KeirinNewsCollector\Article;
use Shimomo\KeirinNewsCollector\SourceFailure;
use Shimomo\KeirinNewsCollector\Sources\ArraySource;

/**
 * 熊本の RSS から「発売レース情報ピックアップ」（他場の場外発売の告知）を除く。ライブラリの REST 版と同じ結果にするため。
 *
 * @author shimomo
 */
final class CategoryExcludingSourceTest extends TestCase
{
    /**
     * @return void
     */
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

        self::assertSame(['2', '3'], array_map(static fn(Article $a): string => $a->id, $result->articles));
        self::assertSame([$failure], $result->failures);
        self::assertSame('kumamoto', $source->key());
        self::assertSame('熊本競輪', $source->name());
    }

    /**
     * @return void
     */
    #[Test]
    public function passesSinceToInner(): void
    {
        $source = new CategoryExcludingSource(
            new ArraySource('kumamoto', '熊本競輪', [
                Articles::library('kumamoto', 'old', '2026-10-17 10:00:00'),
                Articles::library('kumamoto', 'new', '2026-10-19 10:00:00'),
            ]),
            ['発売レース情報ピックアップ'],
        );
        $since = new DateTimeImmutable('2026-10-18 00:00:00', new DateTimeZone('Asia/Tokyo'));

        self::assertSame(['new'], array_map(static fn(Article $a): string => $a->id, $source->fetch($since)->articles));
    }
}
