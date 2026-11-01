<?php

return [

    /*
    | The taxes the engine runs, in order. Null: the taxes country packages register (georgian-tax
    | registers Georgia's six). Set a list to choose and order them yourself.
    */
    'taxes' => null,

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
