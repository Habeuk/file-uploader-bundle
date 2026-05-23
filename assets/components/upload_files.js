// assets/components/upload_files.js
import requestAxios from "../rootRequest";

/**
 * Composant de gestion d'upload de fichiers asynchrone sécurisé.
 */
class uploadFiles {
    /**
     * @param {HTMLElement} containerFiles 
     */
    constructor(containerFiles) {
        this.containerFiles = containerFiles;
        this.multiple = false;
        
        /** @type {number[]} */
        this.fids = [];
        
        /** @type {HTMLElement|null} */
        this.dropzone = null;
        
        /** @type {HTMLInputElement|null} */
        this.fileInput = null;
        
        /** @type {HTMLElement|null} */
        this.previewsContainer = null;
        
        /** @type {HTMLInputElement|null} */
        this.hiddenInput = null;
        
        this.typeOfFile = 'document';
        this.tokenCsrf = '';
        this.storeType = 'public';

        // Tables de hachage pour stocker les liaisons de fonctions d'événements (nécessaire pour removeEventListener)
        this.boundHandlers = new Map();
    }

    /**
     * Initialise le composant et monte les écouteurs.
     * @return {void}
     */
    build() {        
        this.buildFieldsSelector();
        if (!this.fileInput || !this.dropzone || !this.hiddenInput) {
            console.error("Composant uploadFiles : Éléments DOM requis manquants.");
            return;
        }
        this.getValue();
        this.renderInitialPreviews();
        this.showBlockUpload();
        this.manageDragZone();
        this.uploadFiles();
    }

    /**
     * Extrait et mappe les références du DOM.
     * @private
     * @return {void}
     */
    buildFieldsSelector() {
        this.dropzone = this.containerFiles.querySelector("[data-upload-files-target='dropzone']");
        this.fileInput = this.containerFiles.querySelector("[data-upload-files-target='input']");
        this.previewsContainer = this.containerFiles.querySelector("[data-upload-files-target='previews']");
        this.hiddenInput = this.containerFiles.querySelector(".files-upload-js-input");
        this.typeOfFile = this.containerFiles.getAttribute("data-type") || 'document';
        this.tokenCsrf = this.containerFiles.getAttribute("data-csrf-token") || '';
        this.multiple = this.containerFiles.getAttribute("data-multiple") === "true";
        this.storeType = this.containerFiles.getAttribute("data-private-file")?"private":"public";
    }

    /**
     * Extrait les fids initiaux depuis l'input masqué sécurisé.
     * @private
     * @return {number[]}
     */
    getValue() {
        if (!this.hiddenInput || !this.hiddenInput.value) {
            this.fids = [];
            return this.fids;
        }

        const value = this.hiddenInput.value.trim();

        if (value.startsWith('[') && value.endsWith(']')) {
            try {
                this.fids = JSON.parse(value)
                    .map((/** @type {any} */ id) => parseInt(id, 10))
                    .filter((/** @type {number} */ id) => !isNaN(id));
            } catch (e) {
                this.fids = [];
            }
        } else if (value !== '') {
            const singleId = parseInt(value, 10);
            this.fids = !isNaN(singleId) ? [singleId] : [];
        }

        return this.fids;
    }

    /**
     * Synchronise l'état local vers le champ de formulaire de Symfony.
     * @private
     * @return {void}
     */
    setValue() {
        if (!this.hiddenInput) return;

        if (this.multiple) {
            this.hiddenInput.value = this.fids.length > 0 ? JSON.stringify(this.fids) : "";
        } else {
            this.hiddenInput.value = this.fids.length > 0 ? this.fids[0].toString() : "";
        }

        this.hiddenInput.dispatchEvent(new Event("change", { bubbles: true }));
    }

    /**
     * Rentre les miniatures initiales injectées par le serveur (A11y & Sécurisé)
     * @private
     * @return {void}
     */
    renderInitialPreviews() {
        const previewsDataAttr = this.containerFiles.getAttribute('data-previews');
        if (!previewsDataAttr || !this.previewsContainer) return;

        try {
            const initialFiles = JSON.parse(previewsDataAttr);
            if (Array.isArray(initialFiles)) {
                initialFiles.forEach(file => {
                    const id = parseInt(file.id, 10);
                    if (!isNaN(id)) {
                        this.appendPreviewDOM(id, file.name, file.thumbnail);
                    }
                });
            }
        } catch (e) {
            console.error("Erreur d'initialisation des miniatures :", e);
        }
    }

    /**
     * Ajoute un identifiant de fichier de manière sécurisée.
     * @param {number|string} fid 
     * @return {void}
     */
    addFile(fid) {
        const id = parseInt(fid, 10);
        if (isNaN(id)) return;

        if (this.multiple) {
            if (!this.fids.includes(id)) {
                this.fids.push(id);
            }
        } else {
            // Mode simple : On supprime l'ancienne miniature s'il y en a une
            if (this.fids.length > 0 && this.previewsContainer) {
                const oldPreview = this.previewsContainer.querySelector(`[data-fid="${this.fids[0]}"]`);
                if (oldPreview) oldPreview.remove();
            }
            this.fids = [id];
        }
        this.setValue();
        this.showBlockUpload();
    }

    /**
     * Supprime un identifiant de fichier et met à jour l'interface.
     * @param {number|string} fid 
     * @return {void}
     */
    removeFile(fid) {
        const id = parseInt(fid, 10);
        if (isNaN(id)) return;

        this.fids = this.fids.filter((value) => value !== id);
        
        this.setValue();
        this.showBlockUpload();

        if (this.previewsContainer) {
            const preview = this.previewsContainer.querySelector(`[data-fid="${id}"]`);
            if (preview) {
                preview.remove();
            }
        }
    }

    /**
     * Alterne l'affichage de la dropzone via les classes Tailwind CSS.
     * @return {void}
     */
    showBlockUpload() {
        if (!this.dropzone) return;
        
        if (!this.multiple && this.fids.length > 0) {
            this.dropzone.classList.replace("flex", "hidden");
        } else {
            this.dropzone.classList.replace("hidden", "flex");
        }
    }

    /**
     * Enregistre un écouteur et stocke sa référence pour le nettoyage futur.
     * @private
     * @param {EventTarget} target 
     * @param {string} type 
     * @param {Function} callback 
     */
    _addEventListener(target, type, callback) {
        const bound = callback.bind(this);
        target.addEventListener(type, bound);
        if (!this.boundHandlers.has(target)) {
            this.boundHandlers.set(target, []);
        }
        this.boundHandlers.get(target).push({ type, bound });
    }

    /**
     * Gère les événements de Drag & Drop sur la zone ciblée.
     * @private
     * @return {void}
     */
    manageDragZone() {
        if (!this.dropzone || !this.fileInput) return;

        this._addEventListener(this.dropzone, "dragover", (/** @type {DragEvent} */ e) => {
            e.preventDefault();
            this.dropzone?.classList.replace('border-slate-300', 'border-indigo-500');
            this.dropzone?.classList.add('bg-slate-100', 'dark:bg-slate-900');
        });

        const resetDragStyles = (/** @type {DragEvent} */ e) => {
            e.preventDefault();
            this.dropzone?.classList.replace('border-indigo-500', 'border-slate-300');
            this.dropzone?.classList.remove('bg-slate-100', 'dark:bg-slate-900');
        };

        this._addEventListener(this.dropzone, "dragleave", resetDragStyles);

        this._addEventListener(this.dropzone, "drop", (/** @type {DragEvent} */ e) => {
            resetDragStyles(e);
            const files = e.dataTransfer?.files;
            if (files && files.length && this.fileInput) {
                this.fileInput.files = files;
                this.fileInput.dispatchEvent(new Event("change", { bubbles: true }));
            }
        });

        this._addEventListener(this.dropzone, "click", (/** @type {MouseEvent} */ e) => {
            // Ne pas déclencher si le clic provient du bouton supprimer d'une preview
            if (e.target instanceof HTMLElement && e.target.closest('button[data-action="remove"]')) {
                return;
            }
            this.fileInput?.click();
        });
    }

    /**
     * Intercepte les changements sur l'input de fichier pour exécuter la requête d'envoi.
     * @private
     * @return {void}
     */
    uploadFiles() {
        if (!this.fileInput) return;

        this._addEventListener(this.fileInput, "change", (/** @type {Event} */ e) => {
            const target = e.target;
            if (!(target instanceof HTMLInputElement)) return;
            
            const files = target.files;
            if (!files || files.length === 0) return;

            const entities = Array.from(files).map((file) => ({
                file: file,
                type: this.typeOfFile
            }));

            requestAxios.postEntites("/file-uploader-bundle/upload/files?store_type="+this.storeType, entities, {}, this.tokenCsrf)
                .then((result) => {
                    if (result && result.data && Array.isArray(result.data)) {
                        result.data.forEach((entityFile) => {
                            const fid = parseInt(entityFile.id, 10);
                            if (!isNaN(fid)) {
                                this.addFile(fid);
                                this.appendPreviewDOM(fid, entityFile.originalName || 'Fichier', entityFile.thumbnail);
                            }
                        });
                    }
                })
                .catch((error) => {
                    console.error("Erreur lors de l'upload d'entités :", error);
                });
        });
    }

    /**
     * Construit et insère dynamiquement le DOM d'une miniature sous Tailwind.
     * @private
     * @param {number} fid 
     * @param {string} name 
     * @param {string} thumbnailUrl 
     * @return {void}
     */
    appendPreviewDOM(fid, name, thumbnailUrl) {
        if (!this.previewsContainer) return;

        const previewDiv = document.createElement("div");
        previewDiv.className = "relative group/preview w-24 h-24 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-100 shadow-sm animate-fade-in";
        previewDiv.setAttribute("data-fid", fid.toString());

        const img = document.createElement("img");
        img.src = thumbnailUrl;
        img.className = "w-full h-full object-cover";
        img.alt = name;
        img.title = name;

        const button = document.createElement("button");
        button.type = "button";
        button.setAttribute("data-action", "remove");
        button.setAttribute("data-fid", fid.toString());
        button.className = "absolute top-1 right-1 p-1 rounded-full bg-rose-500 hover:bg-rose-600 text-white shadow-md opacity-0 group-hover/preview:opacity-100 focus:opacity-100 transition-opacity duration-150 ease-in-out cursor-pointer";
        button.title = "Supprimer le fichier";

        // Insertion d'un SVG minimaliste et accessible
        button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        `;

        this._addEventListener(button, "click", (/** @type {MouseEvent} */ event) => {
            event.preventDefault();
            event.stopPropagation();
            this.removeFile(fid);
        });

        previewDiv.appendChild(img);
        previewDiv.appendChild(button);
        this.previewsContainer.appendChild(previewDiv);
    }

    /**
     * Nettoie les écouteurs d'événements du DOM lors de la déconnexion pour éviter les fuites de mémoire.
     * @return {void}
     */
    destroy() {
        this.boundHandlers.forEach((listeners, target) => {
            listeners.forEach(({ type, bound }) => {
                target.removeEventListener(type, bound);
            });
        });
        this.boundHandlers.clear();
        this.fids = [];
    }
}

export default uploadFiles;