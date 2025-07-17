<?php

namespace Typhoeus\JleversSpapi\Traits;

trait TimeStamp {

    /**
     * Prepends timestamp to output string
     * @param string $string
     * @return string
     */
    protected function getPrependString($string) {

        return date(property_exists($this, 'outputTimestampFormat') ? $this->outputTimestampFormat : '[Y-m-d H:i:s]') . ' ';
    }
}
