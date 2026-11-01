<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Revisions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mchekhashvili\TaxLegislation\Legislation\Law;

/**
 * Logs every create, update and delete of a legislation record as a Revision, with the editor
 * (Editor::causer()) and the reason given. Bulk inserts bypass model events and are not logged.
 */
trait RecordsRevisions
{
    public static function bootRecordsRevisions(): void
    {
        static::created(fn (Model $m) => self::revise($m, 'created', [], $m->getAttributes()));
        static::updated(fn (Model $m) => self::revise($m, 'updated', array_intersect_key($m->getOriginal(), $m->getChanges()), $m->getChanges()));
        static::deleted(fn (Model $m) => self::revise($m, 'deleted', $m->getAttributes(), []));
    }

    /** @return MorphMany<Revision, $this> */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'subject')->latest('id');
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private static function revise(Model $model, string $action, array $old, array $new): void
    {
        unset($old['updated_at'], $new['updated_at'], $old['created_at'], $new['created_at']);

        if ($action === 'updated' && $new === []) {
            return;
        }

        $editor = app(Editor::class);
        $causer = $editor->causer();

        $revision = new Revision([
            'action' => $action,
            'changes' => array_filter(['old' => $old ?: null, 'new' => $new ?: null]),
            'causer_label' => is_string($causer) ? $causer : null,
            'reason' => $editor->reason(),
            'source' => $editor->source(),
        ]);
        $revision->subject()->associate($model);

        if ($causer instanceof Model) {
            $revision->causer()->associate($causer);
        }

        $revision->save();

        app(Law::class)->forget();
    }
}
