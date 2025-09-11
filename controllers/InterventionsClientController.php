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

        // Récupérer l'intervention
        $intervention = $this->model->getByIdWithAccess($id, $userLocations);
        
        if (!$intervention) {
            $_SESSION['error'] = "Intervention non trouvée ou non autorisée";
            header('Location: ' . BASE_URL . 'interventions_client');
            exit;
        }

        // Vérifier que l'utilisateur a accès à cette intervention
        $hasAccess = false;
        foreach ($userLocations as $location) {
            if ($location['client_id'] == $intervention['client_id']) {
                if ($location['site_id'] === null || $location['site_id'] == $intervention['site_id']) {
                    if ($location['room_id'] === null || $location['room_id'] == $intervention['room_id']) {
                        $hasAccess = true;
                        break;
                    }
                }
            }
        }

        if (!$hasAccess) {
            $_SESSION['error'] = "Vous n'avez pas accès à cette intervention";
            header('Location: ' . BASE_URL . 'interventions_client');
            exit;
        }

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
} 