<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The law over time, per country. Money columns hold minor units with their ISO 4217 currency
 * alongside (`amount` + `amount_currency`).
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('tax-legislation.database.connection');
    }

    private function t(string $name): string
    {
        return config('tax-legislation.database.table_prefix', '') . $name;
    }

    /** effective_from / effective_until, the act that set the value and the act that ended it. */
    private function validity(Blueprint $table): void
    {
        $table->date('effective_from')->nullable();     // null: since before the recorded history
        $table->date('effective_until')->nullable();    // first day no longer in force; null: in force
        $table->string('article')->nullable();          // the provision: '166', '204.1.ა'
        $table->foreignId('legal_act_id')->nullable()->constrained($this->t('legal_acts'))->nullOnDelete();
        $table->foreignId('repealed_by_id')->nullable()->constrained($this->t('legal_acts'))->nullOnDelete();
        $table->index(['effective_from', 'effective_until']);
    }

    public function up(): void
    {
        $countries = config('countries.table', 'countries');

        Schema::create($this->t('legal_acts'), function (Blueprint $table) use ($countries) {
            $table->id();
            $table->foreignId('country_id')->constrained($countries);
            $table->string('kind', 20);
            $table->string('number')->nullable();
            $table->string('title');
            $table->string('issuer')->nullable();
            $table->date('adopted_on')->nullable();
            $table->date('published_on')->nullable();
            $table->date('effective_on')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('amends_id')->nullable()->constrained($this->t('legal_acts'))->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['country_id', 'kind', 'number']);
        });

        Schema::create($this->t('tax_types'), function (Blueprint $table) use ($countries) {
            $table->id();
            $table->foreignId('country_id')->constrained($countries);
            $table->string('code', 40);
            $table->json('names');
            $table->string('level', 20);
            $table->date('in_force_from')->nullable();
            $table->date('abolished_on')->nullable();
            $table->foreignId('replaces_id')->nullable()->constrained($this->t('tax_types'))->nullOnDelete();
            $table->foreignId('introduced_by_id')->nullable()->constrained($this->t('legal_acts'))->nullOnDelete();
            $table->foreignId('abolished_by_id')->nullable()->constrained($this->t('legal_acts'))->nullOnDelete();
            $table->string('engine')->nullable();
            $table->string('legal_basis')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['country_id', 'code', 'in_force_from']);
            $table->index(['in_force_from', 'abolished_on']);
        });

        Schema::create($this->t('tax_rates'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_type_id')->constrained($this->t('tax_types'))->cascadeOnDelete();
            $table->string('key');
            $table->string('name')->nullable();
            $table->decimal('percent', 9, 4)->nullable();
            $table->bigInteger('amount')->nullable();
            $table->char('amount_currency', 3)->nullable();
            $table->string('unit', 40)->nullable();
            $this->validity($table);
            $table->timestamps();
            $table->index(['tax_type_id', 'key']);
        });

        Schema::create($this->t('tax_thresholds'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_type_id')->constrained($this->t('tax_types'))->cascadeOnDelete();
            $table->string('key');
            $table->string('name')->nullable();
            $table->bigInteger('amount');
            $table->char('amount_currency', 3);
            $table->string('period', 30)->nullable();
            $this->validity($table);
            $table->timestamps();
            $table->index(['tax_type_id', 'key']);
        });

        Schema::create($this->t('tax_exemptions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_type_id')->constrained($this->t('tax_types'))->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('with_credit')->default(false);
            $table->json('conditions')->nullable();
            $table->string('handler')->nullable();
            $this->validity($table);
            $table->timestamps();
            $table->index(['tax_type_id', 'key']);
        });

        Schema::create($this->t('tax_table_entries'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_type_id')->constrained($this->t('tax_types'))->cascadeOnDelete();
            $table->string('table', 60);
            $table->string('key');
            $table->json('values');
            $this->validity($table);
            $table->timestamps();
            $table->index(['tax_type_id', 'table', 'key']);
        });

        Schema::create($this->t('tax_assessments'), function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->longText('payload');
            $table->date('occurred_on')->index();
            $table->bigInteger('total_tax');
            $table->char('total_tax_currency', 3);
            $table->timestamps();
        });

        Schema::create($this->t('tax_assessment_outcomes'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained($this->t('tax_assessments'))->cascadeOnDelete();
            $table->string('tax', 20);
            $table->string('outcome', 20);
            $table->string('article', 40);
            $table->string('title')->nullable();
            $table->string('taxpayer')->nullable()->index();
            $table->string('agent')->nullable()->index();
            $table->bigInteger('base')->nullable();
            $table->char('base_currency', 3)->nullable();
            $table->decimal('rate', 9, 4)->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 40)->nullable();
            $table->bigInteger('amount')->nullable();
            $table->char('amount_currency', 3)->nullable();
            $table->string('collection', 30)->nullable();
            $table->date('due_on')->nullable()->index();
            $table->text('summary');
            $table->json('notes')->nullable();
            $table->timestamps();
            $table->index(['tax', 'outcome']);
        });
    }

    public function down(): void
    {
        foreach (['tax_assessment_outcomes', 'tax_assessments', 'tax_table_entries', 'tax_exemptions', 'tax_thresholds', 'tax_rates', 'tax_types', 'legal_acts'] as $name) {
            Schema::dropIfExists($this->t($name));
        }
    }
};
