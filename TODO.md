## TODO
* Limit must be a parameter for each table in config and not an option in cli
* Guesser must be a config and not a class
* Make it work with Oracle, Elastic
* Create CSV without writing in DB
* Set Host / user / password as an Url and not as independent options + Set URL in config overridable by cli
* Manage foreign keys values by creating a new Faker method : randomId which will run a random SQL Select to the parent table
* Remove option "delete" in config to prefer a `pre_action` (`db.query()`)
* Add more tests to get more coverage, since the last modifications
* Add Examples of pre / post actions in doc


## Done
* ~~Implement an iterator for large updates like in doctrine/orm~~ Done with a "pagination" like system
* ~~Make CircleCI Work~~ Done
* ~~Implement batch processing with load data~~ Done and working pretty well
* ~~CircleCI should test with sqlserver to have something else than mysql in my CIs !~~
