### Added

- `Uxid::generate()` takes a `from` argument and then makes a deterministic id: the body is derived from a SHA-256 hash of the prefix and `from`, so the same input always gives the same id, in the same sizes, cases and delimiters as a time-based id (there is no compact form). A deterministic id is exactly as guessable as its input.
- `generate()` refuses fixed `rand` bytes together with `from`, throwing `UxidException` with the `rand_with_from` error code.
