<?php declare(strict_types=1);

namespace App\Service;

use App\Service\Letterboxd\LetterboxdService;
use App\Service\Trakt\TraktService;
use App\Service\Utility\SettingsService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

class SyncService
{
    public function __construct(
        private LoggerInterface $incomingLogger,
        private TraktService $traktService,
        private LetterboxdService $letterboxdService,
        private SettingsService $settingsService,
    )
    {
        // Get the timezone from the Docker environment variable.
        $timezone = getenv('TZ');

        // Set the correct timezone.
        if ($timezone) {
            date_default_timezone_set($timezone);
        }
    }

    /**
     * @param string $postData
     * @return void
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function handleIncomingRequests(string $postData): void
    {
        $settings = $this->settingsService->getSettingsFromStorage();
        if (!$settings or count($settings['settings']['services']) === 0) {
            return;
        }

        /** @var array{event: string, rating: float, Account: array{id: int, thumb: string, title: string}, Metadata: array{title: string, originalTitle: string, summary: string, year: int, type: string, lastRatedAt: int, Guid: array{array{id: string}}, Director: array{array{tag: string}}}} $data */
        $data = json_decode($postData, true);
        /** @var string $event E.g. 'media.play', 'media.stop', 'media.scrobble', 'media.rate' */
        $event = $data['event'];
        if (in_array($event, ['media.play', 'media.stop','media.pause'])) {
            return;
        }
        $metadata = $data['Metadata'];
        $lastRatedAt = (isset($metadata['lastRatedAt'])) ? date('Y-m-d\TH:i:s\.\0\0\0\Z', $metadata['lastRatedAt']) : null;
        $rating = $data['rating'] ?? 0;
        $guids = $metadata['Guid'];
        /** @var string $type movie|episode|show|season|track */
        $type = $metadata['type'];
        $guid = $this->getGuid($guids, $type);

        if ($type === 'track') {
            return;
        }

        $this->incomingLogger->info($postData);

        if (isset($settings['settings']['services']) && in_array('trakt', $settings['settings']['services'])) {
            match ($event) {
                'media.play' => '',
                'media.stop' => '',
                'media.scrobble' => $this->traktService->scrobble($guid, $type),
                'media.rate' => ($lastRatedAt) ? $this->traktService->rateMedia($guid, $rating, $lastRatedAt, $type) : null,
                default => ''
            };
        }

        if (isset($settings['settings']['services']) && in_array('letterboxd', $settings['settings']['services']) && $type === 'movie') {
            match ($event) {
                'media.play' => '',
                'media.stop' => '',
                'media.scrobble' => $this->letterboxdService->publishActivity($guid, null),
                'media.rate' => ($lastRatedAt) ? $this->letterboxdService->publishActivity($guid, $rating) : null,
                default => ''
            };
        }
    }

    /**
     * @param array{array{id: string}} $ids "imdb://tt0033467", "tmdb://4267", "tvdb://1747"
     * @param string|null $type movie|episode|show|season
     * @return string|null tt0033467
     */
    private function getGuid(array $ids, ?string $type): ?string
    {
        $brand = 'imdb';
        $prefix = "$brand://";

        $result = array_filter($ids, function($id) use ($brand) {
            return str_starts_with($id['id'], $brand);
        });

        if ($result) {
            return str_replace($prefix, '', reset($result)['id']);
        }

        return null;
    }
}