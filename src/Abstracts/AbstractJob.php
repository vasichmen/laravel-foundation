<?php

namespace Laravel\Foundation\Abstracts;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AbstractJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, Dispatchable;

    /**Полное название очереди, на которой выполняется джоба
     * @var string
     */
    public $queue;

    /**Данные, переданные в джобу при создании
     * @var array
     */
    protected array $data;

    /**Префикс названия очереди, зависящий от микросервиса
     * @var string
     */
    private string $serviceName;

    public function __construct(array $data)
    {
        $this->serviceName = config('queue.service_name', 'default');
        $this->queue = $this->serviceName . '-' . $this->queue;
        $this->data = $data;
    }

    /**Явное указание очереди для запуска конкретной джобы
     * @param string $queue краткое название очереди без префикса
     * @return $this
     */
    public function onQueue(string $queue)
    {
        $this->queue = $this->serviceName . '-' . $queue;

        return $this;
    }
}
