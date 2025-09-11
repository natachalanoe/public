<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de détail du client
 * Affiche les informations complètes d'un client
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Récupération des données
$client = $client ?? null;
$sites = $sites ?? [];
$contracts = $contracts ?? [];
$contacts = $contacts ?? []; // Assurez-vous que cette variable est définie dans le contrôleur

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['type'] ?? null;

// Récupérer l'ID du client depuis l'URL
$clientId = isset($client['id']) ? $client['id'] : '';

setPageVariables(
    'Client',
    'clients' . ($clientId ? '_view_' . $clientId : '')
);

// Définir la page courante pour le menu
$currentPage = 'clients';

// Vérifier si l'utilisateur a les droits pour modifier un client
$canModifyClient = canModifyClients();

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Détails du client</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo BASE_URL; ?>clients" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>
        <a href="<?php echo BASE_URL; ?>documentation/view/<?php echo $client['id'] ?? ''; ?>" class="btn btn-info me-2">
            Documentation
        </a>
        <?php if ($canModifyClient): ?>
            <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $client['id'] ?? ''; ?>" class="btn btn-warning">
                Modifier
            </a>
        <?php else: ?>
            <button type="button" class="btn btn-secondary" disabled title="Vous n'avez pas les droits pour modifier ce client">
                Modifier
            </button>
        <?php endif; ?>
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

    <?php if ($client): ?>
        <!-- Résumé du client -->
        <div class="card mb-4">
            <div class="card-header py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($client['name'] ?? ''); ?></h5>
                    <span class="badge bg-<?php echo ($client['status'] ?? 0) == 1 ? 'success' : 'danger'; ?>">
                        <?php echo ($client['status'] ?? 0) == 1 ? 'Actif' : 'Inactif'; ?>
                    </span>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="mb-1 text-dark"><?php echo $stats['site_count'] ?? 0; ?></h4>
                            <small class="text-muted">Sites</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="mb-1 text-dark"><?php echo $stats['room_count'] ?? 0; ?></h4>
                            <small class="text-muted">Salles</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="mb-1 text-dark"><?php echo $stats['contract_count'] ?? 0; ?></h4>
                            <small class="text-muted">Contrats</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="mb-1 text-dark"><?php echo count($contacts); ?></h4>
                            <small class="text-muted">Contacts</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglets pour les différentes sections -->
        <ul class="nav nav-tabs mb-4" id="clientTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                    <i class="<?php echo getIcon('info', 'bi bi-info-circle'); ?> me-2"></i> Informations
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab" aria-controls="contacts" aria-selected="false">
                    <i class="<?php echo getIcon('contact', 'bi bi-person-lines-fill'); ?> me-2"></i> Contacts
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sites-tab" data-bs-toggle="tab" data-bs-target="#sites" type="button" role="tab" aria-controls="sites" aria-selected="false">
                    <i class="<?php echo getIcon('site', 'bi bi-building'); ?> me-2"></i> Sites
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="contracts-tab" data-bs-toggle="tab" data-bs-target="#contracts" type="button" role="tab" aria-controls="contracts" aria-selected="false">
                    <i class="<?php echo getIcon('contract', 'bi bi-file-earmark-text'); ?> me-2"></i> Contrats
                </button>
            </li>
        </ul>

        <div class="tab-content" id="clientTabsContent">
            <!-- Onglet Informations -->
            <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0">Informations générales</h5>
                    </div>
                    <div class="card-body py-2">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">Nom</th>
                                        <td><?php echo htmlspecialchars($client['name'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Ville</th>
                                        <td><?php echo htmlspecialchars($client['city'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Adresse</th>
                                        <td><?php echo htmlspecialchars($client['address'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Code Postal</th>
                                        <td><?php echo htmlspecialchars($client['postal_code'] ?? ''); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">Email</th>
                                        <td><?php echo htmlspecialchars($client['email'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Téléphone</th>
                                        <td><?php echo htmlspecialchars($client['phone'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Site Web</th>
                                        <td><?php echo htmlspecialchars($client['website'] ?? ''); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header py-2">
                                        <h5 class="card-title mb-0">Commentaire</h5>
                                    </div>
                                    <div class="card-body py-2">
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($client['comment'] ?? '')); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Contacts -->
            <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0">Contacts</h5>
                    </div>
                    <div class="card-body py-2">
                        <?php if (!empty($contacts)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="contactsTable">
                                    <thead>
                                        <tr>
                                            <th class="sortable" data-sort="first_name">
                                                Prénom <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="last_name">
                                                Nom <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="fonction">
                                                Fonction <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="phone1">
                                                Téléphone fixe <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="phone2">
                                                Mobile <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="email">
                                                Email <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="has_user_account">
                                                Compte utilisateur <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contacts as $contact): ?>
                                            <tr>
                                                <td data-label="Prénom" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['first_name'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['first_name'] ?? ''); ?>
                                                </td>
                                                <td data-label="Nom" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['last_name'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['last_name'] ?? ''); ?>
                                                </td>
                                                <td data-label="Fonction" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['fonction'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['fonction'] ?? ''); ?>
                                                </td>
                                                <td data-label="Téléphone fixe" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['phone1'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['phone1'] ?? ''); ?>
                                                </td>
                                                <td data-label="Mobile" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['phone2'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['phone2'] ?? ''); ?>
                                                </td>
                                                <td data-label="Email" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['email'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['email'] ?? ''); ?>
                                                </td>
                                                <td data-label="Compte utilisateur" data-sort-value="<?php echo $contact['has_user_account'] ? '1' : '0'; ?>">
                                                    <?php if ($contact['has_user_account']): ?>
                                                        <span class="badge bg-success">Oui</span>
                                                        <?php if ($contact['user_username']): ?>
                                                            <br><small><?php echo htmlspecialchars($contact['user_username']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Aucun contact enregistré pour ce client.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Onglet Sites -->
            <div class="tab-pane fade" id="sites" role="tabpanel" aria-labelledby="sites-tab">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0">Sites</h5>
                    </div>
                    <div class="card-body py-2">
                        <?php if (!empty($sites)): ?>
                            <div class="accordion" id="sitesAccordion">
                                <?php foreach ($sites as $siteIndex => $site): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="siteHeading<?php echo $site['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#siteCollapse<?php echo $site['id']; ?>" aria-expanded="false" aria-controls="siteCollapse<?php echo $site['id']; ?>">
                                                    <?php echo htmlspecialchars($site['name']); ?>
                                                    <span class="badge bg-info ms-2"><?php echo count($site['rooms'] ?? []); ?> salles</span>
                                                </button>
                                                <div class="me-3">
                                                    <a href="<?php echo BASE_URL; ?>qrcode/generate/site/<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-primary" title="Générer les QR codes des salles">
                                                        <i class="bi bi-qr-code me-1"></i> QR Codes
                                                    </a>
                                                </div>
                                            </div>
                                        </h2>
                                        <div id="siteCollapse<?php echo $site['id']; ?>" class="accordion-collapse collapse" aria-labelledby="siteHeading<?php echo $site['id']; ?>" data-bs-parent="#sitesAccordion">
                                            <div class="accordion-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <table class="table table-bordered">
                                                            <tr>
                                                                <th>Adresse</th>
                                                                <td><?php echo htmlspecialchars($site['address'] ?? ''); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Code Postal</th>
                                                                <td><?php echo htmlspecialchars($site['postal_code'] ?? ''); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Ville</th>
                                                                <td><?php echo htmlspecialchars($site['city'] ?? ''); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Téléphone</th>
                                                                <td><?php echo htmlspecialchars($site['phone'] ?? ''); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Email</th>
                                                                <td><?php echo htmlspecialchars($site['email'] ?? ''); ?></td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-header py-2">
                                                                <h6 class="card-title mb-0">Commentaire</h6>
                                                            </div>
                                                            <div class="card-body py-2">
                                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($site['comment'] ?? '')); ?></p>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Contact principal du site -->
                                                        <?php if (!empty($site['primary_contact'])): ?>
                                                        <div class="card mt-3">
                                                            <div class="card-header py-2">
                                                                <h6 class="card-title mb-0">Contact principal</h6>
                                                            </div>
                                                            <div class="card-body py-2">
                                                                <div class="d-flex">
                                                                    <div class="avatar avatar-sm me-2">
                                                                        <div class="avatar-initial rounded-circle bg-label-primary">
                                                                            <?php 
                                                                            $initials = substr($site['primary_contact']['first_name'], 0, 1) . substr($site['primary_contact']['last_name'], 0, 1);
                                                                            echo strtoupper($initials);
                                                                            ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-3">
                                                                        <h6 class="mb-1"><?php echo htmlspecialchars($site['primary_contact']['first_name'] . ' ' . $site['primary_contact']['last_name']); ?></h6>
                                                                        <?php if (!empty($site['primary_contact']['phone1'])) : ?>
                                                                            <p class="mb-1 small">
                                                                                <i class="<?php echo getIcon('phone', 'bi bi-telephone'); ?> me-1"></i> <?php echo htmlspecialchars($site['primary_contact']['phone1']); ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($site['primary_contact']['email'])) : ?>
                                                                            <p class="mb-0 small">
                                                                                <i class="bi bi-envelope me-1 me-1"></i> <?php echo htmlspecialchars($site['primary_contact']['email']); ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Salles du site -->
                                                <?php if (!empty($site['rooms'])): ?>
                                                <div class="mt-4">
                                                    <h6 class="fw-bold mb-3">Salles</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-striped table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th>Nom</th>
                                                                    <th>Contact principal</th>
                                                                    <th>Statut</th>
                                                                    <th>Commentaire</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($site['rooms'] as $room): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($room['name']); ?></td>
                                                                    <td>
                                                                        <?php 
                                                                        if (!empty($room['first_name']) && !empty($room['last_name'])) {
                                                                            echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']);
                                                                        } else {
                                                                            echo '<span class="text-muted">Aucun contact</span>';
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-<?php echo ($room['status'] ?? 0) == 1 ? 'success' : 'danger'; ?>">
                                                                            <?php echo ($room['status'] ?? 0) == 1 ? 'Actif' : 'Inactif'; ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <?php 
                                                                        if (!empty($room['comment'])) {
                                                                            echo htmlspecialchars($room['comment']);
                                                                        } else {
                                                                            echo '<span class="text-muted">Aucun commentaire</span>';
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-info mt-3">
                                                    <i class="bi bi-info-circle me-2 me-1"></i> Aucune salle enregistrée pour ce site.
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Aucun site enregistré pour ce client.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Onglet Contrats -->
            <div class="tab-pane fade" id="contracts" role="tabpanel" aria-labelledby="contracts-tab">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0">Contrats</h5>
                    </div>
                    <div class="card-body py-2">
                        <?php if (!empty($contracts)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="contractsTable">
                                    <thead>
                                        <tr>
                                            <th class="sortable" data-sort="client">
                                                Client <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="type">
                                                Type de contrat <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="name">
                                                Nom <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="end_date">
                                                Date de fin <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="tickets_number">
                                                Tickets initiaux <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="tickets_remaining">
                                                Tickets restants <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="status">
                                                Statut <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contracts as $contract): ?>
                                            <tr>
                                                <td data-label="Client" data-sort-value="<?php echo htmlspecialchars(strtolower($client['name'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($client['name'] ?? '-'); ?>
                                                </td>
                                                <td data-label="Type de contrat" data-sort-value="<?php echo htmlspecialchars(strtolower($contract['contract_type_name'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contract['contract_type_name'] ?? '-'); ?>
                                                </td>
                                                <td data-label="Nom" data-sort-value="<?php echo htmlspecialchars(strtolower($contract['name'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contract['name'] ?? '-'); ?>
                                                </td>
                                                <td data-label="Date de fin" data-sort-value="<?php echo strtotime($contract['end_date']); ?>">
                                                    <?php echo formatDateFrench($contract['end_date']); ?>
                                                </td>
                                                <td data-label="Tickets initiaux" data-sort-value="<?php echo $contract['tickets_number']; ?>">
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
                                                <td data-label="Tickets restants" data-sort-value="<?php echo $contract['tickets_remaining']; ?>">
                                                    <?php if ($contract['tickets_number'] > 0): ?>
                                                        <span class="badge bg-<?php echo $contract['tickets_remaining'] > 3 ? 'success' : 'danger'; ?>">
                                                            <?php echo $contract['tickets_remaining']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Statut" data-sort-value="<?php echo htmlspecialchars(strtolower($contract['status'])); ?>">
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
                                                        <a href="<?php echo BASE_URL; ?>contracts/edit/<?php echo $contract['id']; ?>?return_to=client" class="btn btn-sm btn-outline-warning btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Modifier">
                                                            <i class="<?php echo getIcon('edit', 'bi bi-pencil'); ?>"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Aucun contrat enregistré pour ce client.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            Client introuvable.
        </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<style>
.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
}

.sortable:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.sort-icon {
    font-size: 0.8em;
    margin-left: 5px;
    opacity: 0.5;
}

.sortable.sort-asc .sort-icon::before {
    content: "\F12C"; /* bi-arrow-up */
    opacity: 1;
}

.sortable.sort-desc .sort-icon::before {
    content: "\F12F"; /* bi-arrow-down */
    opacity: 1;
}

.sortable.sort-asc,
.sortable.sort-desc {
    background-color: rgba(0, 123, 255, 0.1);
}
</style>

<!-- Script pour la confirmation de suppression -->
<script>
function confirmDelete(contractId, contractName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer le contrat "' + contractName + '" ?')) {
        window.location.href = '<?php echo BASE_URL; ?>contracts/delete/' + contractId;
    }
}
</script>

<!-- Scripts JavaScript -->
<script>
// Initialiser BASE_URL pour JavaScript
initBaseUrl('<?php echo BASE_URL; ?>');

// Debug des données
console.log('Client:', <?php echo json_encode($client); ?>);
console.log('Sites:', <?php echo json_encode($sites); ?>);
console.log('Stats:', <?php echo json_encode($stats); ?>);

// Fonction pour charger les salles d'un site via AJAX si nécessaire
function loadRoomsForSite(siteId, callback) {
    if (typeof loadRooms === 'function') {
        loadRooms(siteId, null, null, callback);
    }
}

// Script de tri pour les tables
document.addEventListener('DOMContentLoaded', function() {
    // Fonction de tri générique
    function initSortableTable(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        let currentSortColumn = null;
        let currentSortDirection = 'asc';

        // Fonction de tri
        function sortTable(columnIndex, direction) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                const aValue = a.cells[columnIndex].getAttribute('data-sort-value') || a.cells[columnIndex].textContent.trim();
                const bValue = b.cells[columnIndex].getAttribute('data-sort-value') || b.cells[columnIndex].textContent.trim();
                
                // Gestion des valeurs numériques
                const aNum = parseFloat(aValue);
                const bNum = parseFloat(bValue);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return direction === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // Gestion des dates (timestamp)
                if (aValue.length === 10 && bValue.length === 10) {
                    const aDate = parseInt(aValue);
                    const bDate = parseInt(bValue);
                    if (!isNaN(aDate) && !isNaN(bDate)) {
                        return direction === 'asc' ? aDate - bDate : bDate - aDate;
                    }
                }
                
                // Tri alphabétique
                const aLower = aValue.toLowerCase();
                const bLower = bValue.toLowerCase();
                
                if (aLower < bLower) return direction === 'asc' ? -1 : 1;
                if (aLower > bLower) return direction === 'asc' ? 1 : -1;
                return 0;
            });
            
            // Réorganiser les lignes
            rows.forEach(row => tbody.appendChild(row));
        }

        // Gestionnaire d'événements pour les en-têtes triables
        table.querySelectorAll('th.sortable').forEach((header, index) => {
            header.addEventListener('click', function() {
                const sortType = this.getAttribute('data-sort');
                
                // Réinitialiser tous les en-têtes de cette table
                table.querySelectorAll('th.sortable').forEach(th => {
                    th.classList.remove('sort-asc', 'sort-desc');
                });
                
                // Déterminer la direction de tri
                let direction = 'asc';
                if (currentSortColumn === index && currentSortDirection === 'asc') {
                    direction = 'desc';
                }
                
                // Appliquer le tri
                sortTable(index, direction);
                
                // Mettre à jour l'état visuel
                this.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');
                
                // Mettre à jour les variables globales
                currentSortColumn = index;
                currentSortDirection = direction;
            });
        });
    }

    // Initialiser le tri pour les deux tables
    initSortableTable('contractsTable');
    initSortableTable('contactsTable');
});
</script> 