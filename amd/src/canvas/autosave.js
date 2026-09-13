/**
 * The canvas's own autosave: whatever is dirty gets written back a moment
 * after the person stops changing it, and a small indicator says so.
 *
 * @module     tool_flowboard/canvas/autosave
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';
import * as Repository from './repository';
import Log from 'core/log';

/** @type {number} Milliseconds of quiet before a change is written back. */
const DEBOUNCE_MS = 1200;

let timer = null;

/**
 * Watches the state and saves a draft shortly after it stops changing.
 *
 * @param {number} flowid
 * @param {HTMLElement} indicator Where "saving…"/"saved" is shown.
 */
export const watch = (flowid, indicator) => {
    State.subscribe(() => {
        if (!State.isDirty()) {
            return;
        }

        indicator.textContent = 'Unpublished changes…';
        window.clearTimeout(timer);
        timer = window.setTimeout(() => save(flowid, indicator), DEBOUNCE_MS);
    });
};

/**
 * Writes the current drawing back, once.
 *
 * @param {number} flowid
 * @param {HTMLElement} indicator
 * @returns {Promise<void>}
 */
const save = async(flowid, indicator) => {
    if (flowid === 0) {
        // A flow that does not exist yet has nowhere to save a draft; the
        // first "Publish" is what creates it.
        return;
    }

    indicator.textContent = 'Saving…';

    try {
        await Repository.saveDraft(flowid, State.getGraph());
        indicator.textContent = 'Saved (not yet published)';
    } catch (error) {
        indicator.textContent = 'Could not save';
        Log.debug(error);
    }
};
