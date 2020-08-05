# Autoloader
This class became necessary during the development of websites using old libraries not conforming to PSR recommendations. This class parses scripts using PHP tokenizer extension allowing to find more than one class per file. Namespaces are also supported. Sometimes it can be useful to see in which file a class reside.

##### Installation

You can install the package via composer:

```
composer require djiele/autoloader dev-master
```

##### Usage


```php
// including the class file
require_once 'path/to/class/Autoloader.php';
// or using composer
require_once 'vendor/autoload.php';

use Djiele\PHP\Autoloader;

$autoloaderId = 'my-project-autoloader';
$useCache = true; // true to create/refresh and use generated cache file
$classLoader = new Autoloader($autoloaderId, $useCache); 
$classLoader
    ->setClassMapDir('cache/autoloader') // path to the cache file, created if not exists
    ->setFolders(['src', 'packages']) // array of directories to be analyzed
    ->register() // launch the analyze and register the class in autoload chain
;
```
Et voilà!

