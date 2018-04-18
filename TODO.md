## TODO
* Limit must be a parameter for each table in config and not an option in cli
* Make CircleCI Work
* Guesser must be a config and not a class
* Make it work with Oracle
* Set Host / user / password as an Url and not as independent options + Set URL in config overridable by cli
* Manager foreign keys values by creating a new Faker method : randomId which will run a random SQL Select to the parent table
* Implement batch processing

## Done
* ~~Implement an iterator for large updates like in doctrine/orm~~ Done with a "pagination" like system
