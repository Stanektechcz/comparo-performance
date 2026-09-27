<?php

namespace App\Domain\Reviews\Abuse;

/**
 * One raised spam heuristic; `capsPercent` is set for {@see SpamSignal::Caps}.
 */
final readonly class TextSignal
{
    public function __construct(
        public SpamSignal $signal,
        public ?int $capsPercent = null,
    ) {}

    /**
     * The prototype's moderation-queue label.
     */
    public function label(): string
    {
        return match ($this->signal) {
            SpamSignal::Caps => "caps {$this->capsPercent} %",
            SpamSignal::ExcessivePunctuation => 'excessive punctuation',
            SpamSignal::OutboundLink => 'outbound link',
            SpamSignal::VeryShort => 'very short',
            SpamSignal::Unverified => 'no verified purchase',
            SpamSignal::GenericPraise => 'generic praise',
        };
    }
}
