<?php
/**
 * Header de l'application
 * Inclut les dépendances et le menu latéral
 */

// Vérification de la session
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}


// Inclure les fonctions utilitaires (seulement si pas déjà inclus)
if (!function_exists('setPageVariables')) {
    require_once 'includes/functions.php';
}

// Variables par défaut si non définies
if (!isset($pageTitle)) $pageTitle = 'Videosonic';
if (!isset($pageDescription)) $pageDescription = '';
if (!isset($currentPageName)) $currentPageName = 'index';
?>
<!doctype html>

<html
  lang="en"
  class="layout-menu-fixed"
  dir="ltr"
  data-skin="default"
  data-assets-path="assets/"
  data-template="vertical-menu-template-starter"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title><?php echo h($pageTitle); ?></title>

    <meta name="description" content="<?php echo h($pageDescription); ?>" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css  -->

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/pickr/pickr-themes.css" />

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/css/core.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/avision.css" />

    <!-- Vendors CSS -->

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />

    <!-- Custom DataTables Dark Mode Fixes -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/datatables-dark-mode.css" />

    <!-- ApexCharts CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/apex-charts/apex-charts.css" />

                    <!-- FullCalendar CSS -->
                <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/fullcalendar/fullcalendar.css" />

                <!-- Select2 CSS -->
                <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/libs/select2/select2.css" />

    <!-- endbuild -->

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/js/helpers.js"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->

    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->

    <script src="<?php echo BASE_URL; ?>assets/js/config.js"></script>

    <!-- Fonctions JavaScript partagées -->
    <script src="<?php echo BASE_URL; ?>assets/js/shared-functions.js"></script>

                    <!-- jQuery -->
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

                <!-- Select2 -->
                <script src="<?php echo BASE_URL; ?>assets/vendor/libs/select2/select2.js"></script>

                <!-- FullCalendar JS -->
                <script src="<?php echo BASE_URL; ?>assets/vendor/libs/fullcalendar/fullcalendar.js"></script>

    <script>
    (function() {
      try {
        var templateName = document.documentElement.getAttribute('data-template') || 'vertical-menu-template-starter';
        var theme = localStorage.getItem('theme-' + templateName);
        if (theme) {
          if (theme === 'system') {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
          }
          document.documentElement.setAttribute('data-bs-theme', theme);
        }
      } catch(e) {}
    })();
    </script>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container"> 