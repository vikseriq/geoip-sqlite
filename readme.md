# GeoIP SQLite database generator & client

Dependency free GeoIP database inside SQLite with PHP converter and tiny client.

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

# Quickstart

## Prepare data - DIY
1. Prepare raw data – download CSV files from MaxMind and extract.

2. Generate database - `php geosqlfactory.php --db=world-ip.sqlite`.

## Ready to use databases

Currently unavaliable. 
I'm not sure is MaxMind allows to re-distribute free compilations over their databases, will look later.

## Use
In your code: include lib, provide path to db and resolve.

```php
include_once 'geoiptiny.php';
$ipResolver = new \vikseriq\GeoipSqlite\GeoipTiny('world-ip.sqlite');
echo $ipResolver->getCountry($_SERVER['REMOTE_ADDR']);
```


# Features

## Database generator [geosqlfactory.php](geosqlfactory.php)

- Converts GeoLite2 Countries CSV into SQLite database.

- Allow to set a limited list of countries/regions.

- Save multi locale names in json form.

- Requires only PHP 5.6+ CLI with SQLite3 extension.

- Can be included as class with `GEOSQLFACTORY_INCLUDED` constant.

- The footprint of all the world database in single language is about 20 Mb.

- Faster (16x) generation over another tools by using SQLite optimisations like transactions, 
  [async](https://www.sqlite.org/faq.html#q19) and [WAL](https://www.sqlite.org/pragma.html#pragma_journal_mode).

## PHP client `geoiptiny.php`

- Ready to use, dependency free, clean PHP class to work with the prepared DB.

- Lookup an IP details in SQLite3 database.

- Requires only PHP 5.6+ with SQLite3 extension, which presented almost on every UNIX instance and shared hostings.

## Tests `tests-geoip.php`

- Sample usage with resolver & localization testing.

- Before running tests create sample database: see the comments.


# Preparing raw data

1. Sign up on free account at [MaxMind](https://maxmind.com)

2. Navigate to Account - GeoLite2 - Download files.

3. Download `GeoLite2 Country: CSV Format` and unzip.

# Converting

Run `geosqlfactory` from PHP CLI:

```shell script
php geosqlfactory.php --country=LT,LV,EE --language=en,ru --db=baltic.sqlite --source=./GeoLite2-Country-CSV
```

| CLI param   | Default          | Example | Description |
| ----------- | ---------------- | ------- | ----------- |
| db          | geoip.sqlite     | /tmp/dach.db | Path to the SQLite database to store the data. |
| source      | ./GeoLite2-Country-CSV | ~/GeoIp2 | Path to the extracted MaxMind GeoIp CSV files. |
| language    | en               | de,en,fr   | Codes of languages that used as filename part of Locations-<lang>.csv files. |
| country     | *all countries*  | DE,AT,CH   | ISO codes of countries to be picked in to database. |
| region      | *all continents* | EU         | ISO codes of regions (continents in terms of MaxMind) to pick. |

# Running tests

Prepare sample database and run tests:

```bash
php tests-geoip.php
```

# Nice To Have

[_] Perform data format & SQLite index performance research like [this one](http://www.siafoo.net/article/53).

# License

MIT © 2020 vikseriq
