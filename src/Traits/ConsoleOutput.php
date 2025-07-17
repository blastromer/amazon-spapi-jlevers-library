<?php

namespace Typhoeus\JleversSpapi\Traits;

trait ConsoleOutput
{
    /**
     * Prepends string on line output
     * @param string $string
     * @param string|null $style
     * @param string|null $verbosity
     * @return void
     */
    public function line($string, $style = null, $verbosity = null)
    {
        parent::line($this->prepend($string), $style, $verbosity);
    }

    /**
     * Prepends string to output string
     * @param string $string
     * @return string
     */
    public function prepend($string)
    {
        if (method_exists($this, 'getPrependString')) {

            return $this->getPrependString($string) . $string;
        }

        return $string;
    }
}
