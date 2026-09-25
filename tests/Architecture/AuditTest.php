<?php

/**
 * The audit log is written through AuditLogger only (redaction, context,
 * actor), and privileged actions are audited inside domain actions — never
 * ad hoc from controllers, where the write could escape the action's
 * transaction.
 */
arch('the AuditLog model is only used by the audit service and other models')
    ->expect('App\Models\AuditLog')
    ->toOnlyBeUsedIn([
        'App\Domain\Platform\Audit',
        'App\Models',
    ]);

arch('controllers do not write audit rows directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'App\Domain\Platform\Audit\AuditLogger',
        'App\Models\AuditLog',
    ]);
