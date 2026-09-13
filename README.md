# Flowboard

Automations for Moodle, built from the events Moodle already fires.

Something happens on the site — a student starts a course, someone is given a
role, an assignment is graded — and Flowboard reacts: it subscribes them to the
right forums, puts them in a group, sends a message, tells another system.
Which things happen, and to whom, is drawn on a board rather than written in
code.

> **Status: alpha, under construction.** The engine and the first actions are
> being built; see the roadmap below for what exists and what does not. Do not
> point it at a production site yet.

## Why not just write a plugin per rule

Because there is always another rule. "When a student starts a course,
subscribe them to the welcome forums" is a morning's work as a one-off plugin,
and so is the next request, and the one after that — until a site is carrying a
drawer of single-purpose plugins that nobody can audit together.

Flowboard turns each of those into a row you can read, test and switch off.

## What makes it different

- **It discovers the triggers.** Moodle already knows which events exist —
  core's and every installed plugin's — and can describe them. Flowboard reads
  that catalogue rather than shipping a hard-coded list, so an event added by a
  plugin you install tomorrow is available tomorrow.
- **The actions are inside Moodle.** Subscribing, enrolling, grouping,
  notifying. Not "call an external system and hope it has an API".
- **You can see what it would do before it does it.** Moodle's own log keeps
  the events that already happened, and core can rebuild them. So a flow can be
  run against last month, in simulation, and answer "this would have touched
  1,284 people" — before it is allowed near anybody.
- **Every flow acts as itself.** A flow gets its own user and its own role,
  holding exactly the capabilities its actions need and nothing else. The
  subscription log says *Flow: Forum welcome*, not *Admin User*, and pausing a
  flow takes its permissions away.

## Requirements

Moodle 4.5 or later. Tested against the versions listed in
`$plugin->supported` in [version.php](version.php); that array is what builds
the CI matrix.

## Installing

Copy this directory to `admin/tool/flowboard` in your Moodle installation, then
visit *Site administration → Notifications*, or run:

```
php admin/cli/upgrade.php
```

## Roadmap

| Phase | What it adds | State |
|---|---|---|
| 1 | The engine, the event catalogue, forum subscribe/unsubscribe by pattern, run history | in progress |
| 2 | Simulation, replay against the site's history, backfill, quarantine | planned |
| 3 | The visual board, flow versions, import/export as JSON and YAML | planned |
| 4 | Branches, loops, waits, sub-flows, expressions, desired state | planned |
| 5 | The rest of the actions, and the outbound ones | planned |
| 6 | Extensibility by hooks, scoping, governance, observability | planned |

## Contributing

See the organisation's [contributing
guide](https://github.com/didactika/.github/blob/main/CONTRIBUTING.md).
Development happens on `main`; this release line is built on `release/0.1.0`.
Commits follow [Conventional Commits](https://www.conventionalcommits.org).

## Licence

GNU GPL v3 or later. See [LICENSE](LICENSE).
