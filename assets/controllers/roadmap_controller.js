import { Controller } from '@hotwired/stimulus';

const OPEN_PROJECTS_KEY = 'roadmap-open-projects';

export default class extends Controller {
    static targets = ['project'];

    projectTargetConnected(project) {
        project.open = this.#openProjects().has(project.dataset.projectId);
    }

    remember({ currentTarget: project }) {
        const openProjects = this.#openProjects();
        project.open ? openProjects.add(project.dataset.projectId) : openProjects.delete(project.dataset.projectId);
        sessionStorage.setItem(OPEN_PROJECTS_KEY, JSON.stringify([...openProjects]));
    }

    #openProjects() {
        return new Set(JSON.parse(sessionStorage.getItem(OPEN_PROJECTS_KEY) ?? '[]'));
    }
}
