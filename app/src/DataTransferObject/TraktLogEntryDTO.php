<?php declare(strict_types=1);

namespace App\DataTransferObject;

final class TraktLogEntryDTO
{
    public function __construct(
        public readonly ?array $movies,
        public readonly ?array $episodes,
        public readonly ?array $movie,
        public readonly ?array $episode,
        public readonly ?int $progress,
        public readonly ?TraktScrobbleDTO $scrobbledItem,
        public readonly ?array $added,
        public readonly ?array $notFound,
        public readonly string $logDate
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['movies']) && is_array($data['movies']) ? array_map(function($ratingArray) {
                return TraktMovieRatingDTO::fromArray($ratingArray);
            }, $data['movies']) : null,
            isset($data['episodes']) && is_array($data['episodes']) ? array_map(function($ratingArray) {
                return TraktEpisodeRatingDTO::fromArray($ratingArray);
            }, $data['episodes']) : null,
            $data['movie'] ?? null,
            $data['episode'] ?? null,
            $data['progress'] ?? null,
            isset($data[0]) && is_string($data[0]) ? TraktScrobbleDTO::fromArray(json_decode($data[0], true)) : null,
            //isset($data[0]) && is_string($data[0]) ? dd($data[0]) : null,
            $data['added'] ?? null,
            $data['not_found'] ?? null,
            $data['logDate']
        );
    }
}
