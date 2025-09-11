<?php
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../includes/functions.php';

class SiteClientController {
    private $db;
    private $siteModel;
    private $roomModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
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
            $_SESSION['error'] = "Vous n'avez pas les permissions pour accéder à cette page.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }

    /**
     * Affiche la liste des sites et salles du client
     */
    public function index() {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur (formaté pour notre contrôleur)
        $userLocations = getUserLocationsFormatted();
        custom_log("SiteClientController::index - userLocations: " . json_encode($userLocations), 'DEBUG');

        try {
            // Récupération des sites selon les localisations autorisées
            $sites = $this->getSitesByLocations($userLocations);
            
            // Pour chaque site, récupérer les salles
            foreach ($sites as &$site) {
                $site['rooms'] = $this->getRoomsBySiteAndLocations($site['id'], $userLocations);
            }

        } catch (Exception $e) {
            // En cas d'erreur, initialiser les variables avec des tableaux vides
            $sites = [];
            
            // Log de l'erreur
            custom_log("Erreur lors du chargement des sites client : " . $e->getMessage(), 'ERROR');
        }

        // Définir la page courante pour le menu
        $currentPage = 'sites_client';
        $pageTitle = 'Mes Sites et Salles';

        // Inclure la vue
        require_once __DIR__ . '/../views/client_client/index.php';
    }

    /**
     * Affiche les détails d'un site spécifique
     */
    public function view($siteId) {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur (formaté pour notre contrôleur)
        $userLocations = getUserLocationsFormatted();

        try {
            // Vérifier que l'utilisateur a accès à ce site
            if (!$this->hasAccessToSite($siteId, $userLocations)) {
                $_SESSION['error'] = "Vous n'avez pas accès à ce site.";
                header('Location: ' . BASE_URL . 'sites_client');
                exit;
            }

            // Récupérer les détails du site
            $site = $this->siteModel->getSiteById($siteId);
            if (!$site) {
                $_SESSION['error'] = "Site non trouvé.";
                header('Location: ' . BASE_URL . 'sites_client');
                exit;
            }

            // Récupérer les salles du site
            $rooms = $this->getRoomsBySiteAndLocations($siteId, $userLocations);

        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors du chargement du site.";
            header('Location: ' . BASE_URL . 'sites_client');
            exit;
        }

        // Définir la page courante pour le menu
        $currentPage = 'sites_client';
        $pageTitle = 'Détails du site';

        // Inclure la vue
        require_once __DIR__ . '/../views/client_client/view.php';
    }

    /**
     * Récupère les sites selon les localisations autorisées
     */
    private function getSitesByLocations($userLocations) {
        if (empty($userLocations)) {
            return [];
        }

        // Convertir le format indexé en format simple pour buildLocationWhereClause
        $simpleLocations = [];
        foreach ($userLocations as $clientId => $locations) {
            foreach ($locations as $location) {
                $simpleLocations[] = $location;
            }
        }

        // Construire la clause WHERE pour les sites
        $siteConditions = [];
        foreach ($simpleLocations as $location) {
            $clientId = $location['client_id'];
            $locSiteId = $location['site_id'];
            $roomId = $location['room_id'];
            
            if ($roomId !== null) {
                // Accès spécifique à une salle - récupérer le site de cette salle
                $siteConditions[] = "s.id = (SELECT site_id FROM rooms WHERE id = {$roomId})";
            } elseif ($locSiteId !== null) {
                // Accès à un site spécifique
                $siteConditions[] = "s.id = {$locSiteId}";
            } else {
                // Accès au client entier - récupérer TOUS les sites de ce client
                $siteConditions[] = "s.client_id = {$clientId}";
            }
        }
        
        $locationWhere = "(" . implode(" OR ", $siteConditions) . ")";

        $query = "
            SELECT DISTINCT s.*, 
                   c.name as client_name,
                   pc.first_name, pc.last_name, pc.phone1, pc.email
            FROM sites s
            JOIN clients c ON s.client_id = c.id
            LEFT JOIN contacts pc ON s.main_contact_id = pc.id
            WHERE {$locationWhere}
            ORDER BY c.name, s.name
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organiser les données du contact principal
        foreach ($sites as &$site) {
            if ($site['first_name'] && $site['last_name']) {
                $site['primary_contact'] = [
                    'first_name' => $site['first_name'],
                    'last_name' => $site['last_name'],
                    'phone1' => $site['phone1'],
                    'email' => $site['email']
                ];
            } else {
                $site['primary_contact'] = null;
            }
        }

        return $sites;
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées
     */
    private function getRoomsBySiteAndLocations($siteId, $userLocations) {
        if (empty($userLocations)) {
            return [];
        }

        // Convertir le format indexé en format simple pour buildLocationWhereClause
        $simpleLocations = [];
        foreach ($userLocations as $clientId => $locations) {
            foreach ($locations as $location) {
                $simpleLocations[] = $location;
            }
        }

        // Construire la clause WHERE pour les salles
        $roomConditions = [];
        foreach ($simpleLocations as $location) {
            $clientId = $location['client_id'];
            $locSiteId = $location['site_id'];
            $roomId = $location['room_id'];
            
            if ($roomId !== null) {
                // Accès spécifique à une salle
                $roomConditions[] = "r.id = {$roomId}";
            } elseif ($locSiteId !== null) {
                // Accès à un site spécifique - récupérer TOUTES les salles de ce site
                $roomConditions[] = "r.site_id = {$locSiteId}";
            } else {
                // Accès au client entier - récupérer toutes les salles des sites de ce client
                $roomConditions[] = "r.site_id IN (SELECT id FROM sites WHERE client_id = {$clientId})";
            }
        }
        
        $locationWhere = "(" . implode(" OR ", $roomConditions) . ")";

        $query = "
            SELECT r.*, 
                   c.first_name, c.last_name, c.phone1, c.email
            FROM rooms r
            LEFT JOIN contacts c ON r.main_contact_id = c.id
            JOIN sites s ON r.site_id = s.id
            WHERE r.site_id = ? AND {$locationWhere}
            ORDER BY r.name
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$siteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si l'utilisateur a accès à un site spécifique
     */
    private function hasAccessToSite($siteId, $userLocations) {
        if (empty($userLocations)) {
            return false;
        }

        // Convertir le format indexé en format simple pour buildLocationWhereClause
        $simpleLocations = [];
        foreach ($userLocations as $clientId => $locations) {
            foreach ($locations as $location) {
                $simpleLocations[] = $location;
            }
        }

        // Construire la clause WHERE pour vérifier l'accès au site
        $siteConditions = [];
        foreach ($simpleLocations as $location) {
            $clientId = $location['client_id'];
            $locSiteId = $location['site_id'];
            $roomId = $location['room_id'];
            
            if ($roomId !== null) {
                // Accès spécifique à une salle - vérifier que le site de la salle correspond
                $siteConditions[] = "s.id = (SELECT site_id FROM rooms WHERE id = {$roomId})";
            } elseif ($locSiteId !== null) {
                // Accès au site entier
                $siteConditions[] = "s.id = {$locSiteId}";
            } else {
                // Accès au client entier - vérifier que le site appartient à ce client
                $siteConditions[] = "s.client_id = {$clientId}";
            }
        }
        
        $locationWhere = "(" . implode(" OR ", $siteConditions) . ")";

        $query = "
            SELECT COUNT(*) as count
            FROM sites s
            WHERE s.id = ? AND {$locationWhere}
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$siteId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }
}

