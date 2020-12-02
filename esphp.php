<?php
require 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

$hosts = [
    'localhost:9200',         // IP + Port
];
$client = ClientBuilder::create()           // Instantiate a new ClientBuilder
                    ->setHosts($hosts)      // Set the hosts
                    ->build();              // Build the client object

$params = [
    'index' => 'smw-data-wiki-31',
    'body' => [
        'query' => [
            'bool' => [
                'must' => [
                    [ 'match' => [ 'P:525.txtField' => 'News item' ] ],
                ]
            ]
        ],
        'aggs' => [
          'mybucket' => [
            'terms' => [
              'field' => 'P:539.txtField.keyword'
            ]
          ]
        ]
    ]
];


$results = $client->search($params);

echo var_dump($results);
