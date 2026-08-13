# Changelog

All notable changes to `liberusoftware/ecommerce-reviews-and-ratings` are
documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-13

First release. The domain package: contracts, actions, queries, read models,
enums, events, exceptions and migrations. No HTTP, no panel, no components —
those are the `-api`, `-filament` and `-livewire` flavours, which build against
the surface described in `docs/domain.md`.

### Added

- **Expressions.** One person, one product, one moment: an optional score with
  its scale, an optional body, and `0..n` typed facets. Append-only and
  immutable; an edit writes a new row carrying `supersedes` and retires the old
  one. A merchant reply is an expression of a different kind on a parent
  expression.
- **Moderation decisions.** Their own append-only table, carrying an actor, an
  outcome, an `occurred_at`, and a reason from a closed enum. An expression's
  display state is derived from the latest decision and is stored nowhere.
- **Aggregates.** `DisplayedRatingAggregate` publishes `sum`, `count`, `scale`
  and the population that produced it, counting only expressions whose latest
  decision is `approved`. `RecordedRatingTotal` is the staff-only figure, named
  differently on purpose.
- **Votes.** One row per voter per expression, uniquely indexed. Idempotent, not
  additive. An author voting on their own expression is refused with a 403.
- **Flags.** Reader reports with closed-enum reasons. Reporting hides nothing.
- **Two seams.** `ConfirmsPurchase` is optional — unbound is a valid deployment
  producing `VerificationState::Unknown`. `ScreensContent` is not — unbound
  refuses any write carrying free text with a 503.
- **Erasure and export.** Erasure redacts the body, the display name and the
  author reference and keeps the score, the scale, the timestamps and the whole
  moderation history. Export returns everything about an author's own
  expressions, including states and reasons no page shows them.
- **Tenancy.** A non-nullable, indexed `tenant_id` on every table.

### Not in this release

- Syndicating *out*. `source` records that an expression came from elsewhere;
  publishing this merchant's reviews to another platform is not implemented.
- Re-verification. Verification is decided once, at write time, and inherited by
  a revision. Binding `ConfirmsPurchase` later does not re-badge existing rows.
- Cached or materialised aggregates. Every figure is computed from rows.

[0.1.0]: https://github.com/liberusoftware/module-ecommerce-reviews-and-ratings/releases/tag/0.1.0
