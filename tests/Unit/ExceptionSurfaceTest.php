<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\DuplicateExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionAlreadySuperseded;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionIsImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionNotFound;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionRedacted;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\InvalidExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ReviewsAndRatingsException;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ScreeningUnavailable;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\SelfVoteRefused;

/*
 * Pest's `toThrow()`, handed a class-string that does not autoload, silently
 * degrades to a message-substring check — so a test naming a mistyped exception
 * passes while asserting nothing at all about the type. Every class the suite
 * names is loaded here first, once, where a typo is a failure rather than a
 * green test that checks nothing.
 */
it('ships every exception the suite and the adapters name', function (string $class) {
    expect(class_exists($class))->toBeTrue()
        ->and(is_subclass_of($class, ReviewsAndRatingsException::class))->toBeTrue();
})->with([
    ScreeningUnavailable::class,
    ExpressionNotFound::class,
    ExpressionAlreadySuperseded::class,
    ExpressionIsImmutable::class,
    DuplicateExpression::class,
    ExpressionRedacted::class,
    SelfVoteRefused::class,
    InvalidExpression::class,
]);

it('maps every refusal to the status an adapter must answer with', function (ReviewsAndRatingsException $exception, int $status, string $code) {
    expect($exception->status())->toBe($status)
        ->and($exception->errorCode)->toBe($code)
        ->and($exception->getMessage())->not->toBe('');
})->with([
    'unbound screener' => [fn () => ScreeningUnavailable::unbound(), 503, 'screening_unavailable'],
    'failed screener' => [fn () => ScreeningUnavailable::failed('vendor down'), 503, 'screening_failed'],
    'unknown expression' => [fn () => ExpressionNotFound::withReference('x'), 404, 'expression_not_found'],
    'stale reference' => [fn () => ExpressionAlreadySuperseded::withReference('x'), 409, 'expression_already_superseded'],
    'rewritten history' => [fn () => ExpressionIsImmutable::column('x', 'score'), 409, 'expression_is_immutable'],
    'second live expression' => [fn () => DuplicateExpression::onProduct('p'), 409, 'duplicate_expression'],
    'second live reply' => [fn () => DuplicateExpression::onReply('x'), 409, 'duplicate_reply'],
    'erased expression' => [fn () => ExpressionRedacted::withReference('x'), 410, 'expression_redacted'],
    'voting for yourself' => [fn () => SelfVoteRefused::withReference('x'), 403, 'self_vote_refused'],
    'unusable submission' => [fn () => InvalidExpression::because('nope'), 422, 'invalid_expression'],
]);

/*
 * A readonly promoted property named `$code` on an Exception subclass is a fatal
 * at class load, not a test failure — and `code` is exactly the name an
 * exception carrying an API error code reaches for.
 */
it('carries its error code under a name that is not already taken on Exception', function () {
    $reflection = new ReflectionClass(ReviewsAndRatingsException::class);

    expect($reflection->hasProperty('errorCode'))->toBeTrue()
        ->and($reflection->getProperty('errorCode')->isReadOnly())->toBeTrue();

    $declaredHere = array_filter(
        $reflection->getProperties(),
        static fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === ReviewsAndRatingsException::class,
    );

    expect($declaredHere)->toHaveCount(1);

    foreach ($declaredHere as $property) {
        expect($property->getName())->not->toBe('code');
    }
});

it('keeps a permanent conflict and a temporary one apart by class, not by message', function () {
    expect(DuplicateExpression::onProduct('p')->status())->toBe(409)
        ->and(ScreeningUnavailable::unbound()->status())->toBe(503)
        ->and(DuplicateExpression::class)->not->toBe(ScreeningUnavailable::class);
});
