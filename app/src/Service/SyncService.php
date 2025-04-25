<?php declare(strict_types=1);

namespace App\Service;

use App\DataTransferObject\PlexEventDTO;
use App\Entity\Episode;
use App\Entity\Event;
use App\Entity\Movie;
use App\Service\Letterboxd\LetterboxdService;
use App\Service\Trakt\TraktService;
use App\Service\Utility\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Psr\Log\LoggerInterface;

class SyncService
{
    public function __construct(
        private LoggerInterface $incomingLogger,
        private TraktService $traktService,
        private LetterboxdService $letterboxdService,
        private SettingsService $settingsService,
        private EntityManagerInterface $entityManager,
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
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \Exception
     */
    public function handleIncomingRequests(string $postData): void
    {
        /** @var array{event: string, rating: float, Account: array{id: int, thumb: string, title: string}, Metadata: array{title: string, originalTitle: string, summary: string, year: int, type: string, lastRatedAt: int, Guid: array{array{id: string}}, Director: array{array{tag: string}}}} $data */
        $data = json_decode($postData, true);

        if (!isset($data['Metadata'])) {
            return;
        }

        if (!in_array($data['event'], ['media.rate', 'media.scrobble'])) {
            return;
        }

        if ($data['Metadata']['type'] === 'track') {
            return;
        }

        $plexData = PlexEventDTO::fromArray($data);

        $this->incomingLogger->info($postData);

        $event = new Event();
        $event->setDate(new \DateTimeImmutable($plexData->date));
        $event->setRating($plexData->rating);
        $event->setEvent($plexData->event);
        $event->setPlexUser($plexData->user);
        $this->entityManager->persist($event);

        $movie = $episode = null;

        if ($plexData->type === 'movie') {
            if (!$movie = $this->checkIfMediaExists($plexData->guid, Movie::class)) {
                $movie = new Movie();
                $movie->setTitle($plexData->title);
                $movie->setYear($plexData->year);
                $movie->setImdb($plexData->imdb);
                $movie->setOriginalTitle($plexData->originalTitle);
                $movie->setPlexGuid($plexData->guid);
                $this->entityManager->persist($movie);
            }
        } elseif ($plexData->type === 'episode') {
            if (!$episode = $this->checkIfMediaExists($plexData->guid, Episode::class)) {
                $episode = new Episode();
                $episode->setTitle($plexData->title);
                $episode->setYear($plexData->year);
                $episode->setImdb($plexData->imdb);
                $episode->setPlexGuid($plexData->guid);
                $this->entityManager->persist($episode);
            }
        }

        if ($movie) {
            $event->setMovie($movie);
        } elseif ($episode) {
            $event->setEpisode($episode);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $activeServices = $this->settingsService->getSettings('services');
        if (count($activeServices) === 0) {
            return;
        }

        $guid = $this->getGuid($plexData->imdb, $plexData->type);

        if (in_array('trakt', $activeServices)) {
            $action = match ($plexData->event) {
                'media.scrobble' => function () use ($plexData, $event, $guid) {
                    if ($this->traktService->scrobble($guid, $plexData->type)) {
                        $event->setStatusTrakt('scrobbled');
                    }
                },
                'media.rate' => function() use($plexData, $event, $guid) {
                    if ($plexData->rating) {
                        $response = $this->traktService->rateMedia($guid, $plexData->rating, $plexData->date, $plexData->type);
                        if ($response) {
                            $event->setStatusTrakt($response);
                        }
                    }
                },
                default => ''
            };

            $action(); // Call the selected closure
            $this->entityManager->flush();
        }

        if (in_array('letterboxd', $activeServices) && $plexData->type === 'movie') {
            $action = match ($plexData->event) {
                'media.scrobble' => function() use ($movie, $event, $guid) {
                    $this->letterboxdService->publishActivity($movie, $guid, $event);
                },
                'media.rate' => function() use ($movie, $event, $plexData, $guid) {
                    if ($plexData->rating === null) {
                        return;
                    }
                    $this->letterboxdService->publishActivity($movie, $guid, $event, $plexData->rating);
                },
                default => ''
            };

            $action(); // Call the selected closure
        }
    }

    /**
     * @param string $id "imdb://tt0033467", "tmdb://4267", "tvdb://1747"
     * @param string|null $type movie|episode|show|season
     * @return string|null tt0033467
     */
    private function getGuid(string $id, ?string $type): ?string
    {
        $brand = 'imdb';
        $prefix = "$brand://";

        if (!str_starts_with($id, $prefix)) {
            return null;
        }

        return str_replace($prefix, '', $id);
    }

    /**
     * @param string $plexGuid
     * @param string $className FQN
     * @return Movie|Episode|null
     */
    private function checkIfMediaExists(string $plexGuid, string $className): Movie|Episode|null
    {
        $repository = $this->entityManager->getRepository($className);
        return $repository->findOneBy(['plexGuid' => $plexGuid]);
    }
}