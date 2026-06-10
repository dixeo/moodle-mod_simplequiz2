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
 * DOM helpers for Moodle HTML editors (TinyMCE default; legacy Atto still supported on M4).
 *
 * @module      mod_simplequiz2/editor_helpers
 * @copyright   2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'editor_tiny/editor',
    'editor_tiny/options',
    'core/ajax',
    'tiny_autosave/options',
], function(Tiny, Options, Ajax, AutosaveOptions) {

    /** @type {Object|null} TinyMCE base options from PHP (includes plugins). */
    let tinyBaseOptions = null;

    /** @type {Map<string, Promise<void>>} In-flight slot resets keyed by question id. */
    const resetSlotPromises = new Map();

    /**
     * @param {Object} options Base TinyMCE options from mod_form.php.
     */
    const setTinyBaseOptions = (options) => {
        tinyBaseOptions = options;
    };

    /**
     * @param {HTMLTextAreaElement} textarea
     * @return {Object|null}
     */
    const getReferenceEditor = (textarea) => {
        const refTextarea = document.querySelector(
            '.simplequiz2-question-block.simplequiz2-editing textarea[id$="_text"]'
        );
        if (!refTextarea || refTextarea === textarea) {
            return null;
        }
        return Tiny.getInstanceForElement(refTextarea);
    };

    /**
     * Read draft item id from the companion hidden input rendered with deferred editor fields.
     *
     * @param {HTMLTextAreaElement} textarea
     * @return {number}
     */
    const getDraftItemIdFromForm = (textarea) => {
        const name = textarea.getAttribute('name');
        if (!name) {
            return 0;
        }
        const itemidName = name.replace(/\[text\]$/, '[itemid]');
        const input = document.querySelector(`input[name="${itemidName}"]`);
        return input ? parseInt(input.value, 10) || 0 : 0;
    };

    /**
     * Update the companion [itemid] hidden input for a deferred editor field.
     *
     * @param {string} elementId Element id without id_ prefix.
     * @param {number} itemid New draft item id.
     */
    const setDraftItemIdForElement = (elementId, itemid) => {
        const textarea = document.getElementById('id_' + elementId);
        if (!textarea) {
            return;
        }
        const name = textarea.getAttribute('name');
        if (!name || !name.endsWith('[text]')) {
            return;
        }
        const itemidInput = document.querySelector(`input[name="${name.replace(/\[text\]$/, '[itemid]')}"]`);
        if (itemidInput) {
            itemidInput.value = String(itemid);
        }
    };

    /**
     * Allocate fresh Moodle user draft item ids.
     *
     * @param {number} count Number of ids required.
     * @return {Promise<number[]>}
     */
    const fetchUnusedDraftItemIds = async(count) => {
        if (count < 1) {
            return [];
        }
        try {
            const requests = Array.from({length: count}, () => ({
                methodname: 'mod_simplequiz2_get_unused_draft_itemids',
                args: {},
            }));
            const responses = await Promise.all(Ajax.call(requests));
            const itemids = responses.map((response) => {
                if (!response) {
                    return null;
                }
                const raw = response.itemid ?? (
                    Array.isArray(response.itemids) && response.itemids.length > 0 ? response.itemids[0] : null
                );
                const itemid = parseInt(raw, 10);
                return itemid > 0 ? itemid : null;
            }).filter((itemid) => itemid !== null);
            return itemids;
        } catch (e) {
            return [];
        }
    };

    /**
     * Clear all editors in a question slot and point each at a fresh empty draft area.
     *
     * @param {string} questionId Question index.
     * @return {Promise<void>}
     */
    const resetQuestionSlotEditors = async(questionId) => {
        if (resetSlotPromises.has(questionId)) {
            await resetSlotPromises.get(questionId);
            return;
        }

        const resetPromise = (async() => {
            const elementIds = getEditorElementIds(questionId);

            await clearAutosaveSessionsForQuestion(questionId);
            await removeEditorsForQuestion(questionId);

            const itemids = await fetchUnusedDraftItemIds(elementIds.length);
            elementIds.forEach((elementId, index) => {
                setEditorHtml(elementId, '');
                const newItemid = itemids[index] || null;
                if (newItemid) {
                    setDraftItemIdForElement(elementId, newItemid);
                }
            });
        })();

        resetSlotPromises.set(questionId, resetPromise);
        try {
            await resetPromise;
        } finally {
            resetSlotPromises.delete(questionId);
        }
    };

    /**
     * Build full setup options for TinyMCE (partial options break plugin loading).
     *
     * @param {HTMLTextAreaElement} textarea
     * @return {Object|null}
     */
    const buildSetupOptions = (textarea) => {
        if (!tinyBaseOptions || !tinyBaseOptions.plugins) {
            return null;
        }
        const inst = Tiny.getInstanceForElement(textarea);
        const refInst = getReferenceEditor(textarea);
        return {
            css: tinyBaseOptions.css,
            context: inst ? Options.getContextId(inst) : tinyBaseOptions.context,
            draftitemid: getDraftItemIdFromForm(textarea),
            filepicker: inst ? Options.getFilepickers(inst) : {},
            currentLanguage: refInst ? Options.getCurrentLanguage(refInst) : (tinyBaseOptions.currentLanguage || 'en'),
            language: refInst ? Options.getMoodleLang(refInst) : {},
            placeholderSelectors: [],
            plugins: tinyBaseOptions.plugins,
            branding: tinyBaseOptions.branding !== undefined ? tinyBaseOptions.branding : true,
            extended_valid_elements: tinyBaseOptions.extended_valid_elements || 'script[*],p[*],i[*]',
        };
    };

    /**
     * Match Moodle adjustEditorSize but enforce a usable minimum when the editor was inited hidden.
     *
     * @param {Object} editor TinyMCE instance
     * @param {HTMLTextAreaElement} textarea
     */
    const applyEditorSizeFix = (editor, textarea) => {
        const wrap = editor.getContainer()?.querySelector('.tox-sidebar-wrap');
        if (!wrap) {
            return;
        }
        const lineHeight = parseFloat(window.getComputedStyle(textarea).lineHeight) || 22;
        const rows = parseInt(textarea.getAttribute('rows'), 10) || 1;
        const fromRows = rows * lineHeight;
        const fromTarget = textarea.clientHeight || 0;
        const minEditing = textarea.id.includes('_answers_') ? 66 : 88;
        const expected = Math.max(fromTarget, fromRows, minEditing);
        wrap.style.height = `${expected}px`;
        textarea.removeAttribute('readonly');
        editor.mode.set('design');
        textarea.dispatchEvent(new Event('form:editorUpdated'));
    };

    /**
     * Remove TinyMCE for one textarea (Moodle removes on next tick to avoid Firefox errors).
     *
     * @param {HTMLTextAreaElement|null} textarea
     * @return {Promise<void>}
     */
    const removeEditorForTextarea = async(textarea) => {
        if (!textarea) {
            return;
        }
        delete textarea.dataset.simplequiz2ValidationBound;
        const editor = Tiny.getInstanceForElement(textarea);
        if (!editor) {
            return;
        }
        try {
            await Promise.all(Ajax.call([{
                methodname: 'tiny_autosave_reset_session',
                args: {
                    contextid: AutosaveOptions.getContextId(editor),
                    pagehash: AutosaveOptions.getPageHash(editor),
                    pageinstance: AutosaveOptions.getPageInstance(editor),
                    elementid: editor.targetElm.id,
                },
            }]));
        } catch (e) {
            // Autosave reset is best-effort; continue tearing down the editor.
        }
        editor.save();
        await new Promise((resolve) => {
            editor.once('remove', resolve);
            setTimeout(() => {
                if (!editor.removed) {
                    editor.remove();
                } else {
                    resolve();
                }
            }, 0);
        });
    };

    /**
     * All editor element ids for one question (without id_ prefix).
     *
     * @param {string} questionId Question index
     * @return {string[]}
     */
    const getEditorElementIds = (questionId) => {
        const ids = [questionTextElementId(questionId)];
        for (let answerId = 0; answerId < 5; answerId++) {
            ids.push(answerElementId(questionId, answerId));
        }
        ids.push(correctFeedbackElementId(questionId));
        ids.push(partiallyCorrectFeedbackElementId(questionId));
        ids.push(incorrectFeedbackElementId(questionId));
        return ids;
    };

    /**
     * Resolve Tiny Autosave session parameters for a question slot.
     *
     * @param {string} questionId Question index.
     * @return {{contextid: number, pagehash: string, pageinstance: string}|null}
     */
    const getAutosaveSessionContext = (questionId) => {
        for (const elementId of getEditorElementIds(questionId)) {
            const textarea = getTextarea(elementId);
            const editor = textarea ? Tiny.getInstanceForElement(textarea) : null;
            if (editor) {
                return {
                    contextid: AutosaveOptions.getContextId(editor),
                    pagehash: AutosaveOptions.getPageHash(editor),
                    pageinstance: AutosaveOptions.getPageInstance(editor),
                };
            }
        }

        const anyTextarea = document.querySelector('textarea.simplequiz2-deferred-editor');
        if (anyTextarea) {
            const editor = Tiny.getInstanceForElement(anyTextarea);
            if (editor) {
                return {
                    contextid: AutosaveOptions.getContextId(editor),
                    pagehash: AutosaveOptions.getPageHash(editor),
                    pageinstance: AutosaveOptions.getPageInstance(editor),
                };
            }
        }

        if (tinyBaseOptions && tinyBaseOptions.context) {
            const autosaveConfig = tinyBaseOptions.plugins?.['tiny_autosave/plugin']?.config || {};
            return {
                contextid: tinyBaseOptions.context,
                pagehash: autosaveConfig.pagehash || '',
                pageinstance: autosaveConfig.pageinstance || '',
            };
        }

        return null;
    };

    /**
     * Delete Tiny Autosave drafts for every editor field in a question slot.
     *
     * @param {string} questionId Question index.
     * @return {Promise<void>}
     */
    const clearAutosaveSessionsForQuestion = async(questionId) => {
        const sessionContext = getAutosaveSessionContext(questionId);
        if (!sessionContext) {
            return;
        }

        const requests = getEditorElementIds(questionId).map((elementId) => ({
            methodname: 'tiny_autosave_reset_session',
            args: {
                contextid: sessionContext.contextid,
                pagehash: sessionContext.pagehash,
                pageinstance: sessionContext.pageinstance,
                elementid: 'id_' + elementId,
            },
        }));

        await Promise.all(Ajax.call(requests));
    };

    /**
     * Tear down TinyMCE for all editors in a question slot.
     *
     * @param {string} questionId Question index
     * @return {Promise<void>}
     */
    const removeEditorsForQuestion = async(questionId) => {
        await Promise.all(getEditorElementIds(questionId).map((elementId) =>
            removeEditorForTextarea(getTextarea(elementId))
        ));
    };

    /**
     * Initialise TinyMCE on a textarea that does not yet have an instance.
     *
     * @param {HTMLTextAreaElement} textarea
     * @return {Promise<boolean>}
     */
    const initEditorForTextarea = async(textarea) => {
        if (Tiny.getInstanceForElement(textarea)) {
            return true;
        }
        const options = buildSetupOptions(textarea);
        if (!options) {
            return false;
        }
        try {
            const editor = await Tiny.setupForTarget(textarea, options);
            applyEditorSizeFix(editor, textarea);
            return true;
        } catch (e) {
            return false;
        }
    };

    /**
     * Bind validation refresh on a textarea (and its TinyMCE instance when present).
     *
     * @param {HTMLTextAreaElement|null} textarea
     * @param {Function} handler
     */
    const bindEditorValidationListener = (textarea, handler) => {
        if (!textarea || textarea.dataset.simplequiz2ValidationBound === '1') {
            return;
        }
        textarea.dataset.simplequiz2ValidationBound = '1';
        textarea.addEventListener('change', handler);
        textarea.addEventListener('input', handler);
        textarea.addEventListener('form:editorUpdated', handler);
        const editor = Tiny.getInstanceForElement(textarea);
        if (editor) {
            editor.on('input', handler);
            editor.on('change', handler);
            editor.on('SetContent', handler);
            editor.on('keyup', handler);
            editor.on('NodeChange', handler);
        }
    };

    /**
     * Initialise TinyMCE editors for one question in edit mode.
     *
     * @param {string} questionId Question index
     * @param {Function|null} [onAnswerChange] Called when an answer field changes (for form validation).
     * @return {Promise<void>}
     */
    const initEditorsForQuestion = async(questionId, onAnswerChange = null) => {
        const textareas = [];
        getEditorElementIds(questionId).forEach((elementId) => {
            const textarea = getTextarea(elementId);
            if (textarea) {
                textareas.push(textarea);
            }
        });

        for (const textarea of textareas) {
            const elementId = textarea.id.replace(/^id_/, '');
            const isAnswer = elementId.indexOf('_answers_') !== -1;

            if (getAttoContent(elementId)) {
                if (onAnswerChange && isAnswer) {
                    const atto = getAttoContent(elementId);
                    atto.addEventListener('input', onAnswerChange);
                    atto.addEventListener('change', onAnswerChange);
                }
                continue;
            }
            const existing = Tiny.getInstanceForElement(textarea);
            if (existing) {
                existing.setContent(textarea.value || '');
                existing.save();
                applyEditorSizeFix(existing, textarea);
            } else {
                await initEditorForTextarea(textarea);
            }
            if (onAnswerChange && isAnswer) {
                bindEditorValidationListener(textarea, onAnswerChange);
            }
        }
    };

    /**
     * Initialise editors for one question (alias kept for callers).
     *
     * @param {string} questionId Question index
     * @param {Function|null} [onAnswerChange] Called when an answer field changes
     * @return {Promise<void>}
     */
    const scheduleInitEditorsForQuestion = (questionId, onAnswerChange = null) =>
        initEditorsForQuestion(questionId, onAnswerChange);

    /**
     * Strip empty paragraph/break markup then trim.
     *
     * @param {string} html Raw HTML
     * @return {string}
     */
    const stripEmptyHtml = (html) => {
        if (!html) {
            return '';
        }
        return html
            .replace(/<\/?p[^>]*>/gi, '')
            .replace(/<\/?br[^>]*>/gi, '')
            .trim();
    };

    /**
     * Moodle quickform element id without the id_ prefix.
     *
     * @param {string} questionId Question index
     * @return {string}
     */
    const questionTextElementId = (questionId) => 'questions' + questionId + '_text';

    /**
     * @param {string} questionId Question index
     * @param {string|number} answerId Answer index
     * @return {string}
     */
    const answerElementId = (questionId, answerId) => 'questions' + questionId + '_answers_' + answerId;

    /**
     * @param {string} questionId Question index
     * @return {string}
     */
    const correctFeedbackElementId = (questionId) => 'questions' + questionId + '_correctfeedback';

    /**
     * @param {string} questionId Question index
     * @return {string}
     */
    const partiallyCorrectFeedbackElementId = (questionId) => 'questions' + questionId + '_partiallycorrectfeedback';

    /**
     * @param {string} questionId Question index
     * @return {string}
     */
    const incorrectFeedbackElementId = (questionId) => 'questions' + questionId + '_incorrectfeedback';

    /**
     * @param {string} elementId Element id without id_ prefix
     * @return {HTMLTextAreaElement|null}
     */
    const getTextarea = (elementId) => document.getElementById('id_' + elementId);

    /**
     * Legacy Atto contenteditable region.
     *
     * @param {string} elementId Element id without id_ prefix
     * @return {HTMLElement|null}
     */
    const getAttoContent = (elementId) => document.querySelector(
        '#id_' + elementId + 'editable.editor_atto_content'
    );

    /**
     * Read HTML from TinyMCE (textarea) or Atto (contenteditable).
     *
     * @param {string} elementId Element id without id_ prefix
     * @return {string}
     */
    const getEditorHtml = (elementId) => {
        const atto = getAttoContent(elementId);
        if (atto) {
            return atto.innerHTML;
        }
        const textarea = getTextarea(elementId);
        if (!textarea) {
            return '';
        }
        const editor = Tiny.getInstanceForElement(textarea);
        if (editor) {
            editor.save();
            return editor.getContent();
        }
        return textarea.value;
    };

    /**
     * Write HTML into Atto and/or the underlying textarea (TinyMCE syncs from textarea).
     *
     * @param {string} elementId Element id without id_ prefix
     * @param {string} html HTML content
     */
    const setEditorHtml = (elementId, html) => {
        const atto = getAttoContent(elementId);
        if (atto) {
            atto.innerHTML = html;
        }
        const textarea = getTextarea(elementId);
        if (textarea) {
            textarea.value = html;
            textarea.dispatchEvent(new Event('change', {bubbles: true}));
        }
        const tinyInst = textarea ? Tiny.getInstanceForElement(textarea) : null;
        if (tinyInst) {
            tinyInst.setContent(html || '');
            tinyInst.save();
        }
    };

    /**
     * @param {string} elementId Element id without id_ prefix
     * @return {boolean}
     */
    const hasEditorContent = (elementId) => stripEmptyHtml(getEditorHtml(elementId)) !== '';

    /**
     * Whether the question text editor is in the DOM (Tiny textarea or Atto region).
     *
     * @param {string} questionId Question index
     * @return {boolean}
     */
    const isQuestionTextEditorReady = (questionId) => {
        const elementId = questionTextElementId(questionId);
        return getAttoContent(elementId) !== null || getTextarea(elementId) !== null;
    };

    /**
     * Snapshot all editor HTML for a question.
     *
     * @param {string} questionId Question index
     * @return {Object<string, string>}
     */
    const snapshotQuestionContent = (questionId) => {
        const snapshot = {};
        getEditorElementIds(questionId).forEach((elementId) => {
            snapshot[elementId] = getEditorHtml(elementId);
        });
        return snapshot;
    };

    /**
     * Restore a snapshot onto question editors.
     *
     * @param {string} questionId Question index
     * @param {Object<string, string>} snapshot Element id to HTML map.
     */
    const restoreQuestionSnapshot = (questionId, snapshot) => {
        getEditorElementIds(questionId).forEach((elementId) => {
            if (Object.prototype.hasOwnProperty.call(snapshot, elementId)) {
                setEditorHtml(elementId, snapshot[elementId]);
            }
        });
    };

    /**
     * Find a hidden companion input fitem by exact field name.
     *
     * @param {HTMLElement} block Question block container.
     * @param {string} fieldName Full input name.
     * @return {HTMLElement|null}
     */
    const findCompanionFitem = (block, fieldName) => {
        const input = Array.from(block.querySelectorAll('input[type="hidden"]'))
            .find((element) => element.name === fieldName);
        return input ? input.closest('.fitem') : null;
    };

    const markQuestionEditorFitems = (block, questionId) => {
        block.querySelectorAll('textarea.simplequiz2-deferred-editor').forEach((textarea) => {
            const fitem = textarea.closest('.fitem');
            if (fitem) {
                fitem.classList.add('simplequiz2-editor-fitem');
            }
            const name = textarea.getAttribute('name');
            if (!name || !name.endsWith('[text]')) {
                return;
            }
            const base = name.slice(0, -'[text]'.length);
            ['[format]', '[itemid]'].forEach((suffix) => {
                const companion = findCompanionFitem(block, base + suffix);
                if (companion) {
                    companion.classList.add('simplequiz2-editor-fitem');
                }
            });
        });

        block.querySelectorAll("input[id^='id_questions" + questionId + "_correctanswers_']").forEach((checkbox) => {
            const fitem = checkbox.closest('.fitem');
            if (fitem) {
                fitem.classList.add('simplequiz2-editor-fitem');
            }
        });
    };

    /**
     * @param {HTMLElement} block Question block container.
     * @param {string} elementId Editor textarea id without the id_ prefix.
     * @return {Element|null}
     */
    const findEditorFitem = (block, elementId) => {
        const textarea = block.querySelector('#id_' + elementId);
        return textarea ? textarea.closest('.fitem') : null;
    };

    /**
     * @param {HTMLElement} block Question block container.
     * @param {string} fieldBase Field name prefix ending before [format] or [itemid].
     * @return {Element|null}
     */
    const findCompanionFitems = (block, fieldBase) => {
        return {
            format: findCompanionFitem(block, fieldBase + '[format]'),
            itemid: findCompanionFitem(block, fieldBase + '[itemid]'),
        };
    };

    /**
     * Append fitem nodes into a parent if they exist and are not already there.
     *
     * @param {HTMLElement} parent Destination parent.
     * @param {...(Element|null)} fitems Fitem nodes to move.
     */
    const appendFitems = (parent, ...fitems) => {
        fitems.forEach((fitem) => {
            if (fitem) {
                parent.appendChild(fitem);
            }
        });
    };

    /**
     * Group editor fitems into a single fields wrapper inside the question block.
     *
     * @param {HTMLElement} block Question block container.
     */
    const wrapQuestionEditorFields = (block) => {
        const blockstart = block.querySelector('.simplequiz2-question-block-start');
        if (!blockstart) {
            return;
        }

        let fieldsWrap = blockstart.querySelector('.simplequiz2-question-editors');
        if (!fieldsWrap) {
            fieldsWrap = document.createElement('div');
            fieldsWrap.className = 'simplequiz2-question-editors d-none';
            blockstart.appendChild(fieldsWrap);
        }

        const questionId = block.dataset.questionid;

        const questionFitem = findEditorFitem(block, questionTextElementId(questionId));
        if (questionFitem) {
            const companions = findCompanionFitems(block, 'questions' + questionId + '[text]');
            appendFitems(fieldsWrap, questionFitem, companions.format, companions.itemid);
        }

        for (let answerId = 0; answerId < 5; answerId++) {
            const group = document.createElement('div');
            group.className = 'simplequiz2-answer-group';
            group.dataset.answerid = String(answerId);

            const answerFitem = findEditorFitem(block, answerElementId(questionId, answerId));
            const companions = findCompanionFitems(
                block,
                'questions' + questionId + '[answers][' + answerId + ']'
            );
            appendFitems(group, answerFitem, companions.format, companions.itemid);

            const checkbox = block.querySelector('#id_questions' + questionId + '_correctanswers_' + answerId);
            const checkboxFitem = checkbox ? checkbox.closest('.fitem') : null;
            appendFitems(group, checkboxFitem);

            fieldsWrap.appendChild(group);
        }

        [
            correctFeedbackElementId(questionId),
            partiallyCorrectFeedbackElementId(questionId),
            incorrectFeedbackElementId(questionId),
        ].forEach((elementId) => {
            const fitem = findEditorFitem(block, elementId);
            if (!fitem) {
                return;
            }
            const fieldKey = elementId.replace('questions' + questionId + '_', '');
            const companions = findCompanionFitems(block, 'questions' + questionId + '[' + fieldKey + ']');
            appendFitems(fieldsWrap, fitem, companions.format, companions.itemid);
        });

        block.querySelectorAll('.simplequiz2-editor-fitem').forEach((fitem) => {
            if (!fieldsWrap.contains(fitem)) {
                fieldsWrap.appendChild(fitem);
            }
        });
    };

    /**
     * CSS selector for answer editor fields for one question.
     *
     * @param {string} questionId Question index
     * @return {string}
     */
    const answerFieldsSelector = (questionId) =>
        'textarea[id^="id_questions' + questionId + '_answers_"], ' +
        'div[id^="id_questions' + questionId + '_answers_"].editor_atto_content';

    /**
     * Bind change listeners on all answer fields for a question.
     *
     * @param {string} questionId Question index
     * @param {Function} handler Callback
     */
    const bindAnswerFieldListeners = (questionId, handler) => {
        document.querySelectorAll(answerFieldsSelector(questionId)).forEach((field) => {
            if (field.classList.contains('editor_atto_content')) {
                field.addEventListener('change', handler);
                field.addEventListener('input', handler);
            }
        });
        for (let answerId = 0; answerId < 5; answerId++) {
            bindEditorValidationListener(getTextarea(answerElementId(questionId, answerId)), handler);
        }
    };

    return {
        stripEmptyHtml,
        questionTextElementId,
        answerElementId,
        correctFeedbackElementId,
        partiallyCorrectFeedbackElementId,
        incorrectFeedbackElementId,
        getEditorHtml,
        setEditorHtml,
        hasEditorContent,
        isQuestionTextEditorReady,
        answerFieldsSelector,
        bindAnswerFieldListeners,
        initEditorsForQuestion,
        scheduleInitEditorsForQuestion,
        removeEditorsForQuestion,
        setTinyBaseOptions,
        snapshotQuestionContent,
        restoreQuestionSnapshot,
        markQuestionEditorFitems,
        wrapQuestionEditorFields,
        resetQuestionSlotEditors,
        getEditorElementIds,
    };
});
