<?php

namespace Starface;

trait StarfaceAwareTrait
{
    /** @var Starface */
    private $starface;

    public function setStarface(Starface $starface)
    {
        $this->starface = $starface;
        return $this;
    }

    public function getStarface()
    {
        return $this->starface;
    }
} 