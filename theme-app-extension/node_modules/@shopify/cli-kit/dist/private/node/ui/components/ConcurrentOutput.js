import { handleCtrlC } from '../../ui.js';
import { addOrUpdateConcurrentUIEventOutput } from '../../demo-recorder.js';
import { treeKill } from '../../tree-kill.js';
import useAbortSignal from '../hooks/use-abort-signal.js';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Box, Static, Text, useInput, useStdin, useApp } from 'ink';
import stripAnsi from 'strip-ansi';
import figures from 'figures';
import { Writable } from 'stream';
var ConcurrentOutputState;
(function (ConcurrentOutputState) {
    ConcurrentOutputState["Running"] = "running";
    ConcurrentOutputState["Stopped"] = "stopped";
})(ConcurrentOutputState || (ConcurrentOutputState = {}));
function addLeadingZero(number) {
    if (number < 10) {
        return `0${number}`;
    }
    else {
        return number.toString();
    }
}
function currentTime() {
    const currentDateTime = new Date();
    const hours = addLeadingZero(currentDateTime.getHours());
    const minutes = addLeadingZero(currentDateTime.getMinutes());
    const seconds = addLeadingZero(currentDateTime.getSeconds());
    return `${hours}:${minutes}:${seconds}`;
}
/**
 * Renders output from concurrent processes to the terminal.
 * Output will be divided in a three column layout
 * with the left column containing the timestamp,
 * the right column containing the output,
 * and the middle column containing the process prefix.
 * Every process will be rendered with a different color, up to 4 colors.
 *
 * For example running `shopify app dev`:
 *
 * ```shell
 * 2022-10-10 13:11:03 | backend    | npm
 * 2022-10-10 13:11:03 | backend    |  WARN ignoring workspace config at ...
 * 2022-10-10 13:11:03 | backend    |
 * 2022-10-10 13:11:03 | backend    |
 * 2022-10-10 13:11:03 | backend    | > shopify-app-template-node@0.1.0 dev
 * 2022-10-10 13:11:03 | backend    | > cross-env NODE_ENV=development nodemon backend/index.js --watch ./backend
 * 2022-10-10 13:11:03 | backend    |
 * 2022-10-10 13:11:03 | backend    |
 * 2022-10-10 13:11:03 | frontend   |
 * 2022-10-10 13:11:03 | frontend   | > starter-react-frontend-app@0.1.0 dev
 * 2022-10-10 13:11:03 | frontend   | > cross-env NODE_ENV=development node vite-server.js
 * 2022-10-10 13:11:03 | frontend   |
 * 2022-10-10 13:11:03 | frontend   |
 * 2022-10-10 13:11:03 | backend    |
 * 2022-10-10 13:11:03 | backend    | [nodemon] to restart at any time, enter `rs`
 * 2022-10-10 13:11:03 | backend    | [nodemon] watching path(s): backend/
 * 2022-10-10 13:11:03 | backend    | [nodemon] watching extensions: js,mjs,json
 * 2022-10-10 13:11:03 | backend    | [nodemon] starting `node backend/index.js`
 * 2022-10-10 13:11:03 | backend    |
 *
 * ```
 */
const ConcurrentOutput = ({ processes, abortSignal, showTimestamps = true, onInput, footer, keepRunningAfterProcessesResolve, }) => {
    const [processOutput, setProcessOutput] = useState([]);
    const { exit: unmountInk } = useApp();
    const prefixColumnSize = Math.max(...processes.map((process) => process.prefix.length));
    const { isRawModeSupported } = useStdin();
    const [state, setState] = useState(ConcurrentOutputState.Running);
    const concurrentColors = useMemo(() => ['yellow', 'cyan', 'magenta', 'green', 'blue'], []);
    const lineColor = useCallback((index) => {
        const colorIndex = index < concurrentColors.length ? index : index % concurrentColors.length;
        return concurrentColors[colorIndex];
    }, [concurrentColors]);
    const writableStream = useCallback((process, index) => {
        return new Writable({
            write(chunk, _encoding, next) {
                const lines = stripAnsi(chunk.toString('utf8').replace(/(\n)$/, '')).split(/\n/);
                addOrUpdateConcurrentUIEventOutput({ prefix: process.prefix, index, output: lines.join('\n') }, { footer });
                setProcessOutput((previousProcessOutput) => [
                    ...previousProcessOutput,
                    {
                        color: lineColor(index),
                        prefix: process.prefix,
                        lines,
                    },
                ]);
                next();
            },
        });
    }, [footer, lineColor]);
    const { isAborted } = useAbortSignal(abortSignal);
    const useShortcuts = isRawModeSupported && state === ConcurrentOutputState.Running && !isAborted;
    useInput((input, key) => {
        handleCtrlC(input, key);
        onInput(input, key, () => treeKill('SIGINT'));
    }, { isActive: typeof onInput !== 'undefined' && useShortcuts });
    useEffect(() => {
        ;
        (() => {
            return Promise.all(processes.map(async (process, index) => {
                const stdout = writableStream(process, index);
                const stderr = writableStream(process, index);
                await process.action(stdout, stderr, abortSignal);
            }));
        })()
            .then(() => {
            if (!keepRunningAfterProcessesResolve) {
                setState(ConcurrentOutputState.Stopped);
                unmountInk();
            }
        })
            .catch((error) => {
            setState(ConcurrentOutputState.Stopped);
            unmountInk(error);
        });
    }, [abortSignal, processes, writableStream, unmountInk, keepRunningAfterProcessesResolve]);
    const { lineVertical } = figures;
    return (React.createElement(React.Fragment, null,
        React.createElement(Static, { items: processOutput }, (chunk, index) => {
            const prefixBuffer = ' '.repeat(prefixColumnSize - chunk.prefix.length);
            return (React.createElement(Box, { flexDirection: "column", key: index }, chunk.lines.map((line, index) => (React.createElement(Box, { key: index, flexDirection: "row" },
                React.createElement(Text, { color: chunk.color },
                    showTimestamps ? (React.createElement(Text, null,
                        currentTime(),
                        " ",
                        lineVertical,
                        ' ')) : null,
                    React.createElement(Text, null,
                        chunk.prefix,
                        prefixBuffer,
                        " ",
                        lineVertical,
                        " ",
                        line)))))));
        }),
        footer ? (React.createElement(Box, { marginY: 1, flexDirection: "column", flexGrow: 1 },
            useShortcuts ? (React.createElement(Box, { flexDirection: "column" }, footer.shortcuts.map((shortcut, index) => (React.createElement(Text, { key: index },
                figures.pointerSmall,
                " Press ",
                React.createElement(Text, { bold: true }, shortcut.key),
                " ",
                figures.lineVertical,
                " ",
                shortcut.action))))) : null,
            footer.subTitle ? (React.createElement(Box, { marginTop: useShortcuts ? 1 : 0 },
                React.createElement(Text, null, footer.subTitle))) : null)) : null));
};
export { ConcurrentOutput };
//# sourceMappingURL=ConcurrentOutput.js.map