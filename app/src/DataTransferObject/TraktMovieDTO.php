<?php declare(strict_types=1);

namespace App\DataTransferObject;

final class TraktMovieDTO
{
    public function __construct(
        public readonly string $title,
        public readonly int $year,
        public readonly array $ids
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['title'],
            $data['year'],
            $data['ids']
        );
    }
}
