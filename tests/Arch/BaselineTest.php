<?php

arch('env() is only read in config files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('domain code uses strict types')
    ->expect('App\Domain')
    ->toUseStrictTypes();
