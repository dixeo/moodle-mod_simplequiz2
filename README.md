# SimpleQuiz

## Description

SimpleQuiz delivers fast, low-friction **multiple-choice practice** with instant feedback, attempt tracking, activity completion rules, and a one-click path to migrate your questions into Moodle’s full **Quiz** when you outgrow the lightweight format.

## About

SimpleQuiz (`mod_simplequiz2`) is an **activity module** for running **multiple-choice questions** with minimal setup. Authors define questions and answers in the activity form, including support for **multiple correct answers** so learners can receive **partial-credit style** outcomes. Learners answer **one question at a time**, **check** their response for immediate feedback, and move forward at their own pace. The interface includes **accessible labels** for images, video, audio, and math content where applicable.

**Results and attempts:** A closing **summary** highlights the session score and **best score** achieved, encouraging retries without losing the highest result. Administrators can **purge attempts** when you need to reset learner data.

**Activity completion** can require a **minimum number of attempts**, so SimpleQuiz fits common completion workflows.

**Graduate to Quiz:** Built-in **export to Quiz** converts compatible questions into Moodle’s native **Quiz** activity (multiple choice), so you can move from lightweight practice to full quiz analytics, question banks, and proctoring-ready workflows when your use case demands it. **Export requires the standard Quiz module** (`mod_quiz`) to be available.

## Requirements

Minimum **Moodle 4.3** (`$plugin->requires` in `version.php`, currently `2023100900`). The activity form uses the site **TinyMCE** editor (legacy Atto markup is still recognised in client-side validation). Empty question slots are rendered as plain textareas and TinyMCE is initialised in the browser when a slot is shown, which avoids broken editors on hidden fieldsets. Tested on Moodle 4.3–4.5 and 5.x.

## Installation

Install the release ZIP `mod_simplequiz2-<release>.zip` under **Site administration → Plugins → Install plugins**. You can also copy the `simplequiz2` directory to `mod/simplequiz2`.

## Source code

- **Repository:** https://github.com/dixeo/moodle-mod_simplequiz2  
- **Bug tracker:** https://github.com/dixeo/moodle-mod_simplequiz2/issues

## External services and subscriptions

The activity player calls `mod_simplequiz2_check_question` and `mod_simplequiz2_get_attempt_results` (`mod/simplequiz2:view`). The tutor embed uses `mod_simplequiz2_render_embed` and `mod_simplequiz2_check_embed_question` (`block/dixeo_tutor:talktotutor`); they are unavailable when block_dixeo_tutor is not installed, and the posted question list is limited to the activity limits below (plus a payload size, JSON depth and per-field length limit, see `classes/util/embed_payload.php`). The question form uses `mod_simplequiz2_get_unused_draft_itemids` and `mod_simplequiz2_render_question_summary` (`moodle/course:manageactivities`), which returns the cleaned summary of an edited question. No paid subscriptions.

## Capabilities

| Capability | Purpose |
|------------|---------|
| `mod/simplequiz2:addinstance` | Create a SimpleQuiz activity in a course (editing teacher / manager by default). |
| `mod/simplequiz2:view` | Access the activity and submit attempts (student, teacher, editing teacher, manager by default). |

**Export to Quiz** requires, in the course context, **`moodle/course:manageactivities`**, **`mod/quiz:addinstance`** and **`moodle/question:add`**, plus **`moodle/course:update`** in a single activity course because the course format is switched to Topics (see `simplequiz2_convert_required_capabilities()` in `lib.php`). The conversion runs in one database transaction and leaves the course unchanged when it fails. Attempt deletion uses **Course reset** (see below).

## Attempts

Each question is answered once per attempt, in order; the attempt is finished, counted and graded when its last question is answered. Opening the activity reuses the user's open attempt while it has no answer, so page views do not add attempt rows.

## Backup and restore

The module advertises **`FEATURE_BACKUP_MOODLE2`** and includes `backup/moodle2` classes. Course backups are **expected** to include SimpleQuiz settings and user attempt data. **Verify** backup and restore (and duplicate activity) on your Moodle version and database engine before production use.

## Privacy

The plugin implements the Moodle **Privacy API** (`classes/privacy/provider.php`): metadata for attempt tables and file areas, export of attempt summaries and per-session answers, and deletion aligned with data requests and context expiry. **Course reset** still removes attempts via the option to delete SimpleQuiz attempt data (see `simplequiz2_reset_userdata` in `lib.php`).

## Limitations

- **Question model:** Up to **25** questions and **5** answers per question (`MOD_SIMPLEQUIZ2_MAX_QUESTION_NB`, `MOD_SIMPLEQUIZ2_MAX_ANSWER_NB` in `lib.php`). Grading scale is oriented to a **100** point scale (`MOD_SIMPLEQUIZ2_GRADE_MAX`).
- **Question types:** Multiple choice only; export to Quiz creates **multichoice** questions in `mod_quiz`.

## Plugin dependencies

- **None** for installing the activity.
- **Export to Quiz** requires the standard **Quiz** module (`mod_quiz`) to be installed and available.

## Databases

Activity deletion and export to Quiz use Moodle’s Data Manipulation API with bound parameters. Both are expected to work on MySQL/MariaDB and PostgreSQL. Automated tests run on MariaDB and PostgreSQL.

# Support

For documentation, licensing or technical support:

**Dixeo**

https://www.dixeo.com

support@dixeo.com

# License

Copyright © Ministère de l'Éducation nationale français and Dixeo

Licensed under the GNU General Public License v3.0 or later.
