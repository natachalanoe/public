<?php
require_once __DIR__ . '/../models/AccessLevelModel.php';
require_once __DIR__ . '/../models/MaterielModel.php';
require_once __DIR__ . '/../includes/functions.php';

class SettingsController {
    private $db;
    private $accessLevelModel;
    private $materielModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->accessLevelModel = new AccessLevelModel($this->db);
        $this->materielModel = new MaterielModel($this->db);
    }

    private function checkAdmin() {
        checkStaffAccess();
        if (!isAdmin()) {
            $_SESSION['error'] = "Accès réservé aux administrateurs.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }

    /**
     * Page d'accueil des paramètres
     */
    public function index() {
        $this->checkAdmin();
        
        // Définir les variables de page
        setPageVariables('Paramètres', 'settings');
        $currentPage = 'settings';

        // Inclure la vue
        require_once VIEWS_PATH . '/settings/index.php';
    }

    // Page de paramétrage des niveaux d'accès
    public function accessLevels() {
        $this->checkAdmin();
        $accessLevels = $this->accessLevelModel->getAllAccessLevels();
        $selectedId = $_GET['access_level_id'] ?? ($accessLevels[0]['id'] ?? null);
        $selectedLevel = $selectedId ? $this->accessLevelModel->getAccessLevelById($selectedId) : null;
        $fields = $this->materielModel->getChampsVisibilite();
        $rules = $selectedId ? $this->accessLevelModel->getVisibilityRulesForLevel($selectedId) : [];
        
        // Passer le modèle à la vue
        $accessLevelModel = $this->accessLevelModel;
        
        require_once VIEWS_PATH . '/settings/access_levels.php';
    }

    // Enregistrement des règles de visibilité
    public function saveAccessLevelVisibility() {
        $this->checkAdmin();
        $accessLevelId = $_POST['access_level_id'] ?? null;
        $fields = $_POST['fields'] ?? [];
        $applyToExisting = isset($_POST['apply_to_existing']) && $_POST['apply_to_existing'] == '1';
        
        if ($accessLevelId) {
            $success = $this->accessLevelModel->updateVisibilityRules($accessLevelId, $fields);
            
            if ($success) {
                $message = "Règles de visibilité mises à jour.";
                
                // Si on doit appliquer aux matériels existants
                if ($applyToExisting) {
                    try {
                        $result = $this->accessLevelModel->applyVisibilityToAllMaterials($accessLevelId);
                        if ($result) {
                            $message .= " Les règles ont été appliquées aux matériels existants.";
                        } else {
                            $message .= " Aucun matériel trouvé pour ce niveau d'accès.";
                        }
                    } catch (Exception $e) {
                        $message .= " Erreur lors de l'application aux matériels existants : " . $e->getMessage();
                    }
                }
                
                $_SESSION['success'] = $message;
            } else {
                $_SESSION['error'] = "Erreur lors de la mise à jour des règles de visibilité.";
            }
        } else {
            $_SESSION['error'] = "ID du niveau d'accès manquant.";
        }
        
        header('Location: ' . BASE_URL . 'settings/accessLevels');
        exit;
    }

    // Création d'un nouveau niveau d'accès
    public function createAccessLevel() {
        $this->checkAdmin();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name && $description) {
            $id = $this->accessLevelModel->createAccessLevel($name, $description);
            if ($id) {
                $_SESSION['success'] = "Niveau d'accès créé.";
                header('Location: ' . BASE_URL . 'settings/accessLevels?access_level_id=' . $id);
                exit;
            }
        }
        $_SESSION['error'] = "Erreur lors de la création du niveau d'accès.";
        header('Location: ' . BASE_URL . 'settings/accessLevels');
        exit;
    }

    // Récupérer les contrats par niveau d'accès
    public function getContractsByAccessLevel($accessLevelId) {
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        try {
            $contracts = $this->accessLevelModel->getContractsByAccessLevel($accessLevelId);
            echo json_encode(['success' => true, 'contracts' => $contracts]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Appliquer la visibilité à tous les matériels existants
    public function applyVisibilityToAllMaterials() {
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $accessLevelId = $input['access_level_id'] ?? null;
            
            if (!$accessLevelId) {
                throw new Exception('ID du niveau d\'accès manquant');
            }
            
            $updatedCount = $this->accessLevelModel->applyVisibilityToAllMaterials($accessLevelId);
            echo json_encode(['success' => true, 'updated_count' => $updatedCount]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Générer un aperçu des changements
    public function getVisibilityPreview($accessLevelId) {
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        try {
            $preview = $this->accessLevelModel->getVisibilityPreview($accessLevelId);
            echo json_encode(['success' => true, 'preview' => $preview]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Mettre à jour un niveau d'accès
    public function updateAccessLevel() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/accessLevels');
            exit;
        }

        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$id || !$name || !$description) {
            $_SESSION['error'] = "Tous les champs sont requis.";
            header('Location: ' . BASE_URL . 'settings/accessLevels');
            exit;
        }

        try {
            $success = $this->accessLevelModel->updateAccessLevel($id, $name, $description);
            
            if ($success) {
                $_SESSION['success'] = "Niveau d'accès mis à jour avec succès.";
            } else {
                $_SESSION['error'] = "Erreur lors de la mise à jour du niveau d'accès.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
        }
        
        header('Location: ' . BASE_URL . 'settings/accessLevels');
        exit;
    }

    // Vérifier si un niveau d'accès peut être supprimé
    public function checkAccessLevelDeletion() {
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID manquant']);
            return;
        }
        
        try {
            $canDelete = $this->accessLevelModel->canDeleteAccessLevel($id);
            echo json_encode([
                'success' => true,
                'can_delete' => $canDelete['can_delete'],
                'contracts_count' => $canDelete['contracts_count']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Supprimer un niveau d'accès
    public function deleteAccessLevel() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/accessLevels');
            exit;
        }

        $id = $_POST['id'] ?? null;

        if (!$id) {
            $_SESSION['error'] = "ID du niveau d'accès manquant.";
            header('Location: ' . BASE_URL . 'settings/accessLevels');
            exit;
        }

        try {
            // Vérifier si le niveau d'accès peut être supprimé
            $canDelete = $this->accessLevelModel->canDeleteAccessLevel($id);
            
            if (!$canDelete['can_delete']) {
                $_SESSION['error'] = "Ce niveau d'accès ne peut pas être supprimé car il est utilisé par " . $canDelete['contracts_count'] . " contrat(s).";
                header('Location: ' . BASE_URL . 'settings/accessLevels');
                exit;
            }

            $success = $this->accessLevelModel->deleteAccessLevel($id);
            
            if ($success) {
                $_SESSION['success'] = "Niveau d'accès supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Erreur lors de la suppression du niveau d'accès.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
        }
        
        header('Location: ' . BASE_URL . 'settings/accessLevels');
        exit;
    }

    // Mettre à jour l'ordre d'affichage des niveaux d'accès
    public function updateAccessLevelOrder() {
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/accessLevels');
            exit;
        }

        try {
            $ordersJson = $_POST['orders'] ?? '';
            
            if (empty($ordersJson)) {
                throw new Exception("Aucun ordre à mettre à jour.");
            }

            // Décoder le JSON reçu
            $orders = json_decode($ordersJson, true);
            
            if ($orders === null) {
                throw new Exception("Format de données invalide.");
            }

            $success = true;
            foreach ($orders as $id => $order) {
                if (!$this->accessLevelModel->updateDisplayOrder($id, $order)) {
                    $success = false;
                    break;
                }
            }

            if ($success) {
                $_SESSION['success'] = "L'ordre d'affichage a été mis à jour avec succès.";
            } else {
                throw new Exception("Erreur lors de la mise à jour de l'ordre d'affichage.");
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour de l'ordre : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'settings/accessLevels');
        exit;
    }

    /**
     * Page de gestion des icônes
     */
    public function icons() {
        $this->checkAdmin();
        require_once VIEWS_PATH . '/settings/icons.php';
    }

    /**
     * Mise à jour des icônes
     */
    public function updateIcons() {
        $this->checkAdmin();
        
        if (isset($_POST['icons']) && is_array($_POST['icons'])) {
            try {
                foreach ($_POST['icons'] as $key => $data) {
                    $sql = "UPDATE settings_icons SET icon_class = ?, icon_library = ?, updated_at = NOW() WHERE icon_key = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$data['class'], $data['library'], $key]);
                }
                $_SESSION['success'] = "Icônes mises à jour avec succès.";
            } catch (Exception $e) {
                $_SESSION['error'] = "Erreur lors de la mise à jour des icônes : " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Aucune donnée reçue.";
        }
        
        header('Location: ' . BASE_URL . 'settings/icons');
        exit;
    }

    // Page de gestion des extensions de fichiers
    public function fileExtensions() {
        $this->checkAdmin();
        
        // Passer la connexion à la base de données à la vue
        $db = $this->db;
        
        require_once VIEWS_PATH . '/settings/file_extensions.php';
    }

    // Ajouter une extension
    public function addExtension() {
        $this->checkAdmin();
        
        $extension = strtolower(trim($_POST['extension'] ?? ''));
        $mimeType = trim($_POST['mime_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validation
        if (empty($extension)) {
            $_SESSION['error'] = "Extension vide";
            header('Location: ' . BASE_URL . 'settings/fileExtensions');
            exit;
        }
        
        // Vérifier le format (lettres et chiffres uniquement)
        if (!preg_match('/^[a-z0-9]+$/', $extension)) {
            $_SESSION['error'] = "Format d'extension invalide";
            header('Location: ' . BASE_URL . 'settings/fileExtensions');
            exit;
        }
        
        // Vérifier si l'extension est blacklistée
        require_once INCLUDES_PATH . '/FileUploadValidator.php';
        if (FileUploadValidator::isExtensionBlacklisted($extension)) {
            $_SESSION['error'] = "Extension interdite pour des raisons de sécurité";
            header('Location: ' . BASE_URL . 'settings/fileExtensions');
            exit;
        }
        
        // Vérifier si l'extension existe déjà
        $stmt = $this->db->prepare("SELECT id FROM settings_allowed_extensions WHERE extension = ?");
        $stmt->execute([$extension]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Extension déjà présente";
            header('Location: ' . BASE_URL . 'settings/fileExtensions');
            exit;
        }
        
        // Ajouter l'extension
        try {
            $stmt = $this->db->prepare("INSERT INTO settings_allowed_extensions (extension, mime_type, description) VALUES (?, ?, ?)");
            $stmt->execute([$extension, $mimeType, $description]);
            $_SESSION['success'] = "Extension $extension ajoutée avec succès";
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de l'ajout de l'extension : " . $e->getMessage();
        }
        
        header('Location: ' . BASE_URL . 'settings/fileExtensions');
        exit;
    }

    // Activer/désactiver une extension
    public function toggleExtension() {
        $this->checkAdmin();
        
        $extensionId = $_POST['extension_id'] ?? null;
        $isActive = $_POST['is_active'] ?? 0;
        
        if ($extensionId) {
            try {
                $stmt = $this->db->prepare("UPDATE settings_allowed_extensions SET is_active = ? WHERE id = ?");
                $stmt->execute([$isActive, $extensionId]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID manquant']);
        }
        exit;
    }

    // Supprimer une extension
    public function deleteExtension() {
        $this->checkAdmin();
        
        $extensionId = $_POST['extension_id'] ?? null;
        
        if ($extensionId) {
            try {
                // Récupérer l'extension avant suppression pour le message
                $stmt = $this->db->prepare("SELECT extension FROM settings_allowed_extensions WHERE id = ?");
                $stmt->execute([$extensionId]);
                $extension = $stmt->fetch();
                
                if ($extension) {
                    // Supprimer l'extension
                    $stmt = $this->db->prepare("DELETE FROM settings_allowed_extensions WHERE id = ?");
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

    // Récupérer les extensions autorisées (pour validation côté client)
    public function getAllowedExtensions() {
        require_once INCLUDES_PATH . '/FileUploadValidator.php';
        $extensions = FileUploadValidator::getAllowedExtensions($this->db);
        $extensionList = [];
        
        foreach ($extensions as $ext) {
            $extensionList[] = $ext['extension'];
        }
        
        header('Content-Type: application/json');
        echo json_encode(['extensions' => $extensionList]);
        exit;
    }
} 