# Cross Corpo 53 · Résultats 2026-2027

Site public des résultats, classements et statistiques des Cross Corporatifs 53.

## Ajouter un cross

1. Télécharger le PDF « Scratch » du cross sur https://www.crosscorpo53.fr/cross/
2. Sur GitHub, ouvrir le dossier `pdfs/`, puis **Add file → Upload files**, déposer le PDF et cliquer sur **Commit changes**.
3. Attendre 1 à 2 minutes : l'automatisation (onglet **Actions**) lit le PDF, met à jour `data/` et republie le site.

Pour corriger un cross, redéposer un PDF du même cross : il remplace le précédent.
Seul le propriétaire du dépôt peut déposer des fichiers, donc personne d'autre ne peut modifier les résultats.

## Contenu

- `index.html` : le site (accueil, pages de cross, classement général, statistiques).
- `pdfs/` : les PDF Scratch déposés.
- `data/` : les résultats extraits (générés automatiquement, ne pas modifier à la main).
- `scripts/build-data.mjs` : lecture des PDF.
- `.github/workflows/build.yml` : lecture des PDF et publication sur GitHub Pages.
