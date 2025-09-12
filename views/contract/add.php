<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout d'un contrat
 * Permet d'ajouter un nouveau contrat
 */

// Vérifier si l'utilisateur est connecté et a les permissions
if (!isset($_SESSION['user']) || !canManageContracts()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer un contrat.";
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer les données du formulaire de la session si elles existent
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Données passées par le contrôleur
$client = $client ?? null;
$allClientsForDropdown = $allClientsForDropdown ?? null;
$sites = $sites ?? [];
$rooms = $rooms ?? [];
$contractTypes = $contractTypes ?? [];

// Définir le titre de la page et le lien de retour
if ($client) {
    $pageTitle = "Ajouter un contrat pour : " . htmlspecialchars($client['name']);
    $backLink = BASE_URL . 'clients/edit/' . $client['id'] . '#contracts';
} else {
    $pageTitle = "Ajouter un nouveau contrat";
    $backLink = BASE_URL . 'contracts';
}

setPageVariables(
    'Ajouter un contrat',
    'contracts'
);

// Définir la page courante pour le menu
$currentPage = 'contracts';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Initialiser BASE_URL pour JavaScript
echo '<script>const baseUrl = "' . BASE_URL . '";</script>';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight"><h4 class="py-4 mb-6"><?php echo $pageTitle; ?></h4></div>

        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo $backLink; ?>" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <button type="submit" form="contractForm" class="btn btn-primary">
                Enregistrer
            </button>
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

    <div class="card">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Informations du contrat</h5>
        </div>
        <div class="card-body py-2">
            <form id="contractForm" action="<?php echo BASE_URL; ?>contracts/create" method="POST">
                <?php if ($client): ?>
                <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                    <p class="mb-3"><strong>Client :</strong> <?php echo htmlspecialchars($client['name']); ?></p>
                <?php else: ?>
                    <div class="mb-3">
                        <label for="client_id_select" class="form-label">Client <span class="text-danger">*</span></label>
                        <select class="form-control bg-body text-body" id="client_id_select" name="client_id" required>
                            <option value="">Sélectionnez un client</option>
                            <?php if ($allClientsForDropdown): ?>
                                <?php foreach ($allClientsForDropdown as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                            <?php echo (isset($formData['client_id']) && $formData['client_id'] == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom du contrat <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-body text-body" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="contract_type_id" class="form-label">Type de contrat <span class="text-danger">*</span></label>
                            <select class="form-control bg-body text-body" id="contract_type_id" name="contract_type_id" required>
                                <option value="">Sélectionnez un type</option>
                                <?php foreach ($contractTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            <?php echo (isset($formData['contract_type_id']) && $formData['contract_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Salles associées</label>
                            <div id="rooms-container" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <div class="text-center text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <?php if ($client): ?>
                                        Chargement des salles...
                                    <?php else: ?>
                                        Sélectionnez d'abord un client
                                    <?php endif; ?>
                                </div>
                            </div>
                            <small class="form-text text-muted">Cochez les salles que vous souhaitez associer à ce contrat.</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control bg-body text-body" id="start_date" name="start_date" required
                                   value="<?php echo htmlspecialchars($formData['start_date'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="end_date" class="form-label">Date de fin <span class="text-danger">*</span></label>
                            <input type="date" class="form-control bg-body text-body" id="end_date" name="end_date" required
                                   value="<?php echo htmlspecialchars($formData['end_date'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="tickets_number" class="form-label">Nombre de tickets initiaux <span class="text-danger">*</span></label>
                            <input type="number" class="form-control bg-body text-body" id="tickets_number" name="tickets_number" required 
                                   value="<?php echo htmlspecialchars($formData['tickets_number'] ?? ''); ?>">
                            <small class="form-text text-muted">Si le nombre de tickets est à zéro, le contrat ne sera pas considéré comme un contrat à tickets.</small>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Statut <span class="text-danger">*</span></label>
                            <select class="form-control bg-body text-body" id="status" name="status" required>
                                <option value="actif" <?php echo (isset($formData['status']) && $formData['status'] === 'actif') ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif" <?php echo (isset($formData['status']) && $formData['status'] === 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                <option value="en_attente" <?php echo (isset($formData['status']) && $formData['status'] === 'en_attente') ? 'selected' : ''; ?>>En attente</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="access_level_id" class="form-label">
                                <i class="bi bi-arrow-up-circle me-1 me-1"></i>
                                Niveau d'Accès <span class="text-danger">*</span>
                            </label>
                            <select class="form-control bg-body text-body" id="access_level_id" name="access_level_id" required>
                                <option value="">Sélectionnez un niveau d'accès</option>
                                <?php if (isset($accessLevels)): ?>
                                    <?php foreach ($accessLevels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>" 
                                                <?php echo (isset($formData['access_level_id']) && $formData['access_level_id'] == $level['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['name']) ?> - <?php echo htmlspecialchars($level['description']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="form-text text-muted">
                                Ce niveau détermine la visibilité des champs matériel pour ce contrat.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="comment" class="form-label">Commentaires</label>
                            <textarea class="form-control bg-body text-body" id="comment" name="comment" rows="3"><?php echo htmlspecialchars($formData['comment'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="reminder_enabled" name="reminder_enabled" value="1" 
                                       <?php echo (isset($formData['reminder_enabled']) && $formData['reminder_enabled']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="reminder_enabled">
                                    Activer le rappel de fin de contrat
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="reminder_days" class="form-label">Nombre de jours avant la fin</label>
                            <input type="number" class="form-control bg-body text-body" id="reminder_days" name="reminder_days" 
                                   value="<?php echo htmlspecialchars($formData['reminder_days'] ?? '30'); ?>" min="1" max="365">
                            <small class="form-text text-muted">Nombre de jours avant la fin du contrat pour déclencher le rappel</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="renouvellement_tacite" name="renouvellement_tacite" value="1" 
                                       <?php echo (isset($formData['renouvellement_tacite']) && $formData['renouvellement_tacite']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="renouvellement_tacite">
                                    <i class="bi bi-arrow-repeat me-1"></i>Renouvellement tacite
                                </label>
                                <small class="form-text text-muted d-block">
                                    Le contrat se renouvelle automatiquement à la date de fin sans intervention manuelle
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations financières -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="num_facture" class="form-label">
                                <i class="bi bi-receipt me-1"></i>Numéro de facture
                            </label>
                            <input type="text" class="form-control bg-body text-body" id="num_facture" name="num_facture" 
                                   value="<?php echo htmlspecialchars($formData['num_facture'] ?? ''); ?>" 
                                   placeholder="FACT-2025-001">
                            <small class="form-text text-muted">Numéro de facture associé au contrat</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="tarif" class="form-label">
                                <i class="bi bi-currency-euro me-1"></i>Tarif (€)
                            </label>
                            <input type="number" class="form-control bg-body text-body" id="tarif" name="tarif" 
                                   value="<?php echo htmlspecialchars($formData['tarif'] ?? ''); ?>" 
                                   placeholder="1500.00" step="0.01" min="0">
                            <small class="form-text text-muted">Montant du contrat en euros</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="indice" class="form-label">
                                <i class="bi bi-graph-up me-1"></i>Indice de révision
                            </label>
                            <input type="text" class="form-control bg-body text-body" id="indice" name="indice" 
                                   value="<?php echo htmlspecialchars($formData['indice'] ?? ''); ?>" 
                                   placeholder="2025-01">
                            <small class="form-text text-muted">Indice pour les révisions tarifaires</small>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <!-- Boutons supprimés car placés en haut -->
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser BASE_URL pour les fonctions communes
    initBaseUrl(baseUrl);
    
    // Initialiser la validation Bootstrap
    initBootstrapValidation();
    
    const clientSelect = document.getElementById('client_id_select');
    const roomsContainer = document.getElementById('rooms-container');
    const contractTypes = <?php echo json_encode($contractTypes); ?>;
    const contractTypeSelect = document.getElementById('contract_type_id');
    const ticketsNumberInput = document.getElementById('tickets_number');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    // Mettre à jour le nombre de tickets quand le type de contrat change
    contractTypeSelect.addEventListener('change', function() {
        const selectedType = contractTypes.find(type => type.id == this.value);
        if (selectedType) {
            ticketsNumberInput.value = selectedType.default_tickets;
        }
    });

    // Calculer automatiquement la date de fin au 31 décembre de l'année suivante
    startDateInput.addEventListener('change', function() {
        if (this.value) {
            const startDate = new Date(this.value);
            const startYear = startDate.getFullYear();
            const endYear = startYear + 1;
            
            // Créer la date de fin au 31 décembre de l'année suivante
            const endDate = new Date(endYear, 11, 31); // 11 = décembre (0-indexé)
            
            // Formater la date au format YYYY-MM-DD
            const year = endDate.getFullYear();
            const month = String(endDate.getMonth() + 1).padStart(2, '0');
            const day = String(endDate.getDate()).padStart(2, '0');
            const formattedEndDate = `${year}-${month}-${day}`;
            
            endDateInput.value = formattedEndDate;
        }
    });

    // Utiliser la fonction standardisée pour charger les salles
    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            if (this.value) {
                loadContractRoomsSimple(this.value, 'rooms-container');
            } else {
                roomsContainer.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Sélectionnez d'abord un client
                    </div>
                `;
            }
        });
        
        // Si un client est déjà sélectionné (ex: rechargement avec erreur de formulaire), charger ses salles
        if (clientSelect.value) {
            loadContractRoomsSimple(clientSelect.value, 'rooms-container');
        }
    } else if (<?php echo $client ? 'true' : 'false'; ?>) {
        // Si le client est fixé (passé par l'URL), charger ses salles au démarrage
        loadContractRoomsSimple(<?php echo $client ? $client['id'] : 'null'; ?>, 'rooms-container');
    }
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 