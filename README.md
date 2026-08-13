# Reviews and Ratings

[![Tests](https://github.com/liberusoftware/module-ecommerce-reviews-and-ratings/actions/workflows/tests.yml/badge.svg)](https://github.com/liberusoftware/module-ecommerce-reviews-and-ratings/actions/workflows/tests.yml)

> Reviews and Ratings owns the record that somebody expressed an opinion about a
> product, and the merchant's decision about whether that expression is
> displayed. It owns no product, no order, no shopper identity, and no opinion of
> its own.

This is a **registry of speech**. It did not author its content, it cannot
verify most of it, and it must not silently alter it. Nearly every rule in it is
a rule about custody: who said it, when, whether it is shown, who decided that,
and what a stranger is allowed to see of it.

```bash
composer require liberusoftware/ecommerce-reviews-and-ratings
```

Installing boots nothing. The package ships no `extra.laravel.providers`; the
host's module manager registers `ReviewsAndRatingsServiceProvider` only when
`ecommerce-reviews-and-ratings` is named in `MODULES_ENABLED`.

## Three things called "a review"

Telling them apart is the module.

| | What it is | How it behaves |
| --- | --- | --- |
| **An expression** | One person, one product, one moment: a star, a sentence, or both | Immutable and append-only. An edit is a *new* row that supersedes the old one. |
| **A moderation decision** | The merchant's answer to *should this be shown* | Its own append-only record with an actor, a time, an outcome and a closed-enum reason. Never a flag on the expression. |
| **An aggregate** | A number derived from the first, filtered by the second | Published as `sum`, `count`, `scale` **and the population it summarises**. Never a rounded float. |

## Recording an opinion

```php
use Liberu\Ecommerce\ReviewsAndRatings\Actions\RecordExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Data\FacetSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FacetKind;

$receipt = app(RecordExpression::class)(new ExpressionSubmission(
    tenantId: $tenantId,
    productReference: 'catalogue:sku-77193',   // opaque; never dereferenced
    authorReference: 'shopper:01J8Z…',         // opaque; never dereferenced
    authorDisplayName: 'Sarah T.',             // chosen for this review, stored, never looked up
    score: 4,
    scale: 5,                                  // the scale is part of the record
    body: 'Arrived early, fits as described.',
    facets: [new FacetSubmission(FacetKind::Quality, 5, 5)],
));

$receipt->displayState;   // DisplayState::Pending — nothing is displayed by arriving
$receipt->verification;   // VerificationState::Unknown unless a purchase seam is bound
```

## Reading it back

```php
use Liberu\Ecommerce\ReviewsAndRatings\Queries\DisplayedRatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\PublicReviewListing;

$reviews   = app(PublicReviewListing::class)($tenantId, 'catalogue:sku-77193');
$aggregate = app(DisplayedRatingAggregate::class)($tenantId, 'catalogue:sku-77193', scale: 5);

$aggregate->toArray();
// ['sum' => 374, 'count' => 87, 'scale' => 5, 'population' => 'displayed']
```

`87` is not `count(reviews)` on the page — it is the population the figure
summarises. The consumer rounds; this module never does, because a `4.3`
computed from `4.2999` and a `4.3` computed from `4.3` are different facts and a
rounded average cannot be re-aggregated without drifting.

`PublicReview` has **no author reference, no tenant, no moderation history and
no reason** — not hidden, absent. There is no code path that could publish one.

## The two seams

Both are resolved optionally, and they fail in opposite directions. An unbound
optional seam is safe when its absence removes a **claim**, and unsafe when its
absence removes a **control**.

### `ConfirmsPurchase` — unbound is a deployment

```php
$this->app->bind(ConfirmsPurchase::class, OrdersPurchaseVerifier::class);
```

Leave it unbound and every expression is `VerificationState::Unknown`. That is a
valid deployment: a merchant with no order history wired up, or one that does
not want the badge.

Verification is **tri-state, not a boolean**, because the absence of a badge is
itself a claim shown to a shopper:

| State | Means | A surface should render |
| --- | --- | --- |
| `Verified` | A bound verifier said they bought it | The badge |
| `Unverified` | A bound verifier said they did not | A negative, if you want one |
| `Unknown` | The module could not ask — unbound, errored, or syndicated content | **Nothing at all** |

`Unknown` must not be flattened into `Unverified` anywhere between the domain
and the badge.

### `ScreensContent` — unbound is a 503

```php
$this->app->bind(ScreensContent::class, ProfanityScreener::class);
```

Leave it unbound and any write carrying a body is refused with
`ScreeningUnavailable` (503). A star with no words is still accepted, because
there is nothing to screen. Screening **never rejects**: it routes an expression
to a person's queue with a priority, or escalates it. A machine does not
moderate speech here.

## What it will not do

- Join to a catalogue. A product reference is a string it never dereferences.
- Hold or return a shopper's name, email, address or avatar. It holds the
  display name the author chose for one expression, and nothing else.
- Look up whether a review's author is still a customer, still exists, or ever
  did.
- Rank products by score, or decide what anybody should be shown.
- Retract a review because the purchase was returned. It does not learn about
  returns.
- Know a jurisdiction, a currency, a weight or a distance. There is no money in
  this module.

## Documentation

- [`docs/domain.md`](docs/domain.md) — the complete public surface an adapter
  codes against, and the tables behind it.
- [`docs/adoption.md`](docs/adoption.md) — migrating a host that already has
  `product_reviews` and `product_rating`.
- [`docs/runbook.md`](docs/runbook.md) — operating it: seams, queues, erasure,
  and what each failure means.

## Testing

```bash
composer update
vendor/bin/pest
vendor/bin/phpstan analyse -c vendor/liberusoftware/package-testbench/phpstan.neon -l 1 src
vendor/bin/pint --test --config vendor/liberusoftware/package-testbench/pint.json
```

The suite runs with no other module installed, over product and author
references nothing in the database has ever heard of.

## License

MIT. See [LICENSE.md](LICENSE.md).
