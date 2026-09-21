<?php
/**
 * stdClass that behaves like a JS object in templates: reading an unknown
 * property yields null instead of raising a warning (EJS did the same with
 * `undefined`). Used by View::normalize() and for every DB row.
 */
class NhObj extends stdClass
{
    public function __get(string $name)
    {
        return null;
    }

    public function __isset(string $name): bool
    {
        return false;
    }
}
