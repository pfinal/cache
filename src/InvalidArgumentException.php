<?php

namespace PFinal\Cache;

class InvalidArgumentException extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException // PSR-16
{
}