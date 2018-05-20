## TODO
* Limit must be a parameter for each table in config and not an option in cli
* Guesser must be a config and not a class
* Make it work with Oracle
* Set Host / user / password as an Url and not as independent options + Set URL in config overridable by cli
* Manager foreign keys values by creating a new Faker method : randomId which will run a random SQL Select to the parent table
* CircleCI should test with sqlserver to have something else than mysql in my CIs !

## Done
* ~~Implement an iterator for large updates like in doctrine/orm~~ Done with a "pagination" like system
* ~~~* Make CircleCI Work~~~ Done
* ~~Implement batch processing with load data~~ Done and working pretty well
