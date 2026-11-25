<?php
// C2: visų nustatymų registras. Kiekvienas site_settings vardas turi būti čia (tikrina tests/checks.sh → tests/settings-check.php).
// Žemėlapis: Valdymas → Nustatymai → „Visi nustatymai“ (admin/nustatymai-visi.php) - dabartinė reikšmė ir kur keisti.
// Kiekvienas pakeitimas per set_site_setting() įrašomas į settings_log (db/050), visai svetainei skirtus galima atkurti.
//
// Laukai: label - pavadinimas; group - skyrius žemėlapyje; type - bool / choice / number / text / json / mark / secret;
//         where - kur keičiama; owner - kas keičia (admin - administratorius); restore - ar galima atkurti iš žurnalo;
//         prefix - vardas su priedu (pvz. varz_sekr_<id>).

const SETTINGS = [
    // Svetainė
    'site_theme'       => ['label' => 'Svetainės tema (visiems lankytojams)', 'group' => 'Svetainė', 'type' => 'choice', 'where' => 'admin/nustatymai.php', 'owner' => 'admin', 'restore' => true],
    'design_likes'     => ['label' => 'Temos ir šriftai, pažymėti „Patinka“', 'group' => 'Svetainė', 'type' => 'json', 'where' => '🎨 ir Aa viršuje', 'owner' => 'admin', 'restore' => true],
    'home_page'        => ['label' => 'Pagrindinio puslapio turinys', 'group' => 'Svetainė', 'type' => 'json', 'where' => 'admin/puslapis.php', 'owner' => 'admin', 'restore' => false],
    'home_page_prev'   => ['label' => 'Pagrindinis puslapis - ankstesnė versija („Atšaukti“)', 'group' => 'Svetainė', 'type' => 'mark', 'where' => 'admin/puslapis.php', 'owner' => 'admin', 'restore' => false],
    'reg_off'          => ['label' => 'Registracija išjungta (paskyros, renginiai, egzaminai, Budo)', 'group' => 'Svetainė', 'type' => 'json', 'where' => 'admin/nustatymai.php', 'owner' => 'admin', 'restore' => true],
    'feedback_button'  => ['label' => 'Atsiliepimų mygtukas (💡) - kam rodomas', 'group' => 'Svetainė', 'type' => 'choice', 'where' => 'admin/atsiliepimai.php', 'owner' => 'admin', 'restore' => true],
    'feedback_api_key' => ['label' => 'Atsiliepimų API raktas', 'group' => 'Svetainė', 'type' => 'secret', 'where' => 'admin/atsiliepimai.php', 'owner' => 'admin', 'restore' => false],
    // Klubas
    'xp_level_base'    => ['label' => 'Pirmų 5 lygių kaina (XP)', 'group' => 'Klubas', 'type' => 'number', 'where' => 'admin/taskai.php', 'owner' => 'admin', 'restore' => true],
    'xp_level_step'    => ['label' => 'Kiek XP lygis brangsta kas 5 lygius', 'group' => 'Klubas', 'type' => 'number', 'where' => 'admin/taskai.php', 'owner' => 'admin', 'restore' => true],
    'exam_prices'      => ['label' => 'Egzaminų kainos', 'group' => 'Klubas', 'type' => 'json', 'where' => 'admin/egzaminai.php', 'owner' => 'admin', 'restore' => true],
    'exam_pay_info'    => ['label' => 'Egzamino mokėjimo informacija', 'group' => 'Klubas', 'type' => 'text', 'where' => 'admin/egzaminai.php', 'owner' => 'admin', 'restore' => true],
    'exam_waitlist_token' => ['label' => 'Egzaminų registracijos nuoroda (laukiantieji)', 'group' => 'Klubas', 'type' => 'secret', 'where' => 'admin/egzaminai.php', 'owner' => 'coach', 'restore' => false],
    'exam_waitlist_open' => ['label' => 'Egzaminų registracija (laukiantieji) atidaryta', 'group' => 'Klubas', 'type' => 'choice', 'where' => 'admin/egzaminai.php', 'owner' => 'coach', 'restore' => true],
    'just_token'       => ['label' => 'Pateisinimų nuoroda', 'group' => 'Klubas', 'type' => 'secret', 'where' => 'admin/pateisinimai.php', 'owner' => 'admin', 'restore' => false],
    'just_open'        => ['label' => 'Pateisinimų prašymai priimami', 'group' => 'Klubas', 'type' => 'choice', 'where' => 'admin/pateisinimai.php', 'owner' => 'admin', 'restore' => true],
    'just_text'        => ['label' => 'Pateisinimo tekstas (be Word šablono)', 'group' => 'Klubas', 'type' => 'text', 'where' => 'admin/pateisinimai.php', 'owner' => 'admin', 'restore' => true],
    'just_notify'      => ['label' => 'Kam pranešti apie naujus pateisinimų prašymus', 'group' => 'Klubas', 'type' => 'json', 'where' => 'admin/pateisinimai.php#pranesimai', 'owner' => 'admin', 'restore' => true],
    'just_template'    => ['label' => 'Pateisinimo Word šablonas', 'group' => 'Klubas', 'type' => 'json', 'where' => 'admin/pateisinimai.php', 'owner' => 'admin', 'restore' => false],
    'exam_protocol_defaults' => ['label' => 'Egzamino protokolo numatytieji (komisija, vieta)', 'group' => 'Klubas', 'type' => 'json', 'where' => 'admin/egzaminai.php', 'owner' => 'coach', 'restore' => false],
    // Žaidimas
    'game_access'      => ['label' => 'Kas mato žaidimą (Dojo)', 'group' => 'Žaidimas', 'type' => 'choice', 'where' => 'admin/testavimas.php', 'owner' => 'admin', 'restore' => true],
    // Budo
    'taika_ui'         => ['label' => 'Budo išvaizda ir meniu (visiems treneriams)', 'group' => 'Budo', 'type' => 'json', 'where' => 'admin/varzybos.php', 'owner' => 'coach', 'restore' => false],
    'taika_diploma'    => ['label' => 'Budo diplomo šablonas', 'group' => 'Budo', 'type' => 'json', 'where' => 'admin/varzybos.php', 'owner' => 'coach', 'restore' => false],
    'taika_demo_token' => ['label' => 'Budo bandymo nuoroda', 'group' => 'Budo', 'type' => 'secret', 'where' => 'admin/varzybos.php', 'owner' => 'admin', 'restore' => false],
    'varz_cam_key'     => ['label' => 'Transliacijos telefono kameros raktas (QR ir OBS nuorodos)', 'group' => 'Budo', 'type' => 'secret', 'where' => 'admin/varzybu-transliacija.php', 'owner' => 'admin', 'restore' => false],
    'varz_bar_key'     => ['label' => 'Transliacijos nuolatinės juostos raktas (OBS)', 'group' => 'Budo', 'type' => 'secret', 'where' => 'admin/varzybu-transliacija.php', 'owner' => 'admin', 'restore' => false],
    'varz_sekr_'       => ['label' => 'Budo sekretoriato nuoroda (kiekvienoms varžyboms)', 'group' => 'Budo', 'type' => 'secret', 'where' => 'admin/varzybos.php', 'owner' => 'coach', 'restore' => false, 'prefix' => true],
    // LTKF
    'ltkf_mode'        => ['label' => 'LTKF svetainės būsena', 'group' => 'LTKF', 'type' => 'choice', 'where' => 'admin/ltkf-perziura.php#busena', 'owner' => 'admin', 'restore' => true],
    'ltkf_menu'        => ['label' => 'LTKF meniu', 'group' => 'LTKF', 'type' => 'json', 'where' => 'ltkf/index.php?redaguoti=1', 'owner' => 'admin', 'restore' => true],
    'ltkf_nust'        => ['label' => 'LTKF išvaizda ir kontaktai', 'group' => 'LTKF', 'type' => 'json', 'where' => 'ltkf/index.php?redaguoti=1', 'owner' => 'admin', 'restore' => true],
    'ltkf_editors'     => ['label' => 'LTKF redaktorių nuorodos', 'group' => 'LTKF', 'type' => 'secret', 'where' => 'admin/ltkf-perziura.php#redaktoriai', 'owner' => 'admin', 'restore' => false],
    'ltkf_review_token' => ['label' => 'LTKF peržiūros nuoroda', 'group' => 'LTKF', 'type' => 'secret', 'where' => 'admin/ltkf-perziura.php', 'owner' => 'admin', 'restore' => false],
    // Vienkartinės žymos (ne nustatymai - kad pavyzdžiai ir pamokos nebūtų kuriami dukart)
    'demo_isvalyta'    => ['label' => 'Pavyzdiniai duomenys išvalyti', 'group' => 'Žymos', 'type' => 'mark', 'where' => '', 'owner' => 'admin', 'restore' => false],
    'dev_pavyzdziai'   => ['label' => 'Pavyzdžiai sukurti (kompiuteryje)', 'group' => 'Žymos', 'type' => 'mark', 'where' => '', 'owner' => 'admin', 'restore' => false, 'prefix' => true],
    'pamokos_kyu'      => ['label' => 'Kyu pamokos sukurtos', 'group' => 'Žymos', 'type' => 'mark', 'where' => '', 'owner' => 'admin', 'restore' => false],
    'pamokos_dan'      => ['label' => 'Dan pamokos sukurtos', 'group' => 'Žymos', 'type' => 'mark', 'where' => '', 'owner' => 'admin', 'restore' => false],
    'pamokos_katos'    => ['label' => 'Katų pamokos sukurtos', 'group' => 'Žymos', 'type' => 'mark', 'where' => '', 'owner' => 'admin', 'restore' => false],
    'reactions'        => ['label' => 'Senas reakcijų sąrašas (nebenaudojamas, db/011)', 'group' => 'Žymos', 'type' => 'mark', 'where' => '', 'owner' => 'admin', 'restore' => false],
    'pamokos_katos_v2' => ['label' => 'Katų pamokos atnaujintos', 'group' => 'Žymos', 'type' => 'mark', 'where' => '', 'owner' => 'admin', 'restore' => false],
];

/** Serverio nustatymai (config.php) - žemėlapyje tik skaitomi */
function settings_server(): array
{
    $sms = (array) config('sms', []);
    return [
        [t('Aplinka (env)'), (string) (config('env') ?: 'live')],
        [t('SMS teikėjas'), (string) ($sms['provider'] ?? 'log') . ((($sms['provider'] ?? 'log') === 'log') ? ' ' . t('(bandomasis režimas)') : '')],
        [t('Paštas: šifravimas'), (string) (config('mail.secure') ?: '-')],
        [t('LTKF adresas (ltkf_url)'), (string) (config('ltkf_url') ?: '-')],
        [t('Išorinės Varžybos'), config('varzybos') ? t('įjungta') : t('išjungta')],
    ];
}

/** Registro įrašas vardui (su priedu - pvz. varz_sekr_12) arba null */
function setting_def(string $name): ?array
{
    if (isset(SETTINGS[$name])) {
        return SETTINGS[$name] + ['name' => $name];
    }
    foreach (SETTINGS as $k => $d) {
        if (!empty($d['prefix']) && strpos($name, $k) === 0) {
            return $d + ['name' => $k];
        }
    }
    return null;
}

function settings_log_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            q_value('SELECT 1 FROM settings_log LIMIT 1');
            $ok = true;
        } catch (PDOException $ex) {
            $ok = false;
        }
    }
    return $ok;
}

/** Pakeitimo įrašas žurnale (iš set_site_setting). Slaptų - be reikšmių, žymų - neįrašoma. */
function settings_log_write(string $name, ?string $old, string $new): void
{
    $def = setting_def($name);
    if (($def['type'] ?? '') === 'mark' || $old === $new || !settings_log_ready()) {
        return;
    }
    $secret = ($def['type'] ?? '') === 'secret';
    $a = function_exists('current_account') ? current_account() : null;
    try {
        q('INSERT INTO settings_log (name, old_value, new_value, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?)',
            [mb_substr($name, 0, 80), $secret ? null : $old, $secret ? null : mb_substr($new, 0, 60000), $a ? (int) $a['id'] : null,
             $a ? mb_substr(trim($a['first_name'] . ' ' . $a['last_name']), 0, 120) : null]);
        q('DELETE FROM settings_log WHERE changed_at < NOW() - INTERVAL 1 YEAR');
    } catch (PDOException $ex) {
        // žurnalas - ne svarbiau už patį pakeitimą
    }
}

/** Reikšmė žemėlapiui: trumpai ir suprantamai */
function setting_show(string $name, ?string $v): string
{
    $def = setting_def($name);
    if ($v === null || $v === '') {
        return t('- (numatyta)');
    }
    switch ($def['type'] ?? 'text') {
        case 'secret':
            return t('••• (nustatyta)');
        case 'mark':
            return '✓ ' . $v;
        case 'json':
            $d = json_decode($v, true);
            if ($name === 'reg_off') {
                return $d ? t('išjungta:') . ' ' . implode(', ', (array) $d) : t('viskas atidaryta');
            }
            if ($name === 'game_access') {
                return t('tik kai kuriems') . ' (' . count((array) $d) . ')';
            }
            return is_array($d) ? count($d) . ' ' . t('įraš.') : mb_strimwidth($v, 0, 60, '…');
        default:
            if ($name === 'game_access') {
                return $v === 'all' ? t('visi nariai') : t('tik kai kuriems');
            }
            return mb_strimwidth($v, 0, 80, '…');
    }
}
