<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Mchekhashvili\TaxLegislation\Http\Resources\RevisionResource;
use Mchekhashvili\TaxLegislation\Http\Subjects;
use Mchekhashvili\TaxLegislation\Revisions\Revision;

/** @internal */
final class RevisionController extends Controller
{
    /**
     * The editing history, newest first. `?subject=rate&subject_id=7` for one record's,
     * `?draft_id=3` for the changes one approved draft applied.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'subject' => ['nullable', 'required_with:subject_id', Rule::in(array_keys(Subjects::MAP))],
            'subject_id' => ['nullable', 'integer'],
            'draft_id' => ['nullable', 'integer'],
        ]);

        $revisions = Revision::query()->with('causer')
            ->when($request->query('subject'), fn ($q, $subject) => $q->where('subject_type', Subjects::morphType($subject)))
            ->when($request->query('subject_id'), fn ($q, $id) => $q->where('subject_id', $id))
            ->when($request->query('draft_id'), fn ($q, $id) => $q->where('draft_id', $id))
            ->latest('id')
            ->paginate($this->perPage($request))->withQueryString();

        return RevisionResource::collection($revisions);
    }
}
