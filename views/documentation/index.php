<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste des clients
 * Affiche la liste de tous les clients avec leurs statistiques
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Documentation',
    'documentation'
);

// Définir la page courante pour le menu
$currentPage = 'documentation';

// Inclure le header qui contient le menu latéral

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
<h4 class="py-4 mb-6">Documentation</h4>

            <!-- Tableau des localisations -->

                    <div class="table-responsive">
                        <table id="documentationTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Statut</th>
                                    <th>Nombre de documents</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($client_docs) && !empty($client_docs)): ?>
                                    <?php foreach ($client_docs as $doc): ?>
                                        <tr>
                                            <td data-label="Client"><?= htmlspecialchars($doc['client_name']) ?></td>
                                            <td data-label="Statut" data-order="<?= $doc['client_status'] ?>">
                                                <?= $doc['client_status'] ? 'Actif' : 'Inactif' ?>
                                            </td>
                                            <td data-label="Nombre de documents" data-order="<?= $doc['doc_count'] ?>">
                                                <?= $doc['doc_count'] ?>
                                            </td>
                                            <td class="actions">
                                                <a href="<?php echo BASE_URL; ?>documentation/view/<?php echo $doc['client_id']; ?>" class="btn btn-sm btn-outline-info" title="Voir">
                                                    <i class="<?php echo getIcon('show', 'bi bi-info-circle'); ?>"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php // Laisser tbody vide. DataTables utilisera language.emptyTable ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>



<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>

