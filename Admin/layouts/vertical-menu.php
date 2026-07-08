<?php
$pendingRequests = [];
if (isset($_SESSION["role_id"]) && can("solicitudes", "view")) {
    $notifSql = "SELECT r.id, r.request_date, COALESCE(u.full_name, u.username) AS requested_by_name
                 FROM requests r
                 LEFT JOIN users u ON u.id = r.requested_by
                 WHERE r.status = 'pendiente'";
    if ((int) $_SESSION["role_id"] === 4) {
        $notifSql .= " AND r.requested_by = " . (int) $_SESSION["id"];
    }
    $notifSql .= " ORDER BY r.id DESC LIMIT 8";
    $notifRes = mysqli_query($link, $notifSql);
    if ($notifRes) {
        while ($notifRow = mysqli_fetch_assoc($notifRes)) {
            $pendingRequests[] = $notifRow;
        }
    }
}
$pendingRequestsCount = count($pendingRequests);
?>
<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <!-- LOGO -->
            <div class="navbar-brand-box">
                <a href="index.php" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="assets/images/logo-detallia-sm.svg" alt="Detallia" height="24">
                    </span>
                    <span class="logo-lg">
                        <img src="assets/images/logo-detallia.svg" alt="Detallia" height="24">
                    </span>
                </a>

                <a href="index.php" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="assets/images/logo-detallia-sm.svg" alt="Detallia" height="24">
                    </span>
                    <span class="logo-lg">
                        <img src="assets/images/logo-detallia.svg" alt="Detallia" height="24">
                    </span>
                </a>
            </div>

            <button type="button" class="btn btn-sm px-3 font-size-16 header-item" id="vertical-menu-btn">
                <i class="fa fa-fw fa-bars"></i>
            </button>

            <!-- App Search-->
            <form class="app-search d-none d-lg-block">
                <div class="position-relative">
                    <input type="text" class="form-control" placeholder="<?php echo $language["Search"]; ?>">
                    <button class="btn btn-primary" type="button"><i class="bx bx-search-alt align-middle"></i></button>
                </div>
            </form>
        </div>

        <div class="d-flex">

            <div class="dropdown d-inline-block d-lg-none ms-2">
                <button type="button" class="btn header-item" id="page-header-search-dropdown"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i data-feather="search" class="icon-lg"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                    aria-labelledby="page-header-search-dropdown">
        
                    <form class="p-3">
                        <div class="form-group m-0">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="<?php echo $language["Search"]; ?>" aria-label="Search Result">

                                <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dropdown d-none d-sm-inline-block">
            <button type="button" class="btn header-item waves-effect" data-bs-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false">
                    <?php if ($lang == 'en') { ?>
                        <img class="me-2" src="assets/images/flags/us.jpg" alt="Header Language" height="16"> 
                    <?php } ?>
                    <?php if ($lang == 'es') { ?>
                        <img class="me-2" src="assets/images/flags/spain.jpg" alt="Header Language" height="16"> 
                    <?php } ?>
                    <?php if ($lang == 'de') { ?>
                        <img class="me-2" src="assets/images/flags/germany.jpg" alt="Header Language" height="16">
                    <?php } ?>
                    <?php if ($lang == 'it') { ?>
                        <img class="me-2" src="assets/images/flags/italy.jpg" alt="Header Language" height="16"> 
                    <?php } ?>
                    <?php if ($lang == 'ru') { ?>
                        <img class="me-2" src="assets/images/flags/russia.jpg" alt="Header Language" height="16"> 
                    <?php } ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end">

                    <!-- item-->
                    <a href="?lang=en" class="dropdown-item notify-item language">
                        <img src="assets/images/flags/us.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle"> English </span>
                    </a>
                    
                    <!-- item-->
                    <a href="?lang=de" class="dropdown-item notify-item language">
                        <img src="assets/images/flags/germany.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle"> German </span>
                    </a>

                    <!-- item-->
                    <a href="?lang=it" class="dropdown-item notify-item language">
                        <img src="assets/images/flags/italy.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle"> Italian </span>
                    </a>

                    <!-- item-->
                    <a href="?lang=es" class="dropdown-item notify-item language">
                        <img src="assets/images/flags/spain.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle"> Spanish </span>
                    </a>

                     <!-- item-->
                     <a href="?lang=ru" class="dropdown-item notify-item language">
                        <img src="assets/images/flags/russia.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle"> Russian </span>
                    </a>
                </div>
            </div>

            <div class="dropdown d-none d-sm-inline-block">
                <button type="button" class="btn header-item" id="mode-setting-btn">
                    <i data-feather="moon" class="icon-lg layout-mode-dark"></i>
                    <i data-feather="sun" class="icon-lg layout-mode-light"></i>
                </button>
            </div>

            <div class="dropdown d-none d-lg-inline-block ms-1">
                <button type="button" class="btn header-item"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i data-feather="grid" class="icon-lg"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <div class="p-2">
                        <div class="row g-0">
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/github.png" alt="Github">
                                    <span>GitHub</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/bitbucket.png" alt="bitbucket">
                                    <span>Bitbucket</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/dribbble.png" alt="dribbble">
                                    <span>Dribbble</span>
                                </a>
                            </div>
                        </div>

                        <div class="row g-0">
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/dropbox.png" alt="dropbox">
                                    <span>Dropbox</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/mail_chimp.png" alt="mail_chimp">
                                    <span>Mail Chimp</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/slack.png" alt="slack">
                                    <span>Slack</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item noti-icon position-relative" id="page-header-notifications-dropdown"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i data-feather="bell" class="icon-lg"></i>
                    <?php if ($pendingRequestsCount > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $pendingRequestsCount; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                    aria-labelledby="page-header-notifications-dropdown">
                    <div class="p-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0">Solicitudes pendientes de despacho</h6>
                            </div>
                            <div class="col-auto">
                                <span class="small text-muted"><?php echo $pendingRequestsCount; ?> pendiente<?php echo $pendingRequestsCount === 1 ? '' : 's'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div data-simplebar style="max-height: 230px;">
                        <?php if (empty($pendingRequests)): ?>
                            <div class="p-3 text-muted text-center">No hay solicitudes pendientes.</div>
                        <?php else: ?>
                            <?php foreach ($pendingRequests as $pr): ?>
                                <a href="admin-request-print.php?id=<?php echo (int) $pr["id"]; ?>" target="_blank" class="text-reset notification-item">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0 avatar-sm me-3">
                                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle font-size-16">
                                                <i class="mdi mdi-clipboard-text-outline"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">Solicitud #<?php echo (int) $pr["id"]; ?></h6>
                                            <div class="font-size-13 text-muted">
                                                <p class="mb-1">Solicitado por <?php echo htmlspecialchars($pr["requested_by_name"] ?? "—"); ?></p>
                                                <p class="mb-0"><i class="mdi mdi-clock-outline"></i> <span><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($pr["request_date"]))); ?></span></p>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="p-2 border-top d-grid">
                        <a class="btn btn-sm btn-link font-size-14 text-center" href="admin-requests-list.php">
                            <i class="mdi mdi-arrow-right-circle me-1"></i> <span>Ver todas las solicitudes</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item right-bar-toggle me-2">
                    <i data-feather="settings" class="icon-lg"></i>
                </button>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item bg-light-subtle border-start border-end" id="page-header-user-dropdown"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="header-profile-user rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center">
                        <i class="mdi mdi-account font-size-20"></i>
                    </span>
                    <span class="d-none d-xl-inline-block ms-1 fw-medium"><?php echo htmlspecialchars($_SESSION["username"] ?? ""); ?></span>
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-item-text">
                        <div class="fw-medium"><?php echo htmlspecialchars($_SESSION["username"] ?? ""); ?></div>
                        <div class="text-muted font-size-12"><?php echo htmlspecialchars($_SESSION["role_name"] ?? ""); ?></div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php"><i class="mdi mdi-logout font-size-16 align-middle me-1"></i> <?php echo $language["Logout"]; ?></a>
                </div>
            </div>

        </div>
    </div>
</header>

<!-- ========== Left Sidebar Start ========== -->
<!-- ========== Left Sidebar Start ========== -->
<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title" data-key="t-menu"><?php echo $language["Menu"]; ?></li>

                <?php $is_solicitante = isset($_SESSION["role_id"]) && (int) $_SESSION["role_id"] === 4; ?>

                <?php if (!$is_solicitante): ?>
                <li>
                    <a href="index.php">
                        <i data-feather="home"></i>
                        <span data-key="t-dashboard"><?php echo $language["Dashboard"]; ?></span>
                    </a>
                </li>

                <?php if (isset($_SESSION["role_id"]) && (int) $_SESSION["role_id"] === 1): ?>
                <li>
                    <a href="admin-users-list.php">
                        <i data-feather="user-check"></i>
                        <span>Usuarios</span>
                    </a>
                </li>

                <li>
                    <a href="admin-permissions.php">
                        <i data-feather="shield"></i>
                        <span>Permisos</span>
                    </a>
                </li>
                <?php endif; ?>

                <li>
                    <a href="admin-providers-list.php">
                        <i data-feather="truck"></i>
                        <span>Proveedores</span>
                    </a>
                </li>

                <li>
                    <a href="admin-articles-list.php">
                        <i data-feather="package"></i>
                        <span>Articulos</span>
                    </a>
                </li>

                <li>
                    <a href="admin-purchases-list.php">
                        <i data-feather="shopping-cart"></i>
                        <span>Compras</span>
                    </a>
                </li>

                <li>
                    <a href="admin-brands-list.php">
                        <i data-feather="tag"></i>
                        <span>Marcas</span>
                    </a>
                </li>

                <li>
                    <a href="admin-clients-list.php">
                        <i data-feather="users"></i>
                        <span>Clientes</span>
                    </a>
                </li>

                <?php if (in_array((int) ($_SESSION["role_id"] ?? 0), [1, 2], true)): ?>
                <li>
                    <a href="admin-validation-links.php">
                        <i data-feather="link"></i>
                        <span>Validacion de contactos</span>
                    </a>
                </li>
                <?php endif; ?>

                <li>
                    <a href="admin-kits-list.php">
                        <i data-feather="gift"></i>
                        <span>Kits</span>
                    </a>
                </li>

                <li>
                    <a href="admin-deliveries-list.php">
                        <i data-feather="send"></i>
                        <span>Entregas</span>
                    </a>
                </li>

                <li>
                    <a href="admin-returns-list.php">
                        <i data-feather="corner-up-left"></i>
                        <span>Devoluciones</span>
                    </a>
                </li>

                <li>
                    <a href="admin-stock-movements.php">
                        <i data-feather="bar-chart-2"></i>
                        <span>Inventario</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php $role_id_now = (int) ($_SESSION["role_id"] ?? 0); ?>
                <?php if ($role_id_now !== 5): ?>
                <li>
                    <a href="admin-requests-list.php">
                        <i data-feather="clipboard"></i>
                        <span><?php echo $is_solicitante ? "Mis solicitudes" : "Solicitudes"; ?></span>
                    </a>
                </li>
                <?php endif; ?>

                <li>
                    <a href="admin-manual.php">
                        <i data-feather="book-open"></i>
                        <span>Manual de usuario</span>
                    </a>
                </li>

                <?php if ($role_id_now === 1): ?>
                <li class="menu-title">Referencia de plantilla (solo Administrador)</li>
                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="grid"></i>
                        <span data-key="t-apps"><?php echo $language["Apps"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li>
                            <a href="apps-calendar.php">
                                <span data-key="t-calendar"><?php echo $language["Calendar"]; ?></span>
                            </a>
                        </li>
        
                        <li>
                            <a href="apps-chat.php">
                                <span data-key="t-chat"><?php echo $language["Chat"]; ?></span>
                            </a>
                        </li>
        
                        <li>
                            <a href="javascript: void(0);" class="has-arrow">
                                <span data-key="t-email"><?php echo $language["Email"]; ?></span>
                            </a>
                            <ul class="sub-menu" aria-expanded="false">
                                <li><a href="apps-email-inbox.php" data-key="t-inbox"><?php echo $language["Inbox"]; ?></a></li>
                                <li><a href="apps-email-read.php" data-key="t-read-email"><?php echo $language["Read_Email"]; ?></a></li>
                            </ul>
                        </li>
                        <li>
                            <a href="javascript: void(0);" class="has-arrow">
                                <span data-key="t-invoices"><?php echo $language["Invoices"]; ?></span>
                            </a>
                            <ul class="sub-menu" aria-expanded="false">
                                <li><a href="apps-invoices-list.php" data-key="t-invoice-list"><?php echo $language["Invoice_List"]; ?></a></li>
                                <li><a href="apps-invoices-detail.php" data-key="t-invoice-detail"><?php echo $language["Invoice_Detail"]; ?></a></li>
                            </ul>
                        </li>
                        <li>
                            <a href="javascript: void(0);" class="has-arrow">
                                <span data-key="t-contacts"><?php echo $language["Contacts"]; ?></span>
                            </a>
                            <ul class="sub-menu" aria-expanded="false">
                                <li><a href="apps-contacts-grid.php" data-key="t-user-grid"><?php echo $language["User_Grid"]; ?></a></li>
                                <li><a href="apps-contacts-list.php" data-key="t-user-list"><?php echo $language["User_List"]; ?></a></li>
                                <li><a href="apps-contacts-profile.php" data-key="t-profile"><?php echo $language["Profile"]; ?></a></li>
                            </ul>
                        </li>
                        <li>
                            <a href="javascript: void(0);" class="">
                                <span data-key="t-blog"><?php echo $language["Blog"]; ?></span>
                                <span class="badge rounded-pill badge-soft-danger float-end" key="t-new"><?php echo $language["New"]; ?></span>
                            </a>
                            <ul class="sub-menu" aria-expanded="false">
                                <li><a href="apps-blog-grid.php" data-key="t-blog-grid"><?php echo $language["Blog_Grid"]; ?></a></li>
                                <li><a href="apps-blog-list.php" data-key="t-blog-list"><?php echo $language["Blog_List"]; ?></a></li>
                                <li><a href="apps-blog-detail.php" data-key="t-blog-details"><?php echo $language["Blog_Details"]; ?></a></li>
                            </ul>
                        </li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="users"></i>
                        <span data-key="t-authentication"><?php echo $language["Authentication"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="pages-login.php" data-key="t-login"><?php echo $language["Login"]; ?></a></li>
                        <li><a href="pages-register.php" data-key="t-register"><?php echo $language["Register"]; ?></a></li>
                        <li><a href="pages-recoverpw.php" data-key="t-recover-password"><?php echo $language["Recover_Password"]; ?></a></li>
                        <li><a href="auth-lock-screen.php" data-key="t-lock-screen"><?php echo $language["Lock_Screen"]; ?></a></li>
                        <li><a href="auth-confirm-mail.php" data-key="t-confirm-mail"><?php echo $language["Confirm_Mail"]; ?></a></li>
                        <li><a href="auth-email-verification.php" data-key="t-email-verification"><?php echo $language["Email_Verification"]; ?></a></li>
                        <li><a href="auth-two-step-verification.php" data-key="t-two-step-verification"><?php echo $language["Two_Step_Verification"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="file-text"></i>
                        <span data-key="t-pages"><?php echo $language["Pages"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="pages-starter.php" data-key="t-starter-page"><?php echo $language["Starter_Page"]; ?> </a></li>
                        <li><a href="pages-maintenance.php" data-key="t-maintenance"><?php echo $language["Maintenance"]; ?></a></li>
                        <li><a href="pages-comingsoon.php" data-key="t-coming-soon"><?php echo $language["Coming_Soon"]; ?></a></li>
                        <li><a href="pages-timeline.php" data-key="t-timeline"><?php echo $language["Timeline"]; ?></a></li>
                        <li><a href="pages-faqs.php" data-key="t-faqs"><?php echo $language["FAQs"]; ?></a></li>
                        <li><a href="pages-pricing.php" data-key="t-pricing"><?php echo $language["Pricing"]; ?></a></li>
                        <li><a href="pages-404.php" data-key="t-error-404"><?php echo $language["Error_404"]; ?></a></li>
                        <li><a href="pages-500.php" data-key="t-error-500"><?php echo $language["Error_500"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="layouts-horizontal.php">
                        <i data-feather="layout"></i>
                        <span data-key="t-horizontal"><?php echo $language["Horizontal"]; ?></span>
                    </a>
                </li>

                <li class="menu-title mt-2" data-key="t-components"><?php echo $language["Elements"]; ?></li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="briefcase"></i>
                        <span data-key="t-components"><?php echo $language["Components"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="ui-alerts.php" data-key="t-alerts"><?php echo $language["Alerts"]; ?></a></li>
                        <li><a href="ui-buttons.php" data-key="t-buttons"><?php echo $language["Buttons"]; ?></a></li>
                        <li><a href="ui-cards.php" data-key="t-cards"><?php echo $language["Cards"]; ?></a></li>
                        <li><a href="ui-carousel.php" data-key="t-carousel"><?php echo $language["Carousel"]; ?></a></li>
                        <li><a href="ui-dropdowns.php" data-key="t-dropdowns"><?php echo $language["Dropdowns"]; ?></a></li>
                        <li><a href="ui-grid.php" data-key="t-grid"><?php echo $language["Grid"]; ?></a></li>
                        <li><a href="ui-images.php" data-key="t-images"><?php echo $language["Images"]; ?></a></li>
                        <li><a href="ui-modals.php" data-key="t-modals"><?php echo $language["Modals"]; ?></a></li>
                        <li><a href="ui-offcanvas.php" data-key="t-offcanvas"><?php echo $language["Offcanvas"]; ?></a></li>
                        <li><a href="ui-progressbars.php" data-key="t-progress-bars"><?php echo $language["Progress_Bars"]; ?></a></li>
                        <li><a href="ui-tabs-accordions.php" data-key="t-tabs-accordions"><?php echo $language["Tabs_n_Accordions"]; ?></a></li>
                        <li><a href="ui-typography.php" data-key="t-typography"><?php echo $language["Typography"]; ?></a></li>
                        <li><a href="ui-video.php" data-key="t-video"><?php echo $language["Video"]; ?></a></li>
                        <li><a href="ui-general.php" data-key="t-general"><?php echo $language["General"]; ?></a></li>
                        <li><a href="ui-colors.php" data-key="t-colors"><?php echo $language["Colors"]; ?></a></li>
                        <li><a href="ui-utilities.php" data-key="t-utilities"><?php echo $language["Utilities"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="gift"></i>
                        <span data-key="t-ui-elements"><?php echo $language["Extended"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="extended-lightbox.php" data-key="t-lightbox"><?php echo $language["Lightbox"]; ?></a></li>
                        <li><a href="extended-rangeslider.php" data-key="t-range-slider"><?php echo $language["Range_Slider"]; ?></a></li>
                        <li><a href="extended-sweet-alert.php" data-key="t-sweet-alert"><?php echo $language["SweetAlert_2"]; ?></a></li>
                        <li><a href="extended-session-timeout.php" data-key="t-session-timeout"><?php echo $language["Session_Timeout"]; ?></a></li>
                        <li><a href="extended-rating.php" data-key="t-rating"><?php echo $language["Rating"]; ?></a></li>
                        <li><a href="extended-notifications.php" data-key="t-notifications"><?php echo $language["Notifications"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);">
                        <i data-feather="box"></i>
                        <span class="badge rounded-pill badge-soft-danger  text-danger float-end">7</span>
                        <span data-key="t-forms"><?php echo $language["Forms"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="form-elements.php" data-key="t-form-elements"><?php echo $language["Basic_Elements"]; ?></a></li>
                        <li><a href="form-validation.php" data-key="t-form-validation"><?php echo $language["Validation"]; ?></a></li>
                        <li><a href="form-advanced.php" data-key="t-form-advanced"><?php echo $language["Advanced_Plugins"]; ?></a></li>
                        <li><a href="form-editors.php" data-key="t-form-editors"><?php echo $language["Editors"]; ?></a></li>
                        <li><a href="form-uploads.php" data-key="t-form-upload"><?php echo $language["File_Upload"]; ?></a></li>
                        <li><a href="form-wizard.php" data-key="t-form-wizard"><?php echo $language["Wizard"]; ?></a></li>
                        <li><a href="form-mask.php" data-key="t-form-mask"><?php echo $language["Mask"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="sliders"></i>
                        <span data-key="t-tables"><?php echo $language["Tables"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="tables-basic.php" data-key="t-basic-tables"><?php echo $language["Bootstrap_Basic"]; ?></a></li>
                        <li><a href="tables-datatable.php" data-key="t-data-tables"><?php echo $language["DataTables"]; ?></a></li>
                        <li><a href="tables-responsive.php" data-key="t-responsive-table"><?php echo $language["Responsive"]; ?></a></li>
                        <li><a href="tables-editable.php" data-key="t-editable-table"><?php echo $language["Editable"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="pie-chart"></i>
                        <span data-key="t-charts"><?php echo $language["Charts"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="charts-apex.php" data-key="t-apex-charts"><?php echo $language["Apexcharts"]; ?></a></li>
                        <li><a href="charts-echart.php" data-key="t-e-charts"><?php echo $language["Echarts"]; ?></a></li>
                        <li><a href="charts-chartjs.php" data-key="t-chartjs-charts"><?php echo $language["Chartjs"]; ?></a></li>
                        <li><a href="charts-knob.php" data-key="t-knob-charts"><?php echo $language["Jquery_Knob"]; ?></a></li>
                        <li><a href="charts-sparkline.php" data-key="t-sparkline-charts"><?php echo $language["Sparkline"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="cpu"></i>
                        <span data-key="t-icons"><?php echo $language["Icons"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="icons-boxicons.php" data-key="t-boxicons"><?php echo $language["Boxicons"]; ?></a></li>
                        <li><a href="icons-materialdesign.php" data-key="t-material-design"><?php echo $language["Material_Design"]; ?></a></li>
                        <li><a href="icons-dripicons.php" data-key="t-dripicons"><?php echo $language["Dripicons"]; ?></a></li>
                        <li><a href="icons-fontawesome.php" data-key="t-font-awesome"><?php echo $language["Font_Awesome_5"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="map"></i>
                        <span data-key="t-maps"><?php echo $language["Maps"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="maps-google.php" data-key="t-g-maps"><?php echo $language["Google"]; ?></a></li>
                        <li><a href="maps-vector.php" data-key="t-v-maps"><?php echo $language["Vector"]; ?></a></li>
                        <li><a href="maps-leaflet.php" data-key="t-l-maps"><?php echo $language["Leaflet"]; ?></a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="share-2"></i>
                        <span data-key="t-multi-level"><?php echo $language["Multi_Level"]; ?></span>
                    </a>
                    <ul class="sub-menu" aria-expanded="true">
                        <li><a href="javascript: void(0);" data-key="t-level-1-1"><?php echo $language["Level_1_1"]; ?></a></li>
                        <li>
                            <a href="javascript: void(0);" class="has-arrow" data-key="t-level-1-2"><?php echo $language["Level_1_2"]; ?></a>
                            <ul class="sub-menu" aria-expanded="true">
                                <li><a href="javascript: void(0);" data-key="t-level-2-1"><?php echo $language["Level_2_1"]; ?></a></li>
                                <li><a href="javascript: void(0);" data-key="t-level-2-2"><?php echo $language["Level_2_2"]; ?></a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>

        </div>
        <!-- Sidebar -->
    </div>
</div>
<!-- Left Sidebar End -->