Simple Templating Pimple Service for WordPress Themes
=====================================================

This Pimple service allows the developer to place the query templates and page templates of a WordPress theme at a custom location. This allows a cleaner, more organized theme structure.

Usage
-----
This service is installable via [Composer](https://getcomposer.org/) and relies on it's class autoloading mechanism. You can package the vendor
directory with you theme or plugin, with your WordPress installation or with a setup of your choosing.

1. Create a composer project for your plugin or theme:
    
    ```bash
    cd your-theme-directory
    # install composer phar
    curl -sS https://getcomposer.org/installer | php
    # create a basic composer.json
    ./composer.phar init
    ```
2. Add the simple templating service as a dependency in your composer.json
    
    ```bash
    ./composer.phar require tmf/wp-simple-templating ~0.1
    ```
3. Create a pimple container and register the simple templating service
    
    ```php
    // load the vendors via composer autoload
    if (file_exists( __DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    
    use Tmf\Wordpress\Service\SimpleTemplatingServiceProvider;
    
    // create the service container
    $services = new Pimple\Container();
    
    // register the templating service (for templates in the ./templates directory)
    $services->register(
        new SimpleTemplatingServiceProvider('templating'),
        array('templating.directory' => 'templates')        // set up the "templates" directory as the "templating.directoy" configuration parameterin the service container
    );
    ```
4. Place all your query templates (`index.php`, `single.php`, `single-cpt.php`, ...) in the `templates` directory. You can place additional template parts in this directory structe and call them with `get_template_parts('templates/partial/header.php')`
5. `index.php` still needs to be exist at the root level of the theme, but you can leave it empty (or throw an exception).

Unfortunately get_header() and get_footer() won't work, WordPress will try to load the BackCompat mode...
   