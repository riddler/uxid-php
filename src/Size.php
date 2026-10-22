<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * The size of an id: how wide its random field is, and so how long its body
 * is. Each case's value is the short spelling (`xs`, `s`, `m`, `l`, `xl`);
 * its name is the long one.
 *
 * | Size   | Random bytes (standard time) | Random bytes (compact time) | Body length (standard / compact) |
 * |--------|------------------------------|-----------------------------|----------------------------------|
 * | XSmall | 0                            | 1                           | 10 / 10                          |
 * | Small  | 2                            | 3                           | 14 / 13                          |
 * | Medium | 5                            | 6                           | 18 / 18                          |
 * | Large  | 7                            | 8                           | 22 / 21                          |
 * | XLarge | 10                           | 10                          | 26 / 24                          |
 *
 * The default size is XLarge.
 */
enum Size: string
{
    /** Extra small: a time field and no random field in standard time. */
    case XSmall = 'xs';

    /** Small. */
    case Small = 's';

    /** Medium. */
    case Medium = 'm';

    /** Large. */
    case Large = 'l';

    /** Extra large, the default. */
    case XLarge = 'xl';

    /**
     * Reads a size from its short spelling (`xs`, `s`, `m`, `l`, `xl`) or its
     * long one (`xsmall`, `small`, `medium`, `large`, `xlarge`), in lower case
     * exactly.
     *
     * @param string $spelling the spelling to read
     *
     * @return self the size it names
     *
     * @throws \InvalidArgumentException when the string is no size's spelling
     */
    public static function parse(string $spelling): self
    {
        return match ($spelling) {
            'xs', 'xsmall' => self::XSmall,
            's', 'small' => self::Small,
            'm', 'medium' => self::Medium,
            'l', 'large' => self::Large,
            'xl', 'xlarge' => self::XLarge,
            default => throw new \InvalidArgumentException(
                'Not a size: expected xs, s, m, l, xl, xsmall, small, medium, large or xlarge.',
            ),
        };
    }
}
