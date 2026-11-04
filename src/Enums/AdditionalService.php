<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Enums;

/**
 * Additional services documented for the Standard and MEBEL services.
 */
enum AdditionalService: string
{
    /** SMS notification of the recipient. */
    case Sms = 'SMS';

    /** E-mail notification of the recipient; ShipTo Email is required (error 1120). */
    case Email = 'EMAIL';

    /** Return of documents. */
    case ReturnOfDocuments = 'ROD';

    /** Carrying the goods into the premises. */
    case CarryIn = 'W';

    /** Assembly. */
    case Assembly = 'M';

    /** Unpacking. */
    case Unpacking = 'R';

    /** Taking away the old item or the packaging. */
    case TakeAway = 'Z';

    /** Carrying the goods down; cannot be mixed with parcels without it (error 1154). */
    case CarryDown = 'ZNI';
}
