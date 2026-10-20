<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shimomo\KeirinNews\CollectorFactory;
use Shimomo\KeirinNews\Tests\Support\Feeds;
use Shimomo\KeirinNewsCollector\Article;
use Shimomo\KeirinNewsCollector\Contracts\Source;

/**
 * Actions の IP から REST API が 403 になる 9 キーを RSS に差し替え、取得できない弥彦・和歌山を外す。
 *
 * @author shimomo
 */
final class CollectorFactoryTest extends TestCase
{
    /**
     * @return void
     */
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

    /**
     * 千葉と佐世保は /feed/ が /feed へ 301 で転送されるので、転送先を直接読む。どのキーも wp-json を要求しない。
     *
     * @return void
     */
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

    /**
     * ライブラリの WordPress 版と同じ「投稿 ID-公開日（JST）」。
     *
     * @return void
     */
    #[Test]
    public function feedIdsMatchWordPressRule(): void
    {
        $result = CollectorFactory::create(Feeds::fetcher([Feeds::fixture('nagoya/feed.xml')]))->only('nagoya')->collect();

        self::assertSame('47378-20261017', $result->articles[0]->id);
        self::assertSame('名古屋競輪', $result->articles[0]->sourceName);
        self::assertSame('2026-10-17T17:00:00+09:00', $result->articles[0]->publishedAt->format(DATE_ATOM));
        self::assertSame('47299-20261004', $result->articles[1]->id);
    }

    /**
     * @return void
     */
    #[Test]
    public function hofuAddsFixedCategory(): void
    {
        $result = CollectorFactory::create(Feeds::fetcher([Feeds::fixture('nagoya/feed.xml')]))->only('hofu')->collect();

        self::assertSame('ホープくん日記', $result->articles[0]->categories[0]);
    }

    /**
     * @return void
     */
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

        self::assertSame(['2-20261019'], array_map(static fn(Article $a): string => $a->id, $result->articles));
    }
}
