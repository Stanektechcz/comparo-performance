<?php

namespace Tests\Feature\Merchant;

use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Merchants\MerchantRole;
use App\Models\Country;
use App\Models\FeedError;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\User;
use Tests\Feature\Matching\MatchingScenario;

/**
 * Arrangement for the merchant portal feature tests: merchants with a feed
 * (planted credentials + URL token), a finished run with errors and a
 * listing waiting for review, plus members in every role.
 */
final class MerchantPortal
{
    public const string PLANTED_PASSWORD = 'planted-merchant-feed-password-Zq81';

    public const string PLANTED_TOKEN = 'planted-merchant-url-token-Hx42';

    public const string PLANTED_HEADER_VALUE = 'planted-header-secret-Pw07';

    /**
     * @return array{merchant: Merchant, feed: FeedSource, run: FeedRun, listing: MerchantProduct}
     */
    public static function tenant(?Country $country = null): array
    {
        $merchant = Merchant::factory()->create();
        $feed = self::feed($merchant, $country);
        $run = self::finishedRun($feed);
        $listing = self::suggestedListing($feed);

        return ['merchant' => $merchant, 'feed' => $feed, 'run' => $run, 'listing' => $listing];
    }

    public static function feed(Merchant $merchant, ?Country $country = null): FeedSource
    {
        return FeedSource::factory()
            ->active()
            ->withCredentials(['type' => 'basic', 'username' => 'feed-user', 'password' => self::PLANTED_PASSWORD])
            ->create([
                'merchant_id' => $merchant->id,
                'country_id' => ($country ?? self::country())->id,
                'url' => 'https://feeds.example.com/listings.csv?token='.self::PLANTED_TOKEN,
            ]);
    }

    public static function finishedRun(FeedSource $feed): FeedRun
    {
        $run = FeedRun::factory()->forSource($feed)->scheduled()->completed(FeedRunOutcome::PublishedWithWarnings, ['rows_read' => 12, 'rows_invalid' => 2, 'warnings' => 1])->create();

        FeedError::factory()->create([
            'feed_run_id' => $run->id,
            'row_number' => 4,
            'code' => 'INVALID_PRICE',
            'severity' => FeedErrorSeverity::Error,
            'field' => 'price',
            'message_params' => ['field' => 'price', 'value' => '=HYPERLINK("http://evil.test")'],
        ]);
        FeedError::factory()->create([
            'feed_run_id' => $run->id,
            'row_number' => 7,
            'code' => 'INVALID_PRICE',
            'severity' => FeedErrorSeverity::Error,
            'field' => 'price',
            'message_params' => ['field' => 'price', 'value' => 'abc'],
        ]);
        FeedError::factory()->warning()->create(['feed_run_id' => $run->id, 'row_number' => 9, 'message_params' => ['value' => '123']]);

        return $run;
    }

    public static function suggestedListing(FeedSource $feed): MerchantProduct
    {
        $product = MatchingScenario::product(name: 'Whey Isolate '.$feed->id);
        $listing = MerchantProduct::factory()->fromFeed($feed)->unmatched()->create(MatchingScenario::confirmFacts($product));
        MatchingScenario::match($listing);

        return $listing->fresh() ?? $listing;
    }

    public static function member(Merchant $merchant, MerchantRole $role = MerchantRole::Owner, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $merchant->members()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public static function country(): Country
    {
        return Country::query()->where('code', 'DE')->first() ?? Country::factory()->code('DE')->create();
    }

    /**
     * Session state of a user who has just confirmed their password.
     *
     * @return array<string, int>
     */
    public static function passwordConfirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }
}
