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

        if ($filmId === null) {
            throw new \Exception("Movie with ID $imdbId not found");
        }

        if (!$movie->getLetterboxdId()) {
            $movie->setLetterboxdId((int) $filmId);
            $this->entityManager->flush();
        }

        $url = $this::LETTERBOXD_ACTIVITY_URL . "{$filmId}";

        $result = $this->submitActivity('watched', $url, $event);

        if ($rating) {
            $result = $this->submitRating($filmId, $rating, $event);
        }

        return $result;
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
                'specifiedDate' => true,
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
                'Cookie:letterboxd.user="' . $this->utilityService->getDockerSecret('letterboxd_cookie_user_value') . '"; com.xk72.webparts.csrf=' . $this->utilityService->getDockerSecret('letterboxd_cookie_csrf_value') . ';',
                'Content-Type' => 'application/x-www-form-urlencoded'
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
        if (preg_match('/id="backdrop".*data-film-id="(.*)"/iU', $markup, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function updateEventStatus(string|Event $event, array $letterboxdData): void
    {
        if (!is_object($event)) {
            $event = $this->entityManager->getRepository(Event::class)->find($event);
        }

        if (!$event) {
            return;
        }

        $message = "logged";

        if (isset($letterboxdData['result']) && $letterboxdData['result'] !== true) {
            $message = "failed";
        } elseif (isset($letterboxdData['rating'])) {
            $message = " logged and rated";
        }

        $event->setStatusLetterboxd($message);

        $this->entityManager->flush();
    }
}