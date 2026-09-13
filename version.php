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
 * Version information for PLUGINTYPE_PLUGINNAME -- MOODLE_405_STABLE line.
 *
 * VERIFY: replace PLUGINTYPE_PLUGINNAME below (and in @package) with the
 * real component, e.g. local_servicemanager, and fill in @author. See the
 * version.php on `main` for a field-by-field explanation of every field --
 * this file only documents what is specific to being a stable branch.
 *
 * @package    PLUGINTYPE_PLUGINNAME
 * @copyright  YEAR YOUR ORGANISATION
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'PLUGINTYPE_PLUGINNAME'; // VERIFY: replace before first commit.
$plugin->version = 2000010100; // VERIFY: replace before first commit.

// Pinned to Moodle 4.5.0's own GA version.php build number (verified against
// https://moodledev.io/general/releases/4.5 on 2026-08-09, using Moodle's own
// YYYYMMDDXX convention for the release date). Raising this only makes sense
// if this branch later needs a floor somewhere inside the 4.5.x line --
// point releases never change this number.
$plugin->requires = 2024100700;

$plugin->maturity = MATURITY_ALPHA; // VERIFY: raise as the plugin stabilises.
$plugin->release = '0.1.0'; // VERIFY: this branch's own release line, independent of main.

// A stable branch tests against exactly the one Moodle version it is named
// after, not a range -- unlike `main`, which may support several at once.
// Keep this a single-element list; if this plugin needs to widen a stable
// branch's support, that is a sign the fix belongs on `main` instead.
$plugin->supported = [405];
