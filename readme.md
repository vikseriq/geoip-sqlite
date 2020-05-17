# Dependency free GeoIP database inside SQLite with PHP converter and sample

## Motivation

One day in one tiny web-project I was in need to detect is visitors from certain countries.
 Looked a lot of online and offline solutions I decide to pick [MaxMind DB](https://maxmind.com/).
 MaxMind offers free and up-to-date version GeoLite2 Country with 98% accuracy, what is fine for my needs.
 Pros is free, offline and mature database. 
 Even in [CSV format](https://dev.maxmind.com/geoip/geoip2/geoip2-city-country-csv-databases/). 
 Cons is lack of ready-to-use dependency-free forms 
 and no clean way to pick specific region set and prepare database with IP ranges. 
 
Of course there are official [Go](https://github.com/maxmind/geoip2-csv-converter) converter,
 some Python [parsers](https://github.com/scullxbones/geolitelegacy-lookup) and 
 [converters](https://github.com/ffissore/geolite2-to-sqlite), 
 and even [pure UNIX solution](https://www.splitbrain.org/blog/2011-02/12-maxmind_geoip_db_and_sqlite),
 but all of them not provide way to limit selection and generate an DB with *IP ranges* from actual GeoLite2 format.

Someone might notice: "Why you not use official lib from Composer" or "Why not install Apache/nginx/Varnish module".
 The answer is: [KISS](https://en.wikipedia.org/wiki/KISS_principle). Composer with huge payload, 
 or libmaxminddb.so + compilation from sources – just to read CSV file on pet project with 3,5 visitors from 3 countries?..
 Guys, stop copy-paste "solutions" from StackOverflow, let's solve problems as engineers, not "monkey-coders".
 
Anyway, making a pure SQLite DB with IP ranges might also be useful in mobile apps and embedded systems.

Let's go.

# Features

## Database generator [geosqlfactory.php](geosqlfactory.php)

- Converts GeoLite2 Countries CSV into SQLite database.

- Allow to set a limited list of countries/regions.

- Save multi locale names in json form.

- Requires only PHP 5.6+ CLI with SQLite3 extension.

- Can be included as class with `GEOSQLFACTORY_INCLUDED` constant.

## PHP sample `geoip-tiny.php`

- Ready to use, dependency free, clean PHP class to work with prepared DB.

- Lookup an IP details in SQLite3 database.

- Requires only PHP 5.6+ with SQLite3 extension, which presented almost on every UNIX instance and shared hostings.


# Preparing raw data

1. Signup free account on [MaxMind](https://maxmind.com)

2. Navigate to Account - GeoLite2 - Download files.

3. Download `GeoLite2 Country: CSV Format` and unzip.



# License

MIT © 2020 vikseriq
