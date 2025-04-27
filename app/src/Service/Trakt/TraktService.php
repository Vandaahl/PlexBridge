<?php declare(strict_types=1);

namespace App\Service\Trakt;

use App\Service\Api\HttpClient;
use App\Service\Utility\SettingsService;
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

    public function __construct(
        private HttpClient $httpClient,
        private UtilityService $utilityService,
        private SettingsService $settingsService
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
     * Refresh the access token (which expires in 24 hours) using the refresh token.
     *
     * @return bool True if the token was successfully refreshed.
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function refreshAccessToken(): bool
    {
        $this->httpClient->enableLogging(true);

        $tokenData = $this->getAccessTokenFromStorage();

        if (empty($tokenData['refresh_token'])) {
            return false;
        }

        $response = $this->httpClient->send(
            self::TRAKT_TOKEN_URL,
            'POST',
            [
                'refresh_token' => $tokenData['refresh_token'],
                'client_id' => $this->utilityService->getDockerSecret('trakt_client_id'),
                'client_secret' => $this->utilityService->getDockerSecret('trakt_client_secret'),
                'redirect_uri' => getenv('TRAKT_REDIRECT_URL'),
                'grant_type' => 'refresh_token'
            ],
            ['content-type:application/json;charset=utf-8']
        );

        if (isset($response['access_token'])) {
            $this->saveAccessTokenData($response);
            return true;
        }

        return false;
    }

    /**
     * Save access-token to the database.
     *
     * @param array $tokenData
     * @return void
     * @throws \Exception
     */
    public function saveAccessTokenData(array $tokenData): void
    {
        $this->settingsService->saveSettings(['traktTokenData' => $tokenData]);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string, created_at: string}
     */
    public function getAccessTokenFromStorage(): array
    {
        return $this->settingsService->getSettings('traktTokenData');
    }

    /**
     * Check if the locally stored access token is still valid.
     *
     * @return bool
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function isAccessTokenValid(): bool
    {
        $tokenData = $this->getAccessTokenFromStorage();

        if (empty($tokenData)) {
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

        $valid = $currentDate < $expiresAtDate;

        if (!$valid) {
            try {
                $valid = $this->refreshAccessToken();
            } catch (\Exception $e) {
                return false;
            }
        }

        return $valid;
    }

    /**
     * @param string $guid
     * @param float $rating
     * @param string $ratedAt
     * @param string $type
     * @return string|bool Returns a string stating how many movies or episodes were rated, or false on error.
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function rateMedia(string $guid, float $rating, string $ratedAt, string $type): string|bool
    {
        if (!$this->isAccessTokenValid()) {
            return false;
        }

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

        $response = $this->httpClient->send(
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

        if (isset($response['added']['movies'])) {
            if ($response['added']['movies'] !== 0) {
                $media = $response['added']['movies'] === 1 ? " movie" : "movies";
                return "rated " . $response['added']['movies'] . " $media";
            } elseif ($response['added']['episodes'] !== 0) {
                $media = $response['added']['episodes'] === 1 ? " episode" : "episodes";
                return "rated " . $response['added']['episodes'] . " $media";
            }
        }

        return false;
    }

    /**
     * @param string $guid IMDb ID
     * @param string $type movie or episode
     * @return bool True on success
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function scrobble(string $guid, string $type): bool
    {
        if (!$this->isAccessTokenValid()) {
            return false;
        }

        $tokenData = $this->getAccessTokenFromStorage();
        $token = $tokenData['access_token'];
        $brand = 'imdb';

        $this->httpClient->enableLogging(true);

        $response = $this->httpClient->send(
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

        if (isset($response['action']) && $response['action'] === 'scrobble') {
            return true;
        }

        return false;
    }
}