<?php

namespace Icinga\Module\Vspheredb;

use dipl\Html\Error as HtmlError;
use dipl\Html\HtmlDocument;
use Error;
use Exception;

class WebUtil
{
    public static function runFailSafe($callback, HtmlDocument $parent)
    {
        try {
            $callback();
        } catch (Exception $e) {
            $parent->add(HtmlError::show($e));
        } catch (Error $e) {
            $parent->add(HtmlError::show($e));
        }
    }
}
