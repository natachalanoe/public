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

    /**
     * Page de configuration système
     */
    public function configuration() {
        $this->checkAdmin();
        
        // Définir les variables de page
        setPageVariables('Configuration système', 'settings');
        $currentPage = 'settings';

        // Inclure la vue
        require_once VIEWS_PATH . '/settings/configuration.php';
    }

    /**
     * Sauvegarde de la configuration
     */
    public function saveConfiguration() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = "Méthode non autorisée.";
            header('Location: ' . BASE_URL . 'settings/configuration');
            exit;
        }

        try {
            $tarif_ticket = trim($_POST['tarif_ticket'] ?? '');
            $coef_intervention = trim($_POST['coef_intervention'] ?? '');
            
            // Validation
            if (empty($tarif_ticket) || !is_numeric($tarif_ticket) || $tarif_ticket < 0) {
                throw new Exception("Le tarif du ticket doit être un nombre positif.");
            }
            
            if (empty($coef_intervention) || !is_numeric($coef_intervention) || $coef_intervention < 0 || $coef_intervention > 1) {
                throw new Exception("Le coefficient d'intervention doit être un nombre entre 0 et 1.");
            }

            // Traiter les deux settings
            $settings = [
                'tarif_ticket' => [
                    'value' => $tarif_ticket,
                    'description' => 'Tarif par défaut pour un ticket d\'intervention (en euros)',
                    'group' => 'pricing'
                ],
                'coef_intervention' => [
                    'value' => $coef_intervention,
                    'description' => 'Coefficient global pour le calcul des tickets d\'intervention',
                    'group' => 'interventions'
                ]
            ];

            foreach ($settings as $key => $data) {
                // Vérifier si le setting existe déjà
                $stmt = $this->db->prepare("SELECT id FROM settings WHERE setting_key = ?");
                $stmt->execute([$key]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Mettre à jour le setting existant
                    $stmt = $this->db->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
                    $stmt->execute([$data['value'], $key]);
                } else {
                    // Créer un nouveau setting
                    $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value, setting_description, setting_group) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$key, $data['value'], $data['description'], $data['group']]);
                }
            }

            // Recharger la configuration
            $config = Config::getInstance();
            $config->reloadSettings();

            $_SESSION['success'] = "Configuration sauvegardée avec succès.";
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la sauvegarde : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'settings/configuration');
        exit;
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

    /**
     * Page de configuration email
     */
    public function email() {
        $this->checkAdmin();
        
        // Définir les variables de page
        setPageVariables('Configuration email', 'settings');
        $currentPage = 'settings';

        // Récupérer les templates
        $templates = [];
        try {
            $stmt = $this->db->query("SELECT * FROM mail_templates ORDER BY template_type, name");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $templates = [];
        }

        // Inclure la vue
        require_once VIEWS_PATH . '/settings/email.php';
    }

    /**
     * Sauvegarde la configuration SMTP
     */
    public function saveEmailConfig() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/email');
            exit;
        }

        try {
            $config = Config::getInstance();
            
            // Paramètres SMTP
            $smtpSettings = [
                'mail_host' => $_POST['mail_host'] ?? '',
                'mail_port' => $_POST['mail_port'] ?? '587',
                'mail_username' => $_POST['mail_username'] ?? '',
                'mail_password' => $_POST['mail_password'] ?? '',
                'mail_encryption' => $_POST['mail_encryption'] ?? 'tls',
                'mail_from_address' => $_POST['mail_from_address'] ?? '',
                'mail_from_name' => $_POST['mail_from_name'] ?? '',
            ];

            // Sauvegarder chaque setting
            foreach ($smtpSettings as $key => $value) {
                $config->set($key, $value);
            }

            $_SESSION['success'] = "Configuration SMTP sauvegardée avec succès.";
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la sauvegarde : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'settings/email');
        exit;
    }

    /**
     * Sauvegarde les paramètres d'envoi automatique
     */
    public function saveEmailSettings() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/email');
            exit;
        }

        try {
            $config = Config::getInstance();
            
            // Paramètres d'envoi automatique
            $emailSettings = [
                'email_auto_send_creation' => isset($_POST['email_auto_send_creation']) ? '1' : '0',
                'email_auto_send_closing' => isset($_POST['email_auto_send_closing']) ? '1' : '0',
                'email_auto_send_bon' => isset($_POST['email_auto_send_bon']) ? '1' : '0',
                'test_email' => $_POST['test_email'] ?? '',
            ];

            // Sauvegarder chaque setting
            foreach ($emailSettings as $key => $value) {
                $config->set($key, $value);
            }

            $_SESSION['success'] = "Paramètres d'envoi automatique sauvegardés avec succès.";
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la sauvegarde : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'settings/email');
        exit;
    }

    /**
     * Page de gestion des templates email
     */
    public function emailTemplate($templateId = null) {
        $this->checkAdmin();
        
        // Définir les variables de page
        setPageVariables('Gestion des templates email', 'settings');
        $currentPage = 'settings';

        // Si aucun ID n'est passé en paramètre, essayer de le récupérer depuis l'URL
        if ($templateId === null) {
            $templateId = isset($_GET['id']) ? (int)$_GET['id'] : null;
        }
        
        $template = null;
        $isEdit = false;

        // Si on édite un template existant
        if ($templateId) {
            try {
                $stmt = $this->db->prepare("SELECT * FROM mail_templates WHERE id = ?");
                $stmt->execute([$templateId]);
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                $isEdit = true;
            } catch (Exception $e) {
                $_SESSION['error'] = "Erreur lors de la récupération du template.";
                header('Location: ' . BASE_URL . 'settings/email');
                exit;
            }
        }

        // Inclure la vue
        require_once VIEWS_PATH . '/settings/emailTemplate.php';
    }

    /**
     * Sauvegarde un template email
     */
    public function saveEmailTemplate() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/email');
            exit;
        }

        try {
            $templateData = [
                'name' => $_POST['name'] ?? '',
                'template_type' => $_POST['template_type'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'body' => $_POST['body'] ?? '',
                'description' => $_POST['description'] ?? '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            // Validation
            if (empty($templateData['name']) || empty($templateData['template_type']) || 
                empty($templateData['subject']) || empty($templateData['body'])) {
                throw new Exception("Tous les champs obligatoires doivent être remplis.");
            }

            $isEdit = !empty($_POST['template_id']);
            
            if ($isEdit) {
                // Mise à jour
                $sql = "UPDATE mail_templates SET 
                        name = ?, template_type = ?, subject = ?, body = ?, 
                        description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $templateData['name'],
                    $templateData['template_type'],
                    $templateData['subject'],
                    $templateData['body'],
                    $templateData['description'],
                    $templateData['is_active'],
                    $_POST['template_id']
                ]);
                $_SESSION['success'] = "Template mis à jour avec succès.";
            } else {
                // Création
                $sql = "INSERT INTO mail_templates (name, template_type, subject, body, description, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $templateData['name'],
                    $templateData['template_type'],
                    $templateData['subject'],
                    $templateData['body'],
                    $templateData['description'],
                    $templateData['is_active']
                ]);
                $_SESSION['success'] = "Template créé avec succès.";
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la sauvegarde : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'settings/email');
        exit;
    }

    /**
     * Supprime un template email
     */
    public function deleteEmailTemplate() {
        $this->checkAdmin();
        
        $templateId = $_GET['id'] ?? null;
        
        if (!$templateId) {
            $_SESSION['error'] = "ID du template manquant.";
            header('Location: ' . BASE_URL . 'settings/email');
            exit;
        }

        try {
            $sql = "DELETE FROM mail_templates WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$templateId]);
            
            $_SESSION['success'] = "Template supprimé avec succès.";
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'settings/email');
        exit;
    }
} 