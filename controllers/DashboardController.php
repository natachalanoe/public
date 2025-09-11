<?php

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../includes/functions.php';

class DashboardController {
    /**
     * Affiche le tableau de bord avec les informations de session
     */
    public function index() {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Récupérer les informations de l'utilisateur
        $userInfo = $_SESSION['user'];
        
        // Utiliser les fonctions helper pour déterminer le type d'utilisateur
        if (isClient()) {
            $this->clientDashboard();
        } else {
            $this->staffDashboard();
        }
    }

    /**
     * Dashboard pour le personnel (admin, technicien)
     */
    private function staffDashboard() {
        // Vérifier que l'utilisateur est staff (sécurité)
        if (!isStaff()) {
            $_SESSION['error'] = 'Accès non autorisé. Vous devez être membre du personnel pour accéder à cette page.';
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }
        
        // Récupérer les permissions de l'utilisateur
        $permissions = [];
        
        // Si les permissions sont dans la session
        if (isset($_SESSION['user']['permissions'])) {
            // Si les permissions sont stockées avec la structure 'rights'
            if (isset($_SESSION['user']['permissions']['rights'])) {
                $permissions = $_SESSION['user']['permissions']['rights'];
            } else {
                // Sinon, utiliser directement les permissions
                $permissions = $_SESSION['user']['permissions'];
            }
        }
        
        // Inclure la vue du dashboard staff
        require_once VIEWS_PATH . '/dashboard/staff.php';
    }

    /**
     * Dashboard pour les clients
     */
    private function clientDashboard() {
        // Vérifier que l'utilisateur est client (sécurité)
        if (!isClient()) {
            $_SESSION['error'] = 'Accès non autorisé. Cette page est réservée aux clients.';
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
        
        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        
        // Si l'utilisateur n'a pas de localisations définies, utiliser le client_id par défaut
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'room_id' => null]];
        }
        
        // Récupérer les informations du client
        $config = Config::getInstance();
        $db = $config->getDb();
        
        try {
            // Récupérer les informations du client
            $stmt = $db->prepare("SELECT * FROM clients WHERE id = :client_id");
            $stmt->execute(['client_id' => $clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                $_SESSION['error'] = "Client non trouvé";
                header('Location: ' . BASE_URL . 'auth/logout');
                exit;
            }
            
            // Récupérer TOUS les sites du client
            $stmt = $db->prepare("
                SELECT s.*, s.client_id, COUNT(r.id) as room_count
                FROM sites s
                LEFT JOIN rooms r ON s.id = r.site_id AND r.status = 1
                WHERE s.client_id = :client_id AND s.status = 1
                GROUP BY s.id, s.client_id
                ORDER BY s.name
            ");
            $stmt->execute(['client_id' => $clientId]);
            $allSites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Pour chaque site, récupérer toutes ses salles
            foreach ($allSites as &$site) {
                $stmt = $db->prepare("
                    SELECT r.* 
                    FROM rooms r 
                    WHERE r.site_id = :site_id AND r.status = 1 
                    ORDER BY r.name
                ");
                $stmt->execute(['site_id' => $site['id']]);
                $site['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Marquer les sites et salles autorisés
            $sitesWithAccess = $this->markAuthorizedLocations($allSites, $userLocations);
            
        } catch (Exception $e) {
            custom_log("Erreur lors du chargement du dashboard client : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors du chargement des données";
            $sitesWithAccess = [];
        }
        
        // Inclure la vue du dashboard client
        require_once VIEWS_PATH . '/dashboard/client.php';
    }
    
    /**
     * Marque les sites et salles autorisés pour l'utilisateur
     * @param array $sites Tous les sites du client
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Les sites avec les informations d'accès
     */
    private function markAuthorizedLocations($sites, $userLocations) {
        $sitesWithAccess = [];
        
        foreach ($sites as $site) {
            $siteData = $site;
            $siteData['authorized'] = false;
            $siteData['rooms_authorized'] = [];
            
            // Vérifier si l'utilisateur a accès au site entier
            foreach ($userLocations as $location) {
                // Conversion explicite en entiers pour éviter les problèmes de type
                $locClientId = (int)$location['client_id'];
                $locSiteId = $location['site_id'] !== null ? (int)$location['site_id'] : null;
                $locRoomId = $location['room_id'] !== null ? (int)$location['room_id'] : null;
                $siteClientId = (int)$site['client_id'];
                $siteId = (int)$site['id'];
                
                if ($locClientId === $siteClientId) {
                    // Accès au client entier
                    if ($locSiteId === null && $locRoomId === null) {
                        $siteData['authorized'] = true;
                        // Toutes les salles sont autorisées
                        foreach ($site['rooms'] as $room) {
                            $siteData['rooms_authorized'][(int)$room['id']] = true;
                        }
                        break;
                    }
                    // Accès au site entier
                    elseif ($locSiteId === $siteId && $locRoomId === null) {
                        $siteData['authorized'] = true;
                        // Toutes les salles du site sont autorisées
                        foreach ($site['rooms'] as $room) {
                            $siteData['rooms_authorized'][(int)$room['id']] = true;
                        }
                        break;
                    }
                    // Accès à des salles spécifiques
                    elseif ($locSiteId === $siteId && $locRoomId !== null) {
                        $siteData['rooms_authorized'][$locRoomId] = true;
                        // Si l'utilisateur a accès à au moins une salle du site, le site est autorisé
                        $siteData['authorized'] = true;
                    }
                }
            }
            
            // Marquer les salles individuelles
            foreach ($siteData['rooms'] as &$room) {
                $roomId = (int)$room['id'];
                $room['authorized'] = isset($siteData['rooms_authorized'][$roomId]) && $siteData['rooms_authorized'][$roomId] === true;
            }
            
            $sitesWithAccess[] = $siteData;
        }
        
        return $sitesWithAccess;
    }
} 