### Added

- `Monotonic`, a generator object the caller holds: `generate()` takes the arguments `Uxid::generate()` takes, and ids made for one prefix and random field width increase strictly, also within one millisecond and when the clock moves back. Within a millisecond the random field moves on by a step drawn uniformly from 1 to 2^(4w); when it overflows, the id carries the next millisecond and a fresh random field.
- A monotonic XSmall id uses compact time unless `compactTime` is given, and a random field of 0 bytes is refused with an `InvalidArgumentException`.
- `DrawSource`, an interface for replaying known seeds and steps into a `Monotonic` generator in place of the byte source, for tests and conformance cases.
- `Monotonic::generate()` refuses `from` with `from_with_monotonic` and `rand` with `rand_with_monotonic`; a refused call leaves the generator's state as it was.
- `Monotonic` takes no lock: PHP's standard runtime shares no object between threads, so each process or thread that holds a generator holds its own.
