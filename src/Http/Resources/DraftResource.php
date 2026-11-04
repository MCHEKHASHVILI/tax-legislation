<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Mchekhashvili\TaxLegislation\Http\Subjects;

/**
 * A change awaiting a decision, or decided. `action` names the form: record-legal-act,
 * record-tax-type, abolish-tax-type, amend-law, repeal-law. `subject` is what a repeal or an
 * abolition changes; `result`, what approval created or changed — both {type, id}.
 *
 * @mixin \Mchekhashvili\TaxLegislation\Drafts\Draft
 */
final class DraftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country' => $this->country?->alpha2,
            'action' => Str::kebab(class_basename($this->action)),
            'kind' => $this->kind?->value,
            'subject' => Subjects::ref($this->subject_type, $this->subject_id),
            'payload' => $this->payload,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'source' => $this->source,
            'drafted_by' => $this->drafter(),
            'drafted_at' => $this->created_at?->toIso8601String(),
            'decided_by' => $this->decider(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decision_note' => $this->decision_note,
            'result' => Subjects::ref($this->result_type, $this->result_id),
        ];
    }
}
