<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'édition d'un contrat
 * Permet de modifier un contrat existant
 */

// Vérifier si l'utilisateur est connecté et a les permissions
if (!isset($_SESSION['user']) || !canManageContracts()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier un contrat.";
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['type'] ?? null;

// Récupérer les données du formulaire de la session si elles existent
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Récupérer les données du client et du contrat
$client = $client ?? null;
$contract = $contract ?? null;

setPageVariables(
    'Modifier le contrat',
    'contracts'
);

// Définir la page courante pour le menu
$currentPage = 'contracts';

// Déterminer l'URL de retour dynamiquement
$returnTo = $_GET['return_to'] ?? null;
if ($returnTo === 'contracts') {
    // Si on vient de la liste des contrats, retourner à cette liste
    $returnUrl = BASE_URL . 'contracts';
} elseif ($returnTo === 'client') {
    // Si on vient de la vue ou édition du client, retourner à l'édition du client avec l'onglet contrats ouvert
    $returnUrl = BASE_URL . 'clients/edit/' . $client['id'] . '#contracts';
} else {
    // Par défaut, retourner à l'édition du client avec l'onglet contrats ouvert
    $returnUrl = BASE_URL . 'clients/edit/' . $client['id'] . '#contracts';
}

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
        <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Modifier le contrat <?php echo htmlspecialchars($contract['name']); ?></h4></div>

        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary me-2">
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
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>contracts/update/<?php echo $contract['id']; ?>" method="POST" id="contractForm">
                <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                <input type="hidden" name="redirect_to" value="view">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom du contrat <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-body text-body" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($formData['name'] ?? $contract['name']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="contract_type_id" class="form-label">Type de contrat <span class="text-danger">*</span></label>
                            <select class="form-control bg-body text-body" id="contract_type_id" name="contract_type_id" required>
                                <option value="">Sélectionnez un type</option>
                                <?php foreach ($contractTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            <?php echo (isset($formData['contract_type_id']) ? $formData['contract_type_id'] : $contract['contract_type_id']) == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Salles associées</label>
                            <div id="rooms-container" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <div class="text-center text-muted">
                                    <i class="bi bi-arrow-clockwise spin me-1"></i> Chargement des salles...
                                </div>
                            </div>
                            <small class="form-text text-muted">Cochez les salles que vous souhaitez associer à ce contrat.</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control bg-body text-body" id="start_date" name="start_date" required
                                   value="<?php echo htmlspecialchars($formData['start_date'] ?? $contract['start_date']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="end_date" class="form-label">Date de fin <span class="text-danger">*</span></label>
                            <input type="date" class="form-control bg-body text-body" id="end_date" name="end_date" required
                                   value="<?php echo htmlspecialchars($formData['end_date'] ?? $contract['end_date']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="tickets_number" class="form-label">Nombre de tickets initiaux <span class="text-danger">*</span></label>
                            <input type="number" class="form-control bg-body text-body" id="tickets_number" name="tickets_number" required
                                   value="<?php echo htmlspecialchars($formData['tickets_number'] ?? $contract['tickets_number']); ?>">
                            <small class="form-text text-muted">Si le nombre de tickets est à zéro, le contrat ne sera pas considéré comme un contrat à tickets.</small>
                        </div>

                        <div class="mb-3">
                            <label for="tickets_remaining" class="form-label">Tickets restants</label>
                            <input type="number" class="form-control bg-body text-body" id="tickets_remaining" name="tickets_remaining"
                                   value="<?php echo htmlspecialchars($contract['tickets_remaining']); ?>">
                            <small class="form-text text-muted">Le nombre de tickets restants est calculé après la clôture d'une intervention. Évitez de modifier la valeur manuellement.</small>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Statut <span class="text-danger">*</span></label>
                            <select class="form-control bg-body text-body" id="status" name="status" required>
                                <option value="actif" <?php echo (isset($formData['status']) ? $formData['status'] : $contract['status']) === 'actif' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif" <?php echo (isset($formData['status']) ? $formData['status'] : $contract['status']) === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                                <option value="en_attente" <?php echo (isset($formData['status']) ? $formData['status'] : $contract['status']) === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
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
                                                <?php echo (isset($formData['access_level_id']) ? $formData['access_level_id'] : $contract['access_level_id']) == $level['id'] ? 'selected' : ''; ?>>
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
                            <textarea class="form-control bg-body text-body" id="comment" name="comment" rows="3"><?php echo htmlspecialchars($formData['comment'] ?? $contract['comment'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="reminder_enabled" name="reminder_enabled" value="1" 
                                       <?php echo (isset($formData['reminder_enabled']) ? $formData['reminder_enabled'] : $contract['reminder_enabled']) ? 'checked' : ''; ?>>
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
                                   value="<?php echo htmlspecialchars($formData['reminder_days'] ?? $contract['reminder_days'] ?? '30'); ?>" min="1" max="365">
                            <small class="form-text text-muted">Nombre de jours avant la fin du contrat pour déclencher le rappel</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="renouvellement_tacite" name="renouvellement_tacite" value="1" 
                                       <?php echo (isset($formData['renouvellement_tacite']) ? $formData['renouvellement_tacite'] : ($contract['renouvellement_tacite'] ?? false)) ? 'checked' : ''; ?>>
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
                                   value="<?php echo htmlspecialchars($formData['num_facture'] ?? $contract['num_facture'] ?? ''); ?>" 
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
                                   value="<?php echo htmlspecialchars($formData['tarif'] ?? $contract['tarif'] ?? ''); ?>" 
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
                                   value="<?php echo htmlspecialchars($formData['indice'] ?? $contract['indice'] ?? ''); ?>" 
                                   placeholder="2025-01">
                            <small class="form-text text-muted">Indice pour les révisions tarifaires</small>
                        </div>
                    </div>
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
    
    const clientId = <?php echo $client['id']; ?>;
    const contractId = <?php echo $contract['id']; ?>;

    // Utiliser la fonction standardisée pour charger les salles avec pré-sélection
    loadContractRoomsSimple(clientId, 'rooms-container', contractId);
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 