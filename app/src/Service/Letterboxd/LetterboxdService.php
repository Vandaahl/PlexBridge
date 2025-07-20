<?php declare(strict_types=1);

namespace App\Service\Letterboxd;

use App\Entity\Event;
use App\Entity\Movie;
use App\Service\Api\HttpClient;
use App\Service\Utility\UtilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class LetterboxdService
{
    public const LETTERBOXD_SEARCH_URL = 'https://letterboxd.com/imdb/';
    public const LETTERBOXD_DIARY_URL = 'https://letterboxd.com/s/save-diary-entry';
    /** @var string When used, it should be appended by Letterboxd movie ID and activity type, e.g. https://letterboxd/com/s/film:51315/watch/ */
    public const LETTERBOXD_ACTIVITY_URL = 'https://letterboxd.com/s/film:';

    public function __construct(
        private HttpClient $httpClient,
        private UtilityService $utilityService,
        private EntityManagerInterface $entityManager
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
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Exception
     */
    public function publishActivity(Movie $movie, string $imdbId, Event $event, ?float $rating = null): array
    {
        $this->httpClient->enableReturnOfRawContent(true);

        $this->httpClient->enableLogging(false);

        $searchPage = $this->httpClient->send(
            $this::LETTERBOXD_SEARCH_URL . $imdbId . "/",
            'GET'
        );

        $filmId = $this->getMovieIdFromMarkup($searchPage);

        // If the film ID could not be found, letterboxd.com markup has probably changed. Log it and abort.
        if ($filmId === null) {
            $this->updateEventStatus($event, null, "movie ID not found");
            return [];
        }

        if (!$movie->getLetterboxdId()) {
            $movie->setLetterboxdId((int) $filmId);
            $this->entityManager->flush();
        }

        $url = $this::LETTERBOXD_ACTIVITY_URL . "{$filmId}";

        $postData = [
            'viewingableUid' => "film:$filmId",
            'viewingableUID' => "film:$filmId",
            'specifiedDate' => "true",
            'viewingDateStr' => date('Y-m-d'),
            'rating' => 0,
        ];

        if ($rating) {
            $postData['rating'] = $rating;
        }

        // Marking a movie as watched (which is different from logged) will return a Letterboxd error if the movie was watched and logged before.
        $result = $this->submitActivity('watched', $url, $event, ['watched' => true]);

        // The movie was watched before, so we mark it as a rewatch for the new log entry. If there is a rating, we don't want a rewatch, because
        // we assume the rewatch was scrobbled and the rating was added manually.
        if (!$rating && $result['result'] === false && $result['errorCodes'][0] === 'film.not.watched.but.viewing.exists') {
            $postData['rewatch'] = "true";
        }

        return $this->submitActivity(
            'diary',
            '',
            $event,
            $postData
        );
    }

    /**
     * @param string $letterboxdId
     * @param float $rating
     * @param Event|string $event Either an Event object or an Event ID, used for updating the status to logged, failed, etc.
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function submitRating(string $letterboxdId, float $rating, Event|string $event): array
    {
        return $this->submitActivity(
            'diary',
            '',
            $event,
            [
                'filmId' => $letterboxdId,
                'specifiedDate' => "true",
                'viewingDateStr' => date('Y-m-d'), // e.g. 2014-08-11
                'rating' => $rating,
            ]
        );
    }

    /**
     * @param string $type One of watched/rating/diary
     * @param string $movieUrl E.g. 'https://letterboxd.com/film/the-cabin-in-the-woods/'
     * @param array $data Post data
     * @param string|Event $event Either an Event object or an Event ID, used for updating the status to logged, failed, etc
     * @return array E.g. ["result"=>true,"csrf"=>"c82e4e46ab6159f5ae24","messages"=>["\u2018The Babysitter\u2019 was added to your films."],"errorCodes":["viewing.created"],"errorFields":[],...]
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function submitActivity(string $type, string $movieUrl, string|Event $event, array $data = []): array
    {
        $url = match ($type) {
            'watched' => $movieUrl . "/watch/",
            'rating' => $movieUrl . "/rate/",
            'diary' => $this::LETTERBOXD_DIARY_URL
        };

        $postData = array_merge($data, ['__csrf' => $this->utilityService->getDockerSecret('letterboxd_cookie_csrf_value')]);

        $this->httpClient->enableLogging(true, 'letterboxd');

        $return = $this->httpClient->send(
            $url,
            'POST',
            $postData,
            [
                'Cookie' => sprintf(
                    'letterboxd.user=%s; com.xk72.webparts.csrf=%s',
                    rawurlencode($this->utilityService->getDockerSecret("letterboxd_cookie_user_value")),
                    rawurlencode($this->utilityService->getDockerSecret("letterboxd_cookie_csrf_value"))
                ),
                'Content-Type' =>  'application/x-www-form-urlencoded'
            ]
        );

        if (is_string($return) && json_validate($return)) {
            $return = json_decode($return, true);
        }

        $this->updateEventStatus($event, $return);

        return $return;
    }

    /**
     * Returns Letterboxd movie ID (e.g. '24492').
     *
     */
    private function getMovieIdFromMarkup(string $markup): ?string
    {
        if (preg_match('/film-poster.*".*data-film-id="(.*)"/iU', $markup, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function updateEventStatus(string|Event $event, ?array $letterboxdData, ?string $customMessage = null): void
    {
        if (!is_object($event)) {
            $event = $this->entityManager->getRepository(Event::class)->find($event);
        }

        if (!$event) {
            return;
        }

        $message = ($letterboxdData) ? "logged" : $customMessage;

        if ($letterboxdData && isset($letterboxdData['result']) && $letterboxdData['result'] !== true) {
            $message = "failed";
        } elseif ($letterboxdData && isset($letterboxdData['rating'])) {
            $message = "logged and rated";
        }

        $event->setStatusLetterboxd($message);

        $this->entityManager->flush();
    }
}