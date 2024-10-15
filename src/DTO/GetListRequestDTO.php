<?php

namespace Laravel\Foundation\DTO;

use Laravel\Foundation\Abstracts\AbstractDto;

class GetListRequestDTO extends AbstractDto
{
    public int $page;
    public int $perPage;
    public array $sort;
    public array $filters = [];
    public array $queries = [];
    public array $select = ['*'];

    public string $q = '';
}