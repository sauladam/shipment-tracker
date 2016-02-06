<?php

class AdditionalInformationTest extends TestCase
{
    /** @test */
    public function it_adds_additional_information()
    {
        $testClass = new AdditionalTestClass;

        $testClass->addAdditionalDetails('key', 'data');

        $this->assertTrue($testClass->hasAdditionalDetails());
    }


    /** @test */
    public function it_retrieves_additional_information_by_key()
    {
        $testClass = new AdditionalTestClass;

        $testClass->addAdditionalDetails('foo', 'bar');

        $this->assertSame('bar', $testClass->getAdditionalDetails('foo'));
    }


    /** @test */
    public function it_gets_all_additional_information_if_no_key_is_provided()
    {
        $testClass = new AdditionalTestClass;

        $testClass->addAdditionalDetails('foo', 'bar');
        $testClass->addAdditionalDetails('bar', 'baz');

        $this->assertCount(2, $testClass->getAdditionalDetails());
    }


    /**
     * @test
     * @expectedException Exception
     */
    public function it_throws_an_exception_if_the_key_does_not_exist()
    {
        $testClass = new AdditionalTestClass;

        $testClass->getAdditionalDetails('non-existent');
    }
}


class AdditionalTestClass
{
    use \Sauladam\ShipmentTracker\Utils\AdditionalDetails;
}
