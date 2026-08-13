# Runbook

Operating `ecommerce-reviews-and-ratings`.

## Health, in one query

```php
app(Liberu\Ecommerce\ReviewsAndRatings\Queries\ModerationQueue::class)($tenantId);
```

An empty pending queue and a bound screener is the healthy state. A growing
pending queue is a staffing problem, not a software one — nothing in this module
approves anything on its own.

## The two seams

| | `ConfirmsPurchase` | `ScreensContent` |
| --- | --- | --- |
| Unbound | Fine. Everything is `Unknown`. | **Writes with a body fail 503.** |
| Throws | `Unknown` + `PurchaseVerificationDegraded`. Write succeeds. | 503. Write refused. |
| Answers | `Verified` / `Unverified` | `Queue` at a priority, or `Escalate` |

An unbound optional seam is safe when its absence removes a claim, and unsafe
when its absence removes a control. That is the whole distinction.

### Reviews suddenly returning 503

`ScreensContent` is unbound or failing. Check the binding first — this is what an
unbound seam looks like, and it looks the same as an outage. Star-only ratings
keep working throughout, so a 503 rate that is high but not total is normal
during a screening outage.

Do **not** "fix" it by binding a no-op screener that returns `clean()`. That
publishes unscreened speech on a merchant's page and looks healthy. If screening
is going to be down for a while, the correct posture is refusing reviews.

### Badges disappearing

Watch `PurchaseVerificationDegraded`. A burst means the order service is down and
new expressions are being written `Unknown`. Reviews are still accepted — refusing
speech because a service is down is worse than publishing it unbadged.

Verification is decided **once**, at write time. Expressions written during the
outage stay `Unknown` after it ends. There is no re-verification in `0.1.0`.

## Screening escalations

An `Escalate` disposition appends a moderation decision with
`actor_reference: "system:screening"` and `reason: MachineEscalation`. That is a
queue position, not a judgement — the expression shows as `Escalated` and is not
displayed, and a person still has to decide. Escalations sort to the top of the
queue by `screening_weight`.

If the screener starts escalating everything, the queue fills with `Escalated`
rather than `Pending`: check `ModerationQueue($tenantId, DisplayState::Escalated)`.

## Moderation

Every decision names an actor and takes a reason from a closed enum. There is no
free-text field, deliberately — a moderation reason sits beside a person's name
by construction, and a free-text box next to an event log is where personal data
gets typed.

To reverse a decision, record the opposite one. Both stay. A moderator looking at
a `StaffReview` sees the whole history and, importantly, `editedAfterApproval` —
the author changing their review after you approved it is the single thing a
moderation queue must not hide.

## Erasure

```php
app(Liberu\Ecommerce\ReviewsAndRatings\Actions\EraseAuthor::class)($tenantId, $authorReference);
```

Redacts the body, the display name and the author reference on **every** revision
in every chain the author wrote; nulls the author out of their votes and reports
while keeping the rows. Keeps score, scale, timestamps and the full moderation
history.

Afterwards:

- The expression is gone from `PublicReviewListing`.
- It is **still counted** in `DisplayedRatingAggregate`. This is correct: a figure
  you already published does not move because somebody exercised their rights.
- `AuthorExport` returns nothing for that reference — there is nothing left keyed
  to it.
- Any further write against the expression fails **410**.

Erasure is not reversible and there is no undo. Run the export first if you need
a record.

## Subject access

```php
app(Liberu\Ecommerce\ReviewsAndRatings\Queries\AuthorExport::class)($tenantId, $authorReference);
```

Returns every revision, including expressions that were withheld or rejected and
the reasons why. A review held pending is data held about a person that no page
shows them; an export that omitted it would be incomplete.

## Aggregates

Nothing is cached. Every figure is computed from rows, and it is reproducible:
sum the scores of live, unsuperseded expressions on the requested scale whose
latest moderation decision is `approved`, and you will get the published number
to the integer.

If you add a cache, invalidate on `ModerationDecided`, `ExpressionRecorded` and
`ExpressionRevised`. `ModerationDecided` is the one people forget, and forgetting
it means a withheld review stays in the average.

Always render `sum / count` yourself and keep the population label. `4.3 stars`
is not a fact; `4.3 from 87 displayed ratings` is.

## Things that will bite

- **Never mass-update these tables.** `Expression::query()->update(...)` does not
  fire model events, so the append-only guard and the `live_key` recomputation
  are both skipped. The unique index is the only backstop that survives it.
- **Scale is a parameter on every aggregate.** Ask for scale 5 and you get the
  five-point ratings only. A store that switched from five-point to ten-point has
  two populations and no honest way to add them.
- **`Unknown` is not `Unverified`.** If a surface renders "not a verified
  purchase" for `Unknown`, it is telling shoppers something the module never
  checked. Render nothing.
- **A vote is one row per voter.** If a total looks low next to page views,
  that is the point — the old counter could be incremented in a loop.
