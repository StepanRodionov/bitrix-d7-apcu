<?php

namespace SR\D7Cache\Utils;

class Serializer
{
    public function serialize($val)
    {
        return \serialize($val);
    }

    public function unserialize($val)
    {
        return \unserialize($val);
    }
}