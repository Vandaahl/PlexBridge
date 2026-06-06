<?php declare(strict_types=1);

namespace App\DataTransferObject;

final class PlexEventDTO
{
    /**
     * @param string $event media.scrobble|media.rate
     * @param float|null $rating
     * @param string $type movie|episode|show|season|track
     * @param string $guid E.g. plex://episode/5d9c120746115600200ce4fd
     * @param string $title
     * @param string|null $originalTitle
     * @param int $year
     * @param string $date
     * @param string|null $imdb E.g. imdb://tt3641472
     * @param string $user Plex user
     */
    public function __construct(
        public readonly string $event,
        public readonly ?float $rating,
        public readonly string $type,
        public readonly string $guid,
        public readonly string $title,
        public readonly ?string $originalTitle,
        public readonly int $year,
        public readonly string $date,
        public readonly ?string $imdb,
        public readonly string $user
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['event'],
            $data['rating'] ?? null,
            $data['Metadata']['type'],
            $data['Metadata']['guid'],
            $data['Metadata']['title'],
            $data['Metadata']['originalTitle'] ?? null,
            $data['Metadata']['year'],
            date('Y-m-d\TH:i:s\.\0\0\0\Z'),
            $data['Metadata']['Guid'][0]['id'],
            $data['Account']['title']
        );
    }
}
