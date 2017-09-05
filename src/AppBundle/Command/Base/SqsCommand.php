<?php

namespace AppBundle\Command\Base;

use Aws\Credentials\Credentials;
use Aws\Result;
use Aws\Sqs\SqsClient;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use React\EventLoop\StreamSelectLoop;
use Survos\Client\SurvosClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Bunny\Channel;
use Bunny\Async\Client as BunnyClient;
use Bunny\Message;
use React\EventLoop\Factory;


abstract class SqsCommand extends BaseCommand
{
    /* @type SqsClient */
    protected $sqs;

    /** @var SurvosClient */
    protected $survosClient;

    /** @var StreamSelectLoop */
    protected $loop;

    protected function configure()
    {
        parent::configure();
        $this
            ->addArgument(
                'queue-name',
                InputArgument::REQUIRED,
                'SQS Queue Name'
            )
            ->addArgument(
                'queue-type',
                InputArgument::OPTIONAL,
                'Queue Type (rabbit or sqs)',
                'sqs'
            )
            ->addOption(
                'aws-key',
                null,
                InputOption::VALUE_REQUIRED,
                'SQS key (defaults to aws_key from parameters.yml)'
            )
            ->addOption(
                'aws-secret',
                null,
                InputOption::VALUE_REQUIRED,
                'SQS secret (defaults to aws_secret from parameters.yml)'
            )
            ->addOption('delete-bad', null, InputOption::VALUE_NONE, 'delete unprocessable messages')
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Limit messages read from the queue in one go',
                10
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // parent::initialize($input, $output);
        $this->input = $input;
        $this->output = $output;
        $container = $this->getContainer();

        if ($input->getArgument('queue-type') == 'sqs') {
            $credentials = new Credentials(
                $input->getOption('aws-key') ?: $container->getParameter('aws_key'),
                $input->getOption('aws-secret') ?: $container->getParameter('aws_secret')
            );
            $region = $input->hasOption('aws-region') ? $input->getOption('aws-region') : 'us-east-1';
            $this->sqs = new SqsClient(
                [
                    'credentials' => $credentials,
                    'region'      => $region,
                    'version'     => '2012-11-05',
                ]
            );
        } else {
            $this->loop = Factory::create();
        }


    }

    /** @var Promise[] */
    protected $promises = [];

    /**
     * Override this to do something other than (or in addition to) processing the queue
     *
     * @param InputInterface   $input
     * @param OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queues = explode(',', $input->getArgument('queue-name'));
        $queueType = $input->getArgument('queue-type');
                while (true) {
                    foreach ($queues as $queue) {
                        if (isset($this->promises[$queue]) && $this->isPromisePending($this->promises[$queue])) {
                            $this->output->writeln("Queue '{$queue}': pending");
                            continue;
                        }
                        $this->output->writeln("{$queueType} Queue '{$queue}': initiating");
                        $this->promises[$queue] = $queueType=='sqs' ? $this->processQueue($queue) : $this->processRabbitQueue($queue);
                    }
                    \GuzzleHttp\Promise\unwrap($this->promises);
                }
        return 0; // OK
    }

    private function isPromisePending(Promise $promise)
    {
        return $promise->getState() === PromiseInterface::PENDING;
    }

    /**
     * @param string $queueName
     * @return string
     */
    protected function getQueueUrl($queueName)
    {
        return preg_match('{^https?:}', $queueName) ? $queueName :
            $this->sqs->getQueueUrl(['QueueName' => $queueName])->get('QueueUrl');
    }

    /**
     * @param string $queueName
     * @return Promise
     */
    protected function processQueue($queueName)
    {
        $options = [
            'QueueUrl' => $this->getQueueUrl($queueName),
            'MaxNumberOfMessages' => $this->input->getOption('limit'),
            'WaitTimeSeconds' => 3,
        ];
        $promise = $this->sqs->receiveMessageAsync($options);
        $promise->then(function (Result $result) use ($queueName) {
            $this->output->writeln("Queue '{$queueName}': resolved!");
            $this->processSqsMessages($result, $queueName);
        }, function (\Exception $e) use ($queueName) {
            $this->output->writeln("Queue '{$queueName}': rejected! {$e->getMessage()}");
        });
        return $promise;
    }

    /**
     * @param string $queueName
     * @return Promise
     */
    protected function processRabbitQueue($queueName, $limit=3)
    {

        $rabbitOptions = [];
        // rabbit server credentials
        foreach (['host', 'port', 'user', 'password', 'vhost'] as $name) {
            $rabbitOptions[$name] = $this->getContainer()->getParameter("rabbitmq.$name");
        }

        $this->queueName = $queueName; // may be a problem if we process multiple queues with one script


        (new BunnyClient($this->loop, $rabbitOptions))
            ->connect()
            ->then(function (BunnyClient $client) {
            return $client->channel();
        })->then(function (Channel $channel) use ($limit) {
            return $channel->qos(0, $limit)->then(function () use ($channel) {
                return $channel;
            });
        })->then(function (Channel $channel) {

            return $channel->queueDeclare($this->queueName, false, true, false, false)->then(function () use ($channel) {
                return $channel;
            });
        })->then(function (Channel $channel) {
            echo sprintf(" [*] Waiting for messages on %s. To exit press CTRL+C\n", $this->queueName);
            $channel->consume(
                function (Message $message, Channel $channel, BunnyClient $client) {

                    $data = json_decode($message->content, true);
                    dump($data);
                    // not async like background, send message as array
                    $ok = $this->processMessage(
                        json_decode($data['Body'], true),
                        json_decode(json_encode($message), true));
                    if ($ok) {
                        $channel->ack($message);
                    } else {
                        // client script should handle errors, just requeue if bad
                        if ($this->input->getOption('delete-bad')) {
                            $this->output->writeln('(dropping message)', OutputInterface::VERBOSITY_VERBOSE);
                            $channel->reject($message, false); // don't requeue
                        } else {
                            $this->output->writeln('(requeuing message)', OutputInterface::VERBOSITY_VERBOSE);
                            // For some reason, requeue isn't working
                            // $channel->reject($message, true); // make sure we've done something, in case exception gets caught so we're still running
                            die(1);
                        }
                    }
                }
            );
        });
        $this->loop->run();

        /*
        $rabbitService = $this->getContainer()->get('survos.rabbitmq');
        $bgQueueInfo = $rabbitService->getQueueInfo($queueName);
        $this->output->writeln(sprintf('%s messages pending on %s', $bgQueueInfo['messages'], $queueName));

        $options = [
        ];
        $promise = $this->sqs->receiveMessageAsync($options);
        $promise->then(function (Result $result) use ($queueName) {
            $this->output->writeln("Queue '{$queueName}': resolved!");
            $this->processMessages($result, $queueName);
        }, function (\Exception $e) use ($queueName) {
            $this->output->writeln("Queue '{$queueName}': rejected! {$e->getMessage()}");
        });
        return $promise;
        */
    }

    /**
     * @param Result $result
     * @param string $queueName
     */
    protected function processSqsMessages(Result $result, $queueName)
    {
        $processed = 0;
        if (isset($result['Messages'])) {
            foreach ($result['Messages'] as $message) {
                try {
                    $ok = $this->processMessage(json_decode($message['Body'], true), $message);
                } catch (\Exception $e) {
                    $this->output->writeln("Queue '{$queueName}': Error! {$e->getMessage()}\n" . $e->getTraceAsString());
                    $ok = $this->input->getOption('delete-bad');
                }
                if ($ok) {
                    $this->deleteMessage($queueName, $message);
                    $processed++;
                }
            }
        }
        $this->output->writeln("Queue '{$queueName}': {$processed} messages processed");
    }

    /**
     * Process message. Return true if everything is OK, and message will be deleted from SQS in processQueue().
     * Return false if something goes wrong, and message will not be deleted.
     *
     * @param array $data
     * @param array $message
     * @return bool whether message was successfully processed
     */
    abstract protected function processMessage(array $data, array $message) : bool;

    /**
     * @param string $queueName
     * @param object|array|string $messageData
     * @return array
     */
    public function queue($queueName, $messageData)
    {
        $body = is_string($messageData) ? $messageData : json_encode($messageData);
        $sqsResponse = $this->sqs->sendMessage(
            [
                'QueueUrl'    => $this->getQueueUrl($queueName),
                'MessageBody' => $body,
            ]
        );
        if ($sqsResponse->hasKey('MessageId')) {
            $data = [
                'status'  => 'success',
                'message' => 'Message queued',
                'id'      => $sqsResponse->get('MessageId'),
            ];
        } else {
            $data = [
                'status'  => 'error',
                'message' => 'Failed to queue message',
            ];
        }

        return $data;
    }

    /**
     * @param string $queueName
     * @param string|array $receiptHandle (or message)
     * @return Result
     */
    public function deleteMessage($queueName, $receiptHandle)
    {
        if (!is_string($receiptHandle)) {
            $receiptHandle = $receiptHandle['ReceiptHandle'];
        }
        return $this->sqs->deleteMessage(
            [
                'QueueUrl'      => $this->getQueueUrl($queueName),
                'ReceiptHandle' => $receiptHandle,
            ]
        );
    }

    /**
     * @param $apiUrl
     * @param $accessToken
     * @return bool|SurvosClient
     * @throws \Exception
     */
    protected function getClient($apiUrl, $accessToken)
    {
        $client = new SurvosClient($apiUrl);
        if (!$client->authByToken($accessToken)) {
            $this->output->writeln(sprintf('Response status: %d', $client->getLastResponseStatus()));
            $this->output->writeln(sprintf('Response data: %s', $client->getLastResponseData()));
            throw new \Exception("Can't log in. ApiUrl: '{$apiUrl}', token: '{$accessToken}'");
        }
        $this->output->writeln(sprintf('Logged in under "%s" against "%s"', $client->getLoggedUser()['username'], $apiUrl));
        return $client;
    }

}
