This storage directory is the default location for the SQLite database
and log files.  This directory should contain no source code that
gets checked into the repository.

If it gets copied to /var/www, this is 

/var/www/half-butt/
├── storage/         # **Contains actual database file (SQLite)**
│   ├── half-butt.db # The actual database
│   ├── logs/
│   ├── cache/

