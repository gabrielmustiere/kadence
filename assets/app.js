import './stimulus_bootstrap.js';
import './styles/app.css';
import { initFlowbite } from 'flowbite';

// Flowbite ne s'initialise qu'au premier chargement : on le relance après chaque rendu Turbo
// (mêmes événements que le build Turbo officiel de Flowbite).
['turbo:render', 'turbo:frame-load', 'turbo:after-stream-render'].forEach((event) => {
    document.addEventListener(event, () => initFlowbite());
});

// Un rafraîchissement en morph ne relit pas l'état qu'un live component garde dans le navigateur : chacun est remplacé
// par sa nouvelle version une fois la page fusionnée, au lieu d'être fusionné avec l'ancienne.
const morphedLiveComponents = [];
document.addEventListener('turbo:before-morph-element', (event) => {
    const { target, detail: { newElement } } = event;
    if (target.hasAttribute('data-live-name-value') && newElement instanceof Element) {
        event.preventDefault();
        morphedLiveComponents.push([target, newElement]);
    }
});
document.addEventListener('turbo:morph', () => {
    morphedLiveComponents.splice(0).forEach(([current, next]) => current.isConnected && current.replaceWith(next));
});
