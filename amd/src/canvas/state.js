/**
 * The one place the canvas keeps what is drawn.
 *
 * Everything else reads this and writes through it — no module reaches into
 * another's own data, which is what lets the list view (§ D12) and the
 * canvas draw the very same flow from the very same state.
 *
 * @module     tool_flowboard/canvas/state
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let graph = {nodes: [], edges: [], comments: []};
let nodeTypes = {};
let events = {};
let eventFields = {};
let selection = null;
let dirty = false;
let counter = 0;

const listeners = new Set();

/**
 * Tells every listener the state has changed. Nothing about what changed is
 * passed along on purpose: a listener reads what it needs from the state
 * itself, which is what keeps this simple enough to trust.
 */
const notify = () => listeners.forEach((fn) => fn());

/**
 * Called whenever the state changes, for as long as the return value is not
 * invoked.
 *
 * @param {Function} fn
 * @returns {Function} Stops listening.
 */
export const subscribe = (fn) => {
    listeners.add(fn);

    return () => listeners.delete(fn);
};

/**
 * The drawing, as it stands right now.
 *
 * @returns {Object}
 */
export const getGraph = () => graph;

/**
 * Every kind of node the palette may offer.
 *
 * @returns {Object}
 */
export const getNodeTypes = () => nodeTypes;

/**
 * The event catalogue.
 *
 * @returns {Object}
 */
export const getEvents = () => events;

/**
 * What is known of each event's own fields.
 *
 * @param {string} eventname
 * @returns {string[]}
 */
export const getEventFields = (eventname) => eventFields[eventname] || [];

/**
 * What is selected right now: {kind: 'node'|'comment', key}, or null.
 *
 * @returns {?Object}
 */
export const getSelection = () => selection;

/**
 * Whether there is unpublished work.
 *
 * @returns {boolean}
 */
export const isDirty = () => dirty;

/**
 * Starts the canvas off with what the server sent.
 *
 * @param {Object} data From {@see repository.getEditorData}.
 */
export const load = (data) => {
    graph = {
        nodes: data.graph.nodes || [],
        edges: data.graph.edges || [],
        comments: data.graph.comments || [],
    };
    nodeTypes = data.nodetypes;
    events = data.events;
    eventFields = data.eventfields;
    selection = null;
    dirty = false;
    notify();
};

/**
 * Replaces the whole drawing at once — what undo and redo do.
 *
 * @param {Object} newGraph
 */
export const replaceGraph = (newGraph) => {
    graph = {
        nodes: newGraph.nodes || [],
        edges: newGraph.edges || [],
        comments: newGraph.comments || [],
    };
    notify();
};

/**
 * Marks the drawing as changed since it was last saved.
 */
export const markDirty = () => {
    dirty = true;
    notify();
};

/**
 * Marks the drawing as matching what was last saved or published.
 */
export const markClean = () => {
    dirty = false;
    notify();
};

/**
 * A key nothing in this drawing is using yet.
 *
 * @param {string} type
 * @returns {string}
 */
const uniqueKey = (type) => {
    const taken = new Set(graph.nodes.map((node) => node.key));
    let key = `${type}_${(counter += 1)}`;

    while (taken.has(key)) {
        key = `${type}_${(counter += 1)}`;
    }

    return key;
};

/**
 * One node, by its key.
 *
 * @param {string} key
 * @returns {?Object}
 */
export const nodeByKey = (key) => graph.nodes.find((node) => node.key === key) || null;

/**
 * Adds a node of one kind, at one spot, and selects it.
 *
 * @param {string} type
 * @param {number} x
 * @param {number} y
 * @returns {string} The new node's key.
 */
export const addNode = (type, x, y) => {
    const key = uniqueKey(type);

    graph.nodes.push({key, type, config: {}, position: {x, y}});
    markDirty();
    select('node', key);

    return key;
};

/**
 * Moves a node, dragged on the canvas.
 *
 * @param {string} key
 * @param {number} x
 * @param {number} y
 */
export const moveNode = (key, x, y) => {
    const node = nodeByKey(key);

    if (node === null) {
        return;
    }

    node.position = {x, y};
    notify();
};

/**
 * Replaces a node's own configuration.
 *
 * @param {string} key
 * @param {Object} config
 */
export const setNodeConfig = (key, config) => {
    const node = nodeByKey(key);

    if (node === null) {
        return;
    }

    node.config = config;
    markDirty();
};

/**
 * Removes a node, and every edge that touched it.
 *
 * @param {string} key
 */
export const removeNode = (key) => {
    graph.nodes = graph.nodes.filter((node) => node.key !== key);
    graph.edges = graph.edges.filter((edge) => edge.from !== key && edge.to !== key);

    if (selection && selection.kind === 'node' && selection.key === key) {
        selection = null;
    }

    markDirty();
};

/**
 * Draws a connection from one node's port to another node.
 *
 * Refuses what could never be a drawing: a node connecting to itself, or a
 * second edge leaving the same port (a port is one way out, not several).
 *
 * @param {string} from
 * @param {string} port
 * @param {string} to
 * @returns {boolean} Whether the edge was actually added.
 */
export const addEdge = (from, port, to) => {
    if (from === to) {
        return false;
    }

    const toNode = nodeByKey(to);
    const toMeta = toNode === null ? undefined : nodeTypes[toNode.type];

    if (toMeta !== undefined && toMeta.istrigger) {
        // A trigger is where a run begins; nothing may lead into one.
        return false;
    }

    if (graph.edges.some((edge) => edge.from === from && edge.port === port)) {
        return false;
    }

    graph.edges.push({from, port, to});
    markDirty();

    return true;
};

/**
 * Removes one edge.
 *
 * @param {string} from
 * @param {string} port
 */
export const removeEdge = (from, port) => {
    graph.edges = graph.edges.filter((edge) => !(edge.from === from && edge.port === port));
    markDirty();
};

/**
 * Adds a sticky note.
 *
 * @param {number} x
 * @param {number} y
 * @returns {string} The comment's id.
 */
export const addComment = (x, y) => {
    const id = `comment_${(counter += 1)}`;

    graph.comments.push({id, x, y, text: ''});
    markDirty();
    select('comment', id);

    return id;
};

/**
 * Moves a sticky note.
 *
 * @param {string} id
 * @param {number} x
 * @param {number} y
 */
export const moveComment = (id, x, y) => {
    const comment = graph.comments.find((entry) => entry.id === id);

    if (comment === undefined) {
        return;
    }

    comment.x = x;
    comment.y = y;
    notify();
};

/**
 * Rewrites a sticky note's own text.
 *
 * @param {string} id
 * @param {string} text
 */
export const setCommentText = (id, text) => {
    const comment = graph.comments.find((entry) => entry.id === id);

    if (comment === undefined) {
        return;
    }

    comment.text = text;
    markDirty();
};

/**
 * Removes a sticky note.
 *
 * @param {string} id
 */
export const removeComment = (id) => {
    graph.comments = graph.comments.filter((entry) => entry.id !== id);

    if (selection && selection.kind === 'comment' && selection.key === id) {
        selection = null;
    }

    markDirty();
};

/**
 * Selects one node or comment, or clears the selection with no arguments.
 *
 * @param {?string} kind 'node' or 'comment'.
 * @param {?string} key
 */
export const select = (kind = null, key = null) => {
    selection = kind === null ? null : {kind, key};
    notify();
};

/**
 * Every node that could feed this one: everything reachable by walking edges
 * backwards from it, so the inspector's "map from…" picker only ever offers
 * a field that has actually run by the time this node does.
 *
 * @param {string} key
 * @returns {Object[]} Node rows, upstream-most first.
 */
export const ancestorsOf = (key) => {
    const found = [];
    const seen = new Set();
    let frontier = [key];

    while (frontier.length > 0) {
        const next = [];

        for (const current of frontier) {
            for (const edge of graph.edges.filter((candidate) => candidate.to === current)) {
                if (seen.has(edge.from)) {
                    continue;
                }

                seen.add(edge.from);
                const node = nodeByKey(edge.from);

                if (node !== null) {
                    found.push(node);
                    next.push(edge.from);
                }
            }
        }

        frontier = next;
    }

    return found.reverse();
};
