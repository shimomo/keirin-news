<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews;

use Shimomo\KeirinNewsCollector\Collector;
use Shimomo\KeirinNewsCollector\Http\HttpFetcher;
use Shimomo\KeirinNewsCollector\Http\NativeSleeper;
use Shimomo\KeirinNewsCollector\Sources\FeedSite;
use Shimomo\KeirinNewsCollector\Sources\FeedSource;
use Shimomo\KeirinNewsCollector\Sources\IdRule;
use Symfony\Component\HttpClient\HttpClient;

/**
 * bin/collect が使う Collector。
 *
 * ライブラリの既定から、GitHub Actions では取れないものを組み替える。
 * - 次の 9 キーは、Actions の IP からの WordPress REST API を 403 で拒む（Xserver などの国外 IP 制限）。
 *   トップページと RSS は通るので、ライブラリの FeedSource で RSS（各最新10件）を読む。ID はライブラリの WordPress 版と同じ「投稿ID-公開日」
 * - 弥彦は REST が 403 で RSS も無い。和歌山はサイト全体が 403。どちらも外す
 *
 * @author shimomo
 */
final class CollectorFactory
{
    /**
     * 外すキー。RSS に差し替えるキーも含む。
     */
    public const array EXCLUDED_KEYS = [
        'yahiko',
        'wakayama',
        'keirin-web',
        'iwakidaira',
        'chiba',
        'nagoya',
        'mukomachi',
        'kishiwada',
        'hofu',
        'sasebo',
        'kumamoto',
    ];

    /**
     * 同じ HttpFetcher を全ソースで共有するので、リクエスト間隔は差し替えた RSS にも効く。
     *
     * @param ?\Shimomo\KeirinNewsCollector\Http\HttpFetcher $fetcher
     * @return \Shimomo\KeirinNewsCollector\Collector
     */
    public static function create(?HttpFetcher $fetcher = null): Collector
    {
        $fetcher ??= new HttpFetcher(HttpClient::create(), new NativeSleeper());

        return new Collector([
            ...Collector::default($fetcher)->without(...self::EXCLUDED_KEYS)->sources(),
            ...self::feedSources($fetcher),
        ]);
    }

    /**
     * @param \Shimomo\KeirinNewsCollector\Http\HttpFetcher $fetcher
     * @return list<\Shimomo\KeirinNewsCollector\Contracts\Source>
     */
    private static function feedSources(HttpFetcher $fetcher): array
    {
        return [
            self::feed($fetcher, 'keirin-web', '月刊競輪WEB', 'https://keirin-web.com/feed/'),
            self::feed($fetcher, 'iwakidaira', 'いわき平競輪', 'https://tairakeirin.com/feed/'),
            // /feed/ は /feed へ 301 で転送される
            self::feed($fetcher, 'chiba', 'PIST6（千葉）', 'https://chiba.pist6.com/feed'),
            self::feed($fetcher, 'nagoya', '名古屋競輪', 'https://nagoyakeirin.com/feed/'),
            self::feed($fetcher, 'mukomachi', '京都向日町競輪', 'https://kyoto-mukomachikeirin.com/feed/'),
            self::feed($fetcher, 'kishiwada', '岸和田競輪', 'https://chalionkun.com/feed/'),
            // ライブラリの REST 版と同じく blog の投稿を固定ラベルで区別する
            self::feed($fetcher, 'hofu', '防府競輪', 'https://www.hofukeirin.jp/blog/feed/', ['ホープくん日記']),
            self::feed($fetcher, 'sasebo', '佐世保競輪', 'https://www.sasebokeirin.jp/feed'),
            // 他場の場外発売の告知。ライブラリの REST 版は分類 ID 160 で除いている
            new CategoryExcludingSource(
                self::feed($fetcher, 'kumamoto', '熊本競輪', 'https://www.kumamotokeirin.jp/feed/'),
                ['発売レース情報ピックアップ'],
            ),
        ];
    }

    /**
     * @param \Shimomo\KeirinNewsCollector\Http\HttpFetcher $fetcher
     * @param string $key
     * @param string $name
     * @param string $url
     * @param list<string> $fixedCategories
     * @return \Shimomo\KeirinNewsCollector\Sources\FeedSource
     */
    private static function feed(HttpFetcher $fetcher, string $key, string $name, string $url, array $fixedCategories = []): FeedSource
    {
        return new FeedSource(new FeedSite($key, $name, $url, IdRule::wordPressGuid(), fixedCategories: $fixedCategories), $fetcher);
    }
}
