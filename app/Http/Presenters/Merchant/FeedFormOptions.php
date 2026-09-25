<?php

namespace App\Http\Presenters\Merchant;

use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\FeedTransport;
use App\Http\Requests\Merchant\Feeds\FeedSourceRequest;
use App\Models\Country;
use App\Models\Currency;

/**
 * Choices of the feed settings form. Currencies and markets are public
 * reference data; the value lists mirror FeedSourceRequest's rules.
 */
final class FeedFormOptions
{
    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'formats' => array_map(
                static fn (FeedFormat $format): array => ['value' => $format->value, 'label' => strtoupper($format->value)],
                FeedFormat::cases(),
            ),
            'transports' => array_map(
                static fn (string $transport): array => FeedPresenter::transport(FeedTransport::from($transport)),
                FeedSourceRequest::TRANSPORTS,
            ),
            'encodings' => array_map(
                static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys(FeedSourceRequest::ENCODINGS),
                FeedSourceRequest::ENCODINGS,
            ),
            'delimiters' => [
                ['value' => 'comma', 'label' => 'Comma (,)'],
                ['value' => 'semicolon', 'label' => 'Semicolon (;)'],
                ['value' => 'tab', 'label' => 'Tab'],
                ['value' => 'pipe', 'label' => 'Pipe (|)'],
            ],
            'intervals' => array_map(
                static fn (int $minutes): array => ['value' => $minutes, 'label' => self::intervalLabel($minutes)],
                FeedSourceRequest::INTERVALS,
            ),
            'currencies' => array_values(Currency::query()->orderBy('code')->get(['code', 'name'])
                ->map(static fn (Currency $currency): array => ['value' => $currency->code, 'label' => "{$currency->code} · {$currency->name}"])
                ->all()),
            'markets' => array_values(Country::query()->where('is_active', true)->orderBy('name')->get(['code', 'name'])
                ->map(static fn (Country $country): array => ['value' => $country->code, 'label' => $country->name])
                ->all()),
        ];
    }

    private static function intervalLabel(int $minutes): string
    {
        return $minutes % 60 === 0 && $minutes < 1440
            ? 'Every '.($minutes === 60 ? 'hour' : ($minutes / 60).' hours')
            : ($minutes === 1440 ? 'Once a day' : "Every {$minutes} minutes");
    }
}
