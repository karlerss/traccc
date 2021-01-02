<?php


namespace karlerss\Traccc;


use Carbon\Carbon;

class Entry
{

    public int $id;
    public Carbon $start_at;
    public ?Carbon $finish_at;
    public ?string $message;

    public function __construct(array $props)
    {
        $this->setProps($props);
    }

    public function setProps(array $props)
    {

    }

    public static function getOpen(): ?self
    {
        return null;
    }
}