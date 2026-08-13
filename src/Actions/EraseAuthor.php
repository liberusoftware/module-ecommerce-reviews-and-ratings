<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ErasureReport;
use Liberu\Ecommerce\ReviewsAndRatings\Events\AuthorErased;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Flag;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Vote;

/**
 * Remove the content, keep the shape.
 *
 * The host deletes the row, which is defensible for an application and wrong
 * for a module: deleting an approved five-star expression silently moves a
 * figure that has already been published, and a moderation record naming a
 * decision about a row that no longer exists is an audit trail with a hole in
 * it. So the body, the display name and the author reference go; the score, the
 * scale, the timestamps and every decision stay.
 *
 * The erased expression leaves the public listing and stays in the aggregate.
 * Those two are the pair a naive implementation gets wrong in opposite
 * directions, which is why they are proved together.
 */
final readonly class EraseAuthor
{
    public function __construct(private Dispatcher $events) {}

    public function __invoke(string $tenantId, string $authorReference): ErasureReport
    {
        $report = DB::transaction(function () use ($tenantId, $authorReference): ErasureReport {
            $now = CarbonImmutable::now();
            $expressions = 0;

            Expression::query()
                ->where('tenant_id', $tenantId)
                ->where('author_reference', $authorReference)
                ->get()
                ->each(function (Expression $expression) use ($now, &$expressions): void {
                    $expression->redacted_at ??= $now;
                    $expression->body = null;
                    $expression->author_display_name = null;
                    $expression->author_reference = null;
                    $expression->save();
                    $expressions++;
                });

            // Votes and flags keep their row and lose their voter: a null does
            // not collide in a unique index, so the totals a shopper already saw
            // do not move because somebody exercised their rights.
            $votes = Vote::query()
                ->where('tenant_id', $tenantId)
                ->where('voter_reference', $authorReference)
                ->update(['voter_reference' => null]);

            $flags = Flag::query()
                ->where('tenant_id', $tenantId)
                ->where('reporter_reference', $authorReference)
                ->update(['reporter_reference' => null]);

            return new ErasureReport($expressions, $votes, $flags);
        });

        $this->events->dispatch(new AuthorErased($tenantId, $report));

        return $report;
    }
}
