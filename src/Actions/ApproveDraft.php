<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Illuminate\Database\Eloquent\Model;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Drafts\DraftStatus;

/**
 * Approves a pending draft and applies it, in one transaction. The change is re-checked against
 * the law as it stands at that moment — the tax's period, overlaps, the act's country — so a draft
 * overtaken by another change fails with field errors and stays pending. The row in force is
 * closed and the new one opened only now. The applied change's revisions name the approver and
 * the draft; the draft names its drafter.
 *
 *     $rate = app(ApproveDraft::class)->handle($draft, ['reason' => 'Checked against Matsne']);
 *
 * Returns the record the draft created or changed.
 */
final class ApproveDraft extends Decision
{
    public function rules(array $input = []): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }

    public function handle(Draft $draft, array $input = []): Model
    {
        return $this->decide($draft, $input, DraftStatus::Approved, function (Draft $draft) {
            $action = $draft->action;

            if (!is_subclass_of($action, Action::class)) {
                throw new \LogicException("Draft #{$draft->getKey()} names '$action', which is not a " . Action::class . '.');
            }

            return $this->editor->applying($draft, fn () => $this->container->make($action)->applyDraft($draft));
        });
    }
}
