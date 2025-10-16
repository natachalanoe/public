<?php
/**
 * Fichier de fonctions utilitaires
 */

/**
 * Formate une taille de fichier en octets vers une unité lisible
 * @param int $bytes Taille en octets
 * @param int $precision Nombre de décimales
 * @return string Taille formatée
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Retourne la classe CSS de l'icône Bootstrap selon le type de fichier
 * @param string $fileType Extension du fichier
 * @return string Classe CSS de l'icône
 */
function getFileIcon($fileType) {
    if (empty($fileType)) {
        return 'bi bi-file';
    }
    
    $type = strtolower($fileType);
    
    if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
        return 'bi bi-file-image';
    }
    if ($type === 'pdf') {
        return 'bi bi-file-pdf';
    }
    if (in_array($type, ['doc', 'docx'])) {
        return 'bi bi-file-word';
    }
    if (in_array($type, ['xls', 'xlsx'])) {
        return 'bi bi-file-excel';
    }
    if (in_array($type, ['zip', 'rar', '7z'])) {
        return 'bi bi-file-zip';
    }
    if ($type === 'txt') {
        return 'bi bi-file-text';
    }
    
    return 'bi bi-file';
}

/**
 * Vérifie si l'utilisateur est connecté
 * Redirige vers la page de connexion si non connecté
 */
function checkLogin() {
    if (!isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . 'auth/login');
        exit();
    }
    return true;
}

/**
 * Récupère le type d'utilisateur actuel
 * @return string|null Le type d'utilisateur ou null
 */
function getUserType() {
    return $_SESSION['user']['user_type'] ?? null;
}

/**
 * Récupère le groupe d'utilisateur actuel
 * @return string|null Le groupe d'utilisateur ou null
 */
function getUserGroup() {
    return $_SESSION['user']['user_group'] ?? null;
}

/**
 * Vérifie si l'utilisateur est d'un type spécifique
 * @param string $type Le type à vérifier
 * @return bool true si l'utilisateur est du type spécifié
 */
function isUserType($type) {
    return getUserType() === $type;
}

/**
 * Vérifie si l'utilisateur est d'un groupe spécifique
 * @param string $group Le groupe à vérifier
 * @return bool true si l'utilisateur est du groupe spécifié
 */
function isUserGroup($group) {
    return getUserGroup() === $group;
}

/**
 * Vérifie si l'utilisateur est administrateur
 * @return bool true si l'utilisateur est administrateur
 */
function isAdmin() {
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    
    // Admin = case à cocher is_admin uniquement
    return isset($user['is_admin']) && $user['is_admin'];
}

/**
 * Vérifie si l'utilisateur est client (groupe Externe)
 * @return bool true si l'utilisateur est client
 */
function isClient() {
    return isUserGroup('Externe');
}

// Autres fonctions de rôles supprimées - le système utilise maintenant groupe + permissions + admin

/**
 * Vérifie si l'utilisateur est staff (membre du personnel)
 * @return bool true si l'utilisateur fait partie du staff
 */
function isStaff() {
    // Staff = groupe Staff uniquement
    return isUserGroup('Staff');
}

/**
 * Vérifie si l'utilisateur a une permission spécifique
 * @param string $permission Le nom de la permission
 * @return bool true si l'utilisateur a la permission
 */
function hasPermission($permission) {
    $user = $_SESSION['user'] ?? null;
    
    if (!$user) return false;
    
    // Administrateur a toutes les permissions
    if (isAdmin()) return true;
    
    // Vérifier les permissions spécifiques
    if (isset($user['permissions']) && is_array($user['permissions'])) {
        if (isset($user['permissions']['rights']) && is_array($user['permissions']['rights'])) {
            return isset($user['permissions']['rights'][$permission]) && 
                   $user['permissions']['rights'][$permission] === true;
        } else {
            return isset($user['permissions'][$permission]) && 
                   $user['permissions'][$permission] === true;
        }
    }
    
    return false;
}



/**
 * Vérifie si l'utilisateur peut voir les interventions (accès en lecture)
 * @return bool true si l'utilisateur peut voir les interventions
 */
function canViewInterventions() {
    // Tous les staff peuvent voir les interventions (lecture seule)
    return isStaff();
}

/**
 * Vérifie si l'utilisateur peut créer/modifier des interventions
 * @return bool true si l'utilisateur peut créer/modifier des interventions
 */
function canModifyInterventions() {
    // Staff + permission tech_manage_interventions
    return isStaff() && hasPermission('tech_manage_interventions');
}

/**
 * Vérifie si l'utilisateur peut supprimer des interventions
 * @return bool true si l'utilisateur peut supprimer des interventions
 */
function canDeleteInterventions() {
    // Staff + admin uniquement
    return isStaff() && isAdmin();
}

/**
 * Vérifie si l'utilisateur peut modifier le matériel
 * @return bool true si l'utilisateur peut modifier le matériel
 */
function canModifyMateriel() {
    // Staff + permission tech_manage_documentation
    return isStaff() && hasPermission('tech_manage_documentation');
}

/**
 * Vérifie si un utilisateur client peut modifier le matériel
 * @return bool true si l'utilisateur client peut modifier le matériel
 */
function canModifyMaterielClient() {
    if (!isClient()) {
        return false;
    }
    
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    
    // Vérifier la permission client_modify_materiel
    if (isset($user['permissions']['rights']['client_modify_materiel']) && 
        $user['permissions']['rights']['client_modify_materiel'] === true) {
        return true;
    }
    
    return false;
}

/**
 * Vérifie si l'utilisateur peut modifier les clients
 * @return bool true si l'utilisateur a les droits de modification des clients
 */
function canModifyClients() {
    return hasPermission('tech_manage_clients');
}

/**
 * Vérifie si l'utilisateur peut gérer les contrats
 * @return bool true si l'utilisateur a les droits de gestion des contrats
 */
function canManageContracts() {
    return hasPermission('tech_manage_contrats');
}

/**
 * Vérifie si l'utilisateur peut supprimer des éléments
 * @return bool true si l'utilisateur peut supprimer
 */
function canDelete() {
    return isAdmin();
}

/**
 * Vérifie si l'utilisateur a accès à une page spécifique
 * @param string $pageType Le type de page ('admin', 'staff', 'externe', 'all')
 * @return bool true si l'utilisateur a accès
 */
function hasAccess($pageType = 'all') {
    switch ($pageType) {
        case 'admin':
            return isAdmin();
        case 'staff':
            return isStaff();
        case 'externe':
            return isUserGroup('Externe');
        case 'all':
        default:
            return true;
    }
}

/**
 * Vérifie l'accès et redirige si nécessaire
 * @param string $pageType Le type de page requis
 * @param string $redirectUrl URL de redirection si pas d'accès
 */
function checkAccess($pageType = 'all', $redirectUrl = null) {
    if (!checkLogin()) {
        return; // checkLogin() gère déjà la redirection
    }
    
    if (!hasAccess($pageType)) {
        $redirectUrl = $redirectUrl ?: BASE_URL . 'auth/login';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Vérifie que l'utilisateur est staff (groupe Staff = group_id = 1)
 * Redirige vers le dashboard si ce n'est pas le cas
 * @param array $exceptions Liste des routes autorisées pour les non-staff
 */
function checkStaffAccess($exceptions = []) {
    if (!checkLogin()) {
        return; // checkLogin() gère déjà la redirection
    }
    
    // Vérifier si l'utilisateur est staff
    if (!isStaff()) {
        $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour accéder à cette page.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur est client (groupe Externe = group_id = 2)
 * Redirige vers le dashboard si ce n'est pas le cas
 * @param array $exceptions Liste des routes autorisées pour les non-clients
 */
function checkClientAccess($exceptions = []) {
    if (!checkLogin()) {
        return; // checkLogin() gère déjà la redirection
    }
    
    // Vérifier si l'utilisateur est client
    if (!isClient()) {
        $_SESSION['error'] = "Cette page est réservée aux clients.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur a la permission de gérer les contrats
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkContractManagementAccess() {
    checkStaffAccess();
    
    if (!canManageContracts()) {
        $_SESSION['error'] = "Vous n'avez pas les permissions pour gérer les contrats.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur a la permission de gérer les clients
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkClientManagementAccess() {
    checkStaffAccess();
    
    if (!canModifyClients()) {
        $_SESSION['error'] = "Vous n'avez pas les permissions pour gérer les clients.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie que l'utilisateur a la permission de gérer les interventions
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkInterventionManagementAccess() {
    // Vérifier d'abord l'accès en lecture
    if (!canViewInterventions()) {
        $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour accéder à cette page.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
    
    // Si l'utilisateur n'a pas les droits de modification, on le laisse en lecture seule
    // (pas de redirection, juste pas de boutons de modification)
}

/**
 * Vérifie que l'utilisateur a la permission de gérer la documentation
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkDocumentationManagementAccess() {
    checkStaffAccess();
    
    if (!hasPermission('tech_manage_documentation')) {
        $_SESSION['error'] = "Vous n'avez pas les permissions pour gérer la documentation.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Vérifie si l'utilisateur peut supprimer la documentation
 * @return bool true si l'utilisateur peut supprimer la documentation
 */
function canDeleteDocumentation() {
    // Administrateur a toutes les permissions
    if (isAdmin()) return true;
    
    // Staff + permission tech_delete_documentation
    return isStaff() && hasPermission('tech_delete_documentation');
}

/**
 * Vérifie que l'utilisateur a la permission de supprimer la documentation
 * Redirige vers le dashboard si ce n'est pas le cas
 */
function checkDocumentationDeleteAccess() {
    checkStaffAccess();
    
    if (!canDeleteDocumentation()) {
        $_SESSION['error'] = "Vous n'avez pas les permissions pour supprimer la documentation.";
        header('Location: ' . BASE_URL . 'dashboard');
        exit();
    }
}

/**
 * Récupère l'ID de l'utilisateur actuel
 * @return int|null L'ID de l'utilisateur ou null
 */
function getUserId() {
    return $_SESSION['user']['id'] ?? null;
}

/**
 * Échappe les caractères spéciaux pour l'affichage sécurisé
 * @param string $text Texte à échapper
 * @return string Texte échappé
 */
function h($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Formate une date pour l'affichage
 * @param string $date La date à formater
 * @return string La date formatée
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Formate un montant pour l'affichage
 * @param float $amount Le montant à formater
 * @return string Le montant formaté
 */
function formatAmount($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}

/**
 * Définit les variables de page pour le menu
 * @param string $title Le titre de la page
 * @param string $currentPage Le nom de la page courante
 */
function setPageVariables($title, $currentPage = 'dashboard') {
   global $pageTitle, $currentPageName;
   $pageTitle = $title;
   $currentPageName = $currentPage;
}

/**
 * Vérifie si une page est active dans le menu
 * @param string $pageName Le nom de la page à vérifier
 * @return string 'active' si la page est active, sinon chaîne vide
 */
function isActivePage($pageName) {
   global $currentPageName;
   return ($currentPageName === $pageName) ? 'active' : '';
}

/**
 * Récupère l'année courante
 * @return string L'année courante
 */
function getCurrentYear() {
   return date('Y');
}

/**
 * Vérifie si l'utilisateur a accès à une localisation spécifique
 * @param int $clientId ID du client
 * @param int|null $siteId ID du site (optionnel)
 * @param int|null $roomId ID de la salle (optionnel)
 * @return bool true si l'utilisateur a accès
 */
function hasLocationAccess($clientId, $siteId = null, $roomId = null) {
    $user = $_SESSION['user'] ?? null;
    
    if (!$user) return false;
    
    // Les administrateurs ont accès à tout
    if (isAdmin()) return true;
    
    // Vérifier les localisations de l'utilisateur
    if (isset($user['permissions']) && is_array($user['permissions'])) {
        $locations = $user['permissions']['locations'] ?? [];
        
        foreach ($locations as $location) {
            if ($location['client_id'] == $clientId) {
                if ($siteId === null) {
                    return true; // Accès au client entier
                }
                if ($location['site_id'] == $siteId) {
                    if ($roomId === null) {
                        return true; // Accès au site entier
                    }
                    if ($location['room_id'] == $roomId) {
                        return true; // Accès à la salle spécifique
                    }
                }
            }
        }
    }
    
    return false;
}

/**
 * Récupère les localisations autorisées de l'utilisateur
 * @return array Liste des localisations (format original pour buildLocationWhereClause)
 */
function getUserLocations() {
    $user = $_SESSION['user'] ?? null;
    if (!$user) return [];

    // Toujours charger depuis la base pour éviter les permissions de session obsolètes
    global $db;
    try {
        $stmt = $db->prepare(
            "SELECT client_id, site_id, room_id FROM user_locations WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $user['id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $locations ?: [];
    } catch (Exception $e) {
        custom_log("Erreur lors du chargement des localisations : " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Récupère les localisations autorisées de l'utilisateur formatées pour les contrôleurs
 * @return array Liste des localisations indexée par client_id
 */
function getUserLocationsFormatted() {
    $user = $_SESSION['user'] ?? null;
    if (!$user) return [];

    // Toujours charger depuis la base pour éviter les permissions de session obsolètes
    global $db;
    try {
        $stmt = $db->prepare(
            "SELECT client_id, site_id, room_id FROM user_locations WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $user['id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedLocations = [];
        foreach ($locations as $location) {
            $clientId = $location['client_id'];
            if (!isset($formattedLocations[$clientId])) {
                $formattedLocations[$clientId] = [];
            }
            $formattedLocations[$clientId][] = $location;
        }

        return $formattedLocations;
    } catch (Exception $e) {
        custom_log("Erreur lors du chargement des localisations : " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Vérifie si l'utilisateur peut voir les données d'un client spécifique
 * @param int $clientId ID du client
 * @return bool true si l'utilisateur peut voir les données
 */
function canViewClientData($clientId) {
    // Les staff peuvent voir toutes les données
    if (isStaff()) return true;
    
    // Les clients ne peuvent voir que leurs propres données
    if (isClient()) {
        $userClientId = $_SESSION['user']['client_id'] ?? null;
        return $userClientId == $clientId;
    }
    
    return false;
}

/**
 * Construit une clause WHERE pour filtrer selon les localisations autorisées
 * VERSION SÉCURISÉE - Empêche tout contournement par les JOINs
 * @param array $userLocations Les localisations de l'utilisateur
 * @param string $clientColumn Nom de la colonne client
 * @param string $siteColumn Nom de la colonne site
 * @param string $roomColumn Nom de la colonne room
 * @return string Clause WHERE
 */
function buildLocationWhereClause($userLocations, $clientColumn, $siteColumn, $roomColumn) {
    $conditions = [];
    
    foreach ($userLocations as $location) {
        $clientId = $location['client_id'];
        $siteId = $location['site_id'];
        $roomId = $location['room_id'];
        
        if ($roomId !== null) {
            // Accès spécifique à une salle - VÉRIFICATION STRICTE
            $conditions[] = "({$clientColumn} = {$clientId})";
        } elseif ($siteId !== null) {
            // Accès à un site entier - VÉRIFICATION STRICTE
            $conditions[] = "({$clientColumn} = {$clientId})";
        } else {
            // Accès au client entier - VÉRIFICATION STRICTE
            $conditions[] = "({$clientColumn} = {$clientId})";
        }
    }
    
    return empty($conditions) ? "1=0" : "(" . implode(" OR ", $conditions) . ")";
}

function canImportMateriel() {
    return isAdmin() || hasPermission('tech_manage_documentation');
}

/**
 * Vérifie si un utilisateur client peut modifier ses propres informations
 * @return bool true si l'utilisateur client peut modifier ses informations
 */
function canModifyOwnInfo() {
    if (!isClient()) {
        return false;
    }
    
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    
    // Vérifier la permission client_modify_info
    if (isset($user['permissions']['rights']['client_modify_info']) && 
        $user['permissions']['rights']['client_modify_info'] === true) {
        return true;
    }
    
    return false;
}

/**
 * Vérifie si un utilisateur client peut gérer les contacts de sa localisation
 * @return bool true si l'utilisateur client peut gérer les contacts
 */
function canManageOwnContacts() {
    if (!isClient()) {
        return false;
    }
    
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    
    // Vérifier la permission client_manage_contacts
    if (isset($user['permissions']['rights']['client_manage_contacts']) && 
        $user['permissions']['rights']['client_manage_contacts'] === true) {
        return true;
    }
    
    return false;
}

/**
 * Formate une date pour l'affichage au format français
 * @param string|null $date La date au format MySQL (AAAA-MM-JJ) ou null
 * @return string La date formatée (JJ/MM/AAAA) ou chaîne vide si null
 */
function formatDateFrench($date) {
    if (empty($date)) {
        return '';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format('d/m/Y');
    } catch (Exception $e) {
        return $date; // Retourner la date originale si erreur de parsing
    }
}

/**
 * Récupère la classe CSS de l'icône paramétrée pour une action donnée
 * @param string $iconKey La clé de l'icône (ex: 'view', 'edit', 'delete', ...)
 * @param string $defaultIcon Classe CSS par défaut si non trouvée
 * @return string
 */
function getIcon($iconKey, $defaultIcon = 'bi bi-eye') {
    global $db;
    try {
        $sql = "SELECT icon_class FROM settings_icons WHERE icon_key = ? AND is_active = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$iconKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['icon_class'] : $defaultIcon;
    } catch (Exception $e) {
        return $defaultIcon;
    }
}

/**
 * Fonction helper pour sécuriser l'affichage des chaînes avec htmlspecialchars
 * Évite les erreurs de dépréciation avec les valeurs null
 * @param mixed $value La valeur à afficher
 * @param string $default La valeur par défaut si null ou vide
 * @return string La valeur sécurisée pour l'affichage
 */
function safeHtml($value, $default = '') {
    if ($value === null || $value === '') {
        return htmlspecialchars($default);
    }
    return htmlspecialchars($value);
}

/**
 * FONCTION DÉPRÉCIÉE - NE PLUS UTILISER
 * 
 * Cette fonction a été remplacée par :
 * - isContractTicketById($contractId) pour vérifier un contrat par son ID
 * - isInterventionLinkedToTicketContract($interventionId) pour vérifier une intervention
 * 
 * @deprecated Utiliser isContractTicketById() ou isInterventionLinkedToTicketContract() à la place
 * @param array $data Les données contenant les informations de contrat
 * @return bool True si le contrat utilise un système de tickets
 */
/*
function isTicketContract($data) {
    if (!is_array($data) || empty($data)) {
        return false;
    }
    
    // Vérification du champ isticketcontract
    // Pour les données de contrat directes
    if (isset($data['isticketcontract'])) {
        return (bool)$data['isticketcontract'];
    }
    
    // Pour les données d'intervention avec informations de contrat ajoutées
    if (isset($data['contract_isticketcontract'])) {
        return (bool)$data['contract_isticketcontract'];
    }
    
    return false;
}
*/

/**
 * Vérifie si un contrat est de type ticket selon son ID
 * 
 * @param int $contractId L'ID du contrat à vérifier
 * @return bool True si le contrat utilise un système de tickets
 */
function isContractTicketById($contractId) {
    if (empty($contractId) || !is_numeric($contractId)) {
        return false;
    }
    
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT isticketcontract FROM contracts WHERE id = ?");
        $stmt->execute([$contractId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (bool)$result['isticketcontract'] : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du contrat ticket: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si une intervention est liée à un contrat à tickets
 * 
 * @param int $interventionId L'ID de l'intervention à vérifier
 * @return bool True si l'intervention est liée à un contrat à tickets
 */
function isInterventionLinkedToTicketContract($interventionId) {
    if (empty($interventionId) || !is_numeric($interventionId)) {
        return false;
    }
    
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT c.isticketcontract 
            FROM interventions i 
            LEFT JOIN contracts c ON i.contract_id = c.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$interventionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (bool)$result['isticketcontract'] : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du contrat ticket pour l'intervention: " . $e->getMessage());
        return false;
    }
}
?> 