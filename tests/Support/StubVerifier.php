<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Tests\Support;

use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ConfirmsPurchase;
use Liberu\Ecommerce\ReviewsAndRatings\Data\PurchaseConfirmation;
use RuntimeException;

/** A purchase verifier the tests drive, including into failure. */
final class StubVerifier implements ConfirmsPurchase
{
    /** @var list<array{author: string, product: string}> */
    public array $asked = [];

    public function __construct(
        private readonly ?PurchaseConfirmation $confirmation = null,
        private readonly bool $throws = false,
    ) {}

    public function confirm(string $authorReference, string $productReference): ?PurchaseConfirmation
    {
        $this->asked[] = ['author' => $authorReference, 'product' => $productReference];

        if ($this->throws) {
            throw new RuntimeException('the order service is down');
        }

        return $this->confirmation;
    }
}
