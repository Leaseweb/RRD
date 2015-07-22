<?php

namespace RRD;

use RRD\Fetcher\Exception as RRDException;

/**
 * Class Fetcher
 *
 * 2 ways to fetch data:
 *
 * 1 - fluent API
 *      $f = new Fetcher ('database.rrd');
 *      $results = $f->start('-1d')->end('start+4h')->fetch(Fetcher::CF_AVERAGE);
 *
 * 2 - from arguments
 *      $results = $f->fetchFromArgs('AVERAGE', 300, '-1d', 'start+4h');
 *
 * @package RRD
 */
class Fetcher
{
    const CF_AVERAGE = 'AVERAGE';
    const CF_MIN = 'MIN';
    const CF_MAX = 'MAX';
    const CF_LAST = 'LAST';

    private $dbFilename;
    private $resolution;
    private $start;
    private $end;

    /** @var array $metricsToFetch */
    private $metricsToFetch = array();

    /**
     * @param $dbFilename
     * @throws \InvalidArgumentException
     */
    public function __construct($dbFilename)
    {
        if (!is_file($dbFilename) || !is_readable($dbFilename)) {
            throw new \InvalidArgumentException(sprintf("%s does not exist", basename($dbFilename)));
        }

        $this->dbFilename = $dbFilename;
    }


    /**
     * @param $res
     * @return $this
     */
    public function resolution($res)
    {
        $this->resolution = $res;

        return $this;
    }

    /**
     * Optionally we can specify which metrics we want to fetch
     *
     * @param array $metrics
     * @return $this
     */
    public function metrics(array $metrics)
    {
        $this->metricsToFetch = $metrics;

        return $this;
    }

    /**
     * @param $start
     * @return $this
     */
    public function start($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * @param $end
     * @return $this
     */
    public function end($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Shortcut function to get MAX value of all metrics
     * @return mixed
     */
    public function max()
    {
        return $this->fetch(self::CF_MAX);
    }

    /**
     * Shortcut function to get MIN value of all metrics
     * @return mixed
     */
    public function min()
    {
        return $this->fetch(self::CF_MIN);
    }

    /**
     * Shortcut function to get LAST value of all metrics
     * @return mixed
     */
    public function last()
    {
        return $this->fetch(self::CF_LAST);
    }

    /**
     * is the only required argument for fetching
     * @param $consolidationFunc
     * @return mixed
     */
    public function fetch($consolidationFunc)
    {
        return $this->fetchFromArgs(
            $consolidationFunc,
            $this->resolution,
            $this->start,
            $this->end
        );
    }

    /**
     * @param $consolidationFunc
     * @param string $resolution
     * @param string $start
     * @param string $end
     * @return mixed
     * @throws RRDException
     */
    public function fetchFromArgs($consolidationFunc, $resolution = '', $start = '', $end = '')
    {
        if (!in_array($consolidationFunc, array(self::CF_AVERAGE, self::CF_MAX, self::CF_MIN, self::CF_LAST))) {
            throw new \InvalidArgumentException(
                "Consolidation function not allowed. Possible values are: AVERAGE, MIN, MAX, LAST"
            );
        }

        $options = array($consolidationFunc);

        if ($resolution) {
            $options = array_merge($options, array('--resolution', $resolution));
        }

        if ($start) {
            $options = array_merge($options, array('--start', $start));
        }

        if ($end) {
            $options = array_merge($options, array('--end', $end));
        }

        $r = rrd_fetch($this->dbFilename, $options);

        if ($r === false) {
            throw new RRDException(rrd_error());
        }

        if ($r && $this->metricsToFetch) {
            $r['data'] = array_intersect_key(
                $r['data'],
                array_flip($this->metricsToFetch)
            );
        }

        return $r;
    }

    /**
     * For percentile calculation, it uses internally
     * AVERAGE consolidation function, so make sure that this function
     * is being applied to the stored data
     *
     * @param $percentileValue
     * @return int | array
     *
     * int   <= if $metric is especified
     * array (metric => percentileValue) if the metric is missing
     */
    public function percentile($metric = '', $percentileValue)
    {
        $results = $this->fetchFromArgs(
            self::CF_AVERAGE,
            $this->resolution,
            $this->start,
            $this->end
        );

        if ($metric) {

            if (!isset($results['data'][$metric])) {
                throw new \InvalidArgumentException(sprintf("metric %s passed is not being returned", $metric));
            }

            $result = $this->calcPercentile($results['data'][$metric], $percentileValue);
        } else {

            $result = array();
            foreach ($results['data'] as $metric => $data) {
                $result[$metric] = $this->calcPercentile($data, $percentileValue);
            }
        }

        return $result;
    }

    /**
     *
     * todo: test
     * @param array $records
     * @param $percentileValue
     *
     * @return double | false
     */
    private function calcPercentile(array $records, $percentileValue)
    {
        // Filter NAN values
        $filteredValues = array_filter($records, function ($record) {
            return !is_nan($record);
        });

        if (!$filteredValues) {
            return false;
        }

        sort($filteredValues);
        $percentileIndex = ($percentileValue * count($filteredValues)) / 100;

        if (floor($percentileIndex) == $percentileIndex) {
            $p = ($filteredValues[$percentileIndex - 1] + $filteredValues[$percentileIndex]) / 2;
        } else {

            $p = $filteredValues[$percentileIndex];
        }

        return $p;
    }
}
