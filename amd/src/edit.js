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
 * Activity form UI for mod_simplequiz2 (question blocks, lazy editors, summaries).
 *
 * @module      mod_simplequiz2/edit
 * @copyright   2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['mod_simplequiz2/editor_helpers', 'core/str', 'core/templates'], function(EditorHelpers, str, Templates) {

    const STRING_KEYS = [
        'editquestion',
        'savequestion',
        'discardquestion',
        'deletequestion',
        'addquestion',
        'previewanswers',
        'previewfeedback',
        'previewcorrect',
        'previewpartial',
        'previewincorrect',
        'formquestiontitle',
    ];

    const SUMMARY_TEMPLATE = 'mod_simplequiz2/question_summary';

    /**
     * True if some checked "correct answer" checkbox points to an answer slot with content.
     *
     * @param {string} questionId Question id
     * @param {NodeListOf<Element>} checkboxs Correct-answer checkboxes for this question
     * @return {boolean}
     */
    const hasCheckedAnswerWithContent = function(questionId, checkboxs) {
        for (let i = 0; i < checkboxs.length; i++) {
            const checkbox = checkboxs[i];
            if (!checkbox.checked) {
                continue;
            }
            const answerId = checkbox.dataset.answerid;
            if (EditorHelpers.hasEditorContent(EditorHelpers.answerElementId(questionId, answerId))) {
                return true;
            }
        }
        return false;
    };

    /**
     * Count non-empty answer slots for a question.
     *
     * @param {string} questionId Question index
     * @return {number}
     */
    const countAnswersWithContent = function(questionId) {
        let count = 0;
        for (let answerId = 0; answerId < 5; answerId++) {
            if (EditorHelpers.hasEditorContent(EditorHelpers.answerElementId(questionId, answerId))) {
                count++;
            }
        }
        return count;
    };

    /**
     * Whether a question slot has any content in text, answers, or feedback fields.
     *
     * @param {string} questionId Question index
     * @return {boolean}
     */
    const questionSlotHasContent = function(questionId) {
        if (EditorHelpers.hasEditorContent(EditorHelpers.questionTextElementId(questionId))) {
            return true;
        }
        for (let answerId = 0; answerId < 5; answerId++) {
            if (EditorHelpers.hasEditorContent(EditorHelpers.answerElementId(questionId, answerId))) {
                return true;
            }
        }
        const feedbackIds = [
            EditorHelpers.correctFeedbackElementId(questionId),
            EditorHelpers.partiallyCorrectFeedbackElementId(questionId),
            EditorHelpers.incorrectFeedbackElementId(questionId),
        ];
        for (let i = 0; i < feedbackIds.length; i++) {
            if (EditorHelpers.hasEditorContent(feedbackIds[i])) {
                return true;
            }
        }
        return false;
    };

    var modSimplequizEdit = {

        activeEditQuestionId: null,
        editSnapshots: {},
        stringCache: {},

        init: async function() {
            const that = this;
            const loaded = await str.get_strings(STRING_KEYS.map((key) => ({
                key: key,
                component: 'mod_simplequiz2',
            })));
            STRING_KEYS.forEach((key, index) => {
                that.stringCache[key] = loaded[index];
            });

            await Templates.prefetchTemplates([SUMMARY_TEMPLATE]);

            that.organizeQuestionBlocks();

            const blocks = document.querySelectorAll('.simplequiz2-question-block');
            for (let index = 0; index < blocks.length; index++) {
                const block = blocks[index];
                const questionId = block.dataset.questionid;
                that.updateQuestionsBackground([questionId]);

                if (index === 0) {
                    if (document.querySelector('input[name="coursemodule"]').value === '') {
                        that.checkQuestionsAnswersWarnings([questionId]);
                    }
                    await that.updateQuestionSummary(questionId);
                    continue;
                }

                const meta = that.getQuestionMeta(questionId);
                if (!meta || meta.dataset.hasContent !== '1') {
                    that.setQuestionBlockHidden(questionId, true);
                    continue;
                }

                await that.updateQuestionSummary(questionId);
            }

            that.checkIfCanAddQuestion();
            that.initAddQuestionButtons();
            that.initDeleteQuestionButtons();
            that.initEditToolbarButtons();
            that.refreshToolbarVisibility(null);
        },

        /**
         * Wrap each question's form items into a single block container.
         */
        organizeQuestionBlocks: function() {
            document.querySelectorAll('[data-simplequiz2-block-start]').forEach((startEl) => {
                if (startEl.closest('.simplequiz2-question-block')) {
                    return;
                }

                const questionId = startEl.dataset.questionid;
                const block = document.createElement('div');
                block.className = 'simplequiz2-question-block simplequiz2-question-wrapper';
                block.dataset.questionid = questionId;

                const parent = startEl.parentNode;
                if (!parent) {
                    return;
                }

                parent.insertBefore(block, startEl);
                block.appendChild(startEl);

                // Html elements are not wrapped in .fitem; editor fitems may be siblings after block-start.
                let next = block.nextElementSibling;
                while (next) {
                    if (next.hasAttribute('data-simplequiz2-block-start')) {
                        break;
                    }
                    const isBlockEnd = next.hasAttribute('data-simplequiz2-block-end') &&
                        next.getAttribute('data-simplequiz2-block-end') === questionId;
                    const toMove = next;
                    next = next.nextElementSibling;
                    block.appendChild(toMove);
                    if (isBlockEnd) {
                        break;
                    }
                }

                EditorHelpers.markQuestionEditorFitems(block, questionId);
                EditorHelpers.wrapQuestionEditorFields(block);

                const meta = block.querySelector('.simplequiz2-question-meta[data-questionid="' + questionId + '"]');
                const hasContent = meta && meta.dataset.hasContent === '1';
                if (parseInt(questionId, 10) > 0 && !hasContent) {
                    block.classList.add('d-none');
                }
            });
        },

        isQuestionBlockHidden: function(block) {
            return !!(block && block.classList.contains('d-none'));
        },

        setQuestionBlockHidden: function(questionId, hidden) {
            const block = this.getQuestionBlock(questionId);
            if (block) {
                block.classList.toggle('d-none', hidden);
            }
        },

        getQuestionBlock: function(questionId) {
            return document.querySelector('.simplequiz2-question-block[data-questionid="' + questionId + '"]');
        },

        getQuestionMeta: function(questionId) {
            const block = this.getQuestionBlock(questionId);
            if (!block) {
                return null;
            }
            return block.querySelector('.simplequiz2-question-meta[data-questionid="' + questionId + '"]');
        },

        setQuestionHasContent: function(questionId, hasContent) {
            const meta = this.getQuestionMeta(questionId);
            if (meta) {
                meta.dataset.hasContent = hasContent ? '1' : '0';
            }
        },

        refreshToolbarVisibility: function(activeEditQuestionId) {
            document.querySelectorAll('.simplequiz2-edit-toolbar').forEach((toolbar) => {
                const questionId = toolbar.dataset.questionid;
                const editBtn = toolbar.querySelector('.edit-question');
                const saveBtn = toolbar.querySelector('.save-question');
                const discardBtn = toolbar.querySelector('.discard-question');
                const deleteBtn = toolbar.querySelector('.delete-simplequestion');
                const addBtn = toolbar.querySelector('.add-simplequestion');

                if (activeEditQuestionId !== null) {
                    const isActive = questionId === activeEditQuestionId;
                    if (editBtn) {
                        editBtn.hidden = true;
                    }
                    if (deleteBtn) {
                        deleteBtn.hidden = true;
                    }
                    if (addBtn) {
                        addBtn.hidden = true;
                    }
                    if (saveBtn) {
                        saveBtn.hidden = !isActive;
                    }
                    if (discardBtn) {
                        discardBtn.hidden = !isActive;
                    }
                    return;
                }

                if (editBtn) {
                    editBtn.hidden = false;
                }
                if (saveBtn) {
                    saveBtn.hidden = true;
                }
                if (discardBtn) {
                    discardBtn.hidden = true;
                }
                if (deleteBtn) {
                    deleteBtn.hidden = (questionId === '0');
                }
                if (addBtn) {
                    addBtn.hidden = false;
                }
            });
        },

        getQuestionSummary: function(questionId) {
            const block = this.getQuestionBlock(questionId);
            return block ? block.querySelector('.header-questiontext[data-questionid="' + questionId + '"]') : null;
        },

        getQuestionEditors: function(questionId) {
            const block = this.getQuestionBlock(questionId);
            return block ? block.querySelector('.simplequiz2-question-editors') : null;
        },

        setQuestionEditorsReady: function(questionId, ready) {
            const block = this.getQuestionBlock(questionId);
            if (block) {
                block.classList.toggle('simplequiz2-editors-ready', ready);
            }
        },

        setQuestionViewMode: function(questionId) {
            const block = this.getQuestionBlock(questionId);
            const summary = this.getQuestionSummary(questionId);
            const editors = this.getQuestionEditors(questionId);
            if (summary) {
                summary.classList.remove('d-none');
            }
            if (editors) {
                editors.classList.add('d-none');
            }
            if (block) {
                block.classList.remove('simplequiz2-editing', 'simplequiz2-editors-ready');
            }
            this.refreshToolbarVisibility(null);
        },

        setQuestionEditMode: function(questionId) {
            const block = this.getQuestionBlock(questionId);
            const summary = this.getQuestionSummary(questionId);
            const editors = this.getQuestionEditors(questionId);
            if (summary) {
                summary.classList.add('d-none');
            }
            if (editors) {
                editors.classList.remove('d-none');
            }
            if (block) {
                block.classList.add('simplequiz2-editing');
                block.classList.remove('simplequiz2-editors-ready');
            }
            this.refreshToolbarVisibility(questionId);
        },

        exitEditMode: async function(questionId, restoreSnapshot) {
            if (restoreSnapshot && this.editSnapshots[questionId]) {
                EditorHelpers.restoreQuestionSnapshot(questionId, this.editSnapshots[questionId]);
            }
            delete this.editSnapshots[questionId];
            await EditorHelpers.removeEditorsForQuestion(questionId);
            this.setQuestionViewMode(questionId);
            await this.updateQuestionSummary(questionId);
            if (this.activeEditQuestionId === questionId) {
                this.activeEditQuestionId = null;
            }
        },

        startEditQuestion: async function(questionId) {
            const that = this;

            if (this.activeEditQuestionId !== null && this.activeEditQuestionId !== questionId) {
                const discard = window.confirm('Discard unsaved changes on the current question?');
                if (!discard) {
                    return;
                }
                await this.exitEditMode(this.activeEditQuestionId, true);
            }

            this.editSnapshots[questionId] = EditorHelpers.snapshotQuestionContent(questionId);
            this.activeEditQuestionId = questionId;
            this.setQuestionEditMode(questionId);

            const refreshQuestion = () => {
                that.checkQuestionsAnswersWarnings(false);
                that.updateQuestionsBackground([questionId]);
            };

            await EditorHelpers.initEditorsForQuestion(questionId, refreshQuestion);
            if (this.activeEditQuestionId !== questionId) {
                return;
            }
            this.setQuestionEditorsReady(questionId, true);
            EditorHelpers.bindAnswerFieldListeners(questionId, refreshQuestion);

            const checkboxs = document.querySelectorAll("input[id^='id_questions" + questionId + "_correctanswers_']");
            checkboxs.forEach((checkbox) => {
                checkbox.addEventListener('change', function() {
                    that.checkQuestionsAnswersWarnings([questionId]);
                    that.updateQuestionsBackground([questionId]);
                });
            });
        },

        saveEditQuestion: async function(questionId) {
            await EditorHelpers.removeEditorsForQuestion(questionId);

            await this.updateQuestionSummary(questionId);

            const hasContent = questionSlotHasContent(questionId);
            this.setQuestionHasContent(questionId, hasContent);

            delete this.editSnapshots[questionId];
            if (this.activeEditQuestionId === questionId) {
                this.activeEditQuestionId = null;
            }
            this.setQuestionViewMode(questionId);

            this.checkQuestionsAnswersWarnings(false);
            this.updateQuestionsBackground([questionId]);
        },

        initEditToolbarButtons: function() {
            const that = this;

            document.querySelectorAll('.edit-question').forEach((button) => {
                button.addEventListener('click', function() {
                    that.startEditQuestion(button.dataset.questionid);
                });
            });

            document.querySelectorAll('.save-question').forEach((button) => {
                button.addEventListener('click', function() {
                    that.saveEditQuestion(button.dataset.questionid);
                });
            });

            document.querySelectorAll('.discard-question').forEach((button) => {
                button.addEventListener('click', function() {
                    that.exitEditMode(button.dataset.questionid, true).then(() => {
                        that.checkQuestionsAnswersWarnings(false);
                        that.updateQuestionsBackground([button.dataset.questionid]);
                    });
                });
            });
        },

        initAddQuestionButtons: function() {
            const that = this;

            document.querySelectorAll('.add-simplequestion').forEach(function(addQuestionButton) {
                addQuestionButton.addEventListener('click', async function() {
                    const currentBlock = that.getQuestionBlock(addQuestionButton.dataset.questionid);
                    const blocks = document.querySelectorAll('.simplequiz2-question-block');

                    for (const block of blocks) {
                        if (that.isQuestionBlockHidden(block)) {
                            if (currentBlock && currentBlock.parentNode) {
                                currentBlock.after(block);
                            }
                            block.classList.remove('d-none');

                            const questionId = block.dataset.questionid;
                            that.setQuestionHasContent(questionId, false);

                            await EditorHelpers.resetQuestionSlotEditors(questionId);
                            await that.startEditQuestion(questionId);
                            if (that.activeEditQuestionId !== questionId) {
                                that.setQuestionViewMode(questionId);
                                await that.updateQuestionSummary(questionId);
                            }

                            that.checkQuestionsAnswersWarnings([questionId]);
                            break;
                        }
                    }

                    that.fixQuestionOrder();
                    that.checkIfCanAddQuestion();
                });
            });
        },

        initDeleteQuestionButtons: function() {
            const that = this;

            document.querySelectorAll('.delete-simplequestion').forEach(function(deleteQuestionButton) {
                deleteQuestionButton.addEventListener('click', async function() {
                    const questionId = deleteQuestionButton.dataset.questionid;

                    if (that.activeEditQuestionId === questionId) {
                        that.activeEditQuestionId = null;
                        delete that.editSnapshots[questionId];
                    }

                    const block = that.getQuestionBlock(questionId);
                    if (block) {
                        block.classList.add('d-none');
                    }
                    that.setQuestionHasContent(questionId, false);

                    await EditorHelpers.resetQuestionSlotEditors(questionId);
                    delete that.editSnapshots[questionId];

                    await that.updateQuestionSummary(questionId);

                    document.querySelectorAll("input[id^='id_questions" + questionId + "_correctanswers_']").forEach((checkbox) => {
                        checkbox.checked = false;
                    });

                    const deleteBlock = that.getQuestionBlock(questionId);
                    if (deleteBlock) {
                        deleteBlock.querySelectorAll(
                            '.simplequiz2-answer-group, [id^="fitem_id_questions' + questionId + '_answers_"]'
                        ).forEach(function(answerEl) {
                            answerEl.classList.remove(
                                'simplequiz2-answer',
                                'simplequiz2-right-answer',
                                'simplequiz2-wrong-answer'
                            );
                        });
                    }

                    that.fixQuestionOrder();
                    that.checkIfCanAddQuestion();
                    that.checkQuestionsAnswersWarnings(false);
                });
            });
        },

        /**
         * Build Mustache context for the question summary template from live editors.
         *
         * @param {string} questionId Question index
         * @return {Object}
         */
        buildQuestionSummaryContext: function(questionId) {
            const strings = this.stringCache;
            const context = {
                hasquestion: false,
                questiontext: '',
                hasanswers: false,
                answerheading: strings.previewanswers || 'Answers',
                answers: [],
                hasfeedback: false,
                feedbackheading: strings.previewfeedback || 'Feedback',
                feedbackitems: [],
            };

            const questionHtml = EditorHelpers.getEditorHtml(EditorHelpers.questionTextElementId(questionId));
            if (EditorHelpers.stripEmptyHtml(questionHtml) !== '') {
                context.hasquestion = true;
                context.questiontext = questionHtml;
            }

            for (let answerId = 0; answerId < 5; answerId++) {
                const answerHtml = EditorHelpers.getEditorHtml(EditorHelpers.answerElementId(questionId, answerId));
                if (EditorHelpers.stripEmptyHtml(answerHtml) === '') {
                    continue;
                }
                const checkbox = document.querySelector(
                    '#id_questions' + questionId + '_correctanswers_' + answerId
                );
                context.answers.push({
                    text: answerHtml,
                    iscorrect: !!(checkbox && checkbox.checked),
                });
            }
            context.hasanswers = context.answers.length > 0;

            const feedbackFields = [
                {
                    id: EditorHelpers.correctFeedbackElementId(questionId),
                    label: strings.previewcorrect || 'Correct',
                },
                {
                    id: EditorHelpers.partiallyCorrectFeedbackElementId(questionId),
                    label: strings.previewpartial || 'Partially correct',
                },
                {
                    id: EditorHelpers.incorrectFeedbackElementId(questionId),
                    label: strings.previewincorrect || 'Incorrect',
                },
            ];
            feedbackFields.forEach((field) => {
                const feedbackHtml = EditorHelpers.getEditorHtml(field.id);
                if (EditorHelpers.stripEmptyHtml(feedbackHtml) === '') {
                    return;
                }
                context.feedbackitems.push({
                    label: field.label,
                    text: feedbackHtml,
                });
            });
            context.hasfeedback = context.feedbackitems.length > 0;

            return context;
        },

        updateQuestionSummary: async function(questionId) {
            const block = this.getQuestionBlock(questionId);
            if (!block) {
                return;
            }
            const summaryEl = this.getQuestionSummary(questionId);
            if (!summaryEl) {
                return;
            }
            const context = this.buildQuestionSummaryContext(questionId);
            const {html, js} = await Templates.renderForPromise(SUMMARY_TEMPLATE, context);
            summaryEl.innerHTML = html;
            Templates.runTemplateJS(js);
        },

        fixQuestionOrder: function() {
            const blocks = document.querySelectorAll('.simplequiz2-question-block');
            let visibleIndex = 1;

            blocks.forEach((block) => {
                if (this.isQuestionBlockHidden(block)) {
                    return;
                }

                const questionId = block.dataset.questionid;
                const title = block.querySelector('.simplequiz2-question-title');
                if (title) {
                    const template = this.stringCache.formquestiontitle || 'Question {$a}';
                    title.textContent = template.replace('{$a}', String(visibleIndex));
                }

                const orderInput = document.querySelector('input[name="questions' + questionId + '[questionorder]"]');
                if (orderInput) {
                    orderInput.value = visibleIndex - 1;
                }

                visibleIndex++;
            });
        },

        checkIfCanAddQuestion: function() {
            const blocks = document.querySelectorAll('.simplequiz2-question-block');
            let hasHiddenQuestions = false;
            blocks.forEach((block) => {
                if (this.isQuestionBlockHidden(block)) {
                    hasHiddenQuestions = true;
                }
            });

            document.querySelectorAll('.add-simplequestion').forEach(function(addButton) {
                addButton.disabled = !hasHiddenQuestions;
            });
        },

        checkQuestionsAnswersWarnings: function(questionIds) {
            let formHasError = false;

            if (questionIds == false) {
                const visibleQuestionIds = [];
                document.querySelectorAll('.simplequiz2-question-block').forEach((block) => {
                    if (this.isQuestionBlockHidden(block)) {
                        return;
                    }
                    visibleQuestionIds.push(block.dataset.questionid);
                });
                questionIds = visibleQuestionIds;
            }

            questionIds.forEach((questionId) => {
                const block = this.getQuestionBlock(questionId);
                if (!block) {
                    return;
                }

                let questionHasError = false;
                const isHidden = this.isQuestionBlockHidden(block);
                const nbContent = countAnswersWithContent(questionId);

                const notEnoughEl = block.querySelector('.error_not_enough_answers');
                if (nbContent < 2) {
                    if (notEnoughEl) {
                        notEnoughEl.classList.add('simplequiz2-error-visible');
                    }
                    questionHasError = true;
                    if (!isHidden) {
                        formHasError = true;
                    }
                } else if (notEnoughEl) {
                    notEnoughEl.classList.remove('simplequiz2-error-visible');
                }

                if (!questionHasError) {
                    const checkboxs = document.querySelectorAll(
                        "input[id^='id_questions" + questionId + "_correctanswers_']"
                    );
                    const checked = hasCheckedAnswerWithContent(questionId, checkboxs);
                    const noRightEl = block.querySelector('.error_no_right_answer');

                    if (!checked) {
                        if (noRightEl) {
                            noRightEl.classList.add('simplequiz2-error-visible');
                        }
                        questionHasError = true;
                        if (!isHidden) {
                            formHasError = true;
                        }
                    } else if (noRightEl) {
                        noRightEl.classList.remove('simplequiz2-error-visible');
                    }
                }
            });

            const submitBtn = document.getElementById('id_submitbutton');
            if (submitBtn) {
                submitBtn.disabled = formHasError;
            }
            const submitBtn2 = document.getElementById('id_submitbutton2');
            if (submitBtn2) {
                submitBtn2.disabled = formHasError;
            }
        },

        updateQuestionsBackground: function(questionIds) {
            questionIds.forEach((questionId) => {
                document.querySelectorAll(
                    "input[id^='id_questions" + questionId + "_correctanswers_']"
                ).forEach((checkbox) => {
                    const answerId = checkbox.dataset.answerid;
                    const container = document.querySelector('#fitem_id_questions' + questionId + '_answers_' + answerId);
                    const group = container ? container.closest('.simplequiz2-answer-group') : null;
                    const target = group || container;
                    if (!target) {
                        return;
                    }
                    target.classList.add('simplequiz2-answer');
                    if (container) {
                        container.classList.add('simplequiz2-answer');
                    }

                    const hasContent = EditorHelpers.hasEditorContent(
                        EditorHelpers.answerElementId(questionId, answerId)
                    );

                    const targets = [target];
                    if (container && container !== target) {
                        targets.push(container);
                    }
                    targets.forEach((el) => {
                        if (!hasContent) {
                            el.classList.remove('simplequiz2-wrong-answer');
                            el.classList.remove('simplequiz2-right-answer');
                        } else if (checkbox.checked === true) {
                            el.classList.add('simplequiz2-right-answer');
                            el.classList.remove('simplequiz2-wrong-answer');
                        } else {
                            el.classList.add('simplequiz2-wrong-answer');
                            el.classList.remove('simplequiz2-right-answer');
                        }
                    });
                });
            });
        },

    };

    return modSimplequizEdit;
});
