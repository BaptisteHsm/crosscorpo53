# Cross Corpo 53 · Résultats 2026-2027

Site public des résultats, classements et statistiques des Cross Corporatifs 53.

## Ajouter un cross

1. Télécharger le PDF « Scratch » du cross sur https://www.crosscorpo53.fr/cross/
2. Sur GitHub, ouvrir le dossier `pdfs/`, puis **Add file → Upload files**, déposer le PDF et cliquer sur **Commit changes**.
3. Attendre 1 à 2 minutes : l'automatisation (onglet **Actions**) lit le PDF, met à jour `data/` et republie le site.

Pour corriger un cross, redéposer un PDF du même cross : il remplace le précédent.
Le PDF est supprimé du dépôt une fois lu : seuls les résultats extraits (sans année de naissance) sont publiés.
Seul le propriétaire du dépôt peut déposer des fichiers, donc personne d'autre ne peut modifier les résultats.

## Ajouter le tracé d'un cross (« Rejouer la course »)

1. Exporter le GPX de la course (Strava : activité → ⋯ → Exporter le GPX).
2. Le renommer avec le numéro du cross, par exemple `cross-2.gpx`, et le déposer dans le dossier `gpx/` (Add file → Upload files).
3. L'onglet « Rejouer la course » apparaît sur la page du cross après la mise à jour.

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
