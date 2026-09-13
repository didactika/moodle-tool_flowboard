/**
 * Where the editor page starts: loads what the canvas needs, wires every
 * module to the page's own mount points, and hands control to the person.
 *
 * @module     tool_flowboard/canvas/main
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';
import * as Render from './render';
import * as Interactions from './interactions';
import * as Palette from './palette';
import * as Inspector from './inspector';
import * as Minimap from './minimap';
import * as ListView from './list_view';
import * as History from './history';
import * as Autosave from './autosave';
import * as TestRunner from './test_runner';
import * as LiveStats from './live_stats';
import * as Repository from './repository';
import {preload} from './strings';
import {layered} from './layout';
import {missingFields} from './validation';
import Log from 'core/log';

/**
 * Starts the editor off.
 *
 * @param {number} flowid 0 for a new flow.
 * @returns {Promise<void>}
 */
export const init = async(flowid) => {
    const data = await Repository.getEditorData(flowid);

    await preload(JSON.parse(data.nodetypes));

    State.load({
        graph: JSON.parse(data.graph),
        nodetypes: JSON.parse(data.nodetypes),
        events: JSON.parse(data.events),
        eventfields: JSON.parse(data.eventfields),
    });

    History.init();
    History.watch();

    const root = document.getElementById('tool-flowboard-editor');
    const canvasRoot = root.querySelector('.tool-flowboard-canvas');
    const paletteRoot = root.querySelector('.tool-flowboard-palette');
    const inspectorRoot = root.querySelector('.tool-flowboard-inspector');
    const minimapRoot = root.querySelector('.tool-flowboard-minimap');
    const listRoot = root.querySelector('.tool-flowboard-listview');
    const indicator = root.querySelector('[data-role="autosave-indicator"]');

    let highlight = null;
    const refs = Render.mount(canvasRoot);

    const redraw = () => {
        Render.draw(refs, currentStats, highlight);
        Inspector.render(inspectorRoot, redraw);
        ListView.render(listRoot, redraw);
    };

    let currentStats = {};

    Interactions.attach(refs, redraw);
    Palette.mount(paletteRoot);
    Minimap.mount(minimapRoot, refs.surface);
    Autosave.watch(flowid, indicator);
    LiveStats.watch(flowid, (stats) => {
        currentStats = stats;
        redraw();
    });

    wireToolbar(root, flowid, () => {
        highlight = null;
        redraw();
    }, (nextHighlight) => {
        highlight = nextHighlight;
        redraw();
    });

    wireViewToggle(root, canvasRoot, paletteRoot, inspectorRoot, minimapRoot, listRoot, redraw);

    redraw();
    if (data.haddraft) {
        indicator.textContent = 'Unpublished changes (restored)';
    }
};

/**
 * Undo, redo, auto-align, "probar" and "publish".
 *
 * @param {HTMLElement} root
 * @param {number} flowid
 * @param {Function} clearHighlight
 * @param {Function} setHighlight
 */
const wireToolbar = (root, flowid, clearHighlight, setHighlight) => {
    on(root, 'undo', () => {
        History.undo();
        clearHighlight();
    });

    on(root, 'redo', () => {
        History.redo();
        clearHighlight();
    });

    on(root, 'align', () => {
        const graph = State.getGraph();
        const positions = layered(graph.nodes, graph.edges);

        graph.nodes.forEach((node) => {
            State.moveNode(node.key, positions[node.key].x, positions[node.key].y);
        });
        clearHighlight();
    });

    on(root, 'test', async() => {
        try {
            const result = await TestRunner.run();

            setHighlight(result);
        } catch (error) {
            Log.debug(error);
        }
    });

    on(root, 'publish', async() => {
        const button = root.querySelector('[data-action="publish"]');
        const banner = root.querySelector('[data-role="publish-error"]');

        banner.hidden = true;

        const problem = preflightProblem();

        if (problem !== null) {
            if (problem.nodekey) {
                State.select('node', problem.nodekey);
                clearHighlight();
            }

            banner.textContent = problem.message;
            banner.hidden = false;
            banner.scrollIntoView({block: 'nearest'});

            return;
        }

        button.disabled = true;

        try {
            await Repository.publish(flowid, State.getGraph());
            window.location.href = root.dataset.listurl;
        } catch (error) {
            button.disabled = false;
            Log.debug(error);
            banner.textContent = error.message || String(error);
            banner.hidden = false;
            banner.scrollIntoView({block: 'nearest'});
        }
    });
};

/**
 * Everything this canvas can already tell, before ever asking the server:
 * a missing trigger, more than one, or a node still missing something it
 * needs. Catching it here means the person sees it the instant they try to
 * publish, at the node responsible, rather than reading it back out of a
 * paragraph of prose afterwards.
 *
 * @returns {?{message: string, nodekey: ?string}} Null when there is
 *          nothing this check can already see wrong.
 */
const preflightProblem = () => {
    const graph = State.getGraph();
    const nodeTypes = State.getNodeTypes();
    const triggers = graph.nodes.filter((node) => {
        const meta = nodeTypes[node.type];

        return meta !== undefined && meta.istrigger;
    });

    if (triggers.length === 0) {
        return {message: 'This flow has no trigger yet. Drag one from the palette to begin.', nodekey: null};
    }

    if (triggers.length > 1) {
        return {message: 'More than one trigger is drawn. A flow starts from exactly one.', nodekey: triggers[1].key};
    }

    for (const node of graph.nodes) {
        const meta = nodeTypes[node.type];
        const missing = missingFields(node, meta);

        if (missing.length > 0) {
            return {
                message: `"${meta.label}" is missing: ${missing.join(', ')}.`,
                nodekey: node.key,
            };
        }
    }

    return null;
};

/**
 * One toolbar button, wired once.
 *
 * @param {HTMLElement} root
 * @param {string} action
 * @param {Function} handler
 */
const on = (root, action, handler) => {
    const button = root.querySelector(`[data-action="${action}"]`);

    if (button !== null) {
        button.addEventListener('click', handler);
    }
};

/**
 * Switches between the canvas and the D12 list view — the same state, drawn
 * two different ways, so nothing is lost moving between them.
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} canvasRoot
 * @param {HTMLElement} paletteRoot
 * @param {HTMLElement} inspectorRoot
 * @param {HTMLElement} minimapRoot
 * @param {HTMLElement} listRoot
 * @param {Function} redraw
 */
const wireViewToggle = (root, canvasRoot, paletteRoot, inspectorRoot, minimapRoot, listRoot, redraw) => {
    const buttons = root.querySelectorAll('[data-role="view-toggle"]');

    const show = (view) => {
        const canvasVisible = view === 'canvas';

        canvasRoot.hidden = !canvasVisible;
        paletteRoot.hidden = !canvasVisible;
        inspectorRoot.hidden = !canvasVisible;
        minimapRoot.hidden = !canvasVisible;
        listRoot.hidden = canvasVisible;

        buttons.forEach((button) => button.classList.toggle('active', button.dataset.view === view));

        if (!canvasVisible) {
            redraw();
        }
    };

    buttons.forEach((button) => button.addEventListener('click', () => show(button.dataset.view)));
    show('canvas');
};
