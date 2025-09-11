<?php
/**
 * Vue du tableau de bord client
 * Affiche les localisations autorisées du client connecté
 */

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../../includes/functions.php';

setPageVariables(
    'Tableau de bord',
    'dashboard'
);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Vérifier que l'utilisateur est client (sécurité)
if (!isClient()) {
    $_SESSION['error'] = 'Accès non autorisé. Cette page est réservée aux clients.';
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Définir la page courante pour le menu
$currentPage = 'dashboard';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Localisations autorisées</h1>
                <div class="text-muted small">
                    <span class="badge bg-success me-2">●</span>Autorisé
                    <span class="badge bg-secondary ms-2">●</span>Non autorisé
                </div>
            </div>

            <?php if (empty($sitesWithAccess)): ?>
                <div class="text-center py-5">
                    <div class="text-muted">
                        <i class="bi bi-building fs-1 mb-3"></i>
                        <p>Aucune localisation disponible</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($sitesWithAccess as $site): ?>
                        <div class="list-group-item p-0 border-0 mb-1">
                            <div class="d-flex align-items-center p-2 <?php echo isset($site['authorized']) && $site['authorized'] ? 'bg-success bg-opacity-10 border-start border-success border-4' : 'bg-light border-start border-secondary border-4'; ?>">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-building me-2 <?php echo isset($site['authorized']) && $site['authorized'] ? 'text-success' : 'text-secondary'; ?>"></i>
                                        <span class="<?php echo isset($site['authorized']) && $site['authorized'] ? 'text-success' : 'text-secondary'; ?>">
                                            <?php echo htmlspecialchars($site['name']); ?>
                                        </span>
                                        <?php if (isset($site['authorized']) && $site['authorized']): ?>
                                            <span class="badge bg-success ms-2">Autorisé</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary ms-2">Non autorisé</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($site['rooms'])): ?>
                                <div class="ms-4">
                                    <?php foreach ($site['rooms'] as $room): ?>
                                        <div class="d-flex align-items-center py-1 <?php echo isset($room['authorized']) && $room['authorized'] ? 'text-success' : 'text-muted'; ?>">
                                            <i class="bi bi-door-open me-2"></i>
                                            <span class="small"><?php echo htmlspecialchars($room['name']); ?></span>
                                            <?php if (isset($room['authorized']) && $room['authorized']): ?>
                                                <span class="badge bg-success ms-auto">Accès</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 