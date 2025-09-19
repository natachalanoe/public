<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';
/**
 * Vue de détail du matériel
 * Affichage complet des informations avec indicateurs de visibilité
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID du matériel depuis l'URL
$materielId = isset($materiel['id']) ? $materiel['id'] : '';

setPageVariables(
    'Matériel',
    'materiel' . ($materielId ? '_view_' . $materielId : '')
);

// Définir la page courante pour le menu
$currentPage = 'materiel';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Détails du matériel</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <?php
        // Construire l'URL de retour avec les paramètres de filtres
        $returnParams = [];
        if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
            $returnParams['client_id'] = $_GET['client_id'];
        }
        if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
            $returnParams['site_id'] = $_GET['site_id'];
        }
        if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
            $returnParams['salle_id'] = $_GET['salle_id'];
        }
        
        $returnUrl = BASE_URL . 'materiel';
        if (!empty($returnParams)) {
            $returnUrl .= '?' . http_build_query($returnParams);
        }
        ?>
        <a href="<?= $returnUrl ?>" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>
        <a href="<?= BASE_URL ?>materiel/edit/<?= $materiel['id'] ?>" class="btn btn-warning me-2">
            <i class="bi bi-pencil me-1"></i> Modifier
        </a>
        <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= $materiel['id'] ?>, '<?= htmlspecialchars($materiel['marque'] ?? '') ?> <?= htmlspecialchars($materiel['modele'] ?? '') ?>')" title="Supprimer le matériel">
                <i class="bi bi-trash"></i>
            </button>
        <?php endif; ?>
    </div>
</div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if ($materiel): ?>
        <!-- Informations du matériel -->
        <div class="row">
            <!-- Colonne gauche : Informations principales -->
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <?= htmlspecialchars($materiel['modele']) ?> - <?= htmlspecialchars($materiel['marque']) ?>
                            </h5>
                        </div>
                        <small class="text-muted">
                            <?= htmlspecialchars($client['name']) ?> > 
                            <?= htmlspecialchars($site['name']) ?> > 
                            <?= htmlspecialchars($room['name']) ?>
                        </small>
                    </div>
                    <div class="card-body py-2">
                        <!-- Bloc 1: Informations Générales -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="bi bi-info-circle me-2"></i>Informations Générales
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-tag me-2"></i>Type de matériel
                                                <?php if (isset($visibilites_champs[$materiel['id']]['type_materiel']) && !$visibilites_champs[$materiel['id']]['type_materiel']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['type_materiel'] ? htmlspecialchars($materiel['type_materiel']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-tag me-2"></i>Marque
                                            </span>
                                            <span class="fw-medium"><?= htmlspecialchars($materiel['marque']) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-barcode me-2"></i>Référence
                                                <?php if (isset($visibilites_champs[$materiel['id']]['reference']) && !$visibilites_champs[$materiel['id']]['reference']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['reference'] ? htmlspecialchars($materiel['reference']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-tasks me-2"></i>Usage
                                                <?php if (isset($visibilites_champs[$materiel['id']]['usage_materiel']) && !$visibilites_champs[$materiel['id']]['usage_materiel']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['usage_materiel'] ? htmlspecialchars($materiel['usage_materiel']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-barcode me-2"></i>Numéro de série
                                                <?php if (isset($visibilites_champs[$materiel['id']]['numero_serie']) && !$visibilites_champs[$materiel['id']]['numero_serie']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['numero_serie'] ? htmlspecialchars($materiel['numero_serie']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-wifi me-2"></i>MAC
                                                <?php if (isset($visibilites_champs[$materiel['id']]['adresse_mac']) && !$visibilites_champs[$materiel['id']]['adresse_mac']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['adresse_mac'] ? htmlspecialchars($materiel['adresse_mac']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-globe me-2"></i>IP
                                                <?php if (isset($visibilites_champs[$materiel['id']]['adresse_ip']) && !$visibilites_champs[$materiel['id']]['adresse_ip']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['adresse_ip'] ? htmlspecialchars($materiel['adresse_ip']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-mask me-2"></i>Masque
                                                <?php if (isset($visibilites_champs[$materiel['id']]['masque']) && !$visibilites_champs[$materiel['id']]['masque']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['masque'] ? htmlspecialchars($materiel['masque']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-route me-2"></i>Passerelle
                                                <?php if (isset($visibilites_champs[$materiel['id']]['passerelle']) && !$visibilites_champs[$materiel['id']]['passerelle']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['passerelle'] ? htmlspecialchars($materiel['passerelle']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-id-card me-2"></i>ID
                                                <?php if (isset($visibilites_champs[$materiel['id']]['id_materiel']) && !$visibilites_champs[$materiel['id']]['id_materiel']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['id_materiel'] ? htmlspecialchars($materiel['id_materiel']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-user me-2"></i>Login
                                                <?php if (isset($visibilites_champs[$materiel['id']]['login']) && !$visibilites_champs[$materiel['id']]['login']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['login'] ? htmlspecialchars($materiel['login']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-lock me-2"></i>Password
                                                <?php if (isset($visibilites_champs[$materiel['id']]['password']) && !$visibilites_champs[$materiel['id']]['password']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <div class="input-group" style="width: 200px;">
                                                <input type="password" class="form-control form-control-sm" id="password" value="<?= $materiel['password'] ? htmlspecialchars($materiel['password']) : '' ?>" readonly>
                                                <button class="btn btn-outline-secondary btn-sm" type="button" id="togglePassword" title="Afficher/Masquer le mot de passe">
                                                    <i class="<?php echo getIcon('visibility', 'bi bi-eye'); ?>" id="passwordIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-cube me-2"></i>Modèle
                                            </span>
                                            <span class="fw-medium"><?= htmlspecialchars($materiel['modele']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 2: Firmware -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-microchip me-2"></i>Firmware
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-microchip me-2"></i>Version firmware
                                                <?php if (isset($visibilites_champs[$materiel['id']]['version_firmware']) && !$visibilites_champs[$materiel['id']]['version_firmware']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['version_firmware'] ? htmlspecialchars($materiel['version_firmware']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-history me-2"></i>Ancien firmware
                                                <?php if (isset($visibilites_champs[$materiel['id']]['ancien_firmware']) && !$visibilites_champs[$materiel['id']]['ancien_firmware']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['ancien_firmware'] ? htmlspecialchars($materiel['ancien_firmware']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fab fa-github me-2"></i>URL GitHub
                                                <?php if (isset($visibilites_champs[$materiel['id']]['url_github']) && !$visibilites_champs[$materiel['id']]['url_github']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium">
                                                <?php if ($materiel['url_github']): ?>
                                                    <a href="<?= htmlspecialchars($materiel['url_github']) ?>" target="_blank" class="text-primary">
                                                        <i class="fab fa-github me-1"></i><?= htmlspecialchars($materiel['url_github']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Non définie</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 3: Audio IP -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-broadcast-tower me-2"></i>Audio IP
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-server me-2"></i>IP Primaire
                                                <?php if (isset($visibilites_champs[$materiel['id']]['ip_primaire']) && !$visibilites_champs[$materiel['id']]['ip_primaire']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['ip_primaire'] ? htmlspecialchars($materiel['ip_primaire']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-ethernet me-2"></i>MAC Primaire
                                                <?php if (isset($visibilites_champs[$materiel['id']]['mac_primaire']) && !$visibilites_champs[$materiel['id']]['mac_primaire']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['mac_primaire'] ? htmlspecialchars($materiel['mac_primaire']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-server me-2"></i>IP Secondaire
                                                <?php if (isset($visibilites_champs[$materiel['id']]['ip_secondaire']) && !$visibilites_champs[$materiel['id']]['ip_secondaire']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['ip_secondaire'] ? htmlspecialchars($materiel['ip_secondaire']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-ethernet me-2"></i>MAC Secondaire
                                                <?php if (isset($visibilites_champs[$materiel['id']]['mac_secondaire']) && !$visibilites_champs[$materiel['id']]['mac_secondaire']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['mac_secondaire'] ? htmlspecialchars($materiel['mac_secondaire']) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-download me-2"></i>Stream AES67 Reçu
                                                <?php if (isset($visibilites_champs[$materiel['id']]['stream_aes67_recu']) && !$visibilites_champs[$materiel['id']]['stream_aes67_recu']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['stream_aes67_recu'] ? htmlspecialchars($materiel['stream_aes67_recu']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-upload me-2"></i>Stream AES67 Transmis
                                                <?php if (isset($visibilites_champs[$materiel['id']]['stream_aes67_transmis']) && !$visibilites_champs[$materiel['id']]['stream_aes67_transmis']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['stream_aes67_transmis'] ? htmlspecialchars($materiel['stream_aes67_transmis']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 4: WiFi -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-wifi me-2"></i>WiFi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-wifi me-2"></i>SSID
                                                <?php if (isset($visibilites_champs[$materiel['id']]['ssid']) && !$visibilites_champs[$materiel['id']]['ssid']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['ssid'] ? htmlspecialchars($materiel['ssid']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-shield-alt me-2"></i>Type de cryptage
                                                <?php if (isset($visibilites_champs[$materiel['id']]['type_cryptage']) && !$visibilites_champs[$materiel['id']]['type_cryptage']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['type_cryptage'] ? htmlspecialchars($materiel['type_cryptage']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-key me-2"></i>Password
                                                <?php if (isset($visibilites_champs[$materiel['id']]['password_wifi']) && !$visibilites_champs[$materiel['id']]['password_wifi']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <div class="input-group" style="width: 200px;">
                                                <input type="password" class="form-control form-control-sm" id="passwordWifi" value="<?= $materiel['password_wifi'] ? htmlspecialchars($materiel['password_wifi']) : '' ?>" readonly>
                                                <button class="btn btn-outline-secondary btn-sm" type="button" id="togglePasswordWifi" title="Afficher/Masquer le mot de passe WiFi">
                                                    <i class="bi bi-eye" id="passwordWifiIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                
                        <!-- Bloc 5: Infrastructure -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-building me-2"></i>Infrastructure
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-volume-up me-2"></i>Libellé de PA salle
                                                <?php if (isset($visibilites_champs[$materiel['id']]['libelle_pa_salle']) && !$visibilites_champs[$materiel['id']]['libelle_pa_salle']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['libelle_pa_salle'] ? htmlspecialchars($materiel['libelle_pa_salle']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-plug me-2"></i>N° Port switch
                                                <?php if (isset($visibilites_champs[$materiel['id']]['numero_port_switch']) && !$visibilites_champs[$materiel['id']]['numero_port_switch']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['numero_port_switch'] ? htmlspecialchars($materiel['numero_port_switch']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-sitemap me-2"></i>VLAN
                                                <?php if (isset($visibilites_champs[$materiel['id']]['vlan']) && !$visibilites_champs[$materiel['id']]['vlan']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['vlan'] ? htmlspecialchars($materiel['vlan']) : 'Non défini' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dates importantes -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="bi bi-tools me-2"></i>Date fin maintenance
                                                <?php if (isset($visibilites_champs[$materiel['id']]['date_fin_maintenance']) && !$visibilites_champs[$materiel['id']]['date_fin_maintenance']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['date_fin_maintenance'] ? date('d/m/Y', strtotime($materiel['date_fin_maintenance'])) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-certificate me-2"></i>Date fin garantie
                                                <?php if (isset($visibilites_champs[$materiel['id']]['date_fin_garantie']) && !$visibilites_champs[$materiel['id']]['date_fin_garantie']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['date_fin_garantie'] ? date('d/m/Y', strtotime($materiel['date_fin_garantie'])) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-calendar-check me-2"></i>Date dernière intervention
                                                <?php if (isset($visibilites_champs[$materiel['id']]['date_derniere_inter']) && !$visibilites_champs[$materiel['id']]['date_derniere_inter']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium"><?= $materiel['date_derniere_inter'] ? date('d/m/Y', strtotime($materiel['date_derniere_inter'])) : 'Non définie' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Commentaire -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="text-muted">
                                                <i class="fas fa-comment me-2"></i>Remarques
                                                <?php if (isset($visibilites_champs[$materiel['id']]['commentaire']) && !$visibilites_champs[$materiel['id']]['commentaire']): ?>
                                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning ms-1" title="Masqué aux clients"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="fw-medium text-end" style="max-width: 60%;"><?= $materiel['commentaire'] ? htmlspecialchars($materiel['commentaire']) : 'Aucune remarque' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonne droite : Informations système et Pièces jointes -->
            <div class="col-md-5">
                <!-- Informations système -->
                <div class="card mb-4">
                    <div class="card-header py-2">
                        <h6 class="mb-0">
                            <i class="bi bi-info-circle me-2 me-1"></i>Informations Système
                        </h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Créé le</small>
                            <small><?= date('d/m/Y H:i', strtotime($materiel['created_at'])) ?></small>
                        </div>
                        <?php if ($materiel['updated_at'] && $materiel['updated_at'] !== $materiel['created_at']): ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Modifié le</small>
                                <small><?= date('d/m/Y H:i', strtotime($materiel['updated_at'])) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pièces jointes -->
                <div class="card">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Pièces jointes</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAttachmentModal">
                            <i class="bi bi-plus me-1"></i> Ajouter une pièce jointe
                        </button>
                    </div>
                    <div class="card-body py-2">
                        <?php if (empty($attachments)): ?>
                            <p class="text-muted mb-0">Aucune pièce jointe pour le moment.</p>
                        <?php else: ?>
                            <?php 
                            // Trier les pièces jointes par date de création (plus récent en premier)
                            usort($attachments, function($a, $b) {
                                return strtotime($b['date_creation']) - strtotime($a['date_creation']);
                            });
                            
                            foreach ($attachments as $attachment): 
                                $isPdf = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION)) === 'pdf';
                            ?>
                                <div class="card mb-2 <?php echo isset($attachment['masque_client']) && $attachment['masque_client'] == 1 ? 'bg-light-warning' : ''; ?>">
                                    <div class="card-header py-1 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo h($attachment['created_by_name'] ?? 'Utilisateur inconnu'); ?></strong>
                                            <small class="text-muted ms-2">
                                                <?php echo date('d/m/Y H:i', strtotime($attachment['date_creation'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-info btn-action" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#previewModal<?= $attachment['id'] ?>"
                                                    title="Aperçu">
                                                    <i class="<?php echo getIcon('preview', 'bi bi-eye'); ?>"></i>
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>materiel/download/<?php echo $attachment['id']; ?>" 
                                               class="btn btn-sm btn-outline-success btn-action" 
                                               title="Télécharger">
                                                <i class="<?php echo getIcon('download', 'bi bi-download'); ?>"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>materiel/toggleAttachmentVisibility/<?php echo $materiel['id']; ?>/<?php echo $attachment['id']; ?>" 
                                               class="btn btn-sm btn-outline-warning btn-action" 
                                               title="<?php echo isset($attachment['masque_client']) && $attachment['masque_client'] == 1 ? 'Rendre visible aux clients' : 'Masquer aux clients'; ?>"
                                               onclick="return confirm('<?php echo isset($attachment['masque_client']) && $attachment['masque_client'] == 1 ? 'Rendre cette pièce jointe visible aux clients ?' : 'Masquer cette pièce jointe aux clients ?'; ?>');">
                                                <i class="<?php echo isset($attachment['masque_client']) && $attachment['masque_client'] == 1 ? 'bi bi-eye' : 'bi bi-eye-slash'; ?>"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>materiel/deleteAttachment/<?php echo $materiel['id']; ?>/<?php echo $attachment['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger btn-action" 
                                               title="Supprimer"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette pièce jointe ?');">
                                                <i class="<?php echo getIcon('delete', 'bi bi-trash'); ?>"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="d-flex align-items-center">
                                            <?php if (isset($attachment['masque_client']) && $attachment['masque_client'] == 1): ?>
                                                <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning me-2" title="Masqué aux clients"></i>
                                            <?php endif; ?>
                                            <?php echo h($attachment['nom_fichier']); ?>
                                            <?php if ($attachment['commentaire']): ?>
                                                <small class="text-muted ms-2">(<?php echo h($attachment['commentaire']); ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal d'aperçu -->
                                <div class="modal fade" id="previewModal<?= $attachment['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><?= h($attachment['nom_fichier']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="preview-container">
                                                    <?php 
                                                    $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
                                                    if ($extension === 'pdf'): 
                                                    ?>
                                                        <iframe src="<?= BASE_URL . $attachment['chemin_fichier'] ?>" 
                                                                width="100%" 
                                                                height="600px" 
                                                                frameborder="0">
                                                        </iframe>
                                                    <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                        <img src="<?= BASE_URL . $attachment['chemin_fichier'] ?>" 
                                                             class="img-fluid" 
                                                             alt="<?= h($attachment['nom_fichier']) ?>">
                                                    <?php else: ?>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-1"></i> 
                                                            Ce type de fichier ne peut pas être prévisualisé. 
                                                            <a href="<?= BASE_URL; ?>materiel/download/<?= $attachment['id'] ?>" 
                                                               class="alert-link" 
                                                               target="_blank">
                                                                Télécharger le fichier
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="<?= BASE_URL; ?>materiel/download/<?= $attachment['id'] ?>" 
                                                   class="btn btn-primary" 
                                                   target="_blank">
                                                    <i class="bi bi-download me-1"></i> Télécharger
                                                </a>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            Matériel introuvable.
        </div>
    <?php endif; ?>
</div>

<!-- Modal de prévisualisation -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="previewContainer" class="text-center">
                    <!-- Le contenu sera injecté dynamiquement par JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajout de pièces jointes avec Drag & Drop -->
<div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="<?php echo BASE_URL; ?>materiel/addMultipleAttachments/<?php echo $materiel['id']; ?>" method="post" enctype="multipart/form-data" id="dragDropForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAttachmentModalLabel">
                        <i class="bi bi-cloud-upload me-2 me-1"></i>
                        Ajouter des pièces jointes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Zone de Drag & Drop -->
                    <div class="drop-zone" id="dropZone">
                        <div class="drop-message">
                            <i class="bi bi-cloud-upload me-1"></i>
                            Glissez-déposez vos fichiers ici<br>
                            <small class="text-muted">ou cliquez pour sélectionner</small>
                        </div>
                        
                        <input type="file" id="fileInput" multiple style="display: none;" 
                               accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>">
                        
                        <div class="file-list" id="fileList"></div>
                        
                        <div class="stats" id="stats" style="display: none;">
                            <div class="row">
                                <div class="col-6">
                                    <strong>Fichiers valides:</strong> <span id="validCount">0</span>
                                </div>
                                <div class="col-6">
                                    <strong>Fichiers rejetés:</strong> <span id="invalidCount">0</span>
                                </div>
                    </div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                    </div>
                        </div>
                    </div>
                    

                    
                    <!-- Liste des fichiers avec options individuelles -->
                    <div id="filesOptions" style="display: none;">
                        <h6 class="mt-3 mb-2">Options par fichier :</h6>
                        <div id="filesOptionsList"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-warning" id="clearAllBtn" style="display: none;">
                        <i class="bi bi-trash me-1 me-1"></i> Tout effacer
                    </button>
                    <button type="submit" class="btn btn-primary" id="uploadValidBtn" style="display: none;">
                        <i class="bi bi-upload me-1 me-1"></i> Uploader les fichiers valides
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.drop-zone {
    border: 2px dashed var(--bs-border-color);
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    background-color: var(--bs-body-bg);
    transition: all 0.3s ease;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.drop-zone.dragover {
    border-color: var(--bs-primary);
    background-color: var(--bs-primary-bg-subtle);
}

.drop-zone.dragover .drop-message {
    color: var(--bs-primary);
}

.drop-message {
    font-size: 1.1em;
    color: var(--bs-secondary-color);
    margin-bottom: 15px;
}

.drop-message i {
    font-size: 2.5em;
    margin-bottom: 10px;
    display: block;
}

.file-list {
    margin-top: 15px;
    max-height: 200px;
    overflow-y: auto;
}

.file-item {
    display: flex;
    align-items: center;
    padding: 8px;
    margin: 3px 0;
    border-radius: 5px;
    border: 1px solid var(--bs-border-color);
    background-color: var(--bs-body-bg);
}

.file-item.valid {
    background-color: var(--bs-success-bg-subtle);
    border-color: var(--bs-success-border-subtle);
}

.file-item.invalid {
    background-color: var(--bs-danger-bg-subtle);
    border-color: var(--bs-danger-border-subtle);
}

.file-name {
    flex: 1;
    font-weight: 500;
    font-size: 0.9em;
    color: var(--bs-body-color);
}

.file-size {
    color: var(--bs-secondary-color);
    font-size: 0.8em;
    margin: 0 8px;
}

.error-message {
    color: var(--bs-danger);
    font-size: 0.8em;
    margin-left: 8px;
}

.remove-file {
    background: none;
    border: none;
    color: var(--bs-danger);
    font-size: 1.1em;
    cursor: pointer;
    padding: 0 4px;
}

.remove-file:hover {
    color: var(--bs-danger-hover);
}

.stats {
    margin-top: 10px;
    padding: 8px;
    background-color: var(--bs-secondary-bg);
    border-radius: 5px;
    font-size: 0.9em;
    color: var(--bs-body-color);
}

.progress-bar {
    height: 3px;
    background-color: var(--bs-secondary-bg);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 8px;
}

.progress-fill {
    height: 100%;
    background-color: var(--bs-primary);
    width: 0%;
    transition: width 0.3s ease;
}

.file-options {
    margin-top: 8px;
    padding: 8px 12px;
    background-color: var(--bs-secondary-bg);
    border-radius: 5px;
    border: 1px solid var(--bs-border-color);
}

.file-options .form-control {
    font-size: 0.85em;
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
    height: 32px;
}

.file-options .form-control:focus {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-primary);
    color: var(--bs-body-color);
}

.file-options .form-check {
    margin: 0;
}

.file-options strong {
    font-size: 0.9em;
    color: var(--bs-body-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Dark mode specific adjustments */
[data-bs-theme="dark"] .drop-zone {
    border-color: var(--bs-border-color);
    background-color: var(--bs-body-bg);
}

[data-bs-theme="dark"] .file-item {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .file-item.valid {
    background-color: rgba(25, 135, 84, 0.1);
    border-color: rgba(25, 135, 84, 0.3);
}

[data-bs-theme="dark"] .file-item.invalid {
    background-color: rgba(220, 53, 69, 0.1);
    border-color: rgba(220, 53, 69, 0.3);
}

[data-bs-theme="dark"] .stats {
    background-color: var(--bs-secondary-bg);
}

[data-bs-theme="dark"] .file-options {
    background-color: var(--bs-secondary-bg);
}
</style>

<script>
// Event listeners pour les boutons de toggle
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password principal
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const passwordIcon = document.getElementById('passwordIcon');

    if (togglePassword && passwordInput && passwordIcon) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Changer l'icône
            if (type === 'text') {
                passwordIcon.classList.remove('bi-eye');
                passwordIcon.classList.add('bi-eye-slash');
                togglePassword.title = 'Masquer le mot de passe';
            } else {
                passwordIcon.classList.remove('bi-eye-slash');
                passwordIcon.classList.add('bi-eye');
                togglePassword.title = 'Afficher le mot de passe';
            }
        });
    }

    // Toggle password WiFi
    const togglePasswordWifi = document.getElementById('togglePasswordWifi');
    const passwordWifiInput = document.getElementById('passwordWifi');
    const passwordWifiIcon = document.getElementById('passwordWifiIcon');

    if (togglePasswordWifi && passwordWifiInput && passwordWifiIcon) {
        togglePasswordWifi.addEventListener('click', function() {
            const type = passwordWifiInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordWifiInput.setAttribute('type', type);
            
            // Changer l'icône
            if (type === 'text') {
                passwordWifiIcon.classList.remove('bi-eye');
                passwordWifiIcon.classList.add('bi-eye-slash');
                togglePasswordWifi.title = 'Masquer le mot de passe WiFi';
            } else {
                passwordWifiIcon.classList.remove('bi-eye-slash');
                passwordWifiIcon.classList.add('bi-eye');
                togglePasswordWifi.title = 'Afficher le mot de passe WiFi';
            }
        });
    }
});

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('passwordIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

function togglePasswordWifi() {
    const passwordInput = document.getElementById('passwordWifi');
    const toggleIcon = document.getElementById('passwordWifiIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
}

// Classe Drag & Drop Uploader pour la modale
class DragDropUploader {
    constructor() {
        this.dropZone = document.getElementById('dropZone');
        this.fileInput = document.getElementById('fileInput');
        this.fileList = document.getElementById('fileList');
        this.stats = document.getElementById('stats');
        this.validCount = document.getElementById('validCount');
        this.invalidCount = document.getElementById('invalidCount');
        this.progressFill = document.getElementById('progressFill');
        this.uploadValidBtn = document.getElementById('uploadValidBtn');
        this.clearAllBtn = document.getElementById('clearAllBtn');
        this.filesOptions = document.getElementById('filesOptions');
        this.filesOptionsList = document.getElementById('filesOptionsList');
        this.dragDropForm = document.getElementById('dragDropForm');
        
        this.files = [];
        this.allowedExtensions = [];
        this.maxSize = parsePhpSize('<?php echo ini_get("upload_max_filesize"); ?>');
        
        this.init();
    }
    
    async init() {
        await this.loadAllowedExtensions();
        this.setupEventListeners();
    }
    
    async loadAllowedExtensions() {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>settings/getAllowedExtensions');
            const data = await response.json();
            this.allowedExtensions = data.extensions || [];
        } catch (error) {
            console.error('Erreur lors du chargement des extensions autorisées:', error);
        }
    }
    
    setupEventListeners() {
        // Drag & Drop events
        this.dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.dropZone.classList.add('dragover');
        });
        
        this.dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            this.dropZone.classList.remove('dragover');
        });
        
        this.dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            this.dropZone.classList.remove('dragover');
            const files = Array.from(e.dataTransfer.files);
            this.handleFiles(files);
        });
        
        // Click to select files
        this.dropZone.addEventListener('click', () => {
            this.fileInput.click();
        });
        
        this.fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            this.handleFiles(files);
        });
        
        // Action buttons
        this.uploadValidBtn.addEventListener('click', () => {
            this.uploadValidFiles();
        });
        
        this.clearAllBtn.addEventListener('click', () => {
            this.clearAllFiles();
        });
        
        // Form submission
        this.dragDropForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.uploadValidFiles();
        });
    }
    
    handleFiles(newFiles) {
        const validatedFiles = this.validateFiles(newFiles);
        this.files = [...this.files, ...validatedFiles];
        this.displayFiles();
        this.updateStats();
        this.updateFilesOptions();
    }
    
    validateFiles(files) {
        return files.map(file => {
            const extension = file.name.split('.').pop().toLowerCase();
            const isValid = this.allowedExtensions.includes(extension);
            const isSizeValid = file.size <= this.maxSize;
            
            let error = null;
            if (!isSizeValid) {
                error = `Le fichier est trop volumineux (${this.formatFileSize(file.size)}). Taille maximale autorisée : ${this.formatFileSize(this.maxSize)}.`;
            } else if (!isValid) {
                error = 'Ce format n\'est pas accepté, rapprochez-vous de l\'administrateur du site, ou utilisez un format compressé.';
            }
            
            return {
                file,
                isValid: isValid && isSizeValid,
                extension,
                error
            };
        });
    }
    
    displayFiles() {
        this.fileList.innerHTML = '';
        
        this.files.forEach((fileData, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = `file-item ${fileData.isValid ? 'valid' : 'invalid'}`;
            
            fileItem.innerHTML = `
                <span class="file-name">${fileData.file.name}</span>
                <span class="file-size">${this.formatFileSize(fileData.file.size)}</span>
                ${fileData.error ? `<span class="error-message">${fileData.error}</span>` : ''}
                <button type="button" class="remove-file" onclick="uploader.removeFile(${index})">×</button>
            `;
            
            this.fileList.appendChild(fileItem);
        });
    }
    
    updateFilesOptions() {
        const validFiles = this.files.filter(f => f.isValid);
        
        if (validFiles.length > 0) {
            this.filesOptions.style.display = 'block';
            this.filesOptionsList.innerHTML = '';
            
            validFiles.forEach((fileData, index) => {
                const fileOptionsDiv = document.createElement('div');
                fileOptionsDiv.className = 'file-options mb-2';
                fileOptionsDiv.innerHTML = `
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <strong class="me-3" style="min-width: 120px;">${fileData.file.name}</strong>
                                <input type="text" class="form-control form-control-sm" name="file_description[${index}]" 
                                       placeholder="Titre ou description (optionnel)" style="max-width: 200px;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="file_masque_client[${index}]" value="1" id="masque_${index}">
                                <label class="form-check-label" for="masque_${index}">
                                    <i class="<?php echo getIcon('visibility_hidden', 'bi bi-eye-slash'); ?> text-warning me-1"></i>
                                    Masquer aux clients
                                </label>
                            </div>
                        </div>
                    </div>
                `;
                
                this.filesOptionsList.appendChild(fileOptionsDiv);
            });
        } else {
            this.filesOptions.style.display = 'none';
        }
    }
    
    removeFile(index) {
        this.files.splice(index, 1);
        this.displayFiles();
        this.updateStats();
        this.updateFilesOptions();
    }
    
    updateStats() {
        const validFiles = this.files.filter(f => f.isValid);
        const invalidFiles = this.files.filter(f => !f.isValid);
        
        this.validCount.textContent = validFiles.length;
        this.invalidCount.textContent = invalidFiles.length;
        
        if (this.files.length > 0) {
            this.stats.style.display = 'block';
            this.uploadValidBtn.style.display = 'inline-block';
            this.clearAllBtn.style.display = 'inline-block';
            
            const progress = (validFiles.length / this.files.length) * 100;
            this.progressFill.style.width = `${progress}%`;
        } else {
            this.stats.style.display = 'none';
            this.uploadValidBtn.style.display = 'none';
            this.clearAllBtn.style.display = 'none';
        }
    }
    
    clearAllFiles() {
        this.files = [];
        this.displayFiles();
        this.updateStats();
        this.updateFilesOptions();
        this.fileInput.value = '';
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    async uploadValidFiles() {
        const validFiles = this.files.filter(f => f.isValid);
        
        if (validFiles.length === 0) {
            alert('Aucun fichier valide à uploader');
            return;
        }
        
        // Préparer les données du formulaire
        const formData = new FormData();
        
        // Ajouter les fichiers
        validFiles.forEach((fileData, index) => {
            formData.append(`attachments[${index}]`, fileData.file);
        });
        

        
        // Ajouter les options individuelles
        validFiles.forEach((fileData, index) => {
            const descriptionInput = document.querySelector(`input[name="file_description[${index}]"]`);
            const masqueClientInput = document.querySelector(`input[name="file_masque_client[${index}]"]`);
            
            if (descriptionInput && descriptionInput.value) {
                formData.append(`file_description[${index}]`, descriptionInput.value);
            }
            if (masqueClientInput && masqueClientInput.checked) {
                formData.append(`file_masque_client[${index}]`, '1');
            }
        });
        
        // Désactiver le bouton pendant l'upload
        this.uploadValidBtn.disabled = true;
        this.uploadValidBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1 me-1"></i>Upload en cours...';
        
        try {
            const response = await fetch(this.dragDropForm.action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert(`${validFiles.length} fichier(s) uploadé(s) avec succès !`);
                // Fermer la modale et recharger la page
                const modal = bootstrap.Modal.getInstance(document.getElementById('addAttachmentModal'));
                modal.hide();
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                alert(`Erreur lors de l'upload : ${result.error || 'Erreur inconnue'}`);
            }
        } catch (error) {
            console.error('Erreur lors de l\'upload:', error);
            alert('Erreur lors de l\'upload des fichiers');
        } finally {
            this.uploadValidBtn.disabled = false;
            this.uploadValidBtn.innerHTML = '<i class="bi bi-upload me-1 me-1"></i> Uploader les fichiers valides';
        }
    }
}

// Initialiser l'uploader quand la modale est ouverte
document.addEventListener('DOMContentLoaded', function() {
    let uploader;
    
    // Initialiser l'uploader quand la modale s'ouvre
    document.getElementById('addAttachmentModal').addEventListener('shown.bs.modal', function() {
        if (!uploader) {
            uploader = new DragDropUploader();
        }
    });
});

function confirmDelete(materielId, materielName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer le matériel "' + materielName + '" ?\n\nCette action est irréversible et supprimera définitivement le matériel et toutes ses données associées (pièces jointes, historique, etc.).')) {
        window.location.href = '<?= BASE_URL ?>materiel/delete/' + materielId;
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 