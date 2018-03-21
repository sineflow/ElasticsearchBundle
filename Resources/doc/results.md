# Working with results

When doing a search request, you may specify the type of the returned results. When using **Finder::RESULTS_RAW**, the results are returned directly as the Elasticsearch client returns them.

Here, we will cover the case when using **Finder::RESULTS_OBJECT** as the result type. In that case, the **find()** method returns a DocumentIterator instance. It implements the **\Countable** and **\Iterator** interfaces and thus you may traverse it like an array, in which each element is an instance of a respective entity.
The object instances are created only when requested, which helps save some memory.

In addition to that, the **DocumentIterator** itself has some extra methods:

* `count()` Gives you the number of returned documents.
* `getTotalCount()` Gives you the total hits, i.e. the number of documents that match the search query.
* `getSuggestions()` Return the suggestions, if you have requested any.
* `getAggregations()` Return the aggregations, if you have requested any.
 
```php
$repo = $this->get('sfes.index.product')->getRepository('AppBundle:Product');
$searchBody = [
    'query' => [
        'match_all' => (object) []
    ]
];
$products = $repo->find($searchBody);

echo $products->count() . "\n";
echo $products->getTotalCount() . "\n";
foreach ($products as $product) {
    echo $product->title . "\n";
}
```

## Nested and inner objects

When a document field is declared as `object` or `nested`, depending on whether `multiple` is set, the field is either returned as an instance of the the respective object entity or as an **ObjectIterator**.
The **ObjectIterator**, like the **DocumentIterator** implements **\Countable** and **\Iterator**, so can be traversed as an array.

## Handling multilanguage fields

When a field is declared as multilanguage, it is returned as an instance of **MLProperty**. 
Let's take the above example and assume that the product title was declared as multilanguage:
```php
var_dump($product->title);
var_dump($product->title->getValue('en'));
```
will give you:
```
object(Sineflow\ElasticsearchBundle\Document\MLProperty)#2655 (1) {
  ["values":"Sineflow\ElasticsearchBundle\Document\MLProperty":private]=>
  array(2) {
    ["en"]=>
    string(4) "Acme"
    ["default"]=>
    string(4) "Acme"
  }
}

string(4) "Acme"
```

