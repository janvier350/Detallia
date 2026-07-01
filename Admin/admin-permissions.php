<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1]);

$success_msg = "";

$modules = [
    "dashboard"    => "Dashboard",
    "proveedores"  => "Proveedores",
    "articulos"    => "Articulos",
    "compras"      => "Compras",
    "marcas"       => "Marcas",
    "clientes"     => "Clientes",
    "kits"         => "Kits",
    "entregas"     => "Entregas",
    "devoluciones" => "Devoluciones",
    "inventario"   => "Inventario",
    "solicitudes"  => "Solicitudes",
];

$roles = mysqli_query($link, "SELECT id, name FROM roles WHERE id != 1 ORDER BY id");
$roleList = [];
while ($r = mysqli_fetch_assoc($roles)) {
    $roleList[] = $r;
}

// ---------------------------------------------------------------
// GUARDAR permisos
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $perm = $_POST["perm"] ?? [];

    mysqli_begin_transaction($link);
    try {
        $stmt = mysqli_prepare($link, "INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete)
                                        VALUES (?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create),
                                                                 can_edit = VALUES(can_edit), can_delete = VALUES(can_delete)");
        foreach ($roleList as $role) {
            $role_id = (int) $role["id"];
            foreach ($modules as $mod_key => $mod_label) {
                $can_view   = isset($perm[$role_id][$mod_key]["view"]) ? 1 : 0;
                $can_create = isset($perm[$role_id][$mod_key]["create"]) ? 1 : 0;
                $can_edit   = isset($perm[$role_id][$mod_key]["edit"]) ? 1 : 0;
                $can_delete = isset($perm[$role_id][$mod_key]["delete"]) ? 1 : 0;

                mysqli_stmt_bind_param($stmt, "isiiii", $role_id, $mod_key, $can_view, $can_create, $can_edit, $can_delete);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_error($link));
                }
            }
        }
        mysqli_commit($link);
        $success_msg = "Permisos actualizados correctamente.";
    } catch (Exception $e) {
        mysqli_rollback($link);
        $success_msg = "";
        $error_msg = "No se pudieron guardar los permisos: " . $e->getMessage();
    }
}

// ---------------------------------------------------------------
// Cargar permisos actuales
// ---------------------------------------------------------------
$currentPerms = [];
$res = mysqli_query($link, "SELECT role_id, module, can_view, can_create, can_edit, can_delete FROM role_permissions");
while ($row = mysqli_fetch_assoc($res)) {
    $currentPerms[(int) $row["role_id"]][$row["module"]] = $row;
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Permisos por rol | Detallia</title>
    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>

</head>

<?php include 'layouts/body.php'; ?>

<div id="layout-wrapper">

    <?php include 'layouts/menu.php'; ?>

    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Permisos por rol</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Permisos</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    El rol <strong>Administrador</strong> siempre tiene acceso total a todos los modulos y no aparece aqui.
                    El modulo <strong>Usuarios</strong> tampoco se gestiona desde esta tabla: solo el Administrador puede acceder a el.
                </div>

                <form method="post">
                    <?php foreach ($roleList as $role): ?>
                        <?php $role_id = (int) $role["id"]; ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3"><?php echo htmlspecialchars($role["name"]); ?></h5>
                                        <div class="table-responsive">
                                            <table class="table table-centered table-nowrap mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Modulo</th>
                                                        <th class="text-center">Ver</th>
                                                        <th class="text-center">Crear</th>
                                                        <th class="text-center">Editar</th>
                                                        <th class="text-center">Eliminar</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($modules as $mod_key => $mod_label): ?>
                                                        <?php $p = $currentPerms[$role_id][$mod_key] ?? ["can_view" => 0, "can_create" => 0, "can_edit" => 0, "can_delete" => 0]; ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($mod_label); ?></td>
                                                            <td class="text-center">
                                                                <input type="checkbox" class="form-check-input" name="perm[<?php echo $role_id; ?>][<?php echo $mod_key; ?>][view]" <?php echo $p["can_view"] ? "checked" : ""; ?>>
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="checkbox" class="form-check-input" name="perm[<?php echo $role_id; ?>][<?php echo $mod_key; ?>][create]" <?php echo $p["can_create"] ? "checked" : ""; ?>>
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="checkbox" class="form-check-input" name="perm[<?php echo $role_id; ?>][<?php echo $mod_key; ?>][edit]" <?php echo $p["can_edit"] ? "checked" : ""; ?>>
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="checkbox" class="form-check-input" name="perm[<?php echo $role_id; ?>][<?php echo $mod_key; ?>][delete]" <?php echo $p["can_delete"] ? "checked" : ""; ?>>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary">Guardar permisos</button>
                    </div>
                </form>

            </div>
        </div>

        <?php include 'layouts/footer.php'; ?>
    </div>
</div>

<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

</body>

</html>
