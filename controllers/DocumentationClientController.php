<?php
require_once __DIR__ . '/../models/DocumentationModel.php';
require_once __DIR__ . '/../models/DocumentationCategoryModel.php';
require_once __DIR__ . '/../includes/functions.php';

class DocumentationClientController {
    private $db;
    private $documentationModel;
    private $categoryModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->documentationModel = new DocumentationModel($this->db);
        $this->categoryModel = new DocumentationCategoryModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur est connecté et a les permissions client
     */
    private function checkAccess() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier que l'utilisateur a la permission de voir la documentation
        if (!hasPermission('client_view_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour accéder à la documentation.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }

    /**
     * Affiche la liste des documents du client selon ses localisations autorisées
     */
    public function index() {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        custom_log("DocumentationClientController::index - userLocations: " . json_encode($userLocations), 'DEBUG');

        // Récupération des filtres
        $siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
        $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;

        try {
            // Récupération des sites selon les localisations autorisées
            $sites = $this->getSitesByLocations($userLocations);
            
            // Récupération des salles selon le filtre site
            $rooms = [];
            if ($siteId) {
                $rooms = $this->getRoomsBySiteAndLocations($siteId, $userLocations);
            }

            // Récupération des catégories
            $categories = $this->categoryModel->getAllCategories();
            
            // Récupération des documents par catégorie selon les localisations autorisées
            $documentsByCategory = [];
            foreach ($categories as $category) {
                $documents = $this->getDocumentsByCategoryAndLocations($category['id'], $userLocations, $siteId, $roomId);
                
                if (!empty($documents)) {
                    $documentsByCategory[$category['id']] = [
                        'category' => $category,
                        'documents' => $documents
                    ];
                }
            }

        } catch (Exception $e) {
            // En cas d'erreur, initialiser les variables avec des tableaux vides
            $sites = [];
            $rooms = [];
            $categories = [];
            $documentsByCategory = [];
            
            // Log de l'erreur
            custom_log("Erreur lors du chargement de la documentation client : " . $e->getMessage(), 'ERROR');
        }

        // Définir la page courante pour le menu
        $currentPage = 'documentation_client';
        $pageTitle = 'Ma Documentation';

        // Inclure la vue
        require_once __DIR__ . '/../views/documentation_client/index.php';
    }

    /**
     * Récupère les sites selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des sites
     */
    private function getSitesByLocations($userLocations) {
        if (empty($userLocations)) {
            return [];
        }
        
        // Extraire les client_id et site_id uniques des localisations
        $siteConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $siteId = $location['site_id'];
            
            if ($siteId !== null) {
                // Accès spécifique à un site
                $siteConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
            } else {
                // Accès au client entier
                $siteConditions[] = "(s.client_id = {$clientId})";
            }
        }
        
        $locationWhere = empty($siteConditions) ? "1=0" : "(" . implode(" OR ", $siteConditions) . ")";
        
        $sql = "SELECT DISTINCT s.* 
                FROM sites s
                WHERE {$locationWhere} AND s.status = 1
                ORDER BY s.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    private function getRoomsBySiteAndLocations($siteId, $userLocations) {
        if (empty($userLocations)) {
            return [];
        }
        
        // Extraire les conditions pour les salles
        $roomConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $locationSiteId = $location['site_id'];
            $roomId = $location['room_id'];
            
            if ($locationSiteId == $siteId) {
                if ($roomId !== null) {
                    // Accès spécifique à une salle
                    $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId} AND r.id = {$roomId})";
                } else {
                    // Accès à un site entier
                    $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
                }
            }
        }
        
        $locationWhere = empty($roomConditions) ? "1=0" : "(" . implode(" OR ", $roomConditions) . ")";
        
        $sql = "SELECT r.* 
                FROM rooms r
                JOIN sites s ON r.site_id = s.id
                WHERE r.site_id = ? AND {$locationWhere} AND r.status = 1
                ORDER BY r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$siteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les documents d'une catégorie selon les localisations autorisées
     * @param int $categoryId ID de la catégorie
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param int|null $siteId Filtre site optionnel
     * @param int|null $roomId Filtre salle optionnel
     * @return array Liste des documents
     */
    private function getDocumentsByCategoryAndLocations($categoryId, $userLocations, $siteId = null, $roomId = null) {
        if (empty($userLocations)) {
            return [];
        }
        
        // Construire les conditions de localisation
        $locationConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $locationSiteId = $location['site_id'];
            $locationRoomId = $location['room_id'];
            
            $condition = "d.client_id = {$clientId}";
            
            if ($locationSiteId !== null) {
                $condition .= " AND (d.site_id IS NULL OR d.site_id = {$locationSiteId})";
                if ($locationRoomId !== null) {
                    $condition .= " AND (d.room_id IS NULL OR d.room_id = {$locationRoomId})";
                }
            }
            
            $locationConditions[] = "({$condition})";
        }
        
        $locationWhere = "(" . implode(" OR ", $locationConditions) . ")";
        
        // Construire la requête
        $sql = "SELECT d.*, s.name as site_name, r.name as room_name, 
                       u.first_name, u.last_name
                FROM documentation d
                LEFT JOIN sites s ON d.site_id = s.id
                LEFT JOIN rooms r ON d.room_id = r.id
                LEFT JOIN users u ON d.created_by = u.id
                WHERE d.category_id = ? 
                AND d.visible_by_client = 1
                AND {$locationWhere}";
        
        $params = [$categoryId];
        
        // Ajouter les filtres optionnels
        if ($siteId) {
            $sql .= " AND (d.site_id = ? OR d.site_id IS NULL)";
            $params[] = $siteId;
        }
        
        if ($roomId) {
            $sql .= " AND (d.room_id = ? OR d.room_id IS NULL)";
            $params[] = $roomId;
        }
        
        $sql .= " ORDER BY d.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées (AJAX)
     */
    public function get_rooms() {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $siteId = $_POST['site_id'] ?? null;
        if (!$siteId) {
            echo json_encode(['error' => 'ID du site manquant']);
            return;
        }

        $userLocations = getUserLocations();
        $rooms = $this->getRoomsBySiteAndLocations($siteId, $userLocations);

        header('Content-Type: application/json');
        echo json_encode($rooms);
    }

    /**
     * Affiche le formulaire d'ajout de document
     */
    public function add() {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour ajouter de la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        
        // Récupération des données pour les filtres
        $sites = $this->getSitesByLocations($userLocations);
        $rooms = [];
        $categories = $this->categoryModel->getAllCategories();
        
        // Définir la page courante pour le menu
        $currentPage = 'documentation_client';
        $pageTitle = 'Ajouter un document';

        // Inclure la vue
        require_once __DIR__ . '/../views/documentation_client/add.php';
    }

    /**
     * Traite l'ajout d'un nouveau document
     */
    public function create() {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour ajouter de la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Récupérer les localisations autorisées de l'utilisateur
            $userLocations = getUserLocations();
            
            // Vérifier que le client_id est autorisé
            $clientId = (int)$_POST['client_id'];
            $siteId = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
            $roomId = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            
            if (!$this->isLocationAuthorized($clientId, $siteId, $roomId, $userLocations)) {
                $_SESSION['error'] = "Vous n'avez pas les permissions pour ajouter un document à cette localisation.";
                header('Location: ' . BASE_URL . 'documentation_client/add');
                exit;
            }

            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'content' => $_POST['content'] ?? null,
                'attachment_path' => '',
                'client_id' => $clientId,
                'site_id' => $siteId,
                'room_id' => $roomId,
                'category_id' => $_POST['category_id'] ?? null,
                'visible_by_client' => 1 // Par défaut visible par les clients
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
                    header('Location: ' . BASE_URL . 'documentation_client/add');
                    exit;
                }
            }

            if ($this->documentationModel->addDocument($data)) {
                $_SESSION['success'] = "Document ajouté avec succès.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du document.";
                header('Location: ' . BASE_URL . 'documentation_client/add');
                exit;
            }
        }
        
        header('Location: ' . BASE_URL . 'documentation_client/add');
        exit;
    }

    /**
     * Affiche le formulaire de modification d'un document
     */
    public function edit($id) {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour modifier la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Récupérer le document
        $document = $this->documentationModel->getDocumentById($id);
        if (!$document) {
            $_SESSION['error'] = "Document non trouvé.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Vérifier que l'utilisateur peut modifier ce document (créé par lui)
        if ($document['created_by'] != $_SESSION['user']['id']) {
            $_SESSION['error'] = "Vous ne pouvez modifier que vos propres documents.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        
        // Récupération des données pour les filtres
        $sites = $this->getSitesByLocations($userLocations);
        $rooms = [];
        if ($document['site_id']) {
            $rooms = $this->getRoomsBySiteAndLocations($document['site_id'], $userLocations);
        }
        $categories = $this->categoryModel->getAllCategories();
        
        // Définir la page courante pour le menu
        $currentPage = 'documentation_client';
        $pageTitle = 'Modifier un document';

        // Inclure la vue
        require_once __DIR__ . '/../views/documentation_client/edit.php';
    }

    /**
     * Traite la modification d'un document
     */
    public function update($id) {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour modifier la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Récupérer le document existant
            $existingDocument = $this->documentationModel->getDocumentById($id);
            if (!$existingDocument) {
                $_SESSION['error'] = "Document non trouvé.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            }

            // Vérifier que l'utilisateur peut modifier ce document (créé par lui)
            if ($existingDocument['created_by'] != $_SESSION['user']['id']) {
                $_SESSION['error'] = "Vous ne pouvez modifier que vos propres documents.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            }

            // Récupérer les localisations autorisées de l'utilisateur
            $userLocations = getUserLocations();
            
            // Vérifier que le client_id est autorisé
            $clientId = (int)$_POST['client_id'];
            $siteId = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
            $roomId = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            
            if (!$this->isLocationAuthorized($clientId, $siteId, $roomId, $userLocations)) {
                $_SESSION['error'] = "Vous n'avez pas les permissions pour modifier un document à cette localisation.";
                header('Location: ' . BASE_URL . 'documentation_client/edit/' . $id);
                exit;
            }

            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'content' => $_POST['content'] ?? null,
                'attachment_path' => $existingDocument['attachment_path'], // Garder l'ancien par défaut
                'client_id' => $clientId,
                'site_id' => $siteId,
                'room_id' => $roomId,
                'category_id' => $_POST['category_id'] ?? null,
                'visible_by_client' => 1 // Par défaut visible par les clients
            ];

            // Gestion de l'upload du fichier
            $fileUploadedSuccessfully = false;
            $newlyUploadedFilePath = null;

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
                    $fileUploadedSuccessfully = true;
                    $newlyUploadedFilePath = $targetPath;
                } else {
                    $_SESSION['error'] = "Erreur lors de l'upload du fichier.";
                    header('Location: ' . BASE_URL . 'documentation_client/edit/' . $id);
                    exit;
                }
            }

            // Supprimer l'ancien fichier si un nouveau a été uploadé
            if ($fileUploadedSuccessfully && !empty($existingDocument['attachment_path'])) {
                $oldFilePath = __DIR__ . '/../' . $existingDocument['attachment_path'];
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            if ($this->documentationModel->updateDocument($id, $data)) {
                $_SESSION['success'] = "Document mis à jour avec succès.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            } else {
                // Si la mise à jour a échoué et qu'un nouveau fichier a été uploadé, le supprimer
                if ($fileUploadedSuccessfully && $newlyUploadedFilePath && file_exists($newlyUploadedFilePath)) {
                    unlink($newlyUploadedFilePath);
                }
                $_SESSION['error'] = "Erreur lors de la mise à jour du document.";
                header('Location: ' . BASE_URL . 'documentation_client/edit/' . $id);
                exit;
            }
        }
        
        header('Location: ' . BASE_URL . 'documentation_client/edit/' . $id);
        exit;
    }

    /**
     * Supprime un document
     */
    public function delete($id) {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour supprimer la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Récupérer le document avant de le supprimer
        $document = $this->documentationModel->getDocumentById($id);
        if (!$document) {
            $_SESSION['error'] = "Document non trouvé.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Vérifier que l'utilisateur peut supprimer ce document (créé par lui)
        if ($document['created_by'] != $_SESSION['user']['id']) {
            $_SESSION['error'] = "Vous ne pouvez supprimer que vos propres documents.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Supprimer le fichier physique s'il existe
        if (!empty($document['attachment_path'])) {
            $filePath = __DIR__ . '/../' . $document['attachment_path'];
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    error_log("[ERROR] DocumentationClient Delete: Failed to delete file: " . $filePath);
                    $_SESSION['error'] = "Erreur lors de la suppression du fichier physique.";
                    header('Location: ' . BASE_URL . 'documentation_client');
                    exit;
                }
            }
        }

        // Supprimer l'entrée dans la base de données
        if ($this->documentationModel->deleteDocument($id)) {
            $_SESSION['success'] = "Document supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du document dans la base de données.";
        }
        
        header('Location: ' . BASE_URL . 'documentation_client');
        exit;
    }

    /**
     * Vérifie si une localisation est autorisée pour l'utilisateur
     * @param int $clientId ID du client
     * @param int|null $siteId ID du site
     * @param int|null $roomId ID de la salle
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return bool True si autorisé, false sinon
     */
    private function isLocationAuthorized($clientId, $siteId, $roomId, $userLocations) {
        foreach ($userLocations as $location) {
            if ($location['client_id'] == $clientId) {
                // Si l'utilisateur a accès au client entier
                if ($location['site_id'] === null) {
                    return true;
                }
                
                // Si l'utilisateur a accès à un site spécifique
                if ($location['site_id'] == $siteId) {
                    if ($location['room_id'] === null) {
                        return true;
                    }
                    
                    // Si l'utilisateur a accès à une salle spécifique
                    if ($location['room_id'] == $roomId) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
} 