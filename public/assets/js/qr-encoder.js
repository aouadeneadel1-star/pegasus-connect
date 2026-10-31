// Générateur de QR code (ISO/IEC 18004), mode octet, correction M, versions 1 à 10.
// Utilisé par la borne pour produire ses QR sans réseau : un jeton fait environ 45 caractères.

const CORRECTION_M = 0; // bits du niveau M dans les informations de format

// Par version : [codewords de correction par bloc, [nombre de blocs, codewords de données]…]
const BLOCS = [
  null,
  [10, [1, 16]],
  [16, [1, 28]],
  [26, [1, 44]],
  [18, [2, 32]],
  [24, [2, 43]],
  [16, [4, 27]],
  [18, [4, 31]],
  [22, [2, 38], [2, 39]],
  [22, [3, 36], [2, 37]],
  [26, [4, 43], [1, 44]],
];

const ALIGNEMENTS = [null, [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50]];

// Corps de Galois GF(256), polynôme primitif x^8 + x^4 + x^3 + x^2 + 1.
const EXP = new Uint8Array(512);
const LOG = new Uint8Array(256);
(() => {
  let x = 1;
  for (let i = 0; i < 255; i++) {
    EXP[i] = x;
    LOG[x] = i;
    x <<= 1;
    if (x & 0x100) {
      x ^= 0x11d;
    }
  }
  for (let i = 255; i < 512; i++) {
    EXP[i] = EXP[i - 255];
  }
})();

const multiplier = (a, b) => (a === 0 || b === 0 ? 0 : EXP[LOG[a] + LOG[b]]);

function generateur(degre) {
  let poly = [1];
  for (let i = 0; i < degre; i++) {
    const suivant = new Array(poly.length + 1).fill(0);
    for (let j = 0; j < poly.length; j++) {
      suivant[j] ^= poly[j];
      suivant[j + 1] ^= multiplier(poly[j], EXP[i]);
    }
    poly = suivant;
  }

  return poly;
}

function correction(donnees, degre) {
  const gen = generateur(degre);
  const reste = [...donnees, ...new Array(degre).fill(0)];
  for (let i = 0; i < donnees.length; i++) {
    const coef = reste[i];
    if (coef !== 0) {
      for (let j = 1; j < gen.length; j++) {
        reste[i + j] ^= multiplier(gen[j], coef);
      }
    }
  }

  return reste.slice(donnees.length);
}

function capacite(version) {
  const [, ...groupes] = BLOCS[version];

  return groupes.reduce((total, [nombre, taille]) => total + nombre * taille, 0);
}

function codewords(octets, version) {
  const bits = [];
  const pousser = (valeur, longueur) => {
    for (let i = longueur - 1; i >= 0; i--) {
      bits.push((valeur >>> i) & 1);
    }
  };
  pousser(0b0100, 4);
  pousser(octets.length, version < 10 ? 8 : 16);
  octets.forEach((octet) => pousser(octet, 8));

  const total = capacite(version) * 8;
  pousser(0, Math.min(4, total - bits.length));
  while (bits.length % 8 !== 0) {
    bits.push(0);
  }
  for (let remplissage = 0xec; bits.length < total; remplissage ^= 0xec ^ 0x11) {
    pousser(remplissage, 8);
  }

  const donnees = [];
  for (let i = 0; i < bits.length; i += 8) {
    donnees.push(bits.slice(i, i + 8).reduce((octet, bit) => (octet << 1) | bit, 0));
  }

  // Découpage en blocs, correction d'erreurs par bloc, puis entrelacement.
  const [degre, ...groupes] = BLOCS[version];
  const blocs = [];
  let position = 0;
  for (const [nombre, taille] of groupes) {
    for (let i = 0; i < nombre; i++) {
      const bloc = donnees.slice(position, position + taille);
      position += taille;
      blocs.push({ donnees: bloc, correction: correction(bloc, degre) });
    }
  }

  const resultat = [];
  const plusLong = Math.max(...blocs.map((b) => b.donnees.length));
  for (let i = 0; i < plusLong; i++) {
    blocs.forEach((b) => {
      if (i < b.donnees.length) {
        resultat.push(b.donnees[i]);
      }
    });
  }
  for (let i = 0; i < degre; i++) {
    blocs.forEach((b) => resultat.push(b.correction[i]));
  }

  return resultat;
}

class Matrice {
  constructor(version) {
    this.version = version;
    this.taille = version * 4 + 17;
    this.modules = Array.from({ length: this.taille }, () => new Array(this.taille).fill(false));
    this.fonction = Array.from({ length: this.taille }, () => new Array(this.taille).fill(false));
  }

  fixer(x, y, sombre) {
    this.modules[y][x] = sombre;
    this.fonction[y][x] = true;
  }

  motifsFixes() {
    const t = this.taille;
    for (let i = 0; i < t; i++) {
      this.fixer(6, i, i % 2 === 0);
      this.fixer(i, 6, i % 2 === 0);
    }
    [[3, 3], [t - 4, 3], [3, t - 4]].forEach(([cx, cy]) => {
      for (let dy = -4; dy <= 4; dy++) {
        for (let dx = -4; dx <= 4; dx++) {
          const x = cx + dx;
          const y = cy + dy;
          if (x >= 0 && x < t && y >= 0 && y < t) {
            const distance = Math.max(Math.abs(dx), Math.abs(dy));
            this.fixer(x, y, distance !== 2 && distance !== 4);
          }
        }
      }
    });

    const centres = ALIGNEMENTS[this.version];
    const dernier = centres.length - 1;
    centres.forEach((cx, i) => centres.forEach((cy, j) => {
      if ((i === 0 && j === 0) || (i === 0 && j === dernier) || (i === dernier && j === 0)) {
        return;
      }
      for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
          this.fixer(cx + dx, cy + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }
      }
    }));

    this.format(0);
    if (this.version >= 7) {
      let reste = this.version;
      for (let i = 0; i < 12; i++) {
        reste = (reste << 1) ^ ((reste >>> 11) * 0x1f25);
      }
      const bits = (this.version << 12) | reste;
      for (let i = 0; i < 18; i++) {
        const sombre = ((bits >>> i) & 1) === 1;
        const a = t - 11 + (i % 3);
        const b = Math.floor(i / 3);
        this.fixer(a, b, sombre);
        this.fixer(b, a, sombre);
      }
    }
  }

  format(masque) {
    const donnees = (CORRECTION_M << 3) | masque;
    let reste = donnees;
    for (let i = 0; i < 10; i++) {
      reste = (reste << 1) ^ ((reste >>> 9) * 0x537);
    }
    const bits = ((donnees << 10) | reste) ^ 0x5412;
    const bit = (i) => ((bits >>> i) & 1) === 1;
    const t = this.taille;

    for (let i = 0; i <= 5; i++) {
      this.fixer(8, i, bit(i));
    }
    this.fixer(8, 7, bit(6));
    this.fixer(8, 8, bit(7));
    this.fixer(7, 8, bit(8));
    for (let i = 9; i < 15; i++) {
      this.fixer(14 - i, 8, bit(i));
    }
    for (let i = 0; i < 8; i++) {
      this.fixer(t - 1 - i, 8, bit(i));
    }
    for (let i = 8; i < 15; i++) {
      this.fixer(8, t - 15 + i, bit(i));
    }
    this.fixer(8, t - 8, true);
  }

  placer(octets) {
    const t = this.taille;
    let i = 0;
    for (let droite = t - 1; droite >= 1; droite -= 2) {
      if (droite === 6) {
        droite = 5;
      }
      for (let vertical = 0; vertical < t; vertical++) {
        for (let j = 0; j < 2; j++) {
          const x = droite - j;
          const montant = ((droite + 1) & 2) === 0;
          const y = montant ? t - 1 - vertical : vertical;
          if (!this.fonction[y][x] && i < octets.length * 8) {
            this.modules[y][x] = ((octets[i >>> 3] >>> (7 - (i & 7))) & 1) === 1;
            i++;
          }
        }
      }
    }
  }

  appliquerMasque(masque) {
    const conditions = [
      (x, y) => (x + y) % 2 === 0,
      (x, y) => y % 2 === 0,
      (x) => x % 3 === 0,
      (x, y) => (x + y) % 3 === 0,
      (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0,
      (x, y) => ((x * y) % 2) + ((x * y) % 3) === 0,
      (x, y) => (((x * y) % 2) + ((x * y) % 3)) % 2 === 0,
      (x, y) => (((x + y) % 2) + ((x * y) % 3)) % 2 === 0,
    ];
    for (let y = 0; y < this.taille; y++) {
      for (let x = 0; x < this.taille; x++) {
        if (!this.fonction[y][x] && conditions[masque](x, y)) {
          this.modules[y][x] = !this.modules[y][x];
        }
      }
    }
  }

  penalite() {
    const t = this.taille;
    const m = this.modules;
    let score = 0;

    const lignes = [];
    for (let y = 0; y < t; y++) {
      lignes.push(m[y]);
      lignes.push(m.map((ligne) => ligne[y]));
    }
    for (const ligne of lignes) {
      let longueur = 1;
      for (let i = 1; i <= t; i++) {
        if (i < t && ligne[i] === ligne[i - 1]) {
          longueur++;
        } else {
          if (longueur >= 5) {
            score += 3 + (longueur - 5);
          }
          longueur = 1;
        }
      }
      const texte = ligne.map((sombre) => (sombre ? '1' : '0')).join('');
      for (const motif of ['10111010000', '00001011101']) {
        for (let index = texte.indexOf(motif); index !== -1; index = texte.indexOf(motif, index + 1)) {
          score += 40;
        }
      }
    }

    for (let y = 0; y < t - 1; y++) {
      for (let x = 0; x < t - 1; x++) {
        const c = m[y][x];
        if (c === m[y][x + 1] && c === m[y + 1][x] && c === m[y + 1][x + 1]) {
          score += 3;
        }
      }
    }

    const sombres = m.reduce((total, ligne) => total + ligne.filter(Boolean).length, 0);
    const k = Math.ceil(Math.abs(sombres * 20 - t * t * 10) / (t * t)) - 1;

    return score + Math.max(0, k) * 10;
  }
}

/**
 * @returns {boolean[][]} modules (true = sombre), sans marge
 */
export function encoder(texte) {
  const octets = [...new TextEncoder().encode(texte)];
  let version = 1;
  while (version <= 10 && 4 + (version < 10 ? 8 : 16) + octets.length * 8 > capacite(version) * 8) {
    version++;
  }
  if (version > 10) {
    throw new Error('Contenu trop long pour un QR code de version 10.');
  }

  const donnees = codewords(octets, version);
  let meilleure = null;
  let meilleurScore = Infinity;
  for (let masque = 0; masque < 8; masque++) {
    const matrice = new Matrice(version);
    matrice.motifsFixes();
    matrice.placer(donnees);
    matrice.appliquerMasque(masque);
    matrice.format(masque);
    const score = matrice.penalite();
    if (score < meilleurScore) {
      meilleurScore = score;
      meilleure = matrice;
    }
  }

  return meilleure.modules;
}

/**
 * Dessine le QR sur un canvas, avec la marge de 4 modules exigée par la norme.
 */
export function dessiner(canvas, texte, couleur = '#000000', fond = '#ffffff') {
  const modules = encoder(texte);
  const marge = 4;
  const total = modules.length + marge * 2;
  const echelle = Math.floor(Math.min(canvas.width, canvas.height) / total);
  const decalage = Math.floor((canvas.width - echelle * total) / 2);
  const contexte = canvas.getContext('2d');

  contexte.fillStyle = fond;
  contexte.fillRect(0, 0, canvas.width, canvas.height);
  contexte.fillStyle = couleur;
  modules.forEach((ligne, y) => ligne.forEach((sombre, x) => {
    if (sombre) {
      contexte.fillRect(decalage + (x + marge) * echelle, decalage + (y + marge) * echelle, echelle, echelle);
    }
  }));
}
