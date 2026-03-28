<?php
// =============================================================================
// DEBUG PAGE — token.php CORS / login failure investigation
// ELIMINAR ESTE ARCHIVO ANTES DE PASAR A PRODUCCIÓN
// =============================================================================
// Uso: https://lms.isi.edu.pa/local/soluttolms_core/debug_token.php
//        ?secret=ISI_DEBUG_TOKEN_2024
//        &username=8-902-889
//        &password=CONTRASEÑA_DEL_USUARIO   (opcional — para probar auth real)
// =============================================================================

// Headers antes de CUALQUIER include de Moodle para no perdernos en un redirect.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

// ── Protección mínima ────────────────────────────────────────────────────────
$secret = $_GET['secret'] ?? '';
if ($secret !== 'ISI_DEBUG_TOKEN_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized. Agrega ?secret=ISI_DEBUG_TOKEN_2024']);
    exit;
}

$username_raw = trim($_GET['username'] ?? '');
$password_raw = $_GET['password'] ?? '';   // Opcional

$out = [];
$out['warning'] = 'ELIMINAR ESTE ARCHIVO ANTES DE PASAR A PRODUCCION';
$out['timestamp'] = date('Y-m-d H:i:s T');
$out['username_input'] = $username_raw;

// ── Detectar si el browser manda cookies de sesión de Moodle ────────────────
$moodle_session_cookies = array_filter(
    $_COOKIE,
    fn($k) => strncmp($k, 'MoodleSession', 13) === 0,
    ARRAY_FILTER_USE_KEY
);
$out['browser_moodle_cookies'] = [
    'count'   => count($moodle_session_cookies),
    'names'   => array_keys($moodle_session_cookies),
    'riesgo'  => count($moodle_session_cookies) > 0
        ? 'ALTO — con NO_MOODLE_COOKIES=false estas cookies activan la sesión de Moodle durante config.php'
        : 'NINGUNO — sin cookies de sesión config.php no debería redirigir',
];

// ── Inicializar Moodle (igual que token.php original) ───────────────────────
define('AJAX_SCRIPT', true);
define('REQUIRE_CORRECT_ACCESS', true);
define('NO_MOODLE_COOKIES', false);   // Igual que token.php para reproducir el problema

// Capturar si config.php genera output/redirect inesperado
$redirect_happened = false;
$init_output       = '';

// Monkey-patch redirect() no es posible en PHP puro; usamos ob para detectar output.
ob_start();
try {
    require_once('../../config.php');
    require_once($CFG->libdir . '/externallib.php');
} catch (Throwable $e) {
    $out['config_php_exception'] = [
        'class'   => get_class($e),
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ];
}
$init_output = ob_get_clean();

$out['config_php'] = [
    'loaded_ok'        => isset($CFG),
    'appurl'           => $CFG->appurl ?? null,
    'wwwroot'          => $CFG->wwwroot ?? null,
    'output_generado'  => $init_output !== ''
        ? substr($init_output, 0, 500)   // Primeros 500 chars — puede ser HTML de redirect
        : null,
    'output_es_redirect_html' => $init_output !== '' && (
        strpos($init_output, 'location.href') !== false ||
        strpos($init_output, '/login/') !== false ||
        strpos($init_output, 'Redirect') !== false
    ),
];

// Si config.php no cargó, no podemos continuar
if (!isset($CFG) || !isset($DB)) {
    $out['fatal'] = 'config.php no cargó correctamente. Revisar logs de PHP.';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Headers HTTP reales enviados hasta este punto ────────────────────────────
// (Solo disponibles antes de enviar body, pero ya los enviamos arriba)
$out['response_headers_sent'] = headers_list();

// ── Verificación del usuario en la base de datos ─────────────────────────────
if ($username_raw === '') {
    $out['info'] = 'Agrega &username=USUARIO para analizar una cuenta específica';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$username_clean = trim(core_text::strtolower($username_raw));
$out['username_normalizado'] = $username_clean;

// Registro en {user}
$user_record = $DB->get_record('user', ['username' => $username_clean], '*', IGNORE_MISSING);
if (!$user_record) {
    $out['user_db'] = [
        'encontrado' => false,
        'diagnostico' => 'El usuario NO EXISTE en Moodle. authenticate_user_login() retornará false → excepción invalidlogin.',
    ];
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$out['user_db'] = [
    'encontrado'   => true,
    'id'           => (int)$user_record->id,
    'username'     => $user_record->username,
    'auth'         => $user_record->auth,
    'deleted'      => (bool)$user_record->deleted,
    'suspended'    => (bool)$user_record->suspended,
    'confirmed'    => (bool)$user_record->confirmed,
    'policyagreed' => (bool)$user_record->policyagreed,
    'mnethostid'   => (int)$user_record->mnethostid,
    'lastlogin'    => $user_record->lastlogin ? date('Y-m-d H:i:s', $user_record->lastlogin) : null,
    'currentlogin' => $user_record->currentlogin ? date('Y-m-d H:i:s', $user_record->currentlogin) : null,
];

// ── Problemas claros de cuenta ───────────────────────────────────────────────
$account_issues = [];
if ($user_record->deleted)   $account_issues[] = 'CUENTA ELIMINADA — authenticate_user_login retorna false';
if ($user_record->suspended) $account_issues[] = 'CUENTA SUSPENDIDA — authenticate_user_login retorna false';
if (!$user_record->confirmed) $account_issues[] = 'EMAIL NO CONFIRMADO — token.php lanza excepción usernotconfirmed (después del header CORS, no causaría CORS error)';
$out['account_issues'] = $account_issues ?: ['Ninguno detectado'];

// ── Sesión activa de este usuario en Moodle ──────────────────────────────────
$active_sessions = $DB->get_records_sql(
    "SELECT id, sid, userid, timecreated, timemodified, firstip, lastip
       FROM {sessions}
      WHERE userid = ?
      ORDER BY timemodified DESC
      LIMIT 5",
    [$user_record->id]
);

$sessions_info = [];
foreach ($active_sessions as $sess) {
    $sessions_info[] = [
        'sid'          => substr($sess->sid, 0, 8) . '...',  // Parcial por seguridad
        'timecreated'  => date('Y-m-d H:i:s', $sess->timecreated),
        'timemodified' => date('Y-m-d H:i:s', $sess->timemodified),
        'lastip'       => $sess->lastip,
    ];
}
$out['active_sessions'] = [
    'count'   => count($active_sessions),
    'sessions' => $sessions_info,
    'diagnostico' => count($active_sessions) > 0
        ? 'El usuario TIENE sesiones activas. Con NO_MOODLE_COOKIES=false, si el browser envía la cookie MoodleSession, config.php puede intentar reanudar una sesión en estado especial y redirigir.'
        : 'Sin sesiones activas — las cookies de sesión no deberían causar redirect.',
];

// ── Política de sitio ────────────────────────────────────────────────────────
$site_policy_active = !empty($CFG->sitepolicy) || !empty($CFG->sitepolicyhandler);
$out['site_policy'] = [
    'activa'         => $site_policy_active,
    'usuario_acepto' => (bool)$user_record->policyagreed,
    'diagnostico'    => ($site_policy_active && !$user_record->policyagreed)
        ? 'PROBLEMA: Política activa y el usuario NO la ha aceptado. Moodle puede redirigir a la página de política durante config.php si hay sesión activa.'
        : 'OK',
];

// ── Contraseña expirada ──────────────────────────────────────────────────────
try {
    $userauth = get_auth_plugin($user_record->auth);
    $exp_enabled = !empty($userauth->config->expiration) && $userauth->config->expiration == 1;
    $days2expire = $exp_enabled ? $userauth->password_expire($username_clean) : null;
    $out['password_expiry'] = [
        'expiracion_habilitada' => $exp_enabled,
        'dias_restantes'        => $days2expire,
        'expirada'              => $days2expire !== null && intval($days2expire) < 0,
        'diagnostico'           => ($exp_enabled && $days2expire !== null && intval($days2expire) < 0)
            ? 'PROBLEMA: La contraseña está EXPIRADA. token.php lanza excepción passwordisexpired (después del header CORS — no causaría CORS error).'
            : 'OK',
    ];
} catch (Throwable $e) {
    $out['password_expiry'] = ['error' => $e->getMessage()];
}

// ── Mantenimiento del sitio ──────────────────────────────────────────────────
$out['site_maintenance'] = [
    'activo'     => !empty($CFG->maintenance_enabled),
    'diagnostico' => !empty($CFG->maintenance_enabled)
        ? 'PROBLEMA: Sitio en mantenimiento. Causaría excepción, no redirect — no debería causar CORS error.'
        : 'OK',
];

// ── Campo personalizado studentstatus ────────────────────────────────────────
$field_record = $DB->get_record('user_info_field', ['shortname' => 'studentstatus'], '*', IGNORE_MISSING);
if (!$field_record) {
    $out['studentstatus'] = [
        'campo_existe' => false,
        'diagnostico'  => 'PROBLEMA CRÍTICO: El campo user_info_field "studentstatus" NO EXISTE. La línea $field->id en token.php lanzará fatal error al intentar acceder a ->id en false. Esto ocurre DESPUÉS del header CORS (línea 33), así que no causaría CORS error, pero sí fallo de autenticación para TODOS los usuarios.',
    ];
} else {
    $data_record = $DB->get_record_sql(
        "SELECT d.* FROM {user_info_data} d
         JOIN {user} u ON u.id = d.userid
         WHERE d.fieldid = ? AND u.deleted = 0 AND d.userid = ?",
        [$field_record->id, $user_record->id],
        IGNORE_MISSING
    );
    $out['studentstatus'] = [
        'campo_existe'         => true,
        'registro_data_existe' => $data_record !== false,
        'valor'                => $data_record ? $data_record->data : null,
        'diagnostico'          => $data_record === false
            ? 'PROBLEMA CRÍTICO: El usuario NO tiene registro en user_info_data para "studentstatus". La línea $user_info_data->data en token.php (línea 125) lanzará un error de PHP al acceder ->data en false/null. Esto ocurre DESPUÉS del header CORS, por lo que no causaría el CORS error reportado, pero SI puede estar impidiendo que el token se genere correctamente.'
            : 'OK — valor: ' . $data_record->data,
    ];
}

// ── is_restored_user ─────────────────────────────────────────────────────────
$out['is_restored_user'] = [
    'valor'        => is_restored_user($username_clean),
    'diagnostico'  => is_restored_user($username_clean)
        ? 'PROBLEMA: Usuario restaurado de backup — token.php lanza excepción restoredaccountresetpassword.'
        : 'OK',
];

// ── Prueba de authenticate_user_login (solo si se provee contraseña) ─────────
if ($password_raw !== '') {
    $reason = null;
    try {
        $auth_result = authenticate_user_login($username_clean, $password_raw, false, $reason, false);
        $out['authenticate_user_login'] = [
            'resultado'  => $auth_result !== false ? 'EXITO — retornó objeto user' : 'FALLO — retornó false',
            'reason_code' => $reason,
            'reason_map'  => [
                0  => 'AUTH_LOGIN_OK',
                1  => 'AUTH_LOGIN_FAILED',
                2  => 'AUTH_LOGIN_NOUSER',
                3  => 'AUTH_LOGIN_UNAUTHORISED',
                4  => 'AUTH_LOGIN_SUSPENDED',
                5  => 'AUTH_LOGIN_LOCKOUT',
            ][$reason] ?? "Código desconocido: $reason",
            'diagnostico' => $auth_result === false
                ? 'authenticate_user_login retornó false. En token.php esto resulta en throw new moodle_exception(invalidlogin). El header CORS YA ESTÁ seteado en ese punto, pero la excepción hace que no se retorne token.'
                : 'Autenticación exitosa hasta este punto.',
        ];
    } catch (Throwable $e) {
        $out['authenticate_user_login'] = [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
        ];
    }
} else {
    $out['authenticate_user_login'] = 'No se probó — agrega &password=CONTRASEÑA para probar autenticación real';
}

// ── Diagnóstico final sobre el CORS error específico ─────────────────────────
$out['diagnostico_cors'] = [
    'hipotesis_principal' =>
        'El error CORS ocurre cuando config.php hace un REDIRECT HTTP (302) a /login/ ' .
        'ANTES de que token.php llegue a setear el header CORS en línea 33. ' .
        'Esto solo puede suceder si el browser envía una cookie MoodleSession activa ' .
        'con NO_MOODLE_COOKIES=false, y esa sesión está en un estado que fuerza re-login.',
    'condiciones_que_lo_causan' => [
        'Cookie MoodleSession en el browser para este usuario',
        'La sesión tiene: política pendiente, MFA incompleto, o flag de require_login',
        'El usuario accedió recientemente a lms.isi.edu.pa y tiene sesión abierta',
    ],
    'por_que_solo_este_usuario' =>
        'Otros usuarios también podrían tener este problema si abren lms.isi.edu.pa ' .
        'en el mismo browser. Es probable que este usuario haya iniciado sesión en Moodle ' .
        'directamente, generando la cookie que interfiere.',
    'solucion_inmediata' =>
        'El usuario debe cerrar sesión en lms.isi.edu.pa antes de intentar usar students.isi.edu.pa. ' .
        'O limpiar cookies del dominio lms.isi.edu.pa en el browser.',
    'solucion_definitiva' =>
        'Ver campo has_moodle_session_cookies arriba. La fix correcta en token.php depende de si ' .
        'la sesión es necesaria para set_user(). Revisar si cambiar NO_MOODLE_COOKIES rompe algo más.',
];

// ── Output ────────────────────────────────────────────────────────────────────
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
