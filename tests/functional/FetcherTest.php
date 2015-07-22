<?php

namespace RRD\Test;

use RRD\Fetcher as RRDFetcher;

class FetcherTest extends \PHPUnit_Framework_TestCase
{
    private $rrd_path;

    /** @var  RRDFetcher $rrd_fetcher */
    private $rrd_fetcher;

    /**
     * @var array $defaultOptions
     * Default rrd definition for this test
     * */
    private $defaultOptions = array(
        "--start", "-4 months",
        "--step", "300",
        "--no-overwrite",
        "DS:network_in:GAUGE:900:0:U",
        "DS:network_out:GAUGE:900:0:U",
        "RRA:MIN:0.5:1:288",
        "RRA:AVERAGE:0.5:1:288",
        "RRA:AVERAGE:0.5:12:168",
        "RRA:AVERAGE:0.5:228:365",
        "RRA:MAX:0.5:12:2184",
        "RRA:MAX:0.5:1:288"
    );

    public function setUp()
    {
        $this->rrd_path = dirname(__FILE__) . "/db.rrd";

        if (!rrd_create($this->rrd_path, $this->defaultOptions)) {
            throw new \Exception("RRD file could not be created in test" . __FILE__ . " Error:" . rrd_error());
        }

        $this->rrd_fetcher = new RRDFetcher($this->rrd_path);
    }

    public function tearDown()
    {
        unlink($this->rrd_path);
    }

    public function testFetchMetrics()
    {
        // todo: use dataProvider
        $expectedValues = $this->feedRRDWithMetrics();

        $metrics = $this->rrd_fetcher
            ->end('now')
            ->start('end-1h')
            ->max();

        $this->assertTrue(isset($metrics['data']));

        //$metrics['data'] = $this->filterNanValues($metrics['data']);

        $this->assertCount(count($expectedValues['network_in']), $metrics['data']['network_in']);
        $this->assertCount(count($expectedValues['network_out']), $metrics['data']['network_out']);

    }

    protected function feedRRDWithMetrics()
    {
        $now = time();

        $expectedValues = array(
            'network_in' => array(),
            'network_out' => array()
        );

        // Simulate last hour of data, with a step of 5 minutes
        for ($t = $now - (3600 * 1 * 1), $i = 1; $t <= $now; $t += 300, $i++) {
            $network_in = $i;
            $network_out = $i;

            $data = "$t:$network_in:$network_out";

            if (!rrd_update($this->rrd_path, array($data))) {
                throw new \Exception("RRD file could not be updated in test" . __FILE__ . " with data {$data}. Error:" . rrd_error());
            }

            $expectedValues['network_in'][] = $network_in;
            $expectedValues['network_out'][] =$network_out;
        }

        echo "Stored {$i} points in RRD";

        return $expectedValues;
    }

    private function filterNanValues(array & $records)
    {
        foreach ($records as $metric => & $data) {

            $data = array_filter($data, function ($record) {
                return !is_nan($record);
            });
        }

        return $records;
    }
}