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
