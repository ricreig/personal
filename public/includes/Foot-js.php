<?php
declare(strict_types=1);
require_once __DIR__ . '/assets.php';
require_once dirname(__DIR__,2) . '/lib/paths.php';
cr_print_js_globals(); // expone window.BASE_URL y window.API_BASE
?>
<script>
  // API en root: /unificado/api → desde el subdominio que apunta a /public, se accede como:
  window.API_BASE = "/api/";  // <- estable, sin depender de rutas relativas
</script>
<?php
$JS = [
  asset_version('/public/assets/js/jquery-3.7.1.min.js'),
  asset_version('/public/assets/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js'),
  asset_version('/public/assets/js/datatables.min.js'),
  asset_version('/public/assets/date-mask.js'),
  asset_version('/public/assets/app.js'), // tu inicializador
  asset_version('/public/assets/js/app_no_ficha.js'),
  asset_version('/public/assets/js/app_lic_button.js'),
];
render_js($JS);

