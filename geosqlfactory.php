<?php
/**
 * Convert MaxMind GeoLite2 CSV to SQLite tables
 *
 * To use GeoSqlFactory class in external code define GEOSQLFACTORY_INCLUDED
 *
 * Inspired by https://www.splitbrain.org/blog/2011-02/12-maxmind_geoip_db_and_sqlite
 */

namespace vikseriq\GeoipSqlite;

class GeoSqlFactory
{
    const SQL_TABLE_RANGES = 'geoip_ranges';
    const SQL_TABLE_LOCATIONS = 'geoip_locations';
    const FILE_COUNTRY_LOCATION_TEMPLATE = '/GeoLite2-Country-Locations-%s.csv';
    const FILE_COUNTRY_BLOCKS = '/GeoLite2-Country-Blocks-IPv4.csv';

    /**
     * @var string Path to the extracted MaxMind CSV database
     */
    protected $sourcePath;
    /**
     * @var \SQLite3 Database connection
     */
    protected $dbo = null;
    /**
     * @var string[] Languages to process
     */
    protected $languages = ['en'];
    /**
     * @var string[] ISO code of regions (continents) to include
     */
    protected $regions = [];
    /**
     * @var string[] ISO code of countries to include
     */
    protected $countries = [];

    public $geonameTextMap = [];
    public $ipRangeCounter = 0;
    public $ipTotalCoverage = 0;

    /**
     * GeoSqlFactory constructor.
     *
     * Performs basic checks.
     *
     * @param string $source_path Path to the extracted MaxMind archive with CSV files
     * @throws \Exception
     */
    function __construct($source_path)
    {
        // check paths
        $path = realpath($source_path);
        if (true !== ($path
                && is_file($path . self::FILE_COUNTRY_BLOCKS)
                && is_file(sprintf($path . self::FILE_COUNTRY_LOCATION_TEMPLATE, 'en'))
            )) {
            throw new \Exception(sprintf('MaxMind CSV files not found in %s. '
                . 'Check path and %s file format constants.',
                $source_path, self::class
            ));
        }
        $this->sourcePath = $path;

        // check for SQLite3
        if (!class_exists('Sqlite3')) {
            throw new \Exception('SQLite3 module must be installed and enabled.');
        }
    }

    function setLanguages($lang_list)
    {
        $this->languages = array_unique($lang_list);
    }

    function setRegions($isoCodes)
    {
        if (!is_array($isoCodes))
            $isoCodes = [$isoCodes];
        $this->regions = $isoCodes;
    }

    function setCountries($isoCodes)
    {
        if (!is_array($isoCodes))
            $isoCodes = [$isoCodes];
        $this->countries = $isoCodes;
    }

    /**
     * Initialise SQLite database;
     * Drop obsolete tables and create a new ones.
     * Create cross-table ref and range index.
     *
     * @param $path
     */
    function prepareDb($path)
    {
        $this->dbo = new \SQLite3($path);

        // table for locations
        // *_name will filled with primary lang
        // locales_json contains json of all other names in various locales
        $q = sprintf('DROP TABLE %1$s; CREATE TABLE %1$s (
            location_id INTEGER PRIMARY KEY,
            continent_code TEXT,
            continent_name TEXT,
            country_iso_code TEXT,
            country_name TEXT,
            locales_json TEXT
        )', self::SQL_TABLE_LOCATIONS);

        $this->dbo->exec($q);

        // table for ranges with reference and index
        $q = sprintf('DROP TABLE %1$s; CREATE TABLE %1$s (
            idx INTEGER,
            ip_start NUMERIC UNIQUE,
            ip_end NUMERIC UNIQUE,
            location_id INTEGER REFERENCES %2$s(location_id)
        );
        CREATE INDEX geoidx ON %1$s(idx);
        ', self::SQL_TABLE_RANGES, self::SQL_TABLE_LOCATIONS);

        $this->dbo->exec($q);
    }

    /**
     * Process FILE_COUNTRY_BLOCKS file
     * and fill to database ranges for picked locations.
     *
     * @throws \Exception
     */
    function fillBlocks()
    {
        if (empty($this->geonameTextMap)) {
            throw new \Exception('No effective locations selected.');
        }
        $this->ipTotalCoverage = 0;
        $this->ipRangeCounter = 0;
        // sql buffer
        $sql_buffer = [];
        $sql_exec_every = 120;

        $fblock = fopen($this->sourcePath . self::FILE_COUNTRY_BLOCKS, 'r');
        if (!$fblock) {
            throw new \Exception('IPv4 blocks file not found.');
        }
        // read header
        fgets($fblock);
        // iterate all lines
        $i = 0;
        while ($line = fgetcsv($fblock, 1024, ',', '"')) {
            // status ping
            if (!defined('GEOSQLFACTORY_INCLUDED')) {
                if (++$i % 25000 === 0) {
                    echo $i . "\t";
                }
            }
            // $line index 0 is network mask, 1 is geoname_id
            // lookup for existing geoname
            if (!isset($this->geonameTextMap[$line[1]])) {
                continue;
            }
            // convert CIDR to IPv4 range
            $range = self::cidr2iplong($line[0]);
            if (!$range) {
                // bad address
                continue;
            }
            // counters
            $this->ipRangeCounter++;
            $this->ipTotalCoverage += $range[1] - $range[0];
            // index clustering by 2^16 addresses
            $idx = $range[1] - $range[1] % 65536;

            // prepare statement
            $sql_buffer[] = sprintf('INSERT INTO %s (location_id, ip_start, ip_end, idx)
            VALUES (%s, %s, %s, %s)',
                self::SQL_TABLE_RANGES,
                +$line[1], $range[0], $range[1], $idx
            );

            // write to db
            if (count($sql_buffer) >= $sql_exec_every) {
                $this->dbo->exec(implode(';', $sql_buffer));
                $sql_buffer = [];
            }
        }
        fclose($fblock);

        // flush the rest
        if (count($sql_buffer)) {
            $this->dbo->exec(implode(';', $sql_buffer));
        }
        // that's all folks
        $this->dbo->close();
    }

    /**
     * Iterate over all $languages and fill $geonameTextMap
     *
     * First language used as primary.
     * All localized names gathering into 'locales_json' array indexed by locale_code
     *
     * @throws \Exception
     */
    function fillGeonames()
    {
        // iterate over languages
        foreach ($this->languages as $language) {
            // open lang file
            $filename = $this->sourcePath . sprintf(self::FILE_COUNTRY_LOCATION_TEMPLATE, $language);
            $flang = fopen($filename, 'r');
            if (!$flang) {
                throw new \Exception('Localization file not found: ' . $filename);
            }
            // read header
            fgets($flang);
            // iterate all lines
            while ($line = fgetcsv($flang, 1024, ',', '"')) {
                // $line index 0 - geoname_id, 1 - locale_code
                // 2 - continent_code, 3 - continent_name
                // 4 - country_iso_code, 5 - country_name

                // filter by region
                if (!empty($this->regions) && !in_array($line[2], $this->regions)) {
                    continue;
                }
                // filter by country
                if (!empty($this->countries) && !in_array($line[4], $this->countries)) {
                    continue;
                }

                if (empty($this->geonameTextMap[$line[0]])) {
                    $locationInfo = [
                        'location_id' => $line[0],
                        'continent_code' => $line[2],
                        'continent_name' => $line[3],
                        'country_iso_code' => $line[4],
                        'country_name' => $line[5],
                        'locales_json' => [],
                    ];
                    // new geoname with primary lang
                    $this->geonameTextMap[$line[0]] = $locationInfo;
                }

                // append localized string
                $this->geonameTextMap[$line[0]]['locales_json'][$line[1]] = [
                    'continent_name' => $line[3],
                    'country_name' => $line[5],
                ];
            }
            fclose($flang);
        }

        // write out to database
        if (empty($this->dbo)) {
            throw new \Exception('Setup a SQLite DB via `prepareDb` method.');
        }
        foreach ($this->geonameTextMap as $geonameId => $geonameInfo) {
            // transform locales_json into json string
            $geonameInfo['locales_json'] = json_encode($geonameInfo['locales_json'], JSON_UNESCAPED_UNICODE);
            // escape + wrap everything
            foreach ($geonameInfo as $k => $v) {
                $geonameInfo[$k] = "'" . \SQLite3::escapeString($v) . "'";
            }

            // construct sql
            $q = 'INSERT INTO ' . self::SQL_TABLE_LOCATIONS
                . '(' . join(', ', array_keys($geonameInfo)) . ') '
                . 'VALUES (' . join(', ', array_values($geonameInfo)) . ');';

            // exec
            $this->dbo->exec($q);
        }
    }

    /**
     * Converts CIDR notation into integer IP v4 ranges
     * @param string $cidr_range CIDR string like 192.168.100.0/24
     * @return array Range [start_ip, end_ip] in integer representation
     */
    static function cidr2iplong($cidr_range)
    {
        $base = explode('/', $cidr_range);
        $baseIp = ip2long($base[0]);
        if (!$baseIp) {
            // invalid ip provided
            return null;
        }
        if (count($base) === 1) {
            // not a masked range
            return [$baseIp, $baseIp];
        } else {
            // calculate mask and return
            $bitMask = (1 << (32 - $base[1])) - 1;
            return [$baseIp & ~$bitMask, $baseIp + $bitMask];
        }
    }
}

if (!defined('GEOSQLFACTORY_INCLUDED')) {
    if (php_sapi_name() === 'cli') {
        set_time_limit(5 * 60);
        $startedAt = microtime(true);
        printf("Started at %s\nProcessing ", date('H:i:s', $startedAt));

        // TODO: parse params from getopt
        $fabric = new GeoSqlFactory(__DIR__ . '/GeoLite2-Country-CSV/');
        $fabric->setLanguages(['en', 'ru']);
        $fabric->setCountries(['RU', 'BY', 'KZ']);
        $fabric->prepareDb(__DIR__ . '/geoip.sqlite');
        $fabric->fillGeonames();
        $fabric->fillBlocks();

        // report
        printf("\nDone in %.2f s\n\nPicked locations: %s\nAdded ranges: %s, total IP coverage: %s\n",
            microtime(true) - $startedAt,
            count($fabric->geonameTextMap),
            $fabric->ipRangeCounter,
            number_format($fabric->ipTotalCoverage)
        );
        printf("Memory footprint: %.2f Mb\n", memory_get_usage() / pow(2, 20));
    } else {
        echo 'Call this script from php cli or define GEOSQLFACTORY_INCLUDED.';
    }
}