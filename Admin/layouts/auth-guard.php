<?php
// Restricts a page to one or more role IDs.
// Usage: require_once 'layouts/session.php'; require_once 'layouts/auth-guard.php'; require_role([1]);
function require_role(array $allowedRoleIds)
{
    if (!isset($_SESSION['role_id']) || !in_array((int) $_SESSION['role_id'], $allowedRoleIds, true)) {
        header('location: pages-403.php');
        exit;
    }
}
