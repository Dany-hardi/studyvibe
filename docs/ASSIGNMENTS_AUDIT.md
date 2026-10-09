# Assignments: audit and what was put in place

*9 October 2026. Written after reading every file involved and running the feature end to end with test accounts.*

## 1. What existed before

A teacher can switch on "assignment" on a lesson (title, instructions, accepted formats, deadline, file and/or link). A student opens the
lesson, types their name and matricule, attaches a file or a link and sends it. The teacher sees a table of submissions in the
"Devoirs" tab and can download a CSV and a ZIP of the files.

That is where it stopped. **Nothing in the app let a teacher mark a submission, and nothing let a student see a mark or any feedback.**
The CSV was the only "report", and it had no marks.

## 2. Defects found by running it (all reproduced, all fixed)

| # | Defect | What happened in the test | Fix |
|---|---|---|---|
| 1 | The deadline was only displayed | A deadline set in 2020 still accepted a submission in 2026 | The server checks the deadline; late work is refused, or accepted and flagged "en retard" when the teacher allows it |
| 2 | Only the file extension was checked | A text file containing PHP code, named `devoir.pdf`, was accepted and stored | The content must match the format: a PDF starts with `%PDF`, a DOCX is a zip with a Word document inside, text files must be text, images must be images. PHP, executables and scripts are refused whatever the teacher lists |
| 3 | The student typed their own identity | A student sent the name "Quelqu'un d'autre" and matricule "99Z999"; both were saved and shown to the teacher and in the CSV | Name and matricule come from the account. A student with no matricule is asked to complete their profile first |
| 4 | Any teacher could download any student's file | A teacher of another course downloaded a student's file (HTTP 200) | Only the student, the teacher of that course, and a promoter. Files are always sent as attachments |
| 5 | One submission only, with no way back | A second attempt was refused, even before the deadline, even if the first was a mistake | Resubmission before the deadline (the teacher can switch it off); the teacher can ask for a new version, allowed even after the deadline; every earlier version is kept with its mark and feedback |
| 6 | No notification | Teacher and student were never told anything | In-app notifications and emails both ways (submitted, marked, new version requested) |
| 7 | Reports had no marks and used the declared name | CSV only, with whatever name the student had typed | Marks sheet per student and per assignment (Excel and PDF), and the CSV now has status, mark, lateness and feedback with the account name |

## 3. How an assignment is corrected now

1. The teacher sets, on the lesson: the maximum mark (20 by default), whether a new submission is allowed before the deadline, whether late work is accepted.
2. The student hands in a file and/or link. The submission is "À noter" and the teacher gets a notification.
3. In **Devoirs**, each row has a **Noter** button. The marking window shows the student (name, matricule, email), the file (download), the link, the comment, the earlier versions, a mark field (comma accepted, bounded by the maximum) and a feedback box.
4. **Save**: the student is told by email and notification, and sees the mark and the feedback in the lesson and in *Résultats → Mes devoirs*.
5. If the work is not good enough: **Demander une nouvelle version** with a note. The student can resubmit even after the deadline. The previous mark and feedback are archived and the new version starts unmarked.
6. **Exports** (Devoirs tab): *Notes (Excel)* and *Notes (PDF)*, one row per enrolled student (matricule, name, email), one column per assignment, then total, maximum and percentage ("Non rendu" and "À noter" are written out); plus the CSV and the ZIP of files.

## 4. Where this stands against the usual LMS standard (Moodle, Canvas, Blackboard)

| Capability | Now |
|---|---|
| Hand-in of a file and/or link, formats and size limit chosen by the teacher | Yes |
| Server-side deadline, late policy, resubmission policy | Yes |
| Content validation of uploads | Yes |
| Mark on a scale, written feedback, release to the student with a notification | Yes |
| Request for a new version, versions history | Yes |
| Student sees status, mark and feedback; list of all their assignments | Yes |
| Marks export (Excel, PDF) with matricule | Yes |
| Audit trail of who marked what and when | Partly: `graded_by` and `graded_at` are stored and every action is in the audit log |
| **Not yet**, in priority order | |
| Rubrics (criteria with points) and reusable comment banks | No. The next step for large classes |
| Feedback file or annotated copy returned to the student | No |
| Extensions for one student (a personal deadline) | No. Today the teacher can ask for a new version, which has the same effect |
| Hide marks until the teacher releases them for the whole class | No. Marks reach the student as soon as they are saved |
| Plagiarism check (similarity between submissions or against the web) | No. Needs an external service or a heavier comparison engine |
| Group assignments | No |
| Anonymous marking | No |
| Marks counted into the course grade automatically | No. The course grade and the certificate still come from the final exam; assignment marks are reported separately |
| Marking from a phone with swipe between students (Canvas SpeedGrader style) | No |

## 5. What to decide next

1. **Should the assignment mark count in the course result or the certificate?** If yes, we need a weight per assignment and a rule (for example 30 % assignments, 70 % exam). Today they are reported side by side.
2. **Release of marks.** For a graded assignment with many students, most universities mark everything first and release together. A "release all" switch per assignment is small to add.
3. **Rubrics** if teachers will mark long reports or projects.

## 6. How to check it yourself

`php tests/integration/assignments_flow.php` (needs the app running): 52 checks covering the rules, uploads, identity, downloads, marking, new versions and the marks sheet.
