<?php

declare(strict_types=1);

namespace Shimomo\KeirinNews;

/**
 * ページに出す「この配信元の取得に失敗した」1件。
 *
 * @author shimomo
 */
final readonly class FailureNotice
{
    /**
     * @param string $source ソースキー
     * @param string $sourceName 表示名。競輪場は数が多いので、ページはこれから配信元名を作る
     * @param string $message ライブラリの失敗メッセージ（最初の1件）
     */
    public function __construct(
        public string $source,
        public string $sourceName,
        public string $message,
    ) {
    }

    /**
     * @return array{source: string, sourceName: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'sourceName' => $this->sourceName,
            'message' => $this->message,
        ];
    }
}
