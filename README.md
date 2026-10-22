# Uxid for PHP

[![MIT License][badge_license_url]](LICENSE)
[![CI][badge_ci_url]](https://github.com/riddler/uxid-php/actions/workflows/ci.yml)

User eXperience focused IDentifiers for PHP: prefixed, K-sortable,
Stripe-style ids a person can read, copy and route back to their resource. An
id looks like `usr_01hf7yat0004h1`: the prefix names the resource, and the
body carries a time and randomness in lower-case Crockford Base32. The ids
follow the wire format of the Elixir reference implementation,
[uxid-ex](https://github.com/riddler/uxid-ex), and the package is checked
against that implementation's conformance corpus, every case, with no skip
list.

## Why UXID

An auto-increment key leaks how many rows a table holds and invites
enumeration; a random UUID fixes both but is long, unordered and anonymous, so
in a log line, a URL or a support thread nobody can tell what it points at, and
a double-click selects only part of it. A UXID names its resource in its
prefix, selects whole on a double-click, reads aloud without ambiguity, sorts
by creation time so it indexes well, and is generated in the application with
no coordination between nodes. Its size is tunable for low-cardinality
resources, a monotonic generator keeps a burst within one millisecond unique
and in order, and a deterministic mode maps the same input to the same id.

Because the format is shared, an id this package makes is read by the other
implementations of the format, and the other way round.

## Install

```bash
composer require riddler/uxid:^1.0
```

## Basic usage

```php
<?php

declare(strict_types=1);

use Riddler\Uxid\Decoded;
use Riddler\Uxid\Monotonic;
use Riddler\Uxid\Size;
use Riddler\Uxid\Uxid;
use Riddler\Uxid\UxidException;

// A prefix names the resource; the body is a time and randomness.
$id = Uxid::generate(prefix: 'usr');
var_dump(str_starts_with($id, 'usr_')); // bool(true)
var_dump(strlen($id)); // int(30)

// Shrink the random part for low-cardinality resources.
// Sizes: XSmall, Small, Medium, Large and XLarge (the default).
$small = Uxid::generate(prefix: 'cus', size: Size::Small);
var_dump(strlen($small)); // int(18)

// Deterministic: the same prefix and input give the same id, every time.
$named = Uxid::generate(prefix: 'usr', from: 'alice@example.com');
var_dump($named); // string(30) "usr_zcvt7epac0t1ebcsjfyf7cwz25"
var_dump(Uxid::isDeterministic($named)); // bool(true)

// Read an id back. A malformed string is a result, never an exception.
$decoded = Uxid::decode('cus_01hf7yat0004h1');
if ($decoded instanceof Decoded) {
    var_dump($decoded->prefix); // string(3) "cus"
    var_dump($decoded->size); // enum(Riddler\Uxid\Size::Small)
    var_dump($decoded->time); // int(1700000000000)
}
var_dump(Uxid::isValid('cus_01hf7yat0004h1', requiredPrefix: 'cus')); // bool(true)
var_dump(Uxid::decode('cus_')->reason->value); // string(5) "empty"

// Monotonic: ids from one generator increase strictly, also within one
// millisecond. You hold the generator; the package keeps no global state.
$ids = new Monotonic();
$first = $ids->generate(prefix: 'usr', size: Size::Medium);
$second = $ids->generate(prefix: 'usr', size: Size::Medium);
var_dump(strcmp($first, $second) < 0); // bool(true)

// A request the wire format refuses throws a UxidException with a stable name.
try {
    Uxid::generate(randSize: 11);
} catch (UxidException $e) {
    var_dump($e->errorCode->value); // string(19) "rand_size_too_large"
}
```

`generate` takes named arguments, and every one has a default: `prefix` (the
empty string is no prefix), `size` (a `Size` case, or its short or long
spelling as a string), `randSize`, `compactTime`, `case` (a `LetterCase`),
`delimiter`, `time`, `rand`, `byteSource` and `from`. A request the wire
format refuses throws a `UxidException`, an `InvalidArgumentException`; switch
on its `errorCode`, an `ErrorCode` case, or log `$e->errorCode->value`, a
stable `snake_case` name, rather than its message. A malformed argument of
another kind, such as an empty delimiter or a time outside the 48-bit range,
throws the usual `InvalidArgumentException`.

`Uxid::generate` and `Monotonic::generate` read the system clock when they
need a time, and `random_bytes`, the platform's cryptographically secure
source, when they need random bytes. `time` replaces the clock and
`byteSource`, a callable that takes a byte count and returns that many bytes,
replaces `random_bytes`; `Uxid::decode` takes a `clock` callable for the
clock it reads for a compact time. `rand` takes fixed random bytes for test
vectors: an id built from them is exactly as guessable as those bytes.

`Uxid::decode` returns a `Decoded` for a well-formed string and an `Invalid`
carrying a `DecodeError` reason for any other. It never throws on the string.
`Uxid::isValid($id, $delimiter, $requiredPrefix)` and
`Uxid::isDeterministic($id, $delimiter)` take the delimiter the id was
generated with, and read no clock. `isValid` answers whether `decode` would
decode the string (a body of a length the format defines, in Crockford Base32
characters) and, given a required prefix, whether it carries exactly that
prefix. `isDeterministic` answers whether `decode` would decode it and its
body starts with the marker `z` or `Z`. Both are stricter than the Elixir
reference, whose `valid?` accepts any alphabet body of at least 8 characters
and whose `deterministic?` checks only the first body character: here a
malformed string is neither valid nor deterministic. The conformance corpus
does not fix these two answers.

A `Monotonic` generator keeps its state, the last time and random value, per
prefix and random field width, inside the instance. Ids from one instance
for one prefix and width increase strictly; ids from two instances carry no
order between them within a millisecond. Hold one instance for as long as
you want the order held: the state is dropped with the instance.

## Compatibility

- **PHP 8.3 and later, on 64-bit builds only.** The package declares
  `"php": "^8.3"`, and CI runs the tests on PHP 8.3, 8.4 and 8.5. The 48-bit
  time field needs a 64-bit integer, so a 32-bit build (`PHP_INT_SIZE` under
  8) makes `generate` and `decode` throw a `LogicException`.
- **No dependency beyond `ext-random`.** `require` names `php` and
  `ext-random` and nothing else, and the default byte source is
  `random_bytes`.
- **The monotonic generator and threads.** PHP's standard runtime shares no
  object between threads: each request, worker or process has its own
  objects, and an extension that runs PHP code on threads copies the objects
  it hands to a thread rather than sharing them. So `Monotonic` holds no lock,
  because a lock would protect nothing, and two processes, or two threads
  holding copies, are two generators with no order between them. Code that
  runs a SAPI which does share one instance between threads has to serialize
  its calls to `generate` itself. `Uxid::generate`, `Uxid::decode` and the
  validity checks keep no state and can be called from anywhere.

## Documentation

- Look up
  - The API reference is the PHPDoc on every public class, method and
    constant in `src/`, starting with the `Uxid` class.
  - [The wire format](docs/wire-format.md): where the normative layout of an
    id lives, and which version of it this package follows.
  - [The changelog](CHANGELOG.md): what changed in each version.
  - [The conformance corpus](conformance): the cases every implementation of
    the wire format passes, vendored here from uxid-ex.
- Understand
  - [The reference spec, at v2.10.0][guide_wire_format_url]: the normative
    layout of an id - alphabet, bit packing, sizes, prefix grammar,
    deterministic and monotonic bodies, and decoding.
  - [Why a UXID has a prefix, a time and randomness][guide_why_url]: what each
    part of an id is for, and what you trade when you tune it.
  - [Why monotonic mode exists and what it costs][guide_monotonic_url]:
    same-millisecond uniqueness and ordering, and the security tradeoff.
  - [_Designing APIs for humans: object IDs_][stripe_ids_url]: the Stripe id
    design many of UXID's choices follow.

## Development

After checking out the repository, run `scripts/install-toolchain.sh` for the
PHP and Composer `mise.toml` pins. Then `mise run gate:loop` runs the inner
loop (PHPStan and the tests), and `mise run gate` runs the full quality gate:
`composer validate --strict`, the PHP-CS-Fixer check, PHPStan at level max,
the conformance corpus check, and the tests with 90 percent line coverage. The
tests include the conformance corpus, every case with no skip list, and run
the code block under "Basic usage" above.

A release is published by CI, never from a checkout: Packagist reads each
version from a pushed tag `vX.Y.Z`, and `.github/workflows/release.yml` checks
the tag against the `VERSION` constant in `src/Uxid.php` and confirms
Packagist lists the version.

## Contributing

Bug reports and pull requests are welcome on GitHub at
https://github.com/riddler/uxid-php.

## License

The package is available as open source under the terms of the
[MIT License](https://opensource.org/licenses/MIT).

<!-- LINKS -->
[guide_wire_format_url]: https://github.com/riddler/uxid-ex/blob/v2.10.0/guides/wire-format.md
[guide_why_url]: https://github.com/riddler/uxid-ex/blob/v2.10.0/guides/why-prefix-time-randomness.md
[guide_monotonic_url]: https://github.com/riddler/uxid-ex/blob/v2.10.0/guides/monotonic.md
[stripe_ids_url]: https://dev.to/stripe/designing-apis-for-humans-object-ids-3o5a

<!-- BADGES -->
[badge_license_url]: https://img.shields.io/badge/license-MIT-brightgreen.svg
[badge_ci_url]: https://github.com/riddler/uxid-php/actions/workflows/ci.yml/badge.svg
