// Lecture de QR codes par la caméra : détecteur natif du navigateur si disponible,
// sinon la bibliothèque html5-qrcode, chargée seulement dans ce cas.

const BIBLIOTHEQUE = '/assets/vendor/html5-qrcode-2.3.8.min.js';
const ANTI_REBOND_MS = 3000;

function chargerBibliotheque() {
  if (window.Html5Qrcode) {
    return Promise.resolve();
  }

  return new Promise((resoudre, rejeter) => {
    const script = document.createElement('script');
    script.src = BIBLIOTHEQUE;
    script.onload = () => resoudre();
    script.onerror = () => rejeter(new Error('Lecteur de QR indisponible.'));
    document.head.append(script);
  });
}

export class Scanner {
  constructor({ conteneur, onLecture, onMessage }) {
    this.conteneur = conteneur;
    this.video = conteneur.querySelector('[data-video]');
    this.zoneBibliotheque = conteneur.querySelector('#lecteur-qr');
    this.onLecture = onLecture;
    this.onMessage = onMessage ?? (() => {});
    this.flux = null;
    this.lecteur = null;
    this.actif = false;
    this.derniereLecture = { texte: '', instant: 0 };
  }

  async demarrer() {
    this.actif = true;
    this.onMessage('Ouverture de la caméra…');
    try {
      if ('BarcodeDetector' in window && (await BarcodeDetector.getSupportedFormats()).includes('qr_code')) {
        await this.demarrerNatif();
      } else {
        await this.demarrerBibliotheque();
      }
      this.onMessage('Placez le QR code dans le cadre');
    } catch (erreur) {
      this.actif = false;
      this.onMessage(erreur.name === 'NotAllowedError'
        ? 'Accès à la caméra refusé. Autorisez-le dans les réglages du navigateur.'
        : 'Caméra indisponible sur cet appareil.');
      throw erreur;
    }
  }

  async demarrerNatif() {
    this.flux = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } },
      audio: false,
    });
    this.video.srcObject = this.flux;
    await this.video.play();
    const detecteur = new BarcodeDetector({ formats: ['qr_code'] });

    const analyser = async () => {
      if (!this.actif) {
        return;
      }
      try {
        const codes = await detecteur.detect(this.video);
        if (codes.length > 0) {
          this.lu(codes[0].rawValue);
        }
      } catch {
        // Image illisible : on passe à la suivante.
      }
      setTimeout(analyser, 150);
    };
    analyser();
  }

  async demarrerBibliotheque() {
    await chargerBibliotheque();
    this.video.hidden = true;
    this.zoneBibliotheque.hidden = false;
    this.lecteur = new window.Html5Qrcode(this.zoneBibliotheque.id, { verbose: false });
    await this.lecteur.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 240, height: 240 } },
      (texte) => this.lu(texte),
      () => {},
    );
  }

  lu(texte) {
    const maintenant = Date.now();
    if (texte === this.derniereLecture.texte && maintenant - this.derniereLecture.instant < ANTI_REBOND_MS) {
      return;
    }
    this.derniereLecture = { texte, instant: maintenant };
    this.onLecture(texte);
  }

  peutEclairer() {
    const piste = this.flux?.getVideoTracks()[0];

    return Boolean(piste?.getCapabilities?.().torch);
  }

  async eclairer(allumer) {
    const piste = this.flux?.getVideoTracks()[0];
    if (piste) {
      await piste.applyConstraints({ advanced: [{ torch: allumer }] });
    }
  }

  async arreter() {
    this.actif = false;
    this.flux?.getTracks().forEach((piste) => piste.stop());
    this.flux = null;
    if (this.lecteur) {
      try {
        await this.lecteur.stop();
      } catch {
        // Déjà arrêté.
      }
      this.lecteur = null;
    }
  }
}

// Position GPS au moment du scan uniquement, avec un délai court pour ne pas faire attendre l'agent.
export function position(autorisee) {
  if (!autorisee) {
    return Promise.resolve({ geoloc: 'refusee' });
  }
  if (!('geolocation' in navigator)) {
    return Promise.resolve({ geoloc: 'indisponible' });
  }

  return new Promise((resoudre) => {
    navigator.geolocation.getCurrentPosition(
      (p) => resoudre({ lat: p.coords.latitude, lng: p.coords.longitude, precision: Math.round(p.coords.accuracy), geoloc: 'ok' }),
      (erreur) => resoudre({ geoloc: erreur.code === erreur.PERMISSION_DENIED ? 'refusee' : 'indisponible' }),
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
    );
  });
}

// Contrôle local du format, utilisé hors ligne : la signature est vérifiée par le serveur.
export function formatValide(texte, types) {
  const parties = String(texte).trim().split('.');

  return parties[0] === 'PGS1' && types.includes(parties[1]) && parties.slice(2, -1).every((p) => /^\d{1,12}$/.test(p));
}
