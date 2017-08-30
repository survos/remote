<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\SqsCommand;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ObserveResource;
use Survos\Client\Resource\UserResource;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MemberApproveCommand extends SqsCommand
{
    protected $name = 'member:approve';
    protected $description = 'Approve and reject members from queue';
    protected $help = 'Reads from an SQS queue';

    /**
     * @param InputInterface   $input
     * @param OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        while (true) {
            $processed = $this->processQueue(
                $input->getArgument('queue-name')
            );
            $this->output->writeln("$processed messages processed");
        }
        return 0; // OK
    }

    protected function processMessage(array $inData, array $message) : bool
    {
        $inData = (object) $inData;
        if ($this->output->isVerbose()) {
            print json_encode($inData, JSON_PRETTY_PRINT) . "\n";
        }
        $memberId = $inData->member_id;
        $action = $inData->action;
        $memberResource = new MemberResource($this->client);
        $response = $memberResource->setApplicantsStatus($memberId, $action);
        if ($this->output->isVerbose()) {
            print json_encode($response, JSON_PRETTY_PRINT) . "\n";
        }
        return true;
    }
}
