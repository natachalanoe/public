<?php
/**
 * Modèle pour la gestion des durées d'intervention
 */
class DurationModel {
    private $db;
    private $table = 'intervention_durations';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Récupère toutes les durées disponibles
     */
    public function getAll() {
        $sql = "SELECT * FROM " . $this->table . " ORDER BY duration";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 