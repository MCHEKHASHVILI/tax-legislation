<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Mchekhashvili\TaxLegislation\Http\Controllers\CountryController;
use Mchekhashvili\TaxLegislation\Http\Controllers\DraftController;
use Mchekhashvili\TaxLegislation\Http\Controllers\LegalActController;
use Mchekhashvili\TaxLegislation\Http\Controllers\RevisionController;
use Mchekhashvili\TaxLegislation\Http\Controllers\TaxTypeController;
use Mchekhashvili\TaxLegislation\Http\Controllers\ValueController;
use Mchekhashvili\TaxLegislation\Http\Middleware\ExpectsJson;

/**
 * The JSON API: the law as recorded, forms that submit changes (drafts while approval is on), the
 * drafts to approve or reject, and the revision log.
 *
 * The service provider registers it when config('tax-legislation.api.enabled') is on, under the
 * configured prefix, name and middleware. To place it yourself — inside your own route group,
 * behind your own guards — leave the switch off and call this from a routes file:
 *
 *     Route::middleware(['api', 'auth:sanctum'])->prefix('law')->group(fn () => Routes::register());
 *
 * Authentication is the app's middleware. Approving and rejecting go through the approval gate
 * (config `tax-legislation.approval.gate`); a refusal answers 403. Field errors, and a draft that
 * was already decided, answer 422.
 *
 * @see https://github.com/MCHEKHASHVILI/tax-legislation#json-api for the endpoints.
 */
final class Routes
{
    /** Registers the routes in the current group. @param array{prefix?: string, name?: string, middleware?: list<string>} $options */
    public static function register(array $options = []): void
    {
        /** @var Router $router */
        $router = app('router');

        $router->group([
            'prefix' => $options['prefix'] ?? '',
            'as' => $options['name'] ?? 'tax-legislation.',
            'middleware' => [ExpectsJson::class, ...($options['middleware'] ?? []), SubstituteBindings::class],
        ], function (Router $router) {
            // The law as recorded.
            $router->get('countries', [CountryController::class, 'index'])->name('countries.index');
            $router->get('countries/{country}', [CountryController::class, 'show'])->name('countries.show');
            $router->get('countries/{country}/legal-acts', [LegalActController::class, 'index'])->name('legal-acts.index');
            $router->get('countries/{country}/tax-types', [TaxTypeController::class, 'index'])->name('tax-types.index');
            $router->get('legal-acts/{legalAct}', [LegalActController::class, 'show'])->name('legal-acts.show');
            $router->get('tax-types/{taxType}', [TaxTypeController::class, 'show'])->name('tax-types.show');
            $router->get('tax-types/{taxType}/law', [TaxTypeController::class, 'law'])->name('tax-types.law');
            $router->get('tax-types/{taxType}/history/{kind}/{key}', [ValueController::class, 'history'])->where('key', '.+')->name('values.history');
            $router->get('values/{kind}/{id}', [ValueController::class, 'show'])->whereNumber('id')->name('values.show');

            // Changes to the law: a draft (202) while approval is on, else the record (201).
            $router->post('legal-acts', [LegalActController::class, 'store'])->name('legal-acts.store');
            $router->post('tax-types', [TaxTypeController::class, 'store'])->name('tax-types.store');
            $router->post('tax-types/{taxType}/abolish', [TaxTypeController::class, 'abolish'])->name('tax-types.abolish');
            $router->post('amendments', [ValueController::class, 'amend'])->name('values.amend');
            $router->post('values/{kind}/{id}/repeal', [ValueController::class, 'repeal'])->whereNumber('id')->name('values.repeal');

            // Drafts awaiting a decision.
            $router->get('drafts', [DraftController::class, 'index'])->name('drafts.index');
            $router->get('drafts/{draft}', [DraftController::class, 'show'])->name('drafts.show');
            $router->post('drafts/{draft}/approve', [DraftController::class, 'approve'])->name('drafts.approve');
            $router->post('drafts/{draft}/reject', [DraftController::class, 'reject'])->name('drafts.reject');

            // Who entered what.
            $router->get('revisions', [RevisionController::class, 'index'])->name('revisions.index');
        });
    }
}
