# Lucca v2

![Lucca logo](public/assets/logo/lucca-color.png)

Application d'assistance a la cabanisation et autres infractions d'urbanisme.

**Stack** : Symfony 7.4 / PHP 8.4 / MariaDB 11.8 / Caddy 2

## Demarrage rapide (Docker)

Prerequis : [Docker Desktop](https://www.docker.com/products/docker-desktop/)

```bash
make install
```

C'est tout. Ajoutez `127.0.0.1 lucca.local` dans votre `/etc/hosts`, puis accedez a **https://lucca.local**.

Credentials : `superadmin` / `superadmin`

> Au premier acces, acceptez le [certificat TLS auto-genere](https://stackoverflow.com/a/15076602/1352334) (cliquez "Avance" puis "Continuer").

## Demarrage rapide (natif, sans Docker)

Prerequis : PHP 8.2+, Composer, MariaDB, [Symfony CLI](https://symfony.com/download), wkhtmltopdf

```bash
cp .env.local.example .env.local
# Editez .env.local avec vos parametres de BDD

make install-native
make native-db-setup
make serve
```

L'application est accessible sur **https://127.0.0.1:8000**.

## Commandes disponibles

Lancez `make` ou `make help` pour voir toutes les commandes :

| Commande | Description |
|---|---|
| `make install` | Installation complete Docker (build + BDD + fixtures) |
| `make start` / `make stop` | Demarrer / arreter les containers |
| `make shell` | Ouvrir un shell dans le container PHP |
| `make logs` | Voir les logs Docker |
| `make db-migrate` | Lancer les migrations |
| `make db-fixtures` | Charger les fixtures |
| `make db-init` | Reset complet de la BDD (avec confirmation) |
| `make tests` | Lancer tous les tests |
| `make test-bundle BUNDLE=UserBundle` | Tester un bundle specifique |
| `make cc` | Vider le cache Symfony |
| `make assets` | Recompiler les assets |

## Migrations

```bash
make db-migrate                                      # Appliquer les migrations
make shell                                           # Puis dans le container :
php bin/console doctrine:migrations:diff             # Creer une migration
php bin/console doctrine:migrations:migrate prev     # Revenir en arriere
php bin/console doctrine:migrations:status           # Voir le statut
```

## Tests

```bash
make tests                           # Tous les tests
make test-bundle BUNDLE=UserBundle   # Un bundle specifique
```

## Commandes Lucca

| Commande | Description |
|---|---|
| `lucca:init:setting` | Initialiser les parametres |
| `lucca:init:media` | Initialiser le bundle media |
| `lucca:init:department` | Initialiser un departement demo |
| `lucca:user:change-password` | Changer le mot de passe d'un utilisateur |
| `lucca:security:unban` | Debannir une adresse IP |

## Documentation

- [Variables d'environnement](docs/env_vars.md)
- [Configuration email](docs/email.md)
- [Deploiement production](docs/production_deploy.md)
- [Initialisation des bundles](docs/initialization_lucca.md)
- [Reseau Docker multi-projets](docs/docker_network_developper.md)

## Credits

Cree par [Numeric Wave](https://numeric-wave.eu).
Licence AGPL-3.0-or-later.
