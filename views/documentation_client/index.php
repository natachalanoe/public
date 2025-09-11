<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la documentation client
 * Affiche les documents selon les localisations autorisées du client
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Ma Documentation',
    'documentation_client'
);

// Définir la page courante pour le menu
$currentPage = 'documentation_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$sites = $sites ?? [];
$rooms = $rooms ?? [];
$categories = $categories ?? [];
$documentsByCategory = $documentsByCategory ?? [];
$siteId = $siteId ?? null;
$roomId = $roomId ?? null;
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec titre -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="fas fa-book me-2"></i>Ma Documentation
                    </h4>
                    <p class="text-muted mb-0">Consultation de la documentation de vos sites autorisés</p>
                </div>
                <div>
                    <?php if (hasPermission('client_add_documentation')): ?>
                        <a href="<?= BASE_URL ?>documentation_client/add" class="btn btn-primary">
                            <i class="bi bi-plus me-2 me-1"></i>Ajouter un document
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages d'erreur/succès -->
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

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header py-2">
            <h6 class="card-title mb-0">Filtres</h6>
        </div>
        <div class="card-body py-2">
            <form method="get" action="" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-4">
                    <label for="site_id" class="form-label fw-bold mb-0">Site</label>
                    <select class="form-select bg-body text-body" id="site_id" name="site_id" onchange="updateRoomsAndSubmit()">
                        <option value="">Tous les sites</option>
                        <?php if (isset($sites) && is_array($sites)): ?>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= ($siteId ?? '') == $site['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($site['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="room_id" class="form-label fw-bold mb-0">Salle</label>
                    <select class="form-select bg-body text-body" id="room_id" name="room_id" onchange="document.getElementById('filterForm').submit();">
                        <option value="">Toutes les salles</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id'] ?>" <?= ($roomId ?? '') == $room['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($room['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <a href="<?= BASE_URL ?>documentation_client" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-2 me-1"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bouton Ouvrir/Fermer tout -->
    <div class="d-flex justify-content-end align-items-center mb-3">
        <button type="button" id="toggleAccordionButton" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-plus-square me-1 me-1"></i>Ouvrir tout
        </button>
    </div>

    <!-- Documents par catégorie -->
    <?php if (!empty($documentsByCategory)): ?>
        <div class="accordion" id="documentsAccordion">
            <?php foreach ($documentsByCategory as $index => $categoryData): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?= $index ?>">
                        <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#collapse<?= $index ?>" 
                                aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" 
                                aria-controls="collapse<?= $index ?>">
                            <?= htmlspecialchars($categoryData['category']['name']) ?>
                            <span class="badge bg-primary ms-2 float-end"><?= count($categoryData['documents']) ?></span>
                        </button>
                    </h2>
                    <div id="collapse<?= $index ?>" 
                         class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" 
                         aria-labelledby="heading<?= $index ?>" 
                         data-bs-parent="#documentsAccordion">
                        <div class="accordion-body p-0">
                            <div class="row p-3">
                                <?php foreach ($categoryData['documents'] as $document): ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="card h-100">
                                            <div class="card-header py-2">
                                                <h6 class="card-title mb-0"><?= htmlspecialchars($document['title']) ?></h6>
                                                <?php 
                                                // Vérifier si l'utilisateur peut modifier/supprimer ce document
                                                $canEditDocument = false;
                                                $canDeleteDocument = false;
                                                
                                                if (hasPermission('client_add_documentation')) {
                                                    // L'utilisateur peut modifier/supprimer ses propres documents
                                                    if ($document['created_by'] == $_SESSION['user']['id']) {
                                                        $canEditDocument = true;
                                                        $canDeleteDocument = true;
                                                    }
                                                }
                                                ?>
                                                <?php if ($canEditDocument || $canDeleteDocument): ?>
                                                    <div class="d-flex gap-1">
                                                        <?php if ($canEditDocument): ?>
                                                            <a href="<?= BASE_URL ?>documentation_client/edit/<?= $document['id'] ?>" 
                                                               class="btn btn-sm btn-outline-warning btn-action" 
                                                               title="Modifier le document">
                                                                <i class="bi bi-pencil me-1"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($canDeleteDocument): ?>
                                                            <a href="<?= BASE_URL ?>documentation_client/delete/<?= $document['id'] ?>" 
                                                               class="btn btn-sm btn-outline-danger btn-action" 
                                                               onclick="return confirmDelete('<?= htmlspecialchars($document['title']) ?>');"
                                                               title="Supprimer le document">
                                                                <i class="bi bi-trash me-1"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="card-text small mb-2"><?= nl2br(htmlspecialchars($document['description'])) ?></p>
                                                
                                                <?php if (!empty($document['content'])): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-secondary btn-action" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#contentModal<?= $document['id'] ?>" 
                                                            title="Voir les détails du contenu">
                                                        <i class="fas fa-file-alt"></i> Voir le détail
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer py-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="text-muted small">
                                                        <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($document['created_at'])) ?>
                                                    </div>
                                                    <?php if (!empty($document['attachment_path'])): ?>
                                                        <a href="<?= BASE_URL . $document['attachment_path'] ?>" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-outline-primary btn-action" 
                                                           title="Télécharger">
                                                            <i class="bi bi-download me-1"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($document['site_name']) || !empty($document['room_name'])): ?>
                                                    <div class="text-muted small mt-1">
                                                        <?php if (!empty($document['site_name'])): ?>
                                                            <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($document['site_name']) ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($document['room_name'])): ?>
                                                            <br><i class="bi bi-door-open me-1"></i> <?= htmlspecialchars($document['room_name']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun document trouvé</h5>
                <p class="text-muted mb-3">Aucun document ne correspond aux critères sélectionnés ou vous n'avez pas accès à la documentation.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modals pour le contenu des documents -->
<?php if (!empty($documentsByCategory)): ?>
    <?php foreach ($documentsByCategory as $categoryData): ?>
        <?php foreach ($categoryData['documents'] as $document): ?>
            <?php if (!empty($document['content'])): ?>
                <div class="modal fade" id="contentModal<?= $document['id'] ?>" tabindex="-1" aria-labelledby="contentModalLabel<?= $document['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="contentModalLabel<?= $document['id'] ?>">
                                    <?= htmlspecialchars($document['title']) ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <strong>Description :</strong>
                                    <p><?= nl2br(htmlspecialchars($document['description'])) ?></p>
                                </div>
                                <div>
                                    <strong>Contenu :</strong>
                                    <div class="border rounded p-3 bg-light">
                                        <?= nl2br(htmlspecialchars($document['content'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                <?php if (!empty($document['attachment_path'])): ?>
                                    <a href="<?= BASE_URL . $document['attachment_path'] ?>" 
                                       target="_blank" 
                                       class="btn btn-primary">
                                        <i class="bi bi-download me-2 me-1"></i>Télécharger
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Fonction pour mettre à jour les salles selon le site sélectionné ET soumettre le formulaire
function updateRoomsAndSubmit() {
    const siteId = document.getElementById('site_id').value;
    console.log('updateRoomsAndSubmit appelé avec siteId:', siteId);
    
    if (siteId) {
        const url = '<?= BASE_URL ?>documentation_client/get_rooms';
        console.log('URL de la requête:', url);
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'site_id=' + siteId
        })
            .then(response => {
                console.log('Réponse reçue:', response);
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Données reçues:', data);
                const roomSelect = document.getElementById('room_id');
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
        document.getElementById('room_id').innerHTML = '<option value="">Toutes les salles</option>';
        
        // Soumettre le formulaire même si aucun site n'est sélectionné
        document.getElementById('filterForm').submit();
    }
}

// Fonction pour ouvrir/fermer tous les accordéons
document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('toggleAccordionButton');
    const accordionItems = document.querySelectorAll('.accordion-collapse');
    
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            const isAllOpen = Array.from(accordionItems).every(item => item.classList.contains('show'));
            
            if (isAllOpen) {
                // Fermer tout
                accordionItems.forEach(item => {
                    const bsCollapse = new bootstrap.Collapse(item, { hide: true });
                });
                toggleButton.innerHTML = '<i class="bi bi-plus-square me-1 me-1"></i>Ouvrir tout';
            } else {
                // Ouvrir tout
                accordionItems.forEach(item => {
                    const bsCollapse = new bootstrap.Collapse(item, { show: true });
                });
                toggleButton.innerHTML = '<i class="bi bi-dash-square me-1 me-1"></i>Fermer tout';
            }
        });
    }
});

// Initialiser les filtres au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    updateSites();
});

// Fonction de confirmation de suppression
function confirmDelete(documentTitle) {
    return confirm('Êtes-vous sûr de vouloir supprimer le document "' + documentTitle + '" ? Cette action est irréversible.');
}
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 