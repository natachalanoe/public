<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

setPageVariables('Configuration email', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les settings actuels
$config = Config::getInstance();
$mailSettings = [
    'mail_host' => $config->get('mail_host', ''),
    'mail_port' => $config->get('mail_port', '587'),
    'mail_username' => $config->get('mail_username', ''),
    'mail_password' => $config->get('mail_password', ''),
    'mail_encryption' => $config->get('mail_encryption', 'tls'),
    'mail_from_address' => $config->get('mail_from_address', ''),
    'mail_from_name' => $config->get('mail_from_name', ''),
    'email_auto_send_creation' => $config->get('email_auto_send_creation', '1'),
    'email_auto_send_closing' => $config->get('email_auto_send_closing', '1'),
    'email_auto_send_bon' => $config->get('email_auto_send_bon', '0'),
    'test_email' => $config->get('test_email', ''),
];

// Les templates sont récupérés par le contrôleur
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-envelope me-2 me-1"></i>Configuration email
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>settings" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
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

    <!-- Indicateur mode test -->
    <?php if (!empty($mailSettings['test_email'])): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Mode test activé :</strong> Tous les emails seront envoyés à 
            <strong><?= h($mailSettings['test_email']) ?></strong> au lieu des destinataires réels.
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Configuration SMTP -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-server text-primary me-2 me-1"></i>
                        Configuration SMTP
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= BASE_URL ?>settings/saveEmailConfig">
                        <!-- Paramètres SMTP -->
                        <div class="mb-3">
                            <label for="mail_host" class="form-label">Serveur SMTP</label>
                            <input type="text" class="form-control" id="mail_host" name="mail_host" 
                                   value="<?= h($mailSettings['mail_host']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_port" class="form-label">Port</label>
                            <input type="number" class="form-control" id="mail_port" name="mail_port" 
                                   value="<?= h($mailSettings['mail_port']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_username" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="mail_username" name="mail_username" 
                                   value="<?= h($mailSettings['mail_username']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="mail_password" name="mail_password" 
                                   value="<?= h($mailSettings['mail_password']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_encryption" class="form-label">Chiffrement</label>
                            <select class="form-select" id="mail_encryption" name="mail_encryption">
                                <option value="none" <?= $mailSettings['mail_encryption'] == 'none' ? 'selected' : '' ?>>Aucun</option>
                                <option value="tls" <?= $mailSettings['mail_encryption'] == 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= $mailSettings['mail_encryption'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_from_address" class="form-label">Adresse d'expédition</label>
                            <input type="email" class="form-control" id="mail_from_address" name="mail_from_address" 
                                   value="<?= h($mailSettings['mail_from_address']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mail_from_name" class="form-label">Nom d'expédition</label>
                            <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" 
                                   value="<?= h($mailSettings['mail_from_name']) ?>" required>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Sauvegarder la configuration SMTP
                            </button>
                            <button type="button" class="btn btn-outline-success" id="testSmtpBtn">
                                <i class="bi bi-envelope-check me-1"></i> Tester la configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Paramètres d'envoi automatique -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear text-primary me-2 me-1"></i>
                        Paramètres d'envoi automatique
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= BASE_URL ?>settings/saveEmailSettings">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_auto_send_creation" 
                                       name="email_auto_send_creation" value="1" 
                                       <?= $mailSettings['email_auto_send_creation'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="email_auto_send_creation">
                                    Envoi automatique à la création d'intervention
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_auto_send_closing" 
                                       name="email_auto_send_closing" value="1" 
                                       <?= $mailSettings['email_auto_send_closing'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="email_auto_send_closing">
                                    Envoi automatique à la fermeture d'intervention
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_auto_send_bon" 
                                       name="email_auto_send_bon" value="1" 
                                       <?= $mailSettings['email_auto_send_bon'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="email_auto_send_bon">
                                    Envoi automatique du bon d'intervention (actuellement désactivé)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Email de test (développement)</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" 
                                   value="<?= h($mailSettings['test_email']) ?>" 
                                   placeholder="dev@example.com">
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Si renseigné, tous les emails seront envoyés à cette adresse au lieu des destinataires réels.
                                <strong>Utile pour le développement.</strong>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Sauvegarder les paramètres
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Gestion des templates -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-text text-primary me-2 me-1"></i>
                        Templates d'emails
                    </h5>
                    <a href="<?= BASE_URL ?>settings/emailTemplate" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus me-1"></i> Nouveau template
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($templates)): ?>
                        <p class="text-muted">Aucun template configuré.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Type</th>
                                        <th>Sujet</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($templates as $template): ?>
                                        <tr>
                                            <td><?= h($template['name']) ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= h($template['template_type']) ?>
                                                </span>
                                            </td>
                                            <td><?= h($template['subject']) ?></td>
                                            <td>
                                                <?php if ($template['is_active']): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>settings/emailTemplate/<?= $template['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil me-1"></i> Modifier
                                                </a>
                                                <a href="<?= BASE_URL ?>settings/deleteEmailTemplate/<?= $template['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce template ?')">
                                                    <i class="bi bi-trash me-1"></i> Supprimer
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
document.getElementById('testSmtpBtn').addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    
    // Désactiver le bouton et afficher un indicateur de chargement
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Test en cours...';
    
    // Récupérer les valeurs du formulaire
    const formData = new FormData();
    formData.append('mail_host', document.getElementById('mail_host').value);
    formData.append('mail_port', document.getElementById('mail_port').value);
    formData.append('mail_username', document.getElementById('mail_username').value);
    formData.append('mail_password', document.getElementById('mail_password').value);
    formData.append('mail_encryption', document.getElementById('mail_encryption').value);
    formData.append('mail_from_address', document.getElementById('mail_from_address').value);
    formData.append('mail_from_name', document.getElementById('mail_from_name').value);
    
    // Envoyer la requête de test
    fetch('<?= BASE_URL ?>settings/testSmtp', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Afficher un message de succès
            showModal('success', 'Test SMTP réussi !', data.message);
        } else {
            // Afficher un message d'erreur
            showModal('danger', 'Test SMTP échoué', data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showModal('danger', 'Erreur lors du test SMTP', error.message);
    })
    .finally(() => {
        // Réactiver le bouton
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

function showModal(type, title, message) {
    // Supprimer les anciennes modales de test SMTP
    const existingModals = document.querySelectorAll('#smtpTestModal');
    existingModals.forEach(modal => modal.remove());
    
    // Définir les icônes selon le type
    const icons = {
        'success': 'bi-check-circle-fill text-success',
        'danger': 'bi-exclamation-triangle-fill text-danger',
        'warning': 'bi-exclamation-triangle-fill text-warning',
        'info': 'bi-info-circle-fill text-info'
    };
    
    // Créer la modale
    const modalHtml = `
        <div class="modal fade" id="smtpTestModal" tabindex="-1" aria-labelledby="smtpTestModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="smtpTestModalLabel">
                            <i class="bi ${icons[type] || icons['info']} me-2"></i>
                            ${title}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-${type} mb-0">
                            <i class="bi ${icons[type] || icons['info']} me-2"></i>
                            ${message}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i> Fermer
                        </button>
                        ${type === 'success' ? '' : `
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('testSmtpBtn').click()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Tester à nouveau
                        </button>
                        `}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Insérer la modale dans le DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Afficher la modale
    const modal = new bootstrap.Modal(document.getElementById('smtpTestModal'));
    modal.show();
    
    // Supprimer la modale du DOM quand elle est fermée
    document.getElementById('smtpTestModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}
</script>
