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
    'Contrats',
    'contracts'
);

// Définir la page courante pour le menu
$currentPage = 'contracts';

// Inclure le header qui contient le menu latéral

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>



<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Gestion des Contrats</h4></div>

        <div class="ms-auto p-2 bd-highlight">
            <?php if (canManageContracts()): ?>
            <a href="<?php echo BASE_URL; ?>contracts/add" class="btn btn-primary">
                <i class="bi bi-plus me-1"></i> Nouveau contrat
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtres par statut -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card status-filter-card">
                <div class="card-body">
                    <!-- Filtres rapides par statut -->
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Tous les contrats -->
                        <?php 
                        $totalCount = 0;
                        foreach ($statsByStatus as $stat) {
                            $totalCount += $stat['count'];
                        }
                        ?>
                        <a href="<?php echo BASE_URL; ?>contracts?show_status=all" 
                           class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo ($current_filter_view ?? 'actif') === 'all' ? 'active' : ''; ?>">
                            <span class="badge bg-secondary me-1">
                                <?php echo $totalCount; ?>
                            </span>
                            Tous
                        </a>
                        
                        <!-- Filtres par statut -->
                        <?php foreach ($statsByStatus as $stat): ?>
                            <a href="<?php echo BASE_URL; ?>contracts?show_status=<?php echo $stat['status']; ?>" 
                               class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo ($current_filter_view ?? 'actif') === $stat['status'] ? 'active' : ''; ?>">
                                <span class="badge <?php echo $stat['color']; ?> me-1">
                                    <?php echo $stat['count']; ?>
                                </span>
                                <?php echo htmlspecialchars($stat['display_name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres par type de tickets -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card ticket-filter-card">
                <div class="card-body">
                    <h6 class="card-title mb-2">
                        <i class="bi bi-ticket-perforated me-1"></i>
                        Filtres par type de tickets
                    </h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Tous les types de tickets -->
                        <a href="<?php echo BASE_URL; ?>contracts?show_status=<?php echo $current_filter_view ?? 'actif'; ?>&ticket_type=all" 
                           class="btn btn-outline-secondary btn-sm ticket-filter-btn <?php echo ($current_ticket_filter ?? 'all') === 'all' ? 'active' : ''; ?>">
                            <i class="bi bi-funnel me-1"></i>
                            Tous les types
                        </a>
                        
                        <!-- Contrats avec tickets -->
                        <a href="<?php echo BASE_URL; ?>contracts?show_status=<?php echo $current_filter_view ?? 'actif'; ?>&ticket_type=with_tickets" 
                           class="btn btn-outline-info btn-sm ticket-filter-btn <?php echo ($current_ticket_filter ?? 'all') === 'with_tickets' ? 'active' : ''; ?>">
                            <i class="bi bi-ticket-perforated me-1"></i>
                            Avec tickets
                        </a>
                        
                        <!-- Contrats sans tickets -->
                        <a href="<?php echo BASE_URL; ?>contracts?show_status=<?php echo $current_filter_view ?? 'actif'; ?>&ticket_type=without_tickets" 
                           class="btn btn-outline-warning btn-sm ticket-filter-btn <?php echo ($current_ticket_filter ?? 'all') === 'without_tickets' ? 'active' : ''; ?>">
                            <i class="bi bi-ticket-perforated-fill me-1"></i>
                            Sans tickets
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

       
 
                    <div class="table-responsive">
                        <table id="contractsTable" class="table table-striped table-hover dt-responsive">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Type de contrat</th>
                                    <th>Nom</th>
                                    <th>Date de fin</th>
                                    <th>Tickets initiaux</th>
                                    <th>Tickets restants</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($contracts)): ?>
                                    <?php foreach ($contracts as $contract): ?>
                                        <tr>
                                            <td data-label="Client"><?php echo htmlspecialchars($contract['client_name'] ?? '-'); ?></td>
                                            <td data-label="Type de contrat"><?php echo htmlspecialchars($contract['contract_type_name'] ?? '-'); ?></td>
                                            <td data-label="Nom"><?php echo htmlspecialchars($contract['name'] ?? '-'); ?></td>
                                            <td data-label="Date de fin" data-order="<?php echo strtotime($contract['end_date']); ?>"><?php echo formatDateFrench($contract['end_date']); ?></td>
                                            <td data-label="Tickets initiaux" data-order="<?php echo $contract['tickets_number']; ?>">
                                                <?php if ($contract['tickets_number'] > 0): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo $contract['tickets_number']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        Sans tickets
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Tickets restants" data-order="<?php echo $contract['tickets_remaining']; ?>">
                                                <?php if ($contract['tickets_number'] > 0): ?>
                                                    <span class="badge bg-<?php echo $contract['tickets_remaining'] > 3 ? 'success' : 'danger'; ?>">
                                                        <?php echo $contract['tickets_remaining']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        Sans tickets
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Statut">
                                                <span class="badge bg-<?php 
                                                    echo $contract['status'] === 'actif' ? 'success' : 
                                                        ($contract['status'] === 'inactif' ? 'danger' : 
                                                        ($contract['status'] === 'en_attente' ? 'warning' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $contract['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="actions">
                                                <div class="d-flex flex-row gap-1">
                                                    <a href="<?php echo BASE_URL; ?>contracts/view/<?php echo $contract['id']; ?>" class="btn btn-sm btn-outline-info btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Voir">
                                                        <i class="<?php echo getIcon('show', 'bi bi-eye'); ?>"></i>
                                                    </a>
                                                    <?php if (canManageContracts()): ?>
                                                    <a href="<?php echo BASE_URL; ?>contracts/edit/<?php echo $contract['id']; ?>?return_to=contracts" class="btn btn-sm btn-outline-warning btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Modifier">
                                                        <i class="<?php echo getIcon('edit', 'bi bi-pencil'); ?>"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if ($isAdmin): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" onclick="confirmDelete(<?php echo $contract['id']; ?>, '<?php echo htmlspecialchars($contract['name'] ?? ''); ?>')" title="Supprimer">
                                                        <i class="<?php echo getIcon('delete', 'bi bi-trash'); ?>"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
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

<!-- Script pour la confirmation de suppression -->
<script>
function confirmDelete(contractId, contractName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer le contrat "' + contractName + '" ?')) {
        window.location.href = '<?php echo BASE_URL; ?>contracts/delete/' + contractId;
    }
}
</script>

<!-- DataTable Persistence -->
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script src="<?php echo BASE_URL; ?>assets/js/contracts-datatable.js"></script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 

<style>
/* Styles pour les filtres de tickets */
.ticket-filter-card {
    border-left: 4px solid #17a2b8;
}

.ticket-filter-card .card-title {
    color: #17a2b8;
    font-weight: 600;
}

.ticket-filter-btn {
    transition: all 0.2s ease;
}

.ticket-filter-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.ticket-filter-btn.active {
    font-weight: 600;
}

/* Styles pour les filtres de statut existants */
.status-filter-card {
    border-left: 4px solid #6c757d;
}

.status-filter-card .card-title {
    color: #6c757d;
    font-weight: 600;
}

.status-filter-btn {
    transition: all 0.2s ease;
}

.status-filter-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-filter-btn.active {
    font-weight: 600;
}

/* Responsive design pour les filtres */
@media (max-width: 768px) {
    .d-flex.flex-wrap.gap-2 {
        gap: 0.5rem !important;
    }
    
    .btn-sm {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
}
</style> 