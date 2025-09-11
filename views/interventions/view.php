<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';
/**
 * Vue de la liste des clients
 * Affiche la liste de tous les clients avec leurs statistiques
 */

// Vérification de l'accès - seuls les utilisateurs connectés peuvent voir les interventions
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID de l'intervention depuis l'URL
$interventionId = isset($intervention['id']) ? $intervention['id'] : '';

setPageVariables(
    'Intervention',
    'interventions' . ($interventionId ? '_view_' . $interventionId : '')
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
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Détails de l'intervention</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo BASE_URL; ?>interventions" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>

        <?php 
        $user = $_SESSION['user'];
        $isAdmin = isAdmin();
        ?>

        <a href="<?php echo BASE_URL; ?>interventions/generateBon/<?php echo $intervention['id']; ?>" class="btn btn-info me-2">
            <i class="bi bi-file-pdf me-1"></i> Générer le bon d'intervention
        </a>

        <?php if (canModifyInterventions()): ?>
            <a href="<?php echo BASE_URL; ?>interventions/edit/<?php echo $intervention['id']; ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil me-1"></i> Modifier
            </a>

            <?php if ($intervention['status_id'] != 6): ?>
                <a href="<?php echo BASE_URL; ?>interventions/assignToMe/<?php echo $intervention['id']; ?>" class="btn btn-success me-2">
                    <i class="bi bi-person-plus me-1"></i> S'attribuer
                </a>

                <?php
                $canClose = true;
                $closeReason = [];
                
                // Vérifier si un technicien est attribué
                if (empty($intervention['technician_id'])) {
                    $canClose = false;
                    $closeReason[] = "Aucun technicien n'est attribué";
                }
                
                // Vérifier si un contrat est sélectionné
                if (empty($intervention['contract_id'])) {
                    $canClose = false;
                    $closeReason[] = "Aucun contrat n'est sélectionné";
                }
                
                // Vérifier si la durée est supérieure à 0
                if (empty($intervention['duration']) || $intervention['duration'] <= 0) {
                    $canClose = false;
                    $closeReason[] = "La durée doit être supérieure à 0";
                }
                
                if ($canClose): ?>
                    <a href="<?php echo BASE_URL; ?>interventions/close/<?php echo $intervention['id']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir fermer cette intervention ?');">
                        <i class="bi bi-x-lg-circle me-1"></i> Fermer l'intervention
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-danger" disabled title="<?php echo implode(', ', $closeReason); ?>">
                        <i class="bi bi-x-lg-circle me-1"></i> Fermer l'intervention
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <button type="button" class="btn btn-secondary me-2" disabled>
                    <i class="bi bi-check-circle me-1"></i> Intervention fermée
                </button>
                
                <?php if ($isAdmin && $intervention['status_id'] == 6): ?>
                    <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#forceTicketsModal">
                        <i class="bi bi-ticket-perforated me-1"></i> Forcer tickets utilisés
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($isAdmin && $intervention['status_id'] == 7): ?>
                <!-- DEBUG: Bouton de suppression affiché pour admin et intervention annulée -->
                <button type="button" class="btn btn-danger me-2" onclick="deleteIntervention(<?php echo $intervention['id']; ?>, '<?php echo htmlspecialchars($intervention['reference'] ?? ''); ?>')">
                    <i class="bi bi-trash me-1"></i> Supprimer l'intervention
                </button>
            <?php else: ?>
                <!-- DEBUG: Bouton non affiché - isAdmin: <?php echo $isAdmin ? 'true' : 'false'; ?>, status_id: <?php echo $intervention['status_id']; ?> -->
            <?php endif; ?>
        <?php else: ?>
            <button type="button" class="btn btn-warning me-2" disabled title="Vous n'avez pas les droits nécessaires">
                <i class="bi bi-pencil me-1"></i> Modifier
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

    <?php if ($intervention): ?>
        <!-- Détails de l'intervention -->
        <div class="card">
            <div class="card-header py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <span class="fw-bold me-3"><?= h($intervention['reference'] ?? '') ?></span>
                        <?= h($intervention['title'] ?? '') ?>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <div class="text-muted me-2">
                            <i class="bi bi-clock me-1 me-1"></i>
                            <?= h($intervention['duration'] ?? '0') ?>h
                        </div>
                        <div class="text-muted me-2">
                            <i class="bi bi-ticket-perforated me-1 me-1"></i>
                            <?= h($intervention['tickets_used'] ?? '0') ?>
                        </div>
                        <span class="badge rounded-pill" style="background-color: <?= h($intervention['status_color'] ?? '') ?>">
                            <?= h($intervention['status_name'] ?? '') ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-3">
                    <!-- Colonne 1 : Client, Site, Salle -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Client -->
                            <div>
                                <label class="form-label fw-bold mb-0">Client</label>
                                <p class="form-control-static mb-0"><?= h($intervention['client_name'] ?? '') ?></p>
                            </div>

                            <!-- Site -->
                            <?php if (!empty($intervention['site_name'])): ?>
                            <div>
                                <label class="form-label fw-bold mb-0">Site</label>
                                <p class="form-control-static mb-0"><?= h($intervention['site_name']) ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Salle -->
                            <?php if (!empty($intervention['room_name'])): ?>
                            <div>
                                <label class="form-label fw-bold mb-0">Salle</label>
                                <p class="form-control-static mb-0"><?= h($intervention['room_name']) ?></p>
                            </div>
                            <?php endif; ?>


                        </div>
                    </div>

                    <!-- Colonne 2 : Type, Déplacement, Contrat -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Type d'intervention -->
                            <div>
                                <label class="form-label fw-bold mb-0">Type d'intervention</label>
                                <p class="form-control-static mb-0"><?= h($intervention['type_name'] ?? '') ?></p>
                            </div>

                            <!-- Déplacement -->
                            <div>
                                <label class="form-label fw-bold mb-0">Déplacement</label>
                                <p class="form-control-static mb-0">
                                    <?= isset($intervention['type_requires_travel']) && (int)$intervention['type_requires_travel'] === 1 ? 'Oui' : 'Non' ?>
                                </p>
                            </div>

                            <!-- Contrat -->
                            <?php if (!empty($intervention['contract_name']) && !empty($intervention['contract_type_id'])): ?>
                            <div>
                                <label class="form-label fw-bold mb-0">Contrat</label>
                                <p class="form-control-static mb-0">
                                    <a href="#" 
                                       class="text-decoration-none contract-info-link" 
                                       data-contract-id="<?= $intervention['contract_id'] ?>"
                                       title="Voir les détails du contrat">
                                        <i class="bi bi-info-circle me-1 me-1"></i>
                                        <?= h($intervention['contract_name']) ?>
                                    </a>
                                </p>
                            </div>
                            <?php elseif (!empty($intervention['contract_name'])): ?>
                            <div>
                                <label class="form-label fw-bold mb-0">Contrat</label>
                                <p class="form-control-static mb-0"><?= h($intervention['contract_name']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Colonne 3 : Priorité, Date de création, Technicien -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Priorité -->
                            <div>
                                <label class="form-label fw-bold mb-0">Priorité</label>
                                <p class="form-control-static mb-0">
                                    <span class="badge" style="background-color: <?= h($intervention['priority_color'] ?? '') ?>">
                                        <?= h($intervention['priority_name'] ?? '') ?>
                                    </span>
                                </p>
                            </div>

                            <!-- Date de création -->
                            <div>
                                <label class="form-label fw-bold mb-0">Date de création</label>
                                <p class="form-control-static mb-0"><?= formatDateFrench($intervention['created_at']) ?></p>
                            </div>

                            <!-- Technicien -->
                            <div>
                                <label class="form-label fw-bold mb-0">Technicien</label>
                                <p class="form-control-static mb-0"><?= h($intervention['technician_name'] ?? 'Non attribué') ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Colonne 4 : Date planifiée, Heure planifiée -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Date planifiée -->
                            <div>
                                <label class="form-label fw-bold mb-0">Date planifiée</label>
                                <p class="form-control-static mb-0">
                                    <?= !empty($intervention['date_planif']) ? formatDateFrench($intervention['date_planif']) : 'Non définie' ?>
                                </p>
                            </div>

                            <!-- Heure planifiée -->
                            <div>
                                <label class="form-label fw-bold mb-0">Heure planifiée</label>
                                <p class="form-control-static mb-0">
                                    <?= !empty($intervention['heure_planif']) ? h($intervention['heure_planif']) : 'Non définie' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($intervention['description'])): ?>
                    <div class="col-12 mt-3">
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">Description</h6>
                            </div>
                            <div class="card-body py-2">
                                <p class="mb-0"><?= nl2br(h($intervention['description'])) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Informations de contact et demande -->
                <?php if (!empty($intervention['demande_par']) || !empty($intervention['ref_client']) || !empty($intervention['contact_client'])): ?>
                    <div class="col-12 mt-3">
                        <div class="card contact-info-card">
                            <div class="card-header py-2 contact-info-header">
                                <h6 class="card-title mb-0 fw-bold">
                                    <i class="bi bi-person-lines-fill me-2"></i>Informations de contact et demande
                                </h6>
                            </div>
                            <div class="card-body py-3">
                                <div class="row g-3">
                                    <?php if (!empty($intervention['demande_par'])): ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold mb-0">Demande par</label>
                                        <p class="mb-0"><?= h($intervention['demande_par']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($intervention['ref_client'])): ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold mb-0">Référence client</label>
                                        <p class="mb-0"><?= h($intervention['ref_client']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($intervention['contact_client'])): ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold mb-0">Contact client</label>
                                        <p class="mb-0">
                                            <i class="bi bi-envelope me-2"></i>
                                            <a href="mailto:<?= h($intervention['contact_client']) ?>"><?= h($intervention['contact_client']) ?></a>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section Commentaires et Pièces jointes -->
        <div class="row mt-4">
            <!-- Section Commentaires -->
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Commentaires</h5>
                        <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCommentModal">
                                <i class="bi bi-plus me-1"></i> Ajouter un commentaire
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment mb-3 p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <strong><?= h($comment['created_by_name'] ?? 'Utilisateur inconnu') ?></strong>
                                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></small>
                                        </div>
                                        <div>
                                            <?php if ($comment['is_solution']): ?>
                                                <span class="badge bg-success">Solution</span>
                                            <?php endif; ?>
                                            <?php if ($comment['is_observation']): ?>
                                                <span class="badge bg-warning">Observation</span>
                                            <?php endif; ?>
                                            <?php if ($comment['visible_by_client']): ?>
                                                <span class="badge bg-info">Visible par le client</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Interne</span>
                                            <?php endif; ?>
                                            <?php if (canModifyInterventions()): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-warning btn-action" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editCommentModal<?= $comment['id'] ?>"
                                                        title="Modifier">
                                                        <i class="bi bi-pencil me-1"></i>
                                                </button>
                                                <a href="<?= BASE_URL ?>interventions/deleteComment/<?= $comment['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger btn-action" 
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')"
                                                   title="Supprimer">
                                                    <i class="bi bi-trash me-1"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="mb-0"><?= nl2br(h($comment['comment'])) ?></p>
                                </div>

                                <!-- Modal Édition de commentaire -->
                                <?php if (canModifyInterventions()): ?>
                                <div class="modal fade" id="editCommentModal<?= $comment['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="<?= BASE_URL ?>interventions/editComment/<?= $comment['id'] ?>" method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Modifier le commentaire</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="comment<?= $comment['id'] ?>" class="form-label">Commentaire</label>
                                                        <textarea class="form-control" id="comment<?= $comment['id'] ?>" name="comment" rows="4" required><?= h($comment['comment']) ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="visible_by_client<?= $comment['id'] ?>" name="visible_by_client" <?= $comment['visible_by_client'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="visible_by_client<?= $comment['id'] ?>">
                                                                Visible par le client
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="is_solution<?= $comment['id'] ?>" name="is_solution" <?= $comment['is_solution'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="is_solution<?= $comment['id'] ?>">
                                                                Marquer comme solution
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="is_observation<?= $comment['id'] ?>" name="is_observation" <?= $comment['is_observation'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="is_observation<?= $comment['id'] ?>">
                                                                Marquer comme observation
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">Aucun commentaire pour le moment.</p>
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
                                // Utiliser nom_fichier pour la détection d'extension (nom physique)
                                $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
                                
                                // Si pas d'extension dans le nom, utiliser le type_fichier
                                if (empty($extension) && !empty($attachment['type_fichier'])) {
                                    $extension = strtolower($attachment['type_fichier']);
                                }
                                
                                // Debug: afficher les valeurs pour les bons d'intervention
                                if ($attachment['type_liaison'] === 'bi') {
                                    // echo "<!-- DEBUG BI: nom_fichier=" . $attachment['nom_fichier'] . ", extension=$extension, type_fichier=" . $attachment['type_fichier'] . " -->";
                                }
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
                                                    data-bs-target="#previewModal<?= $attachment['id'] ?>_<?= $attachment['type_liaison'] ?>"
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
                                                    <i class="bi bi-trash me-1"></i>
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
                                                <div class="display-name"><?php echo h($attachment['nom_personnalise'] ?? $attachment['nom_fichier']); ?></div>
                                                <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                    <div class="original-name text-muted small"><?php echo h($attachment['nom_fichier']); ?></div>
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
                                <div class="modal fade" id="previewModal<?= $attachment['id'] ?>_<?= $attachment['type_liaison'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <div class="attachment-name">
                                                        <div class="display-name"><?= h($attachment['nom_personnalise'] ?? $attachment['nom_fichier']) ?></div>
                                                        <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                            <div class="original-name text-muted small"><?= h($attachment['nom_fichier']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="preview-container">
                                                    <?php 
                                                    // Utiliser la même logique de détection d'extension que plus haut
                                                    $previewExtension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
                                                    if (empty($previewExtension) && !empty($attachment['type_fichier'])) {
                                                        $previewExtension = strtolower($attachment['type_fichier']);
                                                    }
                                                    if ($previewExtension === 'pdf'): 
                                                    ?>
                                                        <iframe src="<?= BASE_URL; ?>interventions/preview/<?= $attachment['id'] ?>" 
                                                                width="100%" 
                                                                height="600px" 
                                                                frameborder="0">
                                                        </iframe>
                                                    <?php elseif (in_array($previewExtension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'])): ?>
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
                                            <?php echo isset($entry['description']) && $entry['description'] !== null ? nl2br(h($entry['description'])) : 'Aucune description disponible.'; ?>
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
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_observation" name="is_observation">
                            <label class="form-check-label" for="is_observation">
                                Marquer comme observation
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

<!-- Modal Détails du contrat -->
<div class="modal fade" id="contractDetailsModal" tabindex="-1" aria-labelledby="contractDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contractDetailsModalLabel">
                    <i class="bi bi-file-earmark-text me-2 me-1"></i> Détails du contrat
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="contractDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2">Chargement des détails du contrat...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajout de pièces jointes avec Drag & Drop -->
<div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="<?php echo BASE_URL; ?>interventions/addMultipleAttachments/<?php echo $intervention['id']; ?>" method="post" enctype="multipart/form-data" id="dragDropForm">
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
    gap: 10px;
}

.file-item.valid {
    background-color: var(--bs-success-bg-subtle);
    border-color: var(--bs-success-border-subtle);
}

.file-item.invalid {
    background-color: var(--bs-danger-bg-subtle);
    border-color: var(--bs-danger-border-subtle);
}

.file-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}

.file-name {
    font-weight: 500;
    font-size: 0.9em;
    color: var(--bs-body-color);
}

.file-size {
    color: var(--bs-secondary-color);
    font-size: 0.8em;
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

/* Styles pour le champ de nom personnalisé intégré */
.custom-name-field {
    flex: 0 0 200px;
}

.custom-name-field input {
    width: 100%;
    padding: 4px 8px;
    border: 1px solid var(--bs-border-color);
    border-radius: 3px;
    font-size: 0.8em;
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.custom-name-field input:focus {
    outline: none;
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
}

[data-bs-theme="dark"] .custom-name-field input {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
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

<script>
function generateReport(interventionId) {
    if (confirm('Voulez-vous générer le bon d\'intervention ?')) {
        // Ouvrir le PDF dans un nouvel onglet
        window.open('<?php echo BASE_URL; ?>interventions/generateReport/' + interventionId, '_blank');
        
        // Actualiser la page d'origine après un court délai
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    }
}

// Fonction pour charger les détails du contrat
function loadContractDetails(contractId) {
    const contentDiv = document.getElementById('contractDetailsContent');
    
    // Afficher le spinner de chargement
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des détails du contrat...</p>
        </div>
    `;
    
    // Faire la requête AJAX
    fetch('<?php echo BASE_URL; ?>interventions/getContractInfo/' + contractId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                contentDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2 me-1"></i>
                        ${data.error}
                    </div>
                `;
            } else {
                // Formater les dates
                const startDate = data.start_date ? new Date(data.start_date).toLocaleDateString('fr-FR') : 'Non définie';
                const endDate = data.end_date ? new Date(data.end_date).toLocaleDateString('fr-FR') : 'Non définie';
                
                // Déterminer la couleur du badge pour les tickets restants
                const ticketsColor = data.tickets_remaining > 3 ? 'success' : 
                                   data.tickets_remaining > 0 ? 'warning' : 'danger';
                
                contentDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Informations du contrat</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th class="text-muted">Type de contrat:</th>
                                    <td>${data.type_name || 'Non défini'}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Date de début:</th>
                                    <td>${startDate}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Date de fin:</th>
                                    <td>${endDate}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Tickets restants:</th>
                                    <td>
                                        <span class="badge bg-${ticketsColor}">
                                            ${data.tickets_remaining || 0}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    ${data.comment ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="fw-bold mb-2">Commentaire</h6>
                                <div class="alert alert-info">
                                    <i class="bi bi-chat-dots me-2 me-1"></i>
                                    ${data.comment}
                                </div>
                            </div>
                        </div>
                    ` : ''}
                `;
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des détails du contrat:', error);
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2 me-1"></i>
                    Erreur lors du chargement des détails du contrat.
                </div>
            `;
        });
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
        
        // Click to select files (seulement sur la zone de drop, pas sur les éléments enfants)
        this.dropZone.addEventListener('click', (e) => {
            // Ne déclencher que si on clique directement sur la zone de drop ou le message
            if (e.target === this.dropZone || e.target.classList.contains('drop-message') || e.target.closest('.drop-message')) {
                this.fileInput.click();
            }
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
            
            // Si le fichier est valide, ajouter le champ de nom personnalisé
            const customNameField = fileData.isValid ? `
                <div class="custom-name-field">
                    <input type="text" 
                           placeholder="Nom personnalisé (optionnel)" 
                           value="${fileData.customName || ''}"
                           maxlength="255"
                           data-file-index="${index}">
                </div>
            ` : '';
            
            fileItem.innerHTML = `
                <div class="file-info">
                    <span class="file-name">${fileData.file.name}</span>
                    <span class="file-size">${this.formatFileSize(fileData.file.size)}</span>
                    ${fileData.error ? `<span class="error-message">${fileData.error}</span>` : ''}
                </div>
                ${customNameField}
                <button type="button" class="remove-file" onclick="uploader.removeFile(${index})" onmousedown="event.stopPropagation()">×</button>
            `;
            
            // Empêcher la propagation des événements sur tout l'élément de fichier
            fileItem.addEventListener('click', (e) => {
                e.stopPropagation();
            });
            
            // Ajouter un event listener pour sauvegarder le nom personnalisé
            if (fileData.isValid) {
                const input = fileItem.querySelector('input[data-file-index]');
                input.addEventListener('input', (e) => {
                    fileData.customName = e.target.value;
                });
                // Empêcher la propagation du clic pour éviter l'ouverture du sélecteur
                input.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }
            
            this.fileList.appendChild(fileItem);
        });
        
    }
    
    removeFile(index) {
        this.files.splice(index, 1);
        this.displayFiles();
        this.updateStats();
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
        
        // Ajouter les fichiers et leurs noms personnalisés
        validFiles.forEach((fileData, index) => {
            formData.append(`attachments[${index}]`, fileData.file);
            if (fileData.customName && fileData.customName.trim()) {
                formData.append(`custom_names[${index}]`, fileData.customName.trim());
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
    
    // Gérer les clics sur les liens de contrat
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('contract-info-link') || e.target.closest('.contract-info-link')) {
            e.preventDefault();
            const link = e.target.classList.contains('contract-info-link') ? e.target : e.target.closest('.contract-info-link');
            const contractId = link.getAttribute('data-contract-id');
            
            if (contractId) {
                // Ouvrir la modal
                const modal = new bootstrap.Modal(document.getElementById('contractDetailsModal'));
                modal.show();
                
                // Charger les détails du contrat
                loadContractDetails(contractId);
            }
        }
    });
});

// Fonction pour éditer le nom d'une pièce jointe
function editAttachmentName(attachmentId, currentName) {
    // Récupérer les informations de la pièce jointe via AJAX
    fetch(`<?php echo BASE_URL; ?>interventions/getAttachmentInfo/${attachmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remplir la modale
                document.getElementById('editAttachmentName').value = data.attachment.nom_personnalise || data.attachment.nom_fichier;
                document.getElementById('editOriginalName').textContent = data.attachment.nom_fichier;
                
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

<!-- Modale pour forcer les tickets utilisés -->
<?php if ($isAdmin && $intervention['status_id'] == 6): ?>
<div class="modal fade" id="forceTicketsModal" tabindex="-1" aria-labelledby="forceTicketsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="forceTicketsModalLabel">
                    <i class="bi bi-ticket-perforated me-2 me-1"></i>Forcer les tickets utilisés
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>interventions/forceTickets/<?php echo $intervention['id']; ?>" method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2 me-1"></i>
                        <strong>Attention !</strong> Cette action va modifier le nombre de tickets utilisés pour cette intervention fermée.
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_tickets" class="form-label">Tickets utilisés actuels</label>
                        <input type="text" class="form-control" id="current_tickets" value="<?php echo $intervention['tickets_used'] ?? 0; ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_tickets" class="form-label">Nouveau nombre de tickets utilisés <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="new_tickets" name="tickets_used" 
                               value="<?php echo $intervention['tickets_used'] ?? 0; ?>" min="0" required>
                        <div class="form-text">Ce nombre sera utilisé pour recalculer les tickets restants du contrat.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Raison de la modification <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" 
                                  placeholder="Expliquez pourquoi vous modifiez le nombre de tickets utilisés..." required></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2 me-1"></i>
                        <strong>Impact sur le contrat :</strong>
                        <ul class="mb-0 mt-2">
                            <li>Tickets restants actuels : <span id="current_remaining">Calcul en cours...</span></li>
                            <li>Tickets restants après modification : <span id="new_remaining">Calcul en cours...</span></li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-ticket-perforated me-1 me-1"></i>Forcer les tickets
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Calculer l'impact sur les tickets restants
document.addEventListener('DOMContentLoaded', function() {
    const newTicketsInput = document.getElementById('new_tickets');
    const currentTickets = <?php echo $intervention['tickets_used'] ?? 0; ?>;
    const contractTickets = <?php echo $intervention['contract_tickets_number'] ?? 0; ?>;
    const contractRemaining = <?php echo $intervention['contract_tickets_remaining'] ?? 0; ?>;
    
    function updateImpact() {
        const newTickets = parseInt(newTicketsInput.value) || 0;
        const difference = newTickets - currentTickets;
        const currentRemaining = contractRemaining;
        const newRemaining = currentRemaining - difference;
        
        document.getElementById('current_remaining').textContent = currentRemaining;
        document.getElementById('new_remaining').textContent = newRemaining;
        
        // Changer la couleur selon l'impact
        const newRemainingElement = document.getElementById('new_remaining');
        if (newRemaining < 0) {
            newRemainingElement.className = 'text-danger fw-bold';
        } else if (newRemaining < 5) {
            newRemainingElement.className = 'text-warning fw-bold';
        } else {
            newRemainingElement.className = 'text-success fw-bold';
        }
    }
    
    if (newTicketsInput) {
        newTicketsInput.addEventListener('input', updateImpact);
        updateImpact(); // Calcul initial
    }
    
    // Debug: Vérifier que le formulaire envoie bien les données
    const forceTicketsForm = document.querySelector('#forceTicketsModal form');
    if (forceTicketsForm) {
        forceTicketsForm.addEventListener('submit', function(e) {
            console.log('DEBUG: Formulaire soumis');
            console.log('DEBUG: tickets_used:', document.getElementById('new_tickets').value);
            console.log('DEBUG: reason:', document.getElementById('reason').value);
        });
    }
});
</script>
<?php endif; ?>

<script>
// Fonction simple pour supprimer une intervention
function deleteIntervention(interventionId, reference) {
    console.log('DEBUG: deleteIntervention appelée avec ID:', interventionId, 'et référence:', reference);
    
    if (confirm('Êtes-vous sûr de vouloir supprimer l\'intervention ' + reference + ' ?\n\nCette action est irréversible et supprimera définitivement l\'intervention et toutes ses données associées (commentaires, pièces jointes, historique).\n\nSi l\'intervention avait des tickets utilisés, ils seront re-crédités au contrat.')) {
        console.log('DEBUG: Confirmation acceptée, redirection vers:', '<?php echo BASE_URL; ?>interventions/delete/' + interventionId);
        window.location.href = '<?php echo BASE_URL; ?>interventions/delete/' + interventionId;
    } else {
        console.log('DEBUG: Confirmation annulée');
    }
}
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
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 






