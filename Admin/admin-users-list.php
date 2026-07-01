<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1]); // Solo Administrador

$success_msg = "";
$error_msg = "";

// ---------------------------------------------------------------
// CREAR / EDITAR usuario
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "save") {
    $user_id   = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $useremail = trim($_POST["useremail"] ?? "");
    $username  = trim($_POST["username"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $role_id   = (int) ($_POST["role_id"] ?? 0);
    $status    = ($_POST["status"] ?? "activo") === "inactivo" ? "inactivo" : "activo";
    $password  = $_POST["password"] ?? "";

    if ($useremail === "" || $username === "" || $role_id <= 0) {
        $error_msg = "Correo, usuario y rol son obligatorios.";
    } else {
        if ($user_id > 0) {
            // Editar usuario existente
            if ($password !== "") {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET useremail=?, username=?, full_name=?, role_id=?, status=?, password=? WHERE id=?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "sssissi", $useremail, $username, $full_name, $role_id, $status, $hashed, $user_id);
            } else {
                $sql = "UPDATE users SET useremail=?, username=?, full_name=?, role_id=?, status=? WHERE id=?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "sssisi", $useremail, $username, $full_name, $role_id, $status, $user_id);
            }
            if ($stmt && mysqli_stmt_execute($stmt)) {
                $success_msg = "Usuario actualizado correctamente.";
            } else {
                $error_msg = "No se pudo actualizar el usuario: " . mysqli_error($link);
            }
        } else {
            // Crear nuevo usuario
            if ($password === "") {
                $error_msg = "La contrasena es obligatoria para un nuevo usuario.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (useremail, username, full_name, password, role_id, status) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "ssssis", $useremail, $username, $full_name, $hashed, $role_id, $status);
                if (mysqli_stmt_execute($stmt)) {
                    $success_msg = "Usuario creado correctamente.";
                } else {
                    if (mysqli_errno($link) == 1062) {
                        $error_msg = "Ya existe un usuario con ese correo.";
                    } else {
                        $error_msg = "No se pudo crear el usuario: " . mysqli_error($link);
                    }
                }
            }
        }
    }
}

// ---------------------------------------------------------------
// ELIMINAR usuario
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $user_id = (int) ($_POST["id"] ?? 0);
    if ($user_id > 0 && $user_id !== (int) $_SESSION["id"]) {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Usuario eliminado correctamente.";
        } else {
            $error_msg = "No se pudo eliminar el usuario: " . mysqli_error($link);
        }
    } else {
        $error_msg = "No puedes eliminar tu propia cuenta.";
    }
}

// ---------------------------------------------------------------
// Cargar roles y usuarios
// ---------------------------------------------------------------
$roles = mysqli_query($link, "SELECT id, name FROM roles ORDER BY id");

$users = mysqli_query($link, "SELECT u.id, u.useremail, u.username, u.full_name, u.status, u.created_at,
                                      r.id AS role_id, r.name AS role_name
                               FROM users u
                               JOIN roles r ON r.id = u.role_id
                               ORDER BY u.id DESC");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Usuarios | Detallia</title>
    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>

</head>

<?php include 'layouts/body.php'; ?>

<!-- Begin page -->
<div id="layout-wrapper">

    <?php include 'layouts/menu.php'; ?>

    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Usuarios</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Usuarios</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

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
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h5 class="card-title mb-0">Listado de usuarios</h5>
                                    <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openCreateModal()">
                                        <i class="mdi mdi-plus me-1"></i> Nuevo usuario
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Usuario</th>
                                                <th>Nombre completo</th>
                                                <th>Correo</th>
                                                <th>Rol</th>
                                                <th>Estado</th>
                                                <th>Creado</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($u = mysqli_fetch_assoc($users)): ?>
                                                <tr>
                                                    <td><?php echo (int) $u["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($u["username"]); ?></td>
                                                    <td><?php echo htmlspecialchars($u["full_name"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($u["useremail"]); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $u["role_id"] == 1 ? 'danger' : ($u["role_id"] == 2 ? 'warning' : ($u["role_id"] == 4 ? 'secondary' : ($u["role_id"] == 5 ? 'dark' : 'info'))); ?>">
                                                            <?php echo htmlspecialchars($u["role_name"]); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $u["status"] === 'activo' ? 'success' : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($u["status"]); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($u["created_at"]); ?></td>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-sm btn-soft-primary"
                                                            onclick='openEditModal(<?php echo json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                            <i class="mdi mdi-pencil"></i>
                                                        </button>
                                                        <?php if ((int) $u["id"] !== (int) $_SESSION["id"]): ?>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo (int) $u["id"]; ?>">
                                                                <button type="submit" class="btn btn-sm btn-soft-danger">
                                                                    <i class="mdi mdi-delete"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
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

<!-- Modal: Crear / Editar usuario -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="user_id" value="">

                <div class="mb-3">
                    <label class="form-label">Nombre de usuario</label>
                    <input type="text" name="username" id="user_username" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="full_name" id="user_full_name" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Correo</label>
                    <input type="email" name="useremail" id="user_useremail" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <select name="role_id" id="user_role_id" class="form-select" required>
                        <?php mysqli_data_seek($roles, 0); while ($r = mysqli_fetch_assoc($roles)): ?>
                            <option value="<?php echo (int) $r["id"]; ?>"><?php echo htmlspecialchars($r["name"]); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="status" id="user_status" class="form-select">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Contrasena</label>
                    <input type="password" name="password" id="user_password" class="form-control" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
                    <small class="text-muted" id="password_hint"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Right Sidebar -->
<?php include 'layouts/right-sidebar.php'; ?>

<!-- JAVASCRIPT -->
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

<script>
function openCreateModal() {
    document.getElementById('userModalLabel').innerText = 'Nuevo usuario';
    document.getElementById('user_id').value = '';
    document.getElementById('user_username').value = '';
    document.getElementById('user_full_name').value = '';
    document.getElementById('user_useremail').value = '';
    document.getElementById('user_role_id').value = '3';
    document.getElementById('user_status').value = 'activo';
    document.getElementById('user_password').value = '';
    document.getElementById('user_password').required = true;
    document.getElementById('password_hint').innerText = '';
}

function openEditModal(user) {
    document.getElementById('userModalLabel').innerText = 'Editar usuario';
    document.getElementById('user_id').value = user.id;
    document.getElementById('user_username').value = user.username;
    document.getElementById('user_full_name').value = user.full_name || '';
    document.getElementById('user_useremail').value = user.useremail;
    document.getElementById('user_role_id').value = user.role_id;
    document.getElementById('user_status').value = user.status;
    document.getElementById('user_password').value = '';
    document.getElementById('user_password').required = false;
    document.getElementById('password_hint').innerText = 'Dejar en blanco para mantener la contrasena actual.';

    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}
</script>

</body>

</html>
