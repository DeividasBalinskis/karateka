<?php
// CSRF apsauga: kiekvienoje POST formoje echo csrf_field(), apdorojant - csrf_check()

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Ar užklausa atsiuntė šios sesijos žetoną (_csrf formoje, X-CSRF-Token antraštėje arba $sent - pvz. iš JSON).
 * C1: kiekvienas POST tikrina - csrf_check() (HTML klaida) arba csrf_ok() (JSON atsakymams). Išimtys - tik API su raktu antraštėje
 * (atsiliepimai-api.php, diegimas-api.php, perziura-api.php), jos surašytos docs/standartai.md ir tests/checks.sh.
 */
function csrf_ok($sent = null): bool
{
    $sent = $sent ?? ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function csrf_check(): void
{
    if (!csrf_ok()) {
        http_response_code(400);
        exit('Sesija pasibaigė arba forma netinkama. Grįžkite atgal, atnaujinkite puslapį ir bandykite dar kartą.');
    }
}
