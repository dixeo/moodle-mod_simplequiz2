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
 * Activity form UI for mod_simplequiz2 (question fieldsets, reorder, warnings).
 *
 * @module      mod_simplequiz2/edit
 * @copyright   2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['mod_simplequiz2/editor_helpers'], function(EditorHelpers) {

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

    var modSimplequizEdit = {

        preparedFieldsets: {},

        init: function() {

            var that = this;

            // Hide empty questions container
            let questionsHeaders = document.querySelectorAll("fieldset[id^='id_question_header_']");
            for (let i = 0; i < questionsHeaders.length; i++) {
                let questionHeader = questionsHeaders[i];

                that.updateQuestionsBackground([i]);

                // The first container is always visible
                if (i == 0) {

                    // This question can be visible and empty if this is a new activity, check his warning
                    if (document.querySelector('input[name="coursemodule"]').value == '') {
                        that.checkQuestionsAnswersWarnings([i]);
                    }

                    continue;
                }

                // If the container has no content, hide it
                if (questionHeader.querySelector('.question-text-editor:not(.has-content)')) {
                    questionHeader.hidden = true;
                }

            }

            // Question fieldset contains question text at any moment
            that.prepareQuestionHeader();

            // If the max questions number is reach, disable all add buttons
            that.checkIfCanAddQuestion();

            // Add question buttons
            that.initAddQuestionButtons();

            // Add delete question buttons
            that.initDeleteQuestionButtons();

            let fieldsets = document.querySelectorAll("fieldset[id^='id_question_header_']");
            fieldsets.forEach(function(fieldset) {
                fieldset.addEventListener('click', function() {

                    let questionId = fieldset.id.replace('id_question_header_', '');

                    // If the fieldset is already init, stop
                    if (that.preparedFieldsets[questionId] === 1) {
                        return;
                    }

                    if (!EditorHelpers.isQuestionTextEditorReady(questionId)) {
                        return;
                    }

                    that.preparedFieldsets[questionId] = 1;

                    that.updateQuestionHeader(questionId);

                    const refreshQuestion = () => {
                        that.checkQuestionsAnswersWarnings(false);
                        that.updateQuestionsBackground([questionId]);
                    };

                    EditorHelpers.bindAnswerFieldListeners(questionId, refreshQuestion);

                    let checkboxs = document.querySelectorAll("input[id^='id_questions" + questionId + "_correctanswers_']");
                    for (const checkbox of checkboxs) {
                        checkbox.addEventListener('change', function() {
                            that.checkQuestionsAnswersWarnings([questionId]);
                            that.updateQuestionsBackground([questionId]);
                        });
                    }

                });
            });

        },

        // Prepare add question buttons
        initAddQuestionButtons: function() {
            var that = this;

            let addQuestionButtons = document.querySelectorAll('form input[type="button"].add-simplequestion');
            addQuestionButtons.forEach(function(addQuestionButton) {
                addQuestionButton.addEventListener('click', function() {

                    let currentFieldset = document.getElementById('id_question_header_' + addQuestionButton.dataset.questionid);

                    // Move the next hidden field at the correct place and unhide it
                    let fieldsets = document.querySelectorAll("fieldset[id^='id_question_header_']");
                    for (const fieldset of fieldsets) {

                        if (fieldset.hidden === true) {
                            // Move the element after sur selected field
                            currentFieldset.after(fieldset);

                            // Unhide the fieldset
                            fieldset.hidden = false;

                            let questionId = fieldset.id.replace('id_question_header_', '');

                            EditorHelpers.removeEditorsForQuestion(questionId);
                            delete that.preparedFieldsets[questionId];

                            const refreshQuestion = () => {
                                that.checkQuestionsAnswersWarnings(false);
                                that.updateQuestionsBackground([questionId]);
                            };

                            // TinyMCE must re-init after the collapse panel is visible, not only after hidden=false.
                            const panel = fieldset.querySelector('.collapse');
                            let header = fieldset.querySelector(
                                'a[aria-expanded="false"][aria-controls^="id_question_header_"]'
                            );
                            let refreshPromise;
                            if (header) {
                                refreshPromise = EditorHelpers.scheduleInitEditorsForQuestion(
                                    questionId,
                                    fieldset,
                                    refreshQuestion
                                );
                                header.click();
                            } else if (panel) {
                                panel.classList.add('show');
                                refreshPromise = EditorHelpers.initEditorsForQuestion(questionId, refreshQuestion);
                            } else {
                                refreshPromise = EditorHelpers.initEditorsForQuestion(questionId, refreshQuestion);
                            }

                            // Display warning message
                            that.checkQuestionsAnswersWarnings([questionId]);

                            refreshPromise.then(() => {
                                if (!that.preparedFieldsets[questionId]) {
                                    that.preparedFieldsets[questionId] = 1;
                                    that.updateQuestionHeader(questionId);
                                    let checkboxs = document.querySelectorAll(
                                        "input[id^='id_questions" + questionId + "_correctanswers_']"
                                    );
                                    for (const checkbox of checkboxs) {
                                        checkbox.addEventListener('change', function() {
                                            that.checkQuestionsAnswersWarnings([questionId]);
                                            that.updateQuestionsBackground([questionId]);
                                        });
                                    }
                                }
                            });

                            break;
                        }
                    }

                    // Check if the order are still correct and update title
                    that.fixQuestionOrder();

                    // If there is only one question, hide delete buttons
                    that.checkIfCanAddQuestion();
                });
            });
        },

        // Prepare delete question buttons
        initDeleteQuestionButtons: function() {
            var that = this;

            let deleteQuestionButtons = document.querySelectorAll('form input[type="button"].delete-simplequestion');
            deleteQuestionButtons.forEach(function(deleteQuestionButton) {
                deleteQuestionButton.addEventListener('click', function() {

                    let questionId = deleteQuestionButton.dataset.questionid;

                    document.getElementById('id_question_header_' + questionId).hidden = true;

                    EditorHelpers.setEditorHtml(EditorHelpers.questionTextElementId(questionId), '');
                    document.querySelector('.header-questiontext[data-questionid="' + questionId + '"]').textContent = '';

                    for (let answerId = 0; answerId < 5; answerId++) {
                        EditorHelpers.setEditorHtml(EditorHelpers.answerElementId(questionId, answerId), '');
                    }

                    EditorHelpers.removeEditorsForQuestion(questionId);
                    delete that.preparedFieldsets[questionId];

                    let checkboxs = document.querySelectorAll("input[id^='id_questions" + questionId + "_correctanswers_']");
                    checkboxs.forEach(checkbox => {
                        checkbox.checked = false;
                    });

                    let questionsState = document.querySelectorAll(
                        "div[id^='fitem_id_questions" + questionId + "_answers_'].simplequiz2-answer"
                    );
                    questionsState.forEach(function(questionState) {
                        questionState.classList.remove("simplequiz2-right-answer");
                        questionState.classList.remove("simplequiz2-wrong-answer");
                    });

                    that.fixQuestionOrder();
                    that.checkIfCanAddQuestion();
                    that.checkQuestionsAnswersWarnings(false);
                });
            });

        },

        // Move elements in fieldset header to better view
        prepareQuestionHeader: function() {
            let questionHeaders = document.querySelectorAll("fieldset[id^='id_question_header_']");
            questionHeaders.forEach(function(questionHeader) {

                let legend = questionHeader.querySelector('legend ~ div.d-flex');
                legend.insertAdjacentHTML('afterend', '<div class="header-info-container"></div>');

                let legendContainer = questionHeader.querySelector('.header-info-container');

                let questionText = questionHeader.querySelector('.header-questiontext');
                if (questionText && legendContainer) {
                    legendContainer.prepend(questionText);
                }

                let warnings = questionHeader.querySelectorAll('.error_not_enough_answers, .error_no_right_answer');
                warnings.forEach(warning => legendContainer.appendChild(warning));

                let buttons = questionHeader.querySelectorAll('.header-btn');
                legendContainer.insertAdjacentHTML('beforeend', '<div class="header-buttons-container"></div>');
                let buttonsContainer = legendContainer.querySelector('.header-buttons-container');
                buttons.forEach(button => buttonsContainer.appendChild(button));
            });
        },

        // Question fieldset header contains question text at any moment
        updateQuestionHeader: function(questionId) {
            const syncHeader = () => {
                const plain = EditorHelpers.getQuestionTextPlain(questionId);
                document.querySelector('.header-questiontext[data-questionid="' + questionId + '"]').textContent = plain;
            };

            const atto = document.querySelector(
                '#id_' + EditorHelpers.questionTextElementId(questionId) + 'editable.editor_atto_content'
            );
            if (atto) {
                atto.addEventListener('keyup', syncHeader);
            }

            const textarea = document.getElementById('id_' + EditorHelpers.questionTextElementId(questionId));
            if (textarea) {
                textarea.addEventListener('input', syncHeader);
                textarea.addEventListener('change', syncHeader);
            }
        },

        // Check if hidden order field and question fieldset match to what to user sees
        fixQuestionOrder: function() {
            let fieldsets = document.querySelectorAll("fieldset[id^='id_question_header_']");

            var visibleIndex = 1;
            for (const fieldset of fieldsets) {

                if (fieldset.hidden === true) {
                    continue;
                }

                let questionId = fieldset.id.replace('id_question_header_', '');

                let title = fieldset.querySelector(".fheader[aria-controls^='id_question_header_'] ~ h3");
                title.innerHTML = "Question " + visibleIndex;

                document.querySelector('input[name="questions' + questionId + '[questionorder]"]').value = visibleIndex - 1;

                visibleIndex++;
            }
        },

        // If there is no hidden question, disable all "add question" buttons
        checkIfCanAddQuestion: function() {

            let fieldsets = document.querySelectorAll("fieldset[id^='id_question_header_']");
            var hasHiddenQuestions = false;
            for (const fieldset of fieldsets) {
                if (fieldset.hidden === true) {
                    hasHiddenQuestions = true;
                    break;
                }
            }

            let addButtons = document.querySelectorAll('input.add-simplequestion');
            addButtons.forEach(function(addButton) {
                addButton.disabled = !hasHiddenQuestions;
            });

        },

        // Check if there is enough answers and correct answers, else display warning message and block save buttons
        checkQuestionsAnswersWarnings: function(questionIds) {
            var formHasError = false;

            if (questionIds == false) {
                let visibleQuestionIds = [];
                let fieldsets = document.querySelectorAll("fieldset[id^='id_question_header_']");
                for (const fieldset of fieldsets) {
                    if (fieldset.hidden === true) {
                        continue;
                    }
                    visibleQuestionIds.push(fieldset.id.replace('id_question_header_', ''));
                }
                questionIds = visibleQuestionIds;
            }

            for (const questionId of questionIds) {

                let fieldset = document.querySelector("fieldset#id_question_header_" + questionId);
                let questionHasError = false;
                let isHidden = fieldset.hidden;

                const nbContent = countAnswersWithContent(questionId);

                if (nbContent < 2) {
                    fieldset.querySelector('.error_not_enough_answers').style.display = "inline";
                    questionHasError = true;
                    if (isHidden === false) {
                        formHasError = true;
                    }

                } else {
                    fieldset.querySelector('.error_not_enough_answers').style.display = "none";
                }

                if (!questionHasError) {
                    let checkboxs = document.querySelectorAll(
                        "input[id^='id_questions" + questionId + "_correctanswers_']"
                    );
                    let checked = hasCheckedAnswerWithContent(questionId, checkboxs);

                    if (!checked) {
                        fieldset.querySelector('.error_no_right_answer').style.display = "inline";
                        questionHasError = true;

                        if (isHidden === false) {
                            formHasError = true;
                        }
                    } else {
                        fieldset.querySelector('.error_no_right_answer').style.display = "none";
                    }
                }

            }

            document.getElementById('id_submitbutton').disabled = formHasError;
            if (document.getElementById('id_submitbutton2')) {
                document.getElementById('id_submitbutton2').disabled = formHasError;
            }
        },

        // Display red or green background on right/wrong answers
        updateQuestionsBackground: function(questionIds) {

            questionIds.forEach(function(questionId) {
                let checkboxs = document.querySelectorAll(
                    "input[id^='id_questions" + questionId + "_correctanswers_']"
                );
                for (const checkbox of checkboxs) {

                    let answerId = checkbox.dataset.answerid;

                    let container = document.querySelector('#fitem_id_questions' + questionId + '_answers_' + answerId);
                    container.classList.add('simplequiz2-answer');

                    const hasContent = EditorHelpers.hasEditorContent(
                        EditorHelpers.answerElementId(questionId, answerId)
                    );

                    if (!hasContent) {
                        container.classList.remove('simplequiz2-wrong-answer');
                        container.classList.remove('simplequiz2-right-answer');
                    } else if (checkbox.checked === true) {
                        container.classList.add('simplequiz2-right-answer');
                        container.classList.remove('simplequiz2-wrong-answer');
                    } else {
                        container.classList.add('simplequiz2-wrong-answer');
                        container.classList.remove('simplequiz2-right-answer');
                    }

                }
            });
        },

    };

    return modSimplequizEdit;
});
