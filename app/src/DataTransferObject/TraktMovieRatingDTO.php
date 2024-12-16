<?php declare(strict_types=1);

namespace App\DataTransferObject;

final class TraktMovieRatingDTO
{
    public function __construct(
        public readonly string $ratedAt,
        public readonly int $rating,
        public readonly array $ids
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['rated_at'],
            $data['rating'],
            $data['ids']
        );
    }
}
