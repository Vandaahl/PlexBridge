<?php declare(strict_types=1);

namespace App\Service\Letterboxd;

use App\Service\Api\HttpClient;
use App\Service\Utility\UtilityService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class LetterboxdService
{
    public const LETTERBOXD_SEARCH_URL = 'https://letterboxd.com/imdb/';
    public const LETTERBOXD_DIARY_URL = 'https://letterboxd.com/s/save-diary-entry';
    /** @var string When used it should be appended by Letterboxd movie ID and activity type, e.g. https://letterboxd/com/s/film:51315/watch/ */
    public const LETTERBOXD_ACTIVITY_URL = 'https://letterboxd.com/s/film:';

    public function __construct(
        private HttpClient $httpClient,
        private UtilityService $utilityService
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
    public function publishActivity(string $imdbId, ?float $rating): array|string
    {
        $this->httpClient->enableReturnOfRawContent(true);

        $this->httpClient->enableLogging(false);

        $searchPage = $this->httpClient->send(
            $this::LETTERBOXD_SEARCH_URL . $imdbId,
            'GET'
        );

        $filmId = $this->getMovieIdFromMarkup($searchPage);

        if ($filmId === null) {
            throw new \Exception("Movie with ID $imdbId not found");
        }

        //$url = $this->httpClient->getUrl();
        $url = $this::LETTERBOXD_ACTIVITY_URL . "{$filmId}";

        $result = $this->submitActivity('watched', $url);

        if ($rating) {
            $result = $this->submitRating($filmId, $rating);
        }

        return $result;
    }

    /**
     * @param string $letterboxdId
     * @param float $rating
     * @return array|string
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function submitRating(string $letterboxdId, float $rating): array|string
    {
        return $this->submitActivity('diary', '', [
            'filmId' => $letterboxdId,
            'specifiedDate' => true,
            'viewingDateStr' => date('Y-m-d'), // e.g. 2014-08-11
            'rating' => $rating
        ]);
    }

    /**
     * @param string $type One of watched/rating/diary
     * @param string $movieUrl E.g. 'https://letterboxd.com/film/the-cabin-in-the-woods/'
     * @param array $data Post data
     * @return array|string E.g. {"result":true,"csrf":"c82e4e46ab6159f5ae24","messages":["\u2018The Babysitter\u2019 was added to your films."],"errorCodes":["viewing.created"],"errorFields":[],...}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function submitActivity(string $type, string $movieUrl, array $data = []): array|string
    {
        $url = match ($type) {
            'watched' => $movieUrl . "/watch/",
            'rating' => $movieUrl . "/rate/",
            'diary' => $this::LETTERBOXD_DIARY_URL
        };

        $postData = array_merge($data, ['__csrf' => $this->utilityService->getDockerSecret('letterboxd_cookie_csrf_value')]);

        $this->httpClient->enableLogging(true, 'letterboxd');

        return $this->httpClient->send(
            $url,
            'POST',
            $postData,
            [
                'Cookie:letterboxd.user="' . $this->utilityService->getDockerSecret('letterboxd_cookie_user_value') . '"; com.xk72.webparts.csrf=' . $this->utilityService->getDockerSecret('letterboxd_cookie_csrf_value') . ';',
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        );
    }

    /**
     * Returns letterboxd movie ID (e.g. '24492').
     *
     */
    private function getMovieIdFromMarkup(string $markup): ?string
    {
        // var filmData = { id: 37598,
        if (preg_match('/var filmData = { id: (.*?),/is', $markup, $matches)) {
            return $matches[1];
        }

        return null;
    }
}