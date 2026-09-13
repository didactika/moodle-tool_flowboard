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
- `classes/local/` split into `flow/`, `actor/`, `run/`, `event/` and
  `matching/` sub-namespaces (`node/` already existed), so a class's folder now
  says what it is for instead of every concern sharing one flat namespace.
  Purely a reorganisation: no behaviour changed, and the full test suite still
  passes.

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

### Added

- The observer. It is registered for every event the site fires, so a flow can
  be built on an event belonging to a plugin this one has never heard of, and
  it answers "is anybody waiting for this?" from an application cache without
  touching the database.
- The engine: it walks the drawing node by node, writes down each one as it
  goes, and counts its steps so that a drawing with a loop in it stops instead
  of running forever.
- The first two kinds of node: an event trigger, which also settles which
  person the run is about, and a question about the event.
- A node registry that finds node classes rather than listing them, which is
  what will let other plugins add nodes without forking this one.

### Added

- Two more nodes: subscribing and unsubscribing the run's subject to the
  forums of a course that match a name or idnumber pattern. Both require
  `mod/forum:managesubscriptions`, because that is the same capability
  `subscribe.php` checks before letting a person override a forum's own
  subscription mode.
- `pattern_matcher`, the one place "does this text match what was typed"
  lives — exact, contains, starts with, or a guarded regular expression —
  shared by the condition node and both forum nodes instead of duplicated.
- `forum_matcher`, which finds a course's forums by name or by
  `course_modules.idnumber` (a forum has no idnumber of its own).

### Fixed

- `events.php` called a method the page's own class never defined
  (`export()` instead of `export_for_template()`), which made the page fatal
  on every visit. Confirmed by rendering the page through the same call the
  route makes.
- A generic-style `@param array<string, \stdClass[]>` in `engine.php` broke
  moodle-plugin-ci's phpdoc checker: its parser splits on whitespace, and the
  comma inside the generic split the type from the parameter name. Rewritten
  as `\stdClass[][]`, which the checker parses correctly and which still
  reads as "grouped by the node it leaves".
- A forum set to "subscription disabled" can still be subscribed to by hand
  in Moodle's own interface, for anyone holding
  `mod/forum:managesubscriptions` — confirmed against `subscribe.php`'s own
  override check. The subscribe node does the same rather than refusing.
- Unsubscribing from a forum that forces subscription on everyone reports the
  true outcome (`stillforced`) instead of claiming success: Moodle considers
  such a person subscribed regardless of any row this node removes, so
  reporting "unsubscribed" would have recorded something that did not happen.

### Added

- The flow's actor (D17): its own user account and role, holding exactly the
  capabilities its published nodes need and nothing else. Created on first
  publish, reconciled on every one after — a node removed from the drawing
  takes its capability away too.
- The publisher of a flow is checked against those same capabilities before
  anything is granted: a flow cannot reach further than the person publishing
  it could reach themselves.
- Pausing a flow unassigns its actor's role immediately and suspends the
  account; making it live again restores both.
- Deleting a flow removes the real user and role behind it, not only the row
  that recorded them.
- The task that runs a flow now runs as the flow's own actor, so that whoever
  Moodle attributes an action to (`mod_forum\subscriptions::subscribe_user()`,
  like most of what a node calls, reads `$USER` rather than taking an explicit
  "who did this" argument) is the flow, not whichever account the cron
  happens to be running as.
- An actors screen (`admin/tool/flowboard/actors.php`) listing every flow's
  account, its status, and what it holds.

### Fixed

- A capability check failing mid-publish now happens before any row is
  written, rather than inside the transaction that saves the drawing. A
  delegated transaction only rolls back automatically once an exception
  escapes the entire request uncaught; letting a refusal reach that point
  would mean either relying on that (which calling code catching the refusal
  to show a friendly message would prevent) or discovering, as a test here
  did, that a transaction nested inside PHPUnit's own does not roll back
  until the outer one is disposed — so nothing could even confirm it had
  worked. Checking first avoids needing either.
- `core\session\manager::kill_user_sessions()` is deprecated since Moodle 4.5;
  replaced with `destroy_user_sessions()`.

### Added

- The accessible editor phase 1 promised (D12): a flow list, a create/edit
  form for the one shape this plugin's nodes can build (a trigger, an
  optional question, one action), a delete confirmation, and a run history
  showing every node a run touched. Plain links, forms and tables throughout
  — no custom JavaScript widget stands between a keyboard-only or
  screen-reader visitor and any of it.
- The four rules this plugin was built to answer, ready in one click: R1-R4
  as templates that fill in everything except the one thing only a site can
  answer — the pattern.
- `flow_editor`, which turns the form's fields into a graph and a graph back
  into the form's fields — kept separate from the form itself so the mapping
  is tested without needing a real form submission.
