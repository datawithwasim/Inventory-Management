<?php
declare(strict_types=1);

namespace App\Models;

/** A stock rule was broken (not enough stock, bad batch...). The message is safe to show the user. */
final class StockException extends \RuntimeException
{
}
