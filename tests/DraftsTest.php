<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Mchekhashvili\Countries\Database\Seeders\CountrySeeder;
use Mchekhashvili\TaxLegislation\Actions\AbolishTaxType;
use Mchekhashvili\TaxLegislation\Actions\AmendLaw;
use Mchekhashvili\TaxLegislation\Actions\ApproveDraft;
use Mchekhashvili\TaxLegislation\Actions\RecordLegalAct;
use Mchekhashvili\TaxLegislation\Actions\RecordTaxType;
use Mchekhashvili\TaxLegislation\Actions\RejectDraft;
use Mchekhashvili\TaxLegislation\Actions\RepealLaw;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Drafts\DraftStatus;
use Mchekhashvili\TaxLegislation\Events\DraftDecided;
use Mchekhashvili\TaxLegislation\Events\DraftSubmitted;
use Mchekhashvili\TaxLegislation\Legislation\Law;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\ValueKind;
use Mchekhashvili\TaxLegislation\Revisions\Editor;

/*
| Invented acts and figures — they exercise approval, they are not legal history.
*/

final class Reviewer extends Illuminate\Foundation\Auth\User
{
    public $timestamps = false;
    protected $table = 'reviewers';
    protected $guarded = [];
}

beforeEach(function () {
    $this->seed(CountrySeeder::class);
    Schema::create('reviewers', fn ($t) => [$t->id(), $t->string('name'), $t->boolean('approves')->default(false)]);
    $this->drafter = Reviewer::query()->create(['name' => 'Nino']);
    $this->approver = Reviewer::query()->create(['name' => 'Giorgi', 'approves' => true]);

    // The law as it stands, entered directly; approval is on from here.
    $this->code = app(RecordLegalAct::class)->handle(['country' => 'AM', 'kind' => 'code', 'title' => 'Example Code']);
    $this->law = app(RecordLegalAct::class)->handle(['country' => 'AM', 'kind' => 'law', 'number' => '2', 'title' => 'Example law', 'amends_id' => $this->code->id]);
    $this->sales = app(RecordTaxType::class)->handle(['country' => 'AM', 'code' => 'sales', 'names' => ['en' => 'Sales tax'], 'level' => 'national', 'in_force_from' => '2001-01-01']);
    $this->rate = TaxRate::amend($this->sales, '1', ['percent' => 18], from: '2001-01-01', by: $this->code, article: '1');

    config(['tax-legislation.approval.required' => true]);
    Gate::define('approve-legislation', fn (Reviewer $user, Draft $draft) => $user->approves && !$draft->draftedBy?->is($user));
});

function raise(array $input = []): array
{
    return [ValueKind::Rate, ['tax_type_id' => test()->sales->id, 'key' => '1', 'from' => '2027-01-01', 'legal_act_id' => test()->law->id, 'percent' => 20, ...$input]];
}

function fieldErrors(Closure $form): array
{
    try {
        $form();
    } catch (ValidationException $e) {
        return array_keys($e->errors());
    }

    return [];
}

it('saves an amendment as a pending draft, with no legal effect', function () {
    Event::fake([DraftSubmitted::class]);
    $this->actingAs($this->drafter);

    $draft = app(AmendLaw::class)->handle(...raise(['reason' => 'Law №2, Art. 1', 'source' => 'https://example.test/2']));

    expect($draft)->toBeInstanceOf(Draft::class)
        ->and($draft->status)->toBe(DraftStatus::Pending)
        ->and($draft->kind)->toBe(ValueKind::Rate)
        ->and($draft->action)->toBe(AmendLaw::class)
        ->and($draft->country->alpha2)->toBe('AM')
        ->and($draft->drafter())->toBe('Nino')
        ->and($draft->reason)->toBe('Law №2, Art. 1')
        ->and($draft->payload['percent'])->toEqual(20)
        ->and(TaxRate::query()->historyOf($this->sales, '1')->count())->toBe(1)
        ->and(TaxRate::query()->ofTax($this->sales)->inForceOn('2027-06-01')->sole()->percent)->toBe(18.0)
        ->and($draft->revisions()->sole()->who())->toBe('Nino');

    Event::assertDispatched(DraftSubmitted::class, fn ($e) => $e->draft->is($draft));
});

it('applies a draft on approval: closes the value in force, opens the new one, records the approver', function () {
    Event::fake([DraftDecided::class]);
    $this->actingAs($this->drafter);
    $draft = app(AmendLaw::class)->handle(...raise(['reason' => 'Law №2, Art. 1']));

    $this->actingAs($this->approver);
    $rate = app(ApproveDraft::class)->handle($draft, ['reason' => 'Checked against the Herald']);

    $history = TaxRate::query()->historyOf($this->sales, '1')->get();
    $revision = $rate->revisions()->sole();

    expect($rate)->toBeInstanceOf(TaxRate::class)->and($rate->percent)->toBe(20.0)
        ->and($history->pluck('effective_until')->map(fn ($d) => $d?->format('Y-m-d'))->all())->toBe(['2027-01-01', null])
        ->and($draft->status)->toBe(DraftStatus::Approved)
        ->and($draft->decider())->toBe('Giorgi')
        ->and($draft->decision_note)->toBe('Checked against the Herald')
        ->and($draft->decided_at)->not->toBeNull()
        ->and($draft->result->is($rate))->toBeTrue()
        ->and($revision->who())->toBe('Giorgi')
        ->and($revision->reason)->toBe('Law №2, Art. 1')
        ->and($revision->draft->is($draft))->toBeTrue()
        ->and($revision->draft->drafter())->toBe('Nino')
        ->and($draft->revisions()->get()->map(fn ($r) => [$r->action, $r->who()])->all())->toBe([['updated', 'Giorgi'], ['created', 'Nino']]);

    Event::assertDispatched(DraftDecided::class, fn ($e) => $e->draft->status === DraftStatus::Approved);
});

it('keeps a rejected draft with the reason, unapplied', function () {
    $this->actingAs($this->drafter);
    $draft = app(AmendLaw::class)->handle(...raise());

    $this->actingAs($this->approver);
    expect(fieldErrors(fn () => app(RejectDraft::class)->handle($draft, [])))->toBe(['reason']);

    app(RejectDraft::class)->handle($draft, ['reason' => 'The law was not adopted']);

    expect($draft->fresh()->status)->toBe(DraftStatus::Rejected)
        ->and($draft->decision_note)->toBe('The law was not adopted')
        ->and($draft->decider())->toBe('Giorgi')
        ->and(TaxRate::query()->historyOf($this->sales, '1')->count())->toBe(1)
        ->and(fieldErrors(fn () => app(ApproveDraft::class)->handle($draft)))->toBe(['draft']);
});

it('decides a draft once', function () {
    $draft = app(Editor::class)->as('agent:herald-reader')->run(fn () => app(AmendLaw::class)->handle(...raise()));
    $this->actingAs($this->approver);
    app(ApproveDraft::class)->handle($draft);

    expect($draft->drafter())->toBe('agent:herald-reader')
        ->and(fieldErrors(fn () => app(ApproveDraft::class)->handle(Draft::query()->find($draft->id))))->toBe(['draft'])
        ->and(fieldErrors(fn () => app(RejectDraft::class)->handle(Draft::query()->find($draft->id), ['reason' => 'x'])))->toBe(['draft'])
        ->and(TaxRate::query()->historyOf($this->sales, '1')->count())->toBe(2);
});

it('re-checks overlaps at approval; a draft overtaken by another stays pending', function () {
    app(AbolishTaxType::class)->directly()->handle($this->sales, ['on' => '2020-01-01', 'legal_act_id' => $this->law->id]);
    $back = fn (string $from) => app(RecordTaxType::class)->handle(['country' => 'AM', 'code' => 'sales', 'names' => ['en' => 'Sales'], 'level' => 'national', 'in_force_from' => $from]);

    [$first, $second] = [$back('2022-01-01'), $back('2023-01-01')];   // each fine on its own, against the law as it stood
    $this->actingAs($this->approver);
    app(ApproveDraft::class)->handle($first);

    expect(fieldErrors(fn () => app(ApproveDraft::class)->handle($second)))->toBe(['in_force_from'])
        ->and($second->fresh()->isPending())->toBeTrue()
        ->and(TaxType::query()->historyOf('sales')->count())->toBe(2);
});

it('re-checks the tax\'s period at approval', function () {
    $draft = app(AmendLaw::class)->handle(...raise(['from' => '2031-01-01']));
    app(AbolishTaxType::class)->directly()->handle($this->sales, ['on' => '2030-01-01', 'legal_act_id' => $this->law->id]);

    $this->actingAs($this->approver);

    expect(fieldErrors(fn () => app(ApproveDraft::class)->handle($draft)))->toBe(['from'])
        ->and($draft->fresh()->isPending())->toBeTrue();
});

it('drafts and applies each kind of change', function () {
    $exemption = Exemption::amend($this->sales, '2', ['name' => 'Food'], from: '2001-01-01', by: $this->code);

    $act = app(RecordLegalAct::class)->handle(['country' => 'AM', 'kind' => 'law', 'number' => '3', 'title' => 'Another law', 'amends_id' => $this->code->id]);
    $tax = app(RecordTaxType::class)->handle(['country' => 'AM', 'code' => 'land', 'names' => ['en' => 'Land tax'], 'level' => 'local']);
    $repeal = app(RepealLaw::class)->handle($exemption, ['on' => '2027-01-01', 'legal_act_id' => $this->law->id]);
    $abolish = app(AbolishTaxType::class)->handle($this->sales, ['on' => '2030-01-01', 'legal_act_id' => $this->law->id]);

    expect([$act, $tax, $repeal, $abolish])->each->toBeInstanceOf(Draft::class)
        ->and(LegalAct::query()->where('number', '3')->exists())->toBeFalse()
        ->and(TaxType::query()->where('code', 'land')->exists())->toBeFalse()
        ->and($exemption->fresh()->effective_until)->toBeNull()
        ->and($this->sales->fresh()->abolished_on)->toBeNull()
        ->and($repeal->subject->is($exemption))->toBeTrue()
        ->and($abolish->subject->is($this->sales))->toBeTrue();

    $this->actingAs($this->approver);
    $applied = array_map(fn (Draft $d) => app(ApproveDraft::class)->handle($d), [$act, $tax, $repeal, $abolish]);

    expect($applied[0])->toBeInstanceOf(LegalAct::class)->and($applied[0]->amends->is($this->code))->toBeTrue()
        ->and($applied[1]->country->alpha2)->toBe('AM')
        ->and($exemption->fresh()->effective_until->format('Y-m-d'))->toBe('2027-01-01')
        ->and($exemption->fresh()->repealedBy->is($this->law))->toBeTrue()
        ->and($this->sales->fresh()->abolished_on->format('Y-m-d'))->toBe('2030-01-01');
});

it('ignores pending drafts when reading the law', function () {
    app(AbolishTaxType::class)->handle($this->sales, ['on' => '2010-01-01', 'legal_act_id' => $this->law->id]);

    expect(app(Law::class)->taxesInForce('AM', new DateTimeImmutable('2026-01-01'))->keys()->all())->toBe(['sales']);
});

it('lets the app decide who approves', function () {
    $this->actingAs($this->drafter);
    $draft = app(AmendLaw::class)->handle(...raise());

    expect(fn () => app(ApproveDraft::class)->handle($draft))->toThrow(AuthorizationException::class)   // not an approver
        ->and(fn () => app(Editor::class)->as('agent:x')->run(fn () => app(RejectDraft::class)->handle($draft, ['reason' => 'x'])))->toThrow(AuthorizationException::class);

    $this->drafter->update(['approves' => true]);
    expect(fn () => app(ApproveDraft::class)->handle($draft))->toThrow(AuthorizationException::class);   // not one's own draft

    config(['tax-legislation.approval.gate' => null]);
    app(ApproveDraft::class)->handle($draft);

    expect($draft->status)->toBe(DraftStatus::Approved);
});

it('denies every decision until the app defines the gate', function () {
    config(['tax-legislation.approval.gate' => 'decide-tax-law']);
    $draft = app(AmendLaw::class)->handle(...raise());
    $this->actingAs($this->approver);

    expect(fn () => app(ApproveDraft::class)->handle($draft))->toThrow(AuthorizationException::class);
});

it('applies at once through directly(), or with approval off', function () {
    $direct = app(AmendLaw::class)->directly()->handle(...raise());
    config(['tax-legislation.approval.required' => false]);
    $off = app(AmendLaw::class)->handle(...raise(['from' => '2028-01-01', 'percent' => 22]));

    expect($direct)->toBeInstanceOf(TaxRate::class)->and($off)->toBeInstanceOf(TaxRate::class)
        ->and(Draft::query()->count())->toBe(0)
        ->and(TaxRate::query()->historyOf($this->sales, '1')->pluck('percent')->all())->toBe([18.0, 20.0, 22.0]);
});

it('validates on submission, before any draft is saved', function () {
    expect(fieldErrors(fn () => app(AmendLaw::class)->handle(ValueKind::Rate, ['tax_type_id' => $this->sales->id, 'key' => '1', 'from' => '2027-01-01', 'legal_act_id' => $this->law->id])))
        ->toEqualCanonicalizing(['percent', 'amount'])
        ->and(Draft::query()->count())->toBe(0);
});
