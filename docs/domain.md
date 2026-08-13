# The domain

Everything a presentation package codes against, and the tables behind it. All
of it lives under `Liberu\Ecommerce\ReviewsAndRatings\`.

An adapter should import **contracts, actions, queries, data and enums**. It
should not import `Models\` — the `-api` boundary rule forbids it, and no action
or query returns an Eloquent model.

---

## 1. Contracts (the seams)

| Interface | Method | Unbound |
| --- | --- | --- |
| `Contracts\ConfirmsPurchase` | `confirm(string $authorReference, string $productReference): ?PurchaseConfirmation` | **Valid deployment.** Everything is `VerificationState::Unknown`. |
| `Contracts\ScreensContent` | `screen(string $body, string $locale): ScreeningOutcome` | **Configuration error.** Any write carrying a body throws `ScreeningUnavailable` (503). |

`confirm()` returning `null` means "cannot answer" and yields `Unknown`.
Throwing also yields `Unknown`, raises `PurchaseVerificationDegraded`, and never
blocks the write.

Both are resolved with a `= null` default inside the module's own wrappers. Do
not reproduce that resolution yourself: inject the actions.

---

## 2. Actions (the write path)

Every action is `final readonly` and invokable. Resolve from the container.

| Action | Signature | Returns |
| --- | --- | --- |
| `Actions\RecordExpression` | `(ExpressionSubmission $submission)` | `ExpressionReceipt` |
| `Actions\ReviseExpression` | `(string $tenantId, string $reference, ExpressionRevision $revision)` | `ExpressionReceipt` |
| `Actions\RecordMerchantReply` | `(ReplySubmission $submission)` | `ExpressionReceipt` |
| `Actions\DecideModeration` | `(string $tenantId, string $reference, ModerationOutcome $outcome, ModerationReason $reason, string $actorReference, ?CarbonImmutable $occurredAt = null)` | `ModerationRecord` |
| `Actions\CastVote` | `(string $tenantId, string $reference, string $voterReference, VoteDirection $direction, ?CarbonImmutable $occurredAt = null)` | `VoteTally` |
| `Actions\FlagExpression` | `(string $tenantId, string $reference, string $reporterReference, FlagReason $reason, ?CarbonImmutable $occurredAt = null)` | `int` (reports on that expression) |
| `Actions\EraseAuthor` | `(string $tenantId, string $authorReference)` | `ErasureReport` |

Notes that are not obvious from the signatures:

- `ReviseExpression` works on a merchant reply as well as a shopper review. A
  revision is a whole new expression, not a patch: anything it does not restate
  is dropped, except the product, the author, the display name, the locale, the
  source and the verification, which are carried across. A revision starts
  `Pending` however the original was decided.
- `RecordMerchantReply` refuses a reply to a reply, to a superseded expression,
  and to a redacted one. At most one live reply per expression.
- `DecideModeration` appends. There is no "un-decide"; record the opposite
  outcome and the history keeps both.
- `CastVote` is idempotent per voter, and updates in place when a voter changes
  direction.
- `FlagExpression` is idempotent per reporter; a second report updates the
  reason. Reporting hides nothing.

---

## 3. Queries (the read path)

Every query is `final` and invokable.

| Query | Signature | Returns |
| --- | --- | --- |
| `Queries\PublicReviewListing` | `(string $tenantId, string $productReference, int $limit = 20, int $offset = 0)` | `list<PublicReview>` |
| `Queries\DisplayedRatingAggregate` | `(string $tenantId, string $productReference, int $scale = 5)` | `RatingAggregate` (`population: displayed`) |
| `Queries\RecordedRatingTotal` | `(string $tenantId, string $productReference, int $scale = 5)` | `RatingAggregate` (`population: recorded`) — **staff only** |
| `Queries\ModerationQueue` | `(string $tenantId, ?DisplayState $state = DisplayState::Pending, int $limit = 50, int $offset = 0)` | `list<StaffReview>` |
| `Queries\StaffExpression` | `(string $tenantId, string $reference)` | `StaffReview` |
| `Queries\FlagQueue` | `(string $tenantId, int $limit = 50, int $offset = 0)` | `list<FlagRecord>` |
| `Queries\AuthorExport` | `(string $tenantId, string $authorReference)` | `list<AuthorExportRecord>` |

- `PublicReviewListing` returns live, unredacted expressions whose latest
  decision is `approved`, newest first, with an approved live reply attached if
  there is one. **This is the only query safe to expose without authentication.**
- `DisplayedRatingAggregate` is the one aggregate a shopper may be shown. Scale
  is a parameter, never guessed: a four out of five and a four out of ten are not
  summable.
- `ModerationQueue` filters by derived display state — pass `null` for every live
  expression — and orders by screening urgency, then by when the thing happened.
- `AuthorExport` returns every revision in every chain, including states and
  reasons no page shows the author. It returns nothing for an erased author.

---

## 4. Data (DTOs and read models)

### Submitted

| Class | Fields |
| --- | --- |
| `Data\ExpressionSubmission` | `tenantId`, `productReference`, `authorReference`, `authorDisplayName`, `?score`, `?scale`, `?body`, `locale = 'en'`, `source = FirstParty`, `?sourceReference`, `incentive = None`, `facets = []`, `?occurredAt` |
| `Data\ExpressionRevision` | `?score`, `?scale`, `?body`, `?incentive`, `facets = []`, `?occurredAt` |
| `Data\ReplySubmission` | `tenantId`, `parentReference`, `authorReference`, `authorDisplayName`, `body`, `locale = 'en'`, `?occurredAt` |
| `Data\FacetSubmission` | `kind: FacetKind`, `score: int`, `scale: int` |

### Answered by a seam

| Class | Fields |
| --- | --- |
| `Data\PurchaseConfirmation` | `purchased: bool`, `?confirmedAt: CarbonImmutable` |
| `Data\ScreeningOutcome` | `disposition: ScreeningDisposition`, `priority: ScreeningPriority`, `signals: list<ModerationReason>`; static `clean()`, `escalate(array $signals, ScreeningPriority $priority = Urgent)` |

### Returned

| Class | Fields |
| --- | --- |
| `Data\ExpressionReceipt` | `reference`, `displayState`, `verification`, `screeningPriority`, `?supersedesReference`, `occurredAt`, `recordedAt`; `toArray()` |
| `Data\PublicReview` | `reference`, `?score`, `?scale`, `?body`, `authorDisplayName`, `verification`, `source`, `incentive`, `votes: VoteTally`, `facets: list<FacetScore>`, `?reply: PublicReply`, `edited: bool`, `occurredAt`, `recordedAt`; `toArray()` |
| `Data\PublicReply` | `reference`, `body`, `authorDisplayName`, `occurredAt`; `toArray()` |
| `Data\StaffReview` | `reference`, `tenantId`, `kind`, `?productReference`, `?parentReference`, `?authorReference`, `?authorDisplayName`, `?score`, `?scale`, `?body`, `locale`, `source`, `?sourceReference`, `incentive`, `verification`, `displayState`, `screeningPriority`, `screeningSignals: list<ModerationReason>`, `facets`, `votes`, `flagCount`, `moderationHistory: list<ModerationRecord>`, `?supersedesReference`, `editedAfterApproval: bool`, `isSuperseded: bool`, `isRedacted: bool`, `occurredAt`, `recordedAt` |
| `Data\ModerationRecord` | `expressionReference`, `outcome`, `reason`, `actorReference`, `occurredAt`, `recordedAt`; `toArray()` |
| `Data\RatingAggregate` | `sum: int`, `count: int`, `scale: int`, `population: AggregatePopulation`; `toArray()` |
| `Data\VoteTally` | `helpful: int`, `unhelpful: int`; `toArray()` |
| `Data\FacetScore` | `kind`, `score`, `scale`; `toArray()` |
| `Data\FlagRecord` | `expressionReference`, `reason`, `?reporterReference`, `occurredAt` |
| `Data\AuthorExportRecord` | `reference`, `?productReference`, `?score`, `?scale`, `?body`, `?authorDisplayName`, `locale`, `source`, `incentive`, `verification`, `displayState`, `facets`, `moderationHistory`, `isSuperseded`, `isRedacted`, `occurredAt`, `recordedAt` |
| `Data\ErasureReport` | `expressionsRedacted: int`, `votesRedacted: int`, `flagsRedacted: int` |

**`PublicReview` and `StaffReview` are two schemas written independently, not one
schema with fields blanked out.** The public one has no field that could carry an
author reference, a tenant, or a moderation reason. Never build a public response
from a `StaffReview`.

### Limits

`Support\SubmissionRules` publishes the numbers so no surface invents its own:
`MAX_BODY_LENGTH = 5000`, `MAX_DISPLAY_NAME_LENGTH = 80`, `MIN_SCALE = 2`,
`MAX_SCALE = 100`. A form that validates against anything else will disagree
with the domain.

---

## 5. Enums

| Enum | Cases |
| --- | --- |
| `Enums\ExpressionKind` | `ShopperReview`, `MerchantReply` |
| `Enums\ExpressionSource` | `FirstParty`, `Syndicated`, `Imported` — `isVerifiable()` is true only for `FirstParty` |
| `Enums\VerificationState` | `Verified`, `Unverified`, `Unknown` |
| `Enums\DisplayState` | `Pending`, `Approved`, `Rejected`, `Withheld`, `Escalated` — `isDisplayed()` is true only for `Approved` |
| `Enums\ModerationOutcome` | `Approved`, `Rejected`, `Withheld`, `Escalated` — `displayState()` |
| `Enums\ModerationReason` | `Compliant`, `OffTopic`, `Profanity`, `Spam`, `PersonalInformation`, `UndisclosedIncentive`, `Counterfeit`, `HarassmentOrHate`, `IllegalContent`, `DuplicateSubmission`, `WrongProduct`, `MachineEscalation`, `AuthorErasure` |
| `Enums\FlagReason` | `OffTopic`, `Profanity`, `Spam`, `PersonalInformation`, `Counterfeit`, `HarassmentOrHate`, `IllegalContent`, `WrongProduct` |
| `Enums\VoteDirection` | `Helpful`, `Unhelpful` |
| `Enums\FacetKind` | `Quality`, `Value`, `Price`, `Accuracy`, `Delivery` |
| `Enums\IncentiveDisclosure` | `None`, `FreeProduct`, `Discount`, `SweepstakesEntry`, `Payment`, `LoyaltyPoints` — `isDisclosed()` |
| `Enums\ScreeningDisposition` | `Queue`, `Escalate` — there is deliberately no `Reject` |
| `Enums\ScreeningPriority` | `Routine`, `Elevated`, `Urgent` — `weight()` |
| `Enums\AggregatePopulation` | `Displayed`, `Recorded` |

Every reason field anywhere in this module is one of these enums. There is no
free text outside the review body and the reply body.

---

## 6. Events

All `final readonly`, dispatched through `Illuminate\Contracts\Events\Dispatcher`.

| Event | Fields |
| --- | --- |
| `Events\ExpressionRecorded` | `tenantId`, `reference`, `kind`, `?productReference`, `verification` |
| `Events\ExpressionRevised` | `tenantId`, `reference`, `supersededReference`, `supersededWasDisplayed` |
| `Events\MerchantReplied` | `tenantId`, `reference`, `parentReference` |
| `Events\ModerationDecided` | `tenantId`, `record: ModerationRecord` |
| `Events\VoteCast` | `tenantId`, `expressionReference`, `direction`, `tally` |
| `Events\ExpressionFlagged` | `tenantId`, `expressionReference`, `reason`, `flagCount` |
| `Events\AuthorErased` | `tenantId`, `report: ErasureReport` |
| `Events\PurchaseVerificationDegraded` | `tenantId`, `productReference`, `reason` |

`ModerationDecided` is the only way a displayed aggregate can change without a
new expression, so a cache of published figures listens there and to
`ExpressionRecorded` / `ExpressionRevised`.

---

## 7. Exceptions and their HTTP meanings

All extend `Exceptions\ReviewsAndRatingsException`, which carries a readonly
`$errorCode` — **not** `$code`, which is not a name an `Exception` subclass can
redeclare — and an abstract `status()`.

| Exception | Status | `errorCode` | Raised when |
| --- | --- | --- | --- |
| `Exceptions\ScreeningUnavailable` | **503** | `screening_unavailable` / `screening_failed` | `ScreensContent` is unbound, or it threw. Retryable. |
| `Exceptions\ExpressionNotFound` | **404** | `expression_not_found` | No expression with that reference **in that tenant**. |
| `Exceptions\ExpressionAlreadySuperseded` | **409** | `expression_already_superseded` | The caller holds a stale reference; re-read the chain. Permanent. |
| `Exceptions\ExpressionIsImmutable` | **409** | `expression_is_immutable` | Something tried to rewrite what was said. Permanent. |
| `Exceptions\DuplicateExpression` | **409** | `duplicate_expression` / `duplicate_reply` | A live expression already exists for that author and product, or a live reply for that expression. Permanent — raised from a unique index, not a read. |
| `Exceptions\ExpressionRedacted` | **410** | `expression_redacted` | Writing against an erased expression. |
| `Exceptions\SelfVoteRefused` | **403** | `self_vote_refused` | An author voted on their own expression. |
| `Exceptions\InvalidExpression` | **422** | `invalid_expression` | The submission cannot become a record as written. |

There is no 423 in this module: nothing here takes a transient in-flight claim
that another caller could retry into. Every conflict listed above is permanent
and a `Retry-After` on any of them would be a lie.

---

## 8. Tables

Five, all prefixed `reviews_`, all invented by this package. None is adopted
from a host: the host's `product_reviews` / `product_rating` pair is not
adoptable (see `docs/adoption.md`). Every table carries a non-nullable, indexed
`tenant_id`, and every table recording an act carries `occurred_at` distinct
from `created_at`.

### `reviews_expressions`

The historical fact. Shopper reviews and merchant replies both live here.

| Column | Notes |
| --- | --- |
| `id` | |
| `reference` | `char(32)`, **unique**. The opaque public handle. Never the row number. |
| `tenant_id` | indexed |
| `kind` | `shopper_review` \| `merchant_reply` |
| `product_reference` | nullable — set on a review, null on a reply. Opaque; no foreign key anywhere. |
| `parent_expression_id` | nullable FK → `reviews_expressions` — set on a reply |
| `supersedes_id` | nullable FK → `reviews_expressions`, **unique**: one successor per expression |
| `author_reference` | nullable (erasure). Opaque. |
| `author_display_name` | nullable (erasure). Denormalised at write time; never resolved. |
| `score` / `scale` | unsigned smallints, both null or both set |
| `body` | `text`, nullable |
| `locale`, `source`, `source_reference`, `incentive` | |
| `verification`, `verified_at` | tri-state, decided once at write |
| `screening_priority`, `screening_weight`, `screening_signals` | the weight is the sortable form of the priority; the signals are enum values |
| `live_key` | `char(64)`, nullable, **unique** — see below |
| `occurred_at`, `superseded_at`, `redacted_at`, `created_at`, `updated_at` | |

Indexes: `(tenant_id, product_reference, kind)`, `(tenant_id, author_reference)`,
`(tenant_id, kind, superseded_at)`, `(tenant_id, screening_weight)`.

**`live_key`** is the natural key of a *live* expression, hashed — for a review,
tenant + product + author; for a reply, tenant + parent. It is nulled the moment
the row is superseded or redacted, and it is uniquely indexed. That is what makes
"one live expression per author per product" a database refusal rather than a
check-then-act read two concurrent writers both pass.

### `reviews_expression_facets`

`id`, `tenant_id`, `expression_id` (FK), `facet`, `score`, `scale`, timestamps.
Unique on `(expression_id, facet)`. Facets are rows, never nullable columns on
the parent, and each carries its own scale. A facet that was not given is a row
that does not exist, which no arithmetic can mistake for a zero.

### `reviews_moderation_decisions`

`id`, `tenant_id`, `expression_id` (FK), `outcome`, `reason`, `actor_reference`,
`occurred_at`, timestamps. Indexed on `(expression_id, occurred_at)` and
`(tenant_id, outcome)`. Append-only, never updated. **No free-text column, by
design.**

### `reviews_votes`

`id`, `tenant_id`, `expression_id` (FK), `voter_reference` (nullable for
erasure), `direction`, `occurred_at`, timestamps. Unique on
`(expression_id, voter_reference)`. There is no counter column anywhere; totals
are a count of rows.

### `reviews_flags`

`id`, `tenant_id`, `expression_id` (FK), `reporter_reference` (nullable for
erasure), `reason`, `occurred_at`, timestamps. Unique on
`(expression_id, reporter_reference)`.

---

## 9. Two invariants worth knowing before you build against this

**Display state is derived.** There is no `approved` column. It is the outcome
of the latest moderation decision, or `Pending` when there is none. Do not cache
it in a view model without listening to `ModerationDecided`.

**Content is append-only, enforced in the model.** `Expression` refuses any
update that changes what was said; the only updates a recorded row accepts are
being superseded, being redacted, and being re-prioritised. Eloquent model events
do not fire for `query()->update()` or `query()->delete()`, so **never write to
these tables with a mass update** — the unique index on `live_key` is the only
part of this that a mass update cannot get around.
