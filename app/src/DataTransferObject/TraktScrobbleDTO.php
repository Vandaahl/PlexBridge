<?php declare(strict_types=1);

namespace App\DataTransferObject;

final class TraktScrobbleDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $action,
        public readonly float $progress,
        public readonly array $sharing,
        public readonly ?TraktMovieDTO $movie,
        public readonly ?TraktEpisodeDTO $episode
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['action'],
            $data['progress'],
            $data['sharing'],
            $data['movie'] ? TraktMovieDTO::fromArray(json_decode($data['movie'], true)) : null,
            $data['episode'] ? TraktEpisodeDTO::fromArray(json_decode($data['episode'], true)) : null,
        );
    }
}
