/**
 * The geometry of the canvas: where a node's own ports sit, the curve an
 * edge draws between two of them, and the one-shot layout the "align" button
 * offers.
 *
 * @module     tool_flowboard/canvas/layout
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {number} A node's own drawn width. */
export const NODE_WIDTH = 220;

/** @type {number} A node's own drawn height. */
export const NODE_HEIGHT = 84;

/**
 * Where the input port sits on a node, in canvas coordinates.
 *
 * @param {Object} node
 * @returns {{x: number, y: number}}
 */
export const inputPortOf = (node) => ({x: node.position.x, y: node.position.y + (NODE_HEIGHT / 2)});

/**
 * Where one of a node's own output ports sits, spread evenly down its right
 * edge when it has more than one.
 *
 * @param {Object} node
 * @param {string} port
 * @param {string[]} ports Every port this node type draws, in order.
 * @returns {{x: number, y: number}}
 */
export const outputPortOf = (node, port, ports) => {
    const index = Math.max(0, ports.indexOf(port));
    const slot = (index + 1) / (ports.length + 1);

    return {
        x: node.position.x + NODE_WIDTH,
        y: node.position.y + (NODE_HEIGHT * slot),
    };
};

/**
 * The bezier curve between two ports, as an SVG path's own `d` attribute —
 * a horizontal S so a connection reads left to right whichever way its ends
 * happen to sit.
 *
 * @param {{x: number, y: number}} from
 * @param {{x: number, y: number}} to
 * @returns {string}
 */
export const edgePath = (from, to) => {
    const reach = Math.max(60, Math.abs(to.x - from.x) / 2);

    return `M ${from.x} ${from.y} C ${from.x + reach} ${from.y}, ${to.x - reach} ${to.y}, ${to.x} ${to.y}`;
};

/**
 * A layered layout: the trigger at the left, and every node ranked by how
 * many edges separate it from the trigger, siblings stacked top to bottom.
 * One shot, not a running simulation — the "auto-align" button asks for this
 * once and the canvas keeps whatever the person does with it afterwards.
 *
 * @param {Object[]} nodes
 * @param {Object[]} edges
 * @returns {Object} node key => {x, y}.
 */
export const layered = (nodes, edges) => {
    const depth = new Map();
    const incoming = new Set(edges.map((edge) => edge.to));
    const roots = nodes.filter((node) => !incoming.has(node.key));
    let frontier = roots.map((node) => node.key);
    let rank = 0;

    frontier.forEach((key) => depth.set(key, 0));

    while (frontier.length > 0) {
        const next = [];

        for (const key of frontier) {
            for (const edge of edges.filter((candidate) => candidate.from === key)) {
                if (!depth.has(edge.to)) {
                    depth.set(edge.to, rank + 1);
                    next.push(edge.to);
                }
            }
        }

        frontier = next;
        rank += 1;
    }

    // Anything an edge never reaches (an orphan, or a cycle nothing above
    // walked into) still gets a rank, so it is placed rather than lost.
    nodes.forEach((node) => {
        if (!depth.has(node.key)) {
            depth.set(node.key, rank);
        }
    });

    const columns = new Map();
    const positions = {};

    nodes.forEach((node) => {
        const nodeRank = depth.get(node.key);
        const column = columns.get(nodeRank) || 0;

        positions[node.key] = {
            x: nodeRank * (NODE_WIDTH + 80),
            y: column * (NODE_HEIGHT + 40),
        };
        columns.set(nodeRank, column + 1);
    });

    return positions;
};
