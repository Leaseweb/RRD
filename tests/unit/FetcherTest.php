<?php

namespace RRD\Test\Unit;

use RRD\Fetcher;


/**
 * Class FetcherTest
 *
 * @package RRD\Test
 */
class FetcherTest extends \PHPUnit_Framework_TestCase
{

    /** @var  Fetcher $fetcher */
    protected $fetcher;

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testThrowsExceptionIfRRDFileNotReachable()
    {
        $this->fetcher = new Fetcher("some-missing-file.rrd");
    }

}