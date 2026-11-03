<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Drafts\DraftStatus;

/**
 * Rejects a pending draft. It is kept, never applied, with who rejected it and why.
 *
 *     app(RejectDraft::class)->handle($draft, ['reason' => 'The law was not adopted']);
 */
final class RejectDraft extends Decision
{
    public function rules(array $input = []): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }

    public function handle(Draft $draft, array $input): Draft
    {
        $this->decide($draft, $input, DraftStatus::Rejected, fn () => null);

        return $draft;
    }
}
