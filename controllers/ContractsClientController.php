<?php
require_once __DIR__ . '/../models/ContractsClientModel.php';
require_once __DIR__ . '/../includes/functions.php';

class ContractsClientController {
    private $db;
    private $model;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->model = new ContractsClientModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur est connecté et a les permissions client
     */
    private function checkAccess() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier que l'utilisateur a la permission de voir les contrats
        if (!hasPermission('client_view_contracts')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour accéder aux contrats.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }

    /**
     * Affiche la liste des contrats du client
     */
    public function index() {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'contract_type_id' => $_GET['contract_type_id'] ?? null,
            'status' => $_GET['status'] ?? 'actif', // Par défaut: contrats actifs
            'search' => $_GET['search'] ?? null
        ];

        // Récupérer les contrats filtrés selon les localisations autorisées
        $contracts = $this->model->getAllByLocations($userLocations, $filters);

        // Récupérer les données pour les filtres
        $clients = $this->model->getClientsByLocations($userLocations);
        $sites = [];
        if ($filters['client_id']) {
            $sites = $this->model->getSitesByClientAndLocations($filters['client_id'], $userLocations);
        }
        $rooms = [];
        if ($filters['site_id']) {
            $rooms = $this->model->getRoomsBySiteAndLocations($filters['site_id'], $userLocations);
        }
        $contractTypes = $this->model->getContractTypes();

        // Statistiques
        $stats = $this->model->getStatsByLocations($userLocations);

        // Charger la vue
        require_once __DIR__ . '/../views/contracts_client/index.php';
    }

    /**
     * Affiche le détail d'un contrat
     */
    public function view($id) {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();

        // Récupérer le contrat avec vérification d'accès
        $contract = $this->model->getByIdWithAccess($id, $userLocations);

        if (!$contract) {
            $_SESSION['error'] = "Contrat introuvable ou vous n'avez pas les permissions pour y accéder.";
            header('Location: ' . BASE_URL . 'contracts_client');
            exit;
        }

        // Récupérer les interventions liées à ce contrat
        $interventions = $this->model->getInterventionsByContractAndLocations($id, $userLocations);

        // Récupérer les pièces jointes du contrat
        $attachments = $this->model->getPiecesJointesWithAccess($id, $userLocations);

        // Charger la vue
        require_once __DIR__ . '/../views/contracts_client/view.php';
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées (AJAX)
     */
    public function getRoomsBySiteAndLocations() {
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
        $rooms = $this->model->getRoomsBySiteAndLocations($siteId, $userLocations);

        header('Content-Type: application/json');
        echo json_encode($rooms);
    }
} 