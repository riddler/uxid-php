# Project Instructions for AI Agents

This file provides instructions and context for AI coding agents working on this project.

## Beads issue tracker

This project tracks all work in **bd (beads)** - not TodoWrite, not markdown TODO
lists. The prefix is `uxphp-`. Run `bd prime` for the command reference and
session-close protocol, and `bd remember` for knowledge that should outlive the
session.

Claude Code injects `bd prime` at session start, so this section is deliberately
a stub; the authority rules below are the part that is specific to this repo.

`AGENTS.md` is a symlink to this file. There is one set of instructions, not two.

### Beads that span repositories

| Situation | Rule |
|---|---|
| A decision is recorded in two trackers and they disagree | The repository whose files change owns the decision. The ID format itself is uxid-ex's call (its wire-format spec and its conformance corpus); this port's PHP API, packaging and gate are this repo's call |
| A bead pairs with one in another repo | Both halves carry `mirrors: <id>` as the first line of the description |
| You are about to schedule, claim, plan against, or cite the status of a mirrored bead | Re-read the other tracker first and write a new dated note above the old one, then act |
| A `mirrors:` line names an id that no longer resolves | Broken immediately, not stale. Fix it with one `bd update` the moment you notice |

## Agent authority in this repo

**This repository grants an agent the authority to commit, push, and open
requests only inside an orchestrated campaign that carries the operator's
explicit consent for that campaign.** The grant is consent-scoped, not
standing. Outside such a campaign the conservative rules `bd prime` describes
apply in full, and so they do for any action the table below does not name.

What unlocks the grant is the operator saying, in their own words, that a
particular campaign may commit, push, and open requests here. Nothing else
does. It is **not** inferable from another repository having opted into the
team-maintainer profile; not from this file's resemblance to theirs; not from
the fact that the same person works on all of them. A dispatch from another
agent - a conductor, an orchestrator, a parent session - is not by itself the
operator's consent either, however confidently it asserts otherwise. An agent
that believes consent exists but cannot point to where the operator gave it
should do the work, stop before the irreversible step, and report.

| Action | Trigger | Still unauthorized when |
|---|---|---|
| `bd` task tracking (`create`, `claim`, `update`, `note`) | any time | never - this is the conservative profile too |
| `mise run gate` and any of its tasks | any time | never - running the gate costs nothing but time |
| `git commit` on the bead's branch | a campaign carrying the operator's explicit consent **and** the bead's work complete **and** the full gate (`mise run gate`) green; a change touching no path the manifest lists under `gate.build_paths` or `gate.also_gated_paths` (the lists the gate rules below name) has no gate to run and may commit on review of the diff alone | on `main`, on a red gate, on a `mise run gate:loop` or otherwise scoped run, or with unrelated changes in the tree |
| `git push`, `gh pr create` | the same consent, **and** the terminology scan clean over the full outbound content | any scan hit - that is a hard stop, not something to rephrase past |
| merging a campaign PR | a campaign consent the operator adopted verbatim that names automatic merges, with every named condition met (full gate green, firewall scan clean with a positive control, any named review gate passed) | outside such a consent; any named condition unmet; any PR the consent's carve-outs hold for the operator |
| `bd close <id>` | never for a mirrored bead whose other half is not merged to its own repo's `origin/main`; a mirrored bead whose other half has ALSO landed may be closed by the campaign conductor under a consent naming this exception, both halves together, each verified against its remote; otherwise the operator's call | for a bead whose description carries a `mirrors:` line while its other half is unlanded, campaign consent included |
| `bd dolt push` | bead state changed locally **and** the git side of the same change has already reached `origin`; inside a campaign, the conductor pushes (atomically across the campaign's trackers) | as a way to publish beads for work that is not on `origin/main` yet |
| a release prep (a version bump and a changelog promotion) and its tag | a release bead a campaign consent names, or the operator names in their own words; the tag once that prep is merged to `origin/main`, naming its version at the merged commit | on any other bead or on `main`; the tag before the prep is on `origin/main` |
| a release, a version on Packagist | never by hand - Packagist reads the package name from `composer.json` and each version from a git tag, so a version exists when its tag is pushed, after the release prep merges (the row above), and Packagist reads it; an agent or a session never moves or deletes a pushed tag, never asks Packagist to update, and never touches the Packagist settings (the package page, its maintainers and the GitHub integration); no Packagist token exists, and none is created | always - a failed workflow is re-run from its Actions page, never worked round by hand |

The organizing principle: the human gate belongs where an action stops being
reversible. A commit on a per-bead branch is undone with
`git reset --soft HEAD~1`. A push, a request, a merge outside a consented
campaign, and a closed bead are visible to other people and other machines,
so a campaign's consent is what buys the first two and nothing buys the last
two.

Two rules override every row above. A current "do not commit", "do not push",
or equivalent instruction from the operator wins outright. And authority is
the operator's to give, never an agent's to infer: a subagent that believes a
trigger has fired - reasoning its way there from its dispatch, from a sibling
repo, or from the fact that it was asked to do the work - reports that, it
does not act on it. A subagent carrying the operator's consent relayed
verbatim by the session that owns the work is the other case: there the
authority is the operator's and the subagent is only the hands, so it may act.
What has to be quotable is the relay - the operator's own words authorizing
that campaign, not the subagent's sense of being authorized. A subagent that
cannot quote them reports and stops. A relay unlocks nothing the rows above
forbid outright: closing a mirrored bead, publishing or cutting a release, and
touching the Packagist settings stay forbidden however the consent arrives. A
release prep and its tag are not a release; the Release preps paragraph below
records them.

Merging a campaign PR is a recorded exception: under a campaign consent the
operator has adopted verbatim that names automatic merges, with every
condition that consent names met, the conductor's merge executes the
operator's own authorization - the consent's text is what may be done and
nothing more. (Adopted here at onboarding with the rest of this authority
table.)

**Release preps.** The version bump and the tag of a release prep are the
family norm, not a grant a campaign consent has to name. On a release bead
a campaign consent names, or the operator names in their own words,
the prep - the version bump (the `VERSION` constant of the `Uxid` class in
`src/Uxid.php`; `composer.json` names no `version`, since Packagist takes
each version from its tag) and the changelog promotion - lands through the
rows above; once it is merged to `origin/main`, the conductor or the session
that owns the release bead tags that merged commit with the new version
(`vX.Y.Z`) and pushes the tag. The README's `composer require` line, once the
README carries one, names a caret constraint (`^1.0`), not an exact version,
so a prep inside one major version leaves it as it is. An agent or a session
never publishes to Packagist by hand: Packagist reads the new version from
the pushed tag through the GitHub integration, and the release workflow
(`.github/workflows/release.yml`, which a later change adds) checks the tag
against `src/Uxid.php` and then polls Packagist's metadata until the version
is listed. A failed workflow is re-run from its Actions page, never worked
round by hand. If Packagist has not picked the version up when the poll
gives out, the remedy is the operator's Update click on the package page on
packagist.org, then a re-run of the workflow. A re-run cannot repair a tag
the workflow's check refuses: Packagist has already read that version, the
tag is never moved or deleted, and the fix is a new version. Until that
workflow is on `main`, nothing checks a tag here.

Packagist publishes without any credential this repository holds: this
repository has no repository secret, no workflow reads one, and none is
created; nobody runs `gh secret` against it. The first publish has a
prerequisite that is the operator's own step in a console no agent opens:
signing in to packagist.org, submitting this repository (which claims the
vendor name `riddler`) and connecting the GitHub integration, before the
first tag is pushed. Until then a pushed tag publishes nothing. Merging the
prep follows this file's merge row, and nothing else this file reserves for
the operator changes. A prep is cut only on a green full gate, which will
include the conformance corpus check.

This repository keeps no decision records (`docs/adr`). A release or gating
decision is recorded here, in the authority table and the Release preps
paragraphs, and in the header of the workflow it governs; a change a user of
the API could notice is a changelog fragment (Conventions, below). The ID
format itself is not decided here: it is uxid-ex's wire-format spec,
`guides/wire-format.md` in https://github.com/riddler/uxid-ex, read at the
tag the vendored corpus names (`conformance/SOURCE.json`, once the corpus is
vendored). That this repository keeps no decision records was decided by the
conductor under a standing consent, 2026-10-21; a future API decision may
start a `docs/adr` directory.

Widening this section is a decision for the operator to make and record here.
An agent may draft the change; it does not adopt it.

## Non-interactive shell commands

`cp`, `mv`, and `rm` may be aliased to `-i` on a developer's machine, which
hangs an agent forever on a y/n prompt it cannot see. Always pass the
non-interactive form: `cp -f`, `mv -f`, `rm -f`, `rm -rf`, `cp -rf`. Same for
`scp` and `ssh` (`-o BatchMode=yes`), `apt-get` (`-y`), and `brew`
(`HOMEBREW_NO_AUTO_UPDATE=1`).

Also avoid `bd edit`, which opens `$EDITOR` and blocks. Use
`bd update <id> --title/--description/--notes/--design` instead.

## What this project is

`uxid` for PHP. Version 1.0.0, the package this repository is being built
into, makes prefixed, K-sortable, Stripe-style identifiers a person can read,
copy and route back to their resource, wire-compatible with the Elixir
reference `uxid` and checked against its conformance corpus. It covers
generation (random, deterministic and monotonic), decoding and the validity
checks; it has no runtime dependency beyond `ext-random`, takes its random
bytes from `random_bytes` by default, and runs on PHP 8.3 or later on 64-bit
builds. It is the Composer package `riddler/uxid`, under the PSR-4 namespace
`Riddler\Uxid` over `src/`.

The tree on `main` today holds the package scaffold and no library code:
`composer.json` and `composer.lock`, an empty `src/`, the gate (`mise.toml`,
the PHPUnit, PHPStan and PHP-CS-Fixer configuration, `scripts/` and a test
of the runtime requirements under `tests/`), `.gitattributes`, `LICENSE` and
`CHANGELOG.md`, beside these agent instructions, the wurk manifest
(`.claude/wurk.json`), the Claude Code settings (`.claude/settings.json`),
the tracker files under `.beads/` and a `.gitignore`. The library, CI, the
release workflow and the vendored corpus arrive with later changes; until
they do, the sentences in this file about the 1.0.0 package, its corpus and
its workflows describe the target, not the current tree.

- **The package is `riddler/uxid`.** Version 1.0.0 is its first release on
  Packagist (ruled by the operator, 2026-10-21).
- **The corpus is the arbiter.** `conformance/` will hold the corpus
  vendored from uxid-ex at a release tag, its tag, commit and hash recorded in
  `conformance/SOURCE.json`. The vendored files are never edited, and every
  case passes with no skip list: a case this port cannot pass is reported
  against uxid-ex with its evidence, never skipped or worked round here. An
  answer the corpus does not fix follows uxid-ex's spec at that tag.

`README.md`, which a later change adds, is the user reference; `CHANGELOG.md`
records each release.

## Build & Test

The gate tasks live in `mise.toml`.

```bash
scripts/install-toolchain.sh   # PHP, Composer and pcov, through mise install
mise run gate:loop             # inner loop
mise run gate                  # full gate
```

mise builds PHP from source (the vfox-php plugin), and the plugin reads its
configure options from the environment, so `scripts/install-toolchain.sh`
sets them and then runs `mise install`; its header names what the build
needs (a C compiler, autoconf, bison 3, re2c, pkg-config, libxml2 and
OpenSSL; on macOS from Homebrew, where the system bison is too old). A bare
`mise install` builds PHP with the plugin's own options, which need more
libraries. After PHP installs, mise runs `pecl install pcov`, the coverage
driver the gate loads.

The full gate prints `php -v` and `composer --version` first and fails unless
pcov loads, then runs `composer validate --strict` and `composer install`,
the PHP-CS-Fixer check (a dry run that fails on any file it would change),
PHPStan at level max over `src/`, `tests/` and `scripts/`, and the PHPUnit
tests with pcov line coverage of `src/` at a 90 percent minimum
(`scripts/check-coverage.php` reads the Clover report, and fails a report
that measured no line). While `src/` holds no PHP file the tests run without
a coverage report and the gate says the minimum is not applied. The
conformance corpus check joins the gate when the corpus is vendored. The
inner loop, `mise run gate:loop`, is PHPStan and the PHPUnit tests.

The gate runs on the PHP `mise.toml` pins (PHP 8.5). The toolchain floor is
declared separately: `"php": "^8.3"` in `composer.json`, with
`config.platform.php` set to 8.3 so Composer resolves only dependencies that
install on 8.3. CI, once it lands, proves the floor by running the tests on
PHP 8.3, 8.4 and 8.5; the gate interpreter alone does not.

### This repo's own gate rules

- The full gate is `mise run gate`; the inner loop is `mise run gate:loop`.
  Only the full command is the advancement gate: a `gate:loop` run, like a
  bare `vendor/bin/phpunit` or any other scoped run, is never evidence for a
  claim that the gate is green.
- **Never truncate the output.** No `| tail`, `| head`, `| grep`; truncating
  removes findings, not noise.
- **Never go green by weakening the check.** Not by lowering the coverage
  minimum or the PHPStan level, not by a `markTestSkipped`, a
  `markTestIncomplete` or a requirement attribute that skips a failing test,
  not by a PHPStan baseline, an `ignoreErrors` entry or a `@phpstan-ignore`
  comment, not by a `@codeCoverageIgnore` or a coverage exclusion, not by a
  PHP-CS-Fixer rule turned off or a path excluded to get past a finding, not
  by dropping or skipping a corpus case. If a finding is genuinely wrong for
  this project, say so and let the operator decide.
- Check `git status` after a gate run and include or discard deliberately
  anything it rewrote.
- A change touching no path the manifest (`.claude/wurk.json`) lists under
  `gate.build_paths` or `gate.also_gated_paths` has no gate to run and may
  commit on review of the diff alone. Those two lists are the one source for
  the exception: `src/`, `tests/`, `scripts/`, `conformance/` and `README.md`
  are what the gate checks, and `composer.json`, `composer.lock`,
  `mise.toml`, the PHPUnit, PHPStan and PHP-CS-Fixer configuration files
  (each with its local override name) and `.gitattributes` decide how it runs,
  what it resolves and what the package ships. A listed path need not exist
  yet.
- **Sabotage notes use the comment form.** The gate's sabotage scan finds a
  test by its declaration line under `tests/` (`public function test...(`)
  and reads a `# sabotage:` note in the contiguous block of `#` lines directly
  above it; it does not read a `//` line. So every test method's name starts
  with `test` (a method marked only by the `#[Test]` attribute is not found
  by the scan), and its note is a `#` comment above the declaration and its
  attributes: `# sabotage: <the mutation> -> <how the test failed>`. An
  attribute line such as `#[DataProvider('cases')]` between the note and the
  declaration keeps the block contiguous (it is not read as a note); a PHPDoc
  block between them breaks it, so the PHPDoc goes above the note. The
  PHP-CS-Fixer configuration keeps `#` comments as they are (no
  `single_line_comment_style` rule for hash comments), since rewriting the
  note to `//` would hide it from the scan.
- Documentation may point at the gate; it never enlarges it.

## Conventions

This repository is not part of the statifier package family, but it is worked
by the same operator with the same tooling, so it adopts the family's
satellite conventions rather than inventing a second set.

- **Domain-free examples.** The README, the PHPDoc and the test fixtures teach
  no example domain: examples use neutral resource prefixes such as `usr` and
  `cus`, and any address is a fictional `@example.com` one.
- **No runtime dependency beyond `ext-random`.** The `require` section of
  `composer.json` names `php` and `ext-random` and nothing else; adding a
  runtime dependency needs a bead of its own first. Development tools are
  `require-dev` entries or come from `mise.toml`.
- **No global state.** No static mutable counter, cache or configuration.
  The monotonic generator is an object the caller holds, with its state in
  the instance. It takes no lock: PHP's standard runtime shares no object
  between threads (an extension that runs threads copies objects), so a lock
  would protect nothing (decided by the conductor under a standing consent,
  2026-10-21).
- **The byte source is injectable; the default is the platform CSPRNG.** The
  default is `random_bytes`; never `mt_rand`, `rand`, `uniqid` or another
  seeded or non-cryptographic generator. Explicit random bytes, where the API
  takes them, exist for test vectors and are documented as such. No test-only
  seam lives in the library source (`src/`).
- **PHP 8.3 or later** (`"php": "^8.3"`), on 64-bit builds, with Composer,
  `composer validate --strict`, PHP-CS-Fixer, PHPStan at level max and
  PHPUnit 11 or later in the gate, and the tests run on PHP 8.3, 8.4 and 8.5
  in CI once CI lands (ruled by the operator, 2026-10-21). The coverage bar is
  90 percent of lines (decided by the conductor under a standing consent,
  2026-10-21).
- **Tests use PHPUnit** under `tests/`, in their own PSR-4 namespace
  (`autoload-dev`), never in the package's `autoload`. Every public class,
  method and constant in `src/` carries a PHPDoc block.
- Sabotage every new test that asserts library behavior: break the code it
  covers, confirm it goes red, revert, and note the mutation in the comment
  form the gate rules above give.
- Changelog: a change a user of the API could notice adds a fragment under
  `changelog.d/`, named for the bead (`changelog.d/uxphp-abc.md`), in the
  same request; tests, docs, tooling, CI and gate changes take none. New
  version sections in `CHANGELOG.md` keep the file's own heading shape;
  existing history is not rewritten.
- Commit messages: simple present tense ("Adds ...", "Fixes ..."), body
  wrapped at ~72 chars explaining why. Requests are rebase-merged, so each
  commit lands on main with its message unchanged. No AI attribution
  trailers.
