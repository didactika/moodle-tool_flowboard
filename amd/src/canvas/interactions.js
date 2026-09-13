/**
 * Everything a pointer does on the canvas: dragging a node or a note,
 * drawing a connection from one port to another, and picking what is
 * selected.
 *
 * @module     tool_flowboard/canvas/interactions
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';
import {edgePath} from './layout';

/** @type {number} Pixels of movement before a press counts as a drag, not a click. */
const DRAG_THRESHOLD = 4;

/**
 * Wires up dragging, connecting and selecting on the canvas the renderer
 * just drew.
 *
 * @param {Object} refs From {@see module:tool_flowboard/canvas/render.mount}.
 * @param {Function} redraw Called whenever something the drawing shows changed.
 */
export const attach = (refs, redraw) => {
    let pending = null;

    refs.surface.addEventListener('pointerdown', (event) => {
        const portOut = event.target.closest('[data-role="port-out"]');
        const handle = event.target.closest('[data-role="drag-handle"]');
        const node = event.target.closest('.tool-flowboard-node');
        const comment = event.target.closest('.tool-flowboard-comment');

        if (portOut !== null) {
            pending = startConnecting(refs, portOut, node, event);
        } else if (handle !== null && node !== null) {
            pending = startMoving(node, 'node', event, redraw);
        } else if (handle !== null && comment !== null) {
            pending = startMoving(comment, 'comment', event, redraw);
        } else if (event.target === refs.surface || event.target.parentElement === refs.surface) {
            State.select();
            redraw();
        }
    });

    refs.surface.addEventListener('pointermove', (event) => {
        if (pending !== null) {
            pending.move(event);
        }
    });

    ['pointerup', 'pointercancel'].forEach((type) => {
        refs.surface.addEventListener(type, (event) => {
            if (pending !== null) {
                pending.finish(event);
                pending = null;
            }
        });
    });

    refs.surface.addEventListener('dblclick', (event) => {
        if (event.target !== refs.surface && !event.target.classList.contains('tool-flowboard-canvas__comments')) {
            return;
        }

        const bounds = refs.surface.getBoundingClientRect();

        State.addComment(event.clientX - bounds.left, event.clientY - bounds.top);
        redraw();
    });

    refs.surface.addEventListener('dragover', (event) => {
        if (event.dataTransfer.types.includes('application/x-flowboard-node-type')) {
            event.preventDefault();
        }
    });

    refs.surface.addEventListener('drop', (event) => {
        const type = event.dataTransfer.getData('application/x-flowboard-node-type');

        if (type === '') {
            return;
        }

        event.preventDefault();

        const bounds = refs.surface.getBoundingClientRect();

        State.addNode(type, event.clientX - bounds.left - 20, event.clientY - bounds.top - 20);
        redraw();
    });
};

/**
 * Begins dragging a node or a comment: the element follows the pointer, and
 * only actually moves the state once the press has travelled far enough to
 * be a drag rather than the start of a click.
 *
 * @param {HTMLElement} el
 * @param {string} kind 'node' or 'comment'.
 * @param {PointerEvent} started
 * @param {Function} redraw
 * @returns {Object}
 */
const startMoving = (el, kind, started, redraw) => {
    const key = el.dataset.key;
    const origin = readTranslate(el);
    let dragged = false;

    return {
        move(event) {
            const dx = event.clientX - started.clientX;
            const dy = event.clientY - started.clientY;

            if (!dragged && Math.hypot(dx, dy) < DRAG_THRESHOLD) {
                return;
            }

            dragged = true;
            const x = origin.x + dx;
            const y = origin.y + dy;

            el.style.transform = `translate(${x}px, ${y}px)`;

            if (kind === 'node') {
                State.moveNode(key, x, y);
            } else {
                State.moveComment(key, x, y);
            }

            redraw();
        },
        finish() {
            if (!dragged) {
                State.select(kind, key);
                redraw();
            }
        },
    };
};

/**
 * Begins drawing a connection from an output port. A temporary line follows
 * the pointer; releasing over a node commits the edge, releasing anywhere
 * else abandons it.
 *
 * @param {Object} refs
 * @param {HTMLElement} portEl
 * @param {HTMLElement} fromNodeEl
 * @param {PointerEvent} started
 * @returns {Object}
 */
const startConnecting = (refs, portEl, fromNodeEl, started) => {
    const from = fromNodeEl.dataset.key;
    const port = portEl.dataset.port;
    const bounds = refs.surface.getBoundingClientRect();
    const start = {
        x: started.clientX - bounds.left,
        y: started.clientY - bounds.top,
    };
    const preview = document.createElementNS('http://www.w3.org/2000/svg', 'path');

    preview.setAttribute('class', 'tool-flowboard-edge tool-flowboard-edge--pending');
    refs.edges.appendChild(preview);

    return {
        move(event) {
            const end = {x: event.clientX - bounds.left, y: event.clientY - bounds.top};

            preview.setAttribute('d', edgePath(start, end));
        },
        finish(event) {
            preview.remove();

            const target = document.elementFromPoint(event.clientX, event.clientY);
            const toNodeEl = target !== null ? target.closest('.tool-flowboard-node') : null;

            if (toNodeEl !== null) {
                State.addEdge(from, port, toNodeEl.dataset.key);
            }
        },
    };
};

/**
 * The x/y a translate() transform is currently holding, for an element the
 * renderer positioned that way.
 *
 * @param {HTMLElement} el
 * @returns {{x: number, y: number}}
 */
const readTranslate = (el) => {
    const match = /translate\(\s*(-?[\d.]+)px,\s*(-?[\d.]+)px\s*\)/.exec(el.style.transform);

    return match ? {x: parseFloat(match[1]), y: parseFloat(match[2])} : {x: 0, y: 0};
};
