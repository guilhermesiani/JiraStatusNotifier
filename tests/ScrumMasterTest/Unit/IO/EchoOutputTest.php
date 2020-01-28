<?php

namespace Chemaclass\ScrumMasterTests\Unit\IO;

use Chemaclass\ScrumMaster\IO\EchoOutput;
use PHPUnit\Framework\TestCase;

class EchoOutputTest extends TestCase
{
    /** @test */
    public function write(): void
    {
        $echoOutput = new EchoOutput();
        $echoOutput->write('foo');

        $this->expectOutputString('foo');
    }

    /** @test */
    public function writeln(): void
    {
        $echoOutput = new EchoOutput();
        $echoOutput->writeln('foo');

        $this->expectOutputString("foo\n");
    }
}