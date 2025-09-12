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
            
            // Récupérer les contrats ticket du client
            $ticketContracts = $this->getTicketContracts($db, $clientId);
            
            // Debug des permissions de l'utilisateur
            $this->debugUserPermissions();
            
            // Récupérer les interventions ouvertes si l'utilisateur a la permission
            $openInterventions = [];
            if (hasPermission('client_view_interventions')) {
                custom_log("DEBUG - Utilisateur a la permission client_view_interventions", 'DEBUG');
                $openInterventions = $this->getOpenInterventions($db, $clientId, $userLocations);
                custom_log("DEBUG - Nombre d'interventions ouvertes trouvées : " . count($openInterventions), 'DEBUG');
            } else {
                custom_log("DEBUG - Utilisateur n'a PAS la permission client_view_interventions", 'DEBUG');
            }
            
        } catch (Exception $e) {
            custom_log("Erreur lors du chargement du dashboard client : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors du chargement des données";
            $sitesWithAccess = [];
            $ticketContracts = [];
            $openInterventions = [];
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
    
    /**
     * Récupère les contrats ticket du client avec leurs informations
     * @param PDO $db Connexion à la base de données
     * @param int $clientId ID du client
     * @return array Liste des contrats ticket
     */
    private function getTicketContracts($db, $clientId) {
        try {
            // Récupérer les contrats avec tickets
            $stmt = $db->prepare("
                SELECT c.*, ct.name as contract_type_name
                FROM contracts c
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                WHERE c.client_id = :client_id 
                AND c.status = 'actif' 
                AND c.tickets_number > 0
                ORDER BY c.end_date ASC
            ");
            $stmt->execute(['client_id' => $clientId]);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Pour chaque contrat, récupérer la date du dernier achat
            foreach ($contracts as &$contract) {
                $contract['last_purchase_date'] = $this->getLastTicketPurchaseDate($db, $contract['id']);
                
                // Debug : afficher l'historique complet pour ce contrat
                $this->debugContractHistory($db, $contract['id']);
            }
            
            return $contracts;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des contrats ticket : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Récupère la date du dernier achat de tickets pour un contrat
     * @param PDO $db Connexion à la base de données
     * @param int $contractId ID du contrat
     * @return string|null Date du dernier achat ou null
     */
    private function getLastTicketPurchaseDate($db, $contractId) {
        try {
            // D'abord, récupérer toutes les entrées liées aux tickets pour debug
            $debugStmt = $db->prepare("
                SELECT field_name, description, created_at
                FROM contract_history
                WHERE contract_id = :contract_id 
                AND (
                    field_name LIKE '%tickets%' 
                    OR field_name LIKE '%Tickets%'
                    OR description LIKE '%tickets%'
                    OR description LIKE '%Tickets%'
                )
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $debugStmt->execute(['contract_id' => $contractId]);
            $debugResults = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log pour debug
            custom_log("DEBUG - Historique tickets pour contrat $contractId : " . json_encode($debugResults), 'DEBUG');
            
            // Maintenant chercher spécifiquement les ajouts
            $stmt = $db->prepare("
                SELECT created_at
                FROM contract_history
                WHERE contract_id = :contract_id 
                AND (
                    (field_name = 'Tickets initiaux' AND description LIKE '%Ajout de%tickets initiaux%')
                    OR (field_name = 'Tickets restants' AND description LIKE '%Ajout de%tickets restants%')
                    OR (field_name = 'Nombre de tickets' AND description LIKE '%Tickets initiaux définis%')
                    OR (description LIKE '%Ajout de%tickets%' AND description NOT LIKE '%Déduction%')
                )
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute(['contract_id' => $contractId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['created_at'] : null;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération de la date du dernier achat : " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Récupère les interventions ouvertes du client
     * @param PDO $db Connexion à la base de données
     * @param int $clientId ID du client
     * @param array $userLocations Localisations autorisées de l'utilisateur
     * @return array Liste des interventions ouvertes
     */
    private function getOpenInterventions($db, $clientId, $userLocations) {
        try {
            custom_log("DEBUG - getOpenInterventions appelée pour client_id: $clientId", 'DEBUG');
            
            // Récupérer les interventions ouvertes
            $stmt = $db->prepare("
                SELECT i.*, 
                       s.name as site_name,
                       r.name as room_name,
                       its.name as status_name,
                       its.color as status_color,
                       it.name as type_name,
                       ip.name as priority_name,
                       ip.color as priority_color,
                       CONCAT(u.first_name, ' ', u.last_name) as technician_name
                FROM interventions i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN intervention_statuses its ON i.status_id = its.id
                LEFT JOIN intervention_types it ON i.type_id = it.id
                LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
                LEFT JOIN users u ON i.technician_id = u.id
                WHERE i.client_id = :client_id 
                AND its.name NOT IN ('Fermé', 'Annulé', 'Terminé')
                ORDER BY i.created_at DESC
                LIMIT 10
            ");
            $stmt->execute(['client_id' => $clientId]);
            $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            custom_log("DEBUG - Interventions trouvées avant filtrage : " . count($interventions), 'DEBUG');
            
            // Filtrer selon les autorisations de l'utilisateur
            $authorizedInterventions = [];
            foreach ($interventions as $intervention) {
                if ($this->isInterventionAuthorized($intervention, $userLocations)) {
                    $authorizedInterventions[] = $intervention;
                }
            }
            
            custom_log("DEBUG - Interventions autorisées après filtrage : " . count($authorizedInterventions), 'DEBUG');
            
            return $authorizedInterventions;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des interventions ouvertes : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Vérifie si une intervention est autorisée pour l'utilisateur
     * @param array $intervention Données de l'intervention
     * @param array $userLocations Localisations autorisées de l'utilisateur
     * @return bool true si autorisée
     */
    private function isInterventionAuthorized($intervention, $userLocations) {
        foreach ($userLocations as $location) {
            $locClientId = (int)$location['client_id'];
            $locSiteId = $location['site_id'] !== null ? (int)$location['site_id'] : null;
            $locRoomId = $location['room_id'] !== null ? (int)$location['room_id'] : null;
            
            // Accès au client entier
            if ($locSiteId === null && $locRoomId === null) {
                return true;
            }
            
            // Accès au site entier
            if ($locSiteId === (int)$intervention['site_id'] && $locRoomId === null) {
                return true;
            }
            
            // Accès à la salle spécifique
            if ($locSiteId === (int)$intervention['site_id'] && $locRoomId === (int)$intervention['room_id']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Méthode de debug pour afficher l'historique complet d'un contrat
     * @param PDO $db Connexion à la base de données
     * @param int $contractId ID du contrat
     */
    private function debugContractHistory($db, $contractId) {
        try {
            $stmt = $db->prepare("
                SELECT field_name, description, created_at, old_value, new_value
                FROM contract_history
                WHERE contract_id = :contract_id 
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute(['contract_id' => $contractId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            custom_log("DEBUG - Historique complet pour contrat $contractId : " . json_encode($history), 'DEBUG');
        } catch (Exception $e) {
            custom_log("Erreur lors du debug de l'historique : " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Méthode de debug pour afficher les permissions de l'utilisateur
     */
    private function debugUserPermissions() {
        try {
            $user = $_SESSION['user'] ?? null;
            if ($user) {
                custom_log("DEBUG - Utilisateur connecté : " . json_encode([
                    'id' => $user['id'] ?? 'N/A',
                    'user_type' => $user['user_type'] ?? 'N/A',
                    'client_id' => $user['client_id'] ?? 'N/A',
                    'permissions' => $user['permissions'] ?? 'N/A'
                ]), 'DEBUG');
                
                // Test de la permission spécifique
                $hasPermission = hasPermission('client_view_interventions');
                custom_log("DEBUG - hasPermission('client_view_interventions') = " . ($hasPermission ? 'true' : 'false'), 'DEBUG');
            } else {
                custom_log("DEBUG - Aucun utilisateur connecté", 'DEBUG');
            }
        } catch (Exception $e) {
            custom_log("Erreur lors du debug des permissions : " . $e->getMessage(), 'ERROR');
        }
    }
} 