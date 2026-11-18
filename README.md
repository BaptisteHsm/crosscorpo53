# Cross Corpo 53 · Résultats 2026-2027

Site public des résultats, classements et statistiques des Cross Corporatifs 53.

## Ajouter un cross

1. Télécharger le PDF « Scratch » du cross sur https://www.crosscorpo53.fr/cross/
2. Sur GitHub, ouvrir le dossier `pdfs/`, puis **Add file → Upload files**, déposer le PDF et cliquer sur **Commit changes**.
3. Attendre 1 à 2 minutes : l'automatisation (onglet **Actions**) lit le PDF, met à jour `data/` et republie le site.

Pour corriger un cross, redéposer un PDF du même cross : il remplace le précédent.
Le PDF est supprimé du dépôt une fois lu : seuls les résultats extraits (sans année de naissance) sont publiés.
Seul le propriétaire du dépôt peut déposer des fichiers, donc personne d'autre ne peut modifier les résultats.

## Ajouter le tracé d'un cross (« Revivre la course »)

1. Exporter le GPX de la course (Strava : activité → ⋯ → Exporter le GPX).
2. Le renommer avec le numéro du cross, par exemple `cross-2.gpx`, et le déposer dans le dossier `gpx/` (Add file → Upload files).
3. Le bouton « Revivre la course » apparaît sur la page du cross après la mise à jour.

Le GPX du cross long sert aussi pour le cross court (premier tour). Pour un parcours court différent, déposer un second fichier nommé par exemple `cross-2-court.gpx`.
Seules les coordonnées du tracé sont conservées : les horaires et le GPX d'origine sont supprimés.

## Retirer un coureur (droit d'opposition)

1. Sur GitHub : **Settings → Secrets and variables → Actions → New repository secret** (ou modifier le secret existant).
2. Nom : `EXCLUSIONS`. Valeur : les numéros de dossard à masquer, séparés par des virgules (ex. `1234, 5678`).
3. Onglet **Actions → Résultats et publication → Run workflow**.

Le coureur apparaît alors comme « Coureur masqué » sur tout le site ; les places des autres ne changent pas.
La liste reste privée (secret), elle n'est pas visible dans le dépôt public.

## Contenu

- `index.html` : le site (accueil, pages de cross, classement général, statistiques).
- `pdfs/` : les PDF Scratch déposés.
- `data/` : les résultats extraits (générés automatiquement, ne pas modifier à la main).
- `scripts/build-data.mjs` : lecture des PDF.
- `.github/workflows/build.yml` : lecture des PDF et publication sur GitHub Pages.

## Module WordPress (site officiel)

Le site existe aussi sous forme d'extension WordPress, pour l'afficher dans une page du site crosscorpo53.fr, avec l'en-tête et le pied de page du thème.

- Télécharger l'extension : https://baptistehsm.github.io/crosscorpo53/wp/cc53-resultats.zip
- WordPress › Extensions › Ajouter › Téléverser, puis Activer.
- Créer une page et y insérer le shortcode `[cc53_resultats]`.

L'extension charge l'application et les résultats depuis GitHub Pages (`/wp/` et `/data/`), régénérés à chaque mise à jour du dépôt par `scripts/build-wp.mjs` : un nouveau cross apparaît sur WordPress sans réinstaller l'extension. Les styles sont isolés dans le conteneur `.cc53` et les polices sont servies depuis GitHub Pages (pas de Google Fonts).

Envoi des résultats depuis WordPress (facultatif) : Réglages › Cross Corpo 53, enregistrer un jeton GitHub « fine-grained » limité à ce dépôt (droits Contents : lecture et écriture, Actions : lecture). Un formulaire permet alors d'envoyer un PDF « Scratch » ou une trace GPX, et affiche l'état des derniers traitements.
