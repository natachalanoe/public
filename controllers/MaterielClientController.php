<?php
require_once __DIR__ . '/../models/MaterielClientModel.php';
require_once __DIR__ . '/../includes/functions.php';

class MaterielClientController {
    private $db;
    private $model;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->model = new MaterielClientModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur est connecté et a les permissions client
     */
    private function checkAccess() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier que l'utilisateur est un client
        if (!isClient()) {
            $_SESSION['error'] = "Accès réservé aux clients.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Vérifier que l'utilisateur a la permission de voir le matériel
        $user = $_SESSION['user'];
        if (isset($user['permissions']['rights']['client_view_materiel']) && 
            $user['permissions']['rights']['client_view_materiel'] === true) {
            // Permission OK
        } else {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour accéder au matériel.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }

    /**
     * Affiche la liste du matériel du client
     */
    public function index() {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        custom_log("MaterielClientController::index - userLocations: " . json_encode($userLocations), 'DEBUG');

        // Récupération des filtres
        $filters = [
            'site_id' => isset($_GET['site_id']) ? (int)$_GET['site_id'] : null,
            'salle_id' => isset($_GET['salle_id']) ? (int)$_GET['salle_id'] : null,
            'search' => $_GET['search'] ?? null
        ];

        try {
            // Récupération des données pour les filtres
            $clients = $this->model->getClientsByLocations($userLocations);
            
            // Initialiser les variables
            $sites = [];
            $salles = [];
            $materiel_list = [];
            $visibilites_champs = [];
            $pieces_jointes_count = [];

                    // Récupération des sites selon les localisations autorisées
        $sites = $this->model->getSitesByLocations($userLocations);
        

        


            // Récupération des salles selon le filtre site
            if ($filters['site_id']) {
                $salles = $this->model->getRoomsBySiteAndLocations($filters['site_id'], $userLocations);
            } else {
                // Si pas de site sélectionné, récupérer toutes les salles selon les localisations
                $salles = $this->model->getRoomsByLocations($userLocations);
            }

            // Récupération du matériel avec filtres et localisations autorisées
            $materiel_list = $this->model->getAllByLocations($userLocations, $filters);

            // Récupération des informations de visibilité des champs
            if (!empty($materiel_list)) {
                $materiel_ids = array_column($materiel_list, 'id');
                $visibilites_champs = $this->model->getVisibiliteChampsForMateriels($materiel_ids);
                
                // Récupération du nombre de pièces jointes pour chaque matériel
                foreach ($materiel_ids as $materiel_id) {
                    $pieces_jointes_count[$materiel_id] = $this->model->getPiecesJointesCount($materiel_id);
                }
            }

        } catch (Exception $e) {
            // En cas d'erreur, initialiser les variables avec des tableaux vides
            $clients = [];
            $sites = [];
            $salles = [];
            $materiel_list = [];
            $visibilites_champs = [];
            $pieces_jointes_count = [];
            
            // Log de l'erreur
            custom_log("Erreur lors du chargement du matériel client : " . $e->getMessage(), 'ERROR');
        }

        // Définir la page courante pour le menu
        $currentPage = 'materiel_client';
        $pageTitle = 'Mon Matériel';



        // Inclure la vue
        require_once __DIR__ . '/../views/materiel_client/index.php';
    }

    /**
     * Affiche le matériel d'une salle spécifique (vue compacte pour client)
     */
    public function salle($salleId) {
        error_log("DEBUG: MaterielClientController::salle() appelé avec salleId = $salleId");
        
        try {
            $this->checkAccess();
            error_log("DEBUG: checkAccess() OK");

            // Récupérer les localisations autorisées de l'utilisateur
            $userLocations = getUserLocations();
            error_log("DEBUG: userLocations = " . json_encode($userLocations));

            // Récupérer les informations de la salle avec vérification d'accès
            $salle = $this->model->getRoomByIdWithAccess($salleId, $userLocations);
            error_log("DEBUG: salle = " . json_encode($salle));
            
            if (!$salle) {
                error_log("DEBUG: Salle non trouvée, redirection");
                $_SESSION['error'] = "Salle non trouvée ou vous n'avez pas les permissions pour y accéder.";
                header('Location: ' . BASE_URL . 'materiel_client');
                exit;
            }

            // Récupérer le matériel de cette salle avec vérification d'accès
            $filters = ['salle_id' => $salleId];
            $materiel_list = $this->model->getAllByLocations($userLocations, $filters);
            error_log("DEBUG: materiel_list count = " . count($materiel_list));

            // Récupérer les informations de visibilité des champs
            $visibilites_champs = [];
            if (!empty($materiel_list)) {
                $materiel_ids = array_column($materiel_list, 'id');
                $visibilites_champs = $this->model->getVisibiliteChampsForMateriels($materiel_ids);
            }

            error_log("DEBUG: Chargement de la vue");
            $currentPage = 'materiel_client';
            $pageTitle = "Matériel - " . $salle['site_name'] . " - " . $salle['salle_name'];
            require_once __DIR__ . '/../views/materiel_client/salle.php';
            error_log("DEBUG: Vue chargée avec succès");
            
        } catch (Exception $e) {
            error_log("DEBUG: Erreur dans salle(): " . $e->getMessage());
            error_log("DEBUG: Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Affiche les détails d'un matériel
     */
    public function view($id) {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();

        try {
            // Récupérer le matériel avec vérification d'accès
            $materiel = $this->model->getByIdWithAccess($id, $userLocations);
            
            if (!$materiel) {
                $_SESSION['error'] = "Matériel introuvable ou vous n'avez pas les permissions pour y accéder.";
                header('Location: ' . BASE_URL . 'materiel_client');
                exit;
            }

            // Récupérer les informations de visibilité des champs
            $visibilites_champs = [];
            $visibilites = $this->model->getVisibiliteChampsForMateriels([$id]);
            if (isset($visibilites[$id])) {
                $visibilites_champs[$id] = $visibilites[$id];
            }

            // Récupérer les pièces jointes
            $attachments = $this->model->getPiecesJointesWithAccess($id, $userLocations);

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du matériel client : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la récupération du matériel";
            header('Location: ' . BASE_URL . 'materiel_client');
            exit;
        }

        $currentPage = 'materiel_client';
        $pageTitle = 'Détails du Matériel';

        require_once __DIR__ . '/../views/materiel_client/view.php';
    }

    /**
     * Récupère les sites selon les localisations autorisées (AJAX)
     */
    public function get_sites() {
        $this->checkAccess();

        $userLocations = getUserLocations();
        $sites = $this->model->getSitesByLocations($userLocations);

        header('Content-Type: application/json');
        echo json_encode($sites);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées (AJAX)
     */
    public function get_rooms() {
        $this->checkAccess();

        $siteId = $_GET['site_id'] ?? null;
        if (!$siteId) {
            echo json_encode(['error' => 'ID du site manquant']);
            return;
        }

        $userLocations = getUserLocations();
        $rooms = $this->model->getRoomsBySiteAndLocations($siteId, $userLocations);

        header('Content-Type: application/json');
        echo json_encode($rooms);
    }
} 