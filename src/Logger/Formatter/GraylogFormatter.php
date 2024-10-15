<?php


namespace Laravel\Foundation\Logger\Formatter;

use Monolog\Formatter\GelfMessageFormatter;

class GraylogFormatter extends GelfMessageFormatter
{
    public function __construct(?string $systemName = null, ?string $extraPrefix = null, string $contextPrefix = 'ctxt_', ?int $maxLength = null)
    {
        $config = config('logging.channels.graylog.formatter_with');

        if (!empty($config['maxLength'])) {
            $maxLength = $config['maxLength'];
        }
        if (!empty($config['systemName'])) {
            $systemName = $config['systemName'];
        }

        if (!empty($config['extraPrefix'])) {
            $extraPrefix = $config['extraPrefix'];
        }

        if (!empty($config['contextPrefix'])) {
            $contextPrefix = $config['contextPrefix'];
        }

        parent::__construct($systemName, $extraPrefix, $contextPrefix, $maxLength);
    }
}