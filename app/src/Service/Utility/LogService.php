<?php declare(strict_types=1);

namespace App\Service\Utility;

use App\DataTransferObject\LetterboxdLogEntryDTO;
use App\DataTransferObject\TraktLogEntryDTO;
use Symfony\Component\HttpKernel\KernelInterface;

class LogService
{
    public function __construct(
        private KernelInterface $kernel
    )
    {
    }

    /**
     * Get an array of log file paths, indexed by creation timestamp (UTC).
     *
     * @param string $prefix First part of the filename.
     * @return array{int: string} $timestampsAndFiles [1710004943 => '/app/var/log/incoming-2024-03-09.log']
     */
    public function getLogFiles(string $prefix = 'incoming'): array
    {
        $directory = $this->kernel->getLogDir();

        // Get a list of files matching the specific string.
        $files = glob($directory . '/' . $prefix . '*.log');

        $timestampsAndFiles = [];

        foreach ($files as $file) {
            $timestamp = filectime($file); // Get the file creation timestamp.
            $timestampsAndFiles[$timestamp] = $file;
        }

        return $timestampsAndFiles;
    }

    /**
     * @param array $log ["[2024-03-10T10:37:40.392725+00:00] incoming.INFO: ...", "..."]
     * @param string $pattern "/{.*}/"
     * @param bool $isJson If your pattern searches for a JSON string, set this to true, and it will be decoded to an assoc array.
     * @return array Array holding assoc arrays of decoded JSON data or arrays with string results. If the log lines included a date
     * between brackets, each array will have a 'logDate' key with the date string as a value.
     */
    private function filterLogLines(array $log, string $pattern, bool $isJson = true): array
    {
        return array_map(function($line) use($pattern, $isJson) {
            $return = [];

            preg_match($pattern, $line, $matches);
            // Unset full pattern result in case capture groups were used.
            if (count($matches) > 1) {
                unset($matches[0]);
            }
            foreach ($matches as $match) {
                if ($isJson && json_validate($match)) {
                    $return += json_decode($match, true);
                } else {
                    $return[] = $match;
                }
            }

            // Date from log entry.
            preg_match('/\[(\d.*)]/U', $line, $matches);

            $return['logDate'] = $matches[1] ?? null;

            return $return;
        }, $log);
    }

    /**
     * @param string $prefix The log name starts with this part, e.g. 'incoming' or 'trakt'.
     * @param int $limit
     * @return TraktLogEntryDTO[]|LetterboxdLogEntryDTO[]|array|null
     */
    public function getLatestLogLines(string $prefix = 'incoming', int $limit = 5): ?array
    {
        $timestampsAndFiles = $this->getLogFiles($prefix);

        // Sort by Unix timestamp, descending.
        krsort($timestampsAndFiles);

        $pattern = match($prefix) {
            "incoming"=> "/{.*}/",
            "trakt"=> "/PostData: ({.*}) .*\|.*Response: ({.*}{2,3}?)/",
            "letterboxd"=> "/PostData: ({.*}) .*\|.*Response: ({.*})/U",
            "retries-letterboxd"=> "/{.*}/"
        };

        $mediaData = [];

        // Loop through the files until we have the desired line $limit.
        foreach ($timestampsAndFiles as $timestamp => $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // Append results to end of array.
            /** @var array{array{logDate: string, rating: float, Metadata: array{title: string, summary: string, lastRatedAt: int}}} $newLines */
            $newLines = $this->filterLogLines($lines, $pattern);

            $mediaData = array_merge($mediaData, $newLines);

            if (count($mediaData) >= $limit) {
                break;
            }
        }

        if (count($mediaData) === 0) {
            return null;
        }

        // Since log lines of older log files may have been appended to the lines of the newest log,
        // we must sort the array again, this time by a particular key, so we get an ascending list.
        $sortKey = 'logDate';
        uasort($mediaData, function ($first, $second) use ($sortKey) {
            return $first[$sortKey] <=> $second[$sortKey];
        });

        $mediaData = array_slice($mediaData, -$limit);

        foreach ($mediaData as $key => $item) {
            // Add IMDb element to each line for Trakt.
            if (isset($item['Metadata'])) {
                /** @var string $imdb imdb://tt0664499 */
                $imdb = $item['Metadata']['Guid'][0]['id'];
                $id = str_replace('imdb://', '', $imdb);
                $mediaData[$key]['imdb'] = $id;
            }
            // Add Letterboxd URL element
            elseif (isset($item['messages']) && is_array($item['messages']) && count($item['messages'])) {
                preg_match('/href=\"(.*)">/U', $item['messages'][0], $matches);
                if (count($matches)) {
                    $mediaData[$key]['url'] = $matches[1];
                }
            }
        }

        if ($prefix === 'letterboxd') {
            $retries = self::getLatestLogLines('retries-letterboxd', 100);
            foreach ($mediaData as $key => &$item) {
                // Replace array with proper DTO object.
                $item = LetterboxdLogEntryDTO::fromArray($item);
                // Remove lines that have no valid data.
                if ($item->filmId === null) {
                    unset($mediaData[$key]);
                }
                // If an entry appears in the retries log, mark it as retried, so it can't be retried again.
                if ($retries) {
                    foreach ($retries as $retry) {
                        if ($item->logDate && $item->logDate === $retry['originalLogDate']) {
                            $item = $item->withRetried(true);
                            break; // Once a match is found, no need to check further retries
                        }
                    }
                }
            }
        } elseif ($prefix === 'trakt') {
            foreach ($mediaData as $key => &$item) {
                // Replace array with proper DTO object.
                $item = TraktLogEntryDTO::fromArray($item);
            }
        }

        return $mediaData;
    }
}