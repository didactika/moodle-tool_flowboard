/**
 * Draws the canvas from the state — nodes, the edges between them, and the
 * sticky notes pinned alongside. Every draw is a fresh one: a flow is small
 * enough that redrawing it whole, on every change, is simpler to trust than
 * patching a difference would be.
 *
 * @module     tool_flowboard/canvas/render
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';
import {NODE_WIDTH, NODE_HEIGHT, inputPortOf, outputPortOf, edgePath} from './layout';
import {isComplete} from './validation';

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Builds the empty canvas surface once, and returns the parts the rest of
 * the module needs to keep drawing into.
 *
 * @param {HTMLElement} root
 * @returns {Object}
 */
export const mount = (root) => {
    root.innerHTML = `
        <div class="tool-flowboard-canvas__surface" tabindex="-1">
            <svg class="tool-flowboard-canvas__edges"></svg>
            <div class="tool-flowboard-canvas__comments"></div>
            <div class="tool-flowboard-canvas__nodes"></div>
        </div>
    `;

    return {
        surface: root.querySelector('.tool-flowboard-canvas__surface'),
        edges: root.querySelector('.tool-flowboard-canvas__edges'),
        comments: root.querySelector('.tool-flowboard-canvas__comments'),
        nodes: root.querySelector('.tool-flowboard-canvas__nodes'),
    };
};

/**
 * One SVG element, with attributes set the way SVG wants them (no className
 * shortcut, attributes only).
 *
 * @param {string} tag
 * @param {Object} attrs
 * @returns {SVGElement}
 */
const svgEl = (tag, attrs) => {
    const el = document.createElementNS(SVG_NS, tag);

    Object.entries(attrs).forEach(([name, value]) => el.setAttribute(name, value));

    return el;
};

/**
 * Every port a node type draws, in a stable order.
 *
 * @param {Object} nodeTypes
 * @param {string} type
 * @returns {string[]}
 */
const portsOf = (nodeTypes, type) => (nodeTypes[type] && nodeTypes[type].ports) || ['out'];

/**
 * Redraws everything: nodes, their ports, the edges between them, and the
 * sticky notes — from the state, as it stands right now.
 *
 * @param {Object} refs From {@see mount}.
 * @param {Object} [liveStats] Node key => {ok, failed, skipped}.
 * @param {?Object} [highlight] From a "probar" run: {visited: Set, path: Map}.
 */
export const draw = (refs, liveStats = {}, highlight = null) => {
    const graph = State.getGraph();
    const nodeTypes = State.getNodeTypes();
    const selection = State.getSelection();

    refs.nodes.innerHTML = '';
    refs.comments.innerHTML = '';
    refs.edges.innerHTML = '';

    graph.nodes.forEach((node) => {
        refs.nodes.appendChild(drawNode(node, nodeTypes, selection, liveStats, highlight));
    });

    graph.comments.forEach((comment) => {
        refs.comments.appendChild(drawComment(comment, selection));
    });

    graph.edges.forEach((edge) => {
        const from = State.nodeByKey(edge.from);
        const to = State.nodeByKey(edge.to);

        if (from === null || to === null) {
            return;
        }

        const ports = portsOf(nodeTypes, from.type);
        const start = outputPortOf(from, edge.port, ports);
        const end = inputPortOf(to);
        const dimmed = highlight !== null && !highlight.path.has(`${edge.from}:${edge.port}`);

        refs.edges.appendChild(svgEl('path', {
            d: edgePath(start, end),
            class: 'tool-flowboard-edge' + (dimmed ? ' tool-flowboard-edge--dim' : ''),
            'data-from': edge.from,
            'data-port': edge.port,
            'marker-end': 'url(#tool-flowboard-arrow)',
        }));
    });

    ensureArrowhead(refs.edges);
};

/**
 * The arrowhead marker every edge points with, added once and reused.
 *
 * @param {SVGElement} svg
 */
const ensureArrowhead = (svg) => {
    if (svg.querySelector('#tool-flowboard-arrow') !== null) {
        return;
    }

    const marker = svgEl('marker', {
        id: 'tool-flowboard-arrow',
        viewBox: '0 0 10 10',
        refX: '8',
        refY: '5',
        markerWidth: '7',
        markerHeight: '7',
        orient: 'auto-start-reverse',
    });
    marker.appendChild(svgEl('path', {d: 'M 0 0 L 10 5 L 0 10 z', class: 'tool-flowboard-edge__head'}));

    const defs = svgEl('defs', {});
    defs.appendChild(marker);
    svg.insertBefore(defs, svg.firstChild);
};

/**
 * One node box, with its own ports as small hit targets.
 *
 * @param {Object} node
 * @param {Object} nodeTypes
 * @param {?Object} selection
 * @param {Object} liveStats
 * @param {?Object} highlight
 * @returns {HTMLElement}
 */
const drawNode = (node, nodeTypes, selection, liveStats, highlight) => {
    const meta = nodeTypes[node.type] || {label: node.type, ports: ['out']};
    const el = document.createElement('div');
    const selected = selection !== null && selection.kind === 'node' && selection.key === node.key;
    const stats = liveStats[node.key];
    const visited = highlight !== null && highlight.visited.has(node.key);
    const dimmed = highlight !== null && !visited;

    el.className = 'tool-flowboard-node' + (selected ? ' tool-flowboard-node--selected' : '')
        + (dimmed ? ' tool-flowboard-node--dim' : '')
        + (visited ? ' tool-flowboard-node--visited' : '');
    el.dataset.key = node.key;
    el.style.transform = `translate(${node.position.x}px, ${node.position.y}px)`;
    el.style.width = `${NODE_WIDTH}px`;
    el.style.height = `${NODE_HEIGHT}px`;

    const incomplete = !isComplete(node, meta);

    el.innerHTML = `
        <div class="tool-flowboard-node__header" data-role="drag-handle">
            <span class="tool-flowboard-node__label">${escapeHtml(meta.label)}</span>
            ${incomplete ? '<span class="tool-flowboard-node__incomplete" title="Still needs something to work">⚠</span>' : ''}
        </div>
        <div class="tool-flowboard-node__body"></div>
        <div class="tool-flowboard-node__port tool-flowboard-node__port--in" data-role="port-in"></div>
    `;

    if (stats !== undefined) {
        const badge = document.createElement('span');
        const total = stats.ok + stats.failed + stats.skipped;

        badge.className = 'tool-flowboard-node__stats' + (stats.failed > 0 ? ' tool-flowboard-node__stats--warn' : '');
        badge.textContent = total === 0 ? '' : `${stats.ok}/${total}`;
        el.querySelector('.tool-flowboard-node__header').appendChild(badge);
    }

    (meta.ports || ['out']).forEach((port, index) => {
        const dot = document.createElement('div');
        const slot = (index + 1) / ((meta.ports || ['out']).length + 1);

        dot.className = 'tool-flowboard-node__port tool-flowboard-node__port--out';
        dot.dataset.role = 'port-out';
        dot.dataset.port = port;
        dot.style.top = `${slot * 100}%`;
        dot.title = port;
        el.appendChild(dot);
    });

    return el;
};

/**
 * One sticky note.
 *
 * @param {Object} comment
 * @param {?Object} selection
 * @returns {HTMLElement}
 */
const drawComment = (comment, selection) => {
    const el = document.createElement('div');
    const selected = selection !== null && selection.kind === 'comment' && selection.key === comment.id;

    el.className = 'tool-flowboard-comment' + (selected ? ' tool-flowboard-comment--selected' : '');
    el.dataset.key = comment.id;
    el.style.transform = `translate(${comment.x}px, ${comment.y}px)`;
    el.innerHTML = '<div class="tool-flowboard-comment__handle" data-role="drag-handle"></div>'
        + `<div class="tool-flowboard-comment__text" contenteditable="true">${escapeHtml(comment.text)}</div>`;

    return el;
};

/**
 * Text as HTML would show it, nothing more.
 *
 * @param {string} text
 * @returns {string}
 */
const escapeHtml = (text) => {
    const div = document.createElement('div');

    div.textContent = text || '';

    return div.innerHTML;
};
