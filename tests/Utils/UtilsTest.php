<?php

class UtilsTest extends TestCase
{
    /** @test */
    public function it_encodes_a_string_as_utf8_if_necessary()
    {
        $string = utf8_decode('motörheád');

        $this->assertFalse(mb_check_encoding($string, 'utf-8'));

        $string = \Sauladam\ShipmentTracker\Utils\Utils::ensureUtf8($string);

        $this->assertTrue(mb_check_encoding($string, 'utf-8'));
    }


    /** @test */
    public function it_encodes_an_array_of_strings()
    {
        $strings = [
            utf8_decode('motörheád-1'),
            utf8_decode('motörheád-2'),
            utf8_decode('motörheád-2'),
        ];

        foreach ($strings as $string) {
            $this->assertFalse(mb_check_encoding($string, 'utf-8'));
        }

        $strings = \Sauladam\ShipmentTracker\Utils\Utils::ensureUtf8($strings);

        foreach ($strings as $string) {
            $this->assertTrue(mb_check_encoding($string, 'utf-8'));
        }
    }
}
