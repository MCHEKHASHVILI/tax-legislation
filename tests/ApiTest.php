<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Mchekhashvili\Countries\Database\Seeders\CountrySeeder;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Http\Routes;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;
use Mchekhashvili\TaxLegislation\TaxLegislationServiceProvider;

/*
| The JSON API over HTTP. Invented acts and figures — they exercise the API, they are not legal history.
*/

final class ApiUser extends Illuminate\Foundation\Auth\User
{
    public $timestamps = false;
    protected $table = 'api_users';
    protected $guarded = [];
}

beforeEach(function () {
    $this->seed(CountrySeeder::class);
    Schema::create('api_users', fn ($t) => [$t->id(), $t->string('name'), $t->boolean('approves')->default(false)]);
    $this->drafter = ApiUser::query()->create(['name' => 'Nino']);
    $this->approver = ApiUser::query()->create(['name' => 'Giorgi', 'approves' => true]);

    // The law as it stands, entered directly (approval is off until a test turns it on).
    $this->code = LegalAct::query()->create(['country_id' => Country::code('AM')->id, 'kind' => 'code', 'title' => 'Example Code', 'adopted_on' => '2000-12-01']);
    $this->law = LegalAct::query()->create(['country_id' => $this->code->country_id, 'kind' => 'law', 'number' => '7', 'title' => 'Example law', 'adopted_on' => '2026-10-01', 'amends_id' => $this->code->id]);
    $this->sales = TaxType::query()->create(['country_id' => $this->code->country_id, 'code' => 'sales', 'names' => ['en' => 'Sales tax'], 'level' => 'national', 'in_force_from' => '2001-01-01', 'introduced_by_id' => $this->code->id]);
    $this->stamp = TaxType::query()->create(['country_id' => $this->code->country_id, 'code' => 'stamp', 'names' => ['en' => 'Stamp duty'], 'level' => 'national', 'in_force_from' => '2001-01-01', 'abolished_on' => '2010-01-01']);
    $this->rate = TaxRate::amend($this->sales, '1', ['percent' => 18], from: '2001-01-01', by: $this->code, article: '1');
    $this->threshold = Threshold::amend($this->sales, 'registration', ['amount' => Money::of('100000', 'AMD')], from: '2001-01-01', by: $this->code, article: '3');
    TableEntry::amend($this->sales, 'wine', ['table' => 'excise', 'values' => ['per_litre' => 2]], from: '2001-01-01', by: $this->code);

    Gate::define('approve-legislation', fn (ApiUser $user, Draft $draft) => $user->approves && !$draft->draftedBy?->is($user));
});

function amendment(array $input = []): array
{
    return ['kind' => 'rate', 'tax_type_id' => test()->sales->id, 'key' => '1', 'article' => '1', 'from' => '2027-01-01',
        'legal_act_id' => test()->law->id, 'percent' => 20, 'reason' => 'Law №7, Art. 1', ...$input];
}

it('is off unless the config switches it on', function () {
    expect(config('tax-legislation.api.enabled'))->toBeFalse()
        ->and(Route::has('tax-legislation.drafts.index'))->toBeFalse();
});

it('registers under the configured prefix, name and middleware when switched on', function () {
    config(['tax-legislation.api' => ['enabled' => true, 'prefix' => 'law', 'name' => 'law.', 'middleware' => ['api']]]);
    new TaxLegislationServiceProvider(app())->boot(app('events'));
    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('law.drafts.index'))->toBeTrue()
        ->and(route('law.tax-types.law', $this->sales))->toEndWith("/law/tax-types/{$this->sales->id}/law")
        ->and(Route::getRoutes()->getByName('law.drafts.approve')->gatherMiddleware())->toContain('api');

    $this->getJson('/law/countries')->assertOk();
});

describe('switched on', function () {
    beforeEach(function () {
        Routes::register(['prefix' => 'api/law']);
        app('router')->getRoutes()->refreshNameLookups();
    });

    it('lists the countries with law recorded, or all of them', function () {
        $this->getJson('/api/law/countries')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.alpha2', 'AM')
            ->assertJsonPath('data.0.legal_acts_count', 2)
            ->assertJsonPath('data.0.tax_types_count', 2);

        expect(count($this->getJson('/api/law/countries?all=1')->json('data')))->toBeGreaterThan(200);

        $this->getJson('/api/law/countries/arm')->assertOk()->assertJsonPath('data.alpha2', 'AM');
        $this->getJson('/api/law/countries/XX')->assertNotFound();
    });

    it("lists a country's acts, newest first, and shows what an act did", function () {
        $this->getJson('/api/law/countries/AM/legal-acts')->assertOk()
            ->assertJsonPath('data.0.id', $this->law->id)
            ->assertJsonPath('data.0.citation', 'Example law №7 of 01.10.2026')
            ->assertJsonPath('data.1.id', $this->code->id)
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/law/countries/AM/legal-acts?kind=law')->assertJsonPath('meta.total', 1);

        $this->getJson("/api/law/legal-acts/{$this->code->id}")->assertOk()
            ->assertJsonPath('data.country', 'AM')
            ->assertJsonPath('data.effects.tax_types_introduced', 1)
            ->assertJsonPath('data.effects.rates', 1)
            ->assertJsonPath('data.effects.thresholds', 1)
            ->assertJsonPath('data.effects.table_entries', 1)
            ->assertJsonPath('data.effects.amended_by', [$this->law->id]);
    });

    it("lists a country's taxes, all periods or those in force on a day", function () {
        $this->getJson('/api/law/countries/AM/tax-types')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/law/countries/AM/tax-types?on=2026-11-04')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'sales')
            ->assertJsonPath('data.0.in_force_from', '2001-01-01');

        $this->getJson('/api/law/countries/AM/tax-types?on=not-a-day')->assertUnprocessable()->assertJsonValidationErrors('on');
    });

    it("shows a tax's law on a day and each value's history", function () {
        TaxRate::amend($this->sales, '1', ['percent' => 20], from: '2027-01-01', by: $this->law);

        $this->getJson("/api/law/tax-types/{$this->sales->id}/law?on=2026-12-31")->assertOk()
            ->assertJsonPath('data.on', '2026-12-31')
            ->assertJsonPath('data.in_force', true)
            ->assertJsonPath('data.rates.0.percent', 18)
            ->assertJsonPath('data.thresholds.0.amount', ['amount' => '100000.00', 'currency' => 'AMD'])
            ->assertJsonPath('data.table_entries.0.values', ['per_litre' => 2]);

        $this->getJson("/api/law/tax-types/{$this->sales->id}/law?on=2027-01-01")
            ->assertJsonPath('data.rates.0.percent', 20)
            ->assertJsonPath('data.rates.0.legal_act_id', $this->law->id);

        $this->getJson("/api/law/tax-types/{$this->sales->id}/history/rate/1")->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.effective_until', '2027-01-01')
            ->assertJsonPath('data.1.effective_from', '2027-01-01')
            ->assertJsonPath('data.1.article', '1');

        $this->getJson("/api/law/tax-types/{$this->sales->id}/history/table/wine")->assertUnprocessable()->assertJsonValidationErrors('table');
        $this->getJson("/api/law/tax-types/{$this->sales->id}/history/table/wine?table=excise")->assertJsonCount(1, 'data');

        $this->getJson("/api/law/values/rate/{$this->rate->id}")->assertOk()->assertJsonPath('data.kind', 'rate');
        $this->getJson("/api/law/values/fee/{$this->rate->id}")->assertNotFound();
        $this->getJson('/api/law/values/rate/999')->assertNotFound();
    });

    it('applies a change at once while approval is off, answering 201 with the record', function () {
        $this->postJson('/api/law/amendments', amendment())->assertCreated()
            ->assertJsonPath('data.kind', 'rate')
            ->assertJsonPath('data.percent', 20)
            ->assertJsonPath('data.effective_from', '2027-01-01');

        expect($this->sales->rateOn('1', '2027-01-01')->percent)->toBe(20.0);
    });

    it('returns field errors as 422', function () {
        $this->postJson('/api/law/amendments', amendment(['kind' => null]))->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->postJson('/api/law/amendments', amendment(['percent' => 120]))->assertUnprocessable()->assertJsonValidationErrors('percent');
        $this->postJson('/api/law/amendments', amendment(['from' => '2011-01-01', 'tax_type_id' => $this->stamp->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->postJson('/api/law/legal-acts', ['country' => 'AM', 'kind' => 'edict'])->assertUnprocessable()->assertJsonValidationErrors(['kind', 'title']);

        // Even without an Accept header.
        $this->post('/api/law/tax-types', [])->assertUnprocessable()->assertJsonValidationErrors(['country', 'code']);
    });

    describe('with approval on', function () {
        beforeEach(fn () => config(['tax-legislation.approval.required' => true]));

        it('saves a change as a draft (202), approves it and logs who did what', function () {
            $this->actingAs($this->drafter);
            $id = $this->postJson('/api/law/amendments', amendment(['source' => 'https://example.test/7']))->assertAccepted()
                ->assertJsonPath('data.status', 'pending')
                ->assertJsonPath('data.action', 'amend-law')
                ->assertJsonPath('data.kind', 'rate')
                ->assertJsonPath('data.country', 'AM')
                ->assertJsonPath('data.payload.percent', 20)
                ->assertJsonPath('data.drafted_by', 'Nino')
                ->json('data.id');

            // No legal effect yet.
            $this->getJson("/api/law/tax-types/{$this->sales->id}/law?on=2027-06-01")->assertJsonPath('data.rates.0.percent', 18);

            $this->getJson('/api/law/drafts?country=AM')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
            $this->getJson('/api/law/drafts?country=GE')->assertJsonCount(0, 'data');

            $this->actingAs($this->approver);
            $rateId = $this->postJson("/api/law/drafts/$id/approve", ['reason' => 'Checked'])->assertOk()
                ->assertJsonPath('data.status', 'approved')
                ->assertJsonPath('data.decided_by', 'Giorgi')
                ->assertJsonPath('data.decision_note', 'Checked')
                ->assertJsonPath('data.result.type', 'rate')
                ->json('data.result.id');

            $this->getJson("/api/law/tax-types/{$this->sales->id}/law?on=2027-06-01")->assertJsonPath('data.rates.0.id', $rateId);
            $this->getJson('/api/law/drafts')->assertJsonCount(0, 'data');
            $this->getJson('/api/law/drafts?status=approved')->assertJsonPath('data.0.id', $id);

            // The new rate and the closed one, both applied by the draft, by the approver.
            $this->getJson("/api/law/revisions?draft_id=$id")->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.by', 'Giorgi')
                ->assertJsonPath('data.0.reason', 'Law №7, Art. 1')
                ->assertJsonPath('data.0.source', 'https://example.test/7');

            $this->getJson("/api/law/revisions?subject=rate&subject_id=$rateId")->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.subject', ['type' => 'rate', 'id' => $rateId])
                ->assertJsonPath('data.0.action', 'created');
            $this->getJson("/api/law/revisions?subject=draft&subject_id=$id")->assertJsonPath('data.0.action', 'updated');
            $this->getJson('/api/law/revisions?subject=person')->assertUnprocessable()->assertJsonValidationErrors('subject');
        });

        it('drafts every form: acts, taxes, abolitions, repeals', function () {
            $this->actingAs($this->drafter);

            $this->postJson('/api/law/legal-acts', ['country' => 'AM', 'kind' => 'law', 'number' => '8', 'title' => 'Another law', 'amends_id' => $this->code->id])
                ->assertAccepted()->assertJsonPath('data.action', 'record-legal-act');
            $this->postJson('/api/law/tax-types', ['country' => 'AM', 'code' => 'land', 'names' => ['en' => 'Land tax'], 'level' => 'local', 'in_force_from' => '2027-01-01'])
                ->assertAccepted()->assertJsonPath('data.action', 'record-tax-type');
            $this->postJson("/api/law/tax-types/{$this->sales->id}/abolish", ['on' => '2030-01-01', 'legal_act_id' => $this->law->id])
                ->assertAccepted()->assertJsonPath('data.subject', ['type' => 'tax-type', 'id' => $this->sales->id]);
            $repeal = $this->postJson("/api/law/values/threshold/{$this->threshold->id}/repeal", ['on' => '2027-01-01', 'legal_act_id' => $this->law->id])
                ->assertAccepted()->assertJsonPath('data.action', 'repeal-law')->assertJsonPath('data.subject.type', 'threshold')->json('data.id');

            $this->getJson('/api/law/drafts')->assertJsonPath('meta.total', 4);

            $this->actingAs($this->approver);
            $this->postJson("/api/law/drafts/$repeal/approve")->assertOk()->assertJsonPath('data.result', ['type' => 'threshold', 'id' => $this->threshold->id]);
            $this->getJson("/api/law/values/threshold/{$this->threshold->id}")->assertJsonPath('data.effective_until', '2027-01-01')
                ->assertJsonPath('data.repealed_by_id', $this->law->id);
        });

        it('answers 403 when the gate refuses, 422 when the reason is missing or the draft is decided', function () {
            $this->actingAs($this->drafter);
            $id = $this->postJson('/api/law/amendments', amendment())->json('data.id');

            // A drafter may not approve their own draft.
            $this->postJson("/api/law/drafts/$id/approve")->assertForbidden();

            $this->actingAs($this->approver);
            $this->postJson("/api/law/drafts/$id/reject")->assertUnprocessable()->assertJsonValidationErrors('reason');
            $this->postJson("/api/law/drafts/$id/reject", ['reason' => 'Not adopted'])->assertOk()
                ->assertJsonPath('data.status', 'rejected')
                ->assertJsonPath('data.decision_note', 'Not adopted')
                ->assertJsonPath('data.result', null);

            $this->postJson("/api/law/drafts/$id/approve")->assertUnprocessable()->assertJsonValidationErrors('draft');
            $this->getJson("/api/law/drafts/$id")->assertOk()->assertJsonPath('data.status', 'rejected');
            $this->getJson('/api/law/drafts/999')->assertNotFound();
            $this->getJson('/api/law/drafts?status=lost')->assertUnprocessable();
        });

        it('keeps a draft overtaken by the law pending, with field errors', function () {
            $this->actingAs($this->drafter);
            $id = $this->postJson('/api/law/tax-types', ['country' => 'AM', 'code' => 'land', 'names' => ['en' => 'Land tax'], 'level' => 'local', 'in_force_from' => '2027-01-01'])->json('data.id');
            TaxType::query()->create(['country_id' => $this->code->country_id, 'code' => 'land', 'names' => ['en' => 'Land tax'], 'level' => 'local', 'in_force_from' => '2026-01-01']);

            $this->actingAs($this->approver);
            $this->postJson("/api/law/drafts/$id/approve")->assertUnprocessable()->assertJsonValidationErrors('in_force_from');
            $this->getJson("/api/law/drafts/$id")->assertJsonPath('data.status', 'pending');
        });
    });
});
