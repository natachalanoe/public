<?php
require_once __DIR__ . '/../../includes/functions.php';

// Vérification de l'accès - Utiliser le nouveau système de permissions
if (!isset($_SESSION['user']) || !canModifyInterventions()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une intervention.";
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le titre de la page pour le header
$pageTitle = "Nouvelle intervention";

// Inclure le header
include_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/functions.php';

// Vérification des permissions pour modifier les interventions
if (!canModifyInterventions()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une intervention.";
    header('Location: ' . BASE_URL . 'interventions');
    exit;
}

setPageVariables(
    'Nouvelle Intervention',
    'interventions'
);

// Définir la page courante pour le menu
$currentPage = 'interventions';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Nouvelle Intervention</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo BASE_URL; ?>interventions" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>
        
        <button type="submit" form="interventionForm" class="btn btn-primary">Créer l'intervention</button>
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

    <!-- Formulaire de création -->
    <div class="card">
                    <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="card-title mb-0">
                            <span class="fw-bold me-3">Nouvelle référence</span>
                            <input type="text" class="form-control d-inline-block bg-body text-body" id="title" name="title" form="interventionForm" placeholder="Titre de l'intervention" required>
                        </h5>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-0 text-white">Date de création</label>
                                <input type="date" class="form-control bg-body text-body" id="created_date" name="created_date" value="<?= date('Y-m-d') ?>" form="interventionForm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-0 text-white">Heure de création</label>
                                <input type="time" class="form-control bg-body text-body" id="created_time" name="created_time" value="<?= date('H:i') ?>" form="interventionForm">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <div class="card-body py-2">
            <form action="<?php echo BASE_URL; ?>interventions/store" method="post" id="interventionForm">
                <div class="row g-3">
                    <!-- Colonne 1 : Client, Site, Salle -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Client -->
                            <div>
                                <label class="form-label fw-bold mb-0">Client *</label>
                                <select class="form-select bg-body text-body" id="client_id" name="client_id" required>
                                    <option value="">Sélectionner un client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id'] ?>">
                                            <?= h($client['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Site -->
                            <div>
                                <label class="form-label fw-bold mb-0">Site</label>
                                <select class="form-select bg-body text-body" id="site_id" name="site_id">
                                    <option value="">Sélectionner un site</option>
                                </select>
                            </div>

                            <!-- Salle -->
                            <div>
                                <label class="form-label fw-bold mb-0">Salle</label>
                                <select class="form-select bg-body text-body" id="room_id" name="room_id">
                                    <option value="">Sélectionner une salle</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne 2 : Type, Déplacement, Contrat -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Type d'intervention -->
                            <div>
                                <label class="form-label fw-bold mb-0">Type d'intervention *</label>
                                <select class="form-select bg-body text-body" id="type_id" name="type_id" required>
                                    <option value="">Sélectionner un type</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?= $type['id'] ?>">
                                            <?= h($type['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Déplacement -->
                            <div>
                                <label class="form-label fw-bold mb-0">Déplacement</label>
                                <input type="text" class="form-control bg-body text-body" id="type_requires_travel" value="Non" readonly>
                                <input type="hidden" name="type_requires_travel" value="0">
                            </div>

                            <!-- Contrat -->
                            <div>
                                <label class="form-label fw-bold mb-0">Contrat associé *</label>
                                <select class="form-select bg-body text-body" id="contract_id" name="contract_id" required>
                                    <option value="">Sélectionner un contrat</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne 3 : Statut, Priorité, Technicien -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Statut -->
                            <div>
                                <label class="form-label fw-bold mb-0">Statut *</label>
                                <select class="form-select bg-body text-body" id="status_id" name="status_id" required>
                                    <option value="">Sélectionner un statut</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <?php 
                                        // Présélectionner le statut "Nouveau" (généralement ID 1)
                                        $isSelected = ($status['name'] == 'Nouveau' || $status['id'] == 1) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $status['id'] ?>" <?= $isSelected ?>>
                                            <?= h($status['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Priorité -->
                            <div>
                                <label class="form-label fw-bold mb-0">Priorité *</label>
                                <select class="form-select bg-body text-body" id="priority_id" name="priority_id" required>
                                    <option value="">Sélectionner une priorité</option>
                                    <?php foreach ($priorities as $priority): ?>
                                        <?php 
                                        // Présélectionner la priorité "Moyenne" (généralement ID 2)
                                        $isSelected = ($priority['name'] == 'Moyenne' || $priority['id'] == 2) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $priority['id'] ?>" <?= $isSelected ?>>
                                            <?= h($priority['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Technicien -->
                            <div>
                                <label class="form-label fw-bold mb-0">Technicien</label>
                                <select class="form-select bg-body text-body" id="technician_id" name="technician_id">
                                    <option value="">Sélectionner un technicien</option>
                                    <?php foreach ($technicians as $technician): ?>
                                        <option value="<?= $technician['id'] ?>">
                                            <?= h($technician['first_name'] ?? '') ?> <?= h($technician['last_name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Colonne 4 : Date planifiée, Heure planifiée, Durée -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Date planifiée -->
                            <div>
                                <label class="form-label fw-bold mb-0">Date planifiée</label>
                                <input type="date" class="form-control bg-body text-body" id="date_planif" name="date_planif">
                            </div>

                            <!-- Heure planifiée -->
                            <div>
                                <label class="form-label fw-bold mb-0">Heure planifiée</label>
                                <input type="time" class="form-control bg-body text-body" id="heure_planif" name="heure_planif">
                            </div>
                            
                            <!-- Durée -->
                            <div>
                                <label class="form-label fw-bold mb-0">Durée</label>
                                <select class="form-select bg-body text-body" id="duration" name="duration">
                                    <option value="">Sélectionner une durée</option>
                                    <?php foreach ($durations as $duration): ?>
                                        <option value="<?= $duration['duration'] ?>">
                                            <?= number_format($duration['duration'], 2) ?> heure(s)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Description sur une ligne complète -->
                    <div class="col-12 mt-3">
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">Description</h6>
                            </div>
                            <div class="card-body py-2">
                                <textarea class="form-control bg-body text-body" id="description" name="description" rows="5"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Informations de contact et demande -->
                    <div class="col-12 mt-3">
                        <div class="card contact-info-card">
                            <div class="card-header py-2 contact-info-header">
                                <h6 class="card-title mb-0 fw-bold">
                                    <i class="bi bi-person-lines-fill me-2"></i>Informations de contact et demande
                                </h6>
                            </div>
                            <div class="card-body py-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Demande par</label>
                                        <input type="text" class="form-control bg-body text-body" id="demande_par" name="demande_par" placeholder="Nom de la personne qui a demandé l'intervention">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Référence client</label>
                                        <input type="text" class="form-control bg-body text-body" id="ref_client" name="ref_client" placeholder="Référence interne du client">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Contact existant</label>
                                        <select class="form-select bg-body text-body" id="contact_client_select" name="contact_client_select">
                                            <option value="">Sélectionner un contact existant</option>
                                            <!-- Les contacts seront chargés dynamiquement selon le client -->
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Ou saisir un email</label>
                                        <input type="email" class="form-control bg-body text-body" id="contact_client" name="contact_client" placeholder="email@exemple.com">
                                        <div class="invalid-feedback" id="email-error"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser BASE_URL pour JavaScript
    initBaseUrl('<?php echo BASE_URL; ?>');
    
    const clientSelect = document.getElementById('client_id');
    const siteSelect = document.getElementById('site_id');
    const roomSelect = document.getElementById('room_id');
    const typeSelect = document.getElementById('type_id');
    const typeRequiresTravelInput = document.getElementById('type_requires_travel');
    const typeRequiresTravelHidden = document.querySelector('input[name="type_requires_travel"]');
    const contractSelect = document.getElementById('contract_id');
    
    // Utiliser les fonctions centralisées pour charger les sites et salles dynamiquement
    clientSelect.addEventListener('change', function() {
        loadSites(this.value, 'site_id', null, null, function() {
            updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
        });
    });
    
    siteSelect.addEventListener('change', function() {
        loadRooms(this.value, 'room_id', null, function() {
            updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
        });
    });
    
    roomSelect.addEventListener('change', function() {
        updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
        // Pré-sélectionner le contrat associé à la salle sélectionnée
        const roomId = this.value;
        if (roomId) {
            fetch(`${BASE_URL}interventions/getContractByRoom/${roomId}`)
                .then(response => response.json())
                .then(contract => {
                    if (contract && contract.id) {
                        setTimeout(() => {
                            const option = contractSelect.querySelector(`option[value="${contract.id}"]`);
                            if (option) {
                                option.selected = true;
                            }
                        }, 100);
                    }
                })
                .catch(error => console.error('Erreur lors de la récupération du contrat de la salle:', error));
        }
    });
    
    typeSelect.addEventListener('change', function() {
        updateTypeRequiresTravel('type_id', 'type_requires_travel', 'type_requires_travel');
    });

    // Gestion des contacts clients
    const contactClientSelect = document.getElementById('contact_client_select');
    const contactClientInput = document.getElementById('contact_client');
    
    // Charger les contacts quand le client change
    clientSelect.addEventListener('change', function() {
        loadContacts(this.value);
    });
    
    // Quand on sélectionne un contact existant, remplir le champ email
    contactClientSelect.addEventListener('change', function() {
        if (this.value) {
            contactClientInput.value = this.value;
        }
    });
    
    // Fonction pour charger les contacts d'un client
    function loadContacts(clientId) {
        if (!clientId) {
            contactClientSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
            return;
        }
        
        fetch(`${BASE_URL}interventions/getContacts/${clientId}`)
            .then(response => response.json())
            .then(contacts => {
                contactClientSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
                contacts.forEach(contact => {
                    const option = document.createElement('option');
                    option.value = contact.email;
                    option.textContent = `${contact.first_name} ${contact.last_name} (${contact.email})`;
                    contactClientSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Erreur lors du chargement des contacts:', error));
    }

    // Initialiser le champ de déplacement
    updateTypeRequiresTravel('type_id', 'type_requires_travel', 'type_requires_travel');
    
    // Validation de l'email
    const emailError = document.getElementById('email-error');
    
    contactClientInput.addEventListener('input', function() {
        validateEmail(this.value);
    });
    
    contactClientInput.addEventListener('blur', function() {
        validateEmail(this.value);
    });
    
    function validateEmail(email) {
        // Réinitialiser les erreurs
        contactClientInput.classList.remove('is-invalid', 'is-valid');
        emailError.textContent = '';
        
        // Si le champ est vide, pas de validation
        if (!email.trim()) {
            return true;
        }
        
        // Regex pour valider l'email
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        
        if (!emailRegex.test(email)) {
            contactClientInput.classList.add('is-invalid');
            emailError.textContent = 'Format d\'email invalide. Exemple : nom@domaine.com';
            return false;
        } else {
            contactClientInput.classList.add('is-valid');
            return true;
        }
    }
    
    // Validation du formulaire avant soumission
    document.getElementById('interventionForm').addEventListener('submit', function(e) {
        const email = contactClientInput.value.trim();
        if (email && !validateEmail(email)) {
            e.preventDefault();
            contactClientInput.focus();
            return false;
        }
    });
});
</script>

<style>
/* Styles pour la carte des informations de contact */
.contact-info-card {
    border-width: 2px !important;
    border-style: solid !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
}

.contact-info-header {
    border-bottom: 2px solid !important;
}

/* Mode clair */
[data-bs-theme="light"] .contact-info-card {
    background-color: #f8f9fa !important;
    border-color: #dee2e6 !important;
}

[data-bs-theme="light"] .contact-info-header {
    background-color: #e9ecef !important;
    border-bottom-color: #dee2e6 !important;
    color: #495057 !important;
}

/* Mode sombre */
[data-bs-theme="dark"] .contact-info-card {
    background-color: var(--bs-body-bg) !important;
    border-color: var(--bs-border-color) !important;
}

[data-bs-theme="dark"] .contact-info-header {
    background-color: var(--bs-secondary-bg) !important;
    border-bottom-color: var(--bs-border-color) !important;
    color: var(--bs-body-color) !important;
}
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 