<?php
require_once __DIR__ . '/../models/DocumentationModel.php';
require_once __DIR__ . '/../models/DocumentationCategoryModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';

class DocumentationController {
    private $db;
    private $documentationModel;
    private $categoryModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->documentationModel = new DocumentationModel($this->db);
        $this->categoryModel = new DocumentationCategoryModel($this->db);
        $this->clientModel = new ClientModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    private function checkAccess() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }
    }

    /**
     * Affiche la liste des documents avec filtres
     */
    public function index() {
        $this->checkAccess();
        
        // Récupération des filtres
        $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
        $site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
        $room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;

        // Récupération des données pour les filtres
        $clients = $this->clientModel->getAllClients();
        $sites = $client_id ? $this->siteModel->getSitesByClientId($client_id) : [];
        $rooms = $site_id ? $this->roomModel->getRoomsBySite($site_id) : [];

        // Requête pour compter les documents par niveau
        $query = "
            SELECT 
                c.id as client_id,
                c.name as client_name,
                c.status as client_status,
                NULL as site_id,
                NULL as site_name,
                NULL as room_id,
                NULL as room_name,
                COUNT(d.id) as doc_count
            FROM documentation d
            JOIN clients c ON d.client_id = c.id
        ";

        $params = [];
        if ($client_id) {
            $query .= " WHERE c.id = ?";
            $params[] = $client_id;
        }

        $query .= " GROUP BY c.id, c.name, c.status ORDER BY c.name";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organisation des résultats
        $client_docs = [];
        foreach ($results as $row) {
            if ($row['doc_count'] > 0) {
                $client_docs[] = $row;
            }
        }

        // Passage des données à la vue
        require_once __DIR__ . '/../views/documentation/index.php';
    }

    /**
     * Affiche les documents d'un utilisateur
     */
    public function userDocuments($userId) {
        $this->checkAccess();
        
        $documents = $this->documentationModel->getUserDocuments($userId);
        require_once __DIR__ . '/../views/documentation/user_documents.php';
    }

    /**
     * Affiche le formulaire d'ajout de document
     */
    public function add() {
        $this->checkAccess();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'content' => $_POST['content'] ?? null,
                'attachment_path' => '',
                'client_id' => $_POST['client_id'] ?? null,
                'site_id' => $_POST['site_id'] ?? null,
                'room_id' => $_POST['room_id'] ?? null,
                'category_id' => $_POST['category_id'] ?? null
            ];

            // Gestion de l'upload du fichier
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/documents/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Créer le répertoire du client s'il n'existe pas
                $clientDir = $uploadDir . $data['client_id'] . '/';
                if (!file_exists($clientDir)) {
                    mkdir($clientDir, 0777, true);
                }

                // Préparer le nom du fichier
                $originalName = $_FILES['document']['name'];
                $safeName = str_replace(' ', '_', $originalName);
                $extension = pathinfo($safeName, PATHINFO_EXTENSION);
                $baseName = pathinfo($safeName, PATHINFO_FILENAME);
                
                // Vérifier si le fichier existe déjà et ajouter un numéro incrémental si nécessaire
                $counter = 1;
                $finalName = $safeName;
                while (file_exists($clientDir . $finalName)) {
                    $finalName = $baseName . '_' . $counter . '.' . $extension;
                    $counter++;
                }

                $targetPath = $clientDir . $finalName;

                if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
                    $data['attachment_path'] = 'uploads/documents/' . $data['client_id'] . '/' . $finalName;
                } else {
                    $_SESSION['error'] = "Erreur lors de l'upload du fichier.";
                    header('Location: ' . BASE_URL . 'documentation/add');
                    exit;
                }
            }

            if ($this->documentationModel->addDocument($data)) {
                $_SESSION['success'] = "Document ajouté avec succès.";
                header('Location: ' . BASE_URL . 'documentation/view/' . $data['client_id']);
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du document.";
                header('Location: ' . BASE_URL . 'documentation/add');
                exit;
            }
        }
        
        $clients = $this->clientModel->getAllClients();
        $sites = [];
        $rooms = [];
        $categories = $this->categoryModel->getAllCategories();
        
        if (isset($_GET['client_id'])) {
            $sites = $this->siteModel->getSitesByClientId($_GET['client_id']);
        }
        
        if (isset($_GET['site_id'])) {
            $rooms = $this->roomModel->getRoomsBySiteId($_GET['site_id']);
        }
        
        require_once __DIR__ . '/../views/documentation/add.php';
    }

    /**
     * Traite l'ajout d'un nouveau document
     */
    public function create() {
        $this->checkAccess();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'content' => $_POST['content'] ?? null,
                'attachment_path' => null,
                'client_id' => isset($_POST['client_id']) ? (int)$_POST['client_id'] : null,
                'site_id' => isset($_POST['site_id']) && !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null,
                'room_id' => isset($_POST['room_id']) && !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null,
                'category_id' => isset($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'visible_by_client' => isset($_POST['visible_by_client']) ? 1 : 0,
                'user_id' => $_SESSION['user']['id']
            ];

            if (empty($data['title']) || empty($data['client_id']) || empty($data['category_id'])) {
                $_SESSION['error'] = "Les champs Titre, Client et Catégorie sont obligatoires.";
                $redirectParams = http_build_query([
                    'client_id' => $data['client_id'],
                    'site_id' => $data['site_id'],
                    'room_id' => $data['room_id'],
                    'form_category_id' => $data['category_id'],
                    'form_title' => $data['title'],
                    'form_description' => $data['description'],
                    'form_visible_by_client' => $data['visible_by_client']
                ]);
                header('Location: ' . BASE_URL . 'documentation/add?' . $redirectParams);
                exit;
            }

            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                // --- Adopt InterventionController's style for path and validation ---
                $file = $_FILES['document_file'];
                $originalFileName = $file['name'];
                $fileTmpPath = $file['tmp_name'];
                $fileSize = $file['size'];
                // $fileError = $file['error']; // Already checked UPLOAD_ERR_OK

                // 1. Server-side File Size Check (e.g., 10MB limit like interventions)
                //    Get this from php.ini ideally, or define a constant
                $phpMaxUpload = $this->parsePhpIniSize(ini_get('upload_max_filesize'));
                $phpPostMax = $this->parsePhpIniSize(ini_get('post_max_size'));
                $serverMaxFileSize = min($phpMaxUpload, $phpPostMax); // Effective limit

                // You might want a specific application limit, e.g. 10MB from intervention example
                $applicationMaxFileSize = 10 * 1024 * 1024; // 10MB
                $maxFileSize = min($serverMaxFileSize, $applicationMaxFileSize);


                if ($fileSize > $maxFileSize) {
                    $_SESSION['error'] = "Le fichier est trop volumineux. Taille maximale autorisée: " . $this->formatBytes($maxFileSize) . ".";
                    // Redirect logic from before...
                    $this->redirectBackToFormWithError($data); // Helper function might be good
                    exit;
                }

                // 2. Server-side File Type Check
                require_once INCLUDES_PATH . '/FileUploadValidator.php';
                
                $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                
                if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                    $_SESSION['error'] = "Ce format n'est pas accepté, rapprochez-vous de l'administrateur du site, ou utilisez un format compressé.";
                    $this->redirectBackToFormWithError($data);
                    exit;
                }

                // Path construction (assuming 'uploads' is sibling of 'controllers', 'models')
                // If 'uploads' is at project root, it should be /../../
                $baseUploadDir = __DIR__ . '/../uploads/documents/'; // Adjusted to /../ like interventions
                $clientSpecificDir = $baseUploadDir . $data['client_id'] . '/';

                if (!is_dir($clientSpecificDir)) {
                    if (!mkdir($clientSpecificDir, 0775, true)) {
                        error_log("[ERROR] Documentation Upload: Failed to create client directory: " . $clientSpecificDir);
                        $_SESSION['error'] = "Erreur technique: Impossible de créer le répertoire de destination.";
                        $this->redirectBackToFormWithError($data);
                        exit;
                    }
                    error_log("[DEBUG] Documentation Upload: Created client directory: " . $clientSpecificDir);
                } else {
                     error_log("[DEBUG] Documentation Upload: Client directory exists: " . $clientSpecificDir);
                }
                
                if (!is_writable($clientSpecificDir)){
                     error_log("[ERROR] Documentation Upload: Client directory IS NOT WRITABLE: " . $clientSpecificDir);
                     $_SESSION['error'] = "Erreur technique: Le répertoire de destination n'est pas accessible en écriture.";
                     $this->redirectBackToFormWithError($data);
                     exit;
                } else {
                    error_log("[DEBUG] Documentation Upload: Client directory IS WRITABLE: " . $clientSpecificDir);
                }


                // Filename sanitization and uniqueness - from InterventionController
                $fileExt = pathinfo($originalFileName, PATHINFO_EXTENSION); // Keep original extension
                $fileNameOnly = pathinfo($originalFileName, PATHINFO_FILENAME);
                $fileNameOnly = str_replace(' ', '_', $fileNameOnly);
                $fileNameOnly = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileNameOnly);
                
                $finalSanitizedName = $fileNameOnly;
                $counter = 1;
                while (file_exists($clientSpecificDir . $finalSanitizedName . '.' . $fileExt)) {
                    $finalSanitizedName = $fileNameOnly . '_' . $counter;
                    $counter++;
                }
                $finalFileNameWithExt = $finalSanitizedName . '.' . $fileExt;
                $targetPath = $clientSpecificDir . $finalFileNameWithExt;

                error_log("[DEBUG] Documentation Upload: Attempting move_uploaded_file. Source: '" . $fileTmpPath . "', Target: '" . $targetPath . "'");

                if (move_uploaded_file($fileTmpPath, $targetPath)) {
                    $data['attachment_path'] = 'uploads/documents/' . $data['client_id'] . '/' . $finalFileNameWithExt; // Relative path for DB
                    error_log("[DEBUG] Documentation Upload: Move successful for '" . $targetPath . "'. DB Path: " . $data['attachment_path']);
                    if (!file_exists($targetPath)) {
                         error_log("[WARN] Documentation Upload: File '" . $targetPath . "' NOT FOUND IMMEDIATELY AFTER successful move_uploaded_file! AV or other interference highly suspected.");
                    }
                } else {
                    $php_upload_error = $_FILES['document_file']['error']; // Error might be from original check if not UPLOAD_ERR_OK
                    $move_error_details = error_get_last();
                    error_log("[ERROR] Documentation Upload: move_uploaded_file FAILED for '" . $targetPath . "'. PHP Upload Error Code (from _FILES): " . $php_upload_error . ". System error during move: " . ($move_error_details['message'] ?? 'N/A'));
                    $_SESSION['error'] = "Erreur lors de l'upload du fichier (interne)."; // Simplified message like interventions
                    $this->redirectBackToFormWithError($data);
                    exit;
                }
                 // --- End of InterventionController style adoption ---
            } elseif (isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                // This block handles other _FILES errors, e.g. UPLOAD_ERR_INI_SIZE if file too big for PHP's global settings
                $_SESSION['error'] = "Erreur lors de l'upload du fichier: Code " . $_FILES['document_file']['error'] . ". Vérifiez la taille du fichier.";
                $this->redirectBackToFormWithError($data); // Use helper
                exit;
            }
            // No specific 'else' needed for UPLOAD_ERR_NO_FILE, attachment_path remains null.

            if (empty($data['site_id'])) $data['site_id'] = null;
            if (empty($data['room_id'])) $data['room_id'] = null;

            if ($this->documentationModel->addDocument($data)) {
                $_SESSION['success'] = "Document ajouté avec succès.";
                header('Location: ' . BASE_URL . 'documentation/view/' . $data['client_id']);
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du document à la base de données.";
                // If DB insert fails after a successful file move, unlink the orphaned file
                if (!empty($data['attachment_path']) && file_exists($targetPath)) { // Check if file was moved and path exists
                    error_log("[CLEANUP] Documentation Upload: DB insert failed. Unlinking orphaned file: " . $targetPath);
                    unlink($targetPath);
                }
                $redirectParams = http_build_query([
                    'client_id' => $data['client_id'],
                    'site_id' => $data['site_id'],
                    'room_id' => $data['room_id'],
                    'form_category_id' => $data['category_id'],
                    'form_title' => $data['title'],
                    'form_description' => $data['description'],
                    'form_visible_by_client' => $data['visible_by_client']
                ]);
                header('Location: ' . BASE_URL . 'documentation/add?' . $redirectParams);
                exit;
            }
        }
        $_SESSION['error'] = "Requête invalide.";
        header('Location: ' . BASE_URL . 'documentation/add');
        exit;
    }

    /**
     * Affiche le formulaire de modification d'un document existant.
     */
    public function edit($id) {
        $this->checkAccess();

        // Log pour debug
        error_log("[DEBUG] DocumentationController::edit - Attempting to edit document ID: " . $id);

        $document = $this->documentationModel->getDocumentById((int)$id);

        if (!$document) {
            error_log("[ERROR] DocumentationController::edit - Document not found for ID: " . $id);
            $_SESSION['error'] = "Document non trouvé.";
            header('Location: ' . BASE_URL . 'documentation/view/' . ($_GET['client_id'] ?? ''));
            exit;
        }

        // Log pour debug
        error_log("[DEBUG] DocumentationController::edit - Document found: " . json_encode($document));

        // Récupérer les données nécessaires pour les listes déroulantes
        $clients = $this->clientModel->getAllClients();
        $categories = $this->categoryModel->getAllCategories();
        $sites = [];
        $rooms = [];

        // Si un client est défini pour le document, charger ses sites
        if (!empty($document['client_id'])) {
            $sites = $this->siteModel->getSitesByClientId($document['client_id']);
        }

        // Si un site est défini pour le document, charger ses salles
        if (!empty($document['site_id'])) {
            $rooms = $this->roomModel->getRoomsBySiteId($document['site_id']);
        }
        
        // Récupérer les valeurs du formulaire depuis GET si elles existent (pour la persistance après rechargement client/site)
        $form_category_id = $_GET['form_category_id'] ?? $document['category_id'];
        $form_title = $_GET['form_title'] ?? $document['title'];
        $form_description = $_GET['form_description'] ?? $document['description'];
        $form_visible_by_client_val = $_GET['form_visible_by_client'] ?? $document['visible_by_client'];
        // $form_content est géré via sessionStorage par JavaScript, et sera initialisé avec $document['content'] dans la vue.

        // Le chemin du fichier actuel pour l'affichage (non modifiable directement ici, géré par la logique d'update)
        $current_attachment_path = $document['attachment_path'];

        require_once __DIR__ . '/../views/documentation/edit.php';
    }

    // Helper function to parse PHP ini sizes like '16M' into bytes
    private function parsePhpIniSize($sizeStr) {
        if (empty($sizeStr)) return 0;
        $sizeStr = trim($sizeStr);
        $last = strtolower($sizeStr[strlen($sizeStr)-1]);
        $val = intval($sizeStr);
        switch($last) {
            case 'g': $val *= 1024; // Fall-through
            case 'm': $val *= 1024; // Fall-through
            case 'k': $val *= 1024;
        }
        return $val;
    }

    // Helper function to format bytes into readable string
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // Helper function to redirect back to add form with error and preserved data
    private function redirectBackToFormWithError($data_from_controller) {
        $redirectParams = http_build_query([
            'client_id' => $data_from_controller['client_id'] ?? null,
            'site_id' => $data_from_controller['site_id'] ?? null,
            'room_id' => $data_from_controller['room_id'] ?? null,
            'form_category_id' => $data_from_controller['category_id'] ?? null,
            'form_title' => $data_from_controller['title'] ?? '',
            'form_description' => $data_from_controller['description'] ?? '',
            'form_visible_by_client' => $data_from_controller['visible_by_client'] ?? '1'
            // Content is handled by sessionStorage on client-side
        ]);
        header('Location: ' . BASE_URL . 'documentation/add?' . $redirectParams);
        // Make sure to exit after header redirect
    }

    /**
     * Traite la mise à jour d'un document existant.
     */
    public function update($id) {
        $this->checkAccess();
        $documentId = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = "Requête invalide.";
            header('Location: ' . BASE_URL . 'documentation/edit/' . $documentId);
            exit;
        }

        $existingDocument = $this->documentationModel->getDocumentById($documentId);
        if (!$existingDocument) {
            $_SESSION['error'] = "Document non trouvé pour la mise à jour.";
            header('Location: ' . BASE_URL . 'documentation');
            exit;
        }

        $data = [
            'id' => $documentId,
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'content' => $_POST['content'] ?? null,
            'client_id' => isset($_POST['client_id']) ? (int)$_POST['client_id'] : null,
            'site_id' => isset($_POST['site_id']) && !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null,
            'room_id' => isset($_POST['room_id']) && !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null,
            'category_id' => isset($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'visible_by_client' => isset($_POST['visible_by_client']) ? 1 : 0,
            'user_id' => $_SESSION['user']['id'] // For updated_by or similar if model supports it
        ];

        // Validate required fields
        if (empty($data['title']) || empty($data['client_id']) || empty($data['category_id'])) {
            $_SESSION['error'] = "Les champs Titre, Client et Catégorie sont obligatoires.";
            $this->redirectBackToEditFormWithError($documentId, $data);
            exit;
        }
        
        $currentAttachmentPathOnServer = null;
        if (!empty($existingDocument['attachment_path'])) {
             // Convert relative DB path to absolute server path for file operations
            $currentAttachmentPathOnServer = __DIR__ . '/../' . $existingDocument['attachment_path'];
        }
        $data['attachment_path'] = $existingDocument['attachment_path']; // Assume keeping old attachment initially

        $fileUploadedSuccessfully = false;
        $newlyUploadedFilePath = null;


        // 1. Handle explicit removal of attachment
        if (isset($_POST['remove_attachment']) && $_POST['remove_attachment'] == '1') {
            if ($currentAttachmentPathOnServer && file_exists($currentAttachmentPathOnServer)) {
                if (unlink($currentAttachmentPathOnServer)) {
                    error_log("[INFO] Documentation Update: Attachment " . $currentAttachmentPathOnServer . " removed by user request.");
                    $data['attachment_path'] = null;
                } else {
                    error_log("[ERROR] Documentation Update: Failed to remove attachment " . $currentAttachmentPathOnServer . " by user request.");
                    $_SESSION['error'] = "Erreur lors de la suppression de l'ancienne pièce jointe.";
                    // Potentially non-fatal, allow update of other fields to proceed or redirect
                }
            } else {
                 error_log("[INFO] Documentation Update: User requested removal, but no attachment found or path invalid: " . $currentAttachmentPathOnServer);
            }
             $currentAttachmentPathOnServer = null; // Mark as removed for subsequent logic
        }

        // 2. Handle new file upload (if any, and if not removed explicitly)
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document_file'];
            // Perform validation (size, type) - reusing logic from create()
            $phpMaxUpload = $this->parsePhpIniSize(ini_get('upload_max_filesize'));
            $phpPostMax = $this->parsePhpIniSize(ini_get('post_max_size'));
            $serverMaxFileSize = min($phpMaxUpload, $phpPostMax);
            $applicationMaxFileSize = 10 * 1024 * 1024; // 10MB
            $maxFileSize = min($serverMaxFileSize, $applicationMaxFileSize);

            if ($file['size'] > $maxFileSize) {
                $_SESSION['error'] = "Le nouveau fichier est trop volumineux. Taille maximale autorisée: " . $this->formatBytes($maxFileSize) . ".";
                $this->redirectBackToEditFormWithError($documentId, $data);
                exit;
            }

            require_once INCLUDES_PATH . '/FileUploadValidator.php';
            
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                $_SESSION['error'] = "Ce format n'est pas accepté, rapprochez-vous de l'administrateur du site, ou utilisez un format compressé.";
                $this->redirectBackToEditFormWithError($documentId, $data);
                exit;
            }

            // If a new file is valid, delete the old one (if it existed and wasn't already removed)
            if ($currentAttachmentPathOnServer && file_exists($currentAttachmentPathOnServer)) {
                if (unlink($currentAttachmentPathOnServer)) {
                    error_log("[INFO] Documentation Update: Old attachment " . $currentAttachmentPathOnServer . " removed to be replaced by new upload.");
                } else {
                    error_log("[ERROR] Documentation Update: Failed to remove old attachment " . $currentAttachmentPathOnServer . " before new upload.");
                    // Decide if this is fatal. For now, proceed with new upload.
                }
            }

            // Proceed with upload logic (similar to create())
            $baseUploadDir = __DIR__ . '/../uploads/documents/';
            $clientSpecificDir = $baseUploadDir . $data['client_id'] . '/';
            if (!is_dir($clientSpecificDir)) {
                if (!mkdir($clientSpecificDir, 0775, true)) {
                    $_SESSION['error'] = "Erreur technique: Impossible de créer le répertoire de destination pour la nouvelle pièce jointe.";
                    $this->redirectBackToEditFormWithError($documentId, $data);
                    exit;
                }
            }

            $originalFileName = $file['name'];
            $fileExt = pathinfo($originalFileName, PATHINFO_EXTENSION);
            $fileNameOnly = pathinfo($originalFileName, PATHINFO_FILENAME);
            $fileNameOnly = str_replace(' ', '_', $fileNameOnly);
            $fileNameOnly = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileNameOnly);
            
            $finalSanitizedName = $fileNameOnly;
            $counter = 1;
            while (file_exists($clientSpecificDir . $finalSanitizedName . '.' . $fileExt)) {
                $finalSanitizedName = $fileNameOnly . '_' . $counter;
                $counter++;
            }
            $finalFileNameWithExt = $finalSanitizedName . '.' . $fileExt;
            $newlyUploadedFilePath = $clientSpecificDir . $finalFileNameWithExt; // Absolute path

            if (move_uploaded_file($file['tmp_name'], $newlyUploadedFilePath)) {
                $data['attachment_path'] = 'uploads/documents/' . $data['client_id'] . '/' . $finalFileNameWithExt; // Relative path for DB
                $fileUploadedSuccessfully = true;
                error_log("[INFO] Documentation Update: New file uploaded successfully: " . $newlyUploadedFilePath);
            } else {
                $_SESSION['error'] = "Erreur lors de l'upload du nouveau fichier.";
                $this->redirectBackToEditFormWithError($documentId, $data);
                exit;
            }
        } elseif (isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle other _FILES errors if a file was attempted but failed for other reasons
            $_SESSION['error'] = "Erreur lors de l'upload du nouveau fichier: Code " . $_FILES['document_file']['error'];
            $this->redirectBackToEditFormWithError($documentId, $data);
            exit;
        }
        // If no new file and not removed, $data['attachment_path'] retains $existingDocument['attachment_path']

        // Ensure site_id and room_id are null if empty (consistency with create)
        if (empty($data['site_id'])) $data['site_id'] = null;
        if (empty($data['room_id'])) $data['room_id'] = null;

        // Log pour debug avant la mise à jour
        error_log("[DEBUG] DocumentationController::update - About to update document ID: " . $documentId);
        error_log("[DEBUG] DocumentationController::update - Final data: " . json_encode($data));

        try {
            if ($this->documentationModel->updateDocument($documentId, $data)) {
                $_SESSION['success'] = "Document mis à jour avec succès.";
                // Redirect to the view page of the client, or index if client_id is somehow missing
                $redirectClientId = $data['client_id'] ?? $existingDocument['client_id'];
                if ($redirectClientId) {
                     header('Location: ' . BASE_URL . 'documentation/view/' . $redirectClientId);
                } else {
                     header('Location: ' . BASE_URL . 'documentation');
                }
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la mise à jour du document dans la base de données.";
                // If DB update failed AND a new file was successfully uploaded, attempt to delete the orphaned new file
                if ($fileUploadedSuccessfully && $newlyUploadedFilePath && file_exists($newlyUploadedFilePath)) {
                    error_log("[CLEANUP] Documentation Update: DB update failed. Unlinking orphaned newly uploaded file: " . $newlyUploadedFilePath);
                    unlink($newlyUploadedFilePath);
                }
                $this->redirectBackToEditFormWithError($documentId, $data);
                exit;
            }
        } catch (Exception $e) {
            error_log("[ERROR] DocumentationController::update - Exception caught: " . $e->getMessage());
            error_log("[ERROR] DocumentationController::update - Exception trace: " . $e->getTraceAsString());
            $_SESSION['error'] = "Erreur lors de la mise à jour du document : " . $e->getMessage();
            $this->redirectBackToEditFormWithError($documentId, $data);
            exit;
        }
    }

    // Helper function to redirect back to edit form with error and preserved data
    private function redirectBackToEditFormWithError($documentId, $data_from_controller) {
        $redirectParams = http_build_query([
            // Use 'client_id' etc. from $data_from_controller to preserve what user *attempted* to set
            'client_id' => $data_from_controller['client_id'] ?? null, 
            'site_id' => $data_from_controller['site_id'] ?? null,
            'room_id' => $data_from_controller['room_id'] ?? null,
            'form_category_id' => $data_from_controller['category_id'] ?? null,
            'form_title' => $data_from_controller['title'] ?? '',
            'form_description' => $data_from_controller['description'] ?? '',
            'form_visible_by_client' => $data_from_controller['visible_by_client'] ?? '0' 
            // Content is handled by sessionStorage on client-side
        ]);
        // The base URL should be the edit page for this specific document
        header('Location: ' . BASE_URL . 'documentation/edit/' . $documentId . '?' . $redirectParams);
        exit; // Always exit after a header redirect
    }

    /**
     * Supprime un document
     */
    public function delete($id) {
        $this->checkAccess();
        
        // Récupérer le document avant de le supprimer pour avoir son chemin de fichier
        $document = $this->documentationModel->getDocumentById($id);
        
        if (!$document) {
            $_SESSION['error'] = "Document non trouvé.";
            header('Location: ' . BASE_URL . 'documentation');
            exit;
        }

        // Supprimer le fichier physique s'il existe
        if (!empty($document['attachment_path'])) {
            $filePath = __DIR__ . '/../' . $document['attachment_path'];
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    error_log("[ERROR] Documentation Delete: Failed to delete file: " . $filePath);
                    $_SESSION['error'] = "Erreur lors de la suppression du fichier physique.";
                    header('Location: ' . BASE_URL . 'documentation/view/' . $document['client_id']);
                    exit;
                }
                error_log("[INFO] Documentation Delete: File deleted successfully: " . $filePath);
            }
        }

        // Supprimer l'entrée dans la base de données
        if ($this->documentationModel->deleteDocument($id)) {
            $_SESSION['success'] = "Document supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du document dans la base de données.";
        }
        
        // Rediriger vers la vue du client
        header('Location: ' . BASE_URL . 'documentation/view/' . $document['client_id']);
        exit;
    }

    /**
     * Affiche les documents d'un client par catégorie
     */
    public function view($clientId) {
        $this->checkAccess();
        
        // Récupération des filtres
        $siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
        $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
        
        // Récupération des données pour les filtres
        $client = $this->clientModel->getClientById($clientId);
        $sites = $this->siteModel->getSitesByClientId($clientId);
        $rooms = $siteId ? $this->roomModel->getRoomsBySiteId($siteId) : [];
        
        // Récupération des catégories
        $categories = $this->categoryModel->getAllCategories();
        
        // Récupération des documents par catégorie
        $documentsByCategory = [];
        foreach ($categories as $category) {
            $query = "SELECT d.*, s.name as site_name, r.name as room_name, 
                     u.first_name, u.last_name
                     FROM documentation d
                     LEFT JOIN sites s ON d.site_id = s.id
                     LEFT JOIN rooms r ON d.room_id = r.id
                     LEFT JOIN users u ON d.created_by = u.id
                     WHERE d.client_id = :client_id 
                     AND d.category_id = :category_id";
            
            $params = [
                ':client_id' => $clientId,
                ':category_id' => $category['id']
            ];
            
            if ($siteId) {
                $query .= " AND d.site_id = :site_id";
                $params[':site_id'] = $siteId;
            }
            
            if ($roomId) {
                $query .= " AND d.room_id = :room_id";
                $params[':room_id'] = $roomId;
            }
            
            $query .= " ORDER BY d.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($documents)) {
                $documentsByCategory[$category['id']] = [
                    'category' => $category,
                    'documents' => $documents
                ];
            }
        }
        
        require_once __DIR__ . '/../views/documentation/view.php';
    }

    /**
     * Récupère les salles d'un site spécifique via AJAX
     */
    public function get_rooms() {
        $this->checkAccess();
        
        if (!isset($_GET['site_id']) || empty($_GET['site_id'])) {
            header('Content-Type: application/json');
            echo json_encode([]);
            exit;
        }
        
        $siteId = (int)$_GET['site_id'];
        $rooms = $this->roomModel->getRoomsBySiteId($siteId);
        
        header('Content-Type: application/json');
        echo json_encode($rooms);
        exit;
    }
} 