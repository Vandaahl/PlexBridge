<?php declare(strict_types=1);

namespace App\Service\Api;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClient
{
    private bool $loggingEnabled = false;
    private string $logPrefix = 'trakt';
    private bool $returnRawContent = false;
    private array $responseHeaders = [];
    private string $url;

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $traktLogger,
        private LoggerInterface $letterboxdLogger
    )
    {
    }

    /**
     * Send json requests to an API. Also keeps track of response status.
     *
     * @param string $endpoint Endpoint to call.
     * @param string $method The HTTP method to use (one of GET, POST, PUT, DELETE).
     * @param array|null $data Assoc array with data to send with this request. Will be sent as JSON.
     * @param array $headers ['Content-Type' => 'text/plain'] Custom headers. Content-Type: application/json will be added automatically if you don't set a type manually.
     * @return array|string $return Contents received.
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function send(string $endpoint, string $method = 'POST', ?array $data = null, array $headers = []): array|string
    {
        $requestData = [];

        if ($data) {
            // Remove capitals and spaces from the $headers array values, so we can search in it.
            $trimmedHeaders = str_replace(' ', '', array_map('strtolower', array_map('trim', $headers)));
            // If the json content type header is set, encode the post data to json.
            if (count($headers) && in_array('application/x-www-form-urlencoded', $trimmedHeaders)) {
                $requestData['body'] = $data;
            } else {
                $requestData['json'] = $data;
            }
        }

        if (count($headers)) {
            $requestData['headers'] = $headers;
        }

        $response = $this->client->request(
            $method,
            $endpoint,
            $requestData
        );

        $content = $response->getContent();

        $this->url = $response->getInfo('url');
        $this->responseHeaders = $response->getHeaders();

        $this->logRequests($endpoint, $headers, json_encode($data), $response->getStatusCode(), $content);

        if ($this->returnRawContent) {
            return $content;
        }

        try {
            return $response->toArray();
        } catch (\Exception $e) {
            throw new \Exception('Return value is not an array. Try setting enableReturnOfRawContent() to true if you expect a string.');
        }
    }

    /**
     * Log all requests to a file.
     *
     * @param  string $endpoint URL.
     * @param  array $headers Headers containing for example an authorization bearer token.
     * @param  string $postData Querystring or json string with data posted.
     * @param  int $responseCode Status code returned by remote server.
     * @param string $response Json response returned by remote server.
     * @return void
     */
    private function logRequests(string $endpoint, array $headers, string $postData, int $responseCode, string $response): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $log = "LastResponseCode: ".$responseCode." | ".
        "Endpoint: ".$endpoint." | ".
        "Headers: ".json_encode($headers)." | ".
        "PostData: ".$postData." | ".
        "Response: ".$response.PHP_EOL;
        if ($this->logPrefix === 'letterboxd') {
            $this->letterboxdLogger->info($log);
        } else {
            $this->traktLogger->info($log);
        }
    }

    /**
     * Enable logging.
     *
     * @param bool $status
     * @param string|null $logPrefix Used to determine which log to write to. Valid values are trakt or letterboxd.
     * @return void
     */
    public function enableLogging(bool $status, ?string $logPrefix = null): void
    {
        $this->loggingEnabled = $status;
        if ($logPrefix) {
            $this->logPrefix = $logPrefix;
        }
    }

    /**
     * The send() method will return the raw contents of the response string if you enable this.
     *
     * @param bool $status
     * @return void
     */
    public function enableReturnOfRawContent(bool $status): void
    {
        $this->returnRawContent = $status;
    }

    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    /**
     * The HTTP request may have followed a redirect. This method returns the final URL, whether it
     * was redirected or not.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
