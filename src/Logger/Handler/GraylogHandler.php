<?php


namespace Laravel\Foundation\Logger\Handler;

use Gelf\Publisher;
use Gelf\Transport\TcpTransport;
use Gelf\Transport\UdpTransport;
use Illuminate\Support\Facades\Log;
use Laravel\Foundation\Logger\Formatter\GraylogFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\GelfHandler;

class GraylogHandler extends GelfHandler
{
    public function __construct($config = [])
    {
        if (empty($config)) {
            $config = config('logging.channels.graylog.with');
        }

        try {
            if ('UDP' === strtoupper($config['type'])) {
                $transport = new UdpTransport(
                    $config['host'],
                    $config['port'],
                    $config['size']
                );
            }
            elseif ('TCP' === strtoupper($config['type'])) {
                $transport = new TcpTransport(
                    $config['host'],
                    $config['port']
                );
            }
            else {
                throw new \DomainException('Invalid Graylog Transport, should be set to TCP or UDP.');
            }

            $publisher = new Publisher();
            $publisher->addTransport($transport);

            parent::__construct($publisher);
        }
        catch (\Exception|\DomainException $e) {
            Log::channel('stack')->critical($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        try {
            $this->publisher->publish($record['formatted']);
        }
        catch (\RuntimeException $exception) {
            Log::channel('stack')->critical($exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new GraylogFormatter();
    }
}