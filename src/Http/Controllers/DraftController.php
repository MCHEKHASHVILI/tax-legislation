<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Mchekhashvili\TaxLegislation\Actions\ApproveDraft;
use Mchekhashvili\TaxLegislation\Actions\RejectDraft;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Drafts\DraftStatus;
use Mchekhashvili\TaxLegislation\Http\Resources\DraftResource;

/**
 * Drafts and their decisions. Approving and rejecting go through the approval gate: a refusal is
 * 403, a draft already decided or overtaken by the law is 422 with field errors.
 *
 * @internal
 */
final class DraftController extends Controller
{
    /** Pending drafts, oldest first. `?status=approved|rejected|all`, `?country=GE`. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in([...array_column(DraftStatus::cases(), 'value'), 'all'])],
            'country' => ['nullable', 'string'],
        ]);
        $status = $request->query('status', DraftStatus::Pending->value);

        $drafts = Draft::query()->with(['country', 'draftedBy', 'decidedBy'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('country'), fn ($q, $country) => $q->where('country_id', $this->country($country)->getKey()))
            ->when($status === DraftStatus::Pending->value, fn ($q) => $q->oldest('id'), fn ($q) => $q->latest('id'))
            ->paginate($this->perPage($request))->withQueryString();

        return DraftResource::collection($drafts);
    }

    public function show(Draft $draft): DraftResource
    {
        return new DraftResource($draft->load(['country', 'draftedBy', 'decidedBy']));
    }

    /** ApproveDraft: optional `reason`. Answers the draft, now approved, with its `result`. */
    public function approve(Request $request, Draft $draft, ApproveDraft $action): DraftResource
    {
        $action->handle($draft, $request->only('reason'));

        return $this->show($draft);
    }

    /** RejectDraft: `reason` is required. */
    public function reject(Request $request, Draft $draft, RejectDraft $action): DraftResource
    {
        $action->handle($draft, $request->only('reason'));

        return $this->show($draft);
    }
}
