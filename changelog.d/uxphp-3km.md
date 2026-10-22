### Added

- `Uxid::decode()` reads a string back and returns a `Decoded` (the prefix, the `Size`, whether the time is compact, whether the body is deterministic, and the time in milliseconds) or an `Invalid` whose `reason` is a `DecodeError`: `empty`, `invalid_length` or `invalid_character`, checked in that order. It never throws on a malformed string.
- `decode()` takes a `delimiter` (`_` by default) and a `clock` closure returning milliseconds (the system clock by default), which supplies the high bits of a compact time; a clock in the first 2^40 ms window can give a negative time.
- `Uxid::isValid()` answers whether a string is a well-formed id under a `delimiter`, optionally carrying a `requiredPrefix`; `Uxid::isDeterministic()` answers whether it is a well-formed deterministic id under a `delimiter`.
