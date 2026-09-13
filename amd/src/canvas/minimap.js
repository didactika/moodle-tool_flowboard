/**
 * A small overview of the whole drawing, with a box showing what the canvas
 * itself is currently scrolled to — click anywhere on it to jump there.
 *
 * @module     tool_flowboard/canvas/minimap
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';
import {NODE_WIDTH, NODE_HEIGHT} from './layout';

/** @type {number} A margin (in drawing pixels) kept around every node's own extent. */
const MARGIN = 200;

/**
 * Builds the minimap and keeps it in step with the surface it overviews.
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} surface The canvas's own scrolling element.
 */
export const mount = (root, surface) => {
    root.innerHTML = '<div class="tool-flowboard-minimap__viewport"></div>';
    const viewport = root.querySelector('.tool-flowboard-minimap__viewport');

    const redraw = () => draw(root, viewport, surface);

    State.subscribe(redraw);
    surface.addEventListener('scroll', redraw);
    window.addEventListener('resize', redraw);
    redraw();

    root.addEventListener('click', (event) => {
        if (event.target === viewport) {
            return;
        }

        const bounds = bounds_(State.getGraph().nodes);
        const rootBox = root.getBoundingClientRect();
        const scale = Math.min(rootBox.width / bounds.width, rootBox.height / bounds.height);
        const x = bounds.minX + ((event.clientX - rootBox.left) / scale);
        const y = bounds.minY + ((event.clientY - rootBox.top) / scale);

        surface.scrollTo({
            left: x - (surface.clientWidth / 2),
            top: y - (surface.clientHeight / 2),
            behavior: 'smooth',
        });
    });
};

/**
 * The drawing's own extent, with a margin so a node at the very edge is not
 * drawn flush against the minimap's own border.
 *
 * @param {Object[]} nodes
 * @returns {{minX: number, minY: number, width: number, height: number}}
 */
const bounds_ = (nodes) => {
    if (nodes.length === 0) {
        return {minX: 0, minY: 0, width: 800, height: 600};
    }

    const xs = nodes.map((node) => node.position.x);
    const ys = nodes.map((node) => node.position.y);

    const minX = Math.min(...xs) - MARGIN;
    const minY = Math.min(...ys) - MARGIN;
    const maxX = Math.max(...xs) + NODE_WIDTH + MARGIN;
    const maxY = Math.max(...ys) + NODE_HEIGHT + MARGIN;

    return {minX, minY, width: maxX - minX, height: maxY - minY};
};

/**
 * Redraws the dots and the viewport box.
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} viewport
 * @param {HTMLElement} surface
 */
const draw = (root, viewport, surface) => {
    const nodes = State.getGraph().nodes;
    const bounds = bounds_(nodes);
    const rootBox = root.getBoundingClientRect();

    if (rootBox.width === 0) {
        return;
    }

    const scale = Math.min(rootBox.width / bounds.width, rootBox.height / bounds.height);

    root.querySelectorAll('.tool-flowboard-minimap__dot').forEach((dot) => dot.remove());

    nodes.forEach((node) => {
        const dot = document.createElement('div');

        dot.className = 'tool-flowboard-minimap__dot';
        dot.style.left = `${(node.position.x - bounds.minX) * scale}px`;
        dot.style.top = `${(node.position.y - bounds.minY) * scale}px`;
        root.insertBefore(dot, viewport);
    });

    viewport.style.left = `${(surface.scrollLeft - bounds.minX) * scale}px`;
    viewport.style.top = `${(surface.scrollTop - bounds.minY) * scale}px`;
    viewport.style.width = `${surface.clientWidth * scale}px`;
    viewport.style.height = `${surface.clientHeight * scale}px`;
};
