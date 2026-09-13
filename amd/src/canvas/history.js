/**
 * Undo and redo: a stack of whole snapshots of the drawing, not a diff of
 * one change — simple enough that trusting it needs no thought at all.
 *
 * @module     tool_flowboard/canvas/history
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';

/** @type {number} How many steps back "undo" can go. */
const LIMIT = 50;

/** @type {number} Milliseconds of quiet before a change becomes its own step. */
const SETTLE_MS = 500;

let past = [];
let future = [];
let restoring = false;
let timer = null;

/**
 * A graph, copied rather than referenced, so a later change cannot reach
 * back and alter a step already on the stack.
 *
 * @param {Object} graph
 * @returns {Object}
 */
const clone = (graph) => JSON.parse(JSON.stringify(graph));

/**
 * Starts the stack off with the drawing as it was opened.
 */
export const init = () => {
    past = [clone(State.getGraph())];
    future = [];
};

/**
 * Watches the state, and remembers a step once a change has settled — one
 * drag, one edit, one typed word once the person pauses, rather than one
 * step per pixel moved or per keystroke.
 */
export const watch = () => {
    State.subscribe(() => {
        if (restoring) {
            return;
        }

        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            const snapshot = clone(State.getGraph());

            if (JSON.stringify(snapshot) === JSON.stringify(past[past.length - 1])) {
                return;
            }

            past.push(snapshot);

            if (past.length > LIMIT) {
                past.shift();
            }

            future = [];
        }, SETTLE_MS);
    });
};

/**
 * Whether there is a step to undo.
 *
 * @returns {boolean}
 */
export const canUndo = () => past.length > 1;

/**
 * Whether there is a step to redo.
 *
 * @returns {boolean}
 */
export const canRedo = () => future.length > 0;

/**
 * Goes back one step.
 */
export const undo = () => {
    if (!canUndo()) {
        return;
    }

    future.push(past.pop());
    apply(past[past.length - 1]);
};

/**
 * Goes forward one step that undo had gone back from.
 */
export const redo = () => {
    if (!canRedo()) {
        return;
    }

    const snapshot = future.pop();

    past.push(snapshot);
    apply(snapshot);
};

/**
 * Puts a snapshot into the live state, without that itself becoming a new
 * step on the stack.
 *
 * @param {Object} snapshot
 */
const apply = (snapshot) => {
    restoring = true;
    State.replaceGraph(clone(snapshot));
    State.markDirty();
    restoring = false;
};
