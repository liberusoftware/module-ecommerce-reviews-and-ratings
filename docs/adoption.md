# Adoption

How to move a host that already has reviews onto this module.

## The short version

**Nothing is adopted.** This module invents all five of its tables. The host's
`product_reviews` and `product_rating` are not renamed, not extended and not
read — they are migrated out of and then dropped.

Under the fleet's table-naming rule a table that existed in the host before the
package keeps its bare name, and the package guards its migration on
`Schema::hasTable()`. Adopting is a **choice**, and it is the wrong one here.

## Why not adopt

Each of these is a reason on its own; together they leave nothing worth keeping.

1. **A public route used to serialise every reviewer's postal address, and the
   fix is a whitelist somebody has to keep remembering.**
   `GET /product/{product}/reviews` is unauthenticated (`routes/web.php:177`,
   outside the `auth` group) and `Customer` still declares no `$hidden` while
   carrying `email`, `phone_number`, `address`, `city`, `state`, `postal_code`.
   `ReviewController::show()` has since been repaired by hand — it now selects
   `customer:id,first_name` and maps to an explicit field list — so the leak is
   closed today. It is closed by a whitelist inside one controller method, which
   is exactly the class of fix that reopens the next time somebody adds a field.
   This module's public projection has no field an identity could go in.
2. **One fact in two columns, and two averages that answer differently.**
   `RatingController::store()` writes the same number to `rating` and to
   `overall_rating`. `Product::getAverageRating()` then averages `rating`, while
   `RatingController::calculateAverageRating()` averages the four detail columns
   and composites the non-null ones. Same question, two answers, agreeing only
   because one controller happens to write both columns.
3. **A five-star rating computes as 1.25 stars.** `ProductRating::getAverageRating()`
   sums four columns and divides by four; three of them are nullable and `null`
   is `0` in PHP arithmetic. The same computation exists elsewhere done
   correctly. There is no test.
4. **Moderation is bypassable.** `approved` lives on `product_reviews` and
   ratings got nothing, while the displayed average is over every rating
   regardless of moderation. Post a one-star with abusive text: the text is held,
   the star publishes immediately.
5. **No unique index behind the duplicate check.** There is no unique index on
   `(customer_id, product_id)` on either table. `ReviewController::store()` does
   `->exists()` then `create()` for the review, and `RatingController::store()`
   does the same for the rating; only the rating written from
   `ReviewController` goes through `firstOrCreate`, and `firstOrCreate` without
   a unique index is still two statements. Two concurrent requests both insert.
6. **A review and its rating are two rows joined by coincidence** — same customer,
   same product, no key. Delete the review and the star survives, uncounted.
7. **`is_verified_purchase` is written by nothing.** It is `false` on every row
   that has ever existed, which means the host currently tells shoppers "not a
   verified purchase" about people who did buy the thing.
8. **Helpfulness is an unlocked counter that records nobody**, so the sort order
   of a product page is purchasable.
9. **Two naming conventions for one concept** — `product_reviews` plural,
   `product_rating` singular.
10. **Approval is gated twice, differently** — a role check over HTTP, a policy in
    the panel. A grant that works in one does nothing in the other.

A schema this module could inherit would have to keep the mutable `approved`
flag, the duplicated rating columns and the unkeyed pair. All three are the
things it exists to not have.

## Migrating

### 1. Enable the module

```
MODULES_ENABLED=…,ecommerce-reviews-and-ratings
```

Run `php artisan migrate`. Five `reviews_*` tables appear. Nothing else changes;
the host's tables are untouched.

### 2. Bind the seams

`ScreensContent` is **required** — any write carrying a body refuses with 503
until it is bound. The host has no screening today, so this is new work and it
is the point: the module will not accept free text nobody looked at.

`ConfirmsPurchase` is optional. Binding it against the orders module is what
finally makes `is_verified_purchase` mean something. Leaving it unbound is
honest: everything reads `Unknown`, and a surface renders no badge at all rather
than a false negative.

### 3. Backfill

Write a one-off command in the host. For each `product_reviews` row:

- Record an `ExpressionSubmission` with `source: ExpressionSource::Imported` and
  a `sourceReference` of `"product_reviews:{id}"`, `occurredAt` set to the
  original `created_at`, and `authorDisplayName` taken from whatever the host is
  willing to show — the module will never look one up again.
- Take the score from `product_rating.overall_rating` for the same
  `(customer_id, product_id)` if there is one, and put the breakdown columns in
  as `FacetSubmission`s, **skipping nulls**. Do not average them.
- Then append a `ModerationDecision`: `Approved` / `Compliant` for a row where
  `approved` was true, `Withheld` / `OffTopic` otherwise, with an actor of
  `"import:legacy"`. The module refuses to invent a decision on your behalf, and
  an unmoderated import would silently unpublish every existing review.

Imported expressions are permanently `VerificationState::Unknown`. That is
correct even where the host's row claimed a purchase: the flag was never
written, so there is nothing to trust.

Rows where two `product_rating` rows exist for one `(customer, product)` — which
nothing prevents today — need a decision from you. Take the newest.

Helpfulness counters cannot be migrated: nothing records who voted, so there is
nothing to write one vote row from. Counts start at zero. This is a real loss and
the alternative is fabricating votes.

### 4. Cut over the read paths

Replace `Product::getAverageRating()` with `DisplayedRatingAggregate` and render
`sum / count` yourself. Replace the unauthenticated JSON route with
`PublicReviewListing`, which cannot serialise a customer because `PublicReview`
has nowhere to put one.

### 5. Delete the host's tables and code

Once the backfill reconciles — compare `RecordedRatingTotal` against a raw
`count(*)` on `product_rating` — drop `product_reviews` and `product_rating` and
delete `ReviewController`, `RatingController`, `ProductReview`, `ProductRating`,
`ReviewRequest` and `ProductReviewPolicy`. Wire `GdprExportService` to
`AuthorExport` and `GdprErasureService` to `EraseAuthor`.

Note that erasure changes meaning: the host deletes review rows, this module
redacts them. That is deliberate. Deleting an approved five-star row silently
moves a figure you have already published, and a moderation record naming a
decision about a row that no longer exists is an audit trail with a hole in it.
The erased row keeps its score and its history, leaves the public listing, and
stays in the aggregate.

## What you do not get in 0.1.0

- **Syndicating out.** `source` records where an expression came from; publishing
  this merchant's reviews to another platform is not implemented.
- **Re-verification.** Verification is decided once, at write time, and inherited
  across revisions. Binding `ConfirmsPurchase` after a backfill does not re-badge
  existing rows.
- **Q&A.** Questions and answers are named in the epic and are not in this
  release. They are a different grain — a question is not about one person's
  experience — and folding them into `reviews_expressions` to hit a list would
  have made `product_reference` and `author_reference` mean two things each.
