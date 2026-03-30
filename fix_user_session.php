<?php
// =============================================================================
// fix_user_session.php — Diagnóstico y corrección de sesiones de usuario
// ELIMINAR ESTE ARCHIVO DESPUÉS DE USARLO
// =============================================================================

define('NO_MOODLE_COOKIES', true);

$username_input = trim($_POST['username'] ?? '');
$action         = $_POST['action'] ?? '';

$result = null;

if ($username_input !== '') {
    try {
        require_once('../../config.php');
        require_once($CFG->libdir . '/externallib.php');
    } catch (Throwable $e) {
        $result = ['error' => 'config.php falló: ' . htmlspecialchars($e->getMessage())];
    }

    if (!isset($DB)) {
        $result = ['error' => 'Moodle no cargó correctamente.'];
    }

    if ($result === null) {
        $username_clean = trim(core_text::strtolower($username_input));
        $user = $DB->get_record('user', ['username' => $username_clean], '*', IGNORE_MISSING);

        if (!$user) {
            $result = ['error' => "Usuario <strong>$username_clean</strong> no encontrado en Moodle."];
        } else {
            $sessions = $DB->get_records('sessions', ['userid' => $user->id], 'timemodified DESC');

            $problems = [];
            if (count($sessions) > 0) {
                $problems[] = count($sessions) . ' sesión(es) activa(s) en mdl_sessions (causa Content-Length: 0 en token.php)';
            }
            if (!$user->policyagreed) {
                $problems[] = 'policyagreed = false';
            }
            if (empty($user->confirmed)) {
                $problems[] = 'confirmed = 0 — token.php lanza moodle_exception("usernotconfirmed") y el login falla';
            }

            $fix_result = null;
            if ($action === 'fix') {
                $fixes = [];
                $errs  = [];

                try {
                    $n = count($sessions);
                    $DB->delete_records('sessions', ['userid' => $user->id]);
                    $fixes[] = "✓ Eliminadas $n sesión(es) de mdl_sessions";
                } catch (Throwable $e) {
                    $errs[] = 'Error eliminando sesiones: ' . $e->getMessage();
                }

                try {
                    $DB->set_field('user', 'policyagreed', 1, ['id' => $user->id]);
                    $fixes[] = '✓ policyagreed establecido a 1';
                } catch (Throwable $e) {
                    $errs[] = 'Error actualizando policyagreed: ' . $e->getMessage();
                }

                try {
                    $DB->set_field('user', 'confirmed', 1, ['id' => $user->id]);
                    $fixes[] = '✓ confirmed establecido a 1';
                } catch (Throwable $e) {
                    $errs[] = 'Error actualizando confirmed: ' . $e->getMessage();
                }

                // Recargar datos después del fix
                $sessions = $DB->get_records('sessions', ['userid' => $user->id]);
                $user     = $DB->get_record('user', ['id' => $user->id], '*', IGNORE_MISSING);
                $problems = [];
                if (count($sessions) > 0) $problems[] = count($sessions) . ' sesión(es) activa(s) restantes';
                if (!$user->policyagreed)  $problems[] = 'policyagreed sigue en false';
                if (empty($user->confirmed)) $problems[] = 'confirmed sigue en 0';

                // $fix_result se construye al final, después de todos los fixes (capability, etc.)
            }

            // ── Diagnóstico extendido ─────────────────────────────────────────────
            // 1. Campo personalizado studentstatus
            $sf_field = $DB->get_record('user_info_field', ['shortname' => 'studentstatus'], 'id,name,shortname', IGNORE_MISSING);
            $sf_data  = null;
            if ($sf_field) {
                $sf_data = $DB->get_record_sql(
                    "SELECT d.data FROM {user_info_data} d WHERE d.fieldid = ? AND d.userid = ?",
                    [$sf_field->id, $user->id], IGNORE_MISSING
                );
            }
            $studentstatus_diag = [
                'field_exists' => !empty($sf_field),
                'has_value'    => !empty($sf_data),
                'value'        => $sf_data ? $sf_data->data : null,
            ];
            if (!$sf_field) {
                $problems[] = 'Campo personalizado "studentstatus" no existe en el sistema';
            } elseif (!$sf_data || empty($sf_data->data)) {
                $problems[] = 'Usuario sin valor en el campo "studentstatus" — token.php lanza TypeError en PHP 8 al leer ->data sobre false';
            }

            // 2. External tokens
            $ext_tokens = $DB->get_records_sql(
                "SELECT et.id, et.token, et.creatorid, et.timecreated, et.validuntil, et.iprestriction,
                        es.shortname AS servicename
                   FROM {external_tokens} et
                   JOIN {external_services} es ON es.id = et.externalserviceid
                  WHERE et.userid = ?
               ORDER BY et.timecreated DESC",
                [$user->id]
            );
            if (count($ext_tokens) > 0) {
                $now = time();
                foreach ($ext_tokens as $tk) {
                    if ($tk->validuntil > 0 && $tk->validuntil < $now) {
                        $problems[] = 'Token expirado para servicio "' . $tk->servicename . '" (id=' . $tk->id . ')';
                    }
                }
            }

            // 3. Servicio moodle_mobile_app — acceso
            $svc = $DB->get_record('external_services', ['shortname' => 'moodle_mobile_app', 'enabled' => 1], 'id,name,restrictedusers,enabled', IGNORE_MISSING);
            $svc_access = null;
            if ($svc) {
                if ($svc->restrictedusers) {
                    $in_list = $DB->record_exists('external_services_users', ['externalserviceid' => $svc->id, 'userid' => $user->id]);
                    $svc_access = $in_list ? 'autorizado (en lista)' : 'DENEGADO — servicio restringido y usuario no está en la lista';
                    if (!$in_list) {
                        $problems[] = 'Servicio "moodle_mobile_app" restringido a usuarios específicos y este usuario no está en la lista';
                    }
                } else {
                    $svc_access = 'abierto a todos los usuarios';
                }
            } else {
                $svc_access = 'Servicio "moodle_mobile_app" no encontrado o deshabilitado';
                $problems[] = $svc_access;
            }

            // 4. Roles asignados
            $roles = $DB->get_records_sql(
                "SELECT ra.id, r.shortname, r.name, ctx.contextlevel, ctx.instanceid
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid
                   JOIN {context} ctx ON ctx.id = ra.contextid
                  WHERE ra.userid = ?
               ORDER BY ctx.contextlevel, r.shortname",
                [$user->id]
            );

            // 5. Auth plugin
            $auth_plugin_ok = in_array($user->auth, get_enabled_auth_plugins());
            if (!$auth_plugin_ok) {
                $problems[] = 'Plugin de autenticación "' . $user->auth . '" no está habilitado';
            }

            // 6. Capabilities críticas para web service token
            $sysctx = context_system::instance();
            $cap_createtoken = has_capability('moodle/webservice:createtoken', $sysctx, $user->id, false);
            $cap_rest        = has_capability('webservice/rest:use',           $sysctx, $user->id, false);
            if (!$cap_createtoken) {
                $problems[] = 'Capability "moodle/webservice:createtoken" DENEGADA — external_generate_token_for_current_user lanzará excepción';
            }
            if (!$cap_rest) {
                $problems[] = 'Capability "webservice/rest:use" DENEGADA — no puede usar el servicio REST';
            }

            // Qué roles tienen esta capability (ALLOW=1, PROHIBIT=-1000, ausente=vacío)
            // NOTA: no hardcodear 'moodle/webservice:createtoken' en el SQL — el parser DML de
            // Moodle interpreta ':createtoken' como parámetro nombrado y lanza mixedtypesqlparam.
            $cap_roles_analysis = $DB->get_records_sql(
                "SELECT rc.roleid, r.shortname, r.name, rc.permission
                   FROM {role_capabilities} rc
                   JOIN {role} r ON r.id = rc.roleid
                  WHERE rc.capability = ?
                    AND rc.contextid  = ?
               ORDER BY rc.permission",
                ['moodle/webservice:createtoken', $sysctx->id]
            );
            // Roles del usuario en contexto sistema
            $user_sysroles = $DB->get_records_sql(
                "SELECT ra.roleid, r.shortname, r.name
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid
                  WHERE ra.userid = ? AND ra.contextid = ?",
                [$user->id, $sysctx->id]
            );

            // 7. Corrección de capability en acción fix
            if ($action === 'fix' && !$cap_createtoken) {
                // Usar assign_capability() de Moodle — maneja caché automáticamente
                foreach ($user_sysroles as $sr) {
                    try {
                        assign_capability('moodle/webservice:createtoken', CAP_ALLOW, $sr->roleid, $sysctx->id, true);
                        $fixes[] = '✓ moodle/webservice:createtoken = ALLOW asignado al rol "' . $sr->shortname . '" en contexto sistema';
                    } catch (Throwable $e) {
                        $errs[] = 'Error asignando capability al rol "' . $sr->shortname . '": ' . $e->getMessage();
                    }
                }
                // Si el usuario no tiene ningún rol de sistema, asignarlo al "authenticated user" role
                if (empty($user_sysroles)) {
                    try {
                        $auth_role = $DB->get_record('role', ['shortname' => 'user'], 'id,shortname', IGNORE_MISSING);
                        if ($auth_role) {
                            assign_capability('moodle/webservice:createtoken', CAP_ALLOW, $auth_role->id, $sysctx->id, true);
                            $fixes[] = '✓ moodle/webservice:createtoken = ALLOW asignado al rol "user" (authenticated user)';
                        }
                    } catch (Throwable $e) {
                        $errs[] = 'Error asignando capability a authenticated user: ' . $e->getMessage();
                    }
                }
                // Refrescar valores
                $cap_createtoken = has_capability('moodle/webservice:createtoken', $sysctx, $user->id, false);
                if (!$cap_createtoken) $problems[] = 'moodle/webservice:createtoken sigue denegada';
            }

            // 8. Simular token generation (solo si la capability está OK y la función existe)
            $token_test = null;
            if ($cap_createtoken) {
                // Intentar cargar externallib si aún no está disponible
                if (!function_exists('external_generate_token_for_current_user') && isset($CFG)) {
                    @include_once($CFG->libdir . '/externallib.php');
                }
                if (!function_exists('external_generate_token_for_current_user')) {
                    $token_test = ['ok' => null, 'error' => 'Función external_generate_token_for_current_user no disponible en este contexto (externallib.php requiere sesión activa). La capability está OK — el login debería funcionar.'];
                } else {
                    try {
                        $svc_test = $DB->get_record('external_services', ['shortname' => 'moodle_mobile_app', 'enabled' => 1], '*', IGNORE_MISSING);
                        if ($svc_test) {
                            \core\session\manager::set_user($user);
                            $tok = external_generate_token_for_current_user($svc_test);
                            $token_test = ['ok' => true, 'token_id' => $tok->id, 'token' => substr($tok->token, 0, 8) . '...'];
                        } else {
                            $token_test = ['ok' => false, 'error' => 'Servicio moodle_mobile_app no encontrado o deshabilitado'];
                        }
                    } catch (Throwable $e) {
                        $token_test = ['ok' => false, 'error' => get_class($e) . ': ' . $e->getMessage()];
                        $problems[] = 'Generación de token falló: ' . $e->getMessage();
                    }
                }
            } else {
                $token_test = ['ok' => false, 'error' => 'No ejecutada — capability moodle/webservice:createtoken denegada. Aplica el fix primero.'];
            }

            // ── Acción fix: limpiar tokens vencidos + setear studentstatus ────────
            if ($action === 'fix') {
                // Limpiar tokens expirados
                try {
                    $now = time();
                    $del = $DB->execute(
                        "DELETE FROM {external_tokens} WHERE userid = ? AND validuntil > 0 AND validuntil < ?",
                        [$user->id, $now]
                    );
                    $fixes[] = '✓ Tokens expirados eliminados';
                } catch (Throwable $e) {
                    $errs[] = 'Error limpiando tokens: ' . $e->getMessage();
                }
                // Crear entrada studentstatus si no existe
                if ($sf_field && !$sf_data) {
                    try {
                        $rec = new stdClass();
                        $rec->userid   = $user->id;
                        $rec->fieldid  = $sf_field->id;
                        $rec->data     = 'Activo';
                        $rec->dataformat = 0;
                        $DB->insert_record('user_info_data', $rec);
                        $fixes[] = '✓ Campo "studentstatus" = "Activo" creado para este usuario';
                    } catch (Throwable $e) {
                        $errs[] = 'Error creando studentstatus: ' . $e->getMessage();
                    }
                }
                // Recargar
                $ext_tokens = $DB->get_records_sql(
                    "SELECT et.id, et.token, et.creatorid, et.timecreated, et.validuntil, et.iprestriction,
                            es.shortname AS servicename
                       FROM {external_tokens} et
                       JOIN {external_services} es ON es.id = et.externalserviceid
                      WHERE et.userid = ?
                   ORDER BY et.timecreated DESC",
                    [$user->id]
                );
                $sf_data = $sf_field
                    ? $DB->get_record_sql(
                        "SELECT d.data FROM {user_info_data} d WHERE d.fieldid = ? AND d.userid = ?",
                        [$sf_field->id, $user->id], IGNORE_MISSING
                      )
                    : null;
                // Re-evaluar problems
                $problems = [];
                if (count($sessions) > 0) $problems[] = count($sessions) . ' sesión(es) activa(s) restantes';
                if (!$user->policyagreed)  $problems[] = 'policyagreed sigue en false';
                if ($sf_field && (!$sf_data || empty($sf_data->data))) {
                    $problems[] = 'Campo "studentstatus" aún sin valor';
                }
            }

            // fix_result se construye aquí para capturar TODOS los fixes (capability incluida)
            if (isset($fixes)) {
                $fix_result = ['fixes' => $fixes, 'errors' => $errs ?? []];
            }

            $result = [
                'user'              => $user,
                'sessions'          => array_values($sessions),
                'problems'          => $problems,
                'fix_result'        => $fix_result,
                'studentstatus'     => $studentstatus_diag,
                'ext_tokens'        => array_values($ext_tokens),
                'svc_access'        => $svc_access,
                'roles'             => array_values($roles),
                'auth_plugin_ok'    => $auth_plugin_ok,
                'cap_createtoken'      => $cap_createtoken,
                'cap_rest'             => $cap_rest,
                'cap_roles_analysis'   => array_values($cap_roles_analysis),
                'user_sysroles'        => array_values($user_sysroles),
                'token_test'           => $token_test,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fix User Session — ISI LMS</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #f4f6f9; color: #333; padding: 32px 16px; }
  .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); padding: 28px 32px; max-width: 680px; margin: 0 auto 24px; }
  h1 { font-size: 1.3rem; margin-bottom: 4px; }
  .subtitle { font-size: .85rem; color: #888; margin-bottom: 24px; }
  label { font-size: .875rem; font-weight: 600; display: block; margin-bottom: 6px; }
  input[type=text] { width: 100%; padding: 10px 14px; border: 1px solid #ccd; border-radius: 6px; font-size: 1rem; }
  input[type=text]:focus { outline: none; border-color: #4f8ef7; box-shadow: 0 0 0 3px rgba(79,142,247,.15); }
  .btn { display: inline-block; padding: 10px 22px; border: none; border-radius: 6px; font-size: .95rem; font-weight: 600; cursor: pointer; }
  .btn-primary { background: #4f8ef7; color: #fff; }
  .btn-primary:hover { background: #3a7ce0; }
  .btn-danger  { background: #e74c3c; color: #fff; }
  .btn-danger:hover  { background: #c0392b; }
  .row { display: flex; gap: 12px; margin-top: 14px; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: .78rem; font-weight: 600; }
  .badge-ok  { background: #d4edda; color: #155724; }
  .badge-err { background: #f8d7da; color: #721c24; }
  .badge-warn{ background: #fff3cd; color: #856404; }
  .section { margin-top: 20px; }
  .section-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; font-size: .85rem; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; }
  th { background: #f8f9fb; font-weight: 600; }
  .field { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: .88rem; }
  .field:last-child { border-bottom: none; }
  .field-label { color: #666; }
  .fix-box { background: #f0fff4; border: 1px solid #b7ebc4; border-radius: 6px; padding: 14px 18px; margin-top: 16px; }
  .fix-box.has-errors { background: #fff5f5; border-color: #fcc; }
  .fix-item { padding: 4px 0; font-size: .88rem; }
  .warn-box { background: #fff8e1; border: 1px solid #ffe082; border-radius: 6px; padding: 12px 16px; margin-top: 16px; font-size: .88rem; }
  .error-box { background: #fff0f0; border: 1px solid #ffb3b3; border-radius: 6px; padding: 12px 16px; margin-top: 16px; font-size: .88rem; }
  .delete-notice { font-size: .8rem; color: #c0392b; font-weight: 600; text-align: center; margin-top: 12px; }
</style>
</head>
<body>

<div class="card">
  <h1>Diagnóstico de Sesión de Usuario</h1>
  <p class="subtitle">Identifica y corrige problemas de sesión que causan <code>Content-Length: 0</code> en token.php</p>

  <form method="POST">
    <label for="username">Nombre de usuario (cédula)</label>
    <input type="text" id="username" name="username"
           placeholder="Ej: 8-902-889"
           value="<?= htmlspecialchars($username_input) ?>"
           autocomplete="off" autofocus>
    <div class="row">
      <button type="submit" class="btn btn-primary" name="action" value="diagnose">Diagnosticar</button>
      <?php if ($result && isset($result['problems']) && count($result['problems']) > 0): ?>
        <button type="submit" class="btn btn-danger" name="action" value="fix"
                onclick="return confirm('¿Aplicar correcciones para <?= htmlspecialchars($username_input) ?>?')">
          Corregir
        </button>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if ($result !== null): ?>

<?php if (isset($result['error'])): ?>
  <div class="card">
    <div class="error-box"><?= $result['error'] ?></div>
  </div>

<?php else:
  $u  = $result['user'];
  $s  = $result['sessions'];
  $p  = $result['problems'];
  $fr = $result['fix_result'];
?>
  <div class="card">

    <?php if ($fr !== null): ?>
      <div class="fix-box <?= !empty($fr['errors']) ? 'has-errors' : '' ?>">
        <strong><?= !empty($fr['errors']) ? '⚠ Correcciones parciales' : '✅ Correcciones aplicadas' ?></strong>
        <?php foreach ($fr['fixes'] as $f): ?>
          <div class="fix-item"><?= htmlspecialchars($f) ?></div>
        <?php endforeach; ?>
        <?php foreach ($fr['errors'] as $e): ?>
          <div class="fix-item" style="color:#c0392b"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="section">
      <div class="section-title">Usuario</div>
      <div class="field"><span class="field-label">ID</span><span><?= (int)$u->id ?></span></div>
      <div class="field"><span class="field-label">Username</span><span><?= htmlspecialchars($u->username) ?></span></div>
      <div class="field"><span class="field-label">Nombre</span><span><?= htmlspecialchars($u->firstname . ' ' . $u->lastname) ?></span></div>
      <div class="field"><span class="field-label">policyagreed</span>
        <span class="badge <?= $u->policyagreed ? 'badge-ok' : 'badge-warn' ?>">
          <?= $u->policyagreed ? 'true' : 'false' ?>
        </span>
      </div>
      <div class="field"><span class="field-label">confirmed</span>
        <span class="badge <?= $u->confirmed ? 'badge-ok' : 'badge-err' ?>">
          <?= $u->confirmed ? 'true' : 'false — token.php lanzará usernotconfirmed' ?>
        </span>
      </div>
      <div class="field"><span class="field-label">deleted / suspended</span>
        <span class="badge <?= ($u->deleted || $u->suspended) ? 'badge-err' : 'badge-ok' ?>">
          <?= $u->deleted ? 'deleted' : ($u->suspended ? 'suspended' : 'activo') ?>
        </span>
      </div>
      <div class="field"><span class="field-label">Último login</span>
        <span><?= $u->lastlogin ? date('Y-m-d H:i:s', $u->lastlogin) : '<em style="color:#aaa">nunca</em>' ?></span>
      </div>
    </div>

    <div class="section">
      <div class="section-title">
        Sesiones en BD
        <span class="badge <?= count($s) > 0 ? 'badge-err' : 'badge-ok' ?>" style="margin-left:8px">
          <?= count($s) ?>
        </span>
      </div>
      <?php if (count($s) > 0): ?>
        <table>
          <tr><th>Session ID</th><th>Creada</th><th>Última actividad</th><th>IP</th></tr>
          <?php foreach ($s as $sess): ?>
            <tr>
              <td><?= htmlspecialchars(substr($sess->sid, 0, 12)) ?>...</td>
              <td><?= date('Y-m-d H:i', $sess->timecreated) ?></td>
              <td><?= date('Y-m-d H:i', $sess->timemodified) ?></td>
              <td><?= htmlspecialchars($sess->lastip) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <p style="font-size:.88rem;color:#555">Sin sesiones activas.</p>
      <?php endif; ?>
    </div>

    <div class="section">
      <div class="section-title">Diagnóstico</div>
      <?php if (count($p) === 0): ?>
        <div class="fix-box"><strong>✅ Sin problemas detectados.</strong>
          <?php if ($fr !== null): ?>
            El login debería funcionar correctamente ahora.
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="warn-box">
          <?php foreach ($p as $prob): ?>
            <div>⚠ <?= htmlspecialchars($prob) ?></div>
          <?php endforeach; ?>
          <?php if ($fr === null): ?>
            <div style="margin-top:8px">Presiona <strong>Corregir</strong> para aplicar el fix automáticamente.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Diagnóstico extendido ──────────────────────────────────────────── -->

    <div class="section">
      <div class="section-title">Auth &amp; Plugin</div>
      <div class="field">
        <span class="field-label">Plugin de autenticación</span>
        <span>
          <?= htmlspecialchars($u->auth) ?>
          <span class="badge <?= $result['auth_plugin_ok'] ? 'badge-ok' : 'badge-err' ?>" style="margin-left:6px">
            <?= $result['auth_plugin_ok'] ? 'habilitado' : 'DESHABILITADO' ?>
          </span>
        </span>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Campo personalizado <code>studentstatus</code></div>
      <?php $ss = $result['studentstatus']; ?>
      <div class="field"><span class="field-label">Campo existe en el sistema</span>
        <span class="badge <?= $ss['field_exists'] ? 'badge-ok' : 'badge-err' ?>">
          <?= $ss['field_exists'] ? 'sí' : 'NO — falta en user_info_field' ?>
        </span>
      </div>
      <div class="field"><span class="field-label">Valor para este usuario</span>
        <span class="badge <?= $ss['has_value'] ? 'badge-ok' : 'badge-warn' ?>">
          <?= $ss['has_value'] ? htmlspecialchars($ss['value']) : 'SIN VALOR — causa TypeError en token.php (PHP 8)' ?>
        </span>
      </div>
      <?php if (!$ss['has_value']): ?>
        <p style="font-size:.82rem;color:#856404;margin-top:6px">
          ⚠ <strong>Causa raíz probable del error de login:</strong> <code>token.php</code> lee
          <code>$user_info_data->data</code> sin verificar que el objeto exista.
          En PHP 8 lanza <em>TypeError</em>, Moodle responde con JSON de excepción (sin <code>usertoken</code>),
          y el store JS falla con <em>Cannot read properties of undefined (reading 'token')</em>.
        </p>
      <?php endif; ?>
    </div>

    <div class="section">
      <div class="section-title">Servicio Web (<code>moodle_mobile_app</code>)</div>
      <div class="field">
        <span class="field-label">Acceso</span>
        <span class="badge <?= (strpos($result['svc_access'], 'DENEGADO') === 0 || strpos($result['svc_access'], 'Servicio') === 0) ? 'badge-err' : 'badge-ok' ?>">
          <?= htmlspecialchars($result['svc_access']) ?>
        </span>
      </div>
    </div>

    <div class="section">
      <div class="section-title">
        Tokens externos (<code>external_tokens</code>)
        <span class="badge badge-ok" style="margin-left:8px"><?= count($result['ext_tokens']) ?></span>
      </div>
      <?php if (count($result['ext_tokens']) > 0): ?>
        <table>
          <tr><th>ID</th><th>Servicio</th><th>Creado</th><th>Válido hasta</th><th>IP</th></tr>
          <?php foreach ($result['ext_tokens'] as $tk):
            $expired = $tk->validuntil > 0 && $tk->validuntil < time();
          ?>
            <tr <?= $expired ? 'style="background:#fff0f0"' : '' ?>>
              <td><?= (int)$tk->id ?></td>
              <td><?= htmlspecialchars($tk->servicename) ?></td>
              <td><?= date('Y-m-d H:i', $tk->timecreated) ?></td>
              <td><?= $tk->validuntil ? date('Y-m-d H:i', $tk->validuntil) . ($expired ? ' <strong style="color:#c0392b">[EXPIRADO]</strong>' : '') : '<em style="color:#aaa">sin límite</em>' ?></td>
              <td><?= htmlspecialchars($tk->iprestriction ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <p style="font-size:.88rem;color:#555">Sin tokens generados.</p>
      <?php endif; ?>
    </div>

    <div class="section">
      <div class="section-title">
        Roles asignados
        <span class="badge badge-ok" style="margin-left:8px"><?= count($result['roles']) ?></span>
      </div>
      <?php if (count($result['roles']) > 0): ?>
        <table>
          <tr><th>Rol (shortname)</th><th>Nombre</th><th>Nivel contexto</th><th>instanceid</th></tr>
          <?php
          $ctx_levels = [10=>'Sistema',30=>'User',40=>'Course cat.',50=>'Course',70=>'Module',80=>'Block'];
          foreach ($result['roles'] as $r): ?>
            <tr>
              <td><code><?= htmlspecialchars($r->shortname) ?></code></td>
              <td><?= htmlspecialchars($r->name) ?></td>
              <td><?= $ctx_levels[$r->contextlevel] ?? $r->contextlevel ?></td>
              <td><?= (int)$r->instanceid ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <p style="font-size:.88rem;color:#c0392b;font-weight:600">Sin roles asignados — el campo <code>manager</code> será false en token.php.</p>
      <?php endif; ?>
    </div>

    <div class="section">
      <div class="section-title">Capabilities para Web Service</div>
      <div class="field">
        <span class="field-label"><code>moodle/webservice:createtoken</code></span>
        <span class="badge <?= $result['cap_createtoken'] ? 'badge-ok' : 'badge-err' ?>">
          <?= $result['cap_createtoken'] ? 'PERMITIDA' : 'DENEGADA — causa excepción en token.php' ?>
        </span>
      </div>
      <div class="field">
        <span class="field-label"><code>webservice/rest:use</code></span>
        <span class="badge <?= $result['cap_rest'] ? 'badge-ok' : 'badge-err' ?>">
          <?= $result['cap_rest'] ? 'PERMITIDA' : 'DENEGADA' ?>
        </span>
      </div>

      <?php if (!empty($result['cap_roles_analysis'])): ?>
      <div style="margin-top:10px">
        <div style="font-size:.78rem;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:6px">
          Roles con <code>moodle/webservice:createtoken</code> explícito en contexto sistema
        </div>
        <table>
          <tr><th>Rol (shortname)</th><th>Nombre</th><th>Permiso</th></tr>
          <?php foreach ($result['cap_roles_analysis'] as $cr): ?>
          <tr>
            <td><code><?= htmlspecialchars($cr->shortname) ?></code></td>
            <td><?= htmlspecialchars($cr->name) ?></td>
            <td>
              <?php if ($cr->permission == 1): ?>
                <span class="badge badge-ok">ALLOW (1)</span>
              <?php elseif ($cr->permission <= -1000): ?>
                <span class="badge badge-err">PROHIBIT (-1000) — bloquea el acceso aunque otro rol lo permita</span>
              <?php else: ?>
                <span class="badge badge-warn">PREVENT (<?= $cr->permission ?>)</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php else: ?>
      <p style="font-size:.82rem;color:#856404;margin-top:8px">
        ⚠ Ningún rol tiene esta capability definida explícitamente en el contexto sistema —
        el valor efectivo viene del arquetipo base del rol o está vacío (equivale a denegado si
        el arquetipo no la incluye).
      </p>
      <?php endif; ?>

      <?php if (!empty($result['user_sysroles'])): ?>
      <div style="margin-top:8px;font-size:.82rem;color:#555">
        Roles del usuario en contexto sistema:
        <?php foreach ($result['user_sysroles'] as $sr): ?>
          <code style="background:#f0f2f5;padding:1px 6px;border-radius:4px"><?= htmlspecialchars($sr->shortname) ?></code>
        <?php endforeach; ?>
        — El botón <strong>Corregir</strong> asignará ALLOW a estos roles.
      </div>
      <?php endif; ?>
    </div>

    <div class="section">
      <div class="section-title">Prueba de generación de token</div>
      <?php $tt = $result['token_test']; ?>
      <?php if ($tt === null): ?>
        <p style="font-size:.88rem;color:#888">No ejecutada.</p>
      <?php elseif ($tt['ok'] === true): ?>
        <div class="fix-box">
          <strong>✅ Token generado correctamente</strong>
          <div class="fix-item">ID: <?= (int)$tt['token_id'] ?> — Token: <code><?= htmlspecialchars($tt['token']) ?></code></div>
          <div class="fix-item" style="color:#555;font-size:.82rem">Si este paso pasa pero el login en la LXP sigue fallando, el problema está en el cliente (localStorage corrupto o redirección a Moodle falla).</div>
        </div>
      <?php elseif ($tt['ok'] === null): ?>
        <div style="background:#e8f4fd;border-left:4px solid #1a73e8;padding:10px 14px;border-radius:4px;font-size:.82rem">
          ℹ <?= htmlspecialchars($tt['error']) ?>
        </div>
      <?php else: ?>
        <div class="warn-box">
          <strong>⚠ Falló la generación de token</strong>
          <div class="fix-item" style="color:#721c24;margin-top:6px"><code><?= htmlspecialchars($tt['error']) ?></code></div>
          <div class="fix-item" style="margin-top:6px;font-size:.82rem">Este es el error exacto que ve el cliente al intentar autenticar.</div>
        </div>
      <?php endif; ?>
    </div>

  </div>
<?php endif; ?>
<?php endif; ?>

<p class="delete-notice">⚠ Eliminar este archivo del servidor después de usarlo</p>

</body>
</html>
