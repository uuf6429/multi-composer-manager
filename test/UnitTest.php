<?php

namespace uuf6429\MultiComposerManagerTest;

use PHPUnit\Framework\TestCase;
use uuf6429\MultiComposerManager\MCM;

class UnitTest extends TestCase
{
    public function testInstall()
    {
        $mock = $this->getMcmMock([], [['install', []]]);
        $mock->install();
    }

    public function testUpdateAll()
    {
        $mock = $this->getMcmMock([], [['update', []]]);
        $mock->update();
    }

    public function testUpdateNamed()
    {
        $mock = $this->getMcmMock([], [['update', ['psr/log', 'symfony/console']]]);
        $mock->update(['psr/log', 'symfony/console']);
    }

    /**
     * @param array $expectedSaveConfigCalls
     * @param array $expectedComposerExecCalls
     * @return \PHPUnit_Framework_MockObject_MockObject|MCM
     */
    private function getMcmMock(
        array $expectedSaveConfigCalls,
        array $expectedComposerExecCalls
    ) {
        $mock = $this->getMockBuilder(MCM::class)
            ->setConstructorArgs(['/root/'])
            ->setMethods(['saveConfig', 'composerExec'])
            ->getMock();

        $mock->expects($this->exactly(count($expectedSaveConfigCalls)))
             ->method('saveConfig')
             ->willReturnCallback(
                 function () use ($expectedSaveConfigCalls) {
                     $actual   = func_get_args();
                     $expected = array_shift($expectedSaveConfigCalls);
                     $this->assertEquals($expected, $actual);
                 }
             );

        $mock->expects($this->exactly(count($expectedComposerExecCalls)))
             ->method('composerExec')
             ->willReturnCallback(
                 function () use ($expectedComposerExecCalls) {
                     $actual   = func_get_args();
                     $expected = array_shift($expectedComposerExecCalls);
                     $this->assertEquals($expected, $actual);
                 }
             );

        return $mock;
    }
}
