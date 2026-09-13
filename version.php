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
 * Version information for tool_flowboard.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'tool_flowboard';

// Bumped on every change that needs an upgrade step. Format YYYYMMDDXX.
$plugin->version = 2026091302;

// Moodle 4.5.0, expressed as core's own version number.
$plugin->requires = 2024100700;

$plugin->maturity = MATURITY_ALPHA;

$plugin->release = '0.1.0';

// The single source of truth for which Moodle versions this plugin is tested
// against: .github/workflows/ci.yml builds its matrix from this array.
$plugin->supported = [405, 502];
