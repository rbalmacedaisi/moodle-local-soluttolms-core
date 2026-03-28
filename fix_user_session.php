<?php
// =============================================================================
// fix_user_session.php — Diagnóstico y corrección de sesiones de usuario
// ELIMINAR ESTE ARCHIVO DESPUÉS DE USARLO
// =============================================================================

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);

$username_input = trim($_POST['username'] ?? '');
$action         = $_POST['action'] ?? '';

$result = null;

if ($username_input !== '') {
    try {
        require_once('../../config.php');
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

                // Recargar datos después del fix
                $sessions = $DB->get_records('sessions', ['userid' => $user->id]);
                $user     = $DB->get_record('user', ['id' => $user->id], '*', IGNORE_MISSING);
                $problems = [];
                if (count($sessions) > 0) $problems[] = count($sessions) . ' sesión(es) activa(s) restantes';
                if (!$user->policyagreed)  $problems[] = 'policyagreed sigue en false';

                $fix_result = ['fixes' => $fixes, 'errors' => $errs];
            }

            $result = [
                'user'       => $user,
                'sessions'   => array_values($sessions),
                'problems'   => $problems,
                'fix_result' => $fix_result,
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

  </div>
<?php endif; ?>
<?php endif; ?>

<p class="delete-notice">⚠ Eliminar este archivo del servidor después de usarlo</p>

</body>
</html>
