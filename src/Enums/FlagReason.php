<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/** Why a reader reported an expression. Closed, for the same reason as ModerationReason. */
enum FlagReason: string
{
    case OffTopic = 'off_topic';
    case Profanity = 'profanity';
    case Spam = 'spam';
    case PersonalInformation = 'personal_information';
    case Counterfeit = 'counterfeit';
    case HarassmentOrHate = 'harassment_or_hate';
    case IllegalContent = 'illegal_content';
    case WrongProduct = 'wrong_product';
}
