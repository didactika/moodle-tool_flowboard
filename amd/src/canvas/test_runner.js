/**
 * "Probar": walks the drawing exactly as it stands, against a sample of the
 * event it is triggered by, and hands back which nodes it actually visited
 * so the canvas can light up the path.
 *
 * @module     tool_flowboard/canvas/test_runner
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';
import * as Repository from './repository';

/**
 * Runs the test, and returns what {@see module:tool_flowboard/canvas/render.draw}
 * wants as its own highlight argument.
 *
 * @returns {Promise<?Object>} Null when the drawing has no trigger to test
 *          from, or the event it names is unknown to the site.
 */
export const run = async() => {
    const trigger = State.getGraph().nodes.find((node) => {
        const meta = State.getNodeTypes()[node.type];

        return meta !== undefined && meta.istrigger;
    });

    const eventname = trigger ? trigger.config.eventname : null;

    if (!eventname) {
        return null;
    }

    const result = await Repository.testRun(eventname, State.getGraph());
    const visited = new Set();
    const path = new Map();

    result.path.forEach((step) => {
        visited.add(step.nodekey);

        if (step.port !== '') {
            path.set(`${step.nodekey}:${step.port}`, true);
        }
    });

    return {visited, path, steps: result.path};
};
