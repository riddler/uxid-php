### Added

- `Uxid::generate()` makes a time-based id from named arguments: `prefix` (the empty string is no prefix), `size` (a `Size` case or its short or long spelling, `xl` by default), `randSize`, `compactTime`, `case` (a `LetterCase`), `delimiter` (`_` by default) and `time` (the system clock by default).
- `generate()` draws its random bytes from `random_bytes` by default, or from a `byteSource` closure that takes a byte count; fixed `rand` bytes reproduce an id exactly, for test vectors.
- The `Size`, `LetterCase` and `ErrorCode` backed enums, `Size::parse()` for a size's short or long spelling, and the `Uxid::VERSION` constant.
- A request the wire format refuses throws `UxidException`, an `InvalidArgumentException` whose `errorCode` is an `ErrorCode` case naming the refusal: `rand_size_too_large`, `rand_width_mismatch` or `compact_time_out_of_range`.
