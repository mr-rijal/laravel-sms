<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Exceptions;

use InvalidArgumentException;

class InvalidConfigurationException extends InvalidArgumentException implements SmsException {}
