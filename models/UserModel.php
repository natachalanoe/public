<?php
/**
 * Modèle User
 * Gère toutes les opérations liées aux utilisateurs
 */
class UserModel {
    private $db;
    private $table = 'users';
    private $id;
    private $username;
    private $email;
    private $firstName;
    private $lastName;
    private $type;
    private $status;
    private $coefUtilisateur;
    private $permissions = null;

    /**
     * Constructeur
     */
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Récupère la liste des utilisateurs avec filtres et pagination
     */
    public function getUsers($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        // Construction des conditions de filtrage
        if (!empty($filters['type'])) {
            $where[] = "ut.name = :type";
            $params[':type'] = $filters['type'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = "u.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(u.username LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // Construction de la requête avec les nouvelles tables
        $sql = "SELECT u.*, ut.name as user_type, ug.name as user_group
                FROM " . $this->table . " u
                JOIN user_types ut ON u.user_type_id = ut.id
                JOIN user_groups ug ON ut.group_id = ug.id";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

        // Ajout des paramètres de pagination
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        // Exécution de la requête
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        // Récupération du nombre total d'enregistrements pour la pagination
        $countSql = "SELECT COUNT(*) as total 
                     FROM " . $this->table . " u
                     JOIN user_types ut ON u.user_type_id = ut.id
                     JOIN user_groups ug ON ut.group_id = ug.id";
        if (!empty($where)) {
            $countSql .= " WHERE " . implode(" AND ", $where);
        }
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $countStmt->bindValue($key, $value);
            }
        }
        $countStmt->execute();
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * Récupère un utilisateur par son ID
     */
    public function getUserById($id) {
        $sql = "SELECT u.*, ut.name as user_type, ug.name as user_group
                FROM " . $this->table . " u
                JOIN user_types ut ON u.user_type_id = ut.id
                JOIN user_groups ug ON ut.group_id = ug.id
                WHERE u.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouvel utilisateur
     */
    public function createUser($data) {
        try {
            // Récupérer l'ID du type d'utilisateur
            $stmt = $this->db->prepare("SELECT id FROM user_types WHERE name = :type_name");
            $stmt->execute(['type_name' => $data['type']]);
            $userType = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userType) {
                custom_log("Type d'utilisateur non trouvé : " . $data['type'], 'ERROR');
                return false;
            }
            
            // Déterminer si c'est un membre du staff
            $stmt = $this->db->prepare("
                SELECT ug.name as group_name 
                FROM user_types ut 
                JOIN user_groups ug ON ut.group_id = ug.id 
                WHERE ut.id = :type_id
            ");
            $stmt->execute(['type_id' => $userType['id']]);
            $groupInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $isStaff = ($groupInfo['group_name'] === 'Staff');
            
            $sql = "INSERT INTO " . $this->table . " 
                    (username, email, password, first_name, last_name, user_type_id, is_admin, status, coef_utilisateur, client_id, created_at) 
                    VALUES 
                    (:username, :email, :password, :first_name, :last_name, :user_type_id, :is_admin, :status, :coef_utilisateur, :client_id, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':username', $data['username']);
            $stmt->bindValue(':email', $data['email']);
            $stmt->bindValue(':password', password_hash($data['password'], PASSWORD_DEFAULT));
            $stmt->bindValue(':first_name', $data['first_name']);
            $stmt->bindValue(':last_name', $data['last_name']);
            $stmt->bindValue(':user_type_id', $userType['id']);
            $stmt->bindValue(':is_admin', $data['is_admin'] ?? false);
            $stmt->bindValue(':status', $data['status'] ?? 1);
            $stmt->bindValue(':coef_utilisateur', $isStaff ? ($data['coef_utilisateur'] ?? 1.00) : null);
            $stmt->bindValue(':client_id', $isStaff ? 0 : ($data['client_id'] ?? null));

            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la création de l'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Met à jour un utilisateur
     */
    public function updateUser($id, $data) {
        try {
            $updates = [];
            $params = [':id' => $id];

            // Construction des champs à mettre à jour
            if (isset($data['username'])) {
                $updates[] = "username = :username";
                $params[':username'] = $data['username'];
            }
            if (isset($data['email'])) {
                $updates[] = "email = :email";
                $params[':email'] = $data['email'];
            }
            if (isset($data['password'])) {
                $updates[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if (isset($data['first_name'])) {
                $updates[] = "first_name = :first_name";
                $params[':first_name'] = $data['first_name'];
            }
            if (isset($data['last_name'])) {
                $updates[] = "last_name = :last_name";
                $params[':last_name'] = $data['last_name'];
            }
            if (isset($data['type'])) {
                // Récupérer l'ID du type d'utilisateur
                $stmt = $this->db->prepare("SELECT id FROM user_types WHERE name = :type_name");
                $stmt->execute(['type_name' => $data['type']]);
                $userType = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($userType) {
                    $updates[] = "user_type_id = :user_type_id";
                    $params[':user_type_id'] = $userType['id'];
                    
                    // Déterminer si c'est un membre du staff
                    $stmt = $this->db->prepare("
                        SELECT ug.name as group_name 
                        FROM user_types ut 
                        JOIN user_groups ug ON ut.group_id = ug.id 
                        WHERE ut.id = :type_id
                    ");
                    $stmt->execute(['type_id' => $userType['id']]);
                    $groupInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $isStaff = ($groupInfo['group_name'] === 'Staff');
                    
                    // Mettre à jour client_id en fonction du groupe
                    $clientIdValue = $isStaff ? 0 : ($data['client_id'] ?? null);
                    $updates[] = "client_id = :client_id";
                    $params[':client_id'] = $clientIdValue;
                }
            }
            if (isset($data['is_admin'])) {
                $updates[] = "is_admin = :is_admin";
                $params[':is_admin'] = $data['is_admin'];
            }
            if (isset($data['status'])) {
                $updates[] = "status = :status";
                $params[':status'] = $data['status'];
            }
            if (isset($data['coef_utilisateur'])) {
                // Déterminer si l'utilisateur est staff pour le coefficient
                $isStaff = false;
                if (isset($data['type'])) {
                    $stmt = $this->db->prepare("
                        SELECT ug.name as group_name 
                        FROM user_types ut 
                        JOIN user_groups ug ON ut.group_id = ug.id 
                        WHERE ut.name = :type_name
                    ");
                    $stmt->execute(['type_name' => $data['type']]);
                    $groupInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    $isStaff = ($groupInfo['group_name'] === 'Staff');
                }
                
                $updates[] = "coef_utilisateur = :coef_utilisateur";
                $params[':coef_utilisateur'] = $isStaff ? $data['coef_utilisateur'] : null;
            }

            if (empty($updates)) {
                return false;
            }

            $sql = "UPDATE " . $this->table . " SET " . implode(", ", $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la mise à jour de l'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Supprime un utilisateur
     */
    public function deleteUser($id) {
        $sql = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }

    /**
     * Vérifie si un nom d'utilisateur existe déjà
     */
    public function usernameExists($username, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE username = :username";
        $params = [':username' => $username];

        if ($excludeId) {
            $sql .= " AND id != :id";
            $params[':id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }

    /**
     * Vérifie si un email existe déjà
     */
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE email = :email";
        $params = [':email' => $email];

        if ($excludeId) {
            $sql .= " AND id != :id";
            $params[':id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }

    /**
     * Authentifie un utilisateur
     * @param string $username Nom d'utilisateur
     * @param string $password Mot de passe
     * @return bool True si l'authentification réussit
     */
    public function authenticate($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.username, u.password, u.email, u.first_name, u.last_name, 
                       u.status, u.coef_utilisateur, u.client_id, u.is_admin,
                       ut.name as user_type, ug.name as user_group
                FROM users u
                JOIN user_types ut ON u.user_type_id = ut.id
                JOIN user_groups ug ON ut.group_id = ug.id
                WHERE u.username = :username AND u.status = 1
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $this->id = $user['id'];
                $this->username = $user['username'];
                $this->email = $user['email'];
                $this->firstName = $user['first_name'];
                $this->lastName = $user['last_name'];
                $this->type = $user['user_type'];
                $this->status = $user['status'];
                $this->coefUtilisateur = $user['coef_utilisateur'];
                $this->isAdmin = $user['is_admin'];

                // Chargement des permissions
                $this->loadPermissions();

                // Stockage dans la session
                $_SESSION['user'] = [
                    'id' => $this->id,
                    'username' => $this->username,
                    'email' => $this->email,
                    'first_name' => $this->firstName,
                    'last_name' => $this->lastName,
                    'user_type' => $user['user_type'],
                    'user_group' => $user['user_group'],
                    'is_admin' => $user['is_admin'],
                    'client_id' => $user['client_id'],
                    'permissions' => $this->permissions
                ];

                // Log de la connexion
                custom_log("Utilisateur connecté : {$this->username}", 'INFO', [
                    'user_id' => $this->id,
                    'user_type' => $user['user_type'],
                    'user_group' => $user['user_group'],
                    'is_admin' => $user['is_admin'],
                    'client_id' => $user['client_id']
                ]);

                return true;
            }

            custom_log("Tentative de connexion échouée pour l'utilisateur : $username", 'WARNING');
            return false;
        } catch (PDOException $e) {
            custom_log("Erreur d'authentification : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Charge les permissions de l'utilisateur
     */
    private function loadPermissions() {
        try {
            // Récupération des droits
            $stmt = $this->db->prepare("
                SELECT right_name 
                FROM user_rights 
                WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $this->id]);
            $rights = $stmt->fetchAll();

            // Récupération des localisations
            $stmt = $this->db->prepare("
                SELECT client_id, site_id, room_id 
                FROM user_locations 
                WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $this->id]);
            $locations = $stmt->fetchAll();

            // Construction du tableau des permissions
            $this->permissions = [
                'role' => $this->type,
                'rights' => [],
                'locations' => [],
                'coefficients' => [
                    'user_coef' => $this->coefUtilisateur
                ]
            ];

            // Ajout des droits
            foreach ($rights as $right) {
                $this->permissions['rights'][$right['right_name']] = true;
            }

            // Ajout des localisations
            foreach ($locations as $location) {
                $this->permissions['locations'][] = [
                    'client_id' => $location['client_id'],
                    'site_id' => $location['site_id'],
                    'room_id' => $location['room_id']
                ];
            }

            // Log temporaire pour debug
            custom_log("Permissions chargées pour {$this->username} : " . json_encode($this->permissions), 'DEBUG');
        } catch (PDOException $e) {
            custom_log("Erreur lors du chargement des permissions : " . $e->getMessage(), 'ERROR');
            $this->permissions = [
                'role' => $this->type,
                'rights' => [],
                'locations' => [],
                'coefficients' => [
                    'user_coef' => $this->coefUtilisateur
                ]
            ];
        }
    }

    /**
     * Vérifie si l'utilisateur a une permission spécifique
     * @param string $permission Le nom de la permission à vérifier
     * @return bool True si l'utilisateur a la permission, false sinon
     */
    public function hasPermission($permission) {
        // Les administrateurs ont des permissions configurées comme les autres membres du staff
        // Les droits spéciaux (gestion users, suppression, etc.) sont gérés au niveau de l'interface
        if ($this->type === 'admin') {
            // Vérifier si l'admin a explicitement cette permission
            if (isset($this->permissions['rights']) && is_array($this->permissions['rights'])) {
                return isset($this->permissions['rights'][$permission]) && 
                       $this->permissions['rights'][$permission] === true;
            }
        }

        // Vérification des permissions simplifiées
        $simplifiedPermissions = [
            // Permissions clients et localisations
            'tech_create_clients' => 'tech_manage_clients',
            'tech_modify_clients' => 'tech_manage_clients',
            'tech_create_client_contacts' => 'tech_manage_clients',
            'tech_modify_client_contacts' => 'tech_manage_clients',
            'tech_modify_contacts' => 'tech_manage_clients',
            'tech_create_sites' => 'tech_manage_clients',
            'tech_modify_sites' => 'tech_manage_clients',
            'tech_create_rooms' => 'tech_manage_clients',
            'tech_modify_rooms' => 'tech_manage_clients',
            
            // Permissions interventions
            'tech_create_interventions' => 'tech_manage_interventions',
            'tech_modify_interventions' => 'tech_manage_interventions',
            'tech_view_all_interventions' => 'tech_manage_interventions',
            
            // Permissions documentation
            'tech_add_documentation' => 'tech_manage_documentation',
            'tech_modify_documentation' => 'tech_manage_documentation',
            
            // Permissions contrats
            'tech_create_contrats' => 'tech_manage_contrats',
            'tech_modify_contrats' => 'tech_manage_contrats',
            'tech_view_contrats' => 'tech_manage_contrats'
        ];

        // Si la permission demandée a une version simplifiée, utiliser celle-ci
        if (isset($simplifiedPermissions[$permission])) {
            $permission = $simplifiedPermissions[$permission];
        }

        // Vérifier si l'utilisateur a la permission
        return isset($this->permissions['rights'][$permission]) && $this->permissions['rights'][$permission] === true;
    }

    /**
     * Vérifie si l'utilisateur a accès à une localisation
     * @param int $clientId ID du client
     * @param int|null $siteId ID du site (optionnel)
     * @param int|null $roomId ID de la salle (optionnel)
     * @return bool True si l'utilisateur a accès
     */
    public function hasLocationAccess($clientId, $siteId = null, $roomId = null) {
        // Les administrateurs ont accès à tout
        if ($this->type === 'admin') {
            return true;
        }

        foreach ($this->permissions['locations'] as $location) {
            if ($location['client_id'] == $clientId) {
                if ($siteId === null) {
                    return true;
                }
                if ($location['site_id'] == $siteId) {
                    if ($roomId === null) {
                        return true;
                    }
                    if ($location['room_id'] == $roomId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        if (isset($_SESSION['user'])) {
            custom_log("Utilisateur déconnecté : {$this->username}", 'INFO', [
                'user_id' => $this->id
            ]);
            unset($_SESSION['user']);
        }
        session_destroy();
    }

    /**
     * Récupère les rôles d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Liste des rôles
     */
    public function getUserRoles($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT r.id, r.name, r.description
                FROM roles r
                JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = :user_id
            ");
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des rôles : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère les permissions d'un utilisateur
     */
    public function getUserPermissions($userId) {
        try {
            $sql = "SELECT ur.right_name, ap.description, ap.category 
                    FROM user_rights ur 
                    JOIN available_permissions ap ON ur.right_name = ap.name 
                    WHERE ur.user_id = :user_id AND ur.right_value = 1 
                    ORDER BY ap.category, ap.name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des permissions : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère les permissions disponibles selon le type d'utilisateur
     * @param string $userType Le type d'utilisateur
     * @return array Les permissions disponibles
     */
    public function getAvailablePermissions($userType) {
        try {
            // Récupérer le groupe du type d'utilisateur
            $stmt = $this->db->prepare("
                SELECT ug.name as group_name
                FROM user_types ut
                JOIN user_groups ug ON ut.group_id = ug.id
                WHERE ut.name = :user_type
            ");
            $stmt->execute(['user_type' => $userType]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                custom_log("Type d'utilisateur non trouvé : $userType", 'WARNING');
                return [];
            }
            
            $groupName = $result['group_name'];
            
            // Récupérer les permissions du groupe
            $query = "SELECT ap.id, ap.name, ap.description, ap.category 
                     FROM available_permissions ap
                     JOIN user_groups ug ON ap.group_id = ug.id
                     WHERE ug.name = :group_name 
                     ORDER BY ap.category, ap.name";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['group_name' => $groupName]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des permissions : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère la liste des clients actifs
     * @return array Liste des clients avec leur id et nom
     */
    public function getActiveClients() {
        try {
            $query = "SELECT id, name FROM clients WHERE status = 1 ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($clients)) {
                custom_log("Aucun client actif trouvé", 'WARNING');
            }
            
            return $clients;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des clients actifs : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Ajoute une permission à un utilisateur
     * @param int $userId L'ID de l'utilisateur
     * @param int $permissionId L'ID de la permission
     * @return bool True si l'ajout a réussi, false sinon
     */
    public function addUserPermission($userId, $permissionId) {
        try {
            // Récupérer le nom de la permission
            $stmt = $this->db->prepare("SELECT name FROM available_permissions WHERE id = :permission_id");
            $stmt->execute(['permission_id' => $permissionId]);
            $permission = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$permission) {
                custom_log("Permission non trouvée : " . $permissionId, 'ERROR');
                return false;
            }

            // Vérifier si le droit existe déjà
            $stmt = $this->db->prepare("SELECT id FROM user_rights WHERE user_id = :user_id AND right_name = :right_name");
            $stmt->execute([
                'user_id' => $userId,
                'right_name' => $permission['name']
            ]);
            
            if ($stmt->fetch()) {
                // Le droit existe déjà, on le met à jour
                $query = "UPDATE user_rights SET right_value = 1 WHERE user_id = :user_id AND right_name = :right_name";
            } else {
                // Le droit n'existe pas, on le crée
                $query = "INSERT INTO user_rights (user_id, right_name, right_value) VALUES (:user_id, :right_name, 1)";
            }

            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'user_id' => $userId,
                'right_name' => $permission['name']
            ]);

            if (!$result) {
                custom_log("Erreur lors de l'ajout de la permission pour l'utilisateur " . $userId, 'ERROR');
            }

            return $result;
        } catch (PDOException $e) {
            custom_log("Erreur lors de l'ajout de la permission : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Supprime toutes les permissions d'un utilisateur
     * @param int $userId L'ID de l'utilisateur
     * @return bool True si la suppression a réussi, false sinon
     */
    public function deleteUserPermissions($userId) {
        try {
            $query = "DELETE FROM user_rights WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['user_id' => $userId]);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la suppression des permissions : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les sites et salles d'un client
     * @param int $clientId ID du client
     * @return array Liste des sites avec leurs salles
     */
    public function getClientLocations($clientId) {
        try {
            // Vérifier que le client existe
            $query = "SELECT id FROM clients WHERE id = :client_id AND status = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['client_id' => $clientId]);
            if (!$stmt->fetch()) {
                custom_log("Client non trouvé ou inactif: " . $clientId, 'ERROR');
                return false;
            }

            // Récupérer les sites
            $query = "SELECT id, name FROM sites WHERE client_id = :client_id AND status = 1 ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['client_id' => $clientId]);
            $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pour chaque site, récupérer ses salles
            foreach ($sites as &$site) {
                $query = "SELECT id, name FROM rooms WHERE site_id = :site_id AND status = 1 ORDER BY name";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['site_id' => $site['id']]);
                $site['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            custom_log("Localisations récupérées avec succès pour le client: " . $clientId, 'INFO');
            return $sites;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des localisations du client " . $clientId . ": " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les localisations d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Liste des localisations
     */
    public function getUserLocations($userId) {
        try {
            $query = "SELECT client_id, site_id, room_id FROM user_locations WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des localisations de l'utilisateur : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Sauvegarde les localisations d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @param array $locations Tableau des localisations à sauvegarder
     * @return bool True si la sauvegarde a réussi
     */
    public function saveUserLocations($userId, $locations) {
        try {
            custom_log("Début de saveUserLocations pour l'utilisateur {$userId}", 'INFO');
            custom_log("Localisations reçues: " . json_encode($locations), 'INFO');
            
            // Validation des paramètres
            if (!$userId || !is_array($locations)) {
                custom_log("Paramètres invalides pour saveUserLocations: userId={$userId}, locations=" . json_encode($locations), 'ERROR');
                return false;
            }
            
            // Vérifier si l'utilisateur est un technicien
            $stmt = $this->db->prepare("SELECT ut.name as user_type FROM " . $this->table . " u 
                                       JOIN user_types ut ON u.user_type_id = ut.id 
                                       WHERE u.id = :user_id");
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            custom_log("Type d'utilisateur récupéré: " . ($user ? $user['user_type'] : 'non trouvé'), 'INFO');
            
            // Si l'utilisateur est un technicien, ne pas sauvegarder de localisations
            if ($user && $user['user_type'] === 'technicien') {
                custom_log("Utilisateur {$userId} est un technicien, pas besoin de sauvegarder les localisations", 'INFO');
                return true;
            }
            
            // Supprimer les anciennes localisations
            $query = "DELETE FROM user_locations WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $userId]);

            // Récupérer le client_id du formulaire
            $clientId = $_POST['client_id'] ?? null;
            if (!$clientId) {
                custom_log("Client ID manquant lors de la sauvegarde des localisations", 'ERROR');
                return false;
            }
            
            // Vérifier que le client existe et est actif
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE id = :client_id AND status = 1");
            $stmt->execute(['client_id' => $clientId]);
            if (!$stmt->fetch()) {
                custom_log("Client {$clientId} non trouvé ou inactif lors de la sauvegarde des localisations", 'ERROR');
                return false;
            }

            // Si accès client complet, on ne traite que cela
            if (isset($locations['client_full'])) {
                $query = "INSERT INTO user_locations (user_id, client_id) VALUES (:user_id, :client_id)";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    'user_id' => $userId,
                    'client_id' => $clientId
                ]);
                custom_log("Accès complet au client {$clientId} accordé à l'utilisateur {$userId}", 'INFO');
                // Retourner ici car l'accès complet couvre tout
                return true;
            }

            // Traiter les sites
            if (isset($locations['sites']) && !empty($locations['sites'])) {
                custom_log("Traitement de " . count($locations['sites']) . " sites pour l'utilisateur {$userId}", 'INFO');
                
                foreach ($locations['sites'] as $siteId) {
                    // Vérifier que le site appartient bien au client
                    $query = "SELECT id, name FROM sites WHERE id = :site_id AND client_id = :client_id AND status = 1";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        'site_id' => $siteId,
                        'client_id' => $clientId
                    ]);
                    
                    $site = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($site) {
                        $query = "INSERT INTO user_locations (user_id, client_id, site_id) 
                                 VALUES (:user_id, :client_id, :site_id)";
                        $stmt = $this->db->prepare($query);
                        $stmt->execute([
                            'user_id' => $userId,
                            'client_id' => $clientId,
                            'site_id' => $siteId
                        ]);
                        custom_log("Site {$siteId} ({$site['name']}) enregistré pour l'utilisateur {$userId}", 'INFO');
                    } else {
                        custom_log("Site {$siteId} non trouvé ou n'appartient pas au client {$clientId}", 'WARNING');
                    }
                }
            }

            // Traiter uniquement les salles qui ne sont pas dans un site déjà sélectionné
            if (isset($locations['rooms']) && !empty($locations['rooms'])) {
                // Récupérer la liste des sites déjà sélectionnés
                $selectedSites = isset($locations['sites']) ? $locations['sites'] : [];
                custom_log("Traitement de " . count($locations['rooms']) . " salles pour l'utilisateur {$userId}", 'INFO');
                custom_log("Sites déjà sélectionnés: " . json_encode($selectedSites), 'INFO');
                
                foreach ($locations['rooms'] as $roomId) {
                    // Récupérer le site_id de la salle
                    $query = "SELECT r.site_id, r.name as room_name, s.name as site_name 
                             FROM rooms r 
                             JOIN sites s ON r.site_id = s.id 
                             WHERE r.id = :room_id AND r.status = 1 AND s.status = 1";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute(['room_id' => $roomId]);
                    $room = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$room) {
                        custom_log("Salle {$roomId} non trouvée ou inactive", 'WARNING');
                        continue;
                    }

                    // Ne traiter la salle que si son site n'est pas déjà sélectionné
                    if (!in_array($room['site_id'], $selectedSites)) {
                        // Vérifier que le site appartient bien au client
                        $query = "SELECT id FROM sites WHERE id = :site_id AND client_id = :client_id AND status = 1";
                        $stmt = $this->db->prepare($query);
                        $stmt->execute([
                            'site_id' => $room['site_id'],
                            'client_id' => $clientId
                        ]);
                        
                        if ($stmt->fetch()) {
                            $query = "INSERT INTO user_locations (user_id, client_id, site_id, room_id) 
                                     VALUES (:user_id, :client_id, :site_id, :room_id)";
                            $stmt = $this->db->prepare($query);
                            $stmt->execute([
                                'user_id' => $userId,
                                'client_id' => $clientId,
                                'site_id' => $room['site_id'],
                                'room_id' => $roomId
                            ]);
                            custom_log("Salle {$roomId} ({$room['room_name']}) du site {$room['site_name']} enregistrée pour l'utilisateur {$userId}", 'INFO');
                        } else {
                            custom_log("Site {$room['site_id']} ({$room['site_name']}) n'appartient pas au client {$clientId}", 'WARNING');
                        }
                    } else {
                        custom_log("Salle {$roomId} ({$room['room_name']}) ignorée car son site {$room['site_id']} est déjà sélectionné", 'INFO');
                    }
                }
            }

            // Log de fin pour tracer le succès
            custom_log("Sauvegarde des localisations terminée avec succès pour l'utilisateur {$userId}", 'INFO');

            return true;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la sauvegarde des localisations : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les informations d'un client
     * @param int $clientId ID du client
     * @return array|false Les informations du client ou false si non trouvé
     */
    public function getClientById($clientId) {
        try {
            $query = "SELECT id, name, status FROM clients WHERE id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['client_id' => $clientId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération du client " . $clientId . ": " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les informations d'un site
     * @param int $siteId ID du site
     * @return array|false Les informations du site ou false si non trouvé
     */
    public function getSiteById($siteId) {
        try {
            $query = "SELECT id, name, status FROM sites WHERE id = :site_id AND status = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['site_id' => $siteId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération du site " . $siteId . ": " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les informations d'une salle
     * @param int $roomId ID de la salle
     * @return array|false Les informations de la salle ou false si non trouvée
     */
    public function getRoomById($roomId) {
        try {
            $query = "SELECT id, name, status FROM rooms WHERE id = :room_id AND status = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['room_id' => $roomId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération de la salle " . $roomId . ": " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère la liste des membres du staff actifs (techniciens, commerciaux, administrateurs)
     * @return array Liste des utilisateurs staff avec leur id, nom et prénom
     */
    public function getTechnicians() {
        try {
            $sql = "SELECT u.id, u.first_name, u.last_name 
                    FROM " . $this->table . " u
                    JOIN user_types ut ON u.user_type_id = ut.id
                    JOIN user_groups ug ON ut.group_id = ug.id
                    WHERE ug.name = 'Staff' 
                    AND u.status = 1 
                    ORDER BY u.last_name, u.first_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des membres du staff : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère les IDs des permissions existantes d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Liste des IDs des permissions
     */
    public function getUserPermissionIds($userId) {
        try {
            $permissionIds = [];
            
            // Récupérer les permissions existantes
            $existingPermissions = $this->getUserPermissions($userId);
            
            if (!empty($existingPermissions)) {
                // Récupérer les IDs des permissions dans available_permissions
                foreach ($existingPermissions as $permission) {
                    $stmt = $this->db->prepare("SELECT id FROM available_permissions WHERE name = ?");
                    $stmt->execute([$permission['right_name']]);
                    $permissionId = $stmt->fetchColumn();
                    if ($permissionId) {
                        $permissionIds[] = $permissionId;
                    }
                }
            }
            
            return $permissionIds;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des IDs des permissions : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
} 