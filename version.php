<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information for PLUGINTYPE_PLUGINNAME
 *
 * VERIFY: replace PLUGINTYPE_PLUGINNAME below (and in @package) with the
 * real component, e.g. local_servicemanager, and fill in @author.
 *
 * @package    PLUGINTYPE_PLUGINNAME
 * @copyright  YEAR YOUR ORGANISATION
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Component name: "<plugin type>_<plugin name>", e.g. "local_servicemanager"
// or "mod_forum". Must match the folder this plugin installs into
// (<moodle>/<type>/<name>, e.g. local/servicemanager/) and the folder name
// moodle-plugin-ci checks out in CI. .github/workflows/release.yml derives
// the release zip's filename and its internal folder prefix from this
// value, so keep it in sync with the actual directory structure.
$plugin->component = 'PLUGINTYPE_PLUGINNAME'; // VERIFY: replace before first commit.

// Bump this on every change that should trigger an upgrade step, even
// between releases -- Moodle uses THIS field (not $plugin->release) to
// decide whether db/upgrade.php needs to run. Convention: YYYYMMDDXX (date
// plus a 2-digit counter for same-day bumps), e.g. 2026081400.
$plugin->version = 2000010100; // VERIFY: replace before first commit.

// Lowest Moodle core version this plugin installs on, expressed as Moodle
// core's OWN version.php integer (not one of the branch codes used in
// $plugin->supported below -- these are two different numbering systems).
// Moodle refuses to install/upgrade this plugin on anything older. Example
// below is Moodle 4.5.0's own version number.
$plugin->requires = 2024100700; // VERIFY: example is Moodle 4.5.0 -- set to your actual floor.

// MATURITY_ALPHA | MATURITY_BETA | MATURITY_RC | MATURITY_STABLE.
$plugin->maturity = MATURITY_ALPHA; // VERIFY: raise as the plugin stabilises.

// Human-readable release string (semver-ish). This is the ONE field that
// drives .github/workflows/release.yml: whenever it changes on `main`, that
// workflow tags the commit v<release> and publishes a GitHub Release with
// the plugin zip attached. Bump it by hand as part of the same commit/PR
// that finishes a release -- bumping $plugin->version alone does not
// trigger a release.
$plugin->release = '0.1.0';

// Moodle versions this plugin is tested against, as integer branch codes
// (405 = 4.5, 502 = 5.2, ...; see MOODLE_XXX_STABLE branch names). THIS
// ARRAY IS THE SINGLE SOURCE OF TRUTH for which Moodle versions get tested:
// .github/workflows/ci.yml reads it directly to build its test matrix (see
// the "setup" job in ci.yml). Do not duplicate this list anywhere else. If
// you add a version here with no matching row in ci.yml's MOODLE_PHP_TABLE,
// CI fails loudly instead of silently testing the wrong PHP versions.
$plugin->supported = [405]; // VERIFY: set to the Moodle versions you actually support.
