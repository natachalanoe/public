<?php
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/ContactModel.php';

class RoomController {
    private $db;
    private $roomModel;
    private $contactModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->roomModel = new RoomModel($this->db);
        $this->contactModel = new ContactModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur a le droit d'accéder aux salles
     */
    private function checkAccess() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if (!isStaff()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour accéder à cette page.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
    }

    /**
     * Affiche le formulaire d'ajout d'une salle
     */
    public function add($siteId) {
        $this->checkAccess();

        // Récupérer les informations du site avant de vérifier les permissions
        $site = $this->roomModel->getSiteById($siteId);
        if (!$site) {
            $_SESSION['error'] = "Site non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Vérifier si l'utilisateur a les droits de création
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une salle.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $siteId . '#sites');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'site_id' => $siteId,
                'name' => $_POST['name'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'main_contact_id' => !empty($_POST['main_contact_id']) ? $_POST['main_contact_id'] : null,
                'status' => 1
            ];

            if ($this->roomModel->createRoom($data)) {
                $_SESSION['success'] = "Salle ajoutée avec succès.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $siteId . '#sites');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout de la salle.";
            }
        }

        // Récupérer les contacts du client pour le select
        $contacts = $this->contactModel->getContactsByClientId($site['client_id']);

        $pageTitle = "Ajouter une salle";
        require_once VIEWS_PATH . '/room/add.php';
    }

    /**
     * Affiche le formulaire d'édition d'une salle
     */
    public function edit($id) {
        $this->checkAccess();

        // Récupérer la salle d'abord
        $room = $this->roomModel->getRoomById($id);
        if (!$room) {
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Récupérer le site associé à la salle
        $site = $this->roomModel->getSiteById($room['site_id']);
        if (!$site) {
            $_SESSION['error'] = "Site associé à cette salle non trouvé.";
            header('Location: ' . BASE_URL . 'clients');
            exit;
        }

        // Vérifier si l'utilisateur a les droits de modification
        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier cette salle.";
            header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $room['site_id'] . '#sites');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'main_contact_id' => !empty($_POST['main_contact_id']) ? $_POST['main_contact_id'] : null,
                'status' => isset($_POST['status']) ? 1 : 0
            ];

            if ($this->roomModel->updateRoom($id, $data)) {
                $_SESSION['success'] = "Salle modifiée avec succès.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $site['client_id'] . '?open_site_id=' . $room['site_id'] . '#sites');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification de la salle.";
            }
        }

        // Récupérer les contacts du client pour le select
        $contacts = $this->contactModel->getContactsByClientId($site['client_id']);

        $pageTitle = "Modifier la salle - " . $room['name'];
        require_once VIEWS_PATH . '/room/edit.php';
    }

    /**
     * Supprime une salle
     */
    public function delete($id) {
        $this->checkAccess();

        // Vérifier si l'utilisateur est un administrateur
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des salles.";
            // Redirect to client edit page if room context is available
            $room = $this->roomModel->getRoomById($id);
            if ($room && isset($room['client_id'])) {
                header('Location: ' . BASE_URL . 'clients/edit/' . $room['client_id'] . '#sites');
            } else {
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }

        // $room is already fetched before the isAdmin check
        $room = $this->roomModel->getRoomById($id);
        if (!$room) {
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'dashboard'); // Or a more relevant general page
            exit;
        }

        // Store client_id and site_id before deletion for the redirect
        $clientId = $room['client_id'];
        $siteId = $room['site_id'];

        if ($this->roomModel->deleteRoom($id)) {
            $_SESSION['success'] = "Salle supprimée avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression de la salle.";
        }

        header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '?open_site_id=' . $siteId . '#sites');
        exit;
    }

    /**
     * Récupère les salles d'un site via API
     */
    public function getRoomsBySite() {
        if (!isset($_GET['site_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID du site manquant']);
            exit;
        }

        $siteId = (int)$_GET['site_id'];
        $rooms = $this->roomModel->getRoomsBySiteId($siteId);

        header('Content-Type: application/json');
        echo json_encode($rooms);
        exit;
    }
} 