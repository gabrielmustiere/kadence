import './stimulus_bootstrap.js';
import './styles/app.css';
import { initFlowbite } from 'flowbite';

// Flowbite ne s'initialise qu'au premier chargement : on le relance après chaque rendu Turbo
// (mêmes événements que le build Turbo officiel de Flowbite).
['turbo:render', 'turbo:frame-load', 'turbo:after-stream-render'].forEach((event) => {
    document.addEventListener(event, () => initFlowbite());
});
