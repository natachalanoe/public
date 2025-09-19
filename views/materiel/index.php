<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste du matériel
 * Affiche la liste du matériel regroupé par site/salle avec filtres
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Matériel',
    'materiel'
);

// Définir la page courante pour le menu
$currentPage = 'materiel';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$materiel_list = $materiel_list ?? [];
$clients = $clients ?? [];
$sites = $sites ?? [];
$salles = $salles ?? [];
$visibilites_champs = $visibilites_champs ?? [];
$pieces_jointes_count = $pieces_jointes_count ?? [];
$filters = $filters ?? [];

// Récupérer les statistiques
$stats = [];
if (isset($materielModel)) {
    $stats = $materielModel->getStats();
}

// Organiser le matériel par client/site/salle
$materiel_organise = [];
foreach ($materiel_list as $materiel) {
    $client_id = $materiel['client_nom'] ?? 'Sans client';
    $site_id = $materiel['site_nom'] ?? 'Sans site';
    $salle_id = $materiel['salle_nom'] ?? 'Sans salle';
    
    if (!isset($materiel_organise[$client_id])) {
        $materiel_organise[$client_id] = [];
    }
    if (!isset($materiel_organise[$client_id][$site_id])) {
        $materiel_organise[$client_id][$site_id] = [];
    }
    if (!isset($materiel_organise[$client_id][$site_id][$salle_id])) {
        $materiel_organise[$client_id][$site_id][$salle_id] = [];
    }
    
    $materiel_organise[$client_id][$site_id][$salle_id][] = $materiel;
}
?>

<style>
.attachments-row {
    background-color: var(--bs-body-bg);
}

.attachments-row .card {
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.attachments-list .list-group-item {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.375rem;
    transition: all 0.2s ease-in-out;
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    padding: 0.5rem;
    margin-bottom: 0.5rem;
}

.attachments-list .list-group-item:hover {
    background-color: var(--bs-secondary-bg);
    border-color: var(--bs-primary);
    box-shadow: 0 2px 4px rgba(var(--bs-primary-rgb), 0.15);
}

.attachments-list .col-md-6:nth-child(odd) .list-group-item {
    margin-right: 0.25rem;
}

.attachments-list .col-md-6:nth-child(even) .list-group-item {
    margin-left: 0.25rem;
}

.btn-action {
    transition: all 0.2s ease-in-out;
}

.btn-action:hover {
    transform: scale(1.05);
}

.attachments-row td {
    border-top: none;
    border-bottom: 1px solid var(--bs-border-color);
}

.min-w-0 {
    min-width: 0;
}

.attachments-list .btn-group {
    flex-shrink: 0;
}

/* Styles pour la zone d'upload */
.upload-zone {
    transition: all 0.3s ease;
}

.upload-zone .card {
    border-style: dashed !important;
    transition: all 0.3s ease;
}

.upload-zone .card:hover {
    border-color: var(--bs-primary) !important;
    background-color: var(--bs-primary-bg-subtle) !important;
}

.upload-zone.dragover .card {
    border-color: var(--bs-success) !important;
    background-color: var(--bs-success-bg-subtle) !important;
    transform: scale(1.02);
}

.upload-zone.dragover .card-body {
    background-color: var(--bs-success-bg-subtle) !important;
}

.border-dashed {
    border-style: dashed !important;
}
</style>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec titre et bouton d'ajout -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-hdd-network me-2 me-1"></i>Liste du Matériel
                    </h4>
                    <p class="text-muted mb-0">Gestion et suivi du matériel par site et salle</p>
                </div>
                <div class="d-flex gap-2">
                    <?php
                    // Construire l'URL d'ajout avec les paramètres de filtres
                    $addParams = [];
                    if (!empty($filters['client_id'])) {
                        $addParams['client_id'] = $filters['client_id'];
                    }
                    if (!empty($filters['site_id'])) {
                        $addParams['site_id'] = $filters['site_id'];
                    }
                    if (!empty($filters['salle_id'])) {
                        $addParams['salle_id'] = $filters['salle_id'];
                    }
                    
                    $addUrl = BASE_URL . 'materiel/add';
                    if (!empty($addParams)) {
                        $addUrl .= '?' . http_build_query($addParams);
                    }
                    ?>
                    <a href="<?= $addUrl ?>" class="btn btn-primary">
                        <i class="bi bi-plus me-2 me-1"></i>Ajouter du Matériel
                    </a>
                    
                                        <?php if (canImportMateriel()): ?>
                        <!-- Bouton pour l'import/export en masse -->
                        <?php
                        $bulkParams = [];
                        if (!empty($filters['client_id'])) {
                            $bulkParams['client_id'] = $filters['client_id'];
                        }
                        if (!empty($filters['site_id'])) {
                            $bulkParams['site_id'] = $filters['site_id'];
                        }
                        
                        $bulkUrl = BASE_URL . 'materiel_bulk';
                        if (!empty($bulkParams)) {
                            $bulkUrl .= '?' . http_build_query($bulkParams);
                        }
                        ?>
                        <a href="<?= $bulkUrl ?>" class="btn btn-info">
                            <i class="bi bi-arrow-left-right me-2 me-1"></i>Import/Export en Masse
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <?php if (!empty($stats)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card border-0 bg-primary bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-hdd-network fa-2x text-primary me-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-primary fw-bold"><?= $stats['total'] ?? 0 ?></h6>
                                    <small class="text-muted">Total Matériel</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-success bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-wifi fa-2x text-success"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-success fw-bold"><?= $stats['online'] ?? 0 ?></h6>
                                    <small class="text-muted">En Ligne</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-warning bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-tools fa-2x text-warning me-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-warning fw-bold"><?= $stats['maintenance_expired'] ?? 0 ?></h6>
                                    <small class="text-muted">Maintenance Expirée</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-danger bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-certificate fa-2x text-danger"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-danger fw-bold"><?= $stats['garantie_expired'] ?? 0 ?></h6>
                                    <small class="text-muted">Garantie Expirée</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header py-2">
            <h6 class="card-title mb-0">Filtres</h6>
        </div>
        <div class="card-body py-2">
            <form method="get" action="" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label for="client_id" class="form-label fw-bold mb-0">Client</label>
                    <select class="form-select bg-body text-body" id="client_id" name="client_id" onchange="updateSitesAndSubmit()">
                        <option value="">Tous les clients</option>
                        <?php if (isset($clients) && is_array($clients)): ?>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= ($filters['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="site_id" class="form-label fw-bold mb-0">Site</label>
                    <select class="form-select bg-body text-body" id="site_id" name="site_id" onchange="updateRoomsAndSubmit()">
                        <option value="">Tous les sites</option>
                        <?php if (isset($sites) && is_array($sites)): ?>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= ($filters['site_id'] ?? '') == $site['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($site['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="salle_id" class="form-label fw-bold mb-0">Salle</label>
                    <select class="form-select bg-body text-body" id="salle_id" name="salle_id" onchange="document.getElementById('filterForm').submit();">
                        <option value="">Toutes les salles</option>
                        <?php foreach ($salles as $salle): ?>
                            <option value="<?= $salle['id'] ?>" <?= ($filters['salle_id'] ?? '') == $salle['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($salle['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <a href="<?= BASE_URL ?>materiel" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-2 me-1"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste du matériel organisée -->
    <?php if (empty($filters['client_id'])): ?>
        <!-- Message d'instruction quand aucun client n'est sélectionné -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-filter fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Sélectionnez un client pour voir le matériel</h5>
                <p class="text-muted mb-3">Choisissez un client dans le filtre ci-dessus pour afficher le matériel associé.</p>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2 me-1"></i>
                            <strong>Astuce :</strong> Commencez par sélectionner un client, puis un site et enfin une salle pour affiner votre recherche.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (empty($materiel_organise)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-hdd-network fa-3x text-muted mb-3 me-1"></i>
                <h5 class="text-muted">Aucun matériel trouvé</h5>
                <p class="text-muted mb-3">Aucun matériel ne correspond aux critères sélectionnés.</p>
                <?php
                // Construire l'URL d'ajout avec les paramètres de filtres
                $addParams = [];
                if (!empty($filters['client_id'])) {
                    $addParams['client_id'] = $filters['client_id'];
                }
                if (!empty($filters['site_id'])) {
                    $addParams['site_id'] = $filters['site_id'];
                }
                if (!empty($filters['salle_id'])) {
                    $addParams['salle_id'] = $filters['salle_id'];
                }
                
                $addUrl = BASE_URL . 'materiel/add';
                if (!empty($addParams)) {
                    $addUrl .= '?' . http_build_query($addParams);
                }
                ?>
                <a href="<?= $addUrl ?>" class="btn btn-primary">
                    <i class="bi bi-plus me-2 me-1"></i>Ajouter du Matériel
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($materiel_organise as $client_nom => $sites): ?>
            <div class="card mb-4">
                <div class="card-header bg-body-secondary">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-building me-2 text-primary me-1"></i>
                        <?= htmlspecialchars($client_nom) ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($sites as $site_nom => $salles): ?>
                        <div class="border-bottom">
                            <div class="p-3 bg-body-secondary bg-opacity-10">
                                <h6 class="mb-0">
                                    <i class="bi bi-geo-alt me-2 text-success me-1"></i>
                                    <?= htmlspecialchars($site_nom) ?>
                                </h6>
                            </div>
                            <?php foreach ($salles as $salle_nom => $materiels): ?>
                                <div class="border-bottom">
                                    <div class="p-3">
                                        <h6 class="mb-3">
                                            <i class="bi bi-door-open me-2 text-info me-1"></i>
                                            <?= htmlspecialchars($salle_nom) ?>
                                            <span class="badge bg-secondary ms-2"><?= count($materiels) ?> équipement(s)</span>
                                        </h6>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm mb-0">
                                                <thead class="bg-body-secondary">
                                                    <tr>
                                                        <th>Équipement</th>
                                                        <th>Type</th>
                                                        <th>S/N</th>
                                                        <th>Firmware</th>
                                                        <th>IP</th>
                                                        <th>MAC</th>
                                                        <th>Expiration</th>
                                                        <th>Pièces jointes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($materiels as $materiel): ?>
                                                        <tr>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['marque']) && !$visibilites_champs[$materiel['id']]['marque']) || (isset($visibilites_champs[$materiel['id']]['modele']) && !$visibilites_champs[$materiel['id']]['modele']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php
                                                                // Construire les paramètres de filtres pour les liens
                                                                $filterParams = [];
                                                                if (!empty($filters['client_id'])) {
                                                                    $filterParams['client_id'] = $filters['client_id'];
                                                                }
                                                                if (!empty($filters['site_id'])) {
                                                                    $filterParams['site_id'] = $filters['site_id'];
                                                                }
                                                                if (!empty($filters['salle_id'])) {
                                                                    $filterParams['salle_id'] = $filters['salle_id'];
                                                                }
                                                                
                                                                $viewUrl = BASE_URL . 'materiel/view/' . $materiel['id'];
                                                                if (!empty($filterParams)) {
                                                                    $viewUrl .= '?' . http_build_query($filterParams);
                                                                }
                                                                ?>
                                                                <a href="<?= $viewUrl ?>" 
                                                                   class="text-decoration-none" 
                                                                   title="Voir le matériel">
                                                                    <div class="fw-bold"><?= htmlspecialchars($materiel['marque'] ?? 'Marque non définie') ?></div>
                                                                    <small class="text-muted"><?= htmlspecialchars($materiel['modele'] ?? 'Modèle non défini') ?></small>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <?= htmlspecialchars($materiel['type_nom'] ?? 'Type non défini') ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['numero_serie']) && !$visibilites_champs[$materiel['id']]['numero_serie']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php if (!empty($materiel['numero_serie'])): ?>
                                                                    <?= htmlspecialchars($materiel['numero_serie']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['version_firmware']) && !$visibilites_champs[$materiel['id']]['version_firmware']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php if (!empty($materiel['version_firmware'])): ?>
                                                                    <?= htmlspecialchars($materiel['version_firmware']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['adresse_ip']) && !$visibilites_champs[$materiel['id']]['adresse_ip']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php if (!empty($materiel['adresse_ip'])): ?>
                                                                    <?= htmlspecialchars($materiel['adresse_ip']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['adresse_mac']) && !$visibilites_champs[$materiel['id']]['adresse_mac']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php if (!empty($materiel['adresse_mac'])): ?>
                                                                    <?= htmlspecialchars($materiel['adresse_mac']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['date_fin_maintenance']) && !$visibilites_champs[$materiel['id']]['date_fin_maintenance']) || (isset($visibilites_champs[$materiel['id']]['date_fin_garantie']) && !$visibilites_champs[$materiel['id']]['date_fin_garantie']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php 
                                                                $today = new DateTime();
                                                                $maintenance_info = [];
                                                                
                                                                if (!empty($materiel['date_fin_maintenance'])) {
                                                                    $maintenance_date = new DateTime($materiel['date_fin_maintenance']);
                                                                    $maintenance_class = $maintenance_date < $today ? 'danger' : ($maintenance_date->diff($today)->days < 30 ? 'warning' : 'success');
                                                                    $maintenance_info[] = '<div class="d-flex justify-content-between align-items-center"><small class="text-muted">Maintenance</small> <span class="text-' . $maintenance_class . '">' . formatDateFrench($materiel['date_fin_maintenance']) . '</span></div>';
                                                                }
                                                                
                                                                if (!empty($materiel['date_fin_garantie'])) {
                                                                    $garantie_date = new DateTime($materiel['date_fin_garantie']);
                                                                    $garantie_class = $garantie_date < $today ? 'danger' : ($garantie_date->diff($today)->days < 30 ? 'warning' : 'success');
                                                                    $maintenance_info[] = '<div class="d-flex justify-content-between align-items-center"><small class="text-muted">Garantie</small> <span class="text-' . $garantie_class . '">' . formatDateFrench($materiel['date_fin_garantie']) . '</span></div>';
                                                                }
                                                                
                                                                if (!empty($maintenance_info)) {
                                                                    echo implode('', $maintenance_info);
                                                                } else {
                                                                    echo '<span class="text-muted">-</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <?php if (isset($pieces_jointes_count[$materiel['id']]) && $pieces_jointes_count[$materiel['id']] > 0): ?>
                                                                    <button class="btn btn-sm btn-outline-info" 
                                                                            onclick="toggleAttachments(<?= $materiel['id'] ?>)"
                                                                            title="Voir les pièces jointes">
                                                                        <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?>"></i>
                                                                        <span class="badge bg-info ms-1"><?= $pieces_jointes_count[$materiel['id']] ?></span>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button class="btn btn-sm btn-outline-secondary" 
                                                                            onclick="toggleAttachments(<?= $materiel['id'] ?>)"
                                                                            title="Voir les pièces jointes (aucune)">
                                                                        <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?>"></i>
                                                                        <span class="badge bg-secondary ms-1">0</span>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <!-- Ligne d'accordéon pour les pièces jointes -->
                                                        <tr id="attachments-<?= $materiel['id'] ?>" class="attachments-row" style="display: none;">
                                                            <td colspan="8" class="p-0">
                                                                <div class="card border-0 m-2">
                                                                    <div class="card-body p-3">
                                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                                            <h6 class="mb-0">
                                                                                <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?> me-2"></i>
                                                                                Pièces jointes (<?= $pieces_jointes_count[$materiel['id']] ?? 0 ?>)
                                                                            </h6>
                                                                        </div>
                                                                        <div class="row">
                                                                            <!-- Colonne des pièces jointes (4/5) -->
                                                                            <div class="col-md-10">
                                                                                <div id="attachments-list-<?= $materiel['id'] ?>" class="attachments-list">
                                                                                    <div class="text-center py-3">
                                                                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                                                            <span class="visually-hidden">Chargement...</span>
                                                                                        </div>
                                                                                        <small class="text-muted ms-2">Chargement des pièces jointes...</small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            
                                                                            <!-- Colonne d'upload rapide (1/5) -->
                                                                            <div class="col-md-2">
                                                                                <div class="upload-zone" id="upload-zone-<?= $materiel['id'] ?>">
                                                                                    <div class="card border-dashed border-2 border-primary bg-light">
                                                                                        <div class="card-body text-center p-2">
                                                                                            <i class="bi bi-cloud-upload fs-4 text-primary mb-1"></i>
                                                                                            <h6 class="mb-1 small">Ajouter</h6>
                                                                                            <p class="small text-muted mb-2" style="font-size: 0.7rem;">Glissez-déposez ou cliquez</p>
                                                                                            
                                                                                            <input type="file" 
                                                                                                   id="file-input-<?= $materiel['id'] ?>" 
                                                                                                   class="d-none" 
                                                                                                   multiple 
                                                                                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.zip,.rar">
                                                                                            
                                                                                                                         <button type="button" 
                                     class="btn btn-sm btn-primary btn-sm" 
                                     style="font-size: 0.7rem; padding: 0.25rem 0.5rem;"
                                     onclick="document.getElementById('file-input-<?= $materiel['id'] ?>').click()">
                                 <i class="bi bi-folder-plus me-1"></i>Fichiers
                             </button>
                             
                             <div class="form-check mt-2" style="font-size: 0.7rem;">
                                 <input class="form-check-input" 
                                        type="checkbox" 
                                        id="masque-client-<?= $materiel['id'] ?>" 
                                        style="transform: scale(0.8);">
                                 <label class="form-check-label text-muted" for="masque-client-<?= $materiel['id'] ?>">
                                     Masquer au client
                                 </label>
                             </div>
                                                                                            
                                                                                            <div class="upload-progress mt-2" id="upload-progress-<?= $materiel['id'] ?>" style="display: none;">
                                                                                                <div class="progress" style="height: 0.5rem;">
                                                                                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                                                                                </div>
                                                                                                <small class="text-muted" style="font-size: 0.6rem;">Upload...</small>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Variable globale pour l'URL de base
const baseUrl = '<?= BASE_URL ?>';

// Fonction pour mettre à jour les sites selon le client sélectionné ET soumettre le formulaire
function updateSitesAndSubmit() {
    const clientId = document.getElementById('client_id').value;
    console.log('updateSitesAndSubmit appelé avec clientId:', clientId);
    
    if (clientId) {
        const url = '<?= BASE_URL ?>materiel/get_sites?client_id=' + clientId;
        console.log('URL de la requête:', url);
        
        fetch(url)
            .then(response => {
                console.log('Réponse reçue:', response);
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Données reçues:', data);
                const siteSelect = document.getElementById('site_id');
                siteSelect.innerHTML = '<option value="">Tous les sites</option>';
                
                if (Array.isArray(data)) {
                    data.forEach(site => {
                        const option = document.createElement('option');
                        option.value = site.id;
                        option.textContent = site.name;
                        siteSelect.appendChild(option);
                    });
                }
                
                // Réinitialiser les salles
                document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
                
                // Soumettre le formulaire après la mise à jour
                document.getElementById('filterForm').submit();
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des sites:', error);
                alert('Erreur lors de la mise à jour des sites: ' + error.message);
            });
    } else {
        document.getElementById('site_id').innerHTML = '<option value="">Tous les sites</option>';
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        
        // Soumettre le formulaire même si aucun client n'est sélectionné
        document.getElementById('filterForm').submit();
    }
}

// Fonction pour mettre à jour les salles selon le site sélectionné ET soumettre le formulaire
function updateRoomsAndSubmit() {
    const siteId = document.getElementById('site_id').value;
    console.log('updateRoomsAndSubmit appelé avec siteId:', siteId);
    
    if (siteId) {
        const url = '<?= BASE_URL ?>materiel/get_rooms?site_id=' + siteId;
        console.log('URL de la requête:', url);
        
        fetch(url)
            .then(response => {
                console.log('Réponse reçue:', response);
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Données reçues:', data);
                const roomSelect = document.getElementById('salle_id');
                roomSelect.innerHTML = '<option value="">Toutes les salles</option>';
                
                if (Array.isArray(data)) {
                    data.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.name;
                        roomSelect.appendChild(option);
                    });
                }
                
                // Soumettre le formulaire après la mise à jour
                document.getElementById('filterForm').submit();
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des salles:', error);
                alert('Erreur lors de la mise à jour des salles: ' + error.message);
            });
    } else {
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        
        // Soumettre le formulaire même si aucun site n'est sélectionné
        document.getElementById('filterForm').submit();
    }
}

// Fonction pour supprimer un matériel
function deleteMateriel(materielId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce matériel ? Cette action est irréversible.')) {
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        
        // Filtrer seulement les paramètres de filtres
        const filterParams = new URLSearchParams();
        if (params.has('client_id')) {
            filterParams.set('client_id', params.get('client_id'));
        }
        if (params.has('site_id')) {
            filterParams.set('site_id', params.get('site_id'));
        }
        if (params.has('salle_id')) {
            filterParams.set('salle_id', params.get('salle_id'));
        }
        
        window.location.href = `<?= BASE_URL ?>materiel/delete/${materielId}?${filterParams.toString()}`;
    }
}

// Fonction pour basculer l'affichage des pièces jointes
function toggleAttachments(materielId) {
    const row = document.getElementById(`attachments-${materielId}`);
    const list = document.getElementById(`attachments-list-${materielId}`);
    
    if (row.style.display === 'none') {
        // Charger les pièces jointes si pas encore fait
        if (!list.hasAttribute('data-loaded')) {
            loadAttachments(materielId);
        }
        row.style.display = 'table-row';
    } else {
        row.style.display = 'none';
    }
}

// Fonction pour charger les pièces jointes via AJAX
function loadAttachments(materielId) {
    const list = document.getElementById(`attachments-list-${materielId}`);
    
    fetch(`<?= BASE_URL ?>materiel/getAttachments/${materielId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur HTTP: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.attachments) {
                renderAttachments(materielId, data.attachments);
                list.setAttribute('data-loaded', 'true');
            } else {
                list.innerHTML = '<div class="text-center py-3"><small class="text-muted">Erreur lors du chargement des pièces jointes</small></div>';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des pièces jointes:', error);
            list.innerHTML = '<div class="text-center py-3"><small class="text-danger">Erreur lors du chargement des pièces jointes</small></div>';
        });
}

// Fonction pour afficher les pièces jointes
function renderAttachments(materielId, attachments) {
    const list = document.getElementById(`attachments-list-${materielId}`);
    
    if (attachments.length === 0) {
        list.innerHTML = '<div class="text-center py-3"><small class="text-muted">Aucune pièce jointe disponible</small></div>';
        return;
    }
    
    let html = '<div class="row">';
    
    attachments.forEach((attachment, index) => {
        const fileIcon = getFileIcon(attachment.type_fichier);
        const fileSize = formatFileSize(attachment.taille_fichier);
        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(attachment.type_fichier.toLowerCase());
        const isPdf = attachment.type_fichier.toLowerCase() === 'pdf';
        
        html += `
            <div class="col-md-6 mb-2">
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="flex-shrink-0 me-2">
                            <i class="${fileIcon} text-primary"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-medium text-truncate small" title="${attachment.nom_fichier}">
                                ${attachment.nom_fichier}
                            </div>
                            ${attachment.commentaire ? `<small class="text-muted d-block" style="font-size: 0.7rem;">${attachment.commentaire}</small>` : ''}
                            ${attachment.masque_client ? '<small class="text-muted"><span class="badge bg-warning" style="font-size: 0.6rem;">Masqué</span></small>' : ''}
                        </div>
                    </div>
                    <div class="flex-shrink-0 ms-2">
                        <a href="${baseUrl}materiel/download/${attachment.id}" 
                           class="btn btn-sm btn-outline-success btn-action me-1" 
                           style="padding: 0.25rem 0.5rem; font-size: 0.7rem;"
                           title="Télécharger">
                            <i class="bi bi-download"></i>
                        </a>
                        ${isImage || isPdf ? `
                            <button class="btn btn-sm btn-outline-info btn-action" 
                                    style="padding: 0.25rem 0.5rem; font-size: 0.7rem;"
                                    onclick="showPreview('${attachment.id}', '${attachment.nom_fichier}', '${attachment.chemin_fichier}', '${attachment.type_fichier}')"
                                    title="Aperçu">
                                <i class="bi bi-eye"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    list.innerHTML = html;
}

// Fonction pour afficher la preview
function showPreview(attachmentId, fileName, filePath, fileType) {
    const modalId = 'previewModal';
    let modal = document.getElementById(modalId);
    
    // Créer le modal s'il n'existe pas
    if (!modal) {
        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="preview-container"></div>
                    </div>
                    <div class="modal-footer">
                        <a href="#" class="btn btn-primary download-link" target="_blank">
                            <i class="bi bi-download me-1"></i> Télécharger
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Mettre à jour le contenu du modal
    const modalTitle = modal.querySelector('.modal-title');
    const previewContainer = modal.querySelector('.preview-container');
    const downloadLink = modal.querySelector('.download-link');
    
    modalTitle.textContent = fileName;
    downloadLink.href = `${baseUrl}materiel/download/${attachmentId}`;
    
    // Générer le contenu de preview selon le type de fichier
    const fileTypeLower = fileType.toLowerCase();
    if (fileTypeLower === 'pdf') {
        previewContainer.innerHTML = `<iframe src="${baseUrl}${filePath}" width="100%" height="600px" frameborder="0"></iframe>`;
    } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileTypeLower)) {
        previewContainer.innerHTML = `<img src="${baseUrl}${filePath}" class="img-fluid" alt="${fileName}">`;
    } else {
        previewContainer.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-1"></i> 
                Ce type de fichier ne peut pas être prévisualisé. 
                <a href="${baseUrl}materiel/download/${attachmentId}" class="alert-link" target="_blank">
                    Télécharger le fichier
                </a>
            </div>
        `;
    }
    
    // Afficher le modal
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}

// Fonction pour obtenir l'icône selon le type de fichier
function getFileIcon(fileType) {
    const type = fileType.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(type)) return 'bi bi-file-image';
    if (type === 'pdf') return 'bi bi-file-pdf';
    if (['doc', 'docx'].includes(type)) return 'bi bi-file-word';
    if (['xls', 'xlsx'].includes(type)) return 'bi bi-file-excel';
    if (['zip', 'rar', '7z'].includes(type)) return 'bi bi-file-zip';
    return 'bi bi-file';
}

// Fonction pour formater la taille de fichier
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// Fonction pour formater la date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Fonction pour basculer la visibilité d'une pièce jointe
function toggleAttachmentVisibility(attachmentId, makeVisible) {
    const formData = new FormData();
    formData.append('attachment_id', attachmentId);
    formData.append('masque_client', makeVisible ? '0' : '1');
    
    fetch('<?= BASE_URL ?>materiel/toggleAttachmentVisibility', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recharger les pièces jointes pour mettre à jour l'affichage
            const materielId = data.materiel_id;
            loadAttachments(materielId);
        } else {
            console.error('Erreur lors du basculement de visibilité:', data.error);
            alert('Erreur lors du basculement de visibilité: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur de connexion');
    });
}

// Fonction pour gérer les erreurs de prévisualisation
function handlePreviewError(fileName) {
    console.error('Erreur de prévisualisation pour:', fileName);
    
    // Afficher un message d'erreur dans le modal
    const modalBody = document.querySelector('.modal-body .preview-container');
    if (modalBody) {
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-exclamation-triangle text-warning fs-1 mb-3"></i>
                <h5 class="text-warning">Impossible de prévisualiser le fichier</h5>
                <p class="text-muted">Le fichier "${fileName}" ne peut pas être affiché.</p>
                <p class="text-muted small">Cela peut être dû à :</p>
                <ul class="text-muted small text-start">
                    <li>Le fichier a été supprimé ou déplacé</li>
                    <li>Le format n'est pas supporté</li>
                    <li>Une erreur de serveur</li>
                </ul>
                <p class="text-muted mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Vous pouvez toujours télécharger le fichier depuis la liste des pièces jointes.
                </p>
            </div>
        `;
    }
}

// Fonction pour charger l'aperçu dans la modal
function loadPreview(attachmentId, fileName, isPdf) {
    const modal = document.getElementById(`previewModal${attachmentId}`);
    const previewContainer = modal.querySelector('.preview-container');
    
    if (isPdf) {
        previewContainer.innerHTML = `
            <iframe src="<?= BASE_URL ?>materiel/preview/${attachmentId}" 
                    width="100%" 
                    height="600px" 
                    frameborder="0"
                    onerror="handlePreviewError('${fileName}')">
            </iframe>
        `;
    } else {
        previewContainer.innerHTML = `
            <img src="<?= BASE_URL ?>materiel/preview/${attachmentId}" 
                 class="img-fluid" 
                 alt="${fileName}"
                 onerror="handlePreviewError('${fileName}')">
        `;
    }
}

// Initialiser les événements de modal pour charger l'aperçu
document.addEventListener('DOMContentLoaded', function() {
    // Écouter l'ouverture des modals d'aperçu
    document.addEventListener('show.bs.modal', function(event) {
        const modal = event.target;
        if (modal.id && modal.id.startsWith('previewModal')) {
            // Extraire l'ID de l'attachment et les informations depuis le bouton qui a ouvert la modal
            const button = event.relatedTarget;
            const attachmentId = button.getAttribute('data-attachment-id');
            const fileName = button.getAttribute('data-file-name');
            const isPdf = button.getAttribute('data-is-pdf') === 'true';
            
            if (attachmentId && fileName) {
                loadPreview(attachmentId, fileName, isPdf);
            }
        }
    });
});

// Initialisation des zones d'upload pour tous les matériels
document.addEventListener('DOMContentLoaded', function() {
    initializeUploadZones();
});

// Fonction pour initialiser toutes les zones d'upload
function initializeUploadZones() {
    // Trouver tous les matériels avec des zones d'upload
    const uploadZones = document.querySelectorAll('[id^="upload-zone-"]');
    
    uploadZones.forEach(zone => {
        const materielId = zone.id.replace('upload-zone-', '');
        setupUploadZone(materielId);
    });
}

// Fonction pour configurer une zone d'upload
function setupUploadZone(materielId) {
    const uploadZone = document.getElementById(`upload-zone-${materielId}`);
    const fileInput = document.getElementById(`file-input-${materielId}`);
    const progressDiv = document.getElementById(`upload-progress-${materielId}`);
    const progressBar = progressDiv.querySelector('.progress-bar');
    
    if (!uploadZone || !fileInput) return;
    
    // Gestion du drag & drop
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
    
    uploadZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
    });
    
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadFiles(materielId, files);
        }
    });
    
    // Gestion du clic sur la zone
    uploadZone.addEventListener('click', function(e) {
        if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
            fileInput.click();
        }
    });
    
    // Gestion de la sélection de fichiers
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            uploadFiles(materielId, e.target.files);
        }
    });
}

// Fonction pour uploader les fichiers
function uploadFiles(materielId, files) {
    const progressDiv = document.getElementById(`upload-progress-${materielId}`);
    const progressBar = progressDiv.querySelector('.progress-bar');
    const progressText = progressDiv.querySelector('small');
    
    // Afficher la barre de progression
    progressDiv.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.textContent = 'Préparation de l\'upload...';
    
    // Créer FormData
    const formData = new FormData();
    formData.append('materiel_id', materielId);
    
    // Ajouter le paramètre de visibilité
    const masqueClientCheckbox = document.getElementById(`masque-client-${materielId}`);
    if (masqueClientCheckbox && masqueClientCheckbox.checked) {
        formData.append('masque_client', '1');
    }
    
    // Ajouter tous les fichiers
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    
    // Upload avec fetch
    fetch('<?= BASE_URL ?>materiel/uploadAttachment', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            progressBar.style.width = '100%';
            progressText.textContent = 'Upload terminé !';
            
            // Recharger les pièces jointes
            setTimeout(() => {
                loadAttachments(materielId);
                progressDiv.style.display = 'none';
                progressBar.style.width = '0%';
            }, 1000);
            
            // Réinitialiser l'input file
            document.getElementById(`file-input-${materielId}`).value = '';
            
        } else {
            progressText.textContent = 'Erreur : ' + (data.error || 'Upload échoué');
            progressBar.classList.add('bg-danger');
        }
    })
    .catch(error => {
        console.error('Erreur upload:', error);
        progressText.textContent = 'Erreur de connexion';
        progressBar.classList.add('bg-danger');
    });
}


</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 
