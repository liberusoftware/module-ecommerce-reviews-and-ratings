<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

const MODULE_TABLES = [
    'reviews_expressions',
    'reviews_expression_facets',
    'reviews_moderation_decisions',
    'reviews_votes',
    'reviews_flags',
];

it('carries a non-nullable tenant on every table', function (string $table) {
    $columns = collect(Schema::getColumns($table))->keyBy('name');

    expect($columns)->toHaveKey('tenant_id')
        ->and($columns['tenant_id']['nullable'])->toBeFalse();
})->with(MODULE_TABLES);

it('indexes the tenant on every table', function (string $table) {
    $indexed = collect(Schema::getIndexes($table))
        ->contains(static fn (array $index): bool => in_array('tenant_id', $index['columns'], true));

    expect($indexed)->toBeTrue();
})->with(MODULE_TABLES);

it('stores every score as an integer and never as a float or a decimal', function (string $table) {
    foreach (Schema::getColumns($table) as $column) {
        expect(strtolower($column['type_name']))
            ->not->toContain('float')
            ->and(strtolower($column['type_name']))->not->toContain('double')
            ->and(strtolower($column['type_name']))->not->toContain('decimal')
            ->and(strtolower($column['type_name']))->not->toContain('real');
    }
})->with(MODULE_TABLES);

it('stores scores on integer columns', function () {
    $columns = collect(Schema::getColumns('reviews_expressions'))->keyBy('name');

    expect(strtolower($columns['score']['type_name']))->toContain('int')
        ->and(strtolower($columns['scale']['type_name']))->toContain('int');

    $facets = collect(Schema::getColumns('reviews_expression_facets'))->keyBy('name');

    expect(strtolower($facets['score']['type_name']))->toContain('int')
        ->and(strtolower($facets['scale']['type_name']))->toContain('int');
});

/*
 * SQLite only enforces foreign keys with the pragma on, and a pragma set inside
 * RefreshDatabase's transaction does nothing — so the declared key is what gets
 * asserted, not the behaviour under a driver that may be ignoring it.
 */
it('declares the foreign keys that hold a chain together', function (string $table, string $column, string $on) {
    $declared = collect(Schema::getForeignKeys($table))
        ->contains(static fn (array $key): bool => in_array($column, $key['columns'], true) && $key['foreign_table'] === $on);

    expect($declared)->toBeTrue();
})->with([
    ['reviews_expressions', 'parent_expression_id', 'reviews_expressions'],
    ['reviews_expressions', 'supersedes_id', 'reviews_expressions'],
    ['reviews_expression_facets', 'expression_id', 'reviews_expressions'],
    ['reviews_moderation_decisions', 'expression_id', 'reviews_expressions'],
    ['reviews_votes', 'expression_id', 'reviews_expressions'],
    ['reviews_flags', 'expression_id', 'reviews_expressions'],
]);

it('holds the duplicate rules in unique indexes rather than in a read somebody can lose a race to', function (string $table, array $columns) {
    $declared = collect(Schema::getIndexes($table))
        ->contains(static fn (array $index): bool => $index['unique'] && $index['columns'] === $columns);

    expect($declared)->toBeTrue();
})->with([
    'one live expression per author per product' => ['reviews_expressions', ['live_key']],
    'one public reference per expression' => ['reviews_expressions', ['reference']],
    'one successor per expression' => ['reviews_expressions', ['supersedes_id']],
    'one facet of a kind per expression' => ['reviews_expression_facets', ['expression_id', 'facet']],
    'one vote per voter per expression' => ['reviews_votes', ['expression_id', 'voter_reference']],
    'one report per reporter per expression' => ['reviews_flags', ['expression_id', 'reporter_reference']],
]);

it('keeps no free-text column beside a moderation decision', function () {
    $columns = array_map(
        static fn (array $column): string => $column['name'],
        Schema::getColumns('reviews_moderation_decisions'),
    );

    expect($columns)->toBe([
        'id', 'tenant_id', 'expression_id', 'outcome', 'reason', 'actor_reference', 'occurred_at', 'created_at', 'updated_at',
    ]);
});

/*
 * Every record of something somebody did carries both. A facet is not one of
 * those: it is part of its parent expression and happened at the same moment,
 * so giving it its own `occurred_at` would invite two answers to one question.
 */
it('separates when a thing happened from when the row appeared', function (string $table) {
    $columns = array_map(static fn (array $column): string => $column['name'], Schema::getColumns($table));

    expect($columns)->toContain('occurred_at');
    expect($columns)->toContain('created_at');
})->with(array_values(array_diff(MODULE_TABLES, ['reviews_expression_facets'])));

it('holds no money, no weight and no distance', function (string $table) {
    foreach (Schema::getColumns($table) as $column) {
        expect($column['name'])->not->toContain('minor')
            ->and($column['name'])->not->toContain('currency')
            ->and($column['name'])->not->toContain('price_amount')
            ->and($column['name'])->not->toContain('weight_grams');
    }
})->with(MODULE_TABLES);
