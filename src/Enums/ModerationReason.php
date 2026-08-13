<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/**
 * Why a moderator decided what they decided, and why the screener raised what
 * it raised.
 *
 * A closed enum rather than free text: a moderation reason sits beside a
 * person's name by construction, and a free-text box next to an event log is
 * where personal data gets typed (addendum §5.10).
 */
enum ModerationReason: string
{
    case Compliant = 'compliant';
    case OffTopic = 'off_topic';
    case Profanity = 'profanity';
    case Spam = 'spam';
    case PersonalInformation = 'personal_information';
    case UndisclosedIncentive = 'undisclosed_incentive';
    case Counterfeit = 'counterfeit';
    case HarassmentOrHate = 'harassment_or_hate';
    case IllegalContent = 'illegal_content';
    case DuplicateSubmission = 'duplicate_submission';
    case WrongProduct = 'wrong_product';
    case MachineEscalation = 'machine_escalation';
    case AuthorErasure = 'author_erasure';
}
