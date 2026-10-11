<?php
/** Student guides, English. Same ids as the French file. Returns [id => ['q', 't', 'a']]. */
return [
'create-account' => ['q' => 'How do I create my account?', 't' => 'Three minutes, four details, one email to confirm.', 'a' => <<<'HTML'
<p>You can create your account in two ways: from the StudyVibe home page, or straight from the link to a course that your teacher sent you. Either way, the steps are the same.</p>
<ol>
<li>Choose <strong>Create an account</strong> and enter your <strong>full name</strong> and your <strong>email address</strong>.</li>
<li>Enter your <strong>phone number</strong> (for example <code>6 12 34 56 78</code> or <code>+237 612 345 678</code>). It is used for evaluation reminders, and you will be asked to verify it by SMS after your first sign-in.</li>
<li>Choose a <strong>password of at least 8 characters</strong>.</li>
<li>Submit, then open the message from StudyVibe in your mailbox and click the confirmation button.</li>
</ol>
<p class="lif-note">Your name, as you write it here, will appear on your transcripts and certificates. Take a moment to write it the way it appears on your official documents.</p>
<p>An email address can only be used for one account. If you see “This email address is already registered”, you already have an account: use the “Already registered” tab or the forgotten password procedure.</p>
HTML],
'email-confirm' => ['q' => 'I did not receive the confirmation email', 't' => 'Where to look, how long the link lasts, how to get a new one.', 'a' => <<<'HTML'
<p>After you sign up, StudyVibe sends you a message to confirm that the address is yours. If it is late, here is what to do, in order.</p>
<ol>
<li><strong>Wait two or three minutes</strong>, then refresh your inbox.</li>
<li>Look in your <strong>junk or spam folder</strong>, and in the “Promotions” tab if your mailbox has one.</li>
<li>Check that the address you typed at sign-up has <strong>no typo</strong>.</li>
<li>On the “Confirm your email” page, use the button that <strong>resends the message</strong>. A new email goes out straight away.</li>
</ol>
<p>The confirmation link stays valid for <strong>48 hours</strong>. After that it is no longer accepted, but nothing is lost: simply ask for a new one from the same page.</p>
<p>Once you have clicked the button in the email, go back to the page you left open: it checks your confirmation automatically and lets you in.</p>
HTML],
'login-help' => ['q' => 'I cannot sign in, or I forgot my password', 't' => 'Error messages, the security pause and resetting your password.', 'a' => <<<'HTML'
<h5>The message “Incorrect email address or password”</h5>
<p>Check the address (the one you signed up with) and the password, paying attention to capital letters and caps lock.</p>
<h5>Several failed attempts in a row</h5>
<p>For security, after <strong>5 failed attempts</strong> sign-in is paused for <strong>15 minutes</strong>. Wait for that time to pass before trying again, or reset your password straight away.</p>
<h5>Resetting my password</h5>
<ol>
<li>On the sign-in page, choose <strong>Forgot password</strong> and enter your email address.</li>
<li>Open the message and click the link. It is valid for <strong>2 hours</strong>.</li>
<li>Choose a new password, then sign in.</li>
</ol>
<p class="lif-note">To protect accounts, the message shown is always the same whether the address exists or not (“If this address exists, an email has been sent”). If nothing arrives, check your spam folder and the address.</p>
<h5>The message “This account has been deactivated”</h5>
<p>Your account has been suspended by the platform administration. Contact them: neither you nor your teacher can reactivate it yourselves.</p>
HTML],
'profile' => ['q' => 'My profile, my photo and my matricule', 't' => 'What you can change, and why the matricule matters.', 'a' => <<<'HTML'
<p>Open your profile by clicking your name at the bottom of the menu (or your photo, on a phone).</p>
<ul>
<li><strong>Photo</strong>: JPG, PNG or GIF, 2 MB at most.</li>
<li><strong>Full name</strong>: you can correct it. It appears on your transcripts and certificates.</li>
<li><strong>Email address</strong>: it <strong>cannot be changed</strong>, because it identifies your account. If you have lost access to that mailbox, contact the administration.</li>
<li><strong>Matricule</strong>: it appears on your transcripts, your reports and in your teachers’ exports. It is required to save your marks and to hand in an assignment.</li>
<li><strong>Preferences</strong>: language (French or English) and light or dark theme.</li>
</ul>
<h5>The checks made on the matricule</h5>
<ul>
<li>It must have a <strong>valid format</strong>; otherwise you will see “This matricule does not look valid”.</li>
<li>The first digits show the <strong>year</strong>: a year that has not arrived yet is refused.</li>
<li>It must be <strong>unique</strong>: if it is already registered on another account, the platform tells you. Correct it if you made a mistake; if it really is yours, contact the administration.</li>
</ul>
<p>If you are asked “Your matricule is required”, just type it into the window: it takes a few seconds and you will not have to do it again.</p>
HTML],
'security' => ['q' => 'Phone, SMS and two-factor authentication', 't' => 'Verify or change your number, get reminders, protect your account.', 'a' => <<<'HTML'
<p>Everything is under <strong>Profile → Account security</strong>.</p>
<h5>Your phone number</h5>
<p>To verify or change your number, enter it, ask for the code, then type the 6 digits received by SMS. The code is <strong>valid for 10 minutes</strong>. If the SMS is late, check the number and ask for a new code.</p>
<h5>SMS reminders</h5>
<p>You can receive a text message when a live evaluation is scheduled in one of your courses. It is a setting you turn on or off as you wish; verification codes, however, are always sent.</p>
<h5>Two-factor authentication (recommended)</h5>
<ol>
<li>Install an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, Authy…).</li>
<li>Scan the QR code shown (or type the key if scanning is not possible).</li>
<li>Enter the 6-digit code the app displays to switch the protection on.</li>
</ol>
<p>From then on you will be asked for this code at each sign-in, on top of your password. <strong>Keep your recovery codes safe</strong>: each works only once and replaces the app if you lose your phone. They are shown only once.</p>
<p class="lif-note">Some institutions require two-factor authentication: in that case the platform asks you to turn it on before you continue.</p>
HTML],
'notifications' => ['q' => 'Notifications and emails: what will I receive?', 't' => 'The bell, the emails and what triggers them.', 'a' => <<<'HTML'
<p>The <strong>bell</strong> (at the bottom of the menu on a computer, at the top on a phone) gathers your notifications. A small counter shows the ones you have not read; “Mark all as read” resets it.</p>
<p>You are told, in the app and most of the time by email too, when:</p>
<ul>
<li>your teacher <strong>answers your question</strong> under a lesson or your request;</li>
<li>an <strong>assignment is marked</strong> and the mark is released, or a <strong>new version</strong> is requested from you;</li>
<li>a <strong>live evaluation</strong> is scheduled or launched, and <strong>15 minutes before it starts</strong> if you are registered;</li>
<li>a <strong>decision</strong> is made on a contestation;</li>
<li>you earn a <strong>certificate</strong>.</li>
</ul>
<p>If an expected email does not show up, look in your junk folder. A mark that the teacher chose to release later produces no message before the release: that is normal.</p>
HTML],

'join-course' => ['q' => 'How do I join a course?', 't' => 'With your teacher’s link or from the catalogue.', 'a' => <<<'HTML'
<h5>With the link your teacher sent you</h5>
<ol>
<li>Open the link. You land on the course presentation page (title, module, teacher).</li>
<li>If you are already signed in, just click <strong>Enrol now</strong>.</li>
<li>If you do not have an account yet, choose <strong>Create an account</strong>; if you do, the <strong>Already registered</strong> tab. You then return to the course page automatically to finish enrolling.</li>
</ol>
<h5>From your space</h5>
<p>Go to <strong>Courses → Catalogue</strong>, find the course (the label “Open access” or “Key required” immediately tells you whether a key is needed) and click <strong>Enrol</strong>.</p>
<p>Once enrolled, the course appears in <strong>Courses → My courses</strong> and on your home page, with your progress. Enrolment is immediate; it does not need the teacher’s approval.</p>
HTML],
'enroll-key' => ['q' => 'The course asks for an enrolment key', 't' => 'Where to find it, how to enter it, what to do if it is refused.', 'a' => <<<'HTML'
<p>Some teachers protect access to their course with an <strong>enrolment key</strong> (for example <code>CODE2026</code>). <strong>Your teacher gives it to you</strong>: in class, by message or by email. It is not shown anywhere else.</p>
<ol>
<li>Click <strong>Enrol</strong>. A “This course needs a key” window opens.</li>
<li>Enter the key and confirm.</li>
</ol>
<p>Do not worry about capital letters, extra spaces or the typographic dashes some phone keyboards add: the platform ignores them when comparing.</p>
<p>If you get “Incorrect enrolment key”, copy it from your teacher’s message instead of retyping it, and check that it is the key for <em>this</em> course. If it still fails, ask the teacher to confirm it: they may have changed it.</p>
HTML],
'enroll-blocked' => ['q' => 'I cannot enrol: “not published”, “not open yet”, “ended”', 't' => 'The three reasons an enrolment is refused.', 'a' => <<<'HTML'
<p>Enrolment is refused when the course calendar does not allow it. The message tells you the exact reason.</p>
<ul>
<li><strong>“This course is not published yet.”</strong> The teacher is still preparing the content. Come back later or ask when it will open.</li>
<li><strong>“Enrolment opens on …”</strong> The course has a start date. You can enrol from that day.</li>
<li><strong>“The enrolment period for this course has ended.”</strong> The end date has passed. Only the teacher can decide to reopen the course: write to them and explain your situation.</li>
</ul>
<p>If the link shows “Course not found” or “Invalid link”, it is incomplete or was copied wrongly. Ask your teacher for a new one.</p>
HTML],
'my-courses' => ['q' => 'Finding my courses and following my progress', 't' => 'The home page, “My courses” and the completion percentage.', 'a' => <<<'HTML'
<ul>
<li><strong>Home</strong>: the lesson to resume, the next evaluation, your latest result and your certificates. The “Resume” button takes you to the lesson where you stopped.</li>
<li><strong>Courses → My courses</strong>: all your courses, with the number of lessons completed and a progress percentage. Search accepts a course title, a module or a teacher’s name.</li>
<li><strong>Results</strong>: progress, best score and number of attempts, course by course.</li>
</ul>
<p>Progress is the share of published lessons you have completed. It can therefore go down if the teacher adds new lessons. At 100 %, the final certification exam opens.</p>
HTML],
'library' => ['q' => 'The library: resources, PDFs and videos', 't' => 'The material your teachers add outside the lessons.', 'a' => <<<'HTML'
<p>In <strong>Courses → Library</strong> you find the material your teachers add to complement their lessons: PDFs, videos, course notes and links to online resources.</p>
<ul>
<li>Each resource offers the right button: <strong>Download</strong> a document, <strong>Watch the video</strong>, <strong>Read</strong> a text or open an online resource.</li>
<li>Only the resources of <strong>your</strong> courses are visible.</li>
</ul>
<p>If the library is empty, your teachers have not published anything there yet; it fills up as the course goes on.</p>
HTML],

'lesson-flow' => ['q' => 'How does a lesson work, and how do I complete it?', 't' => 'Content, completion, quiz: the order to follow.', 'a' => <<<'HTML'
<p>Each lesson offers content (text, PDF document, video, or a mix), sometimes followed by a quiz and an assignment. Here is the usual route.</p>
<ol>
<li><strong>Open the lesson</strong> from “My courses” or the “Resume” button on the home page. The course outline shows where you are.</li>
<li><strong>Go through the whole content</strong>: read the text, turn every page of the PDF, watch the video to the end.</li>
<li>The <strong>“Mark the lesson as completed”</strong> button becomes active, and the <strong>quiz</strong> unlocks if there is one.</li>
<li>Answer the quiz: the lesson is completed when <strong>every question has an answer</strong>, right or wrong.</li>
<li>If there is an assignment, you hand it in on the same lesson (see the “Assignments” section).</li>
</ol>
<p>Lessons follow each other in order: once a lesson is completed, the next one opens. When all lessons are finished, the final exam appears in your space.</p>
HTML],
'lesson-locked' => ['q' => 'A lesson is locked or expired: why?', 't' => 'The required order and the deadlines set by the teacher.', 'a' => <<<'HTML'
<h5>“Finish the lesson … first to open this one”</h5>
<p>Lessons open in order. A lesson stays locked until the previous one is completed. The message names the lesson to finish: open it, finish its content then its quiz, and the next one unlocks.</p>
<h5>“The access period for this lesson has passed”</h5>
<p>The teacher can set a deadline for a lesson’s quiz. After that date the lesson can no longer be completed and appears as <strong>expired</strong>. If a whole course has expired, the platform says so (“All the lessons of this course have expired”). Your teacher can still change the date: if you have a justified reason, write to them from <strong>Results → My requests</strong>.</p>
<h5>The final exam is greyed out</h5>
<p>It shows “Certification after 100 %” until you have finished all the lessons of the course. It can also be closed by an evaluation deadline set by the teacher.</p>
HTML],
'video-pdf' => ['q' => 'Videos and PDF documents: what to know', 't' => 'No skipping ahead, pages to read, several videos in order.', 'a' => <<<'HTML'
<p>So that completing a lesson reflects real learning, the platform checks that the content has actually been viewed.</p>
<h5>Videos</h5>
<ul>
<li>Watch the video <strong>in order and to the end</strong>. Skipping ahead is not counted (“No skipping ahead: watch the video in order”).</li>
<li>If some passages were not seen, the player invites you to go back a little.</li>
<li>When a lesson has <strong>several videos</strong>, the next one unlocks once the previous one is finished.</li>
<li>If the connection drops, “Connection not possible right now” appears: try again in a moment, the progress already saved is not lost.</li>
</ul>
<h5>PDF documents</h5>
<ul>
<li>The “Pages read” counter moves forward as you turn pages. Read each page to continue.</li>
<li>You can zoom in, zoom out, go full screen or download the document.</li>
<li>If a document does not open inside the platform, use “Download” and read it on your device.</li>
</ul>
HTML],
'lesson-quiz' => ['q' => 'The lesson quiz: how does it work?', 't' => 'Answer saved on click, instant correction, score as a percentage.', 'a' => <<<'HTML'
<ul>
<li>The quiz <strong>unlocks</strong> when the content is finished. While it is locked, a message tells you what remains to be done.</li>
<li>Each answer is <strong>saved as soon as you choose it</strong>; there is no “send” button at the end.</li>
<li>The <strong>correction appears immediately</strong>: “Correct” or “Incorrect”, with the right answer and, when the teacher wrote one, an explanation.</li>
<li>A counter shows how many questions remain.</li>
<li>The lesson is completed when every question has an answer. Your <strong>score is the percentage of correct answers</strong>; it appears in <strong>Results → Lesson quizzes</strong>.</li>
</ul>
<p class="lif-note">Lesson quizzes are for practice and tracking your progress. A course certification is played on the final exam (see the “Certificates” section).</p>
HTML],
'notes' => ['q' => 'Taking notes during a lesson', 't' => 'Time-stamped notes on videos, saved on your device.', 'a' => <<<'HTML'
<p>While reading a lesson, the <strong>Notes</strong> tab lets you keep your remarks.</p>
<ul>
<li>For a <strong>video</strong>, give the relevant minute as <code>mm:ss</code> (for example <code>12:40</code>) then your remark.</li>
<li>For a text or a PDF, simply write your note.</li>
<li>You can delete a note at any time.</li>
</ul>
<p class="lif-note">Your notes are saved <strong>on the device you are using</strong>, not on your account. You will find them again on the same device and browser, but not on another phone or computer. If you clear your browser data they disappear: to keep them, copy them into a personal document.</p>
HTML],
'ai' => ['q' => 'The lesson assistant: what it can do', 't' => 'Summarise, explain simply, quiz you.', 'a' => <<<'HTML'
<p>The <strong>Assistant</strong> tab of the lesson offers three kinds of help, based on the lesson you have opened:</p>
<ul>
<li><strong>Summarise</strong> the lesson in a few points;</li>
<li><strong>Explain simply</strong> the notions you find difficult;</li>
<li><strong>Ask me questions</strong> to test you, with no mark at stake.</li>
</ul>
<p>You can also ask it a free question about the lesson. Open a lesson first: the assistant needs content to answer.</p>
<p class="lif-note">The assistant is a revision aid and it can be wrong. It replaces neither the lesson nor your teacher, and its answers have no value as an official correction. If in doubt, ask your teacher in the “Questions” tab.</p>
HTML],
'ask-teacher' => ['q' => 'Asking my teacher a question about a lesson', 't' => 'The “Questions” tab: who sees what, and how you are notified.', 'a' => <<<'HTML'
<ol>
<li>Open the lesson, then the <strong>Questions</strong> tab.</li>
<li>Write your question precisely (what you understood, what blocks you) and post it.</li>
<li>Your teacher is notified. You will be too, by <strong>notification and email</strong>, as soon as they reply.</li>
</ol>
<ul>
<li>Only students <strong>enrolled in the course</strong> and its teacher can read and write in these exchanges.</li>
<li>The teacher can hide a message that does not belong; stay courteous and focused on the lesson.</li>
<li>There is a limit on posts per hour to prevent abuse: if you reach it, wait a little before writing again.</li>
</ul>
<p>For a subject that is not about one particular lesson (a mark problem, a result), use <strong>Results → Write to my teacher</strong> instead (see the “Help” section).</p>
HTML],

'asg-submit' => ['q' => 'How do I hand in an assignment?', 't' => 'File or link, accepted formats, maximum size.', 'a' => <<<'HTML'
<p>The assignment is in the relevant lesson, below the quiz. It shows its title, instructions, accepted formats, deadline and, when the teacher has set one, the <strong>marking grid</strong> (“Here is what you will be marked on”).</p>
<ol>
<li>Read the instructions and the grid.</li>
<li>Attach a <strong>document</strong> (20 MB at most) <strong>and/or</strong> give a direct <strong>link</strong> to your work (GitHub, Drive…). At least one of the two is required.</li>
<li>If you wish, add a note for the teacher.</li>
<li>Click <strong>Hand in my assignment</strong>. A message confirms it was sent and your teacher is notified.</li>
</ol>
<ul>
<li><strong>Your name and matricule come from your profile</strong>: you do not type them at each submission. If your matricule is missing, the platform asks you to complete it first.</li>
<li>The <strong>content</strong> of the file is checked, not just its extension: a PDF must really be a PDF. A file whose content does not match its format, an executable or a script is refused.</li>
<li>If the file is over 20 MB, compress it or put it on a storage service and give the link.</li>
</ul>
HTML],
'asg-deadline' => ['q' => 'Deadline, late work and new versions', 't' => 'What happens after the deadline, and how to send your work again.', 'a' => <<<'HTML'
<h5>After the deadline</h5>
<p>The teacher chooses a rule, and the platform applies it strictly. Either submission is <strong>closed</strong> (“The deadline has passed: submission is closed”), or it stays <strong>open with a “late” label</strong> on your work. The message under the assignment tells you which one applies.</p>
<h5>Handing in a new version</h5>
<ul>
<li><strong>Before the deadline</strong> you can usually hand in an improved version. Some teachers switch this off: the assignment is then locked after the first submission (“the teacher does not allow a new submission”).</li>
<li>If your teacher <strong>requests a new version</strong>, the status becomes “New version requested”, their comment appears, and you can send your work again <strong>even after the deadline</strong>.</li>
<li>Earlier versions are kept with their mark and comment: nothing is erased.</li>
</ul>
<h5>The statuses</h5>
<p>To hand in · Handed in, awaiting a mark · Marked · New version requested · Not handed in (deadline passed without a submission). They are visible in <strong>Results → My assignments</strong>.</p>
<p class="lif-note">Do you have a serious obstacle before the deadline? Write to your teacher in advance with “Write to my teacher”: only they can give you more time.</p>
HTML],
'asg-mark' => ['q' => 'Seeing my mark, my feedback and the marking grid', 't' => 'Where to find the mark, when it appears, and the annotated copy.', 'a' => <<<'HTML'
<p>Your mark and the teacher’s feedback appear in the lesson and in <strong>Results → My assignments</strong> (“See feedback”).</p>
<ul>
<li><strong>The mark</strong> is given out of the maximum set by the teacher (20 by default).</li>
<li><strong>With a grid</strong>, you see the detail criterion by criterion: points earned, level and comment. The final mark is the sum of the criteria.</li>
<li><strong>The annotated copy</strong> (or model answer) can be downloaded from the same place when the teacher attached one.</li>
<li>You are notified by notification and by email.</li>
</ul>
<h5>“Awaiting marking” although my teacher has already marked it?</h5>
<p>Some teachers choose to <strong>release all marks at the same time</strong>, after reviewing every paper. Until the release, you see nothing: no mark, no comment, no grid, no annotated copy, and you receive no message. This is deliberate and fair to the whole class. You will be notified as soon as your mark is released.</p>
<p class="lif-note">Assignment marks are shown separately. They count neither in a course result nor in the certificate, which rely on the final exam.</p>
HTML],
'asg-review' => ['q' => 'My mark seems unfair: can I ask for a review?', 't' => 'Yes: a written request, an answer from the teacher.', 'a' => <<<'HTML'
<p>Yes, it is built in and it is simple. In <strong>Results → My assignments</strong>, the <strong>Request a review</strong> button appears next to a released mark.</p>
<ol>
<li>Click the button: a window opens.</li>
<li>Explain <strong>precisely</strong> what you would like to have re-read (for example “part 3 is covered in the appendix”). At least <strong>20 characters</strong> are required.</li>
<li>Send. The teacher is notified by notification and email.</li>
</ol>
<ul>
<li>There is <strong>one request per mark</strong>: make it count. After sending, “Request sent” is displayed.</li>
<li>The teacher answers; you are notified by email and notification and you read their reply in <strong>Results → My requests</strong>. A request receives a single answer.</li>
<li>If the mark is changed, the teacher does it in their own tool; you will then see it in the lesson.</li>
</ul>
<p class="lif-note">Argue your case: cite the instruction, the grid or the place in your paper. A precise and courteous request gets a more useful answer.</p>
HTML],
'asg-original' => ['q' => 'Are works compared with each other?', 't' => 'What the teacher can check, and what it means for you.', 'a' => <<<'HTML'
<p>The teacher has a tool that compares the works handed in by the students of the same assignment and flags the <strong>shared passages</strong>. Text everyone copies (the question itself, a cover page template) is ignored.</p>
<ul>
<li>The tool only compares students <strong>with each other</strong>, not with the internet.</li>
<li>A high percentage is never proof: it is a reason for the teacher to read closely and, if needed, to ask you for explanations.</li>
<li>You do not see this analysis. It feeds no automatic mark.</li>
</ul>
<p><strong>The advice is simple:</strong> work with your classmates if it is allowed, but write your own submission. If you quote a source, name it. If you are unsure what is permitted, ask your teacher before handing in.</p>
HTML],

'eval-kinds' => ['q' => 'Live evaluation or free evaluation: what is the difference?', 't' => 'A session at a fixed time, or a test you take at your own pace.', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th></th><th>Live</th><th>Free (asynchronous)</th></tr></thead><tbody>
<tr><td><strong>When?</strong></td><td>At the time set by the teacher. Everyone starts together.</td><td>Any time, <strong>until the deadline</strong>.</td></tr>
<tr><td><strong>Pace</strong></td><td>The teacher launches the questions; they follow each other for everyone.</td><td>You move on alone, question after question.</td></tr>
<tr><td><strong>Where to find it</strong></td><td>“Evaluations” tab (“Join”) or the session link/code.</td><td>“Evaluations” tab and the free evaluations page.</td></tr>
<tr><td><strong>Reminder</strong></td><td>Email 15 minutes before (and SMS if on).</td><td>No reminder: keep an eye on the deadline.</td></tr>
</tbody></table>
<p>In both cases each question has an announced <strong>time limit</strong>, the mark is a <strong>percentage of correct answers</strong> and there is <strong>no negative marking</strong>: an unanswered question simply scores 0.</p>
<p>The list of evaluations that concern you, with the countdown to closing, is in the <strong>Evaluations</strong> tab.</p>
HTML],
'eval-join' => ['q' => 'How do I join a live evaluation?', 't' => 'The link, the waiting room, and the case without an account.', 'a' => <<<'HTML'
<h5>If you have a StudyVibe account</h5>
<ol>
<li>Sign in, then open the <strong>session link</strong> from your teacher, or click <strong>Join</strong> in the “Evaluations” tab of your space (or “Enter the room” when the session is running).</li>
<li>You are recognised automatically: your name and address come from your account.</li>
<li>Click <strong>Join the session</strong>: you enter the <strong>waiting room</strong>.</li>
<li>Stay on the page. At the teacher’s signal a countdown starts and the questions appear by themselves.</li>
</ol>
<h5>If you do not have an account</h5>
<p>You can take part as a <strong>guest</strong>, with only your full name and an email address. Only addresses ending in <strong>gmail.com</strong>, <strong>icloud.com</strong> or <strong>facsciences-uy1.cm</strong> are accepted. Your results are sent to that address. If the address already belongs to a StudyVibe account, the page recognises it and uses the account name.</p>
<h5>Possible messages</h5>
<ul>
<li><strong>“Session not found”</strong>: the code or link is wrong or out of date. Ask for it again.</li>
<li><strong>“Tele-evaluation disabled”</strong>: the teacher closed the session.</li>
<li><strong>“Deadline passed”</strong>: for a free evaluation, the deadline is over.</li>
</ul>
HTML],
'eval-reminder' => ['q' => 'Reminders before an evaluation', 't' => 'Email 15 minutes before, optional SMS, and if the time changes.', 'a' => <<<'HTML'
<p>For a <strong>live</strong> evaluation you are informed at several moments:</p>
<ul>
<li><strong>When it is scheduled</strong>: you are generally told that a session is planned (date, time, course).</li>
<li><strong>15 minutes before the start</strong>: a reminder email is sent automatically, <strong>only once</strong>, to all registered students. It contains the time, the link and useful instructions.</li>
<li><strong>By SMS</strong> if you turned on reminders in <em>Profile → Account security</em> and verified your number.</li>
<li><strong>In the app</strong>: a notification and, on the home page, the “Next evaluation” block with the countdown.</li>
</ul>
<p><strong>If the teacher moves the session</strong>, the reminder is recalculated: you are told the new time and receive a new reminder 15 minutes before the new time.</p>
<p>For a <strong>free</strong> evaluation there is no fixed-time reminder since you take it whenever you want: note the deadline.</p>
<p class="lif-note">No reminder received? Check your junk folder and the address on your account. The reminder is sent to the students registered for the session.</p>
HTML],
'eval-prepare' => ['q' => 'Getting ready before the start', 't' => 'Equipment, connection, quiet: the checklist before you join the room.', 'a' => <<<'HTML'
<p>A little preparation avoids most problems on the day. Run through these checks <strong>10 to 15 minutes before</strong> the time:</p>
<ul>
<li><strong>Charged device</strong>, plugged in if possible. A phone is enough, but a bigger screen is more comfortable for reading a question with an image.</li>
<li><strong>Stable connection</strong>: prefer Wi-Fi or a sufficient data plan. Avoid places where the network is unreliable.</li>
<li><strong>Sign in ahead of time</strong> and check that you can see the session’s waiting room.</li>
<li><strong>One tab only</strong>, phone notifications off. If the teacher turned exam monitoring on, leaving the window is recorded (see the dedicated card).</li>
<li><strong>Something to write with</strong> if the teacher allows it: scratch paper, calculator.</li>
<li><strong>A quiet place</strong>. A question lasts from a few dozen seconds to a few minutes: there is no time to go back.</li>
</ul>
<p>Finally, re-read your teacher’s message: it often says what is allowed or not.</p>
HTML],
'eval-during' => ['q' => 'During the test: timer, answers, written questions', 't' => 'How to answer, what is saved, what happens when time runs out.', 'a' => <<<'HTML'
<ul>
<li>Each question appears with its <strong>timer</strong>. The default time is set by the teacher, and some questions have their own.</li>
<li>For a multiple-choice question, click your answer then <strong>Submit my answer</strong>. The “Answer saved” message confirms it has been taken into account; then wait: the next question arrives.</li>
<li>Options may be <strong>shuffled</strong> from one student to another. Marking follows the text of the right answer: do not rely on the order your neighbour saw.</li>
<li>For a <strong>written-answer question</strong>, type your answer in the field. The comparison ignores capital letters, accents, extra spaces and punctuation; simply write the word or value requested, without unnecessary sentences.</li>
<li>An image may come with the question: click “Enlarge the image” to see it bigger.</li>
<li>When time is up the question closes <strong>automatically</strong>. An unanswered question scores 0.</li>
<li>At the end an “Evaluation finished!” message appears and your results are ready.</li>
</ul>
<p class="lif-note">The calculation is simple: correct answers ÷ total number of questions × 100. There is no negative marking; answering is always better than leaving a blank.</p>
HTML],
'eval-connection' => ['q' => 'I left the evaluation, my connection dropped or I closed the tab', 't' => 'What is saved and how to resume exactly where you were.', 'a' => <<<'HTML'
<h5>A short drop while answering</h5>
<p>If the network becomes unstable just as you answer, an orange banner appears: “Unstable connection — answer queued”. Do not click repeatedly: your answer is kept and <strong>sent automatically</strong> as soon as the connection returns.</p>
<h5>The page reloaded or closed</h5>
<ul>
<li>Reopen the session link <strong>with the same account</strong> (or, if you are a guest, enter the same email address again): the platform finds your registration and <strong>the answers already saved</strong>, even from another device.</li>
<li>In a live evaluation the session <strong>carries on without waiting for you</strong>: you are synchronised with the class, on the current question. Questions already closed cannot be replayed; they score 0 if they have no answer.</li>
<li>Even the “Quit” button keeps your answers: only a registration where you have not answered anything yet is deleted.</li>
</ul>
<h5>Free evaluation</h5>
<ul>
<li>Did you leave or close the tab? <strong>Your progress is kept for 24 hours</strong> after your last activity. When you come back (from the list, with the <strong>“Resume”</strong> button, or with the link) you land <strong>directly on the question where you stopped</strong>, with your earlier answers kept.</li>
<li>The time of the question in progress <strong>does not run while you are away</strong>: you find roughly the time you had left.</li>
<li>After 24 hours without activity the unfinished attempt is dropped: next time you start again from the first question.</li>
<li>A finished evaluation is never erased just by opening the link. To take it again, use <strong>“Retake”</strong> (until the deadline): the new attempt <strong>replaces</strong> the previous one.</li>
</ul>
<p class="lif-note">A long outage or technical problem during a live evaluation? Write to your teacher as soon as you can, with the time and what you saw: they decide what happens next (see “My result was cancelled”).</p>
HTML],
'eval-integrity' => ['q' => 'Leaving the window during the test', 't' => 'Exam monitoring, when the teacher turns it on.', 'a' => <<<'HTML'
<p>To keep things fair, a teacher can turn on <strong>exam monitoring</strong> for their session. In practice, when it is on:</p>
<ul>
<li>Each time you <strong>leave the exam window</strong> (switching tab, switching app, sleep screen…), it is <strong>counted</strong>.</li>
<li>When you come back, a banner reminds you: “You left the exam window. This has been recorded and your teacher can see it.”</li>
<li>The teacher sees this counter in their session analysis. It does not disqualify you automatically: interpreting it is up to them.</li>
</ul>
<p>During questions, <strong>copy-paste</strong> and the <strong>right-click</strong> are also disabled on the question.</p>
<h5>How to avoid a false alert</h5>
<ul>
<li>Close other apps and turn off phone notifications before you begin.</li>
<li>Avoid checking your messages. If an emergency forces you to leave, tell your teacher afterwards.</li>
</ul>
HTML],
'eval-results' => ['q' => 'Where do I see my result, my report and my transcript?', 't' => 'Score, question-by-question correction, PDF and email.', 'a' => <<<'HTML'
<ul>
<li><strong>Right after the session</strong>: the end page offers “View my results and explanations online”.</li>
<li><strong>Later</strong>: in your space, <strong>Evaluations → Past</strong> tab and in <strong>Results</strong>, with “See the report”.</li>
<li><strong>By email</strong>: your results are sent to the address used to take part (also true for a guest with no account).</li>
</ul>
<h5>In the report</h5>
<ul>
<li>Your <strong>final score</strong>, as a percentage of correct answers, with a pass marker at <strong>50 %</strong>.</li>
<li>The <strong>question-by-question review</strong>: your answer, the right answer and, when the teacher wrote it, their justification.</li>
<li>A button to <strong>print</strong> or <strong>download as PDF</strong>.</li>
</ul>
<p>The 50 % marker is a display: deciding that a course is passed belongs to the teacher and the institution. Live evaluations do not replace the certification exam.</p>
<p>A result that looks wrong (a badly worded question, two possible right answers)? Use <strong>Question about this result</strong> next to the score to write to your teacher.</p>
HTML],
'eval-cancelled' => ['q' => 'My result was cancelled: what can I do?', 't' => 'What it means, and how to contest it once.', 'a' => <<<'HTML'
<p>The teacher can <strong>cancel</strong> a result, for example after a technical problem, an incident or a suspicion of cheating. Your result then shows the label <strong>“Result cancelled”</strong> and no longer counts.</p>
<h5>Contesting</h5>
<ol>
<li>Click <strong>Contest</strong> next to the result (or the link in the cancellation email, including if you took part as a guest).</li>
<li>Explain calmly what happened, with the facts (time, outage, error message). At least <strong>20 characters</strong> are needed, and 2,000 at most.</li>
<li>Send: the label becomes <strong>“Contestation pending”</strong> and the teacher is notified by notification and email.</li>
</ol>
<ul>
<li>You can <strong>contest only once</strong> per result: prepare your message well.</li>
<li>The teacher <strong>restores</strong> your result or <strong>upholds</strong> the cancellation, explaining their decision. You are informed and can re-read it through <strong>See the decision</strong>.</li>
<li>Only the owner of the result can contest it.</li>
</ul>
HTML],
'eval-missed' => ['q' => 'I missed an evaluation', 't' => 'What is possible depending on the type of evaluation, and how to ask.', 'a' => <<<'HTML'
<h5>Free evaluation</h5>
<p>If the deadline has not passed you can still take it. If it has, the platform shows “Deadline passed” and taking part is no longer possible from your side.</p>
<h5>Live evaluation</h5>
<p>Once the session is over it cannot be replayed by the student. Only your teacher can decide on a solution (a new session, another arrangement). Write to them without delay:</p>
<ol>
<li>Go to <strong>Results → Write to my teacher</strong>.</li>
<li>Choose the course, give a clear subject (“Absence from the evaluation of 12 October”) and explain the reason.</li>
<li>Include, if possible, whatever supports it in your message (dates, circumstances).</li>
</ol>
<p>To avoid missing another session: turn on SMS reminders, verify your number, and add the date to your calendar as soon as the session is scheduled.</p>
HTML],

'final-exam' => ['q' => 'The final certification exam', 't' => 'When it opens, how long it lasts, the 80 % threshold and the 3 attempts.', 'a' => <<<'HTML'
<ul>
<li><strong>When?</strong> It opens when you have finished all the lessons of the course (“Certification after 100 %”). It can also be closed after an evaluation deadline set by the teacher, or at the end of the course.</li>
<li><strong>Duration</strong>: the test is timed (90 minutes by default, adjusted by the teacher). The remaining time is shown, and <strong>submission is automatic</strong> at the end (“Time is up: automatic submission”).</li>
<li><strong>Answers</strong>: answer all questions before submitting (“Submit the test”).</li>
<li><strong>Pass mark: 80 %.</strong> At or above it, the certificate is created and emailed. Below it, no certificate is issued.</li>
<li><strong>Attempts</strong>: at most <strong>3 per 24-hour period</strong>. The number remaining is shown when you open it. Beyond that: “Limit of 3 attempts per 24 hours reached. Try again tomorrow.”</li>
</ul>
<h5>If you fail</h5>
<p>An <strong>attempt report</strong> is added to the Results tab (“Certifications not passed”). It summarises your score, without detailing the answers or giving the correction: revise the lessons, then try again after your pause.</p>
<p>“Give up” ends the test in progress: use it only if you really are giving up.</p>
HTML],
'certificate' => ['q' => 'Getting, printing and verifying my certificate', 't' => 'Where it is, its verification code and the public page.', 'a' => <<<'HTML'
<p>A certificate is issued automatically when you score <strong>at least 80 %</strong> on the final exam of a completed course. You are told on screen, and an email is sent.</p>
<ul>
<li>Find it in the <strong>Certificates</strong> tab: “Open and print” shows it full page, ready to print or save as PDF.</li>
<li>It carries <strong>your name</strong>, the course title, the issue date and a <strong>unique verification code</strong>, with a QR code.</li>
<li>Anyone (an employer, an institution) can <strong>check its authenticity</strong> on the public verification page, by entering the code or scanning the QR code. No account is needed.</li>
</ul>
<p class="lif-note">Your name is the one on your profile at the time of issue. If it contains a mistake, correct it in your profile then contact the administration about the document already issued.</p>
<p>No certificate although you think you earned it? Check in <strong>Results</strong> that your best score reaches 80 %, and that the course is indeed 100 % complete.</p>
HTML],
'transcript' => ['q' => 'My results and my transcript', 't' => 'What the Results tab contains and how to print the transcript.', 'a' => <<<'HTML'
<p>The <strong>Results</strong> tab sums up your journey:</p>
<ul>
<li><strong>Key figures</strong>: completed courses, average of your certifications, study time.</li>
<li><strong>By course</strong>: progress, best score, number of attempts.</li>
<li><strong>Lesson quizzes</strong>: lesson, date and score.</li>
<li><strong>Certifications not passed</strong>: one report per attempt.</li>
<li><strong>My assignments</strong>: status, deadline and mark (see the “Assignments” section).</li>
<li><strong>My requests to my teachers</strong>: your questions and their answers.</li>
</ul>
<h5>The printable transcript</h5>
<p>The <strong>Printable transcript</strong> button generates a dated document summarising your progress and attempts, with your name and matricule. It is given for information (“non-contractual”). For an official document, contact your institution’s administration.</p>
HTML],

'contact-teacher' => ['q' => 'Writing to my teacher', 't' => 'Mark review, question about a result, free message.', 'a' => <<<'HTML'
<p>A mark to review, a question about a result, an absence to justify: everything goes through <strong>Results → My requests to my teachers</strong>. Your teacher keeps a record of each request and you are told when they answer.</p>
<table class="lif-table"><thead><tr><th>Type of request</th><th>Where to start it</th><th>Limit</th></tr></thead><tbody>
<tr><td>Review of an assignment mark</td><td>“Request a review” button in “My assignments”</td><td>One request per released mark</td></tr>
<tr><td>Question about an evaluation result</td><td>“Question about this result” button in “Evaluations”</td><td>One request per result</td></tr>
<tr><td>Free message</td><td>“Write to my teacher”: choose the course and the subject</td><td>A course you are enrolled in</td></tr>
</tbody></table>
<ul>
<li>Write at least <strong>20 characters</strong> (2,000 at most) and be precise.</li>
<li>Statuses: “Awaiting an answer” then “Answered”. The answer appears under your request, with a notification and an email.</li>
<li>There is a limit on submissions per hour to prevent abuse.</li>
</ul>
<p>For a question about a lesson’s content, prefer the lesson’s <strong>Questions</strong> tab: the answer will also help the other students in the course.</p>
HTML],
'privacy' => ['q' => 'My data and my privacy', 't' => 'What is kept, what for, and your rights.', 'a' => <<<'HTML'
<p>StudyVibe keeps your <strong>email address and name</strong> to manage your account, as well as your <strong>study time</strong> and your <strong>quiz answers</strong> to calculate your results. There is <strong>no advertising tracker</strong>.</p>
<ul>
<li>Your <strong>personal notes</strong> taken during lessons stay on your device.</li>
<li>Your <strong>phone number</strong> is used for reminders and verification codes.</li>
<li>Your teacher sees your progress, results and assignments <strong>in their own courses</strong>, not those of others.</li>
<li>The files of your assignments can only be downloaded by you, the teacher of the course and the administration.</li>
</ul>
<h5>Your rights</h5>
<p>You may ask to <strong>access</strong>, <strong>correct</strong> or <strong>delete</strong> your data at any time. To do so, write to the platform administration. The full privacy policy is available from the “Privacy policy” link at the bottom of the pages.</p>
HTML],
'devices' => ['q' => 'Phone, tablet or computer: am I well equipped?', 't' => 'Compatibility, dark mode, language and reading comfort.', 'a' => <<<'HTML'
<ul>
<li>The student space works on a <strong>computer, tablet and phone</strong>. On a phone, navigation moves to the bottom of the screen and the bell to the top.</li>
<li>Use a <strong>recent browser</strong> (Chrome, Edge, Firefox, Safari), kept up to date.</li>
<li>The <strong>language</strong> (FR / EN) changes in one click; the <strong>dark theme</strong> is available for tired eyes.</li>
<li><strong>Videos</strong> need a decent connection; <strong>PDFs</strong> and text are lighter.</li>
<li>If a page does not display as expected, try refreshing, then signing in again. Turn off ad blockers on the site if a video or a document does not load.</li>
</ul>
<p>To take an evaluation a slightly bigger screen is preferable, but a phone is enough to answer.</p>
HTML],
'still-stuck' => ['q' => 'I did not find my answer', 't' => 'Who to contact depending on the nature of the problem.', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th>Your problem</th><th>Who to contact</th></tr></thead><tbody>
<tr><td>Lesson content, assignment instructions, grid, evaluation rules</td><td>Your <strong>teacher</strong>: the lesson’s “Questions” tab, or “Write to my teacher”</td></tr>
<tr><td>Assignment mark or evaluation result</td><td>Your <strong>teacher</strong>: “Request a review” or “Question about this result”</td></tr>
<tr><td>Cancelled result</td><td>Your <strong>teacher</strong>: “Contest” button</td></tr>
<tr><td>Deactivated account, email to change, matricule already used, data deletion</td><td>The platform <strong>administration</strong></td></tr>
<tr><td>Enrolment key, unpublished course, opening or end date</td><td>Your <strong>teacher</strong></td></tr>
</tbody></table>
<p>When you write, give the <strong>course</strong>, the <strong>date</strong> and what you saw on screen (a screenshot helps a lot). The more precise your message, the faster the answer is useful.</p>
HTML],
];
