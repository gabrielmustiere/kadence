import { Controller } from '@hotwired/stimulus';

const OPEN_PROJECTS_KEY = 'roadmap-open-projects';
const ZOOM_KEY = 'roadmap-zoom';
const ZOOMS = [1, 2, 4, 8];

export default class extends Controller {
    static targets = ['project', 'scroller', 'track', 'zoomIn', 'zoomOut', 'zoomReset', 'zoomLevel'];
    // remember: false on the page of a project, which always opens unzoomed and centred, its zoom kept to itself.
    static values = { center: Number, remember: { type: Boolean, default: true } };

    #zoom = 1;

    connect() {
        const stored = this.rememberValue ? Number(sessionStorage.getItem(ZOOM_KEY)) : 1;
        this.#zoom = ZOOMS.includes(stored) ? stored : 1;
        this.#render();
        if (this.#zoom > 1 || !this.rememberValue) {
            this.#centerOn(this.centerValue / 100);
        }
    }

    projectTargetConnected(project) {
        project.open = this.#openProjects().has(project.dataset.projectId);
    }

    remember({ currentTarget: project }) {
        const openProjects = this.#openProjects();
        project.open ? openProjects.add(project.dataset.projectId) : openProjects.delete(project.dataset.projectId);
        sessionStorage.setItem(OPEN_PROJECTS_KEY, JSON.stringify([...openProjects]));
    }

    zoomIn() {
        this.#zoomTo(ZOOMS[Math.min(ZOOMS.indexOf(this.#zoom) + 1, ZOOMS.length - 1)]);
    }

    zoomOut() {
        this.#zoomTo(ZOOMS[Math.max(ZOOMS.indexOf(this.#zoom) - 1, 0)]);
    }

    zoomReset() {
        this.#zoomTo(1);
    }

    #zoomTo(zoom) {
        const center = this.#centerRatio();
        this.#zoom = zoom;
        if (this.rememberValue) {
            sessionStorage.setItem(ZOOM_KEY, String(zoom));
        }
        this.#render();
        if (null !== center) {
            this.#centerOn(center);
        }
    }

    #render() {
        this.zoomLevelTarget.textContent = `×${this.#zoom}`;
        this.zoomOutTarget.disabled = this.#zoom === ZOOMS[0];
        this.zoomInTarget.disabled = this.#zoom === ZOOMS[ZOOMS.length - 1];
        this.zoomResetTarget.disabled = this.#zoom === 1;
        if (this.hasScrollerTarget) {
            this.scrollerTarget.style.setProperty('--roadmap-zoom', String(this.#zoom));
        }
    }

    /** Where the middle of the visible part of the track falls, from 0 (its first day) to 1 (its last). */
    #centerRatio() {
        if (!this.hasScrollerTarget) {
            return null;
        }

        const { width, visible } = this.#trackGeometry();

        return (this.scrollerTarget.scrollLeft + visible / 2) / width;
    }

    #centerOn(ratio) {
        if (!this.hasScrollerTarget) {
            return;
        }

        const { width, visible } = this.#trackGeometry();
        this.scrollerTarget.scrollLeft = ratio * width - visible / 2;
    }

    /** The track starts after the sticky column of titles, which hides the start of what the scroller shows. */
    #trackGeometry() {
        const scroller = this.scrollerTarget;
        const track = this.trackTarget.getBoundingClientRect();
        const start = track.left - scroller.getBoundingClientRect().left - scroller.clientLeft + scroller.scrollLeft;

        return { start, width: track.width, visible: scroller.clientWidth - start };
    }

    #openProjects() {
        return new Set(JSON.parse(sessionStorage.getItem(OPEN_PROJECTS_KEY) ?? '[]'));
    }
}
