# Changelog

All notable changes to this plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!--
.github/workflows/release.yml reads this file: when $plugin->release changes
in version.php on `main` OR on any MOODLE_XXX_STABLE branch, it looks for a
"## [<that release>]" heading below and uses everything under it, verbatim,
as the GitHub Release body. If no such heading exists yet, the release still
happens but with a generic one-line release note instead.

Keep an "## [Unreleased]" section above the latest release for changes that
have not shipped yet; rename it to "## [x.y.z]" (matching $plugin->release)
when you cut that release, and start a fresh "## [Unreleased]" above it. Each
branch keeps its own CHANGELOG.md history from the point it was cut, same as
its own $plugin->release line -- no need to reconcile entries across branches.
-->

## [Unreleased]

### Added

- The plugin itself: component, capabilities, site settings and licence, so
  there is something to install while the engine is built on top of it.
- `tool/flowboard:manage` and `tool/flowboard:viewruns`, kept apart because
  reading what a flow did and deciding what it does next are different jobs.

### Changed

- CI now runs on pushes to `release/*` as well as on pull requests into them.
  This release line is built by committing straight to `release/0.1.0`, so a
  push there is the first time that code is seen at all — not an already-tested
  merge, which is what the template assumes about protected branches.

### Added

- The tables a flow is made of: the flow itself, its published versions, the
  nodes and edges of each, the run history, the actor a flow acts as, and what
  each event was seen carrying.
- A real privacy provider. The run history says which person a flow acted on,
  so the null provider that shipped with the skeleton had stopped being true.
- The event catalogue: every event this site can fire, read off the installed
  components rather than listed here, with a page to search it by name and by
  component.

### Fixed

- The two files the template ships as placeholders were still in the tree and
  failed the code checker, which is what broke the first pipeline run.
