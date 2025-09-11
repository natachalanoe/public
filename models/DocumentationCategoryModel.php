<?php
class DocumentationCategoryModel {
    private $db;
    private $table = 'documentation_categories';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Récupère toutes les catégories
     */
    public function getAllCategories() {
        $query = "SELECT * FROM {$this->table} ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute une nouvelle catégorie
     */
    public function addCategory($name, $description = null) {
        $query = "INSERT INTO {$this->table} (name, description) VALUES (:name, :description)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':name' => $name,
            ':description' => $description
        ]);
    }

    /**
     * Supprime une catégorie
     */
    public function deleteCategory($categoryId) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $categoryId]);
    }
} 