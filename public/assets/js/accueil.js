// Démarrage d'une ronde planifiée ou libre depuis l'accueil et la liste des rondes.
import { envoyer, uuid, ErreurReseau } from './api.js';
import { toast } from './ui.js';

document.addEventListener('click', async (evenement) => {
  const bouton = evenement.target.closest('[data-demarrer-ronde]');
  if (!bouton) {
    return;
  }
  bouton.disabled = true;
  try {
    const rondeId = bouton.dataset.demarrerRonde;
    const etat = await envoyer('/api/v1/rondes', { ronde_id: rondeId === '' ? null : Number(rondeId), uuid: uuid() });
    window.location.href = `/rondes/${etat.id}`;
  } catch (erreur) {
    bouton.disabled = false;
    toast(erreur instanceof ErreurReseau ? 'Le démarrage d\'une ronde demande du réseau. Réessayez dans un instant.' : erreur.message, 'erreur');
  }
});
