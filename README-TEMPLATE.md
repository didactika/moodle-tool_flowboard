# Setting up a repo created from this template

This file is a one-time checklist for whoever creates a new repository from
`template-moodle-plugin`. Work through it before the first real release,
then delete this file (or fold whatever is still relevant into the plugin's
real README -- your call).

## 1. Fill in `version.php`

Four fields drive everything else in this template; do not skip any of them:

- `$plugin->component` -- `<type>_<name>`, e.g. `local_servicemanager`,
  `mod_forum`. Must match the folder Moodle installs this plugin into.
  `release.yml` derives the release zip's filename and its internal folder
  name from this value (see the comment in that step).
- `$plugin->version` -- bump on every change that needs an upgrade step,
  format `YYYYMMDDXX`. Moodle uses this to decide whether to run
  `db/upgrade.php`; it is unrelated to the release process in step 5.
- `$plugin->requires` -- the lowest Moodle core version this plugin installs
  on, expressed as Moodle core's own `version.php` integer -- not one of the
  branch codes used by `$plugin->supported`. These are two different
  numbering systems; see the comment in `version.php`.
- `$plugin->supported` -- the list of Moodle versions (as integer branch
  codes: `405` = 4.5, `502` = 5.2, ...) this plugin is tested against. See
  the next section -- **this is the only place that list lives.**

Also fill in `@copyright`/`@author` in the file's docblock and set
`$plugin->maturity` (`MATURITY_ALPHA` while unstable, `MATURITY_STABLE` once
you'd recommend it for production).

## 2. Add `lang/en/<component>.php`

This template does not ship one, and Moodle requires it: `moodle-plugin-ci
validate` fails immediately with "Failed to find required file:
lang/en/<component>.php" without it -- confirmed the hard way, building the
first plugin from this template. Create it with at least the mandatory
`pluginname` string:

```php
<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'REPLACE-ME';
```

Match the component name from step 1 exactly -- `lang/en/local_servicemanager.php`
for `local_servicemanager`, not `lang/en/servicemanager.php` or anything else.

## 3. Declaring which Moodle versions this plugin supports

`$plugin->supported` in `version.php` is the single source of truth. Do not
also list Moodle versions anywhere in `ci.yml` -- there is nowhere left to
put them:

- `ci.yml`'s `setup` job reads `$plugin->supported` directly and builds the
  `php x moodle-branch x database` test matrix from it, so adding or
  dropping a Moodle version is a one-line change in `version.php` alone.
- The only other input is `MOODLE_PHP_TABLE`, a small table inside that same
  `setup` job mapping each Moodle version to the PHP versions it supports.
  That is a fact about Moodle, not about this plugin, so it changes only
  when a new Moodle version ships (roughly twice a year) -- see the comment
  above the table in `ci.yml` for its source and the date it was last
  checked, and extend it (never guess) before adding a newer Moodle version
  to `$plugin->supported`.
- If you add a version to `$plugin->supported` with no matching row in
  `MOODLE_PHP_TABLE`, the `setup` job fails loudly instead of silently
  testing the wrong (or no) PHP versions for it.

This is a deliberate design choice: the previous, more obvious approach --
declaring supported Moodle versions in the matrix, in an `exclude:` list,
*and* in `version.php`, with a comment tying them together -- is what this
template replaces, because those three declarations drift out of sync in
practice and nothing catches it when they do.

## 4. Branch scheme: `main` plus every `MOODLE_XXX_STABLE` this template ships

- `main` is the development branch (and the default branch), following
  Moodle core's own convention. There is no `develop`.
- **This template ships one already-populated `MOODLE_XXX_STABLE` branch per
  Moodle version it covered when it was generated** (currently `4.0` through
  `5.2` -- see the repo's branch list for the exact set), each with its own
  `version.php` already pinned to that version's real `$plugin->requires`
  floor and `$plugin->supported = [that version]`. **Delete the ones this
  plugin will not actually support** — keeping a branch around that nobody
  tests or maintains is worse than not having it, since `CI complete` is
  still a required check on it. Do not cut new stable branches from scratch
  by hand for a version this template already covers; start from the one
  that shipped with it instead.
- **All of them are protected, `main` and every `MOODLE_*_STABLE` alike:**
  no direct pushes, required review, and `CI complete` as the required
  status check. `ci.yml` already triggers on pushes and pull requests
  targeting any of them.
- The plugin's own version (`$plugin->release`) is independent per branch,
  not shared across them -- see below.

### When a new Moodle version ships

This template's branch list is only as current as the day it was generated
-- Moodle ships a new version roughly twice a year, and this repo does not
learn about that on its own. When that happens:

1. Add a row for the new version to `MOODLE_PHP_TABLE` in `ci.yml` (its PHP
   compatibility range -- see the comment above that table for where to
   check and how it's verified).
2. Cut the new `MOODLE_XXX_STABLE` branch from `main`, and set
   `$plugin->requires` in its `version.php` to that version's own GA build
   number from `https://moodledev.io/general/releases/<x.y>` (the same
   `YYYYMMDDXX`-from-release-date convention every other stable branch here
   already uses -- do not guess it, that page states the release date and
   Moodle's numbering makes the build number derivable from it).
3. Set that branch's `$plugin->supported = [that version's code]`.
4. If this plugin needs anything else specific to that Moodle version
   (a new Behat step, a workaround for a deprecated API, ...), it goes on
   this new branch, not on `main` or the older stable branches.

This is the same operation the org would do to **this template itself** to
keep it current for the next plugin created from it -- see
`.claude/proposal/06-plantillas-org.md` if you're maintaining the template
rather than a plugin built from it.

## 5. Publishing a release

There is no publish step to configure or credentials to set up. Bump
`$plugin->release` in `version.php` by hand (e.g. `1.2.0`) on whichever
branch is finishing a release -- `main`, or a `MOODLE_XXX_STABLE` backporting
a fix to an older Moodle version -- as part of the same commit/PR that
finishes it, and add a matching `## [1.2.0]` section to that branch's own
`CHANGELOG.md`. `release.yml` takes it from there: on every push to `main`
or any `MOODLE_XXX_STABLE` branch that touches `version.php`, it checks
whether a `v<release>` tag already exists, and if not, tags the commit,
builds the plugin zip, and publishes a GitHub Release with that zip attached
and the matching `CHANGELOG.md` section as the release notes.

Pick distinct release numbers per branch that is actively releasing, the
same way an npm package keeps its `vMAJOR.x` lines on separate version
numbers -- nothing here reconciles two branches racing to publish the same
`$plugin->release` value.

It is idempotent -- editing `version.php` for an unrelated reason, or
re-running the workflow, does nothing if the tag already exists.

Keep an `## [Unreleased]` section at the top of `CHANGELOG.md` between
releases; `release.yml` only extracts a section that already exists, and
falls back to a generic one-line note if it can't find one for the release
being published.

## 6. What you do NOT need to add here

- `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md` -- inherited
  automatically from the organization's `.github` repo as long as this repo
  doesn't have its own. Adding a local copy would silently stop that
  inheritance.
- `codeql.yml` -- code scanning is configured at the organization level via
  the default setup API, not a per-repo workflow.
- `dependabot.yml` and the workflows in `.github/workflows/` -- these do
  **not** inherit from `.github`, which is exactly why this template already
  includes them for you.
- **No GitHub Environments, and no tag/release structure to protect.**
  Unlike the org's npm packages, a Moodle plugin is not published to any
  registry and nothing is deployed anywhere -- the zip attached to each
  GitHub Release is the only artifact this repo ever produces, and it needs
  no environment to represent it.

## Other files this template includes, and why

- `.gitattributes` -- marks `.github/`, `.gitignore` and `.gitattributes`
  themselves as `export-ignore`, so `release.yml`'s `git archive` step ships
  a clean plugin zip without CI/VCS-only files inside it.
- `.gitignore` -- ignores non-English language packs (`lang/*` except
  `lang/en/`). Once this plugin is registered in the Moodle plugins
  directory, translations are managed by Moodle's own AMOS translation
  system, not by this repository.
- `CHANGELOG.md` -- starts with just `## [Unreleased]`. This is required for
  the very first release to work at all: see step 5.
- `.github/dependabot.yml` -- `github-actions` updates are active by
  default; the `composer` ecosystem is present but commented out, since most
  Moodle plugins have no `composer.json` of their own. Uncomment it if this
  plugin adds one.
