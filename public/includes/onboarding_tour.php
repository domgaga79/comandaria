<?php
/*
|--------------------------------------------------------------------------
| Include do onboarding de primeira utilização
|--------------------------------------------------------------------------
| Inserir uma vez, antes de </body>, nas telas:
| - dashboard.php
| - index.php
| - painel_cozinha.php
| - painel_caixa.php
*/

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

$onboardingPagina = basename($_SERVER['SCRIPT_NAME'] ?? '');
$onboardingRole = strtolower(trim((string)(
    $_SESSION['user_tipo']
    ?? $_SESSION['tipo']
    ?? $_SESSION['role']
    ?? ''
)));
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/driver.css">
<link rel="stylesheet" href="assets/onboarding/onboarding-tour.css?v=1">

<button
    type="button"
    class="onboarding-repetir-btn"
    data-repetir-onboarding
    title="Rever o guia desta tela"
    aria-label="Repetir guia desta tela"
>
    ❔ Guia
</button>

<script>
window.COMANDARIA_ONBOARDING = {
    page: <?= json_encode($onboardingPagina, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    role: <?= json_encode($onboardingRole, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    version: 'v1',
    statusUrl: 'onboarding_status.php',
    completeUrl: 'onboarding_concluir.php'
};
</script>

<script src="https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/driver.js.iife.js"></script>
<script src="assets/onboarding/onboarding-tour.js?v=1"></script>
