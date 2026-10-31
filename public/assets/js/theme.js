// Chargé dans <head> avant l'affichage pour éviter un flash de thème au démarrage.
(() => {
  try {
    const theme = localStorage.getItem('pg-theme');
    if (theme === 'light' || theme === 'dark') {
      document.documentElement.dataset.theme = theme;
    }
  } catch {
    // Stockage indisponible (navigation privée) : le thème suit le système.
  }
})();
