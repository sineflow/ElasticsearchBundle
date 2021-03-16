# CLI Commands

All commands can be accessed using Symfony's command line interface. To use it, simply type `app/console <command_name> <arguments/options>` in root folder of your project.

## Create index

Command name: `sineflow:es:index:create <index_manager_name>`

Creates a new index in Elasticsearch for the specified manager with the configured mapping (see: [configuration chapter](configuration.md)).

## (Re)build index

Command name: `sineflow:es:index:build <index_manager_name>`

Rebuilds the data in the specified index, using the configured data provider. If no data provider is configured, by default a *self* provider is registered, so the index would be rebuilt from itself - useful when mapping has changed, and you need to update it.

An important thing to note is that currently this command will only work if you have set `use_aliases: true` in your index configuration. What it does is, it creates a new index and points the *write* alias to it, as well as to the old one.
When building the new index is complete without errors, both read and write aliases are pointed to it and removed from the old one.

|     Options             |             Value            |                                      What it does                       |
|:-----------------------:|:----------------------------:|:-----------------------------------------------------------------------:|
|   `delete-old`          |        *not required*        | Deletes the old index, as soon as the new one is built                  |
|   `cancel-current`      |        *not required*        | Deletes all indices pointed to by the write alias (except the live one) |

> We recommend running this command with the **--no-debug** option of **app/console**, otherwise Symfony is leaking a lot of memory resulting in a serious degrade of performance and very likely memory limit errors.
