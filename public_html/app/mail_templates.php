<?php
// C8: laiškai nariams ir tėvams - vienoje vietoje. Kiekvienas laiškas - antraštė ir pastraipos (lietuviškai; vertimai - app/lang/en.php, ru.php).
// Siunčiama gavėjo kalba: paskyros kalba (accounts.lang, C2), be paskyros - kalba, kuria dabar naršoma. Vienas parašas apačioje.
// Laiškai personalui (nauja paskyra, nario žinutė treneriui, registracija) lieka lietuviškai ir rašomi vietoje.
// Žaidimas (Dojo) laiškuose neminimas, kol jis paslėptas.

const MAIL_TPL = [
    'verify_email' => ['Patvirtinkite el. pašto adresą', [
        'Sveiki, {name},',
        'ačiū, kad registruojatės karateka.lt. Patvirtinkite savo el. pašto adresą paspaudę nuorodą:',
        '{link}',
        'Nuoroda galioja 3 dienas. Jei registravotės ne jūs, šį laišką tiesiog ištrinkite.',
    ]],
    'parent_consent' => ['Prašome sutikimo: {child} registruojasi karateka.lt', [
        'Sveiki,',
        '{child} užsiregistravo VšĮ Karate Ateitis narių sistemoje karateka.lt ir nurodė jūsų el. paštą kaip tėvų / globėjų.',
        'Kadangi vaikui dar nėra 14 metų, paskyra bus aktyvuota tik gavus jūsų sutikimą. Peržiūrėkite ir patvirtinkite:',
        '{link}',
        'Patvirtinę galėsite susikurti ir savo tėvų paskyrą, matyti vaiko tvarkaraštį ir renginius.',
        'Jei nieko apie tai nežinote, laišką ignoruokite - paskyra nebus aktyvuota.',
    ]],
    'account_approved' => ['Paskyra patvirtinta', [
        'Sveiki, {name},',
        'jūsų paskyra karateka.lt patvirtinta! Prisijungę matysite grupės tvarkaraštį ir artėjančius renginius:',
        '{link}',
    ]],
    'member_approved' => ['Narys patvirtintas', [
        'Sveiki,',
        '{child} patvirtintas (-a) ir priskirtas (-a) grupei. Tvarkaraštį matysite paskyroje:',
        '{link}',
    ]],
    'coach_note' => ['Trenerio pastaba: {child}', [
        'Sveiki,',
        'treneris {coach} paliko pastabą ({child}, {date}):',
        '{text}',
        '{lesson}',
        'Paskyroje galite atsakyti treneriui: {link}',
    ]],
    'coach_task' => ['Trenerio užduotis: {child}', [
        'Sveiki,',
        'treneris {coach} paliko užduotį ({child}, {date}):',
        '{text}',
        '{lesson}',
        'Paskyroje galite atsakyti treneriui: {link}',
    ]],
    'coach_reply' => ['Treneris atsakė: {child}', [
        'Sveiki,',
        'treneris {coach} atsakė ({child}):',
        '{text}',
        'Atsakyti: {link}',
    ]],
    'cancelled' => ['{title}', [
        'Sveiki, {name},',
        '{title}.',
        '{reason}',
        'Tvarkaraštis: {link}',
    ]],
    'broadcast' => ['{title}', [
        'Sveiki, {name},',
        '{text}',
        '{link}',
    ]],
    'login_code' => ['Kodas: {code}', [
        'Sveiki, {name},',
        'jūsų karateka.lt prisijungimo kodas:',
        '    {code}',
        'Kodas galioja {min} min.',
        'Jei prisijungti bandote ne jūs - nedelsdami pakeiskite slaptažodį.',
    ]],
    'login_code_enable' => ['Kodas: {code}', [
        'Sveiki, {name},',
        'jūsų karateka.lt prisijungimo su kodu įjungimo kodas:',
        '    {code}',
        'Kodas galioja {min} min.',
        'Jei prisijungti bandote ne jūs - nedelsdami pakeiskite slaptažodį.',
    ]],
    'change_email' => ['Patvirtinkite naują el. paštą', [
        'Sveiki, {name},',
        'norėdami pakeisti savo karateka.lt paskyros el. paštą į šį adresą, paspauskite nuorodą:',
        '{link}',
        'Nuoroda galioja 1 dieną. Jei el. pašto nekeitėte, šį laišką ignoruokite.',
    ]],
    'email_changed' => ['Jūsų el. paštas pakeistas', [
        'Sveiki, {name},',
        'jūsų karateka.lt paskyros el. paštas pakeistas į {email}.',
        'Nuo šiol prisijunkite nauju adresu.',
        'Jei to nedarėte, nedelsdami parašykite info@karateka.lt.',
    ]],
    'password_reset' => ['Slaptažodžio atkūrimas', [
        'Sveiki, {name},',
        'norėdami nustatyti naują slaptažodį, paspauskite nuorodą:',
        '{link}',
        'Nuoroda galioja 1 valandą. Jei slaptažodžio nekeitėte, šį laišką ignoruokite.',
    ]],
    'join_invite' => ['Kvietimas į karateka.lt - {child}', [
        'Sveiki,',
        'VšĮ Karate Ateitis treneris pridėjo {child} į klubo narių sistemą karateka.lt.',
        'Susikurkite prisijungimą - matysite tvarkaraštį, renginius ir XP:',
        '{link}',
        'Nuoroda galioja 60 dienų. Jei jau turite paskyrą karateka.lt - prisijunkite ir atidarykite nuorodą: narys bus pridėtas prie jūsų paskyros.',
    ]],
    'kid_invite' => ['Kvietimas prisijungti prie karateka.lt', [
        'Sveiki, {name},',
        '{parent} kviečia tave susikurti savo prisijungimą VšĮ Karate Ateitis narių sistemoje.',
        'Matysi savo grupės tvarkaraštį, egzaminus ir varžybas.',
        '{link}',
        'Nuoroda galioja 14 dienų.',
    ]],
    'event_invite' => ['Kvietimas: {title}', [
        'Sveiki, {name},',
        'treneris kviečia {kids} į renginį:',
        '{event}',
        'Atsakykite „Dalyvausiu“ arba „Negalėsiu“ iki {deadline}: {link}',
    ]],
];

/** Laiško tekstas gavėjo kalba: [antraštė, tekstas]. Reikšmė gali būti funkcija - ji vykdoma jau gavėjo kalba (pvz. datos). */
function mail_tpl(string $key, array $vars, ?string $lang = null): array
{
    [$subject, $paras] = MAIL_TPL[$key];
    return in_lang($lang ?? current_lang(), function () use ($subject, $paras, $vars) {
        foreach ($vars as $k => $v) {
            if ($v instanceof Closure) {
                $vars[$k] = (string) $v();
            }
        }
        $out = [];
        foreach ($paras as $p) {
            $line = trim(t($p, $vars), "\n");
            if (trim($line) !== '') {
                $out[] = $line;   // tuščia pastraipa (pvz. be pamokos) praleidžiama
            }
        }
        return [t($subject, $vars) . ' — Karateka', implode("\n\n", $out) . mail_footer()];
    });
}

/** Siųsti laišką pagal šabloną gavėjo kalba */
function mail_send_tpl(string $to, string $key, array $vars, ?string $lang = null): bool
{
    [$subject, $body] = mail_tpl($key, $vars, $lang);
    return send_mail($to, $subject, $body);
}

/** Vienas parašas visiems laiškams (jau gavėjo kalba) */
function mail_footer(): string
{
    return "\n\n--\n" . t('VšĮ Karate Ateitis') . ' · karateka.lt';
}
