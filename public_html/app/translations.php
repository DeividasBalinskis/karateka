<?php
// Denio rašomo turinio vertimai (naujienos, renginiai, pamokos, pagrindinis puslapis) į anglų ir rusų kalbas.
// Denis įklijuoja vertimą pats (pvz. išsivertęs su AI): Nustatymai → Vertimai arba „🌐 Vertimai“ prie naujienos, renginio, pamokos.
// Vertimas pririštas prie lietuviško teksto (md5): pakeitus tekstą, senas vertimas nebetinka ir rodomas lietuviškas.
// Jei vertimo nėra - rodoma lietuviškai. Sąsajos tekstai (mygtukai, meniu) - app/i18n.php.
// Automatinis vertimas (nemokamai): DeepL API Free - 500 000 simbolių per mėnesį. config.php: 'translate' => ['key' => '...:fx'].
// Su raktu: išsaugojus naujieną, renginį, pamoką ar pagrindinį puslapį, trūkstami vertimai sukuriami patys; Denis gali pataisyti.

/** Turinio tekstas dabartine kalba (jei įrašytas vertimas), kitaip - lietuviškas */
function tr_text(?string $lt): string
{
    $lt = (string) $lt;
    if ($lt === '' || current_lang() === 'lt') {
        return $lt;
    }
    return tr_map(current_lang())[md5(trim($lt))] ?? $lt;
}

/** Visi vienos kalbos vertimai [hash => vertimas] (klubui jų nedaug - įkeliami vienu kartu) */
function tr_map(string $lang): array
{
    static $maps = [];
    if (!isset($maps[$lang])) {
        try {
            $maps[$lang] = q('SELECT hash, body FROM content_translations WHERE lang = ?', [$lang])->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $ex) {
            $maps[$lang] = [];   // dar neimportuotas db/024
        }
    }
    return $maps[$lang];
}

/** Įrašyti (ar ištrinti, jei tuščia) vieno teksto vertimą */
function tr_save(string $lt, string $lang, string $body): void
{
    $hash = md5(trim($lt));
    $body = trim(str_replace("\r\n", "\n", $body));
    if ($body === '') {
        q('DELETE FROM content_translations WHERE hash = ? AND lang = ?', [$hash, $lang]);
    } else {
        q('INSERT INTO content_translations (hash, lang, source, body) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE source = VALUES(source), body = VALUES(body)',
            [$hash, $lang, trim($lt), $body]);
    }
}

/** Ar vertimų lentelė jau yra (db/024) */
function tr_ready(): bool
{
    try {
        q_value('SELECT 1 FROM content_translations LIMIT 1');
        return true;
    } catch (PDOException $ex) {
        return false;
    }
}

/**
 * Ką versti: [['label' => kas tai, 'text' => lietuviškas tekstas, 'multi' => ar ilgas], ...]
 * $entity: news, event, lesson, home. Pasikartojantys tekstai - vieną kartą.
 */
function tr_items(string $entity, int $id): array
{
    $items = [];
    $add = function (string $label, ?string $text, bool $multi = false) use (&$items) {
        $text = trim((string) $text);
        if ($text === '' || !preg_match('/\p{L}{2,}/u', $text) || isset($items[md5($text)])) {
            return;
        }
        $items[md5($text)] = ['label' => $label, 'text' => $text, 'multi' => $multi || mb_strlen($text) > 90 || strpos($text, "\n") !== false];
    };
    switch ($entity) {
        case 'news':
            if ($n = q_one('SELECT * FROM news WHERE id = ?', [$id])) {
                $add(t('Pavadinimas'), $n['title']);
                $add(t('Tekstas'), $n['body'], true);
            }
            break;
        case 'event':
            if ($ev = q_one('SELECT * FROM events WHERE id = ?', [$id])) {
                $add(t('Pavadinimas'), $ev['title']);
                $add(t('Trumpai'), $ev['summary'] ?? '');
                $add(t('Aprašymas'), $ev['description'], true);
                $add(t('Vieta'), $ev['location']);
            }
            break;
        case 'lesson':
            if ($l = q_one('SELECT * FROM lessons WHERE id = ?', [$id])) {
                $add(t('Pavadinimas'), $l['title']);
                foreach (q_all('SELECT * FROM lesson_blocks WHERE lesson_id = ? ORDER BY sort_order, id', [$id]) as $b) {
                    $type = t(LESSON_BLOCK_TYPES[$b['type']][0] ?? $b['type']);
                    if (in_array($b['type'], ['heading', 'text', 'note', 'link'], true)) {
                        $add($type, $b['body'], $b['type'] !== 'heading');
                    }
                    $add($type . ' · ' . t('prierašas'), $b['caption'] ?? '');
                }
            }
            break;
        case 'home':
            // Visi pagrindinio puslapio tekstai (be nuorodų, paveikslėlių, spalvų)
            $walk = function ($v, string $path) use (&$walk, $add) {
                if (is_array($v)) {
                    foreach ($v as $k => $x) {
                        if (!in_array($k, ['image', 'photo', 'images', 'url', 'link_url', 'color', 'sign', 'side', 'type', 'id', 'hidden', 'lead', 'locations', 'icon'], true)) {
                            $walk($x, $path === '' ? (string) $k : $path . ' · ' . $k);
                        }
                    }
                } elseif (is_string($v) && !preg_match('~^(https?:|img/|uploads/|#)~', $v)) {
                    $add($path, $v);
                }
            };
            foreach (home_page(true) as $b) {
                $add(t('Meniu'), $b['menu'] ?? '');
                $walk($b['d'] ?? [], t(HOME_BLOCK_TYPES[$b['type']][0] ?? $b['type']));
            }
            break;
    }
    return array_values($items);
}

// ---------- Automatinis vertimas (DeepL) ----------

function tr_auto_enabled(): bool
{
    return trim((string) config('translate.key', '')) !== '';
}

/** Išverčia tekstus (lietuvių → en / ru). Grąžina vertimus ta pačia tvarka arba [] (nepavyko - klaida įrašoma į žurnalą) */
function tr_auto_texts(array $texts, string $lang): array
{
    $key = trim((string) config('translate.key', ''));
    if ($key === '' || !$texts || !function_exists('curl_init')) {
        return [];
    }
    $url = (string) config('translate.url', '') ?: (substr($key, -3) === ':fx' ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: DeepL-Auth-Key ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['text' => array_values($texts), 'source_lang' => 'LT', 'target_lang' => $lang === 'en' ? 'EN-GB' : 'RU',
            'preserve_formatting' => true], JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if ($status !== 200 || !isset($data['translations']) || count($data['translations']) !== count($texts)) {
        error_log('auto translate failed: HTTP ' . $status . ' ' . mb_substr((string) $raw, 0, 200));
        return [];
    }
    return array_map(function ($t) { return (string) ($t['text'] ?? ''); }, $data['translations']);
}

/**
 * Išverčia įrašo tekstus, kurių vertimo dar nėra ($overwrite - ir esamus). Tekstai, išversti žodyne (numatyti), praleidžiami.
 * Grąžina, kiek vertimų sukurta (0 - jei rakto nėra ar nepavyko).
 */
function tr_auto_entity(string $entity, int $id, bool $overwrite = false): int
{
    if (!tr_auto_enabled() || !tr_ready()) {
        return 0;
    }
    if ($entity === 'home') {
        current_lang('lt');   // numatyti pagrindinio puslapio tekstai - lietuviški originalai
    }
    $done = 0;
    $items = tr_items($entity, $id);
    foreach (['en', 'ru'] as $lang) {
        $have = $overwrite ? [] : tr_map($lang);
        $todo = array_values(array_filter($items, function ($it) use ($have, $lang) {
            return !isset($have[md5($it['text'])]) && t_in($lang, $it['text']) === null;
        }));
        foreach (array_chunk($todo, 40) as $chunk) {
            $res = tr_auto_texts(array_column($chunk, 'text'), $lang);
            foreach ($chunk as $i => $it) {
                if (isset($res[$i]) && trim($res[$i]) !== '') {
                    tr_save($it['text'], $lang, $res[$i]);
                    $done++;
                }
            }
        }
    }
    return $done;
}
