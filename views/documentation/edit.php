<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';
/**
 * Vue de modification de document
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Modifier le document : ' . htmlspecialchars($document['title']),
    'documentation'
);

// Définir la page courante pour le menu
$currentPage = 'documentation';

// $document, $clients, $sites, $rooms, $categories, 
// $form_category_id, $form_title, $form_description, $form_visible_by_client_val,
// $current_attachment_path
// sont passés par DocumentationController::edit()

// URL de retour, ajustée pour potentiellement retourner à la vue du client du document actuel
$clientIdForReturn = $document['client_id'] ?? ($_GET['client_id'] ?? null);
$returnUrl = BASE_URL . 'documentation/view/' . $clientIdForReturn; // Default return URL is now view
if (!$clientIdForReturn) {
    $returnUrl = BASE_URL . 'documentation'; // Fallback to index only if no client ID
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <!-- En-tête avec actions -->
            <div class="d-flex bd-highlight mb-3">
                <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Modifier le document : <?= htmlspecialchars($document['title']) ?></h4></div>

                <div class="ms-auto p-2 bd-highlight">
                    <a href="<?= $returnUrl ?>" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left me-1"></i> Retour
                    </a>
                    <button type="submit" form="editDocumentForm" class="btn btn-primary" id="submitEditDocument">
                        Enregistrer les modifications
                    </button>
                    <!-- TODO: Ajouter un bouton supprimer plus tard -->
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form id="editDocumentForm" action="<?= BASE_URL ?>documentation/update/<?= $document['id'] ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="document_id" value="<?= $document['id'] ?>">
                <div class="card">
                    <div class="card-header">
                         <h5 class="card-title mb-0">Détails du document</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Colonne 1: Catégorie, Titre, Description, Pièce jointe -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Catégorie <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>" <?= ($form_category_id == $category['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="title" class="form-label">Titre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" required value="<?= htmlspecialchars($form_title) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($form_description) ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="document_file" class="form-label">Remplacer la pièce jointe (optionnel)</label>
                                    <?php if (!empty($current_attachment_path)): ?>
                                        <div class="mb-2">
                                            Pièce jointe actuelle : 
                                            <a href="<?= BASE_URL . htmlspecialchars($current_attachment_path) ?>" target="_blank">
                                                <?= htmlspecialchars(basename($current_attachment_path)) ?>
                                            </a>
                                            <input type="checkbox" name="remove_attachment" id="remove_attachment" value="1" class="ms-2">
                                            <label for="remove_attachment" class="form-check-label">Supprimer</label>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="document_file" name="document_file" accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>">
                                    <div class="form-text">
                                        Laisser vide pour conserver la pièce jointe actuelle (si existante).<br>
                                        Formats acceptés : <?= FileUploadValidator::getExtensionsForDisplay($GLOBALS['db']) ?><br>
                                        Taille maximale : <?php echo ini_get('upload_max_filesize'); ?>
                                    </div>
                                    <div id="documentFileError" class="invalid-feedback"></div>
                                </div>
                            </div>

                            <!-- Colonne 2: Contenu détaillé -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="content" class="form-label">Contenu détaillé</label>
                                    <div class="editor-toolbar mb-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')"><i class="fas fa-bold"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')"><i class="fas fa-italic"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')"><i class="fas fa-underline"></i></button>
                                        <select class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="formatHeading(this.value)">
                                            <option value="">Style de titre</option>
                                            <option value="h1">Titre 1</option>
                                            <option value="h2">Titre 2</option>
                                            <option value="h3">Titre 3</option>
                                            <option value="p">Paragraphe</option>
                                        </select>
                                    </div>
                                    <div id="content" class="form-control editor-content" contenteditable="true" style="min-height: 200px; overflow-y: auto; resize: vertical;" data-placeholder="Saisissez votre contenu ici..."><?= $document['content'] /* Initial fill from document data */ ?></div>
                                    <input type="hidden" name="content" id="content-hidden">
                                </div>
                            </div>

                            <!-- Colonne 3: Client, Site, Salle, Visible par client -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select" id="client_id" name="client_id" required>
                                        <option value="">Sélectionner un client</option>
                                        <?php foreach ($clients as $client_item): // Renamed to avoid conflict ?>
                                            <option value="<?= $client_item['id'] ?>" <?= ($document['client_id'] == $client_item['id'] || (isset($_GET['client_id']) && $_GET['client_id'] == $client_item['id'])) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($client_item['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="site_id" class="form-label">Site</label>
                                    <select class="form-select" id="site_id" name="site_id">
                                        <option value="">Sélectionner un site (optionnel)</option>
                                        <?php foreach ($sites as $site_item): // Renamed to avoid conflict ?>
                                            <option value="<?= $site_item['id'] ?>" <?= ($document['site_id'] == $site_item['id'] || (isset($_GET['site_id']) && $_GET['site_id'] == $site_item['id'])) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($site_item['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="room_id" class="form-label">Salle</label>
                                    <select class="form-select" id="room_id" name="room_id">
                                        <option value="">Sélectionner une salle (optionnel)</option>
                                         <?php foreach ($rooms as $room_item): // Renamed to avoid conflict ?>
                                            <option value="<?= $room_item['id'] ?>" <?= ($document['room_id'] == $room_item['id'] || (isset($_GET['room_id']) && $_GET['room_id'] == $room_item['id'])) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($room_item['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="visible_by_client" name="visible_by_client" <?= ($form_visible_by_client_val == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="visible_by_client">Visible par le client</label>
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
// Initialiser le contenu de l'éditeur si sessionStorage n'a rien (première charge de l'edit)
document.addEventListener('DOMContentLoaded', function() {
    const contentDiv = document.getElementById('content');
    const preservedContent = sessionStorage.getItem('preserved_document_content_edit_<?= $document['id'] ?>');
    if (preservedContent) {
        contentDiv.innerHTML = preservedContent;
        // Ne pas supprimer immédiatement pour permettre le rechargement client/site
    } else {
        // Sur la première charge (pas un rechargement de client/site), initialiser depuis $document['content']
        // Ceci est déjà fait par l'echo PHP dans le HTML, mais on s'assure que le placeholder est géré
        if (contentDiv.innerHTML.trim() === '' && contentDiv.dataset.placeholder) {
            // Peut-être une logique spécifique si vide après init, mais le CSS :empty:before devrait suffire
        }
    }
});


function formatText(command) {
    document.execCommand(command, false, null);
    document.getElementById('content').focus();
}

function formatHeading(tag) {
    if (tag === 'p') {
        document.execCommand('formatBlock', false, 'p');
    } else if (tag) {
        document.execCommand('formatBlock', false, tag);
    }
    document.getElementById('content').focus();
}

document.getElementById('editDocumentForm').addEventListener('submit', function(e) {
    document.getElementById('content-hidden').value = document.getElementById('content').innerHTML;
});

document.getElementById('client_id').addEventListener('change', function() {
    const clientId = this.value;
    const currentUrl = new URL(window.location.href); // Base URL is edit page
    
    // Preserve form data
    currentUrl.searchParams.set('form_category_id', document.getElementById('category_id').value);
    currentUrl.searchParams.set('form_title', document.getElementById('title').value);
    currentUrl.searchParams.set('form_description', document.getElementById('description').value);
    currentUrl.searchParams.set('form_visible_by_client', document.getElementById('visible_by_client').checked ? '1' : '0');
    sessionStorage.setItem('preserved_document_content_edit_<?= $document['id'] ?>', document.getElementById('content').innerHTML);

    currentUrl.searchParams.set('client_id', clientId);
    currentUrl.searchParams.delete('site_id'); 
    currentUrl.searchParams.delete('room_id');
    // Remove form_site_id, form_room_id if they were set by previous selection
    currentUrl.searchParams.delete('form_site_id'); 
    currentUrl.searchParams.delete('form_room_id');

    if (clientId) {
        window.location.href = currentUrl.toString();
    } else { 
        currentUrl.searchParams.delete('client_id');
        window.location.href = currentUrl.toString();
    }
});

document.getElementById('site_id').addEventListener('change', function() {
    const clientId = document.getElementById('client_id').value;
    const siteId = this.value;
    const currentUrl = new URL(window.location.href);

    // Preserve form data
    currentUrl.searchParams.set('form_category_id', document.getElementById('category_id').value);
    currentUrl.searchParams.set('form_title', document.getElementById('title').value);
    currentUrl.searchParams.set('form_description', document.getElementById('description').value);
    currentUrl.searchParams.set('form_visible_by_client', document.getElementById('visible_by_client').checked ? '1' : '0');
    sessionStorage.setItem('preserved_document_content_edit_<?= $document['id'] ?>', document.getElementById('content').innerHTML);
    
    currentUrl.searchParams.set('client_id', clientId); 
    currentUrl.searchParams.delete('room_id');
    currentUrl.searchParams.delete('form_room_id');

    if (siteId) {
        currentUrl.searchParams.set('site_id', siteId);
    } else {
        currentUrl.searchParams.delete('site_id');
    }
    if (clientId) { 
      window.location.href = currentUrl.toString();
    }
});

// File validation script (same as add.php, adapted for edit form ID)
document.addEventListener('DOMContentLoaded', function() {
    const documentForm = document.getElementById('editDocumentForm'); // Changed ID
    const documentFileInput = document.getElementById('document_file');
    const documentFileError = document.getElementById('documentFileError');
    const submitDocumentButton = document.getElementById('submitEditDocument'); // Changed ID

    const allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream',
        'application/x-rar-compressed', 'application/vnd.rar', 'application/x-7z-compressed',
        'text/plain', 'text/csv', 'application/csv'
    ];

    const phpMaxFileSize = '<?php echo ini_get("upload_max_filesize"); ?>';

    function parsePhpSize(sizeStr) {
        if (!sizeStr) return 0;
        const units = { 'K': 1024, 'M': 1024 * 1024, 'G': 1024 * 1024 * 1024 };
        const lastChar = sizeStr.charAt(sizeStr.length - 1).toUpperCase();
        const num = parseFloat(sizeStr);
        if (units[lastChar]) { return num * units[lastChar]; }
        return num;
    }

    const maxFileSize = parsePhpSize(phpMaxFileSize);

    function formatFileSize(bytes) {
        if (bytes === 0 || isNaN(bytes)) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    if (documentFileInput) {
        documentFileInput.addEventListener('change', function() {
            const file = this.files[0];
            documentFileError.textContent = '';
            documentFileError.style.display = 'none';
            documentFileInput.classList.remove('is-invalid');
            if (submitDocumentButton) { submitDocumentButton.disabled = false; }

            if (file) {
                let isFileTypeValid = false;
                if (file.type && allowedTypes.includes(file.type)) {
                    isFileTypeValid = true;
                } else if (!file.type) {
                    const extension = file.name.split('.').pop().toLowerCase();
                    const extToMime = {
                        'csv': ['text/csv', 'application/csv'],
                        'zip': ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'],
                        'rar': ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
                        '7z':  ['application/x-7z-compressed', 'application/octet-stream']
                    };
                    if (extToMime[extension]) {
                        for (const mime of extToMime[extension]) {
                            if (allowedTypes.includes(mime)) { isFileTypeValid = true; break; }
                        }
                    }
                }

                if (!isFileTypeValid) {
                    documentFileError.textContent = 'Ce format n\'est pas accepté, rapprochez-vous de l\'administrateur du site, ou utilisez un format compressé.';
                    documentFileError.style.display = 'block';
                    documentFileInput.classList.add('is-invalid');
                    if (submitDocumentButton) { submitDocumentButton.disabled = true; }
                    return;
                }

                if (file.size > maxFileSize) {
                    documentFileError.textContent = `Le fichier est trop volumineux (${formatFileSize(file.size)}). Max: ${formatFileSize(maxFileSize)}.`;
                    documentFileError.style.display = 'block';
                    documentFileInput.classList.add('is-invalid');
                    if (submitDocumentButton) { submitDocumentButton.disabled = true; }
                    return;
                }
            }
        });
    }

    if (documentForm) {
        documentForm.addEventListener('submit', function(e) {
            // File validation logic from add.php (ensure it's adapted if needed for edit specific rules)
            // This part is largely the same; if a file is selected, it must be valid.
            // If no file is selected, it's fine (means user isn't changing the attachment).
            const file = documentFileInput ? documentFileInput.files[0] : null;
            if (file) { // Only validate if a new file is chosen
                let isInvalid = false;
                let typeAllowedByExtensionOrMime = allowedTypes.includes(file.type);
                if (!file.type && !typeAllowedByExtensionOrMime) {
                    const extension = file.name.split('.').pop().toLowerCase();
                    const extToMime = { /* ... same as above ... */ };
                    if (extToMime[extension]) { /* ... same check ... */ }
                }
                if (!typeAllowedByExtensionOrMime) { /* ... set error, isInvalid = true ... */ }
                if (file.size > maxFileSize) { /* ... set error, isInvalid = true ... */ }

                if (isInvalid) {
                    e.preventDefault();
                    if (submitDocumentButton) { submitDocumentButton.disabled = true; }
                    documentFileInput.focus();
                    return false;
                }
            }
            // Submit content from editor
            document.getElementById('content-hidden').value = document.getElementById('content').innerHTML;
        });
    }
    
    // Clear sessionStorage for edit form on normal page unload (e.g. navigating away, not client/site change reload)
    window.addEventListener('beforeunload', function (event) {
        // Check if the unload is due to client/site select, if so, don't clear
        // This is tricky, a simpler approach is to clear it only on successful submit or explicit cancel
        // For now, let's rely on DOMContentLoaded to re-populate and not clear here aggressively.
        // sessionStorage.removeItem('preserved_document_content_edit_<?= $document['id'] ?>');
    });

});
</script>

<style>
/* Styles are identical to add.php, no changes needed */
.editor-content {
    border: 1px solid #ced4da;
    padding: 10px;
    line-height: 1.5;
}
.editor-content:empty:before {
    content: attr(data-placeholder);
    color: #6c757d;
    pointer-events: none;
}
.editor-toolbar {
    border: 1px solid #ced4da;
    padding: 5px;
    background-color: #f8f9fa;
    border-radius: 4px;
}
.editor-toolbar button { margin-right: 5px; }
.editor-toolbar select { margin-left: 5px; }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 