<?php
declare(strict_types=1);

require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/Careers.php';
require_once __DIR__ . '/CareersInterviews.php';

/**
 * The e-mails about an interview. They look nothing like the other StudyVibe messages (EmailTheme: paper, terracotta, serif): an interview
 * is an event, so the message is built like an invitation card, with a night-blue banner, a calendar tile that carries the date and the time,
 * and one colour per situation. Same bodies in French and English, chosen from the candidate's language.
 *
 *   invite      the first message, with confirm / calendar buttons
 *   updated     the time or the place changed (the old time is shown struck through)
 *   cancelled   the interview will not take place
 *   reminder24  the day before
 *   reminder2   two hours before
 *   chase       the candidate has not answered the invitation yet
 *
 * Buttons lead to the candidate's tracking page (a click on a mail link must never change anything by itself: mail scanners open links);
 * the calendar links are a Google Calendar template and the .ics file.
 */
final class InterviewMail
{
    private const NIGHT = '#0B1437';
    private const INK = '#1B2347';
    private const MUTED = '#6B7399';
    private const FOG = '#E9ECF5';
    private const CARD = '#FFFFFF';
    private const LINE = '#E1E5F2';
    private const MINT = '#18C29C';
    private const ACCENTS = ['invite' => '#18C29C', 'updated' => '#FF5D73', 'cancelled' => '#8A92B2', 'reminder24' => '#6C7CFF', 'reminder2' => '#FFB020', 'chase' => '#B07CFF'];
    private const FONT = "'Trebuchet MS','Segoe UI',Helvetica,Arial,sans-serif";

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    public static function invite(array $app, array $iv): bool { return self::send('invite', $app, $iv); }
    public static function updated(array $app, array $iv, ?string $oldWhen): bool { return self::send('updated', $app, $iv, ['old' => $oldWhen]); }
    public static function cancelled(array $app, array $iv): bool { return self::send('cancelled', $app, $iv); }
    public static function reminder24(array $app, array $iv): bool { return self::send('reminder24', $app, $iv); }
    public static function reminder2(array $app, array $iv): bool { return self::send('reminder2', $app, $iv); }
    public static function chase(array $app, array $iv): bool { return self::send('chase', $app, $iv); }

    // ---------------------------------------------------------------------------------------------------------------

    private static function dateParts(string $when, bool $en): array
    {
        $ts = strtotime($when);
        $months = $en ? ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'] : ['JANV', 'FÉVR', 'MARS', 'AVR', 'MAI', 'JUIN', 'JUIL', 'AOÛT', 'SEPT', 'OCT', 'NOV', 'DÉC'];
        $full = $en ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] : ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $days = $en ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] : ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $d = (int)date('j', $ts); $m = (int)date('n', $ts) - 1;
        return ['mon' => $months[$m], 'day' => (string)$d, 'weekday' => $days[(int)date('w', $ts)],
                'long' => $en ? $days[(int)date('w', $ts)] . ', ' . $full[$m] . ' ' . $d . ', ' . date('Y', $ts) : $days[(int)date('w', $ts)] . ' ' . ($d === 1 ? '1er' : $d) . ' ' . $full[$m] . ' ' . date('Y', $ts),
                'time' => date('H:i', $ts)];
    }

    private static function send(string $kind, array $app, array $iv, array $o = []): bool
    {
        $en = ($app['lang'] ?? 'fr') === 'en';
        $accent = self::ACCENTS[$kind];
        $first = explode(' ', trim((string)$app['full_name']))[0] ?? '';
        $role = (string)($app['job_title'] ?: ($en ? 'Open application' : 'Candidature spontanée'));
        $when = self::dateParts((string)$iv['scheduled_at'], $en);
        $endTs = CareersInterviews::endsAt($iv);
        $range = $when['time'] . ' – ' . date('H:i', $endTs);
        $tz = date('T') . ' (UTC' . date('P') . ')';
        $track = Careers::trackUrl((string)$app['track_token'], ['i' => $iv['id']]) . '#interview';
        $ics = Careers::trackUrl((string)$app['track_token'], ['ics' => $iv['id']]);
        $gcal = CareersInterviews::googleLink($iv, $app, $en ? 'en' : 'fr');

        $T = $en ? [
            'invite'     => ['INTERVIEW INVITATION', 'Let’s talk, {first}.', 'We would like to meet you about the position below. Please confirm that the time suits you.', 'Confirm my attendance'],
            'updated'    => ['NEW TIME', 'The interview has moved, {first}.', 'The time or the place of your interview changed. Here are the new details; please confirm again.', 'Confirm the new time'],
            'cancelled'  => ['INTERVIEW CANCELLED', 'We have to cancel, {first}.', 'The interview below will not take place. We are sorry for the inconvenience and will contact you if a new date is set.', 'See my application'],
            'reminder24' => ['TOMORROW', 'See you tomorrow, {first}.', 'A quick reminder of your interview. Everything you need is below.', 'See the details'],
            'reminder2'  => ['IN TWO HOURS', 'Almost time, {first}.', 'Your interview starts in about two hours. Good luck: we are looking forward to talking with you.', 'See the details'],
            'chase'      => ['PLEASE CONFIRM', 'We still need your answer, {first}.', 'We invited you to an interview and have not heard back yet. A single tap tells us whether the time suits you.', 'Answer now'],
        ] : [
            'invite'     => ['INVITATION À UN ENTRETIEN', 'Parlons-nous, {first}.', 'Nous aimerions vous rencontrer pour le poste ci-dessous. Merci de confirmer que le créneau vous convient.', 'Confirmer ma présence'],
            'updated'    => ['NOUVEL HORAIRE', 'L’entretien a changé, {first}.', 'L’horaire ou le lieu de votre entretien a changé. Voici les nouveaux détails ; merci de confirmer à nouveau.', 'Confirmer le nouvel horaire'],
            'cancelled'  => ['ENTRETIEN ANNULÉ', 'Nous devons annuler, {first}.', 'L’entretien ci-dessous n’aura pas lieu. Nous sommes désolés pour ce désagrément et vous recontactons si une nouvelle date est fixée.', 'Voir ma candidature'],
            'reminder24' => ['DEMAIN', 'À demain, {first}.', 'Un petit rappel pour votre entretien. Tout ce qu’il vous faut est ci-dessous.', 'Voir les détails'],
            'reminder2'  => ['DANS DEUX HEURES', 'C’est bientôt, {first}.', 'Votre entretien commence dans environ deux heures. Bonne chance : nous avons hâte d’échanger avec vous.', 'Voir les détails'],
            'chase'      => ['MERCI DE CONFIRMER', 'Nous attendons votre réponse, {first}.', 'Nous vous avons invité(e) à un entretien et n’avons pas encore de réponse. Un seul clic nous dit si le créneau vous convient.', 'Répondre maintenant'],
        ];
        [$eyebrow, $headline, $lede, $cta] = $T[$kind];
        $headline = str_replace('{first}', self::h($first), self::h($headline));
        $L = $en ? ['format' => 'Format', 'where' => 'Where', 'who' => 'With', 'role' => 'Position', 'ref' => 'Reference', 'notes' => 'Good to know', 'cal' => 'Add to calendar', 'was' => 'Previously',
                    'video' => 'Video call', 'onsite' => 'On site', 'phone' => 'Phone call', 'link_later' => 'The link will be sent before the interview.', 'min' => 'min', 'foot' => 'Sent by the StudyVibe recruitment team.',
                    'help' => 'Need to change something? Answer from your application page.', 'gcal' => 'Google Calendar', 'ics' => 'Apple / Outlook (.ics)']
                : ['format' => 'Format', 'where' => 'Lieu', 'who' => 'Avec', 'role' => 'Poste', 'ref' => 'Référence', 'notes' => 'Bon à savoir', 'cal' => 'Ajouter au calendrier', 'was' => 'Avant',
                    'video' => 'Visioconférence', 'onsite' => 'Sur site', 'phone' => 'Appel téléphonique', 'link_later' => 'Le lien sera envoyé avant l’entretien.', 'min' => 'min', 'foot' => 'Envoyé par l’équipe recrutement de StudyVibe.',
                    'help' => 'Un changement à demander ? Répondez depuis votre page de candidature.', 'gcal' => 'Google Agenda', 'ics' => 'Apple / Outlook (.ics)'];

        $where = (string)($iv['location'] ?? '');
        $whereHtml = $where === '' ? ($iv['mode'] === 'video' ? self::h($L['link_later']) : '—')
            : (Careers::url($where) !== '' ? '<a href="' . self::h($where) . '" style="color:' . self::INK . ';font-weight:700;">' . self::h($where) . '</a>' : self::h($where));
        $rows = [[$L['role'], self::h($role)], [$L['format'], self::h($L[$iv['mode']]) . ' · ' . (int)$iv['duration_min'] . ' ' . $L['min']], [$L['where'], $whereHtml]];
        if (!empty($iv['interviewer'])) {
            $rows[] = [$L['who'], self::h((string)$iv['interviewer'])];
        }
        $rows[] = [$L['ref'], '<span style="font-family:Consolas,Menlo,monospace;letter-spacing:.04em;">' . self::h((string)$app['reference']) . '</span>'];
        $tr = '';
        foreach ($rows as $i => [$k, $v]) {
            $b = $i < count($rows) - 1 ? 'border-bottom:1px solid ' . self::LINE . ';' : '';
            $tr .= '<tr><td valign="top" style="padding:12px 12px 12px 0;' . $b . 'font:600 11px ' . self::FONT . ';letter-spacing:.12em;text-transform:uppercase;color:' . self::MUTED . ';width:26%;">' . self::h($k) . '</td>'
                 . '<td valign="top" style="padding:12px 0;' . $b . 'font:15px ' . self::FONT . ';color:' . self::INK . ';">' . $v . '</td></tr>';
        }

        $old = '';
        if ($kind === 'updated' && !empty($o['old'])) {
            $ow = self::dateParts((string)$o['old'], $en);
            $old = '<tr><td style="padding:0 32px 6px;"><p style="margin:0;font:13px ' . self::FONT . ';color:' . self::MUTED . ';">' . self::h($L['was']) . ' : <span style="text-decoration:line-through;">' . self::h($ow['long'] . ' · ' . $ow['time']) . '</span></p></td></tr>';
        }
        $muted = $kind === 'cancelled';
        $tileBg = $muted ? '#8A92B2' : $accent;
        $notes = '';
        if (!empty($iv['instructions']) && !$muted) {
            $notes = '<tr><td style="padding:6px 32px 8px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="border-left:4px solid ' . $accent . ';background:' . self::FOG . ';padding:14px 18px;border-radius:0 12px 12px 0;">'
                   . '<p style="margin:0 0 4px;font:700 11px ' . self::FONT . ';letter-spacing:.12em;text-transform:uppercase;color:' . self::MUTED . ';">' . self::h($L['notes']) . '</p>'
                   . '<p style="margin:0;font:14px/1.6 ' . self::FONT . ';color:' . self::INK . ';">' . nl2br(self::h((string)$iv['instructions'])) . '</p></td></tr></table></td></tr>';
        }
        $btnText = $kind === 'reminder2' || $kind === 'invite' || $kind === 'chase' || $kind === 'updated' || $kind === 'reminder24' ? self::NIGHT : '#FFFFFF';
        $buttons = '<tr><td style="padding:22px 32px 6px;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-radius:999px;background:' . ($muted ? self::INK : $accent) . ';">'
                 . '<a href="' . self::h($track) . '" style="display:inline-block;padding:15px 30px;font:700 15px ' . self::FONT . ';color:' . ($muted ? '#FFFFFF' : $btnText) . ';text-decoration:none;border-radius:999px;">' . self::h($cta) . ' &rarr;</a></td></tr></table></td></tr>';
        if (!$muted) {
            $buttons .= '<tr><td style="padding:14px 32px 4px;font:13px ' . self::FONT . ';color:' . self::MUTED . ';">' . self::h($L['cal']) . ' : '
                      . '<a href="' . self::h($gcal) . '" style="color:' . self::INK . ';font-weight:700;">' . self::h($L['gcal']) . '</a> &nbsp;·&nbsp; '
                      . '<a href="' . self::h($ics) . '" style="color:' . self::INK . ';font-weight:700;">' . self::h($L['ics']) . '</a></td></tr>';
        }

        $strike = $muted ? 'text-decoration:line-through;' : '';
        $html = '<!DOCTYPE html><html lang="' . ($en ? 'en' : 'fr') . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"></head>'
            . '<body style="margin:0;padding:0;background:' . self::FOG . ';">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . self::h($when['long'] . ' · ' . $range . ' — ' . $role) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . self::FOG . ';"><tr><td align="center" style="padding:28px 12px 36px;">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;">'
            . '<tr><td style="padding:0 6px 12px;font:700 11px ' . self::FONT . ';letter-spacing:.28em;color:' . self::MUTED . ';">STUDYVIBE &nbsp;/&nbsp; ' . ($en ? 'RECRUITMENT' : 'RECRUTEMENT') . '</td></tr>'
            . '<tr><td style="background:' . self::CARD . ';border-radius:24px;overflow:hidden;box-shadow:0 18px 40px -18px rgba(11,20,55,.45);">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            // banner
            . '<tr><td style="background:' . self::NIGHT . ';padding:34px 32px 30px;border-bottom:5px solid ' . $accent . ';">'
            . '<p style="margin:0 0 14px;font:800 12px ' . self::FONT . ';letter-spacing:.3em;color:' . $accent . ';">' . self::h($eyebrow) . '</p>'
            . '<h1 style="margin:0;font:800 30px/1.15 ' . self::FONT . ';color:#FFFFFF;letter-spacing:-.01em;">' . $headline . '</h1></td></tr>'
            . '<tr><td style="padding:26px 32px 8px;"><p style="margin:0;font:16px/1.6 ' . self::FONT . ';color:' . self::INK . ';">' . self::h($lede) . '</p></td></tr>'
            // calendar tile
            . '<tr><td style="padding:14px 32px 14px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid ' . self::LINE . ';border-radius:18px;"><tr>'
            . '<td width="104" align="center" style="background:' . $tileBg . ';border-radius:17px 0 0 17px;padding:16px 8px;">'
            . '<div style="font:800 12px ' . self::FONT . ';letter-spacing:.2em;color:' . ($muted ? '#FFFFFF' : self::NIGHT) . ';">' . self::h($when['mon']) . '</div>'
            . '<div style="font:800 44px/1.05 ' . self::FONT . ';color:' . ($muted ? '#FFFFFF' : self::NIGHT) . ';">' . self::h($when['day']) . '</div></td>'
            . '<td style="padding:16px 22px;"><div style="font:600 13px ' . self::FONT . ';color:' . self::MUTED . ';text-transform:capitalize;' . $strike . '">' . self::h($when['long']) . '</div>'
            . '<div style="font:800 28px/1.2 ' . self::FONT . ';color:' . self::INK . ';letter-spacing:-.01em;' . $strike . '">' . self::h($range) . '</div>'
            . '<div style="font:12px ' . self::FONT . ';color:' . self::MUTED . ';margin-top:2px;">' . self::h($tz) . '</div></td></tr></table></td></tr>'
            . $old
            . '<tr><td style="padding:6px 32px 6px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $tr . '</table></td></tr>'
            . $notes . $buttons
            . '<tr><td style="padding:22px 32px 30px;"><p style="margin:0;font:12px/1.6 ' . self::FONT . ';color:' . self::MUTED . ';">' . self::h($L['help']) . '</p></td></tr>'
            . '</table></td></tr>'
            . '<tr><td align="center" style="padding:18px 12px 0;font:12px/1.6 ' . self::FONT . ';color:' . self::MUTED . ';">' . self::h($L['foot']) . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        $subjects = $en ? [
            'invite' => 'Interview invitation: ', 'updated' => 'New time for your interview: ', 'cancelled' => 'Interview cancelled: ', 'reminder24' => 'Tomorrow: your interview · ',
            'reminder2' => 'In 2 hours: your interview · ', 'chase' => 'Please confirm your interview: ',
        ] : [
            'invite' => 'Invitation à un entretien : ', 'updated' => 'Nouvel horaire pour votre entretien : ', 'cancelled' => 'Entretien annulé : ', 'reminder24' => 'Demain : votre entretien · ',
            'reminder2' => 'Dans 2 heures : votre entretien · ', 'chase' => 'Merci de confirmer votre entretien : ',
        ];
        return Mailer::send((string)$app['email'], $subjects[$kind] . $role, $html);
    }
}
