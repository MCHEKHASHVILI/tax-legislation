<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Changes to the law waiting for approval, and the link from a revision to the draft it applied.
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
        Schema::create($this->t('legislation_drafts'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained(config('countries.table', 'countries'))->nullOnDelete();
            $table->string('action');                      // the Action class that applies it
            $table->string('kind', 20)->nullable();        // what AmendLaw changes: rate, threshold…
            $table->nullableMorphs('subject');             // the value repealed, the tax abolished
            $table->json('payload');                       // the validated input
            $table->string('status', 10)->index();         // pending | approved | rejected
            $table->text('reason')->nullable();
            $table->string('source', 2000)->nullable();
            $table->nullableMorphs('drafted_by');
            $table->string('drafted_by_label')->nullable();
            $table->nullableMorphs('decided_by');
            $table->string('decided_by_label')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();     // why it was rejected, or a note on approval
            $table->nullableMorphs('result');              // the record approval created or changed
            $table->timestamps();
        });

        Schema::table($this->t('legislation_revisions'), function (Blueprint $table) {
            $table->unsignedBigInteger('draft_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table($this->t('legislation_revisions'), function (Blueprint $table) {
            $table->dropIndex(['draft_id']);
            $table->dropColumn('draft_id');
        });

        Schema::dropIfExists($this->t('legislation_drafts'));
    }
};
