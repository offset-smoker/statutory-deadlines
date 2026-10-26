<?php

declare(strict_types=1);

arch('PHP preset')->preset()->php();

arch('security preset')->preset()->security();

arch('strict preset')->preset()->strict()->ignoring([
    // A Laravel facade must override the protected getFacadeAccessor().
    'OffsetSmoker\StatutoryDeadlines\Laravel\Facades',
]);

arch('the core does not depend on Laravel')
    ->expect('OffsetSmoker\StatutoryDeadlines')
    ->not->toUse(['Illuminate', 'Carbon'])
    ->ignoring('OffsetSmoker\StatutoryDeadlines\Laravel');

arch('value objects are readonly')
    ->expect([
        'OffsetSmoker\StatutoryDeadlines\Calendar',
        'OffsetSmoker\StatutoryDeadlines\Deadline',
        'OffsetSmoker\StatutoryDeadlines\DeadlineFactory',
        'OffsetSmoker\StatutoryDeadlines\LocalDate',
        'OffsetSmoker\StatutoryDeadlines\Period',
        'OffsetSmoker\StatutoryDeadlines\Cutoff',
        'OffsetSmoker\StatutoryDeadlines\Explain',
        'OffsetSmoker\StatutoryDeadlines\Rules',
    ])
    ->classes()
    ->toBeReadonly();

arch('holiday rules implement the contract')
    ->expect('OffsetSmoker\StatutoryDeadlines\Holidays')
    ->classes()
    ->toImplement('OffsetSmoker\StatutoryDeadlines\Holidays\HolidayRule')
    ->ignoring([
        'OffsetSmoker\StatutoryDeadlines\Holidays\Observance',
        'OffsetSmoker\StatutoryDeadlines\Holidays\FederalHolidays',
        'OffsetSmoker\StatutoryDeadlines\Holidays\PennsylvaniaHolidays',
    ]);
