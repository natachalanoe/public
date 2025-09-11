<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de confirmation d'import de matériel
 * Affiche les erreurs et warnings avant de procéder à l'import
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Vérifier que la validation existe
if (!isset($_SESSION['import_validation'])) {
    header('Location: ' . BASE_URL . 'materiel_bulk');
    exit;
}

$validation = $_SESSION['import_validation'];
$errors = $validation['errors'];
$warnings = $validation['warnings'];
$validRows = $validation['valid_rows'];
$totalRows = $validation['total_rows'];
$fileName = $validation['file_name'];

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Confirmation Import - Matériel',
    'materiel'
);

// Définir la page courante pour le menu
$currentPage = 'materiel';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête de page -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-check-circle me-2"></i>Confirmation Import - Matériel
            </h4>
            <p class="text-muted mb-0">
                Validation du fichier : <?= htmlspecialchars($fileName) ?>
            </p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>materiel_bulk" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>
        </div>
    </div>

    <!-- Résumé de validation -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-body-secondary border-bottom">
                    <h6 class="mb-0 text-body">
                        <i class="bi bi-info-circle me-2"></i>Résumé de la validation
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="text-primary mb-1"><?= $totalRows ?></h3>
                                <small class="text-muted">Lignes totales</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="text-success mb-1"><?= count($validRows) ?></h3>
                                <small class="text-muted">Lignes valides</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="text-danger mb-1"><?= count($errors) ?></h3>
                                <small class="text-muted">Erreurs</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Erreurs bloquantes -->
    <?php if (!empty($errors)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>Erreurs bloquantes (<?= count($errors) ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <strong>Attention :</strong> Les erreurs suivantes doivent être corrigées avant de pouvoir procéder à l'import.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th width="100">Ligne</th>
                                        <th>Erreur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($errors as $error): ?>
                                        <tr>
                                            <td class="fw-bold text-danger"><?= htmlspecialchars(explode(' : ', $error)[0]) ?></td>
                                            <td><?= htmlspecialchars(explode(' : ', $error)[1] ?? $error) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>



    <!-- Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if (empty($errors) && !empty($validRows)): ?>
                                <h6 class="text-success mb-1">
                                    <i class="bi bi-check-circle me-2"></i>Prêt pour l'import
                                </h6>
                                <p class="text-muted mb-0">
                                    <?= count($validRows) ?> matériels seront importés/mis à jour.
                                </p>
                            <?php elseif (empty($errors) && empty($validRows)): ?>
                                <h6 class="text-warning mb-1">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Aucune donnée à importer
                                </h6>
                                <p class="text-muted mb-0">
                                    Le fichier ne contient aucune ligne valide à importer.
                                </p>
                            <?php else: ?>
                                <h6 class="text-danger mb-1">
                                    <i class="bi bi-x-circle me-2"></i>Import impossible
                                </h6>
                                <p class="text-muted mb-0">
                                    Veuillez corriger les erreurs avant de réessayer.
                                </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if (empty($errors) && !empty($validRows)): ?>
                                <form action="<?= BASE_URL ?>materiel_bulk/process_bulk_import" method="POST" class="d-inline">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-circle me-2"></i>Confirmer l'import
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>materiel_bulk" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Retour
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
