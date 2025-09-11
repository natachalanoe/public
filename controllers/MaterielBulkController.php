<?php

require_once __DIR__ . '/../includes/functions.php';

/**
 * Contrôleur pour l'import/export en masse de matériel
 * Module complètement indépendant du système d'import existant
 */
class MaterielBulkController {
    private $db;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $materielModel;

    public function __construct() {
        // Récupérer l'instance de la base de données
        $config = Config::getInstance();
        $this->db = $config->getDb();
        
        // Initialiser les modèles
        require_once MODELS_PATH . '/MaterielModel.php';
        require_once MODELS_PATH . '/ClientModel.php';
        require_once MODELS_PATH . '/SiteModel.php';
        require_once MODELS_PATH . '/RoomModel.php';
        require_once MODELS_PATH . '/AccessLevelModel.php';
        
        $this->materielModel = new MaterielModel($this->db);
        $this->clientModel = new ClientModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
    }

    /**
     * Affiche la page d'import/export en masse
     */
    public function index() {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier les permissions
        if (!canImportMateriel()) {
            $_SESSION['error'] = "Vous n'avez pas les droits pour importer du matériel.";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        try {
            $clients = $this->clientModel->getAllClients();
            $sites = [];
            $salles = [];
            $selectedClientId = $_GET['client_id'] ?? '';
            $selectedSiteId = $_GET['site_id'] ?? '';

            if ($selectedClientId) {
                $sites = $this->siteModel->getSitesByClientId($selectedClientId);
                if ($selectedSiteId) {
                    $salles = $this->roomModel->getRoomsBySiteId($selectedSiteId);
                } else {
                    $salles = $this->roomModel->getRoomsByClientId($selectedClientId);
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors du chargement des données : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        require_once VIEWS_PATH . '/materiel/bulk_operations.php';
    }

    /**
     * Valide le fichier Excel avant import
     */
    public function validate_import() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        // Vérification des champs de localisation
        $client_id = $_POST['client_id'] ?? null;
        $site_id = $_POST['site_id'] ?? null;
        
        if (!$client_id) {
            $_SESSION['error'] = "Veuillez sélectionner un client.";
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        // Vérification du fichier
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Erreur lors du téléchargement du fichier.";
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        $file = $_FILES['excel_file'];
        $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            $_SESSION['error'] = "Format de fichier non supporté. Utilisez un fichier Excel (.xlsx ou .xls).";
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Supprimer les 2 lignes d'en-têtes
            array_shift($rows); // Première ligne d'en-tête
            array_shift($rows); // Deuxième ligne d'en-tête
            
            $errors = [];
            $warnings = [];
            $validRows = [];
            $totalRows = 0;
            
            foreach ($rows as $i => $row) {
                $rowNum = $i + 3; // +3 car on a 2 lignes d'en-têtes et les index commencent à 0
                $totalRows++;
                
                // Vérifier que la ligne n'est pas vide
                if (empty(array_filter($row))) {
                    continue;
                }

                // Mapping des colonnes selon l'ordre exact du template Excel
                $data = [
                    'id_materiel' => $row[0] ?? null, // ID_MATERIEL (colonne A)
                    'salle_id' => $row[1] ?? null, // ID_SALLE (colonne B)
                    'type_materiel' => $row[3] ?? null, // TYPE_MATERIEL (colonne D)
                    'marque' => $row[4] ?? null, // MARQUE (colonne E)
                    'modele' => $row[5] ?? null, // MODELE (colonne F)
                    'reference' => $row[6] ?? null, // REFERENCE (colonne G)
                    'usage_materiel' => $row[7] ?? null, // USAGE_MATERIEL (colonne H)
                    'numero_serie' => $row[8] ?? null, // NUMERO_SERIE (colonne I)
                    'version_firmware' => $row[9] ?? null, // VERSION_FIRMWARE (colonne J)
                    'ancien_firmware' => $row[10] ?? null, // ANCIEN_FIRMWARE (colonne K)
                    'url_github' => $row[11] ?? null, // URL_GITHUB (colonne L) - NOUVEAU CHAMP
                    'adresse_mac' => $row[12] ?? null, // ADRESSE_MAC (colonne M) - DÉCALÉ
                    'adresse_ip' => $row[13] ?? null, // ADRESSE_IP (colonne N) - DÉCALÉ
                    'masque' => $row[14] ?? null, // MASQUE (colonne O) - DÉCALÉ
                    'passerelle' => $row[15] ?? null, // PASSERELLE (colonne P) - DÉCALÉ
                    'id_materiel_tech' => $row[16] ?? null, // ID_MATERIEL_TECH (colonne Q) - DÉCALÉ
                    'login' => $row[17] ?? null, // LOGIN (colonne R) - DÉCALÉ
                    'password' => $row[18] ?? null, // PASSWORD (colonne S) - DÉCALÉ
                    'ip_primaire' => $row[19] ?? null, // IP_PRIMAIRE (colonne T) - DÉCALÉ
                    'mac_primaire' => $row[20] ?? null, // MAC_PRIMAIRE (colonne U) - DÉCALÉ
                    'ip_secondaire' => $row[21] ?? null, // IP_SECONDAIRE (colonne V) - DÉCALÉ
                    'mac_secondaire' => $row[22] ?? null, // MAC_SECONDAIRE (colonne W) - DÉCALÉ
                    'stream_aes67_recu' => $row[23] ?? null, // STREAM_AES67_RECU (colonne X) - DÉCALÉ
                    'stream_aes67_transmis' => $row[24] ?? null, // STREAM_AES67_TRANSMIS (colonne Y) - DÉCALÉ
                    'ssid' => $row[25] ?? null, // SSID (colonne Z) - DÉCALÉ
                    'type_cryptage' => $row[26] ?? null, // TYPE_CRYPTAGE (colonne AA) - DÉCALÉ
                    'password_wifi' => $row[27] ?? null, // PASSWORD_WIFI (colonne AB) - DÉCALÉ
                    'libelle_pa_salle' => $row[28] ?? null, // LIBELLE_PA_SALLE (colonne AC) - DÉCALÉ
                    'numero_port_switch' => $row[29] ?? null, // NUMERO_PORT_SWITCH (colonne AD) - DÉCALÉ
                    'vlan' => $row[30] ?? null, // VLAN (colonne AE) - DÉCALÉ
                    'date_fin_maintenance' => $this->convertExcelDate($row[31] ?? null), // DATE_FIN_MAINTENANCE (colonne AF) - DÉCALÉ
                    'date_fin_garantie' => $this->convertExcelDate($row[32] ?? null), // DATE_FIN_GARANTIE (colonne AG) - DÉCALÉ
                    'date_derniere_inter' => $this->convertExcelDate($row[33] ?? null), // DATE_DERNIERE_INTER (colonne AH) - DÉCALÉ
                    'commentaire' => $row[34] ?? null // COMMENTAIRE (colonne AI) - DÉCALÉ
                ];

                $rowErrors = [];
                $rowWarnings = [];

                // Validation des champs obligatoires
                if (empty($data['salle_id'])) {
                    $rowErrors[] = "ID de salle obligatoire";
                }

                // Vérifier que la salle existe et appartient au client/site sélectionné
                if (!empty($data['salle_id'])) {
                    $salle = $this->roomModel->getRoomById($data['salle_id']);
                    if (!$salle) {
                        $rowErrors[] = "Salle ID {$data['salle_id']} n'existe pas";
                    } else {
                        // Vérifier que la salle appartient au bon client/site
                        if ($site_id) {
                            if ($salle['site_id'] != $site_id) {
                                $rowErrors[] = "Salle ID {$data['salle_id']} n'appartient pas au site sélectionné";
                            }
                        } else {
                            $site = $this->siteModel->getSiteById($salle['site_id']);
                            if ($site['client_id'] != $client_id) {
                                $rowErrors[] = "Salle ID {$data['salle_id']} n'appartient pas au client sélectionné";
                            }
                        }
                    }
                }

                // Vérifier que le matériel existe si ID fourni
                if (!empty($data['id_materiel'])) {
                    $existingMateriel = $this->materielModel->getMaterielById($data['id_materiel']);
                    if (!$existingMateriel) {
                        $rowErrors[] = "Matériel ID {$data['id_materiel']} n'existe pas";
                    }
                }



                // Ajouter les erreurs et warnings de cette ligne
                if (!empty($rowErrors)) {
                    $errors[] = "Ligne $rowNum : " . implode(', ', $rowErrors);
                }
                if (!empty($rowWarnings)) {
                    $warnings[] = "Ligne $rowNum : " . implode(', ', $rowWarnings);
                }

                // Si pas d'erreurs, ajouter aux lignes valides
                if (empty($rowErrors)) {
                    $validRows[] = $data;
                }
            }

            // Stocker les résultats de validation en session
            $_SESSION['import_validation'] = [
                'errors' => $errors,
                'warnings' => $warnings,
                'valid_rows' => $validRows,
                'total_rows' => $totalRows,
                'client_id' => $client_id,
                'site_id' => $site_id,
                'file_name' => $file['name']
            ];

            // Rediriger vers la page de validation
            $redirectUrl = BASE_URL . 'materiel_bulk/confirm_import';
            $filters = [];
            if ($client_id) {
                $filters['client_id'] = $client_id;
            }
            if ($site_id) {
                $filters['site_id'] = $site_id;
            }
            
            if (!empty($filters)) {
                $redirectUrl .= '?' . http_build_query($filters);
            }
            
            header('Location: ' . $redirectUrl);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la validation : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }
    }

    /**
     * Affiche la page de confirmation d'import
     */
    public function confirm_import() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if (!isset($_SESSION['import_validation'])) {
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        $validation = $_SESSION['import_validation'];
        
        require_once VIEWS_PATH . '/materiel/confirm_import.php';
    }

    /**
     * Traite l'import en masse de matériel après validation
     */
    public function process_bulk_import() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if (!isset($_SESSION['import_validation'])) {
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        $validation = $_SESSION['import_validation'];
        $client_id = $validation['client_id'];
        $site_id = $validation['site_id'];
        $validRows = $validation['valid_rows'];

        try {
            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($validRows as $data) {
                try {
                    if (!empty($data['id_materiel'])) {
                        // Mise à jour d'un matériel existant
                        $existingMateriel = $this->materielModel->getMaterielById($data['id_materiel']);
                        
                        // Supprimer l'ID du matériel des données à mettre à jour
                        unset($data['id_materiel']);
                        $this->materielModel->updateMateriel($existingMateriel['id'], $data);
                        $updated++;
                    } else {
                        // Création d'un nouveau matériel
                        $materielId = $this->materielModel->createMateriel($data);
                        
                        // Appliquer les règles de visibilité par défaut
                        if ($materielId) {
                            $this->applyDefaultVisibilityRules($materielId, $client_id);
                        }
                        
                        $imported++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Erreur lors de l'import : " . $e->getMessage();
                }
            }

            // Messages de succès
            $messages = [];
            if ($imported > 0) {
                $messages[] = "$imported matériels importés.";
            }
            if ($updated > 0) {
                $messages[] = "$updated matériels mis à jour.";
            }
            
            if (!empty($messages)) {
                $_SESSION['success'] = implode(' ', $messages);
            }
            
            if ($errors) {
                $_SESSION['error'] = implode('<br>', $errors);
            }

            // Nettoyer la session
            unset($_SESSION['import_validation']);

        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de l'import : " . $e->getMessage();
        }

        // Rediriger vers la page d'import/export
        $redirectUrl = BASE_URL . 'materiel_bulk';
        $filters = [];
        if ($client_id) {
            $filters['client_id'] = $client_id;
        }
        if ($site_id) {
            $filters['site_id'] = $site_id;
        }
        
        if (!empty($filters)) {
            $redirectUrl .= '?' . http_build_query($filters);
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Génère et télécharge le fichier Excel d'export
     */
    public function export() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if (!canImportMateriel()) {
            $_SESSION['error'] = "Vous n'avez pas les droits pour exporter du matériel.";
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        $client_id = $_GET['client_id'] ?? null;
        $site_id = $_GET['site_id'] ?? null;

        if (!$client_id) {
            $_SESSION['error'] = "Veuillez sélectionner un client.";
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            // Récupérer les matériels selon les filtres
            $materiels = $this->materielModel->getMaterielsForBulkExport($client_id, $site_id);
            
            // Utiliser le template existant comme base
            $templatePath = ROOT_PATH . '/assets/templates/materiel_import_template.xlsx';
            
            if (!file_exists($templatePath)) {
                $_SESSION['error'] = "Template non trouvé.";
                header('Location: ' . BASE_URL . 'materiel_bulk');
                exit;
            }
            
            // Charger le template existant
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Le template n'a que les 2 lignes d'en-têtes, pas de données d'exemple
            // On garde les 2 lignes d'en-têtes et on commence à ajouter les données à la ligne 3
            
            // Ajouter les données des matériels
            $row = 3;
            foreach ($materiels as $materiel) {
                $data = [
                    $materiel['id'], // ID_MATERIEL (colonne A)
                    $materiel['salle_id'], // ID_SALLE (colonne B)
                    $materiel['salle_name'], // NOM_SALLE (colonne C)
                    $materiel['type_materiel'], // TYPE_MATERIEL (colonne D)
                    $materiel['marque'], // MARQUE (colonne E)
                    $materiel['modele'], // MODELE (colonne F)
                    $materiel['reference'], // REFERENCE (colonne G)
                    $materiel['usage_materiel'], // USAGE_MATERIEL (colonne H)
                    $materiel['numero_serie'], // NUMERO_SERIE (colonne I)
                    $materiel['version_firmware'], // VERSION_FIRMWARE (colonne J)
                    $materiel['ancien_firmware'], // ANCIEN_FIRMWARE (colonne K)
                    $materiel['url_github'], // URL_GITHUB (colonne L) - NOUVEAU CHAMP
                    $materiel['adresse_mac'], // ADRESSE_MAC (colonne M) - DÉCALÉ
                    $materiel['adresse_ip'], // ADRESSE_IP (colonne N) - DÉCALÉ
                    $materiel['masque'], // MASQUE (colonne O) - DÉCALÉ
                    $materiel['passerelle'], // PASSERELLE (colonne P) - DÉCALÉ
                    $materiel['id_materiel'], // ID_MATERIEL_TECH (colonne Q) - DÉCALÉ
                    $materiel['login'], // LOGIN (colonne R) - DÉCALÉ
                    $materiel['password'], // PASSWORD (colonne S) - DÉCALÉ
                    $materiel['ip_primaire'], // IP_PRIMAIRE (colonne T) - DÉCALÉ
                    $materiel['mac_primaire'], // MAC_PRIMAIRE (colonne U) - DÉCALÉ
                    $materiel['ip_secondaire'], // IP_SECONDAIRE (colonne V) - DÉCALÉ
                    $materiel['mac_secondaire'], // MAC_SECONDAIRE (colonne W) - DÉCALÉ
                    $materiel['stream_aes67_recu'], // STREAM_AES67_RECU (colonne X) - DÉCALÉ
                    $materiel['stream_aes67_transmis'], // STREAM_AES67_TRANSMIS (colonne Y) - DÉCALÉ
                    $materiel['ssid'], // SSID (colonne Z) - DÉCALÉ
                    $materiel['type_cryptage'], // TYPE_CRYPTAGE (colonne AA) - DÉCALÉ
                    $materiel['password_wifi'], // PASSWORD_WIFI (colonne AB) - DÉCALÉ
                    $materiel['libelle_pa_salle'], // LIBELLE_PA_SALLE (colonne AC) - DÉCALÉ
                    $materiel['numero_port_switch'], // NUMERO_PORT_SWITCH (colonne AD) - DÉCALÉ
                    $materiel['vlan'], // VLAN (colonne AE) - DÉCALÉ
                    $materiel['date_fin_maintenance'], // DATE_FIN_MAINTENANCE (colonne AF) - DÉCALÉ
                    $materiel['date_fin_garantie'], // DATE_FIN_GARANTIE (colonne AG) - DÉCALÉ
                    $materiel['date_derniere_inter'], // DATE_DERNIERE_INTER (colonne AH) - DÉCALÉ
                    $materiel['commentaire'] // COMMENTAIRE (colonne AI) - DÉCALÉ
                ];
                
                $sheet->fromArray([$data], null, "A$row");
                $row++;
            }
            
            // Créer le fichier
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            // Nettoyer le buffer de sortie
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Récupérer les informations du client et du site pour le nom de fichier
            $clientName = '';
            $siteName = '';
            
            // Récupérer le nom du client
            $client = $this->clientModel->getClientById($client_id);
            if ($client) {
                $clientName = $client['name'];
            }
            
            // Récupérer le nom du site si spécifié
            if ($site_id) {
                $site = $this->siteModel->getSiteById($site_id);
                if ($site) {
                    $siteName = $site['name'];
                }
            }
            
            // Construire le nom de fichier selon le format demandé
            $dateFormatted = date('Ymd'); // Format 20250829
            $filename = '';
            
            if ($clientName) {
                $filename .= preg_replace('/[^a-zA-Z0-9_-]/', '_', $clientName);
            }
            
            if ($siteName) {
                $filename .= '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $siteName);
            }
            
            $filename .= '_materiel_' . $dateFormatted . '.xlsx';
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            
            // Envoyer le fichier
            $writer->save('php://output');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de l'export : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }
    }

    /**
     * Télécharge le template Excel pour l'import
     */
    public function download_template() {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if (!canImportMateriel()) {
            $_SESSION['error'] = "Vous n'avez pas les droits pour télécharger le template.";
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }

        try {
            // Utiliser le template existant comme base
            $templatePath = ROOT_PATH . '/assets/templates/materiel_import_template.xlsx';
            
            if (!file_exists($templatePath)) {
                $_SESSION['error'] = "Template non trouvé.";
                header('Location: ' . BASE_URL . 'materiel_bulk');
                exit;
            }
            
            require_once __DIR__ . '/../vendor/autoload.php';
            
            // Charger le template existant
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Nettoyer le buffer de sortie
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // En-têtes pour le téléchargement
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="materiel_bulk_template.xlsx"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($templatePath));
            
            // Envoyer le fichier
            readfile($templatePath);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la génération du template : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel_bulk');
            exit;
        }
    }

    /**
     * Convertit une date Excel en format MySQL
     */
    private function convertExcelDate($excelDate) {
        if (empty($excelDate)) {
            return null;
        }
        
        // Si c'est déjà une date au format Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $excelDate)) {
            return $excelDate;
        }
        
        // Si c'est un timestamp Excel
        if (is_numeric($excelDate)) {
            $unixDate = ($excelDate - 25569) * 86400;
            return date('Y-m-d', $unixDate);
        }
        
        // Si c'est une date au format dd/mm/yyyy
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $excelDate)) {
            $date = DateTime::createFromFormat('d/m/Y', $excelDate);
            return $date ? $date->format('Y-m-d') : null;
        }
        
        return null;
    }

    /**
     * Applique les règles de visibilité par défaut selon le contrat
     */
    private function applyDefaultVisibilityRules($materielId, $clientId) {
        try {
            // Récupérer le contrat du client
            $sql = "SELECT c.id as contract_id, c.access_level_id 
                    FROM contracts c 
                    WHERE c.client_id = :client_id 
                    ORDER BY c.created_at DESC 
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':client_id' => $clientId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contract && $contract['access_level_id']) {
                // Utiliser MaterielModel pour appliquer les règles
                $materielModel = new MaterielModel($this->db);
                $champs = $materielModel->getChampsVisibilite(null, $contract['contract_id']);
                
                $visibilites = [];
                foreach ($champs as $nom_champ => $info) {
                    $visibilites[$nom_champ] = $info['visible_client'];
                }
                
                $materielModel->saveVisibiliteChamps($materielId, $visibilites);
            } else {
                // Si pas de contrat ou pas de niveau d'accès, appliquer les valeurs par défaut
                $materielModel = new MaterielModel($this->db);
                $champs = $materielModel->getChampsVisibilite();
                
                $visibilites = [];
                foreach ($champs as $nom_champ => $info) {
                    $visibilites[$nom_champ] = $info['visible_client'];
                }
                
                $materielModel->saveVisibiliteChamps($materielId, $visibilites);
            }
        } catch (Exception $e) {
            // Log silencieux pour éviter les erreurs
        }
    }
}
