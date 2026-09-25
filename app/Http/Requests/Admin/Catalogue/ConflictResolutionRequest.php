<?php

namespace App\Http\Requests\Admin\Catalogue;

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ConflictStatus;
use App\Models\MatchingConflict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff close an open matching conflict as resolved or dismissed, with a
 * note. Access: `staff.access` + `matching.review` (route); a compliance-hold
 * conflict additionally needs `compliance.manage`.
 */
class ConflictResolutionRequest extends FormRequest
{
    public const int VALUE_MAX = 255;

    public function authorize(): bool
    {
        $conflict = $this->route('conflict');

        if (! $conflict instanceof MatchingConflict) {
            return false;
        }

        return $conflict->kind !== ConflictKind::ComplianceHold
            || ($this->user()?->can(Permission::ManageCompliance->value) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', Rule::in([ConflictStatus::Resolved->value, ConflictStatus::Dismissed->value])],
            'note' => ['required', 'string', 'max:'.ListingDecisionRequest::NOTE_MAX],
            'resolved_value' => ['nullable', 'string', 'max:'.self::VALUE_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['note.required' => 'Explain how the conflict was closed.'];
    }

    public function resolution(): ConflictStatus
    {
        return ConflictStatus::from($this->string('resolution')->toString());
    }

    public function note(): string
    {
        return $this->string('note')->trim()->toString();
    }

    public function resolvedValue(): ?string
    {
        return $this->filled('resolved_value') ? $this->string('resolved_value')->trim()->toString() : null;
    }
}
