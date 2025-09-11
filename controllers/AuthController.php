<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}

/**
 * Contrôleur d'authentification
 */
class AuthController {
    private $userModel;
    private $db;

    /**
     * Constructeur
     */
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->userModel = new UserModel($this->db);
    }

    /**
     * Affiche le formulaire de connexion
     */
    public function showLoginForm() {
        // Debug temporaire
        error_log("DEBUG: AuthController::showLoginForm() appelé");
        error_log("DEBUG: GET params: " . json_encode($_GET));

        // Vérifier s'il y a des paramètres QR dans l'URL
        $qrSalle = $_GET['qr'] ?? null;
        $qrType = $_GET['t'] ?? null;

        error_log("DEBUG: Paramètres QR détectés - salle: $qrSalle, type: $qrType");

        // Stocker les paramètres QR dans la session pour utilisation après authentification
        if ($qrSalle && $qrType) {
            $_SESSION['qr_salle'] = $qrSalle;
            $_SESSION['qr_type'] = $qrType;
            error_log("DEBUG: Paramètres QR stockés en session");
        }

        // Si l'utilisateur est déjà connecté ET qu'il y a des paramètres QR, rediriger directement
        if (isset($_SESSION['user']) && $qrSalle && $qrType) {
            error_log("DEBUG: Utilisateur connecté avec paramètres QR, redirection vers qrcode/redirect");
            header('Location: ' . BASE_URL . 'qrcode/redirect');
            exit;
        }

        // Si l'utilisateur est déjà connecté SANS paramètres QR, redirection normale
        if (isset($_SESSION['user'])) {
            error_log("DEBUG: Utilisateur déjà connecté sans paramètres QR, redirection vers dashboard");
            if (isClient()) {
                // Les clients vont vers le dashboard client
                header('Location: ' . BASE_URL . 'dashboard');
            } else {
                // Le personnel (admin, technicien) va vers le dashboard staff
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }

        // Affichage du formulaire de connexion
        require_once VIEWS_PATH . '/auth/login.php';
    }

    /**
     * Traite la connexion
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            try {
                $success = $this->userModel->authenticate($username, $password);
                
                if ($success) {
                    // L'authentification a réussi, les données sont déjà stockées dans la session
                    $_SESSION['last_activity'] = time();
                    
                    // Vérifier s'il y a des paramètres QR dans la session
                    error_log("DEBUG: Vérification des paramètres QR après connexion");
                    error_log("DEBUG: qr_salle en session: " . ($_SESSION['qr_salle'] ?? 'null'));
                    error_log("DEBUG: qr_type en session: " . ($_SESSION['qr_type'] ?? 'null'));
                    
                    if (isset($_SESSION['qr_salle']) && isset($_SESSION['qr_type'])) {
                        error_log("DEBUG: Paramètres QR trouvés, redirection vers qrcode/redirect");
                        // Rediriger vers le contrôleur QRCode pour gérer la redirection
                        header('Location: ' . BASE_URL . 'qrcode/redirect');
                        exit;
                    } else {
                        error_log("DEBUG: Pas de paramètres QR, redirection normale vers dashboard");
                    }
                    
                    // Redirection normale vers le tableau de bord approprié selon le type d'utilisateur
                    if (isClient()) {
                        // Les clients vont vers le dashboard client
                        header('Location: ' . BASE_URL . 'dashboard');
                    } else {
                        // Le personnel (admin, technicien) va vers le dashboard staff
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
                    exit;
                } else {
                    $_SESSION['error'] = "Nom d'utilisateur ou mot de passe incorrect";
                    header('Location: ' . BASE_URL . 'auth/login');
                    exit;
                }
            } catch (Exception $e) {
                custom_log("Erreur de connexion : " . $e->getMessage(), 'ERROR');
                $_SESSION['error'] = "Une erreur est survenue lors de la connexion";
                header('Location: ' . BASE_URL . 'auth/login');
                exit;
            }
        }
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        // Destruction de la session
        session_destroy();
        
        // Redirection vers la page de connexion
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }
} 