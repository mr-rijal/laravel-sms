<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Exceptions;

use InvalidArgumentException;

class InvalidMessageException extends InvalidArgumentException implements SmsException {}
