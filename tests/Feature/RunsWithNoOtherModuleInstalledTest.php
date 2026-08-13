<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\CastVote;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\DisplayedRatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\PublicReviewListing;

/*
 * The boundary, as an executable claim.
 *
 * Nothing in this database has ever heard of the product or the author these
 * references name. There is no catalogue table, no order, no customer, and no
 * sibling module installed — and the whole module works anyway, because a
 * product reference and an author reference are opaque strings it never
 * dereferences.
 */
it('runs the whole module over references nothing in the database has heard of', function () {
    bindScreener();

    $product = 'catalogue:'.bin2hex(random_bytes(8));
    $author = 'shopper:'.bin2hex(random_bytes(8));

    $receipt = submit(submission([
        'productReference' => $product,
        'authorReference' => $author,
        'body' => 'Nothing here can be joined to anything.',
    ]));
    approve($receipt->reference);

    $edit = revise($receipt->reference, new ExpressionRevision(score: 5, scale: 5, body: 'Still nothing.'));
    approve($edit->reference);

    reply($edit->reference);
    app(CastVote::class)(TENANT, $edit->reference, 'shopper:zed', VoteDirection::Helpful);

    expect(app(PublicReviewListing::class)(TENANT, $product))->toHaveCount(1)
        ->and(app(DisplayedRatingAggregate::class)(TENANT, $product)->sum)->toBe(5);
});

it('installs only its own tables', function () {
    $tables = array_map(
        static fn (array $table): string => $table['name'],
        Schema::getTables(),
    );

    $mine = array_values(array_filter($tables, static fn (string $table): bool => str_starts_with($table, 'reviews_')));

    sort($mine);

    expect($mine)->toBe([
        'reviews_expression_facets',
        'reviews_expressions',
        'reviews_flags',
        'reviews_moderation_decisions',
        'reviews_votes',
    ]);

    foreach ($tables as $table) {
        expect($table)->not->toBe('product_reviews')
            ->and($table)->not->toBe('product_rating')
            ->and($table)->not->toBe('products')
            ->and($table)->not->toBe('customers');
    }
});
