<?php

return [

    /*
    | The taxes the engine runs, in order. Null: the taxes country packages register (georgian-tax
    | registers Georgia's six). Set a list to choose and order them yourself.
    */
    'taxes' => null,

    /*
    | Follow the recorded law. Where a country's tax types are recorded, only taxes in force on the
    | event's day run, an in-force tax no engine computes is reported as NotComputed, and an
    | exemption the recorded law had not in force that day does not apply. False: run every tax.
    */
    'follow_law' => env('TAX_LEGISLATION_FOLLOW_LAW', true),

    /*
    | Approve changes to the law. On: the actions (RecordLegalAct, RecordTaxType, AbolishTaxType,
    | AmendLaw, RepealLaw) save a pending Draft with no legal effect, and ApproveDraft applies it.
    | Off: the actions apply changes at once — for tests and seeders. Seeders and imports that
    | write the models directly, or call $action->directly(), always take effect at once.
    |
    | `gate`: the ability ApproveDraft and RejectDraft check for the editor, given the draft. Your
    | app defines it (Gate::define / a policy); until it does, nobody may decide. Null: no check
    | here — authorize in your routes' middleware.
    */
    'approval' => [
        'required' => env('TAX_LEGISLATION_APPROVAL', true),
        'gate' => 'approve-legislation',
    ],

    /* Replace one tax's stages: 'vat' => [Stage::class, Stage::class => [Handler::class]]. */
    'pipelines' => [],

    /* Keep every report in tax_assessments / tax_assessment_outcomes. */
    'store_reports' => env('TAX_LEGISLATION_STORE_REPORTS', true),

    'database' => [
        'connection' => env('TAX_LEGISLATION_DB_CONNECTION'),
        'table_prefix' => '',
        // Run the package migrations with yours. Set false and publish them to edit them first.
        'run_migrations' => true,
    ],

    'queue' => [
        'connection' => env('TAX_LEGISLATION_QUEUE_CONNECTION'),
        'queue' => env('TAX_LEGISLATION_QUEUE'),
    ],

];
