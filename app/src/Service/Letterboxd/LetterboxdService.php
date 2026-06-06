<?php declare(strict_types=1);

namespace App\Service\Letterboxd;

use App\Entity\Event;
use App\Entity\Movie;
use App\Service\Api\HttpClient;
use App\Service\Utility\UtilityService;
use Doctrine\ORM\EntityManagerInterface;

class LetterboxdService
{
    public const LETTERBOXD_SEARCH_URL = 'https://letterboxd.com/imdb/';
    //public const LETTERBOXD_DIARY_URL = 'https://letterboxd.com/s/save-diary-entry';
    public const LETTERBOXD_DIARY_URL = 'https://letterboxd.com/api/v0/production-log-entries';
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
     * @param Movie $movie
     * @param string $imdbId
     * @param Event $event
     * @param float|null $rating
     * @return void
     * @throws \Exception
     */
    public function publishActivity(Movie $movie, string $imdbId, Event $event, ?float $rating = null): void
    {
        $this->httpClient->enableReturnOfRawContent(true);

        $this->httpClient->enableLogging(false);

        $searchPage = $this->httpClient->send(
            $this::LETTERBOXD_SEARCH_URL . $imdbId . "/",
            'GET'
        );

        $filmId = $this->getMovieIdFromMarkup($searchPage, 'uid');
        $filmLid = $this->getMovieIdFromMarkup($searchPage, 'lid');

        // If the film ID could not be found, letterboxd.com markup has probably changed. Log it and abort.
        if ($filmId === null) {
            $this->updateEventStatus($event, null, "movie ID not found");
            return;
        }

        if (!$movie->getLetterboxdId()) {
            $movie->setLetterboxdId((int) $filmId);
        }

        if (!$movie->getLid()) {
            $movie->setLid($filmLid);
        }

        $this->entityManager->flush();

        $url = $this::LETTERBOXD_ACTIVITY_URL . "{$filmId}";

        $filmLid = $this->getMovieIdFromMarkup($searchPage, 'lid');

        $postData = [
            "productionId" => $filmLid,
            "diaryDetails" => [
                "diaryDate" => date('Y-m-d'),
                "rewatch" => "false"
            ],
            "tags" => [],
            "like" => "false"
        ];

        if ($rating) {
            $postData['rating'] = $rating;
        }

        // Marking a movie as watched (which is different from logged) will return a Letterboxd error if the movie was watched and logged before.
        $result = $this->submitActivity('watched', $url, $event, ['watched' => 'true']);

        // The movie was watched before, so we mark it as a rewatch for the new log entry. If there is a rating, we don't want a rewatch, because
        // we assume the rewatch was scrobbled and the rating was added manually.
        if (!$rating &&
            is_array($result) &&
            isset($result['result'], $result['errorCodes']) &&
            $result['result'] === false &&
            $result['errorCodes'][0] === 'film.not.watched.but.viewing.exists'
        )
        {
            $postData['diaryDetails']['rewatch'] = 'true';
        }

        $this->submitActivity(
            'diary',
            '',
            $event,
            $postData
        );
    }

    /**
     * @param string $lid Letterboxd movie ID
     * @param float $rating
     * @param Event|string $event Either an Event object or an Event ID, used for updating the status to logged, failed, etc.
     * @return void
     * @throws \Exception
     */
    public function submitRating(string $lid, float $rating, Event|string $event): void
    {
        $this->submitActivity(
            'diary',
            '',
            $event,
            [
                'productionId' => $lid,
                "diaryDetails" => [
                    "diaryDate" => date('Y-m-d'),
                    //"rewatch" => "false"
                ],
                'rating' => $rating,
            ]
        );
    }

    /**
     * @param string $type One of watched/rating/diary
     * @param string $movieUrl E.g. 'https://letterboxd.com/film/the-cabin-in-the-woods/'
     * @param array $data Post data
     * @param string|Event $event Either an Event object or an Event ID, used for updating the status to logged, failed, etc
     * @return string|array E.g. ["result"=>true,"csrf"=>"c82e4e46ab6159f5ae24","messages"=>["\u2018The Babysitter\u2019 was added to your films."],"errorCodes":["viewing.created"],"errorFields":[],...]
     * @throws \Exception
     */
    private function submitActivity(string $type, string $movieUrl, string|Event $event, array $data = []): string|array
    {
        $url = match ($type) {
            'watched' => $movieUrl . "/watch/",
            'rating' => $movieUrl . "/rate/",
            'diary' => $this::LETTERBOXD_DIARY_URL
        };

        $postData = $type !== 'diary' ? array_merge($data, ['__csrf' => $this->utilityService->getDockerSecret('letterboxd_cookie_csrf_value')]) : $data;

        $this->httpClient->enableLogging(true, 'letterboxd');

        $contentType = $type !== 'diary' ? 'application/x-www-form-urlencoded' : 'application/json; charset=UTF-8';

        $return = $this->httpClient->send(
            $url,
            'POST',
            $postData,
            [
                'Content-Type' => $contentType,
                'x-csrf-token' => $this->utilityService->getDockerSecret('letterboxd_cookie_csrf_value'),
                'Cookie' => sprintf(
                    'letterboxd.user=%s; com.xk72.webparts.csrf=%s',
                    rawurlencode($this->utilityService->getDockerSecret("letterboxd_cookie_user_value")),
                    rawurlencode($this->utilityService->getDockerSecret("letterboxd_cookie_csrf_value"))
                ),
            ]
        );

        if (is_string($return) && json_validate($return)) {
            $return = json_decode($return, true);
        } elseif (is_string($return)) {
            $customMessage = $return;
        }

        $this->updateEventStatus($event, !isset($customMessage) ? $return : null, $customMessage ?? null);

        return $return;
    }

    /**
     * Returns Letterboxd movie ID (e.g. '24492').
     *
     * @param string $markup
     * @param string $type One of 'uid' (default) or 'lid'
     * @return string|null
     */
    private function getMovieIdFromMarkup(string $markup, string $type = 'uid'): ?string
    {
        if ($type !== 'uid' && $type !== 'lid') {
            $type = 'uid';
        }

        if (preg_match('/data-component-class="LazyPoster".+data-postered-identifier=\'(.+)\'/iU', $markup, $matches)) {
            // $matches[1] will look like this: {"lid":"MUU","uid":"film:18804","type":"film","typeName":"film"}
            $data = json_decode(html_entity_decode($matches[1]), true);
            if (str_starts_with($data[$type], 'film:')) {
                return substr($data[$type], 5);
            } else {
                return $data[$type];
            }
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

        if ($letterboxdData) {
            if (isset($letterboxdData['result']) && $letterboxdData['result'] !== true) {
                $message = "failed";
            } elseif (isset($letterboxdData['rating'])) {
                $message = "logged and rated";
            }
        }

        $event->setStatusLetterboxd($message);

        $this->entityManager->flush();
    }
}