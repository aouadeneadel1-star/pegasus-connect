// Modèle de ronde : réordonner les points avec les boutons Monter / Descendre.
const liste = document.querySelector('[data-ordre-points]');

liste?.addEventListener('click', (evenement) => {
  const bouton = evenement.target.closest('[data-monter], [data-descendre]');
  if (!bouton) {
    return;
  }
  const ligne = bouton.closest('[data-point]');
  if (bouton.hasAttribute('data-monter') && ligne.previousElementSibling) {
    liste.insertBefore(ligne, ligne.previousElementSibling);
  } else if (bouton.hasAttribute('data-descendre') && ligne.nextElementSibling) {
    liste.insertBefore(ligne.nextElementSibling, ligne);
  }
  bouton.focus();
});
