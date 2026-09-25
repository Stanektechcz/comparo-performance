<?php

namespace Database\Seeders;

use App\Domain\Accounts\Authorization\StaffRole;
use App\Domain\Merchants\MerchantRole;
use App\Domain\Platform\PrototypeImport\DemoEnvironment;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Demo personas: a shopper, a merchant owner (merchant #1) and a super-admin.
 *
 * Only when COMPARO_DEMO_ACCOUNTS is true and never in production. The
 * password is COMPARO_DEMO_PASSWORD, or a random one printed once. Existing
 * accounts keep their password unless COMPARO_DEMO_PASSWORD is set.
 */
class DemoAccountsSeeder extends DemoSeeder
{
    public const string SHOPPER_EMAIL = 'demo@comparo.example';

    public const string MERCHANT_EMAIL = 'merchant@comparo.example';

    public const string STAFF_EMAIL = 'admin@comparo.example';

    public const int DEMO_MERCHANT_ID = 1;

    private const int GENERATED_PASSWORD_LENGTH = 20;

    private ?string $generatedPassword = null;

    private bool $generatedPasswordUsed = false;

    public function run(): void
    {
        DemoEnvironment::assertAllowed();

        if (! config('comparo.demo.enabled')) {
            $this->info('Demo accounts skipped: COMPARO_DEMO_ACCOUNTS is not enabled.');

            return;
        }

        $this->call(RolesAndPermissionsSeeder::class);

        $configured = config('comparo.demo.password');
        $password = is_string($configured) && $configured !== '' ? $configured : null;

        $this->account(self::SHOPPER_EMAIL, 'Demo Shopper', $password);
        $merchantUser = $this->account(self::MERCHANT_EMAIL, 'Demo Merchant', $password);
        $staff = $this->account(self::STAFF_EMAIL, 'Demo Staff', $password);

        $this->attachToDemoMerchant($merchantUser);
        $this->grantSuperAdmin($staff);

        if ($this->generatedPasswordUsed) {
            $this->warn('Demo account password (shown once, set COMPARO_DEMO_PASSWORD to choose one): '.$this->generatedPassword);
        }

        $this->info('Demo accounts: '.implode(', ', [self::SHOPPER_EMAIL, self::MERCHANT_EMAIL, self::STAFF_EMAIL]).'.');
    }

    private function account(string $email, string $name, ?string $configuredPassword): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->forceFill(['email_verified_at' => now()]);

        if (! $user->exists || $configuredPassword !== null) {
            $user->password = $configuredPassword ?? $this->generatedPassword();
            $this->generatedPasswordUsed = $this->generatedPasswordUsed || $configuredPassword === null;
        }

        $user->save();

        return $user;
    }

    private function generatedPassword(): string
    {
        return $this->generatedPassword ??= Str::password(self::GENERATED_PASSWORD_LENGTH, symbols: false);
    }

    private function attachToDemoMerchant(User $user): void
    {
        $merchant = Merchant::query()->find(self::DEMO_MERCHANT_ID);

        if ($merchant === null) {
            $this->warn('Demo merchant #'.self::DEMO_MERCHANT_ID.' does not exist; run DemoDataSeeder first. Membership skipped.');

            return;
        }

        $merchant->members()->syncWithoutDetaching([$user->id => ['role' => MerchantRole::Owner->value]]);
    }

    private function grantSuperAdmin(User $user): void
    {
        $user->assignRole(StaffRole::SuperAdmin->value);
    }
}
