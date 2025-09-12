<?php
/**
 * Contrôleur pour la gestion des interventions
 */
class InterventionController {
    private $db;
    private $interventionModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $userModel;
    private $contractModel;
    private $durationModel;

    // Constantes pour la configuration du PDF
    const PDF_PAGE_ORIENTATION = 'P'; // P = Portrait, L = Landscape
    const PDF_UNIT = 'mm';
    const PDF_PAGE_FORMAT = 'A4';
    const PDF_CREATOR = 'VideoSonic Support';
    const PDF_MARGIN_LEFT = 15;
    const PDF_MARGIN_TOP = 15;
    const PDF_MARGIN_RIGHT = 15;
    const PDF_MARGIN_BOTTOM = 15;
    const PDF_FONT_NAME_MAIN = 'helvetica';
    const PDF_FONT_SIZE_MAIN = 10;
    const PDF_FONT_NAME_DATA = 'helvetica';
    const PDF_FONT_SIZE_DATA = 8;
    const PDF_FONT_MONOSPACED = 'courier';
    const PDF_IMAGE_SCALE_RATIO = 1.25;
    const HEAD_MAGNIFICATION = 1.1;
    const K_CELL_HEIGHT_RATIO = 1.25;
    const K_TITLE_MAGNIFICATION = 1.3;
    const K_SMALL_RATIO = 2/3;

    public function __construct($db) {
        $this->db = $db;
        
        // Charger les modèles nécessaires
        require_once __DIR__ . '/../models/InterventionModel.php';
        require_once __DIR__ . '/../models/ClientModel.php';
        require_once __DIR__ . '/../models/SiteModel.php';
        require_once __DIR__ . '/../models/RoomModel.php';
        require_once __DIR__ . '/../models/UserModel.php';
        require_once __DIR__ . '/../models/ContractModel.php';
        require_once __DIR__ . '/../models/DurationModel.php';
        
        $this->interventionModel = new InterventionModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->roomModel = new RoomModel($db);
        $this->userModel = new UserModel($db);
        $this->contractModel = new ContractModel($db);
        $this->durationModel = new DurationModel($db);

        // Charger le fichier d'autoload de TCPDF
        require_once __DIR__ . '/../vendor/TCPDF-6.6.2/tcpdf.php';
    }

    /**
     * Vérifie si l'utilisateur a le droit d'accéder aux interventions
     */
    private function checkAccess() {
        checkStaffAccess();
    }

    /**
     * Affiche la liste des interventions
     */
    public function index() {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'technician_id' => $_GET['technician_id'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        // Récupérer les priorités pour identifier les préventives
        $sql = "SELECT * FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Identifier la priorité préventive
        $preventivePriorityId = null;
        foreach ($priorities as $priority) {
            if (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false) {
                $preventivePriorityId = $priority['id'];
                break;
            }
        }
        
        // Déterminer l'onglet actif (par défaut: non-préventives)
        $activeTab = $_GET['tab'] ?? 'non-preventive';
        
        // Récupérer les interventions selon l'onglet actif
        $interventions = [];
        if ($activeTab === 'preventive' && $preventivePriorityId) {
            // Onglet préventives
            $filters['priority_id'] = $preventivePriorityId;
            $interventions = $this->interventionModel->getAll($filters);
        } elseif ($activeTab === 'all') {
            // Onglet toutes
            $interventions = $this->interventionModel->getAll($filters);
        } else {
            // Onglet non-préventives (par défaut)
            if ($preventivePriorityId) {
                $filters['exclude_priority_ids'] = [$preventivePriorityId];
            }
            $interventions = $this->interventionModel->getAll($filters);
        }
        
        // Récupérer les données pour les filtres
        $clients = $this->clientModel->getAllClientsWithStats();
        $sites = !empty($filters['client_id']) ? $this->siteModel->getSitesByClientId($filters['client_id']) : [];
        $rooms = !empty($filters['site_id']) ? $this->roomModel->getRoomsBySiteId($filters['site_id']) : [];
        $technicians = $this->userModel->getTechnicians();
        
        // Récupérer les statuts
        $statuses = $this->getAllStatuses();
        
        // Récupérer les statistiques globales par onglet (sans filtres)
        $statsByTab = [];
        
        // Statistiques globales pour non-préventives (sans filtres)
        if ($preventivePriorityId) {
            $globalNonPreventiveFilters = ['exclude_priority_ids' => [$preventivePriorityId]];
        } else {
            $globalNonPreventiveFilters = [];
        }
        $statsByTab['non-preventive'] = $this->interventionModel->getStats($globalNonPreventiveFilters);
        
        // Statistiques globales pour préventives (sans filtres)
        if ($preventivePriorityId) {
            $globalPreventiveFilters = ['priority_id' => $preventivePriorityId];
            $statsByTab['preventive'] = $this->interventionModel->getStats($globalPreventiveFilters);
        }
        
        // Statistiques globales pour toutes (sans filtres)
        $statsByTab['all'] = $this->interventionModel->getStats([]);
        
        // Récupérer les statistiques par statut pour les filtres rapides (selon l'onglet actif)
        $statsByStatus = [];
        if ($activeTab === 'preventive' && $preventivePriorityId) {
            // Statistiques pour l'onglet préventives
            $preventiveFilters = $filters;
            $preventiveFilters['priority_id'] = $preventivePriorityId;
            $statsByStatus = $this->interventionModel->getStatsByStatus($preventiveFilters);
        } elseif ($activeTab === 'all') {
            // Statistiques pour l'onglet toutes
            $statsByStatus = $this->interventionModel->getStatsByStatus($filters);
        } else {
            // Statistiques pour l'onglet non-préventives
            $nonPreventiveFilters = $filters;
            if ($preventivePriorityId) {
                $nonPreventiveFilters['exclude_priority_ids'] = [$preventivePriorityId];
            }
            $statsByStatus = $this->interventionModel->getStatsByStatus($nonPreventiveFilters);
        }
        
        // Vérifier la permission de gestion des interventions
        $canManageInterventions = $this->checkPermission('technicien', 'manage_interventions');
        
        // Charger la vue
        require_once __DIR__ . '/../views/interventions/index.php';
    }

    /**
     * Affiche les détails d'une intervention
     */
    public function view($id) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        
        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // S'assurer que toutes les clés nécessaires existent
        $intervention = array_merge([
            'site_id' => null,
            'room_id' => null,
            'client_id' => null,
            'technician_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            'duration' => null,
            'description' => null,
            'title' => null
        ], $intervention);

        // Récupérer le contrat associé directement via contract_id
        $contract = null;
        if (!empty($intervention['contract_id'])) {
            $contract = $this->contractModel->getContractById($intervention['contract_id']);
        }
        
        // Ajouter les informations du contrat pour le calcul JavaScript
        if ($contract && $contract['tickets_number'] > 0) {
            $intervention['contract_tickets_number'] = $contract['tickets_number'];
            $intervention['contract_tickets_remaining'] = $contract['tickets_remaining'];
        } else {
            $intervention['contract_tickets_number'] = 0;
            $intervention['contract_tickets_remaining'] = 0;
        }
        
        // Récupérer les commentaires
        $comments = $this->getComments($id);

        // Récupérer les pièces jointes
        $attachments = $this->getAttachments($id);

        // Récupérer l'historique
        $history = $this->getHistory($id);

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/view.php';
    }

    /**
     * Affiche le formulaire d'édition d'une intervention
     */
    public function edit($id) {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        
        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // S'assurer que toutes les clés nécessaires existent
        $intervention = array_merge([
            'site_id' => null,
            'room_id' => null,
            'client_id' => null,
            'technician_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            'duration' => null,
            'description' => null,
            'title' => null
        ], $intervention);

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6 && !isAdmin()) { // 6 = Fermé
            $_SESSION['error'] = "Impossible de modifier une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Récupérer le contrat associé directement via contract_id
        $contract = null;
        if (!empty($intervention['contract_id'])) {
            $contract = $this->contractModel->getContractById($intervention['contract_id']);
        }

        // Définir les variables pour les formulaires
        $client_id = isset($intervention['client_id']) ? $intervention['client_id'] : null;
        $site_id = isset($intervention['site_id']) ? $intervention['site_id'] : null;
        $room_id = isset($intervention['room_id']) ? $intervention['room_id'] : null;

        // Récupérer les données pour les formulaires
        $clients = $this->clientModel->getAllClientsWithStats();
        $sites = $this->siteModel->getSitesByClientId($client_id);
        $rooms = $this->roomModel->getRoomsBySiteId($site_id);
        $technicians = $this->userModel->getTechnicians();
        
        // Récupérer les contrats du client pour le formulaire
        $contracts = [];
        if (!empty($client_id)) {
            $contracts = $this->contractModel->getContractsByClientId($client_id, $site_id, $room_id);
        }
        
        // Récupérer les statuts, priorités et types
        $statuses = $this->getAllStatuses();

        $sql = "SELECT * FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT * FROM intervention_types ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les durées
        $durations = $this->durationModel->getAll();
        
        // Récupérer les commentaires
        $comments = $this->getComments($id);

        // Récupérer les pièces jointes
        $attachments = $this->getAttachments($id);

        // Récupérer l'historique
        $history = $this->getHistory($id);

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/edit.php';
    }

    /**
     * Génère un bon d'intervention au format PDF
     * @param array $intervention Les données de l'intervention
     * @return string Le chemin du fichier PDF généré
     */
    private function generateInterventionReport($intervention) {
        // Récupérer les commentaires marqués comme solution
        $sql = "SELECT * FROM intervention_comments 
                WHERE intervention_id = ? AND is_solution = 1 
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$intervention['id']]);
        $solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Créer le dossier de stockage s'il n'existe pas
        $uploadDir = __DIR__ . '/../uploads/interventions/' . $intervention['id'];
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Générer un nom de fichier unique
        $fileName = 'bon_intervention_' . $intervention['id'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $filePath = $uploadDir . '/' . $fileName;

        // Charger la classe InterventionPDF
        require_once __DIR__ . '/../classes/InterventionPDF.php';

        // Créer et générer le PDF
        $pdf = new InterventionPDF();
        $pdf->generate($intervention, $solutions);
        $pdf->Output($filePath, 'F');

        // Ajouter le PDF comme pièce jointe via le modèle
        $data = [
            'nom_fichier' => $fileName,
            'chemin_fichier' => 'uploads/interventions/' . $intervention['id'] . '/' . $fileName,
            'type_fichier' => 'pdf',
            'taille_fichier' => filesize($filePath),
            'commentaire' => 'Bon d\'intervention généré automatiquement',
            'masque_client' => 0, // Visible par les clients
            'created_by' => $_SESSION['user']['id']
        ];

        // Ajouter la pièce jointe avec le type de liaison 'bi' (Bon d'Intervention)
        $pieceJointeId = $this->interventionModel->addPieceJointeWithType($intervention['id'], $data, 'bi');

        // Enregistrer l'action dans l'historique
        $sql = "INSERT INTO intervention_history (
                    intervention_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':intervention_id' => $intervention['id'],
            ':field_name' => 'Pièce jointe',
            ':old_value' => '',
            ':new_value' => $fileName,
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => "Bon d'intervention généré : " . $fileName
        ]);

        return 'uploads/interventions/' . $intervention['id'] . '/' . $fileName;
    }

    /**
     * Met à jour une intervention
     */
    public function update($id) {
        // Code de débogage temporaire
        error_log("DEBUG - Début de update() pour l'intervention $id");
        error_log("DEBUG - POST data: " . print_r($_POST, true));
        
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        error_log("DEBUG - Intervention récupérée: " . print_r($intervention, true));
        
        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // S'assurer que toutes les clés nécessaires existent
        $intervention = array_merge([
            'site_id' => null,
            'room_id' => null,
            'client_id' => null,
            'technician_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            'duration' => null,
            'description' => null,
            'title' => null
        ], $intervention);

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6 && !isAdmin()) { // 6 = Fermé
            $_SESSION['error'] = "Impossible de modifier une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Récupérer les données du formulaire
        $data = [
            'title' => $_POST['title'] ?? $intervention['title'],
            'client_id' => $_POST['client_id'] ?? $intervention['client_id'],
            'site_id' => $_POST['site_id'] ?? $intervention['site_id'],
            'room_id' => $_POST['room_id'] ?? $intervention['room_id'],
            'status_id' => $_POST['status_id'] ?? $intervention['status_id'],
            'priority_id' => $_POST['priority_id'] ?? $intervention['priority_id'],
            'type_id' => $_POST['type_id'] ?? $intervention['type_id'],
            'duration' => $_POST['duration'] ?? $intervention['duration'],
            'description' => $_POST['description'] ?? $intervention['description'],
            'demande_par' => $_POST['demande_par'] ?? $intervention['demande_par'],
            'ref_client' => $_POST['ref_client'] ?? $intervention['ref_client'],
            'contact_client' => $_POST['contact_client'] ?? $intervention['contact_client'],
            'date_planif' => !empty($_POST['date_planif']) ? $_POST['date_planif'] : $intervention['date_planif'] ?? null,
            'heure_planif' => !empty($_POST['heure_planif']) ? $_POST['heure_planif'] : $intervention['heure_planif'] ?? null
        ];

        // Traiter la date et l'heure de création
        $createdDate = $_POST['created_date'] ?? date('Y-m-d', strtotime($intervention['created_at']));
        $createdTime = $_POST['created_time'] ?? date('H:i', strtotime($intervention['created_at']));
        $data['created_at'] = $createdDate . ' ' . $createdTime . ':00';
        
        // Gérer le technician_id séparément pour s'assurer qu'il est correctement traité
        if (isset($_POST['technician_id']) && $_POST['technician_id'] !== '') {
            $data['technician_id'] = $_POST['technician_id'];
        } else {
            $data['technician_id'] = $intervention['technician_id'];
        }
        
        // Débogage pour les champs date_planif et heure_planif
        error_log("DEBUG - InterventionController::update - POST date_planif: " . ($_POST['date_planif'] ?? 'NON DÉFINI'));
        error_log("DEBUG - InterventionController::update - POST heure_planif: " . ($_POST['heure_planif'] ?? 'NON DÉFINI'));
        error_log("DEBUG - InterventionController::update - data date_planif: " . ($data['date_planif'] ?? 'NULL'));
        error_log("DEBUG - InterventionController::update - data heure_planif: " . ($data['heure_planif'] ?? 'NULL'));
        
        // Gérer le contract_id séparément pour s'assurer qu'il est correctement traité
        if (isset($_POST['contract_id']) && $_POST['contract_id'] !== '') {
            $data['contract_id'] = $_POST['contract_id'];
        } else {
            $data['contract_id'] = null;
        }

        // Vérifier si l'intervention est en train d'être fermée
        custom_log("DEBUG - update() - Vérification de la fermeture", "DEBUG");
        custom_log("DEBUG - update() - data['status_id']: " . ($data['status_id'] ?? 'NON DÉFINI'), "DEBUG");
        custom_log("DEBUG - update() - intervention['status_id']: " . ($intervention['status_id'] ?? 'NON DÉFINI'), "DEBUG");
        
        $isBeingClosed = isset($data['status_id']) && $data['status_id'] == 6 && $intervention['status_id'] != 6;
        custom_log("DEBUG - update() - isBeingClosed: " . ($isBeingClosed ? 'VRAI' : 'FAUX'), "DEBUG");
        
        // Si l'intervention est en train d'être fermée, vérifier que la durée est définie
        if ($isBeingClosed) {
            if (empty($data['duration'])) {
                $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir défini une durée.";
                header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
                exit;
            }
            
            // Vérifier qu'un technicien est assigné
            if (empty($data['technician_id'])) {
                $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir assigné un technicien.";
                header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
                exit;
            }
            
            // Calculer le nombre de tickets utilisés
            custom_log("DEBUG - update() - Calcul des tickets pour l'intervention $id", "DEBUG");
            custom_log("DEBUG - update() - Durée: " . $data['duration'], "DEBUG");
            custom_log("DEBUG - update() - Technicien ID: " . $data['technician_id'], "DEBUG");
            custom_log("DEBUG - update() - Type ID: " . $data['type_id'], "DEBUG");
            
            $ticketsUsed = $this->calculateTicketsUsed($data['duration'], $data['technician_id'], $data['type_id']);
            $data['tickets_used'] = $ticketsUsed;
            
            custom_log("DEBUG - update() - Tickets calculés: " . $ticketsUsed, "DEBUG");
            custom_log("DEBUG - update() - Data après calcul: " . print_r($data, true), "DEBUG");
            
            // Ajouter la date de fermeture
            $data['closed_at'] = date('Y-m-d H:i:s');

            // Déduire les tickets du contrat si un contrat est associé
            if (!empty($data['contract_id'])) {
                $this->deductTicketsFromContract($data['contract_id'], $ticketsUsed, $id);
            }
        }
        
        // Valider le format de l'email si renseigné
        if (!empty($data['contact_client'])) {
            if (!filter_var($data['contact_client'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Le format de l'email de contact est invalide.";
                header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
                exit;
            }
        }

        // Mettre à jour l'intervention
        $result = $this->interventionModel->update($id, $data);

        if ($result) {
            // Vérifier si des modifications ont été apportées
            $hasChanges = false;
            foreach ($data as $key => $value) {
                if (isset($intervention[$key]) && $intervention[$key] != $value) {
                    $hasChanges = true;
                    break;
                }
            }
            
            // Enregistrer les modifications dans l'historique seulement si des changements ont été effectués
            if ($hasChanges) {
                $this->recordChanges($id, $intervention, $data);
            }
            
            $_SESSION['success'] = "Intervention mise à jour avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la mise à jour de l'intervention.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    /**
     * Calcule le nombre de tickets utilisés en fonction de la durée et des coefficients
     * @param float $duration Durée en heures
     * @param int $technicianId ID du technicien
     * @param int $typeId ID du type d'intervention
     * @return int Nombre de tickets utilisés
     */
    private function calculateTicketsUsed($duration, $technicianId, $typeId) {
        custom_log("DEBUG - calculateTicketsUsed() - Paramètres: durée=$duration, technicien=$technicianId, type=$typeId", "DEBUG");
        
        // Récupérer le coefficient utilisateur
        $technician = $this->userModel->getUserById($technicianId);
        $coefUtilisateur = $technician['coef_utilisateur'] ?? 0;
        custom_log("DEBUG - calculateTicketsUsed() - Technicien: " . print_r($technician, true), "DEBUG");
        custom_log("DEBUG - calculateTicketsUsed() - Coef utilisateur: $coefUtilisateur", "DEBUG");

        // Récupérer le type d'intervention pour savoir s'il y a déplacement
        $type = $this->interventionModel->getTypeInfo($typeId);
        $requiresTravel = $type['requires_travel'] ?? false;
        custom_log("DEBUG - calculateTicketsUsed() - Type: " . print_r($type, true), "DEBUG");
        custom_log("DEBUG - calculateTicketsUsed() - Déplacement requis: " . ($requiresTravel ? 'OUI' : 'NON'), "DEBUG");

        // Récupérer le coefficient d'intervention depuis les paramètres
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'coef_intervention'");
        $stmt->execute();
        $coefIntervention = floatval($stmt->fetchColumn()) ?? 0;
        custom_log("DEBUG - calculateTicketsUsed() - Coef intervention: $coefIntervention", "DEBUG");

        // Calculer les tickets selon la formule
        if ($requiresTravel) {
            // Avec déplacement : durée + coef_utilisateur + 1 + coef_intervention
            $tickets = $duration + $coefUtilisateur + 1 + $coefIntervention;
            custom_log("DEBUG - calculateTicketsUsed() - Calcul avec déplacement: $duration + $coefUtilisateur + 1 + $coefIntervention = $tickets", "DEBUG");
        } else {
            // Sans déplacement : durée + coef_utilisateur + coef_intervention
            $tickets = $duration + $coefUtilisateur + $coefIntervention;
            custom_log("DEBUG - calculateTicketsUsed() - Calcul sans déplacement: $duration + $coefUtilisateur + $coefIntervention = $tickets", "DEBUG");
        }

        // Arrondir à l'entier supérieur
        $result = ceil($tickets);
        custom_log("DEBUG - calculateTicketsUsed() - Résultat final (arrondi): $result", "DEBUG");
        return $result;
    }

    /**
     * Enregistre les modifications dans l'historique
     */
    private function recordChanges($interventionId, $oldData, $newData) {
        // Code de débogage temporaire
        error_log("DEBUG - recordChanges() - oldData: " . print_r($oldData, true));
        error_log("DEBUG - recordChanges() - newData: " . print_r($newData, true));
        error_log("DEBUG - site_id existe dans oldData? " . (array_key_exists('site_id', $oldData) ? 'OUI' : 'NON'));
        error_log("DEBUG - site_id existe dans newData? " . (array_key_exists('site_id', $newData) ? 'OUI' : 'NON'));
        
        $fieldsToTrack = [
            'title' => 'Titre',
            'client_id' => 'Client',
            'site_id' => 'Site',
            'room_id' => 'Salle',
            'technician_id' => 'Technicien',
            'status_id' => 'Statut',
            'priority_id' => 'Priorité',
            'type_id' => 'Type',
            'duration' => 'Durée',
            'description' => 'Description',
            'demande_par' => 'Demande par',
            'contract_id' => 'Contrat',
            'date_planif' => 'Date planifiée',
            'heure_planif' => 'Heure planifiée',
            'created_at' => 'Date de création'
        ];

        $changes = [];
        foreach ($fieldsToTrack as $field => $label) {
            // Vérifier si le champ existe dans les nouvelles données
            if (isset($newData[$field])) {
                // Traitement spécial pour le champ description
                if ($field === 'description') {
                    // Pour la description, on vérifie simplement si elle a changé
                    if (!isset($oldData[$field]) || $oldData[$field] !== $newData[$field]) {
                        $changes[] = "Description modifiée";
                        
                        $sql = "INSERT INTO intervention_history (
                                    intervention_id, field_name, old_value, new_value, changed_by, description
                                ) VALUES (
                                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                                )";
                        
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':intervention_id' => $interventionId,
                            ':field_name' => $label,
                            ':old_value' => 'Ancienne description',
                            ':new_value' => 'Nouvelle description',
                            ':changed_by' => $_SESSION['user']['id'],
                            ':description' => "Description modifiée"
                        ]);
                    }
                } else {
                    // S'assurer que la clé existe dans oldData avant d'y accéder
                    $oldFieldValue = array_key_exists($field, $oldData) ? $oldData[$field] : null;
                    
                    // Pour les autres champs, on compare les valeurs d'affichage
                    $oldValue = $this->getDisplayValue($field, $oldFieldValue);
                    $newValue = $this->getDisplayValue($field, $newData[$field]);
                    
                    // Ne créer une entrée que si la valeur a réellement changé
                    if ($oldValue !== $newValue) {
                        $changes[] = "$label : $oldValue → $newValue";
                        
                        $sql = "INSERT INTO intervention_history (
                                    intervention_id, field_name, old_value, new_value, changed_by, description
                                ) VALUES (
                                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                                )";
                        
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':intervention_id' => $interventionId,
                            ':field_name' => $label,
                            ':old_value' => $oldValue,
                            ':new_value' => $newValue,
                            ':changed_by' => $_SESSION['user']['id'],
                            ':description' => "$label : $oldValue → $newValue"
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Récupère la valeur d'affichage d'un champ
     */
    private function getDisplayValue($field, $value) {
        // Code de débogage temporaire
        error_log("DEBUG - getDisplayValue() - field: $field, value: " . var_export($value, true));
        
        if ($value === null) {
            return 'Non défini';
        }

        switch ($field) {
            case 'client_id':
                $sql = "SELECT name FROM clients WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Client inconnu';
                
            case 'site_id':
                error_log("DEBUG - getDisplayValue() - site_id spécifique, value: " . var_export($value, true));
                if (empty($value)) return 'Non spécifié';
                $sql = "SELECT name FROM sites WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("DEBUG - getDisplayValue() - site_id résultat SQL: " . print_r($result, true));
                return $result ? $result['name'] : 'Site inconnu';
                
            case 'room_id':
                if (empty($value)) return 'Non spécifié';
                $sql = "SELECT name FROM rooms WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Salle inconnue';
                
            case 'technician_id':
                $sql = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Technicien inconnu';
                
            case 'status_id':
                $sql = "SELECT name FROM intervention_statuses WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Statut inconnu';
                
            case 'priority_id':
                $sql = "SELECT name FROM intervention_priorities WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Priorité inconnue';
                
            case 'type_id':
                $sql = "SELECT name FROM intervention_types WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Type inconnu';
                
            case 'contract_id':
                if (!$value) return 'Hors contrat';
                $sql = "SELECT name FROM contracts WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Contrat inconnu';
                
            case 'duration':
                return $value . ' heure(s)';
                
            case 'date_planif':
                return date('d/m/Y', strtotime($value));
                
            case 'heure_planif':
                return $value;
                
            case 'demande_par':
                return $value ?: 'Non spécifié';
                
            case 'created_at':
                return date('d/m/Y H:i', strtotime($value));
                
            default:
                return $value;
        }
    }

    /**
     * Récupère les commentaires d'une intervention
     */
    private function getComments($interventionId) {
        $sql = "SELECT c.*, 
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM intervention_comments c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.intervention_id = ?
                ORDER BY c.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les pièces jointes d'une intervention
     */
    private function getAttachments($interventionId) {
        return $this->interventionModel->getPiecesJointes($interventionId);
    }

    /**
     * Récupère l'historique d'une intervention
     */
    private function getHistory($interventionId) {
        $sql = "SELECT h.*, 
                CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
                FROM intervention_history h
                LEFT JOIN users u ON h.changed_by = u.id
                WHERE h.intervention_id = ?
                ORDER BY h.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un commentaire à une intervention
     */
    public function addComment($interventionId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($interventionId);
        
        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6) { // 6 = Fermé
            $_SESSION['error'] = "Impossible d'ajouter un commentaire à une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Récupérer les données du formulaire
        $comment = $_POST['comment'] ?? '';
        $visibleByClient = isset($_POST['visible_by_client']) ? 1 : 0;
        $isSolution = isset($_POST['is_solution']) ? 1 : 0;
        $isObservation = isset($_POST['is_observation']) ? 1 : 0;
        
        if (empty($comment)) {
            $_SESSION['error'] = "Le commentaire ne peut pas être vide.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Ajouter le commentaire
        $sql = "INSERT INTO intervention_comments (
                    intervention_id, comment, visible_by_client, is_solution, is_observation, created_by
                ) VALUES (
                    :intervention_id, :comment, :visible_by_client, :is_solution, :is_observation, :created_by
                )";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':intervention_id' => $interventionId,
            ':comment' => $comment,
            ':visible_by_client' => $visibleByClient,
            ':is_solution' => $isSolution,
            ':is_observation' => $isObservation,
            ':created_by' => $_SESSION['user']['id']
        ]);

        if ($result) {
            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $interventionId,
                ':field_name' => 'Commentaire',
                ':old_value' => '',
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Commentaire ajouté" . ($isSolution ? " (marqué comme solution)" : "") . ($visibleByClient ? " (visible par le client)" : "")
            ]);
            
            $_SESSION['success'] = "Commentaire ajouté avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout du commentaire.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
        exit;
    }

    /**
     * Ajoute une pièce jointe à une intervention
     */
    public function addAttachment($interventionId) {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($interventionId);
        
        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6) { // 6 = Fermé
            $_SESSION['error'] = "Impossible d'ajouter une pièce jointe à une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Vérifier si un fichier a été uploadé
        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Erreur lors de l'upload du fichier.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Récupérer les informations du fichier
        $file = $_FILES['attachment'];
        $originalFileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];

        // Vérifier la taille du fichier (max 10MB)
        $maxFileSize = 10 * 1024 * 1024; // 10MB en octets
        if ($fileSize > $maxFileSize) {
            $_SESSION['error'] = "Le fichier est trop volumineux. Taille maximale : 10MB.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Vérifier le type de fichier
        require_once INCLUDES_PATH . '/FileUploadValidator.php';
        
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        
        if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
            $_SESSION['error'] = "Ce format n'est pas accepté, rapprochez-vous de l'administrateur du site, ou utilisez un format compressé.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Créer le dossier de stockage s'il n'existe pas
        $uploadDir = __DIR__ . '/../uploads/interventions/' . $interventionId;
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Préparer le nom du fichier
        $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
        $fileName = pathinfo($originalFileName, PATHINFO_FILENAME);
        $fileName = str_replace(' ', '_', $fileName); // Remplacer les espaces par des underscores
        $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileName); // Supprimer les caractères spéciaux
        $baseFileName = $fileName;

        // Vérifier si le fichier existe déjà et incrémenter si nécessaire
        $counter = 1;
        while (file_exists($uploadDir . '/' . $fileName . '.' . $fileExtension)) {
            $fileName = $baseFileName . '_' . $counter;
            $counter++;
        }

        $finalFileName = $fileName . '.' . $fileExtension;
        $filePath = $uploadDir . '/' . $finalFileName;

        // Déplacer le fichier
        if (move_uploaded_file($fileTmpPath, $filePath)) {
            // Récupérer le nom personnalisé s'il existe
            $customName = isset($_POST['custom_name']) && !empty(trim($_POST['custom_name'])) 
                ? trim($_POST['custom_name']) 
                : null;
            
            // Utiliser le nom personnalisé s'il existe, sinon le nom original
            $displayName = $customName ?: $originalFileName;
            
            // Préparer les données pour la base
            $data = [
                'nom_fichier' => $originalFileName,
                'nom_personnalise' => $displayName,
                'chemin_fichier' => 'uploads/interventions/' . $interventionId . '/' . $finalFileName,
                'type_fichier' => $fileExtension,
                'taille_fichier' => $fileSize,
                'commentaire' => $_POST['description'] ?? null,
                'masque_client' => isset($_POST['masque_client']) ? 1 : 0,
                'created_by' => $_SESSION['user']['id']
            ];

            // Ajouter la pièce jointe via le modèle
            $pieceJointeId = $this->interventionModel->addPieceJointe($interventionId, $data);

            if ($pieceJointeId) {
                // Enregistrer l'action dans l'historique
                $sql = "INSERT INTO intervention_history (
                            intervention_id, field_name, old_value, new_value, changed_by, description
                        ) VALUES (
                            :intervention_id, 'attachment', '', :filename, :changed_by, 'Ajout de pièce jointe'
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':intervention_id' => $interventionId,
                    ':filename' => $displayName,
                    ':changed_by' => $_SESSION['user']['id']
                ]);
                
                $_SESSION['success'] = "Pièce jointe ajoutée avec succès.";
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout de la pièce jointe.";
                // Supprimer le fichier si l'insertion en base de données a échoué
                unlink($filePath);
            }
        } else {
            $_SESSION['error'] = "Erreur lors de l'upload du fichier.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
        exit;
    }

    /**
     * Ajoute plusieurs pièces jointes à une intervention (Drag & Drop)
     */
    public function addMultipleAttachments($interventionId) {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || (!isStaff() && !isAdmin())) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit;
        }

        try {
            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);
            
            if (!$intervention) {
                throw new Exception("Intervention non trouvée");
            }

            // Vérifier si l'intervention est fermée
            if ($intervention['status_id'] == 6) { // 6 = Fermé
                throw new Exception("Impossible d'ajouter une pièce jointe à une intervention fermée");
            }

            // Vérifier qu'il y a des fichiers
            if (!isset($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
                throw new Exception("Aucun fichier à uploader");
            }

            require_once INCLUDES_PATH . '/FileUploadValidator.php';
            
            $uploadedFiles = [];
            $errors = [];
            
            // Traiter chaque fichier
            foreach ($_FILES['attachments']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['attachments']['error'][$index] !== UPLOAD_ERR_OK) {
                    $errors[] = "Erreur lors de l'upload du fichier " . ($index + 1);
                    continue;
                }

                $originalFileName = $_FILES['attachments']['name'][$index];
                $fileSize = $_FILES['attachments']['size'][$index];
                $fileTmpPath = $tmpName;
                
                // Récupérer le nom personnalisé s'il existe
                $customName = isset($_POST['custom_names'][$index]) && !empty(trim($_POST['custom_names'][$index])) 
                    ? trim($_POST['custom_names'][$index]) 
                    : null;

                // Vérifier la taille du fichier
                $maxFileSize = 10 * 1024 * 1024; // 10MB
                if ($fileSize > $maxFileSize) {
                    $errors[] = "Le fichier '$originalFileName' est trop volumineux (max 10MB)";
                    continue;
                }

                // Vérifier l'extension
                $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                    $errors[] = "Le format du fichier '$originalFileName' n'est pas accepté";
                    continue;
                }

                // Créer le dossier de stockage s'il n'existe pas
                $uploadDir = __DIR__ . '/../uploads/interventions/' . $interventionId;
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Préparer le nom du fichier
                $fileName = pathinfo($originalFileName, PATHINFO_FILENAME);
                $fileName = str_replace(' ', '_', $fileName);
                $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileName);
                $baseFileName = $fileName;

                // Vérifier si le fichier existe déjà et incrémenter si nécessaire
                $counter = 1;
                while (file_exists($uploadDir . '/' . $fileName . '.' . $fileExtension)) {
                    $fileName = $baseFileName . '_' . $counter;
                    $counter++;
                }

                $finalFileName = $fileName . '.' . $fileExtension;
                $filePath = $uploadDir . '/' . $finalFileName;

                // Déplacer le fichier
                if (move_uploaded_file($fileTmpPath, $filePath)) {
                    // Utiliser le nom personnalisé s'il existe, sinon le nom original
                    $displayName = $customName ?: $originalFileName;
                    
                    // Préparer les données pour la base
                    $data = [
                        'nom_fichier' => $originalFileName,
                        'nom_personnalise' => $displayName,
                        'chemin_fichier' => 'uploads/interventions/' . $interventionId . '/' . $finalFileName,
                        'type_fichier' => $fileExtension,
                        'taille_fichier' => $fileSize,
                        'commentaire' => null, // Pas de commentaire pour les interventions
                        'masque_client' => 0, // Pas de masquage pour les interventions
                        'created_by' => $_SESSION['user']['id']
                    ];

                    // Ajouter la pièce jointe via le modèle
                    $pieceJointeId = $this->interventionModel->addPieceJointe($interventionId, $data);

                    if ($pieceJointeId) {
                        // Enregistrer l'action dans l'historique
                        $sql = "INSERT INTO intervention_history (
                                    intervention_id, field_name, old_value, new_value, changed_by, description
                                ) VALUES (
                                    :intervention_id, 'attachment', '', :filename, :changed_by, 'Ajout de pièce jointe'
                                )";
                        
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':intervention_id' => $interventionId,
                            ':filename' => $displayName,
                            ':changed_by' => $_SESSION['user']['id']
                        ]);
                        
                        $uploadedFiles[] = $displayName;
                    } else {
                        $errors[] = "Erreur lors de l'enregistrement du fichier '$originalFileName'";
                    }
                } else {
                    $errors[] = "Erreur lors du déplacement du fichier '$originalFileName'";
                }
            }

            // Retourner le résultat
            header('Content-Type: application/json');
            if (empty($errors) && !empty($uploadedFiles)) {
                echo json_encode([
                    'success' => true,
                    'message' => count($uploadedFiles) . ' fichier(s) uploadé(s) avec succès',
                    'uploaded_files' => $uploadedFiles
                ]);
            } else {
                $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Aucun fichier uploadé';
                echo json_encode([
                    'success' => false,
                    'error' => $errorMessage,
                    'uploaded_files' => $uploadedFiles
                ]);
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout des pièces jointes : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Télécharge une pièce jointe
     */
    public function download($attachmentId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer la pièce jointe via le modèle
        $attachment = $this->interventionModel->getPieceJointeById($attachmentId);

        if (!$attachment || ($attachment['type_liaison'] !== 'intervention' && $attachment['type_liaison'] !== 'bi')) {
            $_SESSION['error'] = "La pièce jointe n'existe pas.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($attachment['entite_id']);

        // Vérifier les permissions
        if (!$this->checkPermission('technicien', 'view_interventions') && 
            $_SESSION['user']['id'] !== $intervention['technician_id']) {
            $_SESSION['error'] = "Vous n'avez pas la permission de télécharger cette pièce jointe.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Construire le chemin du fichier
        $filePath = __DIR__ . '/../' . $attachment['chemin_fichier'];

        if (!file_exists($filePath)) {
            $_SESSION['error'] = "Le fichier n'existe pas.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $intervention['id']);
            exit;
        }

        // Définir les en-têtes pour le téléchargement
        header('Content-Type: ' . mime_content_type($filePath));
        // Utiliser le nom du fichier physique pour le téléchargement
        $downloadName = $attachment['nom_fichier'];
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Lire et envoyer le fichier
        readfile($filePath);
        exit;
    }

    /**
     * Affiche l'aperçu d'une pièce jointe
     */
    public function preview($attachmentId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer la pièce jointe via le modèle
        $attachment = $this->interventionModel->getPieceJointeById($attachmentId);

        if (!$attachment || ($attachment['type_liaison'] !== 'intervention' && $attachment['type_liaison'] !== 'bi')) {
            $_SESSION['error'] = "La pièce jointe n'existe pas.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Construire le chemin du fichier
        $filePath = __DIR__ . '/../' . $attachment['chemin_fichier'];

        // Log pour débogage
        error_log("Tentative d'ouverture du fichier : " . $filePath);
        error_log("Type MIME : " . mime_content_type($filePath));

        if (!file_exists($filePath)) {
            error_log("Le fichier n'existe pas : " . $filePath);
            $_SESSION['error'] = "Le fichier n'existe pas.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $attachment['intervention_id']);
            exit;
        }

        // Définir les en-têtes pour l'aperçu
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        // Utiliser le nom du fichier physique pour la prévisualisation
        $previewName = $attachment['nom_fichier'];
        header('Content-Disposition: inline; filename="' . $previewName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        // En-têtes de sécurité pour éviter les faux positifs
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        // Ignorer les signatures numériques
        header('X-PDF-Signature-Validation: ignore');

        // Lire et envoyer le fichier
        readfile($filePath);
        exit;
    }

    /**
     * Supprime un commentaire
     */
    public function deleteComment($commentId) {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Récupérer le commentaire
        $sql = "SELECT * FROM intervention_comments WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$comment) {
            $_SESSION['error'] = "Commentaire introuvable.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Supprimer le commentaire
        $sql = "DELETE FROM intervention_comments WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$commentId]);

        if ($result) {
            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $comment['intervention_id'],
                ':field_name' => 'Commentaire',
                ':old_value' => $comment['comment'],
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Commentaire supprimé"
            ]);
            
            $_SESSION['success'] = "Commentaire supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du commentaire.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $comment['intervention_id']);
        exit;
    }

    /**
     * Supprime une pièce jointe
     */
    public function deleteAttachment($attachmentId) {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Récupérer la pièce jointe via le modèle
        $attachment = $this->interventionModel->getPieceJointeById($attachmentId);
        
        if (!$attachment || ($attachment['type_liaison'] !== 'intervention' && $attachment['type_liaison'] !== 'bi')) {
            $_SESSION['error'] = "Pièce jointe introuvable.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        try {
            // Supprimer la pièce jointe via le modèle
            $result = $this->interventionModel->deletePieceJointe($attachmentId, $attachment['entite_id']);

            if ($result) {
                // Enregistrer l'action dans l'historique
                $sql = "INSERT INTO intervention_history (
                            intervention_id, field_name, old_value, new_value, changed_by, description
                        ) VALUES (
                            :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':intervention_id' => $attachment['entite_id'],
                    ':field_name' => 'Pièce jointe',
                    ':old_value' => $attachment['nom_fichier'],
                    ':new_value' => '',
                    ':changed_by' => $_SESSION['user']['id'],
                    ':description' => "Pièce jointe supprimée : " . $attachment['nom_fichier']
                ]);
                
                $_SESSION['success'] = "Pièce jointe supprimée avec succès.";
            } else {
                $_SESSION['error'] = "Erreur lors de la suppression de la pièce jointe.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la suppression de la pièce jointe : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $attachment['entite_id']);
        exit;
    }

    /**
     * Récupère les informations d'un type d'intervention
     */
    public function getTypeInfo($typeId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les informations du type
        $sql = "SELECT * FROM intervention_types WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$typeId]);
        $type = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$type) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Type introuvable']);
            exit;
        }

        // Retourner les informations au format JSON
        header('Content-Type: application/json');
        echo json_encode($type);
    }

    /**
     * Récupère les sites d'un client
     */
    public function getSites($clientId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les sites
        $sites = $this->siteModel->getSitesByClientId($clientId);
        
        header('Content-Type: application/json');
        echo json_encode(['sites' => $sites]);
        exit;
    }

    /**
     * Récupère les salles d'un site
     */
    public function getRooms($siteId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les salles
        $rooms = $this->roomModel->getRoomsBySiteId($siteId);
        
        // Retourner les salles au format JSON
        header('Content-Type: application/json');
        echo json_encode(['rooms' => $rooms]);
    }

    /**
     * Vérifie les permissions d'un utilisateur
     */
    private function checkPermission($module, $action) {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        // Les administrateurs ont toutes les permissions
        if (isAdmin()) {
            return true;
        }

        // Vérifier les permissions spécifiques
        $permission = 'tech_' . $action; // Utiliser le préfixe 'tech_' au lieu de 'technicien_'
        
        // Log temporaire pour debug
        custom_log("Vérification permission pour {$permission} : " . json_encode($_SESSION['user']['permissions']), 'DEBUG');
        
        return isset($_SESSION['user']['permissions']['rights'][$permission]) && $_SESSION['user']['permissions']['rights'][$permission] === true;
    }

    /**
     * Récupère les contrats d'un client
     */
    public function getContracts($clientId, $siteId = null, $roomId = null) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer tous les contrats du client
        $contracts = $this->contractModel->getContractsByClientId($clientId, $siteId, $roomId);
        
        // Retourner les contrats au format JSON
        header('Content-Type: application/json');
        echo json_encode($contracts);
    }

    /**
     * Récupère le contrat associé à une salle
     */
    public function getContractByRoom($roomId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer le contrat
        $contract = $this->contractModel->getContractByRoomId($roomId);
        
        // Retourner le contrat au format JSON
        header('Content-Type: application/json');
        echo json_encode($contract);
    }

    /**
     * Récupère les informations détaillées d'un contrat via AJAX
     */
    public function getContractInfo($contractId) {
        // Vérifier les permissions
        $this->checkAccess();

        header('Content-Type: application/json');
        
        try {
            // Récupérer les infos détaillées du contrat
            $contract = $this->contractModel->getContractById($contractId);
            
            if (!$contract) {
                http_response_code(404);
                echo json_encode(['error' => 'Contrat non trouvé']);
                return;
            }
            
            // Formater les données pour l'affichage
            $contractInfo = [
                'id' => $contract['id'],
                'name' => $contract['name'],
                'type_name' => $contract['contract_type_name'] ?? null,
                'start_date' => $contract['start_date'] ?? null,
                'end_date' => $contract['end_date'] ?? null,
                'tickets_remaining' => $contract['tickets_remaining'] ?? null,
                'comment' => $contract['comment'] ?? null,
                'status' => $contract['status'] ?? null
            ];
            
            echo json_encode($contractInfo);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Récupère les contacts d'un client
     */
    public function getContacts($clientId) {
        // Vérifier les permissions
        $this->checkAccess();

        header('Content-Type: application/json');
        
        try {
            // Récupérer les contacts du client
            $sql = "SELECT id, first_name, last_name, email 
                    FROM contacts 
                    WHERE client_id = ? AND status = 1 
                    ORDER BY last_name, first_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($contacts);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Affiche le formulaire de création d'une intervention
     */
    public function create() {
        // Vérifier les permissions
        checkInterventionManagementAccess();
        $clients = $this->clientModel->getAllClientsWithStats();
        $sites = [];
        $rooms = [];
        $technicians = $this->userModel->getTechnicians();
        
        // Récupérer les statuts, priorités et types
        $statuses = $this->getAllStatuses();

        $sql = "SELECT * FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT * FROM intervention_types ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les durées
        $durations = $this->durationModel->getAll();

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/add.php';
    }

    /**
     * Enregistre une nouvelle intervention
     */
    public function store() {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer les données du formulaire
        $data = [
            'title' => $_POST['title'] ?? '',
            'client_id' => $_POST['client_id'] ?? null,
            'site_id' => $_POST['site_id'] ?? null,
            'room_id' => $_POST['room_id'] ?? null,
            'technician_id' => $_POST['technician_id'] ?? null,
            'status_id' => $_POST['status_id'] ?? 1, // 1 = Nouveau par défaut
            'priority_id' => $_POST['priority_id'] ?? 2, // 2 = Normal par défaut
            'type_id' => $_POST['type_id'] ?? null,
            'duration' => $_POST['duration'] ?? 0, // 0 par défaut au lieu de null
            'description' => $_POST['description'] ?? '',
            'demande_par' => $_POST['demande_par'] ?? null,
            'ref_client' => $_POST['ref_client'] ?? null,
            'contact_client' => $_POST['contact_client'] ?? null,
            'contract_id' => $_POST['contract_id'] ?? null,
            'date_planif' => !empty($_POST['date_planif']) ? $_POST['date_planif'] : null,
            'heure_planif' => !empty($_POST['heure_planif']) ? $_POST['heure_planif'] : null
        ];

        // Traiter la date et l'heure de création
        $createdDate = $_POST['created_date'] ?? date('Y-m-d');
        $createdTime = $_POST['created_time'] ?? date('H:i');
        $data['created_at'] = $createdDate . ' ' . $createdTime . ':00';

        // Valider les données requises
        if (empty($data['title'])) {
            $_SESSION['error'] = "Le titre est obligatoire.";
            header('Location: ' . BASE_URL . 'interventions/add');
            exit;
        }

        if (empty($data['client_id'])) {
            $_SESSION['error'] = "Le client est obligatoire.";
            header('Location: ' . BASE_URL . 'interventions/add');
            exit;
        }

        if (empty($data['type_id'])) {
            $_SESSION['error'] = "Le type d'intervention est obligatoire.";
            header('Location: ' . BASE_URL . 'interventions/add');
            exit;
        }
        
        // Valider le format de l'email si renseigné
        if (!empty($data['contact_client'])) {
            if (!filter_var($data['contact_client'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Le format de l'email de contact est invalide.";
                header('Location: ' . BASE_URL . 'interventions/add');
                exit;
            }
        }

        // Valider le contrat (peut être un ID numérique)
        if (empty($data['contract_id'])) {
            $_SESSION['error'] = "Le contrat est obligatoire.";
            header('Location: ' . BASE_URL . 'interventions/add');
            exit;
        }

        // Vérifier si l'intervention est en train d'être créée avec le statut fermé
        if ($data['status_id'] == 6) { // 6 = Fermé
            // Vérifier que la durée est définie
            if (empty($data['duration'])) {
                $_SESSION['error'] = "Impossible de créer une intervention fermée sans avoir défini une durée.";
                header('Location: ' . BASE_URL . 'interventions/add');
                exit;
            }
            
            // Vérifier qu'un technicien est assigné
            if (empty($data['technician_id'])) {
                $_SESSION['error'] = "Impossible de créer une intervention fermée sans avoir assigné un technicien.";
                header('Location: ' . BASE_URL . 'interventions/add');
                exit;
            }
            
            // Calculer le nombre de tickets utilisés
            $ticketsUsed = $this->calculateTicketsUsed($data['duration'], $data['technician_id'], $data['type_id']);
            $data['tickets_used'] = $ticketsUsed;
            
            // Ajouter la date de fermeture
            $data['closed_at'] = date('Y-m-d H:i:s');
        }

        // Créer l'intervention
        $sql = "INSERT INTO interventions (
                    title, client_id, site_id, room_id, technician_id, status_id, 
                    priority_id, type_id, duration, description, demande_par, ref_client, contact_client, 
                    contract_id, reference, date_planif, heure_planif, tickets_used, closed_at, created_at
                ) VALUES (
                    :title, :client_id, :site_id, :room_id, :technician_id, :status_id, 
                    :priority_id, :type_id, :duration, :description, :demande_par, :ref_client, :contact_client, 
                    :contract_id, :reference, :date_planif, :heure_planif, :tickets_used, :closed_at, :created_at
                )";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':title' => $data['title'],
            ':client_id' => $data['client_id'],
            ':site_id' => $data['site_id'],
            ':room_id' => $data['room_id'],
            ':technician_id' => $data['technician_id'],
            ':status_id' => $data['status_id'],
            ':priority_id' => $data['priority_id'],
            ':type_id' => $data['type_id'],
            ':duration' => $data['duration'],
            ':description' => $data['description'],
            ':demande_par' => $data['demande_par'],
            ':ref_client' => $data['ref_client'],
            ':contact_client' => $data['contact_client'],
            ':contract_id' => $data['contract_id'],
            ':reference' => $this->interventionModel->generateReference($data['client_id']),
            ':date_planif' => $data['date_planif'],
            ':heure_planif' => $data['heure_planif'],
            ':tickets_used' => $data['tickets_used'] ?? null,
            ':closed_at' => $data['closed_at'] ?? null,
            ':created_at' => $data['created_at']
        ]);

        if ($result) {
            $interventionId = $this->db->lastInsertId();
            
            // Déduire les tickets du contrat si l'intervention est créée avec le statut fermé
            if ($data['status_id'] == 6 && !empty($data['contract_id']) && !empty($data['tickets_used'])) {
                $this->deductTicketsFromContract($data['contract_id'], $data['tickets_used'], $interventionId);
            }
            
            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $interventionId,
                ':field_name' => 'Création',
                ':old_value' => '',
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Intervention créée"
            ]);
            
            $_SESSION['success'] = "Intervention créée avec succès.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
        } else {
            $_SESSION['error'] = "Erreur lors de la création de l'intervention.";
            header('Location: ' . BASE_URL . 'interventions/add');
        }
        exit;
    }

    /**
     * Modifie un commentaire
     */
    public function editComment($commentId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer le commentaire
        $sql = "SELECT * FROM intervention_comments WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$comment) {
            $_SESSION['error'] = "Commentaire introuvable.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($comment['intervention_id']);
        
        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6) { // 6 = Fermé
            $_SESSION['error'] = "Impossible de modifier un commentaire d'une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $intervention['id']);
            exit;
        }

        // Récupérer les données du formulaire
        $newComment = $_POST['comment'] ?? '';
        $visibleByClient = isset($_POST['visible_by_client']) ? 1 : 0;
        $isSolution = isset($_POST['is_solution']) ? 1 : 0;
        $isObservation = isset($_POST['is_observation']) ? 1 : 0;
        
        if (empty($newComment)) {
            $_SESSION['error'] = "Le commentaire ne peut pas être vide.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $intervention['id']);
            exit;
        }

        // Mettre à jour le commentaire
        $sql = "UPDATE intervention_comments SET 
                comment = :comment,
                visible_by_client = :visible_by_client,
                is_solution = :is_solution,
                is_observation = :is_observation
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':comment' => $newComment,
            ':visible_by_client' => $visibleByClient,
            ':is_solution' => $isSolution,
            ':is_observation' => $isObservation,
            ':id' => $commentId
        ]);

        if ($result) {
            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $intervention['id'],
                ':field_name' => 'Commentaire',
                ':old_value' => $comment['comment'],
                ':new_value' => $newComment,
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Commentaire modifié" . ($isSolution ? " (marqué comme solution)" : "") . ($visibleByClient ? " (visible par le client)" : "")
            ]);
            
            $_SESSION['success'] = "Commentaire modifié avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la modification du commentaire.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $intervention['id']);
        exit;
    }

    /**
     * Récupère tous les statuts disponibles
     */
    public function getAllStatuses() {
        $sql = "SELECT * FROM intervention_statuses ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * S'auto-affecter une intervention
     */
    public function assignToMe($id) {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        
        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6) {
            $_SESSION['error'] = "Impossible de modifier une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Vérifier si l'intervention est déjà affectée au technicien connecté
        if ($intervention['technician_id'] == $_SESSION['user']['id']) {
            $_SESSION['info'] = "Cette intervention vous est déjà affectée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Mettre à jour l'intervention avec le technicien actuel
        $sql = "UPDATE interventions SET technician_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$_SESSION['user']['id'], $id]);

        if ($result) {
            $_SESSION['success'] = "Vous avez été affecté à cette intervention.";
        } else {
            $_SESSION['error'] = "Une erreur est survenue lors de l'affectation.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    /**
     * Ferme une intervention
     */
    public function close($id) {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        
        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Vérifier si l'intervention est déjà fermée
        if ($intervention['status_id'] == 6) {
            $_SESSION['info'] = "Cette intervention est déjà fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Vérifier si l'intervention est affectée au technicien connecté
        if ($intervention['technician_id'] != $_SESSION['user']['id'] && !isAdmin()) {
            $_SESSION['error'] = "Vous ne pouvez fermer que les interventions qui vous sont affectées.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Vérifier tous les prérequis
        if (empty($intervention['type_id'])) {
            $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir défini un type d'intervention.";
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }

        if (empty($intervention['duration'])) {
            $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir défini une durée.";
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }

        if (empty($intervention['technician_id'])) {
            $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir assigné un technicien.";
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }

        // Calculer le nombre de tickets utilisés
        error_log("DEBUG - close() - Calcul des tickets pour l'intervention $id");
        error_log("DEBUG - close() - Durée: " . $intervention['duration']);
        error_log("DEBUG - close() - Technicien ID: " . $intervention['technician_id']);
        error_log("DEBUG - close() - Type ID: " . $intervention['type_id']);
        
        $ticketsUsed = $this->calculateTicketsUsed(
            $intervention['duration'],
            $intervention['technician_id'],
            $intervention['type_id']
        );
        
        error_log("DEBUG - close() - Tickets calculés: " . $ticketsUsed);

        // Mettre à jour l'intervention
        $sql = "UPDATE interventions SET 
                status_id = 6, 
                closed_at = NOW(),
                tickets_used = :tickets_used 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tickets_used' => $ticketsUsed,
            ':id' => $id
        ]);
        
        error_log("DEBUG - close() - Résultat de la mise à jour: " . ($result ? 'SUCCÈS' : 'ÉCHEC'));

        if ($result) {
            // Déduire les tickets du contrat si un contrat est associé
            if (!empty($intervention['contract_id'])) {
                $this->deductTicketsFromContract($intervention['contract_id'], $ticketsUsed, $id);
            }

            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $id,
                ':field_name' => 'Statut',
                ':old_value' => $this->getDisplayValue('status_id', $intervention['status_id']),
                ':new_value' => $this->getDisplayValue('status_id', 6),
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Intervention fermée avec {$ticketsUsed} tickets utilisés"
            ]);

            $_SESSION['success'] = "L'intervention a été fermée avec succès.";
        } else {
            $_SESSION['error'] = "Une erreur est survenue lors de la fermeture de l'intervention.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    public function generateReport($id) {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        
        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Générer le PDF
        $pdfPath = $this->generateInterventionReport($intervention);

        // Enregistrer le message de succès
        $_SESSION['success'] = "Le bon d'intervention a été généré avec succès.";

        // Lire et afficher le PDF
        $fullPath = __DIR__ . '/../' . $pdfPath;
        if (file_exists($fullPath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($pdfPath) . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($fullPath);
            exit;
        } else {
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }
    }

    /**
     * Déduit les tickets utilisés d'un contrat
     */
    private function deductTicketsFromContract($contractId, $ticketsUsed, $interventionId = null) {
        error_log("DEBUG - deductTicketsFromContract appelée avec: contractId=$contractId, ticketsUsed=$ticketsUsed");
        
        if (!$contractId) {
            error_log("DEBUG - deductTicketsFromContract: Pas de contrat, pas de déduction");
            return; // Pas de contrat, pas de déduction
        }
        
        // Vérifier si le contrat est de type ticket (tickets_number > 0)
        $sql = "SELECT tickets_number FROM contracts WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':contract_id' => $contractId]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract || $contract['tickets_number'] <= 0) {
            error_log("DEBUG - deductTicketsFromContract: Contrat non-ticket ou inexistant, pas de déduction");
            // Ce n'est pas un contrat de type ticket, pas de déduction
            return;
        }
        
        error_log("DEBUG - deductTicketsFromContract: Enregistrement de l'historique AVANT mise à jour");
        
        // Enregistrer la déduction dans l'historique du contrat AVANT de modifier les tickets
        $contractModel = new ContractModel($this->db);
        
        // Construire le commentaire avec le code d'intervention si disponible
        $comment = 'Déduction automatique - Intervention fermée';
        if ($interventionId) {
            // Récupérer le code d'intervention
            $stmt = $this->db->prepare("SELECT reference FROM interventions WHERE id = ?");
            $stmt->execute([$interventionId]);
            $intervention = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($intervention && !empty($intervention['reference'])) {
                $comment = $intervention['reference'] . ' - ' . $comment;
            }
        }
        
        $historyResult = $contractModel->recordTicketDeduction($contractId, $ticketsUsed, $comment);
        
        if ($historyResult) {
            error_log("DEBUG - deductTicketsFromContract: Enregistrement dans l'historique réussi");
        } else {
            error_log("ERROR - deductTicketsFromContract: Échec de l'enregistrement dans l'historique");
        }
        
        error_log("DEBUG - deductTicketsFromContract: Mise à jour des tickets restants");
        
        // Maintenant mettre à jour les tickets restants
        $sql = "UPDATE contracts SET tickets_remaining = tickets_remaining - :tickets_used WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tickets_used' => $ticketsUsed,
            ':contract_id' => $contractId
        ]);
        
        if ($result) {
            error_log("DEBUG - deductTicketsFromContract: Mise à jour des tickets réussie");
        } else {
            error_log("ERROR - deductTicketsFromContract: Échec de la mise à jour des tickets");
        }
    }

    /**
     * Force le nombre de tickets utilisés pour une intervention fermée (admin seulement)
     */
    public function forceTickets($id) {
        // Debug: Log de début
        error_log("DEBUG: forceTickets appelé avec ID: " . $id);
        
        // Vérifier les permissions
        $this->checkAccess();
        
        // Vérifier que l'utilisateur est admin
        if (!isAdmin()) {
            error_log("DEBUG: Utilisateur non admin: " . (isAdmin() ? "admin" : "non-admin"));
            $_SESSION['error'] = "Seuls les administrateurs peuvent forcer les tickets utilisés.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }
        
        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        if (!$intervention) {
            error_log("DEBUG: Intervention non trouvée: " . $id);
            $_SESSION['error'] = "Intervention non trouvée.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }
        
        // Vérifier que l'intervention est fermée
        if ($intervention['status_id'] != 6) {
            error_log("DEBUG: Intervention non fermée, status_id: " . $intervention['status_id']);
            $_SESSION['error'] = "Seules les interventions fermées peuvent avoir leurs tickets forcés.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: Méthode POST détectée");
            error_log("DEBUG: POST data: " . print_r($_POST, true));
            
            $newTicketsUsed = (int)($_POST['tickets_used'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            
            error_log("DEBUG: newTicketsUsed: " . $newTicketsUsed);
            error_log("DEBUG: reason: " . $reason);
            
            // Validation
            if ($newTicketsUsed < 0) {
                error_log("DEBUG: Tickets négatifs rejetés");
                $_SESSION['error'] = "Le nombre de tickets utilisés ne peut pas être négatif.";
                header('Location: ' . BASE_URL . 'interventions/view/' . $id);
                exit;
            }
            
            if (empty($reason)) {
                error_log("DEBUG: Raison vide rejetée");
                $_SESSION['error'] = "La raison de la modification est obligatoire.";
                header('Location: ' . BASE_URL . 'interventions/view/' . $id);
                exit;
            }
            
            // Calculer la différence
            $oldTicketsUsed = (int)($intervention['tickets_used'] ?? 0);
            $difference = $newTicketsUsed - $oldTicketsUsed;
            
            error_log("DEBUG: oldTicketsUsed: " . $oldTicketsUsed);
            error_log("DEBUG: difference: " . $difference);
            
            try {
                $this->db->beginTransaction();
                
                // Mettre à jour les tickets utilisés de l'intervention
                $updateQuery = "UPDATE interventions SET tickets_used = :tickets_used, updated_at = NOW() WHERE id = :id";
                $stmt = $this->db->prepare($updateQuery);
                $stmt->bindParam(':tickets_used', $newTicketsUsed, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $result = $stmt->execute();
                
                error_log("DEBUG: Update intervention result: " . ($result ? 'success' : 'failed'));
                
                // Mettre à jour les tickets utilisés du contrat (seulement si c'est un contrat de type ticket)
                if ($intervention['contract_id']) {
                    // Vérifier si le contrat est de type ticket
                    $contract = $this->contractModel->getContractById($intervention['contract_id']);
                    if ($contract && $contract['tickets_number'] > 0) {
                        $contractQuery = "UPDATE contracts SET tickets_remaining = tickets_remaining - :difference WHERE id = :contract_id";
                        $stmt = $this->db->prepare($contractQuery);
                        $stmt->bindParam(':difference', $difference, PDO::PARAM_INT);
                        $stmt->bindParam(':contract_id', $intervention['contract_id'], PDO::PARAM_INT);
                        $result = $stmt->execute();
                        
                        error_log("DEBUG: Update contract result: " . ($result ? 'success' : 'failed'));
                        
                        // Enregistrer la modification dans l'historique du contrat
                        if ($difference != 0) {
                            // Construire le message avec la référence de l'intervention
                            $interventionRef = $intervention['reference'] ?? '#' . $intervention['id'];
                            $message = $interventionRef . ' - Modification forcée des tickets : ' . $reason;
                            
                            $this->contractModel->recordTicketModification(
                                $intervention['contract_id'], 
                                $difference, 
                                $message
                            );
                        }
                    } else {
                        error_log("DEBUG: Contrat non-ticket, pas de mise à jour des tickets");
                    }
                }
                
                // Enregistrer l'historique de la modification (optionnel)
                try {
                    $historyDescription = "Changement manuel tickets utilisés : " . $newTicketsUsed . " avant : " . $oldTicketsUsed;
                    if (!empty($reason)) {
                        $historyDescription .= "\nRaison : " . $reason;
                    }
                    
                    $historyQuery = "INSERT INTO intervention_history (intervention_id, field_name, old_value, new_value, changed_by, description, created_at) 
                                   VALUES (:intervention_id, 'tickets_used', :old_value, :new_value, :changed_by, :description, NOW())";
                    $stmt = $this->db->prepare($historyQuery);
                    $stmt->bindParam(':intervention_id', $id, PDO::PARAM_INT);
                    $stmt->bindParam(':old_value', $oldTicketsUsed, PDO::PARAM_INT);
                    $stmt->bindParam(':new_value', $newTicketsUsed, PDO::PARAM_INT);
                    $stmt->bindParam(':changed_by', $_SESSION['user']['id'], PDO::PARAM_INT);
                    $stmt->bindParam(':description', $historyDescription, PDO::PARAM_STR);
                    $result = $stmt->execute();
                    
                    error_log("DEBUG: Insert history result: " . ($result ? 'success' : 'failed'));
                } catch (Exception $historyError) {
                    error_log("DEBUG: Erreur lors de l'insertion dans l'historique : " . $historyError->getMessage());
                    // On continue même si l'historique échoue
                }
                
                $this->db->commit();
                error_log("DEBUG: Transaction commité avec succès");
                
                $_SESSION['success'] = "Tickets utilisés modifiés avec succès. Différence : " . ($difference >= 0 ? '+' : '') . $difference . " tickets.";
                
            } catch (Exception $e) {
                $this->db->rollBack();
                error_log("DEBUG: Exception lors du forçage des tickets : " . $e->getMessage());
                error_log("DEBUG: Stack trace : " . $e->getTraceAsString());
                error_log("Erreur lors du forçage des tickets : " . $e->getMessage());
                $_SESSION['error'] = "Erreur lors de la modification des tickets utilisés. Détails : " . $e->getMessage();
            }
            
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }
        
        error_log("DEBUG: Méthode non POST, redirection");
        // Si ce n'est pas un POST, rediriger vers la vue
        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    /**
     * Supprime une intervention annulée (admin seulement)
     * Re-crédite les tickets si l'intervention était fermée
     */
    public function delete($id) {
        // Vérifier les permissions - admin seulement
        if (!isset($_SESSION['user']) || !isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des interventions.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        
        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        // Vérifier que l'intervention est annulée (status_id = 7)
        if ($intervention['status_id'] != 7) {
            $_SESSION['error'] = "Seules les interventions annulées peuvent être supprimées.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        try {
            $this->db->beginTransaction();

            // Si l'intervention a des tickets utilisés et un contrat associé ET si c'est un contrat de type ticket, re-créditer les tickets
            if (!empty($intervention['tickets_used']) && !empty($intervention['contract_id'])) {
                // Vérifier si le contrat est de type ticket
                $contract = $this->contractModel->getContractById($intervention['contract_id']);
                if ($contract && $contract['tickets_number'] > 0) {
                    $ticketsToRecredit = $intervention['tickets_used'];
                    
                    // Mettre à jour le nombre de tickets restants dans le contrat
                    $sql = "UPDATE contracts SET tickets_remaining = tickets_remaining + :tickets_used WHERE id = :contract_id";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        ':tickets_used' => $ticketsToRecredit,
                        ':contract_id' => $intervention['contract_id']
                    ]);

                    // Enregistrer le re-crédit dans l'historique du contrat
                    $reference = $intervention['reference'] ?? "ID: {$id}";
                    $this->contractModel->recordTicketAddition(
                        $intervention['contract_id'], 
                        $ticketsToRecredit, 
                        date('Y-m-d'),
                        "Re-crédit automatique - Suppression intervention annulée {$reference}"
                    );
                }
            }

            // Supprimer les commentaires de l'intervention
            $sql = "DELETE FROM intervention_comments WHERE intervention_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);

            // Supprimer l'historique de l'intervention
            $sql = "DELETE FROM intervention_history WHERE intervention_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);

            // Récupérer et supprimer les pièces jointes physiques
            $sql = "SELECT pj.* FROM pieces_jointes pj
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    WHERE lpj.type_liaison = 'intervention' AND lpj.entite_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);
            $piecesJointes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Supprimer les fichiers physiques
            foreach ($piecesJointes as $pieceJointe) {
                $filePath = __DIR__ . '/../' . $pieceJointe['chemin_fichier'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Supprimer les pièces jointes de l'intervention
            $sql = "DELETE pj FROM pieces_jointes pj 
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                    WHERE lpj.type_liaison = 'intervention' AND lpj.entite_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);

            // Supprimer les liaisons de pièces jointes
            $sql = "DELETE FROM liaisons_pieces_jointes 
                    WHERE type_liaison = 'intervention' AND entite_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);

            // Supprimer l'intervention elle-même
            $sql = "DELETE FROM interventions WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            $this->db->commit();

            // Message de succès
            $message = "L'intervention a été supprimée avec succès.";
            if (!empty($intervention['tickets_used']) && !empty($intervention['contract_id'])) {
                $message .= " {$intervention['tickets_used']} tickets ont été re-crédités au contrat.";
            }
            $_SESSION['success'] = $message;

        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la suppression de l'intervention : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la suppression de l'intervention.";
        }

        header('Location: ' . BASE_URL . 'interventions');
        exit;
    }

    /**
     * Récupère les informations d'une pièce jointe
     */
    public function getAttachmentInfo($attachmentId) {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || (!isStaff() && !isAdmin())) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        try {
            $attachment = $this->interventionModel->getPieceJointeById($attachmentId);
            
            if (!$attachment) {
                throw new Exception("Pièce jointe non trouvée");
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'attachment' => $attachment
            ]);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des informations de la pièce jointe : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Met à jour le nom d'une pièce jointe
     */
    public function updateAttachmentName($attachmentId) {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || (!isStaff() && !isAdmin())) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit;
        }

        try {
            // Récupérer les données JSON
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['nom_fichier']) || empty(trim($input['nom_fichier']))) {
                throw new Exception("Le nom du fichier ne peut pas être vide");
            }

            $newName = trim($input['nom_fichier']);

            // Vérifier que la pièce jointe existe
            $attachment = $this->interventionModel->getPieceJointeById($attachmentId);
            if (!$attachment) {
                throw new Exception("Pièce jointe non trouvée");
            }

            // Mettre à jour le nom
            $success = $this->interventionModel->updateAttachmentName($attachmentId, $newName);

            if ($success) {
                // Enregistrer l'action dans l'historique
                $sql = "INSERT INTO intervention_history (
                            intervention_id, field_name, old_value, new_value, changed_by, description
                        ) VALUES (
                            :intervention_id, 'attachment_name', :old_value, :new_value, :changed_by, :description
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':intervention_id' => $attachment['entite_id'],
                    ':old_value' => $attachment['nom_fichier'],
                    ':new_value' => $newName,
                    ':changed_by' => $_SESSION['user']['id'],
                    ':description' => "Nom de la pièce jointe modifié : " . $attachment['nom_fichier'] . " → " . $newName
                ]);

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Nom mis à jour avec succès']);
            } else {
                throw new Exception("Erreur lors de la mise à jour du nom");
            }
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du nom de la pièce jointe : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Afficher la page de génération du bon d'intervention
     */
    public function generateBon($interventionId) {
        if (!canModifyInterventions()) {
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        try {
            // Récupérer l'intervention avec toutes les données nécessaires
            $intervention = $this->interventionModel->getById($interventionId);
            
            if (!$intervention) {
                $_SESSION['error'] = 'Intervention non trouvée';
                header('Location: ' . BASE_URL . 'interventions');
                exit;
            }

            // Récupérer les commentaires
            $comments = $this->getComments($interventionId);
            
            // Récupérer les pièces jointes
            $attachments = $this->getAttachments($interventionId);
            
            // Récupérer les informations du contrat si disponible
            if (!empty($intervention['contract_id'])) {
                $contract = $this->contractModel->getContractById($intervention['contract_id']);
                if ($contract) {
                    $intervention['contract_type_name'] = $contract['contract_type_name'] ?? '';
                    $intervention['tickets_remaining'] = $contract['tickets_remaining'] ?? 0;
                }
            }

            // Inclure la vue
            include __DIR__ . '/../views/interventions/generate_bon.php';
            
        } catch (Exception $e) {
            custom_log("Erreur lors de l'affichage de la génération du bon d'intervention : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = 'Erreur lors du chargement de la page';
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }
    }

    /**
     * Sauvegarder la sélection des éléments pour le bon d'intervention
     */
    public function saveBonSelection($interventionId) {
        if (!canModifyInterventions()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $selectedComments = $input['comments'] ?? [];
            $selectedAttachments = $input['attachments'] ?? [];
            
            // Mettre à jour les commentaires
            $this->interventionModel->updateCommentsForBon($interventionId, $selectedComments);
            
            // Mettre à jour les pièces jointes
            $this->interventionModel->updateAttachmentsForBon($interventionId, $selectedAttachments);
            
            echo json_encode(['success' => true, 'message' => 'Sélection sauvegardée avec succès']);
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la sauvegarde de la sélection du bon d'intervention : " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
    }

    /**
     * Génère le PDF du bon d'intervention avec les éléments sélectionnés
     */
    public function generateBonPdf($interventionId) {
        if (!canModifyInterventions()) {
            header('Location: ' . BASE_URL . 'interventions');
            exit;
        }

        try {
            // Récupérer l'intervention avec toutes les données nécessaires
            $intervention = $this->interventionModel->getById($interventionId);
            
            if (!$intervention) {
                $_SESSION['error'] = 'Intervention non trouvée';
                header('Location: ' . BASE_URL . 'interventions');
                exit;
            }
            

            // Récupérer les commentaires sélectionnés pour le bon
            $selectedComments = $this->getCommentsForBon($interventionId);
            
            // Récupérer les pièces jointes sélectionnées pour le bon
            $selectedAttachments = $this->getAttachmentsForBon($interventionId);
            
            // Récupérer les informations du contrat si disponible
            if (!empty($intervention['contract_id'])) {
                $contract = $this->contractModel->getContractById($intervention['contract_id']);
                if ($contract) {
                    $intervention['contract_type_name'] = $contract['contract_type_name'] ?? '';
                    $intervention['tickets_remaining'] = $contract['tickets_remaining'] ?? 0;
                }
            }

            // Générer le PDF
            try {
                $pdfPath = $this->generateBonInterventionPdf($intervention, $selectedComments, $selectedAttachments);
                custom_log("PDF généré avec succès: $pdfPath", 'INFO');
            } catch (Exception $e) {
                custom_log("Erreur lors de la génération du PDF: " . $e->getMessage(), 'ERROR');
                $_SESSION['error'] = 'Erreur lors de la génération du PDF: ' . $e->getMessage();
                header('Location: ' . BASE_URL . 'interventions/generateBon/' . $interventionId);
                exit;
            }

            // Lire et afficher le PDF
            if (file_exists($pdfPath)) {
                // Extraire le nom du fichier depuis le chemin
                $filename = basename($pdfPath);
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                readfile($pdfPath);
                exit;
            } else {
                custom_log("Fichier PDF non trouvé: $pdfPath", 'ERROR');
                $_SESSION['error'] = 'Fichier PDF non trouvé: ' . $pdfPath;
                header('Location: ' . BASE_URL . 'interventions/generateBon/' . $interventionId);
                exit;
            }
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la génération du PDF du bon d'intervention : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = 'Erreur lors de la génération du PDF';
            header('Location: ' . BASE_URL . 'interventions/generateBon/' . $interventionId);
            exit;
        }
    }

    /**
     * Récupère les commentaires sélectionnés pour le bon d'intervention
     */
    private function getCommentsForBon($interventionId) {
        $sql = "SELECT c.*, 
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM intervention_comments c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.intervention_id = ? AND c.pour_bon_intervention = 1
                ORDER BY c.is_solution DESC, c.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les pièces jointes sélectionnées pour le bon d'intervention
     */
    private function getAttachmentsForBon($interventionId) {
        $query = "
            SELECT 
                pj.*,
                st.setting_value as type_nom,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                lpj.type_liaison,
                lpj.pour_bon_intervention
            FROM pieces_jointes pj
            LEFT JOIN settings st ON pj.type_id = st.id
            LEFT JOIN users u ON pj.created_by = u.id
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            WHERE (lpj.type_liaison = 'intervention' OR lpj.type_liaison = 'bi')
            AND lpj.entite_id = :intervention_id
            AND lpj.pour_bon_intervention = 1
            ORDER BY pj.date_creation ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':intervention_id', $interventionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère le PDF du bon d'intervention avec les éléments sélectionnés
     * 
     * @param array $intervention Données de l'intervention
     * @param array $comments Commentaires sélectionnés
     * @param array $attachments Pièces jointes sélectionnées
     * @return string Chemin du fichier PDF généré
     */
    private function generateBonInterventionPdf($intervention, $comments, $attachments) {
        // Créer le dossier de stockage s'il n'existe pas
        $uploadDir = __DIR__ . '/../uploads/interventions/' . $intervention['id'];
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Générer un nom de fichier unique avec la date et l'heure
        $fileName = 'BI_' . $intervention['reference'] . '_' . date('Ymd') . '_' . date('Hi') . '.pdf';
        $filePath = $uploadDir . '/' . $fileName;

        custom_log("Génération PDF - Dossier: $uploadDir", 'INFO');
        custom_log("Génération PDF - Fichier: $fileName", 'INFO');
        custom_log("Génération PDF - Chemin complet: $filePath", 'INFO');

        // Charger la classe InterventionPDF
        require_once __DIR__ . '/../classes/InterventionPDF.php';

        // Créer et générer le PDF avec les éléments sélectionnés
        $pdf = new InterventionPDF();
        $pdf->generateBonIntervention($intervention, $comments, $attachments);
        $pdf->Output($filePath, 'F');

        custom_log("PDF généré - Vérification existence: " . (file_exists($filePath) ? 'OUI' : 'NON'), 'INFO');

        // Ajouter le PDF comme pièce jointe via le modèle
        $data = [
            'nom_fichier' => $fileName, // Nom du fichier physique avec l'heure
            'nom_personnalise' => 'Bon_intervention_' . date('Ymd'), // Nom d'affichage personnalisé
            'chemin_fichier' => 'uploads/interventions/' . $intervention['id'] . '/' . $fileName,
            'type_fichier' => 'pdf',
            'taille_fichier' => filesize($filePath),
            'commentaire' => 'Bon d\'intervention généré automatiquement',
            'masque_client' => 0, // Visible par les clients
            'created_by' => $_SESSION['user']['id']
        ];

        // Ajouter la pièce jointe avec le type de liaison 'bi' (Bon d'Intervention)
        $pieceJointeId = $this->interventionModel->addPieceJointeWithType($intervention['id'], $data, 'bi');

        // Enregistrer l'action dans l'historique
        $sql = "INSERT INTO intervention_history (
                    intervention_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':intervention_id' => $intervention['id'],
            ':field_name' => 'bon_intervention',
            ':old_value' => '',
            ':new_value' => 'Bon_intervention_' . date('Ymd'),
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => 'Bon d\'intervention généré avec les éléments sélectionnés'
        ]);

        return $filePath;
    }
} 