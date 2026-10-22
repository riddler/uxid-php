# Changelog

## 1.0.0 - 2026-10-21

The first release of `riddler/uxid`, the PHP port of uxid: prefixed,
K-sortable identifiers a person can read, wire-compatible with the Elixir
`uxid` package, under the namespace `Riddler\Uxid`. It runs on PHP 8.3 or
later, on 64-bit builds only, and requires nothing beyond `ext-random`. It
covers time-based generation and deterministic ids (`Uxid::generate()`, with
the `Size` and `LetterCase` enums and `Size::parse()`), decoding
(`Uxid::decode()`, which returns a `Decoded`, read through its `prefix`,
`size`, `compactTime`, `deterministic` and `time`, or an `Invalid`, read
through its `reason`, a `DecodeError`), the validity checks
(`Uxid::isValid()` and `Uxid::isDeterministic()`), the monotonic generator
(`Monotonic`, built with `new Monotonic()` or with a `DrawSource`, whose
methods are `seed()` and `step()`, and its `generate()`), the generation
refusals (`UxidException` and its `errorCode`, an `ErrorCode`) and the
`Uxid::VERSION` constant. It is checked against the conformance corpus
vendored from riddler/uxid-ex at the tag `v2.10.0` (`conformance/SOURCE.json`
records the tag, its commit and the corpus hash), and every case in it passes
with no skip list.

### Added

- `Uxid::decode()` reads a string back and returns a `Decoded` (the prefix, the `Size`, whether the time is compact, whether the body is deterministic, and the time in milliseconds, null for a deterministic body) or an `Invalid` whose `reason` is a `DecodeError`: `empty`, `invalid_length` or `invalid_character`, checked in that order. It never throws on a malformed string.
- `decode()` takes a `delimiter` (`_` by default) and a `clock` callable returning milliseconds (the system clock by default), which supplies the high bits of a compact time; a clock in the first 2^40 ms window can give a negative time.
- `Uxid::isValid()` answers whether a string is a well-formed id under a `delimiter`, optionally carrying a `requiredPrefix`; `Uxid::isDeterministic()` answers whether it is a well-formed deterministic id under a `delimiter`.
- `Uxid::generate()` takes a `from` argument and then makes a deterministic id: the body is derived from a SHA-256 hash of the prefix and `from`, so the same input always gives the same id, in the same sizes, cases and delimiters as a time-based id (there is no compact form). A deterministic id is exactly as guessable as its input.
- `generate()` refuses fixed `rand` bytes together with `from`, throwing `UxidException` with the `rand_with_from` error code.
- `Monotonic`, a generator object the caller holds: `generate()` takes the arguments `Uxid::generate()` takes, and for one prefix and random field width each id carries a time and random value greater than the last one's, also within one millisecond and when the clock moves back. Among ids of one letter case, delimiter and time form, each sorts after the last as a string; compact ids keep that order only within one 2^40 ms window. Within a millisecond the random field moves on by a step drawn uniformly from 1 to 2^(4w); when it overflows, the id carries the next millisecond and a fresh random field.
- A monotonic XSmall id uses compact time unless `compactTime` is given, and a random field of 0 bytes is refused with an `InvalidArgumentException`.
- `DrawSource`, an interface for replaying known seeds and steps into a `Monotonic` generator in place of the byte source, for tests and conformance cases.
- `Monotonic::generate()` refuses `from` with `from_with_monotonic` and `rand` with `rand_with_monotonic`; a refused call leaves the generator's state as it was.
- `Monotonic` takes no lock: PHP's standard runtime shares no object between threads, so each process or thread that holds a generator holds its own.
- `Uxid::generate()` makes a time-based id from named arguments: `prefix` (the empty string is no prefix), `size` (a `Size` case or its short or long spelling, `xl` by default), `randSize`, `compactTime`, `case` (a `LetterCase`), `delimiter` (`_` by default) and `time` (the system clock by default).
- `generate()` draws its random bytes from `random_bytes` by default, or from a `byteSource` callable that takes a byte count; fixed `rand` bytes reproduce an id exactly, for test vectors.
- The `Size`, `LetterCase` and `ErrorCode` backed enums, `Size::parse()` for a size's short or long spelling, and the `Uxid::VERSION` constant.
- A request the wire format refuses throws `UxidException`, an `InvalidArgumentException` whose `errorCode` is an `ErrorCode` case naming the refusal: `rand_size_too_large`, `rand_width_mismatch` or `compact_time_out_of_range`.
