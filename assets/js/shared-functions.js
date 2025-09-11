/**
 * Fonctions JavaScript partagées pour l'application VideoSonic
 * Ce fichier contient les fonctions communes utilisées dans plusieurs vues
 */

// Définition de BASE_URL (sera redéfinie dans chaque page)
let BASE_URL = '';

/**
 * Initialise BASE_URL pour JavaScript
 * @param {string} url - L'URL de base de l'application
 */
function initBaseUrl(url) {
    BASE_URL = url;
}

/**
 * Génère un rapport PDF et l'ouvre dans un nouvel onglet
 * @param {number} id - L'ID de l'intervention
 * @param {string} type - Le type de rapport (optionnel, défaut: 'interventions')
 */
function generateReport(id, type = 'interventions') {
    // Ouvrir le PDF dans un nouvel onglet
    window.open(BASE_URL + type + '/generateReport/' + id, '_blank');
    
    // Actualiser la page d'origine après un court délai
    setTimeout(function() {
        window.location.reload();
    }, 1000);
}

/**
 * Convertit la taille maximale de PHP en octets
 * @param {string} size - La taille au format PHP (ex: "8M", "2G")
 * @returns {number} La taille en octets
 */
function parsePhpSize(size) {
    const units = {
        'K': 1024,
        'M': 1024 * 1024,
        'G': 1024 * 1024 * 1024
    };
    const match = size.match(/^(\d+)([KMG])$/i);
    if (match) {
        return parseInt(match[1]) * units[match[2].toUpperCase()];
    }
    return parseInt(size);
}

/**
 * Formate la taille en MB
 * @param {number} bytes - La taille en octets
 * @returns {string} La taille formatée en MB
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 MB';
    const mb = bytes / (1024 * 1024);
    return mb.toFixed(2) + ' MB';
}

/**
 * Valide un fichier uploadé selon les types et tailles autorisés
 * @param {File} file - Le fichier à valider
 * @param {Array} allowedTypes - Les types MIME autorisés
 * @param {number} maxFileSize - La taille maximale en octets
 * @param {HTMLElement} errorElement - L'élément pour afficher les erreurs
 * @param {HTMLElement} fileInput - L'input file
 * @param {HTMLElement} submitButton - Le bouton de soumission
 * @returns {boolean} true si le fichier est valide
 */
function validateFile(file, maxFileSize, errorElement, fileInput, submitButton) {
    if (!file) return true;

    // Réinitialiser les messages d'erreur
    errorElement.textContent = '';
    fileInput.classList.remove('is-invalid');
    submitButton.disabled = false;

    // Vérifier la taille du fichier
    if (file.size > maxFileSize) {
        errorElement.textContent = `Le fichier est trop volumineux (${formatFileSize(file.size)}). Taille maximale autorisée : ${formatFileSize(maxFileSize)}.`;
        fileInput.classList.add('is-invalid');
        submitButton.disabled = true;
        return false;
    }

    return true;
}

/**
 * Initialise la validation de fichiers pour un formulaire
 * @param {string} formId - L'ID du formulaire
 * @param {string} fileInputId - L'ID de l'input file
 * @param {string} errorElementId - L'ID de l'élément d'erreur
 * @param {string} submitButtonId - L'ID du bouton de soumission
 * @param {string} maxFileSize - La taille maximale PHP (optionnel)
 */
function initFileValidation(formId, fileInputId, errorElementId, submitButtonId, maxFileSize = null) {
    const attachmentForm = document.getElementById(formId);
    const fileInput = document.getElementById(fileInputId);
    const fileError = document.getElementById(errorElementId);
    const submitButton = document.getElementById(submitButtonId);

    if (!attachmentForm || !fileInput || !fileError || !submitButton) {
        console.error('Éléments manquants pour la validation de fichiers');
        return;
    }

    // Utiliser la taille maximale par défaut si non fournie
    const maxSize = maxFileSize ? parsePhpSize(maxFileSize) : parsePhpSize('8M');

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        validateFile(file, maxSize, fileError, fileInput, submitButton);
    });

    attachmentForm.addEventListener('submit', function(e) {
        const file = fileInput.files[0];
        if (file && !validateFile(file, maxSize, fileError, fileInput, submitButton)) {
            e.preventDefault();
            return false;
        }
    });
}

/**
 * Affiche une confirmation avant de supprimer un élément
 * @param {string} message - Le message de confirmation
 * @returns {boolean} true si l'utilisateur confirme
 */
function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer cet élément ?') {
    return confirm(message);
}

/**
 * Redirige vers une URL
 * @param {string} url - L'URL de destination
 */
function redirectTo(url) {
    window.location.href = BASE_URL + url;
}

/**
 * Actualise la page courante
 */
function refreshPage() {
    window.location.reload();
}

/**
 * Ouvre une URL dans un nouvel onglet
 * @param {string} url - L'URL à ouvrir
 */
function openInNewTab(url) {
    window.open(BASE_URL + url, '_blank');
}

/**
 * Initialise la validation des formulaires Bootstrap
 * Doit être appelée après le chargement du DOM
 */
function initBootstrapValidation() {
    'use strict';
    
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Gestion de l'affichage/masquage du mot de passe
 * @param {string} passwordId - L'ID du champ mot de passe
 * @param {string} toggleId - L'ID du bouton toggle
 * @param {string} iconId - L'ID de l'icône (optionnel)
 */
function initPasswordToggle(passwordId, toggleId, iconId = null) {
    const togglePassword = document.getElementById(toggleId);
    const password = document.getElementById(passwordId);
    const icon = iconId ? document.getElementById(iconId) : togglePassword.querySelector('i');

    if (!togglePassword || !password) {
        console.error('Éléments manquants pour le toggle du mot de passe');
        return;
    }

    togglePassword.addEventListener('click', function(e) {
        // Si le champ est en lecture seule, afficher un message
        if (password.readOnly) {
            // Changer temporairement l'icône pour indiquer l'action
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-info-circle');
            }
            
            // Afficher un message d'information
            const originalTitle = togglePassword.title;
            togglePassword.title = 'Mot de passe actuel (non modifiable)';
            
            // Remettre l'icône normale après 2 secondes
            setTimeout(() => {
                if (icon) {
                    icon.classList.remove('fa-info-circle');
                    icon.classList.add('fa-eye');
                }
                togglePassword.title = originalTitle;
            }, 2000);
            
            return;
        }
        
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        if (icon) {
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        }
        
        // Mettre à jour le titre
        togglePassword.title = type === 'text' ? 'Masquer le mot de passe' : 'Afficher le mot de passe';
    });
}

/**
 * Validation en temps réel du mot de passe
 * @param {string} passwordId - L'ID du champ mot de passe
 * @param {Object} rulesContainer - L'élément contenant les règles (optionnel)
 */
function initPasswordValidation(passwordId, rulesContainer = null) {
    const password = document.getElementById(passwordId);
    
    if (!password) {
        console.error('Champ mot de passe manquant');
        return;
    }

    const passwordValidationRules = {
        length: /.{8,}/,
        uppercase: /[A-Z]/,
        lowercase: /[a-z]/,
        number: /[0-9]/,
        special: /[!@#$%^&*(),.?":{}|<>]/
    };

    password.addEventListener('input', function() {
        const value = this.value;
        
        // Afficher/masquer les règles en fonction de la présence de texte
        if (rulesContainer) {
            rulesContainer.style.display = value.length > 0 ? 'block' : 'none';
        }
        
        // Vérifier chaque règle
        for (const [rule, regex] of Object.entries(passwordValidationRules)) {
            const element = document.getElementById(rule);
            if (!element) continue;
            
            const isValid = regex.test(value);
            
            if (isValid) {
                element.classList.remove('text-danger');
                element.classList.add('text-success');
                const icon = element.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-check');
                }
            } else {
                element.classList.remove('text-success');
                element.classList.add('text-danger');
                const icon = element.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-times');
                }
            }
        }
    });
}

/**
 * Charge dynamiquement les sites d'un client dans un select
 * @param {string|number} clientId - L'ID du client
 * @param {string} siteSelectId - L'ID du select des sites
 * @param {string|number|null} currentSiteId - L'ID du site à présélectionner (optionnel)
 * @param {string|null} currentSiteName - Le nom du site à afficher si non présent dans la liste (optionnel)
 * @param {function|null} callback - Callback à appeler après mise à jour (optionnel)
 */
function loadSites(clientId, siteSelectId, currentSiteId = null, currentSiteName = null, callback = null) {
    const siteSelect = document.getElementById(siteSelectId);
    if (!siteSelect) return;
    // Vider le select sauf l'option par défaut
    while (siteSelect.options.length > 1) {
        siteSelect.remove(1);
    }
    if (!clientId) return;
    fetch(BASE_URL + 'interventions/getSites/' + clientId, {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            if (data.sites && Array.isArray(data.sites)) {
                data.sites.forEach(site => {
                    const option = document.createElement('option');
                    option.value = site.id;
                    option.textContent = site.name;
                    if (currentSiteId && site.id == currentSiteId) option.selected = true;
                    siteSelect.appendChild(option);
                });
                // Si le site actuel n'est pas dans la liste et qu'il y a un nom à afficher
                if (currentSiteId && currentSiteName && !data.sites.some(site => site.id == currentSiteId)) {
                    const option = document.createElement('option');
                    option.value = currentSiteId;
                    option.textContent = currentSiteName;
                    option.selected = true;
                    siteSelect.appendChild(option);
                }
            }
            if (typeof callback === 'function') callback();
        })
        .catch(error => console.error('Erreur lors du chargement des sites:', error));
}

/**
 * Charge dynamiquement les salles d'un site dans un select
 * @param {string|number} siteId - L'ID du site
 * @param {string} roomSelectId - L'ID du select des salles
 * @param {string|number|null} currentRoomId - L'ID de la salle à présélectionner (optionnel)
 * @param {function|null} callback - Callback à appeler après mise à jour (optionnel)
 */
function loadRooms(siteId, roomSelectId, currentRoomId = null, callback = null) {
    const roomSelect = document.getElementById(roomSelectId);
    if (!roomSelect) return;
    roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
    if (!siteId) return;
    fetch(BASE_URL + 'interventions/getRooms/' + siteId, {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            if (data.rooms && Array.isArray(data.rooms)) {
                data.rooms.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.name;
                    if (currentRoomId && room.id == currentRoomId) option.selected = true;
                    roomSelect.appendChild(option);
                });
            }
            if (typeof callback === 'function') callback();
        })
        .catch(error => console.error('Erreur lors du chargement des salles:', error));
}

/**
 * Met à jour la liste des contrats en fonction du client, site et salle sélectionnés
 * @param {string} clientSelectId - L'ID du select client
 * @param {string} siteSelectId - L'ID du select site
 * @param {string} roomSelectId - L'ID du select salle
 * @param {string} contractSelectId - L'ID du select contrat
 * @param {string|number|null} currentContractId - L'ID du contrat à présélectionner (optionnel)
 * @param {function|null} callback - Callback à appeler après mise à jour (optionnel)
 */
function updateSelectedContract(clientSelectId, siteSelectId, roomSelectId, contractSelectId, currentContractId = null, callback = null) {
    const clientSelect = document.getElementById(clientSelectId);
    const siteSelect = document.getElementById(siteSelectId);
    const roomSelect = document.getElementById(roomSelectId);
    const contractSelect = document.getElementById(contractSelectId);

    if (!clientSelect || !contractSelect) {
        console.error('Éléments de sélection non trouvés');
        return;
    }

    const clientId = clientSelect.value;
    const siteId = siteSelect ? siteSelect.value : null;
    const roomId = roomSelect ? roomSelect.value : null;

    // Vider la liste des contrats
    contractSelect.innerHTML = '<option value="">Chargement...</option>';

    if (!clientId) {
        contractSelect.innerHTML = '<option value="">Sélectionnez un client</option>';
        if (typeof callback === 'function') callback();
        return;
    }

    // Construire l'URL avec les paramètres de site et salle
    let url = `${BASE_URL}interventions/getContracts/${clientId}`;
    const params = new URLSearchParams();
    if (siteId) params.append('site_id', siteId);
    if (roomId) params.append('room_id', roomId);
    if (params.toString()) {
        url += '?' + params.toString();
    }

    // Récupérer les contrats depuis le serveur
    fetch(url)
        .then(response => response.json())
        .then(contracts => {
            // Vider la liste des contrats
            contractSelect.innerHTML = '<option value="">Sélectionnez un contrat</option>';

            // Ajouter les contrats récupérés
            contracts.forEach(contract => {
                const option = document.createElement('option');
                option.value = contract.id;
                option.textContent = contract.name;
                if (currentContractId == contract.id) {
                    option.selected = true;
                }
                contractSelect.appendChild(option);
            });

            // Si une salle est sélectionnée et qu'aucun contrat n'est actuellement sélectionné, essayer de pré-sélectionner le contrat associé
            if (roomId && !currentContractId) {
                fetch(`${BASE_URL}interventions/getContractByRoom/${roomId}`)
                    .then(response => response.json())
                    .then(contract => {
                        if (contract && contract.id) {
                            // Trouver l'option correspondante et la sélectionner
                            const option = contractSelect.querySelector(`option[value="${contract.id}"]`);
                            if (option) {
                                option.selected = true;
                            }
                        }
                    })
                    .catch(error => console.error('Erreur lors de la récupération du contrat de la salle:', error));
            }
            
            if (typeof callback === 'function') callback();
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des contrats:', error);
            contractSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            if (typeof callback === 'function') callback();
        });
}

/**
 * Met à jour le champ "Déplacement requis" en fonction du type d'intervention sélectionné
 * @param {string} typeSelectId - L'ID du select type d'intervention
 * @param {string} typeRequiresTravelInputId - L'ID du champ input affichant le déplacement requis
 * @param {string} typeRequiresTravelHiddenName - Le nom du champ hidden pour le déplacement requis
 */
function updateTypeRequiresTravel(typeSelectId, typeRequiresTravelInputId, typeRequiresTravelHiddenName) {
    const typeSelect = document.getElementById(typeSelectId);
    const typeRequiresTravelInput = document.getElementById(typeRequiresTravelInputId);
    const typeRequiresTravelHidden = document.querySelector(`input[name="${typeRequiresTravelHiddenName}"]`);
    
    if (!typeSelect || !typeRequiresTravelInput || !typeRequiresTravelHidden) {
        console.error('Éléments manquants pour updateTypeRequiresTravel:', {
            typeSelect: !!typeSelect,
            typeRequiresTravelInput: !!typeRequiresTravelInput,
            typeRequiresTravelHidden: !!typeRequiresTravelHidden
        });
        return;
    }
    
    const typeId = typeSelect.value;
    if (!typeId) {
        typeRequiresTravelInput.value = 'Non';
        typeRequiresTravelHidden.value = '0';
        return;
    }

    fetch(`${BASE_URL}interventions/getTypeInfo/${typeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Erreur:', data.error);
                return;
            }
            
            const requiresTravel = data.requires_travel == 1;
            typeRequiresTravelInput.value = requiresTravel ? 'Oui' : 'Non';
            typeRequiresTravelHidden.value = requiresTravel ? '1' : '0';
        })
        .catch(error => console.error('Erreur lors de la récupération des informations du type:', error));
}

/**
 * Charge les localisations d'un client via AJAX
 * @param {string|number} clientId - L'ID du client
 * @param {string} locationsGridSelector - Le sélecteur CSS pour le conteneur des localisations
 * @param {function|null} callback - Callback à appeler après chargement (optionnel)
 */
function loadClientLocations(clientId, locationsGridSelector = '.locations-grid', callback = null) {
    const locationsGrid = document.querySelector(locationsGridSelector);
    if (!locationsGrid) {
        console.error('Conteneur de localisations non trouvé');
        return;
    }
    
    locationsGrid.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
    
    // Utiliser BASE_URL si disponible, sinon utiliser une URL relative
    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
    
    fetch(`${baseUrl}user/get_client_locations?client_id=${clientId}`)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Données reçues:', data);
            // Le contrôleur retourne {locations: {...}}, nous devons extraire les données
            const locationsData = data.locations || data;
            console.log('Données de localisations:', locationsData);
            buildLocationsInterface(locationsData, locationsGrid);
            if (typeof callback === 'function') callback();
        })
        .catch(error => {
            console.error('Erreur lors du chargement des localisations:', error);
            locationsGrid.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des localisations.</div>';
        });
}

/**
 * Construit l'interface des localisations avec accordéon
 * @param {Object} data - Les données des localisations
 * @param {HTMLElement} container - Le conteneur où afficher l'interface
 * @param {Object|null} existingLocations - Les localisations existantes à pré-sélectionner (optionnel)
 */
function buildLocationsInterface(data, container, existingLocations = null) {
    // Vider le conteneur
    container.innerHTML = '';
    
    // Créer l'option d'accès complet au client
    const clientFullAccessDiv = document.createElement('div');
    clientFullAccessDiv.className = 'form-check mb-3';
    clientFullAccessDiv.innerHTML = `
        <input class="form-check-input" type="checkbox" id="client_full_access" name="locations[client_full]" value="1">
        <label class="form-check-label" for="client_full_access">
            <strong>Accès complet au client</strong> (toutes les localisations)
        </label>
    `;
    container.appendChild(clientFullAccessDiv);
    
    // Ajouter un séparateur
    container.appendChild(document.createElement('hr'));
    
    // Si aucun site n'est disponible
    if (!data.sites || data.sites.length === 0) {
        const noSitesDiv = document.createElement('div');
        noSitesDiv.className = 'alert alert-info';
        noSitesDiv.textContent = 'Aucun site disponible pour ce client.';
        container.appendChild(noSitesDiv);
        return;
    }
    
    // Créer un accordéon pour les sites
    const accordionDiv = document.createElement('div');
    accordionDiv.className = 'accordion';
    accordionDiv.id = 'locationsAccordion';
    
    // Pour chaque site
    data.sites.forEach((site, siteIndex) => {
        const siteDiv = document.createElement('div');
        siteDiv.className = 'accordion-item';
        
        // En-tête du site
        const siteHeader = document.createElement('h2');
        siteHeader.className = 'accordion-header';
        siteHeader.id = `heading${siteIndex}`;
        
        const siteButton = document.createElement('button');
        siteButton.className = 'accordion-button collapsed';
        siteButton.type = 'button';
        siteButton.setAttribute('data-bs-toggle', 'collapse');
        siteButton.setAttribute('data-bs-target', `#collapse${siteIndex}`);
        siteButton.setAttribute('aria-expanded', 'false');
        siteButton.setAttribute('aria-controls', `collapse${siteIndex}`);
        
        // Ajouter une case à cocher pour le site
        siteButton.innerHTML = `
            <div class="form-check me-3">
                <input class="form-check-input site-checkbox" type="checkbox" id="site_${site.id}" name="locations[sites][]" value="${site.id}" data-site-id="${site.id}">
                <label class="form-check-label" for="site_${site.id}"></label>
            </div>
            ${site.name}
        `;
        
        siteHeader.appendChild(siteButton);
        siteDiv.appendChild(siteHeader);
        
        // Contenu du site (salles)
        const siteContent = document.createElement('div');
        siteContent.id = `collapse${siteIndex}`;
        siteContent.className = 'accordion-collapse collapse';
        siteContent.setAttribute('aria-labelledby', `heading${siteIndex}`);
        siteContent.setAttribute('data-bs-parent', '#locationsAccordion');
        
        const siteBody = document.createElement('div');
        siteBody.className = 'accordion-body';
        
        // Si le site a des salles
        if (site.rooms && site.rooms.length > 0) {
            const roomsList = document.createElement('div');
            roomsList.className = 'rooms-list ms-4 mt-2';
            
            site.rooms.forEach(room => {
                const roomDiv = document.createElement('div');
                roomDiv.className = 'form-check mb-2';
                roomDiv.innerHTML = `
                    <input class="form-check-input room-checkbox" type="checkbox" id="room_${room.id}" name="locations[rooms][]" value="${room.id}" data-site-id="${site.id}">
                    <label class="form-check-label" for="room_${room.id}">
                        ${room.name}
                    </label>
                `;
                roomsList.appendChild(roomDiv);
            });
            
            siteBody.appendChild(roomsList);
        } else {
            siteBody.innerHTML = '<p class="text-muted ms-4">Aucune salle disponible pour ce site.</p>';
        }
        
        siteContent.appendChild(siteBody);
        siteDiv.appendChild(siteContent);
        accordionDiv.appendChild(siteDiv);
    });
    
    container.appendChild(accordionDiv);
    
    // Ajouter les écouteurs d'événements pour la gestion des cases à cocher
    setupCheckboxListeners();
    
    // Pré-sélectionner les localisations existantes
    if (existingLocations) {
        preselectLocations(existingLocations);
    }
}

/**
 * Configure les écouteurs d'événements des cases à cocher pour les localisations
 */
function setupCheckboxListeners() {
    // Accès complet au client
    const clientFullAccess = document.getElementById('client_full_access');
    if (clientFullAccess) {
        clientFullAccess.addEventListener('change', function() {
            const allCheckboxes = document.querySelectorAll('.site-checkbox, .room-checkbox');
            allCheckboxes.forEach(checkbox => {
                checkbox.disabled = this.checked;
                if (this.checked) {
                    checkbox.checked = false;
                }
            });
        });
    }
    
    // Sites
    const siteCheckboxes = document.querySelectorAll('.site-checkbox');
    siteCheckboxes.forEach(siteCheckbox => {
        siteCheckbox.addEventListener('change', function() {
            const siteId = this.dataset.siteId;
            const roomCheckboxes = document.querySelectorAll(`.room-checkbox[data-site-id="${siteId}"]`);
            
            roomCheckboxes.forEach(roomCheckbox => {
                roomCheckbox.disabled = this.checked;
                if (this.checked) {
                    roomCheckbox.checked = false;
                }
            });
        });
    });
}

/**
 * Pré-sélectionne les localisations existantes
 * @param {Object} locations - Les localisations à pré-sélectionner
 */
function preselectLocations(locations) {
    // Accès complet au client
    if (locations.client_full) {
        const clientFullAccess = document.getElementById('client_full_access');
        if (clientFullAccess) {
            clientFullAccess.checked = true;
            
            // Désactiver toutes les autres cases
            const allCheckboxes = document.querySelectorAll('.site-checkbox, .room-checkbox');
            allCheckboxes.forEach(checkbox => {
                checkbox.disabled = true;
            });
        }
    } else {
        // Sites
        if (locations.sites && locations.sites.length > 0) {
            locations.sites.forEach(siteId => {
                const siteCheckbox = document.getElementById(`site_${siteId}`);
                if (siteCheckbox) {
                    siteCheckbox.checked = true;
                    
                    // Désactiver les salles de ce site
                    const roomCheckboxes = document.querySelectorAll(`.room-checkbox[data-site-id="${siteId}"]`);
                    roomCheckboxes.forEach(roomCheckbox => {
                        roomCheckbox.disabled = true;
                    });
                }
            });
        }
        
        // Salles
        if (locations.rooms && locations.rooms.length > 0) {
            locations.rooms.forEach(roomId => {
                const roomCheckbox = document.getElementById(`room_${roomId}`);
                if (roomCheckbox) {
                    roomCheckbox.checked = true;
                }
            });
        }
    }
}

/**
 * Gère l'affichage/masquage des sections en fonction du type d'utilisateur
 * @param {string} typeSelectId - L'ID du select type d'utilisateur
 * @param {Object} sections - Objet contenant les IDs des sections à gérer
 */
function toggleUserSections(typeSelectId, sections = {}) {
    const typeSelect = document.getElementById(typeSelectId);
    if (!typeSelect) return;
    
    const userType = typeSelect.value;
    
    // Déterminer le groupe en fonction du type
    let userGroup = '';
    if (userType === 'technicien' || userType === 'adv') {
        userGroup = 'Staff';
    } else if (userType === 'client') {
        userGroup = 'Externe';
    }
    
    // Gérer la section coefficient (pour les membres du staff)
    if (sections.coefficientSection) {
        const coefficientSection = document.getElementById(sections.coefficientSection);
        if (coefficientSection) {
            coefficientSection.style.display = (userGroup === 'Staff') ? 'block' : 'none';
        }
    }
    
    // Gérer la case administrateur (cachée pour les utilisateurs externes)
    if (sections.adminCheckbox) {
        const adminCheckbox = document.getElementById(sections.adminCheckbox);
        if (adminCheckbox) {
            // Trouver le conteneur parent (div.mb-3) qui contient la case et son label
            const adminContainer = adminCheckbox.closest('.mb-3');
            if (adminContainer) {
                if (userGroup === 'Externe') {
                    adminContainer.style.display = 'none';
                    // Décocher la case si elle était cochée
                    adminCheckbox.checked = false;
                } else {
                    adminContainer.style.display = 'block';
                }
            }
        }
    }
    
    // Gérer la section client et localisations (pour les utilisateurs externes)
    if (sections.clientSection) {
        const clientSection = document.getElementById(sections.clientSection);
        const locationsContainer = document.getElementById(sections.locationsContainer || 'locations-container');
        const clientSelect = document.getElementById(sections.clientSelectId || 'client_id');
        
        if (userGroup === 'Externe') {
            if (clientSection) clientSection.style.display = 'block';
            // Masquer les localisations par défaut, elles s'afficheront quand un client sera sélectionné
            if (locationsContainer) locationsContainer.style.display = 'none';
        } else {
            if (clientSection) clientSection.style.display = 'none';
            if (locationsContainer) locationsContainer.style.display = 'none';
        }
    }
    
    // Gérer la section permissions (pour tous les types)
    if (sections.permissionsSection) {
        const permissionsSection = document.getElementById(sections.permissionsSection);
        if (permissionsSection) {
            if (userType === 'technicien' || userType === 'adv' || userType === 'client') {
                // Charger les permissions via AJAX simple
                loadPermissionsSimple(userType, sections.permissionsSection);
            } else {
                permissionsSection.style.display = 'none';
            }
        }
    }
}

/**
 * Charge les permissions de manière simple via AJAX
 * @param {string} userType - Le type d'utilisateur
 * @param {string} permissionsSectionId - L'ID de la section permissions
 */
function loadPermissionsSimple(userType, permissionsSectionId) {
    const permissionsSection = document.getElementById(permissionsSectionId);
    if (!permissionsSection) return;
    
    // Afficher un indicateur de chargement
    permissionsSection.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement des permissions...</div>';
    permissionsSection.style.display = 'block';
    
    // Récupérer l'ID de l'utilisateur depuis l'URL si on est en mode édition
    const urlParts = window.location.pathname.split('/');
    const userId = urlParts.includes('edit') && urlParts[urlParts.length - 1] ? urlParts[urlParts.length - 1] : null;
    
    // Faire la requête AJAX
    fetch(BASE_URL + 'user/load_permissions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'type=' + encodeURIComponent(userType) + (userId ? '&user_id=' + encodeURIComponent(userId) : '')
    })
    .then(response => response.json())
    .then(data => {
        if (data.html) {
            permissionsSection.innerHTML = data.html;
        } else {
            permissionsSection.innerHTML = '<p class="text-muted">Aucune permission disponible pour ce type d\'utilisateur.</p>';
        }
    })
    .catch(error => {
        console.error('Erreur lors du chargement des permissions:', error);
        permissionsSection.innerHTML = '<p class="text-danger">Erreur lors du chargement des permissions.</p>';
    });
}

/**
 * Charge les localisations d'un client de manière simple via AJAX
 * @param {string|number} clientId - L'ID du client
 * @param {string} locationsContainerId - L'ID du conteneur des localisations
 * @param {string|number|null} userId - L'ID de l'utilisateur pour pré-sélection (optionnel)
 */
function loadClientLocationsSimple(clientId, locationsContainerId, userId = null) {
    const locationsContainer = document.getElementById(locationsContainerId);
    if (!locationsContainer) return;
    
    // Afficher un indicateur de chargement
    locationsContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement des localisations...</div>';
    
    // Construire le body de la requête
    let body = 'client_id=' + encodeURIComponent(clientId);
    if (userId) {
        body += '&user_id=' + encodeURIComponent(userId);
    }
    
    // Faire la requête AJAX
    fetch(BASE_URL + 'user/load_client_locations', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.html) {
            locationsContainer.innerHTML = data.html;
        } else {
            locationsContainer.innerHTML = '<p class="text-muted">Aucune localisation disponible pour ce client.</p>';
        }
    })
    .catch(error => {
        console.error('Erreur lors du chargement des localisations:', error);
        locationsContainer.innerHTML = '<p class="text-danger">Erreur lors du chargement des localisations.</p>';
    });
}

/**
 * Charge les salles d'un client pour les contrats de manière simple via AJAX
 * @param {string|number} clientId - L'ID du client
 * @param {string} roomsContainerId - L'ID du conteneur des salles
 * @param {string|number|null} contractId - L'ID du contrat pour pré-sélection (optionnel)
 */
function loadContractRoomsSimple(clientId, roomsContainerId, contractId = null) {
    const roomsContainer = document.getElementById(roomsContainerId);
    if (!roomsContainer) return;
    
    // Afficher un indicateur de chargement
    roomsContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement des salles...</div>';
    
    // Construire le body de la requête
    let body = 'client_id=' + encodeURIComponent(clientId);
    if (contractId) {
        body += '&contract_id=' + encodeURIComponent(contractId);
    }
    
    // Faire la requête AJAX
    fetch(BASE_URL + 'contracts/load_client_rooms', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.html) {
            roomsContainer.innerHTML = data.html;
        } else {
            roomsContainer.innerHTML = '<p class="text-muted">Aucune salle disponible pour ce client.</p>';
        }
    })
    .catch(error => {
        console.error('Erreur lors du chargement des salles:', error);
        roomsContainer.innerHTML = '<p class="text-danger">Erreur lors du chargement des salles.</p>';
    });
} 