<?php declare(strict_types=1);

namespace App\Service\Trakt;

use App\Service\Api\HttpClient;
use App\Service\Utility\UtilityService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class TraktService
{
    public const TRAKT_ADD_RATINGS_URL = "https://api.trakt.tv/sync/ratings";
    public const TRAKT_SCROBBLE_URL = "https://api.trakt.tv/scrobble/stop";
    public const TRAKT_AUTHORIZATON_URL = "https://api.trakt.tv/oauth/authorize";
    public const TRAKT_TOKEN_URL = "https://api.trakt.tv/oauth/token";
    public const TRAKT_STATE = "trakt-sync";
    public const TRAKT_ACCESS_TOKEN_LOCATION = "/app/var/trakt-token-data.json";

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

    public function getAuthorizationUrl(): string
    {
        $query =  http_build_query(
            [
                'response_type' => 'code',
                'client_id' => $this->utilityService->getDockerSecret('trakt_client_id'),
                'redirect_uri' => getenv('TRAKT_REDIRECT_URL'),
                'state' => $this::TRAKT_STATE
            ]
        );

        return $this::TRAKT_AUTHORIZATON_URL . "?$query";
    }

    /**
     * @param string $code
     * @return array
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getAccessToken(string $code): array
    {
        $this->httpClient->enableLogging(true);

        return $this->httpClient->send(
            $this::TRAKT_TOKEN_URL,
            'POST',
            [
                'client_id' => $this->utilityService->getDockerSecret('trakt_client_id'),
                'client_secret' => $this->utilityService->getDockerSecret('trakt_client_secret'),
                'code' => $code,
                'redirect_uri' => getenv('TRAKT_REDIRECT_URL'),
                'grant_type' => 'authorization_code'
            ],
            ['content-type:application/json;charset=utf-8']
        );
    }

    /**
     * Save access token to disk.
     *
     * @param array $tokenData
     * @return void
     * @throws \Exception
     */
    public function saveAccessTokenData(array $tokenData): void
    {
        $json = json_encode($tokenData);
        $file = $this::TRAKT_ACCESS_TOKEN_LOCATION;

        if (!file_exists($file)) {
            if (fopen($file, "w") === false) {
                throw new \Exception('Failed to create location for saving access token');
            }
        }

        if (file_put_contents($file, $json) === false) {
            throw new \Exception('Failed to save access token to disk');
        }
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string, created_at: string}
     * @throws \Exception
     */
    public function getAccessTokenFromStorage(): array
    {
        $tokenData = file_get_contents($this::TRAKT_ACCESS_TOKEN_LOCATION);

        if ($tokenData === false) {
            throw new \Exception('Failed to load access token from disk');
        }

        $tokenData = json_decode($tokenData, true);

        if (!$tokenData) {
            throw new \Exception('Failed to convert access token data to array');
        }

        return $tokenData;
    }

    /**
     * Check if the locally stored access token is still valid.
     *
     * @return bool
     */
    public function isAccessTokenValid(): bool
    {
        try {
            $tokenData = $this->getAccessTokenFromStorage();
        } catch (\Exception $e) {
            return false;
        }

        $expiresAtStamp = (int) $tokenData['created_at'] + (int) $tokenData['expires_in'];
        $expiresAt = date('Y-m-d H:i:s', $expiresAtStamp);
        try {
            $expiresAtDate = new \DateTime($expiresAt);
        } catch (\Exception $e) {
            return false;
        }
        $currentDate = new \DateTime('now');

        return $currentDate < $expiresAtDate;
    }

    public function rateMedia(string $guid, float $rating, string $ratedAt, string $type): array
    {
        $tokenData = $this->getAccessTokenFromStorage();
        $token = $tokenData['access_token'];
        $brand = 'imdb';
        $type = match ($type) {
            'movie' => 'movies',
            'show' => 'shows',
            'episode' => 'episodes',
            'season' => 'seasons'
        };

        $this->httpClient->enableLogging(true);

        return $this->httpClient->send(
            $this::TRAKT_ADD_RATINGS_URL,
            'POST',
            [
                $type => [
                    [
                        'rated_at' => $ratedAt,
                        'rating' => $rating,
                        'ids' => [
                            $brand => $guid
                        ]
                    ]
                ]
            ],
            [
                'trakt-api-key' => $this->utilityService->getDockerSecret('trakt_client_id'),
                'Authorization' => 'Bearer ' . $token,
                'trakt-api-version' => 2
            ]
        );
    }



    /**
     * @param string $guid IMDb ID
     * @param string $type movie or episode
     * @return void
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function scrobble(string $guid, string $type): void
    {
        $tokenData = $this->getAccessTokenFromStorage();
        $token = $tokenData['access_token'];
        $brand = 'imdb';

        $this->httpClient->enableLogging(true);

        $this->httpClient->send(
            $this::TRAKT_SCROBBLE_URL,
            'POST',
            [
                $type => [
                    'ids' => [
                        $brand => $guid
                    ]
                ],
                'progress' => 100
            ],
            [
                'trakt-api-key' => $this->utilityService->getDockerSecret('trakt_client_id'),
                'Authorization' => 'Bearer ' . $token,
                'trakt-api-version' => 2
            ]
        );
    }
}