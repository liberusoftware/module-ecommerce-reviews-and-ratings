<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Tests\Support;

use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ScreensContent;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;
use RuntimeException;

/** A screener the tests drive: it answers what it was told to, and remembers what it saw. */
final class RecordingScreener implements ScreensContent
{
    /** @var list<array{body: string, locale: string}> */
    public array $seen = [];

    public function __construct(
        private readonly ?ScreeningOutcome $outcome = null,
        private readonly bool $throws = false,
    ) {}

    public function screen(string $body, string $locale): ScreeningOutcome
    {
        $this->seen[] = ['body' => $body, 'locale' => $locale];

        if ($this->throws) {
            throw new RuntimeException('the screening vendor is down');
        }

        return $this->outcome ?? ScreeningOutcome::clean();
    }
}
