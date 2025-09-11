<?php

class UserTypeModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Récupère tous les types d'utilisateur
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT id, name, description, group_id, created_at, updated_at 
                FROM user_types 
                ORDER BY name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des types d'utilisateur : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère un type d'utilisateur par son ID
     */
    public function getById($id) {
        try {
            // D'abord essayer de récupérer depuis user_types
            $stmt = $this->db->prepare("
                SELECT * FROM user_types 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result;
            }
            
            // Si pas trouvé dans user_types, vérifier si ce type est utilisé dans users
            $stmt = $this->db->prepare("
                SELECT DISTINCT
                    ? as id,
                    CONCAT('Type ', ?) as name,
                    'Type d\'utilisateur non défini' as description,
                    NOW() as created_at,
                    NOW() as updated_at
                FROM users u
                WHERE u.user_type_id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $id, $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Vérifie si un nom de type d'utilisateur existe déjà
     */
    public function nameExists($name, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) FROM user_types WHERE name = ?";
            $params = [$name];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la vérification du nom du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Crée un nouveau type d'utilisateur
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_types (name, description, group_id, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            return $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['group_id']
            ]);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la création du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Met à jour un type d'utilisateur
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_types 
                SET name = ?, description = ?, group_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['group_id'],
                $id
            ]);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la mise à jour du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Supprime un type d'utilisateur
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM user_types WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la suppression du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère le nombre d'utilisateurs utilisant ce type
     */
    public function getUserCount($typeId) {
        try {
            // Compter les utilisateurs avec ce user_type_id
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM users 
                WHERE user_type_id = ?
            ");
            $stmt->execute([$typeId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            custom_log("Erreur lors du comptage des utilisateurs par type : " . $e->getMessage(), 'ERROR');
            return 0;
        }
    }

    /**
     * Récupère tous les groupes d'utilisateur
     */
    public function getAllGroups() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM user_groups 
                ORDER BY name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des groupes d'utilisateur : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
}
?> 