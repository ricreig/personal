<?php
declare(strict_types=1);
require_once dirname(__DIR__,1) . '/lib/session.php';
require_once dirname(__DIR__,1) . '/lib/paths.php';
require_once dirname(__DIR__,1) . '/lib/auth.php';
cr_define_php_paths(); // define constantes PHP si no existen

// Si ya hay usuario autenticado, manda a inicio
if (auth_user()) {
  header('Location: ' . BASE_URL . 'index.php');
  exit;
}

// CSRF en sesión
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf'];

// Redirección opcional ?to= (solo interna)
$to = BASE_URL . 'index.php';
if (!empty($_GET['to'])) {
  $cand = (string)$_GET['to'];
  // Limpia y asegura que no incluya esquema absoluto
  $cand = filter_var($cand, FILTER_SANITIZE_URL);
  if ($cand && strpos($cand, '://') === false) {
    // Normaliza: si empieza con '/', respeta; si no, relativo a BASE_URL
    $to = ($cand[0] === '/')
      ? $cand
      : rtrim(BASE_URL, '/') . '/' . ltrim($cand, '/');
  }
}

// Estado de la vista
$error = null;
$redirect_snippet = '';

// POST: intentar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['pass'] ?? '');
  $csrf  = (string)($_POST['csrf'] ?? '');
  $remember = !empty($_POST['remember']);

  // Verifica CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    $error = 'CSRF no válido. Recarga la página.';
  } else {
    // Intenta autenticar
    $ok = auth_login($email, $pass, ['remember' => $remember]);
    if ($ok) {
      // Redirige (header) y da fallbacks (meta + JS) por si ya hubo salida
      header('Location: ' . $to, true, 302);
      $to_js = json_encode($to, JSON_UNESCAPED_SLASHES);
      $redirect_snippet = <<<HTML
<meta http-equiv="refresh" content="0;url={$to}">
<script>try{window.location.replace({$to_js});}catch(_){location.href={$to_js};}</script>
<p class="text-center mt-3">Redirigiendo… Si no avanza, <a href="{$to}">haz clic aquí</a>.</p>
HTML;
    } else {
      // No autentica: muestra error genérico (sin filtrar causa)
      $error = 'Usuario o contraseña incorrectos.';
    }
  }
}
?>
<!doctype html>
<html lang="es" class="theme-light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial<>-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#176c72">
  <title>Acceso — Control Regional</title>
    <?php require __DIR__ . '/includes/head-css-heredado.php'; ?>
<style>
:root{
  --brand-bg:   #176c72;  /* turquesa */
  --brand-dark: #114e52;  /* verde más oscuro */
}

html { height: -webkit-fill-available; background-color: var(--brand-dark); }
body {
  min-height: 100vh;
  min-height: 100svh;
  min-height: 100dvh;
  min-height: -webkit-fill-available;
  margin: 0;
  color:#223;
  background: transparent;                /* el fondo real va en html::before */
}

/* Capa fija que PINTA TODO el viewport (sin padding) */
html::before{
  content: "";
  position: fixed;
  inset: 0;
  z-index: -1;
  background: linear-gradient(180deg, #208A92 0%, #011014 100%);
  /* NO usar padding aquí: causaba la franja sin pintar en iOS */
}

/* Centrado; aquí sí aplicamos safe areas */
.login-wrap{
  min-height: calc(100svh - env(safe-area-inset-top) - env(safe-area-inset-bottom));
  min-height: calc(100dvh - env(safe-area-inset-top) - env(safe-area-inset-bottom));
  display: flex; align-items: center; justify-content: center;
  padding: max(24px, env(safe-area-inset-top)) 24px max(24px, env(safe-area-inset-bottom));
}

/* tarjeta más ancha en desktop */
.login-card{
  width:100%;
  max-width:440px;
  border:0; border-radius:18px;
  box-shadow: 0 20px 60px rgba(0,0,0,.25);
  overflow:hidden;
  backdrop-filter:saturate(1.2);
}

@media (min-width: 992px){
  .login-card{ max-width: 680px; }  /* sube el ancho de la card en desktop */
}

/* logo */
.login-brand{ background:#fff; padding:28px 28px 8px; text-align:center; }

/* base (móvil) */
.login-brand picture img{
  display:block; margin:0 auto;
  width:100%; height:auto;
  max-width:220px;              /* vertical en móvil */
}

/* desde tablet/desktop: permite crecer el horizontal */
@media (min-width: 768px){
  .login-brand picture img{
    max-width: clamp(380px, 36vw, 560px);  /* crece hasta ~560px si hay espacio */
  }
}

.login-body{ background:#fff; padding:24px 28px 28px; }
.form-label{ font-weight:600; }
.form-control{ font-size:16px; } /* evita zoom iOS */
.btn-primary{ background:#2b7f86; border-color:#2b7f86; }
.btn-primary:hover{ background:#256e74; border-color:#256e74; }

.brand-footer{ color:#e6f3f4; opacity:.9; font-size:.9rem; text-align:center; margin-top:16px; }

/* Alert oculto por defecto */
.error-msg{ display:none; padding:.5rem .75rem; margin-bottom:1rem; }
</style>
</head>
<body>
  <main class="login-wrap">
    <div class="box has-text-centered">
      <div class="field">
        <img src="assets/SENEAM_Logo_V.webp"
             srcset="assets/SENEAM_Logo_V.webp 180w, assets/SENEAM_Logo_V@2x.webp 360w"
             sizes="200px" width="200" height="151" alt="SENEAM"
             loading="eager" decoding="async" fetchpriority="high">
      </div>
      <div class="login-body">
        <h1 class="h4 mb-3 text-center">Acceso Controlado</h1>

        <?php if ($redirect_snippet): ?>
          <?= $redirect_snippet /* ya autenticado, mostrar fallbacks */ ?>
        <?php else: ?>
          <?php
            // Mensajes por ?err= cuando llegas desde logout o guard
            $map = ['timeout'=>'Tu sesión expiró. Inicia sesión nuevamente.','denied'=>'Acceso denegado.'];
            $err = $_GET['err'] ?? '';
            if (!$error && $err) {
              $msg = $map[$err] ?? $err;
              echo '<div class="notification is-warning is-light has-text-centered" role="alert">'.htmlspecialchars($msg).'</div>';
            }
            // Error de login/CSRF
            if ($error) {
      echo '<div class="notification is-danger is-light has-text-centered" role="alert">'.htmlspecialchars($error).'</div>';
           } 
?>
           <form method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
            <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input id="email" name="email" type="email" class="form-control" required autocomplete="username" autofocus>
            </div>
            
            <div class="mb-3">
              <label class="form-label" for="pass">Contraseña</label>
              <div class="input-group">
                <input id="pass" name="pass" type="password" class="form-control" required autocomplete="current-password">
                <button class="btn btn-outline-secondary" type="button" id="btnToggle">
                  <i class="bi bi-eye"></i><span class="visually-hidden">Mostrar</span>
                </button>
              </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                <label class="form-check-label" for="remember">Recordarme</label>
              </div>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit">Entrar</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <?php require __DIR__ . '/includes/Foot-js.php'; ?>
  <script>
    // Mostrar/ocultar contraseña
    document.getElementById('btnToggle').addEventListener('click', function(){
      const p = document.getElementById('pass');
      const isPwd = p.type === 'password';
      p.type = isPwd ? 'text' : 'password';
      this.innerHTML = isPwd ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  </script>
</body>
</html>
