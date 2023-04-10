<?php


namespace Laravel\Foundation\Traits;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Log;
use Monolog\Utils;

trait Logger
{
    public function log($level, $message, $context = [])
    {
        $context = $this->formatData($context);
        $this->logger()->{$level}($message, $context);
    }

    private function formatData($data): array
    {
        return array_map(function ($item) {
            if (is_object($item)) {
                if ($item instanceof \JsonSerializable) {
                    $item = $item->jsonSerialize();
                }
                else {
                    if ($item instanceof Arrayable) {
                        $item = $item->toArray();
                    }
                    else {
                        if ($item instanceof \Throwable) {
                            $pies = [
                                get_class($item),
                                $item->getMessage() . ' at ' . $item->getFile() . ':' . $item->getLine(),
                                $item->getTraceAsString(),
                            ];
                            $item = implode(' ---->>> ', $pies);
                        }
                        else {
                            $item = Utils::jsonEncode($item, Utils::DEFAULT_JSON_FLAGS, true);
                        }
                    }
                }
            }

            if (is_array($item)) {
                $item = Utils::jsonEncode($item, Utils::DEFAULT_JSON_FLAGS, true);
            }

            return $item;
        },
            $data
        );
    }

    public function logger()
    {
        return Log::channel('graylog'); //logentries для syslog
    }

    public function formatLogMessage($message, ...$data): string
    {
        $data = $this->formatData($data);
        return sprintf($message, ...$data);
    }

    public function formatStackTrace(): array
    {
        $res = [];
        foreach (debug_backtrace() as $item) {
            unset($item['object'], $item['args']);
            $res[] = $item;
        }

        return $res;

    }
}
