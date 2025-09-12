<?php
/**
 * Contrôleur pour la gestion des interventions clients
 * Filtre automatiquement selon les localisations autorisées du client
 */
class InterventionsClientController {
    private $db;
    private $model;
    private $clientModel;
    private $siteModel;
    private $roomModel;

    public function __construct($db) {
        $this->db = $db;
        
        // Charger les modèles nécessaires
        require_once __DIR__ . '/../models/InterventionsClientModel.php';
        require_once __DIR__ . '/../models/ClientModel.php';
        require_once __DIR__ . '/../models/SiteModel.php';
        require_once __DIR__ . '/../models/RoomModel.php';
        
        $this->model = new InterventionsClientModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->roomModel = new RoomModel($db);
    }

    /**
     * Affiche la liste des interventions du client
     */
    public function index() {
        // Vérifier si l'utilisateur est connecté et est un client
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier la permission spécifique pour voir les interventions
        if (!hasPermission('client_view_interventions')) {
            $_SESSION['error'] = "Vous n'avez pas la permission d'accéder aux interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer l'ID du client depuis la session
        $clientId = $_SESSION['user']['client_id'] ?? null;
        
        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupérer les localisations autorisées
        $userLocations = getUserLocations();
        
        // Si l'utilisateur n'a pas de localisations définies, utiliser le client_id par défaut
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'room_id' => null]];
        }

        // Récupérer les filtres depuis l'URL
        $filters = [
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        // Si aucun filtre de statut n'est spécifié, filtrer par défaut sur les interventions non fermées ou annulées
        if (empty($filters['status_id'])) {
            $filters['exclude_status_ids'] = [6, 7]; // 6 = Fermé, 7 = Annulé
        }

        // Construire la clause WHERE pour les localisations
        $locationWhere = buildLocationWhereClause($userLocations, 'i.client_id', 'i.site_id', 'i.room_id');
        
        // Récupérer les interventions filtrées selon les localisations
        $interventions = $this->model->getAllByLocations($userLocations, $filters);
        
        // Récupérer les données pour les filtres
        $sites = $this->model->getSitesByLocations($userLocations);
        $rooms = !empty($filters['site_id']) ? $this->model->getRoomsBySiteAndLocations($filters['site_id'], $userLocations) : [];
        
        // Récupérer les statuts
        $statuses = $this->model->getAllStatuses();
        
        // Récupérer les statistiques
        $stats = $this->model->getStatsByLocations($userLocations);
        
        // Récupérer les statistiques par statut pour les filtres rapides
        $statsByStatus = $this->model->getStatsByStatusAndLocations($userLocations);
        
        // Charger la vue
        require_once __DIR__ . '/../views/interventions_client/index.php';
    }

    /**
     * Affiche les détails d'une intervention
     */
    public function view($id) {
        // Vérifier si l'utilisateur est connecté et est un client
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier la permission spécifique pour voir les interventions
        if (!hasPermission('client_view_interventions')) {
            $_SESSION['error'] = "Vous n'avez pas la permission d'accéder aux interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer l'ID du client depuis la session
        $clientId = $_SESSION['user']['client_id'] ?? null;
        
        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupérer les localisations autorisées
        $userLocations = getUserLocations();
        
        // Si l'utilisateur n'a pas de localisations définies, utiliser le client_id par défaut
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'room_id' => null]];
        }

        // Log pour débogage
        custom_log("Tentative d'accès à l'intervention ID: $id", 'DEBUG');
        custom_log("Localisations utilisateur: " . json_encode($userLocations), 'DEBUG');

        // Récupérer l'intervention
        $intervention = $this->model->getByIdWithAccess($id, $userLocations);
        
        if (!$intervention) {
            custom_log("Intervention non trouvée ou non autorisée pour l'ID: $id", 'ERROR');
            $_SESSION['error'] = "Intervention non trouvée ou non autorisée";
            header('Location: ' . BASE_URL . 'interventions_client');
            exit;
        }

        custom_log("Intervention trouvée: " . json_encode($intervention), 'DEBUG');

        // Si getByIdWithAccess() retourne l'intervention, c'est que l'utilisateur y a déjà accès
        // Pas besoin de double vérification

        // Récupérer les commentaires (filtrés pour les clients)
        $comments = $this->model->getCommentsWithAccess($id, $userLocations, true, $_SESSION['user']['id']);

        // Récupérer les pièces jointes
        $attachments = $this->model->getAttachmentsWithAccess($id, $userLocations);

        // Charger la vue
        require_once __DIR__ . '/../views/interventions_client/view.php';
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées
     */
    public function getRoomsBySiteAndLocations($siteId, $userLocations) {
        return $this->model->getRoomsBySiteAndLocations($siteId, $userLocations);
    }

    /**
     * Ajouter un commentaire
     */
    public function addComment($interventionId) {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment'] ?? '');
            
            if (empty($comment)) {
                $_SESSION['error'] = 'Le commentaire ne peut pas etre vide.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
                exit;
            }

            // Verifier que l'intervention appartient aux locations autorisees du client
            $userLocations = getUserLocations();
            $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);
            
            if (!$intervention) {
                $_SESSION['error'] = 'Intervention non trouvee ou non autorisee.';
                header('Location: ' . BASE_URL . 'interventions_client');
                exit;
            }

            $userId = $_SESSION['user']['id'];
            $success = $this->model->addComment($interventionId, $userId, $comment, true);

            if ($success) {
                $_SESSION['success'] = 'Commentaire ajoute avec succes.';
            } else {
                $_SESSION['error'] = 'Erreur lors de l\'ajout du commentaire.';
            }
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
        exit;
    }

    /**
     * Modifier un commentaire
     */
    public function editComment($commentId) {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment'] ?? '');
            
            if (empty($comment)) {
                $_SESSION['error'] = 'Le commentaire ne peut pas etre vide.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
                exit;
            }

            $userId = $_SESSION['user']['id'];
            
            // Verifier que le commentaire appartient a l'utilisateur connecte
            $commentData = $this->model->getCommentById($commentId);
            if (!$commentData || $commentData['created_by'] != $userId) {
                $_SESSION['error'] = 'Vous n\'etes pas autorise a modifier ce commentaire.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
                exit;
            }

            $success = $this->model->updateComment($commentId, $comment);

            if ($success) {
                $_SESSION['success'] = 'Commentaire modifie avec succes.';
            } else {
                $_SESSION['error'] = 'Erreur lors de la modification du commentaire.';
            }
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
        exit;
    }

    /**
     * Supprimer un commentaire
     */
    public function deleteComment($commentId) {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $userId = $_SESSION['user']['id'];
        
        // Verifier que le commentaire appartient a l'utilisateur connecte
        $commentData = $this->model->getCommentById($commentId);
        if (!$commentData || $commentData['created_by'] != $userId) {
            $_SESSION['error'] = 'Vous n\'etes pas autorise a supprimer ce commentaire.';
            header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
            exit;
        }

        $success = $this->model->deleteComment($commentId);

        if ($success) {
            $_SESSION['success'] = 'Commentaire supprime avec succes.';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression du commentaire.';
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
        exit;
    }

    /**
     * Obtenir l'ID de l'intervention a partir de l'ID du commentaire
     */
    private function getInterventionIdFromComment($commentId) {
        $comment = $this->model->getCommentById($commentId);
        return $comment ? $comment['intervention_id'] : 0;
    }

    /**
     * Ajouter une piece jointe
     */
    public function addAttachment($interventionId) {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verifier que l'intervention appartient aux locations autorisees du client
            $userLocations = getUserLocations();
            $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);
            
            if (!$intervention) {
                $_SESSION['error'] = 'Intervention non trouvee ou non autorisee.';
                header('Location: ' . BASE_URL . 'interventions_client');
                exit;
            }

            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error'] = 'Erreur lors du telechargement du fichier.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
                exit;
            }

            $file = $_FILES['attachment'];
            $userId = $_SESSION['user']['id'];
            
            // Récupérer le nom personnalisé s'il existe
            $customName = isset($_POST['custom_name']) && !empty(trim($_POST['custom_name'])) 
                ? trim($_POST['custom_name']) 
                : null;
            
            $success = $this->model->addAttachment($interventionId, $userId, $file, $customName);

            if ($success) {
                $_SESSION['success'] = 'Piece jointe ajoutee avec succes.';
            } else {
                $_SESSION['error'] = 'Erreur lors de l\'ajout de la piece jointe.';
            }
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
        exit;
    }

    /**
     * Ajouter plusieurs pieces jointes (Drag & Drop)
     */
    public function addMultipleAttachments($interventionId) {
        if (!hasPermission('client_view_interventions')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit;
        }

        try {
            // Vérifier que l'intervention appartient aux locations autorisées du client
            $userLocations = getUserLocations();
            $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);
            
            if (!$intervention) {
                throw new Exception("Intervention non trouvée ou non autorisée");
            }

            // Vérifier qu'il y a des fichiers
            if (!isset($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
                throw new Exception("Aucun fichier à uploader");
            }

            require_once INCLUDES_PATH . '/FileUploadValidator.php';
            
            $uploadedFiles = [];
            $errors = [];
            $userId = $_SESSION['user']['id'];
            
            // Traiter chaque fichier
            foreach ($_FILES['attachments']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['attachments']['error'][$index] !== UPLOAD_ERR_OK) {
                    $errors[] = "Erreur lors de l'upload du fichier " . ($index + 1);
                    continue;
                }

                $originalFileName = $_FILES['attachments']['name'][$index];
                $fileSize = $_FILES['attachments']['size'][$index];
                $fileTmpPath = $tmpName;
                
                // Récupérer le nom personnalisé s'il existe
                $customName = isset($_POST['custom_names'][$index]) && !empty(trim($_POST['custom_names'][$index])) 
                    ? trim($_POST['custom_names'][$index]) 
                    : null;

                // Vérifier la taille du fichier
                $maxFileSize = 10 * 1024 * 1024; // 10MB
                if ($fileSize > $maxFileSize) {
                    $errors[] = "Le fichier '$originalFileName' est trop volumineux (max 10MB)";
                    continue;
                }

                // Vérifier l'extension
                $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                    $errors[] = "Le format du fichier '$originalFileName' n'est pas accepté";
                    continue;
                }

                // Créer le dossier de stockage s'il n'existe pas
                $uploadDir = __DIR__ . '/../uploads/interventions/' . $interventionId;
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Préparer le nom du fichier
                $fileName = pathinfo($originalFileName, PATHINFO_FILENAME);
                $fileName = str_replace(' ', '_', $fileName);
                $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileName);
                $baseFileName = $fileName;
                $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
                $finalFileName = $baseFileName . '.' . $extension;
                $filePath = $uploadDir . '/' . $finalFileName;

                // Éviter les doublons
                $counter = 1;
                while (file_exists($filePath)) {
                    $finalFileName = $baseFileName . '_' . $counter . '.' . $extension;
                    $filePath = $uploadDir . '/' . $finalFileName;
                    $counter++;
                }

                // Déplacer le fichier
                if (move_uploaded_file($fileTmpPath, $filePath)) {
                    // Enregistrer en base de données directement
                    try {
                        $this->db->beginTransaction();

                        // Insérer la pièce jointe
                        $sql = "INSERT INTO pieces_jointes (
                                    nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, 
                                    commentaire, masque_client, created_by
                                ) VALUES (
                                    :nom_fichier, :nom_personnalise, :chemin_fichier, :type_fichier, :taille_fichier,
                                    :commentaire, :masque_client, :created_by
                                )";

                        // Utiliser le nom personnalisé s'il existe, sinon le nom original
                        $displayName = $customName ?: $originalFileName;
                        
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':nom_fichier' => $finalFileName, // Nom physique du fichier
                            ':nom_personnalise' => $displayName, // Nom d'affichage
                            ':chemin_fichier' => 'uploads/interventions/' . $interventionId . '/' . $finalFileName,
                            ':type_fichier' => $extension,
                            ':taille_fichier' => $fileSize,
                            ':commentaire' => null,
                            ':masque_client' => 0, // Visible par les clients
                            ':created_by' => $userId
                        ]);

                        $pieceJointeId = $this->db->lastInsertId();

                        // Créer la liaison
                        $sql = "INSERT INTO liaisons_pieces_jointes (
                                    piece_jointe_id, type_liaison, entite_id
                                ) VALUES (
                                    :piece_jointe_id, 'intervention', :intervention_id
                                )";

                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':piece_jointe_id' => $pieceJointeId,
                            ':intervention_id' => $interventionId
                        ]);

                        $this->db->commit();
                        $uploadedFiles[] = $finalFileName;
                        
                    } catch (Exception $e) {
                        $this->db->rollBack();
                        $errors[] = "Erreur lors de l'enregistrement du fichier '$originalFileName': " . $e->getMessage();
                        // Supprimer le fichier uploadé si l'enregistrement en base a échoué
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                } else {
                    $errors[] = "Erreur lors du déplacement du fichier '$originalFileName'";
                }
            }

            // Préparer la réponse
            if (!empty($uploadedFiles)) {
                $message = count($uploadedFiles) . " fichier(s) uploadé(s) avec succès";
                if (!empty($errors)) {
                    $message .= ". " . count($errors) . " erreur(s) : " . implode(', ', $errors);
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'uploaded_files' => $uploadedFiles,
                    'errors' => $errors
                ]);
            } else {
                throw new Exception("Aucun fichier n'a pu être uploadé. " . implode(', ', $errors));
            }

        } catch (Exception $e) {
            custom_log("Erreur dans InterventionsClientController::addMultipleAttachments : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Supprimer une piece jointe
     */
    public function deleteAttachment($attachmentId) {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $userId = $_SESSION['user']['id'];
        
        // Verifier que la piece jointe appartient a l'utilisateur connecte
        $attachmentData = $this->model->getAttachmentById($attachmentId);
        if (!$attachmentData || $attachmentData['created_by'] != $userId) {
            $_SESSION['error'] = 'Vous n\'etes pas autorise a supprimer cette piece jointe.';
            header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromAttachment($attachmentId));
            exit;
        }

        $success = $this->model->deleteAttachment($attachmentId);

        if ($success) {
            $_SESSION['success'] = 'Piece jointe supprimee avec succes.';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression de la piece jointe.';
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromAttachment($attachmentId));
        exit;
    }

    /**
     * Obtenir l'ID de l'intervention a partir de l'ID de la piece jointe
     */
    private function getInterventionIdFromAttachment($attachmentId) {
        $attachment = $this->model->getAttachmentById($attachmentId);
        return $attachment ? $attachment['intervention_id'] : 0;
    }

    /**
     * Affiche le formulaire de création d'intervention pour les clients
     */
    public function add() {
        // Vérifier si l'utilisateur est connecté et est un client
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier la permission spécifique pour créer des interventions
        if (!hasPermission('client_add_intervention')) {
            $_SESSION['error'] = "Vous n'avez pas la permission de créer des interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer l'ID du client depuis la session
        $clientId = $_SESSION['user']['client_id'] ?? null;
        
        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupérer les localisations autorisées
        $userLocations = getUserLocations();
        
        // Si l'utilisateur n'a pas de localisations définies, utiliser le client_id par défaut
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'room_id' => null]];
        }

        // Récupérer les données nécessaires pour le formulaire
        $sites = $this->model->getSitesByLocations($userLocations);
        $contracts = $this->model->getContractsByClient($clientId);
        $contacts = $this->model->getContactsByClient($clientId);
        
        // Récupérer les statuts et priorités par défaut
        $statuses = $this->model->getAllStatuses();
        $priorities = $this->model->getAllPriorities();
        
        // Trouver les IDs par défaut
        $defaultStatusId = null;
        $defaultPriorityId = null;
        
        foreach ($statuses as $status) {
            if (strtolower($status['name']) === 'nouveau') {
                $defaultStatusId = $status['id'];
                break;
            }
        }
        
        foreach ($priorities as $priority) {
            if (strtolower($priority['name']) === 'normale') {
                $defaultPriorityId = $priority['id'];
                break;
            }
        }

        // Charger la vue
        require_once __DIR__ . '/../views/interventions_client/add.php';
    }

    /**
     * Traite la soumission du formulaire de création d'intervention
     */
    public function store() {
        // Vérifier si l'utilisateur est connecté et est un client
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier la permission spécifique pour créer des interventions
        if (!hasPermission('client_add_intervention')) {
            $_SESSION['error'] = "Vous n'avez pas la permission de créer des interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'interventions_client/add');
            exit;
        }

        // Récupérer l'ID du client depuis la session
        $clientId = $_SESSION['user']['client_id'] ?? null;
        
        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupérer les statuts et priorités pour définir les valeurs par défaut
        $statuses = $this->model->getAllStatuses();
        $priorities = $this->model->getAllPriorities();
        
        // Trouver les IDs par défaut
        $defaultStatusId = null;
        $defaultPriorityId = null;
        
        foreach ($statuses as $status) {
            if (strtolower($status['name']) === 'nouveau') {
                $defaultStatusId = $status['id'];
                break;
            }
        }
        
        foreach ($priorities as $priority) {
            if (strtolower($priority['name']) === 'normale') {
                $defaultPriorityId = $priority['id'];
                break;
            }
        }

        // Récupérer et valider les données du formulaire
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'demande_par' => null, // Champ supprimé du formulaire client
            'client_id' => $clientId,
            'site_id' => !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null,
            'room_id' => !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null,
            'contract_id' => !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null,
            'status_id' => !empty($_POST['status_id']) ? (int)$_POST['status_id'] : $defaultStatusId,
            'priority_id' => !empty($_POST['priority_id']) ? (int)$_POST['priority_id'] : $defaultPriorityId,
            'ref_client' => trim($_POST['ref_client'] ?? ''),
            'contact_client' => trim($_POST['contact_client'] ?? ''),
            'duration' => 0, // Durée par défaut pour les clients
            'type_id' => 1, // Type par défaut (à adapter selon votre logique)
            'technician_id' => null, // Pas de technicien assigné par défaut
            'date_planif' => null, // Pas de date planifiée par défaut
            'heure_planif' => null // Pas d'heure planifiée par défaut
        ];

        // Validation des champs obligatoires
        $errors = [];
        
        if (empty($data['title'])) {
            $errors[] = 'Le titre est obligatoire';
        }
        
        if (empty($data['description'])) {
            $errors[] = 'La description est obligatoire';
        }
        
        if (empty($data['status_id'])) {
            $errors[] = 'Le statut est obligatoire';
        }
        
        if (empty($data['priority_id'])) {
            $errors[] = 'La priorité est obligatoire';
        }

        // Validation de l'email si fourni
        if (!empty($data['contact_client']) && !filter_var($data['contact_client'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email du contact client n\'est pas valide';
        }

        // Si il y a des erreurs, rediriger vers le formulaire
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['form_data'] = $data; // Sauvegarder les données pour les réafficher
            header('Location: ' . BASE_URL . 'interventions_client/add');
            exit;
        }

        // Vérifier que l'utilisateur a accès aux localisations sélectionnées
        $userLocations = getUserLocations();
        $hasAccess = false;
        
        foreach ($userLocations as $location) {
            if ($location['client_id'] == $clientId) {
                if ($location['site_id'] === null || $location['site_id'] == $data['site_id']) {
                    if ($location['room_id'] === null || $location['room_id'] == $data['room_id']) {
                        $hasAccess = true;
                        break;
                    }
                }
            }
        }

        if (!$hasAccess) {
            $_SESSION['error'] = "Vous n'avez pas accès aux localisations sélectionnées";
            header('Location: ' . BASE_URL . 'interventions_client/add');
            exit;
        }

        // Log des données pour débogage
        custom_log("Données d'intervention à créer: " . json_encode($data), 'DEBUG');

        // Créer l'intervention
        $interventionId = $this->model->create($data);

        if ($interventionId) {
            custom_log("Intervention créée avec succès, ID: " . $interventionId, 'INFO');
            $_SESSION['success'] = 'Intervention créée avec succès';
            header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
        } else {
            custom_log("Échec de la création de l'intervention", 'ERROR');
            $_SESSION['error'] = 'Erreur lors de la création de l\'intervention';
            header('Location: ' . BASE_URL . 'interventions_client/add');
        }
        exit;
    }
} 