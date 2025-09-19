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

            // Paramètres OAuth2
            $oauth2Settings = [
                'oauth2_enabled' => isset($_POST['oauth2_enabled']) ? '1' : '0',
                'oauth2_client_id' => $_POST['oauth2_client_id'] ?? '',
                'oauth2_client_secret' => $_POST['oauth2_client_secret'] ?? '',
                'oauth2_tenant_id' => $_POST['oauth2_tenant_id'] ?? '',
                'oauth2_redirect_uri' => $_POST['oauth2_redirect_uri'] ?? '',
            ];

            // Sauvegarder chaque setting SMTP
            foreach ($smtpSettings as $key => $value) {
                $config->set($key, $value);
            }

            // Sauvegarder chaque setting OAuth2
            foreach ($oauth2Settings as $key => $value) {
                $config->set($key, $value);
            }

            $_SESSION['success'] = "Configuration SMTP et OAuth2 sauvegardée avec succès.";
            
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

    /**
     * Test de la configuration SMTP
     */
    public function testSmtp() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        header('Content-Type: application/json');

        try {
            // Récupérer les paramètres SMTP du formulaire
            $smtpSettings = [
                'host' => $_POST['mail_host'] ?? '',
                'port' => (int)($_POST['mail_port'] ?? 587),
                'username' => $_POST['mail_username'] ?? '',
                'password' => $_POST['mail_password'] ?? '',
                'encryption' => $_POST['mail_encryption'] ?? 'tls',
                'from_address' => $_POST['mail_from_address'] ?? '',
                'from_name' => $_POST['mail_from_name'] ?? '',
            ];

            // Validation des paramètres requis
            if (empty($smtpSettings['host']) || empty($smtpSettings['from_address'])) {
                throw new Exception('Serveur SMTP et adresse d\'expédition sont requis');
            }

            // Test de connexion SMTP
            $result = $this->performSmtpTest($smtpSettings);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Connexion SMTP réussie. ' . $result['message']
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => $result['message']
                ]);
            }

        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Effectue le test de connexion SMTP
     */
    private function performSmtpTest($settings) {
        try {
            // Créer une socket pour tester la connexion
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            // Déterminer le protocole selon l'encryption
            $protocol = '';
            $port = $settings['port'];
            
            switch ($settings['encryption']) {
                case 'ssl':
                    $protocol = 'ssl://';
                    if ($port == 587) $port = 465; // Port SSL par défaut
                    break;
                case 'tls':
                    $protocol = 'tcp://';
                    break;
                default:
                    $protocol = 'tcp://';
                    break;
            }

            $host = $protocol . $settings['host'];
            
            // Tentative de connexion
            $socket = @stream_socket_client(
                $host . ':' . $port, 
                $errno, 
                $errstr, 
                10, // timeout 10 secondes
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "Impossible de se connecter au serveur SMTP {$settings['host']}:{$port}. Erreur: {$errstr} (Code: {$errno})"
                ];
            }

            // Lire la réponse initiale du serveur
            $response = fgets($socket, 1024);
            if (!$response || !preg_match('/^220/', $response)) {
                fclose($socket);
                return [
                    'success' => false,
                    'message' => "Réponse invalide du serveur SMTP: " . trim($response)
                ];
            }

            // Envoyer EHLO
            fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
            $response = fgets($socket, 1024);
            
            // Lire toutes les lignes de la réponse EHLO
            $ehloResponse = $response;
            while (preg_match('/^250-/', $response)) {
                $response = fgets($socket, 1024);
                $ehloResponse .= $response;
            }
            
            // Si TLS est demandé, essayer de l'activer
            if ($settings['encryption'] === 'tls' && preg_match('/STARTTLS/i', $ehloResponse)) {
                fwrite($socket, "STARTTLS\r\n");
                $response = fgets($socket, 1024);
                
                if (preg_match('/^220/', $response)) {
                    // Activer le chiffrement TLS
                    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        fclose($socket);
                        return [
                            'success' => false,
                            'message' => "Impossible d'activer le chiffrement TLS"
                        ];
                    }
                    
                    // Renvoyer EHLO après TLS
                    fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
                    $response = fgets($socket, 1024);
                    
                    // Lire toutes les lignes de la nouvelle réponse EHLO
                    $ehloResponse = $response;
                    while (preg_match('/^250-/', $response)) {
                        $response = fgets($socket, 1024);
                        $ehloResponse .= $response;
                    }
                }
            }

            // Si des identifiants sont fournis, tester l'authentification
            if (!empty($settings['username']) && !empty($settings['password'])) {
                // Vérifier si AUTH est supporté dans la réponse EHLO complète
                if (preg_match('/AUTH/i', $ehloResponse)) {
                    // Essayer différentes méthodes d'authentification
                    $authMethods = ['LOGIN', 'PLAIN', 'CRAM-MD5'];
                    $authSuccess = false;
                    $lastError = '';
                    
                    foreach ($authMethods as $method) {
                        if (preg_match('/' . preg_quote($method, '/') . '/i', $ehloResponse)) {
                            fwrite($socket, "AUTH " . $method . "\r\n");
                            $response = fgets($socket, 1024);
                            
                            if (preg_match('/^334/', $response)) {
                                if ($method === 'LOGIN') {
                                    // Envoyer le nom d'utilisateur (base64)
                                    fwrite($socket, base64_encode($settings['username']) . "\r\n");
                                    $response = fgets($socket, 1024);
                                    
                                    if (preg_match('/^334/', $response)) {
                                        // Envoyer le mot de passe (base64)
                                        fwrite($socket, base64_encode($settings['password']) . "\r\n");
                                        $response = fgets($socket, 1024);
                                        
                                        if (preg_match('/^235/', $response)) {
                                            $authSuccess = true;
                                            break;
                                        } else {
                                            $lastError = "Échec de l'authentification LOGIN: " . trim($response);
                                        }
                                    } else {
                                        $lastError = "Erreur lors de l'envoi du nom d'utilisateur: " . trim($response);
                                    }
                                } elseif ($method === 'PLAIN') {
                                    // Envoyer les identifiants en format PLAIN (base64)
                                    $credentials = base64_encode("\0" . $settings['username'] . "\0" . $settings['password']);
                                    fwrite($socket, $credentials . "\r\n");
                                    $response = fgets($socket, 1024);
                                    
                                    if (preg_match('/^235/', $response)) {
                                        $authSuccess = true;
                                        break;
                                    } else {
                                        $lastError = "Échec de l'authentification PLAIN: " . trim($response);
                                    }
                                }
                            } else {
                                $lastError = "Méthode d'authentification " . $method . " non supportée: " . trim($response);
                            }
                        }
                    }
                    
                    if ($authSuccess) {
                        fclose($socket);
                        return [
                            'success' => true,
                            'message' => "Connexion et authentification SMTP réussies avec " . $method
                        ];
                    } else {
                        fclose($socket);
                        return [
                            'success' => false,
                            'message' => $lastError ?: "Aucune méthode d'authentification supportée. Réponse EHLO complète: " . $ehloResponse
                        ];
                    }
                } else {
                    fclose($socket);
                    return [
                        'success' => false,
                        'message' => "Le serveur ne supporte pas l'authentification. Réponse EHLO complète: " . $ehloResponse
                    ];
                }
            } else {
                // Pas d'authentification, juste tester la connexion
                fclose($socket);
                return [
                    'success' => true,
                    'message' => "Connexion SMTP réussie (sans authentification). Réponse EHLO complète: " . $ehloResponse
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Erreur lors du test SMTP: " . $e->getMessage()
            ];
        }
    }

    /**
     * Test de la configuration OAuth2
     */
    public function testOAuth2() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        header('Content-Type: application/json');

        try {
            // Récupérer les paramètres OAuth2 du formulaire
            $oauth2Settings = [
                'client_id' => $_POST['oauth2_client_id'] ?? '',
                'client_secret' => $_POST['oauth2_client_secret'] ?? '',
                'tenant_id' => $_POST['oauth2_tenant_id'] ?? '',
                'redirect_uri' => $_POST['oauth2_redirect_uri'] ?? '',
            ];

            // Validation des paramètres requis
            if (empty($oauth2Settings['client_id']) || empty($oauth2Settings['tenant_id'])) {
                throw new Exception('Client ID et Tenant ID sont requis');
            }

            // Test de la configuration OAuth2
            $result = $this->performOAuth2Test($oauth2Settings);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Configuration OAuth2 valide. ' . $result['message']
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => $result['message']
                ]);
            }

        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Effectue le test de configuration OAuth2
     */
    private function performOAuth2Test($settings) {
        try {
            // Vérifier la connectivité vers Microsoft
            $discoveryUrl = "https://login.microsoftonline.com/{$settings['tenant_id']}/.well-known/openid_configuration";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $discoveryUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return [
                    'success' => false,
                    'message' => "Erreur de connexion: $error"
                ];
            }

            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'message' => "Erreur HTTP $httpCode lors de la vérification du tenant"
                ];
            }

            $discoveryData = json_decode($response, true);
            if (!$discoveryData) {
                return [
                    'success' => false,
                    'message' => "Réponse invalide du serveur de découverte"
                ];
            }

            // Vérifier que les endpoints sont disponibles
            if (!isset($discoveryData['authorization_endpoint']) || !isset($discoveryData['token_endpoint'])) {
                return [
                    'success' => false,
                    'message' => "Endpoints OAuth2 non trouvés dans la réponse de découverte"
                ];
            }

            return [
                'success' => true,
                'message' => "Configuration OAuth2 valide. Tenant ID vérifié avec succès."
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Erreur lors du test OAuth2: " . $e->getMessage()
            ];
        }
    }

    /**
     * Callback OAuth2 - traite la réponse d'autorisation
     */
    public function oauth2Callback() {
        $this->checkAdmin();
        
        try {
            $code = $_GET['code'] ?? '';
            $state = $_GET['state'] ?? '';
            $error = $_GET['error'] ?? '';

            if (!empty($error)) {
                $errorDescription = $_GET['error_description'] ?? 'Erreur inconnue';
                throw new Exception("Erreur d'autorisation: $error - $errorDescription");
            }

            if (empty($code)) {
                throw new Exception("Code d'autorisation manquant");
            }

            if ($state !== 'oauth2_auth') {
                throw new Exception("État de sécurité invalide");
            }

            // Échanger le code contre un token
            $tokenData = $this->exchangeCodeForToken($code);
            
            if ($tokenData) {
                // Sauvegarder les tokens
                $config = Config::getInstance();
                $config->set('oauth2_access_token', $tokenData['access_token']);
                $config->set('oauth2_refresh_token', $tokenData['refresh_token']);
                
                $expiresIn = $tokenData['expires_in'] ?? 3600;
                $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
                $config->set('oauth2_token_expires', $expiresAt);

                $_SESSION['success'] = "Autorisation OAuth2 réussie ! Les tokens ont été sauvegardés.";
            } else {
                throw new Exception("Échec de l'échange du code d'autorisation");
            }

        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de l'autorisation OAuth2: " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'settings/email');
        exit;
    }

    /**
     * Échange le code d'autorisation contre un token d'accès
     */
    private function exchangeCodeForToken($code) {
        try {
            $config = Config::getInstance();
            $clientId = $config->get('oauth2_client_id', '');
            $clientSecret = $config->get('oauth2_client_secret', '');
            $tenantId = $config->get('oauth2_tenant_id', '');
            $redirectUri = $config->get('oauth2_redirect_uri', '');

            if (empty($clientId) || empty($clientSecret) || empty($tenantId) || empty($redirectUri)) {
                throw new Exception("Configuration OAuth2 incomplète");
            }

            $tokenUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";
            
            $postData = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
                'scope' => 'https://outlook.office365.com/SMTP.Send offline_access'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tokenUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Erreur HTTP $httpCode lors de l'échange du token");
            }

            $tokenData = json_decode($response, true);
            if (!$tokenData || isset($tokenData['error'])) {
                throw new Exception("Erreur lors de l'échange du token: " . ($tokenData['error_description'] ?? 'Erreur inconnue'));
            }

            return $tokenData;

        } catch (Exception $e) {
            custom_log("Erreur lors de l'échange du token OAuth2: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
} 