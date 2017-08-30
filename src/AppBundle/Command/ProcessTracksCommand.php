<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\SqsCommand;

class ProcessTracksCommand extends SqsCommand
{
    protected $name = 'process:tracks';
    protected $description = 'Process a queue dedicated to tracks';
    protected $help = 'Reads from an SQS queue';

    protected function processMessage(array $data, array $message) : bool
    {
        return false;
    }
}
