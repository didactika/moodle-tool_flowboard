/**
 * The sidebar of node types a flow can be built from — grouped by whether a
 * kind of node is where a flow starts or something that can only ever follow
 * one, and drag one onto the canvas to add it.
 *
 * @module     tool_flowboard/canvas/palette
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';

/**
 * Draws the palette into its own mount point, and keeps its trigger group
 * in step with whether the drawing already has one.
 *
 * @param {HTMLElement} root
 */
export const mount = (root) => {
    const nodeTypes = State.getNodeTypes();
    const triggers = [];
    const rest = [];

    Object.entries(nodeTypes).forEach(([type, meta]) => {
        (meta.istrigger ? triggers : rest).push({type, meta});
    });

    root.innerHTML = `
        <p class="tool-flowboard-palette__hint">Drag a node onto the canvas to add it.</p>
        <h5 class="tool-flowboard-palette__title">Starts a flow</h5>
        <div class="tool-flowboard-palette__group" data-group="triggers"></div>
        <h5 class="tool-flowboard-palette__title">Happens after something else</h5>
        <div class="tool-flowboard-palette__group" data-group="nodes"></div>
    `;

    const triggerGroup = root.querySelector('[data-group="triggers"]');

    fill(triggerGroup, triggers);
    fill(root.querySelector('[data-group="nodes"]'), rest);

    const refresh = () => {
        const hasTrigger = State.getGraph().nodes.some((node) => {
            const meta = nodeTypes[node.type];

            return meta !== undefined && meta.istrigger;
        });

        triggerGroup.querySelectorAll('.tool-flowboard-palette__item').forEach((item) => {
            item.classList.toggle('tool-flowboard-palette__item--disabled', hasTrigger);
            item.draggable = !hasTrigger;
            item.title = hasTrigger
                ? 'A flow starts from exactly one trigger, and this one already has one.'
                : 'Drag onto the canvas to start the flow with this.';
        });
    };

    State.subscribe(refresh);
    refresh();
};

/**
 * One group's own entries, each draggable onto the canvas.
 *
 * @param {HTMLElement} group
 * @param {Object[]} entries {type, meta}.
 */
const fill = (group, entries) => {
    entries.forEach(({type, meta}) => {
        const item = document.createElement('div');

        item.className = 'tool-flowboard-palette__item';
        item.draggable = true;
        item.textContent = meta.label;
        item.title = 'Drag onto the canvas to add it after a node it can follow.';
        item.addEventListener('dragstart', (event) => {
            if (item.draggable === false) {
                event.preventDefault();

                return;
            }

            event.dataTransfer.setData('application/x-flowboard-node-type', type);
            event.dataTransfer.effectAllowed = 'copy';
        });

        group.appendChild(item);
    });
};
