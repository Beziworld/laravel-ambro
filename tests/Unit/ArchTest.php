<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\Ambro')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\Ambro\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\Ambro\Exceptions')
    ->toExtend('Beziworld\Ambro\Exceptions\AmbroException')
    ->ignoring('Beziworld\Ambro\Exceptions\AmbroException');
