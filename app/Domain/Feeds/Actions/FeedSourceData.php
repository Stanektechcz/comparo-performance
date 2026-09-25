<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\FeedTransport;
use App\Domain\Feeds\Fetching\DestinationGuard;
use App\Domain\Feeds\Fetching\FeedFetchException;
use App\Domain\Feeds\Parsing\FeedParseException;
use App\Domain\Feeds\Parsing\ParseOptions;
use App\Domain\Offers\Availability;
use InvalidArgumentException;

/**
 * The merchant-editable settings of a feed source. Validated on construction
 * (the HTTP layer validates first; this is the domain's own guard). Messages
 * never echo the URL, which may carry tokens.
 */
final readonly class FeedSourceData
{
    public const int MIN_INTERVAL_MINUTES = 15;

    public const int MAX_INTERVAL_MINUTES = 43_200;

    public string $name;

    public string $currency;

    public ?string $marketCountryCode;

    /** @var array<string, string> source value => Availability value */
    public array $availabilityMap;

    /**
     * @param  array<string, string>  $availabilityMap
     */
    public function __construct(
        string $name,
        public FeedFormat $format,
        public FeedTransport $transport,
        #[\SensitiveParameter]
        public ?string $url,
        string $currency,
        ?string $marketCountryCode = null,
        public string $encoding = 'UTF-8',
        public ?string $delimiter = null,
        public ?string $recordElement = null,
        public ?int $intervalMinutes = null,
        array $availabilityMap = [],
    ) {
        $this->name = trim($name);
        $this->currency = strtoupper(trim($currency));
        $this->marketCountryCode = $marketCountryCode === null ? null : strtoupper(trim($marketCountryCode));
        $this->availabilityMap = $this->validAvailabilityMap($availabilityMap);

        if ($this->name === '' || mb_strlen($this->name) > 96) {
            throw new InvalidArgumentException('A feed name must have 1 to 96 characters.');
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException('The feed currency must be an ISO-4217 code.');
        }

        if ($this->marketCountryCode !== null && preg_match('/^[A-Z]{2}$/', $this->marketCountryCode) !== 1) {
            throw new InvalidArgumentException('The feed market must be an ISO-3166 alpha-2 code.');
        }

        if ($intervalMinutes !== null && ($intervalMinutes < self::MIN_INTERVAL_MINUTES || $intervalMinutes > self::MAX_INTERVAL_MINUTES)) {
            throw new InvalidArgumentException('The feed interval must be between 15 minutes and 30 days.');
        }

        $this->assertUrl();
        $this->assertParseOptions();
    }

    private function assertUrl(): void
    {
        if (! $this->transport->isFetched()) {
            if ($this->url !== null) {
                throw new InvalidArgumentException('Only URL feeds have a feed URL.');
            }

            return;
        }

        try {
            (new DestinationGuard)->parse((string) $this->url);
        } catch (FeedFetchException) {
            throw new InvalidArgumentException('The feed URL is not an allowed public http(s) address.');
        }
    }

    private function assertParseOptions(): void
    {
        try {
            new ParseOptions($this->format, $this->encoding, $this->delimiter, $this->recordElement);
        } catch (FeedParseException) {
            throw new InvalidArgumentException('The feed encoding is not supported.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $map
     * @return array<string, string>
     */
    private function validAvailabilityMap(array $map): array
    {
        $valid = [];

        foreach ($map as $sourceValue => $availability) {
            if (! is_string($availability) || Availability::tryFrom($availability) === null || trim((string) $sourceValue) === '') {
                throw new InvalidArgumentException('The availability mapping may only map to known availability values.');
            }

            $valid[(string) $sourceValue] = $availability;
        }

        return $valid;
    }
}
