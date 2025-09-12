<?php
/**
 * Vue du tableau de bord
 * Affiche les statistiques et les informations importantes
 */

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../../includes/functions.php';

setPageVariables(
    'Accueil',
    'dashboard'
);




// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Vérifier que l'utilisateur est staff (sécurité)
if (!isStaff()) {
    $_SESSION['error'] = 'Accès non autorisé. Vous devez être membre du personnel pour accéder à cette page.';
    header('Location: ' . BASE_URL . 'auth/logout');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Définir la page courante pour le menu
$currentPage = 'dashboard';

// Récupération de l'instance de la base de données
$config = Config::getInstance();
$db = $config->getDb();

// Récupération des statistiques des interventions
try {
    $statsByStatus = $db->query("
        SELECT s.name as status, s.color as color, COUNT(i.id) as count
        FROM interventions i
        JOIN intervention_statuses s ON i.status_id = s.id
        WHERE i.status_id NOT IN (6, 7)
        GROUP BY s.name, s.id, s.color
        ORDER BY s.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $statsByClient = $db->query("
        SELECT c.name as client, COUNT(i.id) as count
        FROM interventions i
        JOIN clients c ON i.client_id = c.id
        WHERE i.status_id NOT IN (6, 7)
        GROUP BY c.name
        ORDER BY count DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $statsByPriority = $db->query("
        SELECT p.name as priority, p.color as color, COUNT(i.id) as count
        FROM interventions i
        JOIN intervention_priorities p ON i.priority_id = p.id
        WHERE i.status_id NOT IN (6, 7)
        GROUP BY p.name, p.id, p.color
        ORDER BY p.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Contrats expirant dans 30 jours ou moins, déjà expirés mais pas désactivés
    $expiringContracts = $db->query("
        SELECT c.*, cl.name as client_name,
               GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as site_names
        FROM contracts c
        JOIN clients cl ON c.client_id = cl.id
        LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
        LEFT JOIN rooms r ON cr.room_id = r.id
        LEFT JOIN sites s ON r.site_id = s.id AND s.status = 1
        WHERE c.status = 'actif'
        AND c.contract_type_id IS NOT NULL
        AND (
            (c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
            OR (c.end_date < CURDATE())
        )
        GROUP BY c.id, c.name, c.end_date, cl.name
        ORDER BY c.end_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Contrats actifs avec moins de 5 tickets (uniquement ceux avec tickets initiaux > 0)
    $lowTicketsContracts = $db->query("
        SELECT c.*, cl.name as client_name,
               GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as site_names
        FROM contracts c
        JOIN clients cl ON c.client_id = cl.id
        LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
        LEFT JOIN rooms r ON cr.room_id = r.id
        LEFT JOIN sites s ON r.site_id = s.id AND s.status = 1
        WHERE c.status = 'actif'
        AND c.tickets_remaining < 5
        AND c.tickets_number > 0
        AND c.contract_type_id IS NOT NULL
        GROUP BY c.id, c.name, c.tickets_remaining, cl.name
        ORDER BY c.tickets_remaining ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Interventions avec statut "Nouveau" (excluant les préventives)
    $newInterventions = $db->query("
        SELECT i.*, c.name as client_name, s.name as site_name, r.name as room_name,
               p.name as priority, p.color as color, t.name as type
        FROM interventions i
        JOIN clients c ON i.client_id = c.id
        LEFT JOIN sites s ON i.site_id = s.id
        LEFT JOIN rooms r ON i.room_id = r.id
        JOIN intervention_priorities p ON i.priority_id = p.id
        JOIN intervention_types t ON i.type_id = t.id
        JOIN intervention_statuses st ON i.status_id = st.id
        WHERE st.name = 'Nouveau'
        AND p.name != 'Préventif'
        ORDER BY i.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Prochaines interventions planifiées
    $plannedInterventions = $db->query("
        SELECT i.id, i.reference, i.title, c.name as client_name, 
               i.date_planif, i.heure_planif, u.first_name, u.last_name
        FROM interventions i
        JOIN clients c ON i.client_id = c.id
        LEFT JOIN users u ON i.technician_id = u.id
        WHERE i.date_planif IS NOT NULL 
        AND i.date_planif >= CURDATE()
        AND i.status_id NOT IN (6, 7) -- Exclure les interventions fermées et annulées
        ORDER BY i.date_planif ASC, i.heure_planif ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Salles sans contrat affecté
    $roomsWithoutContract = $db->query("
        SELECT r.id, r.name as room_name, r.comment, r.status,
               c.name as client_name, s.name as site_name,
               CONCAT(cont.first_name, ' ', cont.last_name) as contact_name
        FROM rooms r
        JOIN sites s ON r.site_id = s.id
        JOIN clients c ON s.client_id = c.id
        LEFT JOIN contacts cont ON r.main_contact_id = cont.id
        LEFT JOIN contract_rooms cr ON r.id = cr.room_id
        LEFT JOIN contracts co ON cr.contract_id = co.id AND co.status = 'actif'
        WHERE r.status = 1
        AND cr.contract_id IS NULL
        ORDER BY c.name, s.name, r.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Calcul des montants financiers
    // 1. Récupérer le tarif d'un ticket depuis les settings
    $tarifTicket = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'tarif_ticket'")->fetchColumn();
    $tarifTicket = $tarifTicket ? (float)$tarifTicket : 90.0; // Valeur par défaut si non trouvée

    // 2. Calculer la valeur des tickets restants (tickets_remaining * tarif_ticket)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(tickets_remaining * :tarif_ticket), 0) as total_value
        FROM contracts 
        WHERE status = 'actif' 
        AND contract_type_id IS NOT NULL
        AND tickets_remaining > 0
    ");
    $stmt->execute([':tarif_ticket' => $tarifTicket]);
    $ticketsValue = $stmt->fetchColumn();

    // 3. Calculer la somme des montants des contrats actifs
    $contractsValue = $db->query("
        SELECT COALESCE(SUM(CAST(tarif AS DECIMAL(10,2))), 0) as total_value
        FROM contracts 
        WHERE status = 'actif' 
        AND contract_type_id IS NOT NULL
        AND tarif IS NOT NULL 
        AND tarif != ''
        AND tarif != '0.00'
    ")->fetchColumn();

} catch (Exception $e) {
    // En cas d'erreur, initialiser les variables avec des tableaux vides
    $statsByStatus = [];
    $statsByClient = [];
    $statsByPriority = [];
    $expiringContracts = [];
    $lowTicketsContracts = [];
    $newInterventions = [];
    $plannedInterventions = [];
    $roomsWithoutContract = [];
    $ticketsValue = 0;
    $contractsValue = 0;
    $tarifTicket = 90.0;
    
    // Log de l'erreur
    custom_log("Erreur lors du chargement des statistiques du dashboard : " . $e->getMessage(), 'ERROR');
}

// Préparer les données pour le graphique camembert
$pieChartLabels = [];
$pieChartSeries = [];
$pieChartColors = [];

foreach ($statsByStatus as $stat) {
    $pieChartLabels[] = $stat['status'];
    $pieChartSeries[] = (int)$stat['count'];
    $pieChartColors[] = $stat['color'];
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

?>

<div class="container-fluid flex-grow-1 container-p-y">
<h4 class="py-4 mb-6">Tableau de bord</h4>

            <!-- Card des montants financiers -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header text-dark">
                            <i class="bi bi-currency-euro me-1"></i> Aperçu financier des contrats actifs
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avatar avatar-sm me-3">
                                                <span class="avatar-initial rounded bg-label-primary">
                                                    <i class="bi bi-ticket-perforated"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">Valeur des tickets restants</h6>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <h4 class="mb-0 text-primary"><?php echo formatAmount($ticketsValue); ?></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avatar avatar-sm me-3">
                                                <span class="avatar-initial rounded bg-label-success">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">Valeur des contrats actifs</h6>
                                            <small class="text-muted">Somme des montants des contrats actifs</small>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <h4 class="mb-0 text-success"><?php echo formatAmount($contractsValue); ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cartes de statistiques -->
            <div class="row">
                <!-- Graphique camembert des tickets par statut -->
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-header text-dark">
                            <i class="bi bi-pie-chart me-1"></i> Interventions ouvertes
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div id="ticketsStatusPieChart" style="width: 100%; height: 300px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Interventions par client -->
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-header text-dark">
                            <i class="bi bi-building me-1"></i> Top 5 - Interventions par Client
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Nombre</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statsByClient as $stat): ?>
                                        <tr>
                                            <td><?php echo h($stat['client']); ?></td>
                                            <td><?php echo $stat['count']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prochaines interventions planifiées -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header text-dark">
                            <i class="bi bi-calendar-check me-1"></i> Prochaines interventions planifiées
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>N° Inter</th>
                                            <th>Client</th>
                                            <th>Date planifiée</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($plannedInterventions)): ?>
                                            <?php foreach ($plannedInterventions as $intervention): ?>
                                            <tr>
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" 
                                                       class="text-primary fw-bold">
                                                        <?php echo h($intervention['reference']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo h($intervention['client_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    $datePlanif = $intervention['date_planif'];
                                                    $heurePlanif = $intervention['heure_planif'];
                                                    echo formatDate($datePlanif);
                                                    if ($heurePlanif) {
                                                        echo ' ' . $heurePlanif;
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">
                                                    Aucune intervention planifiée
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contrats expirant et contrats avec peu de tickets -->
            <div class="row mt-4">
                <!-- Contrats expirant -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-dark">
                            <i class="bi bi-calendar-x me-1"></i> Contrats expirant dans les 30 prochains jours
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Nom du contrat</th>
                                            <th>Site</th>
                                            <th>Date de fin</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expiringContracts as $contract): ?>
                                        <tr>
                                            <td><?php echo h($contract['client_name']); ?></td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>contracts/view/<?php echo $contract['id']; ?>" 
                                                   class="text-primary fw-bold text-decoration-none">
                                                    <?php echo h($contract['name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($contract['site_names']) {
                                                    echo h($contract['site_names']);
                                                } else {
                                                    echo "Client";
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo formatDate($contract['end_date']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contrats avec peu de tickets -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-dark">
                            <i class="bi bi-ticket me-1"></i> Contrats actifs avec moins de 5 tickets
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Nom du contrat</th>
                                            <th>Site</th>
                                            <th>Tickets restants</th>
                                            <th>Date de fin</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lowTicketsContracts as $contract): ?>
                                        <tr>
                                            <td><?php echo h($contract['client_name']); ?></td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>contracts/view/<?php echo $contract['id']; ?>" 
                                                   class="text-primary fw-bold text-decoration-none">
                                                    <?php echo h($contract['name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($contract['site_names']) {
                                                    echo h($contract['site_names']);
                                                } else {
                                                    echo "Client";
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo $contract['tickets_remaining']; ?></td>
                                            <td><?php echo formatDate($contract['end_date']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interventions avec statut "Nouveau" -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header text-dark">
                            <i class="bi bi-plus-circle me-1"></i> Interventions avec statut "Nouveau" hors préventives
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="newInterventionsTable">
                                    <thead>
                                        <tr>
                                            <th class="sortable" data-sort="reference">
                                                Référence <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="title">
                                                Titre <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="client_name">
                                                Client <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="site_room">
                                                Site/Salle <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="priority">
                                                Priorité <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="type">
                                                Type <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="created_at">
                                                Date de création <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($newInterventions as $intervention): ?>
                                        <tr>
                                            <td data-label="Référence" data-sort-value="<?php echo h(strtolower($intervention['reference'])); ?>">
                                                <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" class="text-primary fw-bold text-decoration-none">
                                                    <?php echo h($intervention['reference']); ?>
                                                </a>
                                            </td>
                                            <td data-label="Titre" data-sort-value="<?php echo h(strtolower($intervention['title'])); ?>">
                                                <?php echo h($intervention['title']); ?>
                                            </td>
                                            <td data-label="Client" data-sort-value="<?php echo h(strtolower($intervention['client_name'])); ?>">
                                                <?php echo h($intervention['client_name']); ?>
                                            </td>
                                            <td data-label="Site/Salle" data-sort-value="<?php echo h(strtolower($intervention['room_name'] ?: $intervention['site_name'] ?: 'client')); ?>">
                                                <?php 
                                                if ($intervention['room_name']) {
                                                    echo h($intervention['room_name']);
                                                } elseif ($intervention['site_name']) {
                                                    echo h($intervention['site_name']);
                                                } else {
                                                    echo "Client";
                                                }
                                                ?>
                                            </td>
                                            <td data-label="Priorité" data-sort-value="<?php echo h(strtolower($intervention['priority'])); ?>">
                                                <span class="badge" style="background-color: <?php echo h($intervention['color']); ?>">
                                                    <?php echo h($intervention['priority']); ?>
                                                </span>
                                            </td>
                                            <td data-label="Type" data-sort-value="<?php echo h(strtolower($intervention['type'])); ?>">
                                                <?php echo h($intervention['type']); ?>
                                            </td>
                                            <td data-label="Date de création" data-sort-value="<?php echo strtotime($intervention['created_at']); ?>">
                                                <?php echo formatDate($intervention['created_at']); ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" class="btn btn-sm btn-outline-info btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Voir">
                                                    <i class="<?php echo getIcon('visibility', 'bi bi-eye'); ?>"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Salles sans contrat affecté -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header text-dark">
                            <i class="bi bi-building me-1"></i> Salles sans contrat affecté
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="roomsWithoutContractTable">
                                    <thead>
                                        <tr>
                                            <th class="sortable" data-sort="client_name">
                                                Client <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="site_name">
                                                Site <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="room_name">
                                                Salle <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="contact_name">
                                                Contact principal <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="comment">
                                                Commentaire <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($roomsWithoutContract)): ?>
                                            <?php foreach ($roomsWithoutContract as $room): ?>
                                            <tr>
                                                <td data-label="Client" data-sort-value="<?php echo h(strtolower($room['client_name'])); ?>">
                                                    <?php echo h($room['client_name']); ?>
                                                </td>
                                                <td data-label="Site" data-sort-value="<?php echo h(strtolower($room['site_name'])); ?>">
                                                    <?php echo h($room['site_name']); ?>
                                                </td>
                                                <td data-label="Salle" data-sort-value="<?php echo h(strtolower($room['room_name'])); ?>">
                                                    <?php echo h($room['room_name']); ?>
                                                </td>
                                                <td data-label="Contact principal" data-sort-value="<?php echo h(strtolower($room['contact_name'] ?: 'aucun contact')); ?>">
                                                    <?php 
                                                    if ($room['contact_name']) {
                                                        echo h($room['contact_name']);
                                                    } else {
                                                        echo '<span class="text-muted">Aucun contact</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td data-label="Commentaire" data-sort-value="<?php echo h(strtolower($room['comment'] ?: 'aucun commentaire')); ?>">
                                                    <?php 
                                                    if ($room['comment']) {
                                                        echo h($room['comment']);
                                                    } else {
                                                        echo '<span class="text-muted">Aucun commentaire</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">
                                                    Toutes les salles ont un contrat affecté
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

</div>

<!-- Script pour le graphique camembert -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration du graphique camembert
    const pieChartEl = document.querySelector('#ticketsStatusPieChart');
    
    if (pieChartEl) {
        // Couleurs par défaut si les couleurs de la base de données ne sont pas définies
        const defaultColors = [
            config.colors.primary,
            config.colors.success,
            config.colors.warning,
            config.colors.info,
            config.colors.danger,
            config.colors.secondary
        ];
        
        // Utiliser les couleurs de la base de données ou les couleurs par défaut
        const chartColors = <?php echo json_encode($pieChartColors); ?>.map((color, index) => {
            return color || defaultColors[index % defaultColors.length];
        });
        
        const pieChartConfig = {
            chart: {
                height: '100%',
                type: 'donut',
                toolbar: {
                    show: false
                }
            },
            labels: <?php echo json_encode($pieChartLabels); ?>,
            series: <?php echo json_encode($pieChartSeries); ?>,
            colors: chartColors,
            stroke: {
                show: false,
                curve: 'straight'
            },
            dataLabels: {
                enabled: true,
                formatter: function (val, opt) {
                    return opt.w.globals.series[opt.seriesIndex];
                }
            },
            legend: {
                show: true,
                position: 'bottom',
                markers: { 
                    offsetX: -3 
                },
                itemMargin: {
                    vertical: 3,
                    horizontal: 10
                },
                labels: {
                    colors: config.colors.textMuted,
                    useSeriesColors: false
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            name: {
                                fontSize: '1.2rem',
                                fontFamily: config.fontFamily
                            },
                            value: {
                                fontSize: '1rem',
                                color: config.colors.textMuted,
                                fontFamily: config.fontFamily,
                                formatter: function (val) {
                                    return val;
                                }
                            },
                            total: {
                                show: true,
                                fontSize: '1.5rem',
                                color: config.colors.headingColor,
                                label: 'Total',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            },
            responsive: [
                {
                    breakpoint: 992,
                    options: {
                        chart: {
                            height: 380
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                colors: config.colors.textMuted,
                                useSeriesColors: false
                            }
                        }
                    }
                },
                {
                    breakpoint: 576,
                    options: {
                        chart: {
                            height: 320
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    labels: {
                                        show: true,
                                        name: {
                                            fontSize: '1rem'
                                        },
                                        value: {
                                            fontSize: '0.9rem'
                                        },
                                        total: {
                                            fontSize: '1.2rem'
                                        }
                                    }
                                }
                            }
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                colors: config.colors.textMuted,
                                useSeriesColors: false
                            }
                        }
                    }
                },
                {
                    breakpoint: 420,
                    options: {
                        chart: {
                            height: 280
                        },
                        legend: {
                            show: false
                        }
                    }
                }
            ]
        };

        const pieChart = new ApexCharts(pieChartEl, pieChartConfig);
        pieChart.render();
    }
});

// Script de tri pour les tables du dashboard
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

    // Initialiser le tri pour les tables du dashboard
    initSortableTable('newInterventionsTable');
    initSortableTable('roomsWithoutContractTable');
});
</script>

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
