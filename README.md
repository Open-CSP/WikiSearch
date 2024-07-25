# WikiSearch

WikiSearch is an extension for MediaWiki that adds faceted search capabilities. More information can be found on its [MediaWiki page](https://www.mediawiki.org/wiki/Extension:WikiSearch).

## Installation

* Download and place the file(s) in a directory called `WikiSearch` in your `extensions/` folder.
* Add the following code at the bottom of your `LocalSettings.php`:
  * `wfLoadExtension( 'WikiSearch' );`
* Run the update script which will automatically create the necessary database tables that this extension needs.
* Add the following dependencies to your `composer.local.json`:
  * For Elasticsearch 8.x:
    * `elasticsearch/elasticsearch` : `~8.x` (where `x` is the minor version)
    * `handcraftedinthealps/elasticsearch-dsl`: : `^8.0`
  * For Elasticsearch 7.x:
    * `elasticsearch/elasticsearch` : `~7.x` (where `x` is the minor version)
    * `handcraftedinthealps/elasticsearch-dsl`: : `^7.0`
  * Any other version of ElasticSearch is not supported.
* Run `composer update` in your wiki's root folder.
* Navigate to `Special:Version` on your wiki to verify that the extension is successfully installed.
