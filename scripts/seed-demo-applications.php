<?php
declare(strict_types=1);
/**
 * Fills the three example postings (codes SFSD, BDMU, IDAC) with realistic applications so the ATS can be tried:
 * résumés are real PDFs sent through Careers::submit (same checks and storage as the public form), then the screening worker reads them.
 * The requests go to the running site (default http://127.0.0.1:8125), exactly like the public form. Three applicants use real inboxes, the others use example.com addresses.
 *
 *   php scripts/seed-demo-applications.php [http://127.0.0.1:8123]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/CareersWorker.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8125', '/');
$pdo = Database::getInstance();

function demoPdf(array $lines): string
{
    $c = "BT\n/F1 11 Tf\n14 TL\n50 780 Td\n";
    foreach ($lines as $l) {
        foreach (explode("\n", wordwrap($l, 85, "\n", true)) as $part) {
            $c .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $part) . ") Tj\nT*\n";
        }
    }
    $data = gzcompress($c . "ET\n");
    return "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\n4 0 obj<< /Length " . strlen($data) . " /Filter /FlateDecode >>\nstream\n" . $data . "\nendstream\nendobj\ntrailer<< >>\n%%EOF\n";
}

const ME = 'danyhardi06@gmail.com';
const ADE = 'adelinetakou04@gmail.com';
const DAN = 'danielwilfriedtakou@gmail.com';

// name, email (null = example.com), years, education, current title, company, location, skills, languages, work pref, heard from, hours ago, cover letter, résumé text
$sets = [
'SFSD' => [
 ['Marie Ouedraogo', ME, 9, 'master', 'Lead PHP Engineer', 'Campus Digital', 'Douala, Cameroun', 'PHP, MySQL, JavaScript, Git, Docker, PHPUnit, REST API', 'French, English', 'hybrid', 'linkedin', 70, true,
  "Lead PHP engineer, 9 years. Built learning management platforms for three universities with PHP 8, MySQL and vanilla JavaScript. Designed REST API endpoints, wrote PHPUnit and integration tests, set up Docker based deployments with Git workflows. Built a progressive web app with a service worker for offline quizzes. Accessibility work to WCAG 2.1. Master in computer science, University of Douala."],
 ['Idriss Ndam', ADE, 4, 'bachelor', 'Web Developer', 'Lycée Technique de Bonabéri', 'Douala, Cameroun', 'PHP, JavaScript, MySQL, Git', 'French, English', 'onsite', 'school', 52, false,
  "Web developer, 4 years. PHP and JavaScript applications for a technical school: timetable, grades and fee tracking on MySQL. Uses Git daily and calls internal REST API endpoints from JavaScript. Bachelor in computer science. Still learning automated tests."],
 ['Kevin Mbida', null, 3, 'highschool', 'Pastry Chef', 'Boulangerie Saint Michel', 'Yaoundé, Cameroun', 'Baking, Pastry, Customer service', 'French', 'onsite', 'website', 44, false,
  "Pastry chef with three years in a busy bakery in Yaoundé. Specialised in bread, croissants and wedding cakes. Excellent hygiene standards and customer service. Available for night shifts."],
 ['Aminata Diallo', null, 7, 'master', 'Senior Backend Developer', 'Orange Digital Center', 'Dakar, Sénégal', 'PHP, Laravel, MariaDB, JavaScript, Git, REST API, Docker, PHPUnit', 'French, English, Wolof', 'remote', 'linkedin', 40, true,
  "Senior backend developer, 7 years. PHP and Laravel services on MariaDB, REST API design, JavaScript front ends, Git and code review, Docker, automated tests with PHPUnit. Built an e-learning platform for a telecom academy. Master in software engineering. Relocation to Douala possible."],
 ['Paul Essomba', null, 1, 'associate', 'Junior Developer', 'Freelance', 'Douala, Cameroun', 'HTML, CSS, JavaScript, WordPress', 'French', 'any', 'social', 36, false,
  "Self taught junior developer, one year freelancing. WordPress sites, HTML, CSS and JavaScript. Looking for a first full time job and willing to learn PHP and MySQL."],
 ['Sarah Koné', null, 2, 'bachelor', 'Marketing Assistant', 'Brasserie du Golfe', 'Abidjan, Côte d’Ivoire', 'Canva, Social media, Event planning', 'French, English', 'any', 'social', 30, false,
  "Marketing assistant, two years in social media campaigns and events. Fluent French and English. Looking for a creative role."],
 ['Jean-Baptiste Fouda', DAN, 12, 'bachelor', 'Technical Director', 'Fouda Systems', 'Yaoundé, Cameroun', 'PHP, MySQL, JavaScript, Git, REST API, Moodle, Docker', 'French, English', 'hybrid', 'referral', 27, true,
  "Technical director with 12 years of PHP and MySQL. Runs a small agency that integrates Moodle and builds custom e-learning extensions in PHP and JavaScript. REST API integrations with payment providers, Git based delivery, Docker. Managed teams of eight. Bachelor in computer science."],
 ['Grace Nkeng', null, 5, 'bachelor', 'Full-Stack Developer', 'Afriland Tech', 'Douala, Cameroun', 'PHP, MySQL, JavaScript, Vue, Git, REST API, Unit tests', 'English, French', 'hybrid', 'linkedin', 24, true,
  "Full-stack developer, 5 years. PHP, MySQL and JavaScript with Vue on banking back-office tools. Git, REST API integration, unit tests with PHPUnit. Bachelor in computer engineering. Interested in education technology."],
 ['Thierry Kamga', null, 6, 'bachelor', 'Java Developer', 'Camtel', 'Yaoundé, Cameroun', 'Java, Spring, Oracle, Git, REST API', 'French, English', 'onsite', 'website', 20, false,
  "Java developer, 6 years on telecom billing systems. Spring Boot, Oracle, REST API, Git. No PHP or MySQL experience but a quick learner."],
 ['Rokhaya Sow', null, 3, 'bachelor', 'Data Analyst', 'Wari', 'Dakar, Sénégal', 'Python, SQL, MySQL, Excel, Power BI', 'French, English', 'remote', 'linkedin', 16, false,
  "Data analyst, 3 years. SQL and MySQL reporting, Python scripts, Power BI dashboards. Basic JavaScript. Looking to move into software engineering."],
 ['Emmanuel Tchoua', null, 8, 'doctorate', 'Research Engineer', 'University of Dschang', 'Dschang, Cameroun', 'PHP, MySQL, JavaScript, Git, REST API, LMS, Moodle', 'French, English', 'hybrid', 'school', 12, true,
  "Research engineer, 8 years, PhD in information systems. Maintains the campus e-learning platform (Moodle, PHP plugins, MySQL), JavaScript front ends, REST API with the student information system, Git. Teaches web development."],
 ['Lydia Mbarga', null, 4, 'master', 'Mobile Developer', 'Digital Bridge', 'Douala, Cameroun', 'Kotlin, Flutter, Firebase, Git, REST API, JavaScript', 'French, English', 'hybrid', 'event', 9, false,
  "Mobile developer, 4 years. Android with Kotlin and Flutter apps for fintech. REST API consumption, Git, some JavaScript. Limited PHP."],
 ['Oumarou Bello', null, 10, 'bachelor', 'Software Architect', 'Sahel Software', 'Ngaoundéré, Cameroun', 'PHP, MariaDB, JavaScript, Git, REST API, Docker, Accessibility', 'French, English', 'remote', 'referral', 5, true,
  "Software architect, 10 years of PHP and MariaDB. Designed multi-tenant school management systems, REST API, JavaScript front ends, Docker. Accessibility audits to WCAG. Prefers remote work and can visit Douala monthly."],
],
'BDMU' => [
 ['Carine Fotso', ME, 6, 'master', 'Sales Manager', 'Sage Cameroun', 'Douala, Cameroun', 'B2B sales, CRM, Negotiation, Tenders, Demos', 'French, English', 'hybrid', 'linkedin', 68, true,
  "Sales manager, 6 years of B2B software sales to universities and enterprises. Sold LMS and ERP licences, ran demos and presentations, answered tenders and appels d'offres, negotiation of multi-year contracts. Uses HubSpot and Salesforce CRM daily. Master in business. French and English."],
 ['Alain Nguetsop', ADE, 4, 'bachelor', 'Business Development Officer', 'Express Union', 'Douala, Cameroun', 'Sales, CRM, Negotiation, Prospecting', 'French, English', 'onsite', 'website', 55, false,
  "Business development officer, 4 years. Prospecting businesses and institutions, CRM follow up with Zoho, negotiation of partnership agreements. Bachelor in marketing. French and English."],
 ['Patricia Ngono', null, 2, 'bachelor', 'Receptionist', 'Hôtel Akwa Palace', 'Douala, Cameroun', 'Customer service, Reservations', 'French, English', 'onsite', 'website', 46, false,
  "Hotel receptionist for two years. Welcoming guests, reservations and phone support. Bachelor in tourism. Keen to move into sales."],
 ['Samuel Biyiha', DAN, 9, 'master', 'Head of Partnerships', 'EdTech Africa', 'Yaoundé, Cameroun', 'Higher education sales, SaaS, CRM, Tenders, Negotiation, Demos', 'French, English', 'hybrid', 'referral', 42, true,
  "Head of partnerships, 9 years selling SaaS and e-learning solutions to universities and ministries across Central Africa. Led RFP responses, ran demonstrations, negotiated contracts with HubSpot pipeline management. Built a team of five business developers."],
 ['Nadège Tamo', null, 3, 'bachelor', 'Sales Executive', 'MTN Business', 'Douala, Cameroun', 'B2B sales, CRM, Pipedrive, Negotiation', 'French, English', 'onsite', 'linkedin', 38, false,
  "Sales executive, 3 years selling telecom services to businesses. Pipedrive CRM, negotiation, presentations to company directors. Bachelor in commerce."],
 ['Hervé Kuete', null, 1, 'associate', 'Sales Intern', 'Dovv', 'Douala, Cameroun', 'Retail sales', 'French', 'onsite', 'school', 34, false,
  "Internship in a supermarket, in store sales support. Associate degree in commerce. Looking for a first role."],
 ['Fatou Camara', null, 7, 'master', 'Account Executive', 'Jumia Business', 'Abidjan, Côte d’Ivoire', 'B2B sales, SaaS, Salesforce, Negotiation, Demos', 'French, English', 'remote', 'linkedin', 30, true,
  "Account executive, 7 years in B2B marketplace and SaaS sales. Salesforce, demos, negotiation with enterprise customers. Strong French and English. Master in international business."],
 ['Boris Ekotto', null, 5, 'bachelor', 'Business Developer', 'Cameroon Tender Hub', 'Douala, Cameroun', 'Tenders, RFP, Sales, Institutions, Negotiation', 'French, English', 'hybrid', 'event', 26, true,
  "Business developer, 5 years specialised in public and institutional tenders. Writes appel d'offres responses for private schools and universities, CRM tracking in Zoho, negotiation of contracts. Bachelor in law."],
 ['Joëlle Mbella', null, 12, 'bachelor', 'Retail Manager', 'Casino Supermarché', 'Yaoundé, Cameroun', 'Management, Retail, Customer service', 'French', 'onsite', 'website', 22, false,
  "Retail manager with 12 years of experience managing a supermarket team. Inventory, customer service, staff scheduling."],
 ['Mohamed Traoré', null, 4, 'master', 'EdTech Sales Specialist', 'Learnia', 'Bamako, Mali', 'EdTech, Higher education, CRM, HubSpot, Demos, Negotiation', 'French, English', 'remote', 'linkedin', 18, true,
  "EdTech sales specialist, 4 years. Selling an e-learning platform to universities in West Africa, demonstrations to rectors, HubSpot CRM, negotiation. Master in business administration."],
 ['Christelle Atangana', null, 3, 'bachelor', 'Customer Success Manager', 'Seerbit', 'Douala, Cameroun', 'SaaS, Customer success, CRM, Presentations', 'French, English', 'hybrid', 'referral', 14, false,
  "Customer success manager at a payments SaaS, 3 years. Onboarding business clients, presentations, upsell, CRM with HubSpot. Some negotiation of renewals. Bachelor in management."],
 ['Rodrigue Nana', null, 2, 'bachelor', 'Graphic Designer', 'Studio Kreatif', 'Douala, Cameroun', 'Design, Photoshop, Illustrator', 'French, English', 'onsite', 'social', 10, false,
  "Graphic designer, two years of branding work for small companies. Bachelor in visual arts."],
 ['Esther Ngassa', null, 8, 'master', 'Regional Sales Director', 'Huawei Cameroon', 'Douala, Cameroun', 'Enterprise sales, Tenders, Negotiation, CRM, Salesforce, Higher education', 'French, English, Chinese', 'onsite', 'linkedin', 6, true,
  "Regional sales director, 8 years in enterprise technology sales including universities and ministries. Tender and RFP management, Salesforce CRM, contract negotiation, executive presentations. Master in business, French and English."],
 ['Pierre Kenfack', null, 5, 'bachelor', 'Sales Representative', 'Nestlé Cameroun', 'Douala, Cameroun', 'FMCG sales, Negotiation, Distribution', 'French, English', 'onsite', 'website', 3, false,
  "Sales representative, 5 years in FMCG distribution. Negotiation with wholesalers, field visits, monthly targets. Bachelor in commerce."],
],
'IDAC' => [
 ['Dr. Solange Mekongo', ME, 11, 'doctorate', 'Senior Lecturer and Course Designer', 'University of Douala', 'Douala, Cameroun', 'Instructional design, Assessment, Moodle, QCM, Bloom, LaTeX, Excel', 'French, English', 'hybrid', 'school', 66, true,
  "Senior lecturer, 11 years of teaching and course design. Curriculum design for three degree programmes, instructional design with Bloom's taxonomy, assessment design with QCM and quizzes, psychometric analysis of exam results in Excel, Moodle and e-learning platform administration. LaTeX for exam papers. PhD in education sciences."],
 ['Yannick Fomena', ADE, 4, 'master', 'E-learning Designer', 'Orange Cameroun Academy', 'Douala, Cameroun', 'Instructional design, E-learning, LMS, Video, Quiz, Excel', 'French, English', 'hybrid', 'linkedin', 54, true,
  "E-learning designer, 4 years. Instructional design of corporate training modules, quizzes and assessments in an LMS, video scripts, learning platform content migration. Master in educational technology."],
 ['Brice Onana', null, 2, 'bachelor', 'Graphic Designer', 'Studio Pixel', 'Yaoundé, Cameroun', 'Photoshop, Illustrator', 'French', 'any', 'social', 48, false,
  "Graphic designer, two years of poster and logo design. No teaching background."],
 ['Hélène Tchuente', DAN, 7, 'master', 'Teacher Trainer', 'Ministry of Secondary Education', 'Yaoundé, Cameroun', 'Teaching, Training, Curriculum, Assessment, QCM, Excel', 'French, English', 'onsite', 'referral', 41, true,
  "Teacher trainer, 7 years. Training secondary teachers in assessment (quiz, qcm design, marking grids), curriculum design and ingénierie pédagogique. Formation workshops in 8 regions. Excel for item analysis. Master in education."],
 ['Marc Eyenga', null, 5, 'bachelor', 'Mathematics Teacher', 'Collège Libermann', 'Douala, Cameroun', 'Teaching, Question writing, Excel, LaTeX', 'French, English', 'onsite', 'school', 35, false,
  "Mathematics teacher, 5 years. Writes exams and multiple choice question banks, grades with spreadsheets, LaTeX for typesetting. Interested in course design and evaluation. Bachelor in mathematics."],
 ['Léa Mballa', null, 3, 'bachelor', 'Content Writer', 'Blog Afrique', 'Douala, Cameroun', 'Writing, SEO, Social media', 'French, English', 'remote', 'social', 31, false,
  "Content writer, 3 years of blog articles and newsletters. Bachelor in literature."],
 ['Ibrahim Sawadogo', null, 9, 'master', 'Learning Designer', 'Université Virtuelle du Burkina', 'Ouagadougou, Burkina Faso', 'Learning design, Course design, Moodle, Assessment, Bloom, Video, Accessibility', 'French, English', 'remote', 'linkedin', 28, true,
  "Learning designer, 9 years at a virtual university. Course design and curriculum mapping, assessment and question bank design with psychometrics basics, Moodle learning platform, accessible content to WCAG, video lessons. Master in learning sciences."],
 ['Prisca Nguimfack', null, 1, 'bachelor', 'Teaching Assistant', 'Université de Dschang', 'Dschang, Cameroun', 'Teaching, Tutoring, Quiz', 'French, English', 'onsite', 'school', 25, false,
  "Teaching assistant for one year. Tutoring sessions and grading quizzes. Recent bachelor graduate in education sciences."],
 ['Olivier Ndzie', null, 6, 'bachelor', 'Training Manager', 'Total Energies Cameroun', 'Douala, Cameroun', 'Training, Curriculum, E-learning, LMS, Assessment, Excel', 'French, English', 'onsite', 'linkedin', 21, true,
  "Training manager, 6 years. Designs staff training programmes, deploys an LMS, writes assessments and quizzes, analyses results in Excel. Bachelor in human resources."],
 ['Mariam Coulibaly', null, 8, 'master', 'Pedagogical Engineer', 'ESATIC', 'Abidjan, Côte d’Ivoire', 'Ingénierie pédagogique, Évaluation, QCM, Moodle, Formation, LaTeX', 'French, English', 'remote', 'event', 17, true,
  "Ingénieure pédagogique, 8 ans. Conception pédagogique de programmes, évaluation et banques de QCM, plateforme d'apprentissage Moodle, formation d'enseignants. LaTeX pour les sujets d'examen. Master en sciences de l'éducation."],
 ['Daniel Tsafack', null, 4, 'bachelor', 'Video Editor', 'Canal Studio', 'Douala, Cameroun', 'Video editing, After Effects', 'French, English', 'onsite', 'social', 13, false,
  "Video editor, 4 years of promotional videos. Learning about educational content."],
 ['Estelle Biloa', null, 3, 'master', 'Instructional Designer', 'Edulib', 'Douala, Cameroun', 'Instructional design, Assessment, LMS, Quiz, Teaching', 'French, English', 'hybrid', 'linkedin', 11, false,
  "Instructional designer, 3 years. Designs online course modules and quiz assessments on an e-learning platform, previously taught at secondary school. Master in education sciences."],
 ['Aristide Njoya', null, 15, 'doctorate', 'Professor of Educational Measurement', 'University of Yaoundé I', 'Yaoundé, Cameroun', 'Psychometrics, Assessment, Curriculum, Teaching, Statistics, Excel', 'French, English', 'onsite', 'referral', 8, true,
  "Professor of educational measurement, 15 years. Psychometrics of multiple choice tests, item analysis, curriculum evaluation, teaching at master level. Excel and R for statistics. No LMS administration but familiar with Moodle as a user."],
 ['Vanessa Atemkeng', null, 0, 'bachelor', 'Student', 'Université de Buea', 'Buea, Cameroun', 'Teaching, Quiz', 'English, French', 'any', 'school', 4, false,
  "Final year student in education sciences, internship experience in a primary school. Looking for a first job in education."],
 ['Boniface Manga', null, 6, 'bachelor', 'Curriculum Developer', 'GIZ Cameroun', 'Yaoundé, Cameroun', 'Curriculum, Course design, Training, Assessment, E-learning, Accessibility', 'French, English', 'hybrid', 'linkedin', 2, true,
  "Curriculum developer, 6 years on vocational training programmes. Programme pédagogique design, assessment frameworks, training of trainers, an e-learning platform rollout, accessibility. Bachelor in education."],
 ['Annette Fonkou', null, 2, 'associate', 'Secretary', 'Lycée Bilingue', 'Douala, Cameroun', 'Administration, Excel', 'French, English', 'onsite', 'website', 1, false,
  "School secretary, two years of administration and Excel."],
],
];

$coverPdf = static fn(string $n, string $role): string => demoPdf([
    "Cover letter - $n", '', "Subject: application for $role at StudyVibe", '',
    'Dear hiring team,', '',
    'I am applying because StudyVibe works on a problem I care about: making learning and evaluation fair and accessible in our region. My experience matches the position and I would be glad to discuss how I can contribute from the first month.',
    '', 'Kind regards,', $n,
]);

$total = 0;
$stamp = (time() - 30) . '.' . hash_hmac('sha256', 'career-form|' . (time() - 30), defined('APP_SECRET') ? (string)APP_SECRET : 'studyvibe');
foreach ($sets as $code => $rows) {
    $st = $pdo->prepare('SELECT * FROM career_jobs WHERE code = :c'); $st->execute(['c' => $code]);
    $job = $st->fetch(PDO::FETCH_ASSOC);
    if (!$job) { fwrite(STDERR, "posting $code not found, skipped\n"); continue; }
    foreach ($rows as [$name, $mail, $yrs, $edu, $title, $comp, $loc, $skills, $langs, $pref, $heard, $hours, $cover, $cv]) {
        $email = $mail ?? (preg_replace('/[^a-z0-9]+/', '.', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name))) . '@example.com');
        $tmp = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($tmp, demoPdf([$name . ' - ' . $title, $loc . ' - ' . $yrs . ' years of experience', 'Skills: ' . $skills, 'Education: ' . $edu, '', $cv]));
        $post = [
            'job' => (string)$job['public_id'], 'full_name' => $name, 'email' => $email,
            'phone' => '+237 6 ' . random_int(50, 99) . ' ' . random_int(10, 99) . ' ' . random_int(10, 99) . ' ' . random_int(10, 99),
            'location' => $loc, 'years_experience' => (string)$yrs, 'education_level' => $edu, 'current_title' => $title, 'current_company' => $comp,
            'skills' => $skills, 'languages' => $langs, 'work_pref' => $pref, 'heard_from' => $heard,
            'availability' => ['Immediately', '1 month', '2 months', '3 months'][random_int(0, 3)],
            'consent' => '1', 'talent_consent' => $yrs >= 5 ? '1' : '', '_t' => $stamp, 'lang' => $pref === 'remote' ? 'en' : 'fr',
            'resume' => new CURLFile($tmp, 'application/pdf', 'CV ' . $name . '.pdf'),
        ];
        $ctmp = null;
        if ($cover) {
            $ctmp = tempnam(sys_get_temp_dir(), 'cl');
            file_put_contents($ctmp, $coverPdf($name, $job['title']));
            $post['cover_letter'] = new CURLFile($ctmp, 'application/pdf', 'Cover letter ' . $name . '.pdf');
        }
        $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'career:%'");       // the public form allows 8 applications an hour per address
        $ch = curl_init($base . '/career-apply.php');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Requested-With: XMLHttpRequest', 'Origin: ' . $base, 'Cookie: lang=' . $post['lang']]]);
        $res = json_decode((string)curl_exec($ch), true) ?: ['success' => false, 'error' => 'no answer'];
        @unlink($tmp);
        if ($ctmp) { @unlink($ctmp); }
        if (empty($res['success'])) { echo "  skip $name: " . ($res['error'] ?? '?') . ' ' . json_encode($res['fields'] ?? []) . "\n"; continue; }
        $id = (int)$pdo->query("SELECT id FROM career_applications WHERE reference = " . $pdo->quote((string)$res['reference']))->fetchColumn();
        $res['id'] = $id;
        $pdo->prepare('UPDATE career_applications SET created_at = NOW() - INTERVAL :h HOUR WHERE id = :i')->execute(['h' => $hours, 'i' => $res['id']]);
        $total++;
    }
}
for ($i = 0; $i < 6; $i++) { CareersWorker::run($pdo, 50); }
echo "added $total applications\n";
foreach ($pdo->query("SELECT j.code, a.bucket, COUNT(*) n FROM career_applications a JOIN career_jobs j ON j.id = a.job_id WHERE j.code IN ('SFSD','BDMU','IDAC') GROUP BY j.code, a.bucket ORDER BY 1,2") as $r) {
    echo "  {$r['code']} {$r['bucket']}: {$r['n']}\n";
}
