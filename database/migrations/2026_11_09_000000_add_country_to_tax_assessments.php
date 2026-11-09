<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The country whose law assessed an event, as ISO 3166-1 alpha-2: on each outcome, and on the
 * assessment when all its outcomes are one country's. An alpha-2 code, not a key into countries,
 * so a report is kept whether or not the countries table is seeded.
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

    public function up(): void
    {
        Schema::table($this->t('tax_assessments'), function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('event_type');
            $table->index(['country', 'occurred_on']);
        });

        Schema::table($this->t('tax_assessment_outcomes'), function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('assessment_id');
            $table->index(['country', 'tax']);
        });
    }

    public function down(): void
    {
        Schema::table($this->t('tax_assessment_outcomes'), function (Blueprint $table) {
            $table->dropIndex(['country', 'tax']);
            $table->dropColumn('country');
        });

        Schema::table($this->t('tax_assessments'), function (Blueprint $table) {
            $table->dropIndex(['country', 'occurred_on']);
            $table->dropColumn('country');
        });
    }
};
