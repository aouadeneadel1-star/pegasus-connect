// Composants d'interface partagés : messages éphémères, confirmations, vibration.

export function toast(message, type = 'info', duree = 5000) {
  const zone = document.querySelector('[data-toasts]');
  if (!zone) {
    return;
  }
  const element = document.createElement('div');
  element.className = `toast toast-${type}`;
  element.setAttribute('role', type === 'erreur' ? 'alert' : 'status');
  element.textContent = message;
  zone.append(element);
  setTimeout(() => element.remove(), duree);
}

let dialogue = null;

function dialogueConfirmation() {
  if (dialogue) {
    return dialogue;
  }
  dialogue = document.createElement('dialog');
  dialogue.className = 'dialog';
  dialogue.innerHTML = `
    <form method="dialog" class="stack">
      <p data-message></p>
      <div class="row row-end">
        <button class="btn btn-ghost" value="non">Annuler</button>
        <button class="btn btn-primary" value="oui">Confirmer</button>
      </div>
    </form>`;
  document.body.append(dialogue);

  return dialogue;
}

export function confirmer(message) {
  const fenetre = dialogueConfirmation();
  fenetre.querySelector('[data-message]').textContent = message;
  fenetre.returnValue = '';

  return new Promise((resoudre) => {
    fenetre.addEventListener('close', () => resoudre(fenetre.returnValue === 'oui'), { once: true });
    fenetre.showModal();
    fenetre.querySelector('button[value="oui"]').focus();
  });
}

export function vibrer(motif) {
  if (navigator.vibrate) {
    navigator.vibrate(motif);
  }
}

export function formaterHeure(iso) {
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Paris' });
}
