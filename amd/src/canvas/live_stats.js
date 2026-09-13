/**
 * How each node of a published flow has been doing lately — fetched once a
 * minute, so the badge stays roughly current without hammering the server.
 *
 * @module     tool_flowboard/canvas/live_stats
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';
import Log from 'core/log';

/** @type {number} How often the badges refresh themselves. */
const POLL_MS = 60000;

/**
 * Fetches the current counts once, and again on an interval, handing each
 * fresh set to the callback.
 *
 * @param {number} flowid A flow with nothing published yet has no history.
 * @param {Function} onUpdate Called with node key => {ok, failed, skipped}.
 * @returns {Function} Stops polling.
 */
export const watch = (flowid, onUpdate) => {
    if (flowid === 0) {
        return () => {};
    }

    const fetchOnce = async() => {
        try {
            const response = await Repository.nodeStats(flowid);

            onUpdate(JSON.parse(response.stats));
        } catch (error) {
            Log.debug(error);
        }
    };

    fetchOnce();
    const timer = window.setInterval(fetchOnce, POLL_MS);

    return () => window.clearInterval(timer);
};
