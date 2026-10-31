// Planning : ouverture du formulaire de vacation, prérempli depuis la case cliquée.
const dialogue = document.querySelector('[data-dialog-vacation]');

if (dialogue) {
  const formulaire = dialogue.querySelector('[data-formulaire-vacation]');
  const suppression = dialogue.querySelector('[data-formulaire-suppression]');
  const boutonSupprimer = dialogue.querySelector('[data-supprimer]');
  const zoneMessages = dialogue.querySelector('[data-messages]');
  const zoneForcer = dialogue.querySelector('[data-forcer]');

  const remplir = (donnees) => {
    formulaire.reset();
    for (const [champ, valeur] of Object.entries(donnees)) {
      const element = formulaire.elements[champ];
      if (element && element.type !== 'checkbox' && valeur !== null && valeur !== undefined) {
        element.value = String(valeur);
      }
    }
    const existante = Number(donnees.vacation_id ?? 0) > 0;
    boutonSupprimer.hidden = !existante;
    suppression.action = existante ? `/admin/planning/vacations/${donnees.vacation_id}/supprimer` : '';

    const messages = donnees.messages ?? [];
    zoneMessages.hidden = messages.length === 0;
    zoneMessages.querySelector('ul').replaceChildren(...messages.map((texte) => Object.assign(document.createElement('li'), { textContent: texte })));
    zoneForcer.hidden = !donnees.forcable;
    formulaire.elements.motif.required = Boolean(donnees.forcable);
    dialogue.showModal();
  };

  document.addEventListener('click', (evenement) => {
    const existante = evenement.target.closest('[data-vacation]');
    const nouvelle = evenement.target.closest('[data-nouvelle-vacation]');
    if (existante && !existante.disabled) {
      remplir(JSON.parse(existante.dataset.vacation));
    } else if (nouvelle) {
      remplir({ ...JSON.parse(nouvelle.dataset.nouvelleVacation), vacation_id: '', heure_debut: '07:00', heure_fin: '19:00', pause_minutes: 0 });
    }
  });

  dialogue.querySelector('[data-fermer]').addEventListener('click', () => dialogue.close());

  // Retour du serveur avec des alertes : le formulaire est rouvert avec la saisie précédente.
  if (dialogue.dataset.ouvrir) {
    remplir(JSON.parse(dialogue.dataset.ouvrir));
  }
}
