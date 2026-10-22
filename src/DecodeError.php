<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * Why a string is not a well-formed id: a closed set of names, not messages.
 * Each value is the refusal reason the wire format and its conformance
 * corpus give. A string that breaks more than one rule is refused for the
 * first, in the order the cases are declared: empty, invalid length, invalid
 * character.
 */
enum DecodeError: string
{
    /** The body is empty: the empty string, or a string ending in the delimiter. */
    case Empty = 'empty';

    /** The body's length in bytes is not one of the lengths an id's body has. */
    case InvalidLength = 'invalid_length';

    /** A body byte is not a Crockford Base32 symbol in either case. */
    case InvalidCharacter = 'invalid_character';
}
