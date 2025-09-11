<?php

class ContactController {
    private $db;
    private $contactModel;
    private $clientModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->contactModel = new ContactModel($this->db);
        $this->clientModel = new ClientModel($this->db);
    }

    private function checkAccess($clientId = null) {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if (!canModifyClients()) {
            $_SESSION['error'] = "Vous n'avez pas les permissions nécessaires pour accéder à cette page.";
            
            // Rediriger vers la page d'édition du client si l'ID du client est fourni
            if ($clientId) {
                header('Location: ' . BASE_URL . 'clients/edit/' . $clientId);
            } else {
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }
    }

    public function add($clientId = null) {
        $this->checkAccess($clientId);

        if (!$clientId) {
            $_SESSION['error'] = "ID du client non spécifié.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $client = $this->clientModel->getClientById($clientId);
        if (!$client) {
            $_SESSION['error'] = "Client non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validation des champs obligatoires
            if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
                $_SESSION['error'] = "Le prénom, le nom et l'email sont obligatoires.";
            } else {
                $data = [
                    'client_id' => $clientId,
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'fonction' => $_POST['fonction'] ?? '',
                    'phone1' => $_POST['phone1'] ?? '',
                    'phone2' => $_POST['phone2'] ?? '',
                    'email' => $_POST['email'],
                    'comment' => $_POST['comment'] ?? '',
                    'has_user_account' => isset($_POST['has_user_account']) ? 1 : 0,
                    'status' => 1
                ];

                // Vérifier si on doit créer un compte utilisateur
                if (isset($_POST['has_user_account']) && isAdmin()) {
                    if (empty($_POST['username']) || empty($_POST['password'])) {
                        $_SESSION['error'] = "Le nom d'utilisateur et le mot de passe sont obligatoires pour créer un compte utilisateur.";
                    } else {
                        // Validation du mot de passe
                        $password = $_POST['password'];
                        $errors = [];
                        
                        if (strlen($password) < 8) {
                            $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
                        }
                        if (!preg_match('/[A-Z]/', $password)) {
                            $errors[] = "Le mot de passe doit contenir au moins une majuscule";
                        }
                        if (!preg_match('/[a-z]/', $password)) {
                            $errors[] = "Le mot de passe doit contenir au moins une minuscule";
                        }
                        if (!preg_match('/[0-9]/', $password)) {
                            $errors[] = "Le mot de passe doit contenir au moins un chiffre";
                        }
                        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
                            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
                        }

                        if (!empty($errors)) {
                            $_SESSION['error'] = "Erreurs de validation du mot de passe :<br>" . implode("<br>", $errors);
                            header('Location: ' . BASE_URL . 'contacts/add/' . $clientId);
                            exit;
                        }

                        // Créer le compte utilisateur
                        $userData = [
                            'username' => $_POST['username'],
                            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                            'first_name' => $_POST['first_name'],
                            'last_name' => $_POST['last_name'],
                            'email' => $_POST['email'],
                            'type' => 'client',
                            'status' => 1,
                            'client_id' => $clientId
                        ];
                        
                        // Créer l'utilisateur et récupérer son ID
                        $userId = $this->createUser($userData);
                        if ($userId) {
                            $data['user_id'] = $userId;
                        } else {
                            $_SESSION['error'] = "Erreur lors de la création du compte utilisateur. Veuillez vérifier que le nom d'utilisateur n'est pas déjà utilisé.";
                            header('Location: ' . BASE_URL . 'contacts/add/' . $clientId);
                            exit;
                        }
                    }
                }

                if (!isset($_SESSION['error']) && $this->contactModel->createContact($data)) {
                    $_SESSION['success'] = "Contact ajouté avec succès.";
                    header('Location: ' . BASE_URL . 'clients/edit/' . $clientId . '#contacts');
                    exit;
                } else if (!isset($_SESSION['error'])) {
                    $_SESSION['error'] = "Erreur lors de l'ajout du contact.";
                }
            }
        }

        $pageTitle = "Ajouter un contact - " . $client['name'];
        require_once VIEWS_PATH . '/contact/add.php';
    }

    private function createUser($data) {
        try {
            $this->db->beginTransaction();

            // Vérifier si le nom d'utilisateur existe déjà
            $checkQuery = "SELECT id FROM users WHERE username = :username";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                throw new Exception("Ce nom d'utilisateur est déjà utilisé");
            }

            // 1. Créer l'utilisateur
            $query = "INSERT INTO users (
                        username,
                        password,
                        first_name,
                        last_name,
                        email,
                        type,
                        status,
                        client_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        :username,
                        :password,
                        :first_name,
                        :last_name,
                        :email,
                        :type,
                        :status,
                        :client_id,
                        NOW(),
                        NOW()
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
            $stmt->bindParam(':password', $data['password'], PDO::PARAM_STR);
            $stmt->bindParam(':first_name', $data['first_name'], PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $data['last_name'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindParam(':type', $data['type'], PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
            $stmt->bindParam(':client_id', $data['client_id'], PDO::PARAM_INT);

            if (!$stmt->execute()) {
                $error = $stmt->errorInfo();
                throw new Exception("Erreur lors de la création de l'utilisateur: " . $error[2]);
            }

            $userId = $this->db->lastInsertId();

            // 2. Créer la localisation utilisateur (client par défaut)
            $locationQuery = "INSERT INTO user_locations (
                                user_id,
                                client_id,
                                site_id,
                                room_id,
                                created_at
                            ) VALUES (
                                :user_id,
                                :client_id,
                                NULL,
                                NULL,
                                NOW()
                            )";

            $locationStmt = $this->db->prepare($locationQuery);
            $locationStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $locationStmt->bindParam(':client_id', $data['client_id'], PDO::PARAM_INT);

            if (!$locationStmt->execute()) {
                $error = $locationStmt->errorInfo();
                throw new Exception("Erreur lors de la création de la localisation utilisateur: " . $error[2]);
            }

            $this->db->commit();
            return $userId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la création de l'utilisateur et de sa localisation: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            return false;
        }
    }

    public function edit($id = null) {
        // Récupérer d'abord le contact pour obtenir l'ID du client
        $contact = null;
        if ($id) {
            $contact = $this->contactModel->getContactById($id);
        }
        
        // Vérifier les permissions avec l'ID du client
        $this->checkAccess($contact ? $contact['client_id'] : null);

        if (!$id) {
            $_SESSION['error'] = "ID du contact non spécifié.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if (!$contact) {
            $_SESSION['error'] = "Contact non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'fonction' => $_POST['fonction'] ?? '',
                'phone1' => $_POST['phone1'] ?? '',
                'phone2' => $_POST['phone2'] ?? '',
                'email' => $_POST['email'] ?? '',
                'comment' => $_POST['comment'] ?? ''
            ];

            if ($this->contactModel->updateContact($id, $data)) {
                $_SESSION['success'] = "Contact modifié avec succès.";
                header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification du contact.";
            }
        }

        $pageTitle = "Modifier le contact - " . $contact['first_name'] . " " . $contact['last_name'];
        require_once VIEWS_PATH . '/contact/edit.php';
    }

    public function delete($id = null) {
        // Récupérer d'abord le contact pour obtenir l'ID du client
        $contact = null;
        if ($id) {
            $contact = $this->contactModel->getContactById($id);
        }

        // Vérifier si l'utilisateur est un administrateur
        if (!isset($_SESSION['user']) || !isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des contacts.";
            // Rediriger vers la page d'édition du client avec l'onglet contacts actif
            if ($contact && isset($contact['client_id'])) {
                header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
            } else {
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }

        if (!$id) {
            $_SESSION['error'] = "ID du contact non spécifié.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if (!$contact) {
            $_SESSION['error'] = "Contact non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($this->contactModel->deleteContact($id)) {
            $_SESSION['success'] = "Contact supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du contact.";
        }

        header('Location: ' . BASE_URL . 'clients/edit/' . $contact['client_id'] . '#contacts');
        exit;
    }

    public function index() {
        // Récupérer l'ID du client depuis les paramètres GET si disponible
        $clientId = isset($_GET['client_id']) ? $_GET['client_id'] : null;
        
        // Vérifier les permissions avec l'ID du client
        $this->checkAccess($clientId);

        // Récupérer les contacts avec filtres
        $filters = [
            'search' => $_GET['search'] ?? '',
            'client_id' => $clientId,
            'status' => isset($_GET['status']) ? $_GET['status'] : null
        ];

        // Rediriger vers le tableau de bord car les contacts sont gérés dans la vue client
        header('Location: ' . BASE_URL . 'dashboard');
        exit;
    }
} 