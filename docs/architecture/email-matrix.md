# Email matrix

Only the emails that actually exist today (Laravel Fortify defaults, `config/fortify.php` `'features'`)
are listed as implemented. No application-specific Mailable or Notification class exists in
`app/Notifications` or `app/Mail` as of this pass — `Feeds\Events\FeedImported`/`FeedFailed` are
dispatched with no listener, so no feed-run email is sent yet (see
[event-matrix.md](event-matrix.md)). Phase 6 (notifications) items are marked NOT STARTED; do not treat
them as implemented.

## Implemented (Fortify defaults)

| Email | Trigger | Class | Status |
|---|---|---|---|
| Email verification | `Features::emailVerification()`, on registration / resend | `Illuminate\Auth\Notifications\VerifyEmail` (framework default, via `MustVerifyEmail`) | FUNCTIONAL |
| Password reset | `Features::resetPasswords()`, "forgot password" | `Illuminate\Auth\Notifications\ResetPassword` (framework default) | FUNCTIONAL |
| Two-factor recovery / challenge flow | `Features::twoFactorAuthentication()` | Fortify's own challenge views; no separate outbound email for the challenge itself | FUNCTIONAL |

Mailer/queue configuration: see `docs/development/setup.md` and
`docs/autonomy/EXTERNAL-DEPENDENCIES.md` (transactional email provider is `CREDENTIAL_REQUIRED`,
D-27; local/dev uses the `log`/`array` mailer).

## NOT STARTED (planned, no code yet)

| Email | Would trigger on | Notes |
|---|---|---|
| Feed run completed | `Feeds\Events\FeedImported` | Event exists, dispatched, no listener |
| Feed run failed | `Feeds\Events\FeedFailed` | Event exists, dispatched, no listener |
| Feed source moved to `error` (3 failures / AUTH_FAILED / BLOCKED_DESTINATION) | `FeedSourceLifecycle` transition | No event dedicated to this transition yet |
| Matching review queue digest (merchant/staff) | scheduled digest | Not designed |
| Price anomaly detected | `Pricing\Actions\RecheckProductAnomalies` finding | Not designed |
| Order/purchase-proof related emails | Phase 5+ | Out of Phase 2 scope |
| Forwarded-order verification (inbound email) | Phase 6 | Depends on D-27 (inbound email provider) |

Do not add rows to the "Implemented" section without a corresponding Mailable/Notification class and a
dispatch call site in the code.
