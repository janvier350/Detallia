<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 4, 5]);

$current_role = (int) $_SESSION["role_id"];

$roleTabs = [
    1 => "administrador",
    2 => "jefe",
    3 => "asistente",
    5 => "consultor",
    4 => "solicitante",
];
$activeTab = $roleTabs[$current_role] ?? "administrador";

$manual = [
    "administrador" => [
        "label" => "Administrador",
        "icon"  => "mdi-shield-crown-outline",
        "color" => "danger",
        "steps" => [
            "Inicia sesion con tu usuario y contrasena.",
            "Revisa el Dashboard: indicadores generales y el calendario de movimientos de inventario.",
            "Gestiona Usuarios: crea una cuenta para cada persona y asignale el rol que le corresponde.",
            "Ajusta los accesos en Permisos si necesitas cambiar lo que puede hacer cada rol (Jefe, Asistente, Consultor, Solicitante).",
            "Configura el catalogo base: Proveedores, Articulos (con categoria y marca), Marcas, Clientes y Kits.",
            "Registra las facturas en Compras para alimentar el stock de los articulos.",
            "Registra las Entregas de kits a los clientes; el stock se descuenta automaticamente.",
            "Si un cliente devuelve articulos, registralo en Devoluciones para reponer el stock (con motivo obligatorio).",
            "Consulta Inventario para ver el historial completo de entradas y salidas de cada articulo.",
            "Revisa las Solicitudes internas del personal y marcalas como despachadas cuando entregues lo pedido.",
        ],
    ],
    "jefe" => [
        "label" => "Jefe",
        "icon"  => "mdi-account-tie-outline",
        "color" => "warning",
        "steps" => [
            "Inicia sesion con tu usuario y contrasena.",
            "Revisa el Dashboard para ver el estado general del negocio.",
            "Administra Proveedores, Articulos, Marcas, Clientes y Kits (crear, editar y eliminar).",
            "Registra las facturas en Compras; esto alimenta el stock automaticamente.",
            "Registra las Entregas de kits a los clientes.",
            "Registra Devoluciones cuando un cliente regresa articulos, indicando el motivo.",
            "Consulta Inventario para revisar el historial de movimientos de stock.",
            "Revisa y despacha las Solicitudes pendientes del personal.",
        ],
    ],
    "asistente" => [
        "label" => "Asistente",
        "icon"  => "mdi-account-outline",
        "color" => "info",
        "steps" => [
            "Inicia sesion con tu usuario y contrasena.",
            "Revisa el Dashboard.",
            "Consulta Proveedores, Articulos, Marcas, Clientes y Kits (no puedes editarlos).",
            "Registra y edita las facturas de Compra.",
            "Registra las Entregas de kits a los clientes.",
            "Registra Devoluciones cuando un cliente regresa articulos.",
            "Consulta el historial de Inventario.",
            "Si necesitas articulos o kits para tu trabajo, crea tu propia Solicitud e imprimela.",
        ],
    ],
    "consultor" => [
        "label" => "Consultor de Inventario",
        "icon"  => "mdi-clipboard-search-outline",
        "color" => "dark",
        "steps" => [
            "Inicia sesion con tu usuario y contrasena.",
            "Revisa el Dashboard y el calendario de movimientos de inventario.",
            "Consulta Proveedores, Articulos, Compras, Kits, Entregas y Devoluciones (solo lectura, sin crear ni editar).",
            "Puedes crear nuevas Marcas cuando se necesite.",
            "Tienes control completo de Clientes: crear, editar y eliminar.",
            "Usa Inventario para verificar el stock disponible de cada articulo antes de aprobar una entrega o devolucion.",
        ],
    ],
    "solicitante" => [
        "label" => "Solicitante",
        "icon"  => "mdi-clipboard-text-outline",
        "color" => "secondary",
        "steps" => [
            "Inicia sesion con tu usuario y contrasena.",
            "El sistema te lleva directo a Mis solicitudes (no ves el resto de modulos).",
            "Haz clic en Nueva solicitud.",
            "Agrega una o varias lineas: elige Articulo o Kit y la cantidad que necesitas.",
            "Escribe una nota si quieres dar mas detalles de tu pedido.",
            "Haz clic en Generar solicitud; la fecha y tu nombre se registran automaticamente.",
            "Se abre el documento de tu solicitud: imprimelo o guardalo como PDF.",
            "Usa ese documento para tramitar el pedido con el area correspondiente.",
            "Vuelve a Mis solicitudes para ver si tu pedido ya fue marcado como Despachado.",
        ],
    ],
];
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Manual de usuario | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Manual de usuario</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Manual</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted mb-4">Elige tu rol para ver el flujo de uso paso a paso. Tu rol actual esta preseleccionado.</p>

                                <ul class="nav nav-pills mb-4" role="tablist">
                                    <?php foreach ($manual as $key => $role): ?>
                                        <li class="nav-item">
                                            <a class="nav-link <?php echo $key === $activeTab ? "active" : ""; ?>" data-bs-toggle="tab" href="#manual-<?php echo $key; ?>" role="tab">
                                                <i class="mdi <?php echo $role["icon"]; ?> me-1"></i> <?php echo htmlspecialchars($role["label"]); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                                <div class="tab-content">
                                    <?php foreach ($manual as $key => $role): ?>
                                        <div class="tab-pane <?php echo $key === $activeTab ? "active show" : ""; ?>" id="manual-<?php echo $key; ?>" role="tabpanel">
                                            <h5 class="mb-4">
                                                <span class="avatar-title rounded-circle bg-<?php echo $role["color"]; ?>-subtle text-<?php echo $role["color"]; ?> me-2" style="width:36px;height:36px;display:inline-flex;">
                                                    <i class="mdi <?php echo $role["icon"]; ?>"></i>
                                                </span>
                                                Flujo para el rol <?php echo htmlspecialchars($role["label"]); ?>
                                            </h5>

                                            <ul class="list-unstyled">
                                                <?php foreach ($role["steps"] as $i => $step): ?>
                                                    <li class="d-flex mb-4">
                                                        <div class="flex-shrink-0 me-3">
                                                            <span class="avatar-title rounded-circle bg-<?php echo $role["color"]; ?> text-white font-size-14" style="width:32px;height:32px;display:inline-flex;">
                                                                <?php echo $i + 1; ?>
                                                            </span>
                                                        </div>
                                                        <div class="flex-grow-1 pt-1">
                                                            <p class="mb-0"><?php echo htmlspecialchars($step); ?></p>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
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
