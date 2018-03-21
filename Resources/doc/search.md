# Searching

## Get a document by id

```php
$repo = $this->get('sfes.index.product')->getRepository('AppBundle:Product');
$product = $repo->getById(5); // 5 is the _id of the document in Elasticsearch
```
> The result is returned as an instance of the Product class. An optional second parameter of getByID() allows you to specify different format of the result. See [results types](#resulttypes) below for more information.

## Find documents using a search query

```php
$repo = $this->get('sfes.index.product')->getRepository('AppBundle:Product');
$searchBody = [
    'query' => [
        'match_all' => (object) []
    ]
];
$products = $repo->find($searchBody);
```
> The result by default is a DocumentIterator object. See [results types](#resulttypes) below for the different result types.

You can specify additional options as well:

```php
$products = $repo->find($searchBody, $resultsType, $additionalRequestParams);
```

## Getting the count of matching documents

If you have executed a **find()** request with the results returned as objects, you can get the total count of returned documents and the total number of documents matching the query from the returned iterator. This is covered in the [Working with the results](results.md) chapter.

If you want to get the number of documents that match a query, but don't want to get the results themselves, you can do so like this:

```php
$productsCount = $repo->count($searchBody);
```

## Searching in multiple types and indices

It is convenient to search in a single type as shown above, but sometime you may wish to search in multiple indices and/or types. The finder service comes in play:

```php
$finder = $this->get('sfes.finder');
$searchBody = [
    'query' => [
        'match_all' => (object) []
    ]
];
$finder->find(['AppBundle:Product', 'AppBundle:Deals'], $searchBody);
```
> You may specify the same options as when using Repository::find(), except you need to specify all entities to search in as the first parameter.

## <a name=resulttypes></a>Result types

| Argument               | Result                                                                              |
|------------------------|-------------------------------------------------------------------------------------|
| Finder::RESULTS_RAW    | Returns raw output as it comes from the elasticsearch client                        |
| Finder::RESULTS_ARRAY  | An array of results with structure that matches a document                          |
| Finder::RESULTS_OBJECT | `DocumentIterator` or an object of the wanted entity when a single result is wanted |

## Using a query builder to generate the search query

Instead of passing a raw array for the search query you may use the DSL component by ONGR.io. It is a very convenient and structured way of generating your search queries.  
For more information and documentation, have a look here: [Elasticsearch DSL](https://github.com/ongr-io/ElasticsearchDSL/blob/master/docs/index.md)

## Paginating results using KNP Paginator

If you want to use [KNP paginator](https://github.com/KnpLabs/KnpPaginatorBundle) to show paginated results, the bundle has integrated support for it. You just need to pass the **Finder::ADAPTER_KNP** flag to the search results type, regardless of whether you are using the find() method of the **sfes.finder** service, or a **Repository** instance:

```php
// AppBundle\Controller\ProductsController::listAction()

$page = 1;
$recordsPerPage = 10;
//...
$finder = $this->get('sfes.finder');
$results = $finder->find(
    ['AppBundle:Product'], 
    $searchQuery,
    Finder::RESULTS_OBJECT | Finder::ADAPTER_KNP
);
$paginator = $this->get('knp_paginator');
$paginatedResults = $paginator->paginate(
    $results,
    $page,
    $recordsPerPage
);

return $this->render('template.twig', array(
    'paginatedResults' => $paginatedResults,
    'aggregations' => $paginatedResults->getCustomParameter('aggregations'),
    'suggestions' => $paginatedResults->getCustomParameter('suggestions'),
));
```
> **IMPORTANT:** Getting aggregations and suggestions is not supported for **Finder::RESULTS_ARRAY** results and you'd get NULL returned

## Using scroll to retrieve documents

When you need to retrieve a lot of documents in the most efficient way, using the [Scroll](https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-scroll.html#scroll-search-context) API is the way to go.
You just need to pass the **Finder::ADAPTER_SCROLL** flag to the search results type, regardless of whether you are using the find() method of the **sfes.finder** service, or a **Repository** instance.
That will give you a ScrollAdapter object, instead of actual results.
Then you can use the adapter's **getNextScrollResults()** method to get the next scroll results until there are no more.


```
$finder = $this->get('sfes.finder');
$scrollAdapter = $finder->find(
    ['AppBundle:Product'], 
    $searchQuery, 
    Finder::RESULTS_RAW | Finder::ADAPTER_SCROLL, 
    ['size' => 1000, 'scroll' => '5m']
);

while (false !== ($matches = $scrollAdapter->getNextScrollResults())) {
    foreach ($matches['hits']['hits'] as $rawDoc) {
        // Do stuff with the document 
        // ...
    }
}
```
> You can optionally specify the chunk `size` and `scroll` time as additional params to the find() method. The defaults are `10` and `1m` 
> **Tip:** The **Finder::ADAPTER_SCROLL** works with any type of results, but you would usually want speed when you use it, so the most efficient way would be to get the raw results.
