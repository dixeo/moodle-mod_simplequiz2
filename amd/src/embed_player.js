// This file is part of Moodle - http://moodle.org/
//
// @module     mod_simplequiz2/embed_player
// @copyright  2026 Dixeo (contact@dixeo.com)
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define(['core/str'], function(str) {

    /**
     * Whether an answer object is marked correct.
     *
     * @param {Object|null} answer
     * @return {boolean}
     */
    const isAnswerCorrect = function(answer) {
        return !!(answer && answer.iscorrect == 1);
    };

    /**
     * Find whether selected answers match correct indices for a question.
     *
     * @param {Object} question Question with answers[].iscorrect
     * @param {number[]} selectedIds Storage answer indices selected by user.
     * @return {{iscorrect: boolean, haspartial: boolean}}
     */
    const evaluateQuestion = function(question, selectedIds) {
        let hascorrect = false;
        let haswrong = false;

        question.answers.forEach(function(answer, index) {
            const selected = selectedIds.indexOf(index) !== -1;
            const correct = isAnswerCorrect(answer);
            if (selected && correct) {
                hascorrect = true;
            }
            if (selected && !correct) {
                haswrong = true;
            }
            if (!selected && correct) {
                haswrong = true;
            }
        });

        const iscorrect = hascorrect && !haswrong;
        const haspartial = hascorrect && haswrong;
        return {iscorrect: iscorrect, haspartial: haspartial};
    };

    /**
     * Strip HTML to plain text for tutor context payloads.
     *
     * @param {string} html
     * @return {string}
     */
    const plainText = function(html) {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || div.innerText || '').trim();
    };

    /**
     * Initialise embed practice player inside a container.
     *
     * @param {HTMLElement} root Container holding rendered player HTML.
     * @param {Object} config
     * @param {Array} config.questions Full question data including iscorrect.
     * @param {Function} [config.onAnswer]
     * @param {Function} [config.onFinish] Called with {score, total} when Finish is clicked.
     * @param {Function} [config.onRestart] Called with {score, total}|null when Restart is clicked.
     * @param {Object} [config.initialState] Saved progress to restore after reload.
     * @param {Function} [config.onStateChange] Called when answer progress changes.
     * @return {{destroy: Function}}
     */
    const init = function(root, config) {
        const questions = config.questions || [];
        const onAnswer = typeof config.onAnswer === 'function' ? config.onAnswer : function() {};
        const onFinish = typeof config.onFinish === 'function' ? config.onFinish : function() {};
        const onRestart = typeof config.onRestart === 'function' ? config.onRestart : function() {};
        const onStateChange = typeof config.onStateChange === 'function' ? config.onStateChange : function() {};
        const initialState = config.initialState || null;
        const answerResults = [];
        const selectedByQuestion = [];
        let currentQuestionIndex = 0;
        let showingResults = false;
        let lastResult = null;

        const stringKeys = ['questionsuccess', 'questionfail', 'questionpartial', 'embed_finish'];
        let strings = {};

        const loadStrings = str.get_strings(stringKeys.map(function(key) {
            return {key: key, component: 'mod_simplequiz2'};
        })).then(function(loaded) {
            stringKeys.forEach(function(key, i) {
                strings[key] = loaded[i];
            });
        });

        const emitStateChange = function() {
            onStateChange({
                answerResults: answerResults.slice(),
                selectedAnswerIds: selectedByQuestion.map(function(ids) {
                    return (ids || []).slice();
                }),
                currentQuestionIndex: currentQuestionIndex,
                showingResults: showingResults,
                score: lastResult ? lastResult.score : null,
                total: questions.length,
            });
        };

        /**
         * Paint a previously submitted answer onto the DOM.
         *
         * @param {number} questionId
         * @param {number[]} selectedIds
         * @param {boolean} iscorrect
         * @param {boolean} haspartial
         */
        const paintAnsweredQuestion = function(questionId, selectedIds, iscorrect, haspartial) {
            const question = questions[questionId];
            if (!question) {
                return;
            }

            const container = root.querySelector('.question-container[data-questionid="' + questionId + '"]');
            if (!container) {
                return;
            }

            container.querySelectorAll('.answer-container').forEach(function(el) {
                const answerId = parseInt(el.dataset.answerid, 10);
                el.disabled = true;
                el.classList.remove('selected', 'question-success', 'question-fail');
                if (selectedIds.indexOf(answerId) !== -1) {
                    if (isAnswerCorrect(question.answers[answerId])) {
                        el.classList.add('question-success');
                    } else {
                        el.classList.add('question-fail');
                    }
                } else if (isAnswerCorrect(question.answers[answerId])) {
                    el.classList.add('question-success');
                }
            });

            const checkBtn = container.querySelector('.check-answer');
            if (checkBtn) {
                checkBtn.style.display = 'none';
            }
            const nextBtn = container.querySelector('.next-question');
            if (nextBtn) {
                nextBtn.style.display = 'none';
            }

            const statusEl = container.querySelector('.question-status[data-questionid="' + questionId + '"]');
            if (statusEl) {
                statusEl.classList.remove('question-status-success', 'question-status-partial', 'question-status-fail');
                if (iscorrect) {
                    statusEl.textContent = strings.questionsuccess || '';
                    statusEl.classList.add('question-status-success');
                } else if (haspartial) {
                    statusEl.textContent = strings.questionpartial || '';
                    statusEl.classList.add('question-status-partial');
                } else {
                    statusEl.textContent = strings.questionfail || '';
                    statusEl.classList.add('question-status-fail');
                }
            }
        };

        /**
         * Restore pending (unchecked) answer selections for one question.
         *
         * @param {number} questionId
         * @param {number[]} selectedIds
         */
        const paintPendingSelections = function(questionId, selectedIds) {
            const container = root.querySelector('.question-container[data-questionid="' + questionId + '"]');
            if (!container || !selectedIds.length) {
                return;
            }

            container.querySelectorAll('.answer-container').forEach(function(el) {
                const answerId = parseInt(el.dataset.answerid, 10);
                el.classList.toggle('selected', selectedIds.indexOf(answerId) !== -1);
            });
        };

        /**
         * Reset the player to the first question.
         */
        const resetToStart = function() {
            const previousResult = lastResult ? {
                score: lastResult.score,
                total: lastResult.total,
            } : null;

            answerResults.length = 0;
            selectedByQuestion.length = 0;
            currentQuestionIndex = 0;
            showingResults = false;
            root.querySelectorAll('.question-container').forEach(function(q) {
                const qid = parseInt(q.dataset.questionid, 10);
                q.style.display = qid === 0 ? 'block' : 'none';
                q.querySelectorAll('.answer-container').forEach(function(el) {
                    el.classList.remove('selected', 'question-success', 'question-fail');
                    el.disabled = false;
                });
                const checkBtn = q.querySelector('.check-answer');
                if (checkBtn) {
                    checkBtn.style.display = '';
                }
                const nextBtn = q.querySelector('.next-question');
                if (nextBtn) {
                    nextBtn.style.display = 'none';
                }
                const statusEl = q.querySelector('.question-status');
                if (statusEl) {
                    statusEl.textContent = '';
                    statusEl.className = 'question-status';
                }
            });

            const showResults = root.querySelector('#simplequiz_container button.show-results');
            if (showResults) {
                showResults.style.display = 'none';
            }

            const questionsContainer = root.querySelector('#simplequiz-questions');
            const resultContainer = root.querySelector('#simplequiz-result');
            if (questionsContainer) {
                questionsContainer.style.display = '';
            }
            if (resultContainer) {
                resultContainer.style.display = 'none';
            }
            if (finishBtn) {
                finishBtn.style.display = 'none';
            }
            lastResult = null;
            emitStateChange();
            onRestart(previousResult);
        };

        const detailsEl = root.querySelector('#simplequiz-result #details');
        const restartBtn = root.querySelector('#simplequiz_container button.restart');
        let finishBtn = null;

        if (detailsEl && restartBtn) {
            const actionsWrap = document.createElement('div');
            actionsWrap.className = 'simplequiz2-embed__result-actions';
            restartBtn.parentNode.insertBefore(actionsWrap, restartBtn);
            actionsWrap.appendChild(restartBtn);

            finishBtn = document.createElement('button');
            finishBtn.type = 'button';
            finishBtn.className = 'finish-embed';
            finishBtn.style.display = 'none';
            loadStrings.then(function() {
                finishBtn.textContent = strings.embed_finish || 'Finish';
            });
            finishBtn.addEventListener('click', function() {
                if (lastResult) {
                    onFinish(lastResult);
                }
            });
            actionsWrap.appendChild(finishBtn);
        }

        if (restartBtn) {
            restartBtn.addEventListener('click', function() {
                resetToStart();
            });
        }

        root.querySelectorAll('.question-container .answer-container').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const questionContainer = btn.closest('.question-container');
                if (!questionContainer) {
                    return;
                }
                const questionId = parseInt(questionContainer.dataset.questionid, 10);
                if (typeof answerResults[questionId] === 'boolean') {
                    return;
                }

                const wasSelected = btn.classList.contains('selected');
                questionContainer.querySelectorAll('.answer-container').forEach(function(el) {
                    el.classList.remove('selected');
                });
                if (!wasSelected) {
                    btn.classList.add('selected');
                }

                const selectedIds = [];
                questionContainer.querySelectorAll('.answer-container.selected').forEach(function(el) {
                    selectedIds.push(parseInt(el.dataset.answerid, 10));
                });
                selectedByQuestion[questionId] = selectedIds;
                emitStateChange();
            });
        });

        root.querySelectorAll('.question-container button.check-answer').forEach(function(checkBtn) {
            checkBtn.addEventListener('click', function() {
                loadStrings.then(function() {
                    const questionId = parseInt(checkBtn.dataset.questionid, 10);
                    const question = questions[questionId];
                    if (!question) {
                        return;
                    }

                    const selector = '.question-container[data-questionid="' + questionId + '"] .answer-container.selected';
                    const selectedEls = root.querySelectorAll(selector);
                    const selectedIds = [];
                    selectedEls.forEach(function(el) {
                        selectedIds.push(parseInt(el.dataset.answerid, 10));
                    });

                    const evaluation = evaluateQuestion(question, selectedIds);

                    selectedByQuestion[questionId] = selectedIds.slice();
                    paintAnsweredQuestion(questionId, selectedIds, evaluation.iscorrect, evaluation.haspartial);

                    let chosenText = '';
                    let correctText = '';
                    selectedIds.forEach(function(id) {
                        if (question.answers[id]) {
                            chosenText = plainText(question.answers[id].text);
                        }
                    });
                    question.answers.forEach(function(ans) {
                        if (isAnswerCorrect(ans)) {
                            correctText = plainText(ans.text);
                        }
                    });

                    onAnswer({
                        questionIndex: questionId,
                        questionText: plainText(question.text),
                        chosenText: chosenText,
                        correctText: correctText,
                        isCorrect: evaluation.iscorrect,
                    });

                    answerResults[questionId] = evaluation.iscorrect;

                    const nextquestion = root.querySelector(
                        '.question-container[data-questionid="' + (questionId + 1) + '"]'
                    );
                    if (!nextquestion) {
                        const showResultsBtn = root.querySelector('#simplequiz_container button.show-results');
                        if (showResultsBtn) {
                            showResultsBtn.style.display = 'block';
                        }
                    } else {
                        const nextBtn = root.querySelector('button.next-question[data-questionid="' + questionId + '"]');
                        if (nextBtn) {
                            nextBtn.style.display = 'block';
                        }
                    }

                    emitStateChange();
                });
            });
        });

        root.querySelectorAll('.question-container button.next-question').forEach(function(nextBtn) {
            nextBtn.addEventListener('click', function() {
                const questionId = parseInt(nextBtn.dataset.questionid, 10);
                const current = root.querySelector('.question-container[data-questionid="' + questionId + '"]');
                const next = root.querySelector('.question-container[data-questionid="' + (questionId + 1) + '"]');
                if (current) {
                    current.style.display = 'none';
                }
                if (next) {
                    next.style.display = 'block';
                    currentQuestionIndex = questionId + 1;
                    const qtext = next.querySelector('.question-text');
                    if (qtext) {
                        qtext.focus();
                    }
                    emitStateChange();
                }
            });
        });

        const showResultsBtn = root.querySelector('#simplequiz_container button.show-results');
        if (showResultsBtn) {
            showResultsBtn.addEventListener('click', function() {
                let score = 0;
                answerResults.forEach(function(correct) {
                    if (correct) {
                        score++;
                    }
                });

                const questionsContainer = root.querySelector('#simplequiz-questions');
                const resultContainer = root.querySelector('#simplequiz-result');
                if (questionsContainer) {
                    questionsContainer.style.display = 'none';
                }
                if (resultContainer) {
                    resultContainer.style.display = 'flex';
                    const currentScore = resultContainer.querySelector('.current-score');
                    if (currentScore) {
                        currentScore.textContent = score + ' / ' + questions.length;
                    }
                }
                if (finishBtn) {
                    finishBtn.style.display = 'inline-block';
                }

                showingResults = true;
                lastResult = {
                    score: score,
                    total: questions.length,
                };
                emitStateChange();
            });
        }

        if (initialState) {
            loadStrings.then(function() {
                const savedResults = initialState.answerResults || [];
                const savedSelections = initialState.selectedAnswerIds || [];

                savedResults.forEach(function(correct, questionId) {
                    if (typeof correct !== 'boolean') {
                        return;
                    }
                    answerResults[questionId] = correct;
                    const selectedIds = savedSelections[questionId] || [];
                    selectedByQuestion[questionId] = selectedIds.slice();
                    const evaluation = evaluateQuestion(questions[questionId], selectedIds);
                    paintAnsweredQuestion(questionId, selectedIds, correct, evaluation.haspartial);
                });

                currentQuestionIndex = typeof initialState.currentQuestionIndex === 'number'
                    ? initialState.currentQuestionIndex
                    : 0;

                root.querySelectorAll('.question-container').forEach(function(q) {
                    const qid = parseInt(q.dataset.questionid, 10);
                    q.style.display = qid === currentQuestionIndex ? 'block' : 'none';
                });

                if (typeof savedResults[currentQuestionIndex] !== 'boolean') {
                    paintPendingSelections(currentQuestionIndex, savedSelections[currentQuestionIndex] || []);
                } else if (questions[currentQuestionIndex + 1]) {
                    const nextBtn = root.querySelector(
                        'button.next-question[data-questionid="' + currentQuestionIndex + '"]'
                    );
                    if (nextBtn) {
                        nextBtn.style.display = 'block';
                    }
                }

                if (initialState.showingResults) {
                    const score = typeof initialState.score === 'number' ? initialState.score : 0;
                    const questionsContainer = root.querySelector('#simplequiz-questions');
                    const resultContainer = root.querySelector('#simplequiz-result');
                    if (questionsContainer) {
                        questionsContainer.style.display = 'none';
                    }
                    if (resultContainer) {
                        resultContainer.style.display = 'flex';
                        const currentScore = resultContainer.querySelector('.current-score');
                        if (currentScore) {
                            currentScore.textContent = score + ' / ' + questions.length;
                        }
                    }
                    if (finishBtn) {
                        finishBtn.style.display = 'inline-block';
                    }
                    showingResults = true;
                    lastResult = {score: score, total: questions.length};
                } else {
                    const allAnswered = questions.length > 0 &&
                        savedResults.length >= questions.length &&
                        savedResults.every(function(v) { return typeof v === 'boolean'; });
                    const showBtn = root.querySelector('#simplequiz_container button.show-results');
                    if (allAnswered && showBtn) {
                        showBtn.style.display = 'block';
                    }
                }
            });
        }

        return {
            destroy: function() {
                root.innerHTML = '';
            },
        };
    };

    return {
        init: init,
    };
});
