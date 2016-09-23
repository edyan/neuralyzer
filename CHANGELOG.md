Changelog
=========
v0.11
----
* Added a guesser for `timestamp` and fixed the guesser of `time`

v0.10
----
* `empty` has been renamed to `delete` and `where` to `delete_where`
* `delete` can now be used in combination with `cols`
* Update queries are made with a transaction (3 times faster with InnoDB).
* Added symfony/stopwatch to display the execution time.


v0.9
----
* Updated README
* Added an option to empty a table based on criteras (instead of `cols: {...}`, use `empty: true`)
* Minor bugs solved and improvements
* composer update
