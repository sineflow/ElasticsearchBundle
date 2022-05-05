# Setup the bundle


### Step 1: Install Elasticsearch bundle

Assuming you have [Composer](https://getcomposer.org) installed globally, enter your project directory and execute the following command to download the latest stable version of this bundle:

```
$ composer require sineflow/elasticsearch-bundle "~7.1.0"
```

> Replace version accordingly. Latest release is [![Latest Stable Version](https://poser.pugx.org/sineflow/elasticsearch-bundle/v/stable)](https://packagist.org/packages/sineflow/elasticsearch-bundle)

> Since the bundle's major version is kept in line with the Elasticsearch major version, it is likely that breaking changes can occur in minor versions.

### Step 2: Enable the Bundle

To enable the bundle, add the following to `App/AppKernel.php` in your project.

```php
<?php
// App/AppKernel.php
// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Sineflow\ElasticsearchBundle\SineflowElasticsearchBundle(),
        );
        // ...
    }
    // ...
}
```


### Step 3: Add configuration

```yaml
# App/config/config.yml
sineflow_elasticsearch:
    connections:
        default:
            hosts: [127.0.0.1:9200]
    indices:
        customer:
            name: dev_customer
            connection: default
            class: App:Customer
```

> This is the very basic example only, for a more detailed description of configuration options, please take a look at the [configuration](configuration.md) chapter.

A couple of things to note in this example: `dev_customer` is the name of the physical index in Elasticsearch and `App:Customer` represents the class where the document mapping is defined. (more info at [the mapping chapter](mapping.md)).


### Step 4: Define your Elasticsearch types as `Document` objects

The bundle uses `Document` objects to represent Elasticsearch documents. Now lets create a `Customer` class in the `Document` folder.

```php

<?php
namespace App\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

/**
 * @ES\Document
 */
class Customer extends AbstractDocument
{
    /**
     * @var string
     *
     * @ES\Property(name="text", type="text")
     */
    public $name;
}

```

> This is a basic example only, for more information about mapping, please take a look at the [the mapping chapter](mapping.md).


### Step 4: Create index and mappings

Elasticsearch bundle provides several `CLI` commands. One of them is for creating index, run command in your terminal:

```bash
    bin/console sineflow:es:index:create customer
```

> More info about the rest of the commands can be found in the [commands chapter](commands.md).


### Step 5: Further reading

Back to [docs index](index.md)
