<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/**
 * What sort of speech an expression is.
 *
 * A merchant reply is an expression too (addendum §5.8) — same append-only
 * rules, same moderation — so it lives in the same table rather than becoming a
 * second concept that has to be kept in step.
 */
enum ExpressionKind: string
{
    case ShopperReview = 'shopper_review';
    case MerchantReply = 'merchant_reply';
}
