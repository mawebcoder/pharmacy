<?php

declare(strict_types=1);

use App\Modules\Catalog\Queries\ActiveBrandsQuery;

it('files a brand under the first letter of its Persian name', function (string $name, string $letter): void {
    expect(ActiveBrandsQuery::letterFor($name))->toBe($letter);
})->with([
    'alef' => ['اوسرین', 'الف'],
    'alef with madda' => ['آردن', 'الف'],
    'Arabic kaf' => ['كامان', 'ک'],
    'Persian letter' => ['ژیناژن', 'ژ'],
    'Latin name' => ['eucerin', 'E'],
    'digit' => ['3M', '#'],
]);
