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
$userType = $_SESSION['user']['type'] ?? null;

setPageVariables(
    'Interventions',
    'interventions'
);


// Inclure le header qui contient le menu latéral

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Gestion des Interventions</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <?php if (canModifyInterventions()): ?>
            <a href="<?php echo BASE_URL; ?>interventions/add" class="btn btn-primary">
                <i class="bi bi-plus me-1 me-1"></i> Ajouter une intervention
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Onglets de navigation -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-custom" id="interventionTabs" role="tablist">
                    <!-- Onglet Non-Préventives -->
                    <?php 
                    $nonPreventiveUrl = BASE_URL . 'interventions?tab=non-preventive';
                    if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                        $nonPreventiveUrl .= '&technician_id=' . $_GET['technician_id'];
                    }
                    if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                        $nonPreventiveUrl .= '&status_id=' . $_GET['status_id'];
                    }
                    ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($activeTab === 'non-preventive') ? 'active' : ''; ?>" 
                           href="<?php echo $nonPreventiveUrl; ?>" 
                           role="tab">
                            <i class="bi bi-tools me-2"></i>
                            Interventions Curatives
                            <span class="badge bg-secondary ms-2"><?php echo $statsByTab['non-preventive']['total'] ?? 0; ?></span>
                        </a>
                    </li>
                    
                    <!-- Onglet Préventives -->
                    <?php if (isset($preventivePriorityId) && $preventivePriorityId): ?>
                        <?php 
                        $preventiveUrl = BASE_URL . 'interventions?tab=preventive';
                        if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                            $preventiveUrl .= '&technician_id=' . $_GET['technician_id'];
                        }
                        if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                            $preventiveUrl .= '&status_id=' . $_GET['status_id'];
                        }
                        ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo ($activeTab === 'preventive') ? 'active' : ''; ?>" 
                               href="<?php echo $preventiveUrl; ?>" 
                               role="tab">
                                <i class="bi bi-shield-check me-2"></i>
                                Interventions Préventives
                                <span class="badge bg-success ms-2"><?php echo $statsByTab['preventive']['total'] ?? 0; ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Onglet Toutes -->
                    <?php 
                    $allUrl = BASE_URL . 'interventions?tab=all';
                    if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                        $allUrl .= '&technician_id=' . $_GET['technician_id'];
                    }
                    if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                        $allUrl .= '&status_id=' . $_GET['status_id'];
                    }
                    ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($activeTab === 'all') ? 'active' : ''; ?>" 
                           href="<?php echo $allUrl; ?>" 
                           role="tab">
                            <i class="bi bi-collection me-2"></i>
                            Toutes les Interventions
                            <span class="badge bg-primary ms-2"><?php echo $statsByTab['all']['total'] ?? 0; ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Filtres par staff et statut -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card status-filter-card">
            <div class="card-body">
                <!-- Filtre par staff -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-end gap-3">
                            <div class="flex-grow-1">
                                <label for="technician_filter" class="form-label">Filtrer par technicien :</label>
                                <select id="technician_filter" class="form-select" onchange="filterByTechnician(this.value)">
                                    <option value="">Tous les techniciens</option>
                                    <?php foreach ($technicians as $technician): ?>
                                        <option value="<?php echo $technician['id']; ?>" 
                                                <?php echo (isset($_GET['technician_id']) && $_GET['technician_id'] == $technician['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($technician['first_name'] . ' ' . $technician['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Mes interventions (pour les techniciens) -->
                            <?php if ($userType === 'technicien' || $userType === 'admin'): ?>
                                <?php 
                                $myInterventionsUrl = BASE_URL . 'interventions?tab=' . $activeTab . '&technician_id=' . $_SESSION['user']['id'];
                                if (isset($_GET['status_id'])) {
                                    $myInterventionsUrl .= '&status_id=' . $_GET['status_id'];
                                }
                                if (isset($_GET['priority_id'])) {
                                    $myInterventionsUrl .= '&priority_id=' . $_GET['priority_id'];
                                }
                                ?>
                                <div class="d-flex align-items-end">
                                    <a href="<?php echo $myInterventionsUrl; ?>" 
                                       class="btn btn-outline-primary <?php echo (isset($_GET['technician_id']) && $_GET['technician_id'] == $_SESSION['user']['id']) ? 'active' : ''; ?>">
                                        <i class="fas fa-user me-1"></i>
                                        Mes interventions
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Filtres rapides par statut et priorité -->
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <!-- Filtres par statut -->
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Tous les statuts -->
                        <?php 
                        $allUrl = BASE_URL . 'interventions?tab=' . $activeTab;
                        
                        // Si un technicien est sélectionné, l'ajouter à l'URL
                        if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                            $allUrl .= '&technician_id=' . $_GET['technician_id'];
                        }
                        ?>
                        <a href="<?php echo $allUrl; ?>" class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (!isset($_GET['status_id'])) ? 'active' : ''; ?>">
                            <span class="badge bg-secondary me-1"><?php echo array_sum(array_column($statsByStatus, 'count')); ?></span>
                            Tous les statuts
                        </a>
                        
                        <!-- Filtres par statut -->
                        <?php foreach ($statsByStatus as $statusStat): ?>
                            <?php 
                            $statusUrl = BASE_URL . 'interventions?tab=' . $activeTab . '&status_id=' . $statusStat['id'];
                            
                            // Si un technicien est sélectionné, l'ajouter à l'URL
                            if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                                $statusUrl .= '&technician_id=' . $_GET['technician_id'];
                            }
                            ?>
                            <a href="<?php echo $statusUrl; ?>" 
                               class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (isset($_GET['status_id']) && $_GET['status_id'] == $statusStat['id']) ? 'active' : ''; ?>">
                                <span class="badge me-1" style="background-color: <?php echo $statusStat['color']; ?>">
                                    <?php echo $statusStat['count']; ?>
                                </span>
                                <?php echo htmlspecialchars($statusStat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Séparateur -->
                    <div class="vr mx-2"></div>
                    
                    <!-- Filtres par priorité -->
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Toutes les priorités -->
                        <?php 
                        $allPriorityUrl = BASE_URL . 'interventions?tab=' . $activeTab;
                        
                        // Conserver les filtres existants
                        $params = [];
                        if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                            $params[] = 'technician_id=' . $_GET['technician_id'];
                        }
                        if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                            $params[] = 'status_id=' . $_GET['status_id'];
                        }
                        if (!empty($params)) {
                            $allPriorityUrl .= '&' . implode('&', $params);
                        }
                        ?>
                        <a href="<?php echo $allPriorityUrl; ?>" class="btn btn-outline-secondary btn-sm priority-filter-btn <?php echo (!isset($_GET['priority_id'])) ? 'active' : ''; ?>">
                            Toutes les priorités
                        </a>
                        
                        <!-- Filtres par priorité (sauf Préventif) -->
                        <?php foreach ($priorities as $priority): ?>
                            <?php 
                            // Exclure la priorité Préventif car elle est gérée par les onglets
                            if (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false) {
                                continue;
                            }
                            
                            $priorityUrl = BASE_URL . 'interventions?tab=' . $activeTab . '&priority_id=' . $priority['id'];
                            
                            // Conserver les autres filtres
                            if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                                $priorityUrl .= '&technician_id=' . $_GET['technician_id'];
                            }
                            if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                                $priorityUrl .= '&status_id=' . $_GET['status_id'];
                            }
                            ?>
                            <a href="<?php echo $priorityUrl; ?>" 
                               class="btn btn-outline-secondary btn-sm priority-filter-btn <?php echo (isset($_GET['priority_id']) && $_GET['priority_id'] == $priority['id']) ? 'active' : ''; ?>">
                                <span class="badge me-1" style="background-color: <?php echo $priority['color']; ?>">
                                    <?php echo htmlspecialchars($priority['name']); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

                        <div class="table-responsive">
                            <table id="interventionsTable" class="table table-striped table-hover dt-responsive">
                                <thead>
                                    <tr>
                                        <th>Référence</th>
                                        <th>Titre</th>
                                        <th>Client</th>
                                        <th>Site</th>
                                        <th>Salle</th>
                                        <th>Statut</th>
                                        <th>Priorité</th>
                                        <th>Date planifiée</th>
                                        <th>Technicien</th>
                                        <th>Date création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($interventions) && !empty($interventions)): ?>
                                        <?php foreach ($interventions as $intervention): ?>
                                            <tr>
                                                <td data-label="Référence">
                                                    <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" 
                                                       class="text-decoration-none fw-bold" 
                                                       title="Voir l'intervention">
                                                        <?php echo htmlspecialchars($intervention['reference'] ?? ''); ?>
                                                    </a>
                                                </td>
                                                <td data-label="Titre"><?php echo htmlspecialchars($intervention['title'] ?? ''); ?></td>
                                                <td data-label="Client"><?php echo htmlspecialchars($intervention['client_name'] ?? ''); ?></td>
                                                <td data-label="Site"><?php echo htmlspecialchars($intervention['site_name'] ?? '-'); ?></td>
                                                <td data-label="Salle"><?php echo htmlspecialchars($intervention['room_name'] ?? '-'); ?></td>
                                                <td data-label="Statut" data-order="<?php echo $intervention['status_id'] ?? 0; ?>">
                                                    <span class="badge rounded-pill" style="background-color: <?php echo $intervention['status_color'] ?? ''; ?>">
                                                        <?php echo htmlspecialchars($intervention['status_name'] ?? ''); ?>
                                                    </span>
                                                </td>
                                                <td data-label="Priorité" data-order="<?php echo $intervention['priority_id'] ?? 0; ?>">
                                                    <span class="badge rounded-pill" style="background-color: <?php echo $intervention['priority_color'] ?? ''; ?>">
                                                        <?php echo htmlspecialchars($intervention['priority_name'] ?? ''); ?>
                                                    </span>
                                                </td>
                                                                <td data-label="Date planifiée" data-order="<?php echo isset($intervention['date_planif']) ? strtotime($intervention['date_planif']) : 0; ?>">
                    <?php echo !empty($intervention['date_planif']) ? formatDateFrench($intervention['date_planif']) : '-'; ?>
                                                </td>
                                                <td data-label="Technicien"><?php echo htmlspecialchars($intervention['technician_first_name'] ?? '') . ' ' . htmlspecialchars($intervention['technician_last_name'] ?? ''); ?></td>
                                                                <td data-label="Date création" data-order="<?php echo isset($intervention['created_at']) ? strtotime($intervention['created_at']) : 0; ?>">
                    <?php echo formatDateFrench($intervention['created_at']) . ' ' . date('H:i', strtotime($intervention['created_at'] ?? '')); ?>
                                                </td>
                                                <td class="actions">
                                                    <div class="d-flex flex-row gap-1">
                                                        <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" class="btn btn-sm btn-outline-info btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Voir">
                                                            <i class="<?php echo getIcon('visibility', 'bi bi-eye'); ?>"></i>
                                                        </a>
                                                        <a href="<?php echo BASE_URL; ?>interventions/edit/<?php echo $intervention['id']; ?>" class="btn btn-sm btn-outline-warning btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Modifier">
                                                        <i class="<?php echo getIcon('edit', 'bi bi-pencil'); ?>"></i>
                                                        </a>
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

<!-- Styles personnalisés pour les onglets -->
<style>
.nav-tabs-custom {
    border-bottom: 2px solid #e9ecef;
}

.nav-tabs-custom .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 500;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
}

.nav-tabs-custom .nav-link:hover {
    border-color: #dee2e6;
    color: #495057;
    background-color: #f8f9fa;
}

.nav-tabs-custom .nav-link.active {
    border-bottom-color: #0d6efd;
    color: #0d6efd;
    background-color: #fff;
    font-weight: 600;
}

.nav-tabs-custom .nav-link .badge {
    font-size: 0.75rem;
    font-weight: 500;
}

/* Animation subtile pour les onglets */
.nav-tabs-custom .nav-link {
    position: relative;
    overflow: hidden;
}

.nav-tabs-custom .nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 3px;
    background-color: #0d6efd;
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.nav-tabs-custom .nav-link.active::after {
    width: 100%;
}
</style>

<!-- DataTable Persistence -->
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script src="<?php echo BASE_URL; ?>assets/js/interventions-datatable.js"></script>

<script>
function filterByTechnician(technicianId) {
    // Construire l'URL avec le filtre technicien
    let url = '<?php echo BASE_URL; ?>interventions';
    let params = [];
    
    // Conserver l'onglet actif
    params.push('tab=<?php echo $activeTab; ?>');
    
    if (technicianId) {
        params.push('technician_id=' + technicianId);
    }
    
    // Conserver les autres filtres existants
    <?php if (isset($_GET['status_id'])): ?>
        params.push('status_id=<?php echo $_GET['status_id']; ?>');
    <?php endif; ?>
    
    <?php if (isset($_GET['priority_id'])): ?>
        params.push('priority_id=<?php echo $_GET['priority_id']; ?>');
    <?php endif; ?>
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    // Rediriger vers la nouvelle URL
    window.location.href = url;
}
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 