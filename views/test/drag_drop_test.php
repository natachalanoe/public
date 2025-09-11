<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Drag & Drop Multi-fichiers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .drop-zone {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .drop-zone.dragover {
            border-color: #007bff;
            background-color: #e3f2fd;
        }

        .drop-zone.dragover .drop-message {
            color: #007bff;
        }

        .drop-message {
            font-size: 1.2em;
            color: #6c757d;
            margin-bottom: 20px;
        }

        .drop-message i {
            font-size: 3em;
            margin-bottom: 15px;
            display: block;
        }

        .file-list {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            background-color: white;
        }

        .file-item.valid {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .file-item.invalid {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .file-name {
            flex: 1;
            font-weight: 500;
        }

        .file-size {
            color: #6c757d;
            font-size: 0.9em;
            margin: 0 10px;
        }

        .error-message {
            color: #721c24;
            font-size: 0.875em;
            margin-left: 10px;
        }

        .remove-file {
            background: none;
            border: none;
            color: #dc3545;
            font-size: 1.2em;
            cursor: pointer;
            padding: 0 5px;
        }

        .remove-file:hover {
            color: #c82333;
        }

        .upload-actions {
            margin-top: 20px;
        }

        .stats {
            margin-top: 15px;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
        }

        .progress-bar {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background-color: #007bff;
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-cloud-upload me-2 me-1"></i>
                            Test Drag & Drop Multi-fichiers
                        </h4>
                    </div>
                    <div class="card-body">
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
                            
                            <div class="upload-actions" id="uploadActions" style="display: none;">
                                <button id="uploadValid" class="btn btn-primary me-2">
                                    <i class="bi bi-upload me-1 me-1"></i>
                                    Uploader les fichiers valides
                                </button>
                                <button id="clearAll" class="btn btn-secondary">
                                    <i class="bi bi-trash me-1 me-1"></i>
                                    Tout effacer
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Formats acceptés :</h6>
                            <p class="text-muted"><?= FileUploadValidator::getExtensionsForDisplay($GLOBALS['db']) ?></p>
                            
                            <h6>Formats interdits :</h6>
                            <p class="text-danger"><?= implode(', ', FileUploadValidator::getBlacklistedExtensions()) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class DragDropUploader {
            constructor() {
                this.dropZone = document.getElementById('dropZone');
                this.fileInput = document.getElementById('fileInput');
                this.fileList = document.getElementById('fileList');
                this.stats = document.getElementById('stats');
                this.uploadActions = document.getElementById('uploadActions');
                this.validCount = document.getElementById('validCount');
                this.invalidCount = document.getElementById('invalidCount');
                this.progressFill = document.getElementById('progressFill');
                this.uploadValidBtn = document.getElementById('uploadValid');
                this.clearAllBtn = document.getElementById('clearAll');
                
                this.files = [];
                this.allowedExtensions = [];
                
                this.init();
            }
            
            async init() {
                await this.loadAllowedExtensions();
                this.setupEventListeners();
            }
            
            async loadAllowedExtensions() {
                try {
                    const response = await fetch('<?= BASE_URL ?>settings/getAllowedExtensions');
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
                    
                    return {
                        file,
                        isValid,
                        extension,
                        error: isValid ? null : 'Ce format n\'est pas accepté, rapprochez-vous de l\'administrateur du site, ou utilisez un format compressé.'
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
                        <button class="remove-file" onclick="uploader.removeFile(${index})">×</button>
                    `;
                    
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
                    this.uploadActions.style.display = 'block';
                    
                    const progress = (validFiles.length / this.files.length) * 100;
                    this.progressFill.style.width = `${progress}%`;
                } else {
                    this.stats.style.display = 'none';
                    this.uploadActions.style.display = 'none';
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
                
                // Simulation d'upload (pour le test)
                this.uploadValidBtn.disabled = true;
                this.uploadValidBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1 me-1"></i>Upload en cours...';
                
                for (let i = 0; i < validFiles.length; i++) {
                    const fileData = validFiles[i];
                    
                    // Simuler un délai d'upload
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    
                    console.log(`Upload simulé: ${fileData.file.name}`);
                    
                    // Mettre à jour la progression
                    const progress = ((i + 1) / validFiles.length) * 100;
                    this.progressFill.style.width = `${progress}%`;
                }
                
                alert(`${validFiles.length} fichier(s) uploadé(s) avec succès !`);
                
                this.uploadValidBtn.disabled = false;
                this.uploadValidBtn.innerHTML = '<i class="bi bi-upload me-1 me-1"></i>Uploader les fichiers valides';
            }
        }
        
        // Initialiser l'uploader
        let uploader;
        document.addEventListener('DOMContentLoaded', () => {
            uploader = new DragDropUploader();
        });
    </script>
</body>
</html> 