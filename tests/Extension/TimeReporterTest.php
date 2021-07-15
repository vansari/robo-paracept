<?php

namespace Tests\Codeception\Task\Extension;

use Codeception\Event\TestEvent;
use Codeception\Task\Extension\TimeReporter;
use PHPUnit\Framework\TestCase;

/**
 * Class TimeReporterTest
 * @coversDefaultClass \Codeception\Task\Extension\TimeReporter
 */
class TimeReporterTest extends TestCase
{

    /**
     * @covers ::after
     * @covers ::endRun
     */
    public function testAfterAndEndRun(): void
    {
        $eventTests = [
            ['testname' => 'tests/acceptance/bar/baz.php:testA', 'time' => 10,],
            ['testname' => 'tests/acceptance/bar/baz.php:testA', 'time' => 50,], // rerun
            ['testname' => 'tests/acceptance/bar/baz.php:testB', 'time' => 100,],
            ['testname' => 'tests/acceptance/bar/baz.php:testC', 'time' => 50,],
            ['testname' => 'tests/acceptance/bar/baz.php:testD', 'time' => 33,],
            ['testname' => 'tests/acceptance/bar/baz.php:testD', 'time' => 50,], // rerun
            ['testname' => 'tests/acceptance/bar/baz.php:testE', 'time' => 66,],
            ['testname' => 'tests/acceptance/bar/baz.php:testF', 'time' => 90,],
            ['testname' => 'tests/acceptance/bar/baz.php:testG', 'time' => 100,],
            ['testname' => 'tests/acceptance/bar/baz.php:testG', 'time' => 13,], //rerun
            ['testname' => 'tests/acceptance/bar/baz.php:testH', 'time' => 50,],
        ];

        $expected = [
            'tests/acceptance/bar/baz.php:testA' => 60,
            'tests/acceptance/bar/baz.php:testB' => 100,
            'tests/acceptance/bar/baz.php:testC' => 50,
            'tests/acceptance/bar/baz.php:testD' => 83,
            'tests/acceptance/bar/baz.php:testE' => 66,
            'tests/acceptance/bar/baz.php:testF' => 90,
            'tests/acceptance/bar/baz.php:testG' => 113,
            'tests/acceptance/bar/baz.php:testH' => 50,
        ];

        $reporter = $this->getMockBuilder(TimeReporter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTestname', 'getLogDir'])
            ->getMock();
        $reporter->method('getLogDir')->willReturn(TEST_PATH . '/result/');

        // prepare Mocks for Test
        $testEvents = [];
        foreach ($eventTests as $test) {
            $eventMock = $this->getMockBuilder(TestEvent::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getTime'])
                ->getMock();

            $eventMock->method('getTime')->willReturn($test['time']);
            $testEvents[] = [
                'mock' => $eventMock,
                'testname' => $test['testname']
            ];
        }

        // get Testname by the TestEventMock
        $reporter
            ->method('getTestname')
            ->withConsecutive(
                ...array_map(
                    static function (TestEvent $event): array {
                        return [$event];
                    },
                    array_column($testEvents, 'mock')
                )
            )
            ->willReturnOnConsecutiveCalls(...array_column($testEvents, 'testname'));

        // fill timeList with the mocked Events
        foreach ($testEvents as $testEvent) {
            $reporter->after($testEvent['mock']);
        }

        $reporter->endRun();
        $reportFile = TEST_PATH . '/result/timeReport.json';
        $this->assertFileExists($reportFile);

        $lines = json_decode(file_get_contents($reportFile), true);
        $this->assertCount(count($expected), $lines);
        foreach ($expected as $test => $time) {
            $this->assertContains($test, array_keys($lines), $test . ' does not exists in file.');
            $this->assertSame($time, $lines[$test], 'Calculated time does not match the expected.');
        }
    }
}
