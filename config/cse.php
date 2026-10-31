<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Display Timezone
    |--------------------------------------------------------------------------
    |
    | Times are stored in UTC (app.timezone) and converted to this timezone
    | only when shown to users.
    |
    */

    'display_timezone' => env('CSE_DISPLAY_TIMEZONE', 'Asia/Manila'),

    /*
    |--------------------------------------------------------------------------
    | Staged Content
    |--------------------------------------------------------------------------
    |
    | Folder that `cse:import` reads media from (plan §8.2). Media paths in an
    | import file are relative to it. Gitignored; see content/README.md.
    |
    */

    'staged_path' => base_path('content/staged'),

];
