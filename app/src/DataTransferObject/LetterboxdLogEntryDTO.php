<?php declare(strict_types=1);

namespace App\DataTransferObject;

final class LetterboxdLogEntryDTO
{
    /** @var bool $retried Used to mark a log entry as retried if the user has pressed the retry button next to the failed entry. */
    private bool $retried = false;

    public function __construct(
        public readonly ?string $filmId,
        public readonly ?bool $specifiedDate,
        public readonly ?string $viewingDateStr,
        public readonly ?int $rating,
        public readonly ?bool $result,
        public readonly ?string $csrf,
        public readonly ?array $messages,
        public readonly ?array $errorCodes,
        public readonly string $logDate,
        public readonly ?string $film,
        public readonly ?bool $liked,
        public readonly ?bool $isNewViewing,
        public readonly ?string $viewingDate,
        public readonly ?bool $rewatch,
        public readonly ?string $url
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['filmId'] ?? null,
            $data['specifiedDate'] ?? null,
            $data['viewingDateStr'] ?? null,
            $data['rating'] ?? null,
            $data['result'] ?? null,
            $data['csrf'] ?? null,
            $data['messages'] ?? null,
            $data['errorCodes'] ?? null,
            $data['logDate'],
            $data['film'] ?? null,
            $data['liked'] ?? null,
            $data['isNewViewing'] ?? null,
            $data['viewingDate'] ?? null,
            $data['rewatch'] ?? null,
            $data['url'] ?? null
        );
    }

    /**
     * Call this method on a LetterboxdLogEntryDTO object to set the retried property.
     *
     * @param bool $retried
     * @return $this
     */
    public function withRetried(bool $retried): self
    {
        $new = clone $this;
        $new->retried = $retried;
        return $new;
    }

    /**
     * @return bool Has this log entry been retried before?
     */
    public function getRetried(): bool
    {
        return $this->retried;
    }
}
