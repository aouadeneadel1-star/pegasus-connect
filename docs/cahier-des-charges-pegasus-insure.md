# Cahier des charges — Plateforme employés Pegasus Insure

| | |
|---|---|
| **Projet** | Plateforme web PWA « Pegasus Connect » — pointage, rondes, RH, portail client |
| **Maître d'ouvrage** | Pegasus Insure — entreprise de sécurité privée |
| **Version** | 1.1 — 28 octobre 2026 · ajout du pointage par badge NFC |
| **Statut** | Document de référence pour consultation et réalisation |
| **Livrables associés** | `maquettes-pegasus-insure.html` (mockups HTML/CSS autonomes) |

## 0. Hypothèses de cadrage retenues

| # | Sujet | Décision / hypothèse | Source |
|---|---|---|---|
| H1 | Hébergement | Mutualisé **o2switch** (datacenter en France, cPanel, SSH, cron, Composer) | Client |
| H2 | Volumétrie | 150 agents, 30 sites, 10 points de contrôle par site, 5 clients donneurs d'ordre ; dimensionnement jusqu'à 500 agents / 100 sites | Hypothèse par défaut |
| H3 | Heures | Constat planifié / pointé **et** calcul des majorations CCN Prévention-sécurité (IDCC 1351) : nuit, dimanche, jours fériés, heures supplémentaires. Les taux sont paramétrables. Le résultat est un **pré-calcul exporté vers la paie**, pas un bulletin | Client |
| H4 | Fiches de paie | Un PDF par salarié produit par le logiciel de paie, matricule présent dans le nom du fichier (repli : nom + prénom) | Hypothèse par défaut |
| H5 | Identité visuelle | Logo fourni (écusson noir/rouge, pégase, logotype « PEGASUS INSURE »). La charte en dérive | Client |
| H6 | Délai | **3 mois maximum** : 12 semaines de production + 1 semaine tampon (fêtes de fin d'année), priorisation MoSCoW | Client |
| H7 | Accès client | Création d'un **5ᵉ rôle « Client »** (donneur d'ordre), en lecture seule sur ses sites | Client |
| H8 | Réalisation | Prestataire externe (équipe de 2 développeurs + chef de projet/UX à temps partiel) | Hypothèse par défaut |
| H9 | Badge NFC | Carte d'entreprise NFC (carte professionnelle remise par l'employeur) utilisable pour pointer sur la borne, **en complément** du QR code. Cartes NFC standard identifiées par leur numéro de série (UID). La protection anti-clonage est hors périmètre V1 | Client |

## Glossaire

| Terme | Définition |
|---|---|
| **Borne de pointage** | Tablette fixe au poste de sécurité d'un site affichant un QR code dynamique (`/borne/{code_site}`) |
| **Badge NFC** | Carte d'entreprise au format carte bancaire, munie d'une puce sans contact, présentée au lecteur de la borne pour pointer |
| **UID** | Numéro de série unique gravé en usine dans la puce d'une carte NFC |
| **Pointage** | Événement d'arrivée ou de départ d'un agent sur un site |
| **Service** | Couple arrivée/départ formant une période de travail réalisée |
| **Vacation** | Créneau de travail planifié (planning) |
| **Point de contrôle** | Emplacement d'un site muni d'un QR code unique, à scanner pendant une ronde |
| **Ronde** | Parcours d'un agent sur une liste de points de contrôle, planifié ou spontané |
| **Passage** | Scan valide d'un point de contrôle |
| **Anomalie** | Événement signalé pendant une ronde ou hors ronde (main courante) |
| **NIR** | Numéro d'inscription au répertoire (numéro de sécurité sociale) |
| **CNAPS** | Conseil national des activités privées de sécurité — délivre la carte professionnelle |
| **Jeton HMAC** | Chaîne signée par une clé secrète serveur, infalsifiable sans cette clé |

---

## 1. Présentation du projet

### 1.1 Contexte

Pegasus Insure emploie des agents de sécurité répartis sur de nombreux sites clients, souvent la nuit, le week-end, et sans accès à un poste informatique. La gestion actuelle repose sur des documents papier, des feuilles de présence, des échanges téléphoniques et des fichiers dispersés. Elle produit :

- une faible traçabilité des présences et des rondes, difficile à opposer à un client ou en cas de litige ;
- un suivi manuel des échéances réglementaires : carte professionnelle CNAPS, SST, SSIAP ;
- une diffusion lourde des fiches de paie, du planning et des consignes ;
- une absence de preuve de passage exploitable par les donneurs d'ordre.

### 1.2 Objectifs

| Objectif | Indicateur de réussite (6 mois après mise en service) |
|---|---|
| Fiabiliser les présences | ≥ 95 % des services pointés par QR code ou badge NFC, < 2 % de corrections manuelles |
| Prouver les rondes | 100 % des sites équipés en points de contrôle, rapports exportables pour chaque client |
| Supprimer le papier RH | 100 % des fiches de paie distribuées en ligne, accusés de lecture des consignes ≥ 90 % |
| Maîtriser la conformité | 0 agent en poste avec carte professionnelle expirée non signalée |
| Réduire la charge de paie | Rapport mensuel et variables de majoration générés en < 10 min pour l'ensemble des agents |
| Valoriser le service client | Portail client actif pour 100 % des clients sous contrat |

### 1.3 Périmètre

**Inclus :**

- PWA agent installable (Android, iOS) : pointage, rondes, planning, documents, paie, contact
- Bornes de pointage web en mode kiosque
- Badges agents NFC (carte d'entreprise portant les mentions de la carte professionnelle) et pointage par badge sur la borne, en complément du QR code
- Back-office RH/administration et chef d'équipe
- Portail client en lecture seule
- Fiche agent complète avec alertes d'expiration
- Calcul des heures et des majorations, rapports PDF mensuels, exports CSV
- Sécurité, journal d'audit, conformité RGPD (documentation incluse)
- Charte graphique, maquettes, recette, mise en production, formation

### 1.4 Exclusions

| Exclu | Commentaire |
|---|---|
| Production des bulletins de paie | Reste dans le logiciel de paie ; la plateforme exporte des variables et diffuse les PDF |
| Applications natives (stores) | La PWA couvre le besoin ; une encapsulation native pourra être étudiée plus tard |
| Notifications push Web | La bibliothèque nécessaire n'est pas dans la liste autorisée. Notifications in-app + e-mail en V1, push en option V2 |
| Protection anti-clonage des badges | Cartes NFC standard identifiées par leur UID. Passage possible en V2 à des puces sécurisées (NTAG 424 DNA, DESFire) sans changer les lecteurs |
| Lecture NFC par le téléphone de l'agent | Non disponible sur iPhone depuis une application web ; le badge est lu uniquement par la borne |
| Géolocalisation continue des agents | Exclue par principe RGPD : position relevée **uniquement au moment d'un scan** |
| Facturation client, devis, gestion commerciale | Hors périmètre |
| Intégration automatique au logiciel de paie (API) | Export CSV seulement ; connecteur en option |
| Découpage d'un PDF de paie global | Option V2 (nécessiterait une bibliothèque d'analyse PDF non listée) |
| Fourniture et télégestion des tablettes et du matériel badge | Préconisations matérielles fournies, achat à la charge de Pegasus Insure |

---

## 2. Utilisateurs et rôles

### 2.1 Profils

| Rôle | Code | Population | Usage principal | Terminal |
|---|---|---|---|---|
| **Agent** | `agent` | ~150 | Pointer, faire ses rondes, consulter planning, paie, documents | Smartphone personnel ou professionnel |
| **Chef d'équipe** | `chef` | ~10 | Superviser son équipe et ses sites, valider/proposer des corrections, suivre les rondes | Smartphone + PC |
| **RH** | `rh` | 2–3 | Dossiers agents, paie, planning, documents, demandes | PC |
| **Administrateur** | `admin` | 1–2 | Paramétrage, sites, bornes, points de contrôle, droits, audit | PC |
| **Client** | `client` | 1–3 par client | Consulter rondes, anomalies, présence et rapports de **ses** sites | PC / smartphone |

Un chef d'équipe est aussi un agent : il pointe et fait des rondes. Son compte porte le rôle `chef` et une fiche agent. Le périmètre « équipe » d'un chef est défini par les équipes qu'il dirige et par les sites dont il est responsable.

### 2.2 Tableau des droits

Légende : **G** = gestion complète · **L** = lecture · **P** = ses propres données · **E** = son équipe / ses sites · **C** = sites de son client · **—** = aucun accès. Un astérisque (*) signale une action paramétrable.

| Module / action | Agent | Chef d'équipe | RH | Admin | Client |
|---|---|---|---|---|---|
| Connexion, 2FA, mot de passe | P | P | P | P | P |
| Fiche agent — identité, contrat | P (L + demande de modif.) | E (L) | G | G | — |
| Fiche agent — **n° de sécurité sociale** | P masqué | — | G (révélation journalisée) | G (révélation journalisée) | — |
| Carte pro CNAPS, SST, qualifications | P (L) | E (L) | G | G | C (L, nom + validité)* — désactivé par défaut |
| Documents personnels | P (L) | — | G | G | — |
| Fiches de paie | P (L, téléchargement) | — | G | G | — |
| Documents de la société | L + accusé | L + accusé, suivi E | G | G | — |
| Planning | P (L) | E (G)* | G | G | — |
| Pointage arrivée/départ | P | P | — | — | — |
| Pointage par badge NFC (sur la borne) | P | P | — | — | — |
| Badges NFC : émission, impression, désactivation, PIN | P (changer son PIN, déclarer une perte) | E (L, déclarer une perte) | G | G | — |
| Suivi des pointages | P (historique) | E (L) | G | G | C (présence anonymisée)* |
| Correction de pointage | — | E (proposition) | G (validation) | G (validation) | — |
| Rondes : exécution, signalement | P | P | — | — | — |
| Suivi des rondes, plan temps réel | — | E | L | G | C (L) |
| Anomalies / main courante | P (créer, L ses signalements) | E (traiter) | L | G | C (L si « visible client ») |
| Points de contrôle, QR codes | — | E (L, réimpression)* | — | G | — |
| Sites, bornes | — | E (L) | L | G | C (L fiche site) |
| Heures et majorations | P (L ses heures) | E (L) | G | G | — |
| Rapports mensuels agents (PDF) | P (L si envoyé) | E (L) | G | G | — |
| Rapports de rondes (PDF/CSV) | — | E | G | G | C |
| Demandes de contact | P (créer, suivre) | E (traiter planning)* | G | G | P (contacter Pegasus) |
| Utilisateurs, rôles, droits | — | — | L | G | — |
| Clients et accès portail | — | — | L | G | — |
| Paramètres (majorations, seuils, anti-fraude) | — | — | L (majorations : G) | G | — |
| Journal d'audit | — | — | L (hors technique) | G | — |
| Tableau de bord | Accueil agent | Équipe | RH | Global | Client |

Les droits sont implémentés par **permissions atomiques** (ex. `agent.nir.reveler`, `pointage.corriger.valider`) associées aux rôles avec une portée (`soi`, `equipe`, `client`, `tout`). L'administrateur peut ajuster l'association rôle ↔ permission sans développement, à l'exception des permissions verrouillées : révélation du NIR, journal d'audit, gestion des rôles.

---

## 3. Spécifications fonctionnelles détaillées

Priorités MoSCoW : **M** = indispensable à la mise en service, **S** = attendu dans les 3 mois, **C** = souhaitable, reportable en V1.1 si le planning l'exige.

### 3.1 Authentification et comptes (M)

| Fonction | Règles |
|---|---|
| Invitation | Création du compte par RH/admin → e-mail d'invitation avec lien à usage unique valable 72 h → choix du mot de passe → activation 2FA si requise |
| Connexion | E-mail + mot de passe. Message d'erreur générique (« identifiants incorrects »), sans révéler si le compte existe |
| Politique de mot de passe | 12 caractères minimum, vérification contre une liste de mots de passe courants (fichier local), pas d'expiration périodique imposée. Changement forcé en cas de compromission |
| 2FA TOTP (pragmarx/google2fa) | **Obligatoire** pour chef, RH, admin et client. **Optionnelle** pour agent, activable par paramètre global. 10 codes de secours à usage unique (stockés hachés) |
| Appareil de confiance | Option « Se souvenir de cet appareil 30 jours » : la 2FA n'est pas redemandée sur ce terminal. Révocable par l'utilisateur et par l'admin |
| Limitation des tentatives | 5 échecs en 15 min par compte → verrouillage 15 min. 20 échecs par IP en 15 min → délai progressif. Notification e-mail à l'utilisateur au verrouillage |
| Sessions | Agent : 12 h glissantes (couvre une vacation), reconnexion rapide. Back-office : 30 min d'inactivité, 8 h maximum. Régénération de l'ID à la connexion et au changement de privilège |
| Mot de passe oublié | Lien à usage unique, validité 30 min, invalidation des sessions existantes après changement |
| Désactivation | Compte désactivé : connexion impossible, données conservées selon les durées RGPD (§ 6.3) |

### 3.2 Fiche agent (M)

#### 3.2.1 Données

| Bloc | Champs | Obligatoire | Visibilité |
|---|---|---|---|
| Identité | Civilité, nom, nom de naissance, prénom, date et lieu de naissance, nationalité | Oui (sauf nom de naissance) | Agent (L), chef (nom/prénom), RH, admin |
| Coordonnées | Adresse, code postal, ville, e-mail, téléphone | Oui | Agent, RH, admin |
| Identifiants | Matricule (unique, saisi ou généré `PGS-0001`) | Oui | Tous rôles internes |
| **N° de sécurité sociale** | 15 chiffres (13 + clé), contrôle de la clé modulo 97 | Oui pour RH | Voir ci-dessous |
| Contrat | Fonction/poste, date d'embauche, date de sortie, type de contrat, temps plein/partiel, heures mensuelles contractuelles, taux horaire de base (facultatif, pour valorisation) | Oui sauf taux | Agent (L), RH, admin |
| Affectations | Sites (avec dates de début/fin, site principal), équipe | Oui | Agent, chef, RH, admin |
| Carte pro CNAPS | Numéro, type/activité, date de délivrance, date d'expiration, scan recto, scan verso | Oui | Agent (L), chef (L), RH, admin |
| Badge NFC | Numéro de badge, UID masqué, statut, date d'émission, photo d'identité, PIN (haché) | Si le site utilise le badge | Agent (L), chef (L), RH, admin |
| SST | N° de certificat, date d'obtention, date de recyclage (échéance), justificatif | Selon poste | Agent (L), chef (L), RH, admin |
| Qualifications | Type (SSIAP 1/2/3, CQP/TFP APS, habilitation électrique, cynophile, autre), intitulé, organisme, date d'obtention, date de validité ou de recyclage, justificatif | Non | Agent (L), chef (L), RH, admin |

#### 3.2.2 Numéro de sécurité sociale

- **Chiffré** en base (libsodium, clé hors base et hors dossier web, cf. § 6.1) et accompagné d'un index aveugle (HMAC) pour garantir l'unicité sans déchiffrer.
- **Masqué** par défaut partout : `1 85 •• •• ••• ••• 42`.
- **Révélation** uniquement par RH ou admin, via un bouton « Afficher » qui exige une ressaisie du mot de passe ou un code TOTP si la dernière authentification forte date de plus de 5 min. Affichage pendant 30 s, sans copie dans les journaux applicatifs.
- **Chaque révélation** crée une entrée d'audit `agent.nir.reveler` (qui, quand, quel agent, IP).
- Jamais présent dans les exports CSV, les rapports PDF, les e-mails ou le portail client.
- L'agent voit son propre NIR masqué (les 2 derniers chiffres seulement) pour vérification.

#### 3.2.3 Alertes d'expiration (M)

- Objets surveillés : carte pro, SST, qualifications ayant une date de validité ou de recyclage.
- Seuils : **J-90, J-30, J-7**, puis **J0 (expiré)**.
- Destinataires : RH et admin (e-mail + tableau de bord), chef d'équipe concerné (tableau de bord), agent (notification in-app + e-mail).
- Un envoi par objet et par seuil : la table `alertes_expiration_envois` empêche les doublons. Le cycle redémarre si la date d'échéance est mise à jour.
- Widget « Échéances » du tableau de bord : listes 7 j / 30 j / 90 j / expirés, triables, exportables en CSV.
- Badge de statut sur la fiche : *Valide* (vert), *< 90 j* (info), *< 30 j* (orange), *Expiré* (rouge).

#### 3.2.4 Demande de modification de profil (S)

L'agent soumet une modification (adresse, téléphone, e-mail, coordonnées bancaires exclues) avec un justificatif facultatif. RH valide ou refuse avec un motif. La fiche n'est mise à jour qu'après validation, et l'historique conserve l'ancienne et la nouvelle valeur.

### 3.3 Espace agent (PWA)

Navigation inférieure, 5 onglets utilisables au pouce : **Accueil · Planning · Pointer (central, surélevé) · Documents · Plus** (profil, paie, historique, contact, société, déconnexion).

| # | Écran | Contenu et règles | Priorité |
|---|---|---|---|
| 1 | Accueil | Statut du jour (« Hors service » / « En service depuis 21:02 sur Site X »), prochaine vacation, bouton **Pointer** pleine largeur, accès « Démarrer une ronde » si en service, ronde planifiée due, alertes (carte pro, documents à lire, nouvelle fiche de paie), file hors ligne en attente | M |
| 2 | Mon profil | Consultation des blocs § 3.2.1, statut des échéances, bouton « Demander une modification », rubrique **Mon badge** (numéro, statut, changer le PIN, déclarer une perte) | M |
| 3 | Mes documents | Liste par catégorie (contrat, avenants, diplômes, carte pro, attestations), aperçu PDF/image, téléchargement via URL temporaire. L'agent ne voit **que** ses documents marqués visibles | M |
| 4 | Mes fiches de paie | Regroupement par année puis par mois, badge « Nouveau », téléchargement PDF, notification à chaque publication | M |
| 5 | Documents de la société | Liste filtrée par ciblage (site, équipe, rôle), badge « À lire », lecture PDF intégrée, bouton **« J'ai lu et pris connaissance »** obligatoire si l'accusé est requis. Une nouvelle version réinitialise l'accusé | M |
| 6 | Mon planning | Vue semaine (par défaut) et mois, site, horaires, poste, consignes du site. Bannière « Planning modifié » après une publication qui concerne l'agent | M |
| 7 | Pointage et rondes | §§ 3.4 et 3.5 | M |
| 8 | Historique et heures | Liste des services du mois (date, site, arrivée, départ, durée, statut), total du mois, comparaison planifié/pointé, ventilation nuit/dimanche/férié à titre indicatif, sélection du mois | M |
| 9 | Contact | Formulaire (catégorie RH, paie, planning, urgence, autre ; objet ; message ; pièce jointe), suivi des demandes et fil de messages, boutons **Appeler** (`tel:`) et **E-mail** (`mailto:`) par service. La catégorie « Urgence » affiche d'abord le numéro de permanence | M |

### 3.4 Pointage de service par QR code et badge NFC (M)

Deux modes coexistent sur chaque site équipé d'une borne : le **scan du QR code** par le téléphone de l'agent (mode principal) et le **badge NFC** présenté au lecteur de la borne (§ 3.4.8). Les deux alimentent les mêmes services, le même rapprochement avec le planning et les mêmes rapports. Le mode badge s'active par site.

#### 3.4.1 Borne de pointage

- Page `/borne/{code_site}` en plein écran, sans navigation, affichant : logo, nom du site, heure (horloge serveur resynchronisée), QR code dynamique, compte à rebours de renouvellement, consigne « Scannez avec l'application Pegasus ».
- **Enrôlement** : l'admin crée la borne et obtient un code d'enrôlement à usage unique (valable 15 min). Il le saisit sur la tablette. Le serveur remet alors à la borne :
  - un **jeton d'appareil** (cookie HttpOnly longue durée, stocké haché en base), qui identifie la borne ;
  - une **clé de borne** dérivée (HMAC de la clé maître et de l'identifiant de borne), stockée sur la tablette.
- **Génération du QR** : toutes les 60 s, la borne calcule localement `PGS1.D.{site}.{borne}.{fenêtre}.{signature}`, où `fenêtre = floor(epoch/60)` et `signature` = HMAC-SHA256 tronqué à 128 bits avec la clé de borne. La génération locale (Web Crypto API) permet à la borne de fonctionner **même sans réseau**. Le serveur, qui connaît la clé maître, vérifie la signature.
- Tolérance : fenêtres `n` et `n-1` acceptées (soit 60 à 120 s de validité).
- **Battement de cœur** toutes les 5 min (`/borne/{code}/battement`). Une borne silencieuse depuis plus de 30 min génère une alerte « Borne hors ligne » à l'admin et au chef d'équipe.
- **Révocation** d'une borne : son jeton et sa clé sont invalidés immédiatement. Le remplacement de la tablette passe par un nouvel enrôlement.
- **Lecture de badge** : si la borne dispose d'un lecteur NFC (§ 3.4.8), l'écran affiche aussi « ou présentez votre badge » et la borne écoute les lectures en permanence. L'état du lecteur remonte avec le battement de cœur.

#### 3.4.2 Repli QR statique (sites sans écran)

- Étiquette imprimée `PGS1.S.{site}.{version}.{signature}`, paramètre `mode_pointage = statique` du site.
- **Géolocalisation obligatoire** : un pointage hors du rayon du site (150 m par défaut, réglable) est enregistré mais marqué **« À vérifier »**.
- Régénération : la version est incrémentée et l'ancienne étiquette devient invalide (action journalisée).

#### 3.4.3 Parcours agent

1. L'agent appuie sur **Pointer**. L'application ouvre la caméra (API `BarcodeDetector` native si disponible, sinon html5-qrcode).
2. Elle lit le QR, récupère la position GPS (si le consentement est donné), et envoie `{jeton, position, précision, uuid_client, horodatage_appareil, id_appareil}`.
3. Le serveur détermine le **type** automatiquement : pas de service ouvert → *arrivée* ; service ouvert sur ce site → *départ*. Si un service est ouvert sur **un autre** site, il propose « Clôturer le service sur Site A et ouvrir Site B ? ». L'identité vient de la session, jamais du QR.
4. Écran de confirmation plein écran : coche verte, type, site, heure serveur, vibration courte. En cas d'anomalie : écran orange « Pointage enregistré — à vérifier » avec la raison.
5. Bouton « Annuler » disponible pendant 2 min pour une erreur de type (ex. départ involontaire). L'annulation est journalisée, sans suppression physique.

#### 3.4.4 Données enregistrées

Agent, site, borne, vacation rapprochée, type, mode (QR dynamique, QR statique, badge NFC, manuel), badge utilisé le cas échéant, **horodatage serveur** (référence), horodatage appareil, indicateur hors ligne, latitude, longitude, précision, distance au site, statut géoloc, appareil, UUID client (idempotence), fenêtre du jeton, statut, indicateurs de fraude.

#### 3.4.5 Rapprochement avec le planning

Le pointage est rattaché à la vacation de l'agent qui chevauche l'instant (± 2 h). Il en découle :

| Indicateur | Règle | Valeur par défaut |
|---|---|---|
| Retard | Arrivée après le début de la vacation + tolérance | Tolérance 5 min |
| Départ anticipé | Départ avant la fin de la vacation − tolérance | Tolérance 5 min |
| Absence | Aucune arrivée 30 min après le début de la vacation | Alerte chef + RH |
| Départ manquant | Service ouvert 2 h après la fin de la vacation, ou ouvert depuis plus de 14 h sans vacation | Service passé en « départ manquant », alerte |
| Hors planning | Pointage sans vacation correspondante | Accepté, marqué « hors planning » |

#### 3.4.6 Anti-fraude

| Contrôle | Comportement |
|---|---|
| Un seul service ouvert | Contrainte en base (index unique conditionnel) + règle métier |
| Doublon | Second scan de même type dans les 5 min → ignoré (réponse idempotente) |
| Jeton expiré ou rejoué | Hors fenêtre → refus « QR expiré, scannez à nouveau » |
| Proximité | Distance (haversine) au site > rayon + précision → « hors zone », à vérifier |
| Précision insuffisante | Précision > 100 m → « imprécise », à vérifier (pas de refus) |
| Vitesse incohérente | Deux événements de l'agent impliquant un déplacement > 150 km/h → à vérifier |
| Appareil partagé | Même appareil utilisé par 2 agents différents en moins de 12 h → alerte chef/RH |
| Géolocalisation refusée | Mode dynamique : accepté, marqué « géoloc refusée ». Mode statique : à vérifier |
| Carte pro expirée (option) | Paramètre `alerte_carte_pro_pointage` : alerte admin/RH sans blocage (par défaut). Option « bloquer » désactivée par défaut |
| Badge prêté à un collègue | PIN à 4 chiffres exigé à la borne (paramétrable par site) ; 5 PIN erronés → badge verrouillé 15 min + alerte chef/RH |
| Badge perdu, volé ou désactivé | Refus immédiat à la borne (« Badge inactif, contactez votre chef d'équipe ») ; alerte si un badge déclaré volé est présenté |
| Badge présenté hors du site d'affectation | Accepté et marqué « hors affectation » (paramétrable : refus) |

Les pointages « à vérifier » apparaissent dans une file dédiée du suivi. Ils sont comptés dans les heures, mais signalés dans le rapport mensuel tant qu'ils ne sont pas validés.

#### 3.4.7 Mode hors ligne partiel (M)

- Le Service Worker met en cache l'application (shell, CSS, JS, icônes, page hors ligne). Les données consultées récemment (planning de la semaine, statut) sont gardées en cache réseau-puis-cache.
- Un scan hors ligne est **vérifié localement** (format du jeton) puis placé dans une file IndexedDB avec son UUID et l'horodatage de l'appareil. L'écran affiche « Pointage enregistré hors ligne, sera transmis ».
- Synchronisation automatique : retour du réseau (`online`), ouverture de l'application, toutes les 60 s tant que la file n'est pas vide. Lot transmis via `/api/v1/sync`.
- Côté serveur, le jeton est vérifié par rapport à **sa propre fenêtre** (la signature reste valide). L'horodatage retenu est celui de l'appareil, borné par la fenêtre du jeton (± 2 min). L'événement est marqué `hors_ligne`. Au-delà de 24 h de retard de synchronisation : marqué « à vérifier ».
- Idempotence par UUID : un renvoi ne crée jamais de doublon.
- Limite : 200 événements en file. L'agent est alerté si sa file a plus de 6 h d'ancienneté.

#### 3.4.8 Pointage par badge NFC (M)

**Principe.** Chaque agent reçoit une **carte d'entreprise NFC** au format carte bancaire (CR80, 85,6 × 54 mm). Depuis la dématérialisation de la carte professionnelle, le CNAPS délivre un numéro et c'est l'employeur qui remet la carte à l'agent. Cette carte porte les mentions exigées : nom, prénoms, date de naissance, numéro de carte professionnelle, activités autorisées et photo récente, complétées par le logo et la raison sociale de Pegasus Insure. La puce ne contient **aucune donnée personnelle** : la plateforme reconnaît la carte par son numéro de série d'usine (UID).

Le badge **complète** le QR code, il ne le remplace pas :

- l'agent équipé d'un smartphone peut continuer à scanner le QR ;
- le badge sert aux agents sans smartphone, en cas de téléphone déchargé ou en panne, et pour les prises de poste rapides lors des relèves.

**Parcours à la borne**

1. L'agent présente son badge sur le lecteur.
2. La borne affiche « Bonjour Karim B. » et demande le **PIN à 4 chiffres** (clavier numérique à l'écran, touches de 64 px, disposition fixe).
3. Le serveur vérifie le badge, le PIN et le site, puis applique la même logique que pour le QR : type déduit du service ouvert, rapprochement avec le planning, indicateurs.
4. Une confirmation s'affiche en plein écran pendant 4 s (« Arrivée enregistrée — 21:02 »), puis la borne revient à son écran d'accueil. Aucune autre donnée de l'agent n'est affichée.
5. Si le PIN est désactivé pour le site, l'étape 2 disparaît et le badgeage prend une seconde.

L'identité vient ici du **couple badge + PIN**. C'est la seule exception au principe « l'identité vient du compte » (§ 3.4.3). Comme la borne est fixe sur le site, aucune géolocalisation n'est demandée.

**Matériel de lecture** (réglé borne par borne)

| Option | Fonctionnement | Compatibilité |
|---|---|---|
| A — Lecteur USB externe | Lecteur NFC 13,56 MHz en émulation clavier : la page borne capte l'UID dans un champ invisible qui garde le focus. Les saisies trop lentes (plus de 150 ms entre deux caractères) sont ignorées, pour ne pas confondre avec une frappe humaine | Toute tablette (Android, iPad, Windows) |
| B — NFC intégré à la tablette | API Web NFC de Chrome Android (`NDEFReader`, lecture du numéro de série). L'écoute démarre au premier toucher d'écran après l'allumage de la borne | Tablettes Android équipées du NFC, Chrome uniquement |

Les lecteurs retenus doivent être compatibles PC/SC et NDEF. Cela permettra de passer plus tard à des cartes anti-clonage sans racheter de matériel.

**Hors ligne.** La borne met l'événement en file (UID, PIN, horodatage, signature HMAC de la borne). Le PIN est conservé chiffré par une clé Web Crypto non exportable, 24 h au maximum, puis transmis et vérifié au retour du réseau. L'écran indique « Badge enregistré, sera vérifié ». Si le PIN est refusé à la synchronisation, le pointage passe « à vérifier » et une alerte est émise.

**Limite assumée en V1.** Une carte NFC standard peut être copiée par une personne équipée. Ce risque est accepté (H9). Il est compensé par le PIN, la journalisation et le contrôle des chefs d'équipe. Le cycle de vie des badges est décrit au § 3.18.

### 3.5 Module rondes (M)

#### 3.5.1 Gestion des points de contrôle (admin)

| Fonction | Règles |
|---|---|
| Création | Par site : nom, description, étage/zone, ordre dans le parcours, photo, coordonnées GPS (saisie ou relevé « ici » depuis le téléphone), rayon de tolérance (défaut 50 m, hérité du site sinon), position sur plan (x %, y %) |
| Identifiant | Court et lisible, ex. `PC-0142`, imprimé sous le QR |
| QR code | `PGS1.R.{point}.{version}.{signature}` — HMAC-SHA256 avec la clé maître des rondes, tronqué à 128 bits. Généré avec endroid/qr-code (niveau de correction Q) |
| Planche d'étiquettes | PDF A4 (Dompdf), 2 × 4 étiquettes de 90 × 60 mm : logo, nom du lieu, zone, QR, identifiant. Impression d'un point, d'une sélection ou d'un site entier. Recommandation : étiquettes vinyle anti-UV ou plaque gravée |
| Régénération | Incrémente `qr_version`. L'ancien QR est **immédiatement invalide** (scan → « QR obsolète », passage refusé, alerte). Motif obligatoire, journalisé |
| Désactivation | Le point sort des parcours ; son historique reste consultable |
| Plan du site | Import d'une image (PNG/JPG/PDF converti, 5 Mo max), placement des points par glisser-déposer, sauvegarde en coordonnées relatives (responsive) |

#### 3.5.2 Planification (modèles de ronde)

| Paramètre | Exemple / défaut |
|---|---|
| Plage horaire | 22:00 → 06:00 (franchissement de minuit géré) |
| Fréquence | Toutes les 120 min → rondes générées à 22:00, 00:00, 02:00, 04:00 |
| Jours | Lun–Dim (cases à cocher) |
| Points et ordre | Sélection et ordre parmi les points actifs du site, chaque point obligatoire ou facultatif |
| Durée maximale | 45 min |
| Tolérance de démarrage | 15 min après l'heure prévue |
| Ordre imposé | Non par défaut. Si oui, un point scanné hors ordre est enregistré mais marqué |
| Temps minimum entre deux points | 30 s par défaut (0 = désactivé) |

Un cron génère les rondes `à venir` 24 h à l'avance. La ronde est **attribuée** à l'agent qui la démarre, parmi les agents en service sur le site.

#### 3.5.3 Déroulement

1. **Démarrer** : l'agent en service choisit la ronde due ou démarre une ronde libre (tous les points actifs du site). Un agent ne peut avoir qu'une ronde en cours.
2. **Scanner** chaque point : retour immédiat (vibration, coche), compteur « 7 / 10 », liste des points (contrôlé, à faire), prochain point attendu si l'ordre est imposé.
3. **Données d'un passage** : agent, point, ronde, horodatage serveur, horodatage appareil, géolocalisation, précision, distance au point, appareil, version du QR, UUID client.
4. **Signaler une anomalie** depuis le point courant ou à tout moment : catégorie, gravité (*Information, Mineure, Majeure, Critique*), description, 0 à 3 photos (compressées côté client à 1600 px, JPEG 80 %), option « visible client » (cochée par défaut, modifiable par le chef ou l'admin). Une gravité **Critique** déclenche une alerte immédiate (e-mail + tableau de bord) et propose d'appeler la permanence.
5. **Clôturer** : récapitulatif (points contrôlés, manqués, durée, anomalies), commentaire facultatif, confirmation. Les points obligatoires non scannés passent « manqués ». Une ronde dépassant la durée maximale est clôturée automatiquement en « incomplète ».
6. **Hors ligne** : même mécanisme de file que le pointage. La progression reste visible localement.

#### 3.5.4 Anti-fraude rondes

| Contrôle | Règle |
|---|---|
| Le QR identifie le point, pas l'agent | L'agent est celui de la session |
| Proximité | Distance téléphone ↔ point > rayon + précision → indicateur `hors_zone` |
| Ordre | Si imposé : indicateur `ordre` |
| Temps minimum | Délai < seuil depuis le passage précédent → `trop_rapide` |
| Scans groupés | ≥ 3 points distincts scannés en < 20 s → `scan_groupe` (signe de photos des QR) |
| Doublon | Même point dans la même ronde en < 2 min → ignoré |
| QR obsolète | Version ≠ version courante → refusé + alerte admin |
| Agent hors service | Une ronde ne peut pas démarrer sans service ouvert sur le site (paramètre, actif par défaut) |

Les passages marqués restent comptés, mais apparaissent en orange dans les vues et en annexe des rapports.

#### 3.5.5 Traçabilité et vues

| Vue | Contenu |
|---|---|
| **Par emplacement** | Dernier passage (agent, date, heure, ancienneté), historique paginé, filtre par période, nombre de passages par jour, export CSV |
| **Par agent** | Rondes effectuées par jour, points contrôlés et manqués, taux de complétude, anomalies signalées |
| **Par ronde** | Chronologie des passages, points manqués, durée, écart à l'heure prévue, anomalies avec photos, indicateurs de fraude |
| **Plan temps réel** | Plan du site avec pastilles : **vert** contrôlé dans la ronde courante, **orange** en retard (échéance dépassée, ronde en cours), **rouge** manqué, **gris** à venir. Légende textuelle (pas seulement la couleur). Rafraîchissement toutes les 20 s. Volet latéral avec la ronde en cours et le flux des passages |

#### 3.5.6 Alertes

| Événement | Destinataires | Canal |
|---|---|---|
| Ronde non démarrée après tolérance | Chef d'équipe, admin | Tableau de bord + e-mail |
| Ronde en retard (durée max approchée à 80 %) | Agent (in-app), chef | In-app, tableau de bord |
| Ronde incomplète / point obligatoire manqué | Chef, admin | Tableau de bord + e-mail récapitulatif |
| Anomalie critique | Chef, admin, permanence | E-mail immédiat + tableau de bord |
| QR obsolète scanné | Admin | Tableau de bord |
| Indicateur de fraude répété (≥ 3 en 7 j pour un agent) | RH, admin | Tableau de bord |

Un e-mail récapitulatif est envoyé chaque matin à 7 h au chef et à l'admin, au lieu d'un e-mail par événement non critique, afin de respecter les quotas d'envoi du mutualisé.

#### 3.5.7 Rapports de rondes

- **Rapport de ronde par site et par période** (PDF et CSV) : synthèse (rondes prévues, réalisées, incomplètes, manquées, taux de conformité), tableau des rondes, détail des passages, anomalies avec miniatures, signature numérique (empreinte SHA-256 en pied de page). Logo Pegasus et nom du client.
- Exportable par l'admin, le chef (ses sites) et le **client** (ses sites).
- **Synthèse mensuelle par agent** intégrée au rapport mensuel (§ 3.10).

### 3.6 Planning (M)

| Fonction | Règles |
|---|---|
| Création de vacation | Agent, site, début, fin (peut franchir minuit), poste, pause non rémunérée, commentaire |
| Vues | Calendrier semaine/mois par site (lignes = agents) et par agent. Glisser-déposer pour déplacer (C), création par clic sur une case vide |
| Conflits détectés | Chevauchement de vacations d'un même agent ; repos quotidien < 11 h ; durée quotidienne > 12 h ; hebdomadaire > 48 h ; moyenne 12 semaines > 46 h ; agent non affecté au site ; carte pro expirée à la date ; qualification requise par le site manquante (ex. SSIAP 1). Les seuils sont paramétrables et reprennent par défaut les plafonds CCN |
| Duplication | Dupliquer une semaine vers 1 à 4 semaines suivantes, avec option d'écrasement ou de fusion |
| Brouillon / publication | Les vacations sont créées en brouillon, puis publiées par période et périmètre (site, équipe). Notification aux agents concernés (in-app + e-mail) avec le résumé des changements |
| Modification après publication | Notification ciblée à l'agent dont la vacation change ou est annulée |
| Import | Import CSV de vacations (C) |
| Export | PDF hebdomadaire par site (affichage au poste), CSV |

### 3.7 Heures et majorations (S)

#### 3.7.1 Calcul des heures

- **Heures pointées** = somme des durées de services (départ − arrivée) − pause planifiée de la vacation rapprochée.
- Arrondi paramétrable : aucun (par défaut), quart d'heure le plus proche, ou arrondi favorable au salarié.
- Les services sont **découpés par segments** pour être ventilés : nuit, dimanche, jour férié. Le calcul se fait en heure légale `Europe/Paris` à partir des horodatages UTC, ce qui gère correctement les changements d'heure.

#### 3.7.2 Règles par défaut (CCN Prévention-sécurité, IDCC 1351) — paramétrables

| Code | Règle | Taux par défaut |
|---|---|---|
| `nuit` | Heures entre 21:00 et 06:00 | +10 % |
| `dimanche` | Heures du dimanche 00:00–24:00 | +10 % |
| `ferie` | Heures d'un jour férié | +100 % |
| `hs25` | Heures supplémentaires : 8 premières au-delà de la durée de référence hebdomadaire | +25 % |
| `hs50` | Heures supplémentaires suivantes | +50 % |

Cumul nuit + dimanche + férié : **cumulable** par défaut, réglable règle par règle.

> ⚠️ **Avertissement** : ces taux proviennent de sources secondaires sur la convention collective et doivent être **validés par le gestionnaire de paie** avant la mise en service, y compris les accords d'entreprise, les modulations ou annualisations éventuelles, le contingent d'heures supplémentaires et la règle de cumul. La plateforme produit un **pré-calcul** ; le bulletin reste établi par le logiciel de paie.

Calendrier des jours fériés : préchargé (France métropolitaine) avec calcul automatique des fêtes mobiles (Pâques, Ascension, Pentecôte), éditable (Alsace-Moselle, DOM).

#### 3.7.3 Sorties

- Tableau mensuel par agent : planifié, pointé, écart, nuit, dimanche, férié, HS 25, HS 50, retards (nombre et minutes), absences.
- **Export CSV « variables de paie »** (une ligne par agent, colonnes configurables, séparateur `;`, encodage UTF-8 avec BOM pour Excel), destiné au logiciel de paie.
- Clôture de période : RH verrouille le mois. Les corrections ultérieures passent alors par une régularisation tracée.

### 3.8 Fiches de paie (M)

1. **Import** : RH sélectionne la période (AAAA-MM) et dépose jusqu'à 500 PDF (glisser-déposer, envoi par lots de 20 fichiers pour respecter les limites d'upload du mutualisé, ou une archive ZIP).
2. **Contrôles** : type MIME `application/pdf` et signature `%PDF`, taille ≤ 5 Mo, PDF non chiffré, doublon d'empreinte SHA-256.
3. **Association automatique** :
   - par **matricule** trouvé dans le nom du fichier (motif paramétrable, ex. `{matricule}_{AAAAMM}.pdf`) ;
   - à défaut, par `NOM_PRENOM` normalisé (sans accents, insensible à la casse).
4. **Écran de contrôle** : tableau `fichier → agent proposé → statut` (✔ associé, ⚠ ambigu, ✖ introuvable, ⧉ doublon, déjà publié). Association manuelle par liste déroulante avec recherche.
5. **Publication** : seuls les fichiers associés sont publiés. Notification in-app + e-mail « Votre fiche de paie de mars 2027 est disponible », **sans pièce jointe ni montant**. Une fiche déjà publiée pour la même période est versionnée (v2) et l'agent est prévenu.
6. **Stockage** hors dossier public, nom aléatoire, accès par URL temporaire de 5 min. Chaque téléchargement est journalisé.
7. **Dépublication** possible (erreur d'association) avec motif, journalisée.

### 3.9 Documents (M)

#### Documents d'un agent (RH/admin)

Dépôt par catégorie (contrat, avenant, diplôme, carte pro, attestation, pièce d'identité, autre), date du document, date de validité facultative (qui alimente les alertes), visibilité par l'agent (oui par défaut, non pour les documents internes RH). Formats : PDF, JPG, PNG, HEIC converti, 10 Mo maximum.

#### Documents de la société

| Fonction | Règles |
|---|---|
| Publication | Titre, catégorie (règlement intérieur, consignes générales, consignes de site, note de service, procédure), fichier PDF, description, accusé de lecture requis (oui/non), date d'effet |
| Ciblage | Tous, ou combinaison de sites, équipes, rôles |
| Versions | Nouvelle version = nouvel accusé requis. Les versions précédentes restent consultables par RH/admin et sont archivées pour les agents |
| Suivi des accusés | Taux par document, liste des lus et non lus avec date, relance groupée des non-lecteurs (e-mail + in-app), export CSV comme preuve |

### 3.10 Rapports PDF mensuels par agent (S)

Contenu (Dompdf, A4 portrait) :

1. En-tête : logo, « Rapport mensuel d'activité — mars 2027 », agent (nom, prénom, matricule, fonction), **n° de carte professionnelle CNAPS et date d'expiration**, sites d'affectation.
2. Synthèse : heures planifiées, heures pointées, écart, heures de nuit, de dimanche, de jours fériés, HS, nombre et cumul des retards, absences, départs manquants, pointages à vérifier.
3. Détail jour par jour : date, site, vacation prévue, arrivée, départ, durée, écart, indicateurs.
4. Synthèse des rondes : rondes réalisées, points contrôlés, taux de complétude, anomalies signalées.
5. Pied : date de génération, version, empreinte SHA-256, mention « document généré automatiquement ».

Génération individuelle immédiate, ou **en masse** via une file traitée par cron (lots de 20, pour rester sous la limite d'exécution PHP du mutualisé), puis archive ZIP téléchargeable. Les rapports sont archivés et versionnés. Envoi à l'agent possible, individuellement ou en masse : il le retrouve alors dans « Mes documents ». Aucun NIR dans le rapport.

### 3.11 Suivi des pointages (admin, RH, chef) (M)

- **Tableau du jour** : compteurs (attendus, présents, en retard, absents, départs manquants, hors planning, à vérifier) et liste en direct par site, rafraîchie toutes les 30 s.
- **Liste filtrable** : agent, site, période, statut, indicateur ; tri ; pagination ; export CSV.
- **Correction manuelle** : ajout d'un pointage oublié, modification d'heure, suppression logique, validation d'un pointage « à vérifier ». **Motif obligatoire** (liste + texte libre). Le chef **propose**, RH/admin **valide** (paramétrable : validation directe par le chef autorisée ou non). Chaque correction conserve l'avant/après et est journalisée.
- La fiche agent affiche l'historique des pointages et des corrections.

### 3.12 Administration des sites, bornes et points (M)

- **Clients** : raison sociale, SIRET, contact, options du portail (§ 3.13).
- **Sites** : client, code (utilisé dans l'URL de borne), nom, adresse, coordonnées GPS (placement sur carte ou saisie), rayon de pointage, mode de pointage, consignes, téléphone du poste, qualifications requises, plan, actif ou non.
- **Bornes** : liste par site, statut (en ligne, hors ligne, révoquée), dernière activité, enrôlement, révocation.
- **Points de contrôle** : § 3.5.1. **Modèles de ronde** : § 3.5.2.
- **Badges** : activation du mode badge et du PIN par site ; type de lecteur par borne ; gestion des badges au § 3.18.

### 3.13 Portail client (S)

Accès par compte nominatif rattaché à un client, avec 2FA obligatoire. Lecture seule, limitée **aux sites du client**.

| Fonction | Contenu |
|---|---|
| Tableau de bord | Par site : agents actuellement en poste (nombre ; prénom et initiale du nom si l'option est activée), dernière ronde, taux de conformité sur 7 et 30 jours, anomalies ouvertes |
| Plan temps réel | Même vue que l'admin, sans nom d'agent si l'option est désactivée |
| Rondes | Historique, détail d'une ronde (passages, points manqués) |
| Anomalies | Celles marquées « visible client », avec photos et statut de traitement |
| Rapports | Rapport de rondes par période (PDF/CSV), rapport mensuel de site |
| Contact | Formulaire vers Pegasus (catégorie « Client »), suivi des demandes |

**Options par client** (désactivées par défaut, au nom de la minimisation RGPD) : afficher les noms des agents ; afficher la validité de la carte professionnelle des agents présents (sans numéro). Aucune donnée RH, de paie ou de géolocalisation brute n'est exposée au client.

### 3.14 Demandes de contact (M)

- File de traitement (RH, admin, chef selon la catégorie) : statuts *Nouvelle → En cours → En attente de l'agent → Résolue → Close*, assignation, priorité, réponses (visibles par le demandeur) et notes internes.
- Délai cible affiché par catégorie : Urgence (traitement immédiat, mais redirection prioritaire vers le téléphone), Paie 48 h, RH 72 h, Planning 24 h.
- Notification au demandeur à chaque réponse. Clôture automatique 7 jours après résolution sans réponse.

### 3.15 Notifications (M)

| Canal | Utilisation |
|---|---|
| In-app | Cloche avec compteur, centre de notifications, toutes les notifications |
| E-mail (PHPMailer, SMTP o2switch) | Invitation, réinitialisation, nouvelle fiche de paie, planning publié ou modifié, échéances, réponses aux demandes, alertes critiques, récapitulatif quotidien |
| Badge PWA | API Badging lorsque le navigateur la supporte |

Les e-mails passent par une **file d'envoi** (`file_emails`) traitée par cron (lots de 50 par passage), avec reprise sur erreur. Le contenu reste générique : jamais de donnée sensible dans le corps du message.

### 3.16 Journal d'audit (M)

Événements journalisés au minimum : connexion réussie ou échouée, verrouillage, activation ou désactivation 2FA, **révélation du NIR**, consultation ou téléchargement de fiche de paie et de document personnel, création, modification et désactivation d'agent, changement de rôle ou de permission, **correction de pointage**, validation d'un pointage à vérifier, **régénération de QR**, enrôlement ou révocation de borne, émission, désactivation ou remplacement de badge, réinitialisation et verrouillage de PIN, publication et dépublication de paie, publication de planning, export de données, génération et envoi de rapport, changement de paramètre, demande d'exercice de droit RGPD.

Consultation filtrable (période, utilisateur, action, objet) et export CSV. Les entrées sont **chaînées par hash** (chaque ligne contient l'empreinte de la précédente) afin de détecter toute altération. Un contrôle d'intégrité quotidien est réalisé par cron.

### 3.17 Tableaux de bord

| Rôle | Widgets |
|---|---|
| Admin / RH | Présence du jour (compteurs + liste), rondes du jour (conformité, retards), anomalies ouvertes par gravité, échéances 7/30/90 j, pointages à vérifier, corrections en attente, bornes hors ligne, demandes ouvertes, accusés de lecture en retard |
| Chef d'équipe | Idem, restreint à son équipe et à ses sites, plus un accès rapide aux plans temps réel |
| Agent | Écran d'accueil (§ 3.3) |
| Client | § 3.13 |

### 3.18 Badges agents — cartes d'entreprise NFC (M)

| Fonction | Règles |
|---|---|
| Émission | RH crée le badge depuis la fiche agent : choix de la photo, aperçu recto/verso, impression (gabarit PDF CR80 pour l'imprimante à cartes, ou export pour un imprimeur), puis **association** en passant la carte sur le lecteur NFC du poste RH, ou sur une borne en mode « association » avec un code admin à usage unique |
| Unicité | Un seul badge actif par agent ; un UID ne peut être associé qu'à un seul badge |
| PIN | Défini par l'agent dans l'application (Profil › Mon badge), ou PIN initial remis par RH et à changer à la première utilisation. 4 chiffres ; suites triviales refusées (0000, 1234, 1111…) ; stocké avec `password_hash` ; réinitialisable par RH. Il est conservé lors d'un remplacement de badge |
| Statuts | Stock → Actif → Perdu, Volé, Désactivé ou Restitué. Désactivation immédiate en un clic par RH ou admin ; l'agent ou son chef peut déclarer une perte depuis l'application, avec effet immédiat |
| Remplacement | Nouveau badge = nouvel UID ; l'ancien reste refusé. Motif journalisé : perte, vol, casse, changement de photo, renouvellement de la carte professionnelle |
| Lien avec la carte pro | Le badge reprend le numéro CNAPS et sa date d'expiration. Un changement de numéro déclenche l'alerte « badge à réimprimer ». Les alertes à J-90, J-30 et J-7 de la carte pro (§ 3.2.3) valent aussi pour le badge |
| Sortie de l'agent | À la date de sortie, le badge passe automatiquement « désactivé », puis « restitué » quand RH enregistre son retour |
| Suivi | Liste des badges (filtre par statut), stock de cartes vierges, nombre de badges émis par mois, export CSV |

**Mise en page de la carte**

| Face | Contenu |
|---|---|
| Recto | Photo, nom, prénoms, date de naissance, fonction, logo et raison sociale Pegasus Insure, bandeau rouge Pegasus |
| Verso | N° de carte professionnelle CNAPS, activités autorisées, date d'expiration, numéro de badge, mention « Propriété de Pegasus Insure — à restituer en fin de contrat », téléphone à appeler en cas de perte |

---

## 4. User stories et critères d'acceptation

Format : **En tant que… je veux… afin de…** Les critères (CA) sont écrits pour être directement transformés en cas de recette (§ 10.4).

### Épopée E1 — Authentification

**US-01 · Activer mon compte** (M)
En tant qu'agent invité, je veux définir mon mot de passe depuis le lien reçu, afin d'accéder à la plateforme.
- CA1 : un lien de plus de 72 h ou déjà utilisé affiche « lien expiré » et propose de contacter RH.
- CA2 : un mot de passe de moins de 12 caractères ou présent dans la liste des mots de passe courants est refusé, avec explication.
- CA3 : après validation, le compte passe en `actif` et l'événement est journalisé.

**US-02 · Me connecter avec la 2FA** (M)
En tant que RH, je veux saisir un code TOTP après mon mot de passe, afin de protéger les données sensibles.
- CA1 : sans 2FA activée, l'utilisateur est redirigé vers l'enrôlement (QR TOTP + 10 codes de secours) avant tout accès.
- CA2 : un code faux 5 fois verrouille le compte 15 min et envoie un e-mail d'avertissement.
- CA3 : « appareil de confiance » coché → pas de code demandé pendant 30 jours sur ce navigateur.

**US-03 · Réinitialiser mon mot de passe** (M)
En tant qu'utilisateur, je veux recevoir un lien de réinitialisation, afin de retrouver l'accès.
- CA1 : la réponse est identique que l'e-mail existe ou non.
- CA2 : le lien expire après 30 min et toutes les sessions sont fermées après le changement.

### Épopée E2 — Fiche agent et conformité

**US-04 · Créer un agent** (M)
En tant que RH, je veux créer une fiche agent complète et envoyer l'invitation, afin de l'intégrer rapidement.
- CA1 : matricule et e-mail uniques ; NIR validé (format et clé) ; message d'erreur par champ.
- CA2 : bouton « Créer et inviter » → e-mail d'invitation en file d'envoi, compte en statut `invite`.
- CA3 : la fiche est enregistrée même sans scan de carte pro, avec un badge « Pièce manquante ».

**US-05 · Importer des agents par CSV** (S)
En tant que RH, je veux importer un fichier CSV d'agents, afin d'initialiser la base.
- CA1 : modèle CSV téléchargeable ; aperçu ligne par ligne avec erreurs avant validation.
- CA2 : les lignes en erreur sont exclues, les autres créées ; rapport d'import téléchargeable.
- CA3 : invitations envoyées seulement si l'option est cochée.

**US-06 · Consulter le NIR** (M)
En tant que RH, je veux afficher le NIR d'un agent, afin de traiter un dossier administratif.
- CA1 : le NIR est masqué par défaut ; « Afficher » demande une réauthentification si la dernière date de plus de 5 min.
- CA2 : le NIR est visible 30 s puis remasqué ; une entrée d'audit `agent.nir.reveler` est créée.
- CA3 : un chef d'équipe ne voit pas le champ et l'appel direct à l'API renvoie 403.

**US-07 · Être alerté des échéances** (M)
En tant qu'admin, je veux être alerté à J-90, J-30 et J-7 de l'expiration des cartes pro, SST et diplômes, afin d'anticiper les renouvellements.
- CA1 : un seul e-mail par objet et par seuil ; la mise à jour de la date d'échéance relance le cycle.
- CA2 : l'agent concerné reçoit aussi une notification.
- CA3 : le widget « Échéances » liste les objets expirés en rouge en tête.

**US-08 · Demander une modification de profil** (S)
En tant qu'agent, je veux signaler mon changement d'adresse, afin que mon dossier soit à jour.
- CA1 : la demande est visible par RH avec l'ancienne et la nouvelle valeur.
- CA2 : la fiche n'est modifiée qu'après validation ; l'agent est notifié du résultat.

### Épopée E3 — Pointage

**US-09 · Pointer mon arrivée** (M)
En tant qu'agent, je veux scanner le QR de la borne, afin d'enregistrer mon arrivée.
- CA1 : du bouton « Pointer » à la confirmation : ≤ 3 interactions, ≤ 2 s en 4G après la lecture du QR.
- CA2 : la confirmation affiche « Arrivée — Site X — 21:02 » (heure serveur).
- CA3 : un QR de plus de 120 s est refusé avec « QR expiré, scannez à nouveau ».
- CA4 : le type (arrivée/départ) est déduit du service ouvert, sans choix manuel.

**US-10 · Pointer mon départ** (M)
En tant qu'agent, je veux scanner à nouveau la borne à la fin de mon service, afin de clôturer mes heures.
- CA1 : le service est clos et sa durée affichée.
- CA2 : un second scan dans les 5 min ne crée pas de nouvel événement.
- CA3 : bouton « Annuler » disponible 2 min, journalisé.

**US-11 · Pointer sans réseau** (M)
En tant qu'agent en sous-sol, je veux que mon pointage soit enregistré hors ligne, afin de ne pas perdre mes heures.
- CA1 : le scan hors ligne affiche « enregistré hors ligne » et un compteur de file sur l'accueil.
- CA2 : au retour du réseau, la file est envoyée sans action de l'agent ; aucun doublon après plusieurs renvois.
- CA3 : l'horodatage retenu est celui de l'appareil, borné par la fenêtre du jeton ; l'événement est marqué `hors_ligne`.

**US-12 · Pointer sur un site sans écran** (M)
En tant qu'agent, je veux scanner l'étiquette QR statique, afin de pointer là où il n'y a pas de borne.
- CA1 : si la position est à plus de rayon + précision du site, le pointage est enregistré « à vérifier ».
- CA2 : une étiquette d'ancienne version est refusée.

**US-13 · Afficher le QR sur la borne** (M)
En tant qu'admin, je veux enrôler une tablette comme borne d'un site, afin qu'elle affiche le QR dynamique.
- CA1 : le code d'enrôlement est à usage unique et valable 15 min.
- CA2 : le QR change toutes les 60 s, même si la tablette perd le réseau.
- CA3 : borne sans battement depuis 30 min → alerte « borne hors ligne ».
- CA4 : une borne révoquée affiche « Borne désactivée » et ses QR sont refusés.

**US-14 · Suivre la présence du jour** (M)
En tant que chef d'équipe, je veux voir qui est présent, en retard ou absent sur mes sites, afin de réagir.
- CA1 : compteurs et liste mis à jour toutes les 30 s.
- CA2 : un agent sans arrivée 30 min après le début de sa vacation passe « absent » et déclenche une alerte.
- CA3 : le chef ne voit que ses sites et son équipe.

**US-15 · Corriger un pointage** (M)
En tant que RH, je veux ajouter un départ oublié avec un motif, afin de fiabiliser les heures.
- CA1 : le motif est obligatoire ; l'avant/après est conservé et journalisé.
- CA2 : une correction proposée par un chef apparaît « en attente » jusqu'à validation RH.
- CA3 : une correction sur un mois clôturé est refusée sauf permission `paie.regularisation`.

**US-16 · Être alerté d'une carte pro expirée au pointage** (C)
En tant qu'admin, je veux être alerté quand un agent dont la carte pro est expirée pointe, afin de régulariser sa situation.
- CA1 : si l'option est active, une alerte est émise et le pointage n'est pas bloqué.

### Épopée E4 — Rondes

**US-17 · Créer des points de contrôle** (M)
En tant qu'admin, je veux créer les points d'un site et imprimer leurs étiquettes, afin d'équiper le site.
- CA1 : chaque point a un identifiant court unique et un QR signé.
- CA2 : la planche PDF A4 comporte 8 étiquettes avec nom, zone, QR et identifiant, lisibles par scan à 30 cm.

**US-18 · Régénérer un QR** (M)
En tant qu'admin, je veux régénérer le QR d'un point dont l'étiquette a été photographiée ou abîmée, afin d'invalider l'ancienne.
- CA1 : un motif est demandé ; l'ancien QR est refusé immédiatement ; l'action est journalisée.

**US-19 · Planifier des rondes** (M)
En tant qu'admin, je veux définir une ronde toutes les 2 h de 22:00 à 06:00, afin d'automatiser le contrôle.
- CA1 : les rondes sont générées pour les 24 h suivantes avec leur heure prévue.
- CA2 : une ronde non démarrée 15 min après l'heure prévue alerte le chef.

**US-20 · Effectuer une ronde** (M)
En tant qu'agent en service, je veux démarrer une ronde et scanner chaque point, afin de prouver mon passage.
- CA1 : chaque scan valide incrémente la progression (« 7 / 10 ») avec retour haptique.
- CA2 : un QR d'un autre site affiche « Point non rattaché à ce site » et n'est pas enregistré.
- CA3 : la ronde est impossible à démarrer sans service ouvert sur le site (paramètre actif).

**US-21 · Signaler une anomalie** (M)
En tant qu'agent, je veux signaler une porte forcée avec une photo, afin d'alerter ma hiérarchie et le client.
- CA1 : photo compressée à 1600 px maximum avant envoi ; 3 photos maximum.
- CA2 : gravité « Critique » → e-mail immédiat au chef et à l'admin, et proposition d'appel à la permanence.
- CA3 : l'anomalie est rattachée au point, à la ronde, à l'agent et horodatée par le serveur.

**US-22 · Clôturer une ronde** (M)
En tant qu'agent, je veux voir un récapitulatif avant de clôturer, afin de vérifier que je n'ai rien oublié.
- CA1 : les points obligatoires non scannés sont listés et passent « manqués » à la clôture.
- CA2 : une ronde dépassant la durée maximale est clôturée automatiquement « incomplète ».

**US-23 · Détecter les scans suspects** (M)
En tant que chef d'équipe, je veux que les scans trop rapides, groupés ou hors zone soient signalés, afin de repérer la fraude.
- CA1 : 3 points scannés en moins de 20 s → indicateur `scan_groupe` visible en orange.
- CA2 : les indicateurs apparaissent dans la vue ronde, la vue agent et en annexe du rapport.

**US-24 · Suivre un site en temps réel** (M)
En tant que chef d'équipe, je veux voir le plan du site avec l'état des points, afin de savoir ce qui a été contrôlé.
- CA1 : pastilles vert/orange/rouge/gris conformes au § 3.5.5, avec libellé textuel au survol et en liste.
- CA2 : rafraîchissement ≤ 20 s sans rechargement de page.

**US-25 · Consulter l'historique d'un emplacement** (M)
En tant qu'admin, je veux voir tous les passages au point « Parking niveau -2 » sur une période, afin de répondre à une réclamation client.
- CA1 : liste agent/date/heure triée, dernier passage en en-tête, export CSV.

**US-26 · Exporter un rapport de rondes** (M)
En tant qu'admin, je veux générer le rapport de rondes d'un site sur le mois, afin de le transmettre au client.
- CA1 : PDF ≤ 30 s pour 1 000 passages ; CSV équivalent.
- CA2 : empreinte SHA-256 imprimée en pied de page.

### Épopée E5 — Planning

**US-27 · Construire le planning** (M)
En tant que RH, je veux créer les vacations d'un site sur une semaine, afin d'organiser la couverture.
- CA1 : un chevauchement pour un même agent est bloquant ; les dépassements de plafonds sont signalés et peuvent être forcés avec un motif.
- CA2 : une vacation qui franchit minuit s'affiche sur les deux jours.

**US-28 · Dupliquer une semaine** (S)
En tant que RH, je veux dupliquer la semaine courante sur les 3 suivantes, afin de gagner du temps.
- CA1 : un aperçu des conflits est affiché avant validation.

**US-29 · Publier le planning** (M)
En tant que RH, je veux publier le planning, afin que les agents en soient informés.
- CA1 : seuls les agents concernés reçoivent une notification in-app et un e-mail.
- CA2 : les brouillons ne sont jamais visibles par les agents.

**US-30 · Consulter mon planning** (M)
En tant qu'agent, je veux voir mes vacations de la semaine et du mois, afin d'organiser ma vie.
- CA1 : le planning de la semaine reste consultable hors ligne (dernière version en cache, avec sa date).

### Épopée E6 — Paie, heures et rapports

**US-31 · Importer les fiches de paie** (M)
En tant que RH, je veux déposer 150 PDF et les voir associés automatiquement, afin de les publier en quelques minutes.
- CA1 : association par matricule dans le nom du fichier, puis par nom-prénom ; les cas ambigus sont listés.
- CA2 : rien n'est publié avant mon clic « Publier » ; seuls les fichiers associés le sont.
- CA3 : l'e-mail de notification ne contient ni pièce jointe ni montant.

**US-32 · Télécharger ma fiche de paie** (M)
En tant qu'agent, je veux télécharger ma fiche de paie, afin de la conserver.
- CA1 : l'URL est valable 5 min et liée à ma session ; une URL réutilisée par un autre compte renvoie 403.
- CA2 : chaque téléchargement est journalisé.

**US-33 · Calculer les heures et majorations** (S)
En tant que RH, je veux obtenir pour chaque agent les heures de nuit, de dimanche, de jours fériés et les HS du mois, afin de préparer la paie.
- CA1 : un service du samedi 20:00 au dimanche 08:00 (ni pause ni férié) donne 12 h dont 9 h de nuit et 8 h de dimanche.
- CA2 : un service pendant le passage à l'heure d'été est compté sur sa durée réelle.
- CA3 : l'export CSV « variables de paie » respecte les colonnes paramétrées.

**US-34 · Générer les rapports mensuels** (S)
En tant que RH, je veux générer les rapports PDF de tous les agents et les télécharger en ZIP, afin de les archiver.
- CA1 : la génération s'exécute en tâche de fond et affiche sa progression ; le ZIP est disponible à la fin.
- CA2 : chaque PDF contient le n° de carte pro et la synthèse des rondes, sans NIR.

**US-35 · Consulter mes heures** (M)
En tant qu'agent, je veux voir mes heures du mois et l'écart avec mon planning, afin de vérifier ma paie.
- CA1 : les totaux de l'écran correspondent à ceux du rapport mensuel.

### Épopée E7 — Documents et communication

**US-36 · Publier une note de service** (M)
En tant que RH, je veux publier une consigne pour les agents d'un site avec accusé de lecture, afin de prouver qu'ils en ont pris connaissance.
- CA1 : seuls les agents ciblés la voient ; badge « À lire » sur leur accueil.
- CA2 : le tableau de suivi indique le taux de lecture ; « Relancer » notifie les non-lecteurs.

**US-37 · Accuser réception** (M)
En tant qu'agent, je veux confirmer la lecture d'un document, afin de respecter mes obligations.
- CA1 : le bouton n'est actif qu'après ouverture du document ; l'accusé enregistre la date, l'heure et la version.

**US-38 · Contacter l'administration** (M)
En tant qu'agent, je veux envoyer une question sur ma paie et suivre la réponse, afin de ne pas avoir à rappeler.
- CA1 : la demande apparaît dans la file RH ; je suis notifié à chaque réponse.
- CA2 : la catégorie « Urgence » affiche en premier le bouton d'appel de la permanence.

**US-39 · Mes documents personnels** (M)
En tant qu'agent, je veux retrouver mon contrat et mes diplômes, afin de les présenter si besoin.
- CA1 : je ne vois que mes documents marqués visibles ; l'accès à l'identifiant d'un autre document renvoie 404.

### Épopée E8 — Portail client

**US-40 · Consulter les rondes de mon site** (S)
En tant que client, je veux consulter les rondes et anomalies de mes sites, afin de contrôler la prestation.
- CA1 : je ne vois que les sites rattachés à mon entreprise ; toute autre URL renvoie 404.
- CA2 : seules les anomalies « visibles client » apparaissent.
- CA3 : les noms d'agents sont masqués sauf si l'option est activée pour mon entreprise.

**US-41 · Télécharger un rapport client** (S)
En tant que client, je veux télécharger le rapport de rondes du mois, afin de l'archiver.
- CA1 : PDF et CSV disponibles ; le téléchargement est journalisé.

### Épopée E9 — Administration, sécurité, RGPD

**US-42 · Gérer les droits** (S)
En tant qu'admin, je veux ajuster les permissions d'un rôle, afin d'adapter l'organisation.
- CA1 : les permissions verrouillées (NIR, audit, rôles) ne sont pas modifiables ; chaque changement est journalisé.

**US-43 · Consulter le journal d'audit** (M)
En tant qu'admin, je veux filtrer le journal par action et par période, afin de répondre à un contrôle.
- CA1 : l'export CSV est possible ; le contrôle d'intégrité signale toute rupture de la chaîne de hash.

**US-44 · Donner mon consentement à la géolocalisation** (M)
En tant qu'agent, je veux être informé de l'usage de ma position et l'accepter ou la refuser, afin de garder la maîtrise de mes données.
- CA1 : l'écran d'information s'affiche à la première connexion ; le choix est modifiable dans le profil.
- CA2 : sans consentement, le pointage dynamique reste possible et le pointage statique est marqué « à vérifier ».

**US-45 · Répondre à une demande d'accès RGPD** (S)
En tant qu'admin, je veux exporter toutes les données d'un agent, afin de répondre à son droit d'accès dans le délai d'un mois.
- CA1 : archive ZIP (JSON + documents) générée, téléchargement journalisé, NIR inclus en clair uniquement dans cet export chiffré par mot de passe.

### Épopée E10 — Badges NFC

**US-46 · Émettre un badge** (M)
En tant que RH, je veux imprimer et associer le badge d'un nouvel agent, afin qu'il puisse pointer dès sa première vacation.
- CA1 : l'aperçu recto/verso contient toutes les mentions obligatoires ; le gabarit PDF est au format CR80.
- CA2 : l'association se fait en passant la carte sur le lecteur ; un UID déjà associé est refusé.
- CA3 : l'éventuel badge actif précédent est désactivé automatiquement ; l'opération est journalisée.

**US-47 · Pointer avec mon badge** (M)
En tant qu'agent, je veux présenter mon badge à la borne et saisir mon PIN, afin de pointer sans sortir mon téléphone.
- CA1 : la confirmation s'affiche en ≤ 2 s après la saisie du PIN.
- CA2 : le type est déduit du service ouvert ; les indicateurs sont les mêmes que pour le QR.
- CA3 : la borne n'affiche que le prénom et l'initiale du nom, puis revient à l'accueil après 4 s.

**US-48 · Choisir mon PIN** (M)
En tant qu'agent, je veux définir et modifier mon PIN dans l'application, afin que personne ne puisse badger à ma place.
- CA1 : 4 chiffres ; les suites triviales sont refusées.
- CA2 : après 5 PIN erronés, le badge est verrouillé 15 min et le chef d'équipe est alerté.

**US-49 · Déclarer la perte de mon badge** (M)
En tant qu'agent, je veux déclarer la perte de mon badge depuis l'application, afin qu'il ne puisse pas être utilisé.
- CA1 : le badge est désactivé immédiatement ; RH est notifié pour en réémettre un.
- CA2 : s'il est présenté ensuite à une borne, il est refusé et une alerte est émise.

**US-50 · Badger sans réseau** (S)
En tant qu'agent, je veux pouvoir badger même si la borne a perdu le réseau, afin de ne pas perdre mes heures.
- CA1 : l'événement est conservé sur la borne et transmis au retour du réseau, sans doublon.
- CA2 : le PIN est vérifié à la synchronisation ; un PIN faux passe le pointage « à vérifier » avec alerte.

---

## 5. Spécifications techniques

### 5.1 Architecture générale

```
 ┌──────────────────────┐   HTTPS    ┌───────────────────────────────────────────────┐
 │ PWA agent (mobile)   │──────────▶│  o2switch — mutualisé (Apache + PHP-FPM 8.2+)    │
 │ HTML/CSS/JS vanilla  │  JSON API  │                                               │
 │ Service Worker       │◀──────────│  public/index.php  (front controller)          │
 │ IndexedDB (file)     │            │     │                                          │
 └──────────────────────┘            │     ▼                                          │
 ┌──────────────────────┐            │  Router → Middlewares → Controllers            │
 │ Borne (tablette)     │──────────▶│  (Auth, CSRF, Rôle, 2FA, Throttle)               │
 │ /borne/{code}        │            │     │                                          │
 │ Web Crypto (HMAC)    │            │     ▼                                          │
 └──────────────────────┘            │  Services métier ─▶ Repositories (PDO)         │
 ┌──────────────────────┐            │     │                       │                  │
 │ Back-office / Client │──────────▶│     ▼                       ▼                  │
 │ navigateur desktop   │  HTML SSR  │  Views PHP (échappées)   MariaDB (InnoDB)       │
 └──────────────────────┘            │                                               │
                                     │  ~/pegasus/storage (hors web) : fichiers, logs │
                                     │  cron (5 min / quotidien) : bin/cron.php       │
                                     │  SMTP o2switch (PHPMailer, file d'envoi)       │
                                     └───────────────────────────────────────────────┘
```

**Principes :**

- **MVC simple sans framework**, PSR-4 (autoload Composer), PSR-12 pour le style de code.
- Rendu serveur (SSR) des pages HTML. **API JSON** `/api/v1` pour les interactions dynamiques : pointage, ronde, synchronisation, temps réel, planning.
- **JavaScript vanilla en modules ES**, sans étape de build obligatoire. Une minification facultative peut être faite en local avant déploiement.
- **Temps réel par polling** (20–30 s). Les WebSockets et les processus longs ne sont pas disponibles sur un mutualisé, et le SSE monopolise un processus PHP : il est exclu.
- Toutes les dates sont stockées en **UTC** (`DATETIME`). La conversion en `Europe/Paris` se fait à l'affichage et dans les calculs d'heures.

### 5.2 Contraintes d'hébergement o2switch (mutualisé)

| Sujet | Disposition retenue |
|---|---|
| Version PHP | Sélection de PHP ≥ 8.2 dans cPanel (sélecteur de version). Extensions requises : `pdo_mysql`, `sodium`, `openssl`, `mbstring`, `intl`, `gd` ou `imagick`, `zip`, `fileinfo`, `curl` |
| Racine web | Sous-domaine (ex. `app.pegasus-insure.fr`) dont le *document root* pointe sur `~/pegasus/public`. Le code, `vendor/`, `.env` et `storage/` sont **hors** de la racine web |
| Déploiement | SSH + Git (clone depuis un dépôt privé) + `composer install --no-dev --optimize-autoloader`. Script `bin/deploy.sh` : mise en maintenance → pull → composer → migrations → purge du cache → fin de maintenance |
| Cron | Tâches cPanel : `*/5 * * * * php ~/pegasus/bin/cron.php frequent` et `0 2 * * * php ~/pegasus/bin/cron.php quotidien` |
| Limites d'exécution | Traitements lourds (PDF en masse, ZIP, imports) **découpés en lots** et exécutés par cron, avec suivi de progression en base |
| Upload | `upload_max_filesize` / `post_max_size` réglés à 20 Mo via `.user.ini` ; envois par lots côté client |
| E-mail | SMTP authentifié o2switch (domaine expéditeur avec SPF, DKIM, DMARC configurés). Quotas horaires d'envoi à **vérifier auprès d'o2switch** → file d'envoi avec lissage |
| Base de données | MariaDB, un utilisateur SQL dédié. Fichiers stockés sur disque, jamais en BLOB, pour garder la base légère |
| Sauvegardes | Sauvegardes o2switch (JetBackup) **+** `mysqldump` quotidien chiffré dans `storage/backups` (rétention 14 j) **+** copie hebdomadaire chiffrée hors site (stockage objet UE). Test de restauration trimestriel |
| HTTPS | Certificat Let's Encrypt (AutoSSL cPanel), redirection 301 forcée, HSTS |
| Montée en charge | Le mutualisé suffit jusqu'à ~500 agents. Au-delà, ou si les quotas d'e-mail ou de CPU deviennent limitants, migration vers un VPS UE sans modification du code |

### 5.3 Arborescence

```
pegasus/
├── app/
│   ├── Core/                    # Noyau maison
│   │   ├── App.php              # Bootstrap, gestion d'erreurs, en-têtes de sécurité
│   │   ├── Router.php           # Routes GET/POST, paramètres {id}, groupes, middlewares
│   │   ├── Request.php  Response.php  JsonResponse.php
│   │   ├── View.php             # Rendu + helper e() d'échappement
│   │   ├── Database.php         # PDO singleton, ERRMODE_EXCEPTION, EMULATE_PREPARES=false
│   │   ├── Session.php  Csrf.php  Validator.php  Config.php  Env.php
│   │   └── Middleware/          # Auth, RequireRole, RequirePermission, Require2fa,
│   │                            # Csrf, Throttle, BorneAuth, MaintenanceMode
│   ├── Controllers/
│   │   ├── AuthController.php  FichierController.php  BorneController.php
│   │   ├── Agent/               # Accueil, Planning, Pointage, Ronde, Documents, Paie,
│   │   │                        # Historique, Profil, Societe, Contact, Notifications
│   │   ├── Admin/               # Dashboard, Agents, Qualifications, Documents, Paie,
│   │   │                        # Planning, Pointages, Corrections, Rondes, Anomalies,
│   │   │                        # Rapports, SocieteDocs, Sites, Bornes, Points,
│   │   │                        # ModelesRonde, Clients, Utilisateurs, Roles,
│   │   │                        # Parametres, Majorations, Demandes, Audit, Rgpd
│   │   ├── Client/              # Dashboard, Sites, Rondes, Anomalies, Rapports, Contact
│   │   └── Api/                 # EtatApi, PointageApi, RondeApi, SyncApi,
│   │                            # TempsReelApi, PlanningApi, NotificationApi
│   ├── Models/                  # Entités (objets simples typés)
│   ├── Repositories/            # Accès données (une classe par agrégat)
│   ├── Services/
│   │   ├── Auth/                # PasswordService, TotpService, AppareilConfiance
│   │   ├── Crypto/              # Chiffrement (sodium), IndexAveugle, CleManager
│   │   ├── Qr/                  # JetonService (HMAC), QrImageService (endroid)
│   │   ├── Pointage/            # PointageService, BadgeService, RapprochementPlanning, AntiFraude
│   │   ├── Rondes/              # RondeService, PlanificateurRondes, AntiFraudeRonde
│   │   ├── Heures/              # CalculHeures, Segmenteur, JoursFeries, ExportPaie
│   │   ├── Paie/                # ImportPaie, AssociationFichiers
│   │   ├── Planning/            # ConflitsPlanning, Publication
│   │   ├── Pdf/                 # RapportMensuel, RapportRondes, Etiquettes, CarteBadge (Dompdf)
│   │   ├── Stockage/            # FichierService, UrlSignee, ValidationUpload
│   │   ├── Mail/                # MailQueue, Mailer (PHPMailer), Templates
│   │   ├── Notification/        # NotificationService
│   │   ├── Audit/               # AuditLogger (chaînage hash), VerificationIntegrite
│   │   └── Rgpd/                # ExportDonnees, Purge
│   ├── Jobs/                    # Tâches cron (une classe par tâche)
│   ├── Views/
│   │   ├── layouts/ (agent.php, admin.php, client.php, borne.php, auth.php)
│   │   ├── agent/ admin/ client/ borne/ auth/ partials/
│   │   ├── emails/ (*.html.php, *.txt.php)
│   │   └── pdf/ (rapport-mensuel.php, rapport-rondes.php, etiquettes.php, carte-badge.php)
│   └── config/
│       ├── routes.php  permissions.php  parametres.defaut.php  app.php
├── bin/
│   ├── cron.php  migrate.php  seed.php  deploy.sh  creer-admin.php  verifier-audit.php
├── database/
│   ├── migrations/              # 001_roles.sql, 002_utilisateurs.sql, …
│   └── seeds/                   # rôles, permissions, types de qualification, jours fériés
├── public/                      # SEULE racine web
│   ├── index.php  .htaccess  .user.ini  robots.txt
│   ├── manifest.webmanifest  sw.js  offline.html
│   ├── assets/
│   │   ├── css/ (tokens.css, base.css, components.css, agent.css, admin.css, borne.css)
│   │   ├── js/  (app.js, api.js, scanner.js, offline-queue.js, pointage.js, ronde.js,
│   │   │         planning.js, temps-reel.js, borne.js, borne-badge.js, ui.js)
│   │   ├── vendor/html5-qrcode.min.js
│   │   ├── img/ (logo.svg, logo-blanc.svg, embleme.svg)
│   │   └── icons/ (icon-192.png, icon-512.png, maskable-512.png, apple-touch-icon.png)
├── storage/                     # Hors web, chmod 700, aucun script exécutable
│   ├── fichiers/{aa}/{bb}/{uuid}   # documents, paie, photos, plans (noms aléatoires)
│   ├── rapports/  exports/  imports/  tmp/
│   ├── backups/  logs/  sessions/  cache/
├── tests/ (Unit/, Integration/, fixtures/)
├── vendor/
├── .env                         # Secrets (clés, BDD, SMTP) — chmod 600, jamais versionné
├── .env.example  composer.json  composer.lock  README.md
```

### 5.4 Schéma de base de données

Conventions : InnoDB, `utf8mb4_unicode_ci`, clés primaires `INT UNSIGNED` (ou `BIGINT` pour les tables volumineuses), dates en UTC, suppression logique pour les données métier, clés étrangères `ON DELETE RESTRICT` sauf mention contraire.

#### 5.4.1 Modèle conceptuel (résumé)

```
clients 1─n sites 1─n bornes
                 1─n points_controle n─n rondes_modeles (rondes_modeles_points)
                 1─n rondes 1─n passages_ronde n─1 points_controle
                 1─n anomalies n─1 rondes (0..1)
utilisateurs 1─1 agents 1─n cartes_pro | qualifications | documents | fiches_paie
                        n─n sites (agent_sites) | n─1 equipes
                        1─n vacations 1─n pointages ─▶ services
                        1─n rapports_mensuels
                        1─n badges (1 actif) ─▶ pointages (mode badge_nfc)
utilisateurs 1─n demandes 1─n demandes_messages
roles 1─n utilisateurs ; roles n─n permissions
documents_societe 1─n versions 1─n accuses_lecture
audit (toutes actions)
```

#### 5.4.2 Script DDL

```sql
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================================================
-- RÔLES, PERMISSIONS, COMPTES
-- =========================================================
CREATE TABLE roles (
  id            TINYINT UNSIGNED PRIMARY KEY,
  code          VARCHAR(20)  NOT NULL UNIQUE,      -- agent, chef, rh, admin, client
  libelle       VARCHAR(50)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(60)  NOT NULL UNIQUE,      -- ex. agent.nir.reveler
  module        VARCHAR(30)  NOT NULL,
  libelle       VARCHAR(150) NOT NULL,
  verrouillee   TINYINT(1)   NOT NULL DEFAULT 0     -- non modifiable via l'UI
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id       TINYINT UNSIGNED  NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  portee        ENUM('soi','equipe','client','tout') NOT NULL DEFAULT 'tout',
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE clients (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raison_sociale   VARCHAR(150) NOT NULL,
  siret            CHAR(14)     NULL,
  contact_nom      VARCHAR(120) NULL,
  contact_email    VARCHAR(190) NULL,
  contact_tel      VARCHAR(20)  NULL,
  options_portail  JSON         NULL,   -- {"noms_agents":false,"validite_carte_pro":false}
  actif            TINYINT(1)   NOT NULL DEFAULT 1,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_clients_actif (actif)
) ENGINE=InnoDB;

CREATE TABLE utilisateurs (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id              TINYINT UNSIGNED NOT NULL,
  client_id            INT UNSIGNED NULL,               -- rôle client uniquement
  email                VARCHAR(190) NOT NULL,
  password_hash        VARCHAR(255) NULL,               -- NULL tant que l'invitation n'est pas acceptée
  totp_secret_chiffre  VARBINARY(255) NULL,
  totp_actif           TINYINT(1)   NOT NULL DEFAULT 0,
  codes_secours        JSON         NULL,               -- tableau de hashes
  statut               ENUM('invite','actif','suspendu','desactive') NOT NULL DEFAULT 'invite',
  echecs_connexion     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  verrouille_jusqu_a   DATETIME     NULL,
  mdp_modifie_le       DATETIME     NULL,
  auth_forte_le        DATETIME     NULL,               -- dernière réauthentification
  derniere_connexion   DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_util_email (email),
  INDEX idx_util_role (role_id),
  INDEX idx_util_client (client_id),
  CONSTRAINT fk_util_role   FOREIGN KEY (role_id)   REFERENCES roles(id),
  CONSTRAINT fk_util_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE jetons_compte (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NOT NULL,
  type            ENUM('invitation','reinitialisation','appareil_confiance','enrolement_borne') NOT NULL,
  jeton_hash      CHAR(64)     NOT NULL,               -- SHA-256 du jeton envoyé
  expire_le       DATETIME     NOT NULL,
  utilise_le      DATETIME     NULL,
  meta            JSON         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_jeton (jeton_hash),
  INDEX idx_jeton_util (utilisateur_id, type),
  CONSTRAINT fk_jeton_util FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tentatives_connexion (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_hash  CHAR(64)       NOT NULL,
  ip          VARBINARY(16)  NOT NULL,
  succes      TINYINT(1)     NOT NULL,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tc_email (email_hash, created_at),
  INDEX idx_tc_ip (ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE consentements (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NOT NULL,
  type            ENUM('geolocalisation','information_salarie','charte_utilisation') NOT NULL,
  version_texte   VARCHAR(20)  NOT NULL,
  accorde         TINYINT(1)   NOT NULL,
  ip              VARBINARY(16) NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_consent (utilisateur_id, type, created_at),
  CONSTRAINT fk_consent_util FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appareils (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appareil_uuid CHAR(36)     NOT NULL UNIQUE,          -- généré côté client (localStorage)
  user_agent    VARCHAR(255) NULL,
  plateforme    VARCHAR(40)  NULL,
  premier_vu    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dernier_vu    DATETIME     NULL
) ENGINE=InnoDB;

CREATE TABLE appareils_utilisateurs (
  appareil_id     INT UNSIGNED NOT NULL,
  utilisateur_id  INT UNSIGNED NOT NULL,
  premier_vu      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dernier_vu      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (appareil_id, utilisateur_id),
  INDEX idx_au_util (utilisateur_id),
  CONSTRAINT fk_au_app  FOREIGN KEY (appareil_id)    REFERENCES appareils(id) ON DELETE CASCADE,
  CONSTRAINT fk_au_util FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- SITES, BORNES, ÉQUIPES
-- =========================================================
CREATE TABLE sites (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id             INT UNSIGNED NOT NULL,
  code                  VARCHAR(20)  NOT NULL,          -- slug utilisé dans /borne/{code}
  nom                   VARCHAR(120) NOT NULL,
  adresse               VARCHAR(255) NOT NULL,
  code_postal           VARCHAR(10)  NOT NULL,
  ville                 VARCHAR(80)  NOT NULL,
  latitude              DECIMAL(9,6) NOT NULL,
  longitude             DECIMAL(9,6) NOT NULL,
  rayon_metres          SMALLINT UNSIGNED NOT NULL DEFAULT 150,
  mode_pointage         ENUM('dynamique','statique') NOT NULL DEFAULT 'dynamique',
  badge_nfc_actif       TINYINT(1)   NOT NULL DEFAULT 0,  -- pointage par badge autorisé
  badge_pin_requis      TINYINT(1)   NOT NULL DEFAULT 1,
  qr_statique_version   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  plan_document_id      BIGINT UNSIGNED NULL,
  telephone_poste       VARCHAR(20)  NULL,
  consignes             TEXT         NULL,
  qualifications_requises JSON       NULL,              -- [id type_qualification]
  actif                 TINYINT(1)   NOT NULL DEFAULT 1,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sites_code (code),
  INDEX idx_sites_client (client_id, actif),
  CONSTRAINT fk_sites_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE bornes (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id              INT UNSIGNED NOT NULL,
  libelle              VARCHAR(80)  NOT NULL,           -- ex. « Poste PC sécurité »
  appareil_jeton_hash  CHAR(64)     NULL,               -- cookie d'appareil haché
  cle_version          SMALLINT UNSIGNED NOT NULL DEFAULT 1,  -- rotation de la clé dérivée
  lecteur_nfc          ENUM('aucun','usb','web_nfc') NOT NULL DEFAULT 'aucun',
  lecteur_nfc_ok       TINYINT(1)   NULL,                -- état remonté par le battement de cœur
  statut               ENUM('en_attente','active','revoquee') NOT NULL DEFAULT 'en_attente',
  derniere_activite    DATETIME     NULL,
  derniere_ip          VARBINARY(16) NULL,
  user_agent           VARCHAR(255) NULL,
  enrolee_le           DATETIME     NULL,
  enrolee_par          INT UNSIGNED NULL,
  revoquee_le          DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_borne_jeton (appareil_jeton_hash),
  INDEX idx_bornes_site (site_id, statut),
  CONSTRAINT fk_bornes_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE equipes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(80)  NOT NULL UNIQUE,
  chef_agent_id INT UNSIGNED NULL,                      -- FK ajoutée après création d'agents
  actif         TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- =========================================================
-- AGENTS ET CONFORMITÉ
-- =========================================================
CREATE TABLE agents (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id           INT UNSIGNED NOT NULL,
  matricule                VARCHAR(20)  NOT NULL,
  civilite                 ENUM('M','Mme') NULL,
  nom                      VARCHAR(80)  NOT NULL,
  nom_naissance            VARCHAR(80)  NULL,
  prenom                   VARCHAR(80)  NOT NULL,
  date_naissance           DATE         NOT NULL,
  lieu_naissance           VARCHAR(120) NOT NULL,
  nationalite              VARCHAR(60)  NOT NULL,
  adresse                  VARCHAR(255) NOT NULL,
  code_postal              VARCHAR(10)  NOT NULL,
  ville                    VARCHAR(80)  NOT NULL,
  pays                     CHAR(2)      NOT NULL DEFAULT 'FR',
  telephone                VARCHAR(20)  NOT NULL,
  nir_chiffre              VARBINARY(255) NULL,          -- nonce + ciphertext (sodium secretbox)
  nir_index                CHAR(64)     NULL,            -- HMAC-SHA256 (index aveugle)
  nir_fin                  CHAR(2)      NULL,            -- 2 derniers chiffres pour l'affichage masqué
  fonction                 VARCHAR(80)  NOT NULL,        -- ADS, chef de poste, SSIAP 1…
  date_embauche            DATE         NOT NULL,
  date_sortie              DATE         NULL,
  type_contrat             ENUM('CDI','CDD','interim','apprentissage','autre') NOT NULL,
  temps_travail            ENUM('plein','partiel') NOT NULL DEFAULT 'plein',
  heures_mensuelles        DECIMAL(6,2) NOT NULL DEFAULT 151.67,
  taux_horaire_base        DECIMAL(8,4) NULL,
  equipe_id                INT UNSIGNED NULL,
  photo_document_id        BIGINT UNSIGNED NULL,
  created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_agents_util (utilisateur_id),
  UNIQUE KEY uq_agents_matricule (matricule),
  UNIQUE KEY uq_agents_nir (nir_index),
  INDEX idx_agents_nom (nom, prenom),
  INDEX idx_agents_equipe (equipe_id),
  CONSTRAINT fk_agents_util   FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
  CONSTRAINT fk_agents_equipe FOREIGN KEY (equipe_id)      REFERENCES equipes(id)
) ENGINE=InnoDB;

ALTER TABLE equipes ADD CONSTRAINT fk_equipes_chef
  FOREIGN KEY (chef_agent_id) REFERENCES agents(id) ON DELETE SET NULL;

CREATE TABLE agent_sites (
  agent_id     INT UNSIGNED NOT NULL,
  site_id      INT UNSIGNED NOT NULL,
  date_debut   DATE         NOT NULL,
  date_fin     DATE         NULL,
  principal    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (agent_id, site_id, date_debut),
  INDEX idx_as_site (site_id, date_fin),
  CONSTRAINT fk_as_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_as_site  FOREIGN KEY (site_id)  REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE sites_responsables (            -- périmètre des chefs d'équipe par site
  site_id   INT UNSIGNED NOT NULL,
  agent_id  INT UNSIGNED NOT NULL,           -- agent ayant le rôle chef
  PRIMARY KEY (site_id, agent_id),
  CONSTRAINT fk_sr_site  FOREIGN KEY (site_id)  REFERENCES sites(id) ON DELETE CASCADE,
  CONSTRAINT fk_sr_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE documents (                      -- registre unique de tous les fichiers
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id         INT UNSIGNED NULL,
  categorie        ENUM('contrat','avenant','diplome','carte_pro','attestation','identite',
                        'paie','rapport_mensuel','photo_anomalie','photo_point','plan_site',
                        'societe','photo_agent','autre') NOT NULL,
  libelle          VARCHAR(150) NOT NULL,
  nom_original     VARCHAR(255) NOT NULL,
  chemin           VARCHAR(255) NOT NULL,      -- relatif à storage/fichiers, nom aléatoire
  mime             VARCHAR(100) NOT NULL,
  taille_octets    INT UNSIGNED NOT NULL,
  sha256           CHAR(64)     NOT NULL,
  date_document    DATE         NULL,
  date_validite    DATE         NULL,
  visible_agent    TINYINT(1)   NOT NULL DEFAULT 1,
  depose_par       INT UNSIGNED NULL,
  supprime_le      DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_doc_chemin (chemin),
  INDEX idx_doc_agent (agent_id, categorie, supprime_le),
  INDEX idx_doc_validite (date_validite),
  INDEX idx_doc_sha (sha256),
  CONSTRAINT fk_doc_agent FOREIGN KEY (agent_id)   REFERENCES agents(id),
  CONSTRAINT fk_doc_util  FOREIGN KEY (depose_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

ALTER TABLE sites  ADD CONSTRAINT fk_sites_plan   FOREIGN KEY (plan_document_id)  REFERENCES documents(id);
ALTER TABLE agents ADD CONSTRAINT fk_agents_photo FOREIGN KEY (photo_document_id) REFERENCES documents(id);

CREATE TABLE cartes_pro (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id           INT UNSIGNED NOT NULL,
  numero             VARCHAR(40)  NOT NULL,        -- ex. CAR-0XX-20XX-XX-XX-XXXXXXXXXXX
  type_activite      VARCHAR(100) NOT NULL,        -- surveillance humaine, cynophile…
  date_delivrance    DATE         NOT NULL,
  date_expiration    DATE         NOT NULL,
  recto_document_id  BIGINT UNSIGNED NULL,
  verso_document_id  BIGINT UNSIGNED NULL,
  courante           TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cp_agent (agent_id, courante),
  INDEX idx_cp_expiration (date_expiration),
  CONSTRAINT fk_cp_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_cp_recto FOREIGN KEY (recto_document_id) REFERENCES documents(id),
  CONSTRAINT fk_cp_verso FOREIGN KEY (verso_document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE types_qualification (
  id              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(30)  NOT NULL UNIQUE,   -- SST, SSIAP1, SSIAP2, SSIAP3, CQP_APS, H0B0…
  libelle         VARCHAR(120) NOT NULL,
  categorie       ENUM('secourisme','incendie','surveillance','electrique','autre') NOT NULL,
  validite_mois   SMALLINT UNSIGNED NULL,          -- durée par défaut avant recyclage
  actif           TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE qualifications (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id                INT UNSIGNED NOT NULL,
  type_qualification_id   SMALLINT UNSIGNED NOT NULL,
  intitule                VARCHAR(150) NULL,        -- précision libre
  numero                  VARCHAR(60)  NULL,        -- ex. n° certificat SST
  organisme               VARCHAR(150) NULL,
  date_obtention          DATE         NOT NULL,
  date_echeance           DATE         NULL,        -- validité ou recyclage
  document_id             BIGINT UNSIGNED NULL,
  created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_q_agent (agent_id),
  INDEX idx_q_echeance (date_echeance),
  CONSTRAINT fk_q_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_q_type  FOREIGN KEY (type_qualification_id) REFERENCES types_qualification(id),
  CONSTRAINT fk_q_doc   FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE alertes_expiration_envois (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  objet_type     ENUM('carte_pro','qualification','document') NOT NULL,
  objet_id       BIGINT UNSIGNED NOT NULL,
  date_echeance  DATE         NOT NULL,
  seuil_jours    SMALLINT     NOT NULL,             -- 90, 30, 7, 0
  envoye_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alerte (objet_type, objet_id, date_echeance, seuil_jours)
) ENGINE=InnoDB;

CREATE TABLE demandes_modification_profil (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id         INT UNSIGNED NOT NULL,
  changements      JSON         NOT NULL,           -- {"adresse":{"avant":"…","apres":"…"}}
  document_id      BIGINT UNSIGNED NULL,
  statut           ENUM('en_attente','validee','refusee') NOT NULL DEFAULT 'en_attente',
  motif_refus      VARCHAR(255) NULL,
  traite_par       INT UNSIGNED NULL,
  traite_le        DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dmp_statut (statut, created_at),
  CONSTRAINT fk_dmp_agent FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB;

-- =========================================================
-- DOCUMENTS DE LA SOCIÉTÉ
-- =========================================================
CREATE TABLE documents_societe (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titre           VARCHAR(150) NOT NULL,
  categorie       ENUM('reglement','consignes_generales','consignes_site','note_service','procedure','autre') NOT NULL,
  description     TEXT         NULL,
  accuse_requis   TINYINT(1)   NOT NULL DEFAULT 1,
  ciblage_global  TINYINT(1)   NOT NULL DEFAULT 1,  -- 1 = tous
  statut          ENUM('brouillon','publie','archive') NOT NULL DEFAULT 'brouillon',
  version_courante_id INT UNSIGNED NULL,
  created_by      INT UNSIGNED NOT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ds_statut (statut, categorie)
) ENGINE=InnoDB;

CREATE TABLE documents_societe_versions (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_societe_id  INT UNSIGNED NOT NULL,
  version              SMALLINT UNSIGNED NOT NULL,
  document_id          BIGINT UNSIGNED NOT NULL,
  note_version         VARCHAR(255) NULL,
  date_effet           DATE         NULL,
  publiee_le           DATETIME     NULL,
  publiee_par          INT UNSIGNED NULL,
  UNIQUE KEY uq_dsv (document_societe_id, version),
  CONSTRAINT fk_dsv_ds  FOREIGN KEY (document_societe_id) REFERENCES documents_societe(id),
  CONSTRAINT fk_dsv_doc FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE documents_societe_cibles (
  document_societe_id  INT UNSIGNED NOT NULL,
  cible_type           ENUM('site','equipe','role') NOT NULL,
  cible_id             INT UNSIGNED NOT NULL,
  PRIMARY KEY (document_societe_id, cible_type, cible_id),
  CONSTRAINT fk_dsc_ds FOREIGN KEY (document_societe_id) REFERENCES documents_societe(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE accuses_lecture (
  version_id      INT UNSIGNED NOT NULL,
  utilisateur_id  INT UNSIGNED NOT NULL,
  ouvert_le       DATETIME     NULL,
  accuse_le       DATETIME     NULL,
  ip              VARBINARY(16) NULL,
  PRIMARY KEY (version_id, utilisateur_id),
  INDEX idx_al_util (utilisateur_id),
  CONSTRAINT fk_al_v FOREIGN KEY (version_id)     REFERENCES documents_societe_versions(id),
  CONSTRAINT fk_al_u FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- =========================================================
-- PAIE
-- =========================================================
CREATE TABLE imports_paie (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  annee           SMALLINT UNSIGNED NOT NULL,
  mois            TINYINT UNSIGNED  NOT NULL,
  nb_fichiers     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_associes     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_erreurs      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  statut          ENUM('analyse','a_valider','publie','annule') NOT NULL DEFAULT 'analyse',
  cree_par        INT UNSIGNED NOT NULL,
  publie_par      INT UNSIGNED NULL,
  publie_le       DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_periode (annee, mois)
) ENGINE=InnoDB;

CREATE TABLE imports_paie_lignes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_id     INT UNSIGNED NOT NULL,
  nom_fichier   VARCHAR(255) NOT NULL,
  document_id   BIGINT UNSIGNED NULL,
  agent_id      INT UNSIGNED NULL,
  methode       ENUM('matricule','nom','manuel') NULL,
  statut        ENUM('associe','ambigu','introuvable','doublon','invalide','deja_publie') NOT NULL,
  message       VARCHAR(255) NULL,
  INDEX idx_ipl_import (import_id, statut),
  CONSTRAINT fk_ipl_import FOREIGN KEY (import_id) REFERENCES imports_paie(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE fiches_paie (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id      INT UNSIGNED NOT NULL,
  annee         SMALLINT UNSIGNED NOT NULL,
  mois          TINYINT UNSIGNED  NOT NULL,
  version       TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  document_id   BIGINT UNSIGNED NOT NULL,
  import_id     INT UNSIGNED NULL,
  statut        ENUM('publiee','depubliee','remplacee') NOT NULL DEFAULT 'publiee',
  publiee_le    DATETIME     NOT NULL,
  premiere_consultation DATETIME NULL,
  UNIQUE KEY uq_fp (agent_id, annee, mois, version),
  INDEX idx_fp_agent (agent_id, annee, mois, statut),
  CONSTRAINT fk_fp_agent FOREIGN KEY (agent_id)    REFERENCES agents(id),
  CONSTRAINT fk_fp_doc   FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

-- =========================================================
-- PLANNING
-- =========================================================
CREATE TABLE publications_planning (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  periode_debut  DATE NOT NULL,
  periode_fin    DATE NOT NULL,
  perimetre      JSON NOT NULL,                    -- {"sites":[…],"equipes":[…]}
  nb_vacations   INT UNSIGNED NOT NULL,
  publiee_par    INT UNSIGNED NOT NULL,
  publiee_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE vacations (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id         INT UNSIGNED NOT NULL,
  site_id          INT UNSIGNED NOT NULL,
  debut            DATETIME     NOT NULL,          -- UTC
  fin              DATETIME     NOT NULL,          -- UTC, > debut
  poste            VARCHAR(60)  NULL,
  pause_minutes    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  statut           ENUM('brouillon','publiee','annulee') NOT NULL DEFAULT 'brouillon',
  publication_id   INT UNSIGNED NULL,
  force_motif      VARCHAR(255) NULL,              -- si dépassement de plafond forcé
  commentaire      VARCHAR(255) NULL,
  created_by       INT UNSIGNED NOT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vac_agent (agent_id, debut),
  INDEX idx_vac_site (site_id, debut),
  INDEX idx_vac_statut (statut, debut),
  CONSTRAINT fk_vac_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_vac_site  FOREIGN KEY (site_id)  REFERENCES sites(id),
  CONSTRAINT fk_vac_pub   FOREIGN KEY (publication_id) REFERENCES publications_planning(id),
  CONSTRAINT ck_vac_duree CHECK (fin > debut)
) ENGINE=InnoDB;

-- =========================================================
-- POINTAGES ET SERVICES
-- =========================================================
CREATE TABLE badges (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero                VARCHAR(20)  NOT NULL,           -- n° imprimé, ex. B-000153
  uid_hash              CHAR(64)     NULL,               -- HMAC-SHA256 de l'UID normalisé
  uid_fin               CHAR(4)      NULL,               -- 4 derniers caractères hex (affichage)
  agent_id              INT UNSIGNED NULL,
  statut                ENUM('stock','actif','perdu','vole','desactive','restitue') NOT NULL DEFAULT 'stock',
  pin_hash              VARCHAR(255) NULL,
  pin_a_changer         TINYINT(1)   NOT NULL DEFAULT 1,
  echecs_pin            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  verrouille_jusqu_a    DATETIME     NULL,
  photo_document_id     BIGINT UNSIGNED NULL,
  carte_pro_numero      VARCHAR(40)  NULL,               -- instantané imprimé sur la carte
  emis_le               DATETIME     NULL,
  emis_par              INT UNSIGNED NULL,
  desactive_le          DATETIME     NULL,
  motif_desactivation   VARCHAR(255) NULL,
  derniere_utilisation  DATETIME     NULL,
  -- un seul badge actif par agent
  agent_actif           INT UNSIGNED AS (IF(statut = 'actif', agent_id, NULL)) STORED,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_badge_numero (numero),
  UNIQUE KEY uq_badge_uid (uid_hash),
  UNIQUE KEY uq_badge_un_actif (agent_actif),
  INDEX idx_badge_agent (agent_id, statut),
  CONSTRAINT fk_badge_agent FOREIGN KEY (agent_id)          REFERENCES agents(id),
  CONSTRAINT fk_badge_photo FOREIGN KEY (photo_document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE pointages (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id              INT UNSIGNED NOT NULL,
  site_id               INT UNSIGNED NOT NULL,
  borne_id              INT UNSIGNED NULL,
  vacation_id           BIGINT UNSIGNED NULL,
  type                  ENUM('arrivee','depart') NOT NULL,
  mode                  ENUM('qr_dynamique','qr_statique','badge_nfc','manuel') NOT NULL,
  badge_id              INT UNSIGNED NULL,               -- mode badge_nfc
  horodatage            DATETIME(3)  NOT NULL,      -- horodatage retenu (serveur, ou appareil borné si hors ligne)
  horodatage_reception  DATETIME(3)  NOT NULL,      -- réception serveur
  horodatage_appareil   DATETIME(3)  NULL,
  hors_ligne            TINYINT(1)   NOT NULL DEFAULT 0,
  latitude              DECIMAL(9,6) NULL,
  longitude             DECIMAL(9,6) NULL,
  precision_m           SMALLINT UNSIGNED NULL,
  distance_site_m       INT UNSIGNED NULL,
  geoloc_statut         ENUM('ok','hors_zone','imprecise','refusee','indisponible') NOT NULL,
  appareil_id           INT UNSIGNED NULL,
  uuid_client           CHAR(36)     NOT NULL,
  jeton_fenetre         INT UNSIGNED NULL,
  statut                ENUM('valide','a_verifier','rejete','annule','corrige') NOT NULL DEFAULT 'valide',
  indicateurs           SET('doublon','hors_zone','imprecise','vitesse','appareil_partage',
                            'carte_pro_expiree','sync_tardive','hors_affectation','pin_hors_ligne','hors_planning','retard','depart_anticipe') NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ptg_uuid (uuid_client),
  INDEX idx_ptg_agent (agent_id, horodatage),
  INDEX idx_ptg_site (site_id, horodatage),
  INDEX idx_ptg_statut (statut, horodatage),
  CONSTRAINT fk_ptg_agent FOREIGN KEY (agent_id)    REFERENCES agents(id),
  CONSTRAINT fk_ptg_site  FOREIGN KEY (site_id)     REFERENCES sites(id),
  CONSTRAINT fk_ptg_borne FOREIGN KEY (borne_id)    REFERENCES bornes(id),
  CONSTRAINT fk_ptg_vac   FOREIGN KEY (vacation_id) REFERENCES vacations(id),
  CONSTRAINT fk_ptg_badge FOREIGN KEY (badge_id)    REFERENCES badges(id),
  CONSTRAINT fk_ptg_app   FOREIGN KEY (appareil_id) REFERENCES appareils(id)
) ENGINE=InnoDB;

CREATE TABLE services (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id             INT UNSIGNED NOT NULL,
  site_id              INT UNSIGNED NOT NULL,
  vacation_id          BIGINT UNSIGNED NULL,
  pointage_arrivee_id  BIGINT UNSIGNED NOT NULL,
  pointage_depart_id   BIGINT UNSIGNED NULL,
  debut                DATETIME(3)  NOT NULL,
  fin                  DATETIME(3)  NULL,
  duree_minutes        INT UNSIGNED NULL,
  statut               ENUM('ouvert','clos','depart_manquant','corrige','annule') NOT NULL DEFAULT 'ouvert',
  -- garantit un seul service ouvert par agent (NULL non indexé en unicité)
  agent_ouvert         INT UNSIGNED AS (IF(statut = 'ouvert', agent_id, NULL)) STORED,
  UNIQUE KEY uq_srv_un_ouvert (agent_ouvert),
  INDEX idx_srv_agent (agent_id, debut),
  INDEX idx_srv_site (site_id, debut),
  INDEX idx_srv_statut (statut),
  CONSTRAINT fk_srv_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  CONSTRAINT fk_srv_site  FOREIGN KEY (site_id)  REFERENCES sites(id),
  CONSTRAINT fk_srv_arr   FOREIGN KEY (pointage_arrivee_id) REFERENCES pointages(id),
  CONSTRAINT fk_srv_dep   FOREIGN KEY (pointage_depart_id)  REFERENCES pointages(id)
) ENGINE=InnoDB;

CREATE TABLE corrections_pointage (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pointage_id    BIGINT UNSIGNED NULL,
  service_id     BIGINT UNSIGNED NULL,
  action         ENUM('ajout','modification','annulation','validation') NOT NULL,
  avant          JSON         NULL,
  apres          JSON         NULL,
  motif_code     VARCHAR(30)  NOT NULL,            -- oubli, panne_borne, erreur_type…
  motif_texte    VARCHAR(255) NOT NULL,
  statut         ENUM('proposee','appliquee','refusee') NOT NULL,
  demandee_par   INT UNSIGNED NOT NULL,
  validee_par    INT UNSIGNED NULL,
  validee_le     DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_corr_statut (statut, created_at),
  INDEX idx_corr_service (service_id)
) ENGINE=InnoDB;

-- =========================================================
-- RONDES
-- =========================================================
CREATE TABLE points_controle (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id             INT UNSIGNED NOT NULL,
  identifiant         VARCHAR(12)  NOT NULL,       -- ex. PC-0142
  nom                 VARCHAR(120) NOT NULL,
  description         VARCHAR(255) NULL,
  zone                VARCHAR(60)  NULL,           -- étage / zone
  ordre               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  photo_document_id   BIGINT UNSIGNED NULL,
  latitude            DECIMAL(9,6) NULL,
  longitude           DECIMAL(9,6) NULL,
  rayon_metres        SMALLINT UNSIGNED NULL,      -- NULL = valeur par défaut (50 m)
  plan_x              DECIMAL(5,2) NULL,           -- % largeur du plan
  plan_y              DECIMAL(5,2) NULL,           -- % hauteur du plan
  qr_version          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  actif               TINYINT(1)   NOT NULL DEFAULT 1,
  desactive_le        DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pc_ident (identifiant),
  INDEX idx_pc_site (site_id, actif, ordre),
  CONSTRAINT fk_pc_site  FOREIGN KEY (site_id) REFERENCES sites(id),
  CONSTRAINT fk_pc_photo FOREIGN KEY (photo_document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE rondes_modeles (
  id                         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id                    INT UNSIGNED NOT NULL,
  nom                        VARCHAR(80)  NOT NULL,
  heure_debut                TIME         NOT NULL,   -- heure locale Europe/Paris
  heure_fin                  TIME         NOT NULL,   -- peut être < heure_debut (nuit)
  frequence_minutes          SMALLINT UNSIGNED NOT NULL,
  jours_semaine              TINYINT UNSIGNED NOT NULL DEFAULT 127,  -- masque bits lun=1 … dim=64
  duree_max_minutes          SMALLINT UNSIGNED NOT NULL DEFAULT 45,
  tolerance_demarrage_min    SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  ordre_impose               TINYINT(1)   NOT NULL DEFAULT 0,
  temps_min_entre_points_s   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  actif                      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rm_site (site_id, actif),
  CONSTRAINT fk_rm_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE rondes_modeles_points (
  modele_id    INT UNSIGNED NOT NULL,
  point_id     INT UNSIGNED NOT NULL,
  ordre        SMALLINT UNSIGNED NOT NULL,
  obligatoire  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (modele_id, point_id),
  CONSTRAINT fk_rmp_m FOREIGN KEY (modele_id) REFERENCES rondes_modeles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rmp_p FOREIGN KEY (point_id)  REFERENCES points_controle(id)
) ENGINE=InnoDB;

CREATE TABLE rondes (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id               INT UNSIGNED NOT NULL,
  modele_id             INT UNSIGNED NULL,          -- NULL = ronde libre
  agent_id              INT UNSIGNED NULL,          -- attribuée au démarrage
  prevue_le             DATETIME     NULL,
  demarree_le           DATETIME(3)  NULL,
  cloturee_le           DATETIME(3)  NULL,
  statut                ENUM('a_venir','en_cours','terminee','incomplete','manquee','annulee') NOT NULL DEFAULT 'a_venir',
  nb_points_prevus      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_points_controles   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  points_prevus         JSON         NULL,          -- instantané [{point_id,ordre,obligatoire}]
  commentaire_cloture   VARCHAR(500) NULL,
  cloture_auto          TINYINT(1)   NOT NULL DEFAULT 0,
  uuid_client           CHAR(36)     NULL,
  -- une seule ronde en cours par agent
  agent_en_cours        INT UNSIGNED AS (IF(statut = 'en_cours', agent_id, NULL)) STORED,
  UNIQUE KEY uq_ronde_uuid (uuid_client),
  UNIQUE KEY uq_ronde_une_en_cours (agent_en_cours),
  INDEX idx_ronde_site (site_id, prevue_le),
  INDEX idx_ronde_agent (agent_id, demarree_le),
  INDEX idx_ronde_statut (statut, prevue_le),
  CONSTRAINT fk_ronde_site   FOREIGN KEY (site_id)   REFERENCES sites(id),
  CONSTRAINT fk_ronde_modele FOREIGN KEY (modele_id) REFERENCES rondes_modeles(id),
  CONSTRAINT fk_ronde_agent  FOREIGN KEY (agent_id)  REFERENCES agents(id)
) ENGINE=InnoDB;

CREATE TABLE passages_ronde (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ronde_id              BIGINT UNSIGNED NOT NULL,
  point_id              INT UNSIGNED NOT NULL,
  agent_id              INT UNSIGNED NOT NULL,
  horodatage            DATETIME(3)  NOT NULL,
  horodatage_reception  DATETIME(3)  NOT NULL,
  horodatage_appareil   DATETIME(3)  NULL,
  hors_ligne            TINYINT(1)   NOT NULL DEFAULT 0,
  latitude              DECIMAL(9,6) NULL,
  longitude             DECIMAL(9,6) NULL,
  precision_m           SMALLINT UNSIGNED NULL,
  distance_point_m      INT UNSIGNED NULL,
  appareil_id           INT UNSIGNED NULL,
  qr_version            SMALLINT UNSIGNED NOT NULL,
  uuid_client           CHAR(36)     NOT NULL,
  indicateurs           SET('hors_zone','imprecise','ordre','trop_rapide','scan_groupe','sync_tardive') NULL,
  statut                ENUM('valide','a_verifier','rejete') NOT NULL DEFAULT 'valide',
  UNIQUE KEY uq_pass_uuid (uuid_client),
  INDEX idx_pass_point (point_id, horodatage),
  INDEX idx_pass_agent (agent_id, horodatage),
  INDEX idx_pass_ronde (ronde_id, horodatage),
  CONSTRAINT fk_pass_ronde FOREIGN KEY (ronde_id) REFERENCES rondes(id),
  CONSTRAINT fk_pass_point FOREIGN KEY (point_id) REFERENCES points_controle(id),
  CONSTRAINT fk_pass_agent FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB;

CREATE TABLE anomalies (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id                 INT UNSIGNED NOT NULL,
  ronde_id                BIGINT UNSIGNED NULL,
  passage_id              BIGINT UNSIGNED NULL,
  point_id                INT UNSIGNED NULL,
  agent_id                INT UNSIGNED NOT NULL,
  categorie               ENUM('intrusion','degradation','incendie','technique','acces_ouvert',
                               'eclairage','personne','colis_suspect','autre') NOT NULL,
  gravite                 ENUM('info','mineure','majeure','critique') NOT NULL,
  description             TEXT         NOT NULL,
  visible_client          TINYINT(1)   NOT NULL DEFAULT 1,
  statut                  ENUM('ouverte','en_cours','resolue','close') NOT NULL DEFAULT 'ouverte',
  traitee_par             INT UNSIGNED NULL,
  traitee_le              DATETIME     NULL,
  commentaire_traitement  TEXT         NULL,
  uuid_client             CHAR(36)     NOT NULL,
  created_at              DATETIME(3)  NOT NULL,
  UNIQUE KEY uq_ano_uuid (uuid_client),
  INDEX idx_ano_site (site_id, created_at),
  INDEX idx_ano_statut (statut, gravite),
  INDEX idx_ano_ronde (ronde_id),
  CONSTRAINT fk_ano_site  FOREIGN KEY (site_id)  REFERENCES sites(id),
  CONSTRAINT fk_ano_ronde FOREIGN KEY (ronde_id) REFERENCES rondes(id),
  CONSTRAINT fk_ano_pass  FOREIGN KEY (passage_id) REFERENCES passages_ronde(id),
  CONSTRAINT fk_ano_point FOREIGN KEY (point_id) REFERENCES points_controle(id),
  CONSTRAINT fk_ano_agent FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB;

CREATE TABLE anomalies_photos (
  anomalie_id  BIGINT UNSIGNED NOT NULL,
  document_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (anomalie_id, document_id),
  CONSTRAINT fk_ap_ano FOREIGN KEY (anomalie_id) REFERENCES anomalies(id) ON DELETE CASCADE,
  CONSTRAINT fk_ap_doc FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

-- =========================================================
-- HEURES, MAJORATIONS, RAPPORTS
-- =========================================================
CREATE TABLE jours_feries (
  date      DATE PRIMARY KEY,
  libelle   VARCHAR(60) NOT NULL,
  zone      VARCHAR(20) NOT NULL DEFAULT 'metropole'
) ENGINE=InnoDB;

CREATE TABLE regles_majoration (
  id           SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(20)  NOT NULL,               -- nuit, dimanche, ferie, hs25, hs50
  libelle      VARCHAR(80)  NOT NULL,
  taux         DECIMAL(5,2) NOT NULL,               -- en %
  plage_debut  TIME         NULL,
  plage_fin    TIME         NULL,
  seuil_heures DECIMAL(5,2) NULL,                   -- pour HS
  cumulable    TINYINT(1)   NOT NULL DEFAULT 1,
  date_effet   DATE         NOT NULL,
  actif        TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_rm_code_effet (code, date_effet)
) ENGINE=InnoDB;

CREATE TABLE periodes_cloturees (
  annee       SMALLINT UNSIGNED NOT NULL,
  mois        TINYINT UNSIGNED  NOT NULL,
  cloturee_par INT UNSIGNED NOT NULL,
  cloturee_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (annee, mois)
) ENGINE=InnoDB;

CREATE TABLE rapports_mensuels (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id              INT UNSIGNED NOT NULL,
  annee                 SMALLINT UNSIGNED NOT NULL,
  mois                  TINYINT UNSIGNED  NOT NULL,
  version               TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  minutes_planifiees    INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_pointees      INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_nuit          INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_dimanche      INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_ferie         INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_hs25          INT UNSIGNED NOT NULL DEFAULT 0,
  minutes_hs50          INT UNSIGNED NOT NULL DEFAULT 0,
  nb_retards            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  minutes_retard        INT UNSIGNED NOT NULL DEFAULT 0,
  nb_absences           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_rondes             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nb_points_controles   INT UNSIGNED NOT NULL DEFAULT 0,
  carte_pro_numero      VARCHAR(40)  NULL,           -- instantané au moment de la génération
  document_id           BIGINT UNSIGNED NULL,
  sha256                CHAR(64)     NULL,
  statut                ENUM('en_file','genere','envoye','erreur') NOT NULL DEFAULT 'en_file',
  lot_id                CHAR(36)     NULL,           -- génération en masse
  genere_le             DATETIME     NULL,
  genere_par            INT UNSIGNED NOT NULL,
  envoye_le             DATETIME     NULL,
  UNIQUE KEY uq_rm (agent_id, annee, mois, version),
  INDEX idx_rm_lot (lot_id, statut),
  CONSTRAINT fk_rmens_agent FOREIGN KEY (agent_id)    REFERENCES agents(id),
  CONSTRAINT fk_rmens_doc   FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE taches_fond (                          -- file des traitements par lots (cron)
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type        VARCHAR(40)  NOT NULL,                -- rapports_masse, zip, export_rgpd…
  parametres  JSON         NOT NULL,
  progression TINYINT UNSIGNED NOT NULL DEFAULT 0,
  statut      ENUM('attente','en_cours','termine','erreur') NOT NULL DEFAULT 'attente',
  resultat_document_id BIGINT UNSIGNED NULL,
  erreur      TEXT         NULL,
  cree_par    INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tf_statut (statut, created_at)
) ENGINE=InnoDB;

-- =========================================================
-- DEMANDES, NOTIFICATIONS, E-MAILS, PARAMÈTRES, AUDIT
-- =========================================================
CREATE TABLE demandes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  demandeur_id    INT UNSIGNED NOT NULL,
  categorie       ENUM('rh','paie','planning','urgence','client','autre') NOT NULL,
  objet           VARCHAR(150) NOT NULL,
  priorite        ENUM('normale','haute','urgente') NOT NULL DEFAULT 'normale',
  statut          ENUM('nouvelle','en_cours','attente_demandeur','resolue','close') NOT NULL DEFAULT 'nouvelle',
  assignee_a      INT UNSIGNED NULL,
  resolue_le      DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_dem_file (statut, categorie, created_at),
  INDEX idx_dem_demandeur (demandeur_id, created_at),
  CONSTRAINT fk_dem_util FOREIGN KEY (demandeur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE demandes_messages (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  demande_id      INT UNSIGNED NOT NULL,
  auteur_id       INT UNSIGNED NOT NULL,
  message         TEXT         NOT NULL,
  document_id     BIGINT UNSIGNED NULL,
  interne         TINYINT(1)   NOT NULL DEFAULT 0,   -- note non visible du demandeur
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dm_demande (demande_id, created_at),
  CONSTRAINT fk_dm_dem FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NOT NULL,
  type            VARCHAR(40)  NOT NULL,
  titre           VARCHAR(150) NOT NULL,
  message         VARCHAR(500) NULL,
  lien            VARCHAR(255) NULL,
  lue_le          DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_util (utilisateur_id, lue_le, created_at),
  CONSTRAINT fk_notif_util FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE file_emails (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  destinataire    VARCHAR(190) NOT NULL,
  sujet           VARCHAR(200) NOT NULL,
  gabarit         VARCHAR(60)  NOT NULL,
  donnees         JSON         NOT NULL,
  statut          ENUM('attente','envoye','echec') NOT NULL DEFAULT 'attente',
  tentatives      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  derniere_erreur VARCHAR(255) NULL,
  envoyer_apres   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  envoye_le       DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fe_file (statut, envoyer_apres)
) ENGINE=InnoDB;

CREATE TABLE parametres (
  cle          VARCHAR(80)  PRIMARY KEY,           -- ex. pointage.rayon_defaut_m
  valeur       JSON         NOT NULL,
  modifie_par  INT UNSIGNED NULL,
  modifie_le   DATETIME     NULL
) ENGINE=InnoDB;

CREATE TABLE audit (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NULL,
  role_code       VARCHAR(20)  NULL,
  action          VARCHAR(60)  NOT NULL,           -- ex. agent.nir.reveler
  objet_type      VARCHAR(40)  NULL,
  objet_id        BIGINT UNSIGNED NULL,
  details         JSON         NULL,               -- jamais de donnée sensible en clair
  ip              VARBINARY(16) NULL,
  user_agent      VARCHAR(255) NULL,
  created_at      DATETIME(3)  NOT NULL,
  hash_precedent  CHAR(64)     NOT NULL,
  hash            CHAR(64)     NOT NULL,           -- SHA-256(hash_precedent | contenu canonique)
  INDEX idx_audit_action (action, created_at),
  INDEX idx_audit_objet (objet_type, objet_id),
  INDEX idx_audit_util (utilisateur_id, created_at)
) ENGINE=InnoDB;
```

Volumétrie estimée sur 3 ans (150 agents) : environ 330 000 pointages, 2 millions de passages, 25 000 fiches de paie. La base reste sous 2 Go, les fichiers sous 30 Go. Les index composites listés couvrent les requêtes des tableaux de bord et des rapports.

### 5.5 Liste des routes

Toutes les routes `POST` exigent un jeton CSRF (champ caché pour les formulaires, en-tête `X-CSRF-Token` pour l'API). Middlewares : `auth` (session), `2fa` (2FA validée), `perm:xxx` (permission), `throttle`, `borne`.

#### Authentification et fichiers

| Méthode | Route | Contrôleur@action | Accès |
|---|---|---|---|
| GET/POST | `/connexion` | AuthController@connexion | Public, throttle |
| GET/POST | `/connexion/2fa` | AuthController@deuxFacteurs | Session partielle, throttle |
| POST | `/deconnexion` | AuthController@deconnexion | auth |
| GET/POST | `/mot-de-passe/oubli` | AuthController@oubli | Public, throttle |
| GET/POST | `/mot-de-passe/reinitialiser/{jeton}` | AuthController@reinitialiser | Public |
| GET/POST | `/invitation/{jeton}` | AuthController@invitation | Public |
| GET/POST | `/compte/2fa` | AuthController@enrolement2fa | auth |
| GET/POST | `/compte/reauthentifier` | AuthController@reauth | auth, 2fa |
| GET/POST | `/consentements` | AuthController@consentements | auth |
| GET | `/fichier/{jeton}` | FichierController@servir | auth + signature valide + même utilisateur |

#### Espace agent (HTML, rôles agent et chef)

| Méthode | Route | Contrôleur@action |
|---|---|---|
| GET | `/` | Agent\Accueil@index |
| GET | `/pointer` | Agent\Pointage@scanner |
| GET | `/rondes` · `/rondes/{id}` | Agent\Ronde@index · @show |
| GET | `/historique?mois=AAAA-MM` | Agent\Historique@index |
| GET | `/planning?vue=semaine&date=` | Agent\Planning@index |
| GET | `/profil` | Agent\Profil@index |
| POST | `/profil/demande` | Agent\Profil@demanderModification |
| GET/POST | `/profil/badge` | Agent\Profil@badge (changer le PIN, déclarer une perte) |
| GET | `/documents` | Agent\Documents@index |
| GET | `/paie` | Agent\Paie@index |
| GET | `/paie/{id}/telecharger` | Agent\Paie@telecharger → redirection URL signée |
| GET | `/societe` · `/societe/{id}` | Agent\Societe@index · @show |
| GET/POST | `/contact` | Agent\Contact@index · @creer |
| GET | `/contact/{id}` | Agent\Contact@show |
| POST | `/contact/{id}/message` | Agent\Contact@repondre |
| GET | `/notifications` | Agent\Notifications@index |

#### API JSON `/api/v1` (agent et chef)

| Méthode | Route | Rôle | Corps / réponse |
|---|---|---|---|
| GET | `/api/v1/etat` | Statut du jour, service ouvert, prochaine vacation, ronde en cours, compteurs | — |
| POST | `/api/v1/pointages` | Pointage | `{jeton, lat, lng, precision, uuid, horodatage_appareil, appareil_uuid}` → `{type, site, horodatage, statut, indicateurs}` |
| POST | `/api/v1/pointages/{id}/annuler` | Annulation sous 2 min | `{motif}` |
| POST | `/api/v1/rondes` | Démarrer | `{site_id, ronde_id?, uuid}` |
| GET | `/api/v1/rondes/{id}` | État de la ronde (points, progression) | — |
| POST | `/api/v1/rondes/{id}/passages` | Scan d'un point | `{jeton, lat, lng, precision, uuid, horodatage_appareil}` |
| POST | `/api/v1/rondes/{id}/cloture` | Clôture | `{commentaire}` |
| POST | `/api/v1/anomalies` | Signalement (multipart) | champs + `photos[]` |
| POST | `/api/v1/sync` | Lot hors ligne | `{evenements:[{kind, payload}]}` → résultat par UUID |
| GET | `/api/v1/planning?debut=&fin=` | Vacations de l'agent | — |
| POST | `/api/v1/societe/versions/{id}/accuse` | Accusé de lecture | — |
| GET/POST | `/api/v1/notifications` · `/{id}/lue` | Notifications | — |

#### Borne

| Méthode | Route | Accès |
|---|---|---|
| GET | `/borne/{code}` | Cookie d'appareil de borne ; sinon écran d'enrôlement |
| POST | `/borne/{code}/enrolement` | Code d'enrôlement à usage unique, throttle |
| POST | `/borne/{code}/battement` | Cookie de borne → `{heure_serveur, cle_version, statut}` |
| POST | `/borne/{code}/badge` | Cookie de borne, throttle → `{uid, pin?, uuid, horodatage_borne}` → `{prenom_initiale, type, horodatage, statut}` ou demande de PIN |
| POST | `/borne/{code}/badge/sync` | Cookie de borne → lot d'événements hors ligne |
| POST | `/borne/{code}/association` | Code admin à usage unique → associe l'UID présenté à un badge en stock |

#### Back-office `/admin` (chef, RH, admin selon permissions)

| Méthode | Route | Fonction |
|---|---|---|
| GET | `/admin` | Tableau de bord |
| GET | `/admin/agents` | Liste, recherche, filtres |
| GET/POST | `/admin/agents/nouveau` | Création (+ invitation) |
| GET/POST | `/admin/agents/import` | Import CSV (analyse puis validation) |
| GET/POST | `/admin/agents/{id}` | Fiche, modification |
| POST | `/admin/agents/{id}/statut` | Activer, suspendre, désactiver |
| POST | `/admin/agents/{id}/invitation` | Renvoi d'invitation |
| POST | `/admin/agents/{id}/nir` | Révélation du NIR (JSON, reauth, audit) |
| POST | `/admin/agents/{id}/documents` | Dépôt de document |
| POST | `/admin/agents/{id}/carte-pro` · `/qualifications` | Ajout ou modification |
| GET/POST | `/admin/agents/{id}/badge` | Création, aperçu, association, remplacement du badge |
| GET | `/admin/badges` | Liste des badges, stock, export |
| GET | `/admin/badges/{id}/carte.pdf` | Gabarit d'impression CR80 recto/verso |
| POST | `/admin/badges/{id}/statut` | Perte, vol, désactivation, restitution (motif obligatoire) |
| POST | `/admin/badges/{id}/pin/reinitialiser` | Réinitialisation du PIN |
| GET/POST | `/admin/demandes-profil` · `/{id}` | Validation des demandes de modification |
| GET | `/admin/echeances` | Échéances 7/30/90 j, export |
| GET | `/admin/paie` | Historique des imports |
| GET/POST | `/admin/paie/import` | Étape 1 : période + dépôt |
| GET/POST | `/admin/paie/import/{id}` | Étape 2 : contrôle, association manuelle |
| POST | `/admin/paie/import/{id}/publier` | Étape 3 : publication |
| POST | `/admin/paie/fiches/{id}/depublier` | Dépublication motivée |
| GET | `/admin/planning?site=&semaine=` | Calendrier |
| GET | `/api/v1/admin/planning` | Données calendrier (JSON) |
| POST | `/api/v1/admin/vacations` · `/{id}` · `/{id}/supprimer` | CRUD vacation, retour des conflits |
| POST | `/admin/planning/dupliquer` · `/publier` | Duplication, publication |
| GET | `/admin/pointages` | Suivi du jour et liste filtrée |
| GET | `/api/v1/admin/pointages/jour` | Données temps réel (polling 30 s) |
| GET | `/admin/pointages/export.csv` | Export |
| POST | `/admin/pointages/corrections` | Proposer ou appliquer une correction |
| POST | `/admin/corrections/{id}/valider` · `/refuser` | Validation |
| GET | `/admin/heures?mois=` | Tableau heures et majorations |
| GET | `/admin/heures/export-paie.csv?mois=` | Variables de paie |
| POST | `/admin/heures/cloturer` | Clôture du mois |
| GET | `/admin/rondes` · `/admin/rondes/{id}` | Liste, détail |
| GET | `/admin/sites/{id}/temps-reel` | Plan temps réel |
| GET | `/api/v1/admin/sites/{id}/temps-reel` | État des points (polling 20 s) |
| GET | `/admin/points/{id}/historique` | Vue par emplacement |
| GET | `/admin/agents/{id}/rondes` | Vue par agent |
| GET/POST | `/admin/anomalies` · `/{id}` | Liste, traitement |
| GET/POST | `/admin/rapports` | Rapports mensuels : génération unitaire ou en masse |
| GET | `/admin/rapports/lots/{id}` | Progression et ZIP |
| POST | `/admin/rapports/{id}/envoyer` | Envoi à l'agent |
| GET | `/admin/rapports-rondes?site=&du=&au=&format=pdf\|csv` | Rapport de rondes |
| GET/POST | `/admin/societe` · `/nouveau` · `/{id}` · `/{id}/version` | Documents société, versions |
| GET | `/admin/societe/{id}/accuses` · POST `/{id}/relancer` | Suivi et relance |
| GET/POST | `/admin/clients` · `/{id}` | Clients et options du portail |
| GET/POST | `/admin/sites` · `/nouveau` · `/{id}` | Sites |
| POST | `/admin/sites/{id}/plan` | Upload du plan |
| POST | `/admin/sites/{id}/qr-statique/regenerer` | Régénération QR statique |
| GET/POST | `/admin/sites/{id}/bornes` · POST `/admin/bornes/{id}/revoquer` | Bornes |
| GET/POST | `/admin/sites/{id}/points` · `/admin/points/{id}` | Points de contrôle |
| POST | `/admin/points/{id}/regenerer` · `/desactiver` | QR, désactivation |
| POST | `/admin/sites/{id}/points/positions` | Placement sur plan (JSON) |
| GET | `/admin/sites/{id}/etiquettes.pdf?points=` | Planche d'étiquettes |
| GET/POST | `/admin/sites/{id}/modeles-ronde` · `/admin/modeles-ronde/{id}` | Planification |
| GET/POST | `/admin/utilisateurs` · `/{id}` | Comptes (dont clients) |
| GET/POST | `/admin/roles` | Matrice des permissions |
| GET/POST | `/admin/parametres` · `/admin/majorations` · `/admin/jours-feries` | Paramètres |
| GET/POST | `/admin/demandes` · `/{id}` | File des demandes |
| GET | `/admin/audit` · `/admin/audit/export.csv` | Journal d'audit |
| GET/POST | `/admin/rgpd/{agentId}/export` | Export des données (droit d'accès) |

#### Portail client `/client`

| Méthode | Route | Fonction |
|---|---|---|
| GET | `/client` | Tableau de bord multi-sites |
| GET | `/client/sites/{id}` | Fiche site, présence, dernières rondes |
| GET | `/client/sites/{id}/temps-reel` + `/api/v1/client/sites/{id}/temps-reel` | Plan temps réel |
| GET | `/client/rondes` · `/client/rondes/{id}` | Historique, détail |
| GET | `/client/anomalies` · `/{id}` | Anomalies visibles client |
| GET | `/client/rapports?site=&du=&au=&format=` | Rapports PDF/CSV |
| GET/POST | `/client/contact` · `/{id}` | Demandes |

Chaque contrôleur client vérifie `site.client_id === utilisateur.client_id`. Un objet hors périmètre renvoie **404** (et non 403), pour ne pas révéler son existence.

### 5.6 Algorithmes clés

#### 5.6.1 Jetons QR (HMAC)

```php
// Clés dérivées depuis une clé maître (.env, 32 octets aléatoires, base64)
$cleBorne  = hash_hmac('sha256', "borne:{$borneId}:v{$cleVersion}", $cleMaitre, true);
$cleRondes = hash_hmac('sha256', 'rondes:v1', $cleMaitre, true);
$cleStatique = hash_hmac('sha256', 'statique:v1', $cleMaitre, true);

// Format : PGS1.{T}.{champs…}.{sig}   T = D (dynamique), S (statique), R (ronde)
function signer(string $charge, string $cle): string {
    return rtrim(strtr(base64_encode(substr(hash_hmac('sha256', $charge, $cle, true), 0, 16)), '+/', '-_'), '=');
}
// Dynamique : charge = "D.{site}.{borne}.{fenetre}" ; fenetre = intdiv(time(), 60)
// Vérification : hash_equals(signer($charge, $cle), $sigRecue) ET fenetre ∈ {n, n-1}
//   (hors ligne : fenetre comparée à l'horodatage appareil, ± 1)
```

- La borne calcule la même signature en JavaScript (`crypto.subtle.sign('HMAC', …)`) à partir de `cleBorne`, remise à l'enrôlement et stockée en IndexedDB. Rotation possible en incrémentant `cle_version`.
- Comparaisons en temps constant (`hash_equals`). Aucun secret dans le code source ni dans le dépôt Git.
- QR de ronde : `R.{point_id}.{qr_version}` signé avec `cleRondes`. Le contenu du QR (≈ 45 caractères) reste lisible même sur une étiquette de 30 mm.

#### 5.6.2 Enregistrement d'un pointage (transaction)

```
1. Vérifier session agent + CSRF + appareil (créer/mettre à jour appareils*)
2. Parser et vérifier le jeton (type D ou S, signature, fenêtre, borne active, site actif)
3. Idempotence : si uuid_client existe → renvoyer le résultat existant
4. BEGIN ; SELECT service ouvert de l'agent FOR UPDATE
5. Déterminer le type (arrivée/départ/changement de site)
6. Doublon < 5 min ? → réponse idempotente
7. Calcul géoloc : distance haversine, statut, indicateurs anti-fraude
8. Rapprochement vacation (± 2 h), indicateurs retard/départ anticipé/hors planning
9. INSERT pointages ; INSERT ou UPDATE services (durée)
10. COMMIT ; audit si « à vérifier » ; alertes (carte pro expirée, appareil partagé)
11. Réponse JSON pour l'écran de confirmation
```

#### 5.6.3 Ventilation des heures

```
pour chaque service clos du mois (horodatages UTC) :
  convertir en Europe/Paris
  découper en segments aux bornes : 00:00, 06:00, 21:00 de chaque jour
  pour chaque segment : durée réelle = diff UTC (gère les changements d'heure)
    nuit     += durée si segment ∈ [21:00, 06:00[
    dimanche += durée si jour = dimanche
    ferie    += durée si date ∈ jours_feries
  total jour += durée
déduire la pause de la vacation rapprochée (au prorata des segments, ou sur le segment de jour)
HS : par semaine civile (lun–dim), au-delà de la durée de référence (35 h, paramétrable)
     → 8 premières en hs25, suivantes en hs50 (seuils et taux issus de regles_majoration)
```

Le service est rattaché au **mois de son heure de début** (paramétrable). Des tests unitaires dédiés couvrent les changements d'heure, les services à cheval sur deux mois, les jours fériés qui tombent un dimanche, ainsi que les services de 12 h.

#### 5.6.4 Statut des points sur le plan temps réel

```
ronde courante = ronde en_cours du site, sinon la dernière ronde ou la prochaine à venir
pour chaque point du modèle :
  vert   : passage valide dans la ronde courante
  rouge  : ronde terminée/incomplète/manquée sans passage, OU échéance ronde dépassée
  orange : ronde en cours, point non scanné, temps écoulé > 80 % de la durée max
  gris   : à venir (ronde pas démarrée ou point non encore attendu)
```

#### 5.6.5 Pointage par badge NFC

```
1. Borne authentifiée (cookie d'appareil), borne active, site actif, badge_nfc_actif = 1
2. UID normalisé (hexadécimal majuscule, sans séparateur)
   → uid_hash = HMAC-SHA256(UID, clé dédiée aux badges, dérivée de la clé maître)
3. Badge introuvable ou statut ≠ actif → refus (+ alerte si perdu ou volé)
   Badge verrouillé → refus « réessayez dans N min »
4. PIN requis ? → password_verify ; échec → echecs_pin + 1 (5 → verrouillage 15 min + alerte)
5. Agent affecté au site ? sinon indicateur hors_affectation (ou refus selon le paramètre)
6. Suite identique au § 5.6.2 à partir de l'étape 3 (idempotence, service ouvert, type,
   planning), sans géolocalisation ; mode = badge_nfc ; badge_id renseigné
7. Audit badge.utiliser (sans UID en clair) ; réponse minimale à la borne
```

### 5.7 Tâches planifiées (cron)

| Fréquence | Tâche | Rôle |
|---|---|---|
| 5 min | `rondes:generer` | Crée les rondes `a_venir` sur 24 h glissantes |
| 5 min | `rondes:surveiller` | Rondes non démarrées, en retard, clôture automatique, points manqués → alertes |
| 5 min | `pointages:surveiller` | Absences, départs manquants, bornes hors ligne |
| 5 min | `emails:envoyer` | Envoi de 50 e-mails par lot, reprise exponentielle (5 tentatives maximum) |
| 5 min | `taches:executer` | File `taches_fond` : rapports en masse, ZIP, exports (≤ 50 s par exécution) |
| Quotidien 02:00 | `alertes:echeances` | Seuils J-90/30/7/0 |
| Quotidien 02:15 | `rgpd:purger` | Application des durées de conservation (§ 6.3) |
| Quotidien 02:30 | `audit:verifier` | Contrôle de la chaîne de hash, alerte en cas de rupture |
| Quotidien 03:00 | `sauvegarde:base` | `mysqldump` compressé et chiffré, rotation 14 j |
| Quotidien 07:00 | `recap:quotidien` | E-mail récapitulatif chef/admin |
| Hebdomadaire | `nettoyage:tmp` | Fichiers temporaires, jetons expirés, sessions |

Un **verrou** (fichier `flock`) empêche deux exécutions simultanées d'une même tâche. Chaque exécution est tracée dans `storage/logs/cron.log`.

### 5.8 PWA et front-end

| Élément | Spécification |
|---|---|
| Manifest | `name: Pegasus Connect`, `short_name: Pegasus`, `display: standalone`, `orientation: portrait`, `theme_color: #111214`, `background_color: #111214`, icônes 192/512 et `maskable` (emblème sur fond noir) |
| Service Worker | Version dans le nom du cache. Stratégies : *cache first* pour les assets versionnés (`app.3f2a.js`), *network first* avec repli cache pour `/api/v1/etat` et `/api/v1/planning`, *network only* pour les POST (et file IndexedDB en cas d'échec). Page `offline.html`. Pas de mise en cache des PDF de paie |
| File hors ligne | IndexedDB `pegasus-queue` : `{uuid, kind (pointage/passage/anomalie/cloture), payload, cree_le, tentatives}`. Photos d'anomalie stockées en Blob (3 maximum par anomalie) |
| Scan | `BarcodeDetector` (Chrome Android) sinon html5-qrcode, caméra arrière, zone de visée carrée, torche si supportée, saisie manuelle de l'identifiant de point en dernier recours (marqué « à vérifier ») |
| Installation | Invite d'installation contextuelle après le premier pointage réussi. Page d'aide iOS « Partager → Sur l'écran d'accueil » |
| Performance | JS agent < 60 Ko gzip (hors html5-qrcode, chargé à la demande), CSS < 25 Ko, polices en `font-display: swap` avec sous-ensemble latin |
| Accessibilité | Zones tactiles ≥ 48 × 48 px, focus visible, `aria-live` pour les confirmations de scan, aucune information portée uniquement par la couleur |

### 5.9 Matériel recommandé (bornes et badges)

| Élément | Recommandation | Coût indicatif HT |
|---|---|---|
| Tablette | Android 10+ 10", Chrome à jour, alimentation permanente | 150–300 € |
| Support | Support mural antivol verrouillable, câble intégré | 50–150 € |
| Mode kiosque | Épinglage d'écran Android ou application kiosque (ex. MDM gratuit), veille désactivée, luminosité adaptative | 0–5 €/mois |
| Connexion | Wi-Fi du site si fiable, sinon carte SIM data (la borne fonctionne hors ligne, mais le battement de cœur sert à la supervision) | 0–10 €/mois |
| Étiquettes QR | Vinyle anti-UV et anti-rayures, ou plaques gravées pour l'extérieur | 1–5 €/étiquette |
| Lecteur NFC de borne (option A) | Lecteur USB-C 13,56 MHz, émulation clavier, compatible PC/SC et NDEF | 30–80 € |
| Tablette à NFC intégré (option B) | Tablette Android avec NFC (gamme durcie de préférence), à la place de la tablette standard | +150–300 € |
| Cartes NFC | PVC CR80 imprimable, puce NTAG 215/216 ou MIFARE Classic 1K | 0,40–1,20 €/carte |
| Imprimante à cartes | Recto-verso couleur (gammes Evolis, Zebra…) + rubans YMCKO | 1 200–2 500 € + 0,30–0,50 €/carte |
| Lecteur NFC du poste RH | Lecteur USB pour associer les badges | 30–60 € |
| Porte-badges | Étui rigide, enrouleur ou tour de cou | 1–3 € |

---

## 6. Sécurité et RGPD

Référentiel visé : **OWASP ASVS 4.0 niveau 2**, recommandations CNIL et ANSSI.

### 6.1 Mesures de sécurité

| Domaine | Mesures |
|---|---|
| **Mots de passe** | `password_hash(PASSWORD_ARGON2ID)` si disponible, sinon bcrypt coût 12 ; `password_needs_rehash` à la connexion ; 12 caractères minimum ; liste noire locale de 100 000 mots de passe courants |
| **2FA** | TOTP RFC 6238 (pragmarx/google2fa), fenêtre ± 1 période, anti-rejeu (dernier code utilisé mémorisé), secret chiffré en base, codes de secours hachés |
| **Tentatives** | Comptage par compte et par IP (`tentatives_connexion`), verrouillage temporaire, délai progressif, journalisation |
| **Sessions** | Cookie `__Host-pgs` : `Secure`, `HttpOnly`, `SameSite=Lax`, `Path=/`. `session.use_strict_mode=1`, `session_regenerate_id(true)` à la connexion, à la validation 2FA et au changement de rôle. Fichiers de session dans `storage/sessions` (hors web). Expiration par rôle (§ 3.1). Liaison légère à l'empreinte `User-Agent` |
| **Réauthentification** | Exigée pour : révélation du NIR, export RGPD, changement de rôle, régénération de QR, révocation de borne (si `auth_forte_le` > 5 min) |
| **CSRF** | Jeton de synchronisation par session (formulaires) et en-tête `X-CSRF-Token` (fetch). Vérification `Origin`/`Referer` sur toutes les requêtes POST |
| **XSS** | Helper `e()` = `htmlspecialchars($v, ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')` sur toute sortie. En JS : `textContent` uniquement, aucun `innerHTML` avec des données. JSON via `json_encode(JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_THROW_ON_ERROR)` |
| **Injection SQL** | PDO, `ATTR_EMULATE_PREPARES=false`, requêtes préparées exclusivement, liste blanche pour les colonnes de tri dynamiques |
| **Contrôle d'accès** | Middleware de permission sur **chaque route**, **et** vérification de portée dans chaque requête (`WHERE agent_id = :moi`, `site.client_id = :client`). Tests automatisés d'accès croisé (IDOR) pour chaque ressource |
| **Uploads** | Liste blanche d'extensions et de MIME (`finfo`) avec vérification des octets magiques ; taille maximale par type ; renommage UUID ; stockage dans `storage/` hors web (avec `.htaccess` `Require all denied` par défaut) ; images **ré-encodées** avec GD (suppression des métadonnées EXIF, notamment GPS) ; PDF refusés s'ils contiennent `/JavaScript`, `/Launch` ou `/EmbeddedFile` ; aucun droit d'exécution |
| **Fichiers sensibles** | Servis uniquement par `FichierController` : URL signée `HMAC(document_id, utilisateur_id, expiration)` valable 5 min, contrôle de permission à chaque appel, en-têtes `Content-Disposition: attachment`, `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`. Téléchargement journalisé |
| **Chiffrement au repos** | NIR et secrets TOTP : `sodium_crypto_secretbox` (XSalsa20-Poly1305), nonce aléatoire, préfixe de version de clé pour la rotation. Index aveugle HMAC pour l'unicité du NIR. Clés dans `.env` (chmod 600) hors dépôt Git, avec une copie séquestrée hors ligne (coffre-fort de mots de passe de la direction) |
| **Sauvegardes** | Chiffrées avant export hors site ; restauration testée chaque trimestre |
| **Transport** | HTTPS uniquement, TLS 1.2+, redirection 301, HSTS |
| **En-têtes** | `Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'` · `Strict-Transport-Security: max-age=31536000; includeSubDomains` · `X-Frame-Options: DENY` · `X-Content-Type-Options: nosniff` · `Referrer-Policy: strict-origin-when-cross-origin` · `Permissions-Policy: camera=(self), geolocation=(self), microphone=(), payment=()` · `Cross-Origin-Opener-Policy: same-origin` |
| **Erreurs et journaux** | `display_errors=Off` en production ; journaux applicatifs dans `storage/logs` sans donnée personnelle sensible (pas de NIR, pas de jetons, pas de coordonnées précises) ; rotation 30 j |
| **Dépendances** | `composer audit` à chaque déploiement et chaque mois ; versions verrouillées (`composer.lock`) ; html5-qrcode embarqué localement (pas de CDN) |
| **Bornes** | Cookie d'appareil `SameSite=Strict`, révocable ; la page borne n'expose aucune donnée personnelle ; aucune session utilisateur sur la borne |
| **Badges NFC** | UID jamais stocké en clair (HMAC avec une clé dédiée) ; PIN haché (`password_hash`), 5 essais puis verrouillage ; anti-rejeu par UUID d'événement ; limitation de débit par borne ; aucune donnée personnelle dans la puce ; affichage limité au prénom et à l'initiale du nom |
| **Audit** | Journal chaîné par hash (§ 3.16) ; l'hébergement mutualisé ne permet pas de restreindre les droits SQL par table, d'où le chaînage et l'export quotidien de l'empreinte de fin de chaîne |
| **Tests de sécurité** | Revue de code orientée sécurité + test d'intrusion applicatif externe (2 à 3 jours) avant mise en production ; correction des failles critiques et élevées avant MEP |

### 6.2 RGPD — cadre et responsabilités

| Sujet | Disposition |
|---|---|
| Responsable de traitement | Pegasus Insure |
| Sous-traitants (art. 28) | Hébergeur o2switch (contrat et DPA à joindre), prestataire de développement et de maintenance (clause RGPD au contrat). Le prestataire n'accède à la production qu'avec un compte nominatif journalisé |
| Clients donneurs d'ordre | Destinataires de données limitées (rondes, anomalies, présence agrégée). À encadrer dans le contrat de prestation de sécurité (finalité, confidentialité) |
| Hébergement et transferts | Données hébergées en France, **aucun transfert hors UE**. Les polices Google Fonts sont **auto-hébergées** pour éviter toute transmission d'adresse IP à Google |
| Registre des traitements | Fiches à créer : (1) gestion administrative du personnel, (2) gestion du temps de travail par pointage, (3) contrôle des rondes et main courante, (4) diffusion des bulletins de paie, (5) portail client, (6) sécurité du SI et journalisation. Modèles fournis en annexe du livrable de recette |
| **AIPD** | **Recommandée avant la mise en production** : géolocalisation de salariés, NIR, traçage de l'activité des employés. Le prestataire fournit les éléments techniques ; Pegasus Insure (ou son DPO) la conduit |
| Instances représentatives | **Information-consultation du CSE** préalable à la mise en œuvre d'un outil de contrôle de l'activité des salariés (pointage, rondes, géolocalisation) |
| Information des salariés | Notice d'information individuelle (finalités, base légale, données, destinataires, durées, droits, contact DPO), remise avant la mise en service et accessible dans l'application (« Mes données ») |

### 6.3 Base légale et géolocalisation

- **Base légale** : obligation légale (paie, documents RH, contrôle de la carte professionnelle) et **intérêt légitime** (preuve de présence et de passage, sécurité des sites, prévention de la fraude). La CNIL considère que le consentement d'un salarié est rarement libre du fait du lien de subordination. Il est donc **déconseillé d'en faire la base légale**. L'écran « Accepter / Refuser » est conservé au titre de la **transparence** et de l'expression d'un choix, avec un repli sans géolocalisation (§ 3.4.6) et sans aucune sanction automatique.
- **Minimisation** : position relevée **uniquement à l'instant du scan**. Pas de suivi continu, ni de suivi hors service, ni de position en arrière-plan.
- **Finalité limitée** : la géolocalisation sert à corroborer la présence sur site. **Le temps de travail est mesuré par le scan QR**, pas par la géolocalisation.
- **Badge NFC** : aucun relevé de position. La photo imprimée est celle exigée pour la carte professionnelle. La notice d'information des salariés et la consultation du CSE couvrent les deux modes de pointage.
- **Réduction** : au bout de 2 mois, les coordonnées brutes sont effacées. Seuls la distance au site et le statut (`ok`, `hors_zone`…) sont conservés, et ils suffisent comme preuve.

### 6.4 Durées de conservation (paramétrables, à valider par le DPO)

| Données | Durée en base active | Fondement / commentaire |
|---|---|---|
| Coordonnées GPS brutes (pointages, passages) | **2 mois** | Recommandation CNIL en matière de géolocalisation ; distance et statut conservés |
| Pointages, services, corrections | 3 ans après la fin de l'année concernée | Prescription des salaires (3 ans) |
| Rondes, passages, anomalies, photos | 3 ans | Preuve contractuelle vis-à-vis des clients ; durée à aligner sur les contrats |
| Bulletins de paie (copie employeur) | 5 ans | Obligation de conservation du double par l'employeur. **Point à arbitrer** : la disponibilité du bulletin électronique pour le salarié sur une longue durée (50 ans ou jusqu'à ses 75 ans) doit être assurée, par la plateforme ou par le dépôt sur le compte personnel d'activité. Le salarié peut aussi refuser la dématérialisation (option « papier » sur la fiche agent) |
| Contrats, avenants, diplômes, carte pro | Durée du contrat + 5 ans | Prescription en matière civile et prud'homale |
| NIR | Durée du contrat + 5 ans | Déclarations sociales ; effacement ensuite |
| Rapports mensuels | 5 ans | Alignés sur la paie |
| Planning | 3 ans | Contrôle de la durée du travail |
| Journal d'audit | 1 an (3 ans pour révélation du NIR et corrections de pointage) | Recommandations CNIL sur la journalisation, prolongées pour les actions sensibles |
| Tentatives de connexion | 6 mois | Sécurité |
| Demandes de contact | 2 ans après clôture | |
| Comptes clients | Durée du contrat client + 1 an | |
| Badges (historique, motifs de désactivation) | Durée du contrat + 1 an | Preuve en cas de litige sur un pointage |
| Comptes agents sortis | Accès désactivé à la sortie, données conservées selon les lignes ci-dessus | |

La purge est **automatique** (cron `rgpd:purger`) avec un rapport mensuel des suppressions. L'archivage intermédiaire (base séparée, accès restreint) est possible en option.

### 6.5 Droits des personnes

| Droit | Mise en œuvre |
|---|---|
| Accès / portabilité | Export ZIP chiffré (JSON + documents) depuis la fiche agent, délai légal d'un mois, journalisé |
| Rectification | Demande de modification de profil (§ 3.2.4) ou demande de contact |
| Effacement / limitation | Traité par RH en tenant compte des obligations de conservation ; suppression logique puis purge |
| Opposition (géolocalisation) | Réglage dans « Mes données », effet immédiat |
| Information | Page « Mes données » : notice, durées, contact DPO, historique de mes consentements |

---

## 7. Charte graphique

### 7.1 Intentions

L'interface reprend l'identité du logo : **écusson noir, pégase rouge, fond blanc**. Elle est sobre, dense en information et lisible de nuit.

- **Noir Pegasus** : structure, navigation, autorité.
- **Rouge Pegasus** : accent de marque, utilisé avec parcimonie (action principale « Pointer », élément actif, logo). Il n'est pas employé pour signaler les erreurs, qui ont leur propre rouge, plus orangé, toujours accompagné d'une icône.
- **Gris neutres froids** : surfaces et textes secondaires.
- Typographie technique pour les titres (proche du logotype géométrique), humaniste et très lisible pour le texte.

### 7.2 Logo

| Règle | Spécification |
|---|---|
| Versions | (1) Complète, écusson + logotype, sur fond clair ; (2) Écusson seul (icône PWA, favicon, en-tête mobile) ; (3) Monochrome blanc sur fond noir (mode sombre, borne, PDF en-tête foncé) |
| Zone de protection | Hauteur de la lettre « P » du logotype, sur tous les côtés |
| Taille minimale | Écusson 24 px (écran) / 8 mm (impression) ; logo complet 120 px de large |
| Interdits | Déformation, ombre portée, changement des couleurs, rouge sur fond noir sans liseré blanc, placement sur une photo chargée |
| Fichiers à produire | `logo.svg`, `logo-blanc.svg`, `embleme.svg`, `embleme-blanc.svg`, icônes PNG 192/512, `maskable-512.png` (marge de sécurité 20 %), `apple-touch-icon.png` 180 px, favicon SVG et ICO 32 px. **Une version vectorielle du logo est à fournir par Pegasus Insure** (le fichier transmis est une image matricielle) |

### 7.3 Palette

#### Mode clair

| Rôle | Nom | HEX | Usage |
|---|---|---|---|
| Principale | Noir Pegasus | `#111214` | Barre de navigation, en-têtes, boutons secondaires pleins, texte de titre |
| Accent marque | Rouge Pegasus | `#A51D24` | Bouton « Pointer », CTA primaire, onglet actif, liens d'action importants |
| Accent survol | Rouge profond | `#861519` | Survol et appui du CTA |
| Accent doux | Rouge voile | `#F8E8E9` | Fond de l'élément actif, sélection |
| Secondaire | Graphite | `#2B2F36` | Boutons secondaires, en-têtes de tableaux foncés, barre latérale admin |
| Fond | Gris brume | `#F4F5F7` | Fond d'application |
| Surface | Blanc | `#FFFFFF` | Cartes, modales, tableaux |
| Surface 2 | Gris clair | `#ECEEF1` | Zones secondaires, lignes alternées, champs désactivés |
| Texte | Encre | `#16181D` | Texte principal |
| Texte secondaire | Ardoise | `#5A606B` | Libellés, métadonnées |
| Bordure | Gris perle | `#D5D9DF` | Séparateurs, contours de cartes (décoratifs) |
| Bordure de champ | Gris acier | `#8A919C` | Contour des champs de saisie (contraste ≥ 3:1) |
| Succès | Vert sûreté | `#1E7B4A` (fond `#E7F4EC`) | Pointage validé, point contrôlé, document valide |
| Alerte | Ambre | `#9A5200` (fond `#FDF1E3`) | Retard, échéance < 30 j, à vérifier |
| Erreur | Rouge signal | `#B42318` (fond `#FDECEA`) | Erreur, manqué, expiré, anomalie critique |
| Info | Bleu contrôle | `#1D4ED8` (fond `#E8EEFC`) | Information, échéance < 90 j, statut neutre |
| À venir | Gris | `#6B7280` (fond `#EEF0F3`) | Point à venir, brouillon |

#### Mode sombre (automatique via `prefers-color-scheme`, avec bascule manuelle)

| Rôle | HEX |
|---|---|
| Fond | `#0E0F11` |
| Surface | `#17191D` |
| Surface 2 | `#22252B` |
| Texte | `#ECEDEF` |
| Texte secondaire | `#A7ADB7` |
| Bordure / bordure de champ | `#30343B` / `#6B727D` |
| CTA (fond, texte blanc) | `#C42A31` (survol `#D8363E`) |
| Accent texte (liens) | `#F0686D` |
| Succès / Alerte / Erreur / Info | `#4CC38A` / `#F0A54A` / `#FF7A70` / `#6EA8FE` |

La **borne** utilise toujours le thème sombre, mais le QR code est affiché **noir sur carte blanche** avec une zone de silence de 4 modules, pour une lecture fiable.

#### Contrastes vérifiés (WCAG 2.1 AA : 4,5:1 pour le texte, 3:1 pour le texte large et les composants)

| Paire | Ratio | Résultat |
|---|---|---|
| Encre `#16181D` / Blanc | ≈ 17,8:1 | AAA |
| Ardoise `#5A606B` / Blanc | ≈ 6,3:1 | AA |
| Ardoise `#5A606B` / Fond `#F4F5F7` | ≈ 5,8:1 | AA |
| Blanc / Rouge Pegasus `#A51D24` | ≈ 7,5:1 | AAA |
| Blanc / Noir Pegasus `#111214` | ≈ 18,8:1 | AAA |
| Succès `#1E7B4A` / Blanc | ≈ 5,3:1 | AA |
| Alerte `#9A5200` / Blanc | ≈ 5,9:1 | AA |
| Erreur `#B42318` / Blanc | ≈ 6,6:1 | AA |
| Info `#1D4ED8` / Blanc | ≈ 6,7:1 | AA |
| Bordure de champ `#8A919C` / Blanc | ≈ 3,2:1 | AA (composant) |
| Texte `#ECEDEF` / Surface sombre `#17191D` | ≈ 14,9:1 | AAA |
| Texte sec. `#A7ADB7` / Surface sombre | ≈ 7,8:1 | AAA |
| Blanc / CTA sombre `#C42A31` | ≈ 5,6:1 | AA |
| Accent `#F0686D` / Surface sombre | ≈ 5,8:1 | AA |

Les ratios sont calculés avec la formule de luminance relative WCAG. Ils seront revérifiés pendant la recette avec un outil (Colour Contrast Analyser), y compris sur les fonds teintés des badges.

### 7.4 Typographie (Google Fonts, auto-hébergées en WOFF2)

| Usage | Police | Graisses |
|---|---|---|
| Titres, chiffres clés, boutons | **Saira** (géométrique, technique, en écho au logotype) | 600, 700 |
| Texte courant, formulaires, tableaux | **Inter** | 400, 500, 600 |
| Identifiants, matricules, codes | **JetBrains Mono** | 500 |

| Niveau | Mobile | Desktop | Graisse / interligne |
|---|---|---|---|
| H1 (titre d'écran) | 24 px | 30 px | Saira 700 / 1,2 |
| H2 (section) | 20 px | 24 px | Saira 600 / 1,25 |
| H3 (carte) | 17 px | 18 px | Saira 600 / 1,3 |
| Corps | 16 px | 15 px | Inter 400 / 1,5 |
| Petit (métadonnées) | 14 px | 13 px | Inter 500 / 1,4 |
| Légende / badge | 12 px | 12 px | Inter 600, majuscules espacées 0,04 em |
| Chiffre clé (compteurs) | 32 px | 36 px | Saira 700, chiffres tabulaires |
| Horloge borne | 64 px | — | Saira 700 |

Le corps de texte n'est jamais inférieur à 16 px sur mobile, ce qui évite aussi le zoom automatique d'iOS sur les champs.

### 7.5 Espacements, rayons, ombres, icônes

- **Grille de 4 px** : `4, 8, 12, 16, 24, 32, 48, 64`.
- **Rayons** : 6 px (champs, badges), 10 px (boutons, cartes), 16 px (feuilles mobiles, modales), 999 px (pastilles).
- **Ombres** (discrètes, l'univers sécurité appelle la sobriété) : `sm` pour les cartes, `md` pour les menus, `lg` pour les modales et la barre de navigation mobile.
- **Icônes** : jeu de traits 1,75 px, 24 px (Lucide, licence ISC, SVG en sprite local). Icônes clés : `qr-code`, `scan-line`, `shield-check`, `route`, `map-pin`, `clock`, `calendar`, `file-text`, `wallet`, `message-square`, `alert-triangle`, `user`, `building-2`.
- **Mouvement** : transitions de 150–200 ms, `prefers-reduced-motion` respecté (aucune animation non essentielle).

### 7.6 Composants

| Composant | Spécification |
|---|---|
| **Bouton primaire** | Fond rouge Pegasus, texte blanc, Saira 600 16 px, hauteur 48 px (mobile) / 40 px (desktop), rayon 10 px. Survol : rouge profond. Focus : anneau 3 px `#1D4ED8` décalé de 2 px. Désactivé : opacité 0,45, curseur interdit |
| **Bouton « Pointer »** | Variante XL : hauteur 64 px, pleine largeur, icône QR, libellé dynamique « Pointer mon arrivée » / « Pointer mon départ ». Onglet central de la barre inférieure : disque de 60 px surélevé |
| **Bouton secondaire** | Fond noir Pegasus, texte blanc ; **tertiaire** : contour `#8A919C`, texte encre ; **danger** : fond erreur |
| **Champ** | Hauteur 48 px, bordure 1 px gris acier, rayon 6 px, libellé au-dessus (jamais de libellé en placeholder seul), aide en dessous, erreur en rouge signal avec icône et `aria-describedby` |
| **Carte** | Surface blanche, bordure 1 px gris perle, rayon 10 px, padding 16 px, ombre `sm` |
| **Tableau** | En-tête surface 2, texte 13 px semi-gras ; lignes de 48 px ; survol surface 2 ; tri accessible (`aria-sort`). Sur mobile : transformation en liste de cartes |
| **Badge de statut** | Pastille 12 px majuscules, fond teinté + texte foncé + point coloré : *Présent, En retard, Absent, À vérifier, Contrôlé, Manqué, À venir, Valide, Expire bientôt, Expiré, Nouveau, Lu* |
| **Notification (toast)** | Bas d'écran (mobile) ou haut droite (desktop), icône + texte + fermeture, `role="status"` (ou `alert` pour une erreur), 5 s ou persistante pour une erreur |
| **Bannière** | Pleine largeur dans la page, pour une échéance, le mode hors ligne ou un planning modifié |
| **Navigation mobile** | Barre inférieure 64 px + zone de sécurité (`env(safe-area-inset-bottom)`), 5 onglets icône + libellé 11 px, actif = rouge + trait supérieur |
| **Navigation desktop** | Barre latérale graphite 248 px (repliable à 72 px), groupes : Pilotage, Personnel, Activité, Paramétrage ; barre supérieure : recherche globale, notifications, compte |
| **Progression de ronde** | Anneau ou barre segmentée par point, compteur « 7 / 10 », liste des points avec pastilles d'état |
| **Plan temps réel** | Image du plan + marqueurs ronds 28 px numérotés, couleur d'état **et** icône (✓, !, ✕, …) |

### 7.7 Variables CSS (`public/assets/css/tokens.css`)

```css
:root {
  /* Marque */
  --pg-noir: #111214;
  --pg-rouge: #A51D24;
  --pg-rouge-hover: #861519;
  --pg-rouge-voile: #F8E8E9;
  --pg-graphite: #2B2F36;

  /* Neutres (clair) */
  --c-bg: #F4F5F7;
  --c-surface: #FFFFFF;
  --c-surface-2: #ECEEF1;
  --c-text: #16181D;
  --c-text-2: #5A606B;
  --c-border: #D5D9DF;
  --c-border-input: #8A919C;
  --c-cta: var(--pg-rouge);
  --c-cta-hover: var(--pg-rouge-hover);
  --c-cta-text: #FFFFFF;
  --c-link: var(--pg-rouge);
  --c-nav: var(--pg-noir);
  --c-nav-text: #FFFFFF;
  --c-focus: #1D4ED8;

  /* États */
  --c-success: #1E7B4A;  --c-success-bg: #E7F4EC;
  --c-warning: #9A5200;  --c-warning-bg: #FDF1E3;
  --c-danger:  #B42318;  --c-danger-bg:  #FDECEA;
  --c-info:    #1D4ED8;  --c-info-bg:    #E8EEFC;
  --c-muted:   #6B7280;  --c-muted-bg:   #EEF0F3;

  /* Typographie */
  --font-title: "Saira", "Segoe UI", system-ui, sans-serif;
  --font-body: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, "SFMono-Regular", Consolas, monospace;
  --fs-xs: 0.75rem;   /* 12 */
  --fs-sm: 0.875rem;  /* 14 */
  --fs-md: 1rem;      /* 16 */
  --fs-lg: 1.0625rem; /* 17 */
  --fs-xl: 1.25rem;   /* 20 */
  --fs-2xl: 1.5rem;   /* 24 */
  --fs-3xl: 1.875rem; /* 30 */
  --fs-kpi: 2rem;     /* 32 */
  --lh-tight: 1.2;
  --lh-body: 1.5;

  /* Espacements (grille 4 px) */
  --sp-1: 4px;  --sp-2: 8px;  --sp-3: 12px; --sp-4: 16px;
  --sp-5: 24px; --sp-6: 32px; --sp-7: 48px; --sp-8: 64px;

  /* Rayons */
  --r-sm: 6px; --r-md: 10px; --r-lg: 16px; --r-pill: 999px;

  /* Ombres */
  --sh-sm: 0 1px 2px rgba(17, 18, 20, .06), 0 1px 3px rgba(17, 18, 20, .08);
  --sh-md: 0 4px 12px rgba(17, 18, 20, .10);
  --sh-lg: 0 12px 32px rgba(17, 18, 20, .16);

  /* Dimensions */
  --tap-min: 48px;
  --btn-h: 48px;
  --btn-xl-h: 64px;
  --nav-bottom-h: 64px;
  --sidebar-w: 248px;
  --container-max: 1280px;

  /* Mouvement */
  --t-fast: 150ms ease;
  --t-base: 200ms ease;

  color-scheme: light dark;
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --c-bg: #0E0F11;
    --c-surface: #17191D;
    --c-surface-2: #22252B;
    --c-text: #ECEDEF;
    --c-text-2: #A7ADB7;
    --c-border: #30343B;
    --c-border-input: #6B727D;
    --c-cta: #C42A31;
    --c-cta-hover: #D8363E;
    --c-link: #F0686D;
    --c-nav: #0B0C0E;
    --c-focus: #6EA8FE;
    --pg-rouge-voile: #3A1517;
    --c-success: #4CC38A; --c-success-bg: #12291D;
    --c-warning: #F0A54A; --c-warning-bg: #2D2112;
    --c-danger:  #FF7A70; --c-danger-bg:  #33161A;
    --c-info:    #6EA8FE; --c-info-bg:    #142038;
    --c-muted:   #9AA1AC; --c-muted-bg:   #24272D;
    --sh-sm: 0 1px 2px rgba(0, 0, 0, .5);
    --sh-md: 0 4px 12px rgba(0, 0, 0, .5);
    --sh-lg: 0 12px 32px rgba(0, 0, 0, .6);
  }
}
/* Bascule manuelle (préférence enregistrée dans le profil) */
:root[data-theme="dark"] {
  --c-bg: #0E0F11;
  --c-surface: #17191D;
  --c-surface-2: #22252B;
  --c-text: #ECEDEF;
  --c-text-2: #A7ADB7;
  --c-border: #30343B;
  --c-border-input: #6B727D;
  --c-cta: #C42A31;
  --c-cta-hover: #D8363E;
  --c-link: #F0686D;
  --c-nav: #0B0C0E;
  --c-focus: #6EA8FE;
  --pg-rouge-voile: #3A1517;
  --c-success: #4CC38A; --c-success-bg: #12291D;
  --c-warning: #F0A54A; --c-warning-bg: #2D2112;
  --c-danger:  #FF7A70; --c-danger-bg:  #33161A;
  --c-info:    #6EA8FE; --c-info-bg:    #142038;
  --c-muted:   #9AA1AC; --c-muted-bg:   #24272D;
  --sh-sm: 0 1px 2px rgba(0, 0, 0, .5);
  --sh-md: 0 4px 12px rgba(0, 0, 0, .5);
  --sh-lg: 0 12px 32px rgba(0, 0, 0, .6);
}

@media (prefers-reduced-motion: reduce) {
  :root { --t-fast: 0ms; --t-base: 0ms; }
}
```

---

## 8. Maquettes

Les **mockups HTML/CSS autonomes** de tous les écrans sont livrés dans `maquettes-pegasus-insure.html`. Ce fichier unique, responsive et sans dépendance, est navigable par écran et propose une bascule clair/sombre. Pour afficher le logo, déposez le fichier `logo-pegasus-insure.png` dans le même dossier ; à défaut, un logotype de remplacement s'affiche. Les wireframes ci-dessous fixent la structure et la hiérarchie de chaque écran.

### 8.1 Navigation mobile (agent, chef)

```
┌──────────────────────────────────────┐
│                 (écran)              │
├──────┬──────┬────────┬───────┬───────┤
│  ⌂   │  ▦   │  ╔══╗  │  ▤    │  ☰    │
│Accueil│Planning│ ║QR║ │Docs   │ Plus  │
│      │      │Pointer │       │       │
└──────┴──────┴────────┴───────┴───────┘
  « Plus » : Profil · Fiches de paie · Historique · Contact
             Documents société · Mes données · Déconnexion
```

### 8.2 Écrans agent

**A1 — Connexion**
```
┌──────────────────────────────────────┐
│            [ÉCUSSON PEGASUS]         │
│           PEGASUS INSURE             │
│        Espace collaborateurs         │
│                                      │
│  E-mail                              │
│  [______________________________]    │
│  Mot de passe                    👁   │
│  [______________________________]    │
│  [ ] Rester connecté sur cet appareil│
│                                      │
│  [        SE CONNECTER          ]    │
│        Mot de passe oublié ?         │
│                                      │
│  Urgence : ☎ 04 00 00 00 00          │
└──────────────────────────────────────┘
```

**A2 — Double authentification**
```
┌──────────────────────────────────────┐
│ ←  Vérification                      │
│                                      │
│  Saisissez le code à 6 chiffres de   │
│  votre application d'authentification│
│                                      │
│   [ 4 ][ 8 ][ 2 ][ 9 ][ 1 ][ _ ]     │
│                                      │
│  [x] Faire confiance à cet appareil  │
│      pendant 30 jours                │
│  [          VALIDER             ]    │
│  Utiliser un code de secours         │
└──────────────────────────────────────┘
```

**A3 — Accueil**
```
┌──────────────────────────────────────┐
│ [▲] Bonjour Karim            🔔 2    │
├──────────────────────────────────────┤
│ ┌──────────────────────────────────┐ │
│ │ ● HORS SERVICE                   │ │
│ │ Prochaine vacation               │ │
│ │ Ce soir 21:00 – 07:00            │ │
│ │ Entrepôt Logista — Nice Ouest    │ │
│ └──────────────────────────────────┘ │
│ ┌──────────────────────────────────┐ │
│ │  ▣  POINTER MON ARRIVÉE          │ │  ← CTA rouge 64 px
│ └──────────────────────────────────┘ │
│ ⚠ Carte pro : expire dans 24 jours   │
│ ▤ 1 document à lire : Note 2027-03   │
│ € Fiche de paie de février disponible│
│ ⇅ 0 pointage en attente de synchro   │
├──────┬──────┬────────┬───────┬───────┤
│  ⌂   │  ▦   │  (QR)  │  ▤    │  ☰    │
└──────┴──────┴────────┴───────┴───────┘
   En service : la carte devient
   « ● EN SERVICE depuis 21:02 · 2 h 14 »
   + [ ⟲ DÉMARRER LA RONDE DE 22:00 ]
```

**A4 — Scan QR de la borne**
```
┌──────────────────────────────────────┐
│ ✕                    Pointage    🔦  │
│ ┌──────────────────────────────────┐ │
│ │                                  │ │
│ │      ┌──────────────────┐        │ │
│ │      │                  │        │ │
│ │      │   (flux caméra)  │        │ │
│ │      │                  │        │ │
│ │      └──────────────────┘        │ │
│ │                                  │ │
│ └──────────────────────────────────┘ │
│  Visez le QR code affiché sur la     │
│  borne du poste de sécurité.         │
│  📍 Position : précision 12 m ✓       │
└──────────────────────────────────────┘
```

**A5 — Confirmation de pointage**
```
┌──────────────────────────────────────┐
│                                      │
│               ( ✓ )                  │  ← vert, plein écran
│          ARRIVÉE ENREGISTRÉE         │
│                                      │
│     Entrepôt Logista — Nice Ouest    │
│          Mercredi 3 mars 2027        │
│               21:02                  │
│                                      │
│   Vacation 21:00 – 07:00 · Retard 2 min (toléré)
│                                      │
│  [        RETOUR À L'ACCUEIL     ]   │
│     Erreur ? Annuler (1:52)          │
└──────────────────────────────────────┘
  Variante « à vérifier » : fond ambre, ⚠, raison
  (« Position hors du périmètre du site »).
  Variante hors ligne : ⇅ « Enregistré, sera transmis ».
```

**A6 — Ronde en cours**
```
┌──────────────────────────────────────┐
│ ←  Ronde 22:00 · Logista      ⋮      │
├──────────────────────────────────────┤
│  ████████████████░░░░░░  7 / 10      │
│  Démarrée 22:03 · 18 min · max 45    │
├──────────────────────────────────────┤
│ ✓ 1 Accueil principal         22:04  │
│ ✓ 2 Quai de chargement A      22:07  │
│ …                                    │
│ ✓ 7 Local TGBT                22:19  │
│ ○ 8 Issue de secours Nord   ← suivant│
│ ○ 9 Parking -1                       │
│ ○ 10 Toiture (accès échelle)         │
├──────────────────────────────────────┤
│ [ ▣ SCANNER LE POINT ] [ ⚠ SIGNALER ]│
│        [ Clôturer la ronde ]         │
└──────────────────────────────────────┘
```

**A7 — Signalement d'anomalie**
```
┌──────────────────────────────────────┐
│ ✕  Signaler une anomalie             │
│ Point : 8 Issue de secours Nord      │
│ Catégorie  [ Accès ouvert        ▾ ] │
│ Gravité                              │
│ ( Info ) ( Mineure ) (●Majeure) ( Critique )
│ Description                          │
│ [ Porte trouvée entrouverte, gâche ] │
│ [ endommagée…                      ] │
│ Photos  [📷 +] [img] [img]            │
│ [x] Visible par le client            │
│ [        ENVOYER LE SIGNALEMENT   ]  │
└──────────────────────────────────────┘
```

**A8 — Historique de pointage**
```
┌──────────────────────────────────────┐
│ ←  Mes heures         [ Mars 2027 ▾ ]│
│ ┌──────────┬──────────┬───────────┐  │
│ │ Pointées │ Prévues  │ Écart     │  │
│ │ 142 h 30 │ 144 h 00 │ − 1 h 30  │  │
│ └──────────┴──────────┴───────────┘  │
│ Nuit 96 h · Dimanche 24 h · Férié 0 h│
│ ──────────────────────────────────── │
│ Mer 03  Logista   21:02 → 07:01 9h59 │
│         ● Validé                     │
│ Jeu 04  Logista   20:58 → 07:00 10h02│
│ Sam 06  Port Lympia 07:15 → 19:00    │
│         ⚠ Retard 15 min              │
└──────────────────────────────────────┘
```

**A9 — Profil**
```
┌──────────────────────────────────────┐
│ ←  Mon profil                        │
│  (photo)  Karim BENALI               │
│           Agent de sécurité · ADS    │
│           Matricule PGS-0042         │
│ ▸ Identité            (consultation) │
│ ▸ Coordonnées                         │
│ ▸ N° sécu : 1 •• •• •• ••• ••• 42    │
│ ▸ Contrat : CDI depuis 12/09/2022    │
│ ── Conformité ─────────────────────  │
│ Carte pro CAR-006-2029-…  ⚠ 24 j     │
│ SST  recyclage 11/2027    ● Valide   │
│ SSIAP 1                   ● Valide   │
│ [ DEMANDER UNE MODIFICATION ]        │
└──────────────────────────────────────┘
```

**A10 — Mes documents**
```
┌──────────────────────────────────────┐
│ ←  Mes documents                     │
│ [Tous][Contrat][Diplômes][Carte pro] │
│ ▤ Contrat de travail CDI    PDF  ⤓   │
│   12/09/2022                         │
│ ▤ Avenant n°2 – temps plein PDF  ⤓   │
│ ▤ Diplôme SSIAP 1           PDF  ⤓   │
│   valide jusqu'au 05/2028            │
│ ▤ Carte pro (recto/verso)   JPG  ⤓   │
└──────────────────────────────────────┘
```

**A11 — Fiches de paie**
```
┌──────────────────────────────────────┐
│ ←  Mes fiches de paie   [ 2027 ▾ ]   │
│ ▤ Février 2027     NOUVEAU    ⤓      │
│ ▤ Janvier 2027                ⤓      │
│ ── 2026 ───────────────────────────  │
│ ▤ Décembre 2026               ⤓      │
│ ▤ Novembre 2026               ⤓      │
│ Les fiches sont conservées en ligne. │
└──────────────────────────────────────┘
```

**A12 — Planning**
```
┌──────────────────────────────────────┐
│ ←  Planning   [Semaine|Mois]  ‹ › │
│ ⓘ Planning modifié le 01/03 à 14:10  │
│ LUN 01  Repos                         │
│ MAR 02  Repos                         │
│ MER 03  21:00 → 07:00  Logista       │
│         Poste : rondier · Nuit       │
│ JEU 04  21:00 → 07:00  Logista       │
│ VEN 05  —                            │
│ SAM 06  07:00 → 19:00  Port Lympia   │
│ DIM 07  07:00 → 19:00  Port Lympia   │
│ Total semaine : 44 h                 │
└──────────────────────────────────────┘
```

**A13 — Documents de la société**
```
┌──────────────────────────────────────┐
│ ←  Documents société                 │
│ ● À LIRE (1)                         │
│ ▤ Note de service 2027-03 — tenue    │
│   Publiée le 28/02 · À lire  →       │
│ ── Lus ────────────────────────────  │
│ ▤ Règlement intérieur v4  ✓ lu 02/01 │
│ ▤ Consignes site Logista  ✓ lu 15/01 │
│                                      │
│ (détail) [ aperçu PDF ]              │
│ [ J'AI LU ET PRIS CONNAISSANCE ]     │
└──────────────────────────────────────┘
```

**A14 — Contact et suivi**
```
┌──────────────────────────────────────┐
│ ←  Contact                           │
│ [☎ Permanence 24/7] [✉ RH] [✉ Paie]  │
│ ── Nouvelle demande ──────────────── │
│ Catégorie [ Paie ▾ ]                 │
│ Objet     [_____________________]    │
│ Message   [_____________________]    │
│ [📎 Joindre]          [ ENVOYER ]    │
│ ── Mes demandes ──────────────────── │
│ #128 Paie – heures de nuit   EN COURS│
│ #117 Planning – échange      RÉSOLUE │
└──────────────────────────────────────┘
```

### 8.3 Borne de pointage

**B1 — Borne (tablette paysage, thème sombre)**
```
┌──────────────────────────────────────────────────────────────┐
│ [ÉCUSSON] PEGASUS INSURE           Entrepôt Logista — Nice O. │
│                                                              │
│      ┌────────────────────┐        21:02                     │
│      │ ██ ▄▄ █▀█ ▄ ██ ▀▀  │        mercredi 3 mars 2027       │
│      │ ▄▀ ██ ▄ ▀█▄ ▀ ██▄  │                                  │
│      │ (QR dynamique sur  │        Scannez avec l'application │
│      │  carte blanche)    │        Pegasus pour pointer votre │
│      │ ██▄ ▀ █▀▄ ██ ▄▀ █  │        arrivée ou votre départ.   │
│      └────────────────────┘                                  │
│      ▬▬▬▬▬▬▬▬▬▬▬▬░░░░░ renouvelé dans 23 s                    │
│ ● En ligne · Borne « PC sécurité »                           │
└──────────────────────────────────────────────────────────────┘
```

**B2 — Borne en mode badge : saisie du PIN**
```
┌──────────────────────────────────────────────────────────────┐
│ [ÉCUSSON] PEGASUS INSURE           Entrepôt Logista — Nice O. │
│                                                              │
│        ( ✓ ) Badge reconnu                                   │
│        Bonjour Karim B.               ┌─────┬─────┬─────┐    │
│                                       │  1  │  2  │  3  │    │
│        Saisissez votre code PIN       ├─────┼─────┼─────┤    │
│                                       │  4  │  5  │  6  │    │
│          ●   ●   ○   ○                ├─────┼─────┼─────┤    │
│                                       │  7  │  8  │  9  │    │
│                                       ├─────┼─────┼─────┤    │
│        [ Annuler ]                    │  ⌫  │  0  │  ✓  │    │
│                                       └─────┴─────┴─────┘    │
│ Retour automatique à l'accueil dans 20 s                      │
└──────────────────────────────────────────────────────────────┘
  Écran d'accueil de la borne (B1) : bandeau « ou présentez votre badge » sous le QR.
  Après validation : plein écran vert « Arrivée enregistrée — 21:02 », 4 s.
```

### 8.4 Écrans administration (desktop ≥ 1024 px)

Gabarit commun :
```
┌────────────┬─────────────────────────────────────────────────┐
│ [▲] PEGASUS│ 🔎 Rechercher un agent, un site…      🔔 3  (RH)▾│
│────────────├─────────────────────────────────────────────────┤
│ PILOTAGE   │                                                 │
│ ▸ Tableau  │                 (contenu de l'écran)            │
│ ▸ Pointages│                                                 │
│ ▸ Rondes   │                                                 │
│ PERSONNEL  │                                                 │
│ ▸ Agents   │                                                 │
│ ▸ Planning │                                                 │
│ ▸ Paie     │                                                 │
│ ▸ Documents│                                                 │
│ ACTIVITÉ   │                                                 │
│ ▸ Rapports │                                                 │
│ ▸ Demandes │                                                 │
│ PARAMÉTRAGE│                                                 │
│ ▸ Sites    │                                                 │
│ ▸ Clients  │                                                 │
│ ▸ Rôles    │                                                 │
│ ▸ Audit    │                                                 │
└────────────┴─────────────────────────────────────────────────┘
```

**D1 — Tableau de bord**
```
│ Tableau de bord — mercredi 3 mars 2027 · 22:15                  │
│ ┌─────────┬─────────┬─────────┬─────────┬─────────┬──────────┐ │
│ │Attendus │Présents │Retards  │Absents  │Départs  │À vérifier│ │
│ │   42    │   39    │   2     │   1     │manq. 0  │    3     │ │
│ └─────────┴─────────┴─────────┴─────────┴─────────┴──────────┘ │
│ ┌ Rondes du jour ────────────┐ ┌ Anomalies ouvertes ──────────┐ │
│ │ 18/20 conformes · 1 retard │ │ ● 1 critique  ● 2 majeures   │ │
│ │ 1 non démarrée (Port 22:00)│ │ Logista – porte forcée 22:19 │ │
│ └────────────────────────────┘ └──────────────────────────────┘ │
│ ┌ Échéances ─────────────────┐ ┌ À traiter ───────────────────┐ │
│ │ Expirés 1 · 7 j 2 · 30 j 5 │ │ 4 corrections · 6 demandes   │ │
│ │ T. Martin – carte pro 6 j  │ │ 1 borne hors ligne (Lympia)  │ │
│ └────────────────────────────┘ └──────────────────────────────┘ │
```

**D2 — Liste des agents**
```
│ Agents (152)          [⤒ Importer CSV]  [+ Nouvel agent]         │
│ 🔎 [nom, matricule…]  Site [Tous ▾] Statut [Actifs ▾] Échéance [▾]│
│ ┌──────────┬───────────────┬──────────┬────────────┬──────────┐ │
│ │Matricule │ Nom           │ Fonction │ Carte pro  │ Statut   │ │
│ ├──────────┼───────────────┼──────────┼────────────┼──────────┤ │
│ │PGS-0042  │ BENALI Karim  │ ADS      │ ⚠ 24 j     │ ● Actif  │ │
│ │PGS-0051  │ MARTIN Théo   │ SSIAP 1  │ ✕ 6 j      │ ● Actif  │ │
│ │PGS-0063  │ NGUYEN Lina   │ Chef éq. │ ● 2029     │ ● Actif  │ │
│ └──────────┴───────────────┴──────────┴────────────┴──────────┘ │
│                                         ‹ 1 2 3 … 8 ›           │
```

**D3 — Fiche agent**
```
│ ← Agents / BENALI Karim  PGS-0042   ● Actif   [Actions ▾]       │
│ [Identité][Contrat][Conformité][Documents][Pointages][Rondes][Paie]
│ ┌ Identité ─────────────────────┐ ┌ Conformité ───────────────┐ │
│ │ Né le 14/05/1990 à Marseille  │ │ Carte pro CAR-006-…  ⚠ 24j│ │
│ │ Nationalité : française       │ │ SST  ● 11/2027            │ │
│ │ N° sécu 1 •• •• •• ••• ••• 42 │ │ SSIAP 1 ● 05/2028         │ │
│ │          [👁 Afficher]  🔒     │ │ [+ Qualification]         │ │
│ │ 12 rue …, 06200 Nice          │ └───────────────────────────┘ │
│ └───────────────────────────────┘ ┌ Affectations ─────────────┐ │
│                                   │ Logista (principal)       │ │
│                                   │ Port Lympia               │ │
│                                   └───────────────────────────┘ │
```

**D4 — Création d'agent (formulaire en étapes)**
```
│ Nouvel agent    ① Identité ─ ② Contrat ─ ③ Conformité ─ ④ Accès │
│ ┌──────────────────────────────────────────────────────────────┐ │
│ │ Civilité ( )M ( )Mme   Nom [________]  Prénom [________]     │ │
│ │ Date naiss. [__/__/____]  Lieu [__________] Nationalité [▾]  │ │
│ │ N° sécurité sociale [_ __ __ __ ___ ___ __] 🔒 chiffré        │ │
│ │ Adresse [______________________] CP [_____] Ville [_______]  │ │
│ │ E-mail [______________]  Téléphone [__________]              │ │
│ │ Matricule [PGS-0153] (généré, modifiable)                    │ │
│ └──────────────────────────────────────────────────────────────┘ │
│                         [Annuler]  [Enregistrer brouillon] [Suivant →]
│ Étape ④ : rôle, 2FA requise, [x] envoyer l'invitation            │
```

**D5 — Import des fiches de paie**
```
│ Paie › Import — Février 2027                                     │
│ ① Déposer ─── ② Contrôler ─── ③ Publier                          │
│ ┌──────────────────────────────────────────────────────────────┐ │
│ │  ⤒  Glissez les PDF ou un ZIP ici (≤ 5 Mo par fichier)        │ │
│ └──────────────────────────────────────────────────────────────┘ │
│ 152 fichiers · ✔ 148 associés · ⚠ 2 ambigus · ✖ 1 introuvable · ⧉ 1 doublon
│ ┌───────────────────────────┬──────────────────┬───────────────┐ │
│ │ Fichier                   │ Agent            │ Statut        │ │
│ │ PGS-0042_202702.pdf       │ BENALI Karim     │ ✔ matricule   │ │
│ │ martin_202702.pdf         │ [ Choisir ▾ ]    │ ⚠ 2 MARTIN    │ │
│ │ scan0001.pdf              │ [ Choisir ▾ ]    │ ✖ introuvable │ │
│ └───────────────────────────┴──────────────────┴───────────────┘ │
│                      [Annuler l'import]  [Publier 148 fiches →]  │
```

**D6 — Planning**
```
│ Planning  Site [Logista ▾]  Semaine 9 ‹ ›  [Dupliquer] [Publier] │
│ ┌──────────┬──────┬──────┬──────┬──────┬──────┬──────┬──────┐   │
│ │ Agent    │Lun 01│Mar 02│Mer 03│Jeu 04│Ven 05│Sam 06│Dim 07│   │
│ ├──────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┤   │
│ │ BENALI K.│      │      │21–07 │21–07 │      │      │      │   │
│ │ MARTIN T.│07–19 │07–19 │      │ ⚠    │21–07 │21–07 │      │   │
│ │ NGUYEN L.│21–07 │21–07 │      │      │07–19 │07–19 │07–19 │   │
│ │ ░ Non couvert                 21–07 ░                       │   │
│ └──────────┴──────┴──────┴──────┴──────┴──────┴──────┴──────┘   │
│ ⚠ MARTIN T. jeu 04 : repos < 11 h · 3 vacations en brouillon     │
```

**D7 — Suivi des pointages**
```
│ Pointages  [Aujourd'hui ▾] Site [Tous ▾] Statut [Tous ▾] [⤓ CSV] │
│ ┌──────────┬───────────┬────────┬────────┬────────┬────────────┐ │
│ │ Agent    │ Site      │ Prévu  │Arrivée │ Départ │ Statut     │ │
│ │ BENALI K.│ Logista   │ 21–07  │ 21:02  │   —    │ ● Présent  │ │
│ │ DIALLO M.│ Port      │ 19–07  │ 19:16  │   —    │ ⚠ Retard 16│ │
│ │ ROUX P.  │ Clinique  │ 20–08  │   —    │   —    │ ✕ Absent   │ │
│ │ SOW A.   │ Lympia    │ 18–06  │ 18:01  │   —    │ ⚠ Hors zone│ │
│ └──────────┴───────────┴────────┴────────┴────────┴────────────┘ │
│ (ligne) [Corriger] → modale : type, heure, motif* , [Proposer]   │
```

**D8 — Suivi des rondes et plan temps réel**
```
│ Rondes › Logista — temps réel  ⟳ 20 s        [Rapport ⤓]          │
│ ┌───────────────────────────────────────┐ ┌ Ronde 22:00 ───────┐ │
│ │  (plan du site)                       │ │ K. BENALI · 22:03  │ │
│ │    ①✓      ②✓          ③✓            │ │ 7/10 · 18 min      │ │
│ │                 ④✓   ⑤✓               │ │ ── Flux ────────── │ │
│ │   ⑥✓     ⑦✓                           │ │ 22:19 ⑦ TGBT ✓     │ │
│ │                  ⑧!  ⑨○   ⑩○           │ │ 22:19 ⚠ Anomalie   │ │
│ │                                       │ │      majeure ⑧     │ │
│ └───────────────────────────────────────┘ │ 22:15 ⑥ Parking ✓  │ │
│ ✓ Contrôlé  ! En retard  ✕ Manqué  ○ À venir └──────────────────┘ │
│ [Vue par ronde] [Vue par emplacement] [Vue par agent]            │
```

**D9 — Rapports mensuels**
```
│ Rapports mensuels   Période [Février 2027 ▾]  Site [Tous ▾]      │
│ [ Générer pour tous (152) ]  Lot en cours : ███████░░░ 104/152    │
│ ┌──────────┬────────┬────────┬───────┬────────┬────────────────┐ │
│ │ Agent    │ Pointé │ Prévu  │ Nuit  │ Statut │ Actions        │ │
│ │ BENALI K.│142h30  │144h00  │ 96h   │ Généré │ ⤓ ✉ ⟳          │ │
│ │ MARTIN T.│151h00  │151h00  │ 48h   │ Envoyé │ ⤓              │ │
│ └──────────┴────────┴────────┴───────┴────────┴────────────────┘ │
│ [⤓ ZIP du lot]  [⤓ Variables de paie CSV]  [🔒 Clôturer février] │
```

**D10 — Documents de la société**
```
│ Documents société                      [+ Publier un document]   │
│ ┌────────────────────────┬─────────┬──────────┬────────────────┐ │
│ │ Titre                  │ Version │ Ciblage  │ Accusés        │ │
│ │ Règlement intérieur    │ v4      │ Tous     │ ████████░ 92 % │ │
│ │ Note 2027-03 tenue     │ v1      │ Tous     │ ███░░░░░░ 35 % │ │
│ │ Consignes Logista      │ v3      │ 1 site   │ █████████ 100 %│ │
│ └────────────────────────┴─────────┴──────────┴────────────────┘ │
│ (détail) Non lus : 98 agents  [Relancer]  [⤓ Preuves CSV]        │
```

**D11 — Sites, bornes et points de contrôle**
```
│ Sites › Logista — Nice Ouest    Client : Logista SAS  ● Actif     │
│ [Infos][Bornes][Points de contrôle][Plan][Rondes planifiées]      │
│ Points de contrôle (10)    [+ Point] [🖨 Planche d'étiquettes]    │
│ ┌────┬──────────┬──────────────────────┬───────┬────┬──────────┐ │
│ │ #  │ ID       │ Nom                  │ Zone  │ QR │ Actions  │ │
│ │ 1  │ PC-0101  │ Accueil principal    │ RDC   │ v1 │ ▣ ⟳ ✎ ⏻  │ │
│ │ 8  │ PC-0108  │ Issue de secours Nord│ RDC   │ v2 │ ▣ ⟳ ✎ ⏻  │ │
│ └────┴──────────┴──────────────────────┴───────┴────┴──────────┘ │
│ Bornes : « PC sécurité » ● en ligne (il y a 2 min) [Révoquer]    │
│          [+ Enrôler une borne] → code 6 chiffres valable 15 min  │
```

**D12 — Journal d'audit**
```
│ Journal d'audit   Du [01/03] au [03/03]  Action [Toutes ▾] [⤓]   │
│ Intégrité de la chaîne : ✓ vérifiée le 03/03 à 02:30             │
│ ┌──────────────────┬───────────┬────────────────────┬─────────┐  │
│ │ Date             │ Utilisat. │ Action             │ Objet   │  │
│ │ 03/03 14:12:08   │ S. Durand │ agent.nir.reveler  │ PGS-0042│  │
│ │ 03/03 11:40:51   │ A. Admin  │ point.qr.regenerer │ PC-0108 │  │
│ │ 03/03 09:02:17   │ S. Durand │ pointage.corriger  │ #88412  │  │
│ └──────────────────┴───────────┴────────────────────┴─────────┘  │
```

**D13 — Fiche agent, onglet Badge**
```
│ ← Agents / BENALI Karim  PGS-0042  [Identité]…[Badge]…           │
│ ┌ Badge B-000042 ── ● Actif ───────┐ ┌ Aperçu ───────────────────┐ │
│ │ UID ••••3F9A · émis le 12/09/22  │ │ ┌───────────┐ ┌──────────┐ │ │
│ │ Dernière utilisation 21:02       │ │ │(photo) ▬▬ │ │ CNAPS    │ │ │
│ │ PIN : défini · 0 erreur          │ │ │BENALI K.  │ │ CAR-006… │ │ │
│ │ [🖨 Réimprimer] [⟳ Remplacer]    │ │ │ADS        │ │ exp 03/27│ │ │
│ │ [Réinitialiser le PIN]           │ │ └───────────┘ └──────────┘ │ │
│ │ [⛔ Déclarer perdu / volé]       │ │   Recto         Verso      │ │
│ └──────────────────────────────────┘ └───────────────────────────┘ │
│ Historique : 12/09/22 émis · 03/03/27 21:02 badgeage Logista …    │
```

### 8.5 Portail client

**C1 — Tableau de bord client**
```
┌────────────────────────────────────────────────────────────────┐
│ [▲] PEGASUS INSURE · Espace client      Logista SAS   (compte)▾│
├────────────────────────────────────────────────────────────────┤
│ Vos sites                                                      │
│ ┌ Entrepôt Nice Ouest ──────────┐ ┌ Plateforme Cannes ────────┐ │
│ │ ● 2 agents en poste           │ │ ● 1 agent en poste        │ │
│ │ Dernière ronde 22:03 · 10/10  │ │ Dernière ronde 21:30 ⚠ 8/9│ │
│ │ Conformité 7 j : 98 %         │ │ Conformité 7 j : 91 %     │ │
│ │ Anomalies ouvertes : 1        │ │ Anomalies ouvertes : 0    │ │
│ │ [Plan temps réel] [Rapports]  │ │ [Plan temps réel][Rapports]│ │
│ └───────────────────────────────┘ └───────────────────────────┘ │
│ Dernières anomalies                                             │
│ 03/03 22:19 · Nice Ouest · Issue Nord · Majeure · En cours      │
└────────────────────────────────────────────────────────────────┘
```

---

## 9. Contraintes

### 9.1 Performance

| Indicateur | Cible |
|---|---|
| Scan → écran de confirmation (4G) | ≤ 2 s au 95ᵉ percentile |
| Premier affichage de l'accueil agent (4G, cache vide) | ≤ 3 s ; ≤ 1 s en cache (PWA) |
| Temps de réponse API `/api/v1/*` | ≤ 300 ms en médiane, ≤ 800 ms au 95ᵉ percentile |
| Pages back-office | ≤ 1,5 s (liste de 50 lignes paginée côté serveur) |
| Rapport de rondes mensuel (1 000 passages) | ≤ 30 s |
| Rapports mensuels en masse (150 agents) | ≤ 15 min, en tâche de fond |
| Pic de charge | 60 pointages simultanés (relèves à 06:00, 07:00, 19:00, 21:00) sans dégradation |
| Poids de page agent | ≤ 300 Ko transférés hors polices (première visite), ≤ 50 Ko ensuite |

### 9.2 Accessibilité

- Objectif **WCAG 2.1 niveau AA**, appuyé sur la méthode RGAA 4.1 pour l'audit. La plateforme est un outil interne et l'obligation légale RGAA ne s'applique pas nécessairement à Pegasus Insure, mais le niveau AA est retenu comme exigence de qualité. Les agents utilisent l'application de nuit, en mouvement, parfois avec des gants : la lisibilité et la taille des cibles sont donc critiques.
- Navigation complète au clavier (back-office), focus visible, ordre logique, liens d'évitement.
- Libellés explicites, erreurs associées aux champs, messages de statut annoncés (`aria-live`).
- Contrastes conformes au § 7.3, information jamais portée par la seule couleur.
- Zoom à 200 % sans perte de contenu, orientation libre (sauf borne).
- Audit sur un échantillon de 15 écrans représentatifs pendant la recette, corrections avant mise en production.

### 9.3 Compatibilité

| Cible | Versions |
|---|---|
| Android | Chrome 2 dernières versions majeures, Samsung Internet récent ; Android 9+ |
| iOS / iPadOS | Safari 16.4+ (PWA installée et navigateur) |
| Desktop | Chrome, Edge, Firefox, Safari — 2 dernières versions majeures |
| Bornes | Tablette Android 10+ avec Chrome, mode kiosque ; lecteur NFC USB (toute tablette) ou NFC intégré (Chrome Android uniquement) |
| Résolutions | 360 px → 1920 px ; borne 1024 × 600 minimum |

Un panel de test physique est défini en début de projet : au moins un Android d'entrée de gamme, un Android récent, un iPhone récent, un iPhone ancien (iOS 16) et une tablette de borne.

### 9.4 Hébergement et exploitation

- Hébergement **en France** (o2switch), donc dans l'UE. Aucun service tiers hors UE : pas de CDN externe, polices et bibliothèques servies localement.
- Environnements : **recette** (sous-domaine dédié, données fictives, accès protégé) et **production**. Les deux tiennent sur le même compte o2switch dans des dossiers et bases séparés.
- Supervision : sonde de disponibilité externe (service UE, toutes les 5 min) sur `/sante` (réponse 200 si la base et le stockage répondent), alerte e-mail/SMS.
- Disponibilité cible : 99,5 % mensuelle, hors maintenances annoncées. Le mode hors ligne absorbe les courtes indisponibilités.

---

## 10. Planning, livrables, tests et recette

### 10.1 Planning en phases (13 semaines)

Hypothèse de démarrage : **lundi 9 novembre 2026**, mise en production générale **vendredi 5 février 2027**. La semaine du 28 décembre est un tampon (congés).

| Semaine | Dates | Phase | Contenu | Jalon |
|---|---|---|---|---|
| S1 | 09/11 | Cadrage | Ateliers de validation, maquettes HTML finalisées, paramètres métier (tolérances, majorations), commande des tablettes et du matériel badge (lecteurs, cartes, imprimante), information du CSE, lancement de l'AIPD | **J1 — Maquettes et CdC validés** |
| S2 | 16/11 | Socle | Noyau MVC, routeur, sécurité, authentification, 2FA, rôles et permissions, audit, environnement de recette, déploiement automatisé | |
| S3 | 23/11 | Personnel | Fiche agent, NIR chiffré, documents, qualifications, carte pro, alertes d'échéance, import CSV | |
| S4 | 30/11 | Sites | Clients, sites, bornes (enrôlement, QR dynamique, battement), QR statique, lecteurs NFC des bornes | |
| S5 | 07/12 | Pointage | PWA, scan, géolocalisation, anti-fraude, file hors ligne, synchronisation ; badges NFC (émission, impression, PIN, pointage à la borne) | |
| S6 | 14/12 | Planning | Planning, conflits, publication, rapprochement, suivi du jour, corrections | **J2 — Pilote pointage QR et badge sur 2 sites** |
| S7 | 21/12 | Rondes (1) | Points de contrôle, QR, étiquettes, plan, modèles, génération | |
| — | 28/12 | Tampon | Congés, correctifs du pilote | |
| S8 | 04/01 | Rondes (2) | Exécution PWA, anomalies, anti-fraude, plan temps réel, alertes | |
| S9 | 11/01 | RH | Heures et majorations, export paie, import des fiches de paie, documents société, contact, notifications | **J3 — Pilote rondes sur 2 sites** |
| S10 | 18/01 | Restitution | Rapports mensuels, rapports de rondes, portail client, tableaux de bord | |
| S11 | 25/01 | Qualité | Test d'intrusion, performance, accessibilité, purge RGPD, recette fonctionnelle complète | **J4 — PV de recette** |
| S12 | 01/02 | Déploiement | Reprise des agents, impression et remise des badges, équipement des sites, formation, mise en production générale | **J5 — Mise en production** |
| +4 sem. | 08/02 → 05/03 | Hypercare | Support renforcé, correctifs, ajustement des seuils, premier rapport mensuel réel (février) | |

**Gestion du risque de délai** : si le jalon J2 glisse de plus d'une semaine, les fonctions **C** sont reportées en V1.1 (mars 2027) sans toucher à la date de mise en production. Il s'agit notamment de : glisser-déposer planning, import CSV de vacations, statistiques avancées du portail client, badge PWA, validation des corrections par le chef.

### 10.2 Organisation

| Rôle | Charge |
|---|---|
| Chef de projet / UX (prestataire) | ~30 % sur 13 semaines |
| Développeur full-stack senior (lead) | 100 % |
| Développeur full-stack | ~80 % |
| Référent métier Pegasus (RH ou exploitation) | 0,5 j/semaine + ateliers + recette |
| Administrateur Pegasus | Recette, paramétrage, équipement des sites |

Comité de suivi hebdomadaire (30 min), démonstration toutes les 2 semaines sur l'environnement de recette.

### 10.3 Livrables

| Livrable | Format | Échéance |
|---|---|---|
| Cahier des charges validé | Markdown / PDF | S1 |
| Maquettes HTML/CSS | HTML | S1 |
| Code source complet, historique Git, droits de propriété cédés à Pegasus Insure | Dépôt Git privé | Continu, version finale S12 |
| Scripts SQL (migrations, seeds) | SQL | S12 |
| Documentation technique : installation sur o2switch, configuration, cron, sauvegarde et restauration, rotation des clés | Markdown | S12 |
| Documentation d'exploitation et manuel administrateur / RH | PDF | S12 |
| Guide agent (fiche 1 page + vidéo de 3 min « pointer et faire une ronde ») | PDF + MP4 | S12 |
| Cahier de recette et PV | Tableur / PDF | S11 |
| Rapport de test d'intrusion et plan de correction | PDF | S11 |
| Éléments RGPD : fiches registre pré-remplies, éléments techniques de l'AIPD, notice d'information salariés (projet) | Docx | S11 |
| Planches d'étiquettes QR de tous les sites pilotes | PDF | S6 / S9 |
| Gabarit d'impression des badges (CR80) et procédure d'émission | PDF | S5 |

### 10.4 Stratégie de tests

| Niveau | Contenu | Outil |
|---|---|---|
| Unitaire | Jetons HMAC, ventilation des heures (changements d'heure, minuit, fériés), anti-fraude, conflits de planning, association des fiches de paie, chiffrement NIR, permissions | PHPUnit (dépendance de développement uniquement) — couverture ≥ 70 % des services métier |
| Intégration | Contrôleurs + base de test : parcours pointage, ronde, import paie, accès croisés (IDOR) pour chaque ressource | PHPUnit + base MariaDB de test |
| Fonctionnel | Scénarios issus des critères d'acceptation (§ 4), un cas par critère | Cahier de recette |
| Terminaux | Matrice § 9.3 : scan en faible luminosité, QR abîmé, mode avion, bascule réseau, PWA installée | Panel physique |
| Charge | 60 pointages simultanés et 20 rondes en parallèle sur l'environnement de recette | Outil de test de charge (k6 ou équivalent, poste de test) |
| Sécurité | Revue de code, OWASP Top 10, test d'intrusion externe en boîte grise | Prestataire tiers |
| Accessibilité | Échantillon de 15 écrans, contrastes, clavier, lecteur d'écran (NVDA, VoiceOver) | Grille RGAA |

### 10.5 Recette

- **Recette interne** par le prestataire, puis **recette utilisateur** par Pegasus (RH, admin, 2 chefs d'équipe, 4 agents volontaires) sur l'environnement de recette avec des données fictives.
- Classement des anomalies : **bloquante** (fonction indisponible ou faille de sécurité), **majeure** (fonction dégradée sans contournement), **mineure**.
- **Critères de prononciation** : 0 bloquante, 0 majeure sur les modules M, au plus 5 majeures disposant d'un contournement sur les modules S/C, 100 % des critères d'acceptation M validés, test d'intrusion sans faille critique ou élevée ouverte.
- **Pilotes** : 2 sites en pointage (S6), puis rondes (S9), pendant au moins 10 jours chacun, avec collecte des retours terrain (questionnaire de 5 questions aux agents).
- **Garantie** : 3 mois après la mise en production, correction gratuite des anomalies imputables au prestataire.

---

## 11. Budget indicatif et maintenance

### 11.1 Réalisation (fourchettes indicatives, à confirmer par devis)

| Lot | Jours | Fourchette HT (TJM 500–650 €) |
|---|---|---|
| Cadrage, UX/UI, maquettes | 8 | 4 000 – 5 200 € |
| Socle technique, authentification, 2FA, rôles, audit | 9 | 4 500 – 5 850 € |
| Fiche agent, documents, qualifications, alertes, import | 9 | 4 500 – 5 850 € |
| Clients, sites, bornes, QR | 6 | 3 000 – 3 900 € |
| Pointage PWA, hors ligne, anti-fraude | 12 | 6 000 – 7 800 € |
| Planning, rapprochement, suivi, corrections | 10 | 5 000 – 6 500 € |
| Rondes : points, planification, exécution, temps réel, alertes | 14 | 7 000 – 9 100 € |
| Heures, majorations, export paie | 6 | 3 000 – 3 900 € |
| Import paie, documents société, contact, notifications | 8 | 4 000 – 5 200 € |
| Rapports PDF (mensuels, rondes, étiquettes) | 6 | 3 000 – 3 900 € |
| Portail client | 4 | 2 000 – 2 600 € |
| Badges NFC : émission, impression, PIN, lecture sur la borne, hors ligne | 5 | 2 500 – 3 250 € |
| Sécurité, RGPD, performance, accessibilité | 6 | 3 000 – 3 900 € |
| Tests, pilotes, recette, déploiement, formation, documentation | 10 | 5 000 – 6 500 € |
| Gestion de projet | 10 | 5 000 – 6 500 € |
| **Total réalisation** | **123 j** | **61 500 – 79 950 € HT** |

### 11.2 Coûts annexes

| Poste | Fourchette HT |
|---|---|
| Test d'intrusion externe (2–3 jours) | 2 500 – 4 500 € |
| Tablettes de borne + supports (≈ 20 sites équipés) | 4 000 – 9 000 € |
| Étiquettes QR durables (≈ 300 points) | 300 – 1 500 € |
| Matériel badge, scénario économique : 250 cartes imprimées par un prestataire (≈ 2–4 € l'unité), 20 lecteurs USB, lecteur RH, porte-badges | 1 300 – 3 100 € |
| Matériel badge, scénario autonome : imprimante à cartes, rubans, 250 cartes vierges, 20 tablettes à NFC intégré (surcoût), lecteur RH, porte-badges | 4 600 – 9 400 € |
| Cartes SIM data pour les bornes sans Wi-Fi | 0 – 10 €/mois/borne |
| Hébergement o2switch (offre mutualisée unique) | Quelques euros par mois — **tarif en vigueur à vérifier lors de la souscription** |
| Sonde de disponibilité, stockage de sauvegarde hors site (UE) | 5 – 20 €/mois |
| Accompagnement RGPD / AIPD (DPO externe, si pas de DPO interne) | 1 500 – 4 000 € |

### 11.3 Maintenance (après la garantie de 3 mois)

| Formule | Contenu | Fourchette HT |
|---|---|---|
| **Essentielle** | Mises à jour de sécurité PHP et dépendances, surveillance des sauvegardes et des crons, support N2 par e-mail (réponse J+1 ouvré), correctifs | 500 – 750 €/mois (≈ 1 j/mois) |
| **Confort** | Essentielle + 1,5 j/mois d'évolutions mineures, revue trimestrielle de sécurité et de performance, astreinte téléphonique en heures ouvrées | 1 250 – 1 650 €/mois |
| **Évolutions majeures** | Sur devis | — |

Pistes d'évolution (V2) : notifications push web, connecteur API vers le logiciel de paie, découpage d'un PDF de paie global, SMS d'alerte critique, mode « travailleur isolé » (PTI), main courante électronique enrichie, application encapsulée sur les stores, statistiques client avancées.

---

## 12. Risques et points de vigilance

| # | Risque | Probabilité | Impact | Mesures |
|---|---|---|---|---|
| R1 | **Fraude au pointage** : photo du QR de borne envoyée à un collègue | Moyenne | Élevé | QR dynamique de 60 s ; contrôle de proximité ; détection de vitesse et d'appareil partagé ; file « à vérifier » ; journal |
| R2 | **Pointage pour autrui** : agent connecté sur le compte d'un collègue | Moyenne | Élevé | Détection d'appareil partagé ; 2FA agent activable ; rappel dans la charte d'utilisation (sanction disciplinaire) ; contrôle par les chefs. **Biométrie et selfie volontairement exclus** (données sensibles, disproportion RGPD) |
| R3 | **Fraude aux rondes** : photos des étiquettes et scan groupé depuis le poste | Moyenne | Élevé | Détection de scans groupés et trop rapides ; contrôle de proximité ; régénération des QR ; audits terrain inopinés par les chefs |
| R4 | **Perte de réseau** (sous-sols, parkings, zones blanches) | Élevée | Moyen | Mode hors ligne (file IndexedDB, idempotence) ; bornes autonomes pour le calcul du QR ; tests en conditions réelles pendant le pilote |
| R5 | **Matériel des bornes** : vol, casse, coupure de courant, mise à jour Android | Moyenne | Moyen | Supports antivol, alimentation permanente, supervision par battement de cœur ; **QR statique de secours** sous enveloppe scellée au poste, activable à distance par l'admin |
| R6 | **Imprécision GPS en intérieur** (faux « hors zone ») | Élevée | Faible | Pas de blocage, uniquement « à vérifier » ; rayon réglable par site et par point ; prise en compte de la précision déclarée ; calibrage pendant le pilote |
| R7 | **Données sensibles** (NIR, paie) : fuite ou accès indu | Faible | Très élevé | Chiffrement, masquage, réauthentification, audit chaîné, URL signées, test d'intrusion, minimisation des exports, 2FA obligatoire pour les rôles sensibles |
| R8 | **Délai de 3 mois** contraint, avec la période des fêtes | Élevée | Élevé | Priorisation MoSCoW, pilotes anticipés, semaine tampon, report des « C » en V1.1, décisions rapides (référent disponible) |
| R9 | **Erreurs de calcul des majorations** | Moyenne | Élevé | Règles paramétrables ; validation par le gestionnaire de paie ; mois en double run (calcul plateforme vs paie) pendant le pilote ; mention « pré-calcul » |
| R10 | **Smartphones personnels des agents** (BYOD) : refus, absence d'équipement, forfait data | Moyenne | Élevé | Politique d'usage formalisée ; PWA légère (< 1 Mo, peu de data) ; quelques smartphones professionnels de prêt pour les agents non équipés ; borne en repli consultative ; validation juridique de la question de la prise en charge des frais |
| R11 | **Limites iOS des PWA** (permission caméra redemandée, pas de synchronisation en arrière-plan) | Élevée | Faible | Synchronisation à l'ouverture et au retour en ligne ; guide iOS ; tests sur iPhone dès S5 |
| R12 | **Bibliothèque html5-qrcode peu maintenue** | Moyenne | Moyen | API native `BarcodeDetector` en priorité ; bibliothèque embarquée localement (version figée) ; alternative identifiée (moteur de lecture QR JavaScript équivalent) en cas de régression |
| R13 | **Quotas e-mail et limites CPU du mutualisé** | Moyenne | Moyen | File d'envoi lissée ; récapitulatif quotidien plutôt que des e-mails unitaires ; traitements lourds par lots ; architecture prête pour une migration VPS |
| R14 | **Opposition des salariés / CSE**, contestation de la géolocalisation | Moyenne | Élevé | Information-consultation du CSE en amont ; AIPD ; géolocalisation ponctuelle uniquement ; transparence (« Mes données ») ; pas de sanction automatique |
| R15 | **Dépendance au prestataire** | Moyenne | Moyen | Code sans framework mais structuré (PSR), documenté et testé ; propriété du code et des accès cédée ; transfert de connaissances en S12 |
| R16 | **Horloge des appareils faussée** (mode hors ligne) | Faible | Moyen | Horodatage appareil borné par la fenêtre du jeton QR ; horodatage serveur prioritaire en ligne ; écart > 2 min signalé |
| R17 | **Prêt de badge** à un collègue | Moyenne | Élevé | PIN à 4 chiffres ; verrouillage après 5 erreurs ; contrôle par les chefs ; rappel dans le règlement intérieur |
| R18 | **Copie d'une carte NFC standard** | Faible | Moyen | Risque accepté en V1 (H9) ; PIN actif par défaut ; lecteurs compatibles avec des puces sécurisées pour une évolution V2 |
| R19 | **Perte ou oubli du badge** | Élevée | Faible | Le QR code reste disponible ; déclaration de perte depuis l'application ; réémission rapide avec l'imprimante interne |
| R20 | **Lecteur NFC défaillant ou débranché** | Moyenne | Faible | État du lecteur remonté par le battement de cœur ; repli sur le QR code |

---

## Annexes

### A. Paramètres par défaut

| Clé | Valeur par défaut |
|---|---|
| `pointage.rayon_defaut_m` | 150 |
| `pointage.precision_max_m` | 100 |
| `pointage.tolerance_retard_min` / `tolerance_depart_min` | 5 / 5 |
| `pointage.delai_absence_min` | 30 |
| `pointage.delai_depart_manquant_h` | 2 (après la fin de la vacation) / 14 (sans vacation) |
| `pointage.doublon_min` | 5 |
| `pointage.annulation_min` | 2 |
| `pointage.vitesse_max_kmh` | 150 |
| `pointage.alerte_carte_pro_expiree` | `true` (alerte) ; `bloquer` = `false` |
| `badge.pin_requis` | `true` (réglable par site) |
| `badge.pin_essais_max` / `badge.verrouillage_min` | 5 / 15 |
| `badge.hors_affectation` | `accepter_a_verifier` (ou `refuser`) |
| `badge.hors_ligne_conservation_h` | 24 |
| `badge.confirmation_affichage_s` | 4 |
| `qr.fenetre_s` / `qr.fenetres_tolerees` | 60 / 2 |
| `hors_ligne.retard_max_h` | 24 |
| `rondes.rayon_point_m` | 50 |
| `rondes.temps_min_entre_points_s` | 30 |
| `rondes.scan_groupe` | 3 points en 20 s |
| `rondes.exiger_service_ouvert` | `true` |
| `borne.alerte_silence_min` | 30 |
| `echeances.seuils_jours` | [90, 30, 7, 0] |
| `heures.reference_hebdo_h` | 35 |
| `heures.arrondi` | `aucun` |
| `heures.rattachement` | `mois_debut_service` |
| `planning.repos_quotidien_min_h` / `max_jour_h` / `max_semaine_h` / `moyenne_12s_h` | 11 / 12 / 48 / 46 |
| `session.agent_h` / `session.backoffice_inactivite_min` | 12 / 30 |
| `2fa.obligatoire_roles` | [chef, rh, admin, client] |
| `fichier.url_validite_min` | 5 |
| `geoloc.retention_jours` | 60 |

### B. Gabarits d'e-mails

Invitation · Réinitialisation du mot de passe · Compte verrouillé · Nouvelle fiche de paie · Planning publié · Planning modifié · Échéance J-90/J-30/J-7/expirée (agent, RH) · Nouveau document à lire / relance · Réponse à une demande · Anomalie critique · Ronde non démarrée · Récapitulatif quotidien · Rapport mensuel disponible · Borne hors ligne · Badge désactivé (perte, vol) · PIN verrouillé.

Chaque gabarit existe en version HTML et texte, sans donnée sensible, avec un lien vers la plateforme.

### C. Format d'import CSV des agents

`matricule;civilite;nom;nom_naissance;prenom;date_naissance;lieu_naissance;nationalite;adresse;code_postal;ville;email;telephone;nir;fonction;date_embauche;type_contrat;temps_travail;heures_mensuelles;site_principal_code;equipe;carte_pro_numero;carte_pro_type;carte_pro_delivrance;carte_pro_expiration`

UTF-8, séparateur `;`, dates `JJ/MM/AAAA`. Le fichier est supprimé du serveur après l'import ; seul le rapport d'import est conservé.

### D. Export « variables de paie »

`matricule;nom;prenom;periode;heures_planifiees;heures_pointees;heures_nuit;heures_dimanche;heures_ferie;hs25;hs50;nb_retards;minutes_retard;nb_absences;pointages_a_verifier`

Les heures sont exprimées en décimal (ex. `142,50`) avec la virgule comme séparateur. Les colonnes et leur ordre sont paramétrables pour correspondre à l'import du logiciel de paie.

### E. Nommage des fiches de paie

Motif par défaut : `{MATRICULE}_{AAAAMM}.pdf` (ex. `PGS-0042_202702.pdf`). Motifs alternatifs acceptés : `{AAAAMM}_{MATRICULE}.pdf` et `{NOM}_{PRENOM}_{AAAAMM}.pdf`. Le motif est à aligner avec l'export du logiciel de paie dès la semaine S1.

---

*Fin du document.*

