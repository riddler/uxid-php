<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * The letter case an id's body is written in. Case is display only: an
 * upper-case body and its lower-case spelling are the same id. It never
 * applies to the prefix, which is written as given.
 */
enum LetterCase: string
{
    /** Lower case, the default. */
    case Lower = 'lower';

    /** Upper case. */
    case Upper = 'upper';
}
