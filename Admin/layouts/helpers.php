<?php
// Formatea una cantidad decimal quitando ceros y punto decimal innecesarios (1.00 -> 1, 1.50 -> 1.5)
function format_qty($value)
{
    $formatted = number_format((float) $value, 2, '.', '');
    if (strpos($formatted, '.') !== false) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    return $formatted === '' ? '0' : $formatted;
}

// Sistema de permisos por rol/modulo. El rol Administrador (id 1) siempre
// tiene acceso total, sin depender de la tabla role_permissions.
function can($module, $action = 'view')
{
    global $link;

    if (!isset($_SESSION['role_id'])) {
        return false;
    }

    $role_id = (int) $_SESSION['role_id'];
    if ($role_id === 1) {
        return true;
    }

    static $cache = [];
    $key = $role_id . ':' . $module;

    if (!isset($cache[$key])) {
        $stmt = mysqli_prepare($link, "SELECT can_view, can_create, can_edit, can_delete FROM role_permissions WHERE role_id = ? AND module = ?");
        mysqli_stmt_bind_param($stmt, "is", $role_id, $module);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $cache[$key] = $row ?: ['can_view' => 0, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0];
    }

    $col = 'can_' . $action;
    return !empty($cache[$key][$col]);
}

// Bloquea el acceso a la pagina si el rol actual no tiene permiso de vista sobre el modulo.
function require_module_view($module)
{
    if (!can($module, 'view')) {
        header('location: pages-403.php');
        exit;
    }
}

// ---------------------------------------------------------------
// Bitacora de actividad / auditoria
// ---------------------------------------------------------------
function ua_parse($ua)
{
    $ua = (string) $ua;
    $os = 'Otro'; $browser = 'Otro'; $device = 'Computadora';

    if (preg_match('/iPhone|iPad|iPod/i', $ua))        { $os = 'iOS'; $device = preg_match('/iPad/i', $ua) ? 'Tablet' : 'Telefono'; }
    elseif (preg_match('/Android/i', $ua))             { $os = 'Android'; $device = preg_match('/Mobile/i', $ua) ? 'Telefono' : 'Tablet'; }
    elseif (preg_match('/Windows NT 10/i', $ua))       { $os = 'Windows 10/11'; }
    elseif (preg_match('/Windows/i', $ua))             { $os = 'Windows'; }
    elseif (preg_match('/Mac OS X|Macintosh/i', $ua))  { $os = 'macOS'; }
    elseif (preg_match('/CrOS/i', $ua))                { $os = 'ChromeOS'; }
    elseif (preg_match('/Linux/i', $ua))               { $os = 'Linux'; }

    if (preg_match('/Edg/i', $ua))                     { $browser = 'Edge'; }
    elseif (preg_match('/OPR|Opera/i', $ua))           { $browser = 'Opera'; }
    elseif (preg_match('/Brave/i', $ua))               { $browser = 'Brave'; }
    elseif (preg_match('/SamsungBrowser/i', $ua))      { $browser = 'Samsung'; }
    elseif (preg_match('/Chrome/i', $ua))              { $browser = 'Chrome'; }
    elseif (preg_match('/Firefox/i', $ua))             { $browser = 'Firefox'; }
    elseif (preg_match('/Safari/i', $ua))              { $browser = 'Safari'; }

    return ['os' => $os, 'browser' => $browser, 'device' => $device];
}

function client_ip()
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            return substr($ip, 0, 45);
        }
    }
    return null;
}

// Registra un evento en la bitacora. Nunca interrumpe el flujo si falla.
function log_activity($link, $action, $module, $detail = null, $reference = null)
{
    if (!$link) return;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $p  = ua_parse($ua);
    $user_id   = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
    $username  = $_SESSION['username'] ?? null;
    $role_name = $_SESSION['role_name'] ?? null;
    $ip        = client_ip();
    $uaShort   = substr($ua, 0, 255);

    $stmt = mysqli_prepare($link, "INSERT INTO activity_log
        (user_id, username, role_name, module, reference, action, detail, ip, device, os, browser, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, "isssssssssss",
        $user_id, $username, $role_name, $module, $reference, $action, $detail, $ip, $p['device'], $p['os'], $p['browser'], $uaShort);
    @mysqli_stmt_execute($stmt);
}

// Construye un texto "campo: antes -> despues" para los campos que cambiaron.
function diff_detail($old, $new, $labels)
{
    $parts = [];
    foreach ($labels as $key => $label) {
        $o = trim((string) ($old[$key] ?? ''));
        $n = trim((string) ($new[$key] ?? ''));
        if ($o !== $n) {
            $parts[] = $label . ': "' . ($o === '' ? '(vacio)' : $o) . '" -> "' . ($n === '' ? '(vacio)' : $n) . '"';
        }
    }
    return implode('; ', $parts);
}

// =====================================================================
// Papelera / restaurar eliminados
// =====================================================================

// Toma una "foto" completa de un registro (fila padre + filas hijas)
// ANTES de eliminarlo. $children = [['table'=>'kit_items','fk'=>'kit_id'], ...]
// Devuelve un arreglo listo para guardar, o null si el registro no existe.
function snapshot_record($link, $table, $id, $children = [])
{
    $id = (int) $id;
    $res = mysqli_query($link, "SELECT * FROM `" . $table . "` WHERE id = " . $id);
    if (!$res) return null;
    $parent = mysqli_fetch_assoc($res);
    if (!$parent) return null;

    $kids = [];
    foreach ($children as $c) {
        $ct = $c['table'];
        $fk = $c['fk'];
        $cr = mysqli_query($link, "SELECT * FROM `" . $ct . "` WHERE `" . $fk . "` = " . $id);
        $rows = [];
        if ($cr) {
            while ($row = mysqli_fetch_assoc($cr)) {
                $rows[] = $row;
            }
        }
        $kids[$ct] = ['fk' => $fk, 'rows' => $rows];
    }

    return ['parent' => $parent, 'children' => $kids];
}

// Guarda la foto en la papelera (deleted_records). Se llama DESPUES de que
// el DELETE fue exitoso. Nunca interrumpe el flujo si algo falla.
function archive_snapshot($link, $module, $table, $id, $label, $snapshot)
{
    if (!$link || !$snapshot) return;
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
    if ($json === false) return;

    $by      = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
    $by_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? null);
    $id      = (int) $id;

    $stmt = mysqli_prepare($link, "INSERT INTO deleted_records
        (module, table_name, record_id, label, data, deleted_by, deleted_by_name)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, "ssissis", $module, $table, $id, $label, $json, $by, $by_name);
    @mysqli_stmt_execute($stmt);
}

// Construye la lista de columnas y placeholders para un INSERT a partir de una fila.
function _cols_placeholders($row)
{
    $cols = array_keys($row);
    $quoted = array_map(function ($c) { return "`" . $c . "`"; }, $cols);
    $ph = implode(', ', array_fill(0, count($cols), '?'));
    return [implode(', ', $quoted), $ph, array_values($row)];
}

// Inserta una fila (arreglo asociativo) en una tabla usando prepared statement.
function _insert_row($link, $table, $row)
{
    list($colsSql, $ph, $vals) = _cols_placeholders($row);
    $sql = "INSERT INTO `" . $table . "` (" . $colsSql . ") VALUES (" . $ph . ")";
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) return false;
    $types = str_repeat('s', count($vals));
    $bind = [];
    $bind[] = $types;
    foreach ($vals as $k => $v) {
        $bind[] = &$vals[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    return mysqli_stmt_execute($stmt);
}

// Restaura un registro archivado: reinserta la fila padre (con su id original)
// y sus filas hijas. Devuelve [ok(bool), mensaje(string)].
function restore_record($link, $archiveId)
{
    $archiveId = (int) $archiveId;
    $res = mysqli_query($link, "SELECT * FROM deleted_records WHERE id = " . $archiveId . " AND restored = 0");
    $arc = $res ? mysqli_fetch_assoc($res) : null;
    if (!$arc) return [false, "Registro no encontrado o ya restaurado."];

    $snap = json_decode($arc['data'], true);
    if (!$snap || empty($snap['parent'])) return [false, "No se pudo leer la copia guardada."];

    $table = $arc['table_name'];
    $recId = (int) $arc['record_id'];

    // Si ya existe un registro con ese id, no reinsertamos el padre.
    $exists = mysqli_query($link, "SELECT 1 FROM `" . $table . "` WHERE id = " . $recId);
    $already = $exists && mysqli_fetch_row($exists);

    mysqli_begin_transaction($link);
    try {
        if (!$already) {
            if (!_insert_row($link, $table, $snap['parent'])) {
                throw new Exception("No se pudo restaurar el registro principal: " . mysqli_error($link));
            }
        }
        foreach (($snap['children'] ?? []) as $ctable => $info) {
            foreach (($info['rows'] ?? []) as $crow) {
                _insert_row($link, $ctable, $crow);
            }
        }
        $by_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? null);
        $u = mysqli_prepare($link, "UPDATE deleted_records SET restored = 1, restored_at = NOW(), restored_by_name = ? WHERE id = ?");
        mysqli_stmt_bind_param($u, "si", $by_name, $archiveId);
        mysqli_stmt_execute($u);
        mysqli_commit($link);
    } catch (Exception $e) {
        mysqli_rollback($link);
        return [false, $e->getMessage()];
    }

    return [true, $already ? "El registro ya existia; se restauraron sus datos relacionados." : "Registro restaurado correctamente."];
}
