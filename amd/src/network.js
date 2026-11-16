// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Connection policy for web service calls: tells a lost connection apart from a server error,
 * applies timeouts, and provides the retry, stall and single-flight helpers that let polling
 * and page loads survive short network interruptions.
 *
 * Only idempotent reads and polls may be repeated through retryRead(), a retry window or a
 * single-flight loop. Calls that charge credits or create records are sent once.
 *
 * @module     mod_simplequiz2/network
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Prefetch from 'core/prefetch';

/** Timeout of status, poll and read calls. */
export const TIMEOUT_STATUS_MS = 20000;

/** Timeout of one-shot mutating calls. */
export const TIMEOUT_ACTION_MS = 120000;

/** No timeout, for synchronous long-running work. */
export const TIMEOUT_NONE = 0;

/** How long a polling loop keeps going through connection failures. */
export const RETRY_GRACE_MS = 90000;

/** Stall delay when the server never reported a start. */
export const STALL_START_MS = 60000;

/** Stall delay when the server reported progress that stopped changing. */
export const STALL_PROGRESS_MS = 600000;

/** Retries of a read after the first try. */
export const READ_RETRY_ATTEMPTS = 4;

/** Delay before retry n of a read is READ_RETRY_DELAY_MS * n. */
export const READ_RETRY_DELAY_MS = 2000;

// Strings and templates needed to report a failure are loaded while the connection works.
Prefetch.prefetchStrings('core', ['ok']);
Prefetch.prefetchTemplates(['core/local/modal/alert']);
import('core/local/modal/alert').catch(() => null);

/**
 * A failure of the connection, as opposed to an error returned by the server.
 */
class ConnectionError extends Error {
    /**
     * @param {*} cause Original rejection value
     * @param {string} reason 'network', 'timeout', 'parse' or 'abort'
     */
    constructor(cause, reason) {
        super('');
        this.name = 'ConnectionError';
        this.connection = true;
        this.reason = reason;
        this.cause = cause;
    }
}

/**
 * Whether a value is already a tagged connection error.
 *
 * @param {*} err
 * @returns {boolean}
 */
const isTagged = (err) => err instanceof ConnectionError ||
    (!!err && typeof err === 'object' && err.connection === true && err.name === 'ConnectionError');

/**
 * Build a tagged connection error.
 *
 * @param {*} cause Original rejection value
 * @param {string} [reason='network'] 'network', 'timeout', 'parse' or 'abort'
 * @returns {ConnectionError}
 */
export const connectionError = (cause, reason = 'network') => {
    if (isTagged(cause)) {
        return cause;
    }
    return new ConnectionError(cause, reason);
};

/**
 * Whether a failure comes from the connection rather than from the server.
 *
 * A Moodle exception carries an errorcode. A lost connection makes core/ajax reject with a
 * string ('', 'timeout', 'abort', an HTTP status text), a parse error (proxy error page) or
 * the bare jqXHR. Any other Error, such as a TypeError thrown by handler code, is not a
 * connection failure.
 *
 * @param {*} err Rejection value
 * @returns {boolean}
 */
export const isConnectionError = (err) => {
    if (isTagged(err)) {
        return true;
    }
    if (err === null || err === undefined || typeof err !== 'object') {
        return true;
    }
    if (err.errorcode) {
        return false;
    }
    if (err instanceof SyntaxError) {
        return true;
    }
    if (err instanceof Error) {
        return false;
    }
    return !err.message;
};

/**
 * The reason recorded on a connection error for a core/ajax rejection.
 *
 * @param {*} err
 * @returns {string}
 */
const reasonFor = (err) => {
    if (err === 'timeout') {
        return 'timeout';
    }
    if (err instanceof SyntaxError || err === 'parsererror') {
        return 'parse';
    }
    if (err === 'abort') {
        return 'abort';
    }
    return 'network';
};

/**
 * Turn a core/ajax deferred into a native promise that rejects with a tagged connection error
 * on a connection failure and with the server error unchanged otherwise.
 *
 * @param {object} deferred jQuery promise returned by core/ajax
 * @returns {Promise}
 */
const settle = (deferred) => Promise.resolve(deferred).catch((failure) => {
    throw isConnectionError(failure) ? connectionError(failure, reasonFor(failure)) : failure;
});

/**
 * Call several web services in one request, each with the same timeout. Never resends.
 *
 * @param {object[]} requests [{methodname, args}]
 * @param {object} [options]
 * @param {number} [options.timeout=TIMEOUT_STATUS_MS] Milliseconds, TIMEOUT_NONE for no limit
 * @param {boolean} [options.loginrequired=true]
 * @param {boolean} [options.nosessionupdate=false]
 * @returns {Promise[]} Native promises, in the order of the requests
 */
export const callMany = (requests, {timeout = TIMEOUT_STATUS_MS, loginrequired = true, nosessionupdate = false} = {}) =>
    Ajax.call(requests, true, loginrequired, nosessionupdate, timeout).map(settle);

/**
 * Call one web service with a timeout, so a request lost on the network does not hang.
 * Never resends.
 *
 * @param {object} request {methodname, args}
 * @param {object} [options] See callMany()
 * @returns {Promise}
 */
export const call = (request, options = {}) => callMany([request], options)[0];

/**
 * Fetch a URL with a timeout. A network failure or an abort rejects with a tagged connection
 * error; any HTTP response resolves unchanged so the caller handles its status.
 *
 * @param {string} url
 * @param {object} [init] fetch() options
 * @param {number} [timeout=TIMEOUT_ACTION_MS] Milliseconds, TIMEOUT_NONE for no limit
 * @returns {Promise<Response>}
 */
export const fetchWithTimeout = (url, init = {}, timeout = TIMEOUT_ACTION_MS) => {
    const controller = new AbortController();
    let timedOut = false;
    let timer = null;
    if (timeout > 0) {
        timer = setTimeout(() => {
            timedOut = true;
            controller.abort();
        }, timeout);
    }
    if (init.signal) {
        if (init.signal.aborted) {
            controller.abort();
        } else {
            init.signal.addEventListener('abort', () => controller.abort());
        }
    }
    return fetch(url, Object.assign({}, init, {signal: controller.signal})).then((response) => {
        clearTimeout(timer);
        return response;
    }, (err) => {
        clearTimeout(timer);
        if (timedOut) {
            throw connectionError(err, 'timeout');
        }
        if (err && err.name === 'AbortError') {
            throw connectionError(err, 'abort');
        }
        if (err instanceof TypeError) {
            throw connectionError(err, 'network');
        }
        throw err;
    });
};

/**
 * @param {number} ms
 * @returns {Promise}
 */
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Run an idempotent read, retrying it on connection failures only.
 *
 * @param {Function} fn Returns a promise; must be an idempotent read
 * @param {object} [options]
 * @param {number} [options.attempts=READ_RETRY_ATTEMPTS] Retries after the first try
 * @param {number} [options.delayMs=READ_RETRY_DELAY_MS] Delay before retry n is delayMs * n
 * @param {Function} [options.isCancelled] Stops retrying when it returns true
 * @returns {Promise}
 */
export const retryRead = async(fn, {attempts = READ_RETRY_ATTEMPTS, delayMs = READ_RETRY_DELAY_MS,
        isCancelled = () => false} = {}) => {
    for (let retry = 0; ; retry++) {
        try {
            return await fn();
        } catch (failure) {
            if (!isConnectionError(failure) || retry >= attempts || isCancelled()) {
                throw failure;
            }
            await wait(delayMs * (retry + 1));
            if (isCancelled()) {
                throw failure;
            }
        }
    }
};

/**
 * Track consecutive connection failures of a polling loop.
 *
 * shouldRetry() is false for a server error, and true for a connection error until the first
 * failure of the current streak is graceMs old. succeeded() ends the streak.
 *
 * @param {object} [options]
 * @param {number} [options.graceMs=RETRY_GRACE_MS]
 * @param {Function} [options.now=Date.now]
 * @returns {{succeeded: Function, shouldRetry: Function}}
 */
export const createRetryWindow = ({graceMs = RETRY_GRACE_MS, now = Date.now} = {}) => {
    let firstFailureAt = null;
    return {
        succeeded() {
            firstFailureAt = null;
        },
        shouldRetry(err) {
            if (!isConnectionError(err)) {
                return false;
            }
            const time = now();
            if (firstFailureAt === null) {
                firstFailureAt = time;
            }
            return time - firstFailureAt < graceMs;
        },
    };
};

/**
 * Detect server work that no longer advances.
 *
 * The caller builds the signature from its own progress fields. stalled() is true after
 * startMs when the work was never reported as started, otherwise after stallMs without a
 * signature change.
 *
 * @param {object} [options]
 * @param {number} [options.startMs=STALL_START_MS]
 * @param {number} [options.stallMs=STALL_PROGRESS_MS]
 * @param {Function} [options.now=Date.now]
 * @returns {{observe: Function, stalled: Function}}
 */
export const createStallWatch = ({startMs = STALL_START_MS, stallMs = STALL_PROGRESS_MS, now = Date.now} = {}) => {
    let signature = null;
    let started = false;
    let changedAt = now();
    return {
        observe(next, isStarted) {
            if (next !== signature) {
                signature = next;
                changedAt = now();
            }
            if (isStarted) {
                started = true;
            }
        },
        stalled() {
            return now() - changedAt >= (started ? stallMs : startMs);
        },
    };
};

/**
 * Make sure only one run of fn is pending at a time.
 *
 * Calls made while a run is pending return its promise. With trailing, they instead queue a
 * single further run with the latest arguments, started once the pending run settles.
 *
 * @param {Function} fn Returns a promise
 * @param {object} [options]
 * @param {boolean} [options.trailing=false]
 * @returns {Function}
 */
export const singleFlight = (fn, {trailing = false} = {}) => {
    let pending = null;
    let queued = null;

    const run = (args) => {
        const promise = Promise.resolve().then(() => fn(...args));
        pending = promise;
        const clear = () => {
            if (pending === promise) {
                pending = null;
            }
        };
        promise.then(clear, clear).catch(() => null);
        return promise;
    };

    return (...args) => {
        if (!pending) {
            return run(args);
        }
        if (!trailing) {
            return pending;
        }
        if (queued) {
            queued.args = args;
            return queued.promise;
        }
        const next = {args};
        next.promise = pending.then(() => null, () => null).then(() => {
            queued = null;
            return run(next.args);
        });
        queued = next;
        return next.promise;
    };
};

/**
 * Escape text for an HTML sink.
 *
 * @param {*} text
 * @returns {string}
 */
const escapeHtml = (text) => {
    const element = document.createElement('div');
    element.textContent = String(text);
    return element.innerHTML;
};

/**
 * Messages and alerts bound to one component.
 *
 * @param {object} options
 * @param {string} options.component Frankenstyle component of the strings
 * @param {string} [options.connectionKey='error_connection'] String shown for a lost connection
 * @param {string[]} [options.keys] Further strings to load while the connection works
 * @returns {object}
 */
export const forComponent = ({component, connectionKey = 'error_connection', keys = []}) => {
    Prefetch.prefetchStrings(component, [connectionKey, ...keys]);

    const message = (key, a) => getString(key, component, a);

    const alertString = (key, a) => message(key, a).then((text) => Notification.alert('', text)).catch(() => null);

    const notifyConnection = (key = connectionKey) => alertString(key);

    return {
        component,
        connectionMessage: () => message(connectionKey),
        message,
        alertString,
        notifyConnection,
        notifyError(failure, fallbackKey) {
            if (isConnectionError(failure)) {
                return notifyConnection();
            }
            if (failure.message) {
                return Promise.resolve(Notification.alert('', escapeHtml(failure.message))).catch(() => null);
            }
            return alertString(fallbackKey || connectionKey);
        },
        exception(err) {
            if (isConnectionError(err)) {
                return notifyConnection();
            }
            return Notification.exception(err);
        },
        handle(err, onServerError) {
            if (isConnectionError(err)) {
                return notifyConnection();
            }
            return onServerError(err);
        },
    };
};
