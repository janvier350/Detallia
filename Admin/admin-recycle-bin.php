<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2]); // Solo Administrador y Jefe

$success_msg = "";
$error_msg   = "";

// ---------------------------------------------------------------
// Restaurar un registro eliminado
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "restore") {
    $arcId = (int) ($_POST["id"] ?? 0);
    if ($arcId > 0) {
        list($ok, $msg) = restore_record($link, $arcId);
        if ($ok) {
            $_SESSION["flash_success"] = $msg;
            log_activity($link, "restauro", "Papelera", "Restauro registro de papelera #" . $arcId);
        } else {
            $_SESSION["flash_error"] = $msg;
        }
    }
    header("location: admin-recycle-bin.php");
    exit;
}

// ---------------------------------------------------------------
// Eliminar definitivamente (vaciar de la papelera)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "purge") {
    $arcId = (int) ($_POST["id"] ?? 0);
    if ($arcId > 0) {
        $stmt = mysqli_prepare($link, "DELETE FROM deleted_records WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $arcId);
        mysqli_stmt_execute($stmt);
        $_SESSION["flash_success"] = "Registro eliminado definitivamente de la papelera.";
        log_activity($link, "elimino", "Papelera", "Elimino definitivamente registro de papelera #" . $arcId);
    }
    header("location: admin-recycle-bin.php");
    exit;
}

if (isset($_SESSION["flash_success"])) {
    $success_msg = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
}
if (isset($_SESSION["flash_error"])) {
    $error_msg = $_SESSION["flash_error"];
    unset($_SESSION["flash_error"]);
}

// ---------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------
$filter_module = trim($_GET["module"] ?? "");
$show_restored = isset($_GET["restored"]) && $_GET["restored"] === "1";

$where = [];
if (!$show_restored) {
    $where[] = "restored = 0";
}
if ($filter_module !== "") {
    $where[] = "module = '" . mysqli_real_escape_string($link, $filter_module) . "'";
}
$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

$rows = mysqli_query($link, "SELECT id, module, table_name, record_id, label, deleted_by_name,
                                    deleted_at, restored, restored_at, restored_by_name
                             FROM deleted_records" . $whereSql . "
                             ORDER BY deleted_at DESC");

// Lista de modulos para el filtro
$modules = mysqli_query($link, "SELECT DISTINCT module FROM deleted_records ORDER BY module");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Papelera | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Papelera</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Papelera</li>
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
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">

                                <div class="alert alert-info" role="alert">
                                    <i class="mdi mdi-information-outline me-1"></i>
                                    Aqui aparecen los registros eliminados de <strong>Clientes, Articulos, Marcas,
                                    Proveedores, Kits y Simulador de kits</strong>. Puedes restaurarlos o eliminarlos
                                    definitivamente. Las imagenes de articulos eliminados deberan subirse de nuevo.
                                </div>

                                <form method="get" class="row g-2 align-items-end mb-3">
                                    <div class="col-sm-4">
                                        <label class="form-label mb-1">Modulo</label>
                                        <select name="module" class="form-select">
                                            <option value="">Todos</option>
                                            <?php while ($m = mysqli_fetch_assoc($modules)): ?>
                                                <option value="<?php echo htmlspecialchars($m["module"]); ?>" <?php echo $filter_module === $m["module"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($m["module"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="showRestored" name="restored" value="1" <?php echo $show_restored ? "checked" : ""; ?> onchange="this.form.submit()">
                                            <label class="form-check-label" for="showRestored">Mostrar tambien los ya restaurados</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-4 text-sm-end">
                                        <button type="submit" class="btn btn-primary"><i class="mdi mdi-filter-variant me-1"></i>Filtrar</button>
                                        <a href="admin-recycle-bin.php" class="btn btn-soft-secondary">Limpiar</a>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Modulo</th>
                                                <th>Registro</th>
                                                <th>Eliminado por</th>
                                                <th>Fecha</th>
                                                <th>Estado</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($rows && mysqli_num_rows($rows) > 0): ?>
                                                <?php while ($r = mysqli_fetch_assoc($rows)): ?>
                                                    <tr>
                                                        <td><span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($r["module"]); ?></span></td>
                                                        <td>
                                                            <?php echo htmlspecialchars($r["label"] ?: ("#" . $r["record_id"])); ?>
                                                            <small class="text-muted d-block">id original: <?php echo (int) $r["record_id"]; ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($r["deleted_by_name"] ?? "—"); ?></td>
                                                        <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($r["deleted_at"]))); ?></td>
                                                        <td>
                                                            <?php if ((int) $r["restored"] === 1): ?>
                                                                <span class="badge bg-success">Restaurado</span>
                                                                <small class="text-muted d-block">
                                                                    <?php echo htmlspecialchars($r["restored_by_name"] ?? ""); ?>
                                                                    <?php echo $r["restored_at"] ? (" · " . date("d/m/Y H:i", strtotime($r["restored_at"]))) : ""; ?>
                                                                </small>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">En papelera</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if ((int) $r["restored"] === 0): ?>
                                                                <form method="post" class="d-inline" onsubmit="return confirm('¿Restaurar este registro?');">
                                                                    <input type="hidden" name="action" value="restore">
                                                                    <input type="hidden" name="id" value="<?php echo (int) $r["id"]; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-soft-success">
                                                                        <i class="mdi mdi-restore me-1"></i>Restaurar
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar DEFINITIVAMENTE? Esta accion no se puede deshacer.');">
                                                                <input type="hidden" name="action" value="purge">
                                                                <input type="hidden" name="id" value="<?php echo (int) $r["id"]; ?>">
                                                                <button type="submit" class="btn btn-sm btn-soft-danger">
                                                                    <i class="mdi mdi-delete-forever"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">La papelera esta vacia.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

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
