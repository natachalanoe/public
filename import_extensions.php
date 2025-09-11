<?php
// Page d'import des extensions - One-shot
require_once 'config/database.php';
require_once 'includes/FileUploadValidator.php';

// Configuration de base
define('BASE_URL', 'http://localhost/support.videosonicv5/');

// Augmenter les limites PHP pour l'upload
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

// Démarrer la session
session_start();

// Debug initial pour voir si on reçoit quelque chose
$debug = [];
$debug[] = "Méthode HTTP : " . $_SERVER['REQUEST_METHOD'];
$debug[] = "FILES reçus : " . (isset($_FILES) ? count($_FILES) : 0);
$debug[] = "POST reçus : " . (isset($_POST) ? count($_POST) : 0);
$debug[] = "upload_max_filesize : " . ini_get('upload_max_filesize');
$debug[] = "post_max_size : " . ini_get('post_max_size');

// Debug détaillé des données reçues
if (isset($_FILES) && count($_FILES) > 0) {
    $debug[] = "Détail des FILES reçus :";
    foreach ($_FILES as $key => $file) {
        $debug[] = "  - $key : " . $file['name'] . " (erreur: " . $file['error'] . ", taille: " . $file['size'] . ")";
    }
}

if (isset($_POST) && count($_POST) > 0) {
    $debug[] = "Détail des POST reçus :";
    foreach ($_POST as $key => $value) {
        $debug[] = "  - $key : " . (is_string($value) ? $value : 'non-string');
    }
}

if (isset($_FILES['excel_file'])) {
    $debug[] = "Fichier excel_file reçu : " . $_FILES['excel_file']['name'];
    $debug[] = "Erreur upload : " . $_FILES['excel_file']['error'];
    $debug[] = "Taille fichier : " . $_FILES['excel_file']['size'] . " bytes";
} else {
    $debug[] = "Aucun fichier excel_file reçu";
    // Vérifier s'il y a d'autres fichiers
    if (isset($_FILES) && count($_FILES) > 0) {
        $debug[] = "Autres fichiers reçus :";
        foreach ($_FILES as $key => $file) {
            $debug[] = "  - $key : " . $file['name'] . " (erreur: " . $file['error'] . ")";
        }
    }
}

// Fonction pour nettoyer l'extension
function cleanExtension($extension) {
    $extension = strtolower(trim($extension));
    // Enlever le point devant l'extension
    $extension = ltrim($extension, '.');
    return $extension;
}

// Fonction pour valider l'extension
function validateExtension($extension) {
    // Vérifier le format (lettres et chiffres uniquement)
    if (!preg_match('/^[a-z0-9]+$/', $extension)) {
        return false;
    }
    return true;
}

// Traitement de l'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $uploadedFile = $_FILES['excel_file'];
    $errors = [];
    $success = [];
    $skipped = [];
    
    $debug[] = "Début du traitement de l'upload";
    
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $filePath = $uploadedFile['tmp_name'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        
        $debug[] = "Fichier uploadé : " . $uploadedFile['name'];
        $debug[] = "Extension du fichier : " . $fileExtension;
        $debug[] = "Chemin temporaire : " . $filePath;
        
        // Vérifier que c'est un fichier Excel
        if (!in_array($fileExtension, ['xlsx', 'xls'])) {
            $errors[] = "Le fichier doit être un fichier Excel (.xlsx ou .xls)";
            $debug[] = "Extension non autorisée : " . $fileExtension;
        } else {
            try {
                // Inclure PhpSpreadsheet
                require_once 'vendor/autoload.php';
                $debug[] = "PhpSpreadsheet chargé";
                
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();
                
                $debug[] = "Nombre de lignes dans le fichier : " . $highestRow;
                
                $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $debug[] = "Connexion DB établie";
                
                // Préparer les requêtes
                $checkStmt = $db->prepare("SELECT id FROM settings_allowed_extensions WHERE extension = ?");
                $insertStmt = $db->prepare("INSERT INTO settings_allowed_extensions (extension, mime_type, description) VALUES (?, ?, ?)");
                
                for ($row = 1; $row <= $highestRow; $row++) { // Commencer à la ligne 1 (pas d'en-têtes)
                    $rawExtension = $worksheet->getCell('A' . $row)->getValue();
                    $rawMimeType = $worksheet->getCell('B' . $row)->getValue();
                    
                    $debug[] = "Ligne $row - Extension brute: '$rawExtension', MIME brute: '$rawMimeType'";
                    
                    $extension = cleanExtension($rawExtension);
                    $mimeType = trim($rawMimeType);
                    
                    $debug[] = "Ligne $row - Extension nettoyée: '$extension', MIME: '$mimeType'";
                    
                    // Ignorer les lignes vides
                    if (empty($extension)) {
                        $debug[] = "Ligne $row - Extension vide, ignorée";
                        continue;
                    }
                    
                    // Valider l'extension
                    if (!validateExtension($extension)) {
                        $skipped[] = "Extension '$extension' : format invalide";
                        $debug[] = "Ligne $row - Extension '$extension' : format invalide";
                        continue;
                    }
                    
                    // Vérifier si l'extension est blacklistée
                    if (FileUploadValidator::isExtensionBlacklisted($extension)) {
                        $skipped[] = "Extension '$extension' : extension interdite pour des raisons de sécurité";
                        $debug[] = "Ligne $row - Extension '$extension' : blacklistée";
                        continue;
                    }
                    
                    // Vérifier si l'extension existe déjà
                    $checkStmt->execute([$extension]);
                    if ($checkStmt->fetch()) {
                        $skipped[] = "Extension '$extension' : déjà présente en base";
                        $debug[] = "Ligne $row - Extension '$extension' : déjà en base";
                        continue;
                    }
                    
                    // Insérer l'extension
                    try {
                        $description = "Importé automatiquement";
                        $insertStmt->execute([$extension, $mimeType, $description]);
                        $success[] = "Extension '$extension' ajoutée avec succès";
                        $debug[] = "Ligne $row - Extension '$extension' : AJOUTÉE";
                    } catch (Exception $e) {
                        $errors[] = "Erreur lors de l'ajout de '$extension' : " . $e->getMessage();
                        $debug[] = "Ligne $row - Erreur pour '$extension' : " . $e->getMessage();
                    }
                }
                
            } catch (Exception $e) {
                $errors[] = "Erreur lors du traitement du fichier : " . $e->getMessage();
                $debug[] = "Erreur générale : " . $e->getMessage();
            }
        }
    } else {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "Le fichier dépasse la limite upload_max_filesize",
            UPLOAD_ERR_FORM_SIZE => "Le fichier dépasse la limite MAX_FILE_SIZE",
            UPLOAD_ERR_PARTIAL => "Le fichier n'a été que partiellement uploadé",
            UPLOAD_ERR_NO_FILE => "Aucun fichier n'a été uploadé",
            UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant",
            UPLOAD_ERR_CANT_WRITE => "Échec de l'écriture du fichier sur le disque",
            UPLOAD_ERR_EXTENSION => "Une extension PHP a arrêté l'upload"
        ];
        
        $errorMsg = isset($errorMessages[$uploadedFile['error']]) 
            ? $errorMessages[$uploadedFile['error']] 
            : "Erreur inconnue: " . $uploadedFile['error'];
            
        $errors[] = "Erreur lors de l'upload du fichier : " . $errorMsg;
        $debug[] = "Erreur upload détaillée : " . $errorMsg;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import des Extensions - VideoSonic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .upload-container {
            max-width: 800px;
            margin: 50px auto;
        }
        .file-upload {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background-color: white;
            transition: all 0.3s ease;
        }
        .file-upload:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .file-upload.dragover {
            border-color: #0d6efd;
            background-color: #e7f3ff;
        }
        .results {
            margin-top: 30px;
        }
        .result-section {
            margin-bottom: 20px;
        }
        .result-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .debug-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container upload-container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="bi bi-file-earmark-arrow-up me-2"></i>
                    Import des Extensions de Fichiers
                </h4>
                <small>Import one-shot pour compléter la table settings_allowed_extensions</small>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Instructions :</strong>
                    <ul class="mb-0 mt-2">
                        <li>Le fichier Excel doit contenir 2 colonnes : Extension (colonne A) et Type MIME (colonne B)</li>
                        <li>La première ligne doit contenir les en-têtes</li>
                        <li>Les extensions avec un point seront automatiquement nettoyées</li>
                        <li>Les extensions interdites ou déjà présentes seront ignorées</li>
                    </ul>
                </div>

                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Sélectionner votre fichier Excel :</label>
                        <input type="file" name="excel_file" id="excelFile" class="form-control" accept=".xlsx,.xls" required>
                        <div class="form-text">Le fichier doit contenir 2 colonnes : Extension (A) et Type MIME (B)</div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-upload me-2"></i>Importer les Extensions
                        </button>
                    </div>
                </form>

                <?php if (isset($errors) || isset($success) || isset($skipped) || !empty($debug)): ?>
                <div class="results mt-4">
                    <?php if (!empty($success)): ?>
                    <div class="result-section">
                        <h6 class="text-success">
                            <i class="bi bi-check-circle me-2"></i>Extensions importées avec succès (<?= count($success) ?>)
                        </h6>
                        <div class="result-list">
                            <?php foreach ($success as $msg): ?>
                                <div class="text-success small">✓ <?= htmlspecialchars($msg) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($skipped)): ?>
                    <div class="result-section">
                        <h6 class="text-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>Extensions ignorées (<?= count($skipped) ?>)
                        </h6>
                        <div class="result-list">
                            <?php foreach ($skipped as $msg): ?>
                                <div class="text-warning small">⚠ <?= htmlspecialchars($msg) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                    <div class="result-section">
                        <h6 class="text-danger">
                            <i class="bi bi-x-circle me-2"></i>Erreurs (<?= count($errors) ?>)
                        </h6>
                        <div class="result-list">
                            <?php foreach ($errors as $msg): ?>
                                <div class="text-danger small">✗ <?= htmlspecialchars($msg) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($debug)): ?>
                    <div class="debug-section">
                        <h6 class="text-info">
                            <i class="bi bi-bug me-2"></i>Debug (<?= count($debug) ?>)
                        </h6>
                        <div class="result-list">
                            <?php foreach ($debug as $msg): ?>
                                <div class="text-info small">🔍 <?= htmlspecialchars($msg) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 