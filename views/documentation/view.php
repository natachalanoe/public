<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la documentation client
 * Affiche les documents associés à un client
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['type'] ?? null;

// Récupérer l'ID du client depuis l'URL
$clientId = isset($client['id']) ? $client['id'] : '';

setPageVariables(
    'Documentation',
    'documentation' . ($clientId ? '_view_' . $clientId : '')
);

// Définir la page courante pour le menu
$currentPage = 'documentation';

// Vérifier si l'utilisateur a les droits pour ajouter un document
$canAddDocument = false;

if ($userType === 'admin') {
    $canAddDocument = true;
} else if (isset($_SESSION['user']['permissions']['rights']['tech_manage_documents']) && 
          $_SESSION['user']['permissions']['rights']['tech_manage_documents'] === true) {
    $canAddDocument = true;
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Documents du client</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <?php if (isset($client) && $client): ?>
            <a href="<?php echo BASE_URL; ?>clients/view/<?php echo $client['id']; ?>" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour au client
            </a>
        <?php endif; ?>
        <?php if ($canAddDocument && isset($client) && $client): ?>
            <a href="<?php echo BASE_URL; ?>documentation/add?client_id=<?php echo $client['id']; ?>" class="btn btn-primary">
                <i class="bi bi-plus me-1"></i> Ajouter un document
            </a>
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

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header py-2">
            <h6 class="card-title mb-0">Filtres</h6>
        </div>
        <div class="card-body py-2">
            <form method="get" action="" class="row g-3 align-items-end" id="filterForm">
                <?php if (isset($client) && $client): ?>
                    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                <?php endif; ?>
                
                <div class="col-md-4">
                    <label for="site_id" class="form-label fw-bold mb-0">Site</label>
                    <select class="form-select" id="site_id" name="site_id" onchange="updateRooms()">
                        <option value="">Tous les sites</option>
                        <?php if (isset($sites) && is_array($sites)): ?>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= $siteId == $site['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($site['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="room_id" class="form-label fw-bold mb-0">Salle</label>
                    <select class="form-select" id="room_id" name="room_id" onchange="document.getElementById('filterForm').submit();">
                        <option value="">Toutes les salles</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id'] ?>" <?= $roomId == $room['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($room['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                                <h6 class="card-title mb-0"><?= htmlspecialchars($document['title']) ?></h6>
                                                <div>
                                                    <?php
                                                        // $userType is already defined at the top of the view
                                                        $canEditDocument = false;
                                                        if ($userType === 'admin') {
                                                            $canEditDocument = true;
                                                        } else if ($userType === 'technicien' && isset($_SESSION['user']['permissions']['rights']['tech_manage_documents']) && $_SESSION['user']['permissions']['rights']['tech_manage_documents'] === true) {
                                                            $canEditDocument = true;
                                                        }
                                                    ?>
                                                    <div class="d-flex gap-1">
                                                        <?php if ($canEditDocument): ?>
                                                            <a href="<?= BASE_URL ?>documentation/edit/<?= $document['id'] ?>" 
                                                               class="btn btn-sm btn-outline-warning btn-action" 
                                                               title="Modifier le document">
                                                                <i class="bi bi-pencil me-1"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($userType === 'admin'): ?>
                                                            <a href="<?= BASE_URL ?>documentation/delete/<?= $document['id'] ?>" 
                                                               class="btn btn-sm btn-outline-danger btn-action" 
                                                               onclick="return confirmDelete('<?= htmlspecialchars($document['title']) ?>');"
                                                               title="Supprimer le document">
                                                                <i class="bi bi-trash me-1"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
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
                                                        <div class="d-flex gap-2">
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-info btn-action" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#previewModal<?= $document['id'] ?>"
                                                                    title="Prévisualiser">
                                                                <i class="bi bi-eye me-1"></i>
                                                            </button>
                                                            <a href="<?= BASE_URL ?>documentation/download/<?= $document['id'] ?>" 
                                                               class="btn btn-sm btn-outline-primary btn-action"
                                                               title="Télécharger">
                                                                <i class="bi bi-download me-1"></i>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($document['content'])): ?>
                                    <!-- Modal pour le contenu -->
                                    <div class="modal fade" id="contentModal<?= $document['id'] ?>" tabindex="-1" aria-labelledby="contentModalLabel<?= $document['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="contentModalLabel<?= $document['id'] ?>"><?= htmlspecialchars($document['title']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="content-wrapper">
                                                        <?= $document['content'] ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($document['attachment_path']): ?>
                                    <!-- Modal d'aperçu -->
                                    <div class="modal fade" id="previewModal<?= $document['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-xl">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?= htmlspecialchars($document['title']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="preview-container">
                                                        <?php if (strtolower(pathinfo($document['attachment_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                                                            <iframe src="<?= BASE_URL . $document['attachment_path'] ?>" 
                                                                    width="100%" 
                                                                    height="600px" 
                                                                    frameborder="0">
                                                            </iframe>
                                                        <?php elseif (in_array(strtolower(pathinfo($document['attachment_path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                            <img src="<?= BASE_URL . $document['attachment_path'] ?>" 
                                                                 class="img-fluid" 
                                                                 alt="<?= htmlspecialchars($document['title']) ?>">
                                                        <?php else: ?>
                                                            <div class="alert alert-info">
                                                                <i class="bi bi-info-circle me-1"></i> 
                                                                Ce type de fichier ne peut pas être prévisualisé. 
                                                                <a href="<?= BASE_URL . $document['attachment_path'] ?>" 
                                                                   class="alert-link" 
                                                                   target="_blank">
                                                                    Télécharger le fichier
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2 me-1"></i> Aucun document trouvé pour ce client.
        </div>
    <?php endif; ?>
</div>

<script>
// Script pour mettre à jour les salles en fonction du site sélectionné
function updateRooms() {
    const siteId = document.getElementById('site_id').value;
    if (siteId) {
        fetch('<?= BASE_URL ?>documentation/get_rooms?site_id=' + siteId)
            .then(response => response.json())
            .then(data => {
                const roomSelect = document.getElementById('room_id');
                roomSelect.innerHTML = '<option value="">Toutes les salles</option>';
                
                data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.name;
                    roomSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Erreur:', error));
    } else {
        document.getElementById('room_id').innerHTML = '<option value="">Toutes les salles</option>';
    }
}

// Script pour ouvrir/fermer tous les accordéons
document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('toggleAccordionButton');
    let isOpen = false;
    
    toggleButton.addEventListener('click', function() {
        const accordionButtons = document.querySelectorAll('.accordion-button');
        const accordionCollapse = document.querySelectorAll('.accordion-collapse');
        
        if (isOpen) {
            // Fermer tous les accordéons
            accordionButtons.forEach(button => {
                button.classList.add('collapsed');
                button.setAttribute('aria-expanded', 'false');
            });
            
            accordionCollapse.forEach(collapse => {
                collapse.classList.remove('show');
            });
            
            toggleButton.innerHTML = '<i class="bi bi-plus-square me-1 me-1"></i>Ouvrir tout';
        } else {
            // Ouvrir tous les accordéons
            accordionButtons.forEach(button => {
                button.classList.remove('collapsed');
                button.setAttribute('aria-expanded', 'true');
            });
            
            accordionCollapse.forEach(collapse => {
                collapse.classList.add('show');
            });
            
            toggleButton.innerHTML = '<i class="bi bi-dash-square me-1 me-1"></i>Fermer tout';
        }
        
        isOpen = !isOpen;
    });
});

// Confirmation de suppression
function confirmDelete(title) {
    return confirm(`Êtes-vous sûr de vouloir supprimer le document "${title}" ?`);
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 