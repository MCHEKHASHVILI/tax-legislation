<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Mchekhashvili\Countries\Database\Seeders\CountrySeeder;
use Mchekhashvili\TaxLegislation\Actions\AbolishTaxType;
use Mchekhashvili\TaxLegislation\Actions\AmendLaw;
use Mchekhashvili\TaxLegislation\Actions\RecordLegalAct;
use Mchekhashvili\TaxLegislation\Actions\RecordTaxType;
use Mchekhashvili\TaxLegislation\Actions\RepealLaw;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\ValueKind;
use Mchekhashvili\TaxLegislation\Revisions\Editor;
use Mchekhashvili\TaxLegislation\Revisions\Revision;

/*
| Invented acts and figures — they exercise the forms, they are not legal history.
*/

final class Clerk extends Illuminate\Foundation\Auth\User
{
    public $timestamps = false;
    protected $table = 'clerks';
    protected $guarded = [];
}

beforeEach(function () {
    $this->seed(CountrySeeder::class);
    Schema::create('clerks', fn ($t) => [$t->id(), $t->string('name')]);
    $this->clerk = Clerk::query()->create(['name' => 'Nino']);

    $this->act = app(RecordLegalAct::class)->handle(['country' => 'AM', 'kind' => 'code', 'number' => '1', 'title' => 'Example Code', 'adopted_on' => '2000-01-01']);
    $this->law = app(RecordLegalAct::class)->handle(['country' => 'AM', 'kind' => 'law', 'number' => '2', 'title' => 'Example law', 'adopted_on' => '2026-11-01', 'amends_id' => $this->act->id]);
    $this->sales = app(RecordTaxType::class)->handle(['country' => 'AM', 'code' => 'sales', 'names' => ['en' => 'Sales tax'], 'level' => 'national', 'in_force_from' => '2001-01-01', 'introduced_by_id' => $this->act->id]);
});

function errors(Closure $form): array
{
    try {
        $form();
    } catch (ValidationException $e) {
        return array_keys($e->errors());
    }

    return [];
}

it('records acts and taxes from form input', function () {
    expect($this->law->amends->is($this->act))->toBeTrue()
        ->and($this->sales->introducedBy->is($this->act))->toBeTrue()
        ->and($this->sales->country->alpha2)->toBe('AM');
});

it('rejects a malformed act with field errors', function () {
    expect(errors(fn () => app(RecordLegalAct::class)->handle(['country' => 'ZZ', 'kind' => 'poem', 'adopted_on' => '2026-02-01', 'effective_on' => '2026-01-01', 'url' => 'nope'])))
        ->toEqualCanonicalizing(['country', 'kind', 'title', 'effective_on', 'url']);
});

it('rejects an act amending another country\'s act', function () {
    $georgian = app(RecordLegalAct::class)->handle(['country' => 'GE', 'kind' => 'code', 'title' => 'Another code']);

    expect(errors(fn () => app(RecordLegalAct::class)->handle(['country' => 'AM', 'kind' => 'law', 'title' => 'x', 'amends_id' => $georgian->id])))->toBe(['amends_id']);
});

it('rejects overlapping periods of one tax', function () {
    expect(errors(fn () => app(RecordTaxType::class)->handle(['country' => 'AM', 'code' => 'sales', 'names' => ['en' => 'Sales'], 'level' => 'national', 'in_force_from' => '2010-01-01'])))->toBe(['in_force_from']);

    app(AbolishTaxType::class)->handle($this->sales, ['on' => '2020-01-01', 'legal_act_id' => $this->act->id]);
    $back = app(RecordTaxType::class)->handle(['country' => 'AM', 'code' => 'sales', 'names' => ['en' => 'Sales'], 'level' => 'national', 'in_force_from' => '2022-01-01', 'replaces_id' => $this->sales->id]);

    expect($back->replaces->is($this->sales))->toBeTrue();
});

it('rejects an engine that is not a Tax', function () {
    expect(errors(fn () => app(RecordTaxType::class)->handle(['country' => 'AM', 'code' => 'other', 'names' => ['en' => 'Other'], 'level' => 'local', 'engine' => stdClass::class])))->toBe(['engine']);
});

it('amends each kind of value from a form', function () {
    $rate = app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => $this->sales->id, 'key' => '1', 'article' => '1', 'from' => '2027-01-01', 'legal_act_id' => $this->law->id, 'percent' => '20']);
    $threshold = app(AmendLaw::class)->handle(ValueKind::Threshold, ['tax_type_id' => $this->sales->id, 'key' => 'registration', 'from' => '2027-01-01', 'legal_act_id' => $this->law->id, 'amount' => '115000.50', 'currency' => 'AMD', 'period' => 'rolling_12_months']);
    $exemption = app(AmendLaw::class)->handle(ValueKind::Exemption, ['tax_type_id' => $this->sales->id, 'key' => '2', 'from' => '2027-01-01', 'legal_act_id' => $this->law->id, 'name' => 'Food', 'with_credit' => false, 'conditions' => ['max' => 1000]]);
    $row = app(AmendLaw::class)->handle(ValueKind::TableEntry, ['tax_type_id' => $this->sales->id, 'key' => '2710 12', 'from' => '2027-01-01', 'legal_act_id' => $this->law->id, 'table' => 'excise', 'values' => ['rate' => 650]]);

    expect($rate->percent)->toBe(20.0)
        ->and((string) $threshold->amount)->toBe('115000.50 AMD')
        ->and($exemption->conditions)->toBe(['max' => 1000])
        ->and($row->values)->toBe(['rate' => 650])
        ->and($this->law->ratesSet()->count())->toBe(1);
});

it('requires the act, a day within the tax\'s period, and a known currency', function () {
    app(AbolishTaxType::class)->handle($this->sales, ['on' => '2030-01-01', 'legal_act_id' => $this->law->id]);
    $other = app(RecordLegalAct::class)->handle(['country' => 'GE', 'kind' => 'law', 'title' => 'Georgian law']);

    expect(errors(fn () => app(AmendLaw::class)->handle(ValueKind::Threshold, ['tax_type_id' => $this->sales->id, 'key' => 'x', 'from' => '2031-01-01', 'legal_act_id' => $other->id, 'amount' => 5, 'currency' => 'XYZ'])))
        ->toEqualCanonicalizing(['from', 'legal_act_id', 'currency'])
        ->and(errors(fn () => app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => $this->sales->id, 'key' => '1', 'from' => '2027-01-01', 'legal_act_id' => $this->law->id])))
        ->toEqualCanonicalizing(['percent', 'amount']);
});

it('repeals a value from a form, not before it began', function () {
    $exemption = app(AmendLaw::class)->handle(ValueKind::Exemption, ['tax_type_id' => $this->sales->id, 'key' => '2', 'from' => '2027-01-01', 'legal_act_id' => $this->law->id, 'name' => 'Food']);

    expect(errors(fn () => app(RepealLaw::class)->handle($exemption, ['on' => '2026-01-01', 'legal_act_id' => $this->law->id])))->toBe(['on']);

    app(RepealLaw::class)->handle($exemption, ['on' => '2028-01-01', 'legal_act_id' => $this->law->id]);
    expect($exemption->fresh()->repealedBy->is($this->law))->toBeTrue();
});

it('logs who changed what, and why', function () {
    $this->actingAs($this->clerk);

    $rate = app(AmendLaw::class)->handle(ValueKind::Rate, [
        'tax_type_id' => $this->sales->id, 'key' => '1', 'from' => '2027-01-01', 'legal_act_id' => $this->law->id, 'percent' => 20,
        'reason' => 'Entered from Law №2, Art. 1', 'source' => 'https://example.test/law/2',
    ]);

    $revision = $rate->revisions()->sole();

    expect($revision->action)->toBe('created')
        ->and($revision->causer->is($this->clerk))->toBeTrue()
        ->and($revision->who())->toBe('Nino')
        ->and($revision->reason)->toBe('Entered from Law №2, Art. 1')
        ->and($revision->source)->toBe('https://example.test/law/2')
        ->and($revision->changes['new']['percent'])->toEqual(20);
});

it('logs edits with the old and new values, and deletions', function () {
    $rate = TaxRate::query()->create(['tax_type_id' => $this->sales->id, 'key' => '9', 'percent' => 10]);
    $rate->update(['percent' => 12]);
    $rate->delete();

    $log = Revision::query()->where('subject_type', $rate->getMorphClass())->where('subject_id', $rate->id)->oldest('id')->get();

    expect($log->pluck('action')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($log[1]->changes)->toEqual(['old' => ['percent' => '10.0000'], 'new' => ['percent' => 12]]);
});

it('names an agent, or any label, as the editor', function () {
    $agent = Clerk::query()->create(['name' => 'Matsne reader']);

    app(Editor::class)->as($agent, 'Read from Matsne')->run(fn () => TaxRate::query()->create(['tax_type_id' => $this->sales->id, 'key' => 'a', 'percent' => 1]));
    app(Editor::class)->as('agent:matsne-reader')->run(fn () => TaxRate::query()->create(['tax_type_id' => $this->sales->id, 'key' => 'b', 'percent' => 1]));

    [$a, $b] = [TaxRate::query()->where('key', 'a')->sole()->revisions()->sole(), TaxRate::query()->where('key', 'b')->sole()->revisions()->sole()];

    expect($a->who())->toBe('Matsne reader')->and($a->reason)->toBe('Read from Matsne')
        ->and($b->who())->toBe('agent:matsne-reader')->and($b->causer)->toBeNull();
});

it('exposes its rules for a FormRequest', function () {
    expect(app(RecordLegalAct::class)->rules())->toHaveKeys(['country', 'kind', 'title', 'amends_id'])
        ->and(app(AmendLaw::class)->rules(['kind' => 'threshold']))->toHaveKeys(['tax_type_id', 'amount', 'currency', 'period']);
});
