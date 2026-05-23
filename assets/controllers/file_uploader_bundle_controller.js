// assets/controllers/upload_files_controller.js
import { Controller } from "@hotwired/stimulus";
import uploadFiles from "../components/upload_files.js";

export default class extends Controller {    
    connect() {
        console.log(' Upload_files_controller ');
        if (!this.element) return;        
        // Initialisation de l'instance autonome de gestion d'upload
        this.uploadFilesComponent = new uploadFiles(this.element);
        this.uploadFilesComponent.build();
    }
    disconnect() {
        if (this.uploadFilesComponent && typeof this.uploadFilesComponent.destroy === "function") {
            this.uploadFilesComponent.destroy();
        }
        this.uploadFilesComponent = null;
    }
}