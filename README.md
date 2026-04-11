# Atldays Sculptor

This package provides a convenient interface for building complex database queries using Eloquent ORM in Laravel.

---

### It includes the following main features: 

- **HasCache Trait**: This trait provides functionality for caching query results. It also allows you to manage cache tags and cache lifetime.  
- **WithQuery Interface**: This interface defines the query() method, which must be implemented in classes using this package. This method should return an instance of Builder from Eloquent, which can then be modified using the methods provided by this package.

This package is designed to simplify the construction of large and complex database queries in Laravel, providing a convenient and understandable interface.

## Repositories

Since this is a private package, you will need to add the repository to your `composer.json` file:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@bitbucket.org:atldays/laravel-sculptor.git"
        },
        {
            "type": "vcs",
            "url": "git@github.com:atldays/laravel-eloquent-filters.git"
        },
        {
            "type": "vcs",
            "url": "git@github.com:atldays/laravel-eloquent-query-cache.git"
        }
    ]
}
```

## Installation

Install the package via Composer:

```bash
composer require atldays/laravel-sculptor
```

## Configuration

After the package is installed, you may need to publish and run the migration files. You can publish the configuration
file using this command:

```bash
php artisan vendor:publish --provider="Atldays\\Sculptor\\SculptorServiceProvider"
```
