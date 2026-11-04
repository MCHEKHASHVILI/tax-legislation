<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Mchekhashvili\TaxLegislation\Actions\AmendLaw;
use Mchekhashvili\TaxLegislation\Actions\RepealLaw;
use Mchekhashvili\TaxLegislation\Http\Resources\ValueResource;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\ValueKind;

/** Rates, thresholds, exemptions and table rows: {kind} is rate, threshold, exemption or table. @internal */
final class ValueController extends Controller
{
    public function show(ValueKind $kind, int $id): ValueResource
    {
        return new ValueResource($this->value($kind, $id));
    }

    /**
     * Every period of one value, oldest first: what the law said, from when, by which act. A table
     * row also needs `?table=`.
     */
    public function history(Request $request, TaxType $taxType, ValueKind $kind, string $key): AnonymousResourceCollection
    {
        $model = $kind->model();
        $request->validate(['table' => [Rule::requiredIf($kind === ValueKind::TableEntry), 'string']]);

        $rows = $model::query()->historyOf($taxType, $key)
            ->when($kind === ValueKind::TableEntry, fn ($q) => $q->where('table', $request->query('table')))
            ->get();

        return ValueResource::collection($rows);
    }

    /** AmendLaw: `kind` and the amendment's fields. */
    public function amend(Request $request, AmendLaw $action): JsonResponse
    {
        $request->validate(['kind' => ['required', Rule::enum(ValueKind::class)]]);

        return $this->submitted($action->handle(ValueKind::from($request->input('kind')), $request->except('kind')), ValueResource::class);
    }

    /** RepealLaw: `on` and `legal_act_id`. */
    public function repeal(Request $request, ValueKind $kind, int $id, RepealLaw $action): JsonResponse
    {
        return $this->submitted($action->handle($this->value($kind, $id), $request->all()), ValueResource::class);
    }

    private function value(ValueKind $kind, int $id): Model
    {
        return $kind->model()::query()->findOrFail($id);
    }
}
