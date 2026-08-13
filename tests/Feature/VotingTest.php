<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\CastVote;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;
use Liberu\Ecommerce\ReviewsAndRatings\Events\VoteCast;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionNotFound;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\SelfVoteRefused;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Vote;

beforeEach(function () {
    bindScreener();
    $this->receipt = submit(submission(['body' => 'a sentence']));
});

function vote(string $reference, string $voter, VoteDirection $direction = VoteDirection::Helpful): object
{
    return app(CastVote::class)(TENANT, $reference, $voter, $direction);
}

it('counts a vote', function () {
    $tally = vote($this->receipt->reference, 'shopper:zed');

    expect($tally->helpful)->toBe(1)->and($tally->unhelpful)->toBe(0);
});

it('is idempotent rather than additive when the same voter votes again', function () {
    vote($this->receipt->reference, 'shopper:zed');
    vote($this->receipt->reference, 'shopper:zed');
    $tally = vote($this->receipt->reference, 'shopper:zed');

    expect($tally->helpful)->toBe(1)
        ->and(Vote::query()->count())->toBe(1);
});

it('lets a voter change their mind without inventing a second vote', function () {
    vote($this->receipt->reference, 'shopper:zed', VoteDirection::Helpful);
    $tally = vote($this->receipt->reference, 'shopper:zed', VoteDirection::Unhelpful);

    expect($tally->helpful)->toBe(0)
        ->and($tally->unhelpful)->toBe(1)
        ->and(Vote::query()->count())->toBe(1);
});

it('records who voted, so a total can be audited rather than trusted', function () {
    vote($this->receipt->reference, 'shopper:zed');

    expect(Vote::query()->sole()->voter_reference)->toBe('shopper:zed');
});

it('refuses an author voting on their own expression rather than quietly dropping it', function () {
    expect(fn () => vote($this->receipt->reference, AUTHOR))->toThrow(SelfVoteRefused::class);

    expect(Vote::query()->count())->toBe(0);
});

it('counts several voters separately', function () {
    vote($this->receipt->reference, 'shopper:one');
    vote($this->receipt->reference, 'shopper:two', VoteDirection::Unhelpful);
    $tally = vote($this->receipt->reference, 'shopper:three');

    expect($tally->helpful)->toBe(2)->and($tally->unhelpful)->toBe(1);
});

it('announces a vote with the tally that followed it', function () {
    Event::fake([VoteCast::class]);

    vote($this->receipt->reference, 'shopper:zed');

    Event::assertDispatched(VoteCast::class, fn (VoteCast $event): bool => $event->tally->helpful === 1);
});

it('refuses a vote on an expression nobody has heard of', function () {
    expect(fn () => vote('00000000000000000000000000000000', 'shopper:zed'))->toThrow(ExpressionNotFound::class);
});

it('refuses a vote from another tenant', function () {
    expect(fn () => app(CastVote::class)('tenant-beta', $this->receipt->reference, 'shopper:zed', VoteDirection::Helpful))
        ->toThrow(ExpressionNotFound::class);
});
