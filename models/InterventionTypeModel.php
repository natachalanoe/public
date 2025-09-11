<?php
require_once __DIR__ . '/../includes/functions.php';

/**
 * Modèle pour la gestion des types d'intervention
 */
class InterventionTypeModel {
    private $db;
    private $table = 'intervention_types';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Récupère tous les types d'intervention avec le nombre d'interventions
     * @return array Liste des types d'intervention avec intervention_count
     */
    public function getAll() {
        $sql = "SELECT it.*, COUNT(i.id) as intervention_count 
                FROM " . $this->table . " it 
                LEFT JOIN interventions i ON it.id = i.type_id 
                GROUP BY it.id, it.name, it.requires_travel, it.created_at 
                ORDER BY it.name, it.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un type d'intervention par son ID
     * @param int $id ID du type d'intervention
     * @return array|null Les données du type d'intervention ou null si non trouvé
     */
    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouveau type d'intervention
     * @param array $data Les données du type d'intervention
     * @return int|false L'ID du nouveau type d'intervention ou false en cas d'erreur
     */
    public function create($data) {
        $sql = "INSERT INTO " . $this->table . " (name, requires_travel) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute([$data['name'], $data['requires_travel'] ? 1 : 0])) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }

    /**
     * Met à jour un type d'intervention
     * @param int $id ID du type d'intervention
     * @param array $data Les nouvelles données
     * @return bool True si la mise à jour a réussi, false sinon
     */
    public function update($id, $data) {
        $sql = "UPDATE " . $this->table . " SET name = ?, requires_travel = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$data['name'], $data['requires_travel'] ? 1 : 0, $id]);
    }

    /**
     * Supprime un type d'intervention
     * @param int $id ID du type d'intervention
     * @return bool True si la suppression a réussi, false sinon
     */
    public function delete($id) {
        $sql = "DELETE FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Vérifie si un type d'intervention est utilisé par des interventions
     * @param int $id ID du type d'intervention
     * @return int Le nombre d'interventions utilisant ce type
     */
    public function getInterventionCount($id) {
        $sql = "SELECT COUNT(*) as count FROM interventions WHERE type_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    /**
     * Vérifie si un nom de type d'intervention existe déjà
     * @param string $name Le nom à vérifier
     * @param int $excludeId ID à exclure de la vérification (pour les mises à jour)
     * @return bool True si le nom existe déjà, false sinon
     */
    public function nameExists($name, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE name = ?";
        $params = [$name];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'] > 0;
    }
} 