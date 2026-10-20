<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews;

use DateTimeImmutable;
use Shimomo\KeirinNewsCollector\Article;
use Shimomo\KeirinNewsCollector\Contracts\Source;
use Shimomo\KeirinNewsCollector\FetchResult;

/**
 * 包んだ Source の記事から、指定した分類（完全一致）を持つものを落とす。キー・名前・失敗は包んだ Source のまま。
 *
 * 熊本の RSS 用。ライブラリの REST 版は分類 ID で「発売レース情報ピックアップ」を除いているが、RSS では ID が取れないので名前で除く。
 *
 * @author shimomo
 */
final readonly class CategoryExcludingSource implements Source
{
    /**
     * @param \Shimomo\KeirinNewsCollector\Contracts\Source $inner
     * @param list<string> $excludedCategories
     */
    public function __construct(
        private Source $inner,
        private array $excludedCategories,
    ) {
    }

    /**
     * @return string
     */
    #[\Override]
    public function key(): string
    {
        return $this->inner->key();
    }

    /**
     * @return string
     */
    #[\Override]
    public function name(): string
    {
        return $this->inner->name();
    }

    /**
     * @param ?\DateTimeImmutable $since
     * @return \Shimomo\KeirinNewsCollector\FetchResult
     */
    #[\Override]
    public function fetch(?DateTimeImmutable $since): FetchResult
    {
        $result = $this->inner->fetch($since);

        return new FetchResult(
            array_values(array_filter(
                $result->articles,
                fn(Article $article): bool => array_intersect($article->categories, $this->excludedCategories) === [],
            )),
            $result->failures,
        );
    }
}
