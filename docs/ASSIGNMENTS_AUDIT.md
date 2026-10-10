# Assignments: audit and what was put in place

*9 October 2026. Written after reading every file involved and running the feature end to end with test accounts. Updated 10 October 2026: rubrics, an annotated copy returned to the student, a similarity check and release of marks are now built (section 7).*

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
| Audit trail of who marked what and when | Partly: `graded_by` and `graded_at` are stored and every action (marking, releasing, withdrawing, similarity check) is in the audit log |
| Rubrics (criteria with points and levels), the mark is the sum of the criteria | Yes (section 7) |
| Annotated copy or model answer returned to the student | Yes (section 7) |
| Release of marks: hold them and release all together | Yes (section 7) |
| Similarity check between submissions, with the shared passages | Yes (section 7); not against the web |
| **Not yet**, in priority order | |
| Extensions for one student (a personal deadline) | No. Today the teacher can ask for a new version, which has the same effect |
| Similarity against the web or earlier years | No. Needs an external service; today the check compares the students of one assignment with each other |
| Reusable comment banks | No |
| Group assignments | No |
| Anonymous marking | No |
| Marks counted into the course grade automatically | No. The course grade and the certificate still come from the final exam; assignment marks are reported separately |
| Marking from a phone with swipe between students (Canvas SpeedGrader style) | No |

## 5. What to decide next

1. **Should the assignment mark count in the course result or the certificate?** If yes, we need a weight per assignment and a rule (for example 30 % assignments, 70 % exam). Today they are reported side by side.
2. **Reusable comment banks** and a similarity check against the web, the next steps for large classes.
3. **Extensions for one student**, group assignments, anonymous marking (see the table above).

## 6. How to check it yourself

`php tests/integration/assignments_flow.php` (needs the app running): 52 checks covering the rules, uploads, identity, downloads, marking, new versions and the marks sheet.

`php tests/integration/assignments_advanced.php` (same): 101 checks covering rubrics, release of marks, annotated copies and the similarity check.

## 7. Rubrics, annotated copy, similarity check, release of marks

### Rubric (marking grid)
- **Where:** the lesson form, assignment section, "Barème". Each criterion has a title, a maximum number of points, an optional description and optional levels written one per line (`Excellent : 4`). Up to 20 criteria, 1000 points in all.
- **Marking:** the marking window shows one block per criterion with the level names as shortcuts, a points field and a comment. The **server adds the points up**: whatever total the browser sends is ignored, every criterion must be given points between 0 and its maximum.
- **Scale:** with a rubric the maximum mark is the total of the criteria (the field is locked).
- **Snapshot:** when a work is marked, the grid with the points and comments is stored on the submission. Editing the rubric afterwards never changes marks already given (the form says so).
- **Student:** sees the grid on the assignment before handing in, and the detail by criterion (points, level, comment) once the mark is released, in the lesson and in *Résultats → Mes devoirs*.

### Annotated copy
- **Where:** the marking window, "Copie annotée ou corrigé". PDF, DOCX, DOC, ODT, TXT, MD, PNG, JPG or ZIP, 20 MB at most, checked like any upload (extension, real content, size; PHP source renamed `.pdf` is refused).
- **Access:** `download.php?type=assignment_feedback`. The student gets it **only when the mark is released**; the teacher of the course and a promoter always; nobody else (another teacher or student gets 403).
- A new copy replaces the old one (which is deleted); the teacher can remove it. When the student sends a new version, the annotated copy goes to the history (still downloadable by the teacher).

### Release of marks
- **Where:** the lesson form, "Publication des notes": *immediate* (the default and the old behaviour) or *after my approval*.
- **In manual mode:** a mark is saved but **hidden**. The student sees "handed in / waiting for a mark" and gets nothing: no mark, no feedback, no grid, no annotated copy, no e-mail, no notification, not even the marking date (this is enforced on the server in the lesson API and on the results page, not only in the interface). The work stays locked against a new version.
- **Releasing:** "Publier les notes (N)" on the assignment card releases every marked-but-hidden work and tells each student (e-mail and notification), or "Publier cette note" in the marking window for one. "Masquer les notes publiées" hides them again (nobody is told).
- A mark already released stays released when the teacher corrects it (the student is told again only if the mark changed).
- The teacher always sees every mark (marks sheet, exports); only the student's side is held.
- Marks given before this feature stay visible (`released_at` was filled with `graded_at`).

### Similarity check
- **Where:** the assignment card, "Vérifier les ressemblances" (teacher only; students never see it, the endpoint answers 403).
- **How:** the text of each handed-in file is extracted (txt, md, code, ipynb, docx, odt, pdf: `pdftotext` when installed, otherwise a basic reader), normalised (lower case, no accents, no punctuation) and cut into overlapping 5-word shingles. Two works are compared by the share of the **smaller** one that the other contains (containment): copying a whole report into a longer one scores about 100 %.
- **Boilerplate:** passages found in half of the works or more (the statement copied by everybody, a template) are ignored (from 4 works on).
- **Result:** pairs from 15 % up, with names, matricules, the matching passages, and a level (30 % high, 60 % very high). A chip with the worst score appears on each submission row and in the marking window. The page says when new work arrived since the analysis.
- **Not compared, and why:** no file (link only), unreadable (scan, picture, unsupported format), too short (under 30 words). They are listed after each run.
- **Limits, to say plainly:** it compares students with each other, not with the web or earlier years; a score is a reason to look, never a proof. Scanned PDFs cannot be read. Without `pdftotext` the PDF reader only handles simple PDFs.

### What changed in the code
`lib/AssignmentRubric.php`, `lib/AssignmentSimilarity.php`, `lib/Assignments.php` (grade with a rubric and a file, release, withdraw, student view and state), `teacher/grade-assignment.php`, `teacher/assignment-similarity.php`, `download.php`, `student/get-lesson-details.php`, `student/dashboard.php`, `assets/js/assignment-rubric.js`, `assets/js/assignment-grading.js`, `assets/js/student.js`, `Database.php` (migration 2.6j, also `schema_v15_assignments_advanced.sql`).
