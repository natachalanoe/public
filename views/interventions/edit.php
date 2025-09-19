<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';

// Vérification des permissions pour modifier les interventions
if (!canModifyInterventions()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier cette intervention.";
    header('Location: ' . BASE_URL . 'interventions/view/' . ($intervention['id'] ?? ''));
    exit;
}

setPageVariables(
    'Intervention',
    'intervention'
);

// Définir la page courante pour le menu
$currentPage = 'interventions';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Gestion des Interventions</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        

   
        <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>

        
        <button type="submit" form="interventionForm" class="btn btn-primary">Enregistrer les modifications</button>

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

    <?php if ($intervention): ?>
        <!-- Formulaire de modification -->
        <div class="card">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="card-title mb-0">
                            <span class="fw-bold me-3"><?= h($intervention['reference'] ?? '') ?></span>
                            <input type="text" class="form-control d-inline-block bg-body text-body" id="title" name="title" value="<?= h($intervention['title'] ?? '') ?>" required>
                        </h5>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-0 text-white">Date de création</label>
                                <input type="date" class="form-control bg-body text-body" id="created_date" name="created_date" value="<?= date('Y-m-d', strtotime($intervention['created_at'])) ?>" form="interventionForm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-0 text-white">Heure de création</label>
                                <input type="time" class="form-control bg-body text-body" id="created_time" name="created_time" value="<?= date('H:i', strtotime($intervention['created_at'])) ?>" form="interventionForm">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body py-2">
                <form action="<?php echo BASE_URL; ?>interventions/update/<?php echo $intervention['id']; ?>" method="post" id="interventionForm">
                    <div class="row g-3">
                        <!-- Colonne 1 : Client, Site, Salle -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Client -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Client *</label>
                                    <select class="form-select bg-body text-body" id="client_id" name="client_id" required>
                                        <option value="">Sélectionner un client</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?= $client['id'] ?>" <?= $client['id'] == $intervention['client_id'] ? 'selected' : '' ?>>
                                                <?= h($client['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Site -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Site</label>
                                    <select class="form-select bg-body text-body" id="site_id" name="site_id">
                                        <option value="">Sélectionner un site</option>
                                        <?php foreach ($sites as $site): ?>
                                            <option value="<?= $site['id'] ?>" <?= $site['id'] == $intervention['site_id'] ? 'selected' : '' ?>>
                                                <?= h($site['name'] ?? '') ?><?= $site['status'] == 0 ? ' (Site désactivé)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if ($intervention['site_id'] && $intervention['site_id'] !== '0' && !in_array($intervention['site_id'], array_column($sites, 'id'))): ?>
                                            <option value="<?= $intervention['site_id'] ?>" selected style="display: none;">
                                                <?= h($intervention['site_name'] ?? 'Site inconnu') ?>
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Salle -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Salle</label>
                                    <select class="form-select bg-body text-body" id="room_id" name="room_id">
                                        <option value="">Sélectionner une salle</option>
                                        <?php foreach ($rooms as $room): ?>
                                            <option value="<?= $room['id'] ?>" <?= $room['id'] == $intervention['room_id'] ? 'selected' : '' ?>>
                                                <?= h($room['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne 2 : Type, Déplacement, Contrat -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Type d'intervention -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Type d'intervention *</label>
                                    <select class="form-select bg-body text-body" id="type_id" name="type_id" required>
                                        <option value="">Sélectionner un type</option>
                                        <?php foreach ($types as $type): ?>
                                            <option value="<?= $type['id'] ?>" <?= $type['id'] == $intervention['type_id'] ? 'selected' : '' ?>>
                                                <?= h($type['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Déplacement -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Déplacement</label>
                                    <input type="text" class="form-control bg-body text-body" id="type_requires_travel" value="<?php echo isset($intervention['type_requires_travel']) && $intervention['type_requires_travel'] == 1 ? 'Oui' : 'Non'; ?>" readonly>
                                    <input type="hidden" name="type_requires_travel" value="<?php echo isset($intervention['type_requires_travel']) ? $intervention['type_requires_travel'] : '0'; ?>">
                                </div>

                                <!-- Contrat -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Contrat associé *</label>
                                    <select class="form-select bg-body text-body" id="contract_id" name="contract_id" required>
                                        <option value="">Sélectionner un contrat</option>
                                        <?php foreach ($contracts as $contract): ?>
                                            <option value="<?= $contract['id'] ?>" <?= $contract['id'] == $intervention['contract_id'] ? 'selected' : '' ?>>
                                                <?= h($contract['name'] ?? '') ?> (<?= h($contract['contract_type_name'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne 3 : Statut, Priorité, Technicien -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Statut -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Statut *</label>
                                    <select class="form-select bg-body text-body" id="status_id" name="status_id" required>
                                        <option value="">Sélectionner un statut</option>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= $status['id'] ?>" <?= $status['id'] == $intervention['status_id'] ? 'selected' : '' ?>>
                                                <?= h($status['name'] ?: '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Priorité -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Priorité *</label>
                                    <select class="form-select bg-body text-body" id="priority_id" name="priority_id" required>
                                        <?php foreach ($priorities as $priority): ?>
                                            <option value="<?= $priority['id'] ?>" <?= $priority['id'] == $intervention['priority_id'] ? 'selected' : '' ?>>
                                                <?= h($priority['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Technicien -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Technicien</label>
                                    <select class="form-select bg-body text-body" id="technician_id" name="technician_id">
                                        <option value="">Sélectionner un technicien</option>
                                        <?php foreach ($technicians as $technician): ?>
                                            <option value="<?= $technician['id'] ?>" <?= $technician['id'] == $intervention['technician_id'] ? 'selected' : '' ?>>
                                                <?= h($technician['first_name'] ?? '') ?> <?= h($technician['last_name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Colonne 4 : Date planifiée, Heure planifiée, Durée -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Date planifiée -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Date planifiée</label>
                                    <input type="date" class="form-control bg-body text-body" id="date_planif" name="date_planif" 
                                           value="<?= !empty($intervention['date_planif']) ? date('Y-m-d', strtotime($intervention['date_planif'])) : '' ?>">
                                </div>

                                <!-- Heure planifiée -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Heure planifiée</label>
                                    <input type="time" class="form-control bg-body text-body" id="heure_planif" name="heure_planif" 
                                           value="<?= h($intervention['heure_planif'] ?? '') ?>">
                                </div>
                                
                                <!-- Durée -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Durée</label>
                                    <select class="form-select bg-body text-body" id="duration" name="duration">
                                        <option value="">Sélectionner une durée</option>
                                        <?php foreach ($durations as $duration): ?>
                                            <option value="<?= $duration['duration'] ?>" <?= $duration['duration'] == $intervention['duration'] ? 'selected' : '' ?>>
                                                <?= number_format($duration['duration'], 2) ?> heure(s)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Description sur une ligne complète -->
                        <div class="col-12 mt-3">
                            <div class="card">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">Demande/description du problème</h6>
                            </div>
                                <div class="card-body py-2">
                                    <textarea class="form-control bg-body text-body" id="description" name="description" rows="5"><?php echo h($intervention['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Informations de contact et demande -->
                        <div class="col-12 mt-3">
                            <div class="card contact-info-card">
                                <div class="card-header py-2 contact-info-header">
                                    <h6 class="card-title mb-0 fw-bold">
                                        <i class="bi bi-person-lines-fill me-2"></i>Informations de contact et demande
                                    </h6>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Demande par</label>
                                            <input type="text" class="form-control bg-body text-body" id="demande_par" name="demande_par" value="<?php echo h($intervention['demande_par'] ?? ''); ?>" placeholder="Nom de la personne qui a demandé l'intervention">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Référence client</label>
                                            <input type="text" class="form-control bg-body text-body" id="ref_client" name="ref_client" value="<?= h($intervention['ref_client'] ?? '') ?>" placeholder="Référence interne du client">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Contact existant</label>
                                            <select class="form-select bg-body text-body" id="contact_client_select" name="contact_client_select">
                                                <option value="">Sélectionner un contact existant</option>
                                                <!-- Les contacts seront chargés dynamiquement selon le client -->
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Ou saisir un email</label>
                                            <input type="email" class="form-control bg-body text-body" id="contact_client" name="contact_client" value="<?php echo h($intervention['contact_client'] ?? ''); ?>" placeholder="email@exemple.com">
                                            <div class="invalid-feedback" id="email-error"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <!-- Le bouton submit est maintenant en haut de la page -->
                    </div>
                </form>
            </div>
        </div>

        <!-- Espace entre le formulaire et les sections -->
        <div class="mb-4"></div>

        <!-- Section Commentaires et Pièces jointes -->
        <div class="row">
            <!-- Section Commentaires -->
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Compte-rendu/observations</h5>
                        <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCommentModal">
                                <i class="bi bi-plus me-1"></i> Ajouter un commentaire
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted mb-0">Aucun commentaire pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="card mb-2 <?php echo $comment['is_solution'] ? 'bg-success bg-opacity-10' : ''; ?>">
                                    <div class="card-header py-1 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo h($comment['created_by_name'] ?? 'Utilisateur inconnu'); ?></strong>
                                            <small class="text-muted ms-2">
                                                <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php if ($comment['is_solution']): ?>
                                                <span class="badge bg-success">Solution</span>
                                            <?php endif; ?>
                                            <?php if ($comment['visible_by_client']): ?>
                                                <span class="badge bg-info">Visible par le client</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Interne</span>
                                            <?php endif; ?>
                                            <?php if (canDelete()): ?>
                                                <a href="<?php echo BASE_URL; ?>interventions/deleteComment/<?php echo $comment['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger btn-action" 
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?');">
                                                    <i class="<?php echo getIcon('delete', 'bi bi-trash'); ?>"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        <p class="card-text mb-0"><?php echo nl2br(h($comment['comment'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section Pièces jointes -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Pièces jointes</h5>
                        <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAttachmentModal">
                                <i class="bi bi-plus me-1"></i> Ajouter une pièce jointe
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if (empty($attachments)): ?>
                            <p class="text-muted mb-0">Aucune pièce jointe pour le moment.</p>
                        <?php else: ?>
                            <?php 
                            // Trier les pièces jointes pour mettre les bons d'intervention en premier
                            usort($attachments, function($a, $b) {
                                $aIsBI = $a['type_liaison'] === 'bi';
                                $bIsBI = $b['type_liaison'] === 'bi';
                                if ($aIsBI && !$bIsBI) return -1;
                                if (!$aIsBI && $bIsBI) return 1;
                                return strtotime($b['date_creation']) - strtotime($a['date_creation']);
                            });
                            
                            foreach ($attachments as $attachment): 
                                $isBI = $attachment['type_liaison'] === 'bi';
                                $originalFileName = $attachment['nom_personnalise'] ?? $attachment['nom_fichier'];
                                $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                                $isPdf = $extension === 'pdf';
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg']);
                                $isExcel = in_array($extension, ['xls', 'xlsx']);
                            ?>
                                <div class="card mb-2">
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
                                                    <i class="<?php echo getIcon('visibility', 'bi bi-eye'); ?>"></i>
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>interventions/download/<?php echo $attachment['id']; ?>" 
                                               class="btn btn-sm btn-outline-success btn-action" 
                                               title="Télécharger">
                                                <i class="<?php echo getIcon('download', 'bi bi-download'); ?>"></i>
                                            </a>
                                            <?php if (canDelete()): ?>
                                                <a href="<?php echo BASE_URL; ?>interventions/deleteAttachment/<?php echo $attachment['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger btn-action" 
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette pièce jointe ?');">
                                                    <i class="<?php echo getIcon('delete', 'bi bi-trash'); ?>"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="d-flex align-items-center">
                                            <?php if ($isBI): ?>
                                                <i class="bi bi-file-pdf text-danger me-2 me-1"></i>
                                                <span class="badge bg-info me-2">BI</span>
                                            <?php elseif ($isPdf): ?>
                                                <i class="bi bi-file-pdf text-danger me-2 me-1"></i>
                                            <?php elseif ($isImage): ?>
                                                <i class="bi bi-image-fill text-primary me-2 me-1"></i>
                                            <?php elseif ($isExcel): ?>
                                                <i class="bi bi-file-spreadsheet text-success me-2 me-1"></i>
                                            <?php else: ?>
                                                <i class="bi bi-file-earmark text-secondary me-2 me-1"></i>
                                            <?php endif; ?>
                                            <div class="attachment-name flex-grow-1">
                                                <div class="display-name"><?php echo h($attachment['nom_fichier']); ?></div>
                                                <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                    <div class="original-name text-muted small"><?php echo h($attachment['nom_personnalise']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" 
                                                        onclick="editAttachmentName(<?= $attachment['id'] ?>, '<?= h($attachment['nom_fichier']) ?>')"
                                                        title="Modifier le nom">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal d'aperçu -->
                                <div class="modal fade" id="previewModal<?= $attachment['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <div class="attachment-name">
                                                        <div class="display-name"><?= h($attachment['nom_fichier']) ?></div>
                                                        <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                            <div class="original-name text-muted small"><?= h($attachment['nom_personnalise']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </h5>
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
                                                            <a href="<?= BASE_URL; ?>interventions/download/<?= $attachment['id'] ?>" 
                                                               class="alert-link" 
                                                               target="_blank">
                                                                Télécharger le fichier
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="<?= BASE_URL; ?>interventions/download/<?= $attachment['id'] ?>" 
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

        <!-- Section Historique (Bouton flottant) -->
        <button type="button" 
                class="btn btn-sm btn-outline-secondary position-fixed bottom-0 end-0 m-3" 
                data-bs-toggle="modal" 
                data-bs-target="#historyModal"
                title="Historique des modifications">
            <i class="bi bi-clock-history me-1"></i>
        </button>

        <!-- Modal Historique -->
        <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-clock-history me-2 me-1"></i> Historique des modifications
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($history)): ?>
                            <p class="text-muted">Aucun historique disponible.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($history as $entry): ?>
                                    <div class="list-group-item px-0">
                                        <small class="text-muted d-block ps-3">
                                            <?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?>
                                            par <?php echo isset($entry['changed_by_name']) && $entry['changed_by_name'] !== null ? h($entry['changed_by_name']) : 'Utilisateur inconnu'; ?>
                                        </small>
                                        <div class="mt-1 ps-3">
                                            <?php echo isset($entry['description']) && $entry['description'] !== null ? h($entry['description']) : 'Aucune description disponible.'; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            Intervention introuvable.
        </div>
    <?php endif; ?>
</div>

<!-- Modal Ajout de commentaire -->
<div class="modal fade" id="addCommentModal" tabindex="-1" aria-labelledby="addCommentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo BASE_URL; ?>interventions/addComment/<?php echo $intervention['id']; ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCommentModalLabel">Ajouter un commentaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="comment" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="comment" name="comment" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="visible_by_client" name="visible_by_client">
                            <label class="form-check-label" for="visible_by_client">
                                Visible par le client
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_solution" name="is_solution">
                            <label class="form-check-label" for="is_solution">
                                Marquer comme solution
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ajout de pièce jointe -->
<div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo BASE_URL; ?>interventions/addAttachment/<?php echo $intervention['id']; ?>" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAttachmentModalLabel">Ajouter une pièce jointe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Fichier</label>
                        <input type="file" class="form-control" id="attachment" name="attachment" accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>" required>
                        <div class="form-text">
                            Formats acceptés : Images, documents, archives et fichiers texte<br>
                            Taille maximale : <?php echo ini_get('upload_max_filesize'); ?>
                        </div>
                        <div id="attachment-error" class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="custom_name" class="form-label">Nom du fichier (optionnel)</label>
                        <input type="text" class="form-control" id="custom_name" name="custom_name" placeholder="Nom personnalisé pour ce fichier" maxlength="255">
                        <div class="form-text">
                            Si laissé vide, le nom original du fichier sera utilisé
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="addAttachmentModal-submit">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'édition du nom de pièce jointe -->
<div class="modal fade" id="editAttachmentNameModal" tabindex="-1" aria-labelledby="editAttachmentNameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editAttachmentNameForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAttachmentNameModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>
                        Modifier le nom du fichier
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editAttachmentName" class="form-label">Nom du fichier</label>
                        <input type="text" class="form-control" id="editAttachmentName" name="nom_fichier" 
                               placeholder="Nom personnalisé pour ce fichier" maxlength="255" required>
                        <div class="form-text">
                            Le nom original du fichier sera conservé pour référence.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom original</label>
                        <div class="form-control-plaintext text-muted small" id="editOriginalName">
                            <!-- Sera rempli par JavaScript -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>
                        Sauvegarder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour mettre à jour les sites et les salles en fonction du client sélectionné -->
<script>
    // Initialiser BASE_URL pour JavaScript
    initBaseUrl('<?php echo BASE_URL; ?>');
    
    document.addEventListener('DOMContentLoaded', function() {
        const clientSelect = document.getElementById('client_id');
        const siteSelect = document.getElementById('site_id');
        const roomSelect = document.getElementById('room_id');
        const typeSelect = document.getElementById('type_id');
        const typeRequiresTravelInput = document.getElementById('type_requires_travel');
        const typeRequiresTravelHidden = document.querySelector('input[name="type_requires_travel"]');
        const contractSelect = document.getElementById('contract_id');
        
        // Utiliser les fonctions centralisées pour charger les sites et salles dynamiquement
        clientSelect.addEventListener('change', function() {
            loadSites(this.value, 'site_id', null, null, function() {
                updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id', '<?php echo $intervention['contract_id'] ?? ''; ?>');
            });
        });
        
        siteSelect.addEventListener('change', function() {
            loadRooms(this.value, 'room_id', null, function() {
                updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id', '<?php echo $intervention['contract_id'] ?? ''; ?>');
            });
        });
        
        roomSelect.addEventListener('change', function() {
            updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id', '<?php echo $intervention['contract_id'] ?? ''; ?>');
            // Pré-sélectionner le contrat associé à la salle sélectionnée
            const roomId = this.value;
            if (roomId) {
                fetch(`${BASE_URL}interventions/getContractByRoom/${roomId}`)
                    .then(response => response.json())
                    .then(contract => {
                        if (contract && contract.id) {
                            setTimeout(() => {
                                const option = contractSelect.querySelector(`option[value="${contract.id}"]`);
                                if (option) {
                                    option.selected = true;
                                }
                            }, 100);
                        }
                    })
                    .catch(error => console.error('Erreur lors de la récupération du contrat de la salle:', error));
            }
        });
        
        typeSelect.addEventListener('change', function() {
            updateTypeRequiresTravel('type_id', 'type_requires_travel', 'type_requires_travel');
        });

        // Gestion des contacts clients
        const contactClientSelect = document.getElementById('contact_client_select');
        const contactClientInput = document.getElementById('contact_client');
        
        // Charger les contacts quand le client change
        clientSelect.addEventListener('change', function() {
            loadContacts(this.value);
        });
        
        // Quand on sélectionne un contact existant, remplir le champ email
        contactClientSelect.addEventListener('change', function() {
            if (this.value) {
                contactClientInput.value = this.value;
            }
        });
        
        // Fonction pour charger les contacts d'un client
        function loadContacts(clientId) {
            if (!clientId) {
                contactClientSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
                return;
            }
            
            fetch(`${BASE_URL}interventions/getContacts/${clientId}`)
                .then(response => response.json())
                .then(contacts => {
                    contactClientSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
                    contacts.forEach(contact => {
                        const option = document.createElement('option');
                        option.value = contact.email;
                        option.textContent = `${contact.first_name} ${contact.last_name} (${contact.email})`;
                        contactClientSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Erreur lors du chargement des contacts:', error));
        }
        
        // Charger les contacts au chargement de la page si un client est déjà sélectionné
        if (clientSelect.value) {
            loadContacts(clientSelect.value);
        }
        
        // Validation de l'email
        const emailError = document.getElementById('email-error');
        
        contactClientInput.addEventListener('input', function() {
            validateEmail(this.value);
        });
        
        contactClientInput.addEventListener('blur', function() {
            validateEmail(this.value);
        });
        
        function validateEmail(email) {
            // Réinitialiser les erreurs
            contactClientInput.classList.remove('is-invalid', 'is-valid');
            emailError.textContent = '';
            
            // Si le champ est vide, pas de validation
            if (!email.trim()) {
                return true;
            }
            
            // Regex pour valider l'email
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (!emailRegex.test(email)) {
                contactClientInput.classList.add('is-invalid');
                emailError.textContent = 'Format d\'email invalide. Exemple : nom@domaine.com';
                return false;
            } else {
                contactClientInput.classList.add('is-valid');
                return true;
            }
        }
        
        // Validation du formulaire avant soumission
        document.getElementById('interventionForm').addEventListener('submit', function(e) {
            const email = contactClientInput.value.trim();
            if (email && !validateEmail(email)) {
                e.preventDefault();
                contactClientInput.focus();
                return false;
            }
        });

        // Initialiser la validation de fichiers pour le modal d'ajout de pièce jointe
            // Validation côté client avec récupération des extensions autorisées
    const fileInput = document.getElementById('attachment');
    const fileError = document.getElementById('attachment-error');
    const submitButton = document.getElementById('addAttachmentModal-submit');
    const maxSize = parsePhpSize('<?php echo ini_get("upload_max_filesize"); ?>');
    
    // Récupérer les extensions autorisées
    fetch('<?php echo BASE_URL; ?>settings/getAllowedExtensions')
        .then(response => response.json())
        .then(data => {
            const allowedExtensions = data.extensions || [];
            
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                
                // Réinitialiser les messages d'erreur
                fileError.textContent = '';
                fileInput.classList.remove('is-invalid');
                submitButton.disabled = false;
                
                // Vérifier la taille du fichier
                if (file.size > maxSize) {
                    fileError.textContent = `Le fichier est trop volumineux (${formatFileSize(file.size)}). Taille maximale autorisée : ${formatFileSize(maxSize)}.`;
                    fileError.style.display = 'block';
                    fileInput.classList.add('is-invalid');
                    submitButton.disabled = true;
                    return;
                }
                
                // Vérifier l'extension du fichier
                const fileName = file.name;
                const fileExtension = fileName.split('.').pop().toLowerCase();
                
                if (!allowedExtensions.includes(fileExtension)) {
                    fileError.textContent = 'Ce format n\'est pas accepté, rapprochez-vous de l\'administrateur du site, ou utilisez un format compressé.';
                    fileError.style.display = 'block';
                    fileInput.classList.add('is-invalid');
                    submitButton.disabled = true;
                    return;
                }
                
                // Fichier valide
                fileError.style.display = 'none';
                fileInput.classList.remove('is-invalid');
                submitButton.disabled = false;
            });
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des extensions autorisées:', error);
            // En cas d'erreur, on désactive la validation côté client
        });

        // Initialiser les champs au chargement de la page
        const currentSiteId = '<?php echo $intervention['site_id'] ?? ''; ?>';
        const currentSiteName = '<?php echo h($intervention['site_name'] ?? ''); ?>';
        const currentRoomId = '<?php echo $intervention['room_id'] ?? ''; ?>';
        const clientId = clientSelect.value;
        const siteId = siteSelect.value;
        const typeId = typeSelect.value;

        if (clientId) {
            loadSites(clientId, 'site_id', currentSiteId, currentSiteName, function() {
                updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id', '<?php echo $intervention['contract_id'] ?? ''; ?>');
                if (siteId) {
                    loadRooms(siteId, 'room_id', currentRoomId);
                }
            });
        }
        if (typeId) {
            updateTypeRequiresTravel('type_id', 'type_requires_travel', 'type_requires_travel');
        }
        // Initialiser la pré-sélection du contrat si une salle est déjà sélectionnée au chargement
        if (roomSelect.value) {
            const roomId = roomSelect.value;
            fetch(`${BASE_URL}interventions/getContractByRoom/${roomId}`)
                .then(response => response.json())
                .then(contract => {
                    if (contract && contract.id) {
                        setTimeout(() => {
                            const option = contractSelect.querySelector(`option[value="${contract.id}"]`);
                            if (option) {
                                option.selected = true;
                            }
                        }, 100);
                    }
                })
                .catch(error => console.error('Erreur lors de la récupération du contrat de la salle:', error));
        }
    });

    // --- Garder les fonctions spécifiques à la page ---

// Fonction pour éditer le nom d'une pièce jointe
function editAttachmentName(attachmentId, currentName) {
    // Récupérer les informations de la pièce jointe via AJAX
    fetch(`<?php echo BASE_URL; ?>interventions/getAttachmentInfo/${attachmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remplir la modale
                document.getElementById('editAttachmentName').value = data.attachment.nom_fichier;
                document.getElementById('editOriginalName').textContent = data.attachment.nom_personnalise || data.attachment.nom_fichier;
                
                // Stocker l'ID de la pièce jointe pour la sauvegarde
                document.getElementById('editAttachmentNameForm').setAttribute('data-attachment-id', attachmentId);
                
                // Ouvrir la modale
                const modal = new bootstrap.Modal(document.getElementById('editAttachmentNameModal'));
                modal.show();
            } else {
                alert('Erreur lors du chargement des informations du fichier : ' + (data.error || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des informations du fichier');
        });
}

// Gérer la soumission du formulaire d'édition
document.getElementById('editAttachmentNameForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const attachmentId = this.getAttribute('data-attachment-id');
    const newName = document.getElementById('editAttachmentName').value.trim();
    
    if (!newName) {
        alert('Le nom du fichier ne peut pas être vide');
        return;
    }
    
    // Désactiver le bouton de soumission
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Sauvegarde...';
    
    // Envoyer la requête
    fetch(`<?php echo BASE_URL; ?>interventions/updateAttachmentName/${attachmentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            nom_fichier: newName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Fermer la modale
            const modal = bootstrap.Modal.getInstance(document.getElementById('editAttachmentNameModal'));
            modal.hide();
            
            // Recharger la page pour afficher les changements
            window.location.reload();
        } else {
            alert('Erreur lors de la sauvegarde : ' + (data.error || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la sauvegarde du nom');
    })
    .finally(() => {
        // Réactiver le bouton
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});
</script>

<style>
/* Styles pour la carte des informations de contact */
.contact-info-card {
    border-width: 2px !important;
    border-style: solid !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
}

.contact-info-header {
    border-bottom: 2px solid !important;
}

/* Mode clair */
[data-bs-theme="light"] .contact-info-card {
    background-color: #f8f9fa !important;
    border-color: #dee2e6 !important;
}

[data-bs-theme="light"] .contact-info-header {
    background-color: #e9ecef !important;
    border-bottom-color: #dee2e6 !important;
    color: #495057 !important;
}

/* Mode sombre */
[data-bs-theme="dark"] .contact-info-card {
    background-color: var(--bs-body-bg) !important;
    border-color: var(--bs-border-color) !important;
}

[data-bs-theme="dark"] .contact-info-header {
    background-color: var(--bs-secondary-bg) !important;
    border-bottom-color: var(--bs-border-color) !important;
    color: var(--bs-body-color) !important;
}
/* Styles pour l'affichage des noms de fichiers */
.attachment-name {
    display: flex;
    flex-direction: column;
}

.attachment-name .display-name {
    font-weight: 500;
    color: var(--bs-body-color);
}

.attachment-name .original-name {
    font-size: 0.75em;
    margin-top: 2px;
    opacity: 0.7;
    font-style: italic;
}
</style>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 