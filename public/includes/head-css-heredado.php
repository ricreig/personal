<?php
require_once __DIR__ . '/assets.php';
require_once dirname(__DIR__,2) . '/lib/paths.php';
cr_print_js_globals(); // expone window.BASE_URL y window.API_BASE
?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php
$CSS = [
  asset_version('/public/assets/bootstrap-5.3.8-dist/css/bootstrap.min.css'),
  asset_version('/public/assets/css/datatables.min.css'),
  asset_version('/public/assets/fontawesome-free-7.0.0-web/css/fontawesome.min.css'),
  asset_version('/public/assets/fontawesome-free-7.0.0-web/css/brands.min.css'),
  asset_version('/public/assets/fontawesome-free-7.0.0-web/css/solid.min.css'),
  // Tipografía
  'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap',
  // Bulma Style
'https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css',
  // Estilos propios
  asset_version('/public/assets/login.css'),
];
render_css($CSS);