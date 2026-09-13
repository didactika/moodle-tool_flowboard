/**
 * What the canvas asks of Moodle.
 *
 * @module     tool_flowboard/canvas/repository
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Makes one webservice call.
 *
 * @param {string} methodname
 * @param {Object} args
 * @returns {Promise}
 */
const call = (methodname, args) => Ajax.call([{methodname, args: args || {}}])[0];

/**
 * Everything the canvas opens with: the flow's own drawing, and the palette
 * to draw more of it with.
 *
 * @param {number} flowid 0 for a new flow.
 * @returns {Promise<Object>}
 */
export const getEditorData = (flowid) => call('tool_flowboard_get_editor_data', {flowid});

/**
 * Autosaves the canvas's own drawing.
 *
 * @param {number} flowid
 * @param {Object} graph nodes, edges, comments.
 * @returns {Promise<Object>}
 */
export const saveDraft = (flowid, graph) => call('tool_flowboard_save_draft', {
    flowid,
    graph: JSON.stringify(graph),
});

/**
 * Freezes the drawing into a new, immutable, running version.
 *
 * @param {number} flowid
 * @param {Object} graph
 * @param {string} note
 * @returns {Promise<Object>}
 */
export const publish = (flowid, graph, note = '') => call('tool_flowboard_publish', {
    flowid,
    graph: JSON.stringify(graph),
    note,
});

/**
 * Walks the drawing as it stands right now against a sample event.
 *
 * @param {string} eventname
 * @param {Object} graph
 * @returns {Promise<Object>}
 */
export const testRun = (eventname, graph) => call('tool_flowboard_test_run', {
    eventname,
    graph: JSON.stringify(graph),
});

/**
 * How each node has been doing lately.
 *
 * @param {number} flowid
 * @param {number} days
 * @returns {Promise<Object>}
 */
export const nodeStats = (flowid, days = 7) => call('tool_flowboard_node_stats', {flowid, days});
