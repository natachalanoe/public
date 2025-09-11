<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';
/**
 * Vue d'ajout de document
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['type'] ?? null;

setPageVariables(
    'Ajouter un document',
    'documentation'
);

// Définir la page courante pour le menu
$currentPage = 'documentation';

// Client ID pour le bouton Retour, prioriser GET, puis s'assurer qu'il est défini
$clientIdForReturn = $_GET['client_id'] ?? null;
$returnUrl = BASE_URL . 'documentation/index'; // Default return URL
if ($clientIdForReturn) {
    $returnUrl = BASE_URL . 'documentation/view/' . $clientIdForReturn;
}

// Récupérer les valeurs du formulaire depuis GET pour les pré-remplir après rechargement
$form_category_id = $_GET['form_category_id'] ?? null;
$form_title = $_GET['form_title'] ?? '';
$form_description = $_GET['form_description'] ?? '';
// $form_content est géré via sessionStorage par JavaScript
$form_visible_by_client_val = $_GET['form_visible_by_client'] ?? '1'; // Par défaut à '1' (coché)

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
                <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Ajouter un document</h4></div>

                <div class="ms-auto p-2 bd-highlight">
                    <a href="<?= $returnUrl ?>" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left me-1"></i> Retour
                    </a>
                    <button type="submit" form="addDocumentForm" class="btn btn-primary" id="submitAddDocument">
                        Enregistrer
                    </button>
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

            <form id="addDocumentForm" action="<?= BASE_URL ?>documentation/create" method="post" enctype="multipart/form-data">
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
                                    <label for="document_file" class="form-label">Pièce jointe</label>
                                    <input type="file" class="form-control" id="document_file" name="document_file" accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>">
                                    <div class="form-text">
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
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')">
                                                    <i class="fas fa-bold"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')">
                                                    <i class="fas fa-italic"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')">
                                                    <i class="fas fa-underline"></i>
                                                </button>
                                                <select class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="formatHeading(this.value)">
                                                    <option value="">Style de titre</option>
                                                    <option value="h1">Titre 1</option>
                                                    <option value="h2">Titre 2</option>
                                                    <option value="h3">Titre 3</option>
                                                    <option value="p">Paragraphe</option>
                                                </select>
                                            </div>
                                    <div id="content" class="form-control editor-content" contenteditable="true" style="min-height: 200px; overflow-y: auto; resize: vertical;" data-placeholder="Saisissez votre contenu ici..."></div>
                                            <input type="hidden" name="content" id="content-hidden">
                                </div>
                                        </div>

                            <!-- Colonne 3: Client, Site, Salle, Visible par client -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select" id="client_id" name="client_id" required>
                                        <option value="">Sélectionner un client</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?= $client['id'] ?>" <?= (isset($_GET['client_id']) && $_GET['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($client['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                        </div>
                                <div class="mb-3">
                                    <label for="site_id" class="form-label">Site</label>
                                    <select class="form-select" id="site_id" name="site_id">
                                        <option value="">Sélectionner un site (optionnel)</option>
                                        <?php foreach ($sites as $site): /* Предполагается, что $sites загружаются динамически или передаются контроллером */ ?>
                                            <option value="<?= $site['id'] ?>" <?= (isset($_GET['site_id']) && $_GET['site_id'] == $site['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($site['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    </div>
                                <div class="mb-3">
                                    <label for="room_id" class="form-label">Salle</label>
                                    <select class="form-select" id="room_id" name="room_id">
                                        <option value="">Sélectionner une salle (optionnel)</option>
                                        <?php foreach ($rooms as $room): /* Предполагается, что $rooms загружаются динамически */ ?>
                                            <option value="<?= $room['id'] ?>" <?= (isset($_GET['room_id']) && $_GET['room_id'] == $room['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($room['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="visible_by_client" name="visible_by_client" <?= ($form_visible_by_client_val === '1') ? 'checked' : '' ?>>
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
// Fonction pour formater le texte
function formatText(command) {
    document.execCommand(command, false, null);
    document.getElementById('content').focus();
}

// Fonction pour formater les titres
function formatHeading(tag) {
    if (tag === 'p') {
        document.execCommand('formatBlock', false, 'p');
    } else if (tag) {
        document.execCommand('formatBlock', false, tag);
    }
    document.getElementById('content').focus();
}

// Gestionnaire pour le formulaire
document.getElementById('addDocumentForm').addEventListener('submit', function(e) {
    // Copier le contenu HTML dans le champ caché avant la soumission
    document.getElementById('content-hidden').value = document.getElementById('content').innerHTML;
});

// Style pour le placeholder (simplifié, le CSS gère déjà :empty:before)
document.getElementById('content').addEventListener('focus', function() {
    // Logique placeholder gérée par CSS :empty:before, on peut simplifier ici
});

document.getElementById('content').addEventListener('blur', function() {
    // Logique placeholder gérée par CSS :empty:before, on peut simplifier ici
});

// Le JS pour recharger la page sur changement de client/site est conservé
document.getElementById('client_id').addEventListener('change', function() {
    const clientId = this.value;
    const currentUrl = new URL(window.location.href);

    // Sauvegarder les données du formulaire
    const formDataToPreserve = {
        category_id: document.getElementById('category_id').value,
        title: document.getElementById('title').value,
        description: document.getElementById('description').value,
        visible_by_client: document.getElementById('visible_by_client').checked ? '1' : '0'
    };
    for (const key in formDataToPreserve) {
        if (formDataToPreserve[key]) { // Ne pas ajouter de paramètres vides
            currentUrl.searchParams.set(`form_${key}`, formDataToPreserve[key]);
        }
    }
    // Sauvegarder le contenu de l'éditeur dans sessionStorage
    sessionStorage.setItem('preserved_document_content', document.getElementById('content').innerHTML);

    currentUrl.searchParams.set('client_id', clientId);
    currentUrl.searchParams.delete('site_id'); // Reset site and room if client changes
    currentUrl.searchParams.delete('room_id');
    if (clientId) {
        window.location.href = currentUrl.toString();
    } else { // Si "Sélectionner un client" est choisi, recharger sans client_id
        currentUrl.searchParams.delete('client_id');
        // Conserver les autres form_ params
        window.location.href = currentUrl.toString();
    }
});

document.getElementById('site_id').addEventListener('change', function() {
    const clientId = document.getElementById('client_id').value;
    const siteId = this.value;
    const currentUrl = new URL(window.location.href);

    // Sauvegarder les données du formulaire
    const formDataToPreserve = {
        category_id: document.getElementById('category_id').value,
        title: document.getElementById('title').value,
        description: document.getElementById('description').value,
        visible_by_client: document.getElementById('visible_by_client').checked ? '1' : '0'
    };
    for (const key in formDataToPreserve) {
        if (formDataToPreserve[key]) { // Ne pas ajouter de paramètres vides
            currentUrl.searchParams.set(`form_${key}`, formDataToPreserve[key]);
        }
    }
    // Sauvegarder le contenu de l'éditeur dans sessionStorage
    sessionStorage.setItem('preserved_document_content', document.getElementById('content').innerHTML);

    currentUrl.searchParams.set('client_id', clientId); // Assurer que client_id est là
    currentUrl.searchParams.delete('room_id'); // Reset room if site changes
    if (siteId) {
        currentUrl.searchParams.set('site_id', siteId);
    } else {
        currentUrl.searchParams.delete('site_id');
    }
    if (clientId) { // On ne recharge que si un client est sélectionné
      window.location.href = currentUrl.toString();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Restaurer le contenu de l'éditeur depuis sessionStorage
    const preservedContent = sessionStorage.getItem('preserved_document_content');
    if (preservedContent) {
        document.getElementById('content').innerHTML = preservedContent;
        sessionStorage.removeItem('preserved_document_content'); // Nettoyer après usage
    }

    const documentForm = document.getElementById('addDocumentForm');
    const documentFileInput = document.getElementById('document_file');
    const documentFileError = document.getElementById('documentFileError');
    const submitDocumentButton = document.getElementById('submitAddDocument');

    const allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream', // octet-stream for broader zip/rar compatibility
        'application/x-rar-compressed', 'application/vnd.rar', // Common RAR MIME types
        'application/x-7z-compressed', // Common 7z MIME type
        'text/plain', 'text/csv', 'application/csv'
    ];

    const phpMaxFileSize = '<?php echo ini_get("upload_max_filesize"); ?>';

    function parsePhpSize(sizeStr) {
        if (!sizeStr) return 0;
        const units = { 'K': 1024, 'M': 1024 * 1024, 'G': 1024 * 1024 * 1024 };
        const lastChar = sizeStr.charAt(sizeStr.length - 1).toUpperCase();
        const num = parseFloat(sizeStr);
        if (units[lastChar]) {
            return num * units[lastChar];
        }
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
            // Réinitialiser les messages d'erreur et état
            documentFileError.textContent = '';
            documentFileError.style.display = 'none';
            documentFileInput.classList.remove('is-invalid');
            if (submitDocumentButton) {
                 submitDocumentButton.disabled = false;
            }

            if (file) {
                // Réinitialiser les messages d'erreur et état (déjà fait plus haut, mais s'assurer ici aussi)
                documentFileError.textContent = '';
                documentFileError.style.display = 'none';
                documentFileInput.classList.remove('is-invalid');
                if (submitDocumentButton) {
                    submitDocumentButton.disabled = false;
                }

                // --- Revised type validation for 'change' event ---
                let isFileTypeValid = false;
                if (file.type && allowedTypes.includes(file.type)) {
                    isFileTypeValid = true;
                } else if (!file.type) { // file.type is empty, try extension
                    const extension = file.name.split('.').pop().toLowerCase();
                    const extToMime = {
                        'csv': ['text/csv', 'application/csv'],
                        'zip': ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'],
                        'rar': ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
                        '7z': ['application/x-7z-compressed', 'application/octet-stream']
                        // Note: .doc, .docx, .xls, .xlsx often have reliable MIME types, so specific ext mapping might not be as critical here
                        // as for archives or .csv
                    };
                    if (extToMime[extension]) {
                        for (const mime of extToMime[extension]) {
                            if (allowedTypes.includes(mime)) {
                                isFileTypeValid = true;
                                break;
                            }
                        }
                    }
                }

                if (!isFileTypeValid) {
                    documentFileError.textContent = 'Ce format n\'est pas accepté, rapprochez-vous de l\'administrateur du site, ou utilisez un format compressé.';
                    documentFileError.style.display = 'block';
                    documentFileInput.classList.add('is-invalid');
                    if (submitDocumentButton) {
                        submitDocumentButton.disabled = true;
                    }
                    return; // Stop processing if type is invalid
                }
                // --- End of revised type validation ---

                // Vérifier la taille du fichier
                if (file.size > maxFileSize) {
                    documentFileError.textContent = `Le fichier est trop volumineux (${formatFileSize(file.size)}). Taille maximale autorisée : ${formatFileSize(maxFileSize)} (${phpMaxFileSize}).`;
                    documentFileError.style.display = 'block';
                    documentFileInput.classList.add('is-invalid');
                    if (submitDocumentButton) {
                        submitDocumentButton.disabled = true;
                    }
                    return;
                }
            }
        });
    }

    if (documentForm) {
        documentForm.addEventListener('submit', function(e) {
            const file = documentFileInput ? documentFileInput.files[0] : null;
            if (file) {
                let isInvalid = false;
                // Re-valider le type
                const extension = file.name.split('.').pop().toLowerCase();
                let typeAllowedByExtensionOrMime = allowedTypes.includes(file.type);

                if (!file.type && !typeAllowedByExtensionOrMime) { // If MIME type is empty, check by extension
                     const extToMime = {
                        'csv': ['text/csv', 'application/csv'],
                        'zip': ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'],
                        'rar': ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
                        '7z': ['application/x-7z-compressed', 'application/octet-stream']
                    };
                    if (extToMime[extension]) {
                        for (const mime of extToMime[extension]) {
                            if (allowedTypes.includes(mime)) {
                                typeAllowedByExtensionOrMime = true;
                                break;
                            }
                        }
                    }
                }


                if (!typeAllowedByExtensionOrMime) {
                    documentFileError.textContent = 'Type de fichier non autorisé. Veuillez sélectionner un fichier valide.';
                    documentFileError.style.display = 'block';
                    documentFileInput.classList.add('is-invalid');
                    isInvalid = true;
                }

                // Re-valider la taille
                if (file.size > maxFileSize) {
                    documentFileError.textContent = `Le fichier est trop volumineux (${formatFileSize(file.size)}). Taille maximale autorisée : ${formatFileSize(maxFileSize)} (${phpMaxFileSize}).`;
                    documentFileError.style.display = 'block'; // Assurez-vous que le message est visible
                    documentFileInput.classList.add('is-invalid');
                    isInvalid = true;
                }

                if (isInvalid) {
                    e.preventDefault(); // Empêcher la soumission du formulaire
                    if (submitDocumentButton) {
                       submitDocumentButton.disabled = true;
                    }
                    // Optionally, scroll to the error or focus the input
                    documentFileInput.focus();
                    return false;
                }
            }
            // S'il n'y a pas de fichier, ou si le fichier est valide, la soumission continue.
            // Le champ de fichier n'est pas "required", donc pas de fichier est un cas valide.
        });
    }
});
</script>

<style>
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

.editor-toolbar button {
    margin-right: 5px;
}

.editor-toolbar select {
    margin-left: 5px;
}
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 