/**
 * The same flow as an indented, keyboard-navigable outline — D12's own
 * accessible equivalent of the canvas. It reads and writes the very same
 * state, through the very same fields, so a flow built here and a flow built
 * by dragging are never two different things.
 *
 * @module     tool_flowboard/canvas/list_view
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';
import {buildFieldsForm} from './inspector';
import {isComplete} from './validation';

/**
 * Draws the whole outline, replacing whatever was there before.
 *
 * @param {HTMLElement} root
 * @param {Function} redraw
 */
export const render = (root, redraw) => {
    const graph = State.getGraph();
    const incoming = new Set(graph.edges.map((edge) => edge.to));
    const roots = graph.nodes.filter((node) => !incoming.has(node.key));

    root.innerHTML = '';

    if (roots.length === 0) {
        root.appendChild(starter(redraw));

        return;
    }

    const list = document.createElement('ol');

    list.className = 'tool-flowboard-list';
    roots.forEach((node) => list.appendChild(item(node, redraw)));
    root.appendChild(list);
};

/**
 * What an empty flow offers: pick a trigger to begin with.
 *
 * @param {Function} redraw
 * @returns {HTMLElement}
 */
const starter = (redraw) => {
    const wrapper = document.createElement('div');
    const triggers = Object.entries(State.getNodeTypes()).filter(([, meta]) => meta.istrigger);

    wrapper.className = 'tool-flowboard-list__starter';
    wrapper.appendChild(typePicker(triggers, (type) => {
        State.addNode(type, 40, 40);
        redraw();
    }, 'Start with', 'Add'));

    return wrapper;
};

/**
 * One node, its own fields, and — for each of its ports — either what
 * follows it or a control to add something that will.
 *
 * @param {Object} node
 * @param {Function} redraw
 * @returns {HTMLElement}
 */
const item = (node, redraw) => {
    const meta = State.getNodeTypes()[node.type];
    const li = document.createElement('li');
    const selected = (() => {
        const current = State.getSelection();

        return current !== null && current.kind === 'node' && current.key === node.key;
    })();

    li.className = 'tool-flowboard-list__item' + (selected ? ' tool-flowboard-list__item--selected' : '');

    const heading = document.createElement('div');

    heading.className = 'tool-flowboard-list__heading';

    const button = document.createElement('button');

    button.type = 'button';
    button.className = 'btn btn-link p-0 tool-flowboard-list__label';
    button.textContent = meta.label + (isComplete(node, meta) ? '' : ' ⚠');
    button.addEventListener('click', () => {
        State.select('node', node.key);
        redraw();
    });
    heading.appendChild(button);

    const remove = document.createElement('button');

    remove.type = 'button';
    remove.className = 'btn btn-link btn-sm text-danger p-0 ml-2';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => {
        State.removeNode(node.key);
        redraw();
    });
    heading.appendChild(remove);

    li.appendChild(heading);
    li.appendChild(buildFieldsForm(node, meta, redraw));

    const ports = meta.ports || ['out'];
    const sub = document.createElement('ol');

    sub.className = 'tool-flowboard-list';
    ports.forEach((port) => sub.appendChild(portItem(node, port, redraw)));

    if (sub.children.length > 0) {
        li.appendChild(sub);
    }

    return li;
};

/**
 * One of a node's own ports: the node it already leads to, or a way to add
 * one.
 *
 * @param {Object} node
 * @param {string} port
 * @param {Function} redraw
 * @returns {HTMLElement}
 */
const portItem = (node, port, redraw) => {
    const li = document.createElement('li');
    const edge = State.getGraph().edges.find((candidate) => candidate.from === node.key && candidate.port === port);

    li.className = 'tool-flowboard-list__port';

    const label = document.createElement('span');

    label.className = 'tool-flowboard-list__portlabel';
    label.textContent = port;
    li.appendChild(label);

    if (edge) {
        const child = State.nodeByKey(edge.to);
        const disconnect = document.createElement('button');

        disconnect.type = 'button';
        disconnect.className = 'btn btn-link btn-sm p-0 ml-2';
        disconnect.textContent = 'Disconnect';
        disconnect.addEventListener('click', () => {
            State.removeEdge(node.key, port);
            redraw();
        });
        li.appendChild(disconnect);

        const nested = document.createElement('ol');

        nested.className = 'tool-flowboard-list';

        if (child !== null) {
            nested.appendChild(item(child, redraw));
        }

        li.appendChild(nested);
    } else {
        const options = Object.entries(State.getNodeTypes()).filter(([, meta]) => !meta.istrigger);

        li.appendChild(typePicker(options, (type) => {
            const key = State.addNode(type, node.position.x + 260, node.position.y);

            State.addEdge(node.key, port, key);
            redraw();
        }, `After "${port}", add`, 'Add'));
    }

    return li;
};

/** @type {number} Every picker gets its own id, so its label points at the right one. */
let pickerCount = 0;

/**
 * A select of node types, labelled for its own sake, plus a button that adds
 * whichever is chosen.
 *
 * @param {Array} entries [type, meta] pairs.
 * @param {Function} onAdd
 * @param {string} pickerLabel What this particular choice is for — read
 *        aloud by a screen reader before the list of node types is.
 * @param {string} buttonLabel
 * @returns {HTMLElement}
 */
const typePicker = (entries, onAdd, pickerLabel, buttonLabel) => {
    const wrapper = document.createElement('span');
    const select = document.createElement('select');
    const id = `flowboard-picker-${(pickerCount += 1)}`;

    const label = document.createElement('label');

    label.setAttribute('for', id);
    label.className = 'tool-flowboard-list__pickerlabel';
    label.textContent = pickerLabel;
    wrapper.appendChild(label);

    select.id = id;
    select.className = 'custom-select custom-select-sm w-auto d-inline-block';
    entries.forEach(([type, meta]) => {
        const option = document.createElement('option');

        option.value = type;
        option.textContent = meta.label;
        select.appendChild(option);
    });

    const button = document.createElement('button');

    button.type = 'button';
    button.className = 'btn btn-secondary btn-sm ml-2';
    button.textContent = buttonLabel;
    button.disabled = entries.length === 0;
    button.addEventListener('click', () => onAdd(select.value));

    wrapper.appendChild(select);
    wrapper.appendChild(button);

    return wrapper;
};
