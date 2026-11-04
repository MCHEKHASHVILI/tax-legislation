<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mchekhashvili\TaxLegislation\Http\Subjects;

/** @mixin \Mchekhashvili\TaxLegislation\Revisions\Revision */
final class RevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => Subjects::ref($this->subject_type, $this->subject_id),
            'action' => $this->action,
            'changes' => $this->changes,
            'by' => $this->who(),
            'reason' => $this->reason,
            'source' => $this->source,
            'draft_id' => $this->draft_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
