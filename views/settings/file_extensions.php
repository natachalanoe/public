<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Récupérer la connexion à la base de données si elle n'est pas disponible
if (!isset($db)) {
    global $db;
}

// Traitement des actions POST (AVANT d'inclure les headers)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $extension = strtolower(trim($_POST['extension'] ?? ''));
                $mimeType = trim($_POST['mime_type'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                // Validation
                if (empty($extension)) {
                    $_SESSION['error'] = "Extension vide";
                } elseif (!preg_match('/^[a-z0-9]+$/', $extension)) {
                    $_SESSION['error'] = "Format d'extension invalide";
                } elseif (FileUploadValidator::isExtensionBlacklisted($extension)) {
                    $_SESSION['error'] = "Extension interdite pour des raisons de sécurité";
                } else {
                    // Vérifier si l'extension existe déjà
                    $stmt = $db->prepare("SELECT id FROM settings_allowed_extensions WHERE extension = ?");
                    $stmt->execute([$extension]);
                    if ($stmt->fetch()) {
                        $_SESSION['error'] = "Extension déjà présente";
                    } else {
                        // Ajouter l'extension
                        try {
                            $stmt = $db->prepare("INSERT INTO settings_allowed_extensions (extension, mime_type, description) VALUES (?, ?, ?)");
                            $stmt->execute([$extension, $mimeType, $description]);
                            $_SESSION['success'] = "Extension $extension ajoutée avec succès";
                        } catch (Exception $e) {
                            $_SESSION['error'] = "Erreur lors de l'ajout de l'extension : " . $e->getMessage();
                        }
                    }
                }
                break;
            case 'toggle':
                $extensionId = $_POST['extension_id'] ?? null;
                $isActive = $_POST['is_active'] ?? 0;
                
                if ($extensionId) {
                    try {
                        $stmt = $db->prepare("UPDATE settings_allowed_extensions SET is_active = ? WHERE id = ?");
                        $stmt->execute([$isActive, $extensionId]);
                        echo json_encode(['success' => true]);
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'ID manquant']);
                }
                exit;
            case 'delete':
                $extensionId = $_POST['extension_id'] ?? null;
                
                if ($extensionId) {
                    try {
                        // Récupérer l'extension avant suppression pour le message
                        $stmt = $db->prepare("SELECT extension FROM settings_allowed_extensions WHERE id = ?");
                        $stmt->execute([$extensionId]);
                        $extension = $stmt->fetch();
                        
                        if ($extension) {
                            // Supprimer l'extension
                            $stmt = $db->prepare("DELETE FROM settings_allowed_extensions WHERE id = ?");
                            $stmt->execute([$extensionId]);
                            
                            echo json_encode(['success' => true]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Extension non trouvée']);
                        }
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'ID manquant']);
                }
                exit;
        }
        
        // Rediriger pour éviter la soumission multiple du formulaire
        header('Location: ' . BASE_URL . 'settings/fileExtensions');
        exit;
    }
}

setPageVariables('Extensions de fichiers autorisées', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données
$allowedExtensions = FileUploadValidator::getAllExtensions($db);
$blacklistedExtensions = FileUploadValidator::getBlacklistedExtensions();
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-file-earmark-arrow-up me-2 me-1"></i>Extensions de fichiers autorisées
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>settings" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2 me-1"></i>Retour aux paramètres
            </a>
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

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear text-primary me-2 me-1"></i>
                        Configuration des extensions autorisées
                    </h5>
                    <small class="text-muted">Configuration globale pour tout le site</small>
                </div>
                <div class="card-body">
                    <!-- Extensions autorisées -->
                    <div class="mb-4">
                        <h6>Extensions configurées :</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Extension</th>
                                        <th>Type MIME</th>
                                        <th>Description</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allowedExtensions as $ext): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($ext['extension']) ?></code></td>
                                        <td><?= htmlspecialchars($ext['mime_type'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($ext['description'] ?? '') ?></td>
                                        <td>
                                            <?php if ($ext['is_active']): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <?php if ($ext['is_active']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                            onclick="toggleExtension(<?= $ext['id'] ?>, 0)" 
                                                            title="Désactiver">
                                                        <i class="bi bi-pause me-1"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                                            onclick="toggleExtension(<?= $ext['id'] ?>, 1)" 
                                                            title="Activer">
                                                        <i class="bi bi-play me-1"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteExtension(<?= $ext['id'] ?>, '<?= htmlspecialchars($ext['extension']) ?>')" 
                                                        title="Supprimer">
                                                    <i class="bi bi-trash me-1"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Ajouter une extension -->
                    <div class="mb-4">
                        <h6>Ajouter une extension :</h6>
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="extension" class="form-label">Extension</label>
                                    <input type="text" class="form-control" id="extension" name="extension" 
                                           placeholder="ex: odt" maxlength="10" required>
                                    <div class="form-text">Lettres et chiffres uniquement</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="mime_type" class="form-label">Type MIME (optionnel)</label>
                                    <input type="text" class="form-control" id="mime_type" name="mime_type" 
                                           placeholder="ex: text/plain">
                                </div>
                                <div class="col-md-3">
                                    <label for="description" class="form-label">Description (optionnel)</label>
                                    <input type="text" class="form-control" id="description" name="description" 
                                           placeholder="ex: Fichier de configuration">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-plus me-2 me-1"></i>Ajouter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Extensions interdites -->
                    <div class="alert alert-warning">
                        <h6><i class="bi bi-exclamation-triangle me-2 me-1"></i>Extensions interdites pour des raisons de sécurité :</h6>
                        <small class="text-muted">
                            <?= implode(', ', $blacklistedExtensions) ?>
                        </small>
                        <br><small class="text-muted mt-2">
                            Ces extensions ne peuvent pas être ajoutées car elles représentent un risque de sécurité.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleExtension(extensionId, isActive) {
    if (!confirm('Êtes-vous sûr de vouloir modifier le statut de cette extension ?')) {
        return;
    }
    
    fetch('<?= BASE_URL ?>settings/fileExtensions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=toggle&extension_id=' + extensionId + '&is_active=' + isActive
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la modification');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la modification');
    });
}

function deleteExtension(extensionId, extensionName) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement l\'extension "' + extensionName + '" ?\n\nCette action ne peut pas être annulée.')) {
        return;
    }
    
    fetch('<?= BASE_URL ?>settings/fileExtensions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&extension_id=' + extensionId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la suppression : ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la suppression');
    });
}

// Validation côté client pour l'ajout d'extension
document.getElementById('extension').addEventListener('input', function() {
    const extension = this.value.toLowerCase();
    
    // Nettoyer l'extension (lettres et chiffres uniquement)
    const cleanExtension = extension.replace(/[^a-z0-9]/g, '');
    if (cleanExtension !== extension) {
        this.value = cleanExtension;
    }
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 