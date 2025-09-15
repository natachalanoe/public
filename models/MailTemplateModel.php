<?php
require_once __DIR__ . '/../includes/functions.php';

/**
 * Modèle pour la gestion des templates d'emails
 */
class MailTemplateModel {
    private $db;
    private $table = 'mail_templates';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Récupère tous les templates
     * @return array Liste des templates
     */
    public function getAll() {
        $sql = "SELECT * FROM " . $this->table . " ORDER BY template_type, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un template par son ID
     * @param int $id ID du template
     * @return array|null Le template ou null
     */
    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un template par son type
     * @param string $type Type de template
     * @return array|null Le template ou null
     */
    public function getByType($type) {
        $sql = "SELECT * FROM " . $this->table . " WHERE template_type = ? AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'ID du template par type
     * @param string $templateType Type de template
     * @return int|null ID du template
     */
    public function getTemplateIdByType($templateType) {
        $stmt = $this->db->prepare("SELECT id FROM " . $this->table . " WHERE template_type = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$templateType]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }

    /**
     * Crée un nouveau template
     * @param array $data Données du template
     * @return int ID du template créé
     */
    public function create($data) {
        $sql = "INSERT INTO " . $this->table . " (name, template_type, subject, body, description, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['template_type'],
            $data['subject'],
            $data['body'],
            $data['description'],
            $data['is_active']
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Met à jour un template
     * @param int $id ID du template
     * @param array $data Données à mettre à jour
     * @return bool Succès de la mise à jour
     */
    public function update($id, $data) {
        $sql = "UPDATE " . $this->table . " SET 
                name = ?, template_type = ?, subject = ?, body = ?, description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['template_type'],
            $data['subject'],
            $data['body'],
            $data['description'],
            $data['is_active'],
            $id
        ]);
    }

    /**
     * Supprime un template
     * @param int $id ID du template
     * @return bool Succès de la suppression
     */
    public function delete($id) {
        $sql = "DELETE FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Active/désactive un template
     * @param int $id ID du template
     * @param bool $isActive Statut actif
     * @return bool Succès de la mise à jour
     */
    public function setActive($id, $isActive) {
        $sql = "UPDATE " . $this->table . " SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    /**
     * Récupère les templates par type
     * @param string $type Type de template
     * @return array Liste des templates
     */
    public function getByTemplateType($type) {
        $sql = "SELECT * FROM " . $this->table . " WHERE template_type = ? ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un template existe pour un type donné
     * @param string $type Type de template
     * @return bool True si un template existe
     */
    public function existsForType($type) {
        $sql = "SELECT COUNT(*) FROM " . $this->table . " WHERE template_type = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchColumn() > 0;
    }
}

