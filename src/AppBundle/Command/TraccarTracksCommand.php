<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\SqsCommand;
use Survos\Client\Resource\ObserveResource;
use Survos\Client\Resource\UserResource;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TraccarTracksCommand extends SqsCommand
{
    protected $name = 'traccar:tracks';
    protected $description = 'Import tracks from Traccar queue';
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
        $username = $inData->id;
        $userResource = new UserResource($this->client);
        $user = $userResource->getOneBy(['username' => $username]);
        if (!$user) {
            $this->output->writeln("<error>No such user: $username</error>");
            return false;
        }
        $timestamp = gmdate('Y-m-d\TH:i:s\Z', $inData->timestamp);
        $userAgent = $inData->user_agent;
        $app = ['name' => 'Traccar'];
        if (preg_match('{TraccarClient/([\d.]+)}', $userAgent, $m)) {
            $app['version'] = $m[1];
        }
        $device = ['uuid' => md5($username . '-traccar')]; // not sure what to use
        if (preg_match('{Android ([\d.]+); (\S+) Build}', $userAgent, $m)) {
            $device['platform'] = 'Android';
            $device['version'] = $m[1];
            $device['model'] = $m[2];
        }
        elseif (preg_match('{ Darwin/}', $userAgent)) {
            $device['platform'] = 'iOS';
        }
        $outData = [
            'tz' => $user['timezone_name'],
            'app' => $app,
            'device' => $device,
            'location' => [
                'uuid' => md5($username . $timestamp),
                'coords' => [
                    'latitude' => $inData->lat,
                    'longitude' => $inData->lon,
                    'speed' => $inData->speed,
                    'heading' => $inData->bearing,
                    // 'accuracy' => null,
                    'altitude' => $inData->altitude,
                ],
                'battery' => [
                    'level' => $inData->batt / 100,
                ],
                'timestamp' => $timestamp,
            ],
        ];
        if ($this->output->isVerbose()) {
            print json_encode($outData, JSON_PRETTY_PRINT) . "\n";
        }
        $observeResource = new ObserveResource($this->client);
        $response = $observeResource->postLocation($outData, $user['id']);
        if (isset($response['locations']) && $response['locations'] == 1) {
            $this->output->writeln('Point added');
            return true;
        }
        $this->output->writeln('<error>Error: ' . json_encode($response) . '</error>');
        return false;
    }
}
