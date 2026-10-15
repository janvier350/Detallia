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
