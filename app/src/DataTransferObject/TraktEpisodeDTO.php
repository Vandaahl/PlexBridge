<?php declare(strict_types=1);

namespace App\DataTransferObject;

final class TraktEpisodeDTO
{
    public function __construct(
        public readonly int $season,
        public readonly int $number,
        public readonly string $title,
        public readonly array $ids
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['season'],
            $data['number'],
            $data['title'],
            $data['ids']
        );
    }
}
